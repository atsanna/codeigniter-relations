# One to many / Has Many

A one-to-many relationship where one model is associated with multiple instances of another model.

### When to Use

Use `HasMany` when a parent model can have multiple related records. Common examples:

- User has many Posts
- Category has many Products
- Author has many Books

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
created_at
```

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\HasMany;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class UserModel extends Model
{
    use HasRelations;

    protected $table = 'users';
    protected $returnType = User::class;

    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class);
    }
}
```

#### Custom Foreign Keys

By default, the foreign key is assumed to be `{parent_table_singular}_id` (e.g., `user_id`). You can customize it:

```php
public function posts(): HasMany
{
    return $this->hasMany(PostModel::class, 'custom_user_id');
}
```

### Reading Data

#### Eager Loading

```php
// Load user with posts
$user = model(UserModel::class)->with('posts')->find(1);
foreach ($user->posts as $post) {
    echo $post->title;
}

// Load multiple users with posts
$users = model(UserModel::class)->with('posts')->findAll();
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        echo "{$user->name}: {$post->title}";
    }
}
```

#### Lazy Loading

```php
$user = model(UserModel::class)->find(1);
foreach ($user->posts as $post) {
    echo $post->title; // Posts are loaded automatically when accessed
}
```

#### With Query Constraints

```php
// Only published posts
$user = model(UserModel::class)
    ->with('posts', fn($model) => $model->where('published', 1))
    ->find(1);

// Recent posts ordered by date
$user = model(UserModel::class)
    ->with('posts', fn($model) => $model->orderBy('created_at', 'DESC')->limit(5))
    ->find(1);
```

### Writing Data

#### Creating a Single Record

Use `save()` to create a new related record:

```php
$user = model(UserModel::class)->find(1);

// Save with array
$user->posts()->save([
    'title' => 'My First Post',
    'content' => 'This is the content...',
]);

// Save with entity
$post = new Post();
$post->title = 'My Second Post';
$post->content = 'More content...';
$user->posts()->save($post);
```

The `save()` method automatically sets the foreign key (`user_id`) and either inserts a new record or updates an existing one (if ID is present).

#### Creating Multiple Records

Use `saveMany()` to create multiple related records at once:

```php
$user = model(UserModel::class)->find(1);

// Save multiple posts with arrays
$user->posts()->saveMany([
    ['title' => 'Post 1', 'content' => 'Content 1'],
    ['title' => 'Post 2', 'content' => 'Content 2'],
    ['title' => 'Post 3', 'content' => 'Content 3'],
]);

// Save with entities
$post1 = new Post(['title' => 'Post 4', 'content' => 'Content 4']);
$post2 = new Post(['title' => 'Post 5', 'content' => 'Content 5']);
$user->posts()->saveMany([$post1, $post2]);
```

#### Updating Existing Records

```php
$user = model(UserModel::class)->with('posts')->find(1);

// Update individual post
$post = $user->posts[0];
$post->title = 'Updated Title';
$post->save();

// Or use save() with ID present for updates
$user->posts()->save([
    'id' => 5,
    'title' => 'Updated via save()',
    'content' => 'Updated content',
]);
```

### Available Methods

| Method | Description |
|--------|-------------|
| `save($data)` | Create or update a single related record |
| `saveMany($data, $useTransaction = true)` | Create or update multiple related records |

### Transaction Control

By default, `saveMany()` uses a database transaction. If any record fails validation, all changes are rolled back:

```php
// Default: uses transaction
$user->posts()->saveMany([...]);

// Disable transaction for partial success
$user->posts()->saveMany([...], useTransaction: false);
```
