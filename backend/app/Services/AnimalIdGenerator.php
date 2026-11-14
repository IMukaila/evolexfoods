<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\AnimalIdSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Automated Animal ID sequencing (§10, §96).
 *
 * IDs follow EV-{species code}-{zero-padded sequence}, e.g. EV-GT-000001
 * (goats), EV-SH-000001 (sheep), EV-CT-000001 (cattle). The sequence lives in
 * the `animal_id_sequences` table and is advanced inside a row-locked
 * transaction, so:
 *
 *  - concurrent birth/registration requests can never receive the same ID;
 *  - an ID is never reused — deleting an animal does not rewind the counter
 *    (the previous `count() + 1` logic failed on both counts);
 *  - herds registered before this service existed are respected: the counter
 *    is reconciled against the highest ID already present in `animals`.
 */
class AnimalIdGenerator
{
    /** Normalise free-text species input to a canonical key. */
    public static function normaliseSpecies(?string $species): string
    {
        $raw = strtolower(trim((string) $species));

        if (str_contains($raw, 'goat') || str_contains($raw, 'ev-gt') || $raw === 'gt') {
            return 'goat';
        }

        if (str_contains($raw, 'sheep') || str_contains($raw, 'ev-sh') || $raw === 'sh') {
            return 'sheep';
        }

        if (str_contains($raw, 'catt') || str_contains($raw, 'cow') || str_contains($raw, 'bovin') || str_contains($raw, 'ev-ct') || $raw === 'ct') {
            return 'cattle';
        }

        return match ($raw) {
            'goats' => 'goat',
            'sheeps' => 'sheep',
            'cow', 'cows', 'bovine' => 'cattle',
            default => $raw,
        };
    }

    public static function supports(?string $species): bool
    {
        return isset(config('evolex.animal_id.species')[self::normaliseSpecies($species)]);
    }

    /** EV-GT / EV-SH / EV-CT for a species, or a validation error. */
    public static function prefixFor(?string $species): string
    {
        $key = self::normaliseSpecies($species);
        $map = config('evolex.animal_id.species');

        if (! isset($map[$key])) {
            throw ValidationException::withMessages([
                'species' => 'Animal IDs can only be issued for goat (EV-GT), sheep (EV-SH) or cattle (EV-CT). Received: '.($species ?: 'unknown').'.',
            ]);
        }

        return config('evolex.animal_id.prefix_base').'-'.$map[$key]['code'];
    }

    public static function format(string $prefix, int $number): string
    {
        return $prefix.'-'.str_pad((string) $number, (int) config('evolex.animal_id.sequence_width', 6), '0', STR_PAD_LEFT);
    }

    /**
     * The ID that *would* be issued next, without consuming it — used to
     * preview the auto-generated ID on registration/birth forms.
     */
    public function peek(string $species): string
    {
        $species = self::normaliseSpecies($species);
        $prefix = self::prefixFor($species);

        $stored = (int) (AnimalIdSequence::where('prefix', $prefix)->value('last_number') ?? 0);

        return self::format($prefix, max($stored, $this->highestIssuedNumber($prefix)) + 1);
    }

    /**
     * Atomically issue the next ID for a species. Must be called when a new
     * animal record is actually being created; the number is consumed even
     * if the surrounding work later rolls back only when this call runs in
     * the same transaction — callers wrap registration in DB::transaction.
     */
    public function next(string $species): string
    {
        $species = self::normaliseSpecies($species);
        $prefix = self::prefixFor($species);

        return DB::transaction(function () use ($species, $prefix) {
            $sequence = AnimalIdSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['prefix' => $prefix],
                    ['species' => $species, 'last_number' => 0]
                );

            // Reconcile with IDs issued before the sequence table existed
            // (seeded / imported herds) so we never collide with them.
            $next = max((int) $sequence->last_number, $this->highestIssuedNumber($prefix)) + 1;

            $sequence->forceFill(['species' => $species, 'last_number' => $next])->save();

            return self::format($prefix, $next);
        });
    }

    /** Highest numeric suffix currently present on animal records. */
    private function highestIssuedNumber(string $prefix): int
    {
        $ids = Animal::query()
            ->where('animal_id', 'like', $prefix.'-%')
            ->pluck('animal_id');

        $highest = 0;
        foreach ($ids as $id) {
            $suffix = substr((string) $id, strlen($prefix) + 1);
            if (is_numeric($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $highest;
    }

    /**
     * Record an issued or manual Animal ID so the sequence counter stays
     * monotonically increasing and never collides or rewinds.
     */
    public function recordIssued(string $species, string $animalId): void
    {
        $species = self::normaliseSpecies($species);
        if (! self::supports($species)) {
            return;
        }

        $prefix = self::prefixFor($species);
        if (! str_starts_with($animalId, $prefix.'-')) {
            return;
        }

        $suffix = substr($animalId, strlen($prefix) + 1);
        if (! is_numeric($suffix)) {
            return;
        }

        $number = (int) $suffix;

        DB::transaction(function () use ($species, $prefix, $number) {
            $sequence = AnimalIdSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['prefix' => $prefix],
                    ['species' => $species, 'last_number' => 0]
                );

            if ($number > (int) $sequence->last_number) {
                $sequence->forceFill(['species' => $species, 'last_number' => $number])->save();
            }
        });
    }
}
