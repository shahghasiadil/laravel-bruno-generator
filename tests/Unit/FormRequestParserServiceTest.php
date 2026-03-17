<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\FormRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use ShahGhasiAdil\LaravelBrunoGenerator\Exceptions\FormRequestParseException;
use ShahGhasiAdil\LaravelBrunoGenerator\Services\FormRequestParserService;
use ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures\SampleFormRequest;

beforeEach(function () {
    $this->service = new FormRequestParserService([
        'advanced' => [
            'form_request' => [
                'max_nesting_depth' => 5,
                'include_optional_fields' => true,
            ],
        ],
    ]);
});

describe('FormRequestParserService', function () {
    test('parses basic FormRequest', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body)->not->toBeNull();
        expect($body->type)->toBe(BodyType::JSON);
        expect($body->content)->toBeArray();
    });

    test('generates correct example values for string fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['name'])->toBeString();
    });

    test('generates correct example values for email fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['email'])->toBe('user@example.com');
    });

    test('generates correct example values for integer fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['age'])->toBeInt();
        expect($body->content['age'])->toBeGreaterThanOrEqual(18); // Respects min:18
    });

    test('generates correct example values for boolean fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['is_active'])->toBeBool();
    });

    test('generates correct example values for url fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['website'])->toBe('https://example.com');
    });

    test('generates correct example values for uuid fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['uuid'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    test('generates correct example values for date fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['birth_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    });

    test('generates correct example values for array fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['tags'])->toBeArray();
        expect($body->content['tags'])->toHaveCount(1);
        expect($body->content['tags'][0])->toBeString();
    });

    test('handles nested fields correctly', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content)->toHaveKey('user');
        expect($body->content['user'])->toBeArray();
        expect($body->content['user'])->toHaveKey('name');
        expect($body->content['user'])->toHaveKey('email');
    });

    test('handles deeply nested fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content)->toHaveKey('settings');
        expect($body->content['settings'])->toHaveKey('notifications');
        expect($body->content['settings']['notifications'])->toHaveKey('email');
        expect($body->content['settings']['notifications']['email'])->toBeBool();
    });

    test('generates correct example values for numeric fields', function () {
        $body = $this->service->parse(SampleFormRequest::class);

        expect($body->content['price'])->toBeNumeric();
        expect($body->content['price'])->toBeGreaterThanOrEqual(0);
    });

    test('includes nullable fields when configured', function () {
        $service = new FormRequestParserService([
            'advanced' => [
                'form_request' => [
                    'max_nesting_depth' => 5,
                    'include_optional_fields' => true,
                ],
            ],
        ]);

        $body = $service->parse(SampleFormRequest::class);

        expect($body->content)->toHaveKey('description');
    });

    test('excludes nullable fields when configured', function () {
        $service = new FormRequestParserService([
            'advanced' => [
                'form_request' => [
                    'max_nesting_depth' => 5,
                    'include_optional_fields' => false,
                ],
            ],
        ]);

        $body = $service->parse(SampleFormRequest::class);

        expect($body->content)->not->toHaveKey('description');
    });

    test('respects maximum nesting depth', function () {
        $service = new FormRequestParserService([
            'advanced' => [
                'form_request' => [
                    'max_nesting_depth' => 2,
                    'include_optional_fields' => true,
                ],
            ],
        ]);

        // Should not throw exception even with deep nesting
        $body = $service->parse(SampleFormRequest::class);

        expect($body)->not->toBeNull();
    });

    test('returns null for non-existent class', function () {
        expect(function () {
            $this->service->parse('NonExistentClass');
        })->toThrow(FormRequestParseException::class);
    });

    test('returns null for non-FormRequest class', function () {
        expect(function () {
            $this->service->parse(stdClass::class);
        })->toThrow(FormRequestParseException::class);
    });

    test('infers field types from field names when rules are generic', function () {
        $class = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'user_email' => 'string',
                    'is_admin' => 'string',
                    'created_at' => 'string',
                    'user_id' => 'string',
                ];
            }
        };

        $body = $this->service->parse(get_class($class));

        expect($body->content['user_email'])->toBe('user@example.com'); // Inferred from name
        expect($body->content['is_admin'])->toBeBool(); // Inferred from name
        expect($body->content['user_id'])->toBeInt(); // Inferred from name
    });

    test('handles array rules format', function () {
        $class = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email'],
                ];
            }
        };

        $body = $this->service->parse(get_class($class));

        expect($body->content)->toHaveKey('name');
        expect($body->content)->toHaveKey('email');
        expect($body->content['email'])->toBe('user@example.com');
    });

    test('respects min value in rules', function () {
        $class = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'age' => 'integer|min:21',
                    'price' => 'numeric|min:9.99',
                ];
            }
        };

        $body = $this->service->parse(get_class($class));

        expect($body->content['age'])->toBe(21);
        expect($body->content['price'])->toBe(9.99);
    });

    test('handles max length in string rules', function () {
        $class = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'short_text' => 'string|max:10',
                ];
            }
        };

        $body = $this->service->parse(get_class($class));

        expect(strlen($body->content['short_text']))->toBeLessThanOrEqual(10);
    });
});
