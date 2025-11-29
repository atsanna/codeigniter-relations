# Morph to

The inverse of a polymorphic relationship, allowing a model to belong to multiple different parent types.

### When to Use

Use `MorphTo` on the morphed model to define the inverse of MorphOne or MorphMany relations. Common examples:

- Comment belongs to Post or Video (commentable)
- Image belongs to User or Product (imageable)
- Activity belongs to User or Organization (trackable)

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
commentable_type    (stores parent model class: PostModel or VideoModel)
commentable_id      (stores parent model ID)
content
created_at
```

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\MorphTo;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class CommentModel extends Model
{
    use HasRelations;

    protected $table = 'comments';
    protected $returnType = Comment::class;

    public function commentable(): MorphTo
    {
        return $this->morphTo('commentable');
    }
}
```

The `'commentable'` parameter must match the morph name used in the parent models.

#### Custom Column Names

```php
public function commentable(): MorphTo
{
    return $this->morphTo(
        'commentable',        // Morph name
        'custom_type',        // Custom type column
        'custom_id'           // Custom ID column
    );
}
```

### Reading Data

#### Eager Loading

```php
// Load comment with its parent (Post or Video)
$comment = model(CommentModel::class)->with('commentable')->find(1);

if ($comment->commentable instanceof Post) {
    echo "Comment on post: " . $comment->commentable->title;
} elseif ($comment->commentable instanceof Video) {
    echo "Comment on video: " . $comment->commentable->title;
}

// Load multiple comments with their parents
$comments = model(CommentModel::class)->with('commentable')->findAll();
foreach ($comments as $comment) {
    echo $comment->commentable->title; // Works for both Post and Video
}
```

#### Lazy Loading

```php
$comment = model(CommentModel::class)->find(1);
echo $comment->commentable->title; // Parent is loaded automatically
```

#### With Query Constraints

```php
$comments = model(CommentModel::class)
    ->with('commentable', fn($model) => $model->where('published', 1))
    ->findAll();
```

### Writing Data

**MorphTo is a read-only relation.** You cannot save through this relation. Instead, use `associate()` and `dissociate()` to manage the relationship.

#### Associating with a Parent

Use `associate()` to link a morphed model to a parent:

```php
$comment = model(CommentModel::class)->find(1);

// Associate with a post (using entity)
$post = model(PostModel::class)->find(5);
$comment->commentable()->associate($post);

// Associate with a video (using model class and ID)
$comment->commentable()->associate(VideoModel::class, 3);
```

**Behavior:**

- When passing an entity: Sets both `commentable_type` and `commentable_id`, updates in memory and database
- When passing model class and ID: Sets both fields, persists to database

#### Dissociating from Parent

Use `dissociate()` to remove the association:

```php
$comment = model(CommentModel::class)->with('commentable')->find(1);
$comment->commentable()->dissociate();

// Both commentable_type and commentable_id are now null
echo $comment->commentable_type; // null
echo $comment->commentable; // null
```

### Available Methods

| Method | Description |
|--------|-------------|
| `associate($parent, $id = null)` | Link to a parent (entity or model class + ID) |
| `dissociate()` | Remove the association (set morph fields to null) |

### Usage Patterns

#### Two Ways to Associate

```php
$comment = model(CommentModel::class)->find(1);

// Method 1: Pass entity
$post = model(PostModel::class)->find(5);
$comment->commentable()->associate($post);

// Method 2: Pass model class and ID
$comment->commentable()->associate(VideoModel::class, 3);
```

#### Checking Parent Type

```php
$comment = model(CommentModel::class)->with('commentable')->find(1);

if ($comment->commentable instanceof Post) {
    // Handle post comment
} elseif ($comment->commentable instanceof Video) {
    // Handle video comment
}

// Or check the type column directly
if ($comment->commentable_type === PostModel::class) {
    // Post comment
}
```
