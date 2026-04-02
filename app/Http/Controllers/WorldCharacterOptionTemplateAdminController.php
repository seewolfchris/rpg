<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\WorldCharacterOptions\ImportWorldCharacterOptionTemplateRequest;
use App\Models\World;
use App\Support\WorldCharacterOptionTemplateService;
use Illuminate\Http\RedirectResponse;

class WorldCharacterOptionTemplateAdminController extends Controller
{
    public function __construct(
        private readonly WorldCharacterOptionTemplateService $templateService,
    ) {}

    public function importTemplate(ImportWorldCharacterOptionTemplateRequest $request, World $world): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->templateService->importTemplate($world, (string) $validated['template_key']);

        return redirect()
            ->route('admin.worlds.edit', $world)
            ->with('status', 'Vorlage importiert: '.$result['species'].' Spezies, '.$result['callings'].' Berufungen.');
    }
}
