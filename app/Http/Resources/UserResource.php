<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'two_factor_enabled' => $this->two_factor_enabled,
            'two_factor_method' => $this->two_factor_method,
            'roles' => $this->roles->pluck('name')->values()->all(),
        ];
    }
}
