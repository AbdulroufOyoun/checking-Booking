<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportQueryService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportQueryService $reportQueryService)
    {
    }

    public function catalog()
    {
        $items = [
            ['slug' => 'overview', 'category' => 'overview'],
            ['slug' => 'room-board', 'category' => 'operational'],
            ['slug' => 'arrivals-departures', 'category' => 'operational'],
            ['slug' => 'reservations-list', 'category' => 'operational'],
            ['slug' => 'occupancy', 'category' => 'operational'],
            ['slug' => 'accrual-revenue', 'category' => 'financial'],
            ['slug' => 'cash-box', 'category' => 'financial'],
            ['slug' => 'revenue-summary', 'category' => 'financial'],
            ['slug' => 'accrual-cash-reconciliation', 'category' => 'financial'],
            ['slug' => 'ar-aging', 'category' => 'financial'],
            ['slug' => 'adjustments', 'category' => 'financial'],
            ['slug' => 'tax', 'category' => 'financial'],
            ['slug' => 'revpar', 'category' => 'financial'],
            ['slug' => 'by-dimension', 'category' => 'financial'],
            ['slug' => 'peak-analysis', 'category' => 'financial'],
            ['slug' => 'payments-refunds', 'category' => 'financial'],
            ['slug' => 'closing-package', 'category' => 'financial'],
            ['slug' => 'chart-of-accounts', 'category' => 'accounting'],
            ['slug' => 'journal-entries', 'category' => 'accounting'],
            ['slug' => 'general-ledger', 'category' => 'accounting'],
            ['slug' => 'trial-balance', 'category' => 'accounting'],
            ['slug' => 'balance-sheet', 'category' => 'accounting'],
            ['slug' => 'cash-flow', 'category' => 'accounting'],
            ['slug' => 'financial-audit-log', 'category' => 'accounting'],
        ];

        return SuccessData('Report catalog', ['reports' => $items]);
    }

    public function run(string $slug, Request $request)
    {
        try {
            $data = $this->reportQueryService->run($slug, $request->all());

            $perPage = max(1, min(50, (int) config('reports.page_size', 50)));
            $page = max(1, (int) $request->input('page', 1));
            $allRows = $data['rows'] ?? [];
            $totalRows = count($allRows);
            $lastPage = max(1, (int) ceil($totalRows / $perPage));
            $page = min($page, $lastPage);
            $offset = ($page - 1) * $perPage;

            $data['rows'] = array_slice($allRows, $offset, $perPage);
            $data['total_rows'] = $totalRows;
            $data['per_page'] = $perPage;
            $data['current_page'] = $page;
            $data['last_page'] = $lastPage;
            $data['preview_limit'] = $perPage;
            $data['is_truncated'] = $totalRows > $perPage;

            return SuccessData('Report generated', $data);
        } catch (\InvalidArgumentException $e) {
            return Failed($e->getMessage(), 422);
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }
}
