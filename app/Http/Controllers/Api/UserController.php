<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        // $this->middleware('auth:sanctum');
        // $this->middleware('role:admin');
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="قائمة المستخدمين",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['role', 'status', 'search']);
        $perPage = $request->get('per_page', 15);
        
        $users = $this->userService->getAllUsers($filters, $perPage);
        
        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="إنشاء مستخدم جديد",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المستخدم بنجاح',
            'data' => new UserResource($user)
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="عرض بيانات مستخدم",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load(['customer', 'institutionsOwned']))
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="تحديث بيانات مستخدم",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المستخدم بنجاح',
            'data' => new UserResource($updatedUser)
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="حذف مستخدم",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteUser($user);
        
        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستخدم بنجاح'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/activate",
     *     summary="تفعيل مستخدم",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function activate(User $user): JsonResponse
    {
        $user = $this->userService->activateUser($user);
        
        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل المستخدم بنجاح'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/deactivate",
     *     summary="تعطيل مستخدم",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function deactivate(User $user): JsonResponse
    {
        $user = $this->userService->deactivateUser($user);
        
        return response()->json([
            'success' => true,
            'message' => 'تم تعطيل المستخدم بنجاح'
        ]);
    }
}