<?php

namespace App\Services\Clients;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;

class ClientService
{
    /**
     * جلب جميع العملاء مع دعم الباجينيشن
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function index(int $perPage = 10)
    {
        $clients = Client::orderByDesc('created_at')->paginate($perPage);
        $mapped = $clients->getCollection()->map(function ($client) {
            return $this->formatClient($client);
        });
        $result = [
            'data'         => $mapped,
            'current_page' => $clients->currentPage(),
            'next_page_url' => $clients->nextPageUrl(),
            'prev_page_url' => $clients->previousPageUrl(),
            'total'        => $clients->total(),
        ];
        return $result;
    }

    /**
     * جلب كل العملاء بدون تقسيم صفحات
     *
     * @return Collection
     */
    public function all(): Collection
    {
        return Client::orderByDesc('created_at')->get();
    }

    /**
     * إنشاء عميل جديد
     *
     * @param array $data
     * @return Client
     */
    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            return Client::create([
                'name'           => $data['client_name'],
                'phone_primary'  => $data['client_phone_primary'],
                'phone_secondary' => $data['client_phone_secondary'] ?? null,
                'address'        => $data['client_address'] ?? null,
                'visit_date'     => $data['visit_date'] ?? null,
                'event_date'     => $data['event_date'] ?? null,
                'source'         => $data['source'] ?? null,
                'notes'          => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * تحديث بيانات العميل
     *
     * @param Client $client
     * @param array $data
     * @return Client
     */
    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            $client->update([
                'name' => $data['name'] ?? $client->name,
                'phone_primary' => $data['phone_primary'] ?? $client->phone_primary,
                'phone_secondary' => $data['phone_secondary'] ?? $client->phone_secondary,
                'address' => $data['address'] ?? $client->address,
            ]);
            return $client;
        });
    }

    /**
     * حذف العميل
     *
     * @param Client $client
     * @return bool|null
     */
    public function delete(Client $client): ?bool
    {
        return $client->delete();
    }

    /**
     * جلب تفاصيل العميل مع تنسيق التاريخ
     *
     * @param Client $client
     * @return array
     */
    public function formatClient(Client $client): array
    {
        return [
            'uuid' => $client->uuid,
            'name' => $client->name,
            'phone_primary' => $client->phone_primary,
            'phone_secondary' => $client->phone_secondary,
            'address' => $client->address,
            'visit_date' => $client->visit_date ? Carbon::parse($client->visit_date)->format('d-m-Y') : null,
            'event_date' => $client->event_date ? Carbon::parse($client->event_date)->format('d-m-Y') : null,
            'source' => $client->source,
            'notes' => $client->notes,
        ];
    }

    public function getCurrentUser(): ?Authenticatable
    {
        return auth('branch-api')->user() ?? auth('employee-api')->user() ?? auth('admin')->user();
    }
}
