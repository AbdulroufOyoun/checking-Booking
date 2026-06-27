<?php

namespace App\Support;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ReservationDailyCharge;
use App\Models\ReservationPay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationRelatedCleanup
{
    /**
     * Remove accounting rows tied to reservations before deleting reservation rows.
     *
     * @param  Collection<int, int>|array<int, int>  $reservationIds
     */
    public static function purgeAccounting(Collection|array $reservationIds): int
    {
        $ids = collect($reservationIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        $payIds = ReservationPay::query()->whereIn('reservation_id', $ids)->pluck('id');
        $chargeIds = ReservationDailyCharge::query()->whereIn('reservation_id', $ids)->pluck('id');

        $entryIds = JournalEntryLine::query()
            ->whereIn('reservation_id', $ids)
            ->pluck('journal_entry_id');

        if ($payIds->isNotEmpty()) {
            $entryIds = $entryIds->merge(
                JournalEntry::query()
                    ->where('source_type', 'reservation_pay')
                    ->whereIn('source_id', $payIds)
                    ->pluck('id')
            );
        }

        if ($chargeIds->isNotEmpty()) {
            $entryIds = $entryIds->merge(
                JournalEntry::query()
                    ->where('source_type', 'daily_charge')
                    ->whereIn('source_id', $chargeIds)
                    ->pluck('id')
            );
        }

        $entryIds = $entryIds->unique()->values();
        $removed = 0;

        if ($entryIds->isNotEmpty()) {
            $removed += JournalEntryLine::query()->whereIn('journal_entry_id', $entryIds)->delete();
            $removed += JournalEntry::query()->whereIn('id', $entryIds)->delete();
        }

        $removed += JournalEntryLine::query()
            ->whereIn('reservation_id', $ids)
            ->delete();

        return $removed;
    }

    /**
     * Delete journal lines whose reservation_id points to a missing reservation.
     * Removes journal entries left without lines.
     */
    public static function repairOrphanJournalLines(): array
    {
        $orphanLineIds = DB::table('journal_entry_lines')
            ->leftJoin('reservations', 'journal_entry_lines.reservation_id', '=', 'reservations.id')
            ->whereNotNull('journal_entry_lines.reservation_id')
            ->whereNull('reservations.id')
            ->pluck('journal_entry_lines.id');

        $linesDeleted = 0;
        if ($orphanLineIds->isNotEmpty()) {
            $linesDeleted = JournalEntryLine::query()->whereIn('id', $orphanLineIds)->delete();
        }

        $emptyEntries = JournalEntry::query()
            ->whereDoesntHave('lines')
            ->pluck('id');

        $entriesDeleted = 0;
        if ($emptyEntries->isNotEmpty()) {
            $entriesDeleted = JournalEntry::query()->whereIn('id', $emptyEntries)->delete();
        }

        return [
            'lines_deleted' => $linesDeleted,
            'entries_deleted' => $entriesDeleted,
        ];
    }

    public static function countOrphanJournalLines(): int
    {
        return (int) DB::table('journal_entry_lines')
            ->leftJoin('reservations', 'journal_entry_lines.reservation_id', '=', 'reservations.id')
            ->whereNotNull('journal_entry_lines.reservation_id')
            ->whereNull('reservations.id')
            ->count();
    }
}
