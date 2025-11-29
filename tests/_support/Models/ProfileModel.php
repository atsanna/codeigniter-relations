<?php

declare(strict_types=1);

namespace Tests\Support\Models;

use CodeIgniter\Model;
use Michalsn\CodeIgniterRelations\Relations\BelongsTo;
use Michalsn\CodeIgniterRelations\Traits\HasRelations;
use Tests\Support\Entities\Profile;

class ProfileModel extends Model
{
    use HasRelations;

    protected $table           = 'profiles';
    protected $primaryKey      = 'id';
    protected $returnType      = Profile::class;
    protected $allowedFields   = ['user_id', 'bio', 'avatar', 'website'];
    protected $useTimestamps   = true;
    protected $validationRules = [
        'bio' => 'required|min_length[3]',
    ];

    /**
     * Profile belongs to a user
     *
     * Convention:
     * - Foreign key: user_id (on profiles table)
     * - Owner key: id (on users table)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }
}
