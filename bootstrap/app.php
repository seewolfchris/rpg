<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\ApplyWorldContext;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ProtectAgainstCrawlers;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            at: static function (): array {
                $appUrl = config('app.url');
                $host = is_string($appUrl) ? parse_url($appUrl, PHP_URL_HOST) : null;

                if (! is_string($host) || $host === '') {
                    return ['(?!)'];
                }

                return ['^'.preg_quote($host).'$'];
            },
            subdomains: false,
        );
        $middleware->append(TrustProxies::class);
        $middleware->append(ApplySecurityHeaders::class);

        $middleware->web(append: [
            AttachRequestId::class,
            ProtectAgainstCrawlers::class,
            ApplyWorldContext::class,
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo('/dashboard');
        $middleware->alias([
            'role' => EnsureRole::class,
            'active-account' => EnsureActiveAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
