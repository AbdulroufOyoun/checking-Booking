<?php

namespace App\Http\Controllers;

use App\Http\Requests\Earning\AllEarningsRequest;
use App\Http\Requests\Earning\EarningsListRequest;
use App\Http\Requests\Earning\EarningsSummaryRequest;
use Illuminate\Support\Facades\DB;
use App\Models\ReservationPay;
use Carbon\Carbon;

class EarningController extends Controller
{
    /**
     * API 1: Summary of all earnings (in/out totals, counts, rows) from date to date with comparison
     */
    public function allEarnings(AllEarningsRequest $request)
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $current = $this->getEarningsSummary($startDate, $endDate);

        $comparison = null;
        if ($request->filled('compare_start_date') && $request->filled('compare_end_date')) {
            $compStart = Carbon::parse($request->compare_start_date);
            $compEnd = Carbon::parse($request->compare_end_date);
            $comparison = $this->getEarningsSummary($compStart, $compEnd);
        }

        return SuccessData('Earnings data retrieved successfully', [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'current' => $current,
            'comparison' => $comparison,
        ]);
    }

    /**
     * API 2: Paginated earnings list (all/payments/refunds) from date to date
     */
    public function earningsList(EarningsListRequest $request)
    {
        $query = ReservationPay::select('reservation_pay.*', 'reservations.start_date as res_start', 'reservations.client_id')
            ->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->where('reservations.reservation_status', 1);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $query->whereBetween('reservations.start_date', [$startDate, $endDate]);
        }

        if ($request->type === 'in') {
            $query->where('reservation_pay.type', ReservationPay::TYPE_PAYMENT);
        } elseif ($request->type === 'out') {
            $query->where('reservation_pay.type', ReservationPay::TYPE_REFUND);
        }

        $query->orderBy('reservation_pay.created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $items = $query->paginate($perPage, ['*'], 'page', $page);

        return Pagination($items);
    }

    /**
     * API 3: Summary only (total, rows, in/out counts) from date to date
     */
    public function earningsSummary(EarningsSummaryRequest $request)
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $summary = $this->getEarningsSummary($startDate, $endDate);

        return SuccessData('Earnings summary retrieved successfully', $summary);
    }

    /**
     * API 4: Paginated payments only (in)
     */
    public function payments(EarningsListRequest $request)
    {
        $request->merge(['type' => 'in']);
        return $this->earningsList($request);
    }

    /**
     * API 5: Paginated refunds only (out)
     */
    public function refunds(EarningsListRequest $request)
    {
        $request->merge(['type' => 'out']);
        return $this->earningsList($request);
    }

    /**
     * Helper: Get summary stats for period
     */
    private function getEarningsSummary(Carbon $startDate, Carbon $endDate)
    {
        $payments = ReservationPay::join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->where('reservations.reservation_status', 1)
            ->whereBetween('reservations.start_date', [$startDate, $endDate])
            ->where('reservation_pay.type', ReservationPay::TYPE_PAYMENT)
            ->selectRaw('SUM(pay) as total_in, COUNT(*) as count_in')
            ->first();

        $refunds = DB::table('reservation_pay')
            ->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->where('reservations.reservation_status', 1)
            ->whereBetween('reservations.start_date', [$startDate, $endDate])
            ->where('reservation_pay.type', ReservationPay::TYPE_REFUND)
            ->selectRaw('SUM(pay) as total_out, COUNT(*) as count_out')
            ->first();

        $totalRows = DB::table('reservation_pay')
            ->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->where('reservations.reservation_status', 1)
            ->whereBetween('reservations.start_date', [$startDate, $endDate])
            ->count();

        return [
            'total_in' => $payments->total_in ?? 0,
            'total_out' => $refunds->total_out ?? 0,
            'net_earnings' => $payments->total_in ?? 0 - ($refunds->total_out ?? 0),
            'count_in' => $payments->count_in ?? 0,
            'count_out' => $refunds->count_out ?? 0,
            'total_rows' =>  $totalRows,
        ];
    }
}
