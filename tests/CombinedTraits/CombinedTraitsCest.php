<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\CombinedTraits;

use Blackcube\Hazeltree\Tests\Support\CombinedTraitsModel;
use Blackcube\Hazeltree\Tests\Support\CombinedTraitsTester;
use Blackcube\Hazeltree\Tests\Support\MysqlHelper;
use Yiisoft\Db\Connection\ConnectionProvider;

/**
 * Integration tests for combining HazeltreeTrait with another trait that has magic methods.
 * This validates the chainable methods pattern for trait composition.
 */
final class CombinedTraitsCest
{
    public function _before(CombinedTraitsTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();
        ConnectionProvider::set($db);

        // Drop and recreate table
        $db->createCommand('DROP TABLE IF EXISTS `combinedModels`')->execute();

        $db->createCommand('
            CREATE TABLE `combinedModels` (
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

    public function _after(CombinedTraitsTester $I): void
    {
        $helper = new MysqlHelper();
        $db = $helper->createConnection();
        $db->createCommand('DROP TABLE IF EXISTS `combinedModels`')->execute();
    }

    // ==================== HazeltreeTrait tests ====================

    public function testHazeltreeSetAndGet(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->setName('Test');
        $model->save();

        // Tree properties are managed by the trait
        $I->assertEquals('1', $model->path);
        $I->assertEquals(1, $model->level);
        $I->assertNotNull($model->left);
        $I->assertNotNull($model->right);
    }

    public function testHazeltreeIsset(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->setName('Test');
        $model->save();

        $I->assertTrue(isset($model->path));
        $I->assertTrue(isset($model->left));
        $I->assertTrue(isset($model->right));
        $I->assertTrue(isset($model->level));
    }

    public function testHazeltreeCallGetter(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->setName('Test');
        $model->save();

        // Use getter methods via __call
        $I->assertEquals('1', $model->getPath());
        $I->assertEquals(1, $model->getLevel());
        $I->assertNotNull($model->getLeft());
        $I->assertNotNull($model->getRight());
    }

    public function testHazeltreeIsRoot(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->setName('Root');
        $model->save();

        $I->assertTrue($model->isRoot());

        $child = new CombinedTraitsModel();
        $child->setName('Child');
        $child->saveInto($model);

        $I->assertFalse($child->isRoot());
    }

    // ==================== MockElasticTrait tests ====================

    public function testMockElasticSetAndGet(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();

        // Set via magic __set
        $model->title = 'Test Title';
        $model->description = 'Test Description';
        $model->price = 99.99;
        $model->active = true;

        // Get via magic __get
        $I->assertEquals('Test Title', $model->title);
        $I->assertEquals('Test Description', $model->description);
        $I->assertEquals(99.99, $model->price);
        $I->assertTrue($model->active);
    }

    public function testMockElasticIsset(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();

        $I->assertFalse(isset($model->title));

        $model->title = 'Hello';

        $I->assertTrue(isset($model->title));
    }

    public function testMockElasticCallGetter(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->title = 'Getter Test';

        // Use getter method via __call
        $I->assertEquals('Getter Test', $model->getTitle());
    }

    public function testMockElasticCallSetter(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();

        // Use setter method via __call
        $model->setTitle('Setter Test');

        $I->assertEquals('Setter Test', $model->title);
    }

    // ==================== Combined usage tests ====================

    public function testBothTraitsWorkTogether(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->setName('Combined');

        // Set elastic properties before save
        $model->title = 'Combined Test';
        $model->price = 100.50;

        $model->save();

        // Verify tree properties
        $I->assertEquals('1', $model->path);
        $I->assertEquals(1, $model->level);

        // Verify elastic properties
        $I->assertEquals('Combined Test', $model->title);
        $I->assertEquals(100.50, $model->price);
    }

    public function testBothTraitsIssetWorkTogether(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->setName('Test');
        $model->save();

        // Tree properties should be set after save
        $I->assertTrue(isset($model->path));
        $I->assertTrue(isset($model->level));

        // Elastic properties not set yet
        $I->assertFalse(isset($model->title));

        // Set elastic property
        $model->title = 'Test';

        // Now elastic property should be set
        $I->assertTrue(isset($model->title));
    }

    public function testBothTraitsCallWorkTogether(CombinedTraitsTester $I): void
    {
        $model = new CombinedTraitsModel();
        $model->setName('Call Test');
        $model->save();

        // Use setters from elastic
        $model->setTitle('Title via call');
        $model->setPrice(50.00);

        // Use getters from both traits
        $I->assertEquals('1', $model->getPath());
        $I->assertEquals(1, $model->getLevel());
        $I->assertEquals('Title via call', $model->getTitle());
        $I->assertEquals(50.00, $model->getPrice());
    }

    public function testPriorityHazeltreeOverElastic(CombinedTraitsTester $I): void
    {
        // This test verifies that HazeltreeTrait has priority over MockElasticTrait
        $model = new CombinedTraitsModel();
        $model->setName('Priority Test');
        $model->save();

        // path is handled by HazeltreeTrait
        $I->assertEquals('1', $model->path);

        // title is handled by MockElasticTrait (since HazeltreeTrait doesn't know it)
        $model->title = 'Priority Test';
        $I->assertEquals('Priority Test', $model->title);
    }

    public function testNoConflictBetweenTraits(CombinedTraitsTester $I): void
    {
        // Verify there's no PHP fatal error about trait method collision
        // If we got here, the traits were combined successfully
        $model = new CombinedTraitsModel();

        $I->assertInstanceOf(CombinedTraitsModel::class, $model);

        // Verify both traits are functional
        $model->setName('No Conflict');
        $model->save();
        $I->assertEquals('1', $model->path);

        $model->title = 'No Conflict Title';
        $I->assertEquals('No Conflict Title', $model->title);
    }

    public function testTreeOperationsWithElasticProperties(CombinedTraitsTester $I): void
    {
        // Create root with elastic properties
        $root = new CombinedTraitsModel();
        $root->setName('Root');
        $root->title = 'Root Category';
        $root->active = true;
        $root->save();

        // Create child with elastic properties
        $child = new CombinedTraitsModel();
        $child->setName('Child');
        $child->title = 'Child Category';
        $child->price = 25.00;
        $child->saveInto($root);

        // Verify tree structure
        $I->assertEquals('1', $root->path);
        $I->assertEquals('1.1', $child->path);
        $I->assertEquals(1, $root->level);
        $I->assertEquals(2, $child->level);

        // Verify elastic properties preserved
        $I->assertEquals('Root Category', $root->title);
        $I->assertEquals('Child Category', $child->title);
        $I->assertTrue($root->active);
        $I->assertEquals(25.00, $child->price);
    }

    public function testQueryWithTreeMethods(CombinedTraitsTester $I): void
    {
        // Create tree structure
        $root = new CombinedTraitsModel();
        $root->setName('Root');
        $root->title = 'Root';
        $root->save();

        $child1 = new CombinedTraitsModel();
        $child1->setName('Child 1');
        $child1->title = 'First Child';
        $child1->saveInto($root);

        $child2 = new CombinedTraitsModel();
        $child2->setName('Child 2');
        $child2->title = 'Second Child';
        $child2->saveInto($root);

        // Query children using Hazeltree API
        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(2, $children);

        // Verify tree structure on queried models
        $I->assertEquals('1.1', $children[0]->path);
        $I->assertEquals('1.2', $children[1]->path);
        $I->assertEquals('Child 1', $children[0]->getName());
        $I->assertEquals('Child 2', $children[1]->getName());
    }

    public function testMoveNodeWithElasticProperties(CombinedTraitsTester $I): void
    {
        // Create structure
        $root1 = new CombinedTraitsModel();
        $root1->setName('Root 1');
        $root1->save();

        $root2 = new CombinedTraitsModel();
        $root2->setName('Root 2');
        $root2->save();

        $child = new CombinedTraitsModel();
        $child->setName('Child');
        $child->title = 'Movable Child';
        $child->price = 42.00;
        $child->saveInto($root1);

        // Verify initial position
        $I->assertEquals('1.1', $child->path);
        $I->assertEquals('Movable Child', $child->title);

        // Move to root2
        $child->saveInto($root2);

        // Verify new position
        $I->assertEquals('2.1', $child->path);

        // Verify elastic properties preserved after move
        $I->assertEquals('Movable Child', $child->title);
        $I->assertEquals(42.00, $child->price);
    }

    public function testDeleteNodeWithElasticProperties(CombinedTraitsTester $I): void
    {
        // Create structure
        $root = new CombinedTraitsModel();
        $root->setName('Root');
        $root->save();

        $child1 = new CombinedTraitsModel();
        $child1->setName('Child 1');
        $child1->title = 'To Delete';
        $child1->saveInto($root);

        $child2 = new CombinedTraitsModel();
        $child2->setName('Child 2');
        $child2->title = 'To Keep';
        $child2->saveInto($root);

        // Delete child1
        $child1->delete();

        // Verify child2 path was updated
        $child2->refresh();
        $I->assertEquals('1.1', $child2->path);
        $I->assertEquals('To Keep', $child2->title);

        // Verify only 2 nodes remain
        $count = CombinedTraitsModel::query()->count();
        $I->assertEquals(2, $count);
    }
}
