<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Institution;

class CheckInstitutionOwner
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $institutionId = $request->route('institution') ?? $request->institution_id;

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول'
            ], 401);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $institution = Institution::find($institutionId);
        
        if (!$institution) {
            return response()->json([
                'success' => false,
                'message' => 'المؤسسة غير موجودة'
            ], 404);
        }

        if (!$institution->hasOwner($user)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول'
            ], 403);
        }

        return $next($request);
    }
}