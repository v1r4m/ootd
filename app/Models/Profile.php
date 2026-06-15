<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    protected $fillable = ['handle', 'display_name', 'base_look'];

    public function outfits(): HasMany
    {
        return $this->hasMany(Outfit::class);
    }
}
