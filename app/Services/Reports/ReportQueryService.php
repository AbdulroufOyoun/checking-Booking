<?php

namespace App\Services\Reports;

use App\Models\ChartOfAccount;
use App\Models\Client;
use App\Models\JournalEntry;
use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use App\Models\ReservationPay;
use App\Models\Room;
use App\Services\Accounting\FinancialAuditService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\RevenueAccrualService;
use App\Services\RoomOccupancyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReportQueryService
{
    private const TAX_RATE = 0.15;

    public function __construct(
        private RevenueAccrualService $revenueAccrualService,
        private RoomOccupancyService $occupancyService,
        private FinancialStatementService $financialStatementService,
        private FinancialAuditService $financialAuditService,
    ) {
    }

    public function run(string $slug, array $params): array
    {
        [$start, $end, $compareStart, $compareEnd] = $this->parseDates($params);

        return match ($slug) {
            'overview' => $this->overview($start, $end, $compareStart, $compareEnd),
            'room-board' => $this->roomBoard($end),
            'accrual-revenue' => $this->accrualRevenue($start, $end, $compareStart, $compareEnd),
            'cash-box' => $this->cashBox($start, $end),
            'chart-of-accounts' => $this->chartOfAccountsReport($start, $end),
            'journal-entries' => $this->journalEntriesReport($start, $end),
            'arrivals-departures' => $this->arrivalsDepartures($start, $end),
            'reservations-list' => $this->reservationsList($start, $end),
            'occupancy' => $this->occupancy($start, $end, $compareStart, $compareEnd),
            'revenue-summary' => $this->revenueSummary($start, $end, $compareStart, $compareEnd),
            'accrual-cash-reconciliation' => $this->accrualCashReconciliation($start, $end, $compareStart, $compareEnd),
            'ar-aging' => $this->arAging($end),
            'adjustments' => $this->adjustments($start, $end),
            'tax' => $this->tax($start, $end, $compareStart, $compareEnd),
            'revpar' => $this->revpar($start, $end, $compareStart, $compareEnd),
            'by-dimension' => $this->byDimension($start, $end),
            'peak-analysis' => $this->peakAnalysis($start, $end),
            'payments-refunds' => $this->paymentsRefunds($start, $end),
            'closing-package' => $this->closingPackage($start, $end),
            'general-ledger' => $this->generalLedger($start, $end, $params),
            'trial-balance' => $this->trialBalance($start, $end),
            'balance-sheet' => $this->balanceSheet($end),
            'cash-flow' => $this->cashFlow($start, $end),
            'financial-audit-log' => $this->financialAuditLog($start, $end),
            default => throw new InvalidArgumentException("Unknown report slug: {$slug}"),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: ?Carbon, 3: ?Carbon}
     */
    private function parseDates(array $params): array
    {
        $start = Carbon::parse($params['start_date'] ?? Carbon::today()->startOfMonth());
        $end = Carbon::parse($params['end_date'] ?? Carbon::today()->endOfMonth());
        $compareStart = !empty($params['compare_start_date'])
            ? Carbon::parse($params['compare_start_date']) : null;
        $compareEnd = !empty($params['compare_end_date'])
            ? Carbon::parse($params['compare_end_date']) : null;

        return [$start, $end, $compareStart, $compareEnd];
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{label: string, value: string|int|float}>  $summary
     * @param  array<int, string>  $metaLines
     * @return array{columns: array, rows: array, summary: array, meta_lines: array}
     */
    private function format(
        array $columns,
        array $rows,
        array $summary = [],
        array $metaLines = []
    ): array {
        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $summary,
            'meta_lines' => $metaLines,
        ];
    }

    private function periodMeta(Carbon $start, Carbon $end, ?Carbon $compareStart = null, ?Carbon $compareEnd = null): array
    {
        $lines = [
            "Period: {$start->toDateString()} → {$end->toDateString()}",
        ];

        if ($compareStart && $compareEnd) {
            $lines[] = "Compare: {$compareStart->toDateString()} → {$compareEnd->toDateString()}";
        }

        return $lines;
    }

    private function overview(Carbon $start, Carbon $end, ?Carbon $compareStart, ?Carbon $compareEnd): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, false);
        $cash = $this->cashForPeriod($start, $end);
        $arTotal = $this->totalAccountsReceivable($end);

        $accrualTotal = round((float) ($accrual['current']['total'] ?? 0), 2);
        $cashIn = round($cash['total_in'], 2);
        $cashOut = round($cash['total_out'], 2);
        $cashNet = round($cash['net_earnings'], 2);

        $rows = [
            ['metric' => 'Accrual revenue (incl. tax)', 'amount' => $accrualTotal],
            ['metric' => 'Accrual subtotal (pre-tax)', 'amount' => round((float) ($accrual['current']['subtotal'] ?? 0), 2)],
            ['metric' => 'Tax (accrual)', 'amount' => round((float) ($accrual['current']['tax'] ?? 0), 2)],
            ['metric' => 'Room nights (accrual)', 'amount' => (int) ($accrual['current']['count'] ?? 0)],
            ['metric' => 'Cash in', 'amount' => $cashIn],
            ['metric' => 'Cash out (refunds)', 'amount' => $cashOut],
            ['metric' => 'Net cash', 'amount' => $cashNet],
            ['metric' => 'Accrual vs cash difference', 'amount' => round($accrualTotal - $cashNet, 2)],
            ['metric' => 'Accounts receivable (as of end)', 'amount' => $arTotal],
        ];

        $summary = [
            ['label' => 'Accrual revenue', 'value' => $accrualTotal],
            ['label' => 'Net cash', 'value' => $cashNet],
            ['label' => 'A/R balance', 'value' => $arTotal],
            ['label' => 'Accrual − cash', 'value' => round($accrualTotal - $cashNet, 2)],
        ];

        if ($compareStart && $compareEnd) {
            $compareAccrual = $this->revenueAccrualService->calculate('total', null, $compareStart, $compareEnd, false);
            $compareCash = $this->cashForPeriod($compareStart, $compareEnd);
            $summary[] = ['label' => 'Compare accrual', 'value' => round((float) ($compareAccrual['current']['total'] ?? 0), 2)];
            $summary[] = ['label' => 'Compare net cash', 'value' => round($compareCash['net_earnings'], 2)];
        }

        return $this->format(
            [
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'amount', 'label' => 'Amount'],
            ],
            $rows,
            $summary,
            array_merge(
                $this->periodMeta($start, $end, $compareStart, $compareEnd),
                ['Financial overview for the selected period.']
            )
        );
    }

    private function roomBoard(Carbon $date): array
    {
        $board = $this->occupancyService->buildBoard($date);
        $summaryData = $board['summary'] ?? [];

        $rows = collect($board['rooms'] ?? [])->map(function (array $room) {
            $reservation = $room['reservation'] ?? null;

            return [
                'number' => $room['number'] ?? '—',
                'building' => $room['building_name'] ?? '—',
                'floor' => $room['floor_name'] ?? '—',
                'room_type' => $room['room_type_name'] ?? '—',
                'occupancy_status' => $room['occupancy_status'] ?? '—',
                'operational_status' => $room['operational_status_label'] ?? '—',
                'guest' => $reservation['client_name'] ?? '—',
                'reservation_id' => $reservation['reservation_id'] ?? '—',
                'check_in' => $reservation['start_date'] ?? '—',
                'check_out' => $reservation['expire_date'] ?? '—',
            ];
        })->values()->all();

        $summary = [
            ['label' => 'Snapshot date', 'value' => $board['date'] ?? $date->toDateString()],
            ['label' => 'Total rooms', 'value' => $summaryData['total'] ?? count($rows)],
            ['label' => 'In house', 'value' => $summaryData['in_house'] ?? 0],
            ['label' => 'Occupancy rate', 'value' => ($summaryData['occupancy_rate'] ?? 0) . '%'],
        ];

        return $this->format(
            [
                ['key' => 'number', 'label' => 'Room #'],
                ['key' => 'building', 'label' => 'Building'],
                ['key' => 'floor', 'label' => 'Floor'],
                ['key' => 'room_type', 'label' => 'Room type'],
                ['key' => 'occupancy_status', 'label' => 'Occupancy'],
                ['key' => 'operational_status', 'label' => 'Operational status'],
                ['key' => 'guest', 'label' => 'Guest'],
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
                ['key' => 'check_in', 'label' => 'Check-in'],
                ['key' => 'check_out', 'label' => 'Check-out'],
            ],
            $rows,
            $summary,
            [
                "Room board snapshot for {$date->toDateString()} (uses end date as snapshot date).",
            ]
        );
    }

    private function accrualRevenue(Carbon $start, Carbon $end, ?Carbon $compareStart, ?Carbon $compareEnd): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, true);
        $details = collect($accrual['details'] ?? []);

        $rows = $details->map(function ($line) {
            return [
                'charge_date' => $line['charge_date'] ?? '—',
                'reservation_id' => $line['reservation_id'] ?? '—',
                'room_number' => $line['room_number'] ?? '—',
                'base_amount' => round((float) ($line['base_amount'] ?? 0), 2),
                'discount' => round((float) ($line['discount_allocated'] ?? 0), 2),
                'extras' => round((float) ($line['extras_allocated'] ?? 0), 2),
                'penalties' => round((float) ($line['penalties_allocated'] ?? 0), 2),
                'subtotal' => round((float) ($line['subtotal'] ?? 0), 2),
                'tax' => round((float) ($line['tax'] ?? 0), 2),
                'revenue' => round((float) ($line['revenue'] ?? 0), 2),
                'price_source' => $line['price_source'] ?? '—',
            ];
        })->values()->all();

        $summary = [
            ['label' => 'Total revenue', 'value' => round((float) ($accrual['current']['total'] ?? 0), 2)],
            ['label' => 'Total tax', 'value' => round((float) ($accrual['current']['tax'] ?? 0), 2)],
            ['label' => 'Room nights', 'value' => (int) ($accrual['current']['count'] ?? 0)],
            ['label' => 'Detail lines', 'value' => count($rows)],
        ];

        if ($compareStart && $compareEnd) {
            $compare = $this->revenueAccrualService->calculate('total', null, $compareStart, $compareEnd, false);
            $summary[] = ['label' => 'Compare revenue', 'value' => round((float) ($compare['current']['total'] ?? 0), 2)];
        }

        return $this->format(
            [
                ['key' => 'charge_date', 'label' => 'Date'],
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
                ['key' => 'room_number', 'label' => 'Room'],
                ['key' => 'base_amount', 'label' => 'Base'],
                ['key' => 'discount', 'label' => 'Discount'],
                ['key' => 'extras', 'label' => 'Extras'],
                ['key' => 'penalties', 'label' => 'Penalties'],
                ['key' => 'subtotal', 'label' => 'Subtotal'],
                ['key' => 'tax', 'label' => 'Tax'],
                ['key' => 'revenue', 'label' => 'Revenue'],
                ['key' => 'price_source', 'label' => 'Price source'],
            ],
            $rows,
            $summary,
            array_merge(
                $this->periodMeta($start, $end, $compareStart, $compareEnd),
                ['Detailed accrual revenue from reservation_daily_charges.']
            )
        );
    }

    private function cashBox(Carbon $start, Carbon $end): array
    {
        $report = $this->paymentsRefunds($start, $end);
        $report['meta_lines'] = array_merge(
            $this->periodMeta($start, $end),
            ['Cash box report: payments and refunds in the selected period.']
        );

        return $report;
    }

    private function chartOfAccountsReport(Carbon $start, Carbon $end): array
    {
        $accounts = ChartOfAccount::where('active', 1)->orderBy('code')->get();
        $balanceRows = $this->financialStatementService->accountBalances($start, $end->copy()->endOfDay());
        $balanceMap = $balanceRows->keyBy('account_id');

        $rows = $accounts->map(function (ChartOfAccount $account) use ($balanceMap) {
            $row = $balanceMap->get($account->id);

            return [
                'code' => $account->code,
                'name_en' => $account->name_en,
                'name_ar' => $account->name_ar,
                'type' => $account->type,
                'balance' => round((float) ($row->balance ?? 0), 2),
            ];
        })->values()->all();

        $summary = [
            ['label' => 'Active accounts', 'value' => count($rows)],
            ['label' => 'Total balance', 'value' => round(collect($rows)->sum('balance'), 2)],
        ];

        return $this->format(
            [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name_en', 'label' => 'Name (EN)'],
                ['key' => 'name_ar', 'label' => 'Name (AR)'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'balance', 'label' => 'Balance'],
            ],
            $rows,
            $summary,
            array_merge(
                $this->periodMeta($start, $end),
                ['Chart of accounts with balances for the selected period.']
            )
        );
    }

    private function journalEntriesReport(Carbon $start, Carbon $end): array
    {
        $entries = JournalEntry::with(['lines.account'])
            ->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $rows[] = [
                    'entry_date' => $entry->entry_date,
                    'reference' => $entry->reference ?? '—',
                    'description' => $entry->description ?? '—',
                    'account_code' => $line->account?->code ?? '—',
                    'account_name' => $line->account?->name_en ?? $line->account?->name_ar ?? '—',
                    'debit' => round((float) $line->debit, 2),
                    'credit' => round((float) $line->credit, 2),
                ];
            }
        }

        $totalDebit = round(collect($rows)->sum('debit'), 2);
        $totalCredit = round(collect($rows)->sum('credit'), 2);

        return $this->format(
            [
                ['key' => 'entry_date', 'label' => 'Date'],
                ['key' => 'reference', 'label' => 'Reference'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'account_code', 'label' => 'Account code'],
                ['key' => 'account_name', 'label' => 'Account name'],
                ['key' => 'debit', 'label' => 'Debit'],
                ['key' => 'credit', 'label' => 'Credit'],
            ],
            $rows,
            [
                ['label' => 'Journal entries', 'value' => $entries->count()],
                ['label' => 'Lines', 'value' => count($rows)],
                ['label' => 'Total debit', 'value' => $totalDebit],
                ['label' => 'Total credit', 'value' => $totalCredit],
            ],
            array_merge(
                $this->periodMeta($start, $end),
                ['Journal entry lines for the selected period.']
            )
        );
    }

    private function arrivalsDepartures(Carbon $start, Carbon $end): array
    {
        $startStr = $start->toDateString();
        $endStr = $end->toDateString();
        $singleDay = $startStr === $endStr;

        $baseQuery = fn () => Reservation::with(['client', 'reservationRooms.room'])
            ->whereNotIn('reservation_status', [Reservation::STATUS_PENDING_PAYMENT, Reservation::STATUS_CANCELLED]);

        $arrivals = $baseQuery()
            ->whereBetween('start_date', [$startStr, $endStr])
            ->orderBy('start_date')
            ->get();

        $departures = $baseQuery()
            ->whereBetween('expire_date', [$startStr, $endStr])
            ->orderBy('expire_date')
            ->get();

        $rows = [];

        foreach ($arrivals as $reservation) {
            $rows[] = $this->movementRow($reservation, 'Arrival', $reservation->start_date);
        }

        foreach ($departures as $reservation) {
            $rows[] = $this->movementRow($reservation, 'Departure', $reservation->expire_date);
        }

        $stayoverCount = 0;
        if ($singleDay) {
            $stayovers = $baseQuery()
                ->where('start_date', '<', $startStr)
                ->where('expire_date', '>', $startStr)
                ->orderBy('start_date')
                ->get();

            foreach ($stayovers as $reservation) {
                $rows[] = $this->movementRow($reservation, 'Stayover', $startStr);
                $stayoverCount++;
            }
        }

        usort($rows, function ($a, $b) {
            $byDate = strcmp((string) $a['movement_date'], (string) $b['movement_date']);
            if ($byDate !== 0) {
                return $byDate;
            }

            return strcmp((string) $a['movement_type'], (string) $b['movement_type']);
        });

        $summary = [
            ['label' => 'Arrivals', 'value' => $arrivals->count()],
            ['label' => 'Departures', 'value' => $departures->count()],
        ];
        if ($singleDay) {
            $summary[] = ['label' => 'Stayovers', 'value' => $stayoverCount];
        }
        $summary[] = ['label' => 'Total movements', 'value' => count($rows)];

        return $this->format(
            [
                ['key' => 'movement_type', 'label' => 'Type'],
                ['key' => 'movement_date', 'label' => 'Date'],
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
                ['key' => 'guest', 'label' => 'Guest'],
                ['key' => 'room', 'label' => 'Room'],
                ['key' => 'start_date', 'label' => 'Check-in'],
                ['key' => 'expire_date', 'label' => 'Check-out'],
                ['key' => 'checked_in', 'label' => 'In house'],
            ],
            $rows,
            $summary,
            array_merge(
                $this->periodMeta($start, $end),
                $singleDay
                    ? ['Single day: includes arrivals, departures, and stayovers (matches calendar occupancy for that date).']
                    : []
            )
        );
    }

    private function movementRow(Reservation $reservation, string $type, string $date): array
    {
        $room = $reservation->reservationRooms->first()?->room;

        return [
            'movement_type' => $type,
            'movement_date' => $date,
            'reservation_id' => $reservation->id,
            'guest' => $this->guestName($reservation->client),
            'room' => $room?->number ?? '—',
            'start_date' => $reservation->start_date,
            'expire_date' => $reservation->expire_date,
            'checked_in' => (int) $reservation->logedin === Reservation::LOGEDIN_IN_HOUSE ? 'Yes' : 'No',
        ];
    }

    private function reservationsList(Carbon $start, Carbon $end): array
    {
        $reservations = Reservation::with(['client', 'reservationRooms.room'])
            ->whereNotIn('reservation_status', [Reservation::STATUS_PENDING_PAYMENT, Reservation::STATUS_CANCELLED])
            ->where('start_date', '<=', $end->toDateString())
            ->where('expire_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->get();

        $rows = $reservations->map(function (Reservation $r) {
            $room = $r->reservationRooms->first()?->room;

            return [
                'reservation_id' => $r->id,
                'guest' => $this->guestName($r->client),
                'room' => $room?->number ?? '—',
                'start_date' => $r->start_date,
                'expire_date' => $r->expire_date,
                'status' => $this->reservationStatusLabel((int) $r->reservation_status),
                'total' => round((float) $r->total, 2),
            ];
        })->values()->all();

        return $this->format(
            [
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
                ['key' => 'guest', 'label' => 'Guest'],
                ['key' => 'room', 'label' => 'Room'],
                ['key' => 'start_date', 'label' => 'Check-in'],
                ['key' => 'expire_date', 'label' => 'Check-out'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'total', 'label' => 'Total'],
            ],
            $rows,
            [
                ['label' => 'Reservations', 'value' => count($rows)],
                ['label' => 'Confirmed', 'value' => $reservations->where('reservation_status', 1)->count()],
                ['label' => 'Total value', 'value' => round($reservations->sum('total'), 2)],
            ],
            $this->periodMeta($start, $end)
        );
    }

    private function occupancy(Carbon $start, Carbon $end, ?Carbon $compareStart, ?Carbon $compareEnd): array
    {
        $availableRooms = Room::query()
            ->where('active', 1)
            ->whereNotIn('roomStatus', [3, 4])
            ->count();

        $days = $start->diffInDays($end) + 1;
        $capacityNights = $availableRooms * $days;

        $rows = [];
        $totalRoomNights = 0;

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $roomNights = ReservationDailyCharge::query()
                ->join('reservations', 'reservation_daily_charges.reservation_id', '=', 'reservations.id')
                ->where('reservations.reservation_status', 1)
                ->whereDate('reservation_daily_charges.charge_date', $day->toDateString())
                ->count();

            $daySummary = $this->occupancyService->summaryForDate($day);
            $occupancyRate = $availableRooms > 0
                ? round($roomNights / $availableRooms * 100, 1)
                : 0.0;

            $totalRoomNights += $roomNights;

            $rows[] = [
                'date' => $day->toDateString(),
                'room_nights' => $roomNights,
                'available_rooms' => $availableRooms,
                'occupancy_rate' => $occupancyRate,
                'in_house' => $daySummary['in_house'] ?? 0,
                'vacant' => $daySummary['vacant'] ?? 0,
            ];
        }

        $avgOccupancy = $capacityNights > 0
            ? round($totalRoomNights / $capacityNights * 100, 1)
            : 0.0;

        $summary = [
            ['label' => 'Available rooms', 'value' => $availableRooms],
            ['label' => 'Days in period', 'value' => $days],
            ['label' => 'Total room nights sold', 'value' => $totalRoomNights],
            ['label' => 'Average occupancy rate', 'value' => "{$avgOccupancy}%"],
        ];

        if ($compareStart && $compareEnd) {
            $compareNights = ReservationDailyCharge::query()
                ->join('reservations', 'reservation_daily_charges.reservation_id', '=', 'reservations.id')
                ->where('reservations.reservation_status', 1)
                ->whereBetween('reservation_daily_charges.charge_date', [
                    $compareStart->toDateString(),
                    $compareEnd->toDateString(),
                ])
                ->count();

            $compareDays = $compareStart->diffInDays($compareEnd) + 1;
            $compareCapacity = $availableRooms * $compareDays;
            $compareRate = $compareCapacity > 0
                ? round($compareNights / $compareCapacity * 100, 1)
                : 0.0;

            $summary[] = ['label' => 'Compare room nights', 'value' => $compareNights];
            $summary[] = ['label' => 'Compare occupancy rate', 'value' => "{$compareRate}%"];
        }

        return $this->format(
            [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'room_nights', 'label' => 'Room nights'],
                ['key' => 'available_rooms', 'label' => 'Available rooms'],
                ['key' => 'occupancy_rate', 'label' => 'Occupancy %'],
                ['key' => 'in_house', 'label' => 'In house'],
                ['key' => 'vacant', 'label' => 'Vacant'],
            ],
            $rows,
            $summary,
            $this->periodMeta($start, $end, $compareStart, $compareEnd)
        );
    }

    private function revenueSummary(Carbon $start, Carbon $end, ?Carbon $compareStart, ?Carbon $compareEnd): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, true);
        $byDate = collect($accrual['details'] ?? [])
            ->groupBy('charge_date')
            ->map(function (Collection $items, string $date) {
                return [
                    'charge_date' => $date,
                    'base_amount' => round($items->sum('base_amount'), 2),
                    'discount' => round($items->sum('discount_allocated'), 2),
                    'extras' => round($items->sum('extras_allocated'), 2),
                    'penalties' => round($items->sum('penalties_allocated'), 2),
                    'subtotal' => round($items->sum('subtotal'), 2),
                    'tax' => round($items->sum('tax'), 2),
                    'revenue' => round($items->sum('revenue'), 2),
                    'room_nights' => $items->count(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        $summary = [
            ['label' => 'Total base', 'value' => $accrual['current']['total_base'] ?? 0],
            ['label' => 'Total tax', 'value' => $accrual['current']['tax'] ?? 0],
            ['label' => 'Total revenue', 'value' => $accrual['current']['total'] ?? 0],
            ['label' => 'Room nights', 'value' => $accrual['current']['count'] ?? 0],
        ];

        if ($compareStart && $compareEnd) {
            $compare = $this->revenueAccrualService->calculate('total', null, $compareStart, $compareEnd, false);
            $summary[] = ['label' => 'Compare revenue', 'value' => $compare['current']['total'] ?? 0];
        }

        return $this->format(
            [
                ['key' => 'charge_date', 'label' => 'Date'],
                ['key' => 'room_nights', 'label' => 'Room nights'],
                ['key' => 'base_amount', 'label' => 'Base'],
                ['key' => 'discount', 'label' => 'Discount'],
                ['key' => 'extras', 'label' => 'Extras'],
                ['key' => 'penalties', 'label' => 'Penalties'],
                ['key' => 'subtotal', 'label' => 'Subtotal'],
                ['key' => 'tax', 'label' => 'Tax'],
                ['key' => 'revenue', 'label' => 'Revenue'],
            ],
            $byDate,
            $summary,
            array_merge(
                $this->periodMeta($start, $end, $compareStart, $compareEnd),
                ['Accrual revenue from daily charges (reservation_status = 1).']
            )
        );
    }

    private function accrualCashReconciliation(Carbon $start, Carbon $end, ?Carbon $compareStart, ?Carbon $compareEnd): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, false);
        $cash = $this->cashForPeriod($start, $end);

        $accrualTotal = round((float) ($accrual['current']['total'] ?? 0), 2);
        $cashNet = round($cash['net_earnings'], 2);
        $difference = round($accrualTotal - $cashNet, 2);

        $status = abs($difference) < 0.02
            ? 'Balanced'
            : ($difference > 0 ? 'Accrual exceeds cash' : 'Cash exceeds accrual');

        $rows = [[
            'accrual_revenue' => $accrualTotal,
            'cash_in' => round($cash['total_in'], 2),
            'cash_out' => round($cash['total_out'], 2),
            'cash_net' => $cashNet,
            'difference' => $difference,
            'status' => $status,
        ]];

        $summary = [
            ['label' => 'Accrual revenue', 'value' => $accrualTotal],
            ['label' => 'Cash net', 'value' => $cashNet],
            ['label' => 'Difference', 'value' => $difference],
            ['label' => 'Status', 'value' => $status],
        ];

        if ($compareStart && $compareEnd) {
            $compareAccrual = $this->revenueAccrualService->calculate('total', null, $compareStart, $compareEnd, false);
            $compareCash = $this->cashForPeriod($compareStart, $compareEnd);
            $summary[] = ['label' => 'Compare accrual', 'value' => $compareAccrual['current']['total'] ?? 0];
            $summary[] = ['label' => 'Compare cash net', 'value' => round($compareCash['net_earnings'], 2)];
        }

        $metaLines = array_merge(
            $this->periodMeta($start, $end, $compareStart, $compareEnd),
            [
                'Accrual: reservation_status = 1, revenue by charge_date (stay night).',
                'Cash: reservation_status in (1, 2), payments/refunds by reservation_pay.created_at.',
                'Difference is expected when stay dates differ from payment dates (prepay, deposits, refunds).',
                'Pending reservations (status 2) affect cash but not accrual until confirmed.',
            ]
        );

        return $this->format(
            [
                ['key' => 'accrual_revenue', 'label' => 'Accrual revenue'],
                ['key' => 'cash_in', 'label' => 'Cash in'],
                ['key' => 'cash_out', 'label' => 'Cash out (refunds)'],
                ['key' => 'cash_net', 'label' => 'Cash net'],
                ['key' => 'difference', 'label' => 'Difference (accrual − cash net)'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $rows,
            $summary,
            $metaLines
        );
    }

    private function arAging(Carbon $asOf): array
    {
        $reservations = Reservation::with(['client', 'payments', 'reservationRooms.room'])
            ->where('reservation_status', 1)
            ->get();

        $buckets = [
            '0-30' => 0.0,
            '31-60' => 0.0,
            '61-90' => 0.0,
            '90+' => 0.0,
        ];

        $rows = [];

        foreach ($reservations as $reservation) {
            $balanceDue = $this->reservationBalanceDue($reservation);

            if ($balanceDue <= 0.005) {
                continue;
            }

            $daysOverdue = $this->arDaysOverdue($reservation, $asOf);
            $bucket = $this->arBucket($daysOverdue);
            $buckets[$bucket] += $balanceDue;

            $rows[] = [
                'reservation_id' => $reservation->id,
                'guest' => $this->guestName($reservation->client),
                'room' => $reservation->reservationRooms->first()?->room?->number ?? '—',
                'expire_date' => $reservation->expire_date,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
                'total' => round((float) $reservation->total, 2),
                'paid_net' => round($this->reservationPaidNet($reservation), 2),
                'balance_due' => round($balanceDue, 2),
            ];
        }

        usort($rows, fn ($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);

        $totalAr = round(array_sum($buckets), 2);

        return $this->format(
            [
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
                ['key' => 'guest', 'label' => 'Guest'],
                ['key' => 'room', 'label' => 'Room'],
                ['key' => 'expire_date', 'label' => 'Check-out'],
                ['key' => 'days_overdue', 'label' => 'Days overdue'],
                ['key' => 'bucket', 'label' => 'Bucket'],
                ['key' => 'total', 'label' => 'Total'],
                ['key' => 'paid_net', 'label' => 'Paid (net)'],
                ['key' => 'balance_due', 'label' => 'Balance due'],
            ],
            $rows,
            [
                ['label' => 'Total AR', 'value' => $totalAr],
                ['label' => '0-30 days', 'value' => round($buckets['0-30'], 2)],
                ['label' => '31-60 days', 'value' => round($buckets['31-60'], 2)],
                ['label' => '61-90 days', 'value' => round($buckets['61-90'], 2)],
                ['label' => '90+ days', 'value' => round($buckets['90+'], 2)],
            ],
            [
                "As of: {$asOf->toDateString()}",
                'Confirmed reservations (status 1) with balance_due > 0.',
                'Balance = reservation.total − (payments − refunds); total includes tax at ' . (self::TAX_RATE * 100) . '%.',
                'Days overdue measured from check-out date for past stays; current/future stays in 0-30 bucket.',
            ]
        );
    }

    private function adjustments(Carbon $start, Carbon $end): array
    {
        $reservationIds = ReservationDailyCharge::query()
            ->whereBetween('charge_date', [$start->toDateString(), $end->toDateString()])
            ->distinct()
            ->pluck('reservation_id');

        $reservations = Reservation::with(['client', 'reservationRooms.room'])
            ->whereIn('id', $reservationIds)
            ->where('reservation_status', 1)
            ->orderBy('id')
            ->get();

        $rows = $reservations->map(function (Reservation $r) {
            return [
                'reservation_id' => $r->id,
                'guest' => $this->guestName($r->client),
                'room' => $r->reservationRooms->first()?->room?->number ?? '—',
                'discount' => round((float) $r->discount, 2),
                'extras' => round((float) $r->extras, 2),
                'penalties' => round((float) $r->penalties, 2),
                'net_adjustment' => round((float) $r->extras + (float) $r->penalties - (float) $r->discount, 2),
            ];
        })->values()->all();

        return $this->format(
            [
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
                ['key' => 'guest', 'label' => 'Guest'],
                ['key' => 'room', 'label' => 'Room'],
                ['key' => 'discount', 'label' => 'Discount'],
                ['key' => 'extras', 'label' => 'Extras'],
                ['key' => 'penalties', 'label' => 'Penalties'],
                ['key' => 'net_adjustment', 'label' => 'Net adjustment'],
            ],
            $rows,
            [
                ['label' => 'Reservations', 'value' => count($rows)],
                ['label' => 'Total discount', 'value' => round(collect($rows)->sum('discount'), 2)],
                ['label' => 'Total extras', 'value' => round(collect($rows)->sum('extras'), 2)],
                ['label' => 'Total penalties', 'value' => round(collect($rows)->sum('penalties'), 2)],
            ],
            array_merge(
                $this->periodMeta($start, $end),
                ['Adjustments for reservations with daily charges in the selected period.']
            )
        );
    }

    private function tax(Carbon $start, Carbon $end, ?Carbon $compareStart, ?Carbon $compareEnd): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, true);
        $byDate = collect($accrual['details'] ?? [])
            ->groupBy('charge_date')
            ->map(fn (Collection $items, string $date) => [
                'charge_date' => $date,
                'subtotal' => round($items->sum('subtotal'), 2),
                'tax' => round($items->sum('tax'), 2),
                'tax_rate' => self::TAX_RATE * 100 . '%',
                'revenue' => round($items->sum('revenue'), 2),
            ])
            ->sortKeys()
            ->values()
            ->all();

        $summary = [
            ['label' => 'Total subtotal', 'value' => $accrual['current']['subtotal'] ?? 0],
            ['label' => 'Total tax', 'value' => $accrual['current']['tax'] ?? 0],
            ['label' => 'Tax rate', 'value' => (self::TAX_RATE * 100) . '%'],
        ];

        if ($compareStart && $compareEnd) {
            $compare = $this->revenueAccrualService->calculate('total', null, $compareStart, $compareEnd, false);
            $summary[] = ['label' => 'Compare tax', 'value' => $compare['current']['tax'] ?? 0];
        }

        return $this->format(
            [
                ['key' => 'charge_date', 'label' => 'Date'],
                ['key' => 'subtotal', 'label' => 'Subtotal'],
                ['key' => 'tax', 'label' => 'Tax'],
                ['key' => 'tax_rate', 'label' => 'Rate'],
                ['key' => 'revenue', 'label' => 'Revenue incl. tax'],
            ],
            $byDate,
            $summary,
            array_merge(
                $this->periodMeta($start, $end, $compareStart, $compareEnd),
                ['Tax allocated proportionally across daily charges at ' . (self::TAX_RATE * 100) . '%.']
            )
        );
    }

    private function revpar(Carbon $start, Carbon $end, ?Carbon $compareStart, ?Carbon $compareEnd): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, false);
        $roomNights = (int) ($accrual['current']['count'] ?? 0);
        $revenue = round((float) ($accrual['current']['total'] ?? 0), 2);
        $subtotal = round((float) ($accrual['current']['subtotal'] ?? 0), 2);

        $roomCount = Room::query()
            ->where('active', 1)
            ->whereNotIn('roomStatus', [3, 4])
            ->count();

        $days = $start->diffInDays($end) + 1;
        $availableRoomNights = $roomCount * $days;

        $adr = $roomNights > 0 ? round($subtotal / $roomNights, 2) : 0.0;
        $revpar = $availableRoomNights > 0 ? round($revenue / $availableRoomNights, 2) : 0.0;

        $rows = [[
            'revenue' => $revenue,
            'subtotal' => $subtotal,
            'room_nights' => $roomNights,
            'room_count' => $roomCount,
            'days' => $days,
            'available_room_nights' => $availableRoomNights,
            'adr' => $adr,
            'revpar' => $revpar,
        ]];

        $summary = [
            ['label' => 'Revenue (incl. tax)', 'value' => $revenue],
            ['label' => 'ADR (subtotal / room nights)', 'value' => $adr],
            ['label' => 'RevPAR (revenue / available room nights)', 'value' => $revpar],
        ];

        if ($compareStart && $compareEnd) {
            $compare = $this->revenueAccrualService->calculate('total', null, $compareStart, $compareEnd, false);
            $compareNights = (int) ($compare['current']['count'] ?? 0);
            $compareRevenue = round((float) ($compare['current']['total'] ?? 0), 2);
            $compareDays = $compareStart->diffInDays($compareEnd) + 1;
            $compareAvail = $roomCount * $compareDays;
            $compareRevpar = $compareAvail > 0 ? round($compareRevenue / $compareAvail, 2) : 0.0;

            $summary[] = ['label' => 'Compare RevPAR', 'value' => $compareRevpar];
            $summary[] = ['label' => 'Compare room nights', 'value' => $compareNights];
        }

        return $this->format(
            [
                ['key' => 'revenue', 'label' => 'Revenue'],
                ['key' => 'subtotal', 'label' => 'Subtotal'],
                ['key' => 'room_nights', 'label' => 'Room nights sold'],
                ['key' => 'room_count', 'label' => 'Room count'],
                ['key' => 'days', 'label' => 'Days'],
                ['key' => 'available_room_nights', 'label' => 'Available room nights'],
                ['key' => 'adr', 'label' => 'ADR'],
                ['key' => 'revpar', 'label' => 'RevPAR'],
            ],
            $rows,
            $summary,
            $this->periodMeta($start, $end, $compareStart, $compareEnd)
        );
    }

    private function byDimension(Carbon $start, Carbon $end): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, true);
        $reservationIds = collect($accrual['details'] ?? [])->pluck('reservation_id')->unique();

        $reservations = Reservation::with(['reservation_source', 'stay_reason'])
            ->whereIn('id', $reservationIds)
            ->get()
            ->keyBy('id');

        $grouped = collect($accrual['details'] ?? [])
            ->groupBy(function (array $detail) use ($reservations) {
                $reservation = $reservations->get($detail['reservation_id']);

                return ($reservation?->reservation_source_id ?? 0) . '|' . ($reservation?->stay_reason_id ?? 0);
            })
            ->map(function (Collection $items, string $key) use ($reservations) {
                $first = $reservations->get($items->first()['reservation_id']);
                $source = $first?->reservation_source;
                $reason = $first?->stay_reason;

                return [
                    'reservation_source_id' => $first?->reservation_source_id,
                    'source_name' => $source?->name_en ?? $source?->name_ar ?? '—',
                    'stay_reason_id' => $first?->stay_reason_id,
                    'stay_reason_name' => $reason?->name_en ?? $reason?->name_ar ?? '—',
                    'room_nights' => $items->count(),
                    'base_amount' => round($items->sum('base_amount'), 2),
                    'revenue' => round($items->sum('revenue'), 2),
                    'reservation_count' => $items->pluck('reservation_id')->unique()->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        return $this->format(
            [
                ['key' => 'source_name', 'label' => 'Source'],
                ['key' => 'stay_reason_name', 'label' => 'Stay reason'],
                ['key' => 'reservation_count', 'label' => 'Reservations'],
                ['key' => 'room_nights', 'label' => 'Room nights'],
                ['key' => 'base_amount', 'label' => 'Base'],
                ['key' => 'revenue', 'label' => 'Revenue'],
            ],
            $grouped,
            [
                ['label' => 'Total revenue', 'value' => round(collect($grouped)->sum('revenue'), 2)],
                ['label' => 'Dimensions', 'value' => count($grouped)],
            ],
            $this->periodMeta($start, $end)
        );
    }

    private function peakAnalysis(Carbon $start, Carbon $end): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, true);
        $details = collect($accrual['details'] ?? []);

        $peak = $details->where('is_peak_day', true);
        $nonPeak = $details->where('is_peak_day', false);

        $rows = [
            [
                'segment' => 'Peak days',
                'room_nights' => $peak->count(),
                'base_amount' => round($peak->sum('base_amount'), 2),
                'revenue' => round($peak->sum('revenue'), 2),
                'share_pct' => $details->isNotEmpty()
                    ? round($peak->sum('revenue') / $details->sum('revenue') * 100, 1)
                    : 0.0,
            ],
            [
                'segment' => 'Non-peak days',
                'room_nights' => $nonPeak->count(),
                'base_amount' => round($nonPeak->sum('base_amount'), 2),
                'revenue' => round($nonPeak->sum('revenue'), 2),
                'share_pct' => $details->isNotEmpty()
                    ? round($nonPeak->sum('revenue') / $details->sum('revenue') * 100, 1)
                    : 0.0,
            ],
        ];

        return $this->format(
            [
                ['key' => 'segment', 'label' => 'Segment'],
                ['key' => 'room_nights', 'label' => 'Room nights'],
                ['key' => 'base_amount', 'label' => 'Base'],
                ['key' => 'revenue', 'label' => 'Revenue'],
                ['key' => 'share_pct', 'label' => 'Share %'],
            ],
            $rows,
            [
                ['label' => 'Peak revenue', 'value' => $rows[0]['revenue']],
                ['label' => 'Non-peak revenue', 'value' => $rows[1]['revenue']],
                ['label' => 'Total revenue', 'value' => round($details->sum('revenue'), 2)],
            ],
            array_merge(
                $this->periodMeta($start, $end),
                ['Peak classification from reservation_daily_charges.is_peak_day.']
            )
        );
    }

    private function paymentsRefunds(Carbon $start, Carbon $end): array
    {
        $payments = ReservationPay::with(['reservation.client', 'user'])
            ->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.reservation_status', Reservation::cashReportStatuses())
            ->whereBetween('reservation_pay.created_at', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->select('reservation_pay.*')
            ->orderByDesc('reservation_pay.created_at')
            ->get();

        $rows = $payments->map(function (ReservationPay $pay) {
            $reservation = $pay->reservation;

            return [
                'id' => $pay->id,
                'created_at' => $pay->created_at?->toDateTimeString(),
                'reservation_id' => $pay->reservation_id,
                'guest' => $this->guestName($reservation?->client),
                'type' => (int) $pay->type === ReservationPay::TYPE_REFUND ? 'Refund' : 'Payment',
                'amount' => round((float) $pay->pay, 2),
                'reservation_status' => $reservation
                    ? $this->reservationStatusLabel((int) $reservation->reservation_status)
                    : '—',
            ];
        })->values()->all();

        $cashIn = round(collect($rows)->where('type', 'Payment')->sum('amount'), 2);
        $cashOut = round(collect($rows)->where('type', 'Refund')->sum('amount'), 2);

        return $this->format(
            [
                ['key' => 'created_at', 'label' => 'Date/time'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'amount', 'label' => 'Amount'],
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
                ['key' => 'guest', 'label' => 'Guest'],
                ['key' => 'reservation_status', 'label' => 'Reservation status'],
            ],
            $rows,
            [
                ['label' => 'Transactions', 'value' => count($rows)],
                ['label' => 'Cash in', 'value' => $cashIn],
                ['label' => 'Cash out (refunds)', 'value' => $cashOut],
                ['label' => 'Net', 'value' => round($cashIn - $cashOut, 2)],
            ],
            array_merge(
                $this->periodMeta($start, $end),
                ['Filtered by reservation_pay.created_at; includes confirmed and pending reservations.']
            )
        );
    }

    private function closingPackage(Carbon $start, Carbon $end): array
    {
        $accrual = $this->revenueAccrualService->calculate('total', null, $start, $end, false);
        $cash = $this->cashForPeriod($start, $end);
        $arTotal = $this->totalAccountsReceivable($end);
        $taxTotal = round((float) ($accrual['current']['tax'] ?? 0), 2);
        $accrualTotal = round((float) ($accrual['current']['total'] ?? 0), 2);
        $cashNet = round($cash['net_earnings'], 2);

        $rows = [
            ['metric' => 'Accrual revenue (incl. tax)', 'amount' => $accrualTotal],
            ['metric' => 'Accrual subtotal (pre-tax)', 'amount' => round((float) ($accrual['current']['subtotal'] ?? 0), 2)],
            ['metric' => 'Tax (accrual)', 'amount' => $taxTotal],
            ['metric' => 'Cash in', 'amount' => round($cash['total_in'], 2)],
            ['metric' => 'Cash out (refunds)', 'amount' => round($cash['total_out'], 2)],
            ['metric' => 'Cash net', 'amount' => $cashNet],
            ['metric' => 'Accounts receivable (open balances)', 'amount' => $arTotal],
            ['metric' => 'Accrual − cash net', 'amount' => round($accrualTotal - $cashNet, 2)],
        ];

        return $this->format(
            [
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'amount', 'label' => 'Amount'],
            ],
            $rows,
            [
                ['label' => 'Accrual revenue', 'value' => $accrualTotal],
                ['label' => 'Cash net', 'value' => $cashNet],
                ['label' => 'AR outstanding', 'value' => $arTotal],
                ['label' => 'Tax', 'value' => $taxTotal],
            ],
            array_merge(
                $this->periodMeta($start, $end),
                ['Closing package combines accrual, cash, AR, and tax for period-end review.']
            )
        );
    }

    private function generalLedger(Carbon $start, Carbon $end, array $params): array
    {
        $accountId = !empty($params['account_id']) ? (int) $params['account_id'] : null;
        $data = $this->financialStatementService->generalLedger($start, $end, $accountId);

        $rows = collect($data['lines'])->map(fn (array $line) => [
            'entry_date' => $line['entry_date'],
            'reference' => $line['reference'] ?? '—',
            'account_code' => $line['account_code'],
            'account_name' => $line['account_name'],
            'description' => $line['description'] ?? $line['memo'] ?? '—',
            'debit' => $line['debit'],
            'credit' => $line['credit'],
            'reservation_id' => $line['reservation_id'],
        ])->all();

        return $this->format(
            [
                ['key' => 'entry_date', 'label' => 'Date'],
                ['key' => 'reference', 'label' => 'Reference'],
                ['key' => 'account_code', 'label' => 'Account'],
                ['key' => 'account_name', 'label' => 'Account name'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'debit', 'label' => 'Debit'],
                ['key' => 'credit', 'label' => 'Credit'],
                ['key' => 'reservation_id', 'label' => 'Reservation #'],
            ],
            $rows,
            [
                ['label' => 'Total debit', 'value' => $data['totals']['debit']],
                ['label' => 'Total credit', 'value' => $data['totals']['credit']],
                ['label' => 'Lines', 'value' => count($rows)],
            ],
            $this->periodMeta($start, $end)
        );
    }

    private function trialBalance(Carbon $start, Carbon $end): array
    {
        $data = $this->financialStatementService->trialBalance($start, $end);

        $rows = collect($data['accounts'])->map(fn (array $a) => [
            'code' => $a['code'],
            'name' => $a['name'],
            'type' => $a['type'],
            'debit' => $a['debit'],
            'credit' => $a['credit'],
            'balance' => $a['balance'],
        ])->all();

        return $this->format(
            [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Account'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'debit', 'label' => 'Debit'],
                ['key' => 'credit', 'label' => 'Credit'],
                ['key' => 'balance', 'label' => 'Balance'],
            ],
            $rows,
            [
                ['label' => 'Total debit', 'value' => $data['totals']['debit']],
                ['label' => 'Total credit', 'value' => $data['totals']['credit']],
                ['label' => 'Balanced', 'value' => $data['totals']['balanced'] ? 'Yes' : 'No'],
            ],
            $this->periodMeta($start, $end)
        );
    }

    private function balanceSheet(Carbon $asOf): array
    {
        $data = $this->financialStatementService->balanceSheet($asOf);

        $rows = [];

        foreach ($data['assets'] as $item) {
            $rows[] = ['section' => 'Assets', 'code' => $item['code'], 'name' => $item['name'], 'balance' => $item['balance']];
        }

        foreach ($data['liabilities'] as $item) {
            $rows[] = ['section' => 'Liabilities', 'code' => $item['code'], 'name' => $item['name'], 'balance' => $item['balance']];
        }

        foreach ($data['equity'] as $item) {
            $rows[] = ['section' => 'Equity', 'code' => $item['code'], 'name' => $item['name'], 'balance' => $item['balance']];
        }

        return $this->format(
            [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Account'],
                ['key' => 'balance', 'label' => 'Balance'],
            ],
            $rows,
            [
                ['label' => 'Total assets', 'value' => $data['totals']['assets']],
                ['label' => 'Total liabilities', 'value' => $data['totals']['liabilities']],
                ['label' => 'Total equity', 'value' => $data['totals']['equity']],
                ['label' => 'Balanced', 'value' => $data['totals']['balanced'] ? 'Yes' : 'No'],
            ],
            ["As of: {$asOf->toDateString()}"]
        );
    }

    private function cashFlow(Carbon $start, Carbon $end): array
    {
        $data = $this->financialStatementService->cashFlow($start, $end);

        $rows = collect($data['accounts'])->map(fn (array $a) => [
            'code' => $a['code'],
            'name' => $a['name'],
            'inflow' => $a['inflow'],
            'outflow' => $a['outflow'],
            'net' => $a['net'],
        ])->all();

        return $this->format(
            [
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'name', 'label' => 'Cash account'],
                ['key' => 'inflow', 'label' => 'Inflow (debit)'],
                ['key' => 'outflow', 'label' => 'Outflow (credit)'],
                ['key' => 'net', 'label' => 'Net change'],
            ],
            $rows,
            [
                ['label' => 'Total inflow', 'value' => $data['totals']['inflow']],
                ['label' => 'Total outflow', 'value' => $data['totals']['outflow']],
                ['label' => 'Net change', 'value' => $data['totals']['net_change']],
            ],
            array_merge(
                $this->periodMeta($start, $end),
                ['Cash flow from journal entries on cash/bank asset accounts.']
            )
        );
    }

    /**
     * @return array{total_in: float, total_out: float, net_earnings: float}
     */
    private function cashForPeriod(Carbon $start, Carbon $end): array
    {
        $totalIn = (float) ReservationPay::query()
            ->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.reservation_status', Reservation::cashReportStatuses())
            ->whereBetween('reservation_pay.created_at', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->where('reservation_pay.type', ReservationPay::TYPE_PAYMENT)
            ->sum('reservation_pay.pay');

        $totalOut = (float) ReservationPay::query()
            ->join('reservations', 'reservation_pay.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.reservation_status', Reservation::cashReportStatuses())
            ->whereBetween('reservation_pay.created_at', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->where('reservation_pay.type', ReservationPay::TYPE_REFUND)
            ->sum('reservation_pay.pay');

        return [
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'net_earnings' => $totalIn - $totalOut,
        ];
    }

    private function totalAccountsReceivable(Carbon $asOf): float
    {
        $total = 0.0;

        Reservation::with('payments')
            ->where('reservation_status', 1)
            ->get()
            ->each(function (Reservation $reservation) use (&$total) {
                $total += $this->reservationBalanceDue($reservation);
            });

        return round($total, 2);
    }

    private function reservationBalanceDue(Reservation $reservation): float
    {
        $total = (float) $reservation->total;

        if ($total <= 0 && (float) $reservation->subtotal > 0) {
            $total = round((float) $reservation->subtotal * (1 + self::TAX_RATE), 2);
        }

        return max(0, $total - $this->reservationPaidNet($reservation));
    }

    private function reservationPaidNet(Reservation $reservation): float
    {
        $paid = (float) $reservation->payments
            ->where('type', ReservationPay::TYPE_PAYMENT)
            ->sum('pay');
        $refunded = (float) $reservation->payments
            ->where('type', ReservationPay::TYPE_REFUND)
            ->sum('pay');

        return $paid - $refunded;
    }

    private function arDaysOverdue(Reservation $reservation, Carbon $asOf): int
    {
        $expire = Carbon::parse($reservation->expire_date);

        if ($expire->gte($asOf)) {
            return 0;
        }

        return (int) $expire->diffInDays($asOf);
    }

    private function arBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 30 => '0-30',
            $daysOverdue <= 60 => '31-60',
            $daysOverdue <= 90 => '61-90',
            default => '90+',
        };
    }

    private function financialAuditLog(Carbon $start, Carbon $end): array
    {
        $rows = $this->financialAuditService->listForPeriod(
            $start->toDateString(),
            $end->toDateString()
        );

        return [
            'columns' => [
                ['key' => 'created_at', 'label' => 'Date/Time'],
                ['key' => 'action', 'label' => 'Action'],
                ['key' => 'entity_type', 'label' => 'Entity'],
                ['key' => 'entity_id', 'label' => 'Entity ID'],
                ['key' => 'user_id', 'label' => 'User ID'],
                ['key' => 'details', 'label' => 'Details'],
            ],
            'rows' => array_map(function ($row) {
                $row['details'] = is_array($row['details'])
                    ? json_encode($row['details'], JSON_UNESCAPED_UNICODE)
                    : ($row['details'] ?? '');
                return $row;
            }, $rows),
            'summary' => [
                ['label' => 'Total events', 'value' => count($rows)],
            ],
        ];
    }

    private function guestName(?Client $client): string
    {
        if (!$client) {
            return '—';
        }

        return trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) ?: '—';
    }

    private function reservationStatusLabel(int $status): string
    {
        return match ($status) {
            Reservation::STATUS_CONFIRMED => 'Confirmed',
            Reservation::STATUS_PENDING_PAYMENT => 'Pending payment',
            Reservation::STATUS_CANCELLED => 'Cancelled',
            default => "Status {$status}",
        };
    }
}
