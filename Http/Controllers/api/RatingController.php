<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\food_transactions;
use App\Models\rate;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function rating(Request $request)
    {
        $user = auth()->user();
        if ($request->rate === null) {
            return response()->json(['error' => 'Vui lòng chọn điểm đánh giá'], 200);
        }
        $validRatings = [1, 2, 3, 4, 5];
        if (!in_array($request->rate, $validRatings)) {
            return response()->json(['error' => 'Vui lòng chọn điểm đánh giá hợp lệ'], 200);
        }
        if (strlen($request->contentRating) > 1000) {
            return response()->json(['error' => 'Vui lòng nhập đánh giá ngắn hơn'], 200);
        }
        $transaction = food_transactions::find($request->transaction_id);
        if (!$transaction) {
            return response()->json(['error' => 'Giao dịch này không tồn tại'], 200);
        }
        if ($transaction->receiver_status == 1) {
            return response()->json(['error' => 'Bạn đã đánh giá'], 200);
        }
        if (!$user || $transaction->receiver_id !== $user->id) {
            return response()->json(['error' => 'Bạn không được phép đánh giá'], 200);
        }
        $rate = new rate();
        $rate->food_transaction_id = $transaction->id;
        $rate->rating = $request->rate;
        $rate->review = $request->contentRating;
        $rate->save();
        $transaction->receiver_status = 1;
        $transaction->save();
        return response()->json(['message' => "Bạn đã đánh giá thành công"]);
    }
}
