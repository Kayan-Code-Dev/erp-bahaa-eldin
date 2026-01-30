<?php

namespace App\Services\Countries;

use App\Models\Country;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CountryService
{
    public function index($perPage = 10): array
    {
        $countries = Country::paginate($perPage);
        $mapped = $countries->getCollection()->map(function ($country) {
            return [
                'id' => $country->id ?? '',
                'name' => $country->name ?? '',
                'code' => $country->code ?? '',
                'count_cities' => $country->cities->count() ?? '',
                'currency_name' => $country->currency_name ?? '',
                'currency_symbol' => $country->currency_symbol ?? '',
                'image' =>  $country->image_url ?? '',
                'description' => $country->description ?? '',
                'active' => $country->active ?? '',
                'created_at' => $country->created_at ? $country->created_at->format('d-m-Y') : '',
            ];
        });
        return [
            'data' => $mapped,
            'current_page' => $countries->currentPage(),
            'next_page_url' => $countries->nextPageUrl(),
            'prev_page_url' => $countries->previousPageUrl(),
            'total' => $countries->total(),
        ];
    }




    public function createCountry(array $data)
    {
        return Country::create($data);
    }


    public function updateCountry(string $id, array $data)
    {
        $country = Country::find($id);
        if (!$country) {
            return null;
        }
        if (isset($data['image']) && $country->image) {
            Storage::disk('public')->delete($country->image);
        }
        $country->update($data);
        return $country;
    }

    public function deleteCountry(string $id)
    {
        $country = Country::find($id);

        if (!$country) {
            return null;
        }
        if ($country->image) {
            Storage::disk('public')->delete($country->image);
        }
        $country->cities()->delete();
        $country->delete();
        return true;
    }
}
