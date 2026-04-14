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
use App\Models\RoomPriceMaxMonth;
use Carbon\CarbonPeriod;

class ReservationController extends Controller
{
//     use Carbon\Carbon;
// use Carbon\CarbonPeriod;
// use Illuminate\Support\Facades\DB;

public function makeReservation(MakeReservationRequest $request)
{
    try {
        DB::beginTransaction();

        // 1. إعداد التواريخ والبيانات الأساسية
        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->expire_date)->startOfDay();
        $nights = $startDate->diffInDays($endDate);

        $totalBasePrice = 0;
        $roomsData = [];

        // تحسين الأداء: جلب إعدادات الذروة العامة مرة واحدة
        $globalPeakMonths = PeakMonth::where('check', 1)->pluck('month_name_en')->toArray();
        $globalPeakDays = PeakDay::where('check', 1)->pluck('day_name_en')->toArray();

        // 2. معالجة الغرف/الأجنحة وحساب السعر المبدئي
        if ($request->has('rooms') && is_array($request->rooms)) {
            foreach ($request->rooms as $roomData) {
                $roomsToProcess = [];

                if (isset($roomData['suite_id']) && $roomData['suite_id']) {
                    $suite = Suite::findOrFail($roomData['suite_id']);
                    $roomsToProcess = $suite->rooms()->where('active', 1)->get();
                } else {
                    $roomsToProcess = [Room::findOrFail($roomData['room_id'])];
                }

                foreach ($roomsToProcess as $room) {
                    // فحص التوافر
                    if (!$this->isRoomAvailable($room->id, $startDate->toDateString(), $endDate->toDateString())) {
                        throw new \Exception("الغرفة رقم " . ($room->room_number) . " غير متاحة في هذه الفترة.");
                    }

                    // حساب السعر بناءً على المنطق المطور (Case 0, 1, 2)
                    $roomPrice = $this->calculateRoomPrice(
                        $room,
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                        $request->rent_type,
                        $request->price_calculation_mode ?? 0
                    );

                    $totalBasePrice += $roomPrice;
                    $roomsData[] = [
                        'room'     => $room,
                        'suite_id' => $roomData['suite_id'] ?? null,
                        'price'    => $roomPrice,
                    ];
                }
            }
        }

        // 3. الحسابات المالية (الخصم، الضرائب، الإضافات)
        $user = auth()->user();
        $discount = $request->discount ?? 0;
        // (يمكنك إضافة منطق التحقق من صلاحية الخصم هنا كما في كودك السابق)

        $extras = $request->extras ?? 0;
        $penalties = $request->penalties ?? 0;
        $subtotal = $totalBasePrice - $discount + $extras + $penalties;
$taxes = round($subtotal * 0.15, 2, PHP_ROUND_HALF_UP);
        $total = $subtotal + $taxes;

        // 4. إنشاء سجل الحجز الرئيسي
        $reservation = Reservation::create([
            'client_id'             => $request->client_id,
            'start_date'            => $request->start_date,
            'nights'                => $nights,
            'expire_date'           => $request->expire_date,
            'reservation_type'      => $request->reservation_type,
            'reservation_status'    => $request->reservation_status ?? (($request->pay_amount >= $total) ? 1 : 2),
            'stay_reason_id'        => $request->stay_reason_id,
            'reservation_source_id' => $request->reservation_source_id,
            'rent_type'             => $request->rent_type,
            'base_price'            => $totalBasePrice,
            'discount'              => $discount,
            'logedin'              => $request->logedin,
            'extras'                => $extras,
            'penalties'             => $penalties,
            'subtotal'              => $subtotal,
            'taxes'                 => $taxes,
            'total'                 => $total,
            'user_id'               => $user->id,
        ]);

        // 5. تسجيل الدفعة المالية (إن وجدت)
        if ($request->filled('pay_amount') && $request->pay_amount > 0) {
            ReservationPay::create([
                'reservation_id' => $reservation->id,
                'pay'            => $request->pay_amount,
                'type'           => $request->pay_type ?? 0,
                'user_id'        => $user->id,
            ]);
        }

        // 6. حفظ تفاصيل الغرف ولقطات الأسعار (Snapshots)
        $numRooms = count($roomsData);
        foreach ($roomsData as $data) {
            $room = $data['room'];
            $roomType = $room->roomType;

            // توزيع القيم المالية على الغرف
            $roomDiscount = $discount / $numRooms;
            $roomExtras = $extras / $numRooms;
            $roomPenalties = $penalties / $numRooms;
            $finalRoomPrice = ($data['price'] - $roomDiscount + $roomExtras + $roomPenalties) * 1.15;

            $resRoom = ReservationRoom::create([
                'reservation_id' => $reservation->id,
                'room_id'        => $room->id,
                'suite_id'       => $data['suite_id'],
                'price'          => $finalRoomPrice,
            ]);

            // تحديد تقاطع الخطة السعرية (إن وجدت)
            $roomTypePlan = RoomtypePricingplan::where('roomtype_id', $room->room_type_id)
                ->whereHas('pricingplan', function ($q) use ($startDate, $endDate) {
                    $q->where('StartDate', '<=', $endDate->toDateString())
                      ->where('EndDate', '>=', $startDate->toDateString());
                })->first();

            $savedStartPlan = null; $savedEndPlan = null;
            if ($roomTypePlan) {
                $pStart = Carbon::parse($roomTypePlan->pricingplan->StartDate);
                $pEnd = Carbon::parse($roomTypePlan->pricingplan->EndDate);
                $savedStartPlan = $startDate->max($pStart)->toDateString();
                $savedEndPlan = $endDate->min($pEnd)->toDateString();
            }

            // --- الحفظ الانتقائي (Selective Saving) ---
            $roomPriceData = [
                'reservation_room_id' => $resRoom->id,
                'start_plan'          => $savedStartPlan,
                'end_plan'            => $savedEndPlan,
            ];

            if ($request->rent_type == 0) { // حجز يومي
                if ($roomTypePlan) $roomPriceData['pricing_plan_daily'] = $roomTypePlan->DailyPrice;

                if ($roomType->active_type == 0 || $roomType->active_type == 1)
                    $roomPriceData['min_price'] = $roomType->Min_daily_price;
                if ($roomType->active_type == 2 || $roomType->active_type == 1)
                    $roomPriceData['max_price'] = $roomType->Max_daily_price;

            } else { // حجز شهري
                if ($roomTypePlan) $roomPriceData['pricing_plan_monthly'] = $roomTypePlan->MonthlyPrice;

                if ($roomType->active_type == 0 || $roomType->active_type == 1)
                    $roomPriceData['min_month'] = $roomType->Min_monthly_price;
                if ($roomType->active_type == 2 || $roomType->active_type == 1)
                    $roomPriceData['max_month'] = $roomType->Max_monthly_price;
            }

            $roomPriceRecord = RoomPrice::create($roomPriceData);

            // --- حفظ أيام/أشهر الذروة التي يمر بها الحجز حصراً ---
            if ($roomType->active_type == 1) {
                if ($request->rent_type == 0) { // فحص الأيام
                    $period = CarbonPeriod::create($startDate, $endDate->copy()->subDay());
                    foreach ($period as $date) {
                        if (in_array($date->format('l'), $globalPeakDays)) {
                            RoomPriceMaxDay::firstOrCreate([
                                'room_price_id' => $roomPriceRecord->id,
                                'day'           => $date->dayOfWeekIso,
                            ]);
                        }
                    }
                } else { // فحص الأشهر
                    $current = $startDate->copy()->startOfMonth();
                    $final   = $endDate->copy()->startOfMonth();
                    while ($current <= $final) {
                        if (in_array($current->format('F'), $globalPeakMonths)) {
                            RoomPriceMaxMonth::firstOrCreate([
                                'room_price_id' => $roomPriceRecord->id,
                                'month'         => $current->month,
                            ]);
                        }
                        $current->addMonth();
                    }
                }
            }
        }

        DB::commit();
        return \SuccessData('تم إنشاء الحجز بنجاح', $reservation);

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
                return \Failed('Reservation already cancelled, cannot refund');
            }

            $paymentsIn = $reservation->payments()->where('type', ReservationPay::TYPE_PAYMENT)->sum('pay');
            $refundsOut = $reservation->payments()->where('type', ReservationPay::TYPE_REFUND)->sum('pay');
            $netPaid = $paymentsIn - $refundsOut;

            if ($netPaid <= 0) {
                return \Failed('No payments available for refund');
            }

            $now = Carbon::now();
            $startDate = Carbon::parse($reservation->start_date);
            $expireDate = Carbon::parse($reservation->expire_date);

            $daysBeforeCheckin = (int) $now->diffInDays($startDate, false);

            $duringStay = $now->betweenIncluded($startDate, $expireDate) ? 1 : 0;

            if ($netPaid >= $reservation->total) {
                $paymentStatus = 2; // دفع كامل الكلي
            } elseif ($netPaid > 0) {
                $paymentStatus = 1; // دفع جزئي
            } else {
                $paymentStatus = 0; // لم يدفع
            }

            $policyQuery = RefundPolicy::where('during_stay', $duringStay)
                ->where('payment_status', $paymentStatus)
                ->where('days_before_checkin', '>=', $daysBeforeCheckin)
                ->orderBy('days_before_checkin', 'asc')
                ->first();

            if (!$policyQuery) {
                $errorMsg = "No refund policy applies to your case. Days remaining: " . $daysBeforeCheckin . ", payment status: " . $paymentStatus;
                return \Failed($errorMsg);
            }

            $refundAmount = ($reservation->total * $policyQuery->refund_percent) / 100;

            $finalRefundAmount = min($refundAmount, $netPaid);

            if ($finalRefundAmount <= 0) {
                return \Failed('Based on cancellation policy, no refund amount due at this time.');
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

                $reservation->update(['reservation_status' => 2]);

                // DB::commit();

                return \SuccessData('Refund processed successfully', [
                    'refund_id' => $refundPay->id,
                    'amount' => $finalRefundAmount,
                    'policy_name' => $policyQuery->name,
                    'days_calculated' => $daysBeforeCheckin
                ]);

            } catch (\Exception $e) {
                // DB::rollBack();
                return \Failed('Technical error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed($e->getMessage());
        }
    }

    /**
     * NEW: Check if room is available for given dates
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
        return !ReservationRoom::where('room_id', $roomId)
            ->whereHas('reservation', function ($query) use ($startDate, $endDate) {
                $query
                // ->where('reservation_status', '>', 0) // Confirmed or partial payment
                      ->where('start_date', '<', $endDate)
                      ->where('expire_date', '>', $startDate);
            })->exists();
    }


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

        // daily
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
// monthly - same structure as daily (case 0,1,2)
        elseif ($rentType == 1) {

            $daysInPeriod = $startDate->diffInDays($endDate, false);

            switch ($priceMode) {
              case 0: // Mixed plan + fallback (نظام الكتل الحجزية المتطور)
    // 1. تعريف المتغيرات والأسعار الأساسية
    $monthlyMin = $roomType->Min_monthly_price;
    $monthlyMax = $roomType->Max_monthly_price;
    $dailyMin   = $roomType->Min_daily_price;
    $dailyMax   = $roomType->Max_daily_price;

    $totalPrice       = 0;
    $extraPart        = 0;
    $monthlyTotalPart = 0;

    // 2. معالجة تواريخ الخطة (Pricing Plan) إن وجدت
    $planStart = $roomTypePlan ? Carbon::parse($roomTypePlan->pricingplan->StartDate)->startOfDay() : null;
    $planEnd   = $roomTypePlan ? Carbon::parse($roomTypePlan->pricingplan->EndDate)->startOfDay() : null;
    $planPrice = $roomTypePlan ? $roomTypePlan->MonthlyPrice : 0;

    $tempDate  = $startDate->copy();
    $targetDay = $startDate->day;

    // 3. حساب الأشهر الكاملة (باعتبارها "كتلة حجزية" من تاريخ البداية لتاريخ مماثل في الشهر التالي)
    while (true) {
        $nextMonth = $tempDate->copy()->addMonthNoOverflow();

        // تحديد اليوم المستهدف في الشهر التالي
        if ($startDate->isLastOfMonth()) {
            $potentialNext = $nextMonth->copy()->day($nextMonth->daysInMonth);
        } else {
            $potentialNext = $nextMonth->copy()->day(min($targetDay, $nextMonth->daysInMonth));
        }

        // إذا كانت الكتلة الشهرية تقع بالكامل ضمن تاريخ نهاية الحجز
        if ($potentialNext->lte($endDate)) {
            $chunkDays = $tempDate->diffInDays($potentialNext);
            $chunkPrice = 0;

            // فحص كل يوم داخل "الكتلة الشهرية" بشكل منفرد
            for ($i = 0; $i < $chunkDays; $i++) {
                $currDay = $tempDate->copy()->addDays($i);
                $daysInCurrentMonth = $currDay->daysInMonth;
                $mName = $currDay->format('F');

                // أ- فحص إذا كان اليوم يتبع لخطة سعر نشطة
                $inPlan = false;
                if ($roomTypePlan && $planStart && $planEnd) {
                    if ($currDay->betweenIncluded($planStart, $planEnd)) {
                        $inPlan = true;
                    }
                }

                if ($inPlan) {
                    // حساب حصة اليوم من سعر الخطة الشهري
                    $chunkPrice += ($planPrice / $daysInCurrentMonth);
                } else {
                    // ب- في حال كان اليوم خارج الخطة، نعتمد على نوع تسعير الغرفة (ActiveType)
                    $monthlyFallbackForThisDay = $monthlyMin; // الافتراضي هو الأدنى

                    if ($roomType->active_type == 2) {
                        $monthlyFallbackForThisDay = $monthlyMax;
                    } elseif ($roomType->active_type == 1) {
                        // التحقق من ذروة الشهر (Peak Month)
                        $monthlyFallbackForThisDay = $this->checkPeakMonth($mName) ? $monthlyMax : $monthlyMin;
                    }

                    $chunkPrice += ($monthlyFallbackForThisDay / $daysInCurrentMonth);
                }
            }

            $monthlyTotalPart += $chunkPrice;
            $tempDate = $potentialNext;

            if ($tempDate->eq($endDate)) break;
        } else {
            break; // الخروج من حلقة الأشهر الكاملة للتعامل مع الأيام المتبقية
        }
    }

    $totalPrice = $monthlyTotalPart;

    // 4. حساب الأيام المتبقية (Extra Days) التي لا تشكل شهراً كاملاً
    $extraDays = $tempDate->diffInDays($endDate);

    if ($extraDays > 0) {
        for ($d = 0; $d < $extraDays; $d++) {
            $extraDate = $tempDate->copy()->addDays($d);
            $dayName = $extraDate->format('l');

            // فحص إذا كان اليوم الإضافي يقع ضمن الخطة
            $inPlan = false;
            if ($roomTypePlan && $planStart && $planEnd) {
                if ($extraDate->betweenIncluded($planStart, $planEnd)) {
                    $inPlan = true;
                }
            }

            if ($inPlan) {
                // يحسب كنسبة من سعر الخطة الشهري
                $extraPart += ($planPrice / $extraDate->daysInMonth);
            } else {
                // يحسب كـ "سعر يومي" (Daily Price) بناءً على يوم ذروة أم لا
                $extraPart += $this->checkPeakDay($dayName) ? $dailyMax : $dailyMin;
            }
        }
        $totalPrice += $extraPart;
    }

    // 5. التقريب النهائي الموحد
    $totalPrice = round($totalPrice, 2, PHP_ROUND_HALF_UP);
    if (abs($totalPrice - round($totalPrice, 0)) < 0.005) {
        $totalPrice = round($totalPrice, 0);
    }
    break;

                case 1: // Plan only
                    if ($roomTypePlan) {
                        // return $endDate->daysInMonth;
                        $totalPrice = round($roomTypePlan->MonthlyPrice + ($roomTypePlan->MonthlyPrice * max(0, ($daysInPeriod - $startDate->daysInMonth))) / $endDate->daysInMonth, 2, PHP_ROUND_HALF_UP);
                        if (abs($totalPrice - round($totalPrice, 0)) < 0.005) {
                            $totalPrice = round($totalPrice, 0);
                        }
                    } else {
                        throw new \Exception('No pricing plan for mode 1');
                    }
                    break;

case 2:
  $monthlyMin = $roomType->Min_monthly_price;
    $monthlyMax = $roomType->Max_monthly_price;
    $dailyMin = $roomType->Min_daily_price;
    $dailyMax = $roomType->Max_daily_price;

    $totalPrice = 0;
    $extraPart = 0;
    $fullMonths = 0;
    $monthlyTotalPart = 0;

    // استخدام متغير مؤقت للتحرك خطوة بخطوة
    $tempDate = $startDate->copy();

    // تحديد اليوم المستهدف (مثلاً يوم 30)
    $targetDay = $startDate->day;

    // 1. حساب الأشهر الكاملة وحساب سعر كل شهر على حدة
    while (true) {
        $nextMonth = $tempDate->copy()->addMonthNoOverflow();

        if ($startDate->isLastOfMonth()) {
            $potentialNext = $nextMonth->copy()->day($nextMonth->daysInMonth);
        } else {
            $potentialNext = $nextMonth->copy()->day(min($targetDay, $nextMonth->daysInMonth));
        }

        if ($potentialNext->lte($endDate)) {
            // تحديد سعر هذا الشهر بناءً على نوع النشاط
            $currentMonthPrice = 0;
            switch ($roomType->active_type) {
                case 0:
                    $currentMonthPrice = $monthlyMin;
                    break;
                case 2:
                    $currentMonthPrice = $monthlyMax;
                    break;
                case 1:
                    // حساب النسبة والتناسب لعدد الأيام في كل شهر ميلادي يمر به هذا "الشهر الحجزي"
                    $chunkDays = $tempDate->diffInDays($potentialNext);
                    $chunkPrice = 0;

                    // المرور على أيام هذا الشهر الحجزي يوماً بيوم لمعرفة أي شهر ميلادي تتبع
                    for ($i = 0; $i < $chunkDays; $i++) {
                        $currDay = $tempDate->copy()->addDays($i);
                        $mName = $currDay->format('F'); // اسم الشهر الميلادي لهذا اليوم بالتحديد

                        // نحدد إذا كان هذا اليوم يقع في شهر ذروة أم لا
                        $monthlyValueForThisDay = $this->checkPeakMonth($mName) ? $monthlyMax : $monthlyMin;

                        // نضيف نسبة هذا اليوم من إجمالي أيام الشهر الحجزي
                        $chunkPrice += ($monthlyValueForThisDay / $chunkDays);
                    }
                    $currentMonthPrice = $chunkPrice;
                    break;
            }

            $monthlyTotalPart += $currentMonthPrice;
            $fullMonths++;
            $tempDate = $potentialNext;

            if ($tempDate->eq($endDate)) break;
        } else {
            break;
        }
    }

    $totalPrice = $monthlyTotalPart;

    // 2. حساب الأيام المتبقية (إن وجدت)
    $extraDays = $tempDate->diffInDays($endDate);

    if ($extraDays > 0) {
        for ($d = 0; $d < $extraDays; $d++) {
            $extraDate = $tempDate->copy()->addDays($d);
            $dayName = $extraDate->format('l');
            $extraPart += $this->checkPeakDay($dayName) ? $dailyMax : $dailyMin;
        }
        $totalPrice += $extraPart;
    }

    // 3. تقريب السعر النهائي
    $totalPrice = round($totalPrice, 2, PHP_ROUND_HALF_UP);
    if (abs($totalPrice - round($totalPrice, 0)) < 0.005) {
        $totalPrice = round($totalPrice, 0);
    }
            }}
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
                // daily
                $endDate = Carbon::parse($request->endDate);
            } else {
                // monthly
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

            // daily reservation
            if ($request->typeReservation == 0) {
                if ($roomTypePlan) {
                    $planStart = Carbon::parse($roomTypePlan->pricingplan->StartDate);
                    $planEnd   = Carbon::parse($roomTypePlan->pricingplan->EndDate);

                    if ($start >= $planStart->toDateString() && $end <= $planEnd->toDateString()) {
                        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                            $days[$date->toDateString()] = $roomTypePlan->DailyPrice;
                            $totalPrice += $roomTypePlan->DailyPrice;
                        }
                    } else {
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

                // no plan
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

            //  monthly reservation
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
                    // no plan
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
