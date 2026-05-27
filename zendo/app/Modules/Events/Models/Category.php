<?php

namespace App\Modules\Events\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasUuids;

    protected static function newFactory()
    {
        return CategoryFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
