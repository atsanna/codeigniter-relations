# Configuration

This library relies on conventions to automatically discover relationships between models.

##### Models

- Model class names must end with "Model" suffix (e.g., `UserModel`)
- Table names must be plural (e.g., `users` table for `UserModel`)
- Foreign keys follow the pattern: `{singular_table}_{primary_key}` (e.g., `user_id` for `users` table with `id` primary key)

##### Entities

- Entity class names should match their model names without the "Model" suffix (e.g., `User` entity for `UserModel`)
- Entities should be in a namespace discoverable from the model (e.g., models in `App\Models`, entities in `App\Entities`)

### Model Setup

Every model that uses relations **must** include the `HasRelations` trait:

```php
<?php

namespace App\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Michalsn\CodeIgniterRelations\Relations\HasMany;

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

#### Using initialize() Method

If you override the model's `initialize()` method, you **must** call `initRelations()`:

```php
protected function initialize()
{
    $this->initRelations();

    // Your custom initialization code
    // ...
}
```

If you don't override `initialize()`, the trait handles everything automatically.

### Entity Setup

Every entity that works with relations should use the `HasLazyRelations` trait:

```php
<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use Michalsn\CodeIgniterRelations\Traits\HasLazyRelations;

class User extends Entity
{
    use HasLazyRelations;
}
```

This trait enables:

- **Lazy loading**: Automatic loading of relations when accessed via property
- **Relation reloading**: `refresh()` method to reload entity and all relations, `load()` method to load specific relations
- **Active Record pattern**: `$user->save()` and `$user->delete()` methods
- **Relation writes**: `$user->posts()->save([...])` to save related records

