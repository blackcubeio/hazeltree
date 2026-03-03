<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\Support\Migrations;

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Schema\Column\ColumnBuilder;

/**
 * Migration to create the treeNodes table for testing HazeltreeTrait.
 */
final class M241205120000CreateTreeNodes implements RevertibleMigrationInterface
{
    public function up(MigrationBuilder $b): void
    {
        $b->createTable('{{%treeNodes}}', [
            'id' => ColumnBuilder::bigPrimaryKey(),
            'name' => ColumnBuilder::string(255)->notNull(),
            'path' => ColumnBuilder::string(255)->notNull()->unique(),
            'left' => ColumnBuilder::double()->notNull()->unique(false),
            'right' => ColumnBuilder::double()->notNull()->unique(false),
            'level' => ColumnBuilder::integer()->notNull()->unique(false),
        ]);
    }

    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('{{%treeNodes}}');
    }
}
