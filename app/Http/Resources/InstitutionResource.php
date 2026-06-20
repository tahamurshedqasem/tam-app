<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstitutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type_id' => $this->type_id,
            'type' => $this->type ? [
                'id' => $this->type->id,
                'name' => $this->type->name,
                'name_ar' => $this->type->name_ar,
            ] : null,
            'type_name' => $this->type?->name_ar ?? $this->type?->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'discount_percentage' => $this->discount_percentage,
            'contract_file' => $this->contract_file,
            'agreement_date' => $this->agreement_date,
            'agreement_expiry_date' => $this->agreement_expiry_date,
            'status' => $this->status,
            'description' => $this->description,
            'business_hours' => $this->business_hours,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}