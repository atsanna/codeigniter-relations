# Morph many

A polymorphic one-to-many relationship where a related model can belong to multiple types of parent models using a single relation.

### When to Use

Use `MorphMany` when different models can have multiple related records of the same type. Common examples:

- Post or Video has many Comments
- Product or Service has many Reviews
- User or Organization has many Activity Logs

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
commentable_type    (stores the parent model class name)
commentable_id      (stores the parent model ID)
content
created_at
```

The `commentable_type` and `commentable_id` columns work together to identify which parent record each comment belongs to.

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\MorphMany;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class PostModel extends Model
{
    use HasRelations;

    protected $table = 'posts';
    protected $returnType = Post::class;

    public function comments(): MorphMany
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}
```

```php
class VideoModel extends Model
{
    use HasRelations;

    protected $table = 'videos';
    protected $returnType = Video::class;

    public function comments(): MorphMany
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}
```

The `'commentable'` parameter is the morph name. The relation will look for `commentable_type` and `commentable_id` columns in the comments table.

#### Custom Column Names

```php
public function comments(): MorphMany
{
    return $this->morphMany(
        CommentModel::class,
        'commentable',        // Morph name
        'custom_type',        // Custom type column
        'custom_id'           // Custom ID column
    );
}
```

### Reading Data

#### Eager Loading

```php
// Load post with comments
$post = model(PostModel::class)->with('comments')->find(1);
foreach ($post->comments as $comment) {
    echo $comment->content;
}

// Load video with comments
$video = model(VideoModel::class)->with('comments')->find(1);
foreach ($video->comments as $comment) {
    echo $comment->content;
}
```

#### Lazy Loading

```php
$post = model(PostModel::class)->find(1);
foreach ($post->comments as $comment) {
    echo $comment->content; // Comments are loaded automatically
}
```

#### With Query Constraints

```php
// Only approved comments
$post = model(PostModel::class)
    ->with('comments', fn($model) => $model->where('comments.approved', 1))
    ->find(1);

// Recent comments ordered by date
$post = model(PostModel::class)
    ->with('comments', fn($model) => $model->orderBy('created_at', 'DESC')->limit(10))
    ->find(1);
```

### Writing Data

#### Creating a Single Record

Use `save()` to create a new morphed record:

```php
$post = model(PostModel::class)->find(1);

// Save with array
$post->comments()->save([
    'content' => 'Great article!',
    'user_id' => 1,
]);

// Save with entity
$comment = new Comment();
$comment->content = 'Thanks for sharing!';
$comment->user_id = 2;
$post->comments()->save($comment);
```

The `save()` method automatically sets both `commentable_type` (to the parent model class) and `commentable_id` (to the parent ID).

#### Creating Multiple Records

Use `saveMany()` to create multiple morphed records at once:

```php
$post = model(PostModel::class)->find(1);

// Save multiple comments
$post->comments()->saveMany([
    ['content' => 'First comment', 'user_id' => 1],
    ['content' => 'Second comment', 'user_id' => 2],
    ['content' => 'Third comment', 'user_id' => 3],
]);

// With entities
$comment1 = new Comment(['content' => 'Comment 1', 'user_id' => 1]);
$comment2 = new Comment(['content' => 'Comment 2', 'user_id' => 2]);
$post->comments()->saveMany([$comment1, $comment2]);
```

#### Updating Existing Records

```php
$post = model(PostModel::class)->with('comments')->find(1);

// Update individual comment
$comment = $post->comments[0];
$comment->content = 'Updated comment';
$comment->save();
```

### Available Methods

| Method | Description |
|--------|-------------|
| `save($data)` | Create or update a single morphed record |
| `saveMany($data, $useTransaction = true)` | Create or update multiple morphed records |

### Transaction Control

By default, `saveMany()` uses a database transaction:

```php
// Default: uses transaction
$post->comments()->saveMany([...]);

// Disable transaction for partial success
$post->comments()->saveMany([...], useTransaction: false);
```
