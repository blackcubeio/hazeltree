<?php

declare(strict_types=1);

/**
 * MagicComposeModel.php
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
use Blackcube\Hazeltree\HazeltreeQueryTrait;
use Blackcube\MagicCompose\MagicComposeActiveRecordTrait;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Test model combining MagicComposeActiveRecordTrait + HazeltreeTrait + MagicComposeElasticTrait.
 * No manual conflict resolution needed - MagicCompose handles everything.
 *
 * @property string|null $title
 * @property string|null $description
 * @property float|null $price
 * @property bool|null $active
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class MagicComposeModel extends ActiveRecord implements HazeltreeInterface
{
    use MagicComposeActiveRecordTrait, HazeltreeTrait, MagicComposeElasticTrait;

    protected int $id;
    protected string $name = '';

    public function tableName(): string
    {
        return 'magicComposeModels';
    }

    public static function query(\Yiisoft\ActiveRecord\ActiveRecordInterface|string|null $modelClass = null): ActiveQueryInterface
    {
        return new MagicComposeQuery($modelClass ?? static::class);
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

/**
 * Query class for MagicComposeModel with Hazeltree support.
 */
class MagicComposeQuery extends ActiveQuery
{
    use HazeltreeQueryTrait;
}
