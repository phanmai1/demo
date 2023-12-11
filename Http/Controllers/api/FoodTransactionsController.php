<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\ImagesFood;
use Illuminate\Http\Request;
use App\Models\district;
use App\Models\Food;
use App\Models\food_transactions;
use App\Models\Location;
use App\Models\Notification;
use App\Models\province;
use App\Models\rate;
use App\Models\Users;
use App\Models\ward;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class FoodTransactionsController extends Controller
{
    public function collect_food(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'value.Quantity' => 'required|integer|min:1',
        ], [
            'value.Quantity.required' => 'Vui lòng nhập số lượng.',
            'value.Quantity.integer' => 'Số lượng phải là số nguyên.',
            'value.Quantity.min' => 'Số lượng phải lớn hơn hoặc bằng 1.',
        ]);
        $user = auth()->user();
        $user = Users::where('id', $user->id)->first();

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['errors' => $errors], 200);
        }

        $quantity = $request->value["Quantity"];
        $food = Food::findOrFail($request->value["foodId"]);
        $foodTransaction = food_transactions::where('food_id', $request->value["foodId"])
            ->where('receiver_id', $user->id)
            ->latest('created_at')
            ->first();

        if ($foodTransaction) {
            $pickupTime = $foodTransaction->created_at;
            $currentTime = now();
            $timeDiff = $currentTime->diffInHours($pickupTime);
            if ($timeDiff < 4) {
                return response()->json(['error' => 'Bạn đã nhận thực phẩm này trong vòng 4 giờ trước đó.'], 200);
            }
        }

        if ($food->quantity < $quantity) {
            return response()->json(['error' => 'Số lượng không đủ'], 200);
        }

        $foodTrans = new food_transactions([
            'food_id' => $request->value["foodId"],
            'receiver_id' => $user->id,
            'quantity_received' => $request->value["Quantity"],
        ]);

        $food->quantity -= $quantity;
        $food->status = 1;
        $foodtemp = $food;
        try {
            DB::beginTransaction();
            if ($foodTrans->save() && $food->save()) {
                DB::commit();
                $user = $food->user;
                $notification = new Notification();
                $notification->transaction_id = $foodTrans->id;
                $notification->user_id = $user->id;
                $notification->type = 0;
                $user = auth()->user();
                $notification->user_image = $user->image;
                $notification->message = $user->full_name . ' muốn nhận ' . $food->title . ' với số lượng là: ' . $quantity . '. Bạn có chấp nhận không?'; // Note the corrected message string
                $notification->save();
                return response()->json(['message' => 'Nhận Thành Công, vui lòng đợi người tặng xác nhận'], 200);
            } else {
                DB::rollback();
                return response()->json(['error' => 'Failed to update records'], 500);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Giao dịch không thành công'], 500);
        }
    }
    public function getTotalCart($userId)
    {
        $totalQuantity = food_transactions::where('receiver_id', $userId)
            ->sum('quantity_received');
        return response()->json(['total' => $totalQuantity], 201);
    }

    public function get_received_list(request $request)
    {
        $user = auth()->user();
        $user = Users::where('id', $user->id)->first();
        $perPage = $request->input('_limit', 8);
        $page = $request->input('_page', 1);
        if ($user) {
            $received_food = food_transactions::where('receiver_id', $user->id)
                ->with(['food.user', 'ratings'])
                ->with(['food.user']);
            $received_food = $received_food->paginate($perPage, ['*'], 'page', $page);
            return response()->json(['received_list' => $received_food], 201);
        }
    }
    public function cancel_received(Request $request)
    {
        $user = auth()->user();
        $foodTransaction = food_transactions::find($request[0]);
        if ($foodTransaction->receiver_id == $user->id) {
            if (!$foodTransaction) {
                return response()->json(['error' => 'Không tìm thấy giao dịch thực phẩm.'], 404);
            }
            $foodTransaction->status = 2;
            $foodTransaction->save();

            $food = $foodTransaction->food;
            $food->quantity += $foodTransaction->quantity_received;
            $food->save();
            return response()->json(['message' => 'Đã hủy nhận thực phẩm thành công.']);
        } else {
            return response()->json(['error' => 'Không có quyền hủy thực phẩm này.']);
        }
    }
    public function history_transactions()
    {
        $user = auth()->user();
        $user = Users::where('id', $user->id)->first();

        if ($user) {
            $foods = $user->foods()->has('foodTransactions')->with('foodTransactions.receiver')->get();

            foreach ($foods as $food) {
                $imageUrls = ImagesFood::where('food_id', $food->id)->pluck('image_url')->toArray();
                $food->image_urls = $imageUrls;
            }

            if (!empty($foods)) {
                return response()->json(['transactions' => $foods]);
            } else {
                return response()->json(['error' => 'Không có giao dịch nào.']);
            }
        }

        return response()->json(['error' => 'Không tồn tại người dùng.']);
    }


    public function confirm_received(Request $request)
    {
        $foodTransaction = food_transactions::find($request[0]);
        if (!$foodTransaction) {
            return response()->json(['error' => 'Không tìm thấy giao dịch'], 404);
        }
        if ($foodTransaction->status == 1) {
            return response()->json(['error' => 'Đã xác nhận giao dịch'], 400);
        }
        $currentTime = now();
        $foodTransaction->update([
            'status' => 1,
            'pickup_time' => $currentTime,
        ]);
        return response()->json(['message' => 'Xác Nhận Đã Lấy Thành Công'], 200);
    }

    public function notifi_refuse(Request $request)
    {
        $transaction = food_transactions::find($request[0]);
        $receiver_id = $transaction->receiver->id;
        $food = $transaction->food;
        $user = $food->user;
        $transaction->status = 2;
        $transaction->donor_status = 2;
        $transaction->save();
        $notification = new Notification();
        $notification->transaction_id = $transaction->id;
        $notification->type = 2;
        $notification->user_image = $user->image;
        $notification->user_id = $receiver_id;
        $notification->message = $user->full_name . ' đã từ chối tặng sản phẩm ' . $food->title . '. Vui lòng nhận thực phẩm khác bạn nhé!';
        $notification->save();
        $food = $transaction->food;
        $food->quantity += $transaction->quantity_received;
        $food->save();
        return response()->json(['message' => 'Xác nhận từ chối tặng thành công'], 200);
    }
    public function notifi_confirm(Request $request)
    {
        $transaction = food_transactions::find($request[0]);
        $food = $transaction->food;
        $user = $food->user;
        $receiver_id = $transaction->receiver->id;
        $transaction->donor_status = 1;
        $transaction->donor_confirm_time = Carbon::now('Asia/Ho_Chi_Minh');
        $transaction->save();
        $notification = new Notification();
        $notification->user_image = $user->image;
        $notification->transaction_id = $transaction->id;
        $notification->type = 1;
        $notification->user_id = $receiver_id;
        $notification->message = $user->full_name . ' đã chấp nhận bạn tới lấy ' . $food->title . '. Vui lòng kiểm tra lại thời gian cho phép nhận thực phẩm để không bỏ lỡ thực phẩm bạn nhé!';
        $notification->save();
        return response()->json(['message' => 'Xác nhận đồng ý thành công'], 200);
    }

    public function notifi_viewed(Request $request)
    {
        $notification = Notification::where('id', $request[0])->first();
        if ($notification) {
            $notification->is_read = 1;
            $notification->save();
        }
    }

    public function detail_transaction($transactionId)
    {
        $foodTransaction = food_transactions::find($transactionId);
        $user = auth()->user();
        $food = Food::find($foodTransaction->food_id);
        if (empty($food) || $food->status == 2 || $food->status == 4) {
            return view('error404');
        } else {
            $imageUrls = ImagesFood::where('food_id', $foodTransaction->food_id)->pluck('image_url')->toArray();
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
            return response()->json(['food' => $combinedData, 'ratings' => $ratings, 'transaction' => $transaction]);
        }
    }

}
