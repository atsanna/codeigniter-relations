# Has one of many

A specialized one-to-one relationship where a parent model has multiple related records, but only one is considered active or relevant based on a specific condition.

### When to Use

Use `One of Many` when you need to retrieve a single representative record from a collection. Common examples:

- User's latest Post
- Product's highest rated Review
- Customer's most recent Order
- Employee's oldest Assignment

### Database Structure

```
users
-----
id        (primary key)
name
email

posts
-----
id          (primary key)
user_id     (foreign key)
title
content
rating
created_at
```

Same structure as HasMany, but we retrieve only one record based on specific criteria.

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\HasOne;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Michalsn\CodeIgniterRelations\Enums\OrderTypes;

class UserModel extends Model
{
    use HasRelations;

    protected $table = 'users';
    protected $returnType = User::class;

    public function latestPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->latestOfMany();
    }

    public function oldestPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->oldestOfMany();
    }

    public function bestPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->ofMany('rating', OrderTypes::DESC);
    }
}
```

### Available Methods

#### latestOfMany()

Returns the most recent record. If the model uses timestamps, it orders by `createdField`, otherwise by `primaryKey`.

```php
public function latestPost(): HasOne
{
    return $this->hasOne(PostModel::class)->latestOfMany();
}
```

#### oldestOfMany()

Returns the oldest record. If the model uses timestamps, it orders by `createdField`, otherwise by `primaryKey`.

```php
public function oldestPost(): HasOne
{
    return $this->hasOne(PostModel::class)->oldestOfMany();
}
```

#### ofMany($column, $order)

Returns one record ordered by a specific column and direction.

**Parameters:**

- `$column` - The column to order by
- `$order` - `OrderTypes::ASC` or `OrderTypes::DESC`

```php
public function bestPost(): HasOne
{
    return $this->hasOne(PostModel::class)->ofMany('rating', OrderTypes::DESC);
}

public function cheapestProduct(): HasOne
{
    return $this->hasOne(ProductModel::class)->ofMany('price', OrderTypes::ASC);
}
```

### Reading Data

#### Eager Loading

```php
// Get user with their latest post
$user = model(UserModel::class)->with('latestPost')->find(1);
echo $user->latestPost->title;

// Get user with their oldest post
$user = model(UserModel::class)->with('oldestPost')->find(1);
echo $user->oldestPost->title;

// Get user with their best rated post
$user = model(UserModel::class)->with('bestPost')->find(1);
echo "Best post: {$user->bestPost->title} (Rating: {$user->bestPost->rating})";

// Load multiple relations
$user = model(UserModel::class)
    ->with(['latestPost', 'bestPost'])
    ->find(1);
```

#### Lazy Loading

```php
$user = model(UserModel::class)->find(1);
echo $user->latestPost->title; // Latest post is loaded automatically
echo $user->bestPost->rating;  // Best post is loaded automatically
```

### Writing Data

One of Many is primarily a **read-only pattern**. To create or update records, use the standard HasMany relation:

```php
class UserModel extends Model
{
    // Standard relation for writing
    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class);
    }

    // "One of Many" for reading
    public function latestPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->latestOfMany();
    }
}

// Create posts through standard relation
$user->posts()->save(['title' => 'New Post', 'content' => 'Content...']);

// Read through "one of many"
echo $user->latestPost->title;
```

### Common Patterns

#### Combining Multiple Criteria

You can define multiple "one of many" relations on the same model:

```php
class UserModel extends Model
{
    public function latestPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->latestOfMany();
    }

    public function latestPublishedPost(): HasOne
    {
        return $this->hasOne(PostModel::class)
            ->latestOfMany()
            ->where('published', 1);
    }

    public function mostViewedPost(): HasOne
    {
        return $this->hasOne(PostModel::class)->ofMany('views', OrderTypes::DESC);
    }
}
```

#### With Query Constraints

Add additional constraints to filter the candidates:

```php
public function latestPublishedPost(): HasOne
{
    return $this->hasOne(PostModel::class)
        ->latestOfMany();
}

// Usage with callback
$user = model(UserModel::class)
    ->with('latestPublishedPost', fn($model) => $model->where('published', 1))
    ->find(1);
```
