<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Http\Controllers\Reservations;
use App\Messages;
use App\Models\Facilitie;
use App\Models\Room;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Discount;
use App\Models\Suite;
use App\Models\DoorUnlockHistory;
use App\Models\Guest_classification;
use App\Models\Guest_feature;
use App\Models\Guest_classification_feature;
use App\Models\Stay_reason;
use App\Models\Reservation;
use App\Models\Feature;
use App\Models\Room_feature;
use App\Models\Penaltie;
use App\Models\ReservationPenalty;
use App\Models\Tax;
use App\Models\ReservationTax;
use App\Models\ReservationSource;
use App\Models\Client;
use App\Models\Department;
use App\Models\Job_title;
use App\Models\User_permission;
use App\Models\PeakDay;
use App\Models\Pricingplan;
use App\Models\RoomtypePricingplan;
use Exception;

class Users extends Controller
{
    public function createNewStudent(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'string|email|unique:users,email',
            'name' => 'required|string',
            'mobile' => 'required|string|min:10|max:10',
            'student_number' => 'required|string',
            'nationality' => 'string',
            'college' => 'string',
            'study_year' => 'string',
            'term' => 'string',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->mobile = $request->mobile;
        $user->student_number = $request->student_number;
        $user->nationality = $request->nationality;
        $user->college = $request->college;
        $user->study_year = $request->study_year;
        $user->term = $request->term;
        $user->password = password_hash($request->student_number, PASSWORD_DEFAULT);

        try {
            $user->save();
            return ['result' => 'success', 'code' => 1, "user" => $user, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e];
        }
    }

    public function loginStudent1(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'student_number' => 'required|exists:users,student_number',
            'password' => 'required'
            // 'password' => 'required|min:4',
        ]);

        if ($validation->fails()) {
            return response(["result" => "failed", 'code' => 0, "error" => $validation->errors()], 200);
        }

        $user = User::where('student_number', $request->student_number)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ["result" => "failed", 'error' => Messages::getMessage("loginFailed"), 'code' => 0];
        }

        $reservation = Reservations::checkStudentHasActiveReservation($user->id);

        if ($reservation == null) {
            return ["result" => "failed", 'error' => Messages::getMessage("noActiveReservation"), 'code' => 0];
        }

        $token = $user->createToken('token')->plainTextToken;

        return ['result' => 'success', 'code' => 1, 'token' => $token, 'reservation' => $reservation, 'user' => $user];
    }

    public function loginStudent(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'student_number' => 'required|numeric',
            'password' => 'required|min:4',
        ]);
        if ($validation->fails()) {
            return response(["result" => "failed", 'code' => 0, "error" => $validation->errors()], 200);
        }
        $user = User::where('student_number', $request->student_number)->orWhere('mobile', $request->student_number)->first();
        if (!$user) {
            // 'error' => '0'=> Student Number Failed
            return ["result" => "failed", 'error' => 0, 'code' => 0];
        }

        if (!Hash::check($request->password, $user->password)) {
            // 'error' => '1'=> Student Password Failed
            return ["result" => "failed", 'error' => 1, 'code' => 0];
        }
        if ($user->is_active == 1) {
            //0=>The account is deleted
            return ['result' => 'success', 'code' => 1, 'user' => "0"];
        }
        $token = $user->createToken('token')->plainTextToken;
        return ['result' => 'success', 'code' => 1, 'token' => $token, 'userID' => $user->id];
    }

    public  function getInfoUser(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'user_id' => 'required|numeric|exists:users,id',
        ]);
        if ($validation->fails()) {
            return response(["result" => "failed", 'code' => 0, "error" => $validation->errors()], 200);
        }
        $user = User::where('id', '=', $request->user_id)->first();
        if ($user->is_active == 1) {
            //0=>The account is deleted
            return ['result' => 'success', 'code' => 1, 'user' => "0"];
        }
        $reservation = Reservations::checkStudentHasActiveReservation($user->id);
        if ($reservation != null) {
            // return $reservation->room_id;
            $myRoom = Room::find($reservation->room_id);
            if ($reservation->facility_ids != '[]') {
                $facilitie = $this->getFacilitieByRoomForApp($reservation->room_id, $reservation->facility_ids);
            }
        }
        return ['result' => 'success', 'code' => 1, 'user' => $user, 'reservation' => $reservation, 'facilitie' => $facilitie ?? [], 'myRoom' => $myRoom ?? []];
    }

    public   function getFacilitieByRoomForApp($roomId, $facilitie_ids)
    {
        $facilitie_ids = json_decode($facilitie_ids, true);
        $room = Room::find($roomId);
        $allFacilities = Facilitie::all();
        $facilities = [];
        if ($room->suite_id != 0) {
            //  المرافق المرتبطة بالسويت
            $suiteFacilities = $allFacilities->where('suite_id', $room->suite_id);
            $facilities = array_merge($facilities, $suiteFacilities->toArray());
        }
        //  المرافق المرتبطة بالطابق
        $floorFacilities = $allFacilities->where('building_id', $room->building_id)->where('floor_id', $room->floor_id)->where('suite_id', 0);
        if (count($floorFacilities) > 0) {
            $facilities = array_merge($facilities, $floorFacilities->toArray());
        }
        //  المرافق المرتبطة بالمبنى
        $buildingFacilities = $allFacilities->where('building_id', $room->building_id)->where('floor_id', 0)->where('suite_id', 0);
        if (count($buildingFacilities) > 0) {
            $facilities = array_merge($facilities, $buildingFacilities->toArray());
        }
        //  المرافق العامة
        $generalFacilities = $allFacilities->where('building_id', 0)->where('floor_id', 0)->where('suite_id', 0);
        if (count($generalFacilities) > 0) {
            $facilities = array_merge($facilities, $generalFacilities->toArray());
        }

        $filteredFacilities = array_filter($facilities, function ($facility) use ($facilitie_ids) {
            return in_array($facility['id'], $facilitie_ids);
        });

        // جلب أسماء المباني والطوابق والسويتات دفعة واحدة
        $buildingIds = array_column($filteredFacilities, 'building_id');
        $floorIds = array_column($filteredFacilities, 'floor_id');
        $suiteIds = array_column($filteredFacilities, 'suite_id');

        $buildings = Building::whereIn('id', $buildingIds)->get()->keyBy('id');
        $floors = Floor::whereIn('id', $floorIds)->get()->keyBy('id');
        $suites = Suite::whereIn('id', $suiteIds)->get()->keyBy('id');

        // تحديث المرافق بالأسماء
        foreach ($filteredFacilities as &$facility) {
            $facility['building_name'] = $buildings[$facility['building_id']]->name ?? '0';
            $facility['floor_number'] = $floors[$facility['floor_id']]->number ?? '0';
            $facility['suite_number'] = $suites[$facility['suite_id']]->number ?? '0';
        }
        return $filteredFacilities;
    }

    public  function checkReservationAndUser(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'user_id' => 'required|numeric',
        ]);
        if ($validation->fails()) {
            return response(["result" => "failed", 'code' => 0, "error" => $validation->errors()], 200);
        }

        $user = User::where("id", '=', $request->user_id)->first();
        if (!$user || $user->is_active == 1) {
            //0 = >User deleted or not found
            return ['result' => 'success', 'code' => 1, 'user' => '0', 'reservation' => ''];
        }
        $reservation = Reservations::checkStudentHasActiveReservation($user->id);
        if (!$reservation) {
            // => Reservation is inACTIVE OR DELETED
            return ['result' => 'success', 'code' => 1, 'user' => '', 'reservation' => '0'];
        }
        return ['result' => 'success', 'code' => 1, 'user' => 'Done', 'reservation' => 'Done'];
    }

    public  function recordOpeningDoor(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'room_id' => 'required|integer|exists:rooms,id',
            'room_number' => 'required|integer',
            'user_id' => 'required|integer|exists:users,id',
            'user_name' => 'required|string',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        DoorUnlockHistory::create($validation->validated());
        return ['result' => 'success', 'code' => 1, 'error' => '',];
    }

    public  function getRecordOpeningDoor(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'room_id' => 'required|integer|exists:rooms,id',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $record = DoorUnlockHistory::where('room_id', '=', $request->room_id)->get();
        return ['result' => 'success', 'code' => 1, 'record' => $record];
    }

    //=====================================Discounts===============================================
    public function addDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_percentage' => 'required|boolean',
            'percent' => 'required_if:is_percentage,1|numeric|min:0',
            'is_fixed' => 'required|boolean',
            'fixed_amount' => 'required_if:is_fixed,1|numeric|min:0',
            'is_active' => 'boolean',  //0 for inactive, 1 for active
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        if ($request->is_percentage && $request->is_fixed && $request->percent > $request->fixed_amount) {
            return ['result' => 'failed', 'error' => 'Percentage cannot exceed fixed amount'];
        }

        Discount::create([
            'name' => $request->name,
            'is_percentage' => $request->is_percentage,
            'percent' => $request->percent ?? 0,
            'is_fixed' => $request->is_fixed,
            'fixed_amount' => $request->fixed_amount ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);
        return ['result' => 'success', 'error' => ''];
    }

    public function updateDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:discounts,id',
            'name' => 'nullable|string|max:255',
            'is_percentage' => 'nullable|boolean',
            'percent' => 'required_if:is_percentage,1|numeric|min:0',
            'is_fixed' => 'nullable|boolean',
            'fixed_amount' => 'required_if:is_fixed,1|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'discount' => '', 'error' => $validator->errors()];
        }
        $discount = Discount::find($request->id);
        $name = $request->name ?? $discount->name;
        $is_percentage = $request->is_percentage ?? $discount->is_percentage;
        $percent = $request->percent ?? $discount->percent;
        $is_fixed = $request->is_fixed ?? $discount->is_fixed;
        $fixed_amount = $request->fixed_amount ?? $discount->fixed_amount;
        $is_active = $request->is_active ?? $discount->is_active;
        if ($is_percentage && $is_fixed && $percent > $fixed_amount) {
            return ['result' => 'failed', 'discount' => '', 'error' => 'Percentage cannot exceed fixed amount'];
        }
        $discount->update([
            'name' => $name,
            'is_percentage' => $is_percentage,
            'percent' => $percent,
            'is_fixed' => $is_fixed,
            'fixed_amount' => $fixed_amount,
            'is_active' => $is_active,
        ]);
        return ['result' => 'success', 'error' => ''];
    }

    public function deleteDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:discounts,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'discount' => '', 'error' => $validator->errors()];
        }
        $discount = Discount::find($request->id);
        if ($discount->users()->exists()) {
            return ['result' => 'failed', 'error' => 'Discount linked to users, can\'t delete.'];
        }
        if ($discount->guest_classification()->exists()) {
            return ['result' => 'failed', 'error' => 'Discount linked to guest classification, can\'t delete.'];
        }

        try {
            $discount->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getDiscounts()
    {
        return Discount::all();
    }
    //=====================================GuestClassification=======================================
    public function addGuestClassification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_ar' => 'required|string|max:255|unique:guest_classifications,name_ar',
            'name_en' => 'nullable|string|max:255|unique:guest_classifications,name_en',
            'description' => 'nullable|string|max:255',
            'discount_id' => 'nullable|numeric|exists:discounts,id',
            'active' => 'nullable|in:0,1'   //0 for inactive, 1 for active
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            $guestClassification = Guest_classification::create([
                'name_ar' => $request->name_ar,
                'name_en' => $request->name_en,
                'description' => $request->description,
                'discount_id' => $request->discount_id,
                'active' => $request->active ?? 1
            ]);
            return ['result' => 'success', 'guest_classification' => $guestClassification];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function updateGuestClassification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:guest_classifications,id',
            'name_ar' => 'nullable|string|unique:guest_classifications,name_ar,' . $request->id,
            'name_en' => 'nullable|string|unique:guest_classifications,name_en,' . $request->id,
            'description' => 'nullable|string',
            'discount_id' => 'nullable|numeric|exists:discounts,id',
            'active' => 'nullable|boolean',   //0 for inactive, 1 for active
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'guest_classification' => '', 'error' => $validator->errors()];
        }

        $guestClassification = Guest_classification::find($request->id);
        $name_ar = $request->name_ar ?? $guestClassification->name_ar;
        $name_en = $request->name_en ?? $guestClassification->name_en;
        $description = $request->description ?? $guestClassification->description;
        $discount_id = $request->discount_id ?? $guestClassification->discount_id;
        $active = $request->active ?? $guestClassification->active;

        $guestClassification->update([
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'description' => $description,
            'discount_id' => $discount_id,
            'active' => $active,
        ]);

        return ['result' => 'success', 'error' => ''];
    }

    public function getGuestClassification()
    {
        return Guest_classification::all();
    }

    public function deleteGuestClassification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:guest_classifications,id',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        $guestClassification = Guest_classification::find($request->id);

        if ($guestClassification->guest_classification_features()->exists()) {
            return ['result' => 'failed', 'error' => 'The category cannot be deleted because it is linked to existing features'];
        }
        $guestClassification->delete();
        return ['result' => 'success', 'error' => ''];
    }
    //=====================================GuestFeature==========================================
    public function addGuestFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'feature_description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'guest_feature' => '', 'error' => $validator->errors()];
        }

        $guestFeature = Guest_feature::create([
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'feature_description' => $request->feature_description,
        ]);

        return ['result' => 'success', 'guest_feature' => $guestFeature, 'error' => ''];
    }

    public function getGuestFeature()
    {
        return Guest_feature::all();
    }

    public function updateGuestFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:guests_features,id',
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'feature_description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'guest_feature' => '', 'error' => $validator->errors()];
        }
        $guestFeature = Guest_feature::find($request->id);
        $name_ar = $request->name_ar ?? $guestFeature->name_ar;
        $name_en = $request->name_en ?? $guestFeature->name_en;
        $feature_description = $request->feature_description ?? $guestFeature->feature_description;
        $guestFeature->update([
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'feature_description' => $feature_description,
        ]);
        return ['result' => 'success', 'error' => ''];
    }

    public function deleteGuestFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:guests_features,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $guestFeature = Guest_feature::find($request->id);

        if ($guestFeature->guest_classification_features()->exists()) {
            return ['result' => 'failed', 'error' => 'Cannot delete this feature because it is linked to one or more guest classifications'];
        }

        $guestFeature->delete();

        return ['result' => 'success', 'error' => ''];
    }

    //=====================================GuestClassiFicationFeature==============================
    public function addGuestClassificationFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guest_classification_id' => 'required|numeric|exists:guest_classifications,id',
            'guest_feature_id' => 'required|numeric|exists:guests_features,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $exists = Guest_classification_feature::where('guest_classification_id', $request->guest_classification_id)->where('guest_feature_id', $request->guest_feature_id)->exists();

        if ($exists) {
            return ['result' => 'failed', 'error' => 'This guest classification is already linked to this feature.'];
        }

        $featureLink = new Guest_classification_feature();
        $featureLink->guest_classification_id = $request->guest_classification_id;
        $featureLink->guest_feature_id = $request->guest_feature_id;
        $featureLink->save();

        return ['result' => 'success', 'error' => ''];
    }

    public function deleteGuestClassificationFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:guest_classification_features,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $record = Guest_classification_feature::find($request->id);

        if (!$record) {
            return ['result' => 'failed', 'error' => 'Record not found'];
        }

        try {
            $record->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getGuestClassificationFeature()
    {
        return Guest_classification_feature::all();
    }
    //=====================================StayReason==============================================
    public function addStayReason(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'reason' => '', 'error' => $validator->errors()];
        }

        $reason = Stay_reason::create([
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'description' => $request->description,
        ]);

        return ['result' => 'success', 'reason' => $reason, 'error' => ''];
    }

    public function updateStayReason(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:Stay_reasons,id',
            'name_ar' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'reason' => '', 'error' => $validator->errors()];
        }

        $reason = Stay_reason::find($request->id);

        $name_ar = $request->name_ar ?? $reason->name_ar;
        $name_en = $request->name_en ?? $reason->name_en;
        $description = $request->description ?? $reason->description;

        $reason->update([
            'name_ar' => $name_ar,
            'name_en' => $name_en,
            'description' => $description,
        ]);

        return ['result' => 'success', 'error' => ''];
    }

    public function deleteStayReason(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:Stay_reasons,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'reason' => '', 'error' => $validator->errors()];
        }

        try {
            $stayReason = Stay_reason::find($request->id);
            $hasReservations = Reservation::where('stay_reason_id', $stayReason->id)->exists();
            if ($hasReservations) {
                return ['result' => 'failed', 'error' => 'Cannot delete. This stay reason is linked to existing reservations.'];
            }
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getStayReasons()
    {
        return Stay_reason::all();
    }
    //=====================================features=============================================
    public function addFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'feature' => '', 'error' => $validator->errors()];
        }

        $feature = Feature::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return ['result' => 'success', 'feature' => $feature, 'error' => ''];
    }

    public function deleteFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:features,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        try {
            $feature =  Feature::find($request->id);
            if ($feature->room_features->exists()) {
                return ['result' => 'failed', 'error' => 'Cannot delete. This Feature is linked to Room Feature.'];
            }
            $feature->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getFeature()
    {
        return Feature::all();
    }
    //=====================================RoomsFeatures=============================================
    public function addRoomFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|numeric|exists:rooms,id',
            'feature_id' => 'required|numeric|exists:features,id',
            'number' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'room_feature' => '', 'error' => $validator->errors()];
        }

        $roomFeature = Room_feature::updateOrCreate(
            ['room_id' => $request->room_id, 'feature_id' => $request->feature_id],
            ['number' => $request->number ?? 1]
        );

        return ['result' => 'success', 'room_feature' => $roomFeature, 'error' => ''];
    }

    public function deleteRoomFeature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:rooms_features,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            $roomFeature = Room_feature::find($request->id);
            $roomFeature->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }
    public function getRoomFeature()
    {
        return Room_feature::all();
    }
    //=====================================Penaltie===================================================
    public function addPenaltie(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:0,1', // 0 => fixed amount, 1 => percentage
            'value' => 'required|numeric|min:0',
            'name_ar' => 'required|string|max:100',
            'name_en' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $penaltie = new Penaltie();
        $penaltie->type = $request->type;
        $penaltie->value = $request->value;
        $penaltie->name_ar = $request->name_ar;
        $penaltie->name_en = $request->name_en;
        $penaltie->save();
        return ['result' => 'success', 'penaltie' => $penaltie];
    }

    public function getPenalties()
    {
        return Penaltie::all();
    }

    public function deletePenaltie(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:penalties,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        $penalty = Penaltie::find($request->id);
        $penalty->delete();
        return ['result' => 'success', 'message' => 'Penalty deleted successfully'];
    }
    //=====================================ReservationPenalties===========================================
    public function addReservationPenalty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|numeric|exists:reservations,id',
            'penalty_id' => 'required|numeric|exists:penalties,id',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        if (ReservationPenalty::where('reservation_id', $request->reservation_id)->where('penalty_id', $request->penalty_id)->exists()) {
            return ['result' => 'failed', 'error' => 'This penalty is already linked to this reservation'];
        }
        try {
            ReservationPenalty::create([
                'reservation_id' => $request->reservation_id,
                'penalty_id' => $request->penalty_id,
                'amount' => $request->amount,
            ]);
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getPenaltiesByReservationId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|numeric|exists:reservations,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'reason' => '', 'error' => $validator->errors()];
        }
        $penalties = ReservationPenalty::where('reservation_id', $request->reservation_id)->with('penalty')->get();
        return ['result' => 'success', 'data' => $penalties, 'error' => ''];
    }
    //=====================================Tax============================================================
    public function addTax(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:0,1',  //0 = percentage, 1 = fixed amount
            'value' => 'required|numeric',
            'name_ar' => 'required|string|max:100',
            'name_en' => 'required|string|max:100',
            'active' => 'required|in:0,1', //1 => active 0=> inactive
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'reason' => '', 'error' => $validator->errors()];
        }

        try {
            Tax::create($request->only(['type', 'value', 'name_ar', 'name_en', 'active']));
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getTax()
    {
        return Tax::all();
    }

    public function deleteTax(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:taxes,id',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        try {
            $linkedReservations = ReservationTax::where('tax_id', $request->id)->exists();
            if ($linkedReservations) {
                return ['result' => 'failed', 'error' => 'This tax cannot be removed because it is linked to reservations'];
            }
            Tax::find($request->id)->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }
    //=====================================ReservationSources===============================================
    public function addReservationSource(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'NameAr' => 'required|string|max:255',
            'NameEn' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'source' => '', 'error' => $validator->errors()];
        }

        $source = ReservationSource::create([
            'NameAr' => $request->NameAr,
            'NameEn' => $request->NameEn,
        ]);

        return ['result' => 'success', 'source' => $source, 'error' => ''];
    }

    public function updateReservationSource(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:reservation_sources,id',
            'NameAr' => 'nullable|string|max:255',
            'NameEn' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'source' => '', 'error' => $validator->errors()];
        }

        $source = ReservationSource::find($request->id);
        $source->update($request->only(['NameAr', 'NameEn']));

        return ['result' => 'success', 'error' => ''];
    }

    public function deleteReservationSource(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:reservation_sources,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'source' => '', 'error' => $validator->errors()];
        }
        $linkedReservations = Reservation::where('reservation_source_id', $request->id)->exists();
        if ($linkedReservations) {
            return ['result' => 'failed', 'error' => 'This Reservation Source cannot be removed because it is linked to reservations'];
        }
        try {
            ReservationSource::find($request->id)->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getReservationSource()
    {
        return ReservationSource::all();
    }
    //=====================================Clients===========================================================
    public function addClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name'              => 'required|string|max:255',
            'last_name'               => 'required|string|max:255',
            'email'                   => 'nullable|email|unique:mysql2.clients,email',
            'international_code'      => 'required|string|unique:mysql2.clients,international_code',
            'mobile'                  => 'required|string|unique:mysql2.clients,mobile',
            'nationality_id'          => 'nullable|numeric',
            'IdType'                  => 'required|in:ID,PASSPORT',
            'IdNumber'                => 'required|string|unique:mysql2.clients,IdNumber',
            'birth_date'              => 'nullable|date',
            'gender'                  => 'required|in:MALE,FEMALE',
            'guest_classification_id' => 'numeric'
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        try {
            Client::create($request->all());
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getClientByMobile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string'
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $client = Client::where('mobile', $request->mobile)->first();

        if ($client) {
            return ['result' => 'success', 'data' => $client];
        } else {
            return ['result' => 'failed', 'message' => 'Client not found'];
        }
    }
    //=====================================Department=========================================================

    public function addDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:departments,name',
            'description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $department = Department::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return ['result' => 'success', 'message' => 'Department created successfully', 'data' => $department];
    }

    public function getDepartment()
    {
        return Department::all();
    }

    public function getDepartmentById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $department = Department::where('id', $request->id)->first();

        if ($department) {
            return ['result' => 'success', 'data' => $department];
        } else {
            return ['result' => 'failed', 'message' => 'Department not found'];
        }
    }

    public function deleteDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $department = Department::find($request->id);

        if ($department->jobtitles()->exists()) {
            return ['result' => 'failed', 'error' => 'Department is linked to job titles and cannot be deleted'];
        }

        try {
            $department->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }
    public function updateDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:departments,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $department = Department::find($request->id);

        $name = $request->name ?? $department->name;
        $description = $request->description ?? $department->description;

        $department->update([
            'name' => $name,
            'description' => $description,
        ]);

        return ['result' => 'success', 'error' => ''];
    }

    //=====================================JobTitle===========================================================
    public function addJobTitle(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'jobtitle' => 'required|string|max:255',
            'department_id' => 'required|numeric|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'jobtitle' => '', 'error' => $validator->errors()];
        }

        $jobTitle = Job_title::create([
            'jobtitle' => $request->jobtitle,
            'department_id' => $request->department_id,
        ]);

        return ['result' => 'success', 'jobtitle' => $jobTitle, 'error' => ''];
    }
    public function getJobTitle(Request $request)
    {
        return Job_title::all();
    }
    public function getJobTitlesByDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|numeric|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'jobtitles' => [], 'error' => $validator->errors()];
        }

        $jobTitles = Job_title::where('department_id', $request->department_id)->get();

        return ['result' => 'success', 'jobtitles' => $jobTitles, 'error' => ''];
    }

    public function deleteJobTitle(Request $request)

    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:jobtitles,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $jobTitle = Job_Title::find($request->id);

        if ($jobTitle->users()->exists()) {
            return ['result' => 'failed', 'error' => 'JobTitle is linked to users, cannot delete.'];
        }

        try {
            $jobTitle->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    //=====================================Users===============================================================
    public function addUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'job_number'    => 'required|string|unique:users,job_number',
            'jobtitle_id'   => 'required|numeric|exists:jobtitles,id',
            'department_id' => 'required|numeric|exists:departments,id',
            'mobile'        => 'required|string|unique:users,mobile',
            'email'         => 'required|email|unique:users,email',
            'discount_id'   => 'nullable|numeric|exists:discounts,id',
            'password'      => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            $user = User::create([
                'name'          => $request->name,
                'job_number'    => $request->job_number,
                'jobtitle_id'   => $request->jobtitle_id,
                'department_id' => $request->department_id,
                'mobile'        => $request->mobile,
                'email'         => $request->email,
                'discount_id'   => $request->discount_id,
                'active'        => 1,
                'password'      => Hash::make($request->password),
            ]);

            return ['result' => 'success', 'data' => $user];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function updateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'            => 'nullable|numeric',
            'jobtitle_id'   => 'nullable|numeric|exists:jobtitles,id',
            'department_id' => 'nullable|numeric|exists:departments,id',
            'mobile'        => 'nullable|string|unique:users,mobile,' . $request->id,
            'email'         => 'nullable|email|unique:users,email,' . $request->id,
            'discount_id'   => 'nullable|numeric|exists:discounts,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        $user = User::find($request->id);

        if (!$user) {
            return ['result' => 'failed', 'error' => 'User not found'];
        }

        try {
            $user->update($request->only([
                'jobtitle_id',
                'department_id',
                'mobile',
                'email',
                'discount_id'
            ]));

            return ['result' => 'success', 'data' => $user];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function deleteUser(Request $request)
    {
        $user = User::find($request->id);

        if (!$user) {
            return ['result' => 'failed', 'error' => 'User not found'];
        }

        try {
            $user->active = 0;
            $user->save();

            return ['result' => 'success', 'message' => 'User deactivated successfully'];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function getUsersByStatus()
    {
        $activeUsers   = User::where('active', 1)->get();
        $inactiveUsers = User::where('active', 0)->get();
        return ['result' => 'success', 'active_users' => $activeUsers, 'inactive_users' => $inactiveUsers];
    }

    //=====================================Permissions===============================================================
    public function addPermissions()
    {
        $permissions = [
            [
                'name_en'        => 'Discount on room rent',
                'name_ar'        => 'الخصم على اجار الغرفة',
                'description_en' => 'Allows applying discounts on room rent invoices',
                'description_ar' => 'يسمح بإضافة خصومات على فواتير إيجار الغرف',
            ],
            [
                'name_en'        => 'Access accounting reports',
                'name_ar'        => 'الوصول لتقارير المحاسبة',
                'description_en' => 'Grants access to view and generate accounting reports',
                'description_ar' => 'يمنح صلاحية عرض وإنشاء تقارير المحاسبة',
                'active' => 1,
            ],
            [
                'name_en'        => 'Access device control',
                'name_ar'        => 'الوصول للتحكم بالأجهزة',
                'description_en' => 'Enables control over connected devices within the system',
                'description_ar' => 'يمكن المستخدم من التحكم بالأجهزة المرتبطة داخل النظام',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access general control settings',
                'name_ar'        => 'الوصول للإعدادات العامة للتحكم',
                'description_en' => 'Allows managing general control settings',
                'description_ar' => 'يسمح بإدارة الإعدادات العامة للتحكم',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access building settings',
                'name_ar'        => 'الوصول لإعدادات المبنى',
                'description_en' => 'Allows configuration and management of building settings',
                'description_ar' => 'يسمح بتهيئة وإدارة إعدادات المبنى',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access general program settings',
                'name_ar'        => 'الوصول للإعدادات العامة للبرنامج',
                'description_en' => 'Allows access to overall program settings',
                'description_ar' => 'يتيح الوصول إلى الإعدادات العامة للبرنامج',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access room service request reports',
                'name_ar'        => 'الوصول لتقارير طلبات خدمة الغرف',
                'description_en' => 'Grants access to reports of room service requests',
                'description_ar' => 'يمنح صلاحية الوصول لتقارير طلبات خدمة الغرف',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access device control reports',
                'name_ar'        => 'الوصول لتقارير التحكم بالأجهزة',
                'description_en' => 'Grants access to reports related to device control actions',
                'description_ar' => 'يمنح صلاحية الوصول لتقارير متعلقة بالتحكم بالأجهزة',
                'active' => 1,

            ],
        ];

        foreach ($permissions as $permission) {
            \App\Models\Permission::firstOrCreate(
                ['name_en' => $permission['name_en']],
                $permission
            );
        }

        return response()->json([
            'result'  => 'success',
            'message' => 'Permissions successfully',
        ]);
    }

    //=====================================UserPermissions===========================================================
    public function addPermissionUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'permission_id' => 'required|array',
            'user_id' => 'required|exists:users,id',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        DB::beginTransaction();
        try {
            User_permission::where('UserId', $request->user_id)->delete();
            $data = [];
            foreach ($request->permission_id as $permID) {
                $data[] = [
                    'UserId' => $request->user_id,
                    'PermissionId' => $permID,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            User_permission::insert($data);
            DB::commit();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'error', 'error' => 'Something went wrong. Please try again.'];
        }
    }
    //=====================================PricingPlan===============================================================

    public function addPricingPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'NameAr' => 'required|string|max:255',
            'NameEn' => 'required|string|max:255',
            'StartDate' => 'required|date',
            'EndDate' => 'required|date|after_or_equal:StartDate',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            Pricingplan::create([
                'NameAr' => $request->NameAr,
                'NameEn' => $request->NameEn,
                'StartDate' => $request->StartDate,
                'EndDate' => $request->EndDate,
            ]);

            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'error', 'error' => 'Something went wrong. Please try again.' . $e];
        }
    }

    public function getAllPricingplans()
    {
        $plans = Pricingplan::all();
        return ['result' => 'success', 'data' => $plans];
    }

    public function getPricingplansByDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'StartDate' => 'required|date',
            'EndDate' => 'required|date|after_or_equal:StartDate',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            $plans = Pricingplan::where('StartDate', '>=', $request->StartDate)->where('EndDate', '<=', $request->EndDate)->get();
            return ['result' => 'success', 'data' => $plans];
        } catch (Exception $e) {
            return ['result' => 'error', 'error' => 'Something went wrong. Please try again.'];
        }
    }

    public function updatePricingplan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:pricingplans,id',
            'NameAr' => 'sometimes|string|max:255',
            'NameEn' => 'sometimes|string|max:255',
            'StartDate' => 'sometimes|date',
            'EndDate' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            $plan = Pricingplan::find($request->id);
            if (!$plan) {
                return ['result' => 'error', 'error' => 'Pricing plan not found'];
            }

            if ($request->has('NameAr')) $plan->NameAr = $request->NameAr;
            if ($request->has('NameEn')) $plan->NameEn = $request->NameEn;
            if ($request->has('StartDate')) $plan->StartDate = $request->StartDate;
            if ($request->has('EndDate')) $plan->EndDate = $request->EndDate;

            $plan->save();

            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'error', 'error' => 'Something went wrong. Please try again.'];
        }
    }

    public function deletePricingplan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:pricingplans,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            Pricingplan::where('id', $request->id)->delete();
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'error', 'error' => 'Something went wrong. Please try again.'];
        }
    }

    public function addRoomtypePricingplan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roomtype_id' => 'required|exists:room_types,id',
            'pricingplan_id' => 'required|exists:pricing_plans,id',
            'DailyPrice' => 'required|numeric|min:0',
            'MonthlyPrice' => 'required|numeric|min:0',
            'YearlyPrice' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            RoomtypePricingplan::create([
                'roomtype_id' => $request->roomtype_id,
                'pricingplan_id' => $request->pricingplan_id,
                'DailyPrice' => $request->DailyPrice,
                'MonthlyPrice' => $request->MonthlyPrice,
                'YearlyPrice' => $request->YearlyPrice,
            ]);
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'error', 'error' => 'Something went wrong. Please try again.' . $e];
        }
    }

    public function getRoomtypePricingByDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roomtype_id' => 'required|exists:room_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $pricing = RoomtypePricingplan::where('roomtype_id', $request->roomtype_id)
            ->whereHas('pricingplan', function ($query) use ($request) {
                $query->where('StartDate', '<=', $request->start_date)
                    ->where('EndDate', '>=', $request->end_date);
            })->first();

        if (!$pricing) {
            return ['result' => 'not_found', 'error' => 'No active pricing plan found for this date.'];
        }
        // return ['result' => 'success', 'data' => $pricing];
        return ['result' => 'success', 'data' => ['DailyPrice' => $pricing->DailyPrice, 'MonthlyPrice' => $pricing->MonthlyPrice, 'YearlyPrice' => $pricing->YearlyPrice, 'plan_nameEn' => $pricing->pricingplan->NameEn, 'plan_nameAr' => $pricing->pricingplan->NameAr]];
    }

    //=====================================PeakDaysCheck=============================================================

    public function updatePeakDaysCheck(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'required|array|min:1',
            'days.*.id' => 'required|exists:peak_days,id',
            'days.*.check' => 'required|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        foreach ($request->days as $day) {
            PeakDay::where('id', $day['id'])->update(['check' => $day['check']]);
        }

        return ['result' => 'success', 'message' => 'Check values updated successfully.'];
    }
}
