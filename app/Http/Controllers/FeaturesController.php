<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Feature;
use App\Models\Room_feature;
use App\Models\Room;
use App\Http\Requests\Feature\AddFeatureRequest;
use App\Http\Requests\Feature\UpdateFeatureRequest;
use App\Http\Requests\Feature\DeleteFeatureRequest;
use App\Http\Requests\Feature\AddRoomFeatureRequest;
use App\Http\Requests\Feature\UpdateRoomFeatureRequest;
use App\Http\Requests\Feature\DeleteRoomFeatureRequest;
use App\Http\Requests\Feature\GetRoomFeatureByRoomRequest;
use Exception;

class FeaturesController extends Controller
{
    //=====================================Features===============================================

    /**
     * Get all features with pagination
     */
    public function index(Request $request)
    {
        try {
            $perPage = \returnPerPage();
            $features = Feature::paginate($perPage);
            return \Pagination($features);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Add new feature
     */
    public function store(AddFeatureRequest $request)
    {
        DB::beginTransaction();
        try {
            $feature = Feature::create([
                'name_ar' => $request->name_ar,
                'name_en' => $request->name_en,
                'description' => $request->description ?? null,
            ]);
            DB::commit();
            return \SuccessData('Feature added Successfully', $feature);
        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed('An unexpected error occurred. Please try again later.');
        }
    }

    /**
     * Update feature
     */
    public function update(UpdateFeatureRequest $request)
    {
        $feature = Feature::find($request->id);

        try {
            $feature->update([
                'name_ar' => $request->name_ar ?? $feature->name_ar,
                'name_en' => $request->name_en ?? $feature->name_en,
                'description' => $request->description ?? $feature->description,
            ]);
            return \Success('Record Update Successfully');
        } catch (\Exception $e) {
            return \Failed('An unexpected error occurred. Please try again later.');
        }
    }

    /**
     * Delete feature
     */
    public function destroy(DeleteFeatureRequest $request)
    {
        DB::beginTransaction();
        try {
            $feature = Feature::find($request->id);
            if (count($feature->room_features)>0) {
                return \Failed('Cannot delete. This Feature is linked to Room Feature.');
            }
            $feature->delete();
            DB::commit();
            return \Success('Feature deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed('An unexpected error occurred. Please try again later.');
        }
    }

    //=====================================Room Features===============================================

    /**
     * Get all room features with pagination
     */
    public function roomIndex(Request $request)
    {
        try {
            $perPage = \returnPerPage();
            $roomFeatures = Room_feature::with(['room', 'feature'])->paginate($perPage);
            return \Pagination($roomFeatures);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Add new room feature
     */
    public function roomStore(AddRoomFeatureRequest $request)
    {
        DB::beginTransaction();
        try {
            $roomFeature = Room_feature::updateOrCreate(
                ['room_id' => $request->room_id, 'feature_id' => $request->feature_id],
                ['number' => $request->number ?? 1]
            );
            $roomFeature->load(['room', 'feature']);
            DB::commit();
            return \SuccessData('Room Feature added Successfully', $roomFeature);
        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed('An unexpected error occurred. Please try again later.');
        }
    }

    /**
     * Update room feature
     */
    public function roomUpdate(UpdateRoomFeatureRequest $request)
    {
        $roomFeature = Room_feature::find($request->id);

        try {
            $roomFeature->update([
                'room_id' => $request->room_id ?? $roomFeature->room_id,
                'feature_id' => $request->feature_id ?? $roomFeature->feature_id,
                'number' => $request->number ?? $roomFeature->number,
            ]);
            $roomFeature->load(['room', 'feature']);
            return \Success('Record Update Successfully');
        } catch (\Exception $e) {
            return \Failed('An unexpected error occurred. Please try again later.');
        }
    }

    /**
     * Delete room feature
     */
    public function roomDestroy(DeleteRoomFeatureRequest $request)
    {
        DB::beginTransaction();
        try {
            $roomFeature = Room_feature::find($request->id);
            $roomFeature->delete();
            DB::commit();
            return \Success('Room Feature deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return \Failed('An unexpected error occurred. Please try again later.');
        }
    }

    /**
     * Get room features by room
     */
    public function getByRoom(GetRoomFeatureByRoomRequest $request)
    {
        try {
            $roomFeatures = Room_feature::with(['room', 'feature'])
                ->where('room_id', $request->room_id)
                ->get();
            return \SuccessData('Room Features fetched successfully', $roomFeatures);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
