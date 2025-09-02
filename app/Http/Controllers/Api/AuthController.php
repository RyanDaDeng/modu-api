<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. 检查蜜罐字段（如果填写了说明是机器人）
        if ($request->has('website') && !empty($request->input('website'))) {
            // 静默失败，不给机器人任何提示
            return response()->json([
                'user' => ['id' => rand(1000, 9999), 'name' => 'bot', 'email' => 'bot@example.com'],
                'token' => 'fake-token-' . uniqid(),
                'message' => '注册成功',
            ], 201);
        }
        
        // 2. 验证时间戳（防止重放攻击，5分钟内有效）
        if ($request->has('_timestamp') && $request->has('_signature')) {
            $timestamp = $request->input('_timestamp');
            $signature = $request->input('_signature');
            
            // 检查时间戳是否在5分钟内
            $currentTime = round(microtime(true) * 1000);
            if (abs($currentTime - $timestamp) > 300000) { // 5分钟 = 300000毫秒
                return response()->json(['message' => '请求已过期，请刷新页面重试'], 400);
            }
            
            // 验证签名
            $expectedSignature = base64_encode($timestamp . ':modu18');
            if ($signature !== $expectedSignature) {
                return response()->json(['message' => '无效的请求签名'], 400);
            }
        }
        
        // 3. IP基础的速率限制（防止同一IP大量注册）
        $ipKey = 'register:ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 3)) {
            $seconds = RateLimiter::availableIn($ipKey);
            return response()->json([
                'message' => "注册次数过多，请 {$seconds} 秒后再试"
            ], 429);
        }
        
        // 4. 邮箱域名速率限制（防止同一邮箱域名大量注册）
        $email = $request->input('email');
        if ($email) {
            $emailDomain = substr(strrchr($email, "@"), 1);
            $domainKey = 'register:domain:' . $emailDomain;
            if (RateLimiter::tooManyAttempts($domainKey, 10)) {
                $seconds = RateLimiter::availableIn($domainKey);
                return response()->json([
                    'message' => "该邮箱域名注册次数过多，请稍后再试"
                ], 429);
            }
        }
        
        // 正常的验证逻辑
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required' => '请输入用户名',
            'name.max' => '用户名不能超过255个字符',
            'email.required' => '请输入邮箱',
            'email.email' => '请输入有效的邮箱地址',
            'email.unique' => '该邮箱已被注册',
            'password.required' => '请输入密码',
            'password.min' => '密码至少需要8个字符',
            'password.confirmed' => '两次输入的密码不一致',
        ]);

        // 增加速率限制计数
        RateLimiter::hit($ipKey, 3600); // 1小时内最多3次
        if (isset($domainKey)) {
            RateLimiter::hit($domainKey, 3600); // 1小时内同一邮箱域名最多10次
        }

        // Check if inviter_id is provided and valid
        $inviterId = null;
        if ($request->has('inviter_id')) {
            $inviter = User::find($request->inviter_id);
            if ($inviter) {
                $inviterId = $inviter->id;
            }
        }
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'inviter_id' => $inviterId,
        ]);

        // Create token for the user (default 7 days)
        $token = $user->createToken('auth-token', ['*'], now()->addDays(7));

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
            'message' => '注册成功',
        ], 201);
    }

    public function login(Request $request)
    {
        // 1. 验证时间戳（防止重放攻击）
        if ($request->has('_timestamp') && $request->has('_signature')) {
            $timestamp = $request->input('_timestamp');
            $signature = $request->input('_signature');
            
            // 检查时间戳是否在5分钟内
            $currentTime = round(microtime(true) * 1000);
            if (abs($currentTime - $timestamp) > 300000) { // 5分钟
                return response()->json(['message' => '请求已过期，请刷新页面重试'], 400);
            }
            
            // 验证签名
            $expectedSignature = base64_encode($timestamp . ':modu18');
            if ($signature !== $expectedSignature) {
                return response()->json(['message' => '无效的请求签名'], 400);
            }
        }
        
        // 2. 登录失败速率限制（防暴力破解）
        $email = $request->input('email', '');
        $loginKey = 'login:' . $request->ip() . ':' . $email;
        
        if (RateLimiter::tooManyAttempts($loginKey, 5)) {
            $seconds = RateLimiter::availableIn($loginKey);
            
            // 记录可疑行为
            \Log::warning('Too many login attempts', [
                'ip' => $request->ip(),
                'email' => $email,
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json([
                'message' => "登录尝试次数过多，请 {$seconds} 秒后再试"
            ], 429);
        }
        
        // 3. IP级别的全局限制
        $ipKey = 'login:ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $seconds = RateLimiter::availableIn($ipKey);
            return response()->json([
                'message' => "请求过于频繁，请稍后再试"
            ], 429);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean'
        ], [
            'email.required' => '请输入邮箱',
            'email.email' => '请输入有效的邮箱地址',
            'password.required' => '请输入密码',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // 增加失败计数
            RateLimiter::hit($loginKey, 300); // 5分钟窗口
            RateLimiter::hit($ipKey, 3600); // 1小时窗口
            
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误'],
            ]);
        }

        // 登录成功，清除失败计数
        RateLimiter::clear($loginKey);
        
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
            'message' => '登录成功',
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the current user's token
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => '退出成功']);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        // Add VIP status and admin status to response
        if ($user) {
            $userData = $user->toArray();
            $userData['is_vip'] = $user->hasActiveVip();
            $userData['is_admin'] = (bool) $user->is_admin;
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