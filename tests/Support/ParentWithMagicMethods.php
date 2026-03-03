<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\Support;

/**
 * Base class with magic methods for testing parent delegation in HazeltreeTrait.
 */
class ParentWithMagicMethods
{
    private array $dynamicProperties = [];

    public function __get(string $name): mixed
    {
        return $this->dynamicProperties[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->dynamicProperties[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->dynamicProperties[$name]);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'getDynamicProperty' && isset($arguments[0])) {
            return $this->dynamicProperties[$arguments[0]] ?? null;
        }
        if ($name === 'setDynamicProperty' && isset($arguments[0], $arguments[1])) {
            $this->dynamicProperties[$arguments[0]] = $arguments[1];
            return null;
        }
        throw new \Error(sprintf('Call to undefined method %s::%s()', static::class, $name));
    }
}
