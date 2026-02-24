<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Suite;
use App\Models\Room;
use App\Messages;
use Exception;
use App\Models\RoomType;
use App\Models\Permission;
use App\Models\User;
use App\Http\Resources\UserResource;
use App\Models\Discount;
use App\Models\Guest_classification;
use App\Models\Guest_feature;
use App\Models\Guest_classification_feature;
use Illuminate\Support\Facades\DB;


class Buildings extends Controller
{
    //=====================================ADD===============================================
    //a
    public function addBuilding(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'number' => 'required|numeric|unique:buildings,number',
            'name' => 'required|string|unique:buildings,name',
            'numberOfFloor' => 'required|numeric',
            'numberFloor' => 'required|numeric',
            'lock_id' => 'nullable|string|unique:buildings,lock_id',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 422);
        }
        DB::beginTransaction();
        try {
            $building = new Building();
            $building->number = $request->number;
            $building->name = $request->name;
            if ($request->has('lock_id')) {
                $building->lock_id = $request->lock_id;
            }
            $building->save();
            $addFloor = $this->addMultiFloor($building->id, $request->numberFloor, $request->numberOfFloor);
            if (count($addFloor) > 0) {
                DB::commit();
                return ['result' => 'success', 'code' => 1, 'building' => $building, 'floors' => $addFloor, 'error' => ''];
            } else {
                DB::rollBack();
                return ['result' => 'failed', 'code' => -1, 'building' => '', 'error' => 'Failed to add floors'];
            }
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, 'building' => '', 'error' => $e->getMessage()];
        }
    }
    public function addFloor(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'building_id' => 'required|numeric|exists:buildings,id',
            'number' => 'required|numeric',
            'lock_id' => 'nullable|string|unique:floors,lock_id',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 422);
        }

        $building = Building::find($request->building_id);
        $building->floors();
        foreach ($building->floors as $f) {
            if ($f->number == $request->number) {
                return ['result' => 'failed', 'code' => -1, 'floor' => '', 'error' => Messages::getMessage('floorNumberExistsInBuilding')];
            }
        }

        $floor = new Floor();
        $floor->building_id = $building->id;
        $floor->number = $request->number;
        if ($request->has('lock_id')) {
            $floor->lock_id = $request->lock_id;
        }
        try {
            $floor->save();
            return ['result' => 'success', 'code' => 1, 'floor' => $floor, 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'floor' => '', 'error' => $e];
        }
    }
    public function addSuite(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'building_id' => 'required|numeric|exists:buildings,id',
            'floor_id' => 'required|numeric|exists:floors,id',
            'number' => 'required|numeric',
            'lock_id' => 'nullable|string|unique:suites,lock_id',
            'rooms' => 'required|array|min:1',
            'rooms.*.id' => 'required|exists:rooms,id',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 422);
        }

        if ($this->checkSuiteInBuilding($request->building_id, $request->number)) {
            return ['result' => 'failed', 'code' => -1, 'suite' => '', 'error' => 'The suite already exists.'];
        }

        DB::beginTransaction();
        try {
            $suite = Suite::create([
                'building_id' => $request->building_id,
                'floor_id' => $request->floor_id,
                'number' => $request->number,
                'suiteStatus' => 0,
                'active' => 1,
                'lock_id' => $request->lock_id ?? null,
            ]);

            if ($suite) {
                $roomIds = collect($request->rooms)->pluck('id');
                Room::whereIn('id', $roomIds)->update(['suite_id' => $suite->id]);
                DB::commit();
                return ['result' => 'success', 'code' => 1, 'suite' => $suite->load('rooms'), 'error' => ''];
            }

            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, 'suite' => '', 'error' => "The suite has not been added"];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, 'suite' => '', 'error' => $e->getMessage()];
        }
    }
    public function addRoom(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'building_id' => 'required|numeric|exists:buildings,id',
            'floor_id' => 'required|numeric|exists:floors,id',
            'suite_id' => 'nullable|numeric|exists:suites,id',
            'number' => 'required|string',
            'room_type_id' => 'required|numeric|exists:room_types,id',
            'capacity' => 'nullable|numeric',
            'lock_id' => 'nullable|string|unique:rooms,lock_id',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 422);
        }

        if ($this->checkRoomInBuilding($request->building_id, $request->number)) {
            return ['result' => 'failed', 'code' => -1, 'suite' => '', 'error' => 'The room is already exists.'];
        }

        $room = new Room();
        $room->building_id = $request->building_id;
        $room->floor_id = $request->floor_id;
        $room->suite_id = null;
        $room->number = $request->number;
        $room->capacity = $request->capacity ?? 0;
        $room->roomStatus = 1;
        $room->room_type_id = $request->room_type_id;
        $room->active = 1;
        try {
            $room->save();
            return ['result' => 'success', 'code' => 1, 'room' => $room, 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'room' => '', 'error' => $e];
        }
    }
    public function addMultiFloor($building_id, $number, $numberOfFloor)
    {
        $building = Building::find($building_id);
        $existingFloor = $building->floors()->where('building_id', '=', $building_id)->where('number', '>=', $number)->where('number', '<', $number + $numberOfFloor)->exists();
        if ($existingFloor) {
            return [];
        }
        $floors = [];
        for ($i = 0; $i < $numberOfFloor; $i++) {
            $newFloor = new Floor();
            $newFloor->building_id = $building_id;
            $newFloor->number = $number + $i;
            $newFloor->save();
            $floors[] = $newFloor;
        }
        return $floors;
    }
    public function addMultiRoom(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'buildingId' => 'required|numeric|exists:buildings,id',
            'floorId' => 'required|numeric|exists:floors,id',
            'numberOfRoom' => 'required|string',
            'numberRoom' => 'required|string',
            'typeRoom' => 'required|numeric|exists:room_types,id',
            'capacity' => 'nullable|numeric',
        ]);
        if ($validation->fails()) {
            return ['result' => 'failed', 'code' => 0, 'error' => $validation->errors()];
        }
        try {
            $floor = Floor::where('id', $request->floorId)->where('building_id', $request->buildingId)->first();
            if (!$floor) {
                return ['result' => 'failed', 'code' => -1, 'error' => 'Floor does not belong to this building'];
            }
            $existingRoom = Room::where('building_id', $request->buildingId)->where('number', '>=', $request->numberRoom)->where('number', '<', $request->numberRoom + $request->numberOfRoom)->exists();
            if ($existingRoom) {
                return ['result' => 'failed', 'code' => -1, 'error' => 'Room numbers already exist in this building'];
            }

            $rooms = [];
            for ($i = 0; $i < $request->numberOfRoom; $i++) {
                $newRoom = new Room();
                $newRoom->building_id = $request->buildingId;
                $newRoom->floor_id = $request->floorId;
                $newRoom->number = $request->numberRoom + $i;
                $newRoom->capacity = $request->capacity ?? 0;
                $newRoom->room_type_id = $request->typeRoom;
                $newRoom->suite_id = null;
                $newRoom->roomStatus = 1;
                $newRoom->active = 1;
                $newRoom->save();
                $rooms[] = $newRoom;
            }
            return ['result' => 'success', 'code' => 1, 'rooms' => $rooms, 'error' => ''];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e];
        }
    }
    //=====================================GET===============================================
    public function getBuildingData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ['result' => 'failed', 'error' => $validator->errors()];
        }

        $building = Building::where("active", 1)->with([
            'floors' => function ($query) {
                $query->where('active', 1);
            },
            'floors.suites' => function ($query) {
                $query->where('active', 1);
            },
            'floors.suites.rooms' => function ($query) {
                $query->where('active', 1);
            },
            'floors.rooms' => function ($query) {
                $query->whereNull('suite_id')->where('active', 1);
            },
            'floors.facilitie',
            'facilitie' => function ($query) {
                $query->whereNull('floor_id');
            }
        ])->get();
        $roomType = RoomType::all();
        $permissions = Permission::all();
        $discount = Discount::all();
        $user = User::find($request->user_id);
        $user->load(['jobtitle', 'department', 'discount', 'permissions']);
        $classification = Guest_classification::with(['features', 'discount'])->get();
        return ['result' => 'success', 'code' => 1, "user"   => new UserResource($user), 'Buildings' => $building, 'RoomType' => $roomType, 'permissions' => $permissions, 'Discount' => $discount, 'classification' => $classification];
    }
    //=====================================DELETE===============================================
    public function deleteRoom(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => "required|numeric|exists:rooms,id",
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 422);
        }
        $room = Room::find($request->id);
        //if has reservation for room jest edit 'active'   1 active | 0 Inactive
        if ($room->isHasReservation()) {
            $room->active = 0;
            $room->save();
            return ['result' => 'success', 'code' => 1, "error" => ""];
        }
        //else not have reservation for room you can deleted
        else {
            try {
                $room->delete();
                return ['result' => 'success', 'code' => 1, "error" => ""];
            } catch (Exception $e) {
                return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
            }
        }
    }
    public function deleteSuite(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id_building' => "required|numeric|exists:buildings,id",
            'id_suite' => "required|numeric|exists:suites,id",
            'delete_rooms' => "required|boolean" // 0 or 1
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $suite = Suite::with('rooms')->where('building_id', $request->id_building)->where('id', $request->id_suite)->first();

        if (!$suite) {
            return response(['result' => 'failed', 'code' => 0, 'error' => 'Suite not found in the specified building.'], 200);
        }
        $hasReservation = false;
        foreach ($suite->rooms as $room) {
            if ($room->isHasReservation()) {
                $hasReservation = true;
                break;
            }
        }
        if ($hasReservation) {
            $suite->active = 0;
            $suite->save();
            if ($request->delete_rooms) {
                foreach ($suite->rooms as $room) {
                    $room->active = 0;
                    $room->save();
                }
            }
            return ['result' => 'success', 'code' => 1, "error" => ""];
        }
        DB::beginTransaction();
        try {
            if ($request->delete_rooms) {
                // حذف الغرف مع السويت
                foreach ($suite->rooms as $room) {
                    $room->delete();
                }
            } else {
                // نخلي الغرف مستقلة (suite_id = null)
                foreach ($suite->rooms as $room) {
                    $room->suite_id = null;
                    $room->save();
                }
            }
            $suite->delete();
            DB::commit();
            return ['result' => 'success', 'code' => 1, "error" => ""];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }
    public function deleteFloor(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id_building' => "required|numeric|exists:buildings,id",
            'id_floor' => "required|numeric|exists:floors,id",
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }

        $floor = Floor::with(['rooms', 'suites.rooms'])->where('building_id', $request->id_building)->where('id', $request->id_floor)->first();
        if (!$floor) {
            return response(['result' => 'failed', 'code' => 0, 'error' => 'Floor not found in the specified building.'], 200);
        }
        $hasReservation = false;
        foreach ($floor->rooms as $room) {
            if ($room->isHasReservation()) {
                $hasReservation = true;
                break;
            }
        }

        if (!$hasReservation) {
            foreach ($floor->suites as $suite) {
                foreach ($suite->rooms as $room) {
                    if ($room->isHasReservation()) {
                        $hasReservation = true;
                        break 2;
                    }
                }
            }
        }

        DB::beginTransaction();
        try {
            //have reservation
            if ($hasReservation) {
                $floor->active = 0;
                $floor->save();

                foreach ($floor->rooms as $room) {
                    $room->active = 0;
                    $room->save();
                }

                foreach ($floor->suites as $suite) {
                    $suite->active = 0;
                    $suite->save();

                    foreach ($suite->rooms as $room) {
                        $room->active = 0;
                        $room->save();
                    }
                }

                DB::commit();
                return ['result' => 'success', 'code' => 1, "error" => ""];
            } else {
                //not have reservation
                foreach ($floor->rooms as $room) {
                    $room->delete();
                }

                foreach ($floor->suites as $suite) {
                    foreach ($suite->rooms as $room) {
                        $room->delete();
                    }
                    $suite->delete();
                }
                $floor->delete();
                DB::commit();
                return ['result' => 'success', 'code' => 1, "error" => ""];
            }
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }
    public function deleteBuilding(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id_building' => "required|numeric|exists:buildings,id",
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $building = Building::with('floors.suites.rooms', 'rooms')->find($request->id_building);
        if (!$building) {
            return response(['result' => 'failed', 'code' => 0, 'error' => 'Building not found.'], 200);
        }
        $hasReservation = false;
        foreach ($building->rooms as $room) {
            if ($room->isHasReservation()) {
                $hasReservation = true;
                break;
            }
        }
        if (!$hasReservation) {
            foreach ($building->floors as $floor) {
                foreach ($floor->suites as $suite) {
                    foreach ($suite->rooms as $room) {
                        if ($room->isHasReservation()) {
                            $hasReservation = true;
                            break 3;
                        }
                    }
                }
            }
        }
        DB::beginTransaction();
        try {
            if ($hasReservation) {
                $building->active = 0;
                $building->save();

                foreach ($building->floors as $floor) {
                    $floor->active = 0;
                    $floor->save();

                    foreach ($floor->suites as $suite) {
                        $suite->active = 0;
                        $suite->save();

                        foreach ($suite->rooms as $room) {
                            $room->active = 0;
                            $room->save();
                        }
                    }
                }

                foreach ($building->rooms as $room) {
                    $room->active = 0;
                    $room->save();
                }
            } else {
                foreach ($building->rooms as $room) {
                    $room->delete();
                }
                foreach ($building->floors as $floor) {
                    foreach ($floor->suites as $suite) {
                        foreach ($suite->rooms as $room) {
                            $room->delete();
                        }
                        $suite->delete();
                    }
                    $floor->delete();
                }
                $building->delete();
            }
            DB::commit();
            return ['result' => 'success', 'code' => 1, "error" => ""];
        } catch (Exception $e) {
            DB::rollBack();
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }
    //=====================================Update===============================================
    public function updateRoom(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:rooms,id',
            'number' => 'nullable|string',
            // 'suite_id' => 'nullable|numeric|exists:suites,id',
            'room_type_id' => 'nullable|numeric|exists:room_types,id',
            'capacity' => 'nullable|numeric',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $room = Room::find($request->id);
        if ($request->has('number') && $request->number != $room->number) {
            if ($this->checkRoomInBuilding($room->building_id, $request->number)) {
                return ['result' => 'failed', 'code' => -1, 'suite' => '', 'error' => 'The room is already exists.'];
            } else {
                $room->number = $request->number;
            }
        }

        if ($request->has('room_type_id')) {
            $room->room_type_id = $request->room_type_id;
        }
        if ($request->has('capacity')) {
            $room->capacity = $request->capacity;
        }
        // if ($request->has('suite_id')) {
        //     $room->suite_id = $request->suite_id;
        // }
        try {
            $room->update();
            return ['result' => 'success', 'code' => 1, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }
    public function updateSuite(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:suites,id',
            'number' => 'nullable|string',
            'room_ids_to_add' => 'nullable|array',
            'room_ids_to_add.*' => 'numeric|exists:rooms,id',
            'room_ids_to_remove' => 'nullable|array',
            'room_ids_to_remove.*' => 'numeric|exists:rooms,id',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }

        $suite = Suite::find($request->id);

        try {
            // تحقق من رقم السويت إذا تغير
            if ($suite->number !== $request->number && $this->checkSuiteInBuilding($suite->building_id, $request->number)) {
                return ['result' => 'failed', 'code' => -1, 'suite' => '', 'error' => 'The suite already exists.'];
            }

            if ($request->number) {
                $suite->number = $request->number;
                $suite->save();
            }

            // إذا في غرف للإضافة
            if ($request->has('room_ids_to_add')) {
                Room::whereIn('id', $request->room_ids_to_add)->update(['suite_id' => $suite->id]);
            }

            // إذا في غرف للحذف
            if ($request->has('room_ids_to_remove')) {
                Room::whereIn('id', $request->room_ids_to_remove)->update(['suite_id' => null]);
            }

            return ['result' => 'success', 'code' => 1, 'suite' => $suite->load('rooms')]; // يرجع السويت مع الغرف المحدثة'error' => ''

        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, 'error' => $e->getMessage()];
        }
    }
    public function updateFloor(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:floors,id',
            'number' => 'required|string',
        ]);
        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $floor = Floor::find($request->id);

        if ($this->checkFloorInBuilding($floor->building_id, $request->number)) {
            return ['result' => 'failed', 'code' => -1, 'suite' => '', 'error' => 'The floor is already exists.'];
        }

        $floor->number = $request->number;
        try {
            $floor->update();
            return ['result' => 'success', 'code' => 1, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }
    public  function updateBuilding(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:buildings,id',
            'name' => 'required|string',
            'number' => 'required|string',
        ]);

        if ($validation->fails()) {
            return response(['result' => 'failed', 'code' => 0, 'error' => $validation->errors()], 200);
        }
        $checkName = Building::where('name', $request->name)->where('id', '!=', $request->id)->exists();
        $checkNumber = Building::where('number', $request->number)->where('id', '!=', $request->id)->exists();
        if ($checkName || $checkNumber) {
            return ['result' => 'failed', 'code' => -1, 'building' => '', 'error' => 'The building name or number already exists.'];
        }

        $building = Building::find($request->id);
        $building->name = $request->name;
        $building->number = $request->number;
        try {
            $building->update();
            return ['result' => 'success', 'code' => 1, 'building' => $building, "error" => ""];
        } catch (Exception $e) {
            return ['result' => 'failed', 'code' => -1, "error" => $e->getMessage()];
        }
    }
    public function checkRoomInBuilding($building_id, $number)
    {
        $CheckRoom = Room::where('building_id', $building_id)->where('number', $number)->exists();
        if ($CheckRoom) {
            return true;
        }
        return false;
    }
    public function checkSuiteInBuilding($building_id, $number)
    {
        $CheckSuite = Suite::where('building_id', $building_id)->where('number', $number)->exists();
        if ($CheckSuite) {
            return true;
        }
        return false;
    }
    public  function checkFloorInBuilding($building_id, $number)
    {
        $floors = Floor::where('building_id', $building_id)->where('number', $number)->exists();
        if ($floors) {
            return true;
        }
    }
    public function checkBuliding($name, $number)
    {
        $building = Building::where('name', $name)->where('number', $number)->exists();
        if ($building) {
            return true;
        }
    }
}
