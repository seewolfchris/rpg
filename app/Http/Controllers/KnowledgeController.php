<?php

namespace App\Http\Controllers;

use App\Models\EncyclopediaCategory;
use App\Models\EncyclopediaEntry;
use App\Support\EncyclopediaContentRenderer;
use App\Support\EncyclopediaEntryMetaBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeController extends Controller
{
    public function index(): View
    {
        return view('knowledge.index');
    }

    public function howToPlay(): View
    {
        return view('knowledge.how-to-play');
    }

    public function rules(): View
    {
        return view('knowledge.rules');
    }

    public function encyclopedia(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $selectedCategorySlug = trim((string) $request->query('k', ''));

        $availableCategories = EncyclopediaCategory::query()
            ->visible()
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $categories = EncyclopediaCategory::query()
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
            'categories',
            'availableCategories',
            'search',
            'selectedCategorySlug',
            'initialFilters',
            'canManage',
        ));
    }

    public function encyclopediaEntry(
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
            ->with([
                'category:id,name,slug,summary',
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
        $crossLinks = $entryMetaBuilder->extractInternalLinks($entry->content);
        $imagePrompts = $entryMetaBuilder->buildImagePrompts($entry);
        $canManage = $request->user()?->isGmOrAdmin() ?? false;

        return view('knowledge.encyclopedia-entry', compact(
            'entry',
            'relatedEntries',
            'renderedContent',
            'crossLinks',
            'imagePrompts',
            'canManage',
        ));
    }
}
