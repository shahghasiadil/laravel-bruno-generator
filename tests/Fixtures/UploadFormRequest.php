<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class UploadFormRequest extends FormRequest
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
            'title' => 'required|string',
            'attachment' => 'required|file|max:2048',
            'tags' => 'array',
            'tags.*' => 'string',
            'meta.label' => 'string',
        ];
    }
}
