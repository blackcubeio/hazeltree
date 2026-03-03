<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\TreeHelper;

use Blackcube\Hazeltree\Exceptions\InvalidSegmentException;
use Blackcube\Hazeltree\Helpers\Matrix;
use Blackcube\Hazeltree\Helpers\TreeHelper;
use Blackcube\Hazeltree\Tests\Support\TreeHelperTester;

final class TreeHelperCest
{
    // ==================== buildSegmentMatrix tests ====================

    public function testBuildSegmentMatrixForSegment1(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::buildSegmentMatrix(1);

        // Segment 1: | 1     1   |
        //            | 1   1+1=2 |
        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(1, $matrix->b);
        $I->assertEquals(1, $matrix->c);
        $I->assertEquals(2, $matrix->d);
    }

    public function testBuildSegmentMatrixForSegment2(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::buildSegmentMatrix(2);

        // Segment 2: | 1     1   |
        //            | 2   2+1=3 |
        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(1, $matrix->b);
        $I->assertEquals(2, $matrix->c);
        $I->assertEquals(3, $matrix->d);
    }

    public function testBuildSegmentMatrixForSegment5(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::buildSegmentMatrix(5);

        // Segment 5: | 1     1   |
        //            | 5   5+1=6 |
        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(1, $matrix->b);
        $I->assertEquals(5, $matrix->c);
        $I->assertEquals(6, $matrix->d);
    }

    public function testBuildSegmentMatrixThrowsForZero(TreeHelperTester $I): void
    {
        $I->expectThrowable(InvalidSegmentException::class, function () {
            TreeHelper::buildSegmentMatrix(0);
        });
    }

    public function testBuildSegmentMatrixThrowsForNegative(TreeHelperTester $I): void
    {
        $I->expectThrowable(InvalidSegmentException::class, function () {
            TreeHelper::buildSegmentMatrix(-1);
        });
    }

    // ==================== buildBumpMatrix tests ====================

    public function testBuildBumpMatrixWithZeroOffset(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::buildBumpMatrix(0);

        // Bump 0: | 1  0 |
        //         | 0  1 |
        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(0, $matrix->b);
        $I->assertEquals(0, $matrix->c);
        $I->assertEquals(1, $matrix->d);
    }

    public function testBuildBumpMatrixWithPositiveOffset(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::buildBumpMatrix(3);

        // Bump 3: | 1  0 |
        //         | 3  1 |
        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(0, $matrix->b);
        $I->assertEquals(3, $matrix->c);
        $I->assertEquals(1, $matrix->d);
    }

    public function testBuildBumpMatrixWithNegativeOffset(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::buildBumpMatrix(-2);

        // Bump -2: | 1   0 |
        //          | -2  1 |
        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(0, $matrix->b);
        $I->assertEquals(-2, $matrix->c);
        $I->assertEquals(1, $matrix->d);
    }

    // ==================== convert tests ====================

    public function testConvertSingleSegmentPath(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1');

        // Path "1" should give us the matrix for first child of root
        // Initial: | 0 1 | * | 1 1 | = | 1 2 |
        //          | 1 0 |   | 1 2 |   | 1 1 |
        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(2, $matrix->b);
        $I->assertEquals(1, $matrix->c);
        $I->assertEquals(1, $matrix->d);
    }

    public function testConvertPathToMatrix1(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1');

        // left = a/c = 1/1 = 1
        // right = b/d = 2/1 = 2
        $I->assertEquals(1, TreeHelper::getLeft($matrix));
        $I->assertEquals(2, TreeHelper::getRight($matrix));
    }

    public function testConvertPathToMatrix2(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('2');

        // Path "2" gives matrix a=2, b=3, c=1, d=1
        // left = a/c = 2/1 = 2
        // right = b/d = 3/1 = 3
        $I->assertEqualsWithDelta(2, TreeHelper::getLeft($matrix), 0.0001);
        $I->assertEqualsWithDelta(3, TreeHelper::getRight($matrix), 0.0001);
    }

    public function testConvertPathToMatrix1_1(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.1');

        // Path "1.1" - first child of first child
        $left = TreeHelper::getLeft($matrix);
        $right = TreeHelper::getRight($matrix);

        // 1.1 should be nested inside 1 (left=1, right=2)
        $I->assertGreaterThan(1, $left);
        $I->assertLessThan(2, $right);
        $I->assertLessThan($right, $left);
    }

    public function testConvertPathToMatrix1_2(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2');

        // Path "1.2" - second child of first child
        $left = TreeHelper::getLeft($matrix);
        $right = TreeHelper::getRight($matrix);

        // 1.2 should be nested inside 1 (left=1, right=2)
        $I->assertGreaterThan(1, $left);
        $I->assertLessThan(2, $right);
        $I->assertLessThan($right, $left);
    }

    public function testConvertPathToMatrixDeep(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2.3');

        // Path "1.2.3" - third child of second child of first child
        $left = TreeHelper::getLeft($matrix);
        $right = TreeHelper::getRight($matrix);

        // Should have valid left < right
        $I->assertLessThan($right, $left);
    }

    // ==================== convert round trip tests ====================

    public function testConvertMatrixToPathRoundTrip1(TreeHelperTester $I): void
    {
        $path = '1';
        $matrix = TreeHelper::convert($path);
        $result = TreeHelper::convert($matrix);

        $I->assertEquals($path, $result);
    }

    public function testConvertMatrixToPathRoundTrip2(TreeHelperTester $I): void
    {
        $path = '2';
        $matrix = TreeHelper::convert($path);
        $result = TreeHelper::convert($matrix);

        $I->assertEquals($path, $result);
    }

    public function testConvertMatrixToPathRoundTrip1_1(TreeHelperTester $I): void
    {
        $path = '1.1';
        $matrix = TreeHelper::convert($path);
        $result = TreeHelper::convert($matrix);

        $I->assertEquals($path, $result);
    }

    public function testConvertMatrixToPathRoundTrip1_2_3(TreeHelperTester $I): void
    {
        $path = '1.2.3';
        $matrix = TreeHelper::convert($path);
        $result = TreeHelper::convert($matrix);

        $I->assertEquals($path, $result);
    }

    public function testConvertMatrixToPathRoundTripDeep(TreeHelperTester $I): void
    {
        $path = '3.1.4.1.5';
        $matrix = TreeHelper::convert($path);
        $result = TreeHelper::convert($matrix);

        $I->assertEquals($path, $result);
    }

    // ==================== getLastSegment tests ====================

    public function testGetLastSegmentFromPathSingle(TreeHelperTester $I): void
    {
        $I->assertEquals(1, TreeHelper::getLastSegment('1'));
        $I->assertEquals(5, TreeHelper::getLastSegment('5'));
    }

    public function testGetLastSegmentFromPathMultiple(TreeHelperTester $I): void
    {
        $I->assertEquals(3, TreeHelper::getLastSegment('1.2.3'));
        $I->assertEquals(7, TreeHelper::getLastSegment('1.2.3.4.5.6.7'));
    }

    public function testGetLastSegmentFromMatrix(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2.3');
        $I->assertEquals(3, TreeHelper::getLastSegment($matrix));
    }

    public function testGetLastSegmentFromMatrixSingle(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('5');
        $I->assertEquals(5, TreeHelper::getLastSegment($matrix));
    }

    // ==================== getBasePath tests ====================

    public function testGetBasePathFromPathSingle(TreeHelperTester $I): void
    {
        $I->assertEquals('', TreeHelper::getBasePath('1'));
    }

    public function testGetBasePathFromPathMultiple(TreeHelperTester $I): void
    {
        $I->assertEquals('1.2', TreeHelper::getBasePath('1.2.3'));
        $I->assertEquals('1', TreeHelper::getBasePath('1.2'));
    }

    public function testGetBasePathFromMatrix(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2.3');
        $I->assertEquals('1.2', TreeHelper::getBasePath($matrix));
    }

    // ==================== getParent tests ====================

    public function testExtractParentMatrixFromRoot(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1');
        $parent = TreeHelper::getParent($matrix);

        // Root level (path "1") has no parent
        $I->assertNull($parent);
    }

    public function testExtractParentMatrixFromLevel2(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2');
        $parent = TreeHelper::getParent($matrix);

        $I->assertNotNull($parent);
        $parentPath = TreeHelper::convert($parent);
        $I->assertEquals('1', $parentPath);
    }

    public function testExtractParentMatrixFromLevel3(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2.3');
        $parent = TreeHelper::getParent($matrix);

        $I->assertNotNull($parent);
        $parentPath = TreeHelper::convert($parent);
        $I->assertEquals('1.2', $parentPath);
    }

    // ==================== getLevel tests ====================

    public function testGetLevelFromPathLevel1(TreeHelperTester $I): void
    {
        $I->assertEquals(1, TreeHelper::getLevel('1'));
        $I->assertEquals(1, TreeHelper::getLevel('5'));
    }

    public function testGetLevelFromPathLevel2(TreeHelperTester $I): void
    {
        $I->assertEquals(2, TreeHelper::getLevel('1.2'));
        $I->assertEquals(2, TreeHelper::getLevel('3.7'));
    }

    public function testGetLevelFromPathDeep(TreeHelperTester $I): void
    {
        $I->assertEquals(5, TreeHelper::getLevel('1.2.3.4.5'));
    }

    public function testGetLevelFromMatrixLevel1(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1');
        $I->assertEquals(1, TreeHelper::getLevel($matrix));
    }

    public function testGetLevelFromMatrixLevel2(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2');
        $I->assertEquals(2, TreeHelper::getLevel($matrix));
    }

    public function testGetLevelFromMatrixDeep(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('1.2.3.4.5');
        $I->assertEquals(5, TreeHelper::getLevel($matrix));
    }

    // ==================== getLeft / getRight tests ====================

    public function testLeftRightOrdering(TreeHelperTester $I): void
    {
        // For any valid path, left < right
        $paths = ['1', '2', '1.1', '1.2', '2.1', '1.2.3'];

        foreach ($paths as $path) {
            $matrix = TreeHelper::convert($path);
            $left = TreeHelper::getLeft($matrix);
            $right = TreeHelper::getRight($matrix);

            $I->assertLessThan($right, $left, "Path $path: left ($left) should be less than right ($right)");
        }
    }

    public function testChildrenNestedWithinParent(TreeHelperTester $I): void
    {
        $parentMatrix = TreeHelper::convert('1');
        $parentLeft = TreeHelper::getLeft($parentMatrix);
        $parentRight = TreeHelper::getRight($parentMatrix);

        $childMatrix = TreeHelper::convert('1.1');
        $childLeft = TreeHelper::getLeft($childMatrix);
        $childRight = TreeHelper::getRight($childMatrix);

        // Child should be nested within parent
        $I->assertGreaterThan($parentLeft, $childLeft);
        $I->assertLessThan($parentRight, $childRight);
    }

    public function testSiblingsDoNotOverlap(TreeHelperTester $I): void
    {
        $sibling1Matrix = TreeHelper::convert('1.1');
        $sibling1Left = TreeHelper::getLeft($sibling1Matrix);
        $sibling1Right = TreeHelper::getRight($sibling1Matrix);

        $sibling2Matrix = TreeHelper::convert('1.2');
        $sibling2Left = TreeHelper::getLeft($sibling2Matrix);
        $sibling2Right = TreeHelper::getRight($sibling2Matrix);

        // Siblings should not overlap - one should be entirely before or after the other
        $noOverlap = ($sibling1Right <= $sibling2Left) || ($sibling2Right <= $sibling1Left);
        $I->assertTrue($noOverlap, 'Siblings should not overlap');
    }

    // ==================== buildMoveMatrix tests ====================

    public function testBuildMoveMatrixNoBump(TreeHelperTester $I): void
    {
        $fromMatrix = TreeHelper::convert('1.1');
        $toMatrix = TreeHelper::convert('2.1');

        $moveMatrix = TreeHelper::buildMoveMatrix($fromMatrix, $toMatrix, 0);

        // The move matrix transforms coordinates from one position to another
        $I->assertInstanceOf(Matrix::class, $moveMatrix);
    }

    public function testBuildMoveMatrixWithBump(TreeHelperTester $I): void
    {
        $fromMatrix = TreeHelper::convert('1.1');
        $toMatrix = TreeHelper::convert('2.1');

        $moveMatrix = TreeHelper::buildMoveMatrix($fromMatrix, $toMatrix, 1);

        // The move matrix should be different with bump
        $I->assertInstanceOf(Matrix::class, $moveMatrix);
    }

    // ==================== PATH_SEPARATOR constant test ====================

    public function testPathSeparatorConstant(TreeHelperTester $I): void
    {
        $I->assertEquals('.', TreeHelper::PATH_SEPARATOR);
    }

    // ==================== getRootMatrix tests ====================

    public function testGetRootMatrixReturnsSwapMatrix(TreeHelperTester $I): void
    {
        $root = TreeHelper::getRootMatrix();

        // M₀ = | 0  1 |
        //      | 1  0 |
        $I->assertEquals(0, $root->a);
        $I->assertEquals(1, $root->b);
        $I->assertEquals(1, $root->c);
        $I->assertEquals(0, $root->d);
    }

    public function testGetRootMatrixIsNotIdentity(TreeHelperTester $I): void
    {
        $root = TreeHelper::getRootMatrix();

        // Must NOT be identity matrix [1,0,0,1]
        $I->assertFalse(
            $root->a === 1 && $root->b === 0 && $root->c === 0 && $root->d === 1,
            'Root matrix must not be identity'
        );
    }

    public function testGetRootMatrixDeterminantIsMinusOne(TreeHelperTester $I): void
    {
        $root = TreeHelper::getRootMatrix();
        $I->assertEquals(-1, $root->getDeterminant());
    }

    // ==================== getParent with string path tests ====================

    public function testGetParentWithPathStringRoot(TreeHelperTester $I): void
    {
        $parent = TreeHelper::getParent('1');
        $I->assertNull($parent);
    }

    public function testGetParentWithPathStringLevel2(TreeHelperTester $I): void
    {
        $parent = TreeHelper::getParent('1.2');
        $I->assertNotNull($parent);
        $I->assertEquals('1', TreeHelper::convert($parent));
    }

    public function testGetParentWithPathStringLevel3(TreeHelperTester $I): void
    {
        $parent = TreeHelper::getParent('2.4.3');
        $I->assertNotNull($parent);
        $I->assertEquals('2.4', TreeHelper::convert($parent));
    }

    public function testGetParentWithPathStringDeep(TreeHelperTester $I): void
    {
        $parent = TreeHelper::getParent('1.2.3.4.5');
        $I->assertNotNull($parent);
        $I->assertEquals('1.2.3.4', TreeHelper::convert($parent));
    }

    // ==================== getLeft/getRight with string path tests ====================

    public function testGetLeftWithPathString(TreeHelperTester $I): void
    {
        $left = TreeHelper::getLeft('1');
        $I->assertEquals(1.0, $left);
    }

    public function testGetRightWithPathString(TreeHelperTester $I): void
    {
        $right = TreeHelper::getRight('1');
        $I->assertEquals(2.0, $right);
    }

    public function testGetLeftRightWithPathStringPath2(TreeHelperTester $I): void
    {
        // Path "2" has matrix [2, 3, 1, 1]
        // left = 2/1 = 2, right = 3/1 = 3
        $I->assertEquals(2.0, TreeHelper::getLeft('2'));
        $I->assertEquals(3.0, TreeHelper::getRight('2'));
    }

    public function testGetLeftRightWithPathStringDeep(TreeHelperTester $I): void
    {
        $left = TreeHelper::getLeft('1.2.3');
        $right = TreeHelper::getRight('1.2.3');

        $I->assertLessThan($right, $left);
        $I->assertGreaterThan(1.0, $left);
        $I->assertLessThan(2.0, $right);
    }

    // ==================== Dan Hazel paper conformity tests ====================

    public function testPaperFigure3Path2Values(TreeHelperTester $I): void
    {
        // Per Figure 3 of the paper: path "2" gives nv=2, snv=3, dv=1, sdv=1
        $matrix = TreeHelper::convert('2');

        $I->assertEquals(2, $matrix->a, 'nv should be 2');
        $I->assertEquals(3, $matrix->b, 'snv should be 3');
        $I->assertEquals(1, $matrix->c, 'dv should be 1');
        $I->assertEquals(1, $matrix->d, 'sdv should be 1');
    }

    public function testPaperFigure3Path243Values(TreeHelperTester $I): void
    {
        // Per Figure 3 of the paper: path "2.4.3" gives nv=65, snv=82, dv=23, sdv=29
        $matrix = TreeHelper::convert('2.4.3');

        $I->assertEquals(65, $matrix->a, 'nv should be 65');
        $I->assertEquals(82, $matrix->b, 'snv should be 82');
        $I->assertEquals(23, $matrix->c, 'dv should be 23');
        $I->assertEquals(29, $matrix->d, 'sdv should be 29');
    }

    public function testPaperFigure3Path243LeftRight(TreeHelperTester $I): void
    {
        // left = 65/23, right = 82/29
        $matrix = TreeHelper::convert('2.4.3');

        $I->assertEqualsWithDelta(65 / 23, TreeHelper::getLeft($matrix), 0.0001);
        $I->assertEqualsWithDelta(82 / 29, TreeHelper::getRight($matrix), 0.0001);
    }

    public function testDeterminantAlwaysMinusOneForAllPaths(TreeHelperTester $I): void
    {
        $paths = [
            '1', '2', '3', '10',
            '1.1', '1.2', '2.4',
            '1.2.3', '2.4.3',
            '1.2.3.4.5.6.7.8.9.10',
        ];

        foreach ($paths as $path) {
            $matrix = TreeHelper::convert($path);
            $I->assertEquals(
                -1,
                $matrix->getDeterminant(),
                "Determinant for path '$path' should be -1"
            );
        }
    }

    public function testMatrixMultiplicationChain(TreeHelperTester $I): void
    {
        // Verify: M(2.4.3) = M₀ × S(2) × S(4) × S(3)
        $root = TreeHelper::getRootMatrix();
        $s2 = TreeHelper::buildSegmentMatrix(2);
        $s4 = TreeHelper::buildSegmentMatrix(4);
        $s3 = TreeHelper::buildSegmentMatrix(3);

        $result = $root->multiply($s2)->multiply($s4)->multiply($s3);
        $expected = TreeHelper::convert('2.4.3');

        $I->assertEquals($expected->a, $result->a);
        $I->assertEquals($expected->b, $result->b);
        $I->assertEquals($expected->c, $result->c);
        $I->assertEquals($expected->d, $result->d);
    }

    // ==================== getAncestorMatrices tests ====================

    public function testGetAncestorMatricesForRootReturnsEmpty(TreeHelperTester $I): void
    {
        // Path "1" has matrix [1, 2, 1, 1]
        $matrix = TreeHelper::convert('1');
        $ancestors = TreeHelper::getAncestorMatrices($matrix->a, $matrix->c);

        $I->assertEmpty($ancestors);
    }

    public function testGetAncestorMatricesForLevel2ReturnsOne(TreeHelperTester $I): void
    {
        // Path "2.4" should have ancestor "2"
        $matrix = TreeHelper::convert('2.4');
        $ancestors = TreeHelper::getAncestorMatrices($matrix->a, $matrix->c);

        $I->assertCount(1, $ancestors);
        $I->assertEquals('2', TreeHelper::convert($ancestors[0]));
    }

    public function testGetAncestorMatricesForLevel3ReturnsTwo(TreeHelperTester $I): void
    {
        // Path "2.4.3" should have ancestors "2" and "2.4"
        $matrix = TreeHelper::convert('2.4.3');
        $ancestors = TreeHelper::getAncestorMatrices($matrix->a, $matrix->c);

        $I->assertCount(2, $ancestors);
        $I->assertEquals('2', TreeHelper::convert($ancestors[0]));
        $I->assertEquals('2.4', TreeHelper::convert($ancestors[1]));
    }

    public function testGetAncestorMatricesOrderIsRootToParent(TreeHelperTester $I): void
    {
        // Path "1.2.3.4" should return ancestors in order: 1, 1.2, 1.2.3
        $matrix = TreeHelper::convert('1.2.3.4');
        $ancestors = TreeHelper::getAncestorMatrices($matrix->a, $matrix->c);

        $I->assertCount(3, $ancestors);
        $I->assertEquals('1', TreeHelper::convert($ancestors[0]));
        $I->assertEquals('1.2', TreeHelper::convert($ancestors[1]));
        $I->assertEquals('1.2.3', TreeHelper::convert($ancestors[2]));
    }

    // ==================== getAncestorPaths tests ====================

    public function testGetAncestorPathsForRootReturnsEmpty(TreeHelperTester $I): void
    {
        $ancestors = TreeHelper::getAncestorPaths('1');
        $I->assertEmpty($ancestors);
    }

    public function testGetAncestorPathsForLevel2(TreeHelperTester $I): void
    {
        $ancestors = TreeHelper::getAncestorPaths('2.4');
        $I->assertEquals(['2'], $ancestors);
    }

    public function testGetAncestorPathsForLevel3(TreeHelperTester $I): void
    {
        $ancestors = TreeHelper::getAncestorPaths('2.4.3');
        $I->assertEquals(['2', '2.4'], $ancestors);
    }

    public function testGetAncestorPathsForDeepPath(TreeHelperTester $I): void
    {
        $ancestors = TreeHelper::getAncestorPaths('1.2.3.4.5');
        $I->assertEquals(['1', '1.2', '1.2.3', '1.2.3.4'], $ancestors);
    }

    public function testGetAncestorPathsWithLargeSegments(TreeHelperTester $I): void
    {
        $ancestors = TreeHelper::getAncestorPaths('10.20.30');
        $I->assertEquals(['10', '10.20'], $ancestors);
    }

    // ==================== isAncestorOf tests ====================

    public function testIsAncestorOfReturnsTrueForDirectParent(TreeHelperTester $I): void
    {
        $I->assertTrue(TreeHelper::isAncestorOf('2.4', '2.4.3'));
    }

    public function testIsAncestorOfReturnsTrueForGrandparent(TreeHelperTester $I): void
    {
        $I->assertTrue(TreeHelper::isAncestorOf('2', '2.4.3'));
    }

    public function testIsAncestorOfReturnsTrueForRoot(TreeHelperTester $I): void
    {
        $I->assertTrue(TreeHelper::isAncestorOf('1', '1.2.3.4.5'));
    }

    public function testIsAncestorOfReturnsFalseForSameNode(TreeHelperTester $I): void
    {
        $I->assertFalse(TreeHelper::isAncestorOf('2.4.3', '2.4.3'));
    }

    public function testIsAncestorOfReturnsFalseForDescendant(TreeHelperTester $I): void
    {
        $I->assertFalse(TreeHelper::isAncestorOf('2.4.3', '2.4'));
    }

    public function testIsAncestorOfReturnsFalseForDifferentBranch(TreeHelperTester $I): void
    {
        $I->assertFalse(TreeHelper::isAncestorOf('1', '2.4.3'));
        $I->assertFalse(TreeHelper::isAncestorOf('1.1', '1.2.3'));
    }

    public function testIsAncestorOfReturnsFalseForSibling(TreeHelperTester $I): void
    {
        $I->assertFalse(TreeHelper::isAncestorOf('1.1', '1.2'));
        $I->assertFalse(TreeHelper::isAncestorOf('1.2', '1.1'));
    }

    // ==================== buildMoveMatrix complete tests ====================

    public function testBuildMoveMatrixSameParentNoBump(TreeHelperTester $I): void
    {
        $parent = TreeHelper::convert('1');
        $moveMatrix = TreeHelper::buildMoveMatrix($parent, $parent, 0);

        // Moving within same parent with no bump should give identity-like behavior
        $I->assertInstanceOf(Matrix::class, $moveMatrix);
    }

    public function testBuildMoveMatrixPreservesDeterminant(TreeHelperTester $I): void
    {
        $from = TreeHelper::convert('1');
        $to = TreeHelper::convert('2');
        $moveMatrix = TreeHelper::buildMoveMatrix($from, $to, 1);

        // The move matrix should have determinant = 1 (preserves structure)
        $det = $moveMatrix->getDeterminant();
        $I->assertEquals(1, $det);
    }

    public function testBuildMoveMatrixFormula(TreeHelperTester $I): void
    {
        // Test the formula: moveMatrix = toParent × bumpMatrix × fromParent⁻¹
        $fromParent = TreeHelper::convert('1.1');
        $toParent = TreeHelper::convert('2.1');

        $moveMatrix = TreeHelper::buildMoveMatrix($fromParent, $toParent, 0);

        // Verify manual calculation matches
        $bumpMatrix = TreeHelper::buildBumpMatrix(0);
        $fromInverse = $fromParent->inverse();
        $expected = $toParent->multiply($bumpMatrix)->multiply($fromInverse);

        $I->assertEquals($expected->a, $moveMatrix->a);
        $I->assertEquals($expected->b, $moveMatrix->b);
        $I->assertEquals($expected->c, $moveMatrix->c);
        $I->assertEquals($expected->d, $moveMatrix->d);
    }

    public function testBuildMoveMatrixWithBumpFormula(TreeHelperTester $I): void
    {
        $parent = TreeHelper::convert('1.1');

        $moveMatrix = TreeHelper::buildMoveMatrix($parent, $parent, 2);

        // Verify formula with bump
        $bumpMatrix = TreeHelper::buildBumpMatrix(2);
        $parentInverse = $parent->inverse();
        $expected = $parent->multiply($bumpMatrix)->multiply($parentInverse);

        $I->assertEquals($expected->a, $moveMatrix->a);
        $I->assertEquals($expected->b, $moveMatrix->b);
        $I->assertEquals($expected->c, $moveMatrix->c);
        $I->assertEquals($expected->d, $moveMatrix->d);
    }

    public function testBuildMoveMatrixDeterminantIsOne(TreeHelperTester $I): void
    {
        // All move matrices should have determinant = 1
        // (because they preserve the tree structure)
        $testCases = [
            ['from' => '1.1', 'to' => '2.1', 'bump' => 0],
            ['from' => '1.1', 'to' => '1.1', 'bump' => 2],
            ['from' => '1.1', 'to' => '1.1', 'bump' => -1],
            ['from' => '1.2.3', 'to' => '2.1', 'bump' => 5],
        ];

        foreach ($testCases as $case) {
            $from = TreeHelper::convert($case['from']);
            $to = TreeHelper::convert($case['to']);
            $moveMatrix = TreeHelper::buildMoveMatrix($from, $to, $case['bump']);

            $I->assertEquals(
                1,
                $moveMatrix->getDeterminant(),
                "Move matrix det should be 1 for {$case['from']} -> {$case['to']} bump {$case['bump']}"
            );
        }
    }

    public function testBuildMoveMatrixIntegerArithmetic(TreeHelperTester $I): void
    {
        // Move matrix components should be integers
        $from = TreeHelper::convert('1.2.3');
        $to = TreeHelper::convert('2.1.4');
        $moveMatrix = TreeHelper::buildMoveMatrix($from, $to, 2);

        $I->assertIsInt($moveMatrix->a);
        $I->assertIsInt($moveMatrix->b);
        $I->assertIsInt($moveMatrix->c);
        $I->assertIsInt($moveMatrix->d);
    }

    // ==================== Edge cases and stress tests ====================

    public function testDeepPathRoundTrip(TreeHelperTester $I): void
    {
        $path = '1.2.3.4.5.6.7.8.9.10';
        $matrix = TreeHelper::convert($path);
        $result = TreeHelper::convert($matrix);

        $I->assertEquals($path, $result);
    }

    public function testLargeSegmentValues(TreeHelperTester $I): void
    {
        $path = '100.200.300';
        $matrix = TreeHelper::convert($path);

        // Verify valid matrix
        $I->assertEquals(-1, $matrix->getDeterminant());
        $I->assertGreaterThan(0, $matrix->a);
        $I->assertGreaterThan(0, $matrix->c);

        // Round trip
        $I->assertEquals($path, TreeHelper::convert($matrix));
    }

    public function testNestedSetPropertiesHold(TreeHelperTester $I): void
    {
        // Verify that all nested set properties hold for a tree structure
        $paths = ['1', '1.1', '1.2', '1.1.1', '1.1.2', '1.2.1', '2', '2.1'];

        foreach ($paths as $path) {
            $matrix = TreeHelper::convert($path);
            $left = TreeHelper::getLeft($matrix);
            $right = TreeHelper::getRight($matrix);

            // Property 1: left < right
            $I->assertLessThan($right, $left, "Path $path: left < right");

            // Property 2: parent contains children
            $parent = TreeHelper::getParent($path);
            if ($parent !== null) {
                $parentLeft = TreeHelper::getLeft($parent);
                $parentRight = TreeHelper::getRight($parent);
                $I->assertGreaterThan($parentLeft, $left, "Path $path: left > parent.left");
                $I->assertLessThan($parentRight, $right, "Path $path: right < parent.right");
            }
        }
    }

    public function testSiblingsAreContiguousOrTouching(TreeHelperTester $I): void
    {
        // Siblings should either touch or be contiguous
        $siblings = ['1.1', '1.2', '1.3'];

        for ($i = 0; $i < count($siblings) - 1; $i++) {
            $current = TreeHelper::convert($siblings[$i]);
            $next = TreeHelper::convert($siblings[$i + 1]);

            $currentRight = TreeHelper::getRight($current);
            $nextLeft = TreeHelper::getLeft($next);

            // In Hazeltree, siblings touch (right == left of next)
            $I->assertEqualsWithDelta(
                $currentRight,
                $nextLeft,
                0.0001,
                "Sibling {$siblings[$i]} right should equal {$siblings[$i + 1]} left"
            );
        }
    }

    public function testIntegerArithmeticPreserved(TreeHelperTester $I): void
    {
        // All matrix values should remain integers
        $paths = ['1', '2.4.3', '1.2.3.4.5', '10.20.30'];

        foreach ($paths as $path) {
            $matrix = TreeHelper::convert($path);

            $I->assertIsInt($matrix->a, "Path $path: a should be int");
            $I->assertIsInt($matrix->b, "Path $path: b should be int");
            $I->assertIsInt($matrix->c, "Path $path: c should be int");
            $I->assertIsInt($matrix->d, "Path $path: d should be int");
        }
    }

    // ==================== Inverse integer arithmetic test ====================

    public function testInverseIsIntegerArithmetic(TreeHelperTester $I): void
    {
        $matrix = TreeHelper::convert('2.4.3');
        $inverse = $matrix->inverse();

        // All elements must be integers
        $I->assertIsInt($inverse->a);
        $I->assertIsInt($inverse->b);
        $I->assertIsInt($inverse->c);
        $I->assertIsInt($inverse->d);

        // M × M⁻¹ = identity
        $product = $matrix->multiply($inverse);
        $I->assertEquals(1, $product->a, 'Product a should be 1');
        $I->assertEquals(0, $product->b, 'Product b should be 0');
        $I->assertEquals(0, $product->c, 'Product c should be 0');
        $I->assertEquals(1, $product->d, 'Product d should be 1');
    }

    // ==================== getParent edge cases ====================

    public function testGetParentReturnsNullForInvalidMatrix(TreeHelperTester $I): void
    {
        // Test the guard clause: if c <= 0 or d <= 0, return null
        // This shouldn't happen with valid Hazeltree matrices, but the code handles it

        // Create a matrix with c = 0 (invalid for Hazeltree)
        $invalidMatrix = new Matrix([1, 2, 0, 1]);
        $parent = TreeHelper::getParent($invalidMatrix);
        $I->assertNull($parent, 'Should return null when c <= 0');

        // Create a matrix with d = 0 (invalid for Hazeltree)
        $invalidMatrix2 = new Matrix([1, 2, 1, 0]);
        $parent2 = TreeHelper::getParent($invalidMatrix2);
        $I->assertNull($parent2, 'Should return null when d <= 0');

        // Create a matrix with negative c
        $invalidMatrix3 = new Matrix([1, 2, -1, 1]);
        $parent3 = TreeHelper::getParent($invalidMatrix3);
        $I->assertNull($parent3, 'Should return null when c < 0');

        // Create a matrix with negative d
        $invalidMatrix4 = new Matrix([1, 2, 1, -1]);
        $parent4 = TreeHelper::getParent($invalidMatrix4);
        $I->assertNull($parent4, 'Should return null when d < 0');
    }

    public function testGetParentReturnsNullWhenParentAIsInvalid(TreeHelperTester $I): void
    {
        // Test the second guard clause: if parentMatrix.a <= 0, return null
        // This tests the case where the computed parent would be invalid

        // The root matrix [0,1,1,0] has a = 0, so its "parent" would be invalid
        $rootMatrix = TreeHelper::getRootMatrix();
        $parent = TreeHelper::getParent($rootMatrix);
        $I->assertNull($parent, 'Root matrix should have no parent');
    }
}
