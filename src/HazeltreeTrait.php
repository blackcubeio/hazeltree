<?php

declare(strict_types=1);

/**
 * HazeltreeTrait.php
 *
 * PHP Version 8.3+
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */

namespace Blackcube\Hazeltree;

use Blackcube\Hazeltree\Exceptions\InvalidItemConfigurationException;
use Blackcube\Hazeltree\Helpers\Matrix;
use Blackcube\Hazeltree\Helpers\TreeHelper;
use Blackcube\MagicCompose\Attributes\MagicCall;
use Blackcube\MagicCompose\Attributes\MagicExtend;
use Blackcube\MagicCompose\Attributes\MagicGetter;
use Blackcube\MagicCompose\Attributes\MagicIsset;
use Blackcube\MagicCompose\Attributes\MagicSetter;
use Blackcube\MagicCompose\Attributes\Priority;
use Blackcube\MagicCompose\Exceptions\MagicNotHandledException;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

/**
 * Trait implementing the Hazeltree nested set algorithm using rational numbers.
 *
 * This trait implements Dan Hazel's research "Using rational numbers to key nested sets" (2008)
 *
 * The model using this trait must have database columns for:
 * - path (VARCHAR) - dot notation path (e.g., "1.2.3")
 * - left (FLOAT) - left boundary value
 * - right (FLOAT) - right boundary value
 * - level (INT) - depth level (1 = root)
 *
 * @author Philippe Gaultier <philippe@blackcube.io>
 * @copyright 2010-2026 Blackcube
 * @license https://blackcube.io/license
 */
trait HazeltreeTrait
{
    /**
     * @var float|null Left boundary value
     */
    private ?float $left = null;

    /**
     * @var float|null Right boundary value
     */
    private ?float $right = null;

    /**
     * @var string|null Path in dot notation
     */
    private ?string $path = null;

    /**
     * @var int|null Level (depth) in tree
     */
    private ?int $level = null;

    /**
     * @var Matrix|null Cached node matrix
     */
    private ?Matrix $nodeMatrix = null;

    /**
     * @var bool System-level protection flag (toggled by populateRecord/refresh)
     */
    private bool $hazelTreeSystemProtected = true;

    /**
     * @var bool Developer override flag (toggled by protectHazeltree)
     */
    private bool $hazelTreeDevOverride = false;

    /**
     * Check if tree properties are currently protected.
     */
    private function isHazeltreeProtected(): bool
    {
        if ($this->hazelTreeDevOverride) {
            return false;
        }
        return $this->hazelTreeSystemProtected;
    }

    /**
     * Allow developer to temporarily disable tree property protection.
     *
     * @param bool $protect True to enable protection, false to disable
     */
    public function protectHazeltree(bool $protect): void
    {
        $this->hazelTreeDevOverride = !$protect;
    }

    /**
     * Column name for left boundary.
     * Override to customize.
     */
    public function leftColumn(): string
    {
        return 'left';
    }

    /**
     * Column name for right boundary.
     * Override to customize.
     */
    public function rightColumn(): string
    {
        return 'right';
    }

    /**
     * Column name for path.
     * Override to customize.
     */
    public function pathColumn(): string
    {
        return 'path';
    }

    /**
     * Column name for level.
     * Override to customize.
     */
    public function levelColumn(): string
    {
        return 'level';
    }

    /**
     * Check if a property name is a tree property.
     */
    private function isHazeltreeProperty(string $name): bool
    {
        return $name === $this->leftColumn()
            || $name === $this->rightColumn()
            || $name === $this->pathColumn()
            || $name === $this->levelColumn();
    }

    // ========================================
    // Magic methods with MagicCompose attributes
    // ========================================

    /**
     * Get a tree property value.
     */
    #[MagicGetter(Priority::NORMAL)]
    protected function hazeltreeGet(string $name): mixed
    {
        if (!$this->isHazeltreeProperty($name)) {
            throw new MagicNotHandledException();
        }
        return match ($name) {
            $this->leftColumn() => $this->left,
            $this->rightColumn() => $this->right,
            $this->pathColumn() => $this->path,
            $this->levelColumn() => $this->level,
            default => throw new MagicNotHandledException(),
        };
    }

    /**
     * Set a tree property value.
     * Tree properties (left, right, path, level) are protected by default.
     */
    #[MagicSetter(Priority::NORMAL)]
    protected function hazeltreeSet(string $name, mixed $value): void
    {
        if (!$this->isHazeltreeProperty($name)) {
            throw new MagicNotHandledException();
        }
        if ($this->isHazeltreeProtected()) {
            throw new \Error(
                sprintf('Cannot set read-only tree property %s::$%s. Use setNodePath() instead.', static::class, $name)
            );
        }
        $this->protectHazeltreeInternal(false);
        $this->populateProperty($name, $value);
        $this->protectHazeltreeInternal(true);
    }

    /**
     * Check if a tree property is set.
     */
    #[MagicIsset(Priority::NORMAL)]
    protected function hazeltreeIsset(string $name): bool
    {
        if (!$this->isHazeltreeProperty($name)) {
            throw new MagicNotHandledException();
        }
        return match ($name) {
            $this->leftColumn() => $this->left !== null,
            $this->rightColumn() => $this->right !== null,
            $this->pathColumn() => $this->path !== null,
            $this->levelColumn() => $this->level !== null,
            default => false,
        };
    }

    /**
     * Handle magic method calls.
     */
    #[MagicCall(Priority::NORMAL)]
    protected function hazeltreeCall(string $name, array $arguments): mixed
    {
        // Handle getPropertyName()
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));
            if ($this->isHazeltreeProperty($property)) {
                return $this->hazeltreeGet($property);
            }
        }

        // Handle setPropertyName($value) - protected for tree properties
        if (str_starts_with($name, 'set') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));
            if ($this->isHazeltreeProperty($property)) {
                if ($this->isHazeltreeProtected()) {
                    throw new \Error(
                        sprintf('Cannot set read-only tree property %s::$%s. Use setNodePath() instead.', static::class, $property)
                    );
                }
            }
        }

        throw new MagicNotHandledException();
    }

    // ========================================
    // ActiveRecord overrides with MagicExtend
    // ========================================

    /**
     * Include tree columns in property values for ActiveRecord save.
     * If path is null, automatically finds the next available root path.
     */
    #[MagicExtend('propertyValuesInternal', Priority::NORMAL)]
    protected function hazeltreePropertyValues(): array
    {
        if ($this->path === null) {
            $nextRootPath = $this->getNextRootPath();
            $this->setNodePath($nextRootPath);
        }

        $values = $this->next();
        $values[$this->leftColumn()] = $this->left;
        $values[$this->rightColumn()] = $this->right;
        $values[$this->pathColumn()] = $this->path;
        $values[$this->levelColumn()] = $this->level;
        return $values;
    }

    /**
     * Populate tree properties with protection check.
     * This is called by ActiveRecord when loading data from the database.
     */
    #[MagicExtend('populateProperty', Priority::NORMAL)]
    protected function hazeltreePopulateProperty(string $name, mixed $value): void
    {
        if ($this->isHazeltreeProperty($name)) {
            if ($this->isHazeltreeProtected()) {
                throw new \Error(
                    sprintf('Cannot set read-only tree property %s::$%s. Use setNodePath() instead.', static::class, $name)
                );
            }
            match ($name) {
                $this->leftColumn() => $this->left = $value !== null ? (float) $value : null,
                $this->rightColumn() => $this->right = $value !== null ? (float) $value : null,
                $this->pathColumn() => $this->setPathInternal($value),
                $this->levelColumn() => $this->level = $value !== null ? (int) $value : null,
                default => null,
            };
            return;
        }

        $this->next($name, $value);
    }

    /**
     * Temporarily disable protection during DB load.
     */
    #[MagicExtend('populateRecord', Priority::NORMAL)]
    protected function hazeltreePopulateRecord(array|object $row): static
    {
        $this->protectHazeltreeInternal(false);
        $result = $this->next($row);
        $this->protectHazeltreeInternal(true);
        return $result;
    }

    /**
     * Temporarily disable protection during refresh.
     */
    #[MagicExtend('refreshInternal', Priority::NORMAL)]
    protected function hazeltreeRefreshInternal(array|ActiveRecordInterface|null $record): bool
    {
        $this->protectHazeltreeInternal(false);
        $result = $this->next($record);
        $this->protectHazeltreeInternal(true);
        return $result;
    }

    // ========================================
    // Internal helpers
    // ========================================

    /**
     * Internal method to toggle system protection.
     */
    private function protectHazeltreeInternal(bool $protect): void
    {
        $this->hazelTreeSystemProtected = $protect;
    }

    /**
     * Find the next available root path (1, 2, 3, ...).
     */
    private function getNextRootPath(): string
    {
        $lastRoot = static::query()
            ->roots()
            ->previous()
            ->one();

        if ($lastRoot === null) {
            return '1';
        }

        $lastSegment = TreeHelper::getLastSegment($lastRoot->path);
        return (string) ($lastSegment + 1);
    }

    /**
     * Internal method to set path without recalculating other properties.
     * Used when loading from database where all properties are already set.
     */
    private function setPathInternal(?string $value): void
    {
        $this->path = $value;
        $this->nodeMatrix = null;
    }

    // ==================== PUBLIC API ====================

    /**
     * Check if this is a root node (level 1).
     */
    public function isRoot(): bool
    {
        return $this->level === 1;
    }

    /**
     * Check if this node can be moved to a target path.
     */
    public function canMove(string $targetPath): bool
    {
        return strncmp($this->path, $targetPath, strlen($this->path)) !== 0;
    }

    /**
     * Get a query builder for tree navigation relative to this node.
     *
     * Usage:
     * ```php
     * $node->relativeQuery()->children()->all();
     * $node->relativeQuery()->siblings()->next()->one();
     * $node->relativeQuery()->parent()->includeAncestors()->all();
     * ```
     *
     * Requires the ActiveQuery class to use HazeltreeQueryTrait.
     */
    public function relativeQuery(): \Yiisoft\ActiveRecord\ActiveQueryInterface
    {
        $query = static::query();

        if (method_exists($query, 'forNode')) {
            $query->forNode($this);
        }

        return $query;
    }

    // ==================== PRIVATE: NODE MATRIX ====================

    /**
     * Set the node path using dot notation.
     */
    private function setNodePath(string $nodePath): void
    {
        $this->nodeMatrix = TreeHelper::convert($nodePath);
        $this->path = $nodePath;
        $this->level = TreeHelper::getLevel($nodePath);
        $this->left = TreeHelper::getLeft($this->nodeMatrix);
        $this->right = TreeHelper::getRight($this->nodeMatrix);
    }

    /**
     * Set the node position using matrix.
     */
    private function setNodeMatrix(Matrix $matrix): void
    {
        $this->nodeMatrix = $matrix;
        $this->path = TreeHelper::convert($matrix);
        $this->level = TreeHelper::getLevel($this->path);
        $this->left = TreeHelper::getLeft($this->nodeMatrix);
        $this->right = TreeHelper::getRight($this->nodeMatrix);
    }

    /**
     * Get the node matrix.
     */
    private function getNodeMatrix(): Matrix
    {
        if ($this->nodeMatrix === null && $this->path !== null) {
            $this->nodeMatrix = TreeHelper::convert($this->path);
        }
        return $this->nodeMatrix ?? TreeHelper::convert('1');
    }

    // ==================== SAVE OPERATIONS ====================

    /**
     * Resolve a target (Node or path string) to a HazeltreeInterface.
     *
     * @throws InvalidItemConfigurationException if path not found
     */
    private function resolveTarget(HazeltreeInterface|string $target): HazeltreeInterface
    {
        if ($target instanceof HazeltreeInterface) {
            return $target;
        }

        $node = static::query()
            ->where([$this->pathColumn() => $target])
            ->one();

        if ($node === null) {
            throw new InvalidItemConfigurationException(sprintf('Node with path "%s" not found', $target));
        }

        return $node;
    }

    /**
     * Insert or save current item into target item at last position.
     * Accepts a Node or a path string (e.g., '1.2.3').
     *
     * @throws InvalidItemConfigurationException
     */
    public function saveInto(HazeltreeInterface|string $target, ?array $attributeNames = null): bool
    {
        $targetItem = $this->resolveTarget($target);
        if (!$this->isNew()) {
            $db = $this->db();
            $transaction = $db->beginTransaction();
            try {
                $this->save($attributeNames);

                // Move into target (if allowed)
                if ($this->canMove($targetItem->path)) {
                    // Get current parent for bump back later
                    $currentParent = $this->relativeQuery()->parent()->one();

                    // Calculate bumpBack matrix BEFORE any move
                    $nextSibling = $this->relativeQuery()->siblings()->next()->one();
                    $bumpBackMatrix = null;
                    if ($nextSibling !== null) {
                        $bumpBackMatrix = $this->prepareMoveMatrix($nextSibling, $nextSibling, -1);
                    }

                    // Find the last child of target (excluding $this)
                    $pk = static::primaryKey();
                    $pkName = $pk[0];
                    $thisId = $this->get($pkName);

                    $targetLastChild = $targetItem->relativeQuery()->children()
                        ->andWhere(['<>', $pkName, $thisId])
                        ->reverse()
                        ->one();

                    // Calculate the move matrix
                    $currentLastSegment = TreeHelper::getLastSegment($this->getNodeMatrix());
                    if ($targetLastChild !== null) {
                        $lastSegment = TreeHelper::getLastSegment($targetLastChild->getNodeMatrix());
                        $bump = ($lastSegment + 1) - $currentLastSegment;
                        $nodesMoveMatrix = $this->prepareMoveMatrix($this, $targetLastChild, $bump);
                    } else {
                        $bump = 1 - $currentLastSegment;
                        $nodesMoveMatrix = $this->prepareMoveMatrix($this, $targetItem, $bump, true);
                    }

                    // Move $this (and its children) into the target as last child
                    $nodesToMove = $this->relativeQuery()->children()
                        ->includeDescendants()
                        ->includeSelf();
                    foreach ($nodesToMove->each() as $nodeToMove) {
                        $childMatrix = $nodeToMove->getNodeMatrix();
                        $newMatrix = $nodesMoveMatrix->multiply($childMatrix);
                        $nodeToMove->setNodeMatrix($newMatrix);
                        $nodeToMove->save([
                            $this->pathColumn(),
                            $this->leftColumn(),
                            $this->rightColumn(),
                            $this->levelColumn(),
                        ]);
                    }

                    // Bump back to fill the gap
                    $nodesToBumpBackQuery = null;
                    if ($bumpBackMatrix !== null && $currentParent !== null) {
                        $nodesToBumpBackQuery = $currentParent->relativeQuery()
                            ->children()
                            ->includeDescendants();
                    } elseif($bumpBackMatrix !== null && $nextSibling !== null) {
                        $nodesToBumpBackQuery = $nextSibling->relativeQuery()
                            ->siblings()
                            ->includeDescendants()
                            ->includeSelf();
                    }
                    if ($nodesToBumpBackQuery !== null) {
                        foreach ($nodesToBumpBackQuery->each() as $nodeToBumpBack) {
                            $childMatrix = $nodeToBumpBack->getNodeMatrix();
                            $newMatrix = $bumpBackMatrix->multiply($childMatrix);
                            $nodeToBumpBack->setNodeMatrix($newMatrix);
                            $nodeToBumpBack->save([
                                $this->pathColumn(),
                                $this->leftColumn(),
                                $this->rightColumn(),
                                $this->levelColumn(),
                            ]);
                        }
                    }
                    $this->refresh();
                }

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        } elseif ($this->path !== null) {
            throw new InvalidItemConfigurationException('Cannot "saveInto()" a new record with a node path');
        } else {
            $db = $this->db();
            $transaction = $db->beginTransaction();
            try {
                $lastChild = $targetItem->relativeQuery()->children()
                    ->reverse()
                    ->one();

                if ($lastChild === null) {
                    $lastSegment = 1;
                } else {
                    $lastSegment = TreeHelper::getLastSegment($lastChild->getNodeMatrix()) + 1;
                }

                $this->setNodePath($targetItem->path . TreeHelper::PATH_SEPARATOR . $lastSegment);
                $this->save($attributeNames);

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
        return true;
    }

    /**
     * Insert or save current item before target item.
     * Accepts a Node or a path string (e.g., '1.2.3').
     *
     * @throws InvalidItemConfigurationException
     */
    public function saveBefore(HazeltreeInterface|string $target, ?array $attributeNames = null): bool
    {
        $targetItem = $this->resolveTarget($target);

        if (!$this->isNew()) {
            $db = $this->db();
            $transaction = $db->beginTransaction();
            try {
                $this->save($attributeNames);

                // Move before target (if allowed)
                if ($this->canMove($targetItem->path)) {
                    // Store nextSiblingId BEFORE any move
                    $nextSibling = $this->relativeQuery()->siblings()->next()->one();
                    $nextSiblingId = $nextSibling !== null ? $this->getPkValue($nextSibling) : null;

                    // Step 1: Make room at destination - bump target and its siblings +1
                    $bumpMatrix = $this->prepareMoveMatrix($targetItem, $targetItem, 1);
                    $nodesToBump = $targetItem->relativeQuery()->siblings()->next()->includeSelf()->includeDescendants()
                        ->reverse();
                    $this->moveAndSaveItems($nodesToBump, $bumpMatrix);

                    // Step 2: Refresh target and $this after the bump
                    $targetItem->refresh();
                    $this->refresh();

                    // Step 3: Move this node's tree to the target position
                    $this->moveThisItemTree($targetItem, true);

                    // Step 4: Refresh again
                    $targetItem->refresh();
                    $this->refresh();

                    // Step 5: Bump back the nodes that followed $this to fill the gap
                    $this->moveBackItems($nextSiblingId);

                    $targetItem->refresh();
                    $this->refresh();
                }

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        } elseif ($this->path !== null) {
            throw new InvalidItemConfigurationException('Cannot "saveBefore()" a new record with a node path');
        } else {
            $db = $this->db();
            $transaction = $db->beginTransaction();
            try {
                $path = $targetItem->path;

                $nodesMoveMatrix = $this->prepareMoveMatrix($targetItem, $targetItem, 1);

                $nodesToMove = $targetItem->relativeQuery()->siblings()->next()->includeSelf()->includeDescendants()
                    ->reverse();

                $this->moveAndSaveItems($nodesToMove, $nodesMoveMatrix);

                $this->setNodePath($path);
                $this->save($attributeNames);

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
        return true;
    }

    /**
     * Insert or save current item after target item.
     * Accepts a Node or a path string (e.g., '1.2.3').
     *
     * @throws InvalidItemConfigurationException
     */
    public function saveAfter(HazeltreeInterface|string $target, ?array $attributeNames = null): bool
    {
        $targetItem = $this->resolveTarget($target);

        if (!$this->isNew()) {
            $db = $this->db();
            $transaction = $db->beginTransaction();
            try {
                $this->save($attributeNames);

                // Move after target (if allowed)
                if ($this->canMove($targetItem->path)) {
                    // If target has a next sibling, move before that sibling
                    $targetItemNextSibling = $targetItem->relativeQuery()->siblings()->next()->one();

                    if ($targetItemNextSibling !== null) {
                        // Inline moveBefore logic for targetItemNextSibling
                        $nextSibling = $this->relativeQuery()->siblings()->next()->one();
                        $nextSiblingId = $nextSibling !== null ? $this->getPkValue($nextSibling) : null;

                        $bumpMatrix = $this->prepareMoveMatrix($targetItemNextSibling, $targetItemNextSibling, 1);
                        $nodesToBump = $targetItemNextSibling->relativeQuery()->siblings()->next()->includeSelf()->includeDescendants()
                            ->reverse();
                        $this->moveAndSaveItems($nodesToBump, $bumpMatrix);

                        $targetItemNextSibling->refresh();
                        $this->refresh();

                        $this->moveThisItemTree($targetItemNextSibling, true);

                        $targetItemNextSibling->refresh();
                        $this->refresh();

                        $this->moveBackItems($nextSiblingId);

                        $targetItemNextSibling->refresh();
                        $this->refresh();
                    } else {
                        // Target is the last sibling - move $this directly after target
                        $nextSibling = $this->relativeQuery()->siblings()->next()->one();
                        $nextSiblingId = $nextSibling !== null ? $this->getPkValue($nextSibling) : null;

                        $this->moveThisItemTree($targetItem, false);

                        $this->refresh();

                        $this->moveBackItems($nextSiblingId);

                        $targetItem->refresh();
                        $this->refresh();
                    }
                }

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        } elseif ($this->path !== null) {
            throw new InvalidItemConfigurationException('Cannot "saveAfter()" a new record with a node path');
        } else {
            $db = $this->db();
            $transaction = $db->beginTransaction();
            try {
                $nextSiblingItem = $targetItem->relativeQuery()->siblings()->next()->one();

                if ($nextSiblingItem !== null) {
                    $path = $nextSiblingItem->path;

                    $nodesMoveMatrix = $this->prepareMoveMatrix($nextSiblingItem, $nextSiblingItem, 1);

                    $nodesToMove = $targetItem->relativeQuery()->siblings()->next()->includeDescendants()
                        ->reverse();

                    $this->moveAndSaveItems($nodesToMove, $nodesMoveMatrix);
                } else {
                    $parts = explode(TreeHelper::PATH_SEPARATOR, $targetItem->path);
                    $lastSegment = (int) array_pop($parts);
                    $parts[] = (string) ($lastSegment + 1);
                    $path = implode(TreeHelper::PATH_SEPARATOR, $parts);
                }

                $this->setNodePath($path);
                $this->save($attributeNames);

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                throw $e;
            }
        }
        return true;
    }

    // ==================== LEVEL CALCULATION FOR MOVES ====================

    /**
     * Calculate the maximum depth of this node's subtree.
     * Returns 0 if node has no children.
     *
     * @return int The depth of the deepest descendant relative to this node
     */
    public function getSubtreeDepth(): int
    {
        $maxLevel = $this->level;
        $descendants = $this->relativeQuery()->children()->includeDescendants();
        foreach ($descendants->each() as $descendant) {
            if ($descendant->getLevel() > $maxLevel) {
                $maxLevel = $descendant->getLevel();
            }
        }
        return $maxLevel - $this->level;
    }

    /**
     * Calculate the maximum level that would result from moving this node BEFORE target.
     * Includes all descendants.
     *
     * @param HazeltreeInterface $target The target node
     * @return int The maximum level after move
     */
    public function getMaxLevelIfMoveBefore(HazeltreeInterface $target): int
    {
        $newLevel = $target->getLevel();
        return $newLevel + $this->getSubtreeDepth();
    }

    /**
     * Calculate the maximum level that would result from moving this node AFTER target.
     * Includes all descendants.
     *
     * @param HazeltreeInterface $target The target node
     * @return int The maximum level after move
     */
    public function getMaxLevelIfMoveAfter(HazeltreeInterface $target): int
    {
        $newLevel = $target->getLevel();
        return $newLevel + $this->getSubtreeDepth();
    }

    /**
     * Calculate the maximum level that would result from moving this node INTO target (as child).
     * Includes all descendants.
     *
     * @param HazeltreeInterface $target The target node
     * @return int The maximum level after move
     */
    public function getMaxLevelIfMoveInto(HazeltreeInterface $target): int
    {
        $newLevel = $target->getLevel() + 1;
        return $newLevel + $this->getSubtreeDepth();
    }

    /**
     * Check if moving this node to a position would exceed the maximum allowed level.
     *
     * @param HazeltreeInterface $target The target node
     * @param string $mode The move mode: 'before', 'after', or 'into'
     * @param int $maxLevel The maximum allowed level
     * @return bool True if the move would exceed maxLevel
     */
    public function wouldExceedMaxLevel(HazeltreeInterface $target, string $mode, int $maxLevel): bool
    {
        $resultLevel = match ($mode) {
            'before' => $this->getMaxLevelIfMoveBefore($target),
            'after' => $this->getMaxLevelIfMoveAfter($target),
            'into' => $this->getMaxLevelIfMoveInto($target),
            default => throw new \InvalidArgumentException("Invalid move mode: $mode"),
        };
        return $resultLevel > $maxLevel;
    }

    // ==================== DELETE OPERATIONS ====================

    /**
     * Delete this node and all its descendants, then close the gap.
     * Extends ActiveRecord::deleteInternal() to handle tree structure.
     *
     * @return int The number of rows deleted
     */
    #[MagicExtend('deleteInternal', Priority::NORMAL)]
    protected function hazeltreeDeleteInternal(): int
    {
        // Store nextSiblingId BEFORE any delete
        $nextSibling = $this->relativeQuery()->siblings()->next()->one();
        $nextSiblingId = $nextSibling !== null ? $this->getPkValue($nextSibling) : null;

        $db = $this->db();
        $transaction = $db->beginTransaction();

        try {
            // Build condition: this node + all descendants (left > nodeLeft AND right < nodeRight)
            $condition = [
                'and',
                ['>=', $this->leftColumn(), $this->left],
                ['<=', $this->rightColumn(), $this->right],
            ];

            $deletedCount = $this->deleteAll($condition);

            // Close the gap - bump back the siblings that followed
            $this->moveBackItems($nextSiblingId);

            $this->assignOldValues();

            $transaction->commit();

            return $deletedCount;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    // ==================== PRIVATE HELPERS ====================

    /**
     * Move this item's tree to a new position.
     */
    private function moveThisItemTree(HazeltreeInterface $targetItem, bool $moveBefore): void
    {
        $itemLastSegment = TreeHelper::getLastSegment($this->getNodeMatrix());
        $targetLastSegment = TreeHelper::getLastSegment($targetItem->getNodeMatrix());

        if ($moveBefore) {
            $itemBump = $targetLastSegment - $itemLastSegment - 1;
        } else {
            $itemBump = $targetLastSegment - $itemLastSegment + 1;
        }

        $nodesMoveMatrix = $this->prepareMoveMatrix($this, $targetItem, $itemBump);
        $nodesToMove = $this->relativeQuery()->children()->includeDescendants()->includeSelf();
        $this->moveAndSaveItems($nodesToMove, $nodesMoveMatrix);
    }

    /**
     * Get the primary key value of an item.
     */
    private function getPkValue(HazeltreeInterface $item): mixed
    {
        $pk = static::primaryKey();
        $pkName = $pk[0];
        return $item->get($pkName);
    }

    /**
     * Move back (bump -1) the nodes that followed the moved item to fill the gap.
     */
    private function moveBackItems(mixed $nextSiblingId): void
    {
        if ($nextSiblingId === null) {
            return;
        }

        $pk = static::primaryKey();
        $pkName = $pk[0];

        $nextSibling = static::query()
            ->where([$pkName => $nextSiblingId])
            ->one();

        if ($nextSibling === null) {
            return;
        }

        $bumpBackMatrix = $this->prepareMoveMatrix($nextSibling, $nextSibling, -1);
        $nodesToMove = $nextSibling->relativeQuery()->siblings()->next()->includeSelf()->includeDescendants()
            ->orderBy([$this->leftColumn() => SORT_ASC]);
        $this->moveAndSaveItems($nodesToMove, $bumpBackMatrix);
    }

    /**
     * Move and save items using a move matrix.
     */
    private function moveAndSaveItems(\Yiisoft\ActiveRecord\ActiveQueryInterface $itemsToMove, Matrix $moveMatrix): void
    {
        foreach ($itemsToMove->each() as $itemToMove) {
            $childMatrix = $itemToMove->getNodeMatrix();
            $itemMoveMatrix = $moveMatrix->multiply($childMatrix);
            $itemToMove->setNodeMatrix($itemMoveMatrix);
            $itemToMove->save([
                $this->pathColumn(),
                $this->leftColumn(),
                $this->rightColumn(),
                $this->levelColumn(),
            ]);
        }
    }

    /**
     * Prepare a move matrix for relocating nodes.
     */
    private function prepareMoveMatrix(HazeltreeInterface $fromItem, HazeltreeInterface $toItem, int $bump, bool $inside = false): Matrix
    {
        $fromMatrix = TreeHelper::getParent($fromItem->getNodeMatrix()) ?? TreeHelper::getRootMatrix();
        if ($inside) {
            $toMatrix = $toItem->getNodeMatrix();
        } else {
            $toMatrix = TreeHelper::getParent($toItem->getNodeMatrix()) ?? TreeHelper::getRootMatrix();
        }
        return TreeHelper::buildMoveMatrix($fromMatrix, $toMatrix, $bump);
    }
}
