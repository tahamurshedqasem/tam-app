<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'commission_percentage' => (float) $this->commission_percentage,
            'reason' => $this->reason,
            'role' => $this->role,
            'role_name' => $this->getRoleName(),
            'status' => $this->status,
            'status_name' => $this->status_text,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'transaction' => new DiscountTransactionResource($this->whenLoaded('transaction')),
            
            // User Info
            'user_name' => $this->user_name,
            'user_phone' => $this->user?->phone,
        ];
    }

    private function getRoleName(): string
    {
        $roles = [
            'customer_marketer' => 'مسوق عملاء',
            'institution_marketer' => 'مسوق مؤسسات'
        ];
        
        return $roles[$this->role] ?? $this->role;
    }
}