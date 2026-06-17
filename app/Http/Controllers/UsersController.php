<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Permission;
use App\Models\Job_title;
use App\Models\Department;
use Spatie\Permission\Models\Role;
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
        $arr = Arr::only($request->validated(), ['job_number', 'password' ]);
        $where = ['job_number' => $arr['job_number']];
        $user = User::where($where)->first();
        if (!$user) {
            return \Failed('Invalid job number');
        }
        if (!$user->active) {
            return \Failed('This account is disActive');
        }
        if (!Hash::check($arr['password'], $user->password)) {
            return \Failed('Wrong Password');
        }

        $token = $user->createToken('authToken');
        $user->setAttribute('token', $token);
        $user->load(['roles.permissions', 'permissions']);

        return \SuccessData('Logged In Success', new LoginResource($user));
    }

    /**
     * Current session profile (roles + permissions) for SPA refresh after login.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return \Failed('Unauthorized', 401);
        }

        $user->load(['roles.permissions', 'permissions']);

        return \SuccessData('User session', [
            'id' => $user->id,
            'name' => $user->name,
            'job_number' => $user->job_number,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ]);
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
                'password'      => Hash::make($request->password),
            ]);

            if ($request->filled('permission_ids')) {
                $this->addUserPermission($user->id, $request->permission_ids);
            }

            if ($request->filled('role_id')) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $user->syncRoles([$role->name]);
                    $user->forgetCachedPermissions();
                }
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $user->load(['jobtitle', 'department', 'discount', 'permissions', 'roles']);
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
            $newStatus = !$user->active;
            $user->update(['active' => $newStatus]);
            $statusText = $newStatus ? 'activated' : 'deactivated';
            return \Success("User $statusText successfully");
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

    public function getInfoUsers(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search');

            $query = User::with(['roles.permissions', 'permissions', 'jobtitle', 'department', 'discount']);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%")
                      ->orWhere('job_number', 'like', "%$search%");
                });
            }

            $users = $query->paginate($perPage);

            return \SuccessData('Users retrieved successfully', [
                'users' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ]
            ]);
        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    /**
     * Add user permissions
     */
    private function addUserPermission($user_id, $permission_ids)
    {
        $user = User::findOrFail($user_id);
        $permissionNames = Permission::whereIn('id', $permission_ids)->pluck('name')->all();
        $user->syncPermissions($permissionNames);
    }

    public function changePassword(Request $request)
    {
        try {
            $request->validate([
                'old_password' => 'required',
                'new_password' => 'required|min:6|confirmed',
            ]);

            $user = auth()->user();

            if (!Hash::check($request->old_password, $user->password)) {
                return \Failed('The old password does not match our records.');
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return \Success('Password changed successfully');
        } catch (Exception $e) {
            return \Failed($e->getMessage());
        }
    }

    public function loginError(){
        return response()->json([
            'success'=>false,
            'message'=>'You need to Sign In to access',
            'code'=>403
        ],403);
    }
}
