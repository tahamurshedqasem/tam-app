<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstitutionType\InstitutionTypeRequest;
use App\Http\Resources\InstitutionTypeResource;
use App\Models\InstitutionType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InstitutionTypeController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
        // $this->middleware('role:admin')->except(['index', 'show']);
    }

    /**
     * @OA\Get(
     *     path="/api/institutions/types",
     *     summary="قائمة أنواع المؤسسات",
     *     tags={"Institution Types"},
     *     @OA\Response(
     *         response=200,
     *         description="تم جلب البيانات بنجاح"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $onlyActive = $request->get('only_active', false);
        
        $query = InstitutionType::query();
        
        if ($onlyActive) {
            $query->where('is_active', true);
        }
        
        $types = $query->orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'data' => InstitutionTypeResource::collection($types)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/institution-types",
     *     summary="إنشاء نوع مؤسسة جديد",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(InstitutionTypeRequest $request): JsonResponse
    {
        $type = InstitutionType::create($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء نوع المؤسسة بنجاح',
            'data' => new InstitutionTypeResource($type)
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/institutions/types/{id}",
     *     summary="عرض نوع مؤسسة",
     *     tags={"Institution Types"}
     * )
     */
    public function show($id): JsonResponse
    {
        $type = InstitutionType::withCount('institutions')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => new InstitutionTypeResource($type)
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/institution-types/{id}",
     *     summary="تحديث نوع مؤسسة",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(InstitutionTypeRequest $request, $id): JsonResponse
    {
        $type = InstitutionType::findOrFail($id);
        $type->update($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث نوع المؤسسة بنجاح',
            'data' => new InstitutionTypeResource($type)
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/institution-types/{id}",
     *     summary="حذف نوع مؤسسة",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id): JsonResponse
    {
        $type = InstitutionType::findOrFail($id);
        
        // التحقق من وجود مؤسسات مرتبطة بهذا النوع
        if ($type->institutions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف هذا النوع لأنه مرتبط بمؤسسات موجودة',
                'data' => [
                    'institutions_count' => $type->institutions()->count()
                ]
            ], 422);
        }
        
        $type->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'تم حذف نوع المؤسسة بنجاح'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/institution-types/{id}/toggle",
     *     summary="تفعيل/تعطيل نوع مؤسسة",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function toggleStatus($id): JsonResponse
    {
        $type = InstitutionType::findOrFail($id);
        $type->is_active = !$type->is_active;
        $type->save();
        
        return response()->json([
            'success' => true,
            'message' => $type->is_active ? 'تم تفعيل نوع المؤسسة بنجاح' : 'تم تعطيل نوع المؤسسة بنجاح',
            'data' => [
                'is_active' => $type->is_active
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/institution-types/statistics",
     *     summary="إحصائيات أنواع المؤسسات",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function statistics(): JsonResponse
    {
        $totalTypes = InstitutionType::count();
        $activeTypes = InstitutionType::where('is_active', true)->count();
        $inactiveTypes = InstitutionType::where('is_active', false)->count();
        
        $typesWithCounts = InstitutionType::withCount('institutions')
            ->orderBy('institutions_count', 'desc')
            ->get();
        
        $totalInstitutions = $typesWithCounts->sum('institutions_count');
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_types' => $totalTypes,
                'active_types' => $activeTypes,
                'inactive_types' => $inactiveTypes,
                'total_institutions' => $totalInstitutions,
                'average_institutions_per_type' => $totalTypes > 0 ? round($totalInstitutions / $totalTypes, 2) : 0,
                'types_with_counts' => InstitutionTypeResource::collection($typesWithCounts)
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/institution-types/bulk-delete",
     *     summary="حذف متعدد لأنواع المؤسسات",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:institution_types,id'
        ]);
        
        $ids = $request->ids;
        
        // التحقق من وجود مؤسسات مرتبطة
        $typesWithInstitutions = InstitutionType::whereIn('id', $ids)
            ->has('institutions')
            ->pluck('id');
        
        if ($typesWithInstitutions->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف بعض الأنواع因为它们 مرتبطة بمؤسسات',
                'data' => [
                    'failed_ids' => $typesWithInstitutions
                ]
            ], 422);
        }
        
        $deletedCount = InstitutionType::whereIn('id', $ids)->delete();
        
        return response()->json([
            'success' => true,
            'message' => "تم حذف {$deletedCount} نوع مؤسسة بنجاح",
            'data' => [
                'deleted_count' => $deletedCount
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/institution-types/bulk-activate",
     *     summary="تفعيل متعدد لأنواع المؤسسات",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function bulkActivate(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:institution_types,id'
        ]);
        
        $updatedCount = InstitutionType::whereIn('id', $request->ids)
            ->update(['is_active' => true]);
        
        return response()->json([
            'success' => true,
            'message' => "تم تفعيل {$updatedCount} نوع مؤسسة بنجاح",
            'data' => [
                'activated_count' => $updatedCount
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/institution-types/bulk-deactivate",
     *     summary="تعطيل متعدد لأنواع المؤسسات",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function bulkDeactivate(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:institution_types,id'
        ]);
        
        $updatedCount = InstitutionType::whereIn('id', $request->ids)
            ->update(['is_active' => false]);
        
        return response()->json([
            'success' => true,
            'message' => "تم تعطيل {$updatedCount} نوع مؤسسة بنجاح",
            'data' => [
                'deactivated_count' => $updatedCount
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/institution-types/export",
     *     summary="تصدير أنواع المؤسسات",
     *     tags={"Institution Types"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function export(): JsonResponse
    {
        $types = InstitutionType::withCount('institutions')->get();
        
        $exportData = $types->map(function ($type) {
            return [
                'id' => $type->id,
                'name' => $type->name,
                'name_ar' => $type->name_ar,
                'is_active' => $type->is_active ? 'نشط' : 'غير نشط',
                'institutions_count' => $type->institutions_count,
                'created_at' => $type->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $type->updated_at->format('Y-m-d H:i:s')
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $exportData,
            'total' => $exportData->count()
        ]);
    }
}