<?php

namespace App\Modules\Events\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Teacher extends Model
{
    use HasUuids;
    use Searchable;

    protected static function newFactory()
    {
        return TeacherFactory::new();
    }

    protected $fillable = [
        'name',
        'bio',
        'photo',
        'specialties',
        'email',
    ];

    protected $casts = [
        'specialties' => 'array',
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bio' => $this->bio,
            'specialties' => $this->specialties,
            'email' => $this->email,
        ];
    }
}
