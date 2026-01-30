<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\Custody;
use App\Models\CustodyPhoto;
use App\Models\CustodyReturn;
use App\Models\Client;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Rules\MySqlDateTime;

class CustodyController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Handle photo uploads with validation
     *
     * @param array|\Illuminate\Http\UploadedFile|\Illuminate\Http\UploadedFile[] $files
     * @param int|null $custodyId Optional custody ID for file naming
     * @param int $maxFiles Maximum number of files allowed
     * @return array Array of stored file paths (relative paths)
     */
    private function handlePhotoUploads($files, $custodyId = null, $maxFiles = 2)
    {
        $paths = [];

        // Ensure files is an array
        if (!is_array($files)) {
            $files = [$files];
        }

        // Limit to maxFiles
        $files = array_slice($files, 0, $maxFiles);

        $storagePath = 'custody-photos';

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                // Generate unique filename
                $extension = $file->getClientOriginalExtension();
                $filename = 'custody_' . ($custodyId ?? 'temp') . '_' . time() . '_' . uniqid() . '.' . $extension;

                // Store file in private storage
                $path = $file->storeAs($storagePath, $filename, 'private');

                // Store relative path for database
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/orders/{id}/custody",
     *     summary="Add custody to an order",
     *     tags={"Custody"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type", "description"},
     *             @OA\Property(property="type", type="string", enum={"money", "physical_item", "document"}, example="physical_item", description="Custody type"),
     *             @OA\Property(property="description", type="string", example="Cash deposit of 500 EGP"),
     *             @OA\Property(property="value", type="number", example=500.00, description="Required for money type"),
     *             @OA\Property(property="photos", type="array", @OA\Items(type="file"), description="Photo files for physical items (max 2)"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Custody added successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", enum={"money", "physical_item", "document"}, example="physical_item", description="Custody type"),
     *             @OA\Property(property="description", type="string", example="Cash deposit"),
     *             @OA\Property(property="value", type="number", nullable=true, example=500.00),
     *             @OA\Property(property="status", type="string", enum={"pending", "returned", "forfeited"}, example="pending", description="Custody status"),
     *             @OA\Property(property="returned_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(
     *                 property="photos",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="custody_id", type="integer", example=1),
     *                     @OA\Property(property="photo_path", type="string", example="custody-photos/..."),
     *                     @OA\Property(property="photo_type", type="string", enum={"custody_photo", "id_photo", "acknowledgement_receipt"}, example="custody_photo", description="Photo type"),
     *                     @OA\Property(property="photo_url", type="string", example="/api/v1/custody-photos/custody-photos/...")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Order not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Validate order status - can only add custody to orders in created, partially_paid, or paid status
        if (!in_array($order->status, ['created', 'partially_paid', 'paid'])) {
            return response()->json([
                'message' => 'Cannot add custody to order in current status',
                'errors' => ['status' => ['Custody can only be added to orders in created, partially_paid, or paid status']]
            ], 422);
        }

        $data = $request->validate([
            'type' => 'required|string|in:money,physical_item,document',
            'description' => 'required|string',
            'value' => 'required_if:type,money|nullable|numeric|min:0',
            'photos' => 'required_if:type,physical_item|nullable|array|max:2',
            'photos.*' => 'image|mimes:jpeg,png,gif,webp,bmp|max:5120',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // Validate value is provided for money type
        if ($data['type'] === 'money' && !isset($data['value'])) {
            return response()->json([
                'message' => 'Value is required for money type custody',
                'errors' => ['value' => ['Value is required for money type custody']]
            ], 422);
        }

        // Validate photos are provided for physical_item type
        if ($data['type'] === 'physical_item' && (!$request->hasFile('photos') || empty($request->file('photos')))) {
            return response()->json([
                'message' => 'At least one photo is required for physical item custody',
                'errors' => ['photos' => ['At least one photo is required for physical item custody']]
            ], 422);
        }

        $custody = null;
        $transaction = null;

        DB::transaction(function () use ($order, $data, $request, &$custody, &$transaction) {
            // Create custody record first to get ID for photo naming
            $custody = Custody::create([
                'order_id' => $order->id,
                'type' => $data['type'],
                'description' => $data['description'],
                'value' => $data['value'] ?? null,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            // Handle photo uploads if provided
            $photoPaths = [];
            if ($request->hasFile('photos') && !empty($request->file('photos'))) {
                $photoPaths = $this->handlePhotoUploads($request->file('photos'), $custody->id, 2);
            }

            // Add photos to database if uploaded
            if (!empty($photoPaths)) {
                foreach ($photoPaths as $photoPath) {
                    CustodyPhoto::create([
                        'custody_id' => $custody->id,
                        'photo_path' => $photoPath,
                        'photo_type' => 'custody_photo',
                    ]);
                }
            }

            // Create transaction for money type custody (deposit)
            if ($data['type'] === 'money' && isset($data['value']) && $data['value'] > 0) {
                $branchId = $data['branch_id'] ?? $order->branch_id ?? null;

                if ($branchId) {
                    $branch = \App\Models\Branch::find($branchId);
                    if ($branch && $branch->cashbox && $branch->cashbox->is_active) {
                        $transaction = $this->transactionService->recordCustodyDeposit(
                            $branch->cashbox,
                            $data['value'],
                            $custody->id,
                            $order->id,
                            $request->user()
                        );
                    }
                }
            }
        });

        $custody->load('photos');

        $transformed = $this->transformCustodyResponse($custody);

        // Add transaction info to response if created
        if ($transaction) {
            $transformed['transaction'] = [
                'id' => $transaction->id,
                'cashbox_id' => $transaction->cashbox_id,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
            ];
        }

        return response()->json($transformed, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/custody/{id}",
     *     summary="Update custody notes",
     *     tags={"Custody"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", nullable=true, example="Notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custody updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", enum={"money", "physical_item", "document"}, example="physical_item", description="Custody type"),
     *             @OA\Property(property="description", type="string", example="Cash deposit"),
     *             @OA\Property(property="value", type="number", nullable=true, example=500.00),
     *             @OA\Property(property="status", type="string", enum={"pending", "returned", "forfeited"}, example="pending", description="Custody status"),
     *             @OA\Property(property="returned_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(
     *                 property="photos",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="photo_type", type="string", enum={"custody_photo", "id_photo", "acknowledgement_receipt"}, example="custody_photo", description="Photo type"),
     *                     @OA\Property(property="photo_url", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Custody not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $custody = Custody::findOrFail($id);

        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        if (isset($data['notes'])) {
            $custody->notes = ($custody->notes ? $custody->notes . "\n" : '') . $data['notes'];
            $custody->save();
        }

        $custody->load(['photos', 'returns']);

        $transformed = $this->transformCustodyResponse($custody);

        return response()->json($transformed);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/custody/{id}/return",
     *     summary="Return custody with acknowledgement receipt",
     *     tags={"Custody"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"custody_action", "acknowledgement_receipt_photos"},
     *                 @OA\Property(property="custody_action", type="string", enum={"returned_to_user", "forfeit"}, example="returned_to_user", description="Action to perform on custody"),
     *                 @OA\Property(property="acknowledgement_receipt_photos", type="array", @OA\Items(type="file"), description="Acknowledgement receipt photos (1-2 photos)", minItems=1, maxItems=2),
     *                 @OA\Property(property="reason_of_kept", type="string", nullable=true, example="Client did not collect", description="Required when action is forfeit"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Notes")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custody returned successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Custody returned successfully"),
     *             @OA\Property(
     *                 property="custody",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="order_id", type="integer", example=1),
     *                 @OA\Property(property="type", type="string", enum={"money", "physical_item", "document"}, example="physical_item", description="Custody type"),
     *                 @OA\Property(property="description", type="string", example="Cash deposit"),
     *                 @OA\Property(property="value", type="number", nullable=true, example=500.00),
     *                 @OA\Property(property="status", type="string", enum={"pending", "returned", "forfeited"}, example="pending", description="Custody status"),
     *                 @OA\Property(property="returned_at", type="string", format="date-time", nullable=true),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(
     *                     property="photos",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="photo_type", type="string", enum={"custody_photo", "id_photo", "acknowledgement_receipt"}, example="custody_photo", description="Photo type"),
     *                         @OA\Property(property="photo_url", type="string")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="custody_return",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="custody_id", type="integer", example=1),
     *                 @OA\Property(property="client_id", type="integer", example=1),
     *                 @OA\Property(property="reason_of_kept", type="string", nullable=true),
     *                 @OA\Property(property="notes", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Custody not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function returnCustody(Request $request, $id)
    {
        $custody = Custody::with('order')->findOrFail($id);

        $data = $request->validate([
            'custody_action' => 'required|string|in:returned_to_user,forfeit',
            'acknowledgement_receipt_photos' => 'required|array|min:1|max:2',
            'acknowledgement_receipt_photos.*' => 'image|mimes:jpeg,png,gif,webp,bmp|max:5120',
            'reason_of_kept' => 'required_if:custody_action,forfeit|nullable|string',
            'notes' => 'nullable|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // Validate that photos are provided
        if (!$request->hasFile('acknowledgement_receipt_photos') || empty($request->file('acknowledgement_receipt_photos'))) {
            return response()->json([
                'message' => 'At least one acknowledgement receipt photo is required',
                'errors' => ['acknowledgement_receipt_photos' => ['At least one acknowledgement receipt photo is required']]
            ], 422);
        }

        // Determine status based on action
        $status = $data['custody_action'] === 'returned_to_user' ? 'returned' : 'forfeited';

        // Handle acknowledgement receipt photo uploads
        $photoPaths = $this->handlePhotoUploads($request->file('acknowledgement_receipt_photos'), $custody->id, 2);

        if (empty($photoPaths)) {
            return response()->json([
                'message' => 'Failed to upload acknowledgement receipt photos',
                'errors' => ['acknowledgement_receipt_photos' => ['Failed to upload acknowledgement receipt photos']]
            ], 422);
        }

        $custodyReturn = null;
        $transaction = null;

        try {
            DB::transaction(function () use ($custody, $data, $request, $status, $photoPaths, &$custodyReturn, &$transaction) {
                // Store photos in custody_photos table with photo_type = 'acknowledgement_receipt'
                $firstPhotoPath = null;
                foreach ($photoPaths as $index => $photoPath) {
                    CustodyPhoto::create([
                        'custody_id' => $custody->id,
                        'photo_path' => $photoPath,
                        'photo_type' => 'acknowledgement_receipt',
                    ]);
                    // Store first photo path for return_proof_photo
                    if ($index === 0) {
                        $firstPhotoPath = $photoPath;
                    }
                }

                // Get client_id from custody's order
                $clientId = $custody->order->client_id;
                $order = $custody->order;

                // Update custody status
                $custody->status = $status;
                if ($data['custody_action'] === 'returned_to_user') {
                    $custody->returned_at = now();
                }
                $custody->save();

                // Create custody return record
                $custodyReturn = CustodyReturn::create([
                    'custody_id' => $custody->id,
                    'returned_at' => now(),
                    'client_id' => $clientId,
                    'return_proof_photo' => $firstPhotoPath,
                    'reason_of_kept' => $data['reason_of_kept'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'returned_by' => $request->user()?->id,
                ]);

                // Create transaction for money type custody
                if ($custody->type === 'money' && $custody->value > 0) {
                    $branchId = $data['branch_id'] ?? $order->branch_id ?? null;

                    if ($branchId) {
                        $branch = \App\Models\Branch::find($branchId);
                        if ($branch && $branch->cashbox && $branch->cashbox->is_active) {
                            if ($data['custody_action'] === 'returned_to_user') {
                                // Return money to customer (expense)
                                $transaction = $this->transactionService->recordCustodyReturn(
                                    $branch->cashbox,
                                    $custody->value,
                                    $custody->id,
                                    $order->id,
                                    $request->user()
                                );
                            } else {
                                // Forfeiture - record for audit trail (deposit already recorded)
                                $transaction = $this->transactionService->recordCustodyForfeiture(
                                    $branch->cashbox,
                                    $custody->value,
                                    $custody->id,
                                    $order->id,
                                    $request->user(),
                                    $data['reason_of_kept'] ?? 'No reason provided'
                                );
                            }
                        }
                    }
                }
            });
        } catch (\RuntimeException $e) {
            // If transaction fails (e.g., insufficient balance for return), return error
            return response()->json([
                'message' => 'Cannot return custody: ' . $e->getMessage(),
                'errors' => ['transaction' => [$e->getMessage()]]
            ], 422);
        }

        $custody->load(['photos', 'returns']);

        $transformed = $this->transformCustodyResponse($custody);

        $response = [
            'message' => 'Custody returned successfully',
            'custody' => $transformed,
            'custody_return' => $custodyReturn,
        ];

        if ($transaction) {
            $response['transaction'] = [
                'id' => $transaction->id,
                'cashbox_id' => $transaction->cashbox_id,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'type' => $transaction->type,
            ];
        }

        return response()->json($response);
    }

    /**
     * Generate signed URL for a photo path
     * Signed URLs allow browsers to access the photo without sending Authorization headers
     * URLs are set to expire in 100 years (effectively forever)
     *
     * @param string|null $photoPath
     * @return string|null
     */
    private function getPhotoUrl($photoPath)
    {
        if (empty($photoPath)) {
            return null;
        }

        // Generate signed URL that expires in 100 years (effectively forever)
        // This allows browsers to access the photo directly without Authorization headers
        return URL::temporarySignedRoute(
            'custody.photos.show',
            now()->addYears(100), // Expires in 100 years (effectively forever)
            ['path' => $photoPath]
        );
    }

    /**
     * Transform custody response to include photo URLs
     *
     * @param Custody $custody
     * @return array
     */
    private function transformCustodyResponse($custody)
    {
        $custodyArray = $custody->toArray();

        // Transform photos array to include photo_url
        if (isset($custodyArray['photos']) && is_array($custodyArray['photos'])) {
            $custodyArray['photos'] = array_map(function ($photo) {
                $photo['photo_url'] = $this->getPhotoUrl($photo['photo_path'] ?? null);
                return $photo;
            }, $custodyArray['photos']);
        }

        // Transform returns array to include photo URLs for return_proof_photo if it exists
        // Also include acknowledgement receipt photos that belong to this custody
        if (isset($custodyArray['returns']) && is_array($custodyArray['returns'])) {
            // Get all acknowledgement receipt photos from custody photos
            $acknowledgementPhotos = [];
            if (isset($custodyArray['photos']) && is_array($custodyArray['photos'])) {
                $acknowledgementPhotos = array_values(array_filter($custodyArray['photos'], function ($photo) {
                    return isset($photo['photo_type']) && $photo['photo_type'] === 'acknowledgement_receipt';
                }));
            }

            $custodyArray['returns'] = array_map(function ($return) use ($acknowledgementPhotos) {
                // If return has return_proof_photo, generate URL for it
                if (!empty($return['return_proof_photo'])) {
                    $return['return_proof_photo_url'] = $this->getPhotoUrl($return['return_proof_photo']);
                }
                // Include all acknowledgement receipt photos with the return
                if (!empty($acknowledgementPhotos)) {
                    $return['acknowledgement_receipt_photos'] = $acknowledgementPhotos;
                }
                return $return;
            }, $custodyArray['returns']);
        }

        return $custodyArray;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/custody-photos/{path}",
     *     summary="Serve custody photo file",
     *     tags={"Custody"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="path",
     *         in="path",
     *         required=true,
     *         description="Photo file path",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Photo file"),
     *     @OA\Response(response=404, description="Photo not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function showPhoto(Request $request, $path)
    {
        // Validate signed URL signature manually (bypasses auth requirement)
        // This allows the route to work without authentication if signature is valid
        if (!URL::hasValidSignature($request)) {
            abort(403, 'Invalid or expired signature');
        }

        // Validate path to prevent directory traversal
        $path = str_replace('..', '', $path);
        $path = ltrim($path, '/');

        // Ensure path is within custody-photos directory
        if (!str_starts_with($path, 'custody-photos/')) {
            $path = 'custody-photos/' . $path;
        }

        // Check if file exists
        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'Photo not found');
        }

        // Get file path
        $filePath = Storage::disk('private')->path($path);

        // Return file response with proper headers for image
        return response()->file($filePath, [
            'Content-Type' => mime_content_type($filePath) ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000', // Cache for 1 year
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/custody/{id}",
     *     summary="Get custody by ID",
     *     tags={"Custody"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custody retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="type", type="string", enum={"money", "physical_item", "document"}, example="physical_item", description="Custody type"),
     *             @OA\Property(property="description", type="string", example="Cash deposit"),
     *             @OA\Property(property="value", type="number", nullable=true, example=500.00),
     *             @OA\Property(property="status", type="string", enum={"pending", "returned", "forfeited"}, example="pending", description="Custody status"),
     *             @OA\Property(property="returned_at", type="string", format="date-time", nullable=true, example="2025-12-31 12:00:00"),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(
     *                 property="photos",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="custody_id", type="integer", example=1),
     *                     @OA\Property(property="photo_path", type="string", example="custody-photos/..."),
     *                     @OA\Property(property="photo_type", type="string", enum={"custody_photo", "id_photo", "acknowledgement_receipt"}, example="custody_photo", description="Photo type"),
     *                     @OA\Property(property="photo_url", type="string", example="/api/v1/custody-photos/custody-photos/...")
     *                 )
     *             ),
     *             @OA\Property(property="order", type="object"),
     *             @OA\Property(property="returns", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Custody not found")
     * )
     */
    public function show($id)
    {
        $custody = Custody::with(['photos', 'order', 'returns'])->findOrFail($id);

        $transformed = $this->transformCustodyResponse($custody);

        return response()->json($transformed);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/custody",
     *     summary="Get custodies by client ID or order ID",
     *     tags={"Custody"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="client_id",
     *         in="query",
     *         required=false,
     *         description="Filter by client ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="order_id",
     *         in="query",
     *         required=false,
     *         description="Filter by order ID (optional, can be combined with client_id)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page (default: 15)",
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number (default: 1)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Custodies retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="order_id", type="integer", example=1),
     *                     @OA\Property(property="type", type="string", enum={"money", "physical_item", "document"}, example="physical_item", description="Custody type"),
     *                     @OA\Property(property="description", type="string", example="Cash deposit"),
     *                     @OA\Property(property="value", type="number", nullable=true, example=500.00),
     *                     @OA\Property(property="status", type="string", enum={"pending", "returned", "forfeited"}, example="pending", description="Custody status"),
     *                     @OA\Property(property="returned_at", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="notes", type="string", nullable=true),
     *                     @OA\Property(
     *                         property="photos",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="custody_id", type="integer", example=1),
     *                             @OA\Property(property="photo_path", type="string", example="custody-photos/..."),
     *                             @OA\Property(property="photo_type", type="string", enum={"custody_photo", "id_photo", "acknowledgement_receipt"}, example="custody_photo", description="Photo type"),
     *                             @OA\Property(property="photo_url", type="string", example="/api/v1/custody-photos/custody-photos/...")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="last_page", type="integer", example=7),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", example="/api/v1/custody?page=1"),
     *                 @OA\Property(property="last", type="string", example="/api/v1/custody?page=7"),
     *                 @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                 @OA\Property(property="next", type="string", nullable=true, example="/api/v1/custody?page=2")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request, $id = null)
    {
        $clientId = $request->query('client_id');
        // Get order_id from route parameter first (if called via /orders/{id}/custody), then from query string
        // If $id is provided, it means we're accessing via /orders/{id}/custody route
        $orderId = $id ?? $request->query('order_id');
        $perPage = (int) $request->query('per_page', 15);

        $query = Custody::with(['photos', 'order', 'returns']);

        // Apply filters if provided (can be one, both, or neither)
        if ($orderId) {
            // Filter by order ID (from route parameter or query string)
            $query->where('order_id', $orderId);
        }

        if ($clientId) {
            // Filter by client ID through orders (can be combined with order_id filter)
            $query->whereHas('order', function($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        }

        // Apply pagination
        $custodies = $query->paginate($perPage);

        // Transform each custody response in the paginated collection
        $custodies->getCollection()->transform(function ($custody) {
            return $this->transformCustodyResponse($custody);
        });

        return $this->paginatedResponse($custodies);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/custody/export",
     *     summary="Export all custody to CSV",
     *     tags={"Custody"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="order_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="client_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="CSV file download",
     *         @OA\MediaType(
     *             mediaType="text/csv"
     *         )
     *     )
     * )
     */
    public function export(Request $request)
    {
        $clientId = $request->query('client_id');
        $orderId = $request->query('order_id');

        $query = Custody::with(['photos', 'order.client', 'returns']);

        // Apply filters if provided
        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        if ($clientId) {
            $query->whereHas('order', function($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        }

        $items = $query->orderBy('created_at', 'desc')->get();
        return $this->exportToCsv($items, \App\Exports\CustodyExport::class, 'custody_' . date('Y-m-d_His') . '.csv');
    }
}

