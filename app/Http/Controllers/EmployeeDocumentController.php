<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Employee Documents",
 *     description="Employee document management"
 * )
 */
class EmployeeDocumentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/employee-documents",
     *     summary="List all employee documents",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_verified", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="expiring_soon", in="query", description="Get documents expiring within 30 days", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="List of documents"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = EmployeeDocument::with(['employee.user', 'uploadedBy', 'verifiedBy']);

        if ($request->has('employee_id')) {
            $query->forEmployee($request->employee_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('is_verified')) {
            $verified = filter_var($request->is_verified, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_verified', $verified);
        }

        if ($request->boolean('expiring_soon')) {
            $query->expiringSoon();
        }

        $documents = $query->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($documents);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employee-documents",
     *     summary="Upload document for employee",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"employee_id", "type", "title", "file"},
     *                 @OA\Property(property="employee_id", type="integer"),
     *                 @OA\Property(property="type", type="string", enum={"national_id", "passport", "contract", "certificate", "resume", "photo", "other"}),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="file", type="string", format="binary"),
     *                 @OA\Property(property="issue_date", type="string", format="date"),
     *                 @OA\Property(property="expiry_date", type="string", format="date"),
     *                 @OA\Property(property="document_number", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Document uploaded"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:' . implode(',', array_keys(EmployeeDocument::TYPES)),
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'document_number' => 'nullable|string|max:50',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $file = $request->file('file');

        // Store file
        $storagePath = EmployeeDocument::getStoragePath($employee->id);
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs($storagePath, $fileName);

        $document = EmployeeDocument::create([
            'employee_id' => $validated['employee_id'],
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'document_number' => $validated['document_number'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        ActivityLog::logCreated($document);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $document->load(['employee.user', 'uploadedBy']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-documents/{id}",
     *     summary="Get document details",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document details"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $document = EmployeeDocument::with(['employee.user', 'uploadedBy', 'verifiedBy'])->findOrFail($id);
        return response()->json($document);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/employee-documents/{id}",
     *     summary="Update document metadata",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="issue_date", type="string", format="date"),
     *             @OA\Property(property="expiry_date", type="string", format="date"),
     *             @OA\Property(property="document_number", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Document updated"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $document = EmployeeDocument::findOrFail($id);
        $oldValues = $document->toArray();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'document_number' => 'nullable|string|max:50',
        ]);

        $document->update(array_filter($validated, fn($v) => $v !== null));

        ActivityLog::logUpdated($document, $oldValues);

        return response()->json([
            'message' => 'Document updated.',
            'document' => $document->fresh(['employee.user']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employee-documents/{id}/verify",
     *     summary="Verify document",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document verified"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function verify(Request $request, $id)
    {
        $document = EmployeeDocument::findOrFail($id);
        $oldValues = $document->toArray();

        $document->verify($request->user()->id);

        ActivityLog::logUpdated($document, $oldValues, 'Document verified');

        return response()->json([
            'message' => 'Document verified.',
            'document' => $document->fresh(['employee.user', 'verifiedBy']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/employee-documents/{id}/unverify",
     *     summary="Unverify document",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document unverified"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function unverify(Request $request, $id)
    {
        $document = EmployeeDocument::findOrFail($id);
        $oldValues = $document->toArray();

        $document->unverify();

        ActivityLog::logUpdated($document, $oldValues, 'Document unverified');

        return response()->json([
            'message' => 'Document verification removed.',
            'document' => $document->fresh(['employee.user']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-documents/{id}/download",
     *     summary="Download document file",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="File download"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function download($id)
    {
        $document = EmployeeDocument::findOrFail($id);

        if (!Storage::exists($document->file_path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }

        return Storage::download($document->file_path, $document->file_name);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/employee-documents/{id}",
     *     summary="Delete document",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Document deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        $document = EmployeeDocument::findOrFail($id);

        // Delete file from storage
        $document->deleteFile();

        ActivityLog::logDeleted($document);

        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-documents/types",
     *     summary="Get all document types",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of types",
     *         @OA\JsonContent(
     *             @OA\Property(property="types", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="key", type="string", example="contract"),
     *                 @OA\Property(property="name", type="string", example="Contract")
     *             ))
     *         )
     *     )
     * )
     */
    public function types()
    {
        $types = [];
        $id = 1;
        foreach (EmployeeDocument::TYPES as $key => $name) {
            $types[] = [
                'id' => $id++,
                'key' => $key,
                'name' => $name,
            ];
        }

        return response()->json(['types' => $types]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-documents/expiring",
     *     summary="Get expiring documents",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(name="days", in="query", description="Days until expiry", @OA\Schema(type="integer", default=30)),
     *     @OA\Response(response=200, description="List of expiring documents"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function expiring(Request $request)
    {
        $days = $request->get('days', 30);

        $documents = EmployeeDocument::with(['employee.user'])
                                     ->expiringSoon($days)
                                     ->orderBy('expiry_date')
                                     ->paginate($request->get('per_page', 15));

        return response()->json($documents);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-documents/expired",
     *     summary="Get expired documents",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="List of expired documents"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function expired(Request $request)
    {
        $documents = EmployeeDocument::with(['employee.user'])
                                     ->expired()
                                     ->orderBy('expiry_date', 'desc')
                                     ->paginate($request->get('per_page', 15));

        return response()->json($documents);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/employee-documents/my",
     *     summary="Get current user's documents",
     *     tags={"Employee Documents"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="User's documents"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee profile not found")
     * )
     */
    public function myDocuments(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found.'], 404);
        }

        $documents = EmployeeDocument::forEmployee($employee->id)
                                     ->with(['uploadedBy', 'verifiedBy'])
                                     ->orderBy('created_at', 'desc')
                                     ->paginate($request->get('per_page', 15));

        return response()->json($documents);
    }
}





