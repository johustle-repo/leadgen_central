<?php

namespace App\Services;

class CsvDelimiterDetector
{
    /** @var list<string> */
    private const CANDIDATES = [',', "\t", ';'];

    /**
     * Reads the stream's current line to guess whether it's comma, tab, or
     * semicolon separated, then rewinds to where it started. Exports from
     * some tools (e.g. Excel's "Save as Unicode Text") are tab-separated;
     * assuming a comma always would misread the whole header row as a
     * single unmappable column.
     *
     * @param  resource  $stream
     */
    public function detect($stream): string
    {
        $position = ftell($stream);
        $line = fgets($stream);
        fseek($stream, $position === false ? 0 : $position);

        if ($line === false) {
            return ',';
        }

        $counts = array_map(fn (string $candidate): int => substr_count($line, $candidate), self::CANDIDATES);
        $best = array_search(max($counts), $counts, true);

        return $counts[$best] > 0 ? self::CANDIDATES[$best] : ',';
    }
}
