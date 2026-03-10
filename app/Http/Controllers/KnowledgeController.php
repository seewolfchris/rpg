<?php

namespace App\Http\Controllers;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use App\Support\EncyclopediaContentRenderer;
use App\Support\EncyclopediaEntryMetaBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function index(Request $request, ?World $world = null): View
    {
        $worlds = $world === null
            ? $this->activeWorldCatalog()
            : new EloquentCollection();

        $selectedWorldSlug = trim((string) $request->session()->get('world_slug', ''));

        return view('knowledge.index', compact('world', 'worlds', 'selectedWorldSlug'));
    }

    public function howToPlay(?World $world = null): View
    {
        return view('knowledge.how-to-play', compact('world'));
    }

    public function rules(?World $world = null): View
    {
        return view('knowledge.rules', compact('world'));
    }

    public function encyclopedia(Request $request, ?World $world = null): View
    {
        if ($world === null) {
            $worlds = $this->activeWorldCatalog();
            $selectedWorldSlug = trim((string) $request->session()->get('world_slug', ''));

            return view('knowledge.encyclopedia-worlds', compact('worlds', 'selectedWorldSlug'));
        }

        $search = trim((string) $request->query('q', ''));
        $selectedCategorySlug = trim((string) $request->query('k', ''));

        $availableCategories = EncyclopediaCategory::query()
            ->forWorld($world)
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $categories = EncyclopediaCategory::query()
            ->forWorld($world)
            ->visible()
            ->when(
                $selectedCategorySlug !== '',
                fn ($query) => $query->where('slug', $selectedCategorySlug)
            )
            ->with([
                'entries' => function ($query) use ($search): void {
                    $query
                        ->published()
                        ->select([
                            'id',
                            'encyclopedia_category_id',
                            'title',
                            'slug',
                            'excerpt',
                            'published_at',
                            'position',
                        ])
                        ->orderBy('position')
                        ->orderBy('title')
                        ->when($search !== '', function ($searchQuery) use ($search): void {
                            $term = '%'.$search.'%';
                            $searchQuery->where(function ($whereQuery) use ($term): void {
                                $whereQuery
                                    ->where('title', 'like', $term)
                                    ->orWhere('excerpt', 'like', $term)
                                    ->orWhere('content', 'like', $term);
                            });
                        });
                },
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (EncyclopediaCategory $category): bool => $category->entries->isNotEmpty())
            ->values();

        $canManage = $request->user()?->isGmOrAdmin() ?? false;
        $initialFilters = [
            'search' => $search,
            'category' => $selectedCategorySlug,
        ];

        return view('knowledge.encyclopedia', compact(
            'world',
            'categories',
            'availableCategories',
            'search',
            'selectedCategorySlug',
            'initialFilters',
            'canManage',
        ));
    }

    /**
     * @return EloquentCollection<int, World>
     */
    private function activeWorldCatalog(): EloquentCollection
    {
        if (! Schema::hasTable('worlds')) {
            return new EloquentCollection();
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

    public function encyclopediaEntry(
        World $world,
        string $categorySlug,
        string $entrySlug,
        Request $request,
        EncyclopediaContentRenderer $contentRenderer,
        EncyclopediaEntryMetaBuilder $entryMetaBuilder
    ): View {
        $entry = EncyclopediaEntry::query()
            ->published()
            ->where('slug', $entrySlug)
            ->whereHas('category', function ($query) use ($categorySlug): void {
                $query
                    ->visible()
                    ->where('slug', $categorySlug);
            })
            ->whereHas('category', function ($query) use ($world): void {
                $query->where('world_id', $world->id);
            })
            ->with([
                'category:id,world_id,name,slug,summary',
            ])
            ->firstOrFail();

        $relatedEntries = EncyclopediaEntry::query()
            ->published()
            ->where('encyclopedia_category_id', $entry->encyclopedia_category_id)
            ->whereKeyNot($entry->id)
            ->orderBy('position')
            ->orderBy('title')
            ->limit(6)
            ->get(['id', 'encyclopedia_category_id', 'title', 'slug']);

        $renderedContent = $contentRenderer->render($entry->content);
        $crossLinks = collect($entryMetaBuilder->extractInternalLinks($entry->content))
            ->map(function (array $link) use ($world): array {
                $categorySlug = trim((string) Arr::get($link, 'category', ''));
                $entrySlug = trim((string) Arr::get($link, 'slug', ''));

                if ($categorySlug !== '' && $entrySlug !== '') {
                    $link['url'] = route('knowledge.encyclopedia.entry', [
                        'world' => $world,
                        'categorySlug' => $categorySlug,
                        'entrySlug' => $entrySlug,
                    ]);
                }

                return $link;
            })
            ->values()
            ->all();
        $imagePrompts = $entryMetaBuilder->buildImagePrompts($entry);
        $canManage = $request->user()?->isGmOrAdmin() ?? false;

        return view('knowledge.encyclopedia-entry', compact(
            'world',
            'entry',
            'relatedEntries',
            'renderedContent',
            'crossLinks',
            'imagePrompts',
            'canManage',
        ));
    }
}
