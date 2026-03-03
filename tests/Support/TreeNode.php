<?php

declare(strict_types=1);

/**
 * TreeNode.php
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
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * TreeNode model for testing HazeltreeTrait.
 * Uses MagicComposeActiveRecordTrait for magic method dispatch and AR hooks.
 *
 * @property int $id
 * @property string $name
 * @property string $path
 * @property float $left
 * @property float $right
 * @property int $level
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class TreeNode extends ActiveRecord implements HazeltreeInterface
{
    use MagicComposeActiveRecordTrait, HazeltreeTrait;

    protected int $id;
    protected string $name = '';

    public function tableName(): string
    {
        return '{{%treeNodes}}';
    }

    /**
     * Override to return TreeNodeQuery with HazeltreeQueryTrait.
     */
    public static function query($modelClass = null): ActiveQueryInterface
    {
        return new TreeNodeQuery($modelClass ?? static::class);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
