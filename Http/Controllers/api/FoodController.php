<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\district;
use App\Models\food;
use App\Models\ImagesFood;
use App\Models\province;
use App\Models\rate;
use App\Models\Users;
use App\Models\ward;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\Validator;

class FoodController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('_limit', 8);
        $sort = $request->input('_sort_date', 'ASC');
        $page = $request->input('_page', 1);
        $categoryId = $request->input('category_id');
        $food_type = $request->input('food_type');
        $collect_type = $request->input('collect_type');
        $district_id = $request->input('district_id');
        $province_id = $request->input('province_id');
        $ward_id = $request->input('ward_id');
        $currentDateTime = Carbon::now();
        $query = food::join('province', 'food.province_id', '=', 'province.id')
            ->select('food.*', 'province.name as province_name')
            ->leftJoin('district', 'food.district_id', '=', 'district.id')
            ->select('food.*', 'province.name as province_name', 'district.name as district_name')
            ->leftJoin('ward', 'food.ward_id', '=', 'ward.id')
            ->select('food.*', 'province.name as province_name', 'district.name as district_name', 'ward.name as ward_name')
            ->where('food.quantity', '>', 0)
            ->where('food.expiry_date', '>', $currentDateTime)
            ->whereNotIn('food.status', [2, 4])
            ->with('images');
            
        if ($request->has('searchContent')) {
            $searchContent = $request->input('searchContent');
            session(['searchContent' => $searchContent]);
            $query->where(function ($q) use ($searchContent) {
                $q->where('food.title', 'like', '%' . $searchContent . '%')
                    ->orWhere('food.description', 'like', '%' . $searchContent . '%');
            });
        } 
        if ($categoryId != null) {
            $query->where('category_id', $categoryId);
        }
        if ($sort === 'ASC') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'DESC') {
            $query->orderBy('created_at', 'desc');
        }
        if ($food_type != null) {
            $query->where('food_type', $food_type);
        }
        if ($collect_type != null) {
            $query->where('collect_type', $collect_type);
        }
        if ($province_id != null) {
            $query->where('food.province_id', $province_id);
        }
        if ($district_id != null) {
            $query->where('food.district_id', $district_id);
        }
        if ($ward_id != null) {
            $query->where('food.ward_id', $ward_id);
        }

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($products);
    }
    public function getDetail($foodId)
    {
        $food = Food::find($foodId);
        if (empty($food) || $food->status == 2 || $food->status == 4) {
            return view('error404');
        } else {
            $imageUrls = ImagesFood::where('food_id', $foodId)->pluck('image_url')->toArray();
            $user = Users::find($food->user_id);
            $province = Province::find($food->province_id);
            $district = District::find($food->district_id);
            $ward = Ward::find($food->ward_id);
            $foodData = $food->toArray();
            $combinedData = array_merge($foodData, ['imageUrls' => $imageUrls, 'user' => $user, 'province' => $province, 'ward' => $ward, 'district' => $district]);
            $ratings = [];
            $userratings = [];
            if ($food->foodTransactions) {
                foreach ($food->foodTransactions as $transaction) {
                    $transactions[] = $transaction;
                    $transactionRatings = rate::where('food_transaction_id', $transaction->id)
                        ->first();
                    if (isset($transactionRatings)) {
                        $userRating = Users::find($transaction->receiver_id);
                        $ratings[$transaction->id] = ['rating' => $transactionRatings, 'user' => $userRating];
                    } else {
                        $ratings[$transaction->id] = null;
                    }
                }
            }
            return response()->json(['food' => $combinedData, 'ratings' => $ratings]);
        }
    }

    public function getProvinces(Request $request)
    {
        $categories = province::all();
        return response()->json($categories);
    }
    public function getAllDistrictOfProvinceId($provinceID)
    {
        $districts = District::where('province_id', $provinceID)->get();
        if (!$districts) {
            return response()->json(['error' => 'Không Tồn Tại Tỉnh Này'], 404);
        }
        return response()->json($districts);
    }
    public function getAllWardOfDistrictId($districtID)
    {
        $wards = ward::where('district_id', $districtID)->get();
        if (!$wards) {
            return response()->json(['error' => 'Không Tồn Tại Tỉnh Này'], 404);
        }
        return response()->json($wards);
    }
    public function getNameProvince($provinceId)
    {
        $province = province::where('id', $provinceId)->first();
        if (!$province) {
            return response()->json(['error' => 'Không Tồn Tại Tỉnh Này'], 404);
        }
        return response()->json($province);
    }
    public function getNameDistrict($districtId)
    {
        $district = district::where('id', $districtId)->first();
        if (!$district) {
            return response()->json(['error' => 'Không Tồn Tại Huyện Này'], 404);
        }
        return response()->json($district);
    }
    public function getNameWard($wardId)
    {
        $ward = ward::where('id', $wardId)->first();
        if (!$ward) {
            return response()->json(['error' => 'Không Tồn Tại Xã Này'], 404);
        }
        return response()->json($ward);
    }

    public function add_donate_food(request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:1000',
            'quantity' => 'required|integer|min:1',
            'expiry_date' => 'required|date|after_or_equal:today',
            'confirm_time' => 'required|in:30,60,90,120,150,180',
            'province_id' => 'required|exists:province,id',
            'district_id' => 'required|exists:district,id',
            'ward_id' => 'exists:ward,id',
            'location' => 'required|string|max:255',
            'contact_information' => 'required|string|max:255',
            'images_food' => 'required|array',
            'images_food.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'category_id.required' => 'Vui lòng chọn Danh mục.',
            'category_id.in' => 'Danh mục không hợp lệ.',
            'description.required' => 'Vui lòng nhập mô tả.',
            'description.max' => 'Vui lòng nhập mô tả ngắn hơn.',
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn hoặc bằng 1.',
            'expiry_date.required' => 'Vui lòng nhập thời gian hết hạn.',
            'expiry_date.date' => 'Thời gian hết hạn không hợp lệ.',
            'after_or_equal.date' => 'Thời gian hết hạn phải sau thời gian hiện tại.',
            'confirm_time.required' => 'Vui lòng nhập thời gian chấp nhận.',
            'confirm_time.in' => 'Thời gian chấp nhận không hợp lệ.',
            'province_id.required' => 'Vui lòng chọn Tỉnh/Thành Phố hợp lệ.',
            'province_id.in' => 'Tỉnh/Thành Phố không hợp lệ.',
            'district_id.required' => 'Vui lòng chọn Quận/Huyện.',
            'district_id.in' => 'Quận/Huyện không hợp lệ.',
            'ward_id.in' => 'Phường/Xã không hợp lệ.',
            'location.required' => 'Vui lòng nhập địa chỉ cụ thể.',
            'location.max' => 'Địa chỉ cụ thể không được vượt quá 255 ký tự.',
            'contact_information.required' => 'Vui lòng nhập thông tin liên hệ.',
            'contact_information.max' => 'Thông tin liên hệ không được vượt quá 255 ký tự.',
            'images_food.required' => 'Vui lòng thêm ảnh.',
            'images_food.*.image' => 'Tất cả các ảnh mô tả phải là hình ảnh.',
            'images_food.*.mimes' => 'Tất cả các ảnh mô tả phải có định dạng jpeg, png, jpg hoặc gif.',
            'images_food.*.max' => 'Tất cả các ảnh mô tả không được vượt quá 2MB.',
        ]);
        $user = auth()->user();
        if ($user) {
            $userId = $user->id;
        } else {
            $userId = null;
        }
        if ($validator->passes()) {
            $food = new Food();
            $expiryDate = Carbon::parse($request->input('expiry_date'));
            $food->user_id = $userId;
            $food->category_id = $request->input('category_id');
            $food->title = $request->input('title');
            $food->food_type = $request->input('food_type');
            $food->description = $request->input('description');
            $food->quantity = $request->input('quantity');
            $food->expiry_date = $expiryDate;
            $food->remaining_time_to_accept = $request->input('confirm_time');
            $food->province_id = $request->input('province_id');
            $food->district_id = $request->input('district_id');
            $food->ward_id = $request->input('ward_id');
            $food->location = $request->input('location');
            $food->contact_information = $request->input('contact_information');
            $slug_food = Str::slug($request->input('title')) . '_' . time();
            $food->slug = $slug_food;
            $food->save();
            if ($request->hasFile('images_food')) {
                $imageUrls = [];
                foreach ($request->file('images_food') as $image) {
                    $randomTitle = random_int(100000, 999999);
                    $file_name = Str::slug($request->input('title')) . '_' . Str::slug($randomTitle) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads/food_images'), $file_name);
                    $imageUrls[] = '../../uploads/food_images/' . $file_name;
                }
                foreach ($imageUrls as $imageUrl) {
                    ImagesFood::create([
                        'food_id' => $food->id,
                        'image_url' => $imageUrl,
                    ]);
                }
            } else {
                return response()->json(['error' => 'Vui lòng chọn ít nhất 1 ảnh'], 200);
            }
            return response()->json(['message' => 'Tặng thực phẩm thành công'], 200);
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }
    public function show_donate_list(request $request)
    {
        $user = auth()->user();
        $perPage = $request->input('_limit', 10);
        $page = $request->input('_page', 1);
        if ($user) {
            $donatedFoods = Food::where('user_id', $user->id)->with('images')->paginate($perPage, ['*'], 'page', $page);
            ;
            return response()->json(['donatedFoods' => $donatedFoods]);
        }
        return response()->json(['error' => 'Không tìm thấy tài khoản']);
    }
    public function cancel_Donate_Food(Request $request)
    {
        $food_id = $request[0];
        $food = Food::find($food_id);
        if (!$food) {
            return response()->json(['errors' => 'Không tìm thấy thực phẩm.'], 404);
        }
        $food->status = 2; //trạng thái hủy tặng thực phẩm
        $food->save();
        // Trả về phản hồi JSON thành công
        return response()->json(['message' => 'Đã dừng tặng thực phẩm thành công.']);
    }
    public function edit_donate_food(request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:food,id',
            'title' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:1000',
            'quantity' => 'required|integer|min:1',
            'expiry_date' => 'required|date|after_or_equal:today',
            'confirm_time' => 'required|in:30,60,90,120,150,180',
            'province_id' => 'required|exists:province,id',
            'district_id' => 'required|exists:district,id',
            'ward_id' => 'exists:ward,id',
            'location' => 'required|string|max:255',
            'contact_information' => 'required|string|max:255',
            'images_food' => 'array',
            'images_food.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ], [
            'title.required' => 'Vui lòng nhập tiêu đề.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'category_id.required' => 'Vui lòng chọn Danh mục.',
            'category_id.in' => 'Danh mục không hợp lệ.',
            'description.required' => 'Vui lòng nhập mô tả.',
            'description.max' => 'Vui lòng nhập mô tả ngắn hơn.',
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng phải lớn hơn hoặc bằng 1.',
            'expiry_date.required' => 'Vui lòng nhập thời gian hết hạn.',
            'expiry_date.date' => 'Thời gian hết hạn không hợp lệ.',
            'after_or_equal.date' => 'Thời gian hết hạn phải sau thời gian hiện tại.',
            'confirm_time.required' => 'Vui lòng nhập thời gian chấp nhận.',
            'confirm_time.in' => 'Thời gian chấp nhận không hợp lệ.',
            'province_id.required' => 'Vui lòng chọn Tỉnh/Thành Phố hợp lệ.',
            'province_id.in' => 'Tỉnh/Thành Phố không hợp lệ.',
            'district_id.required' => 'Vui lòng chọn Quận/Huyện.',
            'district_id.in' => 'Quận/Huyện không hợp lệ.',
            'ward_id.in' => 'Phường/Xã không hợp lệ.',
            'location.required' => 'Vui lòng nhập địa chỉ cụ thể.',
            'location.max' => 'Địa chỉ cụ thể không được vượt quá 255 ký tự.',
            'contact_information.required' => 'Vui lòng nhập thông tin liên hệ.',
            'contact_information.max' => 'Thông tin liên hệ không được vượt quá 255 ký tự.',
            'images_food.*.image' => 'Tất cả các ảnh mô tả phải là hình ảnh.',
            'images_food.*.mimes' => 'Tất cả các ảnh mô tả phải có định dạng jpeg, png, jpg hoặc gif.',
            'images_food.*.max' => 'Tất cả các ảnh mô tả không được vượt quá 2MB.',
        ]);
        $user = auth()->user();
        if ($user) {
            $userId = $user->id;
        } else {
            $userId = null;
        }
        if ($validator->passes()) {
            $food = Food::find($request->input('id'));
            $expiryDate = Carbon::parse($request->input('expiry_date'));
            $food->user_id = $userId;
            $food->category_id = $request->input('category_id');
            $food->title = $request->input('title');
            $food->food_type = $request->input('food_type');
            $food->description = $request->input('description');
            $food->quantity = $request->input('quantity');
            $food->expiry_date = $expiryDate;
            $food->remaining_time_to_accept = $request->input('confirm_time');
            $food->province_id = $request->input('province_id');
            $food->district_id = $request->input('district_id');
            $food->ward_id = $request->input('ward_id');
            $food->location = $request->input('location');
            $food->contact_information = $request->input('contact_information');
            $slug_food = Str::slug($request->input('title')) . '_' . time();
            $food->slug = $slug_food;
            $food->save();
            if ($request->hasFile('images_food') && $request->file('images_food')[0] !== null) {
                ImagesFood::where('food_id', $request->input('id'))->delete();
                $imageUrls = [];
                foreach ($request->file('images_food') as $image) {
                    $randomTitle = random_int(100000, 999999);
                    $file_name = Str::slug($request->input('title')) . '_' . Str::slug($randomTitle) . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('uploads/food_images'), $file_name);
                    $imageUrls[] = '../../uploads/food_images/' . $file_name;
                }
                foreach ($imageUrls as $imageUrl) {
                    ImagesFood::create([
                        'food_id' => $food->id,
                        'image_url' => $imageUrl,
                    ]);
                }
            }
            return response()->json(['message' => 'Sửa thực phẩm thành công'], 200);
        }
        $errors = $validator->errors()->all();
        return response()->json(['errors' => $errors], 200);
    }

}
