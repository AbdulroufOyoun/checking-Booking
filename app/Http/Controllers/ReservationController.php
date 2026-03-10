<?php

namespace App\Http\Controllers;


use App\Models\PeakDay;
use App\Models\PeakMonth;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomtypePricingplan;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use App\Http\Requests\Reservation\MakeReservationRequest;
use App\Http\Requests\Reservation\CheckReservationRequest;
use App\Http\Requests\Reservation\GetRoomPriceRequest;
use App\Http\Requests\Reservation\GetReservationByDateRequest;
use App\Http\Requests\Reservation\GetReservationByRoomRequest;
use App\Http\Requests\Reservation\SetReservationUnavailableRequest;


class ReservationController extends Controller
{

    public function makeReservation(MakeReservationRequest $request)
    {
        try {
            DB::beginTransaction();
            $reservation = Reservation::create([
                'client_id'          => $request->client_id,
                'room_id'            => $request->room_id,
                'room_suite'         => $request->room_suite,
                'multi_room'         => $request->multi_room,
                'additional_rooms_ids' => $request->additional_rooms_ids ?? '',
                'start_date'         => $request->start_date,
                'nights'             => $request->nights,
                'expire_date'        => $request->expire_date,
                'reservation_type'   => $request->reservation_type,
                'reservation_status' => $request->reservation_status ?? 0,
                'stay_reason_id'     => $request->stay_reason_id,
                'reservation_source_id' => $request->reservation_source_id,
                'rent_type'          => $request->rent_type,
                'base_price'         => $request->base_price,
                'discount'           => $request->discount ?? 0,
                'extras'             => $request->extras ?? 0,
                'penalties'          => $request->penalties ?? 0,
                'subtotal'           => $request->subtotal,
                'taxes'              => $request->taxes ?? 0,
                'total'              => $request->total,
                'logedin'            => $request->logedin ?? 1,
                'login_time'         => $request->login_time,
                'user_id'            => $request->user_id,
            ]);
            DB::commit();
            return \SuccessData('Reservation created successfully', $reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed($e->getMessage());
        }
    }

    public  function checkReservation(CheckReservationRequest $request)
    {
        try {
            $availableRooms = array();
            $roomsQuery = Room::where('building_id', '=', $request->building_id)->where("roomStatus", '=', 1)
                ->where('active', '=', 1)->where('room_type_id', '=', $request->room_type);
            if ($request->filled('floor_id')) {
                $roomsQuery->where('floor_id', '=', $request->floor_id)->where('active', '=', 1);
            }
            $rooms = $roomsQuery->get();
            if (count($rooms) > 0) {
                foreach ($rooms as $room) {
                    $checkReservationForRoom = Reservation::where('room_id', '=', $room->id)
                        ->where('expire_date', '>=', now())
                        ->where(function ($query) use ($request) {
                            $query->whereBetween('start_date', [$request->start_date, $request->expire_date])
                                ->orWhereBetween('expire_date', [$request->start_date, $request->expire_date])
                                ->orWhere(function ($query) use ($request) {
                                    $query->where('start_date', '<=', $request->start_date)
                                        ->where('expire_date', '>=', $request->expire_date);
                                },);
                        },)->exists();
                    switch ($request->type_search) {
                        case 1: // غرفة فارغة: أول غرفة فارغة ترجع فوراً
                            if (!$checkReservationForRoom) {
                                return \SuccessData('Available room found', $room);
                            }
                            break;

                        case 2: // جميع الغرف الفارغة
                            if (!$checkReservationForRoom) {
                                array_push($availableRooms, $room);
                            }
                            break;
                    }
                }
                if (count($availableRooms) > 0) {
                    return \SuccessData('Available rooms found', $availableRooms);
                }
                return \Failed('No available rooms found');
            } else {
                return \Failed('Rooms Not Found');
            }
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function getRoomPrice(GetRoomPriceRequest $request)
    {
        try {
            $startDate = Carbon::parse($request->startDate);
            if ($request->typeReservation == 0 && $request->has('endDate')) {
                // يومي
                $endDate = Carbon::parse($request->endDate);
            } else {
                // شهري أو سنوي
                $endDate = $startDate->copy()->addDays(30 * $request->numberOfMonths);
            }
            $start = $startDate->toDateString();
            $end = $endDate ? $endDate->toDateString() : null;
            $totalPrice = 0;
            $days = [];
            $months = [];
            $roomType = RoomType::find($request->roomTypeId);
            $roomTypePlan = RoomtypePricingplan::with(['pricingplan', 'roomType'])->where('roomtype_id', $request->roomTypeId)
                ->whereHas('pricingplan', function ($query) use ($start, $end) {
                    $query->where('StartDate', '<=', $end)
                        ->where('EndDate', '>=', $start);
                })->first();

            // الحجز اليومي
            if ($request->typeReservation == 0) {
                if ($roomTypePlan) {
                    $planStart = Carbon::parse($roomTypePlan->pricingplan->StartDate);
                    $planEnd   = Carbon::parse($roomTypePlan->pricingplan->EndDate);

                    if ($start >= $planStart->toDateString() && $end <= $planEnd->toDateString()) {
                        // المدة كاملة ضمن الخطة
                        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                            $days[$date->toDateString()] = $roomTypePlan->DailyPrice;
                            $totalPrice += $roomTypePlan->DailyPrice;
                        }
                    } else {
                        // يوجد تداخل بالتواريخ
                        switch ($roomTypePlan->pricingplan->ActiveType) {
                            case 0: // Const
                                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                                    $days[$date->toDateString()] = $roomTypePlan->roomType->Min_daily_price;
                                    $totalPrice += $roomTypePlan->roomType->Min_daily_price;
                                }
                                break;
                            case 1: // As Per Day
                                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                                    if ($date->between($planStart, $planEnd)) {
                                        $price = $roomTypePlan->DailyPrice;
                                    } else {
                                        $dayName = $date->format('l');
                                        $price = $this->checkPeakDay($dayName)
                                            ? $roomTypePlan->roomType->Max_daily_price
                                            : $roomTypePlan->roomType->Min_daily_price;
                                    }
                                    $days[$date->toDateString()] = $price;
                                    $totalPrice += $price;
                                }
                                break;

                            case 2: // Plan Price
                                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                                    $days[$date->toDateString()] = $roomTypePlan->DailyPrice;
                                    $totalPrice += $roomTypePlan->DailyPrice;
                                }
                                break;
                        }
                    }
                    return \SuccessData('Daily pricing calculated', ['days' => $days, 'totalPrice' => $totalPrice]);
                }

                // لا يوجد خطة
                switch ($roomType->active_type) {
                    case 0:
                        $priceType = 'Min_daily_price';
                        break;
                    case 1:
                        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                            $dayName = $date->format('l');
                            $price = $this->checkPeakDay($dayName)
                                ? $roomType->Max_daily_price
                                : $roomType->Min_daily_price;
                            $days[$date->toDateString()] = $price;
                            $totalPrice += $price;
                        }
                        return \SuccessData('Daily pricing calculated', ['days' => $days, 'totalPrice' => $totalPrice]);
                    case 2:
                        $priceType = 'Max_daily_price';
                        break;
                }
                if (isset($priceType)) {
                    for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                        $days[$date->toDateString()] = $roomType->$priceType;
                        $totalPrice += $roomType->$priceType;
                    }
                }
                return \SuccessData('Daily pricing calculated', ['days' => $days, 'totalPrice' => $totalPrice]);
            }

            //  الحجز الشهري
            if ($request->typeReservation == 1) {
                $numberOfMonths = $request->numberOfMonths;
                if ($roomTypePlan) {
                    $planStart = Carbon::parse($roomTypePlan->pricingplan->StartDate);
                    $planEnd   = Carbon::parse($roomTypePlan->pricingplan->EndDate);

                    if ($startDate->toDateString() >= $planStart->toDateString() && $endDate->toDateString() <= $planEnd->toDateString()) {
                        for ($i = 1; $i <= $numberOfMonths; $i++) {
                            $months["Month $i"] = $roomTypePlan->MonthlyPrice;
                            $totalPrice += $roomTypePlan->MonthlyPrice;
                        }
                    } else {
                        switch ($roomTypePlan->pricingplan->ActiveType) {
                            case 0:
                                for ($i = 1; $i <= $numberOfMonths; $i++) {
                                    $months["Month $i"] = $roomTypePlan->roomType->Min_monthly_price;
                                    $totalPrice += $roomTypePlan->roomType->Min_monthly_price;
                                }
                                break;

                            case 1:
                                $inPlanDays = $outPlanDays = 0;
                                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                                    if ($date->between($planStart, $planEnd)) {
                                        $price = $roomTypePlan->MonthlyPrice / 30;
                                        $inPlanDays++;
                                    } else {
                                        $monthName = $date->format('F');
                                        $price = $this->checkPeakMonth($monthName)
                                            ? $roomTypePlan->roomType->Max_monthly_price / 30
                                            : $roomTypePlan->roomType->Min_monthly_price / 30;
                                        $outPlanDays++;
                                    }
                                    $days[$date->toDateString()] = $price;
                                    $totalPrice += $price;
                                }
                                return \SuccessData('Monthly pricing calculated with peak months', [
                                    'startDate' => $startDate->toDateString(),
                                    'endDate' => $endDate->toDateString(),
                                    'inPlanDays' => $inPlanDays,
                                    'outPlanDays' => $outPlanDays,
                                    'days' => $days,
                                    'totalPrice' => round($totalPrice, 2)
                                ]);
                            case 2:
                                for ($i = 1; $i <= $numberOfMonths; $i++) {
                                    $months["Month $i"] = $roomTypePlan->MonthlyPrice;
                                    $totalPrice += $roomTypePlan->MonthlyPrice;
                                }
                                break;
                        }
                    }
                } else {
                    // لا يوجد خطة
                    switch ($roomType->active_type) {
                        case 0:
                            for ($i = 1; $i <= $numberOfMonths; $i++) {
                                $months["Month $i"] = $roomType->Min_monthly_price;
                                $totalPrice += $roomType->Min_monthly_price;
                            }
                            break;
                        case 1:
                            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                                $monthName = $date->format('F');
                                $price = $this->checkPeakMonth($monthName)
                                    ? $roomType->Max_monthly_price / 30
                                    : $roomType->Min_monthly_price / 30;
                                $days[$date->toDateString()] = $price;
                                $totalPrice += $price;
                            }
                            return \SuccessData('Monthly pricing calculated with peak months', [
                                'startDate' => $startDate->toDateString(),
                                'endDate' => $endDate->toDateString(),
                                'days' => $days,
                                'totalPrice' => round($totalPrice, 2)
                            ]);
                        case 2:
                            for ($i = 1; $i <= $numberOfMonths; $i++) {
                                $months["Month $i"] = $roomType->Max_monthly_price;
                                $totalPrice += $roomType->Max_monthly_price;
                            }
                            break;
                    }
                }
                return \SuccessData('Monthly pricing calculated', [
                    'startDate' => $startDate->toDateString(),
                    'endDate' => $endDate->toDateString(),
                    'numberOfMonths' => $numberOfMonths,
                    'months' => $months ?? [],
                    'totalPrice' => $totalPrice
                ]);
            }
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function checkPeakDay($day): bool
    {
        return PeakDay::where('day_name_en', $day)->value('check') == 1;
    }

    public function checkPeakMonth($Month): bool
    {
        return PeakMonth::where('month_name_en', $Month)->value('check') == 1;
    }

    public function getReservationByDate(GetReservationByDateRequest $request)
    {
        try {
            $query = Reservation::where('start_date', '<=', $request->expire_date)->where('expire_date', '>=', $request->start_date)->where('is_available', '=', 0);
            $query->whereHas('room', function ($roomQuery) use ($request) {
                if ($request->building_id) {
                    $roomQuery->where('building_id', $request->building_id);
                }
                if ($request->floor_id) {
                    $roomQuery->where('floor_id', $request->floor_id);
                }
                if ($request->suite_id) {
                    $roomQuery->where('suite_id', $request->suite_id);
                }
                if ($request->room_id) {
                    $roomQuery->where('id', $request->room_id);
                }
            });
            $reservations = $query->get();
            if ($reservations->isEmpty()) {
                return \Success('No reservations found');
            } else {
                return \SuccessData('Reservations found', $reservations);
            }
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public  function getReservationByRoom(GetReservationByRoomRequest $request)
    {
        try {
            $reservations = Reservation::where('room_id', '=', $request->roomId)
                ->where('expire_date', '>=', now())->where('is_available', '=', 0)->get();
            return \SuccessData('Reservations retrieved', $reservations);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }


    public  function setReservationUnavailable(SetReservationUnavailableRequest $request)
    {
        try {
            Reservation::find($request->id)->update(['is_available' => 1]);
            return \Success('Reservation set to unavailable');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public static function checkStudentHasActiveReservation($student_id)
    {
        $today = date("Y-m-d");
        $reservation = Reservation::where('student_id', $student_id)->where('start_date', '<=', $today)->where('expire_date', '>=', $today)->where('is_available', '=', 0)->first();
        return $reservation;
    }
}

