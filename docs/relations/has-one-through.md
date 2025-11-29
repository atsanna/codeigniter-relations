# Has one through

A one-to-one relationship that is linked through an intermediate model. The parent model is related to a single record in the final model through another model acting as a bridge.

### When to Use

Use `HasOneThrough` when you need to access a distantly related single record through an intermediate model. Common examples:

- User (through Company) has one Address
- Author (through Book) has one Publisher
- Employee (through Department) has one Building

### Database Structure

```
users
-----
id           (primary key)
name
email
company_id   (foreign key to companies)

companies
---------
id           (primary key)
name
address_id   (foreign key to addresses)

addresses
---------
id           (primary key)
street
city
country
```

The relation path: User -> Company -> Address

### Defining the Relation

```php
use Michalsn\CodeIgniterRelations\Relations\HasOneThrough;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class UserModel extends Model
{
    use HasRelations;

    protected $table = 'users';
    protected $returnType = User::class;

    public function address(): HasOneThrough
    {
        return $this->hasOneThrough(
            AddressModel::class,   // Final model
            CompanyModel::class    // Intermediate model
        );
    }
}
```

#### Full Parameter Control

If your database structure doesn't follow conventions, you can specify all keys:

```php
public function address(): HasOneThrough
{
    return $this->hasOneThrough(
        AddressModel::class,   // Final model
        CompanyModel::class,   // Intermediate model
        'address_id',          // Foreign key on intermediate model (company.address_id)
        'id',                  // Local key on final model (address.id)
        'id',                  // Local key on parent model (user.id)
        'company_id'           // Foreign key on parent model (user.company_id)
    );
}
```

**Parameter order:**

1. `$relatedModel` - The final model to retrieve
2. `$throughModel` - The intermediate model
3. `$foreignKey` - Foreign key on the intermediate model
4. `$localKey` - Primary key on the final model
5. `$firstKey` - Primary key on the parent model
6. `$secondKey` - Foreign key on the parent model

### Reading Data

#### Eager Loading

```php
// Load user with address
$user = model(UserModel::class)->with('address')->find(1);
echo "{$user->address->street}, {$user->address->city}";

// Load multiple users with addresses
$users = model(UserModel::class)->with('address')->findAll();
foreach ($users as $user) {
    echo "{$user->name} - {$user->address->city}";
}
```

#### Lazy Loading

```php
$user = model(UserModel::class)->find(1);
echo $user->address->city; // Address is loaded automatically through company
```

### With Query Constraints

```php
$user = model(UserModel::class)
    ->with('address', fn($model) => $model->where('addresses.country', 'USA'))
    ->find(1);
```

### Writing Data

**HasOneThrough is a read-only relation.** You cannot write through this relation because it would require coordinating changes across multiple models.

To modify data, work with the intermediate or final models directly:

```php
// Update the address directly
$address = model(AddressModel::class)->find(1);
$address->street = 'New Street';
$address->save();

// Or update through the intermediate model
$company = model(CompanyModel::class)->find(1);
$company->address()->save([...]);
```

### Available Methods

This is a read-only relation with no write methods available.
