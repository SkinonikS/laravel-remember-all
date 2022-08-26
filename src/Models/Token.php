<?php

namespace SkinonikS\Laravel\RememberAll\Models;

use SkinonikS\Laravel\RememberAll\Contracts\Token as TokenContract;
use SkinonikS\Laravel\RememberAll\Concerns\Token as TokenConcern;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Token extends Model implements TokenContract
{
    use TokenConcern;

    /**
     * {@inheritDoc}
     */
    protected $table = 'remember_tokens';

    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'token',
        'session_id',
        'user_id',
    ];

    /**
     * {@inheritDoc}
     */
    protected $hidden = [
        'token',
        'session_id',
    ];
}
