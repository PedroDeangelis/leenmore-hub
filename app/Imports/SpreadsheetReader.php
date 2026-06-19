<?php

namespace App\Imports;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a CSV or XLSX file (first sheet) into rows of plain strings, so callers
 * never deal with openspout's typed cells. Streams via a generator.
 */
class SpreadsheetReader
{
    /**
     * Yield each row as a list of stringified cell values. Pass $extension when
     * the path has none (e.g. a temp upload) so the format is detected correctly.
     *
     * @return iterable<int, array<int, string>>
     */
    public function rows(string $path, ?string $extension = null): iterable
    {
        $reader = $this->readerFor($extension ?? pathinfo($path, PATHINFO_EXTENSION));
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield array_map($this->stringify(...), $row->toArray());
                }

                break; // first sheet only
            }
        } finally {
            $reader->close();
        }
    }

    private function readerFor(string $extension): ReaderInterface
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => new CsvReader,
            default => new XlsxReader,
        };
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && floor($value) === $value => (string) (int) $value,
            default => trim((string) $value),
        };
    }
}
