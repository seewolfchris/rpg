<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class RelativeTimeComponentTest extends TestCase
{
    public function test_component_renders_relative_and_absolute_timestamp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-17 12:00:00'));
        $this->app->setLocale('de');

        try {
            $at = Carbon::parse('2026-03-17 09:00:00');
            $html = (string) view('components.relative-time', ['at' => $at])->render();

            $this->assertStringContainsString('datetime="'.$at->toIso8601String().'"', $html);
            $this->assertStringContainsString('title="17.03.2026 09:00"', $html);
            $this->assertStringContainsString('vor 3 Stunden', $html);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_component_renders_fallback_for_missing_timestamp(): void
    {
        $html = (string) view('components.relative-time', ['at' => null])->render();

        $this->assertStringContainsString('<span>-</span>', $html);
    }
}
