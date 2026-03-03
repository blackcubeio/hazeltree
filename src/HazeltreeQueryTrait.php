<?php

declare(strict_types=1);

/**
 * HazeltreeQueryTrait.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Hazeltree;

use Blackcube\Hazeltree\Helpers\TreeHelper;

/**
 * Trait providing composable tree navigation methods for ActiveQuery.
 *
 * This trait should be used on an ActiveQuery class that queries models using HazeltreeTrait.
 * It provides a fluent API with scopes and modifiers.
 *
 * Usage:
 * ```php
 * class NodeQuery extends ActiveQuery
 * {
 *     use HazeltreeQueryTrait;
 * }
 *
 * class Node extends ActiveRecord implements HazeltreeInterface
 * {
 *     use HazeltreeTrait;
 *
 *     public static function query(): NodeQuery
 *     {
 *         return new NodeQuery(static::class);
 *     }
 * }
 *
 * // Query examples:
 * $node->relativeQuery()->children()->all();
 * $node->relativeQuery()->children()->includeDescendants()->all();
 * $node->relativeQuery()->siblings()->next()->one();
 * $node->relativeQuery()->parent()->includeAncestors()->includeSelf()->all();
 * ```
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
trait HazeltreeQueryTrait
{
    /**
     * The node to query relative to.
     */
    private ?HazeltreeInterface $hazelTreeNode = null;

    /**
     * Whether a scope requiring node context has been applied.
     */
    private bool $scoped = false;

    /**
     * Current scope: 'children', 'parent', 'siblings', or null.
     */
    private ?string $hazelTreeScope = null;

    /**
     * Direction modifier for siblings: 'next', 'previous', or null (all).
     */
    private ?string $hazelTreeDirection = null;

    /**
     * Whether to include descendants of matched nodes.
     */
    private bool $hazelTreeIncludeDescendants = false;

    /**
     * Whether to include ancestors (for parent scope).
     */
    private bool $hazelTreeIncludeAncestors = false;

    /**
     * Whether to include the reference node itself.
     */
    private bool $hazelTreeIncludeSelf = false;

    /**
     * Whether to reverse the default sort order (DESC instead of ASC).
     */
    private bool $hazelTreeReverse = false;

    /**
     * Whether to exclude the reference node itself.
     */
    private bool $hazelTreeExcludeSelf = false;

    /**
     * Whether to exclude descendants of the reference node.
     */
    private bool $hazelTreeExcludeDescendants = false;

    // ==================== NODE CONTEXT ====================

    /**
     * Set the reference node for the query.
     * Called automatically by the model's relativeQuery() method.
     */
    public function forNode(HazeltreeInterface $node): static
    {
        $this->hazelTreeNode = $node;
        return $this;
    }

    // ==================== SCOPES ====================

    /**
     * Query all root nodes (level 1).
     * This scope does not require a reference node.
     */
    public function roots(): static
    {
        $this->scoped = true;
        $this->hazelTreeScope = 'roots';
        return $this;
    }

    /**
     * Query children of the reference node.
     */
    public function children(): static
    {
        $this->scoped = true;
        $this->hazelTreeScope = 'children';
        return $this;
    }

    /**
     * Query the parent of the reference node.
     */
    public function parent(): static
    {
        $this->scoped = true;
        $this->hazelTreeScope = 'parent';
        return $this;
    }

    /**
     * Query siblings of the reference node (excluding self by default).
     */
    public function siblings(): static
    {
        $this->scoped = true;
        $this->hazelTreeScope = 'siblings';
        return $this;
    }

    /**
     * Exclude the reference node from results.
     * Can be combined with excludingDescendants().
     */
    public function excludingSelf(): static
    {
        $this->scoped = true;
        $this->hazelTreeScope = 'excluding';
        $this->hazelTreeExcludeSelf = true;
        return $this;
    }

    /**
     * Exclude descendants of the reference node from results.
     * Can be combined with excludingSelf().
     */
    public function excludingDescendants(): static
    {
        $this->scoped = true;
        $this->hazelTreeScope = 'excluding';
        $this->hazelTreeExcludeDescendants = true;
        return $this;
    }

    // ==================== DIRECTION MODIFIERS ====================

    /**
     * Filter to next siblings only (after the reference node).
     * Only applies to siblings() scope.
     */
    public function next(): static
    {
        $this->hazelTreeDirection = 'next';
        return $this;
    }

    /**
     * Filter to previous siblings only (before the reference node).
     * Only applies to siblings() scope.
     */
    public function previous(): static
    {
        $this->hazelTreeDirection = 'previous';
        return $this;
    }

    // ==================== EXPANSION MODIFIERS ====================

    /**
     * Include all descendants of matched nodes.
     */
    public function includeDescendants(): static
    {
        $this->hazelTreeIncludeDescendants = true;
        return $this;
    }

    /**
     * Include all ancestors (for parent scope).
     */
    public function includeAncestors(): static
    {
        $this->hazelTreeIncludeAncestors = true;
        return $this;
    }

    /**
     * Include the reference node itself in results.
     */
    public function includeSelf(): static
    {
        $this->hazelTreeIncludeSelf = true;
        return $this;
    }

    /**
     * Use natural sort order (ASC). This is the default.
     */
    public function natural(): static
    {
        $this->hazelTreeReverse = false;
        return $this;
    }

    /**
     * Reverse the default sort order (DESC instead of ASC).
     */
    public function reverse(): static
    {
        $this->hazelTreeReverse = true;
        return $this;
    }

    // ==================== QUERY BUILDING ====================

    /**
     * Override prepare to apply tree conditions before execution.
     */
    public function prepare($builder): \Yiisoft\Db\Query\QueryInterface
    {
        // Only apply Hazeltree logic if model supports it
        if ($this->supportsHazeltree()) {
            if ($this->scoped) {
                // Applique TOUT : conditions du scope + orderBy
                $this->applyTreeConditions();
            } else {
                // Applique SEULEMENT ce qui n'a pas besoin de scope (orderBy)
                $this->applyOrderByOnly();
            }
        }

        return parent::prepare($builder);
    }

    /**
     * Check if the model supports Hazeltree.
     */
    private function supportsHazeltree(): bool
    {
        $model = $this->hazelTreeNode ?? $this->getModel();
        return $model instanceof HazeltreeInterface;
    }

    /**
     * Qualify a column name with the table name to avoid ambiguity in JOINs.
     */
    private function qualifyColumn(string $column): string
    {
        $model = $this->hazelTreeNode ?? $this->getModel();
        return $model->tableName() . '.[[' . $column . ']]';
    }

    /**
     * Apply only the orderBy when no scope is set.
     */
    private function applyOrderByOnly(): void
    {
        if ($this->getOrderBy() === []) {
            $model = $this->hazelTreeNode ?? $this->getModel();
            $this->orderBy([$this->qualifyColumn($model->leftColumn()) => $this->getDefaultSortDirection()]);
        }
    }

    /**
     * Apply the tree query conditions based on scope and modifiers.
     */
    private function applyTreeConditions(): void
    {
        /** @var HazeltreeInterface $model */
        $model = $this->hazelTreeNode ?? $this->getModel();
        $leftCol = $this->qualifyColumn($model->leftColumn());

        if ($this->hazelTreeScope === 'roots') {
            $this->applyRootsConditions($model);
        } elseif ($this->hazelTreeNode !== null) {
            $node = $this->hazelTreeNode;
            $rightCol = $this->qualifyColumn($node->rightColumn());
            $levelCol = $this->qualifyColumn($node->levelColumn());

            match ($this->hazelTreeScope) {
                'children' => $this->applyChildrenConditions($node, $leftCol, $rightCol, $levelCol),
                'parent' => $this->applyParentConditions($node, $leftCol, $rightCol, $levelCol),
                'siblings' => $this->applySiblingsConditions($node, $leftCol, $rightCol, $levelCol),
                'excluding' => $this->applyExcludingConditions($node, $leftCol, $rightCol),
                default => null,
            };
        }

        if ($this->getOrderBy() === []) {
            $this->orderBy([$leftCol => $this->getDefaultSortDirection()]);
        }
    }

    /**
     * Apply conditions for roots() scope.
     */
    private function applyRootsConditions(HazeltreeInterface $model): void
    {
        $this->andWhere([$this->qualifyColumn($model->levelColumn()) => 1]);
    }

    /**
     * Apply conditions for children() scope.
     */
    private function applyChildrenConditions(
        HazeltreeInterface $node,
        string $leftCol,
        string $rightCol,
        string $levelCol
    ): void {
        $leftOp = $this->hazelTreeIncludeSelf ? '>=' : '>';
        $rightOp = $this->hazelTreeIncludeSelf ? '<=' : '<';

        $this->andWhere([$leftOp, $leftCol, $node->left]);
        $this->andWhere([$rightOp, $rightCol, $node->right]);

        if (!$this->hazelTreeIncludeDescendants) {
            $this->andWhere([$levelCol => $node->level + 1]);
        }
    }

    /**
     * Apply conditions for parent() scope.
     */
    private function applyParentConditions(
        HazeltreeInterface $node,
        string $leftCol,
        string $rightCol,
        string $levelCol
    ): void {
        $leftOp = $this->hazelTreeIncludeSelf ? '<=' : '<';
        $rightOp = $this->hazelTreeIncludeSelf ? '>=' : '>';

        $this->andWhere([$leftOp, $leftCol, $node->left]);
        $this->andWhere([$rightOp, $rightCol, $node->right]);

        if (!$this->hazelTreeIncludeAncestors) {
            $this->andWhere([$levelCol => $node->level - 1]);
        }
    }

    /**
     * Apply conditions for siblings() scope.
     */
    private function applySiblingsConditions(
        HazeltreeInterface $node,
        string $leftCol,
        string $rightCol,
        string $levelCol
    ): void {
        if ($node->isRoot()) {
            $this->applyRootSiblingsConditions($node, $leftCol, $rightCol, $levelCol);
            return;
        }

        $parentMatrix = TreeHelper::getParent(TreeHelper::convert($node->path));
        $parentLeft = TreeHelper::getLeft($parentMatrix);
        $parentRight = TreeHelper::getRight($parentMatrix);

        $this->andWhere(['>', $leftCol, $parentLeft]);
        $this->andWhere(['<', $rightCol, $parentRight]);

        if ($this->hazelTreeDirection === 'next') {
            $this->andWhere(['>=', $leftCol, $this->hazelTreeIncludeSelf ? $node->left : $node->right]);
        } elseif ($this->hazelTreeDirection === 'previous') {
            $this->andWhere(['<=', $rightCol, $this->hazelTreeIncludeSelf ? $node->right : $node->left]);
        } elseif (!$this->hazelTreeIncludeSelf) {
            $this->andWhere(['<>', $leftCol, $node->left]);
        }

        if (!$this->hazelTreeIncludeDescendants) {
            $this->andWhere([$levelCol => $node->level]);
        }
    }

    /**
     * Apply conditions for root siblings.
     */
    private function applyRootSiblingsConditions(
        HazeltreeInterface $node,
        string $leftCol,
        string $rightCol,
        string $levelCol
    ): void {
        if ($this->hazelTreeDirection === 'next') {
            $this->andWhere(['>=', $leftCol, $this->hazelTreeIncludeSelf ? $node->left : $node->right]);
        } elseif ($this->hazelTreeDirection === 'previous') {
            $this->andWhere(['<=', $rightCol, $this->hazelTreeIncludeSelf ? $node->right : $node->left]);
        } elseif (!$this->hazelTreeIncludeSelf) {
            $this->andWhere(['<>', $leftCol, $node->left]);
        }

        if (!$this->hazelTreeIncludeDescendants) {
            $this->andWhere([$levelCol => 1]);
        }
    }

    /**
     * Apply conditions for excluding() scope.
     */
    private function applyExcludingConditions(
        HazeltreeInterface $node,
        string $leftCol,
        string $rightCol
    ): void {
        if ($this->hazelTreeExcludeSelf && $this->hazelTreeExcludeDescendants) {
            // Exclude self AND descendants: NOT (left >= node.left AND right <= node.right)
            $this->andWhere(['not', ['and',
                ['>=', $leftCol, $node->left],
                ['<=', $rightCol, $node->right],
            ]]);
        } elseif ($this->hazelTreeExcludeSelf) {
            // Exclude only self: left != node.left
            $this->andWhere(['<>', $leftCol, $node->left]);
        } elseif ($this->hazelTreeExcludeDescendants) {
            // Exclude only descendants (not self): NOT (left > node.left AND right < node.right)
            $this->andWhere(['not', ['and',
                ['>', $leftCol, $node->left],
                ['<', $rightCol, $node->right],
            ]]);
        }
    }

    /**
     * Get the default sort direction based on direction modifier and reverse flag.
     * - previous() direction uses DESC by default (to get closest first with one())
     * - reverse() inverts the default order
     */
    private function getDefaultSortDirection(): int
    {
        $isDescByDefault = $this->hazelTreeDirection === 'previous';

        if ($this->hazelTreeReverse) {
            return $isDescByDefault ? SORT_ASC : SORT_DESC;
        }

        return $isDescByDefault ? SORT_DESC : SORT_ASC;
    }
}