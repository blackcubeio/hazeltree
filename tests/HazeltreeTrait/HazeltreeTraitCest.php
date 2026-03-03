<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\HazeltreeTrait;

use Blackcube\Hazeltree\Exceptions\InvalidItemConfigurationException;
use Blackcube\Hazeltree\Helpers\TreeHelper;
use Blackcube\Hazeltree\Tests\Support\HazeltreeTraitTester;
use Blackcube\Hazeltree\Tests\Support\Migrations\M241205120000CreateTreeNodes;
use Blackcube\Hazeltree\Tests\Support\MysqlHelper;
use Blackcube\Hazeltree\Tests\Support\NodeWithParentMagic;
use Blackcube\Hazeltree\Tests\Support\SimpleNode;
use Blackcube\Hazeltree\Tests\Support\TreeNode;
use Blackcube\Hazeltree\Tests\Support\TreeNodeFactory;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * Integration tests for HazeltreeTrait with MySQL database.
 */
final class HazeltreeTraitCest
{
    private ConnectionInterface $db;
    private TreeNodeFactory $factory;

    public function _before(HazeltreeTraitTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();
        ConnectionProvider::set($this->db);
        $this->factory = new TreeNodeFactory($this->db);

        // Drop table if exists
        $this->db->createCommand('DROP TABLE IF EXISTS `treeNodes`')->execute();

        // Run migration
        $migration = new M241205120000CreateTreeNodes();
        $builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $migration->up($builder);
    }

    public function _after(HazeltreeTraitTester $I): void
    {
        $this->db->createCommand('DROP TABLE IF EXISTS `treeNodes`')->execute();
    }

    // ==================== Magic methods tests ====================

    public function testMagicGetReturnsTreeProperties(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Test', '1.2.3');
        $node = $this->factory->findById($id);

        $I->assertEquals('1.2.3', $node->path);
        $I->assertEquals(3, $node->level);
        $I->assertIsFloat($node->left);
        $I->assertIsFloat($node->right);
        $I->assertLessThan($node->right, $node->left);
    }

    public function testMagicSetThrowsForReadonlyProperties(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Test', '1');
        $node = $this->factory->findById($id);

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->path = '1.2.3';
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->left = 1.5;
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->right = 2.5;
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->level = 3;
        });
    }

    public function testMagicIsset(HazeltreeTraitTester $I): void
    {
        $node = new TreeNode();

        $I->assertFalse(isset($node->path));
        $I->assertFalse(isset($node->left));

        // After loading from DB, properties should be set
        $id = $this->factory->insert('Test', '1');
        $loadedNode = $this->factory->findById($id);

        $I->assertTrue(isset($loadedNode->path));
        $I->assertTrue(isset($loadedNode->left));
    }

    public function testMagicCallGetterWorks(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Test', '1.2');
        $node = $this->factory->findById($id);

        $I->assertEquals('1.2', $node->getPath());
        $I->assertEquals(2, $node->getLevel());
        $I->assertIsFloat($node->getLeft());
        $I->assertIsFloat($node->getRight());
    }

    public function testMagicCallSetterThrowsForReadonlyProperties(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Test', '1');
        $node = $this->factory->findById($id);

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setPath('1.2');
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setLeft(1.0);
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setRight(2.0);
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setLevel(2);
        });
    }

    // ==================== Parent delegation tests ====================

    public function testMagicSetDelegatesToParentForNonTreeProperties(HazeltreeTraitTester $I): void
    {
        // NodeWithParentMagic extends ParentWithMagicMethods which has __set
        // Setting a dynamic attribute should delegate to parent::__set
        $node = new NodeWithParentMagic();

        // 'dynamicAttr' doesn't exist as a property, so it goes through __set -> parent::__set
        $node->dynamicAttr = 'Dynamic Value';

        $I->assertEquals('Dynamic Value', $node->dynamicAttr);
    }

    public function testMagicGetDelegatesToParentForNonTreeProperties(HazeltreeTraitTester $I): void
    {
        // NodeWithParentMagic extends ParentWithMagicMethods which has __get
        $node = new NodeWithParentMagic();

        // Set a dynamic attribute and verify we can get it back via __get -> parent::__get
        $node->dynamicAttr = 'Test Value';
        $I->assertEquals('Test Value', $node->dynamicAttr);
    }

    public function testMagicIssetDelegatesToParentForNonTreeProperties(HazeltreeTraitTester $I): void
    {
        // NodeWithParentMagic extends ParentWithMagicMethods which has __isset
        $node = new NodeWithParentMagic();

        // Before setting, dynamic attribute should not be set
        $I->assertFalse(isset($node->dynamicAttr));

        // After setting via parent delegation
        $node->dynamicAttr = 'Value';
        $I->assertTrue(isset($node->dynamicAttr));
    }

    public function testMagicCallDelegatesToParentForNonTreeMethods(HazeltreeTraitTester $I): void
    {
        // NodeWithParentMagic extends ParentWithMagicMethods which has __call
        $node = new NodeWithParentMagic();

        // setDynamicProperty/getDynamicProperty are methods accessible via parent::__call
        $node->setDynamicProperty('myKey', 'Call Value');
        $I->assertEquals('Call Value', $node->getDynamicProperty('myKey'));
    }

    // ==================== TreeHelper integration tests ====================

    public function testTreeHelperCalculatesCorrectValues(HazeltreeTraitTester $I): void
    {
        // Verify TreeHelper calculations match what we store
        $path = '1';
        $matrix = TreeHelper::convert($path);
        $left = TreeHelper::getLeft($matrix);
        $right = TreeHelper::getRight($matrix);
        $level = TreeHelper::getLevel($path);

        $I->assertEquals(1.0, $left);
        $I->assertEquals(2.0, $right);
        $I->assertEquals(1, $level);
    }

    public function testTreeHelperDeepPath(HazeltreeTraitTester $I): void
    {
        $path = '1.2.3';
        $matrix = TreeHelper::convert($path);
        $left = TreeHelper::getLeft($matrix);
        $right = TreeHelper::getRight($matrix);
        $level = TreeHelper::getLevel($path);

        $I->assertEquals(3, $level);
        $I->assertLessThan($right, $left);
    }

    // ==================== isRoot tests ====================

    public function testIsRootForLevel1(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Root', '1');
        $node = $this->factory->findById($id);

        $I->assertTrue($node->isRoot());
    }

    public function testIsRootForLevel2(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $id = $this->factory->insert('Child', '1.1');
        $node = $this->factory->findById($id);

        $I->assertFalse($node->isRoot());
    }

    // ==================== Save and load tests ====================

    public function testSaveAndLoadNode(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Test Node', '1.2');
        $loaded = $this->factory->findById($id);

        $I->assertNotNull($loaded);
        $I->assertEquals('Test Node', $loaded->getName());
        $I->assertEquals('1.2', $loaded->path);
        $I->assertEquals(2, $loaded->level);
    }

    public function testSaveMultipleNodes(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('Child 1', '1.1');
        $this->factory->insert('Child 2', '1.2');

        $nodes = TreeNode::query()->orderBy(['left' => SORT_ASC])->all();

        $I->assertCount(3, $nodes);
        $I->assertEquals('Root', $nodes[0]->getName());
        $I->assertEquals('Child 1', $nodes[1]->getName());
        $I->assertEquals('Child 2', $nodes[2]->getName());
    }

    // ==================== canMove tests ====================

    public function testCanMoveToUnrelatedBranch(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $id1 = $this->factory->insert('Branch 1', '1.1');
        $this->factory->insert('Branch 2', '1.2');

        $node = $this->factory->findById($id1);

        $I->assertTrue($node->canMove('1.2'));
        $I->assertTrue($node->canMove('1.2.1'));
    }

    public function testCannotMoveIntoOwnSubtree(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $id = $this->factory->insert('Parent', '1.1');
        $this->factory->insert('Child', '1.1.1');

        $node = $this->factory->findById($id);

        $I->assertFalse($node->canMove('1.1'));
        $I->assertFalse($node->canMove('1.1.1'));
        $I->assertFalse($node->canMove('1.1.2'));
    }

    // ==================== saveInto tests ====================

    public function testSaveIntoNewRecord(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $root = $this->factory->findById($rootId);

        $child = new TreeNode();
        $child->setName('Child');
        $child->saveInto($root);

        $I->assertEquals('1.1', $child->path);
        $I->assertEquals(2, $child->level);
    }

    public function testSaveIntoWithExistingChildren(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $this->factory->insert('Child 1', '1.1');
        $root = $this->factory->findById($rootId);

        $child2 = new TreeNode();
        $child2->setName('Child 2');
        $child2->saveInto($root);

        $I->assertEquals('1.2', $child2->path);
    }

    // ==================== Path string parameter tests ====================

    public function testSaveIntoWithPathString(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');

        $child = new TreeNode();
        $child->setName('Child');
        $child->saveInto('1'); // Using path string instead of Node

        $I->assertEquals('1.1', $child->path);
        $I->assertEquals(2, $child->level);
    }

    public function testSaveBeforeWithPathString(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('Child 2', '1.2');

        $child1 = new TreeNode();
        $child1->setName('Child 1');
        $child1->saveBefore('1.2'); // Using path string

        // child1 takes child2's old position (1.2), but becomes 1.1 since it inserts BEFORE
        // Wait no - saveBefore('1.2') means insert BEFORE 1.2
        // So child1 takes 1.2's position (1.2), and former 1.2 becomes 1.3
        // But there was no 1.1, so actually child1 should just take 1.2's position
        // and former child2 (was 1.2) gets bumped to 1.3

        // Actually - saveBefore saves the path of target, then bumps target and siblings
        // So child1 gets path '1.2' (the old target path), child2 gets bumped to 1.3
        $I->assertEquals('1.2', $child1->path);

        // Verify child2 was shifted
        $child2 = TreeNode::query()->where(['name' => 'Child 2'])->one();
        $I->assertEquals('1.3', $child2->path);
    }

    public function testSaveAfterWithPathString(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('Child 1', '1.1');

        $child2 = new TreeNode();
        $child2->setName('Child 2');
        $child2->saveAfter('1.1'); // Using path string

        $I->assertEquals('1.2', $child2->path);
    }

    public function testSaveIntoWithInvalidPathThrows(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');

        $child = new TreeNode();
        $child->setName('Child');

        $I->expectThrowable(InvalidItemConfigurationException::class, function () use ($child) {
            $child->saveInto('99.99.99'); // Non-existent path
        });
    }

    public function testSaveIntoExistingNodeWithPathString(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('Child 1', '1.1');
        $childId = $this->factory->insert('Child 2', '1.2');
        $this->factory->insert('Other Parent', '2');

        $child2 = $this->factory->findById($childId);
        $child2->saveInto('2'); // Move existing node using path string

        $child2->refresh();
        $I->assertEquals('2.1', $child2->path);
    }

    // ==================== saveBefore tests ====================

    public function testSaveBeforeNewRecord(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $child1Id = $this->factory->insert('Child 1', '1.1');
        $child1 = $this->factory->findById($child1Id);

        $newChild = new TreeNode();
        $newChild->setName('New Child');
        $newChild->saveBefore($child1);

        $I->assertEquals('1.1', $newChild->path);

        $child1->refresh();
        $I->assertEquals('1.2', $child1->path);
    }

    // ==================== saveAfter tests ====================

    public function testSaveAfterNewRecord(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $child1Id = $this->factory->insert('Child 1', '1.1');
        $child1 = $this->factory->findById($child1Id);

        $newChild = new TreeNode();
        $newChild->setName('New Child');
        $newChild->saveAfter($child1);

        $I->assertEquals('1.2', $newChild->path);
        $I->assertEquals('1.1', $child1->path);
    }

    public function testSaveAfterWithExistingNextSibling(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $child1Id = $this->factory->insert('Child 1', '1.1');
        $child2Id = $this->factory->insert('Child 2', '1.2');
        $child1 = $this->factory->findById($child1Id);
        $child2 = $this->factory->findById($child2Id);

        $newChild = new TreeNode();
        $newChild->setName('New Child');
        $newChild->saveAfter($child1);

        $I->assertEquals('1.2', $newChild->path);

        $child2->refresh();
        $I->assertEquals('1.3', $child2->path);
    }

    // ==================== Root node auto-creation tests ====================

    public function testSaveWithoutPathCreatesRootNode(HazeltreeTraitTester $I): void
    {
        $node = new TreeNode();
        $node->setName('Auto Root');
        $node->save();

        $I->assertEquals('1', $node->path);
        $I->assertEquals(1, $node->level);
        $I->assertTrue($node->isRoot());
    }

    public function testSaveWithoutPathCreatesNextRoot(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root 1', '1');

        $node = new TreeNode();
        $node->setName('Root 2');
        $node->save();

        // Should automatically get path '2'
        $I->assertEquals('2', $node->path);
        $I->assertEquals(1, $node->level);
        $I->assertTrue($node->isRoot());
    }

    // ==================== Root level sibling tests ====================

    public function testSaveBeforeRootWorks(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $root = $this->factory->findById($rootId);

        $node = new TreeNode();
        $node->setName('BeforeRoot');
        $node->saveBefore($root);

        $I->assertEquals('1', $node->path);
        $I->assertEquals(1, $node->level);

        $root->refresh();
        $I->assertEquals('2', $root->path);
    }

    public function testSaveAfterRootWorks(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $root = $this->factory->findById($rootId);

        $node = new TreeNode();
        $node->setName('AfterRoot');
        $node->saveAfter($root);

        $I->assertEquals('1', $root->path);
        $I->assertEquals('2', $node->path);
        $I->assertEquals(1, $node->level);
    }

    // ==================== Full integration scenario ====================

    public function testFullIntegrationScenario(HazeltreeTraitTester $I): void
    {
        // Step 1: Create root
        $root = new TreeNode();
        $root->setName('Root');
        $root->save();

        $I->assertEquals('1', $root->path);
        $I->assertEquals(1, $root->level);

        // Step 2: Add first child via saveInto
        $node2 = new TreeNode();
        $node2->setName('Node 2');
        $node2->saveInto($root);

        $I->assertEquals('1.1', $node2->path);
        $I->assertEquals(2, $node2->level);

        // Step 3: Add grandchild
        $node3 = new TreeNode();
        $node3->setName('Node 3');
        $node3->saveInto($node2);

        $I->assertEquals('1.1.1', $node3->path);
        $I->assertEquals(3, $node3->level);

        // Step 4: Add sibling via saveAfter
        $node4 = new TreeNode();
        $node4->setName('Node 4');
        $node4->saveAfter($node2);

        $I->assertEquals('1.2', $node4->path);

        // Step 5: saveInto on existing node - move node4 into root at last position (after node2)
        // node4 becomes the last child of root
        $node4->saveInto($root);

        $node2->refresh();
        $node3->refresh();
        $node4->refresh();

        // node4 is at 1.2 (was 1.2, now becomes last child which is still 1.2 since node2 is 1.1)
        // Actually, node4 was already 1.2, and it moves into root at last position
        // Since node2 is 1.1 and is the only direct child, node4 becomes 1.2 again
        $I->assertEquals('1.1', $node2->path);
        $I->assertEquals('1.1.1', $node3->path);
        $I->assertEquals('1.2', $node4->path);

        // Step 6: saveBefore - insert node5 before node2
        // node5 takes node2's position (1.1), node2 shifts to 1.2, node4 shifts to 1.3
        $node5 = new TreeNode();
        $node5->setName('Node 5');
        $node5->saveBefore($node2);

        $I->assertEquals('1.1', $node5->path);

        $node2->refresh();
        $node3->refresh();
        $node4->refresh();
        $I->assertEquals('1.2', $node2->path);
        $I->assertEquals('1.2.1', $node3->path);
        $I->assertEquals('1.3', $node4->path);
    }

    // ==================== Delete operation tests ====================

    public function testDeleteNodeClosesGap(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('Child 1', '1.1');
        $child2Id = $this->factory->insert('Child 2', '1.2');
        $child3Id = $this->factory->insert('Child 3', '1.3');

        $child2 = $this->factory->findById($child2Id);
        $child2->delete();

        $child1 = TreeNode::query()->where(['name' => 'Child 1'])->one();
        $child3 = $this->factory->findById($child3Id);
        $child3->refresh();

        $I->assertEquals('1.1', $child1->path);
        $I->assertEquals('1.2', $child3->path);

        $allNodes = TreeNode::query()->orderBy(['left' => SORT_ASC])->all();
        $I->assertCount(3, $allNodes);
    }

    public function testDeleteNodeWithChildrenClosesGap(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $child1Id = $this->factory->insert('Child 1', '1.1');
        $this->factory->insert('Grandchild', '1.1.1');
        $child2Id = $this->factory->insert('Child 2', '1.2');

        $child1 = $this->factory->findById($child1Id);
        $child1->delete();

        $child2 = $this->factory->findById($child2Id);
        $child2->refresh();

        $I->assertEquals('1.1', $child2->path);

        $allNodes = TreeNode::query()->orderBy(['left' => SORT_ASC])->all();
        $I->assertCount(2, $allNodes);
        $I->assertEquals('Root', $allNodes[0]->getName());
        $I->assertEquals('Child 2', $allNodes[1]->getName());
    }

    public function testDeleteLastSiblingDoesNotAffectOthers(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $child1Id = $this->factory->insert('Child 1', '1.1');
        $child2Id = $this->factory->insert('Child 2', '1.2');
        $child3Id = $this->factory->insert('Child 3', '1.3');

        $child3 = $this->factory->findById($child3Id);
        $child3->delete();

        $child1 = $this->factory->findById($child1Id);
        $child2 = $this->factory->findById($child2Id);

        $I->assertEquals('1.1', $child1->path);
        $I->assertEquals('1.2', $child2->path);

        $allNodes = TreeNode::query()->orderBy(['left' => SORT_ASC])->all();
        $I->assertCount(3, $allNodes);
    }

    public function testDeleteRootWorks(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $this->factory->insert('Child', '1.1');
        $root = $this->factory->findById($rootId);

        $deleted = $root->delete();

        $I->assertEquals(2, $deleted, 'Root and child should be deleted');
        $I->assertNull(TreeNode::query()->where(['id' => $rootId])->one());
    }

    public function testDeleteFirstSiblingShiftsAll(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $child1Id = $this->factory->insert('Child 1', '1.1');
        $child2Id = $this->factory->insert('Child 2', '1.2');
        $child3Id = $this->factory->insert('Child 3', '1.3');

        $child1 = $this->factory->findById($child1Id);
        $child1->delete();

        $child2 = $this->factory->findById($child2Id);
        $child3 = $this->factory->findById($child3Id);
        $child2->refresh();
        $child3->refresh();

        $I->assertEquals('1.1', $child2->path);
        $I->assertEquals('1.2', $child3->path);
    }

    // ==================== Multi-root tests ====================

    public function testCreateMultipleRoots(HazeltreeTraitTester $I): void
    {
        $root1 = new TreeNode();
        $root1->setName('Root 1');
        $root1->save();

        $root2 = new TreeNode();
        $root2->setName('Root 2');
        $root2->save();

        $root3 = new TreeNode();
        $root3->setName('Root 3');
        $root3->save();

        $I->assertEquals('1', $root1->path);
        $I->assertEquals('2', $root2->path);
        $I->assertEquals('3', $root3->path);

        $I->assertTrue($root1->isRoot());
        $I->assertTrue($root2->isRoot());
        $I->assertTrue($root3->isRoot());
    }

    public function testMoveNodeBetweenRoots(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root 1', '1');
        $childId = $this->factory->insert('Child 1', '1.1');
        $this->factory->insert('Grandchild', '1.1.1');
        $this->factory->insert('Root 2', '2');

        $child = $this->factory->findById($childId);
        $child->saveInto('2');

        $child->refresh();
        $I->assertEquals('2.1', $child->path);
        $I->assertEquals(2, $child->level);

        // Verify grandchild moved too
        $grandchild = TreeNode::query()->where(['name' => 'Grandchild'])->one();
        $I->assertEquals('2.1.1', $grandchild->path);
    }

    public function testMoveNodeFromOneRootToAnother(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root 1', '1');
        $this->factory->insert('Child A', '1.1');
        $this->factory->insert('Root 2', '2');
        $childBId = $this->factory->insert('Child B', '2.1');

        $childB = $this->factory->findById($childBId);
        $childB->saveInto('1');

        $childB->refresh();
        $I->assertEquals('1.2', $childB->path);

        // Verify original tree still correct
        $childA = TreeNode::query()->where(['name' => 'Child A'])->one();
        $I->assertEquals('1.1', $childA->path);
    }

    public function testSaveNewNodeIntoSecondRoot(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root 1', '1');
        $this->factory->insert('Root 2', '2');

        $newChild = new TreeNode();
        $newChild->setName('New Child');
        $newChild->saveInto('2');

        $I->assertEquals('2.1', $newChild->path);
        $I->assertEquals(2, $newChild->level);
    }

    public function testCanMoveAcrossRoots(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root 1', '1');
        $childId = $this->factory->insert('Child', '1.1');
        $this->factory->insert('Root 2', '2');

        $child = $this->factory->findById($childId);

        // Can move to another root
        $I->assertTrue($child->canMove('2'));
        $I->assertTrue($child->canMove('2.1'));

        // Cannot move into own subtree
        $I->assertFalse($child->canMove('1.1'));
        $I->assertFalse($child->canMove('1.1.1'));
    }

    // ==================== find() method tests ====================

    public function testFindMethodReturnsQueryWithNodeContext(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('Child 1', '1.1');
        $this->factory->insert('Child 2', '1.2');

        $root = TreeNode::query()->where(['path' => '1'])->one();
        $query = $root->relativeQuery();

        // The query should be a TreeNodeQuery instance
        $I->assertInstanceOf(\Yiisoft\ActiveRecord\ActiveQueryInterface::class, $query);
    }

    // ==================== Tree consistency tests ====================

    public function testTreeConsistencyAfterMultipleOperations(HazeltreeTraitTester $I): void
    {
        // Create initial tree
        $this->factory->insert('Root', '1');
        $this->factory->insert('A', '1.1');
        $this->factory->insert('B', '1.2');
        $this->factory->insert('C', '1.3');

        // Move B before A (B takes 1.1, A becomes 1.2)
        $b = TreeNode::query()->where(['name' => 'B'])->one();
        $a = TreeNode::query()->where(['name' => 'A'])->one();
        $b->saveBefore($a);

        // Verify all nodes have valid left < right
        $allNodes = TreeNode::query()->all();
        foreach ($allNodes as $node) {
            $I->assertLessThan($node->right, $node->left, "Node {$node->path}: left should be less than right");
        }

        // Verify no overlapping siblings
        $root = TreeNode::query()->where(['path' => '1'])->one();
        $children = $root->relativeQuery()->children()->all();

        for ($i = 0; $i < count($children) - 1; $i++) {
            $current = $children[$i];
            $next = $children[$i + 1];
            $I->assertLessThanOrEqual(
                $next->left,
                $current->right,
                "Sibling {$current->path} should end before {$next->path} starts"
            );
        }
    }

    public function testTreeConsistencyAfterDeleteAndInsert(HazeltreeTraitTester $I): void
    {
        // Create tree
        $this->factory->insert('Root', '1');
        $this->factory->insert('A', '1.1');
        $this->factory->insert('B', '1.2');
        $this->factory->insert('C', '1.3');

        // Delete middle node
        $b = TreeNode::query()->where(['name' => 'B'])->one();
        $b->delete();

        // Insert new node
        $root = TreeNode::query()->where(['path' => '1'])->one();
        $newNode = new TreeNode();
        $newNode->setName('D');
        $newNode->saveInto($root);

        // Verify tree consistency
        $allNodes = TreeNode::query()->orderBy(['left' => SORT_ASC])->all();

        $I->assertCount(4, $allNodes);

        // Verify paths are correct
        $paths = array_map(fn($n) => $n->path, $allNodes);
        $I->assertEquals(['1', '1.1', '1.2', '1.3'], $paths);
    }

    // ==================== saveAfter on existing node tests ====================

    public function testSaveAfterExistingNodeMovesIt(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('A', '1.1');
        $this->factory->insert('B', '1.2');
        $cId = $this->factory->insert('C', '1.3');

        // Move C after A (so C becomes 1.2, B shifts to 1.3)
        $c = $this->factory->findById($cId);
        $a = TreeNode::query()->where(['path' => '1.1'])->one();
        $c->saveAfter($a);

        $c->refresh();
        $I->assertEquals('1.2', $c->path);

        $b = TreeNode::query()->where(['name' => 'B'])->one();
        $I->assertEquals('1.3', $b->path);
    }

    public function testSaveAfterExistingNodeWhenTargetIsLastSibling(HazeltreeTraitTester $I): void
    {
        // This tests the second branch of moveAfter (lines 936-960)
        // where target has no next sibling
        $this->factory->insert('Root', '1');
        $aId = $this->factory->insert('A', '1.1');
        $this->factory->insert('B', '1.2');
        $this->factory->insert('C', '1.3'); // C is last sibling

        // Move A after C (C is the last sibling, so no next sibling exists)
        // Initial: A=1.1, B=1.2, C=1.3
        // Step 1: A moves to 1.4 → A=1.4, B=1.2, C=1.3
        // Step 2: Close gap (bump -1) → B=1.1, C=1.2, A=1.3
        // Final: A is after C (1.2), so A=1.3
        $a = $this->factory->findById($aId);
        $c = TreeNode::query()->where(['path' => '1.3'])->one();
        $a->saveAfter($c);

        // Verify final positions
        $a->refresh();
        $b = TreeNode::query()->where(['name' => 'B'])->one();
        $c->refresh();

        $I->assertEquals('1.1', $b->path);
        $I->assertEquals('1.2', $c->path);
        $I->assertEquals('1.3', $a->path); // A is after C
    }

    public function testSaveAfterExistingNodeWithChildren(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('A', '1.1');
        $bId = $this->factory->insert('B', '1.2');
        $this->factory->insert('B1', '1.2.1');
        $this->factory->insert('C', '1.3');

        // Move B (with child B1) after C
        $b = $this->factory->findById($bId);
        $c = TreeNode::query()->where(['path' => '1.3'])->one();
        $b->saveAfter($c);

        $b->refresh();
        $I->assertEquals('1.3', $b->path);

        // Verify child moved too
        $b1 = TreeNode::query()->where(['name' => 'B1'])->one();
        $I->assertEquals('1.3.1', $b1->path);

        // Verify C shifted
        $c->refresh();
        $I->assertEquals('1.2', $c->path);
    }

    // ==================== moveInto with bump scenarios ====================

    public function testMoveIntoWithExistingSiblingsAfterTarget(HazeltreeTraitTester $I): void
    {
        // Tree: Root -> A, B, C
        // Move A into Root at last position (after C)
        // This tests the bump logic when there are siblings after the insertion point
        $this->factory->insert('Root', '1');
        $aId = $this->factory->insert('A', '1.1');
        $this->factory->insert('B', '1.2');
        $this->factory->insert('C', '1.3');

        $a = $this->factory->findById($aId);

        // Create a second root and move A into it
        $this->factory->insert('Root2', '2');
        $a->saveInto('2');

        $a->refresh();
        $I->assertEquals('2.1', $a->path);

        // Verify B and C shifted to fill the gap
        $b = TreeNode::query()->where(['name' => 'B'])->one();
        $c = TreeNode::query()->where(['name' => 'C'])->one();
        $I->assertEquals('1.1', $b->path);
        $I->assertEquals('1.2', $c->path);
    }

    public function testMoveIntoTargetWithExistingChildren(HazeltreeTraitTester $I): void
    {
        // Tree: Root1 -> A, B  and Root2 -> X
        // Move A into Root2 (which already has X)
        // A should become 2.2, after X
        $this->factory->insert('Root1', '1');
        $aId = $this->factory->insert('A', '1.1');
        $this->factory->insert('B', '1.2');
        $this->factory->insert('Root2', '2');
        $this->factory->insert('X', '2.1');

        $a = $this->factory->findById($aId);
        $a->saveInto('2');

        $a->refresh();
        $I->assertEquals('2.2', $a->path);

        // X should remain unchanged
        $x = TreeNode::query()->where(['name' => 'X'])->one();
        $I->assertEquals('2.1', $x->path);

        // B should shift to fill A's gap
        $b = TreeNode::query()->where(['name' => 'B'])->one();
        $I->assertEquals('1.1', $b->path);
    }

    public function testMoveIntoBumpsExistingChildrenWhenInsertingInMiddle(HazeltreeTraitTester $I): void
    {
        // This tests the bump logic at lines 794-807
        // Tree: Root -> A -> A1, A2 and Root -> B
        // Move B into A (B becomes A.3, after A2)
        $this->factory->insert('Root', '1');
        $this->factory->insert('A', '1.1');
        $this->factory->insert('A1', '1.1.1');
        $this->factory->insert('A2', '1.1.2');
        $bId = $this->factory->insert('B', '1.2');

        $b = $this->factory->findById($bId);
        $b->saveInto('1.1');

        $b->refresh();
        $I->assertEquals('1.1.3', $b->path);

        // A1 and A2 should remain unchanged
        $a1 = TreeNode::query()->where(['name' => 'A1'])->one();
        $a2 = TreeNode::query()->where(['name' => 'A2'])->one();
        $I->assertEquals('1.1.1', $a1->path);
        $I->assertEquals('1.1.2', $a2->path);
    }

    // ==================== moveInto bumpBack scenario ====================

    public function testMoveIntoWithDeepTreeBumpsBackCorrectly(HazeltreeTraitTester $I): void
    {
        // Tree: Root -> A -> A1, A2 and Root -> B -> B1
        // Move A to Root2, verify B and B1 adjust correctly
        $this->factory->insert('Root', '1');
        $aId = $this->factory->insert('A', '1.1');
        $this->factory->insert('A1', '1.1.1');
        $this->factory->insert('A2', '1.1.2');
        $this->factory->insert('B', '1.2');
        $this->factory->insert('B1', '1.2.1');
        $this->factory->insert('Root2', '2');

        $a = $this->factory->findById($aId);
        $a->saveInto('2');

        // Verify A and children moved
        $a->refresh();
        $I->assertEquals('2.1', $a->path);

        $a1 = TreeNode::query()->where(['name' => 'A1'])->one();
        $a2 = TreeNode::query()->where(['name' => 'A2'])->one();
        $I->assertEquals('2.1.1', $a1->path);
        $I->assertEquals('2.1.2', $a2->path);

        // Verify B and B1 shifted to fill the gap
        $b = TreeNode::query()->where(['name' => 'B'])->one();
        $b1 = TreeNode::query()->where(['name' => 'B1'])->one();
        $I->assertEquals('1.1', $b->path);
        $I->assertEquals('1.1.1', $b1->path);
    }

    // ==================== Root siblings operations ====================

    public function testGetNextSiblingsTreesForRoot(HazeltreeTraitTester $I): void
    {
        // This tests the getNextSiblingsTrees() method for root nodes (lines 429-433)
        $this->factory->insert('Root1', '1');
        $this->factory->insert('Child1', '1.1');
        $this->factory->insert('Root2', '2');
        $this->factory->insert('Child2', '2.1');
        $this->factory->insert('Root3', '3');

        // The find()->siblings()->next() should use getNextSiblingsTrees internally for roots
        $root1 = TreeNode::query()->where(['path' => '1'])->one();
        $nextRoots = $root1->relativeQuery()->siblings()->next()->includeDescendants()->all();

        // Should include Root2, Child2, Root3
        $I->assertCount(3, $nextRoots);
        $paths = array_map(fn($n) => $n->path, $nextRoots);
        $I->assertContains('2', $paths);
        $I->assertContains('2.1', $paths);
        $I->assertContains('3', $paths);
    }

    // ==================== saveBefore on existing node ====================

    public function testSaveBeforeExistingNodeMovesIt(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('A', '1.1');
        $this->factory->insert('B', '1.2');
        $cId = $this->factory->insert('C', '1.3');

        // Move C before B (C takes 1.2, B shifts to 1.3)
        $c = $this->factory->findById($cId);
        $b = TreeNode::query()->where(['path' => '1.2'])->one();
        $c->saveBefore($b);

        $c->refresh();
        $I->assertEquals('1.2', $c->path);

        $b->refresh();
        $I->assertEquals('1.3', $b->path);
    }

    public function testSaveBeforeExistingNodeWithChildrenMovesEntireSubtree(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $this->factory->insert('A', '1.1');
        $bId = $this->factory->insert('B', '1.2');
        $this->factory->insert('B1', '1.2.1');
        $this->factory->insert('B2', '1.2.2');
        $this->factory->insert('C', '1.3');

        // Move B (with children B1, B2) before A
        $b = $this->factory->findById($bId);
        $a = TreeNode::query()->where(['path' => '1.1'])->one();
        $b->saveBefore($a);

        $b->refresh();
        $I->assertEquals('1.1', $b->path);

        // Verify children moved
        $b1 = TreeNode::query()->where(['name' => 'B1'])->one();
        $b2 = TreeNode::query()->where(['name' => 'B2'])->one();
        $I->assertEquals('1.1.1', $b1->path);
        $I->assertEquals('1.1.2', $b2->path);

        // Verify A and C shifted
        $a->refresh();
        $I->assertEquals('1.2', $a->path);

        $c = TreeNode::query()->where(['name' => 'C'])->one();
        $I->assertEquals('1.3', $c->path);
    }

    // ==================== Standalone magic methods tests (no parent magic) ====================

    public function testStandaloneMagicGetReturnsTreeProperties(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();
        // Manually set internal state via reflection to simulate a loaded node
        $reflection = new \ReflectionClass($node);

        $pathProp = $reflection->getProperty('path');
        $pathProp->setAccessible(true);
        $pathProp->setValue($node, '1.2.3');

        $levelProp = $reflection->getProperty('level');
        $levelProp->setAccessible(true);
        $levelProp->setValue($node, 3);

        $leftProp = $reflection->getProperty('left');
        $leftProp->setAccessible(true);
        $leftProp->setValue($node, 1.5);

        $rightProp = $reflection->getProperty('right');
        $rightProp->setAccessible(true);
        $rightProp->setValue($node, 1.6);

        $I->assertEquals('1.2.3', $node->path);
        $I->assertEquals(3, $node->level);
        $I->assertEquals(1.5, $node->left);
        $I->assertEquals(1.6, $node->right);
    }

    public function testStandaloneMagicGetReturnsNullForUnknownProperty(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        // MagicComposeTrait triggers a PHP warning and returns null for unknown properties
        // Use @ to suppress warning in test
        $value = @$node->unknownProperty;
        $I->assertNull($value);
    }

    public function testStandaloneMagicSetThrowsForReadonlyTreeProperties(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->path = '1.2.3';
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->left = 1.5;
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->right = 1.6;
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->level = 3;
        });
    }

    public function testStandaloneMagicSetTriggersWarningForUnknownProperty(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        // MagicComposeTrait triggers a PHP warning for unknown properties
        // Use @ to suppress warning in test - no exception is thrown
        @($node->unknownProperty = 'value');
        $I->assertTrue(true);
    }

    public function testStandaloneMagicIssetReturnsTrueForSetProperties(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        // Before setting, should be false
        $I->assertFalse(isset($node->path));
        $I->assertFalse(isset($node->level));

        // Set via reflection
        $reflection = new \ReflectionClass($node);
        $pathProp = $reflection->getProperty('path');
        $pathProp->setAccessible(true);
        $pathProp->setValue($node, '1.2');

        $levelProp = $reflection->getProperty('level');
        $levelProp->setAccessible(true);
        $levelProp->setValue($node, 2);

        // After setting, should be true
        $I->assertTrue(isset($node->path));
        $I->assertTrue(isset($node->level));
    }

    public function testStandaloneMagicIssetReturnsFalseForUnknownProperty(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        // MagicComposeTrait triggers a PHP warning but returns false for unknown properties
        // Use @ to suppress warning in test
        $I->assertFalse(@isset($node->unknownProperty));
    }

    public function testStandaloneMagicCallGetterWorks(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        // Set via reflection
        $reflection = new \ReflectionClass($node);
        $pathProp = $reflection->getProperty('path');
        $pathProp->setAccessible(true);
        $pathProp->setValue($node, '1.2');

        $levelProp = $reflection->getProperty('level');
        $levelProp->setAccessible(true);
        $levelProp->setValue($node, 2);

        $I->assertEquals('1.2', $node->getPath());
        $I->assertEquals(2, $node->getLevel());
    }

    public function testStandaloneMagicCallSetterThrowsForReadonlyProperties(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setPath('1.2');
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setLeft(1.0);
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setRight(2.0);
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->setLevel(2);
        });
    }

    public function testStandaloneMagicCallThrowsForUnknownMethod(HazeltreeTraitTester $I): void
    {
        $node = new SimpleNode();

        // MagicComposeTrait throws Error for unknown methods
        // Codeception may wrap it in Codeception\Exception\Error, so we catch both
        $I->expectThrowable(\Throwable::class, function () use ($node) {
            $node->unknownMethod();
        });
    }

    // ==================== Protection tests ====================

    public function testSetMethodThrowsForTreeProperties(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Test', '1');
        $node = $this->factory->findById($id);

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->set('left', 1.5);
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->set('right', 2.5);
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->set('level', 5);
        });

        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->set('path', '1.2.3');
        });
    }

    public function testProtectHazeltreeAllowsWriteWhenDisabled(HazeltreeTraitTester $I): void
    {
        $id = $this->factory->insert('Test', '1');
        $node = $this->factory->findById($id);

        $node->protectHazeltree(false);

        // Should not throw
        $node->left = 99.0;
        $node->right = 100.0;
        $node->level = 42;
        $node->path = '9.9.9';

        $I->assertEquals(99.0, $node->left);
        $I->assertEquals(100.0, $node->right);
        $I->assertEquals(42, $node->level);
        $I->assertEquals('9.9.9', $node->path);

        // Re-enable protection
        $node->protectHazeltree(true);

        // Should throw again
        $I->expectThrowable(\Error::class, function () use ($node) {
            $node->left = 1.0;
        });
    }

    // ==================== Level Calculation tests ====================

    public function testGetSubtreeDepthNoChildren(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $root = $this->factory->findById($rootId);

        $I->assertEquals(0, $root->getSubtreeDepth());
    }

    public function testGetSubtreeDepthOneLevel(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $this->factory->insert('Child 1', '1.1');
        $this->factory->insert('Child 2', '1.2');
        $root = $this->factory->findById($rootId);

        $I->assertEquals(1, $root->getSubtreeDepth());
    }

    public function testGetSubtreeDepthTwoLevels(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $this->factory->insert('Child', '1.1');
        $this->factory->insert('Grandchild', '1.1.1');
        $root = $this->factory->findById($rootId);

        $I->assertEquals(2, $root->getSubtreeDepth());
    }

    public function testGetSubtreeDepthFromMiddleNode(HazeltreeTraitTester $I): void
    {
        $this->factory->insert('Root', '1');
        $childId = $this->factory->insert('Child', '1.1');
        $this->factory->insert('Grandchild 1', '1.1.1');
        $this->factory->insert('Grandchild 2', '1.1.2');
        $this->factory->insert('Great-grandchild', '1.1.1.1');
        $child = $this->factory->findById($childId);

        $I->assertEquals(2, $child->getSubtreeDepth());
    }

    public function testGetMaxLevelIfMoveBefore(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $sourceId = $this->factory->insert('Source', '2');
        $this->factory->insert('Source Child', '2.1');
        $root = $this->factory->findById($rootId);
        $source = $this->factory->findById($sourceId);

        // Moving source (level 1) + subtree (depth 1) before root (level 1)
        // Result: source would be at level 1, child at level 2
        $I->assertEquals(2, $source->getMaxLevelIfMoveBefore($root));
    }

    public function testGetMaxLevelIfMoveAfter(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $sourceId = $this->factory->insert('Source', '2');
        $this->factory->insert('Source Child', '2.1');
        $root = $this->factory->findById($rootId);
        $source = $this->factory->findById($sourceId);

        // Moving source (level 1) + subtree (depth 1) after root (level 1)
        $I->assertEquals(2, $source->getMaxLevelIfMoveAfter($root));
    }

    public function testGetMaxLevelIfMoveInto(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $sourceId = $this->factory->insert('Source', '2');
        $this->factory->insert('Source Child', '2.1');
        $root = $this->factory->findById($rootId);
        $source = $this->factory->findById($sourceId);

        // Moving source (will be level 2) + subtree (depth 1) into root (level 1)
        // Result: source would be at level 2, child at level 3
        $I->assertEquals(3, $source->getMaxLevelIfMoveInto($root));
    }

    public function testWouldExceedMaxLevelReturnsFalse(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $sourceId = $this->factory->insert('Source', '2');
        $this->factory->insert('Source Child', '2.1');
        $root = $this->factory->findById($rootId);
        $source = $this->factory->findById($sourceId);

        // Max level 5, moving into root would give level 3 max
        $I->assertFalse($source->wouldExceedMaxLevel($root, 'into', 5));
        $I->assertFalse($source->wouldExceedMaxLevel($root, 'before', 5));
        $I->assertFalse($source->wouldExceedMaxLevel($root, 'after', 5));
    }

    public function testWouldExceedMaxLevelReturnsTrue(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $sourceId = $this->factory->insert('Source', '2');
        $this->factory->insert('Source Child', '2.1');
        $root = $this->factory->findById($rootId);
        $source = $this->factory->findById($sourceId);

        // Max level 2, moving into root would give level 3 max
        $I->assertTrue($source->wouldExceedMaxLevel($root, 'into', 2));
    }

    public function testWouldExceedMaxLevelExactBoundary(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $sourceId = $this->factory->insert('Source', '2');
        $this->factory->insert('Source Child', '2.1');
        $root = $this->factory->findById($rootId);
        $source = $this->factory->findById($sourceId);

        // Max level 3, moving into root would give exactly level 3
        $I->assertFalse($source->wouldExceedMaxLevel($root, 'into', 3));
    }

    public function testWouldExceedMaxLevelInvalidModeThrows(HazeltreeTraitTester $I): void
    {
        $rootId = $this->factory->insert('Root', '1');
        $sourceId = $this->factory->insert('Source', '2');
        $root = $this->factory->findById($rootId);
        $source = $this->factory->findById($sourceId);

        $I->expectThrowable(\InvalidArgumentException::class, function () use ($source, $root) {
            $source->wouldExceedMaxLevel($root, 'invalid', 5);
        });
    }
}
