<?php

namespace PortalConnect\Subscriptions\Tests;

use InvalidArgumentException;
use PortalConnect\Subscriptions\Pricing;
use Tests\TestCase;

class PricingTest extends TestCase
{
    public function test_prices_come_from_config(): void
    {
        config(['subscriptions.prices' => [1 => 11100, 12 => 99900]]);

        $this->assertSame(11100, Pricing::priceFor(1));
        $this->assertSame(99900, Pricing::priceFor(12));
        $this->assertTrue(Pricing::isValidPeriod(12));
        $this->assertFalse(Pricing::isValidPeriod(3));
    }

    public function test_unknown_period_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Pricing::priceFor(99);
    }
}
