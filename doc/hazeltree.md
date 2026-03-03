# Hazeltree

> **⚠️ Blackcube Warning**
>
> Ce package implémente les travaux de recherche de Dan Hazel (2008).
>
> Deux options parfaitement acceptables :
> - **L'utiliser** : l'API est simple, la théorie reste sous le capot
> - **Passer son chemin** : d'autres solutions existent
>
> Une option inacceptable :
> - Affirmer "c'est inutile" sans avoir lu le papier

---

Arborescences ultra rapides en lecture et performantes en écriture.

> **Attribution**
>
> Hazeltree est une implémentation des travaux de recherche de **Dan Hazel** publiés en 2008 : *"Using rational numbers to key nested sets"*. Cette approche s'inscrit dans la continuité des travaux de Vadim Tropashko (2005) et des nested sets popularisés par Joe Celko.
>
> [Lire le papier original sur arXiv](https://arxiv.org/abs/0806.3115)

---

## Pourquoi Hazeltree ?

Gérer des arborescences en base de données, c'est un vieux problème. Deux solutions dominent :

**Parent-enfant** : chaque élément pointe vers son parent. Simple à comprendre, simple à écrire. Mais pour afficher un menu ou un fil d'Ariane, il faut enchaîner les requêtes. Ça devient vite un goulet d'étranglement. La solution classique : du cache. Le problème : l'invalidation du cache sur un arbre est un cauchemar. Déplacer un nœud — qu'est-ce qui est invalidé ? Le nœud ? Ses ancêtres ? Ses descendants ? Tout l'arbre ?

Le cache est un cataplasme sur une jambe de bois.

**Nested sets** : chaque élément a des bornes left/right. Une seule requête suffit pour récupérer toute une branche. Mais dès qu'on insère un élément, il faut recalculer tout l'arbre. C'est mieux que parent-enfant parce qu'on lit plus souvent qu'on écrit, mais ce n'est pas optimal.

**Hazeltree** prend le meilleur des deux mondes : lecture en une requête comme les nested sets, et en écriture on ne touche qu'aux frères suivants et leurs descendants — quasiment jamais à tout l'arbre.

Comment ? Au lieu d'entiers séquentiels, Hazeltree utilise des fractions rationnelles. Il y a toujours de la place entre deux valeurs pour insérer un nouvel élément. Pas de renumérotation globale.

Résultat : des arborescences plus stables, toujours performantes, et pas de cache nécessaire.

| Approche | Lecture | Écriture |
|----------|---------|----------|
| Parent-enfant | 🔴 O(k) récursif, k non prévisible | 🟢 O(1) |
| Nested sets | 🟢 O(1) | 🔴 O(n) tout l'arbre |
| **Hazeltree** | 🟢 O(1) | 🟡 O(1) ou O(k)* |

*O(1) en fin de liste, O(k) ailleurs où k = frères suivants + leurs descendants

**Quel système choisir ?**

| Cas d'usage | 🥇 | 🥈 | 🥉 |
|-------------|----|----|---|
| Beaucoup de lecture, peu d'écriture | **Hazeltree** | Nested sets | Parent-enfant |
| Beaucoup d'écriture, peu de lecture | Parent-enfant | **Hazeltree** | Nested sets |

**Note** : Hazeltree et nested sets gèrent des arborescences pures — un nœud n'a qu'un seul parent. Pour des structures où un nœud peut avoir plusieurs parents (graphes), parent-enfant reste la seule option.

**Difficile de choisir ?**

- **Parent-enfant** : à privilégier si plus d'écriture que de lecture, ou si un nœud peut avoir plusieurs parents (graphes)
- **Nested sets** : correct pour la plupart des cas, mais basique — l'écriture reste coûteuse
- **Hazeltree** : aussi performant que nested sets en lecture, plus élégant et plus performant en écriture

La plupart du temps, on lit bien plus qu'on écrit. C'est là qu'Hazeltree prend tout son sens.

**Envie d'essayer ?**

Hazeltree s'intègre sans casser l'existant. Les noms de colonnes sont configurables, l'API est non-intrusive. Facile à tester, facile à intégrer, facile à retirer si ça ne convient pas.

Le `path` porte tout. Pour migrer :

1. Ajouter les colonnes `path`, `left`, `right`, `level`
2. Remplir les paths (ex: `1`, `1.1`, `1.2`, `2`, `2.1`...)
3. Recalculer le reste :

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

4. Ajouter les index (`UNIQUE` sur `path`, index sur `left`, `right`, `level`)

C'est tout. Pas de récursion, pas de tri. Chaque nœud se suffit à lui-même.

---

## Comment ça marche

Les développeurs familiers avec les nested sets savent déjà utiliser Hazeltree. L'API est familière. La différence est sous le capot.

Chaque nœud stocke 4 valeurs :

| Colonne | Rôle |
|---------|------|
| `path` | **Source de vérité.** Encode toute la position dans l'arbre. `1.3.2` = 2ème enfant du 3ème enfant du 1er nœud racine. |
| `left` | Dénormalisé. Permet de requêter comme avec les nested sets classiques. |
| `right` | Dénormalisé. Idem. |
| `level` | Dénormalisé. Profondeur dans l'arbre. |

`left`, `right` et `level` n'existent que pour le requêtage. Ils sont calculés depuis le `path`. Le `path` porte tout : parent, ancêtres, niveau, position parmi les frères.

**Règle d'or** : ne jamais modifier ces colonnes manuellement. L'API s'en charge.

---

## Usage

### Écriture

Les méthodes `saveInto()`, `saveBefore()` et `saveAfter()` servent à la fois pour la création et le déplacement. Pas de méthodes `moveXxx()` séparées — ce serait de la complexité pour rien.

```php
// Créer un nœud racine
$root = new Category();
$root->name = 'Électronique';
$root->save();

// Créer des enfants
$phones = new Category();
$phones->name = 'Téléphones';
$phones->saveInto($root);

$laptops = new Category();
$laptops->name = 'Ordinateurs';
$laptops->saveInto($root);

// Insérer avant un frère
$tablets = new Category();
$tablets->name = 'Tablettes';
$tablets->saveBefore($laptops);

// Insérer après un frère
$accessories = new Category();
$accessories->name = 'Accessoires';
$accessories->saveAfter($laptops);

// Déplacer un nœud existant (même méthode)
$phones->saveInto($otherCategory);
$tablets->saveAfter($accessories);

// Supprimer un nœud et ses descendants
$node->delete();
```

`save()` seul ne fonctionne que pour créer un nœud racine ou mettre à jour un nœud existant sans le déplacer. Pour positionner un nœud — création ou déplacement — utiliser `saveInto()`, `saveBefore()` ou `saveAfter()`.

### Lecture

```php
// Enfants directs
$children = $node->relativeQuery()->children()->all();

// Tous les descendants
$descendants = $node->relativeQuery()->children()->includeDescendants()->all();

// Parent direct
$parent = $node->relativeQuery()->parent()->one();

// Fil d'Ariane (tous les ancêtres)
$breadcrumb = $node->relativeQuery()->parent()->includeAncestors()->includeSelf()->all();

// Frères et sœurs
$siblings = $node->relativeQuery()->siblings()->all();

// Nœuds racines
$roots = Category::query()->roots()->all();
```

Une requête. Pas de cache.

---

## Memento

### Écriture

| Méthode | Description |
|---------|-------------|
| `save()` | Créer un root ou mettre à jour sans déplacer |
| `saveInto($parent)` | Créer ou déplacer comme dernier enfant |
| `saveBefore($sibling)` | Créer ou déplacer avant un frère |
| `saveAfter($sibling)` | Créer ou déplacer après un frère |
| `delete()` | Supprimer le nœud et ses descendants |

### Lecture

| Besoin | Syntaxe |
|--------|---------|
| Enfants directs | `relativeQuery()->children()` |
| Tous les descendants | `relativeQuery()->children()->includeDescendants()` |
| Soi + descendants | `relativeQuery()->children()->includeDescendants()->includeSelf()` |
| Parent direct | `relativeQuery()->parent()->one()` |
| Tous les ancêtres | `relativeQuery()->parent()->includeAncestors()` |
| Soi + ancêtres | `relativeQuery()->parent()->includeAncestors()->includeSelf()` |
| Frères et sœurs | `relativeQuery()->siblings()` |
| Frères suivants | `relativeQuery()->siblings()->next()` |
| Frères précédents | `relativeQuery()->siblings()->previous()` |
| Nœuds racines | `query()->roots()` |

---

## Soyons honnêtes

Hazeltree n'est pas magique. Insérer **avant** un frère existant recalcule les frères suivants et leurs descendants. Mais contrairement aux nested sets classiques, on ne touche quasiment jamais à tout l'arbre — seulement à la portion impactée.

Hazeltree ne prétend pas être parfait. Il tente d'offrir le meilleur des deux mondes — et dans la plupart des cas d'usage réels, il y arrive.

---

## Références

- Joe Celko, *Trees and Hierarchies in SQL for Smarties* — La référence qui a popularisé les nested sets
- Vadim Tropashko, *Nested intervals tree encoding in SQL*, 2005 — Introduction des nombres rationnels
- Dan Hazel, *Using rational numbers to key nested sets*, 2008 — L'approche implémentée par Hazeltree

Préviens-nous si tu lis le dernier. On sera deux.

---

## Installation

```bash
composer require blackcube/hazeltree
```

---

## Setup

### 1. Le modèle ActiveRecord

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

### 2. La classe Query

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

### 3. Lier le modèle à la query

```php
public static function query(): CategoryQuery
{
    return new CategoryQuery(static::class);
}
```

### 4. La table

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

---

## Configuration

Les noms de colonnes peuvent être personnalisés :

```php
public function leftColumn(): string    { return 'lft'; }       // Défaut: 'left'
public function rightColumn(): string   { return 'rgt'; }       // Défaut: 'right'
public function pathColumn(): string    { return 'tree_path'; } // Défaut: 'path'
public function levelColumn(): string   { return 'depth'; }     // Défaut: 'level'
```

---

## Memento complet

### Écriture

| Méthode | Description |
|---------|-------------|
| `save()` | Créer un root ou mettre à jour sans déplacer |
| `saveInto($parent)` | Créer ou déplacer comme dernier enfant |
| `saveBefore($sibling)` | Créer ou déplacer avant un frère |
| `saveAfter($sibling)` | Créer ou déplacer après un frère |
| `delete()` | Supprimer le nœud et ses descendants |
| `canMove($targetPath)` | Vérifier si le déplacement est possible |

### Lecture

| Besoin | Syntaxe |
|--------|---------|
| Enfants directs | `relativeQuery()->children()` |
| Tous les descendants | `relativeQuery()->children()->includeDescendants()` |
| Soi + descendants | `relativeQuery()->children()->includeDescendants()->includeSelf()` |
| Parent direct | `relativeQuery()->parent()->one()` |
| Tous les ancêtres | `relativeQuery()->parent()->includeAncestors()` |
| Soi + ancêtres | `relativeQuery()->parent()->includeAncestors()->includeSelf()` |
| Frères et sœurs | `relativeQuery()->siblings()` |
| Frères suivants | `relativeQuery()->siblings()->next()` |
| Frères précédents | `relativeQuery()->siblings()->previous()` |
| Nœuds racines | `query()->roots()` |
| Exclure soi-même | `excludingSelf()` |
| Exclure descendants | `excludingDescendants()` |

### Tri

| Méthode | Description |
|---------|-------------|
| `natural()` | Ordre naturel (ASC) — défaut |
| `reverse()` | Ordre inversé (DESC) |

### Inspection

| Méthode | Description |
|---------|-------------|
| `isRoot()` | Le nœud est-il une racine ? |
| `isLeaf()` | Le nœud est-il une feuille ? |