# One to many (inverse) / Belongs To

A one-to-many inverse relationship where a model belongs to another model. This is the inverse side of the `HasMany` or `HasOne` relationship.

### When to Use

Use `BelongsTo` when the current model has a foreign key pointing to another model. Common examples:

- Post belongs to User
- Comment belongs to Post
- Order belongs to Customer

### Database Structure

```
users
-----
id           (primary key)
name
email

posts
-----
id           (primary key)
user_id      (foreign key)
title
content
```

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class PostModel extends Model
{
    use HasRelations;

    protected $table = 'posts';
    protected $returnType = Post::class;

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }
}
```

#### Custom Foreign Keys

By default, the foreign key is assumed to be `{related_table_singular}_id` (e.g., `user_id`). You can customize it:

```php
public function author(): BelongsTo
{
    return $this->belongsTo(UserModel::class, 'author_id');
}
```

### Reading Data

#### Eager Loading

```php
// Load post with user
$post = model(PostModel::class)->with('user')->find(1);
echo $post->user->name;

// Load multiple posts with users
$posts = model(PostModel::class)->with('user')->findAll();
foreach ($posts as $post) {
    echo "{$post->title} by {$post->user->name}";
}
```

#### Lazy Loading

```php
$post = model(PostModel::class)->find(1);
echo $post->user->name; // User is loaded automatically when accessed
```

#### With Query Constraints

```php
$posts = model(PostModel::class)
    ->with('user', fn($model) => $model->where('users.active', 1))
    ->findAll();
```

### Writing Data

#### Saving the Parent Record

Use `save()` to create or update the parent record and automatically link it:

```php
$post = model(PostModel::class)->find(1);

// Create a new user and link the post to them
$post->user()->save([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Update existing user
$post->user()->save([
    'id' => 2,
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
]);
```

#### Associating Records

Use `associate()` to link the current model to a parent without creating a new parent record:

```php
$post = model(PostModel::class)->find(1);

// Associate with user entity
$user = model(UserModel::class)->find(2);
$post->user()->associate($user);

// Associate with user ID
$post->user()->associate(2);
```

**Behavior when passing an object vs ID:**

- **Pass object**: Updates both foreign key and relation object in memory immediately
- **Pass ID**: Only updates foreign key; relation object remains stale until `refresh()` or `load()` is called

```php
$post = model(PostModel::class)->with('user')->find(1);

// With object - relation updates immediately
$newUser = model(UserModel::class)->find(2);
$post->user()->associate($newUser);
echo $post->user->name; // "Jane" (updated immediately)

// With ID - foreign key updates, but relation remains stale
$post->user()->associate(3);
echo $post->user_id; // 3 (updated)
echo $post->user->name; // Still "Jane" (stale, needs refresh)
$post->refresh(); // Now $post->user->name is current
```

#### Dissociating Records

Use `dissociate()` to remove the association (sets foreign key to null):

```php
$post = model(PostModel::class)->with('user')->find(1);
$post->user()->dissociate();

echo $post->user_id; // null
echo $post->user; // null
```

### Available Methods

| Method | Description |
|--------|-------------|
| `save($data)` | Create or update the parent record and link to current model |
| `associate($parent)` | Link current model to an existing parent (by ID or entity) |
| `dissociate()` | Remove the association (set foreign key to null) |
