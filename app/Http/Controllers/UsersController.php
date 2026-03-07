<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Job_title;
use App\Models\Department;
use App\Models\User_permission;
use App\Http\Resources\UserResource;
use App\Http\Requests\User\AddUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\InActiveUserRequest;
use App\Http\Requests\User\LoginRequest;
use App\Http\Resources\Login\LoginResource;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UsersController extends Controller
{
    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('job_number', $request->job_number)->first();

        if (!$user) {
            return \Failed('User not found');
        }

        if (!$user->active) {
            return \Failed('This account is disActive');
        }

        if (!Hash::check($request->password, $user->password)) {
            return \Failed('Wrong Password');
        }

        $user['token'] = $user->createToken('authToken');
        $user['permissions'] = $user->permissions();
        return \SuccessData('Login Successful', new LoginResource($user));
    }

    /**
     * Add new user
     */
    public function store(AddUserRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = User::create([
                'name'          => $request->name,
                'job_number'    => $request->job_number,
                'jobtitle_id'   => $request->jobtitle_id,
                'department_id' => $request->department_id,
                'mobile'        => $request->mobile,
                'email'         => $request->email,
                'discount_id'   => $request->discount_id ?? null,
                'active'        => 1,
                'password'      => Hash::make($request->job_number),
            ]);

            if ($request->has('permission_ids')) {
                $this->addUserPermission($user->id, $request->permission_ids);
            }

            $user->load(['jobtitle', 'department', 'discount', 'permissions']);
            DB::commit();
            return \SuccessData('User added Successfully', new UserResource($user));
        } catch (Exception $e) {
            DB::rollBack();
            return \Failed($e->getMessage());
        }
    }

    /**
     * Update user
     */
    public function update(UpdateUserRequest $request)
    {
        $user = User::find($request->id);

        if (!$user) {
            return \Failed('User not found');
        }

        DB::beginTransaction();
        try {
            $user->update($request->only([
                'jobtitle_id',
                'department_id',
                'mobile',
                'email',
                'discount_id',
                'name'
            ]));

            if ($request->has('permission_ids')) {
                $oldPermissions = $user->permissions()->pluck('permission_id')->toArray();
                $newPermissions = $request->permission_ids;
                sort($oldPermissions);
                sort($newPermissions);
                if ($oldPermissions !== $newPermissions) {
                    $this->addUserPermission($request->id, $newPermissions);
                }
            }

            $user->load(['jobtitle', 'department', 'discount', 'permissions']);
            DB::commit();
            return \Success('Record Update Successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return \Failed($e->getMessage());
        }
    }

    /**
     * Deactivate user
     */
    public function inActive(InActiveUserRequest $request)
    {
        $user = User::find($request->id);

        if (!$user) {
            return \Failed('User not found');
        }

        try {
            $user->update(['active' => !$user->active]);
            return \Success('User deactivated successfully');
        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Get all users info
     */
    public function index()
    {
        try {
            $perPage = \returnPerPage();
            $activeUsers = User::with(['jobtitle', 'department', 'discount', 'permissions'])
                ->where('active', 1)
                ->paginate($perPage);
            return \Pagination($activeUsers);
        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Add user permissions
     */
    private function addUserPermission($user_id, $permission_ids)
    {
        User_permission::where('user_id', $user_id)->delete();
        $data = [];
        foreach ($permission_ids as $permID) {
            $data[] = [
                'user_id'      => $user_id,
                'permission_id' => $permID,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        User_permission::insert($data);
    }
}
