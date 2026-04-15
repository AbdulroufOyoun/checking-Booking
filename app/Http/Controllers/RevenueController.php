<?php

namespace App\Http\Controllers;


use App\Http\Requests\Revenue\GetRevenueRequest;
use App\Http\Resources\Revenue\RevenueResource;
use App\Models\Reservation;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{


    public function getTotalRevenue(GetRevenueRequest $request)
    {
        try {
            $validated = $request->validated();
            $scope = 'total';
            $entityId = null;
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
            $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;
            $data = $this->calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart, $compareEnd);
            $responseData = [
                'scope' => $scope,
                'entity_id' => $entityId,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $endDate->diffInDays($startDate) + 1,
                ],
                'revenue' => $data
            ];
            return SuccessData("Revenue by {$scope} retrieved successfully.", new RevenueResource($responseData));
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function getRoomRevenue(GetRevenueRequest $request, int $entity_id)
    {
        try {
            $validated = $request->validated();
            $scope = 'room';
            $entityId = $entity_id;
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
            $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;
            $data = $this->calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart, $compareEnd);
            $responseData = [
                'scope' => $scope,
                'entity_id' => $entityId,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $endDate->diffInDays($startDate) + 1,
                ],
                'revenue' => $data
            ];
            return SuccessData("Revenue by {$scope} retrieved successfully.", new RevenueResource($responseData));
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function getSuiteRevenue(GetRevenueRequest $request, int $entity_id)
    {
        try {
            $validated = $request->validated();
            $scope = 'suite';
            $entityId = $entity_id;
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
            $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;
            $data = $this->calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart, $compareEnd);
            $responseData = [
                'scope' => $scope,
                'entity_id' => $entityId,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $endDate->diffInDays($startDate) + 1,
                ],
                'revenue' => $data
            ];
            return SuccessData("Revenue by {$scope} retrieved successfully.", new RevenueResource($responseData));
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function getFloorRevenue(GetRevenueRequest $request, int $entity_id)
    {
        try {
            $validated = $request->validated();
            $scope = 'floor';
            $entityId = $entity_id;
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
            $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;
            $data = $this->calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart, $compareEnd);
            $responseData = [
                'scope' => $scope,
                'entity_id' => $entityId,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $endDate->diffInDays($startDate) + 1,
                ],
                'revenue' => $data
            ];
            return SuccessData("Revenue by {$scope} retrieved successfully.", new RevenueResource($responseData));
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function getBuildingRevenue(GetRevenueRequest $request, int $entity_id)
    {
        try {
            $validated = $request->validated();
            $scope = 'building';
            $entityId = $entity_id;
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
            $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;
            $data = $this->calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart, $compareEnd);
            $responseData = [
                'scope' => $scope,
                'entity_id' => $entityId,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $endDate->diffInDays($startDate) + 1,
                ],
                'revenue' => $data
            ];
            return SuccessData("Revenue by {$scope} retrieved successfully.", new RevenueResource($responseData));
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function getRoomTypeRevenue(GetRevenueRequest $request, int $entity_id)
    {
        try {
            $validated = $request->validated();
            $scope = 'roomtype';
            $entityId = $entity_id;
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $compareStart = isset($validated['compare_start_date']) ? Carbon::parse($validated['compare_start_date']) : null;
            $compareEnd = isset($validated['compare_end_date']) ? Carbon::parse($validated['compare_end_date']) : null;
            $data = $this->calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart, $compareEnd);
            $responseData = [
                'scope' => $scope,
                'entity_id' => $entityId,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $endDate->diffInDays($startDate) + 1,
                ],
                'revenue' => $data
            ];
            return SuccessData("Revenue by {$scope} retrieved successfully.", new RevenueResource($responseData));
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }


    private function calculateRevenue($scope, $entityId, $startDate, $endDate, $compareStart = null, $compareEnd = null)
    {
        $nightlyRevenue = $this->getNightlyRevenueQuery($scope, $entityId, $startDate, $endDate);
        $monthlyRevenue = $this->getMonthlyRevenueQuery($scope, $entityId, $startDate, $endDate);

        $currentNightly = $nightlyRevenue->get()->sum('pro_rated_price');
        $currentMonthly = $monthlyRevenue->get()->sum('pro_rated_price');
        $currentTotal = $currentNightly + $currentMonthly;
        $currentCount = $nightlyRevenue->get()->count() + $monthlyRevenue->get()->count();

        $comparison = null;
        if ($compareStart && $compareEnd) {
            $compNightlyRevenue = $this->getNightlyRevenueQuery($scope, $entityId, $compareStart, $compareEnd);
            $compMonthlyRevenue = $this->getMonthlyRevenueQuery($scope, $entityId, $compareStart, $compareEnd);

            $comparisonNightly = $compNightlyRevenue->get()->sum('pro_rated_price');
            $comparisonMonthly = $compMonthlyRevenue->get()->sum('pro_rated_price');
            $comparisonTotal = $comparisonNightly + $comparisonMonthly;
            $comparisonCount = $compNightlyRevenue->get()->count() + $compMonthlyRevenue->get()->count();

            $comparison = [
                'nightly' => $comparisonNightly,
                'monthly' => $comparisonMonthly,
                'total' => $comparisonTotal,
                'count' => $comparisonCount,
            ];
        }

        return [
            'current' => [
                'nightly' => $currentNightly,
                'monthly' => $currentMonthly,
                'total' => $currentTotal,
                'count' => $currentCount,
            ],
            'comparison' => $comparison,
        ];
    }

    private function getNightlyRevenueQuery($scope, $entityId, $startDate, $endDate)
    {
        return DB::table('reservation_rooms')
            ->join('room_prices', 'reservation_rooms.id', '=', 'room_prices.reservation_room_id')
            ->join('rooms', 'reservation_rooms.room_id', '=', 'rooms.id')
            ->join('reservations', 'reservation_rooms.reservation_id', '=', 'reservations.id')
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.expire_date', '>=', $startDate)
            ->where('reservations.reservation_status', 1)
            ->where(function ($q) {
                $q->where('room_prices.pricing_plan_daily', '>', 0)
                  ->orWhere('room_prices.max_price', '>', 0)
                  ->orWhere('room_prices.min_price', '>', 0);
            })
            ->when($scope !== 'total', function ($q) use ($scope, $entityId) {
                $q->where($scope . 's.id', $entityId);
            })
            ->selectRaw('
                GREATEST(0, LEAST(?, reservations.expire_date) - GREATEST(?, reservations.start_date)) as overlap_days,
                COALESCE(room_prices.pricing_plan_daily, room_prices.max_price, room_prices.min_price, 0) as daily_price,
                COALESCE(room_prices.pricing_plan_daily, room_prices.max_price, room_prices.min_price, 0) * GREATEST(0, LEAST(?, reservations.expire_date) - GREATEST(?, reservations.start_date)) as pro_rated_price
            ', [$endDate, $startDate, $endDate, $startDate]);
    }

    private function getMonthlyRevenueQuery($scope, $entityId, $startDate, $endDate)
    {
        return DB::table('reservation_rooms')
            ->join('room_prices', 'reservation_rooms.id', '=', 'room_prices.reservation_room_id')
            ->join('rooms', 'reservation_rooms.room_id', '=', 'rooms.id')
            ->join('reservations', 'reservation_rooms.reservation_id', '=', 'reservations.id')
            ->where('reservations.start_date', '<=', $endDate)
            ->where('reservations.expire_date', '>=', $startDate)
            ->where('reservations.reservation_status', 1)
            ->where(function ($q) {
                $q->where('room_prices.pricing_plan_monthly', '>', 0)
                  ->orWhere('room_prices.max_month', '>', 0)
                  ->orWhere('room_prices.min_month', '>', 0);
            })
            ->when($scope !== 'total', function ($q) use ($scope, $entityId) {
                $q->where($scope . 's.id', $entityId);
            })
            ->selectRaw('
                GREATEST(0, LEAST(?, reservations.expire_date) - GREATEST(?, reservations.start_date)) / 30 as overlap_months,
                COALESCE(room_prices.pricing_plan_monthly, room_prices.max_month, room_prices.min_month, 0) as monthly_price,
                COALESCE(room_prices.pricing_plan_monthly, room_prices.max_month, room_prices.min_month, 0) * (GREATEST(0, LEAST(?, reservations.expire_date) - GREATEST(?, reservations.start_date)) / 30) as pro_rated_price
            ', [$endDate, $startDate, $endDate, $startDate]);
    }

}

