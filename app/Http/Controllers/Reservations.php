<?php

namespace App\Http\Controllers;

use App\Models\PeakDay;
use App\Models\PeakMonth;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomtypePricingplan;
use App\SMS;
use Exception;
use Carbon\Carbon;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class Reservations extends Controller
{
    public  function makeReservation1(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'Reservations' => 'required|array',
            'Reservations.*.student_id' => 'nullable|numeric|exists:users,id',
            'Reservations.*.room_id' => 'required|numeric|exists:rooms,id',
            'Reservations.*.student_name' => 'required|string',
            'Reservations.*.room_number' => 'required|numeric',
            'Reservations.*.start_date' => 'required|date',
            'Reservations.*.expire_date' => 'required|date',
            'Reservations.*.start_time' => 'nullable|string',
            'Reservations.*.expire_time' => 'nullable|string',
            'Reservations.*.facility_ids' => 'nullable|array',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        DB::beginTransaction();
        $sms = new SMS();
        try {
            foreach ($request->Reservations as $reservationData) {
                $isAvailable = $this->checkReservationByRoom($reservationData['room_id'], $reservationData['start_date'], $reservationData['expire_date'], null);
                if (!$isAvailable) {
                    throw new Exception("Room is not available for reservation");
                }
                $isCheckReservationUser = $this->checkReservationForUser($reservationData['student_id'], $reservationData['start_date'], $reservationData['expire_date']);
                if (!$isCheckReservationUser) {
                    throw new Exception("The user has an active reservation.");
                }
                $reservation = new Reservation();
                $reservation->student_id = $reservationData['student_id'];
                $reservation->room_id = $reservationData['room_id'];
                $reservation->student_name = $reservationData['student_name'];
                $reservation->room_number = $reservationData['room_number'];
                $reservation->start_date = $reservationData['start_date'];
                $reservation->expire_date = $reservationData['expire_date'];
                $reservation->start_time = $reservationData['start_time'];
                $reservation->expire_time = $reservationData['expire_time'];
                $reservation->facility_ids = json_encode($reservationData['facility_ids']);
                $reservation->save();
            }
            DB::commit();
            // $userIds = array_column($request->Reservations, 'student_id');
            // $users = User::whereIn('id', $userIds)->get()->keyBy('id');
            // foreach ($users as $user) {
            //     $randomNumber = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            //     $res = $sms->sendConfermationSMSToClient('Welcome to RATCO your password is ' . $randomNumber, $user['mobile']);
            //     $user['password'] = password_hash($randomNumber, PASSWORD_DEFAULT);
            //     $user->save();
            // }
            return ['result' => 'success', 'code' => 1, 'error' => '',];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => 0, 'error' => $e->getMessage()];
        }
    }

    public function makeReservation(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'client_id'             => 'required|numeric',
            'room_id'               => 'required|numeric|exists:rooms,id',
            'room_suite'            => 'required|in:0,1', // 0 room, 1 suite
            'multi_room'            => 'required|in:0,1', // 0 single, 1 multiple
            // 'additional_rooms_ids'  => ['nullable', 'string', 'regex:/^\d+(?:-\d+)*$/'],
            'additional_rooms_ids'  => 'nullable|string',
            'start_date'            => 'required|date|after_or_equal:today',
            'nights'                => 'required_if:rent_type,0|integer|min:1',
            'expire_date'           => 'required|date|after_or_equal:start_date',
            'reservation_type'      => 'required|in:0,1', // 0 Single, 1 collective
            'reservation_status'    => 'nullable|in:0,1', //0=> Confirmed 1=> Unconfirmed
            'stay_reason_id'        => 'nullable|numeric|exists:stay_reasons,id',
            'reservation_source_id' => 'nullable|numeric|exists:reservation_sources,id',
            'rent_type'             => 'required|in:0,1', // 0 daily,1 monthly
            'base_price'            => 'required|numeric|min:0',
            'discount'              => 'nullable|numeric|min:0',
            'extras'                => 'nullable|numeric|min:0',
            'penalties'             => 'nullable|numeric|min:0',
            'subtotal'              => 'required|numeric|min:0',
            'taxes'                 => 'nullable|numeric|min:0',
            'total'                 => 'required|numeric|min:0',
            'logedin'               => 'nullable|in:0,1', // 0 logged in, 1 not logged in
            'login_time'            => 'nullable|date',
            'user_id'               => 'required|numeric|exists:users,id',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        // $startDate  = Carbon::parse($request->start_date);
        // $expireDate = Carbon::parse($request->expire_date);
        DB::beginTransaction();
        try {
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
            return ['result' => 'success', 'code' => 1,  "error" => ''];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }

    public  function checkReservation(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'building_id' => 'required|numeric|exists:buildings,id',
            'floor_id' => 'nullable|numeric',
            'capacity' => 'nullable|numeric',
            'room_type' => 'required|numeric|exists:room_types,id',
            'start_date' => 'required|date',
            'expire_date' => 'required|date',
            'type_search' => 'required|numeric|in:1,2',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $availableRooms = array();
        $roomsQuery = Room::where('building_id', '=', $request->building_id)->where("roomStatus", '=', 1)
            ->where('active', '=', 1)->where('room_type_id', '=', $request->room_type);
        if ($request->filled('floor_id')) {
            $roomsQuery->where('floor_id', '=', $request->floor_id)->where('active', '=', 1);
        }
        // if ($request->filled('room_type')) {
        // $roomsQuery->where('room_types_id', '=', $request->room_type);
        // }
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
                            return ['result' => 'success', 'code' => 1, "data" => $room, "error" => ""];
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
                return ['result' => 'success', 'code' => 1, "data" => $availableRooms, "error" => ""];
            }
            return ['result' => 'failed', 'code' => 0, "data" => [], "error" => "No available rooms found"];
        } else {
            return ['result' => 'failed', 'code' => 0, "data" => [], "error" => "Rooms Not Found"];
        }
    }

    public function getRoomPrice(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'startDate'        => 'required|date',
            'endDate'          => 'required_if:typeReservation,0|date|after_or_equal:startDate',
            'roomTypeId'       => 'required|numeric|exists:room_types,id',
            'typeReservation'  => 'required|in:0,1,2,',   //0=>daily 1=>monthly 2=>annual
            'numberOfMonths'   => 'required_if:typeReservation,1|numeric'
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }

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
                return ['result' => "success", 'msg' => "daily", 'days' => $days, 'totalPrice' => $totalPrice];
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
                    return ['result' => "success", 'msg' => "daily", 'days' => $days, 'totalPrice' => $totalPrice];
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
            return ['result' => "success", 'msg' => "daily", 'days' => $days, 'totalPrice' => $totalPrice];
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
                            return ['result' => "success", 'msg' => "monthly - mixed pricing with peak months", 'startDate' => $startDate->toDateString(), 'endDate' => $endDate->toDateString(), 'inPlanDays' => $inPlanDays, 'outPlanDays' => $outPlanDays, 'days' => $days, 'totalPrice' => round($totalPrice, 2)];
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
                        return ['result' => "success", 'msg' => "monthly - mixed pricing with peak months", 'startDate' => $startDate->toDateString(), 'endDate' => $endDate->toDateString(), 'days' => $days, 'totalPrice' => round($totalPrice, 2)];
                    case 2:
                        for ($i = 1; $i <= $numberOfMonths; $i++) {
                            $months["Month $i"] = $roomType->Max_monthly_price;
                            $totalPrice += $roomType->Max_monthly_price;
                        }
                        break;
                }
            }
            return ['result' => "success", 'msg' => "monthly", 'startDate' => $startDate->toDateString(), 'endDate' => $endDate->toDateString(), 'numberOfMonths' => $numberOfMonths, 'months' => $months ?? [], 'totalPrice' => $totalPrice];
        }

        // الحجز السنوي
        // if ($request->typeReservation == 2) {
        //     $endDate = $startDate->copy()->addYear();
        //     return [
        //         'result' => "success",
        //         'msg' => "yearly",
        //         'startDate' => $startDate->toDateString(),
        //         'endDate' => $endDate->toDateString(),
        //         'totalPrice' => $roomType->Min_yearly_price
        //     ];
        // }
    }

    public function checkPeakDay($day): bool
    {
        return PeakDay::where('day_name_en', $day)->value('check') == 1;
    }

    public function checkPeakMonth($Month): bool
    {
        return PeakMonth::where('month_name_en', $Month)->value('check') == 1;
    }

    public  function getReservationByStudent(Request $request)
    {
        $column = '';
        $validation = Validator::make($request->all(), [
            'type' => 'required|string',
            'word' => 'required|string',
            'start_date' => 'nullable|date',
            'expire_date' => 'nullable|date',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        if ($request->type == '0') {
            $column = 'mobile';
        } else {
            $column = 'student_number';
        }
        $user = User::where($column, '=', $request->word)->first('id');
        if ($user) {
            $query = Reservation::where('student_id', '=', $user->id)->where('is_available', '=', 0);
            if ($request->start_date && $request->expire_date) {
                $query->where(function ($q) use ($request) {
                    $q->where('start_date', '<=', $request->expire_date)
                        ->where('expire_date', '>=', $request->start_date);
                });
            }
            $reservations = $query->get();
            if ($reservations->isNotEmpty()) {
                return ['result' => 'success', 'code' => 1, "reservations" => $reservations, "error" => ""];
            } else {
                return ['result' => 'success', 'code' => 0, "reservations" => [], "error" => "Not Found Reservation For This User"];
            }
        } else {
            return ['result' => 'failed', 'code' => 0, "reservations" => [], "error" => "Not Found User"];
        }
    }

    public function getReservationByDate(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'expire_date' => 'required|date',
            'building_id' => 'nullable|integer',
            'floor_id' => 'nullable|integer',
            'suite_id' => 'nullable|integer',
            'room_id' => 'nullable|integer',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
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
            return ['result' => 'success', 'code' => 0, "reservations" => [], "error" => 'Not Found Reservation'];
        } else {
            return ['result' => 'success', 'code' => 1, "reservations" => $reservations, "error" => ""];
        }
    }

    public  function getReservationByRoom(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'roomId' => 'required|numeric',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $reservations = Reservation::where('room_id', '=', $request->roomId)
            ->where('expire_date', '>=', now())->where('is_available', '=', 0)->get();
        return ['result' => 'success', 'code' => 1, 'reservations' => $reservations, "error" => ""];
    }

    public  function getReservation()
    {
        return Reservation::all();
    }

    public  function setReservationUnavailable(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|integer|exists:reservations,id'
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        Reservation::find($request->id)->update(['is_available' => 1]);
        return ['result' => 'success', 'code' => 1, 'error' => ''];
    }

    public static function checkStudentHasActiveReservation($student_id)
    {
        $today = date("Y-m-d");
        $reservation = Reservation::where('student_id', $student_id)->where('start_date', '<=', $today)->where('expire_date', '>=', $today)->where('is_available', '=', 0)->first();
        return $reservation;
    }
}
