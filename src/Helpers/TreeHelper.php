<?php

declare(strict_types=1);

/**
 * TreeHelper.php
 *
 * PHP version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Hazeltree\Helpers;

use Blackcube\Hazeltree\Exceptions\InvalidSegmentException;

/**
 * Helper class to ease node creation / management
 *
 * This class implements conversion between path notation (e.g., "1.2.3")
 * and matrix representation for the nested set algorithm based on
 * Dan Hazel's paper "Using rational numbers to key nested sets".
 *
 * @see https://arxiv.org/abs/0806.3115v1
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
final class TreeHelper
{
    public const string PATH_SEPARATOR = '.';

    /**
     * Get the root matrix (swap matrix M₀)
     *
     * This is the starting point for all path-to-matrix conversions.
     * IMPORTANT: This is NOT an identity matrix!
     *
     * Per Dan Hazel's paper (equation 3.5):
     * M₀ = | 0  1 |
     *      | 1  0 |
     *
     * This "swap" matrix ensures det(M) = -1 for all nodes,
     * enabling integer-only arithmetic for inverse calculations.
     *
     * @return Matrix the root swap matrix
     */
    public static function getRootMatrix(): Matrix
    {
        return new Matrix([0, 1, 1, 0]);
    }

    /**
     * Build a move matrix to relocate a node from one position to another
     *
     * Formula from Dan Hazel's paper (equation 3.18):
     * moveMatrix = toParent × bumpMatrix × fromParent⁻¹
     *
     * Where:
     * - fromParent = parent matrix of the source position
     * - toParent = parent matrix of the target position
     * - bumpMatrix = position offset matrix (m - n where m=new position, n=old position)
     *
     * @param Matrix $fromMatrix parent matrix where we detach the node
     * @param Matrix $toMatrix parent matrix where we re-attach the node
     * @param int $bump position offset (newSegment - oldSegment)
     * @return Matrix the transformation matrix
     */
    public static function buildMoveMatrix(Matrix $fromMatrix, Matrix $toMatrix, int $bump = 0): Matrix
    {
        $bumpMatrix = self::buildBumpMatrix($bump);
        $fromInverse = $fromMatrix->inverse();
        return $toMatrix->multiply($bumpMatrix)->multiply($fromInverse);
    }

    /**
     * Convert between path and matrix notation
     *
     * Path to Matrix: Builds the matrix by multiplying the root matrix
     * with each segment matrix. Example for path '2.4.3':
     * M = M₀ × S(2) × S(4) × S(3)
     *
     * Matrix to Path: Extracts segments by walking up the tree.
     *
     * @param string|Matrix $element path in dot notation or matrix
     * @return Matrix|string matrix if string given, path if matrix given
     */
    public static function convert(string|Matrix $element): Matrix|string
    {
        if (is_string($element)) {
            $matrix = self::getRootMatrix();
            $nodePath = explode(self::PATH_SEPARATOR, $element);
            foreach ($nodePath as $segment) {
                $matrix = $matrix->multiply(self::buildSegmentMatrix((int)$segment));
            }
            return $matrix;
        }

        $nodePath = [];
        $currentMatrix = $element;
        do {
            $nodePath[] = self::getLastSegment($currentMatrix);
            $currentMatrix = self::getParent($currentMatrix);
        } while ($currentMatrix !== null);
        $nodePath = array_reverse($nodePath);
        return implode(self::PATH_SEPARATOR, $nodePath);
    }

    /**
     * Get parent matrix from path or matrix. null if current element is root
     *
     * @param string|Matrix $element
     * @return Matrix|null
     */
    public static function getParent(string|Matrix $element): ?Matrix
    {
        if (is_string($element)) {
            $element = self::convert($element);
        }

        if ($element->c <= 0 || $element->d <= 0) {
            return null;
        }

        $leafMatrix = self::buildSegmentMatrix(self::getLastSegment($element));
        $parentMatrix = $element->multiply($leafMatrix->inverse());

        if ($parentMatrix->a <= 0) {
            return null;
        }

        return $parentMatrix;
    }

    /**
     * Get the last segment number from a path or matrix
     *
     * @param Matrix|string $element full path in dot notation or in Matrix notation
     * @return int
     */
    public static function getLastSegment(Matrix|string $element): int
    {
        if ($element instanceof Matrix) {
            $lastSegment = (int)($element->a / ($element->b - $element->a));
        } else {
            $path = explode(self::PATH_SEPARATOR, $element);
            $lastSegment = (int)end($path);
        }
        return $lastSegment;
    }

    /**
     * Get the base path (without the last segment)
     *
     * @param Matrix|string $element
     * @return string
     */
    public static function getBasePath(Matrix|string $element): string
    {
        if ($element instanceof Matrix) {
            $element = self::convert($element);
        }
        $elements = explode(self::PATH_SEPARATOR, $element);
        array_pop($elements);
        return implode(self::PATH_SEPARATOR, $elements);
    }

    /**
     * Build segment matrix for a given segment number
     *
     * @param int $segment segment number (must be > 0)
     * @return Matrix
     * @throws InvalidSegmentException
     */
    public static function buildSegmentMatrix(int $segment): Matrix
    {
        if ($segment <= 0) {
            throw new InvalidSegmentException();
        }
        return new Matrix([
            1, 1,
            $segment, $segment + 1,
        ]);
    }

    /**
     * Build a bump matrix to shift node positions
     *
     * @param int $offset bump size
     * @return Matrix
     */
    public static function buildBumpMatrix(int $offset = 0): Matrix
    {
        return new Matrix([
            1, 0,
            $offset, 1,
        ]);
    }

    /**
     * Get the left border value from a path or matrix
     *
     * @param string|Matrix $element
     * @return float left border
     */
    public static function getLeft(string|Matrix $element): float
    {
        if (is_string($element)) {
            $element = self::convert($element);
        }
        return $element->a / $element->c;
    }

    /**
     * Get the right border value from a path or matrix
     *
     * @param string|Matrix $element
     * @return float right border
     */
    public static function getRight(string|Matrix $element): float
    {
        if (is_string($element)) {
            $element = self::convert($element);
        }
        return $element->b / $element->d;
    }

    /**
     * Get the level from a path or matrix
     *
     * @param string|Matrix $element
     * @return int level (1-indexed)
     */
    public static function getLevel(string|Matrix $element): int
    {
        if ($element instanceof Matrix) {
            $element = self::convert($element);
        }
        return (substr_count($element, self::PATH_SEPARATOR) + 1);
    }

    /**
     * Calculate ancestor matrices WITHOUT database access
     *
     * This implements the Euclidean algorithm from Dan Hazel's paper
     * (Section 2.2, Figure 4) to compute all ancestors from matrix values only.
     *
     * USE CASE: validation, tests, pure calculations without DB
     * FOR DATABASE: use nested set query (left < X AND right > Y) which is faster
     *
     * @param int $numerator the 'a' (nv) value of the node
     * @param int $denominator the 'c' (dv) value of the node
     * @return Matrix[] list of ancestor matrices (from root to direct parent)
     */
    public static function getAncestorMatrices(int $numerator, int $denominator): array
    {
        $ancestors = [];

        // Accumulators to reconstruct matrices
        $ancNv = 0;
        $ancDv = 1;
        $ancSnv = 1;
        $ancSdv = 0;

        while ($numerator > 0 && $denominator > 0) {
            $div = intdiv($numerator, $denominator);
            $mod = $numerator % $denominator;

            $ancNv = $ancNv + $div * $ancSnv;
            $ancDv = $ancDv + $div * $ancSdv;
            $ancSnv = $ancNv + $ancSnv;
            $ancSdv = $ancDv + $ancSdv;

            $ancestors[] = new Matrix([$ancNv, $ancSnv, $ancDv, $ancSdv]);

            $numerator = $mod;
            if ($numerator !== 0) {
                $denominator = $denominator % $mod;
                if ($denominator === 0) {
                    $denominator = 1;
                }
            }
        }

        // Remove the last element (it's the node itself, not an ancestor)
        array_pop($ancestors);

        return $ancestors;
    }

    /**
     * Calculate ancestor paths WITHOUT database access
     *
     * @param string $path the node path
     * @return string[] list of ancestor paths (from root to direct parent)
     */
    public static function getAncestorPaths(string $path): array
    {
        $matrix = self::convert($path);
        $ancestorMatrices = self::getAncestorMatrices($matrix->a, $matrix->c);

        return array_map(
            fn(Matrix $m) => self::convert($m),
            $ancestorMatrices
        );
    }

    /**
     * Check if ancestorPath is an ancestor of descendantPath WITHOUT database access
     *
     * Useful for validation (e.g., canMove() check) without DB queries.
     *
     * @param string $ancestorPath potential ancestor path
     * @param string $descendantPath potential descendant path
     * @return bool true if ancestorPath is an ancestor of descendantPath
     */
    public static function isAncestorOf(string $ancestorPath, string $descendantPath): bool
    {
        $ancestorPaths = self::getAncestorPaths($descendantPath);
        return in_array($ancestorPath, $ancestorPaths, true);
    }
}
