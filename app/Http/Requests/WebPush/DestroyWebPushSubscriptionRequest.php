<?php

namespace App\Http\Requests\WebPush;

use App\Models\World;
use App\Rules\WebPushEndpointAllowed;
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
            'endpoint' => ['required', 'string', 'url:https', 'max:500', new WebPushEndpointAllowed],
            'public_key' => ['nullable', 'string', 'max:500'],
            'auth_token' => ['nullable', 'string', 'max:500'],
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

    public function publicKey(): ?string
    {
        $publicKey = trim((string) $this->validated('public_key', ''));

        return $publicKey !== '' ? $publicKey : null;
    }

    public function authToken(): ?string
    {
        $authToken = trim((string) $this->validated('auth_token', ''));

        return $authToken !== '' ? $authToken : null;
    }
}
