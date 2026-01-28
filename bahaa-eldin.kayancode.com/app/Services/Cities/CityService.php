<?php

namespace App\Services\Cities;

use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

class CityService
{
    public function getAllCities($perPage = 10)
    {
        $cities = City::with('country')->paginate($perPage);
        $mapped = $cities->getCollection()->map(function ($city) {
            return [
                'id' => $city->id ?? '',
                'name' => $city->name ?? '',
                'code' => $city->code ?? '',
                'country_name' => $city->country->name ?? '',
                'country_id' => $city->country_id ?? '',
                'latitude' => $city->latitude ?? '',
                'longitude' => $city->longitude ?? '',
                'active' => $city->active ?? '',
                'created_at' => $city->created_at ? $city->created_at->format('d-m-Y') : '',
            ];
        });
        return [
            'data' => $mapped,
            'current_page' => $cities->currentPage(),
            'next_page_url' => $cities->nextPageUrl(),
            'prev_page_url' => $cities->previousPageUrl(),
            'total' => $cities->total(),
        ];
    }

    public function getAllCitiesByCountry(Country $country)
    {
        $cities = City::where('country_id', '=', $country->id)->get()->map(function ($city) {
            return [
                'id' => $city->id ?? '',
                'name' => $city->name ?? '',
                'code' => $city->code ?? '',
            ];
        });
        return $cities;
    }

    public function createCity(array $data)
    {
        return City::create($data);
    }

    public function updateCity(string $id, array $data)
    {
        $city = City::find($id);
        if (!$city) return null;
        $city->update($data);
        return $city;
    }

    public function deleteCity(string $id)
    {
        $city = City::find($id);
        if (!$city) return null;
        $city->delete();
        return true;
    }
}
