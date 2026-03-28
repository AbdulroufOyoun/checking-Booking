<?php

namespace App\Http\Controllers;

use App\Models\PeakDay;
use App\Models\PeakMonth;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomtypePricingplan;
use App\Models\Suite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Reservation\MakeReservationRequest;
use App\Http\Requests\Reservation\CheckReservationRequest;
use App\Http\Requests\Reservation\GetRoomPriceRequest;
use App\Http\Requests\Reservation\GetReservationByDateRequest;
use App\Models\RoomPrice;
use App\Models\RoomPriceMaxDay;

class ReservationController extends Controller
{
    public function makeReservation(MakeReservationRequest $request)
    {
        try {
            DB::beginTransaction();

            // حساب سعر الغرف تلقائياً (مثل getRoomPrice)
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->expire_date);
            $nights = $startDate->diffInDays($endDate);
            $start = $startDate->toDateString();
            $end = $endDate->toDateString();
            $totalBasePrice = 0;
            $roomsData = [];


            if ($request->has('rooms') && is_array($request->rooms) && !empty($request->rooms)) {
                foreach ($request->rooms as $roomData) {
                    if (isset($roomData['suite_id']) && $roomData['suite_id']) {
                        $suite = Suite::find($roomData['suite_id']);
                        if (!$suite) {
                            throw new \Exception("Suite not found: " . $roomData['suite_id']);
                        }

                        $suiteRooms = $suite->rooms()->where('active', 1)->get();

                        if ($suiteRooms->isEmpty()) {
                            throw new \Exception("No active rooms found in suite: " . $roomData['suite_id']);
                        }


                        foreach ($suiteRooms as $room) {
                            $roomPrice = $this->calculateRoomPrice(
                                $room,
                                $start,
                                $end,
                                $request->rent_type,
                                $request->price_calculation_mode ?? 0
                            );

                            $totalBasePrice += $roomPrice;

                            $roomsData[] = [
                                'room_id'   => $room->id,
                                'suite_id'  => $roomData['suite_id'],
                                'price'     => $roomPrice,
                            ];
                        }
                    } else {
                        $room = Room::find($roomData['room_id']);
                        if (!$room) {
                            throw new \Exception("Room not found: " . $roomData['room_id']);
                        }

                        $roomPrice = $this->calculateRoomPrice(
                            $room,
                            $start,
                            $end,
                            $request->rent_type,
                            $request->price_calculation_mode ?? 0
                        );

                        $totalBasePrice += $roomPrice;

                        $roomsData[] = [
                            'room_id'   => $roomData['room_id'],
                            'suite_id'  => $roomData['suite_id'] ?? null,
                            'price'     => $roomPrice,
                        ];
                    }
                }
            }

            foreach ($roomsData as $index => $roomData) {
                $room = Room::find($roomData['room_id']);
                if (!$room) {
                    throw new \Exception('Room not found: ' . $roomData['room_id']);
                }
                if (!$this->isRoomAvailable($roomData['room_id'], $startDate->toDateString(), $endDate->toDateString())) {
                    throw new \Exception('Room ' . ($room->room_number ?? $roomData['room_id']) . ' is not available for dates ' . $start . ' to ' . $end);
                }
            }

             $user = auth()->user();
            $discount = $request->discount ?? 0;
            if ($discount > 0) {
                $userDiscount = $user->discount;
                if (!$userDiscount || !$userDiscount->is_active) {
                    throw new \Exception('You do not have active discount permission');
                }
                $hasPermission = false;
                if ($userDiscount->percent>0) {

                    $hasPermission = $totalBasePrice *$user->discount->percent/100 >= $discount;
                    if (!$hasPermission) {
                    throw new \Exception('Discount amount exceeds your permission (max ' .  $userDiscount->percent.  '%)');
                }
                } else {
                    $hasPermission = $userDiscount->fixed_amount >= $discount;
                     if (!$hasPermission) {
                    throw new \Exception('Discount amount exceeds your permission (max '. $userDiscount->fixed_amount . ')');
                }
                }
            }
            $extras = $request->extras ?? 0;
            $penalties = $request->penalties ?? 0;

             $subtotal = $totalBasePrice - $discount + $extras + $penalties;
            $taxes = $subtotal *15/100;

             $total = $subtotal + $taxes;

            $reservation = Reservation::create([
                'client_id'          => $request->client_id,
                'start_date'         => $request->start_date,
                'nights'             => $nights,
                'expire_date'        => $request->expire_date,
                'reservation_type'   => $request->reservation_type,
                'reservation_status' => $request->reservation_status ?? 0,
                'stay_reason_id'     => $request->stay_reason_id,
                'reservation_source_id' => $request->reservation_source_id,
                'rent_type'          => $request->rent_type,
                'base_price'         => $totalBasePrice,
                'discount'           => $discount,
                'extras'             => $extras,
                'penalties'          => $penalties,
                'subtotal'           => $subtotal,
                'taxes'              => $taxes,
                'total'              => $total,
                'logedin'            => $request->logedin ?? 1,
                'login_time'         => $request->login_time,
                'user_id'            => $user->id,
            ]);

            foreach ($roomsData as $roomData) {
                $reservationRoom = ReservationRoom::create([
                    'reservation_id' => $reservation->id,
                    'room_id'        => $roomData['room_id'],
                    'suite_id'       => $roomData['suite_id'],
                    'price'          => $roomData['price'],
                ]);

                // Create room_price for this reservation_room
                $room = Room::find($roomData['room_id']);
                $roomType = $room->roomType;
                $roomTypePlan = $roomType ? RoomtypePricingplan::where('roomtype_id', $room->room_type_id)->first() : null;
                $pricingPlanId = $roomTypePlan ? $roomTypePlan->pricingplan_id : null;

                $roomPriceData = [
                    'reservation_room_id' => $reservationRoom->id,
                    'pricing_plan_daily' => $roomTypePlan ? $roomTypePlan->DailyPrice : ($roomType ? $roomType->Min_daily_price : 0),
                    'pricing_plan_monthly' => $roomTypePlan ? $roomTypePlan->MonthlyPrice : ($roomType ? $roomType->Min_monthly_price : 0),
                    'daily_price' => $roomTypePlan ? $roomTypePlan->DailyPrice : ($roomType ? $roomType->Min_daily_price : 0),
                    'monthly_price' => $roomTypePlan ? $roomTypePlan->MonthlyPrice : ($roomType ? $roomType->Min_monthly_price : 0),
                    'max_price' => $roomType ? $roomType->Max_daily_price : 0,
                    'min_price' => $roomType ? $roomType->Min_daily_price : 0,
                ];

                $roomPrice = RoomPrice::create($roomPriceData);

                // Create room_price_max_days for days 1-7 with monthly_price
                for ($day = 1; $day <= 7; $day++) {
                    RoomPriceMaxDay::create([
                        'room_price_id' => $roomPrice->id,
                        'day' => $day,
                        'monthly_price' => $roomPriceData['monthly_price'],
                    ]);
                }
            }

            DB::commit();
            return \SuccessData('Reservation created successfully', $reservation);
        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed($e->getMessage());
        }
    }

    /**
     * NEW: Check if room is available for given dates
     * Reuses logic from checkReservation
     */
    private function isRoomAvailable($roomId, $startDate, $endDate): bool
    {
        return !ReservationRoom::where('room_id', $roomId)
            ->whereHas('reservation', function ($query) use ($startDate, $endDate) {
                $query->where('expire_date', '>', $startDate)  // Changed >= now() to > startDate, allows end_date == start_date
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<', $endDate)
                            ->orWhere('expire_date', '>', $startDate)
                            ->orWhere(function ($q) use ($startDate, $endDate) {
                                $q->where('start_date', '<=', $startDate)
                                    ->where('expire_date', '>=', $endDate);
                            });
                    });
            })->exists();
    }

    /**
     * حساب سعر الغرفة تلقائياً (نفس منطق getRoomPrice)
     */
    private function calculateRoomPrice($room, $start, $end, $rentType, $priceMode = 0)
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        $roomType = RoomType::find($room->room_type_id);
        $roomTypePlan = RoomtypePricingplan::with(['pricingplan', 'roomType'])->where('roomtype_id', $room->room_type_id)
            ->whereHas('pricingplan', function ($query) use ($start, $end) {
                $query->where('StartDate', '<=', $end)
                    ->where('EndDate', '>=', $start);
            })->first();

        $totalPrice = 0;

        // يومي
        if ($rentType == 0) {
            $nights = $startDate->diffInDays($endDate);

            switch ($priceMode) {
                case 0: // Current mixed logic
                    if ($roomTypePlan) {
                        $planStart = Carbon::parse($roomTypePlan->pricingplan->StartDate);
                        $planEnd = Carbon::parse($roomTypePlan->pricingplan->EndDate);

                        if ($start >= $planStart->toDateString() && $end <= $planEnd->toDateString()) {
                            $totalPrice = $roomTypePlan->DailyPrice * $nights;
                        } else {
                            switch ($roomTypePlan->pricingplan->ActiveType) {
                                case 0: // Const
                                    $totalPrice = $roomTypePlan->roomType->Min_daily_price * $nights;
                                    break;
                                case 1: // As Per Day
                                    $dailyPrices = [];
                                    for ($date = $startDate->copy(); $date->lt($endDate); $date->addDay()) {
                                        if ($date->between($planStart, $planEnd)) {
                                            $dailyPrices[] = $roomTypePlan->DailyPrice;
                                        } else {
                                            $dayName = $date->format('l');
                                            $dailyPrices[] = $this->checkPeakDay($dayName)
                                                ? $roomTypePlan->roomType->Max_daily_price
                                                : $roomTypePlan->roomType->Min_daily_price;
                                        }
                                    }
                                    $totalPrice = array_sum($dailyPrices);
                                    break;
                                case 2: // Plan Price
                                    $totalPrice = $roomTypePlan->DailyPrice * $nights;
                                    break;
                            }
                        }
                    } else {
                        // Fallback to roomtype
                        switch ($roomType->active_type) {
                            case 0:
                                $totalPrice = $roomType->Min_daily_price * $nights;
                                break;
                            case 1:
                                $dailyPrices = [];
                                for ($date = $startDate->copy(); $date->lt($endDate); $date->addDay()) {
                                    $dayName = $date->format('l');
                                    $dailyPrices[] = $this->checkPeakDay($dayName)
                                        ? $roomType->Max_daily_price
                                        : $roomType->Min_daily_price;
                                }
                                $totalPrice = array_sum($dailyPrices);
                                break;
                            case 2:
                                $totalPrice = $roomType->Max_daily_price * $nights;
                                break;
                        }
                    }
                    break;

                case 1: // Pricing plan only (fixed DailyPrice)
                    if ($roomTypePlan) {
                        $totalPrice = $roomTypePlan->DailyPrice * $nights;
                    } else {
                        throw new \Exception('No pricing plan found for price mode 1');
                    }
                    break;

                case 2: // Roomtype only (min/max prices)
                    switch ($roomType->active_type) {
                        case 0:
                            $totalPrice = $roomType->Min_daily_price * $nights;
                            break;
                        case 1:
                            $dailyPrices = [];
                            for ($date = $startDate->copy(); $date->lt($endDate); $date->addDay()) {
                                $dayName = $date->format('l');
                                $dailyPrices[] = $this->checkPeakDay($dayName)
                                    ? $roomType->Max_daily_price
                                    : $roomType->Min_daily_price;
                            }
                            $totalPrice = array_sum($dailyPrices);
                            break;
                        case 2:
                            $totalPrice = $roomType->Max_daily_price * $nights;
                            break;
                    }
                    break;
            }
        }
        // شهري - similar logic with mode (simplified for now)
        elseif ($rentType == 1) {
            $numberOfMonths = $startDate->diffInMonths($endDate);
            if ($numberOfMonths < 1) $numberOfMonths = 1;

            switch ($priceMode) {
                case 0: // Current
                case 1: // Pricing plan
                    if ($roomTypePlan) {
                        $totalPrice = $roomTypePlan->MonthlyPrice * $numberOfMonths;
                    } else {
                        // fallback
                        $totalPrice = $roomType->Min_monthly_price * $numberOfMonths;
                    }
                    break;
                case 2: // Roomtype
                    $totalPrice = $roomType->Min_monthly_price * $numberOfMonths;
                    break;
            }
        }

        return $totalPrice;
    }

    public  function checkReservation(CheckReservationRequest $request)
    {
        try {
            $perPage = \returnPerPage();
            $availableRoomsQuery = Room::where('building_id', '=', $request->building_id)->where("roomStatus", '=', 1)
                ->where('active', '=', 1);
            if ($request->filled('room_type')) {
                $availableRoomsQuery->where('room_type_id', '=', $request->room_type);
            }
            if ($request->filled('suite_id')) {
                $availableRoomsQuery->where('suite_id', '=', $request->suite_id);
            }
            if ($request->filled('floor_id')) {
                $availableRoomsQuery->where('floor_id', '=', $request->floor_id);
            }
            $availableRoomsQuery->whereDoesntHave('reservationRooms.reservation', function ($reservationQuery) use ($request) {
                $reservationQuery->where('expire_date', '>', $request->start_date)
                    ->where(function ($q) use ($request) {
                        $q->where('start_date', '<', $request->expire_date)
                            ->orWhere('expire_date', '>', $request->start_date)
                            ->orWhere(function ($q) use ($request) {
                                $q->where('start_date', '<=', $request->start_date)
                                    ->where('expire_date', '>=', $request->expire_date);
                            });
                    });
            });
            //  $availableRooms = AvailableRoomResource::collection($availableRoomsQuery->paginate($perPage));
             $availableRooms = $availableRoomsQuery->paginate($perPage);
            return \Pagination($availableRooms);
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
            $query->whereHas('reservationRooms.room', function ($roomQuery) use ($request) {
                if ($request->has('building_id') && $request->building_id) {
                    $roomQuery->where('building_id', $request->building_id);
                }
                if ($request->has('floor_id') && $request->floor_id) {
                    $roomQuery->where('floor_id', $request->floor_id);
                }
                if ($request->has('suite_id') && $request->suite_id) {
                    $roomQuery->where('suite_id', $request->suite_id);}
                    ;});
            $reservations = $query->get();
            if ($reservations->isEmpty()) {}
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }


}

