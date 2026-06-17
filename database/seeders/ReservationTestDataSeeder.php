<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\PeakDay;
use App\Models\Pricingplan;
use App\Models\Reservation;
use App\Models\ReservationPay;
use App\Models\ReservationRoom;
use App\Models\Reservation_source;
use App\Models\ReservationDailyCharge;
use App\Models\Room;
use App\Models\RoomPrice;
use App\Models\RoomtypePricingplan;
use App\Models\RoomType;
use App\Models\Stay_reason;
use App\Models\User;
use App\Services\PricingEngine;
use App\Services\RevenueAccrualService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test reservations for financial / revenue reports.
 *
 * Run alone:  php artisan db:seed --class=ReservationTestDataSeeder
 * With all:   php artisan db:seed
 */
class ReservationTestDataSeeder extends Seeder
{
    private const TEST_YEAR = 2026;

    public function run(): void
    {
        $this->command?->info('Seeding test reservations...');

        $pricingEngine = app(PricingEngine::class);
        $revenueAccrual = app(RevenueAccrualService::class);

        $userId = User::first()?->id ?? 1;
        $stayReasonId = $this->ensureStayReason();
        $sourceId = $this->ensureReservationSource();
        $clients = $this->ensureClients();
        $roomType = $this->ensureRoomType();
        $this->ensurePricingPlan($roomType);
        $rooms = $this->ensureRooms($roomType);

        $this->enableFridayPeak();

        $shouldClear = app()->environment('testing')
            || (bool) env('SEED_RESERVATIONS_FRESH', false)
            || (bool) env('DEMO_SEED', false)
            || ($this->command && !$this->command->option('no-interaction')
                && $this->command->confirm('Remove existing test reservations (' . self::TEST_YEAR . ')?', true));

        if ($shouldClear) {
            $this->clearTestReservations();
        }

        $scenarios = [
            ['start' => '2026-05-10', 'end' => '2026-05-17', 'rent_type' => 0, 'discount' => 0, 'pay' => 'full', 'status' => 1],
            ['start' => '2026-06-01', 'end' => '2026-06-08', 'rent_type' => 0, 'discount' => 100, 'pay' => 'full', 'status' => 1],
            ['start' => '2026-06-12', 'end' => '2026-06-19', 'rent_type' => 0, 'discount' => 0, 'pay' => 'partial', 'status' => 2],
            ['start' => '2026-06-20', 'end' => '2026-07-04', 'rent_type' => 0, 'discount' => 50, 'pay' => 'full', 'status' => 1],
            ['start' => '2026-07-10', 'end' => '2026-07-17', 'rent_type' => 0, 'discount' => 0, 'pay' => 'full', 'status' => 1],
            ['start' => '2026-07-26', 'end' => '2026-08-09', 'rent_type' => 0, 'discount' => 0, 'pay' => 'full', 'status' => 1],
            ['start' => '2026-08-01', 'end' => '2026-08-15', 'rent_type' => 0, 'discount' => 200, 'pay' => 'full', 'status' => 1],
            ['start' => '2026-08-20', 'end' => '2026-09-03', 'rent_type' => 0, 'discount' => 0, 'pay' => 'none', 'status' => 2],
        ];

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $scenarios,
            $rooms,
            $clients,
            $userId,
            $stayReasonId,
            $sourceId,
            $pricingEngine,
            $revenueAccrual,
            &$created,
            &$skipped
        ) {
            foreach ($scenarios as $index => $scenario) {
                $room = $rooms[$index % $rooms->count()];
                $client = $clients[$index % $clients->count()];

                if ($this->roomHasOverlap($room->id, $scenario['start'], $scenario['end'])) {
                    $this->command?->warn("Skip overlap: room {$room->id}, {$scenario['start']} – {$scenario['end']}");
                    $skipped++;
                    continue;
                }

                $this->createTestReservation(
                    $room,
                    $client->id,
                    $userId,
                    $stayReasonId,
                    $sourceId,
                    $scenario,
                    $pricingEngine,
                    $revenueAccrual
                );
                $created++;
            }
        });

        $this->command?->info("Done: {$created} reservation(s) created, {$skipped} skipped.");
        $this->command?->table(
            ['Test report periods', 'Suggestion'],
            [
                ['May 2026', 'Financials → Reports → 2026-05-01 to 2026-05-31'],
                ['June 2026', '2026-06-01 to 2026-06-30'],
                ['August 2026 (partial stays)', '2026-08-01 to 2026-08-31 — cross-month bookings'],
            ]
        );
    }

    private function clearTestReservations(): void
    {
        $ids = Reservation::whereYear('start_date', self::TEST_YEAR)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        ReservationDailyCharge::whereIn('reservation_id', $ids)->delete();
        ReservationPay::whereIn('reservation_id', $ids)->delete();
        ReservationRoom::whereIn('reservation_id', $ids)->delete();
        Reservation::whereIn('id', $ids)->delete();

        $this->command?->info('Removed ' . $ids->count() . ' test reservation(s) from ' . self::TEST_YEAR . '.');
    }

    private function createTestReservation(
        Room $room,
        int $clientId,
        int $userId,
        int $stayReasonId,
        int $sourceId,
        array $scenario,
        PricingEngine $pricingEngine,
        RevenueAccrualService $revenueAccrual
    ): void {
        $startDate = Carbon::parse($scenario['start'])->startOfDay();
        $endDate = Carbon::parse($scenario['end'])->startOfDay();
        $nights = $startDate->diffInDays($endDate);
        $rentType = (int) $scenario['rent_type'];
        $priceMode = 0;

        $lines = $pricingEngine->buildDailyBreakdown(
            $room,
            $startDate->toDateString(),
            $endDate->toDateString(),
            $rentType,
            $priceMode
        );

        $basePrice = round(array_sum(array_column($lines, 'base_amount')), 2);
        $discount = min((float) ($scenario['discount'] ?? 0), max(0, $basePrice - 1));
        $extras = 0;
        $penalties = 0;
        $subtotal = $basePrice - $discount + $extras + $penalties;
        $taxes = round($subtotal * 0.15, 2, PHP_ROUND_HALF_UP);
        $total = round($subtotal + $taxes, 2);

        $payAmount = match ($scenario['pay'] ?? 'full') {
            'full' => $total,
            'partial' => round($total * 0.5, 2),
            default => 0,
        };

        $reservation = Reservation::create([
            'client_id'             => $clientId,
            'start_date'            => $startDate->toDateString(),
            'nights'                => $nights,
            'expire_date'           => $endDate->toDateString(),
            'reservation_type'      => 0,
            'reservation_status'    => $scenario['status'] ?? 1,
            'stay_reason_id'        => $stayReasonId,
            'reservation_source_id' => $sourceId,
            'rent_type'             => $rentType,
            'base_price'            => $basePrice,
            'discount'              => $discount,
            'extras'                => $extras,
            'penalties'             => $penalties,
            'subtotal'              => $subtotal,
            'taxes'                 => $taxes,
            'total'                 => $total,
            'logedin'               => 1,
            'login_time'            => $startDate->toDateString(),
            'user_id'               => $userId,
        ]);

        $finalRoomPrice = ($basePrice - $discount + $extras + $penalties) * 1.15;

        $resRoom = ReservationRoom::create([
            'reservation_id' => $reservation->id,
            'room_id'        => $room->id,
            'suite_id'       => null,
            'price'          => round($finalRoomPrice, 2),
        ]);

        $revenueAccrual->persistDailyCharges(
            $reservation->id,
            $resRoom->id,
            $room->id,
            $rentType,
            $lines
        );

        $this->createRoomPriceSnapshot($room, $resRoom->id, $startDate, $endDate, $rentType);

        if ($payAmount > 0) {
            ReservationPay::create([
                'reservation_id' => $reservation->id,
                'pay'            => $payAmount,
                'type'           => ReservationPay::TYPE_PAYMENT,
                'user_id'        => $userId,
                'created_at'     => $startDate->copy()->subDays(2),
                'updated_at'     => $startDate->copy()->subDays(2),
            ]);
        }
    }

    private function createRoomPriceSnapshot(Room $room, int $reservationRoomId, Carbon $start, Carbon $end, int $rentType): void
    {
        $roomType = $room->roomType;
        if (!$roomType) {
            return;
        }

        $roomTypePlan = RoomtypePricingplan::where('roomtype_id', $room->room_type_id)
            ->whereHas('pricingplan', function ($q) use ($start, $end) {
                $q->where('StartDate', '<=', $end->toDateString())
                    ->where('EndDate', '>=', $start->toDateString());
            })->first();

        $data = ['reservation_room_id' => $reservationRoomId];

        if (Schema::hasColumn('room_prices', 'start_plan') && $roomTypePlan) {
            $pStart = Carbon::parse($roomTypePlan->pricingplan->StartDate);
            $pEnd = Carbon::parse($roomTypePlan->pricingplan->EndDate);
            $data['start_plan'] = $start->max($pStart)->toDateString();
            $data['end_plan'] = $end->min($pEnd)->toDateString();
        }

        if ($rentType === 0) {
            if ($roomTypePlan) {
                $data['pricing_plan_daily'] = $roomTypePlan->DailyPrice;
            }
            $data['min_price'] = $roomType->Min_daily_price;
            $data['max_price'] = $roomType->Max_daily_price;
        } else {
            if ($roomTypePlan) {
                $data['pricing_plan_monthly'] = $roomTypePlan->MonthlyPrice;
            }
            $data['min_month'] = $roomType->Min_monthly_price;
            $data['max_month'] = $roomType->Max_monthly_price;
        }

        RoomPrice::create($data);
    }

    private function roomHasOverlap(int $roomId, string $start, string $end): bool
    {
        return ReservationRoom::where('room_id', $roomId)
            ->whereHas('reservation', function ($q) use ($start, $end) {
                $q->where('start_date', '<', $end)
                    ->where('expire_date', '>', $start);
            })->exists();
    }

    private function ensureStayReason(): int
    {
        return Stay_reason::firstOrCreate(
            ['name_en' => 'Tourism'],
            ['name_ar' => 'سياحة', 'description' => 'Test stay reason']
        )->id;
    }

    private function ensureReservationSource(): int
    {
        return Reservation_source::firstOrCreate(
            ['name_en' => 'Walk-in'],
            ['name_ar' => 'مباشر', 'description' => 'Test source']
        )->id;
    }

    private function ensureClients()
    {
        if (Client::count() >= 4) {
            return Client::limit(8)->get();
        }

        $names = [
            ['Ahmed', 'Ali'],
            ['Sara', 'Hassan'],
            ['Omar', 'Khaled'],
            ['Layla', 'Mahmoud'],
            ['Youssef', 'Nasser'],
            ['Nour', 'Saleh'],
        ];

        foreach ($names as $i => [$first, $last]) {
            Client::firstOrCreate(
                ['IdNumber' => 'TEST' . (100000 + $i)],
                [
                    'first_name'         => $first,
                    'last_name'          => $last,
                    'email'              => strtolower($first) . '.test' . $i . '@hotel.test',
                    'international_code' => '+966',
                    'mobile'             => '0500000' . str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT),
                    'IdType'             => 'ID',
                    'birth_date'         => '1990-01-15',
                    'gender'             => 'MALE',
                    'guest_type'         => 'VISITOR',
                    'nationality'        => 'SA',
                ]
            );
        }

        return Client::limit(8)->get();
    }

    private function ensureRoomType(): RoomType
    {
        return RoomType::firstOrCreate(
            ['name_en' => 'Deluxe Test'],
            [
                'name_ar'           => 'ديلوكس تجريبي',
                'description'       => 'Seeder room type',
                'Min_daily_price'   => 100,
                'Max_daily_price'   => 200,
                'Min_monthly_price' => 2400,
                'Max_monthly_price' => 4800,
                'active_type'       => 1,
            ]
        );
    }

    private function ensurePricingPlan(RoomType $roomType): void
    {
        $plan = Pricingplan::firstOrCreate(
            ['NameEn' => 'Summer Test Plan'],
            [
                'NameAr'     => 'خطة صيف تجريبية',
                'StartDate'  => '2026-07-01',
                'EndDate'    => '2026-08-31',
                'ActiveType' => 1,
            ]
        );

        RoomtypePricingplan::firstOrCreate(
            [
                'roomtype_id'     => $roomType->id,
                'pricingplan_id'  => $plan->id,
            ],
            [
                'DailyPrice'   => 150,
                'MonthlyPrice' => 3500,
            ]
        );
    }

    private function ensureRooms(RoomType $roomType)
    {
        $rooms = Room::with('roomType')
            ->where('active', 1)
            ->where('roomStatus', 1)
            ->where('room_type_id', $roomType->id)
            ->limit(10)
            ->get();

        if ($rooms->count() >= 8) {
            return $rooms;
        }

        [$buildingId, $floorId] = $this->ensureBuildingAndFloor();

        for ($n = 101; $n <= 108; $n++) {
            Room::firstOrCreate(
                ['number' => (string) $n, 'building_id' => $buildingId],
                [
                    'room_type_id' => $roomType->id,
                    'floor_id'     => $floorId,
                    'suite_id'     => null,
                    'active'       => 1,
                    'roomStatus'   => 1,
                ]
            );
        }

        return Room::with('roomType')
            ->where('active', 1)
            ->where('roomStatus', 1)
            ->limit(10)
            ->get();
    }

    private function ensureBuildingAndFloor(): array
    {
        $buildingId = DB::table('buildings')->value('id');
        if (!$buildingId) {
            $buildingId = DB::table('buildings')->insertGetId([
                'name'       => 'Main Building',
                'number'     => 'B1',
                'active'     => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $floorId = DB::table('floors')->where('building_id', $buildingId)->value('id');
        if (!$floorId) {
            $floorId = DB::table('floors')->insertGetId([
                'building_id' => $buildingId,
                'number'      => '1',
                'active'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return [(int) $buildingId, (int) $floorId];
    }

    private function enableFridayPeak(): void
    {
        PeakDay::where('day_name_en', 'Friday')->update(['check' => 1]);
    }
}
