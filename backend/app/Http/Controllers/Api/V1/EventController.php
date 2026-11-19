<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\AuditLog;
use App\Models\Birth;
use App\Models\Death;
use App\Models\HealthRecord;
use App\Models\PurchaseRequest;
use App\Models\Treatment;
use App\Services\BirthRegistrationService;
use App\Services\ProcurementApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    /**
     * Birth lifecycle automation (§96): validates the dam, auto-generates an
     * EV-GT / EV-SH / EV-CT ID per kid, creates the animal + birth records,
     * closes the dam's pregnancy and returns her to open breeding status.
     *
     * Accepts a litter via `kids: [{sex, birth_weight}]`, or the legacy
     * single-kid shape (`sex`, `birth_weight` at the top level).
     */
    public function birth(Request $request, BirthRegistrationService $births): JsonResponse
    {
        $data = $request->validate([
            'mother_animal_id' => 'required|string',
            'father_animal_id' => 'nullable|string',
            'species' => 'nullable|string',
            'breed' => 'nullable|string',
            'birth_date' => 'nullable|date|before_or_equal:today',
            'birth_weight' => 'nullable|numeric|min:0',
            'sex' => 'nullable|in:female,male',
            'location_id' => 'nullable|exists:locations,id',
            'notes' => 'nullable|string',
            'kids' => 'nullable|array|min:1|max:12',
            'kids.*.sex' => 'required_with:kids|in:female,male',
            'kids.*.birth_weight' => 'nullable|numeric|min:0',
            'kids.*.breed' => 'nullable|string',
            'kids.*.notes' => 'nullable|string',
        ]);

        $kids = $data['kids'] ?? null;
        if (! $kids) {
            if (empty($data['sex'])) {
                throw ValidationException::withMessages([
                    'sex' => 'Record the kid\'s sex, or submit a `kids` list for a litter.',
                ]);
            }
            $kids = [[
                'sex' => $data['sex'],
                'birth_weight' => $data['birth_weight'] ?? null,
                'breed' => $data['breed'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]];
        }

        $result = $births->register([
            'mother_animal_id' => $data['mother_animal_id'],
            'father_animal_id' => $data['father_animal_id'] ?? null,
            'species' => $data['species'] ?? null,
            'breed' => $data['breed'] ?? null,
            'birth_date' => $data['birth_date'] ?? now()->toDateString(),
            'location_id' => $data['location_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'kids' => $kids,
        ], $request->user()?->id);

        return response()->json([
            'dam' => $result['dam'],
            'animals' => $result['animals'],
            'births' => $result['births'],
            'litter_size' => $result['litter_size'],
            'pregnancy_closed' => $result['pregnancy_closed'],
            // Legacy single-birth consumers continue to work.
            'animal' => $result['animals'][0] ?? null,
            'birth' => $result['births'][0] ?? null,
        ], 201);
    }

    public function death(Request $request): JsonResponse
    {
        $data = $request->validate([
            'animal_id' => 'required|string',
            'cause' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $animal = Animal::where('animal_id', $data['animal_id'])->firstOrFail();
        $animal->update(['status' => 'dead', 'current_status' => 'inactive', 'health_status' => 'dead']);

        $death = Death::create([
            'animal_id' => $animal->id,
            'death_date' => now()->toDateString(),
            'cause' => $data['cause'] ?? 'unknown',
            'location_id' => $animal->location_id,
            'notes' => $data['notes'] ?? null,
        ]);

        Alert::create([
            'type' => 'animal_death',
            'severity' => 'critical',
            'title' => 'Animal death recorded',
            'message' => $animal->animal_id.' recorded as dead.',
            'module' => 'livestock',
            'triggered_at' => now(),
        ]);

        $this->logEvent($animal->id, 'death', $request, ['death_id' => $death->id]);

        return response()->json($death, 201);
    }

    public function sick(Request $request): JsonResponse
    {
        $data = $request->validate([
            'animal_id' => 'required|string',
            'condition' => 'required|string',
            'severity' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $animal = Animal::where('animal_id', $data['animal_id'])->firstOrFail();
        $animal->update(['health_status' => 'sick']);

        $record = HealthRecord::create([
            'animal_id' => $animal->id,
            'recorded_date' => now()->toDateString(),
            'condition' => $data['condition'],
            'severity' => $data['severity'] ?? 'moderate',
            'status' => 'open',
            'recorded_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);

        Alert::create([
            'type' => 'sick_animal',
            'severity' => 'warning',
            'title' => 'Sick animal reported',
            'message' => $animal->animal_id.': '.$data['condition'],
            'module' => 'health',
            'triggered_at' => now(),
        ]);

        $this->logEvent($animal->id, 'sick', $request, ['health_record_id' => $record->id]);

        return response()->json($record, 201);
    }

    public function treatment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'animal_id' => 'required|string',
            'treatment_type' => 'required|string',
            'medicine' => 'nullable|string',
            'dosage' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $animal = Animal::where('animal_id', $data['animal_id'])->firstOrFail();

        $treatment = Treatment::create([
            'animal_id' => $animal->id,
            'start_date' => now()->toDateString(),
            'treatment_type' => $data['treatment_type'],
            'medicine' => $data['medicine'] ?? null,
            'dosage' => $data['dosage'] ?? null,
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logEvent($animal->id, 'treatment', $request, ['treatment_id' => $treatment->id]);

        return response()->json($treatment, 201);
    }

    public function purchaseRequest(Request $request, ProcurementApprovalService $matrix): JsonResponse
    {
        $data = $request->validate([
            'item_name' => 'required|string',
            'category' => 'nullable|string',
            'quantity' => 'required|numeric',
            'unit' => 'nullable|string',
            'estimated_cost' => 'nullable|numeric',
            'priority' => 'nullable|string',
            'justification' => 'nullable|string',
        ]);

        $count = PurchaseRequest::count() + 1;
        // Two-tier routing (§44–47): > GH₵1,000 goes straight to Executive.
        $routing = $matrix->routingFor($data['estimated_cost'] ?? null);
        $pr = PurchaseRequest::create([
            'request_number' => 'PR-'.now()->format('Y').'-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT),
            'requested_by' => $request->user()->id,
            'requested_at' => now()->toDateString(),
            'item_name' => $data['item_name'],
            'category' => $data['category'] ?? null,
            'quantity' => $data['quantity'],
            'unit' => $data['unit'] ?? null,
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'pending',
            'justification' => $data['justification'] ?? null,
            ...$routing,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'purchase.requested',
            'auditable_type' => PurchaseRequest::class,
            'auditable_id' => $pr->id,
            'meta' => ['request_number' => $pr->request_number],
            'ip_address' => $request->ip(),
        ]);

        return response()->json($pr, 201);
    }

    public function locations(): JsonResponse
    {
        return response()->json(\App\Models\Location::whereIn('type', ['paddock', 'barn', 'pen'])->orderBy('name')->get());
    }

    private function logEvent(int $animalId, string $type, Request $request, array $payload): void
    {
        AnimalEvent::create([
            'animal_id' => $animalId,
            'event_type' => $type,
            'occurred_at' => now(),
            'recorded_by' => $request->user()->id,
            'payload' => $payload,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'animal.'.$type,
            'auditable_type' => Animal::class,
            'auditable_id' => $animalId,
            'meta' => $payload,
            'ip_address' => $request->ip(),
        ]);
    }
}
