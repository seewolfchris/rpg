<?php

namespace App\Support;

use Throwable;

final class SensitiveFeatureGate
{
    public static function enabled(string $featureKey, bool $default = false): bool
    {
        $fallback = (bool) config($featureKey, $default);

        if (! class_exists(\Laravel\Pennant\Feature::class)) {
            return $fallback;
        }

        try {
            return (bool) \Laravel\Pennant\Feature::active($featureKey);
        } catch (Throwable) {
            return $fallback;
        }
    }
}
