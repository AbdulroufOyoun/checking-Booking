<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use App\Http\Requests\Department\AddDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Requests\Department\DeleteDepartmentRequest;
use App\Http\Requests\Department\ShowDepartmentRequest;

class DepartmentsController extends Controller
{
    public function index()
    {
        try {
            $perPage = \returnPerPage();
            $departments = Department::paginate($perPage);
            return \Pagination($departments);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function store(AddDepartmentRequest $request)
    {
        try {
            $department = Department::create([
                'name' => $request->name,
                'description' => $request->description ?? null,
            ]);

            return \SuccessData('Department added successfully', $department);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function show(ShowDepartmentRequest $request)
    {
        try {
            $department = Department::find($request->id);

            return \SuccessData('Department retrieved successfully', $department);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function update(UpdateDepartmentRequest $request)
    {
        try {
            $department = Department::find($request->id);

            $department->update([
                'name' => $request->name ?? $department->name,
                'description' => $request->description ?? $department->description,
            ]);

            return \SuccessData('Department updated successfully', $department);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function destroy(DeleteDepartmentRequest $request)
    {
        try {
            $department = Department::find($request->id);

            if ($department->jobtitles()->exists()) {
                return \Failed('Department linked to job titles, can\'t delete.');
            }

            $department->delete();

            return \Success('Department deleted successfully');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
