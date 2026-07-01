<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GovernorateController extends Controller
{
    /**
     * Display a listing of governorates.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Governorate::query();
            
            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            
            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('name_ar', 'LIKE', "%{$search}%");
                });
            }
            
            $governorates = $query->orderBy('name')->get();
            
            return response()->json([
                'success' => true,
                'data' => $governorates,
                'message' => 'Governorates retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in GovernorateController@index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load governorates: ' . $e->getMessage(),
                'debug' => env('APP_DEBUG', false) ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }

    /**
     * Store a newly created governorate.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Creating new governorate', ['data' => $request->all()]);
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:governorates,name',
                'name_ar' => 'nullable|string|max:255|unique:governorates,name_ar',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);
            
            if ($validator->fails()) {
                Log::warning('Governorate validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            $governorate = Governorate::create($request->all());
            
            Log::info('Governorate created successfully', ['id' => $governorate->id]);
            
            return response()->json([
                'success' => true,
                'data' => $governorate,
                'message' => 'Governorate created successfully'
            ], 201);
            
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in GovernorateController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'debug' => env('APP_DEBUG', false) ? [
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings()
                ] : null
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error in GovernorateController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create governorate: ' . $e->getMessage(),
                'debug' => env('APP_DEBUG', false) ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], 500);
        }
    }

    /**
     * Display the specified governorate.
     */
    public function show($id): JsonResponse
    {
        try {
            $governorate = Governorate::with('districts')->find($id);
            
            if (!$governorate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Governorate not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $governorate,
                'message' => 'Governorate retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in GovernorateController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load governorate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified governorate.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $governorate = Governorate::find($id);
            
            if (!$governorate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Governorate not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('governorates')->ignore($id)
                ],
                'name_ar' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('governorates')->ignore($id)
                ],
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            $governorate->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $governorate,
                'message' => 'Governorate updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in GovernorateController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update governorate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified governorate.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $governorate = Governorate::find($id);
            
            if (!$governorate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Governorate not found'
                ], 404);
            }
            
            // Check if governorate has related institutions
            if ($governorate->institutions()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete governorate because it has related institutions'
                ], 400);
            }
            
            $governorate->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Governorate deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in GovernorateController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete governorate: ' . $e->getMessage()
            ], 500);
        }
    }
}