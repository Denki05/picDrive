<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        \Log::info("=== [TokenAuth Middleware] ===");
    
        $requestToken = $request->header('Authorization') ?? $request->bearerToken();
        \Log::info("Token diterima dari request: " . $requestToken);
    
        $expectedToken = env('DRIVE_API_TOKEN');
        \Log::info("Token yang diharapkan dari .env: " . $expectedToken);
    
        if ($requestToken !== $expectedToken) {
            \Log::warning('Token tidak valid atau tidak dikirim');
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    
        return $next($request);
    }

}