<?php

namespace Tests\Feature\Reports;

use Tests\TestCase;

class ReportApiTest extends TestCase
{
    public function test_reports_catalog_requires_auth(): void
    {
        $response = $this->getJson('/api/users/reports/catalog');
        $response->assertStatus(401);
    }

    public function test_revenue_summary_report(): void
    {
        $user = $this->userWithApiPermissions(['view reports', 'view financial reports']);
        $response = $this->actingAs($user, 'api')->getJson(
            '/api/users/reports/revenue-summary?start_date=2026-08-01&end_date=2026-08-31'
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertArrayHasKey('columns', $response->json('data'));
        $this->assertArrayHasKey('rows', $response->json('data'));
    }

    public function test_trial_balance_report(): void
    {
        $user = $this->userWithApiPermissions(['view reports', 'view accounting reports']);
        $response = $this->actingAs($user, 'api')->getJson(
            '/api/users/reports/trial-balance?start_date=2026-08-01&end_date=2026-08-31'
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_chart_of_accounts(): void
    {
        $user = $this->userWithApiPermissions(['view accounting reports']);
        $response = $this->actingAs($user, 'api')->getJson('/api/users/accounting/chart-of-accounts');
        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_financial_audit_log_report(): void
    {
        $user = $this->userWithApiPermissions(['view reports', 'view accounting reports']);
        $response = $this->actingAs($user, 'api')->getJson(
            '/api/users/reports/financial-audit-log?start_date=2026-08-01&end_date=2026-08-31'
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }
}
