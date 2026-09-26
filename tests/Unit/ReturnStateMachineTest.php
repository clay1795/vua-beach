<?php

namespace Tests\Unit;

use App\Services\ReturnStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReturnStateMachineTest extends TestCase
{
    #[DataProvider('validTransitions')]
    public function test_every_defined_return_transition_is_allowed(string $from, string $to): void
    {
        $this->assertTrue((new ReturnStateMachine)->canTransition($from, $to));
    }

    #[DataProvider('invalidTransitions')]
    public function test_every_undefined_return_transition_is_rejected(string $from, string $to): void
    {
        $this->assertFalse((new ReturnStateMachine)->canTransition($from, $to), "Unexpectedly allowed {$from} -> {$to}");
    }

    public static function invalidTransitions(): array
    {
        $states = array_keys(ReturnStateMachine::TRANSITIONS);
        $cases = [];
        foreach ($states as $from) {
            foreach ($states as $to) {
                if ($from !== $to && ! in_array($to, ReturnStateMachine::TRANSITIONS[$from], true)) {
                    $cases["{$from}_to_{$to}"] = [$from, $to];
                }
            }
        }

        return $cases;
    }

    public static function validTransitions(): array
    {
        $cases = [];
        foreach (ReturnStateMachine::TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $cases["{$from}_to_{$to}"] = [$from, $to];
            }
        }

        return $cases;
    }
}
