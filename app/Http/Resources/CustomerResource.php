<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'membership_number' => $this->membership_number,
          
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'identity_image' => $this->identity_image_url,
            'personal_image' => $this->personal_image_url,
            'has_fingerprint' => !is_null($this->fingerprint_data),
            'membership_expiry_date' => $this->membership_expiry_date?->format('Y-m-d'),
            'membership_status' => $this->membership_status,
            'membership_status_name' => $this->getMembershipStatusName(),
            'days_remaining' => $this->days_remaining,
            'total_discount_saved' => (float) $this->total_discount_saved,
            'total_savings' => (float) $this->total_savings,
            'total_discount_usage' => $this->total_discount_usage,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'discount_transactions' => DiscountTransactionResource::collection($this->whenLoaded('discountTransactions')),
            
            // Statistics
            'recent_transactions' => DiscountTransactionResource::collection(
                $this->whenLoaded('discountTransactions', function() {
                    return $this->discountTransactions->take(5);
                })
            ),
            'total_transactions' => $this->whenCounted('discountTransactions'),
        ];
    }

    private function getMembershipStatusName(): string
    {
        $statuses = [
            'active' => 'نشطة',
            'expired' => 'منتهية',
            'expiring_soon' => 'تنتهي قريباً'
        ];
        
        return $statuses[$this->membership_status] ?? $this->membership_status;
    }
}