<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discount_percentage' => (float) $this->discount_percentage,
            'original_amount' => (float) $this->original_amount,
            'discounted_amount' => (float) $this->discounted_amount,
            'amount_saved' => (float) $this->amount_saved,
            'savings_percentage' => (float) $this->savings_percentage,
            'transaction_date' => $this->transaction_date?->format('Y-m-d H:i:s'),
            'transaction_date_formatted' => $this->transaction_date?->format('d/m/Y h:i A'),
            'notes' => $this->notes,
            'verification_method' => $this->verification_method,
            'verification_method_name' => $this->getVerificationMethodName(),
            'transaction_receipt' => $this->receipt_url,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'institution' => new InstitutionResource($this->whenLoaded('institution')),
            'institution_owner' => new UserResource($this->whenLoaded('institutionOwner')),
            'commission' => new CommissionResource($this->whenLoaded('commission')),
            
            // Customer Info
            'customer_name' => $this->customer_name,
            'customer_membership' => $this->customer?->membership_number,
            'institution_name' => $this->institution_name,
        ];
    }

    private function getVerificationMethodName(): string
    {
        $methods = [
            'qr' => 'QR Code',
            'manual' => 'إدخال يدوي'
        ];
        
        return $methods[$this->verification_method] ?? $this->verification_method;
    }
}