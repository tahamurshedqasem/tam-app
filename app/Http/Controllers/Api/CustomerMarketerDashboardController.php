<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Commission;
use App\Models\RevenueTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerMarketerDashboardController extends Controller
{
    /**
     * Constructor - Apply middleware
     */
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
        // $this->middleware('check.status');
        // $this->middleware('role:customer_marketer,admin');
    }

    /**
     * GET /api/customer-marketer/dashboard/stats
     * Get dashboard statistics for customer marketer
     */
    public function dashboardStats(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // Check if user is a customer marketer
            if (!$user->isCustomerMarketer() && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. User is not a customer marketer.'
                ], 403);
            }

            // If admin, they can view all stats or specific marketer
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            // Get statistics
            $stats = $this->getMarketerStats($marketerId);

            // Get recent activities
            $recentCustomers = $this->getRecentCustomers($marketerId, 5);
            $recentCommissions = $this->getRecentCommissions($marketerId, 5);

            // Calculate growth percentages
            $customersGrowth = $this->calculateCustomersGrowth($marketerId);
            $commissionsGrowth = $this->calculateCommissionsGrowth($marketerId);
            $monthlyActivity = $this->getMonthlyActivity($marketerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_customers' => $stats['total_customers'],
                    'active_customers' => $stats['active_customers'],
                    'pending_customers' => $stats['pending_customers'],
                    'expired_customers' => $stats['expired_customers'],
                    'total_commissions' => $stats['total_commissions'],
                    'pending_commissions' => $stats['pending_commissions'],
                    'paid_commissions' => $stats['paid_commissions'],
                    'total_revenue' => $stats['total_revenue'],
                    'monthly_activity' => $monthlyActivity,
                    'customers_growth' => $customersGrowth,
                    'commissions_growth' => $commissionsGrowth,
                    'recent_customers' => $recentCustomers,
                    'recent_commissions' => $recentCommissions,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in dashboardStats: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketer/dashboard/me
     * Get current marketer profile with stats
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->isCustomerMarketer() && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. User is not a customer marketer.'
                ], 403);
            }

            // If admin and marketer_id is provided, get that marketer
            $marketerId = $user->id;
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
                $user = $marketer;
            }

            // Get marketer stats
            $stats = $this->getMarketerStats($user->id);

            // Get recent customers
            $recentCustomers = $this->getRecentCustomers($user->id, 3);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'name' => $user->full_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'status' => $user->status,
                    'region' => $user->region,
                    'role' => $user->role,
                    'customers_count' => $stats['total_customers'],
                    'active_customers' => $stats['active_customers'],
                    'total_commission' => $stats['total_commissions'],
                    'pending_commission' => $stats['pending_commissions'],
                    'paid_commission' => $stats['paid_commissions'],
                    'total_revenue' => $stats['total_revenue'],
                    'recent_customers' => $recentCustomers,
                    'created_at' => $user->created_at ? $user->created_at->toISOString() : null,
                    'updated_at' => $user->updated_at ? $user->updated_at->toISOString() : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in me: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load marketer profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketer/dashboard/customers
     * Get all customers for the current marketer
     */
    public function getCustomers(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $status = $request->get('status');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');

            $query = Customer::where('created_by_marketer', $marketerId)
                ->with(['user', 'marketer']);

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('membership_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            // Apply status filter
            if ($status && $status !== 'all') {
                $query->where(function ($q) use ($status) {
                    $q->where('membership_status', $status)
                        ->orWhere('status', $status);
                });
            }

            // Apply sorting
            if ($sortBy === 'full_name' || $sortBy === 'phone' || $sortBy === 'email') {
                $query->join('users', 'customers.user_id', '=', 'users.id')
                    ->orderBy('users.' . $sortBy, $sortDirection)
                    ->select('customers.*');
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            $customers = $query->paginate($perPage);

            // Transform the data
            $customers->getCollection()->transform(function ($customer) {
                return $this->formatCustomerData($customer);
            });

            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getCustomers: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketer/dashboard/customers/{id}
     * Get a specific customer for the current marketer
     */
    public function getCustomer($id, Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $customer = Customer::where('created_by_marketer', $marketerId)
                ->with(['user', 'marketer', 'discountTransactions'])
                ->find($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found or does not belong to this marketer'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatCustomerData($customer, true)
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getCustomer: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'customer_id' => $id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/customer-marketer/dashboard/customers/{id}
     * Update a customer for the current marketer
     */
    public function updateCustomer($id, Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $customer = Customer::where('created_by_marketer', $marketerId)
                ->with('user')
                ->find($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found or does not belong to this marketer'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'full_name' => 'sometimes|string|max:255',
                'phone' => [
                    'sometimes',
                    'string',
                    'regex:/^([0-9\s\-\+\(\)]*)$/',
                    'min:10',
                    'max:15',
                    Rule::unique('users', 'phone')->ignore($customer->user_id),
                ],
                'email' => [
                    'nullable',
                    'email',
                    Rule::unique('users', 'email')->ignore($customer->user_id),
                ],
                'address' => 'nullable|string|max:500',
                'membership_status' => 'sometimes|in:active,pending,suspended,expired',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Update user
                $userData = [];
                if ($request->has('full_name')) {
                    $userData['full_name'] = $request->full_name;
                }
                if ($request->has('phone')) {
                    $userData['phone'] = $request->phone;
                }
                if ($request->has('email')) {
                    $userData['email'] = $request->email;
                }

                if (!empty($userData)) {
                    $customer->user->update($userData);
                }

                // Update customer
                $customerData = [];
                if ($request->has('address')) {
                    $customerData['address'] = $request->address;
                }
                if ($request->has('membership_status')) {
                    $customerData['membership_status'] = $request->membership_status;
                    $customerData['status'] = $request->membership_status;
                }

                if (!empty($customerData)) {
                    $customer->update($customerData);
                }

                DB::commit();

                Log::info('Customer updated by marketer', [
                    'customer_id' => $customer->id,
                    'marketer_id' => $marketerId,
                    'updated_by' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Customer updated successfully',
                    'data' => $this->formatCustomerData($customer->fresh(['user', 'marketer']), true)
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error in updateCustomer: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'customer_id' => $id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/customer-marketer/dashboard/customers/{id}
     * Delete a customer for the current marketer
     */
    public function deleteCustomer($id, Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $customer = Customer::where('created_by_marketer', $marketerId)
                ->with('user')
                ->find($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found or does not belong to this marketer'
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Delete customer and associated user
                $userToDelete = $customer->user;
                $customer->delete();
                
                if ($userToDelete) {
                    $userToDelete->delete();
                }

                DB::commit();

                Log::info('Customer deleted by marketer', [
                    'customer_id' => $id,
                    'marketer_id' => $marketerId,
                    'deleted_by' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Customer deleted successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error in deleteCustomer: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'customer_id' => $id
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketer/dashboard/commissions
     * Get commissions for the current marketer
     */
    public function getCommissions(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            $query = Commission::where('user_id', $marketerId)
                ->where('role', 'customer_marketer')
                ->with(['customer', 'customer.user']);

            if ($status) {
                $query->where('status', $status);
            }

            if ($fromDate) {
                $query->whereDate('created_at', '>=', $fromDate);
            }

            if ($toDate) {
                $query->whereDate('created_at', '<=', $toDate);
            }

            $commissions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Transform the data
            $commissions->getCollection()->transform(function ($commission) {
                return [
                    'id' => $commission->id,
                    'amount' => (float) $commission->amount,
                    'status' => $commission->status,
                    'reason' => $commission->reason,
                    'service_fee' => (float) $commission->service_fee,
                    'currency' => $commission->currency ?? 'YER',
                    'due_date' => $commission->due_date ? $commission->due_date->toISOString() : null,
                    'paid_at' => $commission->paid_at ? $commission->paid_at->toISOString() : null,
                    'created_at' => $commission->created_at ? $commission->created_at->toISOString() : null,
                    'customer' => $commission->customer ? [
                        'id' => $commission->customer->id,
                        'full_name' => $commission->customer->user ? $commission->customer->user->full_name : 'Unknown',
                        'membership_number' => $commission->customer->membership_number,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $commissions->items(),
                'meta' => [
                    'current_page' => $commissions->currentPage(),
                    'last_page' => $commissions->lastPage(),
                    'per_page' => $commissions->perPage(),
                    'total' => $commissions->total(),
                    'from' => $commissions->firstItem(),
                    'to' => $commissions->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getCommissions: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load commissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketer/dashboard/commission-stats
     * Get commission statistics for the current marketer
     */
    public function getCommissionStats(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $stats = $this->getMarketerStats($marketerId);

            // Get monthly breakdown
            $monthlyCommissions = Commission::where('user_id', $marketerId)
                ->where('role', 'customer_marketer')
                ->where('status', 'paid')
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(amount) as total')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_commission' => $stats['total_commissions'],
                    'pending_commission' => $stats['pending_commissions'],
                    'paid_commission' => $stats['paid_commissions'],
                    'customers_count' => $stats['total_customers'],
                    'active_customers' => $stats['active_customers'],
                    'total_revenue' => $stats['total_revenue'],
                    'currency' => 'YER',
                    'monthly_breakdown' => $monthlyCommissions->map(function ($item) {
                        return [
                            'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                            'total' => (float) $item->total,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getCommissionStats: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load commission statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketer/dashboard/recent-activities
     * Get recent activities for the current marketer
     */
    public function getRecentActivities(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;
            $limit = $request->get('limit', 10);

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $recentCustomers = $this->getRecentCustomers($marketerId, $limit);
            $recentCommissions = $this->getRecentCommissions($marketerId, $limit);

            // Combine and format activities
            $activities = [];

            foreach ($recentCustomers as $customer) {
                $activities[] = [
                    'id' => $customer['id'],
                    'type' => 'customer_registered',
                    'title' => 'New Customer Registered',
                    'description' => $customer['user']['full_name'] . ' (#' . $customer['membership_number'] . ')',
                    'icon' => 'person_add',
                    'icon_color' => 'green',
                    'amount' => null,
                    'timestamp' => $customer['created_at'],
                    'data' => $customer,
                ];
            }

            foreach ($recentCommissions as $commission) {
                $customerName = $commission['customer'] ? $commission['customer']['full_name'] : 'Unknown';
                $activities[] = [
                    'id' => $commission['id'],
                    'type' => 'commission_earned',
                    'title' => 'Commission Earned',
                    'description' => $commission['amount'] . ' YER for ' . $customerName,
                    'icon' => 'attach_money',
                    'icon_color' => 'orange',
                    'amount' => $commission['amount'],
                    'timestamp' => $commission['created_at'],
                    'data' => $commission,
                ];
            }

            // Sort by timestamp (newest first)
            usort($activities, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            // Limit results
            $activities = array_slice($activities, 0, $limit);

            return response()->json([
                'success' => true,
                'data' => $activities,
                'meta' => [
                    'total' => count($activities),
                    'limit' => $limit,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getRecentActivities: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load recent activities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/customer-marketer/dashboard/summary
     * Get a summary for the marketer dashboard
     */
    public function getSummary(Request $request)
    {
        try {
            $user = $request->user();
            $marketerId = $user->id;

            // If admin and marketer_id is provided
            if ($user->isAdmin() && $request->has('marketer_id')) {
                $marketerId = $request->marketer_id;
                $marketer = User::where('role', 'customer_marketer')->find($marketerId);
                if (!$marketer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Marketer not found'
                    ], 404);
                }
            }

            $stats = $this->getMarketerStats($marketerId);
            $monthlyActivity = $this->getMonthlyActivity($marketerId);
            $customersGrowth = $this->calculateCustomersGrowth($marketerId);
            $commissionsGrowth = $this->calculateCommissionsGrowth($marketerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_customers' => $stats['total_customers'],
                    'active_customers' => $stats['active_customers'],
                    'total_commissions' => $stats['total_commissions'],
                    'pending_commissions' => $stats['pending_commissions'],
                    'paid_commissions' => $stats['paid_commissions'],
                    'total_revenue' => $stats['total_revenue'],
                    'monthly_activity' => $monthlyActivity,
                    'customers_growth' => $customersGrowth,
                    'commissions_growth' => $commissionsGrowth,
                    'currency' => 'YER',
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getSummary: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load summary: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // Private Helper Methods
    // ============================================================

    /**
     * Get marketer statistics
     */
    private function getMarketerStats(int $marketerId): array
    {
        $totalCustomers = Customer::where('created_by_marketer', $marketerId)->count();
        $activeCustomers = Customer::where('created_by_marketer', $marketerId)
            ->where(function ($q) {
                $q->where('membership_status', 'active')
                    ->orWhere('status', 'active');
            })->count();
        $pendingCustomers = Customer::where('created_by_marketer', $marketerId)
            ->where(function ($q) {
                $q->where('membership_status', 'pending')
                    ->orWhere('status', 'pending');
            })->count();
        $expiredCustomers = Customer::where('created_by_marketer', $marketerId)
            ->where(function ($q) {
                $q->where('membership_status', 'expired')
                    ->orWhere('status', 'expired');
            })->count();

        $totalCommissions = Commission::where('user_id', $marketerId)
            ->where('role', 'customer_marketer')
            ->where('status', 'paid')
            ->sum('amount');

        $pendingCommissions = Commission::where('user_id', $marketerId)
            ->where('role', 'customer_marketer')
            ->where('status', 'pending')
            ->sum('amount');

        $paidCommissions = Commission::where('user_id', $marketerId)
            ->where('role', 'customer_marketer')
            ->where('status', 'paid')
            ->sum('amount');

        // Get total revenue from revenue transactions
        $totalRevenue = RevenueTransaction::where('marketer_id', $marketerId)
            ->where('status', 'completed')
            ->sum('net_amount');

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'pending_customers' => $pendingCustomers,
            'expired_customers' => $expiredCustomers,
            'total_commissions' => (float) $totalCommissions,
            'pending_commissions' => (float) $pendingCommissions,
            'paid_commissions' => (float) $paidCommissions,
            'total_revenue' => (float) $totalRevenue,
        ];
    }

    /**
     * Get recent customers
     */
    private function getRecentCustomers(int $marketerId, int $limit): array
    {
        return Customer::where('created_by_marketer', $marketerId)
            ->with(['user', 'marketer'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($customer) {
                return $this->formatCustomerData($customer);
            })
            ->toArray();
    }

    /**
     * Get recent commissions
     */
    private function getRecentCommissions(int $marketerId, int $limit): array
    {
        return Commission::where('user_id', $marketerId)
            ->where('role', 'customer_marketer')
            ->with(['customer', 'customer.user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'amount' => (float) $commission->amount,
                    'status' => $commission->status,
                    'reason' => $commission->reason,
                    'created_at' => $commission->created_at ? $commission->created_at->toISOString() : null,
                    'customer' => $commission->customer ? [
                        'id' => $commission->customer->id,
                        'full_name' => $commission->customer->user ? $commission->customer->user->full_name : 'Unknown',
                        'membership_number' => $commission->customer->membership_number,
                    ] : null,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate customers growth percentage
     */
    private function calculateCustomersGrowth(int $marketerId): float
    {
        $currentMonth = Customer::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->month)
            ->count();

        $previousMonth = Customer::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->count();

        if ($previousMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
    }

    /**
     * Calculate commissions growth percentage
     */
    private function calculateCommissionsGrowth(int $marketerId): float
    {
        $currentMonth = Commission::where('user_id', $marketerId)
            ->where('role', 'customer_marketer')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $previousMonth = Commission::where('user_id', $marketerId)
            ->where('role', 'customer_marketer')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');

        if ($previousMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
    }

    /**
     * Get monthly activity count
     */
    private function getMonthlyActivity(int $marketerId): int
    {
        return Customer::where('created_by_marketer', $marketerId)
            ->whereMonth('created_at', now()->month)
            ->count();
    }

    /**
     * Format customer data for API response
     */
    private function formatCustomerData($customer, bool $detailed = false): array
    {
        $data = [
            'id' => $customer->id,
            'membership_number' => $customer->membership_number,
            'address' => $customer->address,
            'membership_status' => $customer->membership_status,
            'status' => $customer->status,
            'total_discount_saved' => (float) $customer->total_discount_saved,
            'membership_expiry_date' => $customer->membership_expiry_date ? $customer->membership_expiry_date->toISOString() : null,
            'created_at' => $customer->created_at ? $customer->created_at->toISOString() : null,
            'updated_at' => $customer->updated_at ? $customer->updated_at->toISOString() : null,
            'user' => $customer->user ? [
                'id' => $customer->user->id,
                'full_name' => $customer->user->full_name,
                'phone' => $customer->user->phone,
                'email' => $customer->user->email,
                'role' => $customer->user->role,
                'status' => $customer->user->status,
            ] : null,
        ];

        if ($detailed) {
            $data['marketer'] = $customer->marketer ? [
                'id' => $customer->marketer->id,
                'full_name' => $customer->marketer->full_name,
                'phone' => $customer->marketer->phone,
            ] : null;

            $data['transactions_count'] = $customer->discountTransactions ? $customer->discountTransactions->count() : 0;
            $data['recent_transactions'] = $customer->discountTransactions ?
                $customer->discountTransactions
                    ->take(5)
                    ->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'amount' => (float) $transaction->amount,
                            'amount_saved' => (float) $transaction->amount_saved,
                            'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->toISOString() : null,
                            'institution_name' => $transaction->institution ? $transaction->institution->name : 'Unknown',
                        ];
                    }) : [];
        }

        return $data;
    }
}