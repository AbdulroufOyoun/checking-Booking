<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ReservationPay;

class AccountingPostingService
{
    public function createManualEntry(array $payload, ?int $userId): JournalEntry
    {
        $entry = JournalEntry::create([
            'entry_date' => $payload['entry_date'],
            'reference' => $payload['reference'] ?? null,
            'description' => $payload['description'] ?? null,
            'user_id' => $userId,
            'source_type' => 'manual',
        ]);

        foreach ($payload['lines'] as $line) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $line['account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'memo' => $line['memo'] ?? null,
            ]);
        }

        return $entry->load('lines');
    }

    public function postPayment(ReservationPay $payment): ?JournalEntry
    {
        $ref = 'PAY-' . $payment->id;
        $existing = JournalEntry::where('reference', $ref)->first();
        if ($existing) {
            return $existing;
        }

        $cash = ChartOfAccount::where('code', '1010')->first();
        $ar = ChartOfAccount::where('code', '1100')->first();
        if (!$cash || !$ar) {
            return null;
        }

        $amount = (float) $payment->pay;
        if ($amount <= 0) {
            return null;
        }

        $isPayment = (int) $payment->type === ReservationPay::TYPE_PAYMENT;
        $lines = $isPayment
            ? [
                ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $ar->id, 'debit' => 0, 'credit' => $amount],
            ]
            : [
                ['account_id' => $ar->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount],
            ];

        $entry = JournalEntry::create([
            'entry_date' => $payment->created_at?->toDateString() ?? now()->toDateString(),
            'reference' => $ref,
            'description' => $isPayment ? 'Guest payment' : 'Guest refund',
            'user_id' => $payment->user_id,
            'source_type' => 'reservation_pay',
            'source_id' => $payment->id,
        ]);

        foreach ($lines as $line) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $line['account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'reservation_id' => $payment->reservation_id,
                'memo' => $isPayment ? 'Payment' : 'Refund',
            ]);
        }

        return $entry->load('lines');
    }
}
