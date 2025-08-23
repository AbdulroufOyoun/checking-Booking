<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Facilitie;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\UpdateReservation;
use App\SMS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;
use App\Models\User;
use App\Models\Reservation;
use App\Messages;
use Exception;
use Illuminate\Support\Facades\DB;


class Admins extends Controller
{
    function createNewAdmin(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'required|string',
            'job_number' => 'required|string',
            'jobtitle_id' => 'required|numeric|exists:jobtitles,id',
            'department_id' => 'required|numeric|exists:departments,id',
            'mobile' => 'required|string|min:10|max:10|unique:users,mobile',
            'email' => 'required|email|unique:users,email',
            'discount_id' => 'required|numeric|exists:discounts,id',
            'active' => 'required|numeric',   //0 => InActive 1 => Active
            'password' => 'required|confirmed|string',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 422);
        }
        $user = new User();
        $user->name = $request->name;
        $user->job_number = $request->job_number;
        $user->jobtitle_id = $request->jobtitle_id;
        $user->department_id = $request->department_id;
        $user->mobile = $request->mobile;
        $user->email = $request->email;
        $user->discount_id = $request->discount_id;
        $user->active = $request->active;
        $user->remember_token =  $user->createToken('remember_token')->plainTextToken;
        $user->password = Hash::make($request->password);
        try {
            $user->save();
            return ['result' => 'success', 'code' => 1, "user" => $user, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }

    function updateAdmin(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $request->id,
            'name' => 'required|string',
            'mobile' => 'required|string|min:10|max:10',
            'is_admin' => 'required|numeric',
        ]);

        if ($validation->fails()) {
            return ['result' => 'failed', 'code' => 0, 'error' => $validation->errors()];
        }
        $admin = Admin::find($request->id);

        if (!$admin) {
            return ['result' => 'failed', 'code' => -1, 'error' => 'Admin not found'];
        }
        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->mobile = $request->mobile;
        $admin->is_admin = $request->is_admin;
        try {
            $admin->save();
            return ['result' => 'success', 'code' => 1, "user" => $admin, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'error' => $e->getMessage()];
        }
    }

    function inActiveAdmin(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric',
        ]);

        if ($validation->fails()) {
            return ['result' => 'failed', 'code' => 0, 'error' => $validation->errors()];
        }
        $admin = Admin::find($request->id);

        if ($admin) {
            $admin->is_active = 1;
            $admin->save();
            return ['result' => 'success', 'code' => 1, "user" => $admin, "error" => ""];
        } else {
            return ['result' => 'failed', 'code' => 0, "user" => "", "error" => "Not Found"];
        }
    }

    function getAllAdmin()
    {
        $admin = Admin::where('is_active', '=', 0)->get();
        if (count($admin) > 0) {
            return ['result' => 'success', 'code' => 1, "admin" => $admin, "error" => ""];
        } else {
            return ['result' => 'success', 'code' => 0, "user" => $admin, "error" => ""];
        }
    }

    function loginAdmin(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|exists:admins,email',
            'password' => 'required|min:4',
        ]);
        if ($validation->fails()) {
            return response(["result" => "failed", 'code' => 0, "error" => $validation->errors()], 200);
        }
        $admin = Admin::where('email', $request->email)->first();
        if ($admin->is_active === 1) {
            return ['result' => 'failed', 'code' => 0, 'error' => "The account has been deleted cannot be logged in", 'token' => ""];
        }
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return ["result" => "failed", 'error' => Messages::getMessage("loginFailed"), 'code' => 0];
        }
        $token = $admin->createToken('token')->plainTextToken;
        return ['result' => 'success', 'code' => 1, 'name' => $admin->name, 'id' => $admin->id, 'token' => $token];
    }

    function createNewStudent(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'nullable|email|unique:users,email',
            'name' => 'required|string',
            'mobile' => 'required|string|min:10|max:10',
            'student_number' => 'required|string',
            'nationality' => 'string|nullable',
            'college_id' => 'numeric|nullable',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }

        $user = new User();
        $user->name = $request->name;
        if ($request->email != null) {
            $user->email = $request->email;
        }
        $user->mobile = $request->mobile;
        if ($request->student_number != null) {
            $user->student_number = $request->student_number;
        }
        if ($request->nationality != null) {
            $user->nationality = $request->nationality;
        }
        if ($request->college_id != null) {
            $user->college_id = $request->college_id;
        }
        $user->password = password_hash($request->student_number, PASSWORD_DEFAULT);
        $user->is_active = 0;
        try {
            $user->save();
            return ['result' => 'success', 'code' => 1, "user" => $user, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e];
        }
    }

    function getAllStudents()
    {
        return User::all();
    }

    function makeReservation(Request $request)
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

    function checkReservation(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'building_id' => 'required|numeric|exists:buildings,id',
            'floor_id' => 'nullable|numeric',
            'capacity' => 'nullable|numeric',
            'room_type' => 'nullable|numeric',
            'start_date' => 'required|date',
            'expire_date' => 'required|date',
            'type_search' => 'required|numeric|in:1,2,3,4',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $availableRooms = array();
        $roomsQuery = Room::where('building_id', '=', $request->building_id);
        if ($request->filled('floor_id')) {
            $roomsQuery->where('floor_id', '=', $request->floor_id);
        }
        if ($request->filled('capacity')) {
            $roomsQuery->where('capacity', '=', $request->capacity);
        }
        if ($request->filled('room_type')) {
            $roomsQuery->where('room_types_id', '=', $request->room_type);
        }
        $rooms = $roomsQuery->get();
        if (count($rooms) > 0) {
            foreach ($rooms as $room) {
                $checkReservationForRoom = Reservation::where('room_id', '=', $room->id)
                    ->where('expire_date', '>=', now())
                    ->where('is_available', '=', 0)
                    ->where(function ($query) use ($request) {
                        $query->whereBetween('start_date', [$request->start_date, $request->expire_date])
                            ->orWhereBetween('expire_date', [$request->start_date, $request->expire_date])
                            ->orWhere(function ($query) use ($request) {
                                $query->where('start_date', '<=', $request->start_date)
                                    ->where('expire_date', '>=', $request->expire_date);
                            },);
                    },)->get();

                switch ($request->type_search) {
                    case 1: // غرفة فارغة: أول غرفة فارغة ترجع فوراً
                        if ($checkReservationForRoom->isEmpty()) {
                            $roomInfo = $room->toArray();
                            $roomInfo['reservations'] = [];
                            $roomInfo['availableCapacity'] = $room->capacity;
                            return ['result' => 'success', 'code' => 1, "AvailableRooms" => [$roomInfo], "error" => ""];
                        }
                        break;

                    case 2: // غرفة متاحة: أول غرفة متاحة ترجع فوراً
                        if (count($checkReservationForRoom) < $room->capacity) {
                            $roomInfo = $room->toArray();
                            $roomInfo['reservations'] = $checkReservationForRoom;
                            $roomInfo['availableCapacity'] = $room->capacity - count($checkReservationForRoom);
                            return ['result' => 'success', 'code' => 1, "AvailableRooms" => [$roomInfo], "error" => ""];
                        }
                        break;

                    case 3: // جميع الغرف الفارغة
                        if ($checkReservationForRoom->isEmpty()) {
                            $roomInfo = $room->toArray();
                            $roomInfo['reservations'] = [];
                            $roomInfo['availableCapacity'] = $room->capacity;
                            array_push($availableRooms, $roomInfo);
                        }
                        break;

                    case 4: // جميع الغرف المتاحة
                        if ($checkReservationForRoom->count() < $room->capacity) {
                            $roomInfo = $room->toArray();
                            $roomInfo['reservations'] = $checkReservationForRoom;
                            $roomInfo['availableCapacity'] = $room->capacity - count($checkReservationForRoom);
                            array_push($availableRooms, $roomInfo);
                        }
                        break;
                }
            }
            if (count($availableRooms) > 0) {
                return ['result' => 'success', 'code' => 1, "AvailableRooms" => $availableRooms, "error" => ""];
            }
            return ['result' => 'failed', 'code' => 0, "AvailableRooms" => [], "error" => "No available rooms found"];
        } else {
            return ['result' => 'failed', 'code' => 0, "AvailableRooms" => [], "error" => "Rooms Not Found"];
        }
    }

    function updateReservation(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'reservation_id' => 'required|numeric|exists:reservations,id',
            'new_room_id' => 'nullable|numeric|exists:rooms,id',
            'new_room_number' => 'nullable|numeric',
            'new_expire_date' => 'nullable|date',
            'new_start_date' => 'nullable|date',
            'new_facility_ids' => 'nullable',
            'is_update_room' => 'nullable|numeric',
            'is_update_date' => 'nullable|numeric',
            'is_update_facility' => 'nullable|numeric',
            'update_by' => 'required|numeric|exists:admins,id',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $reservation = Reservation::find($request->reservation_id);
        DB::beginTransaction();
        try {
            if ($request->is_update_room && $request->is_update_date) {
                //تغيير غرفة وتغيير تاريخ
                $isAvailable = $this->checkReservationByRoom(
                    $request->new_room_id,
                    $request->new_start_date,
                    $request->new_expire_date,
                    null
                );

                if (!$isAvailable) {
                    return ['result' => 'failed', 'code' => 0, 'error' => 'The new room is not available for the new dates'];
                }
            }
            if ($request->is_update_room && !$request->is_update_date) {
                // تغيير غرفة بدون تغيير ع التاريخ
                $isAvailable = $this->checkReservationByRoom(
                    $request->new_room_id,
                    $reservation->start_date,
                    $reservation->expire_date,
                    null
                );

                if (!$isAvailable) {
                    return ['result' => 'failed', 'code' => 0, 'error' => 'The new room is not available for reservation'];
                }
            }
            if ($request->is_update_date && !$request->is_update_room) {
                // تغيير تاريخ بدون تغيير غرفة
                $isAvailable = $this->checkReservationByRoom(
                    $reservation->room_id,
                    $request->new_start_date,
                    $request->new_expire_date,
                    $reservation->id
                );
                // return $isAvailable;
                if (!$isAvailable) {
                    return ['result' => 'failed', 'code' => 0, 'error' => 'The room is not available for the new dates'];
                }
            }
            $updateReservation = new UpdateReservation();
            $updateReservation->reservation_id = $request->reservation_id;
            $updateReservation->old_room_id = $reservation->room_id;
            $updateReservation->new_room_id = $request->new_room_id ?? $reservation->room_id;
            $updateReservation->old_room_number = $reservation->room_number;
            $updateReservation->new_room_number = $request->new_room_number ?? $reservation->room_number;
            $updateReservation->old_start_date = $reservation->start_date;
            $updateReservation->new_start_date = $request->new_start_date ?? $reservation->start_date;
            $updateReservation->old_expire_date = $reservation->expire_date;
            $updateReservation->new_expire_date = $request->new_expire_date ?? $reservation->expire_date;
            $updateReservation->old_facility_ids = $reservation->facility_ids;
            $updateReservation->new_facility_ids = $request->new_facility_ids ?? $reservation->facility_ids;
            $updateReservation->is_update_room = $request->is_update_room ?? 0;
            $updateReservation->is_update_date = $request->is_update_date ?? 0;
            $updateReservation->is_update_facility = $request->is_update_facility ?? 0;
            $updateReservation->update_by = $request->update_by;
            if ($updateReservation->is_update_room == 1) {
                $reservation->room_id = $request->new_room_id;
                $reservation->room_number = $request->new_room_number;
            }
            if ($updateReservation->is_update_facility == 1) {
                $newFacilityIds = json_decode($request->new_facility_ids, true);
                if (is_array($newFacilityIds)) {
                    $newFacilityIds = array_map('intval', $newFacilityIds);
                    $reservation->facility_ids = json_encode($newFacilityIds);
                } else {
                    throw new Exception("Invalid facility data format");
                }
            }
            if ($updateReservation->is_update_date == 1) {
                $reservation->start_date = $request->new_start_date;
                $reservation->expire_date = $request->new_expire_date;
            }
            $updateReservation->save();
            $reservation->update();
            DB::commit();
            return ['result' => 'success', 'code' => 1, 'reservation' => $reservation, 'error' => ''];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }

    function checkReservationByRoom($roomId, $startDate, $expireDate, $reservationId = null): bool
    {
        $check = false;
        $room = Room::find($roomId);
        $query = Reservation::where('room_id', $room->id)
            ->where('expire_date', '>=', now())
            ->where('is_available', '=', 0)
            ->where(function ($query) use ($startDate, $expireDate) {
                $query->whereBetween('start_date', [$startDate, $expireDate])
                    ->orWhereBetween('expire_date', [$startDate, $expireDate])
                    ->orWhere(function ($query) use ($startDate, $expireDate) {
                        $query->where('start_date', '<=', $startDate)
                            ->where('expire_date', '>=', $expireDate);
                    });
            });
        if ($reservationId != null) {
            $query->where('id', '!=', $reservationId);
        }
        $checkReservationForRoom = $query->get();
        // return $checkReservationForRoom;
        if (count($checkReservationForRoom) > 0) {
            if (count($checkReservationForRoom) < $room->capacity) {
                $check = true;
            }
        } else {
            $check = true;
        }
        return $check;
    }

    function checkReservationForUser($userId, $startDate, $expireDate): bool
    {
        // التحقق إذا كان هناك حجز لنفس المستخدم يتداخل مع التواريخ المحددة
        $exists = Reservation::where('student_id', $userId)
            ->where('is_available', 0) // الحجز فعال
            ->where(function ($query) use ($startDate, $expireDate) {
                $query->where(function ($q) use ($startDate, $expireDate) {
                    // الشرط: بداية الحجز الجديد تقع داخل فترة حجز موجودة
                    $q->where('start_date', '<=', $startDate)
                        ->where('expire_date', '>=', $startDate);
                })->orWhere(function ($q) use ($startDate, $expireDate) {
                    // الشرط: نهاية الحجز الجديد تقع داخل فترة حجز موجودة
                    $q->where('start_date', '<=', $expireDate)
                        ->where('expire_date', '>=', $expireDate);
                })->orWhere(function ($q) use ($startDate, $expireDate) {
                    // الشرط: فترة الحجز الجديد تغطي فترة حجز موجودة بالكامل
                    $q->where('start_date', '>=', $startDate)
                        ->where('expire_date', '<=', $expireDate);
                });
            })
            ->exists();
        return !$exists; // إذا لم يكن هناك حجز يتداخل مع المدة، يرجع true
    }

    function getFacilitieByRoom(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'building_id' => 'required|numeric|exists:buildings,id',
            'floor_id' => 'nullable|numeric',
            'suite_id' => 'nullable|numeric',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }

        $allFacilities = Facilitie::all();

        $facilities = [];

        if ($request->suite_id != 0) {
            //  المرافق المرتبطة بالسويت
            $suiteFacilities = $allFacilities->where('suite_id', $request->suite_id);
            $facilities = array_merge($facilities, $suiteFacilities->toArray());
        }
        //  المرافق المرتبطة بالطابق
        $floorFacilities = $allFacilities->where('building_id', $request->building_id)->where('floor_id', $request->floor_id)->where('suite_id', 0);
        if (count($floorFacilities) > 0) {
            $facilities = array_merge($facilities, $floorFacilities->toArray());
        }

        //  المرافق المرتبطة بالمبنى
        $buildingFacilities = $allFacilities->where('building_id', $request->building_id)->where('floor_id', 0)->where('suite_id', 0);
        if (count($buildingFacilities) > 0) {
            $facilities = array_merge($facilities, $buildingFacilities->toArray());
        }
        //  المرافق العامة
        $generalFacilities = $allFacilities->where('building_id', 0)->where('floor_id', 0)->where('suite_id', 0);
        if (count($generalFacilities) > 0) {
            $facilities = array_merge($facilities, $generalFacilities->toArray());
        }
        return ['result' => 'success', 'code' => 1, "facilities" => $facilities, "error" => ""];

        // if (count($facilities) > 0) {
        //     return ['result' => 'success', 'code' => 1, "facilities" => $facilities, "error" => ""];
        // } else {
        //     return ['result' => 'failed', 'code' => 0, "facilities" => [], "error" => "Something went wrong .. try again"];
        // }
    }

    function searchStudentByName(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'student_name' => 'required|string',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        try {
            $students = User::where('name', 'LIKE', '%' . $request->student_name . '%')->get();
            return ['result' => 'success', 'code' => 1, "students" => $students, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e];
        }
    }

    function serachStudentBy(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'value' => 'required|string',
            'by' => 'required|string',
            'college_id' => 'nullable|numeric',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $user = User::where("is_active", '=', 0)->where($request->by, 'LIKE', '%' . $request->value . '%');
        if ($request->has('college_id')) {
            $user->where("college_id", '=', $request->college_id);
        }
        $users = $user->get();
        if (count($users) > 0) {
            return ['result' => 'success', 'code' => 1, "user" => $users, "error" => ""];
        } else {
            return ['result' => 'failed', 'code' => 0, "user" => [], "error" => "Not Found Student"];
        }
    }

    function updateInfoStudent(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:users,id',
            'email' => 'nullable|email|unique:users,email,' . $request->id,
            'name' => 'nullable|string',
            'mobile' => 'nullable|string|min:10|max:10|unique:users,mobile,' . $request->id,
            'student_number' => 'nullable|string|unique:users,student_number,' . $request->id,
            'nationality' => 'nullable|string',
            'college' => 'nullable|string',
            'study_year' => 'nullable|string',
            'term' => 'nullable|string',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }

        $student = User::find($request->id);

        if ($request->has('email') && $request->email !== $student->email) {
            $student->email = $request->email;
        }
        if ($request->has('name') && $request->name !== $student->name) {
            $student->name = $request->name;
        }
        if ($request->has('mobile') && $request->mobile !== $student->mobile) {
            $student->mobile = $request->mobile;
        }
        if ($request->has('student_number') && $request->student_number !== $student->student_number) {
            $student->student_number = $request->student_number;
        }
        if ($request->has('nationality') && $request->nationality !== $student->nationality) {
            $student->nationality = $request->nationality;
        }
        if ($request->has('college') && $request->college !== $student->college) {
            $student->college = $request->college;
        }
        if ($request->has('study_year') && $request->study_year !== $student->study_year) {
            $student->study_year = $request->study_year;
        }
        if ($request->has('term') && $request->term !== $student->term) {
            $student->term = $request->term;
        }

        try {
            $student->save();
            return ['result' => 'success', 'code' => 1, "user" => $student, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }

    function deleteStudent(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:users,id',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        try {
            $student = User::where('id', $request->id)->update(['is_active' => 1]);
            if ($student) {
                return ['result' => 'success', 'code' => 1, 'error' => ''];
            } else {
                return ['result' => 'failed', 'code' => -1, 'error' => 'Student not found'];
            }
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'error' => $e->getMessage()];
        }
    }

    function addCollege(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'college_ar' => 'required|string',
            'college_en' => 'required|string',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $colleg = new College();
        $colleg->college_ar = $request->college_ar;
        $colleg->college_en = $request->college_en;
        try {
            $colleg->save();
            return ['result' => 'success', 'code' => 1, 'colleg' => $colleg, 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'colleg' => '', 'error' => $e];
        }
    }

    function getAllCollege()
    {
        return College::all();
    }

    function getAllRoomType()
    {
        return RoomType::all();
    }

    function addRoomType(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $roomType = new RoomType();
        $roomType->name_ar = $request->name_ar;
        $roomType->name_en = $request->name_en;
        try {
            $roomType->save();
            return ['result' => 'success', 'code' => 1, 'Room Type' => $roomType, 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'colleg' => '', 'error' => $e];
        }
    }

    function addFacilitie(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name_ar' => 'required|string',
            'name_en' => 'required|string',
            'building_id' => 'required|numeric',
            'floor_id' => 'required|numeric',
            // 'suite_id' => 'required|numeric',
            'room_types_id' => 'required|numeric',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $facilitie = new Facilitie();
        $facilitie->name_ar = $request->name_ar;
        $facilitie->name_en = $request->name_en;
        $facilitie->building_id = $request->building_id;
        $facilitie->floor_id = $request->floor_id;
        $facilitie->suite_id = 0;
        $facilitie->room_types_id = $request->room_types_id;
        try {
            $facilitie->save();
            return ['result' => 'success', 'code' => 1, 'facilitie' => $facilitie, 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'error' => $e];
        }
    }

    function deleteFacilitie(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        try {
            Facilitie::find($request->id)->delete();
            return ['result' => 'success', 'code' => 1, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e];
        }
    }

    function getFacilitie(Request $request)
    {
        return Facilitie::all();
    }

    function setLockDataValue(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'roomId' => 'required|numeric|exists:rooms,id',
            'lockData' => 'required|string',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $room = Room::where('id', $request->roomId)->update(['lock_data' => $request->lockData]);
        if ($room) {
            return ['result' => 'Success', 'code' => 1, 'error' => ''];
        } else {
            return ['result' => 'failed', 'code' => 0, 'error' => 'Data not saved'];
        }
    }
}
