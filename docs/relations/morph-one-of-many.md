# Morph one of many

A specialized polymorphic one-to-one relationship where a parent model has multiple morphed records, but only one is considered active or relevant based on a specific condition.

### When to Use

Use `Morph One of Many` when different parent types need to retrieve a single representative record from their polymorphic collection. Common examples:

- Post or Video's latest Comment
- Product or Service's highest rated Review
- User or Organization's most recent Activity
- Article or Tutorial's newest Revision

### Database Structure

```
posts
-----
id        (primary key)
title
content

videos
------
id        (primary key)
title
url

comments
--------
id                  (primary key)
commentable_type    (stores parent model class)
commentable_id      (stores parent ID)
content
rating
created_at
```

Same structure as MorphMany, but we retrieve only one record based on specific criteria.

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\MorphOne;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Michalsn\CodeIgniterRelations\Enums\OrderType;

class PostModel extends Model
{
    use HasRelations;

    protected $table = 'posts';
    protected $returnType = Post::class;

    public function latestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')->latestOfMany();
    }

    public function oldestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')->oldestOfMany();
    }

    public function bestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')
            ->ofMany('rating', OrderType::MAX);
    }
}
```

```php
class VideoModel extends Model
{
    use HasRelations;

    protected $table = 'videos';
    protected $returnType = Video::class;

    public function latestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')->latestOfMany();
    }

    public function bestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')
            ->ofMany('rating', OrderTypes::MAX);
    }
}
```

### Available Methods

#### latestOfMany()

Returns the most recent morphed record. If the model uses timestamps, it orders by `createdField`, otherwise by `primaryKey`.

```php
public function latestComment(): MorphOne
{
    return $this->morphOne(CommentModel::class, 'commentable')->latestOfMany();
}
```

### oldestOfMany()

Returns the oldest morphed record. If the model uses timestamps, it orders by `createdField`, otherwise by `primaryKey`.

```php
public function oldestComment(): MorphOne
{
    return $this->morphOne(CommentModel::class, 'commentable')->oldestOfMany();
}
```

### ofMany($column, $order)

Returns one morphed record ordered by a specific column and direction.

**Parameters:**
- `$column` - The column to order by
- `$order` - `OrderTypes::MIN` or `OrderTypes::MAX`

```php
public function bestComment(): MorphOne
{
    return $this->morphOne(CommentModel::class, 'commentable')
        ->ofMany('rating', OrderTypes::MAX);
}

public function lowestRatedReview(): MorphOne
{
    return $this->morphOne(ReviewModel::class, 'reviewable')
        ->ofMany('rating', OrderTypes::MIN;
}
```

### Reading Data

#### Eager Loading

```php
// Get post with latest comment
$post = model(PostModel::class)->with('latestComment')->find(1);
echo $post->latestComment->content;

// Get video with best comment
$video = model(VideoModel::class)->with('bestComment')->find(1);
echo "Best comment: {$video->bestComment->content} (Rating: {$video->bestComment->rating})";

// Load multiple relations
$post = model(PostModel::class)
    ->with(['latestComment', 'bestComment'])
    ->find(1);
```

#### Lazy Loading

```php
$post = model(PostModel::class)->find(1);
echo $post->latestComment->content; // Latest comment is loaded automatically
```

### Writing Data

Morph One of Many is primarily a **read-only pattern**. To create or update records, use the standard MorphMany relation:

```php
class PostModel extends Model
{
    // Standard relation for writing
    public function comments(): MorphMany
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }

    // "Morph One of Many" for reading
    public function latestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')->latestOfMany();
    }
}

// Create comments through standard relation
$post->comments()->save(['content' => 'New comment', 'rating' => 5]);

// Read through "morph one of many"
echo $post->latestComment->content;
```

### Common Patterns

#### Multiple Criteria Across Different Parent Types

```php
class PostModel extends Model
{
    public function latestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')->latestOfMany();
    }

    public function latestApprovedComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')
            ->latestOfMany();
    }

    public function highestRatedComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')
            ->ofMany('rating', OrderTypes::MAX);
    }
}

class VideoModel extends Model
{
    // Same relations work for videos too
    public function latestComment(): MorphOne
    {
        return $this->morphOne(CommentModel::class, 'commentable')->latestOfMany();
    }
}
```

#### With Query Constraints

```php
$post = model(PostModel::class)
    ->with('latestComment', fn($model) => $model->where('approved', 1))
    ->find(1);
```
