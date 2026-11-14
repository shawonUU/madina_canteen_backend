<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;

class AuthController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('auth::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('auth::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('auth::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('auth::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}

    
    
    public function register(Request $request)
    {
        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $user = User::create([
            'code' => getGenerateCode(User::class, 'code', 'USR', 8),
            'employee_id' => $request->employee_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'status' => 'Active'
        ]);

        $user = User::with('employee')->find($user->id);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ]);
    }

    public function login(Request $request) { 
        $request->validate([ 
            'email' => 'required|email', 
            'password' => 'required', 
        ]); 
        $user = User::with('employee') 
        ->where('email', $request->email) ->first(); 
        if (!$user) { 
            return response()->json([ 'message' => 'User not found', ], 404); 
        } 
        if ($user->status !== 'Active') { 
            return response()->json([ 'message' => 'User is inactive', ], 403); 
        } 
        if (!Hash::check($request->password, $user->password)) { 
            return response()->json([ 'message' => 'Invalid credentials', ], 401); 
        } 
        $accesses = UserAccess::where('user_id', $user->id) 
            ->get([ 'id', 'module_id', 'menu_id', 'child_menu_id', 'can_view', 'can_create', 'can_update', 'can_delete', ]) 
            ->map(function ($permission) { 
                return [ 
                    'module_id' => $permission->module_id, 
                    'menu_id' => $permission->menu_id, 
                    'child_menu_id' => $permission->child_menu_id, 
                    'can_view' => (bool) $permission->can_view, 
                    'can_create' => (bool) $permission->can_create, 
                    'can_update' => (bool) $permission->can_update, 
                    'can_delete' => (bool) $permission->can_delete,
                ]; 
            }) ->values(); 
        
        $token = $user->createToken('auth_token')->plainTextToken; 
        return response()->json([ 
            'message' => 'Login successful', 
            'token' => $token, 
            'user' => [ 'id' => $user->id, 
            'name' => $user->name, 
            'email' => $user->email, 
            'status' => $user->status, 
            'employee' => $user->employee, ], 
            'accesses' => $accesses, 
        ]); 
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email.',
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Please wait before requesting another reset link.',
            ], 429);
        }

        return response()->json([
            'message' => 'Unable to send password reset link.',
        ], 422);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully.',
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired password reset token.',
        ], 422);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

}
