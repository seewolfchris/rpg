<?php

namespace Tests\Unit;

use App\Support\ConfigEnv;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

class ConfigEnvBooleanTest extends TestCase
{
    #[DataProvider('booleanCases')]
    public function test_boolean_parsing(mixed $value, bool $default, bool $expected): void
    {
        $this->assertSame($expected, ConfigEnv::boolean($value, $default));
    }

    /**
     * @return array<string, array{0:mixed, 1:bool, 2:bool}>
     */
    public static function booleanCases(): array
    {
        return [
            'bool_true' => [true, false, true],
            'bool_false' => [false, true, false],
            'string_true' => ['true', false, true],
            'string_false' => ['false', true, false],
            'string_on' => ['on', false, true],
            'string_off' => ['off', true, false],
            'string_yes' => ['yes', false, true],
            'string_no' => ['no', true, false],
            'string_one' => ['1', false, true],
            'string_zero' => ['0', true, false],
            'int_one' => [1, false, true],
            'int_zero' => [0, true, false],
            'empty_string' => ['', true, false],
            'null_uses_default' => [null, true, true],
            'invalid_string_uses_default_true' => ['garbage', true, true],
            'invalid_string_uses_default_false' => ['garbage', false, false],
            'object_uses_default' => [new stdClass, true, true],
        ];
    }
}
