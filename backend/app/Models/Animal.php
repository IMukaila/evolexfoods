<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Animal extends Model
{
    protected $fillable = [
        'animal_id', 'tag_id', 'species', 'breed', 'sex', 'dob',
        'mother_id', 'father_id', 'source', 'cost', 'location_id',
        'weight', 'breeding_status', 'pregnancy_status', 'expected_birth_date',
        'health_status', 'status', 'current_status', 'photo_url', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'expected_birth_date' => 'date',
            'cost' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Animal $animal) {
            $animal->events()->delete();
            $animal->pregnancies()->delete();
            $animal->healthRecords()->delete();
            $animal->treatments()->delete();
            $animal->deaths()->delete();
            $animal->births()->delete();
            Weight::where('animal_id', $animal->id)->delete();
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AnimalEvent::class);
    }

    public function pregnancies(): HasMany
    {
        return $this->hasMany(Pregnancy::class);
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function deaths(): HasMany
    {
        return $this->hasMany(Death::class);
    }

    public function births(): HasMany
    {
        return $this->hasMany(Birth::class);
    }

    public function scopeAlive($query)
    {
        return $query->where('animals.status', 'alive')->where('animals.current_status', 'active');
    }
}
