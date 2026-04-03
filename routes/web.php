<?php

require __DIR__.'/web/public.php';
require __DIR__.'/web/world.php';
require __DIR__.'/web/guest.php';
require __DIR__.'/web/authenticated.php';

if (app()->environment(['local', 'testing'])) {
    require __DIR__.'/web/e2e.php';
}
