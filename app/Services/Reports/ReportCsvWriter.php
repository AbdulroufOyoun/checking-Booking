<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Storage;

class ReportCsvWriter
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function write(string $relativePath, array $columns, array $rows): int
    {
        Storage::disk('local')->makeDirectory('reports');

        $fullPath = Storage::disk('local')->path($relativePath);
        $handle = fopen($fullPath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Unable to create report export file.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, array_map(fn ($col) => $col['label'], $columns));

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $value = $row[$column['key']] ?? '';
                $line[] = $value === null ? '' : (is_scalar($value) ? $value : json_encode($value));
            }
            fputcsv($handle, $line);
        }

        fclose($handle);

        return count($rows);
    }
}
