<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueAccrualService
{
    private const TAX_RATE = 0.15;

    /**
     * Accrual revenue for a date range from stored daily charges.
     */
    public function calculate(
        string $scope,
        ?int $entityId,
        Carbon $startDate,
        Carbon $endDate,
        bool $includeDetails = false
    ): array {
        $charges = $this->queryCharges($scope, $entityId, $startDate, $endDate)->get();

        $allocated = $this->allocateCharges($charges);

        $nightlyBase = 0.0;
        $monthlyBase = 0.0;
        $details = [];

        foreach ($allocated as $row) {
            if ((int) $row->rent_type === 1) {
                $monthlyBase += $row->period_base;
            } else {
                $nightlyBase += $row->period_base;
            }

            if ($includeDetails) {
                $details[] = [
                    'charge_date' => $row->charge_date,
                    'reservation_id' => $row->reservation_id,
                    'room_id' => $row->room_id,
                    'room_number' => $row->room_number,
                    'base_amount' => round($row->period_base, 2),
                    'discount_allocated' => round($row->discount_allocated, 2),
                    'extras_allocated' => round($row->extras_allocated, 2),
                    'penalties_allocated' => round($row->penalties_allocated, 2),
                    'subtotal' => round($row->period_subtotal, 2),
                    'tax' => round($row->period_tax, 2),
                    'revenue' => round($row->period_revenue, 2),
                    'is_in_plan' => (bool) $row->is_in_plan,
                    'is_peak_day' => (bool) $row->is_peak_day,
                    'price_source' => $row->price_source,
                ];
            }
        }

        $totalBase = $nightlyBase + $monthlyBase;
        $allocatedCollection = collect($allocated);
        $totalSubtotal = $allocatedCollection->sum('period_subtotal');
        $totalTax = $allocatedCollection->sum('period_tax');
        $totalRevenue = $allocatedCollection->sum('period_revenue');

        $reservationIds = $charges->pluck('reservation_id')->unique();

        return [
            'current' => [
                'nightly' => round($nightlyBase, 2),
                'monthly' => round($monthlyBase, 2),
                'total_base' => round($totalBase, 2),
                'subtotal' => round($totalSubtotal, 2),
                'tax' => round($totalTax, 2),
                'total' => round($totalRevenue, 2),
                'count' => $charges->count(),
                'reservation_count' => $reservationIds->count(),
            ],
            'details' => $includeDetails ? $details : null,
        ];
    }

    private function queryCharges(string $scope, ?int $entityId, Carbon $startDate, Carbon $endDate)
    {
        $query = ReservationDailyCharge::query()
            ->select([
                'reservation_daily_charges.*',
                'reservations.discount',
                'reservations.extras',
                'reservations.penalties',
                'reservations.base_price as reservation_base_price',
                'rooms.number as room_number',
            ])
            ->join('reservations', 'reservation_daily_charges.reservation_id', '=', 'reservations.id')
            ->join('rooms', 'reservation_daily_charges.room_id', '=', 'rooms.id')
            ->where('reservations.reservation_status', 1)
            ->whereBetween('reservation_daily_charges.charge_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        $this->applyScope($query, $scope, $entityId);

        return $query;
    }

    private function applyScope($query, string $scope, ?int $entityId): void
    {
        if ($scope === 'total' || $entityId === null) {
            return;
        }

        match ($scope) {
            'room' => $query->where('rooms.id', $entityId),
            'building' => $query->where('rooms.building_id', $entityId),
            'floor' => $query->where('rooms.floor_id', $entityId),
            'suite' => $query->where(function ($q) use ($entityId) {
                $q->where('rooms.suite_id', $entityId)
                    ->orWhereExists(function ($sub) use ($entityId) {
                        $sub->select(DB::raw(1))
                            ->from('reservation_rooms')
                            ->whereColumn('reservation_rooms.id', 'reservation_daily_charges.reservation_room_id')
                            ->where('reservation_rooms.suite_id', $entityId);
                    });
            }),
            'roomtype' => $query->where('rooms.room_type_id', $entityId),
            default => null,
        };
    }

    /**
     * @return array<int, object>
     */
    private function allocateCharges(Collection $charges): array
    {
        if ($charges->isEmpty()) {
            return [];
        }

        $reservationTotals = ReservationDailyCharge::query()
            ->whereIn('reservation_id', $charges->pluck('reservation_id')->unique())
            ->selectRaw('reservation_id, SUM(base_amount) as total_base')
            ->groupBy('reservation_id')
            ->pluck('total_base', 'reservation_id');

        $result = [];

        foreach ($charges as $charge) {
            $reservationTotalBase = (float) ($reservationTotals[$charge->reservation_id] ?? 0);
            $weight = $reservationTotalBase > 0
                ? (float) $charge->base_amount / $reservationTotalBase
                : 0;

            $discountAlloc = (float) $charge->discount * $weight;
            $extrasAlloc = (float) $charge->extras * $weight;
            $penaltiesAlloc = (float) $charge->penalties * $weight;

            $periodBase = (float) $charge->base_amount;
            $periodSubtotal = $periodBase - $discountAlloc + $extrasAlloc + $penaltiesAlloc;
            $periodTax = round($periodSubtotal * self::TAX_RATE, 2);
            $periodRevenue = $periodSubtotal + $periodTax;

            $result[] = (object) [
                'charge_date' => $charge->charge_date->format('Y-m-d'),
                'reservation_id' => $charge->reservation_id,
                'room_id' => $charge->room_id,
                'room_number' => $charge->room_number,
                'rent_type' => $charge->rent_type,
                'is_in_plan' => $charge->is_in_plan,
                'is_peak_day' => $charge->is_peak_day,
                'price_source' => $charge->price_source,
                'period_base' => $periodBase,
                'discount_allocated' => $discountAlloc,
                'extras_allocated' => $extrasAlloc,
                'penalties_allocated' => $penaltiesAlloc,
                'period_subtotal' => $periodSubtotal,
                'period_tax' => $periodTax,
                'period_revenue' => $periodRevenue,
            ];
        }

        return $result;
    }

    public function persistDailyCharges(
        int $reservationId,
        int $reservationRoomId,
        int $roomId,
        int $rentType,
        array $lines
    ): void {
        ReservationDailyCharge::where('reservation_room_id', $reservationRoomId)->delete();

        foreach ($lines as $line) {
            ReservationDailyCharge::create([
                'reservation_id' => $reservationId,
                'reservation_room_id' => $reservationRoomId,
                'room_id' => $roomId,
                'charge_date' => $line['date'],
                'base_amount' => $line['base_amount'],
                'is_peak_day' => $line['is_peak_day'],
                'is_in_plan' => $line['is_in_plan'],
                'price_source' => $line['price_source'],
                'rent_type' => $rentType,
            ]);
        }
    }
}
