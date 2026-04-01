<?php

declare(strict_types=1);

namespace App\Data\Character;

use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class CreateCharacterInput
{
    public function __construct(
        public User $actor,
        /**
         * @var array<string, mixed>&array{
         *     advantages?: list<string>,
         *     disadvantages?: list<string>,
         *     origin?: string,
         *     species?: string,
         *     calling?: string,
         *     calling_custom_name?: string|null,
         *     calling_custom_description?: string|null,
         *     concept?: string|null,
         *     gm_secret?: string|null,
         *     world_connection?: string|null,
         *     gm_note?: string|null
         * }
         */
        public array $payload,
        public ?UploadedFile $avatar = null,
    ) {}
}
