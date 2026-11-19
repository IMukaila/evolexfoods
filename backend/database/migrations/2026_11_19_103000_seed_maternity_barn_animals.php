<?php

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\Location;
use App\Models\Weight;
use App\Services\AnimalIdGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $maternityBarn = Location::where('name', 'Maternity Barn')
            ->orWhere('code', 'BRN-1')
            ->first();

        if (! $maternityBarn) {
            $maternityBarn = Location::create([
                'name' => 'Maternity Barn',
                'code' => 'BRN-1',
                'type' => 'barn',
                'capacity' => 200,
                'current_count' => 0,
                'status' => 'active',
            ]);
        }

        $generator = app(AnimalIdGenerator::class);
        $batchTag = 'maternity-barn-cohort-2026';

        // Check if this batch was already seeded
        if (Animal::where('notes', 'like', "%[{$batchTag}]%")->exists()) {
            return;
        }

        DB::transaction(function () use ($maternityBarn, $generator, $batchTag) {
            // 1. Goats: 87 total (8 male, 79 female)
            for ($i = 1; $i <= 87; $i++) {
                $isMale = ($i <= 8);
                $sex = $isMale ? 'male' : 'female';
                $animalId = $generator->next('goat');
                $monthsOld = rand(14, 34);
                $dob = now()->subMonths($monthsOld)->subDays(rand(1, 28))->toDateString();
                $weight = $isMale ? round(rand(300, 440) / 10, 1) : round(rand(220, 360) / 10, 1);

                $animal = Animal::create([
                    'animal_id' => $animalId,
                    'tag_id' => 'TAG-GT-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'species' => 'goat',
                    'breed' => 'West African Dwarf',
                    'sex' => $sex,
                    'dob' => $dob,
                    'source' => 'born_on_farm',
                    'location_id' => $maternityBarn->id,
                    'weight' => $weight,
                    'breeding_status' => $isMale ? 'sire' : 'open',
                    'pregnancy_status' => $isMale ? null : 'open',
                    'health_status' => 'healthy',
                    'status' => 'alive',
                    'current_status' => 'active',
                    'notes' => "Registered into Livestock Master. Location: Maternity Barn. [{$batchTag}]",
                ]);

                AnimalEvent::create([
                    'animal_id' => $animal->id,
                    'event_type' => 'registration',
                    'occurred_at' => now(),
                    'notes' => 'Registered into Livestock Master - Maternity Barn cohort',
                ]);

                Weight::create([
                    'animal_id' => $animal->id,
                    'weighed_at' => now(),
                    'weight_kg' => $weight,
                    'method' => 'scale',
                ]);
            }

            // 2. Sheep: 3 total (1 male, 2 female)
            for ($i = 1; $i <= 3; $i++) {
                $isMale = ($i === 1);
                $sex = $isMale ? 'male' : 'female';
                $animalId = $generator->next('sheep');
                $monthsOld = rand(14, 30);
                $dob = now()->subMonths($monthsOld)->subDays(rand(1, 28))->toDateString();
                $weight = $isMale ? round(rand(360, 480) / 10, 1) : round(rand(280, 400) / 10, 1);

                $animal = Animal::create([
                    'animal_id' => $animalId,
                    'tag_id' => 'TAG-SH-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'species' => 'sheep',
                    'breed' => 'Djallonke',
                    'sex' => $sex,
                    'dob' => $dob,
                    'source' => 'born_on_farm',
                    'location_id' => $maternityBarn->id,
                    'weight' => $weight,
                    'breeding_status' => $isMale ? 'sire' : 'open',
                    'pregnancy_status' => $isMale ? null : 'open',
                    'health_status' => 'healthy',
                    'status' => 'alive',
                    'current_status' => 'active',
                    'notes' => "Registered into Livestock Master. Location: Maternity Barn. [{$batchTag}]",
                ]);

                AnimalEvent::create([
                    'animal_id' => $animal->id,
                    'event_type' => 'registration',
                    'occurred_at' => now(),
                    'notes' => 'Registered into Livestock Master - Maternity Barn cohort',
                ]);

                Weight::create([
                    'animal_id' => $animal->id,
                    'weighed_at' => now(),
                    'weight_kg' => $weight,
                    'method' => 'scale',
                ]);
            }

            // 3. Cattle: 2 total (1 male, 1 female)
            for ($i = 1; $i <= 2; $i++) {
                $isMale = ($i === 1);
                $sex = $isMale ? 'male' : 'female';
                $animalId = $generator->next('cattle');
                $monthsOld = rand(15, 36);
                $dob = now()->subMonths($monthsOld)->subDays(rand(1, 28))->toDateString();
                $weight = $isMale ? round(rand(3200, 4200) / 10, 1) : round(rand(2600, 3500) / 10, 1);

                $animal = Animal::create([
                    'animal_id' => $animalId,
                    'tag_id' => 'TAG-CT-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'species' => 'cattle',
                    'breed' => 'Sanga',
                    'sex' => $sex,
                    'dob' => $dob,
                    'source' => 'born_on_farm',
                    'location_id' => $maternityBarn->id,
                    'weight' => $weight,
                    'breeding_status' => $isMale ? 'sire' : 'open',
                    'pregnancy_status' => $isMale ? null : 'open',
                    'health_status' => 'healthy',
                    'status' => 'alive',
                    'current_status' => 'active',
                    'notes' => "Registered into Livestock Master. Location: Maternity Barn. [{$batchTag}]",
                ]);

                AnimalEvent::create([
                    'animal_id' => $animal->id,
                    'event_type' => 'registration',
                    'occurred_at' => now(),
                    'notes' => 'Registered into Livestock Master - Maternity Barn cohort',
                ]);

                Weight::create([
                    'animal_id' => $animal->id,
                    'weighed_at' => now(),
                    'weight_kg' => $weight,
                    'method' => 'scale',
                ]);
            }
        });
    }

    public function down(): void
    {
        $batchTag = 'maternity-barn-cohort-2026';
        $animals = Animal::where('notes', 'like', "%[{$batchTag}]%")->get();
        foreach ($animals as $animal) {
            $animal->delete();
        }
    }
};
