# One to one / Has One

A one-to-one relationship where one model is associated with exactly one instance of another model.

### When to Use

Use `HasOne` when a parent model owns exactly one related record. Common examples:

- User has one Profile
- Country has one Capital
- Order has one Invoice

### Database Structure

```
users
------
id        (primary key)
name
email

profiles
--------
id        (primary key)
user_id   (foreign key)
bio
avatar
```

### Defining the Relation

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

#### Custom Foreign Keys

By default, the foreign key is assumed to be `{parent_table_singular}_id` (e.g., `user_id`). You can customize it:

```php
public function profile(): HasOne
{
    return $this->hasOne(ProfileModel::class, 'custom_user_id');
}
```

### Reading Data

#### Eager Loading

```php
// Load user with profile
$user = model(UserModel::class)->with('profile')->find(1);
echo $user->profile->bio;

// Load multiple users with profiles
$users = model(UserModel::class)->with('profile')->findAll();
foreach ($users as $user) {
    echo "{$user->name}: {$user->profile->bio}";
}
```

#### Lazy Loading

```php
$user = model(UserModel::class)->find(1);
echo $user->profile->bio; // Profile is loaded automatically when accessed
```

#### With Query Constraints

```php
$user = model(UserModel::class)
    ->with('profile', fn($model) => $model->where('is_public', 1))
    ->find(1);
```

### Writing Data

#### Creating a Related Record

Use `save()` to create a new related record:

```php
$user = model(UserModel::class)->find(1);

// Save with array
$user->profile()->save([
    'bio' => 'Software developer',
    'avatar' => 'avatar.jpg',
]);

// Save with entity
$profile = new Profile();
$profile->bio = 'Senior developer';
$profile->avatar = 'avatar2.jpg';
$user->profile()->save($profile);
```

The `save()` method automatically sets the foreign key (`user_id`) to link the profile to the user.

#### Updating an Existing Record

```php
$user = model(UserModel::class)->with('profile')->find(1);

// Update and save
$user->profile->bio = 'Updated biography';
$user->profile->save();
```

### Available Methods

| Method | Description |
|--------|-------------|
| `save($data)` | Create or update the related record |
