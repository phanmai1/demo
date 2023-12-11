<?php

namespace App\Http\Controllers\api;

use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Users as ModelsUsers;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class Users extends Controller
{
    public function edit_profice(request $request)
    {

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:50|min:4',
            'phone_number' => 'required|regex:/^\d{10}$/u',
            'birthdate' => 'required|date',
        ], [
            'full_name.string' => 'Họ và Tên phải là một chuỗi.',
            'full_name.max' => 'Họ và Tên không được vượt quá 50 ký tự.',
            'full_name.min' => 'Họ và Tên phải có ít nhất 4 ký tự.',
            'full_name.required' => 'Vui lòng nhập Họ và tên.',
            'phone_number.regex' => 'Số điện thoại không hợp lệ. Hãy nhập 10 chữ số.',
            'phone_number.required' => 'Vui lòng nhập số điện thoại.',
            'birthdate.date' => 'Ngày không hợp lệ.',
            'birthdate.required' => 'Vui lòng nhập ngày sinh.',
        ]);
        $birthdate = Carbon::parse($request->input('birthdate'));
        $currentTime = Carbon::now();

        if ($validator->passes()) {
            if ($birthdate < $currentTime) {
                $user = auth()->user();
                $user = ModelsUsers::where('id', $user->id)->first();
                $user->full_name = $request->input('full_name');
                $user->phone_number = $request->input('phone_number');
                $user->birthdate = $birthdate;
                $user->save();
                return response()->json(['message' => 'Cập nhật thành công'], 200);
            } else {
                return response()->json(['error' => 'Ngày sinh phải trước ngày hiện tại'], 200);
            }
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }
    public function get_profice()
    {
        $user = auth()->user();
        $user = ModelsUsers::where('id', $user->id)->first();
        return response()->json(['user' => $user], 200);
    }
    public function new_avatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'image.image' => 'Vui lòng chọn hình ảnh.',
            'image.mimes' => 'Ảnh phải có định dạng jpeg, png, jpg hoặc gif.',
            'image.max' => 'Ảnh không được vượt quá 2MB.',
        ]);
        $user = auth()->user();
        $user = ModelsUsers::where('id', $user->id)->first();
        if ($user) {
            if ($validator->passes()) {
                if ($request->hasFile('image')) {
                    if ($user->image != null) {
                        $oldImagePath = $user->image;
                        $parts = explode('/', $oldImagePath);
                        $filename = end($parts);
                        $oldImagePath = public_path('uploads/user/' . $filename);
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    $image = $request->file('image');
                    $file_name = Str::slug($user->full_name) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads/user'), $file_name);
                    $user->image = '../../uploads/user/' . $file_name;
                    $user->save();
                    return response()->json(['message' => 'Cập nhật ảnh đại diện thành công'], 200);
                }
            }
            $errors = $validator->errors()->all();
            return response()->json(['errors' => $errors], 200);
        }


    }
    public function new_password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'password' => 'required|min:8|max:30',
            're_password' => 'required|same:password',
        ], [
            'old_password.required' => 'Mật khẩu cũ là bắt buộc.',
            'password.required' => 'Mật khẩu mới là bắt buộc.',
            'password.min' => 'Mật khẩu phải chứa ít nhất 8 ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá 30 ký tự.',
            're_password.required' => 'Xác nhận mật khẩu là bắt buộc.',
            're_password.same' => 'Xác nhận mật khẩu không khớp với mật khẩu.',
        ]);
        if ($validator->passes()) {
            $oldpassword = $request->input('old_password');
            $userTemp = auth()->user();
            $user = ModelsUsers::where('id', $userTemp->id)->first();

            if ($user && Hash::check($oldpassword, $user->password)) {
                $password = $request->input('password');
                if (Hash::check($password, $user->password)) {
                    return response()->json(['error' => 'Mật Khẩu Mới Không Được Giống Mật Khẩu Cũ'], 200);
                }
                $hashedPassword = Hash::make($password);
                if ($user) {
                    $user->password = $hashedPassword;
                }
                if ($user->save()) {
                    return response()->json(['message' => 'Thay đổi mật khẩu thành công'], 200);
                }
            } else {
                return response()->json(['errors' => ['Mật Khẩu Cũ Không Đúng']], 200);
            }
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }


    public function get_count_notication()
    {
        $user = auth()->user();
        $user = ModelsUsers::where('id', $user->id)->first();
        if ($user) {
            $notificationCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();
            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->with('transaction')
                ->get();
            return response()->json([
                'notificationCount' => $notificationCount,
                'notifications' => $notifications
            ]);
        }
        return response()->json(['notificationCount' => 0]);
    }
}
