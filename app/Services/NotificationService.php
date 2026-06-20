<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Customer;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * ✅ الحصول على إشعارات المستخدم
     */
    public function getUserNotifications(int $userId, int $perPage = 15)
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * ✅ إنشاء إشعار لمستخدم معين
     */
    public function createNotification(int $userId, string $title, string $body, string $type = 'info', ?array $data = null): Notification
    {
        return DB::transaction(function () use ($userId, $title, $body, $type, $data) {
            return Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'data' => $data,
                'is_read' => false,
                'read_at' => null,
            ]);
        });
    }

    /**
     * ✅ إرسال إشعار لجميع المستخدمين النشطين
     */
    public function sendToAllUsers(string $title, string $body, string $type = 'info', ?array $data = null): void
    {
        $users = User::where('status', 'active')->get();
        
        foreach ($users as $user) {
            $this->createNotification($user->id, $title, $body, $type, $data);
        }
    }

    /**
     * ✅ إرسال إشعار لدور معين
     */
    public function sendToRole(string $role, string $title, string $body, string $type = 'info', ?array $data = null): void
    {
        $users = User::where('role', $role)->where('status', 'active')->get();
        
        foreach ($users as $user) {
            $this->createNotification($user->id, $title, $body, $type, $data);
        }
    }

    /**
     * ✅ إرسال إشعار لمستخدم واحد
     */
    public function sendToUser(int $userId, string $title, string $body, string $type = 'info', ?array $data = null): ?Notification
    {
        try {
            return $this->createNotification($userId, $title, $body, $type, $data);
        } catch (\Exception $e) {
            Log::error('Failed to send notification to user: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ إرسال إشعار لعميل معين (باستخدام customer_id)
     */
    public function sendToCustomer(int $customerId, string $title, string $body, string $type = 'info', ?array $data = null): ?Notification
    {
        try {
            $customer = Customer::find($customerId);
            if (!$customer || !$customer->user_id) {
                Log::warning('Customer not found or has no user_id', ['customer_id' => $customerId]);
                return null;
            }
            return $this->createNotification($customer->user_id, $title, $body, $type, $data);
        } catch (\Exception $e) {
            Log::error('Failed to send notification to customer: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ إشعار عند إضافة مؤسسة جديدة
     */
    public function notifyNewInstitution(Institution $institution): void
    {
        $title = '🏢 مؤسسة جديدة';
        $body = "تم إضافة مؤسسة جديدة: {$institution->name} في شبكة تام. استمتع بخصم {$institution->discount_percentage}% على خدماتها.";
        $type = 'institution';
        $data = [
            'institution_id' => $institution->id,
            'institution_name' => $institution->name,
            'discount_percentage' => $institution->discount_percentage,
            'type' => 'new_institution',
        ];

        // إرسال لجميع العملاء
        $this->sendToRole('customer', $title, $body, $type, $data);
        
        Log::info('New institution notification sent', [
            'institution_id' => $institution->id,
            'institution_name' => $institution->name
        ]);
    }

    /**
     * ✅ إشعار عند التحقق من رقم الهاتف
     */
    public function notifyPhoneVerified(Customer $customer): void
    {
        $title = '✅ تم التحقق من رقم الهاتف';
        $body = "مرحباً {$customer->full_name}، تم التحقق من رقم هاتفك بنجاح. يمكنك الآن استخدام جميع خدمات تام.";
        $type = 'verification';
        $data = [
            'customer_id' => $customer->id,
            'membership_number' => $customer->membership_number,
            'type' => 'phone_verified',
        ];

        $this->sendToCustomer($customer->id, $title, $body, $type, $data);
        
        Log::info('Phone verification notification sent', [
            'customer_id' => $customer->id,
            'membership_number' => $customer->membership_number
        ]);
    }

    /**
     * ✅ إشعار عند تسجيل البصمة
     */
    public function notifyFingerprintRegistered(Customer $customer): void
    {
        $title = '👆 تم تسجيل البصمة';
        $body = "مرحباً {$customer->full_name}، تم تسجيل بصمة إصبعك بنجاح. يمكنك الآن استخدام البصمة لتسجيل الدخول بسرعة.";
        $type = 'verification';
        $data = [
            'customer_id' => $customer->id,
            'membership_number' => $customer->membership_number,
            'type' => 'fingerprint_registered',
        ];

        $this->sendToCustomer($customer->id, $title, $body, $type, $data);
        
        Log::info('Fingerprint registration notification sent', [
            'customer_id' => $customer->id,
            'membership_number' => $customer->membership_number
        ]);
    }

    /**
     * ✅ إشعار عند التحقق من الباركود (QR Code)
     */
    public function notifyQRCodeVerified(Customer $customer, Institution $institution): void
    {
        $title = '📱 تم التحقق من الباركود';
        $body = "مرحباً {$customer->full_name}، تم التحقق من باركودك بنجاح في {$institution->name}. استمتع بخصم {$institution->discount_percentage}%.";
        $type = 'verification';
        $data = [
            'customer_id' => $customer->id,
            'institution_id' => $institution->id,
            'institution_name' => $institution->name,
            'membership_number' => $customer->membership_number,
            'type' => 'qr_verified',
        ];

        $this->sendToCustomer($customer->id, $title, $body, $type, $data);
        
        Log::info('QR code verification notification sent', [
            'customer_id' => $customer->id,
            'institution_id' => $institution->id,
            'membership_number' => $customer->membership_number
        ]);
    }

    /**
     * ✅ إشعار عند التحقق من العضوية
     */
    public function notifyMembershipVerified(Customer $customer, Institution $institution): void
    {
        $title = '✅ تم التحقق من العضوية';
        $body = "مرحباً {$customer->full_name}، تم التحقق من عضويتك في {$institution->name}. العضوية سارية المفعول حتى {$customer->membership_expiry_date->format('d/m/Y')}.";
        $type = 'verification';
        $data = [
            'customer_id' => $customer->id,
            'institution_id' => $institution->id,
            'institution_name' => $institution->name,
            'membership_number' => $customer->membership_number,
            'expiry_date' => $customer->membership_expiry_date?->format('Y-m-d'),
            'type' => 'membership_verified',
        ];

        $this->sendToCustomer($customer->id, $title, $body, $type, $data);
        
        Log::info('Membership verification notification sent', [
            'customer_id' => $customer->id,
            'institution_id' => $institution->id,
            'membership_number' => $customer->membership_number
        ]);
    }

    /**
     * ✅ إشعار عند انتهاء العضوية قريباً
     */
    public function notifyMembershipExpiring(Customer $customer, int $daysRemaining): void
    {
        $title = '⚠️ تنبيه: انتهاء العضوية';
        $body = "مرحباً {$customer->full_name}، تنتهي عضويتك خلال $daysRemaining يوم. يرجى تجديد عضويتك للاستمرار في الاستفادة من الخصومات.";
        $type = 'warning';
        $data = [
            'customer_id' => $customer->id,
            'membership_number' => $customer->membership_number,
            'days_remaining' => $daysRemaining,
            'expiry_date' => $customer->membership_expiry_date?->format('Y-m-d'),
            'type' => 'membership_expiring',
        ];

        $this->sendToCustomer($customer->id, $title, $body, $type, $data);
        
        Log::info('Membership expiring notification sent', [
            'customer_id' => $customer->id,
            'membership_number' => $customer->membership_number,
            'days_remaining' => $daysRemaining
        ]);
    }

    /**
     * ✅ إشعار عند نجاح عملية الخصم
     */
    public function notifyDiscountApplied(Customer $customer, Institution $institution, float $discountAmount): void
    {
        $title = '💰 تم تطبيق الخصم';
        $body = "مرحباً {$customer->full_name}، تم تطبيق خصم بقيمة $discountAmount ريال في {$institution->name}. شكراً لاستخدامك بطاقة تام.";
        $type = 'offer';
        $data = [
            'customer_id' => $customer->id,
            'institution_id' => $institution->id,
            'institution_name' => $institution->name,
            'discount_amount' => $discountAmount,
            'membership_number' => $customer->membership_number,
            'type' => 'discount_applied',
        ];

        $this->sendToCustomer($customer->id, $title, $body, $type, $data);
        
        Log::info('Discount applied notification sent', [
            'customer_id' => $customer->id,
            'institution_id' => $institution->id,
            'discount_amount' => $discountAmount
        ]);
    }

    /**
     * ✅ إشعار عند إضافة عرض جديد
     */
    public function notifyNewOffer(string $title, string $description, array $data = []): void
    {
        $notificationTitle = '🎉 عرض جديد';
        $notificationBody = $title . ': ' . $description;
        $type = 'offer';
        
        $this->sendToRole('customer', $notificationTitle, $notificationBody, $type, $data);
        
        Log::info('New offer notification sent', ['offer_title' => $title]);
    }

    /**
     * ✅ تحديد إشعار كمقروء
     */
    public function markAsRead(Notification $notification): Notification
    {
        $notification->markAsRead();
        return $notification->fresh();
    }

    /**
     * ✅ تحديد جميع الإشعارات كمقروءة
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * ✅ الحصول على عدد الإشعارات غير المقروءة
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * ✅ حذف الإشعارات القديمة
     */
    public function deleteOldNotifications(int $days = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))
            ->where('is_read', true)
            ->delete();
    }
}