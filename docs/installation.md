# Installation

- [Composer Installation](#composer-installation)
- [Manual Installation](#manual-installation)
- [Verification](#verification)

## Composer Installation

Install via Composer:

```console
composer require michalsn/codeigniter-relations
```

## Manual Installation

If you prefer manual installation, follow these steps:

1. Download this project
2. Place the files in `app/ThirdParty/relations` directory
3. Enable the package by editing `app/Config/Autoload.php`:

```php
<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig
{
    // ...

    public $psr4 = [
        APP_NAMESPACE => APPPATH,
        'Michalsn\CodeIgniterRelations' => APPPATH . 'ThirdParty/relations/src',
    ];

    // ...

    public $files = [
        APPPATH . 'ThirdParty/relations/src/Common.php',
    ];

    // ...
}
```

## Verification

To verify the installation, create a simple model with the `HasRelations` trait:

```php
<?php

namespace App\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;

class UserModel extends Model
{
    use HasRelations;

    // ...
}
```

If no errors occur, the installation is successful.
