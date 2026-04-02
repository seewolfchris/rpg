<?php

namespace App\Http\Requests\Post;

use App\Models\PostReaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::in(PostReaction::ALLOWED_EMOJIS)],
        ];
    }
}
