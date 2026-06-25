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
            
            // ✅ المالك الأساسي
            'owner' => $primaryOwner ? [
                'id' => $primaryOwner->id,
                'full_name' => $primaryOwner->full_name,
                'phone' => $primaryOwner->phone,
                'email' => $primaryOwner->email,
            ] : null,
            
            // ✅ المسوق المسؤول
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'full_name' => $this->createdBy->full_name,
                'phone' => $this->createdBy->phone,
            ] : null,
            
            // ✅ نسخ احتياطية للـ Flutter
            'owner_id' => $primaryOwner ? $primaryOwner->id : null,
            'owner_name' => $primaryOwner ? $primaryOwner->full_name : null,
            'marketer_id' => $this->createdBy ? $this->createdBy->id : null,
            'marketer_name' => $this->createdBy ? $this->createdBy->full_name : null,
            
            // ✅ جميع المالكين
            'owners' => $this->owners->map(function ($owner) {
                return [
                    'id' => $owner->id,
                    'full_name' => $owner->full_name,
                    'phone' => $owner->phone,
                    'is_primary' => $owner->pivot->is_primary ?? false,
                ];
            }),
        ];
    }
}