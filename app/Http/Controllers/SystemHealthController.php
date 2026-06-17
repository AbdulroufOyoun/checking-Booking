<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationDailyCharge;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemHealthController extends Controller
{
    public function index()
    {
        try {
            $checks = [];

            $checks[] = $this->check('database', function () {
                DB::connection()->getPdo();

                return 'Connected';
            });

            $checks[] = $this->check('reservations_table', function () {
                if (!Schema::hasTable('reservations')) {
                    throw new \RuntimeException('Missing reservations table');
                }

                return Reservation::count() . ' rows';
            });

            $checks[] = $this->check('daily_charges_table', function () {
                if (!Schema::hasTable('reservation_daily_charges')) {
                    throw new \RuntimeException('Missing reservation_daily_charges table');
                }

                return ReservationDailyCharge::count() . ' rows';
            });

            $failed = collect($checks)->where('status', 'fail')->count();

            return \SuccessData('System health', [
                'healthy' => $failed === 0,
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    private function check(string $name, callable $fn): array
    {
        try {
            $message = $fn();

            return ['name' => $name, 'status' => 'pass', 'message' => (string) $message];
        } catch (\Throwable $e) {
            return ['name' => $name, 'status' => 'fail', 'message' => $e->getMessage()];
        }
    }
}
