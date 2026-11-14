<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Asset;
use App\Models\Breeding;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\ChannelMetric;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentItem;
use App\Models\Deal;
use App\Models\Decision;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\HealthRecord;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\MaintenanceTicket;
use App\Models\MediaAsset;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\Pregnancy;
use App\Models\PurchaseRequest;
use App\Models\Risk;
use App\Models\SalesCull;
use App\Models\Sop;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\Treatment;
use App\Models\User;
use Database\Seeders\PipelineStageSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CrudController extends Controller
{
    public function index(Request $request, string $resource): JsonResponse
    {
        $config = $this->config($resource);
        $query = $config['model']::query();

        if (! empty($config['with'])) {
            $query->with($config['with']);
        }

        if ($request->filled('q') && ! empty($config['search'])) {
            $term = $request->query('q');
            $query->where(function ($builder) use ($config, $term) {
                foreach ($config['search'] as $i => $column) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $builder->{$method}($column, 'like', "%{$term}%");
                }
            });
        }

        $rows = $query->orderByDesc('id')->get();

        return response()->json(['data' => $rows]);
    }

    public function show(string $resource, int $id): JsonResponse
    {
        $config = $this->config($resource);
        $query = $config['model']::query();
        if (! empty($config['with'])) {
            $query->with($config['with']);
        }

        return response()->json($query->findOrFail($id));
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $config = $this->config($resource);
        $rules = $config['rules'];
        if ($resource === 'livestock') {
            $rules['animal_id'] = 'nullable|string|max:40|unique:animals,animal_id';
        }
        $data = $request->validate($rules);
        $data = $this->beforeSave($resource, $data, $request, null);
        $record = $config['model']::create($data);
        $this->audit($request, $resource.'.created', $record);

        if ($resource === 'payments') {
            $this->syncInvoicePaymentStatus((int) $record->invoice_id);
        }

        if (! empty($config['with'])) {
            $record->load($config['with']);
        }

        return response()->json($record, 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $config = $this->config($resource);
        $record = $config['model']::findOrFail($id);
        $previousInvoiceId = $resource === 'payments' ? (int) $record->invoice_id : null;
        $rules = $config['rules'];
        if ($resource === 'livestock') {
            $rules['animal_id'] = 'nullable|string|max:40|unique:animals,animal_id,'.$id;
        }
        $data = $request->validate($rules);
        $data = $this->beforeSave($resource, $data, $request, $record);
        $record->update($data);
        $this->audit($request, $resource.'.updated', $record);

        if ($resource === 'payments') {
            $this->syncInvoicePaymentStatus($previousInvoiceId);
            $this->syncInvoicePaymentStatus((int) $record->invoice_id);
        } elseif ($resource === 'invoices' && Payment::where('invoice_id', $record->id)->exists()) {
            $this->syncInvoicePaymentStatus((int) $record->id);
        }

        if (! empty($config['with'])) {
            $record->load($config['with']);
        }

        return response()->json($record->fresh($config['with'] ?? []));
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $config = $this->config($resource);
        $record = $config['model']::findOrFail($id);
        $invoiceId = $resource === 'payments' ? (int) $record->invoice_id : null;

        if ($resource === 'invoices' && Payment::where('invoice_id', $record->id)->exists()) {
            return response()->json([
                'message' => 'This invoice has a payment history and cannot be deleted. Keep it for accounting records.',
            ], 409);
        }

        $this->audit($request, $resource.'.deleted', $record);

        try {
            $record->delete();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'This record has related data and cannot be deleted until those records are removed.',
            ], 409);
        }

        if ($resource === 'payments') {
            $this->syncInvoicePaymentStatus($invoiceId);
        }

        return response()->json(['deleted' => true]);
    }

    public function lookups(): JsonResponse
    {
        if (! PipelineStage::exists()) {
            app(PipelineStageSeeder::class)->run();
        }

        return response()->json([
            'locations' => Location::orderBy('name')->get(['id', 'name', 'code']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'role']),
            'employees' => Employee::orderBy('name')->get(['id', 'name', 'employee_code']),
            'animals' => Animal::orderBy('animal_id')->get(['id', 'animal_id', 'species', 'sex', 'status']),
            'assets' => Asset::orderBy('asset_id')->get(['id', 'asset_id', 'name']),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::with('company:id,name')->orderBy('name')->get(['id', 'name', 'company_id']),
            'pipeline_stages' => PipelineStage::whereNotIn('name', ['Qualified', 'Meeting', 'Sample', 'Quote'])
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'media_assets' => MediaAsset::orderByDesc('captured_at')->get(['id', 'title', 'type', 'captured_at']),
            'invoices' => Invoice::orderByDesc('invoice_date')->get(['id', 'invoice_number']),
        ]);
    }

    private function beforeSave(string $resource, array $data, Request $request, ?Model $existing): array
    {
        if ($resource === 'livestock') {
            $data['status'] = $data['status'] ?? 'alive';
            $data['current_status'] = $data['current_status'] ?? 'active';
            $data['health_status'] = $data['health_status'] ?? 'healthy';

            if (empty($data['animal_id'])) {
                // Automated Animal ID sequencing (§10) — sequence-table backed,
                // never reuses an ID (replaces the old count()+1 allocation).
                $data['animal_id'] = app(\App\Services\AnimalIdGenerator::class)->next($data['species'] ?? 'goat');
            } else {
                app(\App\Services\AnimalIdGenerator::class)->recordIssued($data['species'] ?? 'goat', $data['animal_id']);
            }
        }

        if ($resource === 'maintenance') {
            $data['reported_by'] = $data['reported_by'] ?? $request->user()?->id;
            $data['status'] = $data['status'] ?? 'open';
            $data['type'] = $data['type'] ?? 'corrective';
        }

        if ($resource === 'channel_metrics') {
            foreach (['reach', 'views', 'followers', 'website_visits', 'leads', 'engagement_rate'] as $metric) {
                $data[$metric] = $data[$metric] ?? 0;
            }
        }

        if ($resource === 'procurement') {
            // Two-tier approval routing (§44–47) on every creation path.
            $routing = app(\App\Services\ProcurementApprovalService::class)
                ->routingFor($data['estimated_cost'] ?? ($existing?->estimated_cost ?? null));
            $data['approval_tier'] = $data['approval_tier'] ?? $routing['approval_tier'];
            $data['approval_stage'] = $data['approval_stage'] ?? $routing['approval_stage'];
            $data['required_approver_role'] = $data['required_approver_role'] ?? $routing['required_approver_role'];
            if ($existing && ($data['status'] ?? $existing->status) !== 'pending') {
                // A decided/converted request keeps its terminal stage.
                $data['approval_stage'] = match ($data['status'] ?? $existing->status) {
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    'ordered', 'converted' => 'ordered',
                    default => $data['approval_stage'],
                };
            }
        }

        if ($resource === 'assets' && empty($data['asset_id'])) {
            $next = Asset::count() + 1;
            $data['asset_id'] = 'EV-AS-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        }

        if ($resource === 'inventory' && empty($data['item_id'])) {
            $next = InventoryItem::count() + 1;
            $data['item_id'] = 'INV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        }

        if ($resource === 'suppliers' && empty($data['code'])) {
            $next = Supplier::count() + 1;
            $data['code'] = 'SUP-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
        }

        if ($resource === 'employees' && empty($data['employee_code'])) {
            $next = Employee::count() + 1;
            $data['employee_code'] = 'EV-EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }

        if ($resource === 'procurement' && empty($data['request_number'])) {
            $next = PurchaseRequest::count() + 1;
            $data['request_number'] = 'PR-'.now()->format('Y').'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $data['requested_at'] = $data['requested_at'] ?? now()->toDateString();
        }

        if ($resource === 'maintenance' && empty($data['ticket_number'])) {
            $next = MaintenanceTicket::count() + 1;
            $data['ticket_number'] = 'MT-'.now()->format('Y').'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $data['reported_at'] = $data['reported_at'] ?? now();
        }

        if ($resource === 'risks') {
            $data['identified_at'] = $data['identified_at'] ?? now()->toDateString();
            $data['status'] = $data['status'] ?? 'open';
            $data['severity'] = $data['severity'] ?? 'medium';
            $data['likelihood'] = $data['likelihood'] ?? 'possible';
        }

        if ($resource === 'decisions') {
            $data['requested_by'] = $data['requested_by'] ?? $request->user()?->id;
            $data['status'] = $data['status'] ?? 'pending_owner';
            if (in_array($data['status'] ?? '', ['approved', 'rejected']) && empty($data['decided_at'])) {
                $data['decided_at'] = now();
            }
        }

        return $data;
    }

    private function audit(Request $request, string $action, Model $record): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $record::class,
            'auditable_id' => $record->getKey(),
            'meta' => ['resource' => class_basename($record)],
            'ip_address' => $request->ip(),
        ]);
    }

    private function syncInvoicePaymentStatus(?int $invoiceId): void
    {
        if (! $invoiceId) {
            return;
        }

        $invoice = Invoice::find($invoiceId);
        if (! $invoice) {
            return;
        }

        $paid = (float) Payment::where('invoice_id', $invoiceId)->sum('amount');
        $amount = (float) $invoice->amount;
        $status = $paid <= 0
            ? 'unpaid'
            : ($paid + 0.005 >= $amount ? 'paid' : 'partial');

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }

    private function config(string $resource): array
    {
        return match ($resource) {
            'livestock' => [
                'model' => Animal::class,
                'with' => ['location'],
                'search' => ['animal_id', 'tag_id', 'species', 'breed'],
                'rules' => [
                    'animal_id' => 'nullable|string|max:40',
                    'tag_id' => 'nullable|string|max:40',
                    'species' => 'required|string',
                    'breed' => 'nullable|string',
                    'sex' => 'required|in:female,male',
                    'dob' => 'nullable|date',
                    'mother_id' => 'nullable|string',
                    'father_id' => 'nullable|string',
                    'source' => 'nullable|string',
                    'cost' => 'nullable|numeric',
                    'location_id' => 'nullable|exists:locations,id',
                    'weight' => 'nullable|numeric',
                    'breeding_status' => 'nullable|string',
                    'pregnancy_status' => 'nullable|string',
                    'expected_birth_date' => 'nullable|date',
                    'health_status' => 'nullable|string',
                    'status' => 'nullable|string',
                    'current_status' => 'nullable|string',
                    'notes' => 'nullable|string',
                ],
            ],
            'breeding' => [
                'model' => Breeding::class,
                'with' => ['female', 'male'],
                'search' => ['method', 'status', 'notes'],
                'rules' => [
                    'female_id' => 'required|exists:animals,id',
                    'male_id' => 'nullable|exists:animals,id',
                    'breeding_date' => 'required|date',
                    'method' => 'nullable|string',
                    'status' => 'nullable|string',
                    'notes' => 'nullable|string',
                ],
            ],
            'pregnancies' => [
                'model' => Pregnancy::class,
                'with' => ['animal.location', 'breeding.male'],
                'search' => ['status', 'notes'],
                'rules' => [
                    'animal_id' => 'required|exists:animals,id',
                    'breeding_id' => 'nullable|exists:breedings,id',
                    'confirmed_date' => 'required|date',
                    'expected_birth_date' => 'nullable|date',
                    'status' => 'nullable|string',
                    'ended_at' => 'nullable|date',
                    'notes' => 'nullable|string',
                ],
            ],
            'health' => [
                'model' => HealthRecord::class,
                'with' => ['animal'],
                'search' => ['condition', 'severity', 'status'],
                'rules' => [
                    'animal_id' => 'required|exists:animals,id',
                    'recorded_date' => 'required|date',
                    'condition' => 'required|string',
                    'severity' => 'nullable|string',
                    'status' => 'nullable|string',
                    'notes' => 'nullable|string',
                ],
            ],
            'treatments' => [
                'model' => Treatment::class,
                'with' => ['animal'],
                'search' => ['treatment_type', 'medicine', 'status'],
                'rules' => [
                    'animal_id' => 'required|exists:animals,id',
                    'start_date' => 'required|date',
                    'end_date' => 'nullable|date',
                    'treatment_type' => 'required|string',
                    'medicine' => 'nullable|string',
                    'dosage' => 'nullable|string',
                    'status' => 'nullable|string',
                    'notes' => 'nullable|string',
                ],
            ],
            'inventory' => [
                'model' => InventoryItem::class,
                'with' => ['supplier'],
                'search' => ['item_id', 'name', 'category'],
                'rules' => [
                    'item_id' => 'nullable|string',
                    'name' => 'required|string',
                    'category' => 'required|string',
                    'unit' => 'nullable|string',
                    'storage_location' => 'nullable|string',
                    'quantity_on_hand' => 'nullable|numeric',
                    'average_cost' => 'nullable|numeric',
                    'supplier_id' => 'nullable|exists:suppliers,id',
                    'minimum_stock' => 'nullable|numeric',
                    'reorder_level' => 'nullable|numeric',
                    'maximum_level' => 'nullable|numeric',
                    'expiry_date' => 'nullable|date',
                    'status' => 'nullable|string',
                ],
            ],
            'suppliers' => [
                'model' => Supplier::class,
                'with' => [],
                'search' => ['code', 'name', 'category', 'email'],
                'rules' => [
                    'code' => 'nullable|string',
                    'name' => 'required|string',
                    'category' => 'nullable|string',
                    'contact_name' => 'nullable|string',
                    'phone' => 'nullable|string',
                    'email' => 'nullable|email',
                    'location' => 'nullable|string',
                    'rating' => 'nullable|numeric',
                    'status' => 'nullable|string',
                    'notes' => 'nullable|string',
                ],
            ],
            'procurement' => [
                'model' => PurchaseRequest::class,
                'with' => ['requester', 'approvals.approver'],
                'search' => ['request_number', 'item_name', 'status'],
                'rules' => [
                    'request_number' => 'nullable|string',
                    'requested_by' => 'nullable|exists:users,id',
                    'requested_at' => 'nullable|date',
                    'item_name' => 'required|string',
                    'category' => 'nullable|string',
                    'quantity' => 'required|numeric',
                    'unit' => 'nullable|string',
                    'estimated_cost' => 'nullable|numeric',
                    'priority' => 'nullable|string',
                    'status' => 'nullable|string',
                    'justification' => 'nullable|string',
                ],
            ],
            'assets' => [
                'model' => Asset::class,
                'with' => ['location', 'supplier'],
                'search' => ['asset_id', 'name', 'category', 'serial_number'],
                'rules' => [
                    'asset_id' => 'nullable|string',
                    'name' => 'required|string',
                    'category' => 'nullable|string',
                    'serial_number' => 'nullable|string',
                    'purchase_date' => 'nullable|date',
                    'purchase_price' => 'nullable|numeric',
                    'supplier_id' => 'nullable|exists:suppliers,id',
                    'location_id' => 'nullable|exists:locations,id',
                    'assigned_employee_id' => 'nullable|exists:employees,id',
                    'condition' => 'nullable|string',
                    'warranty_until' => 'nullable|date',
                    'status' => 'nullable|string',
                ],
            ],
            'maintenance' => [
                'model' => MaintenanceTicket::class,
                'with' => ['asset', 'assignee', 'reporter'],
                'search' => ['ticket_number', 'title', 'status', 'priority'],
                'rules' => [
                    'ticket_number' => 'nullable|string',
                    'asset_id' => 'nullable|exists:assets,id',
                    'title' => 'required|string',
                    'type' => 'nullable|string',
                    'priority' => 'nullable|string',
                    'status' => 'nullable|string',
                    'reported_by' => 'nullable|exists:users,id',
                    'assigned_to' => 'nullable|exists:users,id',
                    'reported_at' => 'nullable|date',
                    'description' => 'nullable|string',
                ],
            ],
            'employees' => [
                'model' => Employee::class,
                'with' => ['user'],
                'search' => ['employee_code', 'name', 'role', 'department', 'email'],
                'rules' => [
                    'employee_code' => 'nullable|string',
                    'user_id' => 'nullable|exists:users,id',
                    'name' => 'required|string',
                    'role' => 'nullable|string',
                    'department' => 'nullable|string',
                    'phone' => 'nullable|string',
                    'email' => 'nullable|email',
                    'hire_date' => 'nullable|date',
                    'status' => 'nullable|string',
                ],
            ],
            'tasks' => [
                'model' => Task::class,
                'with' => ['assignee', 'creator'],
                'search' => ['title', 'status', 'module', 'priority'],
                'rules' => [
                    'title' => 'required|string',
                    'description' => 'nullable|string',
                    'assigned_to' => 'nullable|exists:users,id',
                    'due_date' => 'nullable|date',
                    'priority' => 'nullable|string',
                    'status' => 'nullable|string',
                    'module' => 'nullable|string',
                ],
            ],
            'expenses' => [
                'model' => Expense::class,
                'with' => [],
                'search' => ['category', 'description'],
                'rules' => [
                    'category' => 'required|string',
                    'description' => 'nullable|string',
                    'amount' => 'required|numeric',
                    'expense_date' => 'required|date',
                    'source' => 'nullable|string',
                ],
            ],
            'invoices' => [
                'model' => Invoice::class,
                'with' => ['supplier', 'payments'],
                'search' => ['invoice_number', 'status'],
                'rules' => [
                    'invoice_number' => 'required|string',
                    'supplier_id' => 'nullable|exists:suppliers,id',
                    'amount' => 'required|numeric',
                    'invoice_date' => 'required|date',
                    'due_date' => 'nullable|date',
                    'status' => 'nullable|in:unpaid,partial,paid',
                ],
            ],
            'payments' => [
                'model' => Payment::class,
                'with' => ['invoice.supplier'],
                'search' => ['reference', 'method'],
                'rules' => [
                    'invoice_id' => 'required|exists:invoices,id',
                    'amount' => 'required|numeric|min:0.01',
                    'paid_at' => 'required|date',
                    'method' => 'nullable|string',
                    'reference' => 'nullable|string',
                ],
            ],
            'companies' => [
                'model' => Company::class,
                'with' => [],
                'search' => ['name', 'category', 'lifecycle_stage', 'city'],
                'rules' => [
                    'name' => 'required|string',
                    'category' => 'nullable|string',
                    'lifecycle_stage' => 'nullable|string',
                    'city' => 'nullable|string',
                    'phone' => 'nullable|string',
                    'email' => 'nullable|email',
                    'status' => 'nullable|string',
                ],
            ],
            'contacts' => [
                'model' => Contact::class,
                'with' => ['company'],
                'search' => ['name', 'company_name', 'email', 'phone', 'lifecycle_stage'],
                'rules' => [
                    'company_id' => 'nullable|exists:companies,id',
                    'company_name' => 'nullable|string|max:255',
                    'name' => 'required|string',
                    'email' => 'nullable|email',
                    'phone' => 'nullable|string',
                    'role' => 'nullable|string',
                    'lifecycle_stage' => 'nullable|string',
                ],
            ],
            'deals' => [
                'model' => Deal::class,
                'with' => ['company', 'contact', 'stage'],
                'search' => ['name', 'company_name', 'contact_name', 'status', 'next_action'],
                'rules' => [
                    'name' => 'required|string',
                    'company_name' => 'nullable|string|max:255',
                    'contact_name' => 'nullable|string|max:255',
                    'company_id' => 'nullable|exists:companies,id',
                    'contact_id' => 'nullable|exists:contacts,id',
                    'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
                    'amount' => 'nullable|numeric|min:0',
                    'close_date' => 'nullable|date',
                    'next_action' => 'nullable|string',
                    'next_action_date' => 'nullable|date',
                    'status' => 'nullable|in:open,won,lost',
                ],
            ],
            'sales' => [
                'model' => SalesCull::class,
                'with' => ['animal'],
                'search' => ['type', 'buyer'],
                'rules' => [
                    'animal_id' => 'required|exists:animals,id',
                    'type' => 'required|in:sale,cull',
                    'event_date' => 'required|date',
                    'amount' => 'nullable|numeric|min:0',
                    'buyer' => 'nullable|string',
                    'notes' => 'nullable|string',
                ],
            ],
            'marketing' => [
                'model' => ContentItem::class,
                'with' => ['media'],
                'search' => ['title', 'channel', 'content_type', 'status'],
                'rules' => [
                    'title' => 'required|string',
                    'channel' => 'nullable|string',
                    'content_type' => 'nullable|string',
                    'status' => 'nullable|string',
                    'scheduled_for' => 'nullable|date',
                    'published_at' => 'nullable|date',
                    'media_asset_id' => 'nullable|exists:media_assets,id',
                    'body' => 'nullable|string',
                ],
            ],
            'media_assets' => [
                'model' => MediaAsset::class,
                'with' => [],
                'search' => ['title', 'type', 'subject', 'location', 'rights'],
                'rules' => [
                    'title' => 'required|string|max:255',
                    'type' => 'nullable|string|max:50',
                    'location' => 'nullable|string|max:255',
                    'subject' => 'nullable|string|max:255',
                    'url' => 'nullable|string|max:2048',
                    'tags' => 'nullable|array',
                    'tags.*' => 'string|max:100',
                    'captured_at' => 'nullable|date',
                    'rights' => 'nullable|string|max:255',
                ],
            ],
            'campaigns' => [
                'model' => Campaign::class,
                'with' => [],
                'search' => ['name', 'channel', 'status'],
                'rules' => [
                    'name' => 'required|string',
                    'channel' => 'nullable|string',
                    'start_date' => 'nullable|date',
                    'end_date' => 'nullable|date',
                    'status' => 'nullable|in:draft,active,paused,completed',
                    'budget' => 'nullable|numeric|min:0',
                ],
            ],
            'channel_metrics' => [
                'model' => ChannelMetric::class,
                'with' => [],
                'search' => ['channel'],
                'rules' => [
                    'channel' => 'required|string',
                    'metric_date' => 'required|date',
                    'reach' => 'nullable|integer|min:0',
                    'views' => 'nullable|integer|min:0',
                    'followers' => 'nullable|integer|min:0',
                    'website_visits' => 'nullable|integer|min:0',
                    'leads' => 'nullable|integer|min:0',
                    'engagement_rate' => 'nullable|numeric|min:0|max:100',
                ],
            ],
            'risks' => [
                'model' => Risk::class,
                'with' => ['owner'],
                'search' => ['title', 'category', 'severity', 'status'],
                'rules' => [
                    'title' => 'required|string',
                    'category' => 'nullable|string',
                    'severity' => 'nullable|string',
                    'likelihood' => 'nullable|string',
                    'owner_id' => 'nullable|exists:users,id',
                    'status' => 'nullable|string',
                    'mitigation' => 'nullable|string',
                    'identified_at' => 'nullable|date',
                ],
            ],
            'decisions' => [
                'model' => Decision::class,
                'with' => ['requester', 'owner'],
                'search' => ['title', 'status'],
                'rules' => [
                    'title' => 'required|string',
                    'description' => 'nullable|string',
                    'status' => 'nullable|string',
                    'requested_by' => 'nullable|exists:users,id',
                    'owner_id' => 'nullable|exists:users,id',
                    'due_date' => 'nullable|date',
                    'decision_notes' => 'nullable|string',
                ],
            ],
            'sops' => [
                'model' => Sop::class,
                'with' => [],
                'search' => ['code', 'title', 'category'],
                'rules' => [
                    'code' => 'required|string',
                    'title' => 'required|string',
                    'category' => 'nullable|string',
                    'version' => 'nullable|string',
                    'status' => 'nullable|string',
                    'summary' => 'nullable|string',
                    'drive_url' => 'nullable|string',
                ],
            ],
            default => throw ValidationException::withMessages([
                'resource' => ['Unknown resource '.$resource],
            ]),
        };
    }
}
