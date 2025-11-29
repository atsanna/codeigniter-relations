# Morph one

A polymorphic one-to-one relationship where a related model can belong to multiple types of parent models using a single relation.

### When to Use

Use `MorphOne` when different models can have the same type of single related record. Common examples:

- Post or Video has one Image (featured image)
- User or Company has one Address
- Product or Service has one Rating

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

images
------
id               (primary key)
imageable_type   (stores the parent model class name)
imageable_id     (stores the parent model ID)
url
alt_text
```

The `imageable_type` and `imageable_id` columns work together to identify which parent record the image belongs to.

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\MorphOne;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class PostModel extends Model
{
    use HasRelations;

    protected $table = 'posts';
    protected $returnType = Post::class;

    public function image(): MorphOne
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }
}
```

```php
class VideoModel extends Model
{
    use HasRelations;

    protected $table = 'videos';
    protected $returnType = Video::class;

    public function image(): MorphOne
    {
        return $this->morphOne(ImageModel::class, 'imageable');
    }
}
```

The `'imageable'` parameter is the morph name. The relation will look for `imageable_type` and `imageable_id` columns in the images table.

#### Custom Column Names

```php
public function image(): MorphOne
{
    return $this->morphOne(
        ImageModel::class,
        'imageable',          // Morph name
        'custom_type',        // Custom type column
        'custom_id'           // Custom ID column
    );
}
```

### Reading Data

#### Eager Loading

```php
// Load post with image
$post = model(PostModel::class)->with('image')->find(1);
echo $post->image->url;

// Load video with image
$video = model(VideoModel::class)->with('image')->find(1);
echo $video->image->url;

// Load multiple posts with images
$posts = model(PostModel::class)->with('image')->findAll();
foreach ($posts as $post) {
    if ($post->image) {
        echo $post->image->url;
    }
}
```

#### Lazy Loading

```php
$post = model(PostModel::class)->find(1);
echo $post->image->url; // Image is loaded automatically
```

### With Query Constraints

```php
$post = model(PostModel::class)
    ->with('image', fn($model) => $model->where('images.approved', 1))
    ->find(1);
```

### Writing Data

#### Creating a Related Record

Use `save()` to create a new morphed record:

```php
$post = model(PostModel::class)->find(1);

// Save with array
$post->image()->save([
    'url' => 'https://example.com/image.jpg',
    'alt_text' => 'Featured image',
]);

// Save with entity
$image = new Image();
$image->url = 'https://example.com/image2.jpg';
$image->alt_text = 'Another image';
$post->image()->save($image);
```

The `save()` method automatically sets both `imageable_type` (to the parent model class) and `imageable_id` (to the parent ID).

#### Updating an Existing Record

```php
$post = model(PostModel::class)->with('image')->find(1);

// Update and save
$post->image->alt_text = 'Updated alt text';
$post->image->save();
```

### Available Methods

| Method | Description |
|--------|-------------|
| `save($data)` | Create or update the morphed record |
