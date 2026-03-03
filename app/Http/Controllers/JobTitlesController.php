<?php

namespace App\Http\Controllers;

use App\Models\Job_title;
use App\Http\Requests\JobTitle\AddJobTitleRequest;
use App\Http\Requests\JobTitle\UpdateJobTitleRequest;
use App\Http\Requests\JobTitle\DeleteJobTitleRequest;
use App\Http\Requests\JobTitle\GetJobTitleByDepartmentRequest;

class JobTitlesController extends Controller
{
    public function index()
    {
        try {
            $perPage = \returnPerPage();
            $jobTitles = Job_title::paginate($perPage);
            return \Pagination($jobTitles);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function store(AddJobTitleRequest $request)
    {
        try {
            $jobTitle = Job_title::create([
                'jobtitle' => $request->jobtitle,
                'department_id' => $request->department_id,
                'active' => $request->active ?? 1,
            ]);

            return \SuccessData('Job title added successfully', $jobTitle);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function update(UpdateJobTitleRequest $request)
    {
        try {
            $jobTitle = Job_title::find($request->id);

            $jobTitle->update([
                'name' => $request->name ?? $jobTitle->name,
                'department_id' => $request->department_id ?? $jobTitle->department_id,
                'active' => $request->active ?? $jobTitle->active,
            ]);

            return \SuccessData('Job title updated successfully', $jobTitle);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function destroy(DeleteJobTitleRequest $request)
    {
        try {
            $jobTitle = Job_title::find($request->id);

            if ($jobTitle->users()->exists()) {
                return \Failed('Job title linked to users, can\'t delete.');
            }

            $jobTitle->delete();

            return \Success('Job title deleted successfully');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function getByDepartment(GetJobTitleByDepartmentRequest $request)
    {
        try {
            $jobTitles = Job_title::where('department_id', $request->department_id)
                ->get();

            return \SuccessData('Job titles retrieved successfully', $jobTitles);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
