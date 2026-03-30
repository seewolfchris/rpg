<?php

namespace App\Http\Controllers;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Models\World;
use App\Support\EncyclopediaContentRenderer;
use App\Support\EncyclopediaEntryMetaBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const WORLD_LORE_CATEGORIES = [
        'zeitalter' => 'Zeitalter',
        'machtbloecke' => 'Machtbloecke',
        'regionen' => 'Regionen',
        'kernausdruecke' => 'Kernausdruecke',
    ];

    public function index(Request $request, ?World $world = null): View
    {
        $worlds = $world === null
            ? $this->activeWorldCatalog()
            : new EloquentCollection;

        $selectedWorldSlug = trim((string) $request->session()->get('world_slug', ''));

        return view('knowledge.index', compact('world', 'worlds', 'selectedWorldSlug'));
    }

    public function howToPlay(?World $world = null): View
    {
        return view('knowledge.how-to-play', compact('world'));
    }

    public function rules(?World $world = null): View
    {
        $rulebookSections = $this->loadGlobalRulebookSections();

        return view('knowledge.rules', compact('world', 'rulebookSections'));
    }

    public function worldOverview(World $world): View
    {
        abort_unless((bool) config('content.world_markdown_preview', false), 404);

        $worldOverviewHtml = $this->loadWorldMarkdown($world, 'weltueberblick.md');

        return view('knowledge.world-overview', compact('world', 'worldOverviewHtml'));
    }

    public function worldLore(World $world, ?string $category = null): View
    {
        abort_unless((bool) config('content.world_markdown_preview', false), 404);

        $normalizedCategory = trim((string) $category);
        [$loreTitle, $loreSections] = $this->loadWorldLoreMarkdown(
            $world,
            $normalizedCategory === '' ? null : $normalizedCategory
        );

        return view('knowledge.world-lore', compact('world', 'normalizedCategory', 'loreTitle', 'loreSections'));
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

    /**
     * @return array<string, string>
     */
    private function loadGlobalRulebookSections(): array
    {
        return [
            'grundregeln' => $this->renderRulebookMarkdown('grundregeln.md'),
            'glossar' => $this->renderRulebookMarkdown('glossar.md'),
            'abkuerzungen' => $this->renderRulebookMarkdown('abkuerzungen.md'),
        ];
    }

    private function renderRulebookMarkdown(string $filename): string
    {
        $path = base_path('docs/content/global/'.$filename);

        if (! is_file($path)) {
            return '<p class="text-stone-400 italic">Regelwerksinhalt fehlt.</p>';
        }

        $markdown = trim((string) file_get_contents($path));

        if ($markdown === '') {
            return '<p class="text-stone-400 italic">Regelwerksinhalt ist leer.</p>';
        }

        return (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    private function loadWorldMarkdown(World $world, string $relativePath): string
    {
        $path = base_path('docs/content/worlds/'.$world->slug.'/'.$relativePath);

        if (! is_file($path)) {
            return '<p class="text-stone-400 italic">Weltinhalt fehlt.</p>';
        }

        $markdown = trim((string) file_get_contents($path));

        if ($markdown === '') {
            return '<p class="text-stone-400 italic">Weltinhalt ist leer.</p>';
        }

        $markdown = ltrim((string) preg_replace('/\A---\R.*?\R---\R/s', '', $markdown));

        return (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }

    /**
     * @return array{0: string, 1: array<int, array{key: string, title: string, html: string}>}
     */
    private function loadWorldLoreMarkdown(World $world, ?string $category): array
    {
        if ($category === null) {
            return [
                'Lore-Index',
                [[
                    'key' => 'index',
                    'title' => 'Lore-Index',
                    'html' => $this->loadWorldMarkdown($world, 'lore/INDEX.md'),
                ]],
            ];
        }

        $title = self::WORLD_LORE_CATEGORIES[$category] ?? null;

        if ($title === null) {
            abort(404);
        }

        $directory = base_path('docs/content/worlds/'.$world->slug.'/lore/'.$category);
        $filenames = is_dir($directory)
            ? collect(scandir($directory) ?: [])
                ->filter(fn (string $filename): bool => Str::endsWith($filename, '.md'))
                ->sort(SORT_STRING)
                ->values()
                ->all()
            : [];

        $sections = collect($filenames)
            ->map(function (string $filename) use ($world, $category): array {
                $slug = pathinfo($filename, PATHINFO_FILENAME);

                return [
                    'key' => $slug,
                    'title' => (string) Str::headline($slug),
                    'html' => $this->loadWorldMarkdown($world, 'lore/'.$category.'/'.$filename),
                ];
            })
            ->values()
            ->all();

        if ($sections === []) {
            $sections[] = [
                'key' => 'none',
                'title' => 'Keine Eintraege',
                'html' => '<p class="text-stone-400 italic">Keine Lore-Dateien gefunden.</p>',
            ];
        }

        return [$title, $sections];
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
            ->whereIn('encyclopedia_category_id', EncyclopediaCategory::query()
                ->forWorld($world)
                ->visible()
                ->where('slug', $categorySlug)
                ->select('id'))
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
