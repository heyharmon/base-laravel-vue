<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:pages,slug',
            'intro' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:160',
            'published_at' => 'nullable|date',
            'content_blocks' => 'array',
            'content_blocks.*.type' => 'required|in:text,image,cta',
            'content_blocks.*.data' => 'required|array',
        ];
    }
}
