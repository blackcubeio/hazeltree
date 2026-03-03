<?php

declare(strict_types=1);

/**
 * SimpleNode.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Hazeltree\Tests\Support;

use Blackcube\Hazeltree\HazeltreeInterface;
use Blackcube\Hazeltree\HazeltreeTrait;
use Blackcube\MagicCompose\MagicComposeTrait;

/**
 * SimpleNode for testing HazeltreeTrait magic methods without ActiveRecord parent.
 * Uses MagicComposeTrait for magic method dispatch.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class SimpleNode implements HazeltreeInterface
{
    use MagicComposeTrait, HazeltreeTrait;

    public string $name = '';

    public function tableName(): string
    {
        return 'simple_nodes';
    }

    public static function primaryKey(): array
    {
        return ['id'];
    }

    public function leftColumn(): string
    {
        return 'left';
    }

    public function rightColumn(): string
    {
        return 'right';
    }

    public function pathColumn(): string
    {
        return 'path';
    }

    public function levelColumn(): string
    {
        return 'level';
    }

    public function isRoot(): bool
    {
        return $this->level === 1;
    }

    public function canMove(string $targetPath): bool
    {
        return true;
    }

    public function saveInto(HazeltreeInterface|string $target, ?array $attributeNames = null): bool
    {
        return false;
    }

    public function saveBefore(HazeltreeInterface|string $target, ?array $attributeNames = null): bool
    {
        return false;
    }

    public function saveAfter(HazeltreeInterface|string $target, ?array $attributeNames = null): bool
    {
        return false;
    }
}
