<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User_permission;
use Illuminate\Http\Request;
use App\Http\Requests\Permission\AddPermissionRequest;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use App\Http\Requests\Permission\DeletePermissionRequest;
use Exception;

class PermissionsController extends Controller
{
    /**
     * Get all permissions with pagination
     */
    public function index()
    {
        try {
            $perPage = \returnPerPage();
            $permissions = Permission::paginate($perPage);
            return \Pagination($permissions);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }


    /**
     * Update a permission
     */
    public function update(UpdatePermissionRequest $request)
    {
        try {
            $permission = Permission::find($request->id);

            $permission->update([
                // 'name_en' => $request->name_en ?? $permission->name_en,
                // 'name_ar' => $request->name_ar ?? $permission->name_ar,
                'description_en' => $request->description_en ?? $permission->description_en,
                'description_ar' => $request->description_ar ?? $permission->description_ar,
                'active' => $request->active ?? $permission->active,
            ]);

            return \SuccessData('Permission updated successfully', $permission);
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Delete a permission
     */
    public function destroy(DeletePermissionRequest $request)
    {
        try {
            $permission = Permission::find($request->id);

            // Check if permission is linked to any users
            if ($permission->userPermissions()->exists()) {
                return \Failed('Cannot delete. This permission is linked to users.');
            }

            $permission->delete();

            return \Success('Permission deleted successfully');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Add user permission
     */
    public function addUserPermission(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'user_id' => 'required|numeric|exists:users,id',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'numeric|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return \Failed($validator->errors()->first());
        }

        try {
            User_permission::where('user_id', $request->user_id)->delete();

            $data = [];
            foreach ($request->permission_ids as $permID) {
                $data[] = [
                    'user_id' => $request->user_id,
                    'permission_id' => $permID,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            User_permission::insert($data);

            return \Success('User permissions added successfully');
        } catch (\Exception $e) {
            return \Failed($e->getMessage());
        }
    }
}
