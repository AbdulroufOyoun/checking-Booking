<?php

namespace App\Console\Commands;

use App\Models\ReservationPay;
use App\Services\Accounting\AccountingPostingService;
use Illuminate\Console\Command;

class BackfillJournalCommand extends Command
{
    protected $signature = 'accounting:backfill-journal {--dry-run : List payments without posting}';

    protected $description = 'Backfill journal entries from historical reservation payments and refunds';

    public function handle(AccountingPostingService $postingService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;
        $skipped = 0;

        ReservationPay::orderBy('id')->chunk(100, function ($payments) use ($postingService, $dryRun, &$count, &$skipped) {
            foreach ($payments as $payment) {
                if ($dryRun) {
                    $ref = 'PAY-' . $payment->id;
                    if (\App\Models\JournalEntry::where('reference', $ref)->exists()) {
                        $skipped++;
                    } else {
                        $count++;
                        $this->line("Would post payment #{$payment->id} ({$payment->pay})");
                    }
                    continue;
                }

                $entry = $postingService->postPayment($payment);
                if ($entry) {
                    $count++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info($dryRun
            ? "Dry run: {$count} would be posted, {$skipped} already exist or skipped."
            : "Posted {$count} journal entries, skipped {$skipped}.");

        return self::SUCCESS;
    }
}
