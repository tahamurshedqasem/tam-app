<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DistrictController extends Controller
{
    /**
     * Display a listing of districts.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = District::with('governorate');
            
            // Filter by governorate
            if ($request->has('governorate_id')) {
                $query->where('governorate_id', $request->governorate_id);
            }
            
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
            
            $districts = $query->orderBy('name')->get();
            
            return response()->json([
                'success' => true,
                'data' => $districts,
                'message' => 'Districts retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in DistrictController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load districts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created district.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            Log::info('Creating new district', ['data' => $request->all()]);
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'governorate_id' => 'required|exists:governorates,id',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);
            
            if ($validator->fails()) {
                Log::warning('District validation failed', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Validation failed'
                ], 422);
            }
            
            // Check if district with same name exists in this governorate
            $exists = District::where('governorate_id', $request->governorate_id)
                ->where(function($q) use ($request) {
                    $q->where('name', $request->name)
                      ->orWhere('name_ar', $request->name_ar);
                })->exists();
            
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This district already exists in this governorate'
                ], 422);
            }
            
            $district = District::create($request->all());
            
            Log::info('District created successfully', ['id' => $district->id]);
            
            return response()->json([
                'success' => true,
                'data' => $district,
                'message' => 'District created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error in DistrictController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create district: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified district.
     */
    public function show($id): JsonResponse
    {
        try {
            $district = District::with('governorate')->find($id);
            
            if (!$district) {
                return response()->json([
                    'success' => false,
                    'message' => 'District not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $district,
                'message' => 'District retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in DistrictController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load district: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified district.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $district = District::find($id);
            
            if (!$district) {
                return response()->json([
                    'success' => false,
                    'message' => 'District not found'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'governorate_id' => 'required|exists:governorates,id',
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
            
            // Check if district with same name exists in this governorate
            $exists = District::where('governorate_id', $request->governorate_id)
                ->where('id', '!=', $id)
                ->where(function($q) use ($request) {
                    $q->where('name', $request->name)
                      ->orWhere('name_ar', $request->name_ar);
                })->exists();
            
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This district already exists in this governorate'
                ], 422);
            }
            
            $district->update($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $district,
                'message' => 'District updated successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in DistrictController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update district: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified district.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $district = District::find($id);
            
            if (!$district) {
                return response()->json([
                    'success' => false,
                    'message' => 'District not found'
                ], 404);
            }
            
            // Check if district has related institutions
            if ($district->institutions()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete district because it has related institutions'
                ], 400);
            }
            
            $district->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'District deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in DistrictController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete district: ' . $e->getMessage()
            ], 500);
        }
    }
}