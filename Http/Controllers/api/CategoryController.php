<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\categories;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request){
        $categories = categories::all();
        return response()->json($categories);
    }
}
