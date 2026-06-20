<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Customer;

class CheckCustomerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $customerId = $request->route('customer');

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول'
            ], 401);
        }

        if ($user->isAdmin() || $user->isCustomerMarketer()) {
            return $next($request);
        }

        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'العميل غير موجود'
            ], 404);
        }

        if ($user->isCustomer() && $user->id === $customer->user_id) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'غير مصرح لك بالوصول'
        ], 403);
    }
}