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
         *     gm_note?: string|null,
         *     mu_note?: string|null,
         *     kl_note?: string|null,
         *     in_note?: string|null,
         *     ch_note?: string|null,
         *     ff_note?: string|null,
         *     ge_note?: string|null,
         *     ko_note?: string|null,
         *     kk_note?: string|null,
         *     inventory?: list<array{
         *         name: string,
         *         quantity: int,
         *         equipped?: bool|null
         *     }>|null,
         *     armors?: list<array{
         *         name: string,
         *         protection: int,
         *         equipped?: bool|null
         *     }>|null,
         *     weapons?: list<array{
         *         name: string,
         *         attack: int,
         *         parry: int,
         *         damage: int
         *     }>|null
         * }
         */
        public array $payload,
        public ?UploadedFile $avatar = null,
    ) {}
}
