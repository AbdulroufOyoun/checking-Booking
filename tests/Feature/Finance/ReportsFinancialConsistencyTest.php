<?php

namespace Tests\Feature\Finance;

use App\Services\RevenueAccrualService;
use Carbon\Carbon;
use Database\Seeders\ReservationTestDataSeeder;
use Tests\Feature\Reports\AllReportsSlugTest;
use Tests\Support\FinanceAssertions;
use Tests\TestCase;

class ReportsFinancialConsistencyTest extends TestCase
{
    use FinanceAssertions;

    private function seededUser(): \App\Models\User
    {
        $this->artisan('db:seed', [
            '--class' => ReservationTestDataSeeder::class,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        return $this->userWithApiPermissions([
            'view reports', 'view financial reports', 'view accounting reports',
            'view revenue', 'view earnings',
        ]);
    }

    /** @dataProvider financialReportSlugProvider */
    public function test_financial_report_slugs_match_accrual_service(string $slug): void
    {
        $user = $this->seededUser();
        $start = '2026-08-01';
        $end = '2026-08-31';

        $response = $this->actingAs($user, 'api')->getJson(
            "/api/users/reports/{$slug}?start_date={$start}&end_date={$end}"
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('columns', $data);
        $this->assertArrayHasKey('rows', $data);

        $service = app(RevenueAccrualService::class);
        $accrual = $service->calculate('total', null, Carbon::parse($start), Carbon::parse($end), false);
        $expectedRevenue = round((float) $accrual['current']['total'], 2);
        $expectedTax = round((float) $accrual['current']['tax'], 2);
        $expectedNights = (int) $accrual['current']['count'];

        if ($slug === 'overview') {
            $summaryRevenue = $this->reportSummaryValue($data, 'Accrual revenue');
            $this->assertEqualsWithDelta($expectedRevenue, $summaryRevenue ?? 0, 0.05);
        }

        if ($slug === 'accrual-revenue') {
            $reportRevenue = $this->reportSummaryValue($data, 'Total revenue');
            $reportTax = $this->reportSummaryValue($data, 'Total tax');
            $reportNights = $this->reportSummaryValue($data, 'Room nights');

            $this->assertEqualsWithDelta($expectedRevenue, $reportRevenue ?? 0, 0.05);
            $this->assertEqualsWithDelta($expectedTax, $reportTax ?? 0, 0.05);
            $this->assertEquals($expectedNights, (int) ($reportNights ?? 0));

            $rowRevenue = round(collect($data['rows'] ?? [])->sum(fn ($r) => (float) ($r['revenue'] ?? 0)), 2);
            $this->assertEqualsWithDelta($expectedRevenue, $rowRevenue, 0.10);
        }

        if ($slug === 'revenue-summary') {
            $reportRevenue = $this->reportSummaryValue($data, 'Total revenue');
            $this->assertEqualsWithDelta($expectedRevenue, $reportRevenue ?? 0, 0.05);
        }

        if ($slug === 'tax') {
            $reportTax = $this->reportSummaryValue($data, 'Total tax');
            if ($reportTax !== null) {
                $this->assertEqualsWithDelta($expectedTax, $reportTax, 0.10);
            }
        }
    }

    public static function financialReportSlugProvider(): array
    {
        $financial = [
            'overview',
            'accrual-revenue',
            'cash-box',
            'revenue-summary',
            'accrual-cash-reconciliation',
            'tax',
            'payments-refunds',
            'adjustments',
            'by-dimension',
            'peak-analysis',
            'revpar',
            'trial-balance',
            'balance-sheet',
            'cash-flow',
            'general-ledger',
            'financial-audit-log',
            'closing-package',
        ];

        return array_map(fn ($slug) => [$slug], $financial);
    }

    public function test_all_report_slugs_still_valid(): void
    {
        $provider = AllReportsSlugTest::reportSlugProvider();
        $this->assertGreaterThanOrEqual(20, count($provider));
    }
}
