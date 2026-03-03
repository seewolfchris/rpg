<?php

namespace App\Http\Requests\Character;

class StoreCharacterRequest extends CharacterSheetRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function extraRules(): array
    {
        return [];
    }
}
