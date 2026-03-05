<?php

namespace App\Support;

use App\Models\Character;
use App\Models\CharacterInventoryLog;

class CharacterInventoryService
{
    /**
     * @param  mixed  $entries
     * @return array<int, array{name: string, quantity: int, equipped: bool}>
     */
    public function normalize(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        $normalizedByKey = [];
        $order = [];

        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $name = trim($entry);
                $quantity = 1;
                $equipped = false;
            } elseif (is_array($entry)) {
                $name = trim((string) ($entry['name'] ?? $entry['item'] ?? ''));
                $rawQuantity = $entry['quantity'] ?? $entry['qty'] ?? 1;
                $quantity = is_numeric($rawQuantity) ? (int) $rawQuantity : 1;
                $equipped = (bool) ($entry['equipped'] ?? false);
            } else {
                continue;
            }

            if ($name === '') {
                continue;
            }

            $quantity = $this->clampQuantity($quantity);
            $key = $this->inventoryKey($name, $equipped);

            if (! array_key_exists($key, $normalizedByKey)) {
                $normalizedByKey[$key] = [
                    'name' => $name,
                    'quantity' => $quantity,
                    'equipped' => $equipped,
                ];
                $order[] = $key;
                continue;
            }

            $normalizedByKey[$key]['quantity'] = $this->clampQuantity(
                (int) $normalizedByKey[$key]['quantity'] + $quantity
            );
        }

        $result = [];
        foreach ($order as $key) {
            $result[] = $normalizedByKey[$key];
        }

        return $result;
    }

    /**
     * @param  array<int, array{name: string, quantity: int, equipped: bool}>  $inventory
     * @return array<int, array{name: string, quantity: int, equipped: bool}>
     */
    public function add(array $inventory, string $name, int $quantity = 1, bool $equipped = false): array
    {
        $current = $this->normalize($inventory);
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return $current;
        }

        $quantity = $this->clampQuantity($quantity);
        $needleKey = $this->inventoryKey($normalizedName, $equipped);

        foreach ($current as &$entry) {
            if ($this->inventoryKey($entry['name'], (bool) $entry['equipped']) !== $needleKey) {
                continue;
            }

            $entry['quantity'] = $this->clampQuantity((int) $entry['quantity'] + $quantity);

            return $current;
        }
        unset($entry);

        $current[] = [
            'name' => $normalizedName,
            'quantity' => $quantity,
            'equipped' => $equipped,
        ];

        return $this->normalize($current);
    }

    /**
     * @param  array<int, array{name: string, quantity: int, equipped: bool}>  $inventory
     * @return array{inventory: array<int, array{name: string, quantity: int, equipped: bool}>, removed: int, removed_equipped: bool|null}
     */
    public function remove(array $inventory, string $name, int $quantity = 1): array
    {
        $current = $this->normalize($inventory);
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            return [
                'inventory' => $current,
                'removed' => 0,
                'removed_equipped' => null,
            ];
        }

        $remaining = $this->clampQuantity($quantity);
        $removed = 0;
        $removedEquipped = null;

        foreach ($current as $index => $entry) {
            if (strcasecmp((string) $entry['name'], $normalizedName) !== 0) {
                continue;
            }

            if ($removedEquipped === null) {
                $removedEquipped = (bool) $entry['equipped'];
            }

            $stackQty = (int) $entry['quantity'];
            $delta = min($stackQty, $remaining);
            $stackQty -= $delta;
            $removed += $delta;
            $remaining -= $delta;

            if ($stackQty <= 0) {
                unset($current[$index]);
            } else {
                $current[$index]['quantity'] = $stackQty;
            }

            if ($remaining <= 0) {
                break;
            }
        }

        return [
            'inventory' => array_values($current),
            'removed' => $removed,
            'removed_equipped' => $removedEquipped,
        ];
    }

    /**
     * @param  array<int, array{name: string, quantity: int, equipped: bool}>  $before
     * @param  array<int, array{name: string, quantity: int, equipped: bool}>  $after
     * @return array<int, array{action: string, item_name: string, quantity: int, equipped: bool}>
     */
    public function diff(array $before, array $after): array
    {
        $normalizedBefore = $this->normalize($before);
        $normalizedAfter = $this->normalize($after);

        $beforeByKey = [];
        foreach ($normalizedBefore as $entry) {
            $key = $this->inventoryKey($entry['name'], (bool) $entry['equipped']);
            $beforeByKey[$key] = $entry;
        }

        $afterByKey = [];
        foreach ($normalizedAfter as $entry) {
            $key = $this->inventoryKey($entry['name'], (bool) $entry['equipped']);
            $afterByKey[$key] = $entry;
        }

        $operations = [];
        $keys = array_unique(array_merge(array_keys($beforeByKey), array_keys($afterByKey)));

        foreach ($keys as $key) {
            $beforeEntry = $beforeByKey[$key] ?? null;
            $afterEntry = $afterByKey[$key] ?? null;

            $beforeQty = (int) ($beforeEntry['quantity'] ?? 0);
            $afterQty = (int) ($afterEntry['quantity'] ?? 0);

            if ($afterQty > $beforeQty) {
                $operations[] = [
                    'action' => 'add',
                    'item_name' => (string) ($afterEntry['name'] ?? $beforeEntry['name'] ?? ''),
                    'quantity' => $afterQty - $beforeQty,
                    'equipped' => (bool) ($afterEntry['equipped'] ?? $beforeEntry['equipped'] ?? false),
                ];
            } elseif ($beforeQty > $afterQty) {
                $operations[] = [
                    'action' => 'remove',
                    'item_name' => (string) ($beforeEntry['name'] ?? $afterEntry['name'] ?? ''),
                    'quantity' => $beforeQty - $afterQty,
                    'equipped' => (bool) ($beforeEntry['equipped'] ?? $afterEntry['equipped'] ?? false),
                ];
            }
        }

        return array_values(array_filter($operations, static fn (array $operation): bool => $operation['item_name'] !== ''));
    }

    /**
     * @param  array<int, array{action: string, item_name: string, quantity: int, equipped: bool}>  $operations
     * @param  array<string, mixed>  $context
     */
    public function log(
        Character $character,
        ?int $actorUserId,
        string $source,
        array $operations,
        ?string $note = null,
        array $context = [],
    ): void {
        if ($operations === []) {
            return;
        }

        foreach ($operations as $operation) {
            $itemName = trim((string) ($operation['item_name'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            CharacterInventoryLog::query()->create([
                'character_id' => $character->id,
                'actor_user_id' => $actorUserId,
                'source' => $source,
                'action' => (string) ($operation['action'] ?? 'add'),
                'item_name' => $itemName,
                'quantity' => $this->clampQuantity((int) ($operation['quantity'] ?? 1)),
                'equipped' => (bool) ($operation['equipped'] ?? false),
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'context' => $context !== [] ? $context : null,
                'created_at' => now(),
            ]);
        }
    }

    private function clampQuantity(int $value): int
    {
        return max(1, min(999, $value));
    }

    private function inventoryKey(string $name, bool $equipped): string
    {
        return mb_strtolower(trim($name)).'|'.($equipped ? '1' : '0');
    }
}
