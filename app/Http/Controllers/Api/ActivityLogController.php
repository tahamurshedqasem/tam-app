<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
        // $this->middleware('role:admin');
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs",
     *     summary="قائمة سجلات النشاط",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['user_id', 'action', 'module', 'date_from', 'date_to']);
        $perPage = $request->get('per_page', 15);
        
        $query = ActivityLog::with('user');
        
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        
        if (isset($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => ActivityLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs/user/{user}",
     *     summary="سجلات نشاط مستخدم محدد",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getUserActivities(User $user, Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        
        $logs = ActivityLog::where('user_id', $user->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'phone' => $user->phone,
                    'role' => $user->role
                ],
                'activities' => ActivityLogResource::collection($logs)
            ],
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs/module/{module}",
     *     summary="سجلات نشاط وحدة محددة",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getModuleActivities($module, Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        
        $logs = ActivityLog::where('module', $module)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => ActivityLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs/actions/list",
     *     summary="قائمة الإجراءات المتاحة",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getActionsList(): JsonResponse
    {
        $actions = ActivityLog::select('action')
            ->distinct()
            ->pluck('action');
        
        return response()->json([
            'success' => true,
            'data' => $actions
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs/modules/list",
     *     summary="قائمة الوحدات المتاحة",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getModulesList(): JsonResponse
    {
        $modules = ActivityLog::select('module')
            ->distinct()
            ->pluck('module');
        
        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs/statistics",
     *     summary="إحصائيات سجلات النشاط",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30));
        $dateTo = $request->get('date_to', now());
        
        $totalLogs = ActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        
        $logsByAction = ActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('action', \DB::raw('count(*) as total'))
            ->groupBy('action')
            ->get();
        
        $logsByModule = ActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('module', \DB::raw('count(*) as total'))
            ->groupBy('module')
            ->get();
        
        $logsByDay = ActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(\DB::raw('DATE(created_at) as date'), \DB::raw('count(*) as total'))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();
        
        $topUsers = ActivityLog::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('user_id', \DB::raw('count(*) as total'))
            ->with('user')
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'total_logs' => $totalLogs,
                'by_action' => $logsByAction,
                'by_module' => $logsByModule,
                'by_day' => $logsByDay,
                'top_users' => $topUsers->map(function ($item) {
                    return [
                        'user_id' => $item->user_id,
                        'user_name' => $item->user->full_name ?? 'System',
                        'total' => $item->total
                    ];
                })
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/activity-logs/old",
     *     summary="حذف سجلات النشاط القديمة",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function deleteOldLogs(Request $request): JsonResponse
    {
        $days = $request->get('days', 90);
        
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
        
        return response()->json([
            'success' => true,
            'message' => "تم حذف {$deleted} سجل نشاط أقدم من {$days} يوم",
            'data' => [
                'deleted_count' => $deleted,
                'days' => $days
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs/{id}",
     *     summary="عرض تفاصيل سجل نشاط",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id): JsonResponse
    {
        $log = ActivityLog::with('user')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => new ActivityLogResource($log)
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/activity-logs/{id}",
     *     summary="حذف سجل نشاط",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id): JsonResponse
    {
        $log = ActivityLog::findOrFail($id);
        $log->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'تم حذف سجل النشاط بنجاح'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/activity-logs/clear-all",
     *     summary="حذف جميع سجلات النشاط",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function clearAll(): JsonResponse
    {
        $count = ActivityLog::count();
        ActivityLog::truncate();
        
        return response()->json([
            'success' => true,
            'message' => "تم حذف جميع سجلات النشاط ({$count} سجل)",
            'data' => [
                'deleted_count' => $count
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/activity-logs/export",
     *     summary="تصدير سجلات النشاط",
     *     tags={"Activity Logs"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function export(Request $request): JsonResponse
    {
        $filters = $request->only(['user_id', 'action', 'module', 'date_from', 'date_to']);
        
        $query = ActivityLog::with('user');
        
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        
        if (isset($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        
        $logs = $query->orderBy('created_at', 'desc')->get();
        
        $exportData = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'user_name' => $log->user->full_name ?? 'System',
                'action' => $log->action,
                'module' => $log->module,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->format('Y-m-d H:i:s')
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $exportData,
            'total' => $exportData->count()
        ]);
    }
}