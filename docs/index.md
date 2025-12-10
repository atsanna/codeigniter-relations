# CodeIgniter Relations

A powerful package that adds seamless **relationship management** to CodeIgniter 4, with a clean, object-oriented API for handling complex database relationships.

### Requirements

![PHP](https://img.shields.io/badge/PHP-%5E8.2-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-%5E4.6-blue)

### Key Features

- **9 Relation Types**: `HasOne`, `HasMany`, `BelongsTo`, `BelongsToMany`, `HasOneThrough`, `HasManyThrough`, `MorphOne`, `MorphMany`, and `MorphTo`
- **Eager Loading**: Prevent N+1 queries with `with()` and nested relations
- **Lazy Loading**: Automatic loading when accessing relation properties
- **Entity Model Integration**: Save and delete entities directly (`$user->save()`, `$user->delete()`)
- **Relation Writes**: Save related data through relations (`$user->posts()->save([...])`)
- **Polymorphic Relations**: Relations that can belong to multiple model types
- **Convention-Based**: Minimal configuration with sensible defaults

### Quick Example

```php
// Define relation in model
class UserModel extends Model
{
    use HasRelations;

    protected $returnType = User::class;

    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class);
    }
}

// Eager load to prevent N+1 queries
$users = $userModel->with('posts')->findAll();

// Access relations
foreach ($users as $user) {
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}

// Save through relations
$user->posts()->save(['title' => 'New Post']);
```

