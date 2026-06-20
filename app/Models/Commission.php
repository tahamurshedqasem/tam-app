<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'amount',
        'commission_percentage',
        'reason',
        'transaction_id',
        'customer_id',
        'institution_id',
        'status',
        'currency',
        'service_fee',
        'customer_discount',
        'due_date',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'float',
        'commission_percentage' => 'float',
        'service_fee' => 'float',
        'customer_discount' => 'float',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * العلاقة مع المستخدم (المسوق)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع العميل
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * العلاقة مع المؤسسة
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * العلاقة مع معاملة الخصم
     */
    public function transaction()
    {
        return $this->belongsTo(DiscountTransaction::class, 'transaction_id');
    }

    /**
     * نطاق: العمولات المعلقة
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * نطاق: العمولات المدفوعة
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * نطاق: عمولات مسوق العملاء
     */
    public function scopeCustomerMarketer($query)
    {
        return $query->where('role', 'customer_marketer');
    }

    /**
     * نطاق: عمولات مسوق المؤسسات
     */
    public function scopeInstitutionMarketer($query)
    {
        return $query->where('role', 'institution_marketer');
    }

    /**
     * الحصول على اسم المسوق
     */
    public function getMarketerNameAttribute(): string
    {
        return $this->user?->full_name ?? 'غير معروف';
    }

    /**
     * التحقق من أن العمولة معلقة
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * التحقق من أن العمولة مدفوعة
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * تحديث حالة العمولة إلى مدفوعة
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * إلغاء العمولة
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }
}