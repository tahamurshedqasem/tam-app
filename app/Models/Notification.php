<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'data',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== Relationships ====================

    /**
     * المستخدم المستلم للإشعار
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Scopes ====================

    /**
     * فلتر الإشعارات غير المقروءة
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * فلتر الإشعارات المقروءة
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * فلتر حسب النوع
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    // ==================== Accessors ====================

    /**
     * تحديد إذا كان الإشعار مقروء
     */
    public function getIsReadAttribute($value)
    {
        return (bool) $value;
    }

    // ==================== Business Methods ====================

    /**
     * تحديد الإشعار كمقروء
     */
    public function markAsRead()
    {
        $this->is_read = true;
        $this->read_at = now();
        $this->save();
        
        return $this;
    }

    /**
     * تحديد الإشعار كغير مقروء
     */
    public function markAsUnread()
    {
        $this->is_read = false;
        $this->read_at = null;
        $this->save();
        
        return $this;
    }

    /**
     * إنشاء إشعار جديد
     */
    public static function createNotification($userId, $title, $body, $type = 'info', $data = null)
    {
        return self::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
            'is_read' => false
        ]);
    }
}