<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

abstract class Controller
{
    /**
     * Transform pagination response to include current_page, total, and total_pages at root level
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param string|null $resourceClass Optional resource class to transform items
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginatedResponse($paginator, ?string $resourceClass = null)
    {
        // Transform items through resource class if provided
        $data = $resourceClass
            ? $resourceClass::collection($paginator->items())
            : $paginator->items();

        // Ensure mandatory pagination fields are at root level
        $response = [
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'total' => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];

        // Optionally include other useful fields
        $response['per_page'] = $paginator->perPage();

        return response()->json($response);
    }

    /**
     * Export collection to CSV
     *
     * @param \Illuminate\Support\Collection $collection
     * @param string $exportClass
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    protected function exportToCsv($collection, $exportClass, $filename = null)
    {
        $filename = $filename ?? 'export_' . date('Y-m-d_His') . '.csv';

        return Excel::download(new $exportClass($collection), $filename, \Maatwebsite\Excel\Excel::CSV, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
