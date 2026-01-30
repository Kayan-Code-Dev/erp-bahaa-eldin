<?php

namespace Tests\Feature\Client;

use Tests\Feature\BaseTestCase;
use App\Models\Client;
use App\Models\Address;

/**
 * Client Validation Tests
 *
 * Tests all validation scenarios and edge cases for clients according to TEST_COVERAGE.md specification
 */
class ClientValidationTest extends BaseTestCase
{
    /**
     * Test: Create Client with Missing Required Fields
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: POST /api/v1/clients
     * - Expected Status: 422
     * - Description: Cannot create client without required fields
     */
    public function test_client_create_without_required_fields_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $response = $this->postJson('/api/v1/clients', []);

        $this->assertValidationError($response, [
            'first_name',
            'last_name',
            'national_id',
            'address',
            'phones'
        ]);
    }

    public function test_client_create_without_first_name_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['first_name']);
    }

    public function test_client_create_without_last_name_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['last_name']);
    }

    public function test_client_create_without_national_id_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['national_id']);
    }

    public function test_client_create_without_address_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['address']);
    }

    public function test_client_create_without_phones_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['phones']);
    }

    /**
     * Test: Create Client with Duplicate National ID
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: POST /api/v1/clients
     * - Expected Status: 422
     * - Description: Cannot create client with duplicate national_id
     */
    public function test_client_create_with_duplicate_national_id_fails_422()
    {
        // Create existing client
        Client::factory()->create(['national_id' => '12345678901234']);

        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'national_id' => '12345678901234', // Duplicate
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567891', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['national_id']);
    }

    /**
     * Test: Create Client with Invalid Email Format
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: POST /api/v1/clients
     * - Expected Status: 422
     * - Description: Email must be valid format when provided
     */
    public function test_client_create_with_invalid_email_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'email' => 'invalid-email-format',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['email']);
    }

    /**
     * Test: Create Client with Future Birth Date
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: POST /api/v1/clients
     * - Expected Status: 422
     * - Description: Birth date cannot be in the future
     */
    public function test_client_create_with_future_birth_date_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'date_of_birth' => now()->addDays(1)->format('Y-m-d'), // Future date
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['date_of_birth']);
    }

    /**
     * Test: Create Client with Invalid National ID Format
     * - Type: Feature Test
     * - Module: Clients
     * - Endpoint: POST /api/v1/clients
     * - Expected Status: 422
     * - Description: National ID must be 14 digits
     */
    public function test_client_create_with_invalid_national_id_length_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '123456789', // Too short
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['national_id']);
    }

    public function test_client_create_with_non_numeric_national_id_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => 'abcdefghijklmn', // Non-numeric
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['national_id']);
    }

    /**
     * Test: Address Validation
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Address fields must be valid
     */
    public function test_client_create_with_missing_address_street_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                // Missing street
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['address.street']);
    }

    public function test_client_create_with_missing_address_building_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                // Missing building
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['address.building']);
    }

    public function test_client_create_with_missing_address_city_id_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                // Missing city_id
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['address.city_id']);
    }

    public function test_client_create_with_invalid_address_city_id_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => 99999, // Non-existent city
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['address.city_id']);
    }

    /**
     * Test: Phone Validation
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Phone fields must be valid
     */
    public function test_client_create_with_missing_phone_number_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                [
                    // Missing phone_number
                    'phone_type' => 'mobile'
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['phones.0.phone_number']);
    }

    public function test_client_create_with_missing_phone_type_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                [
                    'phone_number' => '+201234567890',
                    // Missing phone_type
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['phones.0.phone_type']);
    }

    public function test_client_create_with_invalid_phone_type_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                [
                    'phone_number' => '+201234567890',
                    'phone_type' => 'invalid_type'
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['phones.0.phone_type']);
    }

    public function test_client_create_with_duplicate_phone_number_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                [
                    'phone_number' => '+201234567890',
                    'phone_type' => 'mobile'
                ],
                [
                    'phone_number' => '+201234567890', // Duplicate
                    'phone_type' => 'home'
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['phones.1.phone_number']);
    }

    /**
     * Test: Field Length Validations
     * - Type: Feature Test
     * - Module: Clients
     * - Description: Test field length limits
     */
    public function test_client_create_with_name_too_long_fails_422()
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $data = [
            'first_name' => str_repeat('A', 256), // Too long
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, ['first_name']);
    }

    /**
     * Data Provider for Validation Tests
     */
    public static function validationErrorDataProvider(): array
    {
        return [
            'missing_first_name' => [
                ['last_name' => 'Doe', 'national_id' => '12345678901234'],
                ['first_name']
            ],
            'missing_last_name' => [
                ['first_name' => 'John', 'national_id' => '12345678901234'],
                ['last_name']
            ],
            'missing_national_id' => [
                ['first_name' => 'John', 'last_name' => 'Doe'],
                ['national_id']
            ],
            'invalid_email' => [
                ['email' => 'invalid-email'],
                ['email']
            ],
            'duplicate_national_id' => [
                ['national_id' => '12345678901234'],
                ['national_id']
            ],
        ];
    }

    /**
     * @dataProvider validationErrorDataProvider
     */
    public function test_client_create_validation_errors(array $invalidData, array $expectedErrors)
    {
        $this->authenticateAs('reception_employee');

        $address = Address::factory()->create();

        $baseData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'national_id' => '12345678901234',
            'address' => [
                'street' => $address->street,
                'building' => $address->building,
                'city_id' => $address->city_id,
            ],
            'phones' => [
                ['phone_number' => '+201234567890', 'phone_type' => 'mobile']
            ]
        ];

        $data = array_merge($baseData, $invalidData);

        $response = $this->postJson('/api/v1/clients', $data);

        $this->assertValidationError($response, $expectedErrors);
    }
}
