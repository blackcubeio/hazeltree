<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\HazeltreeTrait;

use Blackcube\Hazeltree\Tests\Support\HazeltreeTraitTester;
use Blackcube\Hazeltree\Tests\Support\Migrations\M241205120000CreateTreeNodes;
use Blackcube\Hazeltree\Tests\Support\MysqlHelper;
use Blackcube\Hazeltree\Tests\Support\TreeNode;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;

/**
 * Full integration test simulating a contributor's workflow.
 *
 * This test simulates building a complete website structure:
 * - Homepage with sections (About, Services, Contact)
 * - Blog with categories and articles
 * - Products catalog with categories
 *
 * Then performs various operations: reorganizing, moving content, deleting sections.
 */
final class ContributorIntegrationCest
{
    private ConnectionInterface $db;

    public function _before(HazeltreeTraitTester $I): void
    {
        $helper = new MysqlHelper();
        $this->db = $helper->createConnection();
        ConnectionProvider::set($this->db);

        $this->db->createCommand('DROP TABLE IF EXISTS `treeNodes`')->execute();

        $migration = new M241205120000CreateTreeNodes();
        $builder = new MigrationBuilder($this->db, new NullMigrationInformer());
        $migration->up($builder);
    }

    public function _after(HazeltreeTraitTester $I): void
    {
        $this->db->createCommand('DROP TABLE IF EXISTS `treeNodes`')->execute();
    }

    /**
     * Complete contributor workflow test.
     *
     * Scenario:
     * 1. Create initial website structure
     * 2. Add content to various sections
     * 3. Reorganize content (move pages between sections)
     * 4. Delete obsolete sections
     * 5. Verify tree integrity throughout
     */
    public function testCompleteContributorWorkflow(HazeltreeTraitTester $I): void
    {
        // ================================================================
        // PHASE 1: Create initial website structure
        // ================================================================
        $I->wantTo('build a complete website structure as a contributor');

        // Create main sections (roots)
        $homepage = $this->createNode('Homepage');
        $homepage->save();
        $I->assertEquals('1', $homepage->path);
        $I->assertEquals(1, $homepage->level);

        $blog = $this->createNode('Blog');
        $blog->save();
        $I->assertEquals('2', $blog->path);

        $products = $this->createNode('Products');
        $products->save();
        $I->assertEquals('3', $products->path);

        // Verify we have 3 roots
        $roots = TreeNode::query()->roots()->all();
        $I->assertCount(3, $roots);

        // ================================================================
        // PHASE 2: Build Homepage structure
        // ================================================================
        $I->wantTo('add pages under Homepage');

        $about = $this->createNode('About Us');
        $I->assertTrue($about->saveInto($homepage));
        $I->assertEquals('1.1', $about->path);
        $I->assertEquals(2, $about->level);

        $services = $this->createNode('Services');
        $I->assertTrue($services->saveInto($homepage));
        $I->assertEquals('1.2', $services->path);

        $contact = $this->createNode('Contact');
        $I->assertTrue($contact->saveInto($homepage));
        $I->assertEquals('1.3', $contact->path);

        // Add sub-services
        $webDev = $this->createNode('Web Development');
        $I->assertTrue($webDev->saveInto($services));
        $I->assertEquals('1.2.1', $webDev->path);

        $mobileDev = $this->createNode('Mobile Development');
        $I->assertTrue($mobileDev->saveInto($services));
        $I->assertEquals('1.2.2', $mobileDev->path);

        $consulting = $this->createNode('Consulting');
        $I->assertTrue($consulting->saveInto($services));
        $I->assertEquals('1.2.3', $consulting->path);

        // Verify Homepage children
        $homepageChildren = $homepage->relativeQuery()->children()->all();
        $I->assertCount(3, $homepageChildren);
        $I->assertEquals(['About Us', 'Services', 'Contact'], array_map(fn($n) => $n->getName(), $homepageChildren));

        // Verify Services children
        $servicesChildren = $services->relativeQuery()->children()->all();
        $I->assertCount(3, $servicesChildren);

        // ================================================================
        // PHASE 3: Build Blog structure
        // ================================================================
        $I->wantTo('create blog categories and articles');

        $techCategory = $this->createNode('Technology');
        $I->assertTrue($techCategory->saveInto($blog));

        $lifestyleCategory = $this->createNode('Lifestyle');
        $I->assertTrue($lifestyleCategory->saveInto($blog));

        $newsCategory = $this->createNode('News');
        $I->assertTrue($newsCategory->saveInto($blog));

        // Add articles to Technology
        $article1 = $this->createNode('Introduction to PHP 8.3');
        $I->assertTrue($article1->saveInto($techCategory));

        $article2 = $this->createNode('Understanding Nested Sets');
        $I->assertTrue($article2->saveInto($techCategory));

        $article3 = $this->createNode('Yii3 Framework Guide');
        $I->assertTrue($article3->saveInto($techCategory));

        // Add articles to Lifestyle
        $article4 = $this->createNode('Work-Life Balance Tips');
        $I->assertTrue($article4->saveInto($lifestyleCategory));

        $article5 = $this->createNode('Remote Work Best Practices');
        $I->assertTrue($article5->saveInto($lifestyleCategory));

        // Verify blog structure
        $blogChildren = $blog->relativeQuery()->children()->all();
        $I->assertCount(3, $blogChildren);

        $allBlogContent = $blog->relativeQuery()->children()->includeDescendants()->all();
        $I->assertCount(8, $allBlogContent); // 3 categories + 5 articles

        // ================================================================
        // PHASE 4: Build Products structure
        // ================================================================
        $I->wantTo('create product catalog');

        $electronics = $this->createNode('Electronics');
        $I->assertTrue($electronics->saveInto($products));

        $clothing = $this->createNode('Clothing');
        $I->assertTrue($clothing->saveInto($products));

        // Electronics subcategories
        $phones = $this->createNode('Phones');
        $I->assertTrue($phones->saveInto($electronics));

        $laptops = $this->createNode('Laptops');
        $I->assertTrue($laptops->saveInto($electronics));

        // Products
        $iphone = $this->createNode('iPhone 15');
        $I->assertTrue($iphone->saveInto($phones));

        $samsung = $this->createNode('Samsung Galaxy');
        $I->assertTrue($samsung->saveInto($phones));

        $macbook = $this->createNode('MacBook Pro');
        $I->assertTrue($macbook->saveInto($laptops));

        // Verify tree integrity
        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 5: Navigation queries
        // ================================================================
        $I->wantTo('verify navigation queries work correctly');

        // Get ancestors of iPhone
        $iphoneAncestors = $iphone->relativeQuery()->parent()->includeAncestors()->all();
        $I->assertCount(3, $iphoneAncestors);
        $ancestorNames = array_map(fn($n) => $n->getName(), $iphoneAncestors);
        $I->assertContains('Products', $ancestorNames);
        $I->assertContains('Electronics', $ancestorNames);
        $I->assertContains('Phones', $ancestorNames);

        // Get siblings of Technology category
        $techSiblings = $techCategory->relativeQuery()->siblings()->all();
        $I->assertCount(2, $techSiblings);
        $siblingNames = array_map(fn($n) => $n->getName(), $techSiblings);
        $I->assertContains('Lifestyle', $siblingNames);
        $I->assertContains('News', $siblingNames);

        // Get next sibling
        $nextSibling = $techCategory->relativeQuery()->siblings()->next()->one();
        $I->assertNotNull($nextSibling);
        $I->assertEquals('Lifestyle', $nextSibling->getName());

        // Get previous sibling
        $prevSibling = $lifestyleCategory->relativeQuery()->siblings()->previous()->one();
        $I->assertNotNull($prevSibling);
        $I->assertEquals('Technology', $prevSibling->getName());

        // ================================================================
        // PHASE 6: Reorganize content - Move articles between categories
        // ================================================================
        $I->wantTo('move articles between blog categories');

        // Move "Remote Work Best Practices" from Lifestyle to Technology
        $article5 = TreeNode::query()->where(['name' => 'Remote Work Best Practices'])->one();
        $I->assertTrue($article5->saveInto($techCategory));

        // Verify move
        $article5->refresh();
        $I->assertStringStartsWith('2.1.', $article5->path);

        // Technology should now have 4 articles
        $techArticles = $techCategory->relativeQuery()->children()->all();
        $I->assertCount(4, $techArticles);

        // Lifestyle should have 1 article
        $lifestyleCategory->refresh();
        $lifestyleArticles = $lifestyleCategory->relativeQuery()->children()->all();
        $I->assertCount(1, $lifestyleArticles);

        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 7: Reorder siblings - Move Consulting before Web Development
        // ================================================================
        $I->wantTo('reorder services by moving Consulting to first position');

        $consulting = TreeNode::query()->where(['name' => 'Consulting'])->one();
        $webDev = TreeNode::query()->where(['name' => 'Web Development'])->one();

        $I->assertTrue($consulting->saveBefore($webDev));

        // Verify new order
        $services->refresh();
        $servicesChildren = $services->relativeQuery()->children()->all();
        $childNames = array_map(fn($n) => $n->getName(), $servicesChildren);
        $I->assertEquals(['Consulting', 'Web Development', 'Mobile Development'], $childNames);

        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 8: Insert new page at specific position
        // ================================================================
        $I->wantTo('insert a new service after Web Development');

        $uiDesign = $this->createNode('UI/UX Design');
        $webDev = TreeNode::query()->where(['name' => 'Web Development'])->one();
        $I->assertTrue($uiDesign->saveAfter($webDev));

        // Verify order
        $services->refresh();
        $servicesChildren = $services->relativeQuery()->children()->all();
        $childNames = array_map(fn($n) => $n->getName(), $servicesChildren);
        $I->assertEquals(['Consulting', 'Web Development', 'UI/UX Design', 'Mobile Development'], $childNames);

        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 9: Move entire section to another location
        // ================================================================
        $I->wantTo('move entire Electronics section under a new parent');

        // Create a new "Tech Products" category under Products
        $techProducts = $this->createNode('Tech Products');
        $techProducts->saveInto($products);

        // Move Electronics (with all its children) into Tech Products
        $electronics = TreeNode::query()->where(['name' => 'Electronics'])->one();
        $I->assertTrue($electronics->saveInto($techProducts));

        // Verify the entire subtree moved
        $electronics->refresh();
        $phones = TreeNode::query()->where(['name' => 'Phones'])->one();
        $iphone = TreeNode::query()->where(['name' => 'iPhone 15'])->one();

        $I->assertEquals(3, $electronics->level);
        $I->assertEquals(4, $phones->level);
        $I->assertEquals(5, $iphone->level);

        // Verify ancestry
        $iphoneAncestors = $iphone->relativeQuery()->parent()->includeAncestors()->all();
        $ancestorNames = array_map(fn($n) => $n->getName(), $iphoneAncestors);
        $I->assertContains('Tech Products', $ancestorNames);
        $I->assertContains('Electronics', $ancestorNames);
        $I->assertContains('Phones', $ancestorNames);
        $I->assertContains('Products', $ancestorNames);

        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 10: Delete a category with all its content
        // ================================================================
        $I->wantTo('delete the News category and all its content');

        $newsCategory = TreeNode::query()->where(['name' => 'News'])->one();
        $I->assertNotNull($newsCategory, 'News category should exist');
        $newsCategory->delete();

        // Verify deletion
        $deletedCategory = TreeNode::query()->where(['name' => 'News'])->one();
        $I->assertNull($deletedCategory);

        // Blog should now have 2 categories
        $blog->refresh();
        $blogChildren = $blog->relativeQuery()->children()->all();
        $I->assertCount(2, $blogChildren);

        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 11: Delete a page with children
        // ================================================================
        $I->wantTo('delete Phones category with all its products');

        $phones = TreeNode::query()->where(['name' => 'Phones'])->one();
        $phones->delete();

        // Verify iPhone and Samsung are also deleted
        $iphone = TreeNode::query()->where(['name' => 'iPhone 15'])->one();
        $samsung = TreeNode::query()->where(['name' => 'Samsung Galaxy'])->one();
        $I->assertNull($iphone);
        $I->assertNull($samsung);

        // Electronics should still exist with only Laptops
        $electronics = TreeNode::query()->where(['name' => 'Electronics'])->one();
        $I->assertNotNull($electronics);
        $electronicsChildren = $electronics->relativeQuery()->children()->all();
        $I->assertCount(1, $electronicsChildren);
        $I->assertEquals('Laptops', $electronicsChildren[0]->getName());

        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 12: Complex reorganization - Merge sections
        // ================================================================
        $I->wantTo('merge Lifestyle articles into Technology');

        $lifestyleCategory = TreeNode::query()->where(['name' => 'Lifestyle'])->one();
        $lifestyleArticles = $lifestyleCategory->relativeQuery()->children()->all();

        foreach ($lifestyleArticles as $article) {
            $techCategory->refresh();
            $article->saveInto($techCategory);
        }

        // Delete empty Lifestyle category
        $lifestyleCategory->refresh();
        $lifestyleCategory->delete();

        // Verify Technology now has all articles
        $techCategory->refresh();
        $techArticles = $techCategory->relativeQuery()->children()->all();
        $I->assertCount(5, $techArticles);

        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 13: Verify final structure using fluent API
        // ================================================================
        $I->wantTo('verify final structure with fluent API queries');

        // Count all nodes
        $totalNodes = TreeNode::query()->count();
        $I->assertGreaterThan(15, $totalNodes);

        // Get all roots
        $roots = TreeNode::query()->roots()->all();
        $I->assertCount(3, $roots);
        $rootNames = array_map(fn($n) => $n->getName(), $roots);
        $I->assertEquals(['Homepage', 'Blog', 'Products'], $rootNames);

        // Get entire Homepage tree
        $homepage = TreeNode::query()->where(['name' => 'Homepage'])->one();
        $homepageTree = $homepage->relativeQuery()->children()->includeDescendants()->includeSelf()->all();
        $I->assertGreaterThan(5, count($homepageTree));

        // Verify reverse order works
        $servicesReversed = $services->relativeQuery()->children()->reverse()->all();
        $reversedNames = array_map(fn($n) => $n->getName(), $servicesReversed);
        $I->assertEquals(array_reverse(['Consulting', 'Web Development', 'UI/UX Design', 'Mobile Development']), $reversedNames);

        // Final integrity check
        $this->assertTreeIntegrity($I);

        // ================================================================
        // PHASE 14: Edge cases
        // ================================================================
        $I->wantTo('test edge cases');

        // Move node to become root sibling (now allowed)
        $about = TreeNode::query()->where(['name' => 'About Us'])->one();
        $aboutOldPath = $about->path;
        $about->saveBefore($homepage);
        $about->refresh();
        $homepage->refresh();
        $I->assertEquals(1, $about->level, 'About should now be at root level');
        $I->assertNotEquals($aboutOldPath, $about->path, 'About path should have changed');

        // Try to move parent into its own child (canMove should return false)
        $services = TreeNode::query()->where(['name' => 'Services'])->one();
        $webDev = TreeNode::query()->where(['name' => 'Web Development'])->one();
        $I->assertFalse($services->canMove($webDev->path));

        // Verify canMove returns true for valid moves
        $I->assertTrue($about->canMove($blog->path));

        $this->assertTreeIntegrity($I);
    }

    /**
     * Test building a deep tree structure (10+ levels).
     */
    public function testDeepTreeStructure(HazeltreeTraitTester $I): void
    {
        $I->wantTo('build and manipulate a deep tree structure');

        // Create root
        $root = $this->createNode('Level 1');
        $root->save();

        // Build 10 levels deep
        $parent = $root;
        for ($i = 2; $i <= 10; $i++) {
            $node = $this->createNode("Level $i");
            $node->saveInto($parent);
            $parent = $node;
        }

        // Verify depth
        $deepestNode = TreeNode::query()->where(['name' => 'Level 10'])->one();
        $I->assertEquals(10, $deepestNode->level);

        // Get all ancestors
        $ancestors = $deepestNode->relativeQuery()->parent()->includeAncestors()->all();
        $I->assertCount(9, $ancestors);

        // Move deep node to root level child
        $level5 = TreeNode::query()->where(['name' => 'Level 5'])->one();
        $level5->saveInto($root);

        // Verify level changed for entire subtree
        $level5->refresh();
        $I->assertEquals(2, $level5->level);

        $deepestNode->refresh();
        $I->assertEquals(7, $deepestNode->level); // Was 10, now 10 - 5 + 2 = 7

        $this->assertTreeIntegrity($I);
    }

    /**
     * Test concurrent-like operations (multiple operations in sequence).
     */
    public function testMultipleOperationsSequence(HazeltreeTraitTester $I): void
    {
        $I->wantTo('perform multiple operations in sequence without issues');

        // Create structure
        $root = $this->createNode('Root');
        $root->save();

        for ($i = 1; $i <= 5; $i++) {
            $child = $this->createNode("Child $i");
            $child->saveInto($root);

            for ($j = 1; $j <= 3; $j++) {
                $grandchild = $this->createNode("Child $i - Sub $j");
                $grandchild->saveInto($child);
            }
        }

        // Perform multiple moves
        $child1 = TreeNode::query()->where(['name' => 'Child 1'])->one();
        $child5 = TreeNode::query()->where(['name' => 'Child 5'])->one();

        // Move Child 1 after Child 5
        $child1->saveAfter($child5);

        // Move Child 3 before Child 2
        $child3 = TreeNode::query()->where(['name' => 'Child 3'])->one();
        $child2 = TreeNode::query()->where(['name' => 'Child 2'])->one();
        $child3->saveBefore($child2);

        // Delete Child 4
        $child4 = TreeNode::query()->where(['name' => 'Child 4'])->one();
        $child4->delete();

        // Verify final order
        $root->refresh();
        $children = $root->relativeQuery()->children()->all();
        $childNames = array_map(fn($n) => $n->getName(), $children);
        $I->assertEquals(['Child 3', 'Child 2', 'Child 5', 'Child 1'], $childNames);

        // Verify all grandchildren still exist for remaining children
        foreach ($children as $child) {
            $grandchildren = $child->relativeQuery()->children()->all();
            $I->assertCount(3, $grandchildren);
        }

        $this->assertTreeIntegrity($I);
    }

    /**
     * Test with special characters in names.
     */
    public function testSpecialCharactersInNames(HazeltreeTraitTester $I): void
    {
        $I->wantTo('handle special characters in node names');

        $root = $this->createNode('Root <test>');
        $root->save();

        $child1 = $this->createNode("Child with 'quotes'");
        $child1->saveInto($root);

        $child2 = $this->createNode('Child with "double quotes"');
        $child2->saveInto($root);

        $child3 = $this->createNode('Child with émojis');
        $child3->saveInto($root);

        $child4 = $this->createNode('Child/with/slashes');
        $child4->saveInto($root);

        // Verify all saved correctly
        $children = $root->relativeQuery()->children()->all();
        $I->assertCount(4, $children);

        // Verify can query
        $found = TreeNode::query()->where(['name' => "Child with 'quotes'"])->one();
        $I->assertNotNull($found);

        $this->assertTreeIntegrity($I);
    }

    /**
     * Helper to create a new TreeNode.
     */
    private function createNode(string $name): TreeNode
    {
        $node = new TreeNode();
        $node->setName($name);
        return $node;
    }

    /**
     * Assert that the tree maintains integrity (nested set properties).
     */
    private function assertTreeIntegrity(HazeltreeTraitTester $I): void
    {
        $allNodes = TreeNode::query()->orderBy(['left' => SORT_ASC])->all();

        foreach ($allNodes as $node) {
            // Left must be less than right
            $I->assertLessThan(
                $node->right,
                $node->left,
                "Node '{$node->getName()}' has invalid left/right: {$node->left} >= {$node->right}"
            );

            // Children must be within parent bounds
            $children = $node->relativeQuery()->children()->includeDescendants()->all();
            foreach ($children as $child) {
                $I->assertGreaterThan(
                    $node->left,
                    $child->left,
                    "Child '{$child->getName()}' left ({$child->left}) must be > parent '{$node->getName()}' left ({$node->left})"
                );
                $I->assertLessThan(
                    $node->right,
                    $child->right,
                    "Child '{$child->getName()}' right ({$child->right}) must be < parent '{$node->getName()}' right ({$node->right})"
                );
            }
        }

        // Check for overlapping siblings
        $roots = TreeNode::query()->roots()->all();
        $this->assertNoOverlap($I, $roots);

        foreach ($allNodes as $node) {
            $children = $node->relativeQuery()->children()->all();
            $this->assertNoOverlap($I, $children);
        }
    }

    /**
     * Assert that sibling nodes don't overlap.
     * Note: In Hazeltree, consecutive siblings touch (a.right == b.left), which is valid.
     */
    private function assertNoOverlap(HazeltreeTraitTester $I, array $siblings): void
    {
        for ($i = 0; $i < count($siblings); $i++) {
            for ($j = $i + 1; $j < count($siblings); $j++) {
                $a = $siblings[$i];
                $b = $siblings[$j];

                // Either a is before or touching b, or b is before or touching a
                // Siblings can touch (a.right == b.left) but not overlap (a.right > b.left && a.left < b.right)
                $aBeforeOrTouchingB = $a->right <= $b->left;
                $bBeforeOrTouchingA = $b->right <= $a->left;

                $I->assertTrue(
                    $aBeforeOrTouchingB || $bBeforeOrTouchingA,
                    "Siblings '{$a->getName()}' and '{$b->getName()}' overlap: [{$a->left}, {$a->right}] vs [{$b->left}, {$b->right}]"
                );
            }
        }
    }
}
