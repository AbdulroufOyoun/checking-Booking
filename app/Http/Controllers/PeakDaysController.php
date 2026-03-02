<?php

namespace App\Http\Controllers;

use App\Models\PeakDay;
use Illuminate\Http\Request;
use App\Http\Requests\PeakDay\UpdatePeakDayRequest;
use App\Http\Resources\PeakDay\PeakDayResource;
use Exception;

class PeakDaysController extends Controller
{
    /**
     * Get all peak days
     */
    public function index()
    {
        try {
            $peakDays = PeakDay::all();
            return \SuccessData('Peak days fetched successfully', PeakDayResource::collection($peakDays));
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Update peak days check
     */
    public function updateCheck(UpdatePeakDayRequest $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'days' => 'required|array|min:1',
                'days.*.id' => 'required|exists:peak_days,id',
                'days.*.check' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return \Failed($validator->errors()->first());
            }

            foreach ($request->days as $day) {
                PeakDay::where('id', $day['id'])->update(['check' => $day['check']]);
            }

            return \Success('Peak days check values updated successfully');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
