<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidatePic
{
    public function handle(Request $request, Closure $next)
    {
        $allowedPics = explode(',', env('DRIVE_PICS', ''));
        $pic = $request->route('pic_name');
        
        \Log::debug("ValidatePic: pic_name={$pic}");

        if (!in_array($pic, $allowedPics)) {
            abort(403, 'PIC tidak diizinkan.');
        }

        return $next($request);
    }
}
