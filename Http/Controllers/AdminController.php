<?php

namespace App\Http\Controllers;

use App\Models\district;
use App\Models\Food;
use App\Models\food_transactions;
use App\Models\ImagesFood;
use App\Models\province;
use App\Models\rate;
use App\Models\Users;
use App\Models\ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class AdminController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
        ], [
            'email.required' => 'Email là bắt buộc.',
            'password.required' => 'Mật khẩu là bắt buộc.',
        ]);
        if ($validator->passes()) {
            //tiến hành kiểm tra thông tin đăng nhập
            $email = $request->input('email');
            $password = $request->input('password');
            $user = Users::where('email', $email)->first();
            if ($user && Hash::check($password, $user->password)) {
                Session::put('id', $user->id);
                if ($user->role == 1) {
                    return response()->json(['admin' => 'Đăng nhập thành công'], 200);
                }
                return response()->json(['message' => 'Đăng nhập thành công'], 200);
            } else {
                return response()->json(['errors' => ['Tên đăng nhập hoặc mật khẩu không đúng']], 200);
            }
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }


    public function show_dashboard()
    {
        $today = now()->format('Y-m-d');
        $transactionCounttoday = DB::table('food_transactions')
            ->whereDate('created_at', $today)
            ->count();
        $transactionCount = DB::table('food_transactions')->count();
        $userCount = DB::table('users')->count();
        $foodCount = DB::table('food')->count();
        return view("admin.admin_dashboard", [
            'transactionCounttoday' => $transactionCount,
            'transactionCount' => $transactionCount,
            'userCount' => $userCount,
            'foodCount' => $foodCount,
        ]);
    }
    public function logout()
    {
        session()->forget('id');
        return redirect()->route('login');
    }
    public function show_manage_donated(Request $request)
    {
        // $request->session()->forget('searchContent');
        // $foodType = $request->input('food_type');
        // $foodStatus = $request->input('food_status');
        // $foods = Food::orderBy('created_at', 'desc');
        // if ($request->has('searchContent')) {
        //     $searchContent = $request->input('searchContent');
        //     session(['searchContent' => $searchContent]);
        //     $foods->where(function ($query) use ($searchContent) {
        //         $query->where('title', 'like', '%' . $searchContent . '%')
        //             ->orWhere('description', 'like', '%' . $searchContent . '%');
        //     });
        //     $foods = $foods->orWhereHas('user', function ($userQuery) use ($searchContent) {
        //         $userQuery->where('username', 'like', '%' . $searchContent . '%');
        //     });
        // }
        // if ($request->has('food_type')) {
        //     $foodType = $request->input('food_type');
        //     if ($foodType != 'null') {
        //         $foods->where('food_type', $foodType);
        //     }
        // }
        // if ($request->has('food_status')) {
        //     $foodStatus = $request->input('food_status');
        //     if ($foodStatus != 'null') {
        //         $foods->where('status', $foodStatus);
        //     }
        // }
        // $foods = $foods->paginate(10);
        // return view('admin/manage_donated', compact('foods'));


        $request->session()->forget('searchContent');
        $foodType = $request->input('food_type');
        $foodStatus = $request->input('food_status');
        $foods = Food::with('user')
            ->orderBy('created_at', 'desc');
        if ($request->has('searchContent')) {
            $searchContent = $request->input('searchContent');
            session(['searchContent' => $searchContent]);
            $foods->where(function ($query) use ($searchContent) {
                $query->where('title', 'like', '%' . $searchContent . '%')
                    ->orWhere('description', 'like', '%' . $searchContent . '%');
            });
            $foods = $foods->orWhereHas('user', function ($userQuery) use ($searchContent) {
                $userQuery->where('full_name', 'like', '%' . $searchContent . '%');
            });
        }
        if ($request->has('food_type')) {
            $foodType = $request->input('food_type');
            if ($foodType != 'null') {
                $foods->where('food_type', $foodType);
            }
        }
        if ($request->has('food_status')) {
            $foodStatus = $request->input('food_status');
            if ($foodStatus != 'null') {
                $foods->where('status', $foodStatus);
            }
        }
        $foods = $foods->paginate(10);
        return view('admin/manage_donated', compact('foods'));

    }

    public function show_manage_transactions(Request $request)
    {
        $request->session()->forget('searchContent');
        $transaction_status = $request->input('transaction_status');
        $transactions = food_transactions::with('receiver', 'food')
            ->orderBy('created_at', 'desc');
        if ($request->has('searchContent')) {
            $searchContent = $request->input('searchContent');
            session(['searchContent' => $searchContent]);
            $transactions = $transactions->where(function ($query) use ($searchContent) {
                $query->orWhereHas('food', function ($foodQuery) use ($searchContent) {
                    $foodQuery->where('title', 'like', '%' . $searchContent . '%');
                })
                    ->orWhereHas('receiver', function ($userQuery) use ($searchContent) {
                        $userQuery->where('full_name', 'like', '%' . $searchContent . '%');
                    });
            });
        }
        if ($request->has('transaction_status') && $transaction_status != 'null') {
            $transactions = $transactions->where('status', $transaction_status);
        }

        $transactions = $transactions->paginate(10);
        return view('admin/manage_transactions', compact('transactions'));
    }
    public function show_manage_users(Request $request)
    {
        $request->session()->forget('searchContent');
        $user_role = $request->input('user_role');
        $user_status = $request->input('user_status');

        $users = Users::orderBy('created_at', 'desc');

        if ($request->has('searchContent')) {
            $searchContent_user = $request->input('searchContent');
            session(['searchContent' => $searchContent_user]);
            $users = $users->where(function ($query) use ($searchContent_user) {
                $query->where('full_name', 'like', '%' . $searchContent_user . '%')
                    ->orWhere('full_name', 'like', '%' . $searchContent_user . '%');
            });
        }

        if ($request->has('user_role') && $user_role != 'null') {
            $users = $users->where('role', $user_role);
        }

        if ($request->has('user_status') && $user_status != 'null') {
            $users = $users->where('is_verified', $user_status);
        }

        $users = $users->paginate(10);
        return view('admin/manage_users', compact('users'));
    }

    public function lock_donated($lock_id)
    {
        $food = Food::find($lock_id);
        if (!$food) {
            return response()->json(['errors' => 'Không tìm thấy thực phẩm này'], 404);
        }
        $food->status = 4;
        $food->save();
        $foodTransactions = food_transactions::where('food_id', $food->id)->get();
        foreach ($foodTransactions as $foodTransaction) {
            $foodTransaction->status = 4;
            $foodTransaction->save();
        }
        return response()->json(['message' => 'Cập nhật thành công']);
    }

    public function manage_donated_detail($food_donated_id)
    {
        $food = Food::find($food_donated_id);
        if (empty($food)) {
            return View('error404');
        } else if ($food->status == 2 || $food->status == 4) {
            return View('error404');
        } else {
            $imageUrls = ImagesFood::where('food_id', $food_donated_id)->pluck('image_url')->toArray();
            $foodData = $food->toArray();
            $user = Users::find($foodData['user_id']);
            $province = province::find($foodData['province_id']);
            $district = district::find($foodData['district_id']);
            $ward = ward::find($foodData['district_id']);
            $combinedData = array_merge($foodData, ['imageUrls' => $imageUrls]);
            $ratings = [];
            $userratings = [];
            if ($food->foodTransactions) {
                foreach ($food->foodTransactions as $transaction) {
                    $transactions[] = $transaction;
                    $transactionRatings = rate::where('food_transaction_id', $transaction->id)->first();
                    if (isset($transactionRatings)) {
                        $ratings[$transaction->id] = $transactionRatings;
                    } else {
                        $ratings[$transaction->id] = null;
                    }
                }
            }
            return view('admin/manage_donated_detail', compact('foodData', 'imageUrls', 'user', 'province', 'ward', 'district', 'ratings', 'userratings')); // Corrected variable name
        }
    }
    public function lock_user($lock_id)
    {
        $user = Users::find($lock_id);
        if (!$user) {
            return response()->json(['errors' => 'Không tìm thấy tài khoản này'], 404);
        }
        $user->is_verified = 3;
        $user->save();
        return response()->json(['message' => 'Khóa Tài Khoản Thành Công']);
    }
    public function show_role_user($user_id)
    {
        $user = Users::find($user_id);
        if (!$user) {
            return View('error404');
        }
        return view('admin/manage_user_role', compact('user'));
    }
    public function role_user($user_id, $role)
    {
        $user = Users::find($user_id);
        if (!$user) {
            return View('error404');
        } else if ($user->is_verified == 3) {
            return View('error404');
        } else if ($role != 0 && $role != 1) {
            return View('error404');
        } else {
            $user->role = $role;
            $user->save();
            return response()->json(['message' => 'Cập Nhật Quyền Thành Công']);
        }
    }
}