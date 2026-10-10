<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Locates the database backup files written by app:backup-database.
 */
class DatabaseBackupStore
{
    public const string FILE_PATTERN = '/^leadgen-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/';

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('leadgen.backup.disk'));
    }

    public function directory(): string
    {
        return trim((string) config('leadgen.backup.path'), '/');
    }

    public function path(string $fileName): string
    {
        return "{$this->directory()}/{$fileName}";
    }

    public function isBackupFileName(string $fileName): bool
    {
        return preg_match(self::FILE_PATTERN, $fileName) === 1;
    }

    public function exists(string $fileName): bool
    {
        return $this->isBackupFileName($fileName) && $this->disk()->exists($this->path($fileName));
    }

    /**
     * @return array<int, array{name: string, size: int, created_at: string}>
     */
    public function all(): array
    {
        $disk = $this->disk();

        return collect($disk->files($this->directory()))
            ->filter(fn (string $path): bool => $this->isBackupFileName(basename($path)))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => $disk->size($path),
                'created_at' => now()->setTimestamp($disk->lastModified($path))->toIso8601String(),
            ])
            ->sortByDesc('name')
            ->values()
            ->all();
    }
}
