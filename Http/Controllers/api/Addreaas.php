<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class Addreaas extends Controller
{
    public function add_new_address(request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_name' => [
                'required',
                'string',
                'max:100',
                'min:1',
            ],
            'address' => [
                'required',
                'string',
                'min:30',
            ],
            'contact_info' => [
                'required',
                'string',
                'regex:/^[0-9]+$/',
                'min:10',
            ],
        ], [
            'contact_name.required' => 'Vui lòng nhập họ và tên người liên hệ',
            'contact_name.string' => 'Họ và tên phải là chuỗi',
            'contact_name.max' => 'Họ và tên không được vượt quá 100 kí tự',
            'contact_name.min' => 'Họ và tên phải có ít nhất 1 kí tự',

            'address.required' => 'Vui lòng nhập địa chỉ',
            'address.string' => 'Địa chỉ phải là chuỗi',
            'address.min' => 'Địa chỉ phải có ít nhất 30 kí tự',

            'contact_info.required' => 'Vui lòng nhập số điện thoại liên hệ',
            'contact_info.string' => 'Số điện thoại liên hệ phải là chuỗi',
            'contact_info.regex' => 'Vui lòng chỉ nhập số điện thoại liên hệ',
            'contact_info.min' => 'Số điện thoại phải có ít nhất 10 số',
        ]);
        $user = auth()->user();
        $existingAddress = Address::where('user_id', $user->id)
            ->where('address', $request->input('address'))
            ->where('contact_info', $request->input('contact_info'))
            ->where('contact_name', $request->input('contact_name'))
            ->first();

        if ($existingAddress) {
            return response()->json(['errors' => ['Địa này chỉ đã tồn tại']], 200);
        }
        if ($validator->passes()) {
            $address = new Address();
            $address->user_id = $user->id;
            $address->address = $request->input('address');
            $address->contact_name = $request->input('contact_name');
            $address->contact_info = $request->input('contact_info');
            $address->save();
            return response()->json(['message' => 'Thêm mới địa chỉ thành công'], 200);
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }
    public function get_add_address(Request $request)
    {
        $user = auth()->user();
        $addresses = Address::where('user_id', $user->id)->get();
        return response()->json($addresses);
    }

    
}
