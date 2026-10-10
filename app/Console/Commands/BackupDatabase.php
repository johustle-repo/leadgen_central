<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Writes a gzipped SQL dump of the default database using PDO only.
 *
 * The production host disables proc_open/exec, so mysqldump cannot be used.
 * The dump recreates every table and its rows and can be imported through
 * phpMyAdmin or the mysql client to restore the database.
 */
#[Signature('app:backup-database')]
#[Description('Back up the database to a compressed SQL file and remove expired backups')]
class BackupDatabase extends Command
{
    /**
     * Tables whose structure is backed up but whose rows are disposable.
     *
     * @var list<string>
     */
    private const array STRUCTURE_ONLY_TABLES = ['cache', 'cache_locks', 'sessions'];

    private const int ROWS_PER_INSERT = 100;

    private const string COMPLETION_MARKER = '-- LeadGen Central backup completed';

    public function handle(): int
    {
        $connection = DB::connection();
        $disk = Storage::disk((string) config('leadgen.backup.disk'));
        $directory = trim((string) config('leadgen.backup.path'), '/');
        $fileName = 'leadgen-'.now()->format('Y-m-d-His').'.sql.gz';
        $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'leadgen-backup-');

        try {
            $this->writeDump($connection, $temporaryPath);
            $this->verifyDump($temporaryPath);

            $this->storeDump($disk, $temporaryPath, "{$directory}/{$fileName}");
        } catch (Throwable $exception) {
            Log::error('Database backup failed.', ['exception' => $exception]);
            $this->components->error("Database backup failed: {$exception->getMessage()}");

            return self::FAILURE;
        } finally {
            @unlink($temporaryPath);
        }

        $removed = $this->removeExpiredBackups($directory, $fileName);

        $this->components->info("Database backed up to {$directory}/{$fileName}. Removed {$removed} expired backup(s).");

        return self::SUCCESS;
    }

    private function writeDump(Connection $connection, string $path): void
    {
        $file = gzopen($path, 'wb6');

        if ($file === false) {
            throw new RuntimeException('Unable to open the temporary backup file.');
        }

        $isMySql = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);

        // Reading inside one transaction gives InnoDB a consistent snapshot, so
        // rows written while the backup runs never leave related tables out of step.
        $connection->beginTransaction();

        try {
            gzwrite($file, '-- LeadGen Central database backup '.now()->toIso8601String()."\n");
            gzwrite($file, $isMySql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" : "PRAGMA foreign_keys=OFF;\n\n");

            foreach ($this->tableNames($connection, $isMySql) as $table) {
                gzwrite($file, $this->tableStructure($connection, $table, $isMySql));

                if (! in_array($table, self::STRUCTURE_ONLY_TABLES, true)) {
                    $this->writeRows($connection, $file, $table);
                }
            }

            gzwrite($file, ($isMySql ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n").self::COMPLETION_MARKER."\n");
        } finally {
            $connection->rollBack();
            gzclose($file);
        }
    }

    /**
     * @return list<string>
     */
    private function tableNames(Connection $connection, bool $isMySql): array
    {
        $tables = $connection->getSchemaBuilder()->getTables($isMySql ? $connection->getDatabaseName() : null);

        return array_values(array_filter(
            array_column($tables, 'name'),
            fn (string $name): bool => ! str_starts_with($name, 'sqlite_'),
        ));
    }

    private function tableStructure(Connection $connection, string $table, bool $isMySql): string
    {
        $quotedTable = $connection->getQueryGrammar()->wrapTable($table);

        if ($isMySql) {
            $create = (array) $connection->selectOne("SHOW CREATE TABLE {$quotedTable}");
            $statements = [$create['Create Table']];
        } else {
            $statements = $connection->table('sqlite_master')
                ->where('tbl_name', $table)
                ->whereNotNull('sql')
                ->orderByRaw("type = 'table' desc")
                ->pluck('sql')
                ->all();
        }

        return "DROP TABLE IF EXISTS {$quotedTable};\n".implode(";\n", $statements).";\n\n";
    }

    /**
     * @param  resource  $file
     */
    private function writeRows(Connection $connection, $file, string $table): void
    {
        $columns = $connection->getSchemaBuilder()->getColumnListing($table);
        $grammar = $connection->getQueryGrammar();
        $prefix = 'INSERT INTO '.$grammar->wrapTable($table).' ('.$grammar->columnize($columns).") VALUES\n";

        $query = $connection->table($table);

        if (in_array('id', $columns, true)) {
            $rows = $query->lazyById(500, 'id');
        } else {
            foreach ($columns as $column) {
                $query->orderBy($column);
            }

            $rows = $query->lazy(500);
        }

        foreach ($rows->chunk(self::ROWS_PER_INSERT) as $chunk) {
            $values = $chunk->map(fn (object $row): string => '('.implode(', ', array_map(
                fn (string $column): string => $this->sqlValue($connection, $row->{$column}),
                $columns,
            )).')');

            gzwrite($file, $prefix.$values->implode(",\n").";\n");
        }

        gzwrite($file, "\n");
    }

    private function sqlValue(Connection $connection, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            mb_check_encoding((string) $value, 'UTF-8') => $connection->getPdo()->quote((string) $value),
            default => "X'".bin2hex((string) $value)."'",
        };
    }

    /**
     * Reads the whole archive back so a truncated or corrupt file is never kept as a backup.
     */
    private function verifyDump(string $path): void
    {
        $file = gzopen($path, 'rb');
        $tail = '';

        while ($file !== false && ! gzeof($file)) {
            $chunk = gzread($file, 1024 * 1024);

            if ($chunk === false) {
                break;
            }

            $tail = substr($tail.$chunk, -200);
        }

        $file !== false && gzclose($file);

        if (! str_contains($tail, self::COMPLETION_MARKER)) {
            throw new RuntimeException('The backup file is incomplete.');
        }
    }

    private function storeDump(Filesystem $disk, string $temporaryPath, string $destination): void
    {
        $stream = fopen($temporaryPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read the temporary backup file.');
        }

        try {
            if (! $disk->writeStream($destination, $stream)) {
                throw new RuntimeException("Unable to store the backup at {$destination}.");
            }
        } finally {
            fclose($stream);
        }
    }

    private function removeExpiredBackups(string $directory, string $currentFileName): int
    {
        $disk = Storage::disk((string) config('leadgen.backup.disk'));
        $cutoff = now()->subDays((int) config('leadgen.backup.keep_days'))->getTimestamp();

        $expired = collect($disk->files($directory))
            ->filter(fn (string $path): bool => preg_match('/^leadgen-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/', basename($path)) === 1)
            ->reject(fn (string $path): bool => basename($path) === $currentFileName)
            ->filter(fn (string $path): bool => $disk->lastModified($path) < $cutoff)
            ->values();

        $expired->each(fn (string $path): bool => $disk->delete($path));

        return $expired->count();
    }
}
