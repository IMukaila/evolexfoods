<?php

use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\Breeding;
use App\Models\Location;
use App\Models\Pregnancy;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $batchTag = 'maternity-barn-cohort-2026-preg';

        if (Pregnancy::where('notes', 'like', "%[{$batchTag}]%")->count() >= 45) {
            return;
        }

        $maternityBarn = Location::where('name', 'Maternity Barn')
            ->orWhere('code', 'BRN-1')
            ->first();

        $existingPregDamIds = Pregnancy::pluck('animal_id')->all();

        // 43 female goats and 2 female sheep from Maternity Barn
        $femaleGoats = Animal::alive()
            ->where('location_id', $maternityBarn?->id)
            ->where('sex', 'female')
            ->where('species', 'goat')
            ->whereNotIn('id', $existingPregDamIds)
            ->orderBy('id')
            ->take(43)
            ->get();

        $femaleSheep = Animal::alive()
            ->where('location_id', $maternityBarn?->id)
            ->where('sex', 'female')
            ->where('species', 'sheep')
            ->whereNotIn('id', $existingPregDamIds)
            ->orderBy('id')
            ->take(2)
            ->get();

        $dams = $femaleGoats->concat($femaleSheep);

        // Fallback if Maternity Barn alone did not reach 45
        if ($dams->count() < 45) {
            $needed = 45 - $dams->count();
            $moreDams = Animal::alive()
                ->where('sex', 'female')
                ->whereNotIn('id', array_merge($existingPregDamIds, $dams->pluck('id')->all()))
                ->orderBy('id')
                ->take($needed)
                ->get();
            $dams = $dams->concat($moreDams);
        }

        $goatSires = Animal::alive()
            ->where('sex', 'male')
            ->where('species', 'goat')
            ->when($maternityBarn, fn ($q) => $q->orderByRaw('location_id = ' . $maternityBarn->id . ' DESC'))
            ->pluck('id')
            ->all();

        $sheepSires = Animal::alive()
            ->where('sex', 'male')
            ->where('species', 'sheep')
            ->when($maternityBarn, fn ($q) => $q->orderByRaw('location_id = ' . $maternityBarn->id . ' DESC'))
            ->pluck('id')
            ->all();

        // 22 due dates in November 2026 and 23 due dates in December 2026
        $expectedDueDates = [
            // November 2026 (22 dates)
            '2026-11-04', '2026-11-06', '2026-11-08', '2026-11-10', '2026-11-12',
            '2026-11-14', '2026-11-15', '2026-11-17', '2026-11-18', '2026-11-20',
            '2026-11-21', '2026-11-22', '2026-11-23', '2026-11-24', '2026-11-25',
            '2026-11-26', '2026-11-27', '2026-11-28', '2026-11-28', '2026-11-29',
            '2026-11-30', '2026-11-30',
            // December 2026 (23 dates)
            '2026-12-02', '2026-12-03', '2026-12-05', '2026-12-06', '2026-12-07',
            '2026-12-09', '2026-12-10', '2026-12-12', '2026-12-13', '2026-12-15',
            '2026-12-16', '2026-12-17', '2026-12-19', '2026-12-20', '2026-12-21',
            '2026-12-22', '2026-12-24', '2026-12-25', '2026-12-26', '2026-12-27',
            '2026-12-28', '2026-12-29', '2026-12-30',
        ];

        DB::transaction(function () use ($dams, $goatSires, $sheepSires, $expectedDueDates, $batchTag) {
            foreach ($dams->values() as $index => $dam) {
                if ($index >= 45) {
                    break;
                }

                $expectedBirthDate = $expectedDueDates[$index];
                $expectedCarbon = Carbon::parse($expectedBirthDate);

                // Normal gestation for goat & sheep is 150 days
                $gestationDays = 150;
                $breedingDate = $expectedCarbon->copy()->subDays($gestationDays)->toDateString();

                // All confirmed dates in July 2026 (spread between 2026-07-02 and 2026-07-31)
                $dayOfJuly = min(31, max(2, (int) round(2 + ($index * 29 / 44))));
                $confirmedDate = sprintf('2026-07-%02d', $dayOfJuly);

                // Select sire
                $sireId = null;
                if ($dam->species === 'sheep' && ! empty($sheepSires)) {
                    $sireId = $sheepSires[$index % count($sheepSires)];
                } elseif (! empty($goatSires)) {
                    $sireId = $goatSires[$index % count($goatSires)];
                }

                // Create Breeding cycle record
                $breeding = Breeding::create([
                    'female_id' => $dam->id,
                    'male_id' => $sireId,
                    'breeding_date' => $breedingDate,
                    'method' => 'Natural service',
                    'status' => 'completed',
                    'notes' => "Seasonal breeding program [{$batchTag}]",
                ]);

                // Create Pregnancy record
                Pregnancy::create([
                    'animal_id' => $dam->id,
                    'breeding_id' => $breeding->id,
                    'confirmed_date' => $confirmedDate,
                    'expected_birth_date' => $expectedBirthDate,
                    'status' => 'active',
                    'notes' => "Confirmed pregnant via ultrasound - expected {$expectedCarbon->format('M Y')} [{$batchTag}]",
                ]);

                // Update Animal record to sync with Livestock Master & Kidding Radar
                $dam->update([
                    'pregnancy_status' => 'pregnant',
                    'expected_birth_date' => $expectedBirthDate,
                    'breeding_status' => 'mated',
                ]);

                // Record animal event
                AnimalEvent::create([
                    'animal_id' => $dam->id,
                    'event_type' => 'pregnancy_confirmed',
                    'occurred_at' => Carbon::parse($confirmedDate)->setTime(9, 0),
                    'payload' => [
                        'due_date' => $expectedBirthDate,
                        'method' => 'ultrasound',
                        'notes' => 'Pregnancy confirmed in July 2026',
                    ],
                    'notes' => "Confirmed pregnancy; expected {$expectedBirthDate} [{$batchTag}]",
                ]);
            }
        });
    }

    public function down(): void
    {
        $batchTag = 'maternity-barn-cohort-2026-preg';

        $pregnancies = Pregnancy::where('notes', 'like', "%[{$batchTag}]%")->get();
        $damIds = $pregnancies->pluck('animal_id')->all();
        $breedingIds = $pregnancies->pluck('breeding_id')->filter()->all();

        Pregnancy::whereIn('id', $pregnancies->pluck('id'))->delete();
        Breeding::whereIn('id', $breedingIds)->delete();
        AnimalEvent::where('notes', 'like', "%[{$batchTag}]%")->delete();

        Animal::whereIn('id', $damIds)->update([
            'pregnancy_status' => 'open',
            'expected_birth_date' => null,
            'breeding_status' => null,
        ]);
    }
};
