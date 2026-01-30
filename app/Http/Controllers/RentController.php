<?php

namespace App\Http\Controllers;

use App\Models\Rent;
use App\Models\Cloth;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/appointments",
     *     summary="List all appointments/rents",
     *     description="Get a paginated list of appointments with filters.",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="client_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="cloth_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="order_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="appointment_type", in="query", required=false, @OA\Schema(type="string", enum={"rental_delivery", "rental_return", "measurement", "tailoring_pickup", "tailoring_delivery", "fitting", "other"})),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"scheduled", "confirmed", "in_progress", "completed", "cancelled", "no_show", "rescheduled", "active"})),
     *     @OA\Parameter(name="date", in="query", required=false, description="Filter by specific date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="start_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="upcoming_only", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="overdue_only", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="today_only", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of appointments",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $query = Rent::with(['client', 'branch', 'cloth', 'order', 'creator']);

        // Filters
        if ($request->filled('client_id')) {
            $query->forClient($request->client_id);
        }

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('cloth_id')) {
            $query->forCloth($request->cloth_id);
        }

        if ($request->filled('order_id')) {
            $query->forOrder($request->order_id);
        }

        if ($request->filled('appointment_type')) {
            $query->ofType($request->appointment_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->onDate($request->date);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->betweenDates($request->start_date, $request->end_date);
        } elseif ($request->filled('start_date')) {
            $query->where('delivery_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('delivery_date', '<=', $request->end_date);
        }

        if ($request->boolean('upcoming_only')) {
            $query->upcoming();
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        if ($request->boolean('today_only')) {
            $query->today();
        }

        $appointments = $query->orderBy('delivery_date')
            ->orderBy('appointment_time')
            ->paginate($perPage);

        // Add computed fields
        $appointments->getCollection()->transform(function ($appointment) {
            $appointment->display_title = $appointment->display_title;
            $appointment->is_overdue = $appointment->is_overdue;
            $appointment->is_rental = $appointment->is_rental;
            $appointment->is_tailoring = $appointment->is_tailoring;
            return $appointment;
        });

        return $this->paginatedResponse($appointments);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/{id}",
     *     summary="Get appointment details",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Appointment details"),
     *     @OA\Response(response=404, description="Appointment not found")
     * )
     */
    public function show($id)
    {
        $appointment = Rent::with(['client', 'branch', 'cloth', 'order', 'creator', 'completer'])
            ->findOrFail($id);

        $appointment->display_title = $appointment->display_title;
        $appointment->is_overdue = $appointment->is_overdue;
        $appointment->appointment_date_time = $appointment->appointment_date_time;

        return response()->json($appointment);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments",
     *     summary="Create a new appointment",
     *     description="Create a new appointment/rent. Checks for conflicts when cloth_id is provided.",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"appointment_type", "delivery_date"},
     *             @OA\Property(property="appointment_type", type="string", enum={"rental_delivery", "rental_return", "measurement", "tailoring_pickup", "tailoring_delivery", "fitting", "other"}, example="rental_delivery"),
     *             @OA\Property(property="title", type="string", nullable=true, example="Wedding dress rental"),
     *             @OA\Property(property="client_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="branch_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="cloth_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="order_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="delivery_date", type="string", format="date", example="2026-01-15"),
     *             @OA\Property(property="appointment_time", type="string", nullable=true, example="10:00"),
     *             @OA\Property(property="return_date", type="string", format="date", nullable=true, example="2026-01-20"),
     *             @OA\Property(property="return_time", type="string", nullable=true, example="18:00"),
     *             @OA\Property(property="days_of_rent", type="integer", nullable=true, example=5),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Appointment created successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=409, description="Scheduling conflict"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'appointment_type' => 'required|string|in:rental_delivery,rental_return,measurement,tailoring_pickup,tailoring_delivery,fitting,other',
            'title' => 'nullable|string|max:255',
            'client_id' => 'nullable|exists:clients,id',
            'branch_id' => 'nullable|exists:branches,id',
            'cloth_id' => 'nullable|exists:clothes,id',
            'order_id' => 'nullable|exists:orders,id',
            'delivery_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'nullable|date_format:H:i',
            'return_date' => 'nullable|date|after_or_equal:delivery_date',
            'return_time' => 'nullable|date_format:H:i',
            'days_of_rent' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Calculate return_date if days_of_rent is provided
        if (isset($data['days_of_rent']) && !isset($data['return_date'])) {
            $data['return_date'] = Carbon::parse($data['delivery_date'])->addDays($data['days_of_rent']);
        }

        // Check for cloth conflicts if cloth_id is provided
        if (!empty($data['cloth_id']) && !empty($data['return_date'])) {
            $startDate = Carbon::parse($data['delivery_date']);
            $endDate = Carbon::parse($data['return_date']);

            if (Rent::hasClothConflict($data['cloth_id'], $startDate, $endDate)) {
                $conflicts = Rent::getClothConflicts($data['cloth_id'], $startDate, $endDate);
                return response()->json([
                    'message' => 'Scheduling conflict: This cloth is already booked for the requested dates',
                    'conflicts' => $conflicts->map(function ($c) {
                        return [
                            'id' => $c->id,
                            'delivery_date' => $c->delivery_date->format('Y-m-d'),
                            'return_date' => $c->return_date?->format('Y-m-d'),
                            'status' => $c->status,
                        ];
                    }),
                ], 409);
            }
        }

        $appointment = Rent::create([
            'appointment_type' => $data['appointment_type'],
            'title' => $data['title'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'cloth_id' => $data['cloth_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'delivery_date' => $data['delivery_date'],
            'appointment_time' => $data['appointment_time'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'return_time' => $data['return_time'] ?? null,
            'days_of_rent' => $data['days_of_rent'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => Rent::STATUS_SCHEDULED,
            'created_by' => $request->user()->id,
        ]);

        $appointment->load(['client', 'branch', 'cloth', 'order', 'creator']);

        return response()->json([
            'message' => 'Appointment created successfully',
            'appointment' => $appointment,
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/appointments/{id}",
     *     summary="Update an appointment",
     *     description="Update appointment details. Cannot update completed or cancelled appointments.",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="client_id", type="integer"),
     *             @OA\Property(property="branch_id", type="integer"),
     *             @OA\Property(property="delivery_date", type="string", format="date"),
     *             @OA\Property(property="appointment_time", type="string"),
     *             @OA\Property(property="return_date", type="string", format="date"),
     *             @OA\Property(property="return_time", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Appointment updated"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=409, description="Scheduling conflict"),
     *     @OA\Response(response=422, description="Validation error or appointment cannot be modified")
     * )
     */
    public function update(Request $request, $id)
    {
        $appointment = Rent::findOrFail($id);

        if (!$appointment->canBeModified()) {
            return response()->json([
                'message' => 'This appointment cannot be modified',
                'errors' => ['status' => ['Appointment is ' . $appointment->status]]
            ], 422);
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'client_id' => 'nullable|exists:clients,id',
            'branch_id' => 'nullable|exists:branches,id',
            'delivery_date' => 'sometimes|date',
            'appointment_time' => 'nullable|date_format:H:i',
            'return_date' => 'nullable|date|after_or_equal:delivery_date',
            'return_time' => 'nullable|date_format:H:i',
            'days_of_rent' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Check for cloth conflicts if dates are changing
        if ($appointment->cloth_id && (isset($data['delivery_date']) || isset($data['return_date']))) {
            $startDate = Carbon::parse($data['delivery_date'] ?? $appointment->delivery_date);
            $endDate = isset($data['return_date']) 
                ? Carbon::parse($data['return_date']) 
                : ($appointment->return_date ?? $startDate);

            if (Rent::hasClothConflict($appointment->cloth_id, $startDate, $endDate, $appointment->id)) {
                return response()->json([
                    'message' => 'Scheduling conflict: This cloth is already booked for the requested dates',
                ], 409);
            }
        }

        $appointment->update($data);
        $appointment->load(['client', 'branch', 'cloth', 'order', 'creator']);

        return response()->json($appointment);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments/{id}/confirm",
     *     summary="Confirm an appointment",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Appointment confirmed"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=422, description="Appointment cannot be confirmed")
     * )
     */
    public function confirm($id)
    {
        $appointment = Rent::findOrFail($id);

        if (!$appointment->confirm()) {
            return response()->json([
                'message' => 'Appointment cannot be confirmed',
                'errors' => ['status' => ['Current status: ' . $appointment->status]]
            ], 422);
        }

        return response()->json([
            'message' => 'Appointment confirmed',
            'appointment' => $appointment->fresh(['client', 'branch', 'cloth']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments/{id}/start",
     *     summary="Start an appointment (mark as in progress)",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Appointment started"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=422, description="Appointment cannot be started")
     * )
     */
    public function start($id)
    {
        $appointment = Rent::findOrFail($id);

        if (!$appointment->startProgress()) {
            return response()->json([
                'message' => 'Appointment cannot be started',
                'errors' => ['status' => ['Current status: ' . $appointment->status]]
            ], 422);
        }

        return response()->json([
            'message' => 'Appointment started',
            'appointment' => $appointment->fresh(['client', 'branch', 'cloth']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments/{id}/complete",
     *     summary="Complete an appointment",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="notes", type="string", description="Completion notes")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Appointment completed"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=422, description="Appointment cannot be completed")
     * )
     */
    public function complete(Request $request, $id)
    {
        $appointment = Rent::findOrFail($id);

        $notes = $request->input('notes');
        if ($notes) {
            $appointment->notes = ($appointment->notes ? $appointment->notes . "\n" : '') . "Completion notes: {$notes}";
        }

        if (!$appointment->complete($request->user())) {
            return response()->json([
                'message' => 'Appointment cannot be completed',
                'errors' => ['status' => ['Current status: ' . $appointment->status]]
            ], 422);
        }

        return response()->json([
            'message' => 'Appointment completed',
            'appointment' => $appointment->fresh(['client', 'branch', 'cloth', 'completer']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments/{id}/cancel",
     *     summary="Cancel an appointment",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", description="Cancellation reason")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Appointment cancelled"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=422, description="Appointment cannot be cancelled")
     * )
     */
    public function cancel(Request $request, $id)
    {
        $appointment = Rent::findOrFail($id);

        $reason = $request->input('reason');

        if (!$appointment->cancel($reason)) {
            return response()->json([
                'message' => 'Appointment cannot be cancelled',
                'errors' => ['status' => ['Current status: ' . $appointment->status]]
            ], 422);
        }

        return response()->json([
            'message' => 'Appointment cancelled',
            'appointment' => $appointment->fresh(['client', 'branch', 'cloth']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments/{id}/no-show",
     *     summary="Mark appointment as no-show",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Appointment marked as no-show"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=422, description="Appointment cannot be marked as no-show")
     * )
     */
    public function noShow($id)
    {
        $appointment = Rent::findOrFail($id);

        if (!$appointment->markNoShow()) {
            return response()->json([
                'message' => 'Appointment cannot be marked as no-show',
                'errors' => ['status' => ['Current status: ' . $appointment->status]]
            ], 422);
        }

        return response()->json([
            'message' => 'Appointment marked as no-show',
            'appointment' => $appointment->fresh(['client', 'branch', 'cloth']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/appointments/{id}/reschedule",
     *     summary="Reschedule an appointment",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"new_date"},
     *             @OA\Property(property="new_date", type="string", format="date", example="2026-01-20"),
     *             @OA\Property(property="new_time", type="string", nullable=true, example="14:00")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Appointment rescheduled"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=409, description="Scheduling conflict"),
     *     @OA\Response(response=422, description="Appointment cannot be rescheduled")
     * )
     */
    public function reschedule(Request $request, $id)
    {
        $appointment = Rent::findOrFail($id);

        $data = $request->validate([
            'new_date' => 'required|date|after_or_equal:today',
            'new_time' => 'nullable|date_format:H:i',
        ]);

        if (!$appointment->canBeModified()) {
            return response()->json([
                'message' => 'Appointment cannot be rescheduled',
                'errors' => ['status' => ['Current status: ' . $appointment->status]]
            ], 422);
        }

        // Check for cloth conflicts
        if ($appointment->cloth_id && $appointment->return_date) {
            $newStart = Carbon::parse($data['new_date']);
            $daysDiff = $appointment->delivery_date->diffInDays($newStart);
            $newEnd = $appointment->return_date->copy()->addDays($daysDiff);

            if (Rent::hasClothConflict($appointment->cloth_id, $newStart, $newEnd, $appointment->id)) {
                return response()->json([
                    'message' => 'Scheduling conflict: This cloth is already booked for the new dates',
                ], 409);
            }

            // Update return date to match the shift
            $appointment->return_date = $newEnd;
        }

        $appointment->reschedule(
            Carbon::parse($data['new_date']),
            $data['new_time'] ?? null
        );

        return response()->json([
            'message' => 'Appointment rescheduled',
            'appointment' => $appointment->fresh(['client', 'branch', 'cloth']),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/types",
     *     summary="Get all appointment types",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of appointment types")
     * )
     */
    public function types()
    {
        return response()->json([
            'types' => Rent::getAppointmentTypes(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/statuses",
     *     summary="Get all appointment statuses",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="List of appointment statuses")
     * )
     */
    public function statuses()
    {
        return response()->json([
            'statuses' => Rent::getStatuses(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/calendar",
     *     summary="Get appointments for calendar view",
     *     description="Get appointments within a date range formatted for calendar display.",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="appointment_type", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Calendar events")
     * )
     */
    public function calendar(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'branch_id' => 'nullable|exists:branches,id',
            'appointment_type' => 'nullable|string',
        ]);

        $query = Rent::with(['client', 'cloth'])
            ->betweenDates($request->start_date, $request->end_date);

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('appointment_type')) {
            $query->ofType($request->appointment_type);
        }

        $appointments = $query->get();

        // Format for calendar
        $events = $appointments->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'title' => $appointment->display_title,
                'start' => $appointment->appointment_date_time?->toIso8601String() 
                    ?? $appointment->delivery_date->format('Y-m-d'),
                'end' => $appointment->return_date_time?->toIso8601String()
                    ?? $appointment->return_date?->format('Y-m-d'),
                'type' => $appointment->appointment_type,
                'status' => $appointment->status,
                'client_name' => $appointment->client 
                    ? "{$appointment->client->first_name} {$appointment->client->last_name}" 
                    : null,
                'cloth_name' => $appointment->cloth?->name,
                'is_overdue' => $appointment->is_overdue,
            ];
        });

        return response()->json([
            'events' => $events,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/today",
     *     summary="Get today's appointments",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Today's appointments")
     * )
     */
    public function today(Request $request)
    {
        $query = Rent::with(['client', 'branch', 'cloth'])
            ->today()
            ->orderBy('appointment_time');

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        $appointments = $query->get();

        // Add summary
        $summary = [
            'total' => $appointments->count(),
            'by_status' => $appointments->groupBy('status')->map->count(),
            'by_type' => $appointments->groupBy('appointment_type')->map->count(),
        ];

        return response()->json([
            'date' => today()->format('Y-m-d'),
            'appointments' => $appointments,
            'summary' => $summary,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/upcoming",
     *     summary="Get upcoming appointments",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="days", in="query", required=false, description="Number of days to look ahead", @OA\Schema(type="integer", default=7)),
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="client_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Upcoming appointments")
     * )
     */
    public function upcoming(Request $request)
    {
        $days = (int) $request->query('days', 7);

        $query = Rent::with(['client', 'branch', 'cloth'])
            ->betweenDates(today(), today()->addDays($days))
            ->active()
            ->orderBy('delivery_date')
            ->orderBy('appointment_time');

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        if ($request->filled('client_id')) {
            $query->forClient($request->client_id);
        }

        $appointments = $query->get();

        // Group by date
        $byDate = $appointments->groupBy(function ($appointment) {
            return $appointment->delivery_date->format('Y-m-d');
        });

        return response()->json([
            'period' => [
                'start' => today()->format('Y-m-d'),
                'end' => today()->addDays($days)->format('Y-m-d'),
            ],
            'total' => $appointments->count(),
            'by_date' => $byDate,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/appointments/overdue",
     *     summary="Get overdue appointments",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="branch_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Overdue appointments")
     * )
     */
    public function overdue(Request $request)
    {
        $query = Rent::with(['client', 'branch', 'cloth'])
            ->overdue()
            ->orderBy('delivery_date');

        if ($request->filled('branch_id')) {
            $query->forBranch($request->branch_id);
        }

        $appointments = $query->get();

        return response()->json([
            'total' => $appointments->count(),
            'appointments' => $appointments,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{client_id}/appointments",
     *     summary="Get appointments for a client",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="client_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Client's appointments"),
     *     @OA\Response(response=404, description="Client not found")
     * )
     */
    public function forClient(Request $request, $clientId)
    {
        Client::findOrFail($clientId);

        $query = Rent::with(['branch', 'cloth', 'order'])
            ->forClient($clientId)
            ->orderBy('delivery_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->get();

        $upcoming = $appointments->filter(fn($a) => $a->delivery_date >= today() && $a->isActive());
        $past = $appointments->filter(fn($a) => $a->delivery_date < today() || !$a->isActive());

        return response()->json([
            'upcoming' => $upcoming->values(),
            'past' => $past->values(),
            'total' => $appointments->count(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clothes/{cloth_id}/availability",
     *     summary="Check cloth availability for dates",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="cloth_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="start_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="end_date", in="query", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Availability check result"),
     *     @OA\Response(response=404, description="Cloth not found")
     * )
     */
    public function checkClothAvailability(Request $request, $clothId)
    {
        Cloth::findOrFail($clothId);

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $hasConflict = Rent::hasClothConflict($clothId, $startDate, $endDate);
        $conflicts = $hasConflict 
            ? Rent::getClothConflicts($clothId, $startDate, $endDate) 
            : collect();

        $unavailableDates = Rent::getClothUnavailableDates($clothId, $startDate, $endDate);

        return response()->json([
            'cloth_id' => (int) $clothId,
            'requested_period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'is_available' => !$hasConflict,
            'conflicts' => $conflicts->map(function ($c) {
                return [
                    'id' => $c->id,
                    'type' => $c->appointment_type,
                    'delivery_date' => $c->delivery_date->format('Y-m-d'),
                    'return_date' => $c->return_date?->format('Y-m-d'),
                    'client' => $c->client ? "{$c->client->first_name} {$c->client->last_name}" : null,
                ];
            }),
            'unavailable_dates' => $unavailableDates,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/appointments/{id}",
     *     summary="Delete an appointment",
     *     description="Only scheduled appointments can be deleted. Use cancel for confirmed appointments.",
     *     tags={"Appointments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Appointment deleted"),
     *     @OA\Response(response=404, description="Appointment not found"),
     *     @OA\Response(response=422, description="Appointment cannot be deleted")
     * )
     */
    public function destroy($id)
    {
        $appointment = Rent::findOrFail($id);

        if ($appointment->status !== Rent::STATUS_SCHEDULED) {
            return response()->json([
                'message' => 'Only scheduled appointments can be deleted. Use cancel instead.',
                'errors' => ['status' => ['Current status: ' . $appointment->status]]
            ], 422);
        }

        $appointment->delete();

        return response()->json([
            'message' => 'Appointment deleted successfully',
        ]);
    }
}






