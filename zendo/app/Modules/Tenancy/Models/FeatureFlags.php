<?php

namespace App\Modules\Tenancy\Models;

use Illuminate\Contracts\Support\Arrayable;

class FeatureFlags implements \JsonSerializable, Arrayable
{
    private const VALID_FLAGS = [
        'meals',
        'lodging',
        'memberships',
        'recurring-events',
        'stripe-connect',
    ];

    private array $flags;

    public function __construct(array $flags = [])
    {
        $this->flags = [];

        foreach (self::VALID_FLAGS as $flag) {
            $this->flags[$flag] = $flags[$flag] ?? false;
        }
    }

    public function meals(): bool
    {
        return $this->flags['meals'];
    }

    public function lodging(): bool
    {
        return $this->flags['lodging'];
    }

    public function memberships(): bool
    {
        return $this->flags['memberships'];
    }

    public function recurringEvents(): bool
    {
        return $this->flags['recurring-events'];
    }

    public function stripeConnect(): bool
    {
        return $this->flags['stripe-connect'];
    }

    public function has(string $flag): bool
    {
        if (! in_array($flag, self::VALID_FLAGS, true)) {
            return false;
        }

        return $this->flags[$flag] ?? false;
    }

    public function toArray(): array
    {
        return $this->flags;
    }

    public function jsonSerialize(): mixed
    {
        return $this->flags;
    }

    public static function default(): self
    {
        return new self([
            'meals' => true,
            'lodging' => true,
            'memberships' => true,
        ]);
    }
}
