<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use App\Models\ReservationPay;

class AccountingPostingService
{
    private const TAX_RATE = 0.15;

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

    /**
     * Post accrual journal for one daily charge (Dr AR / Cr Revenue / Cr VAT).
     */
    public function postDailyChargeAccrual(ReservationDailyCharge $charge): ?JournalEntry
    {
        $reservation = $charge->reservation ?? Reservation::find($charge->reservation_id);
        if (!$reservation || (int) $reservation->reservation_status !== 1) {
            return null;
        }

        $ref = $this->accrualReference($charge);
        $existing = JournalEntry::where('reference', $ref)->first();
        if ($existing) {
            return $existing;
        }

        $amounts = $this->allocateChargeAmounts($charge, $reservation);
        if ($amounts['revenue'] <= 0) {
            return null;
        }

        $ar = ChartOfAccount::where('code', '1100')->first();
        $revenue = ChartOfAccount::where('code', '4010')->first();
        $vat = ChartOfAccount::where('code', '2150')->first();
        if (!$ar || !$revenue || !$vat) {
            return null;
        }

        $entry = JournalEntry::create([
            'entry_date' => $charge->charge_date->toDateString(),
            'reference' => $ref,
            'description' => 'Accrual revenue — reservation #' . $charge->reservation_id,
            'user_id' => null,
            'source_type' => 'daily_charge',
            'source_id' => $charge->id,
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $ar->id,
            'debit' => $amounts['revenue'],
            'credit' => 0,
            'reservation_id' => $charge->reservation_id,
            'memo' => 'AR accrual',
        ]);

        if ($amounts['subtotal'] > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $revenue->id,
                'debit' => 0,
                'credit' => $amounts['subtotal'],
                'reservation_id' => $charge->reservation_id,
                'memo' => 'Room revenue',
            ]);
        }

        if ($amounts['tax'] > 0) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $vat->id,
                'debit' => 0,
                'credit' => $amounts['tax'],
                'reservation_id' => $charge->reservation_id,
                'memo' => 'VAT',
            ]);
        }

        return $entry->load('lines');
    }

    public function syncAccrualForReservationRoom(int $reservationRoomId): int
    {
        $prefix = 'ACCRUAL-RR' . $reservationRoomId . '-';
        $entries = JournalEntry::where('reference', 'like', $prefix . '%')->get();
        foreach ($entries as $entry) {
            $entry->lines()->delete();
            $entry->delete();
        }

        $charges = ReservationDailyCharge::where('reservation_room_id', $reservationRoomId)
            ->with('reservation')
            ->orderBy('charge_date')
            ->get();

        $count = 0;
        foreach ($charges as $charge) {
            if ($this->postDailyChargeAccrual($charge)) {
                $count++;
            }
        }

        return $count;
    }

    public function backfillAllAccruals(): int
    {
        JournalEntry::query()
            ->where(function ($query) {
                $query->where('source_type', 'daily_charge')
                    ->orWhere('reference', 'like', 'ACCRUAL-RR%');
            })
            ->each(function (JournalEntry $entry) {
                $entry->lines()->delete();
                $entry->delete();
            });

        $count = 0;
        ReservationDailyCharge::query()
            ->join('reservations', 'reservation_daily_charges.reservation_id', '=', 'reservations.id')
            ->where('reservations.reservation_status', 1)
            ->select('reservation_daily_charges.*')
            ->orderBy('reservation_daily_charges.id')
            ->chunk(200, function ($charges) use (&$count) {
                foreach ($charges as $charge) {
                    if ($this->postDailyChargeAccrual($charge)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * @return array{subtotal: float, tax: float, revenue: float}
     */
    public function allocateChargeAmounts(ReservationDailyCharge $charge, Reservation $reservation): array
    {
        $reservationTotalBase = (float) ReservationDailyCharge::where('reservation_id', $reservation->id)
            ->sum('base_amount');

        $weight = $reservationTotalBase > 0
            ? (float) $charge->base_amount / $reservationTotalBase
            : 0;

        $periodSubtotal = (float) $charge->base_amount
            - (float) $reservation->discount * $weight
            + (float) $reservation->extras * $weight
            + (float) $reservation->penalties * $weight;

        $periodTax = round($periodSubtotal * self::TAX_RATE, 2);
        $periodRevenue = round($periodSubtotal + $periodTax, 2);

        return [
            'subtotal' => round($periodSubtotal, 2),
            'tax' => $periodTax,
            'revenue' => $periodRevenue,
        ];
    }

    private function accrualReference(ReservationDailyCharge $charge): string
    {
        $date = $charge->charge_date instanceof \Carbon\Carbon
            ? $charge->charge_date->format('Y-m-d')
            : (string) $charge->charge_date;

        return 'ACCRUAL-RR' . $charge->reservation_room_id . '-' . $date;
    }
}
