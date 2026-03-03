<?php

declare(strict_types=1);

namespace Blackcube\Hazeltree\Tests\Matrix;

use Blackcube\Hazeltree\Helpers\Matrix;
use Blackcube\Hazeltree\Tests\Support\MatrixTester;

/**
 * Unit tests for Matrix class.
 * Target: 100% coverage of mathematical operations.
 */
final class MatrixCest
{
    // ==================== Construction and properties ====================

    public function testConstructor(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);

        $I->assertEquals(1, $matrix->a);
        $I->assertEquals(2, $matrix->b);
        $I->assertEquals(3, $matrix->c);
        $I->assertEquals(4, $matrix->d);
    }

    public function testToArray(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);

        $I->assertEquals([1, 2, 3, 4], $matrix->toArray());
    }

    // ==================== Determinant ====================

    public function testDeterminantStandard(MatrixTester $I): void
    {
        // det = (a*d) - (b*c) = 1*4 - 2*3 = -2
        $matrix = new Matrix([1, 2, 3, 4]);

        $I->assertEquals(-2, $matrix->getDeterminant());
    }

    public function testDeterminantIdentityMatrix(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 0, 0, 1]);

        $I->assertEquals(1, $matrix->getDeterminant());
    }

    public function testDeterminantZero(MatrixTester $I): void
    {
        // det = 2*4 - 4*2 = 0
        $matrix = new Matrix([2, 4, 4, 8]);

        $I->assertEquals(0, $matrix->getDeterminant());
    }

    public function testDeterminantNegative(MatrixTester $I): void
    {
        // det = 1*4 - 2*3 = -2
        $matrix = new Matrix([1, 2, 3, 4]);

        $I->assertEquals(-2, $matrix->getDeterminant());
        $I->assertTrue($matrix->getDeterminant() < 0);
    }

    public function testDeterminantWithFloats(MatrixTester $I): void
    {
        // det = 1.5*4.0 - 2.5*3.0 = 6.0 - 7.5 = -1.5
        $matrix = new Matrix([1.5, 2.5, 3.0, 4.0]);

        $I->assertEqualsWithDelta(-1.5, $matrix->getDeterminant(), 0.0001);
    }

    // ==================== Scalar multiplication ====================

    public function testMultiplyByPositiveInteger(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->multiply(2);

        $I->assertEquals(2, $result->a);
        $I->assertEquals(4, $result->b);
        $I->assertEquals(6, $result->c);
        $I->assertEquals(8, $result->d);

        // Original unchanged (immutable)
        $I->assertEquals(1, $matrix->a);
    }

    public function testMultiplyByNegativeInteger(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->multiply(-2);

        $I->assertEquals(-2, $result->a);
        $I->assertEquals(-4, $result->b);
        $I->assertEquals(-6, $result->c);
        $I->assertEquals(-8, $result->d);
    }

    public function testMultiplyByZero(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->multiply(0);

        $I->assertEquals(0, $result->a);
        $I->assertEquals(0, $result->b);
        $I->assertEquals(0, $result->c);
        $I->assertEquals(0, $result->d);
    }

    public function testMultiplyByFloat(MatrixTester $I): void
    {
        $matrix = new Matrix([2, 4, 6, 8]);
        $result = $matrix->multiply(0.5);

        $I->assertEquals(1, $result->a);
        $I->assertEquals(2, $result->b);
        $I->assertEquals(3, $result->c);
        $I->assertEquals(4, $result->d);
    }

    // ==================== Matrix multiplication ====================

    public function testMultiplyByMatrix(MatrixTester $I): void
    {
        // | 1 2 | * | 5 6 | = | 1*5+2*7  1*6+2*8 | = | 19 22 |
        // | 3 4 |   | 7 8 |   | 3*5+4*7  3*6+4*8 |   | 43 50 |
        $matrix1 = new Matrix([1, 2, 3, 4]);
        $matrix2 = new Matrix([5, 6, 7, 8]);

        $result = $matrix1->multiply($matrix2);

        $I->assertEquals(19, $result->a);
        $I->assertEquals(22, $result->b);
        $I->assertEquals(43, $result->c);
        $I->assertEquals(50, $result->d);

        // Original unchanged
        $I->assertEquals(1, $matrix1->a);
    }

    public function testMultiplyByIdentityMatrix(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $identity = new Matrix([1, 0, 0, 1]);

        $result = $matrix->multiply($identity);

        $I->assertEquals(1, $result->a);
        $I->assertEquals(2, $result->b);
        $I->assertEquals(3, $result->c);
        $I->assertEquals(4, $result->d);
    }

    public function testMultiplyByInverseGivesIdentity(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $inverse = $matrix->inverse();

        $result = $matrix->multiply($inverse);

        // Should be identity matrix
        $I->assertEqualsWithDelta(1, $result->a, 0.0001);
        $I->assertEqualsWithDelta(0, $result->b, 0.0001);
        $I->assertEqualsWithDelta(0, $result->c, 0.0001);
        $I->assertEqualsWithDelta(1, $result->d, 0.0001);
    }

    public function testMultiplyNonCommutative(MatrixTester $I): void
    {
        // A*B != B*A
        $a = new Matrix([1, 2, 3, 4]);
        $b = new Matrix([5, 6, 7, 8]);

        $ab = $a->multiply($b);
        $ba = $b->multiply($a);

        // They should be different
        $I->assertNotEquals($ab->a, $ba->a);
        $I->assertNotEquals($ab->b, $ba->b);
    }

    // ==================== Adjugate ====================

    public function testAdjugate(MatrixTester $I): void
    {
        // Adjugate of | a b | is | d -b |
        //             | c d |    |-c  a |
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->adjugate();

        $I->assertEquals(4, $result->a);
        $I->assertEquals(-2, $result->b);
        $I->assertEquals(-3, $result->c);
        $I->assertEquals(1, $result->d);

        // Original unchanged
        $I->assertEquals(1, $matrix->a);
    }

    public function testDoubleAdjugateEqualsOriginal(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->adjugate()->adjugate();

        $I->assertEquals($matrix->a, $result->a);
        $I->assertEquals($matrix->b, $result->b);
        $I->assertEquals($matrix->c, $result->c);
        $I->assertEquals($matrix->d, $result->d);
    }

    // ==================== Inverse ====================

    public function testInverse(MatrixTester $I): void
    {
        // For | 1 2 |, det = -2
        //     | 3 4 |
        // Inverse = (1/det) * adj = (-1/2) * | 4 -2 | = | -2   1   |
        //                                    |-3  1 |   |  1.5 -0.5|
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->inverse();

        $I->assertEquals(-2, $result->a);
        $I->assertEquals(1, $result->b);
        $I->assertEquals(1.5, $result->c);
        $I->assertEquals(-0.5, $result->d);

        // Original unchanged
        $I->assertEquals(1, $matrix->a);
    }

    public function testInverseOfInverseEqualsOriginal(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->inverse()->inverse();

        $I->assertEqualsWithDelta($matrix->a, $result->a, 0.0001);
        $I->assertEqualsWithDelta($matrix->b, $result->b, 0.0001);
        $I->assertEqualsWithDelta($matrix->c, $result->c, 0.0001);
        $I->assertEqualsWithDelta($matrix->d, $result->d, 0.0001);
    }

    // ==================== Transpose ====================

    public function testTranspose(MatrixTester $I): void
    {
        // Transpose of | a b | is | a c |
        //              | c d |    | b d |
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->transpose();

        $I->assertEquals(1, $result->a);
        $I->assertEquals(3, $result->b);
        $I->assertEquals(2, $result->c);
        $I->assertEquals(4, $result->d);

        // Original unchanged
        $I->assertEquals(2, $matrix->b);
    }

    public function testDoubleTransposeEqualsOriginal(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);
        $result = $matrix->transpose()->transpose();

        $I->assertEquals($matrix->a, $result->a);
        $I->assertEquals($matrix->b, $result->b);
        $I->assertEquals($matrix->c, $result->c);
        $I->assertEquals($matrix->d, $result->d);
    }

    // ==================== Fluent interface ====================

    public function testFluentInterface(MatrixTester $I): void
    {
        $matrix = new Matrix([1, 2, 3, 4]);

        // Chain: adjugate then multiply
        $result = $matrix->adjugate()->multiply(2);

        // Original unchanged
        $I->assertEquals(1, $matrix->a);

        // Result of adjugate * 2
        $I->assertEquals(8, $result->a);   // 4 * 2
        $I->assertEquals(-4, $result->b);  // -2 * 2
        $I->assertEquals(-6, $result->c);  // -3 * 2
        $I->assertEquals(2, $result->d);   // 1 * 2
    }
}
