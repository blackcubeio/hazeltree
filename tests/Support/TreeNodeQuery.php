<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\Support;

use Blackcube\Hazeltree\HazeltreeQueryTrait;
use Yiisoft\ActiveRecord\ActiveQuery;

/**
 * ActiveQuery for TreeNode with tree navigation capabilities.
 */
class TreeNodeQuery extends ActiveQuery
{
    use HazeltreeQueryTrait;
}
