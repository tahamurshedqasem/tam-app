<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class LogActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->user() && $request->method() !== 'GET') {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => $request->method(),
                'module' => $request->path(),
                'description' => $request->user()->full_name . ' ' . $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }

        return $response;
    }
}