<?php

declare(strict_types=1);

namespace App\Actions\Knowledge;

use App\Models\World;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

class LoadActiveWorldCatalogAction
{
    /**
     * @return EloquentCollection<int, World>
     */
    public function execute(): EloquentCollection
    {
        if (! Schema::hasTable('worlds')) {
            return new EloquentCollection;
        }

        $query = World::query()
            ->active()
            ->ordered();

        if (Schema::hasTable('campaigns')) {
            $query->withCount('campaigns');
        }

        return $query->get([
            'id',
            'name',
            'slug',
            'tagline',
            'description',
        ]);
    }
}
