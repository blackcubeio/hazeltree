# Blackcube Hazeltree

> **⚠️ Blackcube Warning**
>
> This package implements Dan Hazel's research (2008).
>
> Two perfectly acceptable options:
> - **Use it**: the API is simple, the theory stays under the hood
> - **Move on**: other solutions exist
>
> One unacceptable option:
> - Claiming "it's useless" without having read the paper

Nested set implementation using rational numbers.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/hazeltree.svg)](https://packagist.org/packages/blackcube/hazeltree)
[![Warning](https://img.shields.io/badge/Blackcube-Warning-orange)](BLACKCUBE_WARNING.md)

> **Attribution**
>
> Hazeltree is an implementation of **Dan Hazel**'s research published in 2008: *"Using rational numbers to key nested sets"*. This approach builds on Vadim Tropashko's work (2005) and the nested sets popularized by Joe Celko.
>
> [Read the original paper on arXiv](https://arxiv.org/abs/0806.3115)

## Installation

```bash
composer require blackcube/hazeltree
```

## Why Hazeltree?

Managing tree structures in databases is an old problem. Two solutions dominate:

**Parent-child**: each element points to its parent. Simple to understand, simple to write. But to display a menu or a breadcrumb, you need to chain queries. It quickly becomes a bottleneck. The classic solution: cache. The problem: cache invalidation on a tree is a nightmare. Move a node — what gets invalidated? The node? Its ancestors? Its descendants? The whole tree?

Cache is a band-aid on a wooden leg.

**Nested sets**: each element has left/right boundaries. A single query is enough to retrieve an entire branch. But as soon as you insert an element, you have to recalculate the whole tree. It's better than parent-child because you read more often than you write, but it's not optimal.

**Hazeltree** takes the best of both worlds: reading in one query like nested sets, and writing only touches the following siblings and their descendants — almost never the whole tree.

How? Instead of sequential integers, Hazeltree uses rational fractions. There's always room between two values to insert a new element. No global renumbering.

Result: more stable trees, always performant, and no cache needed.

| Approach | Read | Write |
|----------|------|-------|
| Parent-child | 🔴 O(k) recursive, k unpredictable | 🟢 O(1) |
| Nested sets | 🟢 O(1) | 🔴 O(n) whole tree |
| **Hazeltree** | 🟢 O(1) | 🟡 O(1) or O(k)* |

*O(1) at end of list, O(k) elsewhere where k = following siblings + their descendants

**Which system to choose?**

| Use case | 🥇 | 🥈 | 🥉 |
|----------|----|----|---|
| Lots of reads, few writes | **Hazeltree** | Nested sets | Parent-child |
| Lots of writes, few reads | Parent-child | **Hazeltree** | Nested sets |

**Note**: Hazeltree and nested sets handle pure trees — a node has only one parent. For structures where a node can have multiple parents (graphs), parent-child remains the only option.

**Hard to choose?**

- **Parent-child**: prefer if more writes than reads, or if a node can have multiple parents (graphs)
- **Nested sets**: fine for most cases, but basic — writing remains costly
- **Hazeltree**: as performant as nested sets for reading, more elegant and more performant for writing

Most of the time, you read far more than you write. That's where Hazeltree makes sense.

**Want to try it?**

Hazeltree integrates without breaking existing code. Column names are configurable, the API is non-intrusive. Easy to test, easy to integrate, easy to remove if it doesn't fit.

The `path` carries everything. To migrate:

1. Add columns `path`, `left`, `right`, `level`
2. Fill in the paths (e.g., `1`, `1.1`, `1.2`, `2`, `2.1`...)
3. Recalculate the rest:

```php
foreach (Category::query()->each() as $node) {
    $matrix = TreeHelper::convert($node->path);
    $node->updateAttributes([
        'left' => TreeHelper::getLeft($matrix),
        'right' => TreeHelper::getRight($matrix),
        'level' => TreeHelper::getLevel($matrix),
    ]);
}
```

4. Add indexes (`UNIQUE` on `path`, index on `left`, `right`, `level`)

That's it. No recursion, no sorting. Each node is self-sufficient.

## How It Works

Developers familiar with nested sets already know how to use Hazeltree. The API is familiar. The difference is under the hood.

Each node stores 4 values:

| Column | Role |
|--------|------|
| `path` | **Source of truth.** Encodes the entire position in the tree. `1.3.2` = 2nd child of the 3rd child of the 1st root node. |
| `left` | Denormalized. Allows querying like classic nested sets. |
| `right` | Denormalized. Same. |
| `level` | Denormalized. Depth in the tree. |

`left`, `right` and `level` exist only for querying. They are calculated from the `path`. The `path` carries everything: parent, ancestors, level, position among siblings.

**Golden rule**: never modify these columns manually. The API handles it.

## Setup

### 1. The ActiveRecord model

```php
<?php

declare(strict_types=1);

namespace App\Model;

use Blackcube\Hazeltree\HazeltreeInterface;
use Blackcube\Hazeltree\HazeltreeTrait;
use Yiisoft\ActiveRecord\ActiveRecord;

class Category extends ActiveRecord implements HazeltreeInterface
{
    use HazeltreeTrait;

    protected string $name = '';

    public function tableName(): string
    {
        return '{{%categories}}';
    }
}
```

### 2. The Query class

```php
<?php

declare(strict_types=1);

namespace App\Model;

use Blackcube\Hazeltree\HazeltreeQueryTrait;
use Yiisoft\ActiveRecord\ActiveQuery;

class CategoryQuery extends ActiveQuery
{
    use HazeltreeQueryTrait;
}
```

### 3. Link the model to the query

```php
public static function query(): CategoryQuery
{
    return new CategoryQuery(static::class);
}
```

### 4. The table

```sql
CREATE TABLE categories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL UNIQUE,
    `left` DECIMAL(65,30) NOT NULL,
    `right` DECIMAL(65,30) NOT NULL,
    level INT NOT NULL,
    INDEX idx_left (`left`),
    INDEX idx_right (`right`),
    INDEX idx_level (level)
);
```

## Configuration

Column names can be customized:

```php
public function leftColumn(): string    { return 'lft'; }       // Default: 'left'
public function rightColumn(): string   { return 'rgt'; }       // Default: 'right'
public function pathColumn(): string    { return 'tree_path'; } // Default: 'path'
public function levelColumn(): string   { return 'depth'; }     // Default: 'level'
```

## Usage

### Writing

The methods `saveInto()`, `saveBefore()` and `saveAfter()` serve for both creation and movement. No separate `moveXxx()` methods — that would be unnecessary complexity.

```php
// Create a root node
$root = new Category();
$root->name = 'Electronics';
$root->save();

// Create children
$phones = new Category();
$phones->name = 'Phones';
$phones->saveInto($root);

$laptops = new Category();
$laptops->name = 'Laptops';
$laptops->saveInto($root);

// Insert before a sibling
$tablets = new Category();
$tablets->name = 'Tablets';
$tablets->saveBefore($laptops);

// Insert after a sibling
$accessories = new Category();
$accessories->name = 'Accessories';
$accessories->saveAfter($laptops);

// Move an existing node (same method)
$phones->saveInto($otherCategory);
$tablets->saveAfter($accessories);

// Delete a node and its descendants
$node->delete();
```

`save()` alone only works to create a root node or update an existing node without moving it. To position a node — creation or movement — use `saveInto()`, `saveBefore()` or `saveAfter()`.

### Reading

```php
// Direct children
$children = $node->relativeQuery()->children()->all();

// All descendants
$descendants = $node->relativeQuery()->children()->includeDescendants()->all();

// Direct parent
$parent = $node->relativeQuery()->parent()->one();

// Breadcrumb (all ancestors)
$breadcrumb = $node->relativeQuery()->parent()->includeAncestors()->includeSelf()->all();

// Siblings
$siblings = $node->relativeQuery()->siblings()->all();

// Root nodes
$roots = Category::query()->roots()->all();
```

One query. No cache.

## Quick Reference

### Writing

| Method | Description |
|--------|-------------|
| `save()` | Create a root or update without moving |
| `saveInto($parent)` | Create or move as last child |
| `saveBefore($sibling)` | Create or move before a sibling |
| `saveAfter($sibling)` | Create or move after a sibling |
| `delete()` | Delete the node and its descendants |
| `canMove($targetPath)` | Check if the move is possible |

### Reading

| Need | Syntax |
|------|--------|
| Direct children | `relativeQuery()->children()` |
| All descendants | `relativeQuery()->children()->includeDescendants()` |
| Self + descendants | `relativeQuery()->children()->includeDescendants()->includeSelf()` |
| Direct parent | `relativeQuery()->parent()->one()` |
| All ancestors | `relativeQuery()->parent()->includeAncestors()` |
| Self + ancestors | `relativeQuery()->parent()->includeAncestors()->includeSelf()` |
| Siblings | `relativeQuery()->siblings()` |
| Next siblings | `relativeQuery()->siblings()->next()` |
| Previous siblings | `relativeQuery()->siblings()->previous()` |
| Root nodes | `query()->roots()` |
| Exclude self | `excludingSelf()` |
| Exclude descendants | `excludingDescendants()` |

### Sorting

| Method | Description |
|--------|-------------|
| `natural()` | Natural order (ASC) — default |
| `reverse()` | Reversed order (DESC) |

### Inspection

| Method | Description |
|--------|-------------|
| `isRoot()` | Is the node a root? |
| `isLeaf()` | Is the node a leaf? |

## Let's be honest

Hazeltree is not magic. Inserting **before** an existing sibling recalculates the following siblings and their descendants. But unlike classic nested sets, you almost never touch the whole tree — only the impacted portion.

Hazeltree doesn't claim to be perfect. It tries to offer the best of both worlds — and in most real-world use cases, it succeeds.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

## Author

Philippe Gaultier <philippe@blackcube.io>

## References

- [Joe Celko, *Trees and Hierarchies in SQL for Smarties*](https://www.oreilly.com/library/view/joe-celkos-trees/9781558609204/) — The reference that popularized nested sets
- [Vadim Tropashko, *Nested intervals tree encoding in SQL*, 2005](https://dl.acm.org/doi/10.1145/1083320.1083321) — Introduction of rational numbers
- [Dan Hazel, *Using rational numbers to key nested sets*, 2008](https://arxiv.org/abs/0806.3115) — The approach implemented by Hazeltree

Let us know if you read the last one. We'll be two.