# Relations

Relations allow you to define connections between your models, making it easy to retrieve and manage related data. This package supports 9 relation types, plus 2 specialized "of many" variations, giving you 11 different ways to define database relationships.

### Available Relation Types

#### Standard Relations

| Relation | Description | Example |
|----------|-------------|---------|
| [One to one / Has One](relations/one-to-one.md) | One-to-one relationship | User has one Profile |
| [One to many / Has Many](relations/one-to-many.md) | One-to-many relationship | User has many Posts |
| [One to many (inverse) / Belongs To](relations/one-to-many-inverse.md) | Inverse of one-to-many | Post belongs to User |
| [Has one of many](relations/has-one-of-many.md) | Retrieve one record from many based on criteria | User's latest Post or best rated Post |
| [Has one through](relations/has-one-through.md) | One-to-one through intermediate model | User has one Address through Company |
| [Has many through](relations/has-many-through.md) | One-to-many through intermediate model | Country has many Posts through Users |
| [Many to many / Belongs To many](relations/many-to-many.md) | Many-to-many relationship | Students belong to many Courses |

#### Polymorphic Relations

| Relation | Description | Example |
|----------|-------------|---------|
| [Morph one](relations/morph-one.md) | Polymorphic one-to-one | Post or Video has one Image |
| [Morph many](relations/morph-many.md) | Polymorphic one-to-many | Post or Video has many Comments |
| [Morph one of many](relations/morph-one-of-many.md) | Retrieve one morphed record from many | Post or Video's latest Comment |
| [Morph to](relations/morph-to.md) | Inverse of polymorphic relations | Comment belongs to Post or Video |

### Common Features

All relation types support:

#### Eager Loading
Prevent N+1 queries by loading relations upfront:

```php
$user = model(UserModel::class)->with('posts')->find(1);
```

#### Lazy Loading
Automatically load relations when accessed (requires entities):

```php
$user = model(UserModel::class)->find(1);
echo $user->posts[0]->title; // Loads posts automatically
```

#### Nested Relations
Load relations of relations:

```php
$user = model(UserModel::class)->with(['posts', 'posts.comments'])->find(1);
```

#### Query Constraints
Filter relation results with callbacks:

```php
$user = model(UserModel::class)
    ->with('posts', fn($model) => $model->where('published', 1))
    ->find(1);
```

### Writing Operations

Different relation types support different write methods. See each relation's documentation for details:

| Relation | Write Methods |
|----------|---------------|
| One to one / Has One, One to many / Has Many | `save()`, `saveMany()` |
| One to many (inverse) / Belongs To | `save()`, `associate()`, `dissociate()` |
| Many to many / Belongs To many | `save()`, `saveMany()`, `attach()`, `detach()`, `sync()` |
| Morph one, Morph many | `save()`, `saveMany()` |
| Morph to | `associate()`, `dissociate()` |
| Has one through, Has many through | Read-only |
| Has one of many, Morph one of many | Read-only (use One to many / Has Many or Morph many for writes) |
