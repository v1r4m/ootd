<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outfit extends Model
{
    protected $fillable = ['profile_id', 'worn_on', 'description', 'prompt', 'avatar_path', 'engine'];

    protected function casts(): array
    {
        return [
            'worn_on' => 'date',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
