<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'gross_amount',
        'total_commissions',
        'net_amount',
        'total', // ✅ إضافة العمود الجديد
        'commission_breakdown',
        'customer_id',
        'institution_id',
        'marketer_id',
        'status',
        'currency',
        'transaction_date',
        'notes',
    ];

    protected $casts = [
        'gross_amount' => 'float',
        'total_commissions' => 'float',
        'net_amount' => 'float',
        'total' => 'float', // ✅ تحويل إلى float
        'commission_breakdown' => 'array',
        'transaction_date' => 'datetime',
    ];

    // ==================== العلاقات ====================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_id');
    }

    // ==================== النطاقات ====================

    public function scopeCustomerRegistration($query)
    {
        return $query->where('type', 'customer_registration');
    }

    public function scopeInstitutionRegistration($query)
    {
        return $query->where('type', 'institution_registration');
    }

    public function scopeCommissionPayment($query)
    {
        return $query->where('type', 'commission_payment');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ==================== دوال مساعدة ====================

    /**
     * حساب صافي الإيرادات
     */
    public function calculateNetAmount(): float
    {
        return $this->gross_amount - $this->total_commissions;
    }

    /**
     * الحصول على نسبة العمولة من الإجمالي
     */
    public function getCommissionPercentageAttribute(): float
    {
        if ($this->gross_amount == 0) {
            return 0;
        }
        return round(($this->total_commissions / $this->gross_amount) * 100, 2);
    }

    /**
     * ✅ تحديث المجموع التراكمي للشركة
     */
    public static function updateTotal(): void
    {
        // حساب مجموع net_amount لجميع المعاملات المكتملة
        $total = self::where('status', 'completed')->sum('net_amount');
        
        // تحديث جميع الصفوف بالمجموع التراكمي (اختياري)
        // أو يمكن استخدام هذا فقط عند إضافة معاملة جديدة
    }

    /**
     * ✅ الحصول على مجموع ربح الشركة الحالي
     */
    public static function getCompanyTotal(): float
    {
        return self::where('status', 'completed')->sum('net_amount');
    }

    /**
     * ✅ الحصول على مجموع ربح الشركة حسب النوع
     */
    public static function getCompanyTotalByType(string $type): float
    {
        return self::where('type', $type)
            ->where('status', 'completed')
            ->sum('net_amount');
    }

    /**
     * ✅ الحصول على تقرير الأرباح
     */
    public static function getProfitReport(): array
    {
        $total = self::getCompanyTotal();
        
        $byType = [
            'customer_registration' => self::getCompanyTotalByType('customer_registration'),
            'institution_registration' => self::getCompanyTotalByType('institution_registration'),
            'renewal' => self::getCompanyTotalByType('renewal'),
            'commission_payment' => self::getCompanyTotalByType('commission_payment'),
        ];

        return [
            'total_profit' => round($total, 2),
            'by_type' => array_map(fn($value) => round($value, 2), $byType),
            'currency' => 'YER',
            'count' => self::where('status', 'completed')->count(),
        ];
    }
}