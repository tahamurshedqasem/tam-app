<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Institution;
use App\Models\DiscountTransaction;
use App\Models\Commission;
use App\Models\RevenueTransaction;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     * البيانات الرئيسية للوحة التحكم
     */
    public function index()
    {
        try {
            // ✅ إحصائيات عامة
            $totalCustomers = Customer::count();
            $totalInstitutions = Institution::count();
            
            // ✅ إجمالي التوفير (من عمود total في revenue_transactions)
            // total هو المجموع التراكمي لربح الشركة
            $totalSavings = (float) RevenueTransaction::where('status', 'completed')
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;
            
            // ✅ العمولات المستحقة (غير المدفوعة)
            $pendingCommissions = (float) Commission::where('status', 'pending')->sum('amount');
            
            // ✅ العمولات المدفوعة
            $paidCommissions = (float) Commission::where('status', 'paid')->sum('amount');
            
            // ✅ إجمالي العمولات
            $totalCommissions = (float) Commission::sum('amount');
            
            // ✅ إجمالي إيرادات الشركة (من revenue_transactions)
            $totalRevenue = (float) RevenueTransaction::where('status', 'completed')->sum('net_amount');
            
            // ✅ صافي أرباح الشركة (الإيرادات - العمولات المدفوعة)
            $netProfit = $totalRevenue - $paidCommissions;
            
            // ✅ حساب النسبة المئوية للعمولات من الإيرادات
            $commissionPercentage = $totalRevenue > 0 
                ? round(($totalCommissions / $totalRevenue) * 100, 1) 
                : 0;

            // ✅ حساب نسبة النمو للتوفير (من revenue_transactions)
            $lastMonth = now()->subMonth();
            
            $previousCustomers = Customer::where('created_at', '<', $lastMonth)->count();
            $customersGrowth = $previousCustomers > 0 
                ? (($totalCustomers - $previousCustomers) / $previousCustomers) * 100 
                : 0;
                
            $previousInstitutions = Institution::where('created_at', '<', $lastMonth)->count();
            $institutionsGrowth = $previousInstitutions > 0 
                ? (($totalInstitutions - $previousInstitutions) / $previousInstitutions) * 100 
                : 0;
                
            // ✅ نسبة نمو التوفير من revenue_transactions
            $previousSavings = (float) RevenueTransaction::where('created_at', '<', $lastMonth)
                ->where('status', 'completed')
                ->orderBy('id', 'desc')
                ->value('total') ?? 0;
            $savingsGrowth = $previousSavings > 0 
                ? (($totalSavings - $previousSavings) / $previousSavings) * 100 
                : 0;
                
            $previousCommissions = (float) Commission::where('created_at', '<', $lastMonth)->where('status', 'pending')->sum('amount');
            $commissionsGrowth = $previousCommissions > 0 
                ? (($pendingCommissions - $previousCommissions) / $previousCommissions) * 100 
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    // ✅ الإحصائيات الأساسية
                    'total_customers' => $totalCustomers,
                    'total_institutions' => $totalInstitutions,
                    'total_savings' => round($totalSavings, 2),
                    
                    // ✅ إحصائيات العمولات
                    'pending_commissions' => round($pendingCommissions, 2),
                    'paid_commissions' => round($paidCommissions, 2),
                    'total_commissions' => round($totalCommissions, 2),
                    'commission_percentage' => $commissionPercentage,
                    
                    // ✅ إحصائيات الإيرادات والأرباح
                    'total_revenue' => round($totalRevenue, 2),
                    'net_profit' => round($netProfit, 2),
                    
                    // ✅ نسبة النمو
                    'customers_growth' => round($customersGrowth, 1),
                    'institutions_growth' => round($institutionsGrowth, 1),
                    'savings_growth' => round($savingsGrowth, 1),
                    'commissions_growth' => round($commissionsGrowth, 1),
                    
                    // ✅ العملة
                    'currency' => 'YER',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/dashboard/recent-activities
     * أحدث الأنشطة (مترجمة باللغة العربية)
     */
    public function recentActivities(Request $request)
    {
        try {
            $limit = $request->get('limit', 5);
            
            $activities = ActivityLog::with('user')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'title' => $this->formatActivityTitle($activity),
                        'subtitle' => $this->formatActivitySubtitle($activity),
                        'description' => $this->formatFullDescription($activity),
                        'time_ago' => $activity->created_at->diffForHumans(),
                        'type' => $this->getActivityType($activity),
                        'name' => $this->getActivityName($activity),
                        'amount' => $this->getActivityAmount($activity),
                        'icon' => $this->getActivityIcon($activity->action),
                        'icon_color' => $this->getActivityIconColor($activity->action),
                        'created_at' => $activity->created_at->toISOString(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * GET /api/admin/dashboard/top-marketers
     * أفضل المسوقين
     */
    public function topMarketers(Request $request)
    {
        try {
            $limit = $request->get('limit', 3);
            
            $topMarketers = User::whereIn('role', ['customer_marketer', 'institution_marketer'])
                ->withCount(['createdCustomers', 'createdInstitutions'])
                ->withSum('commissions', 'amount')
                ->orderBy('commissions_sum_amount', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($marketer, $index) {
                    return [
                        'rank' => $index + 1,
                        'name' => $marketer->full_name,
                        'customers_count' => $marketer->created_customers_count ?? 0,
                        'institutions_count' => $marketer->created_institutions_count ?? 0,
                        'commission' => round($marketer->commissions_sum_amount ?? 0, 2),
                        'currency' => 'YER',
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $topMarketers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * GET /api/admin/dashboard/new-institutions
     * أحدث المؤسسات
     */
    public function newInstitutions(Request $request)
    {
        try {
            $limit = $request->get('limit', 4);
            
            $newInstitutions = Institution::with('type')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($institution) {
                    $typeName = $institution->type->name_ar ?? $institution->type->name;
                    return [
                        'name' => $institution->name,
                        'type' => $typeName,
                        'discount' => round($institution->discount_percentage) . '%',
                        'icon' => $this->getInstitutionIcon($institution->type->name),
                        'color' => $this->getInstitutionColor($institution->type->name),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $newInstitutions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * GET /api/admin/dashboard/monthly-stats
     * إحصائيات الشهر (للوحة التحكم)
     */
    public function monthlyStats()
    {
        try {
            $months = [];
            $customersData = [];
            $transactionsData = [];
            $savingsData = [];
            $revenueData = [];
            
            for ($i = 11; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $months[] = $month->format('M');
                
                $customersCount = Customer::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count();
                $customersData[] = $customersCount;
                
                $transactionsCount = DiscountTransaction::whereYear('transaction_date', $month->year)
                    ->whereMonth('transaction_date', $month->month)
                    ->count();
                $transactionsData[] = $transactionsCount;
                
                // ✅ التوفير من revenue_transactions
                $savings = (float) RevenueTransaction::whereYear('transaction_date', $month->year)
                    ->whereMonth('transaction_date', $month->month)
                    ->where('status', 'completed')
                    ->orderBy('id', 'desc')
                    ->value('total') ?? 0;
                $savingsData[] = round($savings / 1000, 1);
                
                $revenue = (float) RevenueTransaction::whereYear('transaction_date', $month->year)
                    ->whereMonth('transaction_date', $month->month)
                    ->where('status', 'completed')
                    ->sum('net_amount');
                $revenueData[] = round($revenue / 1000, 1);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'months' => $months,
                    'customers' => $customersData,
                    'transactions' => $transactionsData,
                    'savings' => $savingsData,
                    'revenue' => $revenueData,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'months' => [],
                    'customers' => [],
                    'transactions' => [],
                    'savings' => [],
                    'revenue' => [],
                ]
            ]);
        }
    }

    /**
     * GET /api/admin/dashboard/commission-summary
     * ملخص العمولات
     */
    public function commissionSummary()
    {
        try {
            $customerMarketerCommissions = Commission::where('role', 'customer_marketer')->sum('amount');
            $institutionMarketerCommissions = Commission::where('role', 'institution_marketer')->sum('amount');
            $totalCommissions = Commission::sum('amount');
            $pendingCommissions = Commission::where('status', 'pending')->sum('amount');
            $paidCommissions = Commission::where('status', 'paid')->sum('amount');

            // ✅ إحصائيات لكل مسوق
            $topMarketers = User::whereIn('role', ['customer_marketer', 'institution_marketer'])
                ->withSum('commissions', 'amount')
                ->orderBy('commissions_sum_amount', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($marketer) {
                    return [
                        'name' => $marketer->full_name,
                        'role' => $marketer->role === 'customer_marketer' ? 'مسوق عملاء' : 'مسوق مؤسسات',
                        'total_commission' => round($marketer->commissions_sum_amount ?? 0, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_commissions' => round($totalCommissions, 2),
                    'pending_commissions' => round($pendingCommissions, 2),
                    'paid_commissions' => round($paidCommissions, 2),
                    'customer_marketer_commissions' => round($customerMarketerCommissions, 2),
                    'institution_marketer_commissions' => round($institutionMarketerCommissions, 2),
                    'currency' => 'YER',
                    'top_marketers' => $topMarketers,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/dashboard/revenue-summary
     * ملخص الإيرادات والأرباح
     */
    public function revenueSummary()
    {
        try {
            $totalRevenue = (float) RevenueTransaction::where('status', 'completed')->sum('net_amount');
            $totalCommissions = (float) Commission::sum('amount');
            $paidCommissions = (float) Commission::where('status', 'paid')->sum('amount');
            
            $netProfit = $totalRevenue - $paidCommissions;
            
            $revenueByType = RevenueTransaction::where('status', 'completed')
                ->select('type', DB::raw('SUM(net_amount) as total'))
                ->groupBy('type')
                ->get()
                ->mapWithKeys(function ($item) {
                    $labels = [
                        'customer_registration' => 'تسجيل عملاء',
                        'institution_registration' => 'تسجيل مؤسسات',
                        'renewal' => 'تجديد',
                        'commission_payment' => 'دفع عمولات',
                    ];
                    return [$labels[$item->type] ?? $item->type => round($item->total, 2)];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'total_commissions' => round($totalCommissions, 2),
                    'paid_commissions' => round($paidCommissions, 2),
                    'net_profit' => round($netProfit, 2),
                    'currency' => 'YER',
                    'revenue_by_type' => $revenueByType,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== Helper Methods for Activity Formatting ====================

    /**
     * تنسيق عنوان النشاط بشكل مقروء بالعربية
     */
    private function formatActivityTitle($activity): string
    {
        $moduleName = $this->getModuleName($activity->module);
        $actionName = $this->getActionName($activity->action);
        
        return "$actionName $moduleName";
    }

    /**
     * تنسيق النص الفرعي للنشاط بشكل مقروء بالعربية
     */
    private function formatActivitySubtitle($activity): string
    {
        $userName = $activity->user->full_name ?? 'نظام';
        $description = $this->formatDescription($activity);
        
        return "$userName - $description";
    }

    /**
     * تنسيق الوصف الكامل للنشاط بالعربية
     */
    private function formatFullDescription($activity): string
    {
        $moduleName = $this->getModuleName($activity->module);
        $userName = $activity->user->full_name ?? 'النظام';
        
        $descriptions = [
            'create' => "قام {$userName} بإضافة {$moduleName} جديد",
            'store' => "قام {$userName} بإضافة {$moduleName} جديد",
            'update' => "قام {$userName} بتحديث بيانات {$moduleName}",
            'edit' => "قام {$userName} بتعديل بيانات {$moduleName}",
            'delete' => "قام {$userName} بحذف {$moduleName}",
            'destroy' => "قام {$userName} بحذف {$moduleName}",
            'login' => "قام {$userName} بتسجيل الدخول إلى النظام",
            'logout' => "قام {$userName} بتسجيل الخروج من النظام",
            'view' => "قام {$userName} بعرض بيانات {$moduleName}",
            'index' => "قام {$userName} بعرض قائمة {$moduleName}",
            'show' => "قام {$userName} بعرض بيانات {$moduleName}",
            'approve' => "قام {$userName} بالموافقة على {$moduleName}",
            'reject' => "قام {$userName} برفض {$moduleName}",
            'renew' => "قام {$userName} بتجديد {$moduleName}",
            'suspend' => "قام {$userName} بإيقاف {$moduleName}",
            'activate' => "قام {$userName} بتفعيل {$moduleName}",
            'export' => "قام {$userName} بتصدير بيانات {$moduleName}",
            'import' => "قام {$userName} باستيراد بيانات {$moduleName}",
        ];
        
        return $descriptions[$activity->action] ?? 
               "قام {$userName} بـ {$activity->action} في {$moduleName}";
    }

    /**
     * الحصول على نوع النشاط
     */
    private function getActivityType($activity): string
    {
        $types = [
            'customers' => 'customer_registration',
            'customer' => 'customer_registration',
            'institutions' => 'institution_registration',
            'institution' => 'institution_registration',
            'commissions' => 'commission_created',
            'commission' => 'commission_created',
        ];
        
        return $types[$activity->module] ?? 'general';
    }

    /**
     * الحصول على اسم العنصر المرتبط بالنشاط
     */
    private function getActivityName($activity): string
    {
        // محاولة استخراج الاسم من البيانات الإضافية
        if (isset($activity->metadata)) {
            $metadata = is_string($activity->metadata) 
                ? json_decode($activity->metadata, true) 
                : $activity->metadata;
            
            if (isset($metadata['name'])) {
                return $metadata['name'];
            }
            if (isset($metadata['customer_name'])) {
                return $metadata['customer_name'];
            }
            if (isset($metadata['institution_name'])) {
                return $metadata['institution_name'];
            }
        }
        
        return '';
    }

    /**
     * الحصول على المبلغ المرتبط بالنشاط
     */
    private function getActivityAmount($activity): float
    {
        if (isset($activity->metadata)) {
            $metadata = is_string($activity->metadata) 
                ? json_decode($activity->metadata, true) 
                : $activity->metadata;
            
            if (isset($metadata['amount'])) {
                return (float) $metadata['amount'];
            }
            if (isset($metadata['commission_amount'])) {
                return (float) $metadata['commission_amount'];
            }
        }
        
        return 0;
    }

    /**
     * الحصول على اسم الوحدة بالعربية
     */
    private function getModuleName(string $module): string
    {
        $modules = [
            'customers' => 'عميل',
            'customer' => 'عميل',
            'institutions' => 'مؤسسة',
            'institution' => 'مؤسسة',
            'users' => 'مستخدم',
            'user' => 'مستخدم',
            'commissions' => 'عمولة',
            'commission' => 'عمولة',
            'discounts' => 'خصم',
            'discount' => 'خصم',
            'transactions' => 'معاملة',
            'transaction' => 'معاملة',
            'auth' => 'مصادقة',
            'login' => 'دخول',
            'logout' => 'خروج',
            'customer-marketers' => 'مسوق عملاء',
            'customer-marketer' => 'مسوق عملاء',
            'institution-marketers' => 'مسوق مؤسسات',
            'institution-marketer' => 'مسوق مؤسسات',
            'divisions' => 'قسم',
            'division' => 'قسم',
            'notifications' => 'إشعار',
            'notification' => 'إشعار',
        ];
        
        return $modules[$module] ?? $module;
    }

    /**
     * الحصول على اسم الإجراء بالعربية
     */
    private function getActionName(string $action): string
    {
        $actions = [
            'create' => 'إضافة',
            'store' => 'إضافة',
            'update' => 'تحديث',
            'edit' => 'تعديل',
            'delete' => 'حذف',
            'destroy' => 'حذف',
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'view' => 'عرض',
            'index' => 'عرض',
            'show' => 'عرض',
            'approve' => 'موافقة',
            'reject' => 'رفض',
            'renew' => 'تجديد',
            'suspend' => 'إيقاف',
            'activate' => 'تفعيل',
            'export' => 'تصدير',
            'import' => 'استيراد',
        ];
        
        return $actions[$action] ?? $action;
    }

    /**
     * تنسيق الوصف بناءً على نوع العملية
     */
    private function formatDescription($activity): string
    {
        $moduleName = $this->getModuleName($activity->module);
        
        switch ($activity->action) {
            case 'create':
            case 'store':
                return "تم إضافة $moduleName جديد";
            case 'update':
            case 'edit':
                return "تم تحديث بيانات $moduleName";
            case 'delete':
            case 'destroy':
                return "تم حذف $moduleName";
            case 'login':
                return "قام بتسجيل الدخول";
            case 'logout':
                return "قام بتسجيل الخروج";
            case 'view':
            case 'index':
            case 'show':
                return "قام بعرض بيانات $moduleName";
            case 'approve':
                return "تمت الموافقة على $moduleName";
            case 'reject':
                return "تم رفض $moduleName";
            case 'renew':
                return "تم تجديد $moduleName";
            case 'suspend':
                return "تم إيقاف $moduleName";
            case 'activate':
                return "تم تفعيل $moduleName";
            default:
                return $activity->description ?? "قام بـ {$activity->action} في $moduleName";
        }
    }

    /**
     * الحصول على أيقونة النشاط
     */
    private function getActivityIcon(string $action): string
    {
        $icons = [
            'create' => 'add_circle',
            'store' => 'add_circle',
            'update' => 'edit',
            'edit' => 'edit',
            'delete' => 'delete',
            'destroy' => 'delete',
            'login' => 'login',
            'logout' => 'logout',
            'view' => 'visibility',
            'index' => 'list',
            'show' => 'visibility',
            'approve' => 'check_circle',
            'reject' => 'cancel',
            'renew' => 'autorenew',
            'suspend' => 'block',
            'activate' => 'check_circle',
            'export' => 'download',
            'import' => 'upload',
        ];
        
        return $icons[$action] ?? 'history';
    }

    /**
     * الحصول على لون الأيقونة
     */
    private function getActivityIconColor(string $action): string
    {
        $colors = [
            'create' => 'green',
            'store' => 'green',
            'update' => 'blue',
            'edit' => 'blue',
            'delete' => 'red',
            'destroy' => 'red',
            'login' => 'teal',
            'logout' => 'orange',
            'view' => 'purple',
            'index' => 'purple',
            'show' => 'purple',
            'approve' => 'green',
            'reject' => 'red',
            'renew' => 'amber',
            'suspend' => 'orange',
            'activate' => 'green',
            'export' => 'blue',
            'import' => 'teal',
        ];
        
        return $colors[$action] ?? 'grey';
    }

    // ==================== Helper Methods for Institutions ====================

    /**
     * الحصول على أيقونة المؤسسة حسب النوع
     */
    private function getInstitutionIcon(string $type): string
    {
        $icons = [
            'Hospital' => 'local_hospital',
            'Hotel' => 'hotel',
            'Restaurant' => 'restaurant',
            'Mall' => 'shopping_mall',
            'Clinic' => 'medical_services',
            'Education Center' => 'school',
            'Gym' => 'fitness_center',
            'Salon' => 'cut',
            'Car Rental' => 'directions_car',
            'Travel Agency' => 'flight',
            'Insurance' => 'verified',
        ];
        
        return $icons[$type] ?? 'business';
    }

    /**
     * الحصول على لون المؤسسة حسب النوع
     */
    private function getInstitutionColor(string $type): string
    {
        $colors = [
            'Hospital' => 'red',
            'Hotel' => 'blue',
            'Restaurant' => 'orange',
            'Mall' => 'purple',
            'Clinic' => 'teal',
            'Education Center' => 'indigo',
            'Gym' => 'green',
            'Salon' => 'pink',
            'Car Rental' => 'cyan',
            'Travel Agency' => 'amber',
            'Insurance' => 'brown',
        ];
        
        return $colors[$type] ?? 'grey';
    }
}