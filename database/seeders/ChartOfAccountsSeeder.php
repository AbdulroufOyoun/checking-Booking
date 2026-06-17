<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '1010', 'name_en' => 'Cash / Bank', 'name_ar' => 'النقدية / البنك', 'type' => 'asset'],
            ['code' => '1100', 'name_en' => 'Accounts Receivable', 'name_ar' => 'ذمم مدينة', 'type' => 'asset'],
            ['code' => '1200', 'name_en' => 'Prepaid Expenses', 'name_ar' => 'مصروفات مدفوعة مقدماً', 'type' => 'asset'],
            ['code' => '2100', 'name_en' => 'Unearned Revenue', 'name_ar' => 'إيراد غير مكتسب', 'type' => 'liability'],
            ['code' => '2150', 'name_en' => 'VAT Payable', 'name_ar' => 'ضريبة مستحقة', 'type' => 'liability'],
            ['code' => '3000', 'name_en' => 'Retained Earnings', 'name_ar' => 'أرباح محتجزة', 'type' => 'equity'],
            ['code' => '4010', 'name_en' => 'Room Revenue', 'name_ar' => 'إيراد الغرف', 'type' => 'revenue'],
            ['code' => '4020', 'name_en' => 'Extras Revenue', 'name_ar' => 'إيراد الإضافات', 'type' => 'revenue'],
            ['code' => '5010', 'name_en' => 'Discounts', 'name_ar' => 'خصومات', 'type' => 'expense'],
            ['code' => '5020', 'name_en' => 'Refunds', 'name_ar' => 'مرتجعات', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::updateOrCreate(['code' => $account['code']], $account + ['active' => true]);
        }
    }
}
