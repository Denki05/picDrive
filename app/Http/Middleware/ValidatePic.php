<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidatePic
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $allowedPics = explode(',', env('DRIVE_PICS', ''));
        $pic = $request->route('pic_name');

        if (!in_array($pic, $allowedPics)) {
            abort(403, 'PIC tidak diizinkan.');
        }

        return $next($request);
    }
}
