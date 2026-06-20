<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SimpleResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'message' => $this['message'] ?? '',
            'data' => $this['data'] ?? null,
        ];
    }
}