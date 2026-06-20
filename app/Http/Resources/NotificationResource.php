<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'type_name' => $this->getTypeName(),
            'type_color' => $this->getTypeColor(),
            'data' => $this->data,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at_diff' => $this->created_at?->diffForHumans(),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }

    private function getTypeName(): string
    {
        $types = [
            'info' => 'معلومات',
            'success' => 'نجاح',
            'warning' => 'تحذير',
            'error' => 'خطأ'
        ];
        
        return $types[$this->type] ?? $this->type;
    }

    private function getTypeColor(): string
    {
        $colors = [
            'info' => 'blue',
            'success' => 'green',
            'warning' => 'yellow',
            'error' => 'red'
        ];
        
        return $colors[$this->type] ?? 'gray';
    }
}