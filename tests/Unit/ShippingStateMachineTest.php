<?php

namespace Tests\Unit;

use App\Services\ShippingStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ShippingStateMachineTest extends TestCase
{
    #[DataProvider('validTransitions')]
    public function test_every_defined_shipping_transition_is_allowed(string $from, string $to): void
    {
        $this->assertTrue((new ShippingStateMachine)->canTransition($from, $to));
    }

    #[DataProvider('invalidTransitions')]
    public function test_every_undefined_shipping_transition_is_rejected(string $from, string $to): void
    {
        $this->assertFalse((new ShippingStateMachine)->canTransition($from, $to), "Unexpectedly allowed {$from} -> {$to}");
    }

    #[DataProvider('providerStatuses')]
    public function test_provider_statuses_are_reduced_to_supported_canonical_states(string $provider, ?string $canonical): void
    {
        $this->assertSame($canonical, (new ShippingStateMachine)->normalize($provider));
    }

    public static function validTransitions(): array
    {
        $cases = [];
        foreach (ShippingStateMachine::TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $cases["{$from}_to_{$to}"] = [$from, $to];
            }
        }

        return $cases;
    }

    public static function invalidTransitions(): array
    {
        $states = array_keys(ShippingStateMachine::TRANSITIONS);
        $cases = [];
        foreach ($states as $from) {
            foreach ($states as $to) {
                if ($from !== $to && ! in_array($to, ShippingStateMachine::TRANSITIONS[$from], true)) {
                    $cases["{$from}_to_{$to}"] = [$from, $to];
                }
            }
        }

        return $cases;
    }

    public static function providerStatuses(): array
    {
        return [
            'picking' => ['picking', 'ready_to_pick'],
            'transporting' => ['transporting', 'shipping'],
            'money_collect_delivering' => ['money_collect_delivering', 'shipping'],
            'waiting_to_return' => ['waiting_to_return', 'returning'],
            'cancel' => ['cancel', 'cancelled'],
            'unknown' => ['provider_added_a_new_status', null],
        ];
    }
}
