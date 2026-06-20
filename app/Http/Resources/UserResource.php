<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'role' => $this->role,
            'role_name' => $this->getRoleName(),
            'status' => $this->status,
            'status_name' => $this->getStatusName(),
            'is_verified' => $this->is_verified,
            'phone_verified_at' => $this->phone_verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'institutions_owned' => InstitutionResource::collection($this->whenLoaded('institutionsOwned')),
            'created_customers_count' => $this->whenCounted('createdCustomers'),
            'created_institutions_count' => $this->whenCounted('createdInstitutions'),
            
            // Statistics
            'total_commissions' => $this->when($this->isMarketer(), $this->total_commissions),
            'total_paid_commissions' => $this->when($this->isMarketer(), $this->paid_commissions),
            'registered_customers_count' => $this->when($this->isCustomerMarketer(), $this->registered_customers_count),
            'registered_institutions_count' => $this->when($this->isInstitutionMarketer(), $this->registered_institutions_count),
        ];
    }

    private function getRoleName(): string
    {
        $roles = [
            'admin' => 'مدير النظام',
            'customer' => 'عميل',
            'customer_marketer' => 'مسوق عملاء',
            'institution_marketer' => 'مسوق مؤسسات',
            'institution_owner' => 'مالك مؤسسة'
        ];
        
        return $roles[$this->role] ?? $this->role;
    }

    private function getStatusName(): string
    {
        $statuses = [
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'suspended' => 'موقوف'
        ];
        
        return $statuses[$this->status] ?? $this->status;
    }
}