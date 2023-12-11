<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Users;

class checkAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $id = session('id');
        $user = Users::where('id', $id)->first();
        if ($user->role != 1) {
            return;
        }
        return $next($request);
    }
}