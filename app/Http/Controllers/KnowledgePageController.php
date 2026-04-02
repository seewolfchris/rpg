<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Knowledge\LoadActiveWorldCatalogAction;
use App\Models\World;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgePageController extends Controller
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

    public function __construct(
        private readonly LoadActiveWorldCatalogAction $loadActiveWorldCatalogAction,
    ) {}

    public function index(Request $request, ?World $world = null): View
    {
        $worlds = $world === null
            ? $this->loadActiveWorldCatalogAction->execute()
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
}
