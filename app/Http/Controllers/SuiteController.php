<?php

namespace App\Http\Controllers;

use App\Http\Requests\Suite\AddSuiteRequest;
use App\Http\Requests\Suite\DeleteSuiteRequest;
use App\Http\Requests\Suite\UpdateSuiteRequest;
use App\Http\Requests\Suite\SuiteIndexRequest;
use App\Models\Suite;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Exception;

class SuiteController extends Controller
{
    public function index(SuiteIndexRequest $request)
    {
        try {
            $perPage = \returnPerPage();
            $query = Suite::where('active', 1);

            if($request->building_id) {
                $query->where('building_id', $request->building_id);
            }
            if ($request->floor_id) {
                $query=Suite::where('active', 1)->where('floor_id', $request->floor_id);
            }



            $suites = $query->paginate($perPage);
            return \Pagination($suites);
        } catch (\Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function addSuite(AddSuiteRequest $request)
    {
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
                return SuccessData('Suite added Successfully', $suite->load('rooms'));
            }
            DB::rollBack();
            return Failed('The suite has not been added',422);
        } catch (Exception $e) {
            DB::rollBack();
            return Failed($e->getMessage());
        }
    }

    public function updateSuite(UpdateSuiteRequest $request)
    {
        $suite = Suite::find($request->id);

        try {
            if ($request->number) {
                $suite->number = $request->number;
                $suite->save();
            }
            if ($request->has('room_ids_to_add')) {
                Room::whereIn('id', $request->room_ids_to_add)->update(['suite_id' => $suite->id]);
            }
            if ($request->has('room_ids_to_remove')) {
                Room::whereIn('id', $request->room_ids_to_remove)->update(['suite_id' => null]);
            }
            return SuccessData('Suite updated Successfully', $suite->load('rooms'));
        } catch (Exception $e) {
            return Failed($e->getMessage());
        }
    }

    public function deleteSuite(DeleteSuiteRequest $request)
    {
        $suite = Suite::with('rooms')->find($request->id_suite);

        if (!$suite) {
            return Failed('Suite not found in the specified building.');
        }
        $hasReservation = $suite->rooms->contains->isHasReservation();

        DB::beginTransaction();
        try {
            if ($hasReservation) {
                $suite->update(['active' => 0]);

                if ($request->delete_rooms) {
                    $suite->rooms()->update(['active' => 0]);
                }

                DB::commit();
                return Success('Suite deactivated due to existing reservations.');
            }

            if ($request->delete_rooms) {
                $suite->rooms()->delete();
            } else {
                $suite->rooms()->update(['suite_id' => null]);
            }

            $suite->delete();

            DB::commit();
            return Success('Suite deleted successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            return Failed($e->getMessage());
        }
    }
}
