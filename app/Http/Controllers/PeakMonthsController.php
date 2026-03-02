<?php

namespace App\Http\Controllers;

use App\Models\PeakMonth;
use Illuminate\Http\Request;
use App\Http\Requests\PeakMonth\UpdatePeakMonthRequest;
use App\Http\Resources\PeakMonth\PeakMonthResource;
use Exception;

class PeakMonthsController extends Controller
{
    /**
     * Get all peak months
     */
    public function index()
    {
        try {
            $peakMonths = PeakMonth::all();
            return \SuccessData('Peak months fetched successfully', PeakMonthResource::collection($peakMonths));
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Update peak months check
     */
    public function updateCheck(UpdatePeakMonthRequest $request)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'months' => 'required|array|min:1',
                'months.*.id' => 'required|exists:peak_months,id',
                'months.*.check' => 'required|in:0,1',
            ]);

            if ($validator->fails()) {
                return \Failed($validator->errors()->first());
            }

            foreach ($request->months as $month) {
                PeakMonth::where('id', $month['id'])->update(['check' => $month['check']]);
            }

            return \Success('Peak months check values updated successfully');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
