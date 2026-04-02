<?php

namespace App\Http\Requests\WorldCharacterOptions;

use App\Models\World;
use Illuminate\Foundation\Http\FormRequest;

abstract class WorldCharacterOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    protected function worldId(): int
    {
        $world = $this->route('world');

        if ($world instanceof World) {
            return (int) $world->id;
        }

        return (int) $this->input('world_id', 0);
    }
}
