<?php

namespace Gzai\LaravelBoxAdapter\Models;

use Illuminate\Database\Eloquent\Model;

class BoxToken extends Model
{
    protected $table = 'box_tokens';

    protected $fillable = [
        'user_id', 
        'access_token', 
        'refresh_token', 
        'expires_in', 
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}