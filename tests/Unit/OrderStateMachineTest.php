<?php

namespace Tests\Unit;

use App\Services\OrderStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderStateMachineTest extends TestCase
{
    #[DataProvider('validOrderTransitions')]
    public function test_valid_order_transitions_are_explicitly_allowed(string $from, string $to): void
    {
        $this->assertTrue((new OrderStateMachine)->canTransition($from, $to));
    }

    #[DataProvider('invalidOrderTransitions')]
    public function test_every_undefined_order_transition_is_rejected(string $from, string $to): void
    {
        $this->assertFalse((new OrderStateMachine)->canTransition($from, $to), "Unexpectedly allowed {$from} -> {$to}");
    }

    #[DataProvider('invalidPaymentTransitions')]
    public function test_every_undefined_payment_transition_is_rejected(string $from, string $to): void
    {
        $this->assertFalse((new OrderStateMachine)->canTransitionPayment($from, $to), "Unexpectedly allowed {$from} -> {$to}");
    }

    #[DataProvider('validPaymentTransitions')]
    public function test_valid_payment_transitions_are_explicitly_allowed(string $from, string $to): void
    {
        $this->assertTrue((new OrderStateMachine)->canTransitionPayment($from, $to));
    }

    public static function validOrderTransitions(): array
    {
        $cases = [];
        foreach (OrderStateMachine::ORDER_TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $cases["{$from}_to_{$to}"] = [$from, $to];
            }
        }

        return $cases;
    }

    public static function invalidOrderTransitions(): array
    {
        return self::undefinedTransitions(OrderStateMachine::ORDER_TRANSITIONS);
    }

    public static function invalidPaymentTransitions(): array
    {
        return self::undefinedTransitions(OrderStateMachine::PAYMENT_TRANSITIONS);
    }

    public static function validPaymentTransitions(): array
    {
        return self::definedTransitions(OrderStateMachine::PAYMENT_TRANSITIONS);
    }

    private static function definedTransitions(array $graph): array
    {
        $cases = [];
        foreach ($graph as $from => $targets) {
            foreach ($targets as $to) {
                $cases["{$from}_to_{$to}"] = [$from, $to];
            }
        }

        return $cases;
    }

    private static function undefinedTransitions(array $graph): array
    {
        $states = array_keys($graph);
        $cases = [];
        foreach ($states as $from) {
            foreach ($states as $to) {
                if ($from !== $to && ! in_array($to, $graph[$from], true)) {
                    $cases["{$from}_to_{$to}"] = [$from, $to];
                }
            }
        }

        return $cases;
    }
}
