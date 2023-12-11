<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware("auth::api", ['except' => ['login', 'register']]);
    }
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'password.required' => 'Mật khẩu là bắt buộc.',
        ]);
        if ($validator->passes()) {
            $email = $request->input('email');
            $password = $request->input('password');
            $user = Users::where('email', $email)->first();
            if ($user && Hash::check($password, $user->password)) {
                $credentials = $request->only('email', 'password');
                $token = Auth::attempt($credentials);
                if (!$token) {
                    return response()->json([
                        'message' => 'Unauthorized',
                    ], 401);
                }
                $user = Auth::user();
                return response()->json([
                    'user' => $user,
                    'authorization' => [
                        'token' => $token,
                        'type' => 'bearer',
                    ]
                ]);
            } else {
                return response()->json(['errors' => ['Tên đăng nhập hoặc mật khẩu không đúng']], 200);
            }
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }
    public function register(request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|max:40',
            'email' => 'required|email|unique:users|max:100',
            'password' => 'required|min:8|max:30',
        ], [
            'full_name.required' => 'Tên đăng nhập là bắt buộc.',
            'full_name.max' => 'Tên đăng nhập không được vượt quá 40 ký tự.',
            'email.required' => 'Địa chỉ email là bắt buộc.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Địa chỉ email đã tồn tại.',
            'email.max' => 'Địa chỉ email không được vượt quá 100 ký tự.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password.min' => 'Mật khẩu phải chứa ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá 30 ký tự.',
        ]);

        if ($validator->passes()) {
            $user = Users::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'role' => 0,
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user
            ]);
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }
    public function logout()
    {
        Auth::logout();
        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'user' => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }


}
