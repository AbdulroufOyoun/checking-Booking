<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationPay;
use App\Services\RevenueAccrualService;
use App\Services\RoomOccupancyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private RoomOccupancyService $occupancyService,
        private RevenueAccrualService $revenueAccrualService
    ) {
    }

    public function summary()
    {
        try {
            $today = Carbon::today();
            $monthStart = $today->copy()->startOfMonth();
            $monthEnd = $today->copy()->endOfMonth();

            $occupancy = $this->occupancyService->summaryForDate($today);

            $monthRevenue = $this->revenueAccrualService->calculate(
                'total',
                null,
                $monthStart,
                $monthEnd,
                false
            );

            $monthRoomNights = (int) ($monthRevenue['current']['count'] ?? 0);
            $monthAccrualBase = (float) ($monthRevenue['current']['total_base'] ?? 0);
            $avgDailyRate = $monthRoomNights > 0
                ? round($monthAccrualBase / $monthRoomNights, 2)
                : 0.0;

            $cash = $this->earningsForPeriod($monthStart, $monthEnd);

            $weeklyAccrual = $this->weeklyAccrual($today);

            $recent = Reservation::with(['client', 'reservationRooms.room'])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
                ->map(fn ($r) => $this->formatRecentReservation($r));

            return \SuccessData('Dashboard summary retrieved', [
                'date' => $today->toDateString(),
                'occupancy' => $occupancy,
                'check_ins_today' => $this->occupancyService->checkInsToday($today),
                'check_outs_today' => $this->occupancyService->checkOutsToday($today),
                'arrivals_today' => $this->occupancyService->arrivalsToday($today),
                'departures_today' => $this->occupancyService->departuresToday($today),
                'month_accrual_revenue' => round($monthRevenue['current']['total'] ?? 0, 2),
                'month_accrual_base' => round($monthAccrualBase, 2),
                'month_room_nights' => $monthRoomNights,
                'avg_daily_rate' => $avgDailyRate,
                'month_cash_in' => round($cash['total_in'], 2),
                'month_cash_out' => round($cash['total_out'], 2),
                'month_cash_net' => round($cash['net_earnings'], 2),
                'weekly_accrual' => $weeklyAccrual,
                'recent_reservations' => $recent,
            ]);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    private function weeklyAccrual(Carbon $today): array
    {
        $labels = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $dayRevenue = $this->revenueAccrualService->calculate(
                'total',
                null,
                $day->copy()->startOfDay(),
                $day->copy()->startOfDay(),
                false
            );

            $labels[] = $day->toDateString();
            $values[] = round((float) ($dayRevenue['current']['total'] ?? 0), 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function earningsForPeriod(Carbon $start, Carbon $end): array
    {
        $payments = ReservationPay::join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.reservation_status', [1, 2])
            ->whereBetween('reservation_pay.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where('reservation_pay.type', ReservationPay::TYPE_PAYMENT)
            ->selectRaw('COALESCE(SUM(pay), 0) as total_in')
            ->value('total_in');

        $refunds = DB::table('reservation_pay')
            ->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.reservation_status', [1, 2])
            ->whereBetween('reservation_pay.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where('reservation_pay.type', ReservationPay::TYPE_REFUND)
            ->selectRaw('COALESCE(SUM(pay), 0) as total_out')
            ->value('total_out');

        $totalIn = (float) $payments;
        $totalOut = (float) $refunds;

        return [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'net_earnings' => $totalIn - $totalOut,
        ];
    }

    private function formatRecentReservation(Reservation $r): array
    {
        $room = $r->reservationRooms->first()?->room;
        $client = $r->client;

        return [
            'id' => $r->id,
            'guest' => $client
                ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))
                : '—',
            'room' => $room?->number ?? '—',
            'start_date' => $r->start_date,
            'expire_date' => $r->expire_date,
            'status' => (int) $r->reservation_status,
            'total' => round((float) $r->total, 2),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
