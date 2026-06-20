<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_customers' => $this['total_customers'] ?? 0,
            'total_institutions' => $this['total_institutions'] ?? 0,
            'total_transactions' => $this['total_transactions'] ?? 0,
            'total_discounts_given' => $this['total_discounts_given'] ?? 0,
            'total_savings' => (float) ($this['total_savings'] ?? 0),
            'total_commissions_pending' => (float) ($this['total_commissions_pending'] ?? 0),
            'total_commissions_paid' => (float) ($this['total_commissions_paid'] ?? 0),
            
            'recent_customers' => CustomerResource::collection($this['recent_customers'] ?? []),
            'recent_institutions' => InstitutionResource::collection($this['recent_institutions'] ?? []),
            'recent_transactions' => DiscountTransactionResource::collection($this['recent_transactions'] ?? []),
            
            'daily_stats' => $this['daily_stats'] ?? [],
            'weekly_stats' => $this['weekly_stats'] ?? [],
            'monthly_stats' => $this['monthly_stats'] ?? [],
            
            'top_institutions' => InstitutionResource::collection($this['top_institutions'] ?? []),
            'top_marketers' => UserResource::collection($this['top_marketers'] ?? []),
            
            'institutions_by_type' => $this['institutions_by_type'] ?? [],
            'customers_by_status' => $this['customers_by_status'] ?? [],
            
            'chart_data' => [
                'transactions' => $this['chart_data']['transactions'] ?? [],
                'savings' => $this['chart_data']['savings'] ?? [],
                'commissions' => $this['chart_data']['commissions'] ?? [],
            ]
        ];
    }
}