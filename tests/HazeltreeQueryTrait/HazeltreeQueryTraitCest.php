<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\HazeltreeQueryTrait;

use Blackcube\Hazeltree\Tests\Support\HazeltreeQueryTraitTester;
use Blackcube\Hazeltree\Tests\Support\Migrations\M241205120000CreateTreeNodes;
use Blackcube\Hazeltree\Tests\Support\MysqlHelper;
use Blackcube\Hazeltree\Tests\Support\TreeNode;
use Blackcube\Hazeltree\Tests\Support\TreeNodeFactory;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * Tests for HazeltreeQueryTrait composable API.
 *
 * Tree structure used in tests:
 * ```
 * 1 (Root A)
 *   1.1 (Child A1)
 *     1.1.1 (Grandchild A1a)
 *     1.1.2 (Grandchild A1b)
 *   1.2 (Child A2)
 *   1.3 (Child A3)
 * 2 (Root B)
 *   2.1 (Child B1)
 * ```
 */
class HazeltreeQueryTraitCest
{
    private ConnectionInterface $db;
    private TreeNodeFactory $factory;

    public function _before(HazeltreeQueryTraitTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();
        ConnectionProvider::set($this->db);
        $this->factory = new TreeNodeFactory($this->db);

        // Drop and recreate table
        $this->db->createCommand('DROP TABLE IF EXISTS `treeNodes`')->execute();
        $migration = new M241205120000CreateTreeNodes();
        $builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $migration->up($builder);

        // Build standard test tree
        $this->factory->insert('Root A', '1');
        $this->factory->insert('Child A1', '1.1');
        $this->factory->insert('Grandchild A1a', '1.1.1');
        $this->factory->insert('Grandchild A1b', '1.1.2');
        $this->factory->insert('Child A2', '1.2');
        $this->factory->insert('Child A3', '1.3');
        $this->factory->insert('Root B', '2');
        $this->factory->insert('Child B1', '2.1');
    }

    public function _after(HazeltreeQueryTraitTester $I): void
    {
        $this->db->createCommand('DROP TABLE IF EXISTS `treeNodes`')->execute();
    }

    // ==================== children() tests ====================

    public function testChildrenReturnsDirectChildren(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $children = $root->relativeQuery()->children()->all();

        $I->assertCount(3, $children);
        $I->assertEquals('1.1', $children[0]->path);
        $I->assertEquals('1.2', $children[1]->path);
        $I->assertEquals('1.3', $children[2]->path);
    }

    public function testChildrenIncludeDescendantsReturnsAll(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $descendants = $root->relativeQuery()->children()->includeDescendants()->all();

        $I->assertCount(5, $descendants);
        $paths = array_map(fn($n) => $n->path, $descendants);
        $I->assertContains('1.1', $paths);
        $I->assertContains('1.1.1', $paths);
        $I->assertContains('1.1.2', $paths);
        $I->assertContains('1.2', $paths);
        $I->assertContains('1.3', $paths);
    }

    public function testChildrenIncludeDescendantsIncludeSelf(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $nodes = $root->relativeQuery()->children()->includeDescendants()->includeSelf()->all();

        $I->assertCount(6, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertContains('1', $paths);
    }

    public function testChildrenOfLeafReturnsEmpty(HazeltreeQueryTraitTester $I): void
    {
        $leaf = TreeNode::query()->where(['path' => '1.1.1'])->one();

        $children = $leaf->relativeQuery()->children()->all();

        $I->assertCount(0, $children);
    }

    // ==================== parent() tests ====================

    public function testParentReturnsDirectParent(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        $parent = $child->relativeQuery()->parent()->one();

        $I->assertNotNull($parent);
        $I->assertEquals('1', $parent->path);
    }

    public function testParentIncludeAncestorsReturnsAllAncestors(HazeltreeQueryTraitTester $I): void
    {
        $grandchild = TreeNode::query()->where(['path' => '1.1.1'])->one();

        $ancestors = $grandchild->relativeQuery()->parent()->includeAncestors()->all();

        $I->assertCount(2, $ancestors);
        $paths = array_map(fn($n) => $n->path, $ancestors);
        $I->assertContains('1', $paths);
        $I->assertContains('1.1', $paths);
    }

    public function testParentIncludeAncestorsIncludeSelf(HazeltreeQueryTraitTester $I): void
    {
        $grandchild = TreeNode::query()->where(['path' => '1.1.1'])->one();

        $nodes = $grandchild->relativeQuery()->parent()->includeAncestors()->includeSelf()->all();

        $I->assertCount(3, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertContains('1.1.1', $paths);
    }

    public function testParentOfRootReturnsNull(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $parent = $root->relativeQuery()->parent()->one();

        $I->assertNull($parent);
    }

    // ==================== siblings() tests ====================

    public function testSiblingsReturnsAllSiblingsWithoutSelf(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.2'])->one();

        $siblings = $child->relativeQuery()->siblings()->all();

        $I->assertCount(2, $siblings);
        $paths = array_map(fn($n) => $n->path, $siblings);
        $I->assertContains('1.1', $paths);
        $I->assertContains('1.3', $paths);
        $I->assertNotContains('1.2', $paths);
    }

    public function testSiblingsIncludeSelfReturnsAllWithSelf(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.2'])->one();

        $siblings = $child->relativeQuery()->siblings()->includeSelf()->all();

        $I->assertCount(3, $siblings);
        $paths = array_map(fn($n) => $n->path, $siblings);
        $I->assertContains('1.2', $paths);
    }

    public function testSiblingsNextReturnsFollowingSiblings(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        $nextSiblings = $child->relativeQuery()->siblings()->next()->all();

        $I->assertCount(2, $nextSiblings);
        $I->assertEquals('1.2', $nextSiblings[0]->path);
        $I->assertEquals('1.3', $nextSiblings[1]->path);
    }

    public function testSiblingsPreviousReturnsPrecedingSiblings(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.3'])->one();

        $prevSiblings = $child->relativeQuery()->siblings()->previous()->all();

        // Ordered DESC (closest first)
        $I->assertCount(2, $prevSiblings);
        $I->assertEquals('1.2', $prevSiblings[0]->path);
        $I->assertEquals('1.1', $prevSiblings[1]->path);
    }

    public function testSiblingsNextOneReturnsImmediateNextSibling(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        $nextSibling = $child->relativeQuery()->siblings()->next()->one();

        $I->assertNotNull($nextSibling);
        $I->assertEquals('1.2', $nextSibling->path);
    }

    public function testSiblingsPreviousOneReturnsImmediatePreviousSibling(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.3'])->one();

        $prevSibling = $child->relativeQuery()->siblings()->previous()->one();

        $I->assertNotNull($prevSibling);
        $I->assertEquals('1.2', $prevSibling->path);
    }

    public function testSiblingsNextOfLastReturnsEmpty(HazeltreeQueryTraitTester $I): void
    {
        $lastChild = TreeNode::query()->where(['path' => '1.3'])->one();

        $nextSiblings = $lastChild->relativeQuery()->siblings()->next()->all();

        $I->assertCount(0, $nextSiblings);
    }

    public function testSiblingsPreviousOfFirstReturnsEmpty(HazeltreeQueryTraitTester $I): void
    {
        $firstChild = TreeNode::query()->where(['path' => '1.1'])->one();

        $prevSiblings = $firstChild->relativeQuery()->siblings()->previous()->all();

        $I->assertCount(0, $prevSiblings);
    }

    public function testSiblingsNextIncludeSelf(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        $nodes = $child->relativeQuery()->siblings()->next()->includeSelf()->all();

        $I->assertCount(3, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertContains('1.1', $paths);
        $I->assertContains('1.2', $paths);
        $I->assertContains('1.3', $paths);
    }

    public function testSiblingsPreviousIncludeSelf(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.3'])->one();

        $nodes = $child->relativeQuery()->siblings()->previous()->includeSelf()->all();

        $I->assertCount(3, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertContains('1.1', $paths);
        $I->assertContains('1.2', $paths);
        $I->assertContains('1.3', $paths);
    }

    public function testSiblingsNextIncludeDescendants(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        // Next siblings are 1.2 and 1.3, they have no children
        $nodes = $child->relativeQuery()->siblings()->next()->includeDescendants()->all();

        $I->assertCount(2, $nodes);
    }

    // ==================== Root siblings tests ====================

    public function testRootSiblingsReturnsOtherRoots(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $siblings = $root->relativeQuery()->siblings()->all();

        $I->assertCount(1, $siblings);
        $I->assertEquals('2', $siblings[0]->path);
    }

    public function testRootSiblingsNextReturnsFollowingRoots(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $nextSiblings = $root->relativeQuery()->siblings()->next()->all();

        $I->assertCount(1, $nextSiblings);
        $I->assertEquals('2', $nextSiblings[0]->path);
    }

    public function testRootSiblingsPreviousOfFirstRootReturnsEmpty(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $prevSiblings = $root->relativeQuery()->siblings()->previous()->all();

        $I->assertCount(0, $prevSiblings);
    }

    // ==================== Composability order tests ====================

    public function testParentIncludeAncestorsOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $grandchild = TreeNode::query()->where(['path' => '1.1.1'])->one();

        // Order 1: parent()->includeAncestors()
        $result1 = $grandchild->relativeQuery()->parent()->includeAncestors()->all();

        // Order 2: includeAncestors()->parent()
        $result2 = $grandchild->relativeQuery()->includeAncestors()->parent()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    public function testParentIncludeSelfOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        // Order 1: parent()->includeSelf()
        $result1 = $child->relativeQuery()->parent()->includeSelf()->all();

        // Order 2: includeSelf()->parent()
        $result2 = $child->relativeQuery()->includeSelf()->parent()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    public function testParentIncludeAncestorsIncludeSelfOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $grandchild = TreeNode::query()->where(['path' => '1.1.1'])->one();

        // Order 1: parent()->includeAncestors()->includeSelf()
        $result1 = $grandchild->relativeQuery()->parent()->includeAncestors()->includeSelf()->all();

        // Order 2: includeSelf()->includeAncestors()->parent()
        $result2 = $grandchild->relativeQuery()->includeSelf()->includeAncestors()->parent()->all();

        // Order 3: includeAncestors()->includeSelf()->parent()
        $result3 = $grandchild->relativeQuery()->includeAncestors()->includeSelf()->parent()->all();

        $I->assertCount(count($result1), $result2);
        $I->assertCount(count($result1), $result3);

        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        $paths3 = array_map(fn($n) => $n->path, $result3);
        sort($paths1);
        sort($paths2);
        sort($paths3);
        $I->assertEquals($paths1, $paths2);
        $I->assertEquals($paths1, $paths3);
    }

    public function testChildrenIncludeDescendantsOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        // Order 1: children()->includeDescendants()
        $result1 = $root->relativeQuery()->children()->includeDescendants()->all();

        // Order 2: includeDescendants()->children()
        $result2 = $root->relativeQuery()->includeDescendants()->children()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    public function testChildrenIncludeSelfOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        // Order 1: children()->includeSelf()
        $result1 = $root->relativeQuery()->children()->includeSelf()->all();

        // Order 2: includeSelf()->children()
        $result2 = $root->relativeQuery()->includeSelf()->children()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    public function testChildrenIncludeDescendantsIncludeSelfOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        // Order 1: children()->includeDescendants()->includeSelf()
        $result1 = $root->relativeQuery()->children()->includeDescendants()->includeSelf()->all();

        // Order 2: includeSelf()->includeDescendants()->children()
        $result2 = $root->relativeQuery()->includeSelf()->includeDescendants()->children()->all();

        // Order 3: includeDescendants()->includeSelf()->children()
        $result3 = $root->relativeQuery()->includeDescendants()->includeSelf()->children()->all();

        $I->assertCount(count($result1), $result2);
        $I->assertCount(count($result1), $result3);

        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        $paths3 = array_map(fn($n) => $n->path, $result3);
        sort($paths1);
        sort($paths2);
        sort($paths3);
        $I->assertEquals($paths1, $paths2);
        $I->assertEquals($paths1, $paths3);
    }

    public function testSiblingsNextOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        // Order 1: siblings()->next()
        $result1 = $child->relativeQuery()->siblings()->next()->all();

        // Order 2: next()->siblings()
        $result2 = $child->relativeQuery()->next()->siblings()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    public function testSiblingsPreviousOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.3'])->one();

        // Order 1: siblings()->previous()
        $result1 = $child->relativeQuery()->siblings()->previous()->all();

        // Order 2: previous()->siblings()
        $result2 = $child->relativeQuery()->previous()->siblings()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    public function testSiblingsNextIncludeSelfOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        // Order 1: siblings()->next()->includeSelf()
        $result1 = $child->relativeQuery()->siblings()->next()->includeSelf()->all();

        // Order 2: includeSelf()->siblings()->next()
        $result2 = $child->relativeQuery()->includeSelf()->siblings()->next()->all();

        // Order 3: next()->includeSelf()->siblings()
        $result3 = $child->relativeQuery()->next()->includeSelf()->siblings()->all();

        $I->assertCount(count($result1), $result2);
        $I->assertCount(count($result1), $result3);

        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        $paths3 = array_map(fn($n) => $n->path, $result3);
        sort($paths1);
        sort($paths2);
        sort($paths3);
        $I->assertEquals($paths1, $paths2);
        $I->assertEquals($paths1, $paths3);
    }

    public function testSiblingsPreviousIncludeSelfOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.3'])->one();

        // Order 1: siblings()->previous()->includeSelf()
        $result1 = $child->relativeQuery()->siblings()->previous()->includeSelf()->all();

        // Order 2: includeSelf()->siblings()->previous()
        $result2 = $child->relativeQuery()->includeSelf()->siblings()->previous()->all();

        // Order 3: previous()->includeSelf()->siblings()
        $result3 = $child->relativeQuery()->previous()->includeSelf()->siblings()->all();

        $I->assertCount(count($result1), $result2);
        $I->assertCount(count($result1), $result3);

        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        $paths3 = array_map(fn($n) => $n->path, $result3);
        sort($paths1);
        sort($paths2);
        sort($paths3);
        $I->assertEquals($paths1, $paths2);
        $I->assertEquals($paths1, $paths3);
    }

    public function testSiblingsIncludeSelfOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.2'])->one();

        // Order 1: siblings()->includeSelf()
        $result1 = $child->relativeQuery()->siblings()->includeSelf()->all();

        // Order 2: includeSelf()->siblings()
        $result2 = $child->relativeQuery()->includeSelf()->siblings()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    // ==================== excludingSelf() tests ====================

    public function testExcludingSelfExcludesOnlyReferenceNode(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        $nodes = $child->relativeQuery()->excludingSelf()->all();

        // Total 8 nodes minus 1.1 = 7 nodes
        $I->assertCount(7, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertNotContains('1.1', $paths);
        $I->assertContains('1', $paths);
        $I->assertContains('1.1.1', $paths);
        $I->assertContains('1.1.2', $paths);
        $I->assertContains('1.2', $paths);
        $I->assertContains('1.3', $paths);
        $I->assertContains('2', $paths);
        $I->assertContains('2.1', $paths);
    }

    public function testExcludingSelfOnRootExcludesOnlyRoot(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $nodes = $root->relativeQuery()->excludingSelf()->all();

        // Total 8 nodes minus 1 = 7 nodes
        $I->assertCount(7, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertNotContains('1', $paths);
        $I->assertContains('1.1', $paths);
    }

    // ==================== excludingDescendants() tests ====================

    public function testExcludingDescendantsExcludesOnlyDescendants(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        $nodes = $child->relativeQuery()->excludingDescendants()->all();

        // Descendants of 1.1 are: 1.1.1, 1.1.2
        // Total 8 nodes minus 2 = 6 nodes
        $I->assertCount(6, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertContains('1.1', $paths); // Self is included
        $I->assertNotContains('1.1.1', $paths);
        $I->assertNotContains('1.1.2', $paths);
        $I->assertContains('1', $paths);
        $I->assertContains('1.2', $paths);
        $I->assertContains('1.3', $paths);
        $I->assertContains('2', $paths);
        $I->assertContains('2.1', $paths);
    }

    public function testExcludingDescendantsOnRootExcludesAllDescendants(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $nodes = $root->relativeQuery()->excludingDescendants()->all();

        // Descendants of 1 are: 1.1, 1.1.1, 1.1.2, 1.2, 1.3
        // Remaining: 1, 2, 2.1 = 3 nodes
        $I->assertCount(3, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertContains('1', $paths); // Self is included
        $I->assertContains('2', $paths);
        $I->assertContains('2.1', $paths);
        $I->assertNotContains('1.1', $paths);
    }

    public function testExcludingDescendantsOnLeafReturnsAll(HazeltreeQueryTraitTester $I): void
    {
        $leaf = TreeNode::query()->where(['path' => '1.1.1'])->one();

        $nodes = $leaf->relativeQuery()->excludingDescendants()->all();

        // Leaf has no descendants, so all 8 nodes returned
        $I->assertCount(8, $nodes);
    }

    // ==================== Combined excludingSelf + excludingDescendants tests ====================

    public function testExcludingSelfAndDescendantsExcludesBoth(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        $nodes = $child->relativeQuery()->excludingSelf()->excludingDescendants()->all();

        // Exclude 1.1 AND its descendants (1.1.1, 1.1.2)
        // Remaining: 1, 1.2, 1.3, 2, 2.1 = 5 nodes
        $I->assertCount(5, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertNotContains('1.1', $paths);
        $I->assertNotContains('1.1.1', $paths);
        $I->assertNotContains('1.1.2', $paths);
        $I->assertContains('1', $paths);
        $I->assertContains('1.2', $paths);
        $I->assertContains('1.3', $paths);
        $I->assertContains('2', $paths);
        $I->assertContains('2.1', $paths);
    }

    public function testExcludingSelfAndDescendantsOrderDoesNotMatter(HazeltreeQueryTraitTester $I): void
    {
        $child = TreeNode::query()->where(['path' => '1.1'])->one();

        // Order 1: excludingSelf()->excludingDescendants()
        $result1 = $child->relativeQuery()->excludingSelf()->excludingDescendants()->all();

        // Order 2: excludingDescendants()->excludingSelf()
        $result2 = $child->relativeQuery()->excludingDescendants()->excludingSelf()->all();

        $I->assertCount(count($result1), $result2);
        $paths1 = array_map(fn($n) => $n->path, $result1);
        $paths2 = array_map(fn($n) => $n->path, $result2);
        sort($paths1);
        sort($paths2);
        $I->assertEquals($paths1, $paths2);
    }

    public function testExcludingSelfAndDescendantsOnRootExcludesEntireSubtree(HazeltreeQueryTraitTester $I): void
    {
        $root = TreeNode::query()->where(['path' => '1'])->one();

        $nodes = $root->relativeQuery()->excludingSelf()->excludingDescendants()->all();

        // Exclude 1 AND its descendants (1.1, 1.1.1, 1.1.2, 1.2, 1.3)
        // Remaining: 2, 2.1 = 2 nodes
        $I->assertCount(2, $nodes);
        $paths = array_map(fn($n) => $n->path, $nodes);
        $I->assertContains('2', $paths);
        $I->assertContains('2.1', $paths);
    }
}
