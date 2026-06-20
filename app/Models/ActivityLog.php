<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'description',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== Relationships ====================

    /**
     * المستخدم الذي قام بالنشاط
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Scopes ====================

    /**
     * فلتر حسب الوحدة
     */
    public function scopeOfModule($query, $module)
    {
        return $query->where('module', $module);
    }

    /**
     * فلتر حسب الإجراء
     */
    public function scopeOfAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * فلتر حسب المستخدم
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * فلتر في فترة زمنية
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ==================== Accessors ====================

    /**
     * اسم المستخدم الذي قام بالنشاط
     */
    public function getUserNameAttribute()
    {
        return $this->user->full_name ?? 'System';
    }

    /**
     * الإجراء كنص عربي
     */
    public function getActionTextAttribute()
    {
        $actions = [
            'create' => 'إنشاء',
            'update' => 'تحديث',
            'delete' => 'حذف',
            'view' => 'عرض',
            'login' => 'تسجيل دخول',
            'logout' => 'تسجيل خروج',
            'verify' => 'تحقق',
            'approve' => 'موافقة'
        ];
        
        return $actions[$this->action] ?? $this->action;
    }

    // ==================== Business Methods ====================

    /**
     * تسجيل نشاط جديد
     */
    public static function log($userId, $action, $module, $description, $oldData = null, $newData = null)
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}