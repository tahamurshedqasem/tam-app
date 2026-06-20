<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        // $this->middleware('auth:sanctum');
    }

    /**
     * GET /api/notifications
     * ✅ قائمة الإشعارات مع تصفية حسب النوع
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $perPage = $request->get('per_page', 15);
            $type = $request->get('type');
            $isRead = $request->get('is_read');

            $query = Notification::where('user_id', $user->id);

            // ✅ تصفية حسب النوع (institution, verification, offer, etc.)
            if ($type) {
                $query->where('type', $type);
            }

            // ✅ تصفية حسب حالة القراءة
            if ($isRead !== null) {
                $query->where('is_read', $isRead === 'true' || $isRead === '1');
            }

            $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $this->notificationService->getUnreadCount($user->id),
                    'notifications' => NotificationResource::collection($notifications)
                ],
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'from' => $notifications->firstItem(),
                    'to' => $notifications->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Notifications index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/notifications/institution
     * ✅ جلب إشعارات المؤسسات فقط
     */
    public function institutionNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $perPage = $request->get('per_page', 15);
            $isRead = $request->get('is_read');

            $query = Notification::where('user_id', $user->id)
                ->where('type', 'institution');

            if ($isRead !== null) {
                $query->where('is_read', $isRead === 'true' || $isRead === '1');
            }

            $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Institution notifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/notifications/verification
     * ✅ جلب إشعارات التحقق فقط (الخاصة بالعميل فقط)
     */
    public function verificationNotifications(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $perPage = $request->get('per_page', 15);
            $isRead = $request->get('is_read');

            // ✅ جلب إشعارات التحقق الخاصة بالمستخدم فقط
            $query = Notification::where('user_id', $user->id)
                ->where('type', 'verification');

            if ($isRead !== null) {
                $query->where('is_read', $isRead === 'true' || $isRead === '1');
            }

            $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Verification notifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/notifications/by-type/{type}
     * ✅ جلب الإشعارات حسب النوع المحدد
     */
    public function getByType(Request $request, string $type): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            // ✅ التحقق من أن النوع مسموح به
            $allowedTypes = ['institution', 'verification', 'offer', 'warning', 'info', 'success', 'error'];
            if (!in_array($type, $allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'نوع الإشعار غير صالح'
                ], 422);
            }

            $perPage = $request->get('per_page', 15);
            $isRead = $request->get('is_read');

            $query = Notification::where('user_id', $user->id)
                ->where('type', $type);

            if ($isRead !== null) {
                $query->where('is_read', $isRead === 'true' || $isRead === '1');
            }

            $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get by type notifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/notifications/unread-count
     * ✅ عدد الإشعارات غير المقروءة
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $count = $this->notificationService->getUnreadCount($user->id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Unread count error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/notifications/unread-count/by-type/{type}
     * ✅ عدد الإشعارات غير المقروءة حسب النوع
     */
    public function unreadCountByType(Request $request, string $type): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $allowedTypes = ['institution', 'verification', 'offer', 'warning', 'info', 'success', 'error'];
            if (!in_array($type, $allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'نوع الإشعار غير صالح'
                ], 422);
            }

            $count = Notification::where('user_id', $user->id)
                ->where('type', $type)
                ->where('is_read', false)
                ->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count,
                    'type' => $type
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Unread count by type error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/notifications/user/{userId}
     * ✅ جلب إشعارات مستخدم محدد (للمدير فقط)
     */
    public function getUserNotifications(Request $request, int $userId): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user || !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بالوصول إلى إشعارات المستخدمين الآخرين'
                ], 403);
            }

            $targetUser = User::find($userId);
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 404);
            }

            $perPage = $request->get('per_page', 15);
            $type = $request->get('type');
            $isRead = $request->get('is_read');

            $query = Notification::where('user_id', $userId);

            if ($type) {
                $query->where('type', $type);
            }

            if ($isRead !== null) {
                $query->where('is_read', $isRead === 'true' || $isRead === '1');
            }

            $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => NotificationResource::collection($notifications),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get user notifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/notifications/{id}/read
     * ✅ تحديد إشعار كمقروء
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإشعار غير موجود أو غير مصرح لك بالوصول إليه'
                ], 404);
            }

            $notification = $this->notificationService->markAsRead($notification);
            
            return response()->json([
                'success' => true,
                'message' => 'تم تحديد الإشعار كمقروء',
                'data' => new NotificationResource($notification)
            ]);
        } catch (\Exception $e) {
            Log::error('Mark as read error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/notifications/read-all
     * ✅ تحديد جميع الإشعارات كمقروءة
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $count = $this->notificationService->markAllAsRead($user->id);
            
            return response()->json([
                'success' => true,
                'message' => "تم تحديد {$count} إشعار كمقروء",
                'data' => [
                    'marked_count' => $count
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Mark all as read error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/notifications/{id}
     * ✅ حذف إشعار
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $notification = Notification::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'الإشعار غير موجود أو غير مصرح لك بالوصول إليه'
                ], 404);
            }

            $notification->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الإشعار بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Delete notification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/notifications/clear-all
     * ✅ حذف جميع الإشعارات
     */
    public function clearAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            $count = Notification::where('user_id', $user->id)->delete();
            
            return response()->json([
                'success' => true,
                'message' => "تم حذف {$count} إشعار بنجاح",
                'data' => [
                    'deleted_count' => $count
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Clear all notifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/notifications/send
     * ✅ إرسال إشعار (للمدير فقط)
     */
    public function send(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            // ✅ التحقق من صلاحيات المدير
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإرسال الإشعارات'
                ], 403);
            }

            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'role' => 'nullable|string|in:admin,customer_marketer,institution_marketer,customer',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'type' => 'nullable|string|max:50',
                'data' => 'nullable|array',
            ]);

            $type = $validated['type'] ?? 'info';

            // ✅ إرسال لمستخدم محدد
            if (isset($validated['user_id'])) {
                $notification = $this->notificationService->sendToUser(
                    (int) $validated['user_id'],
                    $validated['title'],
                    $validated['body'],
                    $type,
                    $validated['data'] ?? null
                );

                return response()->json([
                    'success' => true,
                    'message' => 'تم إرسال الإشعار بنجاح',
                    'data' => $notification ? new NotificationResource($notification) : null
                ]);
            }

            // ✅ إرسال لدور محدد
            if (isset($validated['role'])) {
                $this->notificationService->sendToRole(
                    $validated['role'],
                    $validated['title'],
                    $validated['body'],
                    $type,
                    $validated['data'] ?? null
                );

                return response()->json([
                    'success' => true,
                    'message' => "تم إرسال الإشعار لجميع {$validated['role']}"
                ]);
            }

            // ✅ إرسال لجميع المستخدمين
            $this->notificationService->sendToAllUsers(
                $validated['title'],
                $validated['body'],
                $type,
                $validated['data'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الإشعار لجميع المستخدمين'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Send notification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/notifications/send-to-customer
     * ✅ إرسال إشعار لعميل محدد
     */
    public function sendToCustomer(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود'
                ], 401);
            }

            // ✅ التحقق من صلاحيات المدير أو مالك المؤسسة
            if (!$user->isAdmin() && !$user->isInstitutionOwner()) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بإرسال الإشعارات'
                ], 403);
            }

            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'type' => 'nullable|string|max:50',
                'data' => 'nullable|array',
            ]);

            $type = $validated['type'] ?? 'info';

            $notification = $this->notificationService->sendToCustomer(
                (int) $validated['customer_id'],
                $validated['title'],
                $validated['body'],
                $type,
                $validated['data'] ?? null
            );

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إرسال الإشعار'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الإشعار للعميل بنجاح',
                'data' => new NotificationResource($notification)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Send to customer error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/notifications/types
     * ✅ أنواع الإشعارات المتاحة
     */
    public function types(): JsonResponse
    {
        try {
            $types = [
                ['value' => 'info', 'label' => 'معلومات', 'color' => 'blue'],
                ['value' => 'warning', 'label' => 'تحذير', 'color' => 'orange'],
                ['value' => 'success', 'label' => 'نجاح', 'color' => 'green'],
                ['value' => 'error', 'label' => 'خطأ', 'color' => 'red'],
                ['value' => 'institution', 'label' => 'مؤسسة', 'color' => 'purple'],
                ['value' => 'offer', 'label' => 'عرض', 'color' => 'green'],
                ['value' => 'verification', 'label' => 'تحقق', 'color' => 'teal'],
            ];

            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            Log::error('Get notification types error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}