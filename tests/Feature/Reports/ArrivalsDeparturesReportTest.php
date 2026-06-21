<?php

namespace Tests\Feature\Reports;

use App\Models\Reservation;
use App\Services\Reports\ReportQueryService;
use Tests\TestCase;

class ArrivalsDeparturesReportTest extends TestCase
{
    public function test_date_range_row_count_matches_reservations_table_overlap(): void
    {
        $start = '2026-06-01';
        $end = '2026-06-30';

        $tableCount = Reservation::excludingCancelled()
            ->where('start_date', '<=', $end)
            ->where('expire_date', '>=', $start)
            ->count();

        $report = app(ReportQueryService::class)->run('arrivals-departures', [
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $this->assertCount($tableCount, $report['rows']);

        $summary = collect($report['summary'])->pluck('value', 'label');
        $this->assertSame($tableCount, $summary->get('Reservations'));
    }

    public function test_single_day_includes_stayover_movements(): void
    {
        $day = '2026-06-16';

        $report = app(ReportQueryService::class)->run('arrivals-departures', [
            'start_date' => $day,
            'end_date' => $day,
        ]);

        $types = collect($report['rows'])->pluck('movement_type')->all();
        $this->assertContains('Stayover', $types);
    }

    public function test_pending_payment_reservation_included_in_period_report(): void
    {
        $pending = Reservation::excludingCancelled()
            ->where('reservation_status', Reservation::STATUS_PENDING_PAYMENT)
            ->where('start_date', '<=', '2026-06-30')
            ->where('expire_date', '>=', '2026-06-01')
            ->first();

        if ($pending === null) {
            $this->markTestSkipped('No pending-payment reservation overlapping June 2026 in seed data.');
        }

        $report = app(ReportQueryService::class)->run('arrivals-departures', [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);

        $ids = collect($report['rows'])->pluck('reservation_id');
        $this->assertTrue($ids->contains($pending->id));
    }
}
