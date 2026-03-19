<?php

namespace App\Http\Requests\WebPush;

use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebPushSubscriptionRequest extends FormRequest
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
            'public_key' => ['required', 'string', 'max:500'],
            'auth_token' => ['required', 'string', 'max:500'],
            'content_encoding' => ['nullable', 'string', Rule::in(['aesgcm', 'aes128gcm'])],
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

    public function publicKey(): string
    {
        return (string) $this->validated('public_key');
    }

    public function authToken(): string
    {
        return (string) $this->validated('auth_token');
    }

    public function contentEncoding(): string
    {
        return (string) $this->validated('content_encoding', 'aes128gcm');
    }
}
