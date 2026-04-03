<?php

declare(strict_types=1);

use App\Actions\World\UpdateWorldAction;
use App\Models\World;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

$rootPath = dirname(__DIR__, 3);

require $rootPath.'/vendor/autoload.php';

$app = require $rootPath.'/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$operation = (string) ($argv[1] ?? '');
$worldId = (int) ($argv[2] ?? 0);
$payload = json_decode((string) ($argv[3] ?? '{}'), true);
$delayMs = max(0, (int) ($argv[4] ?? 0));
$startedAt = microtime(true);

if ($delayMs > 0) {
    usleep($delayMs * 1000);
}

try {
    /** @var World $world */
    $world = World::query()->findOrFail($worldId);
    /** @var UpdateWorldAction $action */
    $action = $app->make(UpdateWorldAction::class);

    if ($operation === 'toggle') {
        $action->toggleActive($world);
    } elseif ($operation === 'update') {
        if (! is_array($payload)) {
            $payload = [];
        }

        $action->execute($world, $payload);
    } else {
        throw new InvalidArgumentException('Unsupported operation: '.$operation);
    }

    echo json_encode([
        'status' => 'ok',
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);

    exit(0);
} catch (ValidationException $exception) {
    echo json_encode([
        'status' => 'validation',
        'errors' => $exception->errors(),
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);

    exit(20);
} catch (Throwable $exception) {
    echo json_encode([
        'status' => 'error',
        'message' => $exception->getMessage(),
        'class' => $exception::class,
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);

    exit(99);
}

