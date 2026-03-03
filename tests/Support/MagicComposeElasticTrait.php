<?php

declare(strict_types=1);

/**
 * MagicComposeElasticTrait.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Hazeltree\Tests\Support;

use Blackcube\MagicCompose\Attributes\MagicCall;
use Blackcube\MagicCompose\Attributes\MagicGetter;
use Blackcube\MagicCompose\Attributes\MagicIsset;
use Blackcube\MagicCompose\Attributes\MagicSetter;
use Blackcube\MagicCompose\Attributes\Priority;
use Blackcube\MagicCompose\Exceptions\MagicNotHandledException;

/**
 * Mock trait simulating ElasticTrait for testing MagicCompose integration.
 * Uses MagicCompose attributes instead of chainable pattern.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
trait MagicComposeElasticTrait
{
    private array $elasticData = [];

    private static array $elasticProperties = ['title', 'description', 'price', 'active'];

    private function isElasticProperty(string $name): bool
    {
        return in_array($name, self::$elasticProperties, true);
    }

    #[MagicGetter(Priority::NORMAL)]
    protected function elasticGet(string $name): mixed
    {
        if (!$this->isElasticProperty($name)) {
            throw new MagicNotHandledException();
        }
        return $this->elasticData[$name] ?? null;
    }

    #[MagicSetter(Priority::NORMAL)]
    protected function elasticSet(string $name, mixed $value): void
    {
        if (!$this->isElasticProperty($name)) {
            throw new MagicNotHandledException();
        }
        $this->elasticData[$name] = $value;
    }

    #[MagicIsset(Priority::NORMAL)]
    protected function elasticIsset(string $name): bool
    {
        if (!$this->isElasticProperty($name)) {
            throw new MagicNotHandledException();
        }
        return isset($this->elasticData[$name]);
    }

    #[MagicCall(Priority::NORMAL)]
    protected function elasticCall(string $name, array $arguments): mixed
    {
        // Handle getPropertyName()
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));
            if ($this->isElasticProperty($property)) {
                return $this->elasticData[$property] ?? null;
            }
        }

        // Handle setPropertyName($value)
        if (str_starts_with($name, 'set') && strlen($name) > 3 && count($arguments) === 1) {
            $property = lcfirst(substr($name, 3));
            if ($this->isElasticProperty($property)) {
                $this->elasticData[$property] = $arguments[0];
                return null;
            }
        }

        throw new MagicNotHandledException();
    }

    public function getElasticData(): array
    {
        return $this->elasticData;
    }
}
