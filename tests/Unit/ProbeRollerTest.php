<?php

namespace Tests\Unit;

use App\Models\DiceRoll;
use App\Support\ProbeRoller;
use Closure;
use Tests\TestCase;

class ProbeRollerTest extends TestCase
{
    public function test_roll_marks_one_as_critical_success_and_hundred_as_critical_failure_in_normal_mode(): void
    {
        $roller = new ProbeRoller($this->sequenceGenerator([1, 100]));

        $first = $roller->roll(DiceRoll::MODE_NORMAL, 0);
        $this->assertSame(1, $first['kept_roll']);
        $this->assertTrue($first['critical_success']);
        $this->assertFalse($first['critical_failure']);

        $second = $roller->roll(DiceRoll::MODE_NORMAL, 0);
        $this->assertSame(100, $second['kept_roll']);
        $this->assertFalse($second['critical_success']);
        $this->assertTrue($second['critical_failure']);
    }

    public function test_critical_flags_are_based_on_kept_roll_for_advantage_and_disadvantage(): void
    {
        $roller = new ProbeRoller($this->sequenceGenerator([1, 100, 100, 1]));

        $advantage = $roller->roll(DiceRoll::MODE_ADVANTAGE, 0);
        $this->assertSame(1, $advantage['kept_roll']);
        $this->assertTrue($advantage['critical_success']);
        $this->assertFalse($advantage['critical_failure']);

        $disadvantage = $roller->roll(DiceRoll::MODE_DISADVANTAGE, 0);
        $this->assertSame(100, $disadvantage['kept_roll']);
        $this->assertFalse($disadvantage['critical_success']);
        $this->assertTrue($disadvantage['critical_failure']);
    }

    /**
     * @param  list<int>  $rolls
     * @return Closure(): int
     */
    private function sequenceGenerator(array $rolls): Closure
    {
        return static function () use (&$rolls): int {
            $next = array_shift($rolls);

            return is_int($next) ? $next : 1;
        };
    }
}
