<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use App\Models\RoomtypePricingplan;
use App\Models\Pricingplan;
use App\Http\Requests\RoomType\AddRoomTypeRequest;
use App\Http\Requests\RoomType\UpdateRoomTypeRequest;
use App\Http\Requests\RoomType\DeleteRoomTypeRequest;
use App\Http\Requests\RoomType\AddRoomtypePricingRequest;
use App\Http\Requests\RoomType\AddRoomtypePricingPlanRequest;
use App\Http\Requests\RoomType\UpdateRoomtypePricingRequest;
use App\Http\Requests\RoomType\DeleteRoomtypePricingRequest;
use Illuminate\Support\Facades\DB;

class RoomTypesController extends Controller
{

    public function addRoomType(AddRoomTypeRequest $request)
    {
        try {
            $roomType = RoomType::create([
                'name_ar'           => $request->name_ar,
                'name_en'           => $request->name_en,
                'description'       => $request->description,
                'Max_daily_price'   => $request->Max_daily_price ?? 0,
                'Min_daily_price'   => $request->Min_daily_price,
                'Max_monthly_price' => $request->Max_monthly_price ?? 0,
                'Min_monthly_price' => $request->Min_monthly_price,
                'Max_yearly_price'  => $request->Max_yearly_price ?? 0,
                'Min_yearly_price'  => $request->Min_yearly_price,
                'active_type'       => $request->active_type,
            ]);
            return SuccessData('Room type added successfully', $roomType);
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function getRoomType()
    {
        try {
            $roomTypes = RoomType::all();
            return SuccessData('Room types retrieved successfully', $roomTypes);
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function updateRoomType(UpdateRoomTypeRequest $request)
    {
        try {
            $roomType = RoomType::findOrFail($request->id);

            $roomType->update([
                'name_ar'           => $request->name_ar,
                'name_en'           => $request->name_en,
                'description'       => $request->description,
                'Max_daily_price'   => $request->Max_daily_price ?? 0,
                'Min_daily_price'   => $request->Min_daily_price,
                'Max_monthly_price' => $request->Max_monthly_price ?? 0,
                'Min_monthly_price' => $request->Min_monthly_price,
                'Max_yearly_price'  => $request->Max_yearly_price  ?? 0,
                'Min_yearly_price'  => $request->Min_yearly_price,
                'active_type'       => $request->active_type,
            ]);

            return Success('Room type updated successfully');
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function deleteRoomType(DeleteRoomTypeRequest $request)
    {
        try {
            $roomType = RoomType::find($request->id);

            if ($roomType->rooms()->exists()) {
                return Failed('Can not delete this Room type');
            }

            $roomType->delete();

            return Success('Room type deleted successfully');
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }


    public function getRoomtypePricing()
    {
        try {
            $pricing = RoomtypePricingplan::with('pricingplan', 'roomType')->get();

            $formatted = $pricing->map(function ($item) {
                return [
                    'id' => $item->id,
                    'roomtype_id' => $item->roomtype_id,
                    'roomtype_nameAr' => $item->roomType?->name_ar,
                    'roomtype_nameEn' => $item->roomType?->name_en,
                    'pricingplan_id' => $item->pricingplan_id,
                    'pricingplan_nameAr' => $item->pricingplan?->NameAr,
                    'pricingplan_nameEn' => $item->pricingplan?->NameEn,
                    'pricingplan_StartDate' => $item->pricingplan?->StartDate,
                    'pricingplan_EndDate' => $item->pricingplan?->EndDate,
                    'pricingplan_ActiveType' => $item->pricingplan?->ActiveType,
                    'DailyPrice' => $item->DailyPrice,
                    'MonthlyPrice' => $item->MonthlyPrice,
                    'YearlyPrice' => $item->YearlyPrice,
                ];
            });
            return SuccessData('Pricing plans retrieved successfully', $formatted);
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function addRoomtypePricing(AddRoomtypePricingRequest $request)
    {
        try {
            DB::beginTransaction();

            $pricingPlan = Pricingplan::create([
                'NameAr' => $request->NameAr,
                'NameEn' => $request->NameEn,
                'StartDate' => $request->StartDate,
                'EndDate' => $request->EndDate,
                'ActiveType' => $request->ActiveType,
            ]);

            $roomTypePricing = RoomtypePricingplan::create([
                'roomtype_id' => $request->roomtype_id,
                'pricingplan_id' => $pricingPlan->id,
                'DailyPrice' => $request->DailyPrice,
                'MonthlyPrice' => $request->MonthlyPrice,
                'YearlyPrice' => $request->YearlyPrice,
            ]);

            DB::commit();
            $data = [
                'id' => $roomTypePricing->id,
                'roomtype_id' => $roomTypePricing->roomtype_id,
                'roomtype_nameAr' => $roomTypePricing->roomType->name_ar ?? '',
                'roomtype_nameEn' => $roomTypePricing->roomType->name_en ?? '',
                'pricingplan_id' => $pricingPlan->id,
                'pricingplan_nameAr' => $pricingPlan->NameAr,
                'pricingplan_nameEn' => $pricingPlan->NameEn,
                'pricingplan_StartDate' => $pricingPlan->StartDate,
                'pricingplan_EndDate' => $pricingPlan->EndDate,
                'pricingplan_ActiveType' => $pricingPlan->ActiveType,
                'DailyPrice' => $roomTypePricing->DailyPrice,
                'MonthlyPrice' => $roomTypePricing->MonthlyPrice,
                'YearlyPrice' => $roomTypePricing->YearlyPrice,
            ];
            return SuccessData('Pricing plan added successfully', $data);
        } catch (\Exception $e) {
            DB::rollBack();
            return Failed($e->getMessage());
        }
    }

    public function addRoomtypePricingPlan(AddRoomtypePricingPlanRequest $request)
    {
        try {
            DB::beginTransaction();

            $pricingPlan = Pricingplan::findOrFail($request->pricingplan_id);
            $roomTypePricing = RoomtypePricingplan::create([
                'roomtype_id' => $request->roomtype_id,
                'pricingplan_id' => $pricingPlan->id,
                'DailyPrice' => $request->DailyPrice,
                'MonthlyPrice' => $request->MonthlyPrice,
                'YearlyPrice' => $request->YearlyPrice ?? 0,
            ]);

            DB::commit();

            $data = [
                'id' => $roomTypePricing->id,
                'roomtype_id' => $roomTypePricing->roomtype_id,
                'roomtype_nameAr' => $roomTypePricing->roomType->name_ar ?? '',
                'roomtype_nameEn' => $roomTypePricing->roomType->name_en ?? '',
                'pricingplan_id' => $pricingPlan->id,
                'pricingplan_nameAr' => $pricingPlan->NameAr,
                'pricingplan_nameEn' => $pricingPlan->NameEn,
                'pricingplan_StartDate' => $pricingPlan->StartDate,
                'pricingplan_EndDate' => $pricingPlan->EndDate,
                'pricingplan_ActiveType' => $pricingPlan->ActiveType,
                'DailyPrice' => $roomTypePricing->DailyPrice,
                'MonthlyPrice' => $roomTypePricing->MonthlyPrice,
                'YearlyPrice' => $roomTypePricing->YearlyPrice,
            ];
            return SuccessData('Roomtype pricing plan added successfully', $data);
        } catch (\Exception $e) {
            DB::rollBack();
            return Failed($e->getMessage());
        }
    }

    public function updateRoomtypePricing(UpdateRoomtypePricingRequest $request)
    {
        try {
            DB::beginTransaction();
            $pricingPlan = Pricingplan::findOrFail($request->pricingplan_id);
            $pricingPlan->update([
                'NameAr'    => $request->NameAr,
                'NameEn'    => $request->NameEn,
                'StartDate' => $request->StartDate,
                'EndDate'   => $request->EndDate,
                'ActiveType' => $request->ActiveType,
            ]);
            $roomTypePricing = RoomtypePricingplan::findOrFail($request->id);
            $roomTypePricing->update([
                'roomtype_id'    => $request->roomtype_id,
                'pricingplan_id' => $pricingPlan->id,
                'DailyPrice'     => $request->DailyPrice,
                'MonthlyPrice'   => $request->MonthlyPrice,
                'YearlyPrice'    => $request->YearlyPrice,
            ]);
            DB::commit();
            $data = [
                'id'                    => $roomTypePricing->id,
                'roomtype_id'           => $roomTypePricing->roomtype_id,
                'roomtype_nameAr'       => $roomTypePricing->roomType->name_ar ?? '',
                'roomtype_nameEn'       => $roomTypePricing->roomType->name_en ?? '',
                'pricingplan_id'        => $pricingPlan->id,
                'pricingplan_nameAr'    => $pricingPlan->NameAr,
                'pricingplan_nameEn'    => $pricingPlan->NameEn,
                'pricingplan_StartDate' => $pricingPlan->StartDate,
                'pricingplan_EndDate'   => $pricingPlan->EndDate,
                'pricingplan_ActiveType' => $pricingPlan->ActiveType,
                'DailyPrice'            => $roomTypePricing->DailyPrice,
                'MonthlyPrice'          => $roomTypePricing->MonthlyPrice,
                'YearlyPrice'           => $roomTypePricing->YearlyPrice,
            ];
            return SuccessData('Pricing plan updated successfully', $data);
        } catch (\Exception $e) {
            DB::rollBack();
            return Failed($e->getMessage());
        }
    }

    public function deleteRoomtypePricing(DeleteRoomtypePricingRequest $request)
    {
        try {
            DB::beginTransaction();

            $roomTypePricing = RoomtypePricingplan::findOrFail($request->id);
            $pricingPlanId = $roomTypePricing->pricingplan_id;
            $roomTypePricing->delete();

            $pricingPlan = Pricingplan::find($pricingPlanId);
            if ($pricingPlan) {
                $pricingPlan->delete();
            }

            DB::commit();

            return Success('Pricing plan deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return Failed($e->getMessage());
        }
    }
}
