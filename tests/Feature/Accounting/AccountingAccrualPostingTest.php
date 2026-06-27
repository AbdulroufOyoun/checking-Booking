<?php

namespace Tests\Feature\Accounting;

use App\Services\Accounting\AccountingPostingService;
use App\Services\Accounting\FinancialStatementService;
use App\Services\RevenueAccrualService;
use Carbon\Carbon;
use Tests\Support\FinanceTestBootstrap;
use Tests\TestCase;

class AccountingAccrualPostingTest extends TestCase
{
    use FinanceTestBootstrap;

    public function test_accrual_gl_matches_revenue_accrual_service_for_august_2026(): void
    {
        $this->bootstrapFinanceData();

        $start = Carbon::parse(self::FINANCE_PERIOD_START);
        $end = Carbon::parse(self::FINANCE_PERIOD_END);

        $accrual = app(RevenueAccrualService::class)->calculate('total', null, $start, $end, false);
        $expectedSubtotal = round((float) $accrual['current']['subtotal'], 2);
        $expectedTax = round((float) $accrual['current']['tax'], 2);
        $expectedRevenue = round((float) $accrual['current']['total'], 2);

        $balances = app(FinancialStatementService::class)->accountBalances($start, $end);
        $byCode = $balances->keyBy('code');

        $roomRevenue = round((float) ($byCode->get('4010')->balance ?? 0), 2);
        $vat = round((float) ($byCode->get('2150')->balance ?? 0), 2);
        $ar = round((float) ($byCode->get('1100')->balance ?? 0), 2);

        $this->assertEqualsWithDelta($expectedSubtotal, $roomRevenue, 0.15, 'GL room revenue vs accrual subtotal');
        $this->assertEqualsWithDelta($expectedTax, $vat, 0.15, 'GL VAT vs accrual tax');

        $trial = app(FinancialStatementService::class)->trialBalance($start, $end);
        $this->assertTrue($trial['totals']['balanced'], 'Trial balance must balance for August 2026');

        $cashNet = round((float) ($byCode->get('1010')->balance ?? 0), 2);
        $this->assertGreaterThan(0, $expectedRevenue, 'Fixture should have August accrual');
        $this->assertNotEquals($ar, $expectedRevenue, 'AR net includes payments; should differ from gross accrual when cash collected');
        $this->assertIsFloat($cashNet);
    }

    public function test_post_daily_charge_accrual_is_idempotent(): void
    {
        $this->bootstrapFinanceData();

        $charge = \App\Models\ReservationDailyCharge::query()
            ->join('reservations', 'reservation_daily_charges.reservation_id', '=', 'reservations.id')
            ->where('reservations.reservation_status', 1)
            ->select('reservation_daily_charges.*')
            ->first();

        $this->assertNotNull($charge);

        $posting = app(AccountingPostingService::class);
        $first = $posting->postDailyChargeAccrual($charge);
        $second = $posting->postDailyChargeAccrual($charge);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
    }
}
