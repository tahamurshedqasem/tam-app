<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutionResource extends JsonResource
{
    public function toArray($request)
    {
        // ✅ الحصول على المالك الأساسي
        $primaryOwner = $this->primaryOwner();
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type_id' => $this->type_id,
            'type_name' => $this->type->name_ar ?? $this->type->name ?? 'غير محدد',
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'discount_percentage' => (float) $this->discount_percentage,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'agreement_date' => $this->agreement_date,
            'agreement_expiry_date' => $this->agreement_expiry_date,
            'contract_file' => $this->contract_file,
            'description' => $this->description,
            'business_hours' => $this->business_hours,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            
            // =============================================
            // ✅ المحافظة (Governorate)
            // =============================================
            'governorate_id' => $this->governorate_id,
            'governorate_name' => $this->governorate_name ?? ($this->governorate?->name_ar ?? $this->governorate?->name),
            'governorate' => $this->whenLoaded('governorate', function () {
                return [
                    'id' => $this->governorate?->id,
                    'name' => $this->governorate?->name,
                    'name_ar' => $this->governorate?->name_ar,
                ];
            }),
            
            // =============================================
            // ✅ المنطقة (District)
            // =============================================
            'district_id' => $this->district_id,
            'district_name' => $this->district_name ?? ($this->district?->name_ar ?? $this->district?->name),
            'district' => $this->whenLoaded('district', function () {
                return [
                    'id' => $this->district?->id,
                    'name' => $this->district?->name,
                    'name_ar' => $this->district?->name_ar,
                ];
            }),
            
            // =============================================
            // ✅ الموقع الكامل (Full Location)
            // =============================================
            'location' => [
                'governorate' => $this->governorate_display,
                'district' => $this->district_display,
                'full_location' => $this->location_full,
            ],
            'full_location' => $this->location_full,
            'has_governorate' => $this->has_governorate,
            'has_district' => $this->has_district,
            
            // =============================================
            // ✅ المالك الأساسي
            // =============================================
            'owner' => $primaryOwner ? [
                'id' => $primaryOwner->id,
                'full_name' => $primaryOwner->full_name,
                'phone' => $primaryOwner->phone,
                'email' => $primaryOwner->email,
            ] : null,
            
            // =============================================
            // ✅ المسوق المسؤول
            // =============================================
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'full_name' => $this->createdBy->full_name,
                'phone' => $this->createdBy->phone,
            ] : null,
            
            // =============================================
            // ✅ نسخ احتياطية للـ Flutter
            // =============================================
            'owner_id' => $primaryOwner ? $primaryOwner->id : null,
            'owner_name' => $primaryOwner ? $primaryOwner->full_name : null,
            'marketer_id' => $this->createdBy ? $this->createdBy->id : null,
            'marketer_name' => $this->createdBy ? $this->createdBy->full_name : null,
            
            // =============================================
            // ✅ جميع المالكين
            // =============================================
            'owners' => $this->owners->map(function ($owner) {
                return [
                    'id' => $owner->id,
                    'full_name' => $owner->full_name,
                    'phone' => $owner->phone,
                    'is_primary' => $owner->pivot->is_primary ?? false,
                ];
            }),
            
            // =============================================
            // ✅ معلومات إضافية
            // =============================================
            'agreement_status' => $this->agreement_status,
            'is_valid' => $this->isValid(),
            'days_until_expiry' => $this->days_until_expiry,
            'contract_file_url' => $this->contract_file_url,
            'type' => $this->whenLoaded('type', function () {
                return [
                    'id' => $this->type?->id,
                    'name' => $this->type?->name,
                    'name_ar' => $this->type?->name_ar,
                ];
            }),
        ];
    }
}