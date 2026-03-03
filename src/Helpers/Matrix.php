<?php

declare(strict_types=1);

/**
 * Matrix.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Hazeltree\Helpers;

/**
 * Immutable Matrix 2x2 with fluent interface
 *
 *  Matrix | a, b | is written in array [a, b, c, d]
 *         | c, d |
 *
 * Correspondence with Dan Hazel's paper "Using rational numbers to key nested sets":
 *  - a = nv  (Numerator Value) - numerator of the node
 *  - b = snv (Sibling Numerator Value) - numerator of the next sibling
 *  - c = dv  (Denominator Value) - denominator of the node
 *  - d = sdv (Sibling Denominator Value) - denominator of the next sibling
 *
 * Key property: For all Hazeltree nodes, det(M) = a*d - b*c = -1
 *
 * @see https://arxiv.org/abs/0806.3115v1
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final readonly class Matrix
{
    /**
     * @var int|float a11 = nv (Numerator Value)
     */
    public int|float $a;

    /**
     * @var int|float a12 = snv (Sibling Numerator Value)
     */
    public int|float $b;

    /**
     * @var int|float a21 = dv (Denominator Value)
     */
    public int|float $c;

    /**
     * @var int|float a22 = sdv (Sibling Denominator Value)
     */
    public int|float $d;

    /**
     * MatrixHelper constructor.
     *
     * @param array{0: int|float, 1: int|float, 2: int|float, 3: int|float} $data array must be in format [a, b, c, d]
     */
    public function __construct(array $data)
    {
        [$this->a, $this->b, $this->c, $this->d] = $data;
    }

    /**
     * Get matrix determinant
     *
     * @return int|float
     */
    public function getDeterminant(): int|float
    {
        return ($this->a * $this->d) - ($this->b * $this->c);
    }

    /**
     * Multiply matrix by another matrix or scalar
     *
     * @param Matrix|int|float $element element to multiply to the matrix
     * @return self new matrix instance
     */
    public function multiply(self|int|float $element): self
    {
        if ($element instanceof self) {
            return new self([
                $this->a * $element->a + $this->b * $element->c,
                $this->a * $element->b + $this->b * $element->d,
                $this->c * $element->a + $this->d * $element->c,
                $this->c * $element->b + $this->d * $element->d,
            ]);
        }

        return new self([
            $this->a * $element,
            $this->b * $element,
            $this->c * $element,
            $this->d * $element,
        ]);
    }

    /**
     * Get adjugate matrix
     *
     * @return self new matrix instance
     */
    public function adjugate(): self
    {
        return new self([
            $this->d, -1 * $this->b,
            -1 * $this->c, $this->a,
        ]);
    }

    /**
     * Get inverse matrix
     *
     * Optimized for Hazeltree where det(M) = ±1.
     * Uses integer arithmetic only (no division needed).
     *
     * Standard inverse formula: M⁻¹ = (1/det) * adjugate(M)
     * adjugate(M) = | d  -b |
     *               |-c   a |
     *
     * For det = 1:  M⁻¹ = [d, -b, -c, a]
     * For det = -1: M⁻¹ = [-d, b, c, -a]
     *
     * @return self new matrix instance
     */
    public function inverse(): self
    {
        $det = $this->getDeterminant();

        if ($det === 1 || $det === -1) {
            // Integer arithmetic only - no division needed
            // (1/det) * [d, -b, -c, a] = [d/det, -b/det, -c/det, a/det]
            return new self([
                intdiv($this->d, $det),
                intdiv(-$this->b, $det),
                intdiv(-$this->c, $det),
                intdiv($this->a, $det),
            ]);
        }

        // Fallback for non-Hazeltree matrices
        return $this->adjugate()->multiply(1 / $det);
    }

    /**
     * Get transposed matrix
     *
     * @return self new matrix instance
     */
    public function transpose(): self
    {
        return new self([
            $this->a, $this->c,
            $this->b, $this->d,
        ]);
    }

    /**
     * Convert matrix to array
     *
     * @return array{0: int|float, 1: int|float, 2: int|float, 3: int|float}
     */
    public function toArray(): array
    {
        return [
            $this->a, $this->b,
            $this->c, $this->d,
        ];
    }
}
