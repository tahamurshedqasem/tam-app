<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'error' => $this['error'] ?? true,
            'message' => $this['message'] ?? 'حدث خطأ ما',
            'code' => $this['code'] ?? 500,
            'errors' => $this['errors'] ?? null,
        ];
    }
}