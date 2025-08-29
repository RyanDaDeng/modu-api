<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create token for the user (default 7 days)
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7));

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'message' => 'Registration successful',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Delete existing tokens for this user (optional - for single device login)
        // $user->tokens()->delete();

        // Create token with different expiration based on "remember me"
        $remember = $request->input('remember', false);
        
        if ($remember) {
            // Remember me: 30 days
            $token = $user->createToken('auth-token', ['*'], now()->addDays(30));
        } else {
            // Normal: 7 days
            $token = $user->createToken('auth-token', ['*'], now()->addDays(7));
        }

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'message' => 'Login successful',
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the current user's token
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        
        // Add VIP status to response
        if ($user) {
            $userData = $user->toArray();
            $userData['is_vip'] = $user->hasActiveVip();
            return response()->json($userData);
        }
        
        return response()->json($user);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $user->name = $request->name;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '用户名已更新',
            'user' => $user
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'old_password.required' => '请输入旧密码',
            'new_password.required' => '请输入新密码',
            'new_password.min' => '新密码至少需要8个字符',
            'new_password.confirmed' => '两次输入的新密码不一致',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        // Check if old password is correct
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'errors' => [
                    'old_password' => ['旧密码不正确']
                ]
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '密码修改成功'
        ]);
    }
}
