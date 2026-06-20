<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'action_text' => $this->action_text,
            'module' => $this->module,
            'description' => $this->description,
            'old_data' => $this->old_data,
            'new_data' => $this->new_data,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at_diff' => $this->created_at?->diffForHumans(),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'user_name' => $this->user_name,
        ];
    }
}