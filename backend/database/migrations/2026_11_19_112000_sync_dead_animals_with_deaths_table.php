<?php

use App\Models\Animal;
use App\Models\Death;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $deadAnimals = Animal::where(function ($q) {
            $q->where('status', 'dead')
                ->orWhere('current_status', 'dead')
                ->orWhere('current_status', 'deceased')
                ->orWhere('health_status', 'dead');
        })->whereDoesntHave('deaths')->get();

        foreach ($deadAnimals as $animal) {
            Death::create([
                'animal_id' => $animal->id,
                'death_date' => $animal->updated_at?->toDateString() ?? now()->toDateString(),
                'cause' => !empty($animal->notes) ? substr($animal->notes, 0, 100) : 'Recorded on animal master',
                'location_id' => $animal->location_id,
                'notes' => 'Synchronized from animal status',
            ]);
            if ($animal->current_status !== 'inactive') {
                $animal->forceFill(['current_status' => 'inactive'])->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        // No down needed as death records reflect underlying animal state
    }
};
