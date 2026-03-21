<?php

use App\Models\World;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_species', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('world_id')->constrained('worlds')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->json('modifiers_json')->nullable();
            $table->integer('le_bonus')->default(0);
            $table->integer('ae_bonus')->default(0);
            $table->boolean('is_magic_capable')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_template')->default(true);
            $table->timestamps();

            $table->unique(['world_id', 'key']);
            $table->index(['world_id', 'is_active', 'position']);
        });

        Schema::create('world_callings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('world_id')->constrained('worlds')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->json('minimums_json')->nullable();
            $table->json('bonuses_json')->nullable();
            $table->boolean('is_magic_capable')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_template')->default(true);
            $table->timestamps();

            $table->unique(['world_id', 'key']);
            $table->index(['world_id', 'is_active', 'position']);
        });

        $this->seedDefaultWorldOptions();
    }

    public function down(): void
    {
        Schema::dropIfExists('world_callings');
        Schema::dropIfExists('world_species');
    }

    private function seedDefaultWorldOptions(): void
    {
        /** @var array<string, mixed> $config */
        $config = config('world_character_options', []);

        /** @var array<string, array<string, mixed>> $speciesCatalog */
        $speciesCatalog = (array) ($config['species'] ?? []);
        /** @var array<string, array<string, mixed>> $callingCatalog */
        $callingCatalog = (array) ($config['callings'] ?? []);
        /** @var array<string, array<string, mixed>> $templates */
        $templates = (array) ($config['templates'] ?? []);

        if ($speciesCatalog === [] || $callingCatalog === [] || $templates === []) {
            return;
        }

        $worlds = World::query()->get(['id', 'slug']);
        $now = now();

        foreach ($worlds as $world) {
            $template = $templates[$world->slug] ?? null;
            if (! is_array($template)) {
                continue;
            }

            $speciesKeys = array_values(array_filter(
                (array) ($template['species'] ?? []),
                static fn ($value): bool => is_string($value) && $value !== ''
            ));
            $callingKeys = array_values(array_filter(
                (array) ($template['callings'] ?? []),
                static fn ($value): bool => is_string($value) && $value !== ''
            ));

            $speciesRows = [];
            foreach ($speciesKeys as $index => $speciesKey) {
                $species = $speciesCatalog[$speciesKey] ?? null;
                if (! is_array($species)) {
                    continue;
                }

                $speciesRows[] = [
                    'world_id' => (int) $world->id,
                    'key' => $speciesKey,
                    'label' => (string) ($species['label'] ?? ucfirst($speciesKey)),
                    'description' => (string) ($species['description'] ?? ''),
                    'modifiers_json' => json_encode((array) ($species['modifiers'] ?? []), JSON_UNESCAPED_UNICODE),
                    'le_bonus' => (int) ($species['le_bonus'] ?? 0),
                    'ae_bonus' => (int) ($species['ae_bonus'] ?? 0),
                    'is_magic_capable' => (bool) ($species['is_magic_capable'] ?? false),
                    'position' => ($index + 1) * 10,
                    'is_active' => true,
                    'is_template' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($speciesRows !== []) {
                DB::table('world_species')->insert($speciesRows);
            }

            $callingRows = [];
            foreach ($callingKeys as $index => $callingKey) {
                $calling = $callingCatalog[$callingKey] ?? null;
                if (! is_array($calling)) {
                    continue;
                }

                $callingRows[] = [
                    'world_id' => (int) $world->id,
                    'key' => $callingKey,
                    'label' => (string) ($calling['label'] ?? ucfirst($callingKey)),
                    'description' => (string) ($calling['description'] ?? ''),
                    'minimums_json' => json_encode((array) ($calling['minimums'] ?? []), JSON_UNESCAPED_UNICODE),
                    'bonuses_json' => json_encode((array) ($calling['bonuses'] ?? []), JSON_UNESCAPED_UNICODE),
                    'is_magic_capable' => (bool) ($calling['is_magic_capable'] ?? false),
                    'is_custom' => (bool) ($calling['is_custom'] ?? false),
                    'position' => ($index + 1) * 10,
                    'is_active' => true,
                    'is_template' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($callingRows !== []) {
                DB::table('world_callings')->insert($callingRows);
            }
        }
    }
};
