<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use App\Http\Requests\Permission\DeletePermissionRequest;
use App\Models\PermissionCategory;

class PermissionsController extends Controller
{
    public function index()
    {
        try {
            $permissions = Permission::with('category')->get();
            return \SuccessData('Permissions retrieved successfully', $permissions);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function update(UpdatePermissionRequest $request)
    {
        try {
            $permission = Permission::findOrFail($request->id);

            $permission->update([
                'name_ar' => $request->name_ar ?? $permission->name_ar,
                'description_en' => $request->description_en ?? $permission->description_en,
                'description_ar' => $request->description_ar ?? $permission->description_ar,
                'category_id' => $request->category_id ?? $permission->category_id,
            ]);

            return \SuccessData('Permission updated successfully', $permission->load('category'));
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function destroy(DeletePermissionRequest $request)
    {
        try {
            $permission = Permission::findOrFail($request->id);
            $permission->delete();
            return \Success('Permission deleted successfully');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
