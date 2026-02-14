<?php

namespace App\Http\Controllers;

use App\Models\Client_Classifications;
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
use App\Models\PeakMonth;
use App\Models\Pricingplan;
use App\Models\RoomtypePricingplan;
use App\Http\Resources\UserResource;
use App\Models\RoomType;
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

    //=====================================Users===============================================================

    public function login(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'job_number' => 'required|string',
            'password'   => 'required|min:4',
        ]);

        if ($validation->fails()) {
            return response(["result" => "failed", "code" => 0, "error" => $validation->errors()], 200);
        }

        $user = User::where('job_number', $request->job_number)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response(["result" => "failed", "code" => 0, "error" => "Login failed"], 200);
        }

        if ($user->active === 0) {
            return response(["result" => "failed", "code"   => 0, "error"  => "The account has been deleted, cannot log in", "token"  => ""], 200);
        }

        $token = $user->createToken('token')->plainTextToken;

        return ['result' => 'success', 'code' => 1, 'id' => $user->id, 'token' => $token];
    }

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
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'numeric|exists:permissions,id',
        ]);
        if ($validator->fails()) {
            return response(['result' => 'failed', 'error'  => $validator->errors()], 200);
        }

        DB::beginTransaction();
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
                'password'      => Hash::make($request->job_number),
            ]);
            $this->addUserPermission($user->id, $request->permission_ids);
            $user->load(['jobtitle', 'department', 'discount', 'permissions']);
            DB::commit();
            return response(['result' => 'success', 'data'   => new UserResource($user)],  200);
        } catch (Exception $e) {
            DB::rollBack();
            return response(['result' => 'failed', 'error'  => $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'            => 'required|numeric',
            'jobtitle_id'   => 'nullable|numeric|exists:jobtitles,id',
            'department_id' => 'nullable|numeric|exists:departments,id',
            'mobile'        => 'nullable|string|unique:users,mobile,' . $request->id,
            'email'         => 'nullable|email|unique:users,email,' . $request->id,
            'discount_id'   => 'nullable|numeric|exists:discounts,id',
            'name'          => 'nullable|string',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'numeric|exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        $user = User::find($request->id);

        if (!$user) {
            return ['result' => 'failed', 'error' => 'User not found'];
        }
        if ($request->discount_id == 0) {
            $user->discount_id = null;
        }

        try {
            $user->update($request->only([
                'jobtitle_id',
                'department_id',
                'mobile',
                'email',
                'discount_id',
                'name'
            ]));
            if ($request->has('permission_ids')) {
                $oldPermissions = $user->permissions()->pluck('permission_id')->toArray();
                $newPermissions = $request->permission_ids;
                sort($oldPermissions);
                sort($newPermissions);
                if ($oldPermissions !== $newPermissions) {
                    $this->addUserPermission($request->id, $newPermissions);
                }
            }
            return ['result' => 'success', 'data' => new UserResource($user)];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function inActiveUser(Request $request)
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

    public function getInfoUsers()
    {
        $activeUsers = User::with(['jobtitle', 'department', 'discount', 'permissions'])->where('active', 1)->get();
        $inactiveUsers = User::with(['jobtitle', 'department', 'discount', 'permissions'])->where('active', 0)->get();
        $jobTitle = Job_title::all();
        $department = Department::all();
        return ['result' => 'success', 'active_users' => UserResource::collection($activeUsers), 'inactive_users' => UserResource::collection($inactiveUsers), 'department' => $department, 'jobTitle' => $jobTitle];
    }

    //=====================================Discounts===============================================

    public function addDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_percentage' => 'required|numeric',
            'percent' => 'required_if:is_percentage,1|numeric|min:0',
            'is_fixed' => 'required|numeric',
            'fixed_amount' => 'required_if:is_fixed,1|numeric|min:0',
            'is_active' => 'boolean',  //0 for inactive, 1 for active
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        if ($request->is_percentage && $request->is_fixed && $request->percent > $request->fixed_amount) {
            return ['result' => 'failed', 'error' => 'Percentage cannot exceed fixed amount'];
        }

        $discount = Discount::create([
            'name' => $request->name,
            'is_percentage' => $request->is_percentage,
            'percent' => $request->percent ?? 0,
            'is_fixed' => $request->is_fixed,
            'fixed_amount' => $request->fixed_amount ?? 0,
            'is_active' => $request->is_active ?? 1,
        ]);
        return ['result' => 'success', 'error' => '', 'data' => $discount];
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
        return ['result' => 'success', 'error' => '', 'data' => $discount];
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

    //=====================================GuestCategories==========================================
    public function addGuestClassification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_ar' => 'required|string|max:255|unique:guest_classifications,name_ar',
            'name_en' => 'nullable|string|max:255|unique:guest_classifications,name_en',
            'description' => 'nullable|string|max:255',
            'discount_id' => 'nullable|numeric|exists:discounts,id',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'numeric|exists:guests_features,id',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        DB::beginTransaction();
        try {
            $guestClassification = Guest_classification::create([
                'name_ar' => $request->name_ar,
                'name_en' => $request->name_en,
                'description' => $request->description,
                'discount_id' => $request->discount_id,
                'active' =>  1
            ]);
            if ($request->has('feature_ids')) {
                // $this->addGuestClassificationFeature($guestClassification->id, $request->feature_ids);
                $guestClassification->features()->sync($request->feature_ids);
            }
            $guestClassification->load(['features', 'discount']);
            DB::commit();
            return ['result' => 'success', 'guest_classification' => $guestClassification];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function addGuestClassificationFeature($id, $feature_ids)
    {
        $data = [];
        foreach ($feature_ids as $fratID) {
            $data[] = [
                'guest_classification_id' => $id,
                'guest_feature_id' => $fratID,
            ];
        }
        Guest_classification_feature::insert($data);
    }

    public function updateGuestClassification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:guest_classifications,id',
            'name_ar' => 'nullable|string|unique:guest_classifications,name_ar,' . $request->id,
            'name_en' => 'nullable|string|unique:guest_classifications,name_en,' . $request->id,
            'description' => 'nullable|string',
            'discount_id' => 'nullable|numeric',
            'active' => 'nullable|boolean',   //0 for inactive, 1 for active
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => 'numeric|exists:guests_features,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'guest_classification' => '', 'error' => $validator->errors()];
        }

        try {
            $guestClassification = Guest_classification::find($request->id);

            $guestClassification->update([
                'name_ar' => $request->name_ar,
                'name_en' => $request->name_en,
                'description' => $request->description,
                'discount_id' => $request->discount_id == 0 ? null : $request->discount_id,
                'active' => $request->active,
            ]);
            $guestClassification->features()->sync($request->feature_ids);
            $guestClassification->load(['features', 'discount']);

            return ['result' => 'success', 'guest_classification' => $guestClassification];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
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
        $guestClassification->features()->sync([]);
        $guestClassification->delete();
        return ['result' => 'success', 'error' => ''];
    }

    //=====================================GuestFeature==============================================
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

        return ['result' => 'success', 'guestFeature' => $guestFeature, 'error' => ''];
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
        return ['result' => 'success', 'error' => '', 'guestFeature' => $guestFeature];
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

        return ['result' => 'success', 'Reason' => $reason, 'error' => ''];
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
            return ['result' => 'failed', 'Reason' => '', 'error' => $validator->errors()];
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

        return ['result' => 'success', 'Reason' => $reason, 'error' => ''];
    }

    public function deleteStayReason(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:stay_reasons,id',
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
            $stayReason->delete();
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
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'source' => '', 'error' => $validator->errors()];
        }

        $source = ReservationSource::create([
            'name_ar' => $request->name_ar,
            'name_en' => $request->name_en,
            'description' => $request->description,
        ]);
        return ['result' => 'success', 'source' => $source, 'error' => ''];
    }

    public function updateReservationSource(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:reservation_sources,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'source' => '', 'error' => $validator->errors()];
        }
        $source = ReservationSource::find($request->id);
        $source->update($request->only(['name_ar', 'name_en', 'description']));
        return ['result' => 'success', 'error' => '', 'source' => $source];
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
            'international_code'      => 'required|string',
            'mobile'                  => 'required|string|unique:mysql2.clients,mobile',
            'IdType'                  => 'required|in:ID,PASSPORT',
            'IdNumber'                => 'required|string|unique:mysql2.clients,IdNumber',
            'nationality'             => 'required|string',
            'birth_date'              => 'nullable|date',
            'gender'                  => 'required|in:MALE,FEMALE',
            'guest_type'              => 'required|in:CITIZEN,RESIDENT,GULF CITIZEN,VISITOR',
            'classifications_id'      => 'nullable|numeric|exists:guest_classifications,id'
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        try {
            $cli = Client::create($request->all());
            if ($request->has('classifications_id')) {
                $this->addClientClassification($cli->id, $request->classifications_id);
            }
            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function addClientClassification($client_id, $classifications_id)
    {
        $client = Client::find($client_id);
        if (!$client) {
            return false;
        }
        $classification = Guest_classification::find($classifications_id);
        if (!$classification) {
            return false;
        }
        try {
            $cc =  client_classifications::where('client_id', $client_id)->first();
            if ($cc) {
                $cc->delete();
            }
            $c = new Client_Classifications();
            $c->client_id = $client_id;
            $c->classifications_id = $classifications_id;
            $c->save();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getClientBy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'by' => "required|string",
            'value' => 'required|string',
            'code' => 'required_if:by,mobile|string'
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        if ($request->by === 'mobile') {
            // $client = DB::connection('checkinbookingclient')->table('Client')->where('international_code', $request->code)->where($request->by, $request->value)->first();
            $client = Client::where('international_code', $request->code)->where('mobile', $request->value)->first();
        } else {
            // $client = DB::connection('checkinbookingclient')->table('Client')->where($request->by, $request->value)->first();
            $client = Client::where('IdNumber', $request->value)->first();
        }
        if ($client) {
            $classification = Client_Classifications::where('client_id', $client->id)->pluck('classifications_id')->first();
            return ['.' => 'success', 'data' => $client, 'Classification' => $classification];
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

    public function addUserPermission($user_id, $permission_ids)
    {
        User_permission::where('user_id', $user_id)->delete();
        $data = [];
        foreach ($permission_ids as $permID) {
            $data[] = [
                'user_id'      => $user_id,
                'permission_id' => $permID,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        User_permission::insert($data);
    }

    //=====================================RoomType==================================================================

    public function addRoomType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name_ar'     => 'required|string|max:255',
            'name_en'     => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'active_type' => 'required|numeric|in:0,1,2',
        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        if ($request->active_type == 0) {
            $validator = Validator::make($request->all(), [
                'Min_daily_price'   => 'required|numeric|min:1',
                'Min_monthly_price' => 'required|numeric|min:1',
                'Min_yearly_price'  => 'required|numeric|min:1',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'Min_daily_price'   => 'required|numeric|min:1',
                'Max_daily_price'   => 'required|numeric|min:1',
                'Min_monthly_price' => 'required|numeric|min:1',
                'Max_monthly_price' => 'required|numeric|min:1',
                'Min_yearly_price'  => 'required|numeric|min:1',
                'Max_yearly_price'  => 'required|numeric|min:1',
            ]);
        }

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
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
        return ['result' => 'success', 'error' => '', 'data' => $roomType];
    }

    public function getRoomType()
    {
        return RoomType::all();
    }

    public function updateRoomType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'          => 'required|numeric|exists:room_types,id',
            'name_ar'     => 'required|string|max:255',
            'name_en'     => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'active_type' => 'required|numeric|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
        if ($request->active_type == 0) {
            $validator = Validator::make($request->all(), [
                'Min_daily_price'   => 'required|numeric|min:1',
                'Min_monthly_price' => 'required|numeric|min:1',
                'Min_yearly_price'  => 'required|numeric|min:1',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'Min_daily_price'   => 'required|numeric|min:1',
                'Max_daily_price'   => 'required|numeric|min:1',
                'Min_monthly_price' => 'required|numeric|min:1',
                'Max_monthly_price' => 'required|numeric|min:1',
                'Min_yearly_price'  => 'required|numeric|min:1',
                'Max_yearly_price'  => 'required|numeric|min:1',
            ]);
        }

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

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

            return ['result' => 'success', 'error' => '', 'data' => $roomType];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function deleteRoomType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:room_types,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        try {
            $roomType = RoomType::find($request->id);

            if ($roomType->rooms()->exists()) {
                return ['result' => 'failed', 'error'  => 'لا يمكن حذف نوع الغرفة لأنه مرتبط بغرف موجودة'];
            }

            $roomType->delete();

            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'error' => $e->getMessage()];
        }
    }

    //=====================================PricingPlan===============================================================

    public function getRoomtypePricing()
    {
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
        return response()->json($formatted);
    }

    public function addRoomtypePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'NameAr' => 'required|string|max:255',
            'NameEn' => 'required|string|max:255',
            'StartDate' => 'required|date',
            'EndDate' => 'required|date|after_or_equal:StartDate',
            'roomtype_id' => 'required|exists:room_types,id',
            'DailyPrice' => 'required|numeric|min:0',
            'MonthlyPrice' => 'required|numeric|min:0',
            'YearlyPrice' => 'required|numeric|min:0',
            'ActiveType' => 'required|numeric|in:0 , 1 , 2',  //0->Const Price , 1=>For As Per Day , 2=>Plan Price
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

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
            return ['result' => 'success', 'data' => $data, 'error' => ''];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'error', 'error' => 'Something went wrong. ' . $e->getMessage()];
        }
    }

    public function updateRoomtypePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'             => 'required|exists:roomtype_pricingplan,id',
            'pricingplan_id' => 'required|exists:pricing_plans,id',
            'roomtype_id'    => 'required|exists:room_types,id',
            'NameAr'         => 'required|string|max:255',
            'NameEn'         => 'required|string|max:255',
            'StartDate'      => 'required|date',
            'EndDate'        => 'required|date|after_or_equal:StartDate',
            'DailyPrice'     => 'required|numeric|min:0',
            'MonthlyPrice'   => 'required|numeric|min:0',
            'YearlyPrice'    => 'required|numeric|min:0',
            'ActiveType' => 'required|numeric|in:0 , 1 , 2',  //0->Const Price , 1=>For As Per Day , 2=>Plan Price

        ]);
        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }
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
            return ['result' => 'success', 'data' => $data, 'error' => ''];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'error', 'error' => 'Something went wrong. ' . $e->getMessage()];
        }
    }

    public function deleteRoomtypePricing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:roomtype_pricingplan,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

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

            return ['result' => 'success', 'error' => ''];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'error', 'error' => 'Something went wrong. ' . $e->getMessage()];
        }
    }

    //=====================================PeakDaysCheck=============================================================
    public function updatePeakDaysCheck(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'required|array|min:1',
            'days.*.id' => 'required|exists:peak_days,id',
            'days.*.check' => 'required|in:0,1',  //0=Normal, 1=Peak day
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        foreach ($request->days as $day) {
            PeakDay::where('id', $day['id'])->update(['check' => $day['check']]);
        }

        return ['result' => 'success', 'message' => 'Check values updated successfully.'];
    }

    public function seedWeekDays()
    {
        $days = [
            ['day_name_en' => 'Saturday',   'day_name_ar' => 'السبت'],
            ['day_name_en' => 'Sunday',     'day_name_ar' => 'الأحد'],
            ['day_name_en' => 'Monday',     'day_name_ar' => 'الاثنين'],
            ['day_name_en' => 'Tuesday',    'day_name_ar' => 'الثلاثاء'],
            ['day_name_en' => 'Wednesday',  'day_name_ar' => 'الأربعاء'],
            ['day_name_en' => 'Thursday',   'day_name_ar' => 'الخميس'],
            ['day_name_en' => 'Friday',     'day_name_ar' => 'الجمعة'],
        ];

        foreach ($days as $day) {
            PeakDay::updateOrCreate(
                ['day_name_en' => $day['day_name_en']],
                ['day_name_ar' => $day['day_name_ar'], 'check' => 0]
            );
        }

        return response()->json(['message' => 'Weekdays seeded successfully'], 200);
    }

    //=====================================PeakMonthsCheck=============================================================

    public function updatePeakMonthsCheck(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'months' => 'required|array|min:1',
            'months.*.id' => 'required|exists:peak_months,id',
            'months.*.check' => 'required|in:0,1', // 0=Normal, 1=Peak month
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        foreach ($request->months as $month) {
            PeakMonth::where('id', $month['id'])->update(['check' => $month['check']]);
        }

        return ['result' => 'success', 'message' => 'Check values updated successfully.'];
    }

    public function seedMonths()
    {
        $months = [
            ['month_name_en' => 'January',   'month_name_ar' => 'يناير'],
            ['month_name_en' => 'February',  'month_name_ar' => 'فبراير'],
            ['month_name_en' => 'March',     'month_name_ar' => 'مارس'],
            ['month_name_en' => 'April',     'month_name_ar' => 'أبريل'],
            ['month_name_en' => 'May',       'month_name_ar' => 'مايو'],
            ['month_name_en' => 'June',      'month_name_ar' => 'يونيو'],
            ['month_name_en' => 'July',      'month_name_ar' => 'يوليو'],
            ['month_name_en' => 'August',    'month_name_ar' => 'أغسطس'],
            ['month_name_en' => 'September', 'month_name_ar' => 'سبتمبر'],
            ['month_name_en' => 'October',   'month_name_ar' => 'أكتوبر'],
            ['month_name_en' => 'November',  'month_name_ar' => 'نوفمبر'],
            ['month_name_en' => 'December',  'month_name_ar' => 'ديسمبر'],
        ];

        foreach ($months as $month) {
            PeakMonth::updateOrCreate(
                ['month_name_en' => $month['month_name_en']],
                ['month_name_ar' => $month['month_name_ar'], 'check' => 0]
            );
        }

        return response()->json(['message' => 'Months seeded successfully'], 200);
    }
}
