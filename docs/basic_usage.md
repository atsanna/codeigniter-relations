# Basic usage

This library works with both entities and simple arrays or objects. If your models use `'array'` or `'object'` as the return type, you can still use the `with()` method for eager loading relations. This prevents N+1 queries and works with all relation types.

However, features like lazy loading, writing relations through entities, and Entity Model Integration methods require entity classes with the `HasLazyRelations` trait. For the full feature set, using custom entities is recommended.

### Eager loading

First, define your relations in the model. In the example below, `UserModel` has a one-to-one relation with `ProfileModel`.

```php
use Michalsn\CodeIgniterRelations\Relations\HasOne;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class UserModel extends Model
{
    use HasRelations;

    protected $table = 'users';
    protected $returnType = User::class;

    public function profile(): HasOne
    {
        return $this->hasOne(ProfileModel::class);
    }
}
```

To eagerly load the data, use the `with()` method and specify the relation name:

```php
$users = model(UserModel::class)->with('profile')->findAll();
```

This performs two queries: one for all users, and one to fetch all profiles for these users. The relation data will be available under the relation name, in this case `$user->profile`. The data format will respect the `$returnType` set in the `ProfileModel` class.

#### Loading multiple relations

You can load multiple relations at once by passing an array:

```php
$user = model(UserModel::class)->with(['profile', 'posts', 'roles'])->find(1);
```

#### Nested relations

You can load nested relations using dot notation. This will query all posts for the user and then all comments for those posts:

```php
$user = model(UserModel::class)->with(['posts', 'posts.comments'])->find(1);
```

The `'posts.comments'` relation means the `comments` relation will be looked up in the `PostModel` class.

#### Query constraints

You can apply constraints to relation queries using a callback:

```php
$user = model(UserModel::class)
    ->with('posts', static function (Model $model) {
        $model->where('posts.published', 1);
        $model->orderBy('posts.created_at', 'DESC');
    })
    ->find(1);
```

This loads only published posts, ordered by creation date. Callbacks work with nested relations as well:

```php
$user = model(UserModel::class)
    ->with('posts')
    ->with('posts.comments', static function (Model $model) {
        $model->where('comments.user_id', 1);
    })
    ->find(1);
```

### Lazy loading

Lazy loading requires using an entity class for the `$returnType`. The entity is responsible for triggering the relation request when you access the relation property.

Define your model with an entity return type:

```php
use Michalsn\CodeIgniterRelations\Relations\HasOne;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class UserModel extends Model
{
    use HasRelations;

    protected $table = 'users';
    protected $returnType = User::class;

    public function profile(): HasOne
    {
        return $this->hasOne(ProfileModel::class);
    }
}
```

The entity class must use the `HasLazyRelations` trait:

```php
use CodeIgniter\Entity\Entity;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

class User extends Entity
{
    use HasLazyRelations;
}
```

With lazy loading, data is fetched on demand when you access the relation property:

```php
$users = model(UserModel::class)->findAll();
foreach ($users as $user) {
    // Profile is loaded here when accessed
    echo $user->profile->bio;
}
```

!!! Note

    This performs N+1 queries - one to get all users and then one for each profile accessed. For better performance, use eager loading with `with()` instead.

## Writing relations

You can save related data directly through entities without referencing the model. This requires using entity classes with the `HasLazyRelations` trait.

#### Saving a single record

Use `save()` to create or update a single related record. You can pass an array or entity:

```php
$user = model(UserModel::class)->find(1);

// Save with array
$user->posts()->save([
    'title' => 'My First Post',
    'content' => 'This is the content...',
]);

// Or save an entity
$post = new Post();
$post->title = 'My Second Post';
$post->content = 'More content...';
$user->posts()->save($post);
```

The `save()` method automatically sets the foreign key, inserts new records (if no ID is present), or updates existing records (if ID is present).

#### Saving multiple records

For `HasMany` and `MorphMany` relations, use `saveMany()` to save multiple records at once:

```php
$user = model(UserModel::class)->find(1);

$user->posts()->saveMany([
    ['title' => 'Post 1', 'content' => 'Content 1'],
    ['title' => 'Post 2', 'content' => 'Content 2'],
]);
```

#### Updating existing relations

You can update already loaded related records using the entity's `save()` method:

```php
$user = model(UserModel::class)->with('profile')->find(1);

// Update and save the profile
$user->profile->bio = 'Updated biography';
$user->profile->save();
```

#### More write operations

Different relation types support different operations like `attach()`, `detach()`, `sync()`, `associate()`, and `dissociate()`. See the [Supported write operations](#supported-write-operations) table below and the [Relations](relations.md) documentation for detailed information.

### Reloading relations

When working with entities, you can reload data from the database using `refresh()` and `load()` methods.

#### refresh()

Reloads the entity's attributes and all previously loaded relations from the database:

```php
$user = model(UserModel::class)->with('posts')->find(1);

// Make changes elsewhere...

// Reload everything
$user->refresh();
```

#### load()

Loads or reloads specific relations without touching the entity's attributes:

```php
$user = model(UserModel::class)->find(1);

// Load relations on demand
$user->load('posts');
$user->load(['profile', 'roles']);

// Load with nested relations
$user->load('posts.comments');

// Load with callback
$user->load('posts', static function (Model $model) {
    $model->where('published', 1);
});
```

### Supported write operations

Different relation types support different write operations:

| Relation Type | Available Methods | Description |
|--------------|------------------|-------------|
| **HasOne** | `save()` | Save a single related record (insert or update) |
| **HasMany** | `save()`<br>`saveMany()` | Save one or multiple related records (insert or update) |
| **BelongsTo** | `save()`<br>`associate()`<br>`dissociate()` | Save the parent record, associate child with parent, or remove the association |
| **BelongsToMany** | `save()`<br>`saveMany()`<br>`attach()`<br>`detach()`<br>`sync()` | Create and attach new records, or manage existing pivot table entries |
| **HasOneThrough** | *None* | Read-only relation (cannot write through intermediate models) |
| **HasManyThrough** | *None* | Read-only relation (cannot write through intermediate models) |
| **MorphOne** | `save()` | Save a single polymorphic related record (insert or update) |
| **MorphMany** | `save()`<br>`saveMany()` | Save one or multiple polymorphic related records (insert or update) |
| **MorphTo** | `associate()`<br>`dissociate()` | Associate child with any parent type, or remove the association |

For detailed method descriptions, usage examples, and edge cases, see the [Relations documentation](relations.md).
