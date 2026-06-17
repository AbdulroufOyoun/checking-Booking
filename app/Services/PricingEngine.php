<?php

namespace App\Services;

use App\Models\PeakDay;
use App\Models\PeakMonth;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomtypePricingplan;
use Carbon\Carbon;

class PricingEngine
{
    /**
     * Build per-night breakdown for accrual accounting.
     *
     * @return array<int, array{date: string, base_amount: float, is_peak_day: bool, is_in_plan: bool, price_source: string}>
     */
    /**
     * @param  array<int, array{date: string, base_amount: float, is_peak_day: bool, is_in_plan: bool, price_source: string}>  $lines
     */
    public function sumBaseAmount(array $lines): float
    {
        return round(array_sum(array_column($lines, 'base_amount')), 2);
    }

    /**
     * @param  array<int, array{date: string, base_amount: float, ...}>  $lines
     * @return array<string, float>
     */
    public function linesToDaysMap(array $lines): array
    {
        $days = [];
        foreach ($lines as $line) {
            $days[$line['date']] = $line['base_amount'];
        }

        return $days;
    }

    /**
     * Group consecutive nights into summary rows (monthly chunk vs daily).
     *
     * @param  array<int, array{date: string, base_amount: float, price_source?: string}>  $lines
     * @return array<int, array{from: string, to: string, kind: string, billing_source?: string, amount: float, nights: int}>
     */
    public function buildPriceSegments(array $lines, int $rentType = 1): array
    {
        if (empty($lines)) {
            return [];
        }

        $segments = [];
        $current = null;

        foreach ($lines as $line) {
            $date = $line['date'];
            $source = (string) ($line['price_source'] ?? '');
            $kind = $this->segmentKindFromSource($source, $rentType);
            $amount = (float) $line['base_amount'];

            if ($current !== null
                && $current['kind'] === $kind
                && ($current['billing_source'] ?? '') === $source
                && $this->isNextCalendarDay($current['to'], $date)) {
                $current['to'] = $date;
                $current['amount'] = round($current['amount'] + $amount, 2);
                $current['nights']++;
                continue;
            }

            if ($current !== null) {
                $segments[] = $current;
            }

            $current = [
                'from' => $date,
                'to' => $date,
                'kind' => $kind,
                'billing_source' => $source,
                'amount' => round($amount, 2),
                'nights' => 1,
            ];
        }

        if ($current !== null) {
            $segments[] = $current;
        }

        return $segments;
    }

    private function isNextCalendarDay(string $prevDate, string $nextDate): bool
    {
        return Carbon::parse($prevDate)->addDay()->toDateString() === $nextDate;
    }

    public function calculateStayBase(
        Room $room,
        string $start,
        string $end,
        int $rentType,
        int $priceMode = 0
    ): float {
        return $this->sumBaseAmount(
            $this->buildDailyBreakdown($room, $start, $end, $rentType, $priceMode)
        );
    }

    public function buildDailyBreakdown(
        Room $room,
        string $start,
        string $end,
        int $rentType,
        int $priceMode = 0
    ): array {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        if ($rentType === 0) {
            return $this->buildDailyRentalBreakdown($room, $startDate, $endDate, $priceMode);
        }

        return $this->buildMonthlyAsDailyBreakdown($room, $startDate, $endDate, $priceMode);
    }

    private function buildDailyRentalBreakdown(Room $room, Carbon $startDate, Carbon $endDate, int $priceMode): array
    {
        $roomType = RoomType::findOrFail($room->room_type_id);
        $start = $startDate->toDateString();
        $end = $endDate->toDateString();

        $stayPlan = $this->resolveStayPricingPlan($room->room_type_id, $start, $end);

        $lines = [];

        for ($date = $startDate->copy(); $date->lt($endDate); $date->addDay()) {
            $nightPlan = $priceMode === 2 ? null : $stayPlan;

            $lines[] = $this->priceSingleNight(
                $date,
                $roomType,
                $nightPlan,
                $start,
                $end,
                $priceMode
            );
        }

        return $lines;
    }

    /**
     * Among pricing plans overlapping the stay, pick the narrowest date window.
     * This avoids a long-running base plan swallowing a shorter promotional plan
     * (e.g. stay Aug 1–11 with base 2024–2026 and promo Aug 5–31 → use promo from Aug 5).
     */
    public function resolveStayPricingPlan(int $roomTypeId, string $start, string $end): ?RoomtypePricingplan
    {
        $plans = RoomtypePricingplan::with(['pricingplan', 'roomType'])
            ->where('roomtype_id', $roomTypeId)
            ->whereHas('pricingplan', function ($query) use ($start, $end) {
                $query->where('StartDate', '<=', $end)
                    ->where('EndDate', '>=', $start);
            })
            ->get();

        if ($plans->isEmpty()) {
            return null;
        }

        if ($plans->count() === 1) {
            return $plans->first();
        }

        return $plans->sortBy(function (RoomtypePricingplan $link) {
            $planStart = Carbon::parse($link->pricingplan->StartDate)->startOfDay();
            $planEnd = Carbon::parse($link->pricingplan->EndDate)->startOfDay();
            $span = $planStart->diffInDays($planEnd);

            // Prefer narrower window; on tie prefer the plan that starts latest (more specific promo).
            return [$span, $planStart->timestamp * -1];
        })->first();
    }

    private function priceSingleNight(
        Carbon $date,
        RoomType $roomType,
        ?RoomtypePricingplan $roomTypePlan,
        string $stayStart,
        string $stayEnd,
        int $priceMode
    ): array {
        $dayName = $date->format('l');
        $isPeakDay = $this->isPeakDay($dayName);

        if ($priceMode === 1 && $roomTypePlan) {
            return $this->line($date, (float) $roomTypePlan->DailyPrice, $isPeakDay, true, 'plan');
        }

        if ($priceMode === 2 || !$roomTypePlan) {
            return $this->lineFromRoomType($date, $roomType, $isPeakDay, false);
        }

        // priceMode 0 — mixed
        return $this->priceSingleNightMixed(
            $date,
            $roomType,
            $roomTypePlan,
            $isPeakDay
        );
    }

    private function priceSingleNightMixed(
        Carbon $date,
        RoomType $roomType,
        RoomtypePricingplan $roomTypePlan,
        bool $isPeakDay
    ): array {
        if ($this->nightIsWithinPlanDates($date, $roomTypePlan)) {
            return $this->line($date, (float) $roomTypePlan->DailyPrice, $isPeakDay, true, 'plan');
        }

        return $this->lineFromRoomType($date, $roomType, $isPeakDay, false);
    }

    private function nightIsWithinPlanDates(Carbon $date, RoomtypePricingplan $roomTypePlan): bool
    {
        $dateStr = $date->toDateString();
        $planStartStr = Carbon::parse($roomTypePlan->pricingplan->StartDate)->toDateString();
        $planEndStr = Carbon::parse($roomTypePlan->pricingplan->EndDate)->toDateString();

        return $dateStr >= $planStartStr && $dateStr <= $planEndStr;
    }

    private function lineFromRoomType(Carbon $date, RoomType $roomType, bool $isPeakDay, bool $inPlan): array
    {
        $activeType = (int) $roomType->active_type;

        if ($activeType === 0) {
            return $this->line($date, (float) $roomType->Min_daily_price, $isPeakDay, $inPlan, 'min');
        }

        if ($activeType === 2) {
            return $this->line($date, (float) $roomType->Max_daily_price, $isPeakDay, $inPlan, 'max');
        }

        $amount = $isPeakDay ? (float) $roomType->Max_daily_price : (float) $roomType->Min_daily_price;

        return $this->line($date, $amount, $isPeakDay, $inPlan, $isPeakDay ? 'max' : 'min');
    }

    private function line(Carbon $date, float $amount, bool $isPeakDay, bool $inPlan, string $source): array
    {
        return [
            'date' => $date->toDateString(),
            'base_amount' => round($amount, 2),
            'is_peak_day' => $isPeakDay,
            'is_in_plan' => $inPlan,
            'price_source' => $source,
        ];
    }

    /**
     * Monthly rent: full calendar months at monthly rate, extra days at daily rate.
     */
    private function buildMonthlyAsDailyBreakdown(Room $room, Carbon $startDate, Carbon $endDate, int $priceMode): array
    {
        $roomType = RoomType::findOrFail($room->room_type_id);
        $start = $startDate->toDateString();
        $end = $endDate->toDateString();

        $stayPlan = $this->resolveStayPricingPlan($room->room_type_id, $start, $end);

        $lines = [];
        $tempDate = $startDate->copy();
        $targetDay = $startDate->day;

        while (true) {
            $nextMonth = $tempDate->copy()->addMonthNoOverflow();
            if ($startDate->isLastOfMonth()) {
                $periodEnd = $nextMonth->copy()->endOfMonth()->startOfDay();
            } else {
                $periodEnd = $nextMonth->copy()->day(min($targetDay, $nextMonth->daysInMonth));
            }

            if (!$periodEnd->lte($endDate)) {
                break;
            }

            $chunkDays = $tempDate->diffInDays($periodEnd);
            if ($chunkDays < 1) {
                break;
            }

            if ($priceMode === 0) {
                array_push($lines, ...$this->buildMixedMonthChunkLines(
                    $tempDate,
                    $chunkDays,
                    $roomType,
                    $stayPlan
                ));
            } else {
                $chunkTotal = $this->monthChunkBasePrice($tempDate, $periodEnd, $roomType, $stayPlan, $priceMode);
                $perNight = $chunkTotal / $chunkDays;

                for ($i = 0; $i < $chunkDays; $i++) {
                    $date = $tempDate->copy()->addDays($i);
                    $lines[] = $this->line(
                        $date,
                        $perNight,
                        $this->isPeakMonth($date->format('F')),
                        $stayPlan !== null,
                        'monthly'
                    );
                }
            }

            $tempDate = $periodEnd;
            if ($tempDate->gte($endDate)) {
                break;
            }
        }

        while ($tempDate->lt($endDate)) {
            $nightPlan = $priceMode === 0 ? $stayPlan : $stayPlan;

            $lines[] = $this->priceSingleNight(
                $tempDate,
                $roomType,
                $nightPlan,
                $start,
                $end,
                $priceMode
            );
            $tempDate->addDay();
        }

        return $lines;
    }

    private function monthChunkBasePrice(
        Carbon $periodStart,
        Carbon $periodEnd,
        RoomType $roomType,
        ?RoomtypePricingplan $roomTypePlan,
        int $priceMode
    ): float {
        if ($priceMode === 1 && $roomTypePlan) {
            return (float) $roomTypePlan->MonthlyPrice;
        }

        if ($priceMode === 2 || !$roomTypePlan) {
            return $this->monthlyRateForPeriod($periodStart, $roomType);
        }

        if ($priceMode === 0) {
            $chunkDays = $periodStart->diffInDays($periodEnd);
            if ($chunkDays < 1) {
                return 0.0;
            }

            return $this->sumBaseAmount($this->buildMixedMonthChunkLines(
                $periodStart,
                $chunkDays,
                $roomType,
                $roomTypePlan
            ));
        }

        $planStart = Carbon::parse($roomTypePlan->pricingplan->StartDate)->startOfDay();
        $planEnd = Carbon::parse($roomTypePlan->pricingplan->EndDate)->startOfDay();
        if ($periodStart->gte($planStart) && $periodEnd->lte($planEnd)) {
            return (float) $roomTypePlan->MonthlyPrice;
        }

        return $this->monthlyRateForPeriod($periodStart, $roomType);
    }

    /**
     * Mixed monthly chunk: per calendar month, (planN/D)*planMonthly + (roomN/D)*roomMonthly.
     *
     * @return array<int, array{date: string, base_amount: float, is_peak_day: bool, is_in_plan: bool, price_source: string}>
     */
    private function buildMixedMonthChunkLines(
        Carbon $periodStart,
        int $chunkDays,
        RoomType $roomType,
        ?RoomtypePricingplan $roomTypePlan
    ): array {
        if (!$roomTypePlan) {
            $chunkTotal = $this->monthlyRateForPeriod($periodStart, $roomType);
            $perNight = $chunkDays > 0 ? $chunkTotal / $chunkDays : 0;

            $lines = [];
            for ($i = 0; $i < $chunkDays; $i++) {
                $date = $periodStart->copy()->addDays($i);
                $lines[] = $this->line(
                    $date,
                    $perNight,
                    $this->isPeakMonth($date->format('F')),
                    false,
                    $this->roomTypeMonthlySource($roomType, $date)
                );
            }

            return $lines;
        }

        $planMonthly = (float) $roomTypePlan->MonthlyPrice;

        /** @var array<string, array{plan: int, room: int, sample: Carbon}> $byMonth */
        $byMonth = [];
        $nightMeta = [];

        for ($i = 0; $i < $chunkDays; $i++) {
            $date = $periodStart->copy()->addDays($i);
            $ym = $date->format('Y-m');
            if (!isset($byMonth[$ym])) {
                $byMonth[$ym] = ['plan' => 0, 'room' => 0, 'sample' => $date->copy()];
            }

            $inPlan = $this->nightIsWithinPlanDates($date, $roomTypePlan);
            if ($inPlan) {
                $byMonth[$ym]['plan']++;
            } else {
                $byMonth[$ym]['room']++;
            }

            $nightMeta[] = ['date' => $date->copy(), 'ym' => $ym, 'in_plan' => $inPlan];
        }

        /** @var array<string, array{plan_amounts: array<int, float>, room_amounts: array<int, float>}> $alloc */
        $alloc = [];
        foreach ($byMonth as $ym => $counts) {
            $daysInMonth = $counts['sample']->daysInMonth;
            $planN = $counts['plan'];
            $roomN = $counts['room'];
            $roomMonthly = $this->monthlyRateForPeriod($counts['sample'], $roomType);

            $planPart = $planN > 0 ? ($planN / $daysInMonth) * $planMonthly : 0.0;
            $roomPart = $roomN > 0 ? ($roomN / $daysInMonth) * $roomMonthly : 0.0;

            $alloc[$ym] = [
                'plan_amounts' => $this->distributeAmount(round($planPart, 2), $planN),
                'room_amounts' => $this->distributeAmount(round($roomPart, 2), $roomN),
            ];
        }

        $planIdx = [];
        $roomIdx = [];
        foreach (array_keys($byMonth) as $ym) {
            $planIdx[$ym] = 0;
            $roomIdx[$ym] = 0;
        }

        $lines = [];
        foreach ($nightMeta as $meta) {
            $date = $meta['date'];
            $ym = $meta['ym'];
            $isPeakMonth = $this->isPeakMonth($date->format('F'));

            if ($meta['in_plan']) {
                $amount = $alloc[$ym]['plan_amounts'][$planIdx[$ym]++] ?? 0.0;
                $lines[] = $this->line($date, $amount, $isPeakMonth, true, 'plan');
            } else {
                $amount = $alloc[$ym]['room_amounts'][$roomIdx[$ym]++] ?? 0.0;
                $source = $this->roomTypeMonthlySource($roomType, $date);
                $lines[] = $this->line($date, $amount, $isPeakMonth, false, $source);
            }
        }

        return $lines;
    }

    /**
     * @return array<int, float>
     */
    private function distributeAmount(float $total, int $nights): array
    {
        if ($nights <= 0) {
            return [];
        }

        if ($nights === 1) {
            return [round($total, 2)];
        }

        $per = round($total / $nights, 2);
        $amounts = array_fill(0, $nights, $per);
        $diff = round($total - array_sum($amounts), 2);
        if (abs($diff) >= 0.01) {
            $amounts[$nights - 1] = round($amounts[$nights - 1] + $diff, 2);
        }

        return $amounts;
    }

    private function roomTypeMonthlySource(RoomType $roomType, Carbon $date): string
    {
        $activeType = (int) $roomType->active_type;
        if ($activeType === 0) {
            return 'min';
        }
        if ($activeType === 2) {
            return 'max';
        }

        return $this->isPeakMonth($date->format('F')) ? 'max' : 'min';
    }

    private function segmentKindFromSource(string $source, int $rentType): string
    {
        // Daily rent: every night is billed daily; price_source (min/max/plan) is not "monthly billing".
        if ($rentType === 0) {
            return 'daily';
        }

        if (in_array($source, ['monthly', 'plan', 'min', 'max'], true)) {
            return 'monthly';
        }

        return 'daily';
    }

    private function monthlyRateForPeriod(Carbon $periodStart, RoomType $roomType): float
    {
        $isPeakMonth = $this->isPeakMonth($periodStart->format('F'));
        $activeType = (int) $roomType->active_type;

        if ($activeType === 0) {
            return (float) $roomType->Min_monthly_price;
        }

        if ($activeType === 2) {
            return (float) $roomType->Max_monthly_price;
        }

        return $isPeakMonth
            ? (float) $roomType->Max_monthly_price
            : (float) $roomType->Min_monthly_price;
    }

    public function isPeakDay(string $dayNameEn): bool
    {
        return PeakDay::where('day_name_en', $dayNameEn)->value('check') == 1;
    }

    public function isPeakMonth(string $monthNameEn): bool
    {
        return PeakMonth::where('month_name_en', $monthNameEn)->value('check') == 1;
    }
}
