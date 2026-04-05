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
use App\Models\RefundPolicy;
use App\Models\ReservationPay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Reservation\MakeReservationRequest;
use App\Http\Requests\Reservation\CheckReservationRequest;
use App\Http\Requests\Reservation\GetRoomPriceRequest;
use App\Http\Requests\Reservation\GetReservationByDateRequest;
use App\Http\Requests\Reservation\RefundRequest;
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
                    $room = Room::find($roomData['room_id']);
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

            // Auto-determine reservation_status based on payment (if not explicitly provided)
            // 0: unconfirmed/no payment, 1: confirmed/full payment, 2: partial payment (pay >0 but < total)
            $reservation_status = $request->reservation_status ?? 0;
            if (!$request->filled('reservation_status')) {
                if ($request->filled('pay_amount') && $request->pay_amount > 0) {
                    $reservation_status = ($request->pay_amount >= $total) ? 1 : 2;
                }
            }

            $reservation = Reservation::create([
                'client_id'          => $request->client_id,
                'start_date'         => $request->start_date,
                'nights'             => $nights,
                'expire_date'        => $request->expire_date,
                'reservation_type'   => $request->reservation_type,
                'reservation_status' => $reservation_status,
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

            // Create initial payment if provided
            if ($request->filled('pay_amount') && $request->pay_amount > 0) {
                if ($request->pay_amount > $total) {
                    throw new \Exception('Pay amount cannot exceed reservation total: ' . $total);
                }
                if (!in_array($request->pay_type ?? 0, [0, 1])) {
                    throw new \Exception('Invalid pay_type. Use 0 for payment, 1 for refund.');
                }
                ReservationPay::create([
                    'reservation_id' => $reservation->id,
                    'pay' => $request->pay_amount,
                    'type' => $request->pay_type ?? 0,
                    'user_id' => auth()->user()->id,
                ]);
            }

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
                    'max_price' => $roomType ? $roomType->Max_daily_price : 0,
                    'min_price' => $roomType ? $roomType->Min_daily_price : 0,
                ];

                $roomPrice = RoomPrice::create($roomPriceData);

                // Create room_price_max_days for days 1-7 with monthly_price
                for ($day = 1; $day <= 7; $day++) {
                    RoomPriceMaxDay::create([
                        'room_price_id' => $roomPrice->id,
                        'day' => $day,
                        'monthly_price' => $roomPriceData['pricing_plan_monthly'],
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
     * Process refund for a reservation using refund policy discount
     */
    public function refund(RefundRequest $request)
    {
        try {
            DB::beginTransaction();
            $reservation = Reservation::with(['payments', 'client'])->findOrFail($request->reservation_id);

            // Prevent refund if already canceled (status 2)
            if ($reservation->reservation_status == 2) {
                return \Failed('الحجز ملغى مسبقاً، لا يمكن الاسترجاع');
            }

            // 1. حساب صافي المبلغ المدفوع
            $paymentsIn = $reservation->payments()->where('type', ReservationPay::TYPE_PAYMENT)->sum('pay');
            $refundsOut = $reservation->payments()->where('type', ReservationPay::TYPE_REFUND)->sum('pay');
            $netPaid = $paymentsIn - $refundsOut;

            if ($netPaid <= 0) {
                return \Failed('لا يوجد مبالغ مدفوعة قابلة للاسترجاع');
            }

            // 2. حساب التوقيت بدقة
            $now = Carbon::now();
            $startDate = Carbon::parse($reservation->start_date);
            $expireDate = Carbon::parse($reservation->expire_date);

            // حساب الفرق بالأيام (رقم موجب إذا كان قبل الدخول)
            $daysBeforeCheckin = (int) $now->diffInDays($startDate, false);

            // تحديد هل نحن أثناء الإقامة
            $duringStay = $now->betweenIncluded($startDate, $expireDate) ? 1 : 0;

            // 3. تحديد حالة الدفع من reservation_pay (كما مطلوب)
            // 0: لا يوجد، 1: جزئي (غير كامل)، 2: كامل (total مدفوع)
            if ($netPaid >= $reservation->total) {
                $paymentStatus = 2; // دفع كامل الكلي
            } elseif ($netPaid > 0) {
                $paymentStatus = 1; // دفع جزئي
            } else {
                $paymentStatus = 0; // لم يدفع
            }

// 4. البحث عن السياسة الأقرب (nearest) للأيام المحسوبة - أعلى days_before_checkin <= actual days
            $policyQuery = RefundPolicy::where('during_stay', $duringStay)
                ->where('payment_status', $paymentStatus)
                ->where('days_before_checkin', '>=', $daysBeforeCheckin)
                ->orderBy('days_before_checkin', 'asc')
                ->first();

            // 5. في حال لم يجد سياسة
            if (!$policyQuery) {
                $errorMsg = "لا توجد سياسة استرجاع تنطبق على حالتك. الأيام المتبقية: " . $daysBeforeCheckin . "، حالة الدفع: " . $paymentStatus;
                return \Failed($errorMsg);
            }

            // 6. حساب المبلغ بناءً على السياسة
            $refundAmount = ($reservation->total * $policyQuery->refund_percent) / 100;

            // التأكد أننا لا نعيد أكثر مما دفعه العميل فعلياً
            $finalRefundAmount = min($refundAmount, $netPaid);

            if ($finalRefundAmount <= 0) {
                return \Failed('بناءً على سياسة الإلغاء، لا يوجد مبلغ مستحق للاسترجاع في هذا التوقيت.');
            }
                DB::commit();

            try {
            // DB::beginTransaction();

                // إنشاء سجل الاسترجاع
                 $refundPay = ReservationPay::create([
                    'reservation_id' => $reservation->id,
                    'pay' => $finalRefundAmount,
                    'type' => ReservationPay::TYPE_REFUND,
                    'user_id' => auth()->id(),
                ]);

                // تحديث حالة الحجز إلى canceled (status = 2 كما مطلوب)
                $reservation->update(['reservation_status' => 2]);

                // DB::commit();

                return \SuccessData('تمت عملية الاسترجاع بنجاح', [
                    'refund_id' => $refundPay->id,
                    'amount' => $finalRefundAmount,
                    'policy_name' => $policyQuery->name,
                    'days_calculated' => $daysBeforeCheckin
                ]);

            } catch (\Exception $e) {
                // DB::rollBack();
                return \Failed('خطأ تقني: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed($e->getMessage());
        }
    }

    /**
     * NEW: Check if room is available for given dates
     * Reuses logic from checkReservation
     */
private function isRoomAvailable($roomId, $startDate, $endDate)
    {
        // Check if room is active first
        $room = Room::where('id', $roomId)
            ->where('active', 1)
            ->where('roomStatus', 1)
            ->first();
        if (!$room) {
            return false;
        }
        // dd(ReservationRoom::where('room_id', $roomId)
        //     ->whereHas('reservation', function ($query) use ($startDate, $endDate) {
        //         $query
        //         // ->where('reservation_status', '>', 0) // Confirmed or partial payment
        //               ->where('start_date', '<', $endDate)
        //               ->where('expire_date', '>', $startDate);})->get());
        return !ReservationRoom::where('room_id', $roomId)
            ->whereHas('reservation', function ($query) use ($startDate, $endDate) {
                $query
                // ->where('reservation_status', '>', 0) // Confirmed or partial payment
                      ->where('start_date', '<', $endDate)
                      ->where('expire_date', '>', $startDate);
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
                            $totalPrice += $price
                                ? $roomType->Max_daily_price
                                : $roomType->Min_daily_price;
                            $days[$date->toDateString()] = $price;
                            $totalPrice += $price;
                        }
                        return SuccessData('Daily pricing calculated', ['days' => $days, 'totalPrice' => $totalPrice]);
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
                return SuccessData('Daily pricing calculated', ['days' => $days, 'totalPrice' => $totalPrice]);
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
                                return SuccessData('Monthly pricing calculated with peak months', [
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
                            return SuccessData('Monthly pricing calculated with peak months', [
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
                return SuccessData('Monthly pricing calculated', [
                    'startDate' => $startDate->toDateString(),
                    'endDate' => $endDate->toDateString(),
                    'numberOfMonths' => $numberOfMonths,
                    'months' => $months ?? [],
                    'totalPrice' => $totalPrice
                ]);
            }
        } catch (\Exception $e) {
            return Failed($e->getMessage());
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
                    $roomQuery->where('suite_id', $request->suite_id);
                }
            });
            $reservations = $query->get();
            if ($reservations->isEmpty()) {
                return SuccessData('No reservations found', []);
            }
            return SuccessData('Reservations found', $reservations);
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }
}
