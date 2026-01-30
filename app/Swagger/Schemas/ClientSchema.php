<?php

/**
 * @OA\Schema(
 *   schema="ClientCreateRequest",
 *   type="object",
 *   required={"first_name", "middle_name", "last_name", "date_of_birth", "national_id", "address"},
 *   @OA\Property(property="first_name", type="string", example="John", description="First name"),
 *   @OA\Property(property="middle_name", type="string", example="Michael", description="Middle name"),
 *   @OA\Property(property="last_name", type="string", example="Doe", description="Last name"),
 *   @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-15", description="Date of birth (Y-m-d format)"),
 *   @OA\Property(property="national_id", type="string", example="1234567890", description="Unique national ID"),
 *   @OA\Property(property="source", type="string", nullable=true, example="website", description="Client source"),
 *   @OA\Property(property="address", type="object", required={"street", "building", "city_id"},
 *     @OA\Property(property="street", type="string", example="Main Street"),
 *     @OA\Property(property="building", type="string", example="Building 123"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Near the park"),
 *     @OA\Property(property="city_id", type="integer", example=1)
 *   ),
 *   @OA\Property(property="phones", type="array", nullable=true, @OA\Items(type="object",
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="type", type="string", nullable=true, example="mobile")
 *   ), description="Client phones array")
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClientUpdateRequest",
 *   type="object",
 *   @OA\Property(property="first_name", type="string", nullable=true, example="John", description="First name"),
 *   @OA\Property(property="middle_name", type="string", nullable=true, example="Michael", description="Middle name"),
 *   @OA\Property(property="last_name", type="string", nullable=true, example="Doe", description="Last name"),
 *   @OA\Property(property="date_of_birth", type="string", format="date", nullable=true, example="1990-01-15", description="Date of birth (Y-m-d format)"),
 *   @OA\Property(property="national_id", type="string", nullable=true, example="1234567890", description="Unique national ID"),
 *   @OA\Property(property="source", type="string", nullable=true, example="website", description="Client source"),
 *   @OA\Property(property="address_id", type="integer", nullable=true, example=1, description="Address ID")
 * )
 */

/**
 * @OA\Schema(
 *   schema="ClientResponse",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1, description="Client ID"),
 *   @OA\Property(property="first_name", type="string", example="John"),
 *   @OA\Property(property="middle_name", type="string", example="Michael"),
 *   @OA\Property(property="last_name", type="string", example="Doe"),
 *   @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-15"),
 *   @OA\Property(property="national_id", type="string", example="1234567890"),
 *   @OA\Property(property="address_id", type="integer", example=1),
 *   @OA\Property(property="source", type="string", nullable=true, example="website"),
 *   @OA\Property(property="address", ref="#/components/schemas/Address", nullable=true),
 *   @OA\Property(property="phones", type="array", @OA\Items(ref="#/components/schemas/Phone"), nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example="2025-12-02 23:33:25")
 * )
 */

// nothing to execute in this file; it's only for annotations

