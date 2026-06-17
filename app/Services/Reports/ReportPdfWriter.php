<?php

namespace App\Services\Reports;

use App\Models\ReportExport;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class ReportPdfWriter
{
    /**
     * @param  array{columns?: array, rows?: array, summary?: array, meta_lines?: array}  $data
     */
    public function write(string $relativePath, ReportExport $export, array $data): int
    {
        Storage::disk('local')->makeDirectory('reports');

        $columns = $data['columns'] ?? [];
        $rows = $data['rows'] ?? [];
        $landscape = count($columns) > 6 || count($rows) > 40;

        $html = view('reports.export-pdf', [
            'export' => $export,
            'title' => str_replace('-', ' ', $export->slug),
            'periodLabel' => $this->periodLabel($export),
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $data['summary'] ?? [],
            'metaLines' => $data['meta_lines'] ?? [],
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', $landscape ? 'landscape' : 'portrait');
        $dompdf->render();

        Storage::disk('local')->put($relativePath, $dompdf->output());

        return count($rows);
    }

    private function periodLabel(ReportExport $export): string
    {
        if ($export->slug === 'room-board') {
            return $export->end_date?->toDateString() ?? '—';
        }

        $start = $export->start_date?->toDateString() ?? '—';
        $end = $export->end_date?->toDateString() ?? '—';

        return "{$start} → {$end}";
    }
}
