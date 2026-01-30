<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchManagerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id ?? '',
            'image' => $this->image_url ?? '',
            'name' => $this->first_name . ' ' . $this->last_name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'id_number' => $this->id_number ?? '',
            'country' => $this->city->country->name ?? '',
            'city' => $this->city->name ?? '',
            'last_login' => Carbon::parse($this->last_login)->translatedFormat('g:i:s A') ?? '',
            'status' => $this->status ?? '',
            'blocked' => $this->blocked ?? false,
            'token' => $this->token->accessToken ?? null,
        ];
    }
}
