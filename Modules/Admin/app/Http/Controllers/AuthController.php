<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\User;
use Modules\HRM\Models\Employee;

class AuthController extends Controller
{
    public function __construct(private AccessService $access) {}

    public function register(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'nullable|integer|exists:employees,id',
            'name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);
        if (!empty($data['employee_id'])) {
            $employee = Employee::findOrFail($data['employee_id']);
            abort_unless(strcasecmp((string) $employee->email, $data['email']) === 0, 422, 'Employee email does not match.');
            abort_if(User::where('employee_id', $employee->id)->exists(), 422, 'This employee already has an account.');
        }
        $user = User::create([
            'code' => getGenerateCode(User::class, 'code', 'USR'), 'employee_id' => $data['employee_id'] ?? null,
            'name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'status' => 'Active',
        ]);
        return response()->json(['message' => 'User registered successfully', 'user' => $user->load('employee')], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }
        if ($user->status !== 'Active') return response()->json(['message' => 'Your account is inactive.'], 403);
        return response()->json([
            'message' => 'Login successful', 'token' => $user->createToken('auth_token')->plainTextToken,
            ...$this->payload($user),
        ]);
    }

    private function payload(User $user): array
    {
        $user->load(['employee.departmentRelation', 'employee.designationRelation', 'roles']);
        return ['user' => $user, 'accesses' => $this->access->effectiveAccesses($user), 'permissions' => $this->access->permissions($user)];
    }

    public function me(Request $request)
    {
        return response()->json($this->payload($request->user()));
    }

    public function navigation(Request $request)
    {
        $accesses = $this->access->effectiveAccesses($request->user());
        $modules = Module::with(['menus.childMenus'])->where('is_active', true)->orderBy('sort_order')->get();
        $data = $modules->map(function ($module) use ($accesses) {
            $menus = $module->menus->where('is_active', true)->sortBy('sort_order')->map(function ($menu) use ($accesses) {
                $children = $menu->childMenus->where('is_active', true)->sortBy('sort_order')
                    ->filter(fn ($child) => $accesses->contains(fn ($a) => (int) $a['child_menu_id'] === (int) $child->id && $a['can_view']))->values();
                $view = $children->isNotEmpty() || $accesses->contains(fn ($a) => (int) $a['menu_id'] === (int) $menu->id && !$a['child_menu_id'] && $a['can_view']);
                if (!$view) return null;
                $item = $menu->toArray(); $item['child_menus'] = $children;
                return $item;
            })->filter()->values();
            if ($menus->isEmpty()) return null;
            $item = $module->toArray(); $item['menus'] = $menus;
            return $item;
        })->filter()->values();
        return response()->json(['success' => true, 'status' => 'Success', 'data' => $data, 'accesses' => $accesses, 'permissions' => $this->access->permissions($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $status = Password::broker()->sendResetLink($data);
        if ($status === Password::RESET_THROTTLED) return response()->json(['message' => 'Please wait before requesting another reset link.'], 429);
        return response()->json(['message' => 'If the account exists, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => 'required|string|min:8|confirmed']);
        $status = Password::broker()->reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
            $user->save();
            $user->tokens()->delete();
        });
        return response()->json(['message' => $status === Password::PASSWORD_RESET ? 'Password reset successfully.' : 'Invalid or expired password reset link.'], $status === Password::PASSWORD_RESET ? 200 : 422);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'new_password' => 'required|string|min:8|confirmed']);
        if (!Hash::check($data['current_password'], $request->user()->password)) return response()->json(['message' => 'Current password is incorrect.'], 422);
        $request->user()->update(['password' => Hash::make($data['new_password'])]);
        return response()->json(['message' => 'Password changed successfully.']);
    }
}
