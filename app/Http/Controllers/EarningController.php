<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Earning\GetSuiteEarningsRequest;
use App\Http\Requests\Earning\GetRoomTypeEarningsRequest;
use App\Http\Requests\Earning\GetRoomEarningsRequest;
use App\Http\Requests\Earning\GetFloorEarningsRequest;
use App\Http\Requests\Earning\GetEarningsRequest;
use App\Http\Requests\Earning\GetRevenueRequest;
use App\Models\ReservationRoom;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Suite;
use App\Models\RoomType;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Requests\Earning\GetYearlyEarningsRequest;
use App\Models\Reservation;




class EarningController extends Controller
{
    public function getRevenue(\App\Http\Requests\Earning\GetRevenueRequest $request)
    {
        $validated = $request->validated();

        $scope = $validated['scope'];
        $entityId = $validated['entity_id'] ?? null;
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
        $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;

        $data = $this->calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart, $compareEnd);

        return response()->json([
            'scope' => $scope,
            'entity_id' => $entityId,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $endDate->diffInDays($startDate) + 1,
            ],
            'revenue' => $data
        ]);
    }

    private function calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart = null, $compareEnd = null)
    {
        $query = Reservation::where('start_date', '<=', $endDate)
            ->where('expire_date', '>=', $startDate)
            ->where('reservation_status', 1);

        if ($scope !== 'total') {
            $query->whereHas('reservationRooms.' . $scope, function ($q) use ($entityId) {
                $q->where('id', $entityId);
            });
        }

        $current = $query->clone()->selectRaw('SUM(total) as total, SUM(base_price) as base_price, SUM(discount) as discount, COUNT(*) as count')
            ->first();

        $comparison = null;
        if ($compareStart && $compareEnd) {
            $comparisonQuery = Reservation::where('start_date', '<=', $compareEnd)
                ->where('expire_date', '>=', $compareStart)
                ->where('reservation_status', 1);

            if ($scope !== 'total') {
                $comparisonQuery->whereHas('reservationRooms.' . $scope, function ($q) use ($entityId) {
                    $q->where('id', $entityId);
                });
            }

            $comparison = $comparisonQuery->selectRaw('SUM(total) as total, SUM(base_price) as base_price, SUM(discount) as discount, COUNT(*) as count')
                ->first();
        }

        return [
            'current' => [
                'total' => (float) ($current->total ?? 0),
                'base_price' => (float) ($current->base_price ?? 0),
                'discount' => (float) ($current->discount ?? 0),
                'net' => (float) (($current->total ?? 0) - ($current->discount ?? 0)),
                'count' => (int) ($current->count ?? 0),
            ],
            'comparison' => $comparison ? [
                'total' => (float) ($comparison->total ?? 0),
                'base_price' => (float) ($comparison->base_price ?? 0),
                'discount' => (float) ($comparison->discount ?? 0),
                'net' => (float) (($comparison->total ?? 0) - ($comparison->discount ?? 0)),
                'count' => (int) ($comparison->count ?? 0),
            ] : null,
        ];
    }




    public function getRoomTypeEarnings(GetRoomTypeEarningsRequest $request)
    {
        $validated = $request->validated();

        $entityId = $validated['entity_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        if (!RoomType::find($entityId)) {
            return response()->json(['error' => 'Room Type not found'], 404);
        }

$compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
        $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;

        $data = $this->calculateEarnings('roomtype', $entityId, $startDate, $endDate, $compareStart, $compareEnd);

        return response()->json([
            'scope' => 'roomtype',
            'entity_id' => $entityId,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $endDate->diffInDays($startDate) + 1,
            ],
            'earnings' => $data
        ]);
    }

    public function getSuiteEarnings(GetSuiteEarningsRequest $request)
    {
        $validated = $request->validated();

        $entityId = $validated['entity_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        if (!Suite::find($entityId)) {
            return response()->json(['error' => 'Suite not found'], 404);
        }

        $data = $this->calculateEarnings('suite', $entityId, $startDate, $endDate, null, null);

        return response()->json([
            'scope' => 'suite',
            'entity_id' => $entityId,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $endDate->diffInDays($startDate) + 1,
            ],
            'earnings' => $data
        ]);
    }

    public function getFloorEarnings(GetFloorEarningsRequest $request)
    {
        $validated = $request->validated();

        $entityId = $validated['entity_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        if (!Floor::find($entityId)) {
            return response()->json(['error' => 'Floor not found'], 404);
        }

        $data = $this->calculateEarnings('floor', $entityId, $startDate, $endDate, null, null);

        return response()->json([
            'scope' => 'floor',
            'entity_id' => $entityId,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $endDate->diffInDays($startDate) + 1,
            ],
            'earnings' => $data
        ]);
    }

    public function getBuildingEarnings(Request $request)
    {
        $request->merge(['scope' => 'building']);
        $validated = app(GetEarningsRequest::class)->new($request)->validateResolved();

        $entityId = $validated['entity_id'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        if (!Building::find($entityId)) {
            return response()->json(['error' => 'Building not found'], 404);
        }

        $data = $this->calculateEarnings('building', $entityId, $startDate, $endDate, null, null);

        return response()->json([
            'scope' => 'building',
            'entity_id' => $entityId,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $endDate->diffInDays($startDate) + 1,
            ],
            'earnings' => $data
        ]);
    }

    public function getYearlyEarnings(Request $request)
    {
$validated = (new GetYearlyEarningsRequest())->merge($request->all())->validate();
        $years = $validated['years'];

        // Month names
        $monthNames = [
            1 => ['en' => 'January', 'ar' => 'يناير'],
            2 => ['en' => 'February', 'ar' => 'فبراير'],
            3 => ['en' => 'March', 'ar' => 'مارس'],
            4 => ['en' => 'April', 'ar' => 'أبريل'],
            5 => ['en' => 'May', 'ar' => 'مايو'],
            6 => ['en' => 'June', 'ar' => 'يونيو'],
            7 => ['en' => 'July', 'ar' => 'يوليو'],
            8 => ['en' => 'August', 'ar' => 'أغسطس'],
            9 => ['en' => 'September', 'ar' => 'سبتمبر'],
            10 => ['en' => 'October', 'ar' => 'أكتوبر'],
            11 => ['en' => 'November', 'ar' => 'نوفمبر'],
            12 => ['en' => 'December', 'ar' => 'ديسمبر'],
        ];

        // Grand total yearly
        $query = ReservationRoom::query()
            ->join('reservations', 'reservation_rooms.reservation_id', '=', 'reservations.id')
            ->join('rooms', 'reservation_rooms.room_id', '=', 'rooms.id')
            ->whereIn(DB::raw('YEAR(reservations.start_date)'), $years)
            ->where('reservations.reservation_status', 1)
            ->where('reservations.reservation_status', '!=', 'cancelled');

        $grandYearly = $query->select(
            DB::raw('SUM(reservations.total) as total'),
            DB::raw('SUM(reservations.discount) as discount'),
            DB::raw('COUNT(DISTINCT reservations.id) as count')
        )->first();

        $totalYearly = [
            'net_earnings' => (float) ($grandYearly->total ?? 0) - (float) ($grandYearly->discount ?? 0),
            'total' => (float) ($grandYearly->total ?? 0),
            'count' => (int) ($grandYearly->count ?? 0),
        ];

        // Grand total monthly (across years)
        $grandMonthly = $query->select(
            DB::raw('MONTH(reservations.start_date) as month'),
            DB::raw('SUM(reservations.total) as total'),
            DB::raw('SUM(reservations.discount) as discount'),
            DB::raw('COUNT(DISTINCT reservations.id) as count')
        )->groupBy('month')
        ->get()
        ->keyBy('month');

        $totalMonthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthData = $grandMonthly->get($m, (object)['total' => 0, 'discount' => 0, 'count' => 0]);
            $totalMonthly[] = [
                'month' => $m,
                'name_ar' => $monthNames[$m]['ar'],
                'name_en' => $monthNames[$m]['en'],
                'net_earnings' => (float) $monthData->total - (float) $monthData->discount,
                'total' => (float) $monthData->total,
                'count' => (int) $monthData->count,
            ];
        }

        // Per building
        $query->join('buildings', 'rooms.building_id', '=', 'buildings.id');
        $buildingsData = $query->select(
            'buildings.id',
            'buildings.name as name_ar',
'buildings.name as name_en', // use name as en too
            DB::raw('SUM(reservations.total) as total'),
            DB::raw('SUM(reservations.discount) as discount'),
            DB::raw('COUNT(DISTINCT reservations.id) as count')
        )->groupBy('buildings.id', 'buildings.name')
        ->havingRaw('SUM(reservations.total) > 0')
        ->get();

        $buildings = [];
        foreach ($buildingsData as $b) {
            $b->net_earnings = (float) $b->total - (float) $b->discount;
            $buildings[] = $b;
        }

        return response()->json([
            'years' => $years,
            'total_yearly' => $totalYearly,
            'total_monthly' => $totalMonthly,
            'buildings' => $buildings,
        ]);
    }

    private function getEarningsForPeriod(Carbon $startDate, Carbon $endDate)
    {
        $totals = DB::table('reservations')
            ->where('start_date', '>=', $startDate)
            ->where('start_date', '<=', $endDate)
            ->where('reservation_status', 1)
            ->select(
                DB::raw('SUM(total) as total'),
                DB::raw('SUM(subtotal) as subtotal'),
                DB::raw('SUM(taxes) as taxes'),
                DB::raw('SUM(penalties) as penalties'),
                DB::raw('SUM(discount) as discount'),
                DB::raw('SUM(base_price) as base_price'),
                DB::raw('SUM(extras) as extras'),
                DB::raw('COUNT(id) as reservation_count'),
                DB::raw('SUM(nights) as total_nights')
            )->first();

        return [
            'total'             => (float) ($totals->total ?? 0),
            'subtotal'          => (float) ($totals->subtotal ?? 0),
            'taxes'             => (float) ($totals->taxes ?? 0),
            'penalties'         => (float) ($totals->penalties ?? 0),
            'discount'          => (float) ($totals->discount ?? 0),
            'base_price'        => (float) ($totals->base_price ?? 0),
            'extras'            => (float) ($totals->extras ?? 0),
            'net_earnings'      => (float) ($totals->total ?? 0) - (float) ($totals->discount ?? 0),
            'reservation_count' => (int) ($totals->reservation_count ?? 0),
            'total_nights'      => (int) ($totals->total_nights ?? 0),
            'avg_nightly'       => ($totals->total_nights ?? 0) > 0
                                   ? (float) ($totals->total / $totals->total_nights)
                                   : 0.0,
        ];
    }

    private function calculateEarnings(string $scope, int $entityId, Carbon $startDate, Carbon $endDate, ?Carbon $compareStart = null, ?Carbon $compareEnd = null)
    {
        $current = $this->getEarningsForPeriod($startDate, $endDate);

        $comparison = null;
        if ($compareStart && $compareEnd) {

            $comparison = $this->getEarningsForPeriod($compareStart, $compareEnd);
        }

        return [
            'current' => $current,
            'comparison' => $comparison,
        ];
    }
}

