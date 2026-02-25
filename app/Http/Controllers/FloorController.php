<?php

namespace App\Http\Controllers;

use App\Http\Requests\Floor\AddFloorRequest;
use App\Http\Requests\Floor\DeleteFloorRequest;
use App\Http\Requests\Floor\UpdateFloorRequest;
use App\Models\Building;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Suite;
use App\Messages;
use Exception;
use Illuminate\Support\Facades\DB;

class FloorController extends Controller
{
    //=====================================ADD FLOOR===============================================
    public function addFloor(AddFloorRequest $request)
    {
        $building = Building::find($request->building_id);
        $building->floors();
        // foreach ($building->floors as $floor) {
        //     if ($floor->number == $request->number) {
        //         return Failed(Messages::getMessage('floorNumberExistsInBuilding'));
        //     }
        // }

        $floor = new Floor();
        $floor->building_id = $building->id;
        $floor->number = $request->number;
        if ($request->has('lock_id')) {
            $floor->lock_id = $request->lock_id;
        }
        try {
            $floor->save();
            return SuccessData('Floor added Successfully', $floor);
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    // public function addMultiFloor($building_id, $number, $numberOfFloor)
    // {
    //     $building = Building::find($building_id);
    //     $existingFloor = $building->floors()->where('building_id', '=', $building_id)->where('number', '>=', $number)->where('number', '<', $number + $numberOfFloor)->exists();
    //     if ($existingFloor) {
    //         return [];
    //     }
    //     $floors = [];
    //     for ($i = 0; $i < $numberOfFloor; $i++) {
    //         $newFloor = new Floor();
    //         $newFloor->building_id = $building_id;
    //         $newFloor->number = $number + $i;
    //         $newFloor->save();
    //         $floors[] = $newFloor;
    //     }
    //     return $floors;
    // }

    public function deleteFloor(DeleteFloorRequest $request)
    {
        $floor = Floor::with(['rooms', 'suites.rooms'])->where('id', $request->id_floor)->first();
        if (!$floor) {
            return Failed('Floor not found in the specified building.');
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
                return Success('Floor deactivated due to existing reservations.');
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
                return Success('Floor deleted successfully.');
            }
        } catch (Exception $e) {
            DB::rollBack();
            return Failed($e->getMessage());
        }
    }

    //=====================================UPDATE FLOOR===============================================
    public function updateFloor(UpdateFloorRequest $request)
    {
        $floor = Floor::find($request->id);
        $floor->number = $request->number;
        try {
            $floor->update();
            return Success('Floor updated Successfully');
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    //=====================================HELPER FUNCTIONS===============================================
    // public function checkFloorInBuilding($building_id, $number)
    // {
    //     $floors = Floor::where('building_id', $building_id)->where('number', $number)->exists();
    //     if ($floors) {
    //         return true;
    //     }
    // }
}
