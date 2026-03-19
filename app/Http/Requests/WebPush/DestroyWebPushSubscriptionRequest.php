<?php

namespace App\Http\Requests\WebPush;

use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroyWebPushSubscriptionRequest extends FormRequest
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
            'world_slug' => [
                'required',
                'string',
                Rule::exists('worlds', 'slug')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'endpoint' => ['required', 'string', 'url', 'max:500'],
        ];
    }

    public function world(): World
    {
        return World::query()
            ->where('slug', $this->validated('world_slug'))
            ->where('is_active', true)
            ->firstOrFail();
    }

    public function endpoint(): string
    {
        return (string) $this->validated('endpoint');
    }
}
