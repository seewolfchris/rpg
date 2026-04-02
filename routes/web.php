<?php

use App\Models\World;
use Illuminate\Http\Request;

$resolveWorldSlug = static function (Request $request): string {
    $sessionSlug = $request->session()->get('world_slug');

    if (is_string($sessionSlug) && $sessionSlug !== '') {
        return $sessionSlug;
    }

    return World::defaultSlug();
};

require __DIR__.'/web/public.php';
require __DIR__.'/web/world.php';
require __DIR__.'/web/guest.php';
require __DIR__.'/web/authenticated.php';

if (app()->environment(['local', 'testing'])) {
    require __DIR__.'/web/e2e.php';
}
