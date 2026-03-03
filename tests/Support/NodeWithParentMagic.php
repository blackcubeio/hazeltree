<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\Support;

use Blackcube\Hazeltree\HazeltreeInterface;
use Blackcube\Hazeltree\HazeltreeTrait;
use Blackcube\MagicCompose\MagicComposeTrait;

/**
 * Node class extending a parent with magic methods for testing parent delegation.
 * This tests that HazeltreeTrait correctly delegates to parent::__get(), etc.
 * Uses MagicComposeTrait for magic method dispatch.
 */
class NodeWithParentMagic extends ParentWithMagicMethods implements HazeltreeInterface
{
    use MagicComposeTrait, HazeltreeTrait;

    public string $name = '';

    public function tableName(): string
    {
        return 'nodes_with_parent_magic';
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
