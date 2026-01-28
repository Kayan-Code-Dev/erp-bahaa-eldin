<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminResource extends JsonResource
{

    protected $guard;

    public function __construct($resource, $guard = null)
    {
        parent::__construct($resource);
        $this->guard = $guard;
    }
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        switch ($this->guard) {
            case 'admin-api':
                return [
                    'id' => $this->id ?? '',
                    'uuid' => $this->uuid ?? '',
                    'guard' => $this->guard ?? '',
                    'image' => $this->image_url ?? '',
                    'name' => trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')),
                    'email' => $this->email ?? '',
                    'phone' => $this->phone ?? '',
                    'id_number' => $this->id_number ?? '',
                    'country' => $this->city->country->name ?? '',
                    'city' => $this->city->name ?? '',
                    'last_login' => $this->last_login  ? Carbon::parse($this->last_login)->translatedFormat('g:i:s A') : '',
                    'status' => $this->status ?? '',
                    'blocked' => $this->blocked ?? false,
                    'token' => $this->token->accessToken ?? null,
                    'permissions' => $this->getAllPermissions()->pluck('name'),
                ];

            case 'branchManager-api':
                return [
                    'id' => $this->id ?? '',
                    'uuid' => $this->uuid ?? '',
                    'guard' => $this->guard ?? '',
                    'first_name' => $this->first_name ?? '',
                    'last_name' => $this->last_name ?? '',
                    'email' => $this->email ?? '',
                    'phone' => $this->phone ?? '',
                    'branch_number' => $this->branch_number ?? '',
                    'branch_name' => $this->branch_name ?? '',
                    'id_number' => $this->id_number ?? '',
                    'image' => $this->image_url ?? '',
                    'country' => $this->city->country->name ?? '',
                    'city' => $this->city->name ?? '',
                    'last_login' => $this->last_login  ? Carbon::parse($this->last_login)->translatedFormat('g:i:s A') : '',
                    'status' => $this->status ?? '',
                    'blocked' => $this->blocked ?? false,
                    'token' => $this->token->accessToken ?? null,
                    'permissions' => $this->getAllPermissions()->pluck('name'),
                ];

            case 'branch-api':
                return [
                    'id' => $this->id ?? '',
                    'uuid' => $this->uuid ?? '',
                    'guard' => $this->guard ?? '',
                    'name' => $this->name ?? '',
                    'email' => $this->email ?? '',
                    'phone' => $this->phone ?? '',
                    'branchManager' => $this->manager->full_name,
                    'branchManager_id' => $this->branch_manager_id,
                    'last_login' => $this->last_login  ? Carbon::parse($this->last_login)->translatedFormat('g:i:s A') : '',
                    'status' => $this->status ?? '',
                    'blocked' => $this->blocked ?? false,
                    'token' => $this->token->accessToken ?? null,
                    'permissions' => $this->getAllPermissions()->pluck('name'),
                ];

            case 'employee-api':
                return [
                    'id' => $this->employee->id ?? '',
                    'uuid' => $this->employee->uuid ?? '',
                    'guard' => $this->guard ?? '',
                    'name' => $this->employee->full_name ?? '',
                    'email' => $this->email ?? '',
                    'username' => $this->username ?? '',
                    'mobile' => $this->mobile ?? '',
                    'department' => $this->employee->department->name ?? '',
                    'branch' => $this->employee->branch->name ?? '',
                    'last_login' => $this->last_login  ? Carbon::parse($this->last_login)->translatedFormat('g:i:s A') : '',
                    'blocked' => $this->blocked ?? false,
                    'token' => $this->token->accessToken ?? null,
                    'permissions' => $this->getAllPermissions()->pluck('name'),
                ];

            default:
                return [
                    'id' => $this->id ?? '',
                    'name' => $this->first_name . ' ' . $this->last_name ?? '',
                    'role' => 'unknown',
                ];
        }
    }
}
