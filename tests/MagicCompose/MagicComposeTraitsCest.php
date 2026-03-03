<?php

declare(strict_types=1);

/**
 * MagicComposeTraitsCest.php
 *
 * PHP Version 8.3+
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Hazeltree\Tests\MagicCompose;

use Blackcube\Hazeltree\Tests\Support\MagicComposeModel;
use Blackcube\Hazeltree\Tests\Support\MagicComposeTester;
use Blackcube\Hazeltree\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

/**
 * Integration tests for HazeltreeTrait with MagicCompose.
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
final class MagicComposeTraitsCest
{
    private ConnectionInterface $db;

    public function _before(MagicComposeTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();
        ConnectionProvider::set($this->db);

        $this->db->createCommand('DROP TABLE IF EXISTS `magicComposeModels`')->execute();

        $this->db->createCommand('
            CREATE TABLE `magicComposeModels` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `path` VARCHAR(255) NOT NULL,
                `left` DECIMAL(25,22) NOT NULL,
                `right` DECIMAL(25,22) NOT NULL,
                `level` INT NOT NULL,
                INDEX idx_left (`left`),
                INDEX idx_right (`right`),
                INDEX idx_level (`level`)
            )
        ')->execute();
    }

    public function _after(MagicComposeTester $I): void
    {
        $this->db->createCommand('DROP TABLE IF EXISTS `magicComposeModels`')->execute();
    }

    // ==================== HazeltreeTrait tests ====================

    public function testHazeltreeSetAndGet(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->setName('Test');
        $model->save();

        $I->assertEquals('1', $model->path);
        $I->assertEquals(1, $model->level);
        $I->assertNotNull($model->left);
        $I->assertNotNull($model->right);
    }

    public function testHazeltreeIsset(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->setName('Test');
        $model->save();

        $I->assertTrue(isset($model->path));
        $I->assertTrue(isset($model->left));
        $I->assertTrue(isset($model->right));
        $I->assertTrue(isset($model->level));
    }

    public function testHazeltreeCallGetter(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->setName('Test');
        $model->save();

        $I->assertEquals('1', $model->getPath());
        $I->assertEquals(1, $model->getLevel());
        $I->assertNotNull($model->getLeft());
        $I->assertNotNull($model->getRight());
    }

    public function testHazeltreeIsRoot(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->setName('Root');
        $model->save();

        $I->assertTrue($model->isRoot());

        $child = new MagicComposeModel();
        $child->setName('Child');
        $child->saveInto($model);

        $I->assertFalse($child->isRoot());
    }

    // ==================== MagicComposeElasticTrait tests ====================

    public function testMockElasticSetAndGet(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $model->title = 'Test Title';
        $model->description = 'Test Description';
        $model->price = 99.99;
        $model->active = true;

        $I->assertEquals('Test Title', $model->title);
        $I->assertEquals('Test Description', $model->description);
        $I->assertEquals(99.99, $model->price);
        $I->assertTrue($model->active);
    }

    public function testMockElasticIsset(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $I->assertFalse(isset($model->title));

        $model->title = 'Hello';

        $I->assertTrue(isset($model->title));
    }

    public function testMockElasticCallGetter(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->title = 'Getter Test';

        $I->assertEquals('Getter Test', $model->getTitle());
    }

    public function testMockElasticCallSetter(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $model->setTitle('Setter Test');

        $I->assertEquals('Setter Test', $model->title);
    }

    // ==================== Combined usage tests ====================

    public function testBothTraitsWorkTogether(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();
        $model->setName('Combined');
        $model->title = 'Combined Test';
        $model->price = 100.50;
        $model->save();

        $I->assertEquals('1', $model->path);
        $I->assertEquals(1, $model->level);
        $I->assertEquals('Combined Test', $model->title);
        $I->assertEquals(100.50, $model->price);
    }

    public function testNoConflictBetweenTraits(MagicComposeTester $I): void
    {
        $model = new MagicComposeModel();

        $I->assertInstanceOf(MagicComposeModel::class, $model);

        $model->setName('No Conflict');
        $model->save();
        $I->assertEquals('1', $model->path);

        $model->title = 'No Conflict Title';
        $I->assertEquals('No Conflict Title', $model->title);
    }

    public function testTreeOperationsWithMockElasticProperties(MagicComposeTester $I): void
    {
        $root = new MagicComposeModel();
        $root->setName('Root');
        $root->title = 'Root Category';
        $root->active = true;
        $root->save();

        $child = new MagicComposeModel();
        $child->setName('Child');
        $child->title = 'Child Category';
        $child->price = 25.00;
        $child->saveInto($root);

        $I->assertEquals('1', $root->path);
        $I->assertEquals('1.1', $child->path);
        $I->assertEquals(1, $root->level);
        $I->assertEquals(2, $child->level);

        $I->assertEquals('Root Category', $root->title);
        $I->assertEquals('Child Category', $child->title);
        $I->assertTrue($root->active);
        $I->assertEquals(25.00, $child->price);
    }

    public function testQueryWithTreeMethods(MagicComposeTester $I): void
    {
        $root = new MagicComposeModel();
        $root->setName('Root');
        $root->title = 'Root';
        $root->save();

        $child1 = new MagicComposeModel();
        $child1->setName('Child 1');
        $child1->title = 'First Child';
        $child1->saveInto($root);

        $child2 = new MagicComposeModel();
        $child2->setName('Child 2');
        $child2->title = 'Second Child';
        $child2->saveInto($root);

        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(2, $children);

        $I->assertEquals('1.1', $children[0]->path);
        $I->assertEquals('1.2', $children[1]->path);
    }

    public function testMoveNodeWithMockElasticProperties(MagicComposeTester $I): void
    {
        $root1 = new MagicComposeModel();
        $root1->setName('Root 1');
        $root1->save();

        $root2 = new MagicComposeModel();
        $root2->setName('Root 2');
        $root2->save();

        $child = new MagicComposeModel();
        $child->setName('Child');
        $child->title = 'Movable Child';
        $child->price = 42.00;
        $child->saveInto($root1);

        $I->assertEquals('1.1', $child->path);
        $I->assertEquals('Movable Child', $child->title);

        $child->saveInto($root2);

        $I->assertEquals('2.1', $child->path);
        $I->assertEquals('Movable Child', $child->title);
        $I->assertEquals(42.00, $child->price);
    }

    public function testDeleteNodeWithMockElasticProperties(MagicComposeTester $I): void
    {
        $root = new MagicComposeModel();
        $root->setName('Root');
        $root->save();

        $child1 = new MagicComposeModel();
        $child1->setName('Child 1');
        $child1->title = 'To Delete';
        $child1->saveInto($root);

        $child2 = new MagicComposeModel();
        $child2->setName('Child 2');
        $child2->title = 'To Keep';
        $child2->saveInto($root);

        $child1->delete();

        $child2->refresh();
        $I->assertEquals('1.1', $child2->path);
        $I->assertEquals('To Keep', $child2->title);

        $count = MagicComposeModel::query()->count();
        $I->assertEquals(2, $count);
    }
}
