# Has many through

A one-to-many relationship that is linked through an intermediate model. The parent model is related to multiple records in the final model through another model acting as a bridge.

### When to Use

Use `HasManyThrough` when you need to access distantly related multiple records through an intermediate model. Common examples:

- Country (through Users) has many Posts
- Organization (through Departments) has many Employees
- Category (through Subcategories) has many Products

### Database Structure

```
countries
---------
id           (primary key)
name
code

users
-----
id           (primary key)
name
email
country_id   (foreign key to countries)

posts
-----
id           (primary key)
user_id      (foreign key to users)
title
content
created_at
```

The relation path: Country -> User -> Post

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\HasManyThrough;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class CountryModel extends Model
{
    use HasRelations;

    protected $table = 'countries';
    protected $returnType = Country::class;

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(
            PostModel::class,  // Final model
            UserModel::class   // Intermediate model
        );
    }
}
```

#### Full Parameter Control

If your database structure doesn't follow conventions, you can specify all keys:

```php
public function posts(): HasManyThrough
{
    return $this->hasManyThrough(
        PostModel::class,  // Final model
        UserModel::class,  // Intermediate model
        'country_id',      // Foreign key on intermediate model (user.country_id)
        'user_id',         // Foreign key on final model (post.user_id)
        'id',              // Local key on parent model (country.id)
        'id'               // Local key on intermediate model (user.id)
    );
}
```

**Parameter order:**

1. `$relatedModel` - The final model to retrieve
2. `$throughModel` - The intermediate model
3. `$firstKey` - Foreign key on intermediate model
4. `$secondKey` - Foreign key on final model
5. `$localKey` - Primary key on parent model
6. `$secondLocalKey` - Primary key on intermediate model

### Reading Data

#### Eager Loading

```php
// Load country with posts
$country = model(CountryModel::class)->with('posts')->find(1);
foreach ($country->posts as $post) {
    echo $post->title;
}

// Load multiple countries with posts
$countries = model(CountryModel::class)->with('posts')->findAll();
foreach ($countries as $country) {
    echo "{$country->name}: " . count($country->posts) . " posts";
}
```

#### Lazy Loading

```php
$country = model(CountryModel::class)->find(1);
foreach ($country->posts as $post) {
    echo $post->title; // Posts are loaded automatically through users
}
```

#### With Query Constraints

```php
// Only published posts
$country = model(CountryModel::class)
    ->with('posts', fn($model) => $model->where('posts.published', 1))
    ->find(1);

// Recent posts ordered by date
$country = model(CountryModel::class)
    ->with('posts', fn($model) => $model->orderBy('posts.created_at', 'DESC')->limit(10))
    ->find(1);

echo "Total posts from this country: " . count($country->posts);
```

### Writing Data

**HasManyThrough is a read-only relation.** You cannot write through this relation because it would require coordinating changes across multiple models.

To modify data, work with the intermediate or final models directly:

```php
// Create post for a user
$user = model(UserModel::class)->find(1);
$user->posts()->save([
    'title' => 'New Post',
    'content' => 'Content...',
]);

// Or create directly
$post = model(PostModel::class);
$post->save([
    'user_id' => 1,
    'title' => 'New Post',
    'content' => 'Content...',
]);
```

### Available Methods

This is a read-only relation with no write methods available.
