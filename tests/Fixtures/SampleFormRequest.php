<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class SampleFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'integer|min:18',
            'is_active' => 'boolean',
            'website' => 'url',
            'uuid' => 'uuid',
            'birth_date' => 'date',
            'tags' => 'array',
            'tags.*' => 'string',
            'user.name' => 'required|string',
            'user.email' => 'required|email',
            'settings.notifications.email' => 'boolean',
            'price' => 'numeric|min:0',
            'description' => 'string|nullable',
        ];
    }
}
