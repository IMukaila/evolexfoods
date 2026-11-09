<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Animal;
use App\Models\AnimalEvent;
use App\Models\Asset;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Birth;
use App\Models\Breeding;
use App\Models\Campaign;
use App\Models\ChannelMetric;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContentItem;
use App\Models\Death;
use App\Models\Deal;
use App\Models\Decision;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\GoodsReceipt;
use App\Models\HealthRecord;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceTicket;
use App\Models\MediaAsset;
use App\Models\MedicineBatch;
use App\Models\Payment;
use App\Models\PipelineStage;
use App\Models\Pregnancy;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestApproval;
use App\Models\Risk;
use App\Models\Sop;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SystemRegistry;
use App\Models\Task;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\Weight;
use App\Models\AiPrompt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);
        $this->call(LocationSeeder::class);

        $users = collect([
            ['name' => 'Inusah Mukaila', 'email' => 'owner@evolex.com', 'role' => 'owner', 'department' => 'Ownership'],
            ['name' => 'Daniel Mensah', 'email' => 'executive@evolex.com', 'role' => 'executive', 'department' => 'Executive'],
            ['name' => 'Nia Boateng', 'email' => 'ops@evolex.com', 'role' => 'operations_manager', 'department' => 'Business Operations'],
            ['name' => 'Kwame Asante', 'email' => 'farm@evolex.com', 'role' => 'farm_manager', 'department' => 'Farm'],
            ['name' => 'Amina Diallo', 'email' => 'finance@evolex.com', 'role' => 'finance', 'department' => 'Finance'],
            ['name' => 'Sofia Ribeiro', 'email' => 'marketing@evolex.com', 'role' => 'marketing', 'department' => 'Marketing'],
            ['name' => 'James Carter', 'email' => 'sales@evolex.com', 'role' => 'sales', 'department' => 'Sales'],
            ['name' => 'Ibrahim Touré', 'email' => 'store@evolex.com', 'role' => 'storekeeper', 'department' => 'Stores'],
            ['name' => 'Dr. Helen Nkrumah', 'email' => 'vet@evolex.com', 'role' => 'vet', 'department' => 'Animal Health'],
            ['name' => 'Kojo Ampofo', 'email' => 'procurement@evolex.com', 'role' => 'procurement', 'department' => 'Procurement'],
            ['name' => 'Yaw Mensah', 'email' => 'worker@evolex.com', 'role' => 'farm_employee', 'department' => 'Farm'],
        ])->map(fn ($row) => User::create([
            ...$row,
            'password' => Hash::make('password'),
            'status' => 'active',
            'phone' => '+23320'.rand(1000000, 9999999),
        ]));

        $owner = $users->firstWhere('email', 'owner@evolex.com');
        $ops = $users->firstWhere('email', 'ops@evolex.com');
        $farm = $users->firstWhere('email', 'farm@evolex.com');
        $finance = $users->firstWhere('email', 'finance@evolex.com');
        $worker = $users->firstWhere('email', 'worker@evolex.com');
        $vet = $users->firstWhere('email', 'vet@evolex.com');
        $proc = $users->firstWhere('email', 'procurement@evolex.com');

        $locations = Location::orderBy('id')->get();

        $employees = $users->map(fn ($user, $i) => Employee::create([
            'user_id' => $user->id,
            'employee_code' => 'EV-EMP-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT),
            'name' => $user->name,
            'role' => $user->role,
            'department' => $user->department,
            'phone' => $user->phone,
            'email' => $user->email,
            'hire_date' => now()->subMonths(rand(4, 36)),
            'status' => 'active',
        ]));

        $speciesMap = [
            ['species' => 'goat', 'prefix' => 'EV-GT', 'breed' => 'West African Dwarf'],
            ['species' => 'sheep', 'prefix' => 'EV-SH', 'breed' => 'Djallonke'],
            ['species' => 'cattle', 'prefix' => 'EV-CT', 'breed' => 'Sanga'],
        ];

        $animals = collect();
        $counters = ['goat' => 1, 'sheep' => 1, 'cattle' => 1];

        foreach (range(1, 48) as $i) {
            $spec = $speciesMap[$i % 3];
            $n = $counters[$spec['species']]++;
            $sex = $i % 3 === 0 ? 'male' : 'female';
            $status = $i <= 45 ? 'alive' : 'dead';
            $health = in_array($i, [7, 19, 31], true) ? 'sick' : 'healthy';
            $preg = $sex === 'female' && in_array($i, [2, 5, 8, 11, 14, 17, 20, 23], true);

            $animal = Animal::create([
                'animal_id' => $spec['prefix'].'-'.str_pad($n, 6, '0', STR_PAD_LEFT),
                'tag_id' => 'TAG-'.str_pad($i, 4, '0', STR_PAD_LEFT),
                'species' => $spec['species'],
                'breed' => $spec['breed'],
                'sex' => $sex,
                'dob' => now()->subMonths(rand(4, 48))->subDays(rand(0, 20)),
                'source' => $i % 5 === 0 ? 'purchased' : 'born_on_farm',
                'cost' => $i % 5 === 0 ? rand(400, 1800) : null,
                'location_id' => $locations[$i % $locations->count()]->id,
                'weight' => rand(18, 320),
                'breeding_status' => $sex === 'female' ? ($preg ? 'bred' : 'open') : 'sire',
                'pregnancy_status' => $preg ? 'pregnant' : ($sex === 'female' ? 'open' : null),
                'expected_birth_date' => $preg ? now()->addDays(rand(8, 80)) : null,
                'health_status' => $health,
                'status' => $status,
                'current_status' => $status === 'alive' ? 'active' : 'inactive',
                'notes' => $preg ? 'Confirmed pregnancy' : null,
            ]);
            $animals->push($animal);

            AnimalEvent::create([
                'animal_id' => $animal->id,
                'event_type' => 'registration',
                'occurred_at' => $animal->created_at,
                'recorded_by' => $farm->id,
                'notes' => 'Registered into Livestock Master',
            ]);

            Weight::create([
                'animal_id' => $animal->id,
                'weighed_at' => now()->subDays(rand(1, 20)),
                'weight_kg' => $animal->weight,
                'method' => 'scale',
            ]);
        }

        $liveFemales = $animals->where('sex', 'female')->where('status', 'alive')->values();
        $liveMales = $animals->where('sex', 'male')->where('status', 'alive')->values();

        // Gestation length drives both the service date behind an active
        // pregnancy and the due date of every new cycle.
        $gestation = fn (string $species): int => match ($species) {
            'cattle' => 283,
            'sheep' => 150,
            default => 150,
        };
        $methods = ['natural', 'natural', 'natural', 'artificial_insemination', 'embryo_transfer'];
        $birthWeight = fn (string $species): float => match ($species) {
            'cattle' => rand(180, 320) / 10,
            'sheep' => rand(15, 45) / 10,
            default => rand(15, 45) / 10,
        };

        // The eight animals flagged pregnant above: their service is dated back
        // one gestation from the expected birth date so the breeding workspace,
        // the animal record and the CEO dashboard all tell the same story.
        foreach ($liveFemales->take(8) as $idx => $female) {
            $male = $liveMales[$idx % $liveMales->count()];
            $due = $female->expected_birth_date ?? now()->addDays(rand(8, 80));
            $length = $gestation($female->species);

            $breeding = Breeding::create([
                'female_id' => $female->id,
                'male_id' => $male->id,
                'breeding_date' => $due->copy()->subDays($length),
                'method' => 'natural',
                'status' => 'completed',
            ]);
            Pregnancy::create([
                'animal_id' => $female->id,
                'breeding_id' => $breeding->id,
                'confirmed_date' => $due->copy()->subDays($length - 21),
                'expected_birth_date' => $due,
                'status' => 'active',
            ]);
        }

        // The breeding programme itself: 16 services spread across the last
        // year. Three out of four took — still gestating services stay active
        // (and update the dam's record), the rest close out with a calf.
        $programmeFemales = $liveFemales->slice(8)->values();
        $calves = $animals->slice(20)->where('status', 'alive')->values();

        foreach (range(0, 15) as $i) {
            $female = $programmeFemales[$i % $programmeFemales->count()];
            $male = $liveMales[($i * 3) % $liveMales->count()];
            $served = now()->subDays(60 + $i * 21);
            $length = $gestation($female->species);
            $conceived = $i % 4 !== 3;
            $due = $served->copy()->addDays($length);
            $calf = $calves->get($i);

            $breeding = Breeding::create([
                'female_id' => $female->id,
                'male_id' => $male->id,
                'breeding_date' => $served->copy(),
                'method' => $methods[$i % count($methods)],
                'status' => 'completed',
            ]);

            Pregnancy::create([
                'animal_id' => $female->id,
                'breeding_id' => $breeding->id,
                'confirmed_date' => $served->copy()->addDays(21),
                'expected_birth_date' => $due->copy(),
                'status' => ! $conceived ? 'failed' : ($due->isFuture() ? 'active' : 'completed'),
                'ended_at' => $conceived && ! $due->isFuture() ? $due->copy() : null,
            ]);

            if (! $conceived) {
                continue;
            }

            if ($due->isFuture()) {
                $female->update([
                    'breeding_status' => 'bred',
                    'pregnancy_status' => 'pregnant',
                    'expected_birth_date' => $due->copy(),
                ]);
                continue;
            }

            if (! $calf) {
                continue;
            }

            Birth::create([
                'animal_id' => $calf->id,
                'mother_animal_id' => $female->animal_id,
                'father_animal_id' => $male->animal_id,
                'birth_date' => $due->copy(),
                'sex' => $calf->sex,
                'birth_weight' => $birthWeight($female->species),
                'location_id' => $locations[3]->id,
                'status' => 'alive',
            ]);
        }

        // Recent arrivals at the maternity barn, so the "last 30 days" tiles and
        // the monthly chart always have something to show.
        foreach (range(1, 9) as $i) {
            $mother = $liveFemales[$i % $liveFemales->count()];
            $child = $animals[$i];
            Birth::create([
                'animal_id' => $child->id,
                'mother_animal_id' => $mother->animal_id,
                'birth_date' => now()->subDays($i * 9),
                'sex' => $child->sex,
                'birth_weight' => $birthWeight($child->species),
                'location_id' => $locations[3]->id,
                'status' => 'alive',
            ]);
        }

        foreach ($animals->where('status', 'dead') as $dead) {
            Death::create([
                'animal_id' => $dead->id,
                'death_date' => now()->subDays(rand(3, 25)),
                'cause' => collect(['pneumonia', 'injury', 'unknown'])->random(),
                'location_id' => $dead->location_id,
            ]);
        }

        foreach ([7, 19, 31] as $i) {
            $animal = $animals[$i - 1];
            $record = HealthRecord::create([
                'animal_id' => $animal->id,
                'recorded_date' => now()->subDays(rand(1, 6)),
                'condition' => collect(['mastitis', 'lameness', 'respiratory'])->random(),
                'severity' => 'moderate',
                'status' => 'open',
                'recorded_by' => $vet->id,
            ]);
            Treatment::create([
                'animal_id' => $animal->id,
                'health_record_id' => $record->id,
                'start_date' => now()->subDays(2),
                'treatment_type' => 'medication',
                'medicine' => 'Oxytetracycline',
                'dosage' => '10ml',
                'status' => 'active',
            ]);
        }

        foreach ($animals->where('status', 'alive')->take(20) as $animal) {
            Vaccination::create([
                'animal_id' => $animal->id,
                'vaccine_name' => 'PPR',
                'administered_date' => now()->subDays(rand(20, 90)),
                'next_due_date' => now()->addDays(rand(20, 120)),
                'batch_number' => 'VAC-'.rand(100, 999),
                'status' => 'completed',
            ]);
        }

        $suppliers = collect([
            ['code' => 'SUP-001', 'name' => 'Savanna Feeds Ltd', 'category' => 'Feed'],
            ['code' => 'SUP-002', 'name' => 'VetCare Ghana', 'category' => 'Medicine'],
            ['code' => 'SUP-003', 'name' => 'AgriTools West', 'category' => 'Tools'],
            ['code' => 'SUP-004', 'name' => 'GreenFuel Depot', 'category' => 'Fuel'],
            ['code' => 'SUP-005', 'name' => 'Coastal Packaging', 'category' => 'Packaging'],
        ])->map(fn ($row) => Supplier::create($row + [
            'contact_name' => fake()->name(),
            'phone' => '+23324'.rand(1000000, 9999999),
            'email' => strtolower(str_replace(' ', '', $row['name'])).'@supply.gh',
            'location' => 'Accra',
            'rating' => rand(35, 50) / 10,
            'status' => 'active',
        ]));

        $inventory = collect([
            ['item_id' => 'INV-FEED-001', 'name' => 'Dairy Mash', 'category' => 'Feed', 'unit' => 'kg', 'qty' => 420, 'reorder' => 250, 'cost' => 4.2, 'daily' => 38],
            ['item_id' => 'INV-FEED-002', 'name' => 'Hay Bales', 'category' => 'Feed', 'unit' => 'bale', 'qty' => 90, 'reorder' => 40, 'cost' => 18, 'daily' => 8],
            ['item_id' => 'INV-FEED-003', 'name' => 'Mineral Mix', 'category' => 'Feed', 'unit' => 'kg', 'qty' => 55, 'reorder' => 40, 'cost' => 12, 'daily' => 3],
            ['item_id' => 'INV-MED-001', 'name' => 'Oxytetracycline', 'category' => 'Medicine', 'unit' => 'vial', 'qty' => 24, 'reorder' => 10, 'cost' => 15],
            ['item_id' => 'INV-MED-002', 'name' => 'Ivermectin', 'category' => 'Medicine', 'unit' => 'bottle', 'qty' => 8, 'reorder' => 12, 'cost' => 22],
            ['item_id' => 'INV-MED-003', 'name' => 'Multivitamin', 'category' => 'Medicine', 'unit' => 'bottle', 'qty' => 18, 'reorder' => 8, 'cost' => 9],
            ['item_id' => 'INV-PPE-001', 'name' => 'Nitrile Gloves', 'category' => 'PPE', 'unit' => 'box', 'qty' => 4, 'reorder' => 6, 'cost' => 18],
            ['item_id' => 'INV-FUL-001', 'name' => 'Diesel', 'category' => 'Fuel', 'unit' => 'L', 'qty' => 210, 'reorder' => 150, 'cost' => 1.4],
            ['item_id' => 'INV-TOL-001', 'name' => 'Fence Staples', 'category' => 'Tools', 'unit' => 'box', 'qty' => 3, 'reorder' => 5, 'cost' => 11],
        ])->map(function ($row) use ($suppliers) {
            $item = InventoryItem::create([
                'item_id' => $row['item_id'],
                'name' => $row['name'],
                'category' => $row['category'],
                'unit' => $row['unit'],
                'storage_location' => $row['category'] === 'Feed' ? 'Feed Store' : 'Clinic Store',
                'quantity_on_hand' => $row['qty'],
                'average_cost' => $row['cost'],
                'supplier_id' => $suppliers->random()->id,
                'minimum_stock' => $row['reorder'] - 2,
                'reorder_level' => $row['reorder'],
                'maximum_level' => $row['qty'] * 3,
                'expiry_date' => $row['category'] === 'Medicine' ? now()->addDays(rand(10, 180)) : null,
                'status' => 'active',
            ]);

            if ($row['category'] === 'Feed') {
                $feed = FeedItem::create([
                    'inventory_item_id' => $item->id,
                    'daily_consumption' => $row['daily'],
                    'feed_type' => $row['name'],
                ]);
                FeedTransaction::create([
                    'feed_item_id' => $feed->id,
                    'type' => 'issue',
                    'quantity' => $row['daily'],
                    'transaction_date' => now(),
                    'notes' => 'Daily ration',
                ]);
            }

            if ($row['category'] === 'Medicine') {
                MedicineBatch::create([
                    'inventory_item_id' => $item->id,
                    'batch_number' => 'MB-'.rand(1000, 9999),
                    'quantity' => $row['qty'],
                    'expiry_date' => $item->expiry_date,
                    'status' => 'available',
                ]);
            }

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'type' => 'receive',
                'quantity' => $row['qty'],
                'moved_at' => now()->subDays(rand(2, 20)),
                'reference' => 'GRN-SEED',
            ]);

            return $item;
        });

        $pr1 = PurchaseRequest::create([
            'request_number' => 'PR-2026-001',
            'requested_by' => $farm->id,
            'requested_at' => now()->subDays(6),
            'item_name' => 'Dairy Mash',
            'category' => 'Feed',
            'quantity' => 500,
            'unit' => 'kg',
            'estimated_cost' => 2100,
            'priority' => 'high',
            'status' => 'approved',
            'justification' => 'Feed days remaining approaching threshold',
        ]);
        PurchaseRequestApproval::create([
            'purchase_request_id' => $pr1->id,
            'approver_id' => $ops->id,
            'decision' => 'approved',
            'decided_at' => now()->subDays(5),
            'comments' => 'Approved against low-feed alert',
        ]);
        $po1 = PurchaseOrder::create([
            'po_number' => 'PO-2026-001',
            'purchase_request_id' => $pr1->id,
            'supplier_id' => $suppliers[0]->id,
            'ordered_at' => now()->subDays(4),
            'total_amount' => 2100,
            'status' => 'received',
            'odoo_ref' => 'ODOO-PO-1042',
        ]);
        GoodsReceipt::create([
            'purchase_order_id' => $po1->id,
            'received_at' => now()->subDays(2),
            'quantity_received' => 500,
            'condition' => 'ok',
            'received_by' => $users->firstWhere('email', 'store@evolex.com')->id,
        ]);

        $pr2 = PurchaseRequest::create([
            'request_number' => 'PR-2026-002',
            'requested_by' => $vet->id,
            'requested_at' => now()->subDays(1),
            'item_name' => 'Ivermectin',
            'category' => 'Medicine',
            'quantity' => 20,
            'unit' => 'bottle',
            'estimated_cost' => 440,
            'priority' => 'high',
            'status' => 'pending',
            'justification' => 'Below reorder level',
        ]);

        $pr3 = PurchaseRequest::create([
            'request_number' => 'PR-2026-003',
            'requested_by' => $worker->id,
            'requested_at' => now()->subDays(3),
            'item_name' => 'Fence Staples',
            'category' => 'Tools',
            'quantity' => 10,
            'unit' => 'box',
            'estimated_cost' => 110,
            'priority' => 'normal',
            'status' => 'approved',
            'justification' => 'South paddock repair',
        ]);
        PurchaseRequestApproval::create([
            'purchase_request_id' => $pr3->id,
            'approver_id' => $farm->id,
            'decision' => 'approved',
            'decided_at' => now()->subDays(2),
        ]);
        PurchaseOrder::create([
            'po_number' => 'PO-2026-002',
            'purchase_request_id' => $pr3->id,
            'supplier_id' => $suppliers[2]->id,
            'ordered_at' => now()->subDays(2),
            'total_amount' => 110,
            'status' => 'open',
        ]);

        $assets = collect([
            ['asset_id' => 'EV-AS-000001', 'name' => 'John Deere Tractor', 'category' => 'Machinery'],
            ['asset_id' => 'EV-AS-000002', 'name' => 'Feed Mixer', 'category' => 'Equipment'],
            ['asset_id' => 'EV-AS-000003', 'name' => 'Water Pump', 'category' => 'Utilities'],
            ['asset_id' => 'EV-AS-000004', 'name' => 'Backup Generator', 'category' => 'Utilities'],
            ['asset_id' => 'EV-AS-000005', 'name' => 'Cold Store Fridge', 'category' => 'Storage'],
            ['asset_id' => 'EV-AS-000006', 'name' => 'Farm Pickup', 'category' => 'Vehicle'],
        ])->map(fn ($row, $i) => Asset::create($row + [
            'serial_number' => 'SN-'.rand(10000, 99999),
            'purchase_date' => now()->subMonths(rand(6, 40)),
            'purchase_price' => rand(800, 28000),
            'supplier_id' => $suppliers->random()->id,
            'location_id' => $locations[$i % $locations->count()]->id,
            'assigned_employee_id' => $employees[$i % $employees->count()]->id,
            'condition' => $i === 2 ? 'fair' : 'good',
            'warranty_until' => now()->addMonths(rand(2, 18)),
            'status' => 'active',
        ]));

        MaintenanceSchedule::create([
            'asset_id' => $assets[0]->id,
            'title' => 'Tractor oil and filter',
            'type' => 'preventive',
            'due_date' => now()->addDays(4),
            'interval_days' => 90,
            'status' => 'scheduled',
        ]);
        MaintenanceSchedule::create([
            'asset_id' => $assets[2]->id,
            'title' => 'Pump seal inspection',
            'type' => 'inspection',
            'due_date' => now()->subDays(2),
            'interval_days' => 30,
            'status' => 'overdue',
        ]);
        MaintenanceTicket::create([
            'ticket_number' => 'MT-2026-001',
            'asset_id' => $assets[2]->id,
            'title' => 'Water pump losing pressure',
            'type' => 'corrective',
            'priority' => 'high',
            'status' => 'open',
            'reported_by' => $farm->id,
            'assigned_to' => $worker->id,
            'reported_at' => now()->subHours(8),
            'description' => 'South paddock trough filling slowly.',
        ]);
        MaintenanceTicket::create([
            'ticket_number' => 'MT-2026-002',
            'asset_id' => $assets[3]->id,
            'title' => 'Generator monthly test',
            'type' => 'preventive',
            'priority' => 'normal',
            'status' => 'in_progress',
            'reported_by' => $ops->id,
            'assigned_to' => $worker->id,
            'reported_at' => now()->subDays(1),
        ]);

        foreach ($employees as $i => $employee) {
            Attendance::create([
                'employee_id' => $employee->id,
                'work_date' => now()->toDateString(),
                'time_in' => '06:30:00',
                'time_out' => $i === 4 ? null : '16:00:00',
                'status' => $i === 4 ? 'leave' : ($i === 5 ? 'absent' : 'present'),
                'overtime_hours' => $i === 2 ? 1.5 : 0,
            ]);
        }

        Task::create([
            'title' => 'Complete weekly herd count',
            'assigned_to' => $farm->id,
            'created_by' => $ops->id,
            'due_date' => now()->toDateString(),
            'priority' => 'high',
            'status' => 'open',
            'module' => 'livestock',
        ]);
        Task::create([
            'title' => 'Follow up Ivermectin purchase',
            'assigned_to' => $proc->id,
            'created_by' => $vet->id,
            'due_date' => now()->addDay(),
            'priority' => 'high',
            'status' => 'open',
            'module' => 'procurement',
        ]);
        Task::create([
            'title' => 'Publish goat birth content pack',
            'assigned_to' => $users->firstWhere('email', 'marketing@evolex.com')->id,
            'created_by' => $ops->id,
            'due_date' => now()->subDays(1),
            'priority' => 'normal',
            'status' => 'in_progress',
            'module' => 'marketing',
        ]);
        Task::create([
            'title' => 'Archive September invoices',
            'assigned_to' => $finance->id,
            'created_by' => $ops->id,
            'due_date' => now()->addDays(3),
            'priority' => 'normal',
            'status' => 'completed',
            'module' => 'finance',
        ]);

        Expense::create(['category' => 'Feed', 'description' => 'Emergency hay', 'amount' => 640, 'expense_date' => now()->subDays(2), 'recorded_by' => $finance->id]);
        Expense::create(['category' => 'Fuel', 'description' => 'Generator diesel', 'amount' => 280, 'expense_date' => now()->subDays(1), 'recorded_by' => $finance->id]);
        Expense::create(['category' => 'Veterinary', 'description' => 'Clinic visit', 'amount' => 190, 'expense_date' => now()->subDays(8), 'recorded_by' => $finance->id]);
        Expense::create(['category' => 'Wages', 'description' => 'Casual labour', 'amount' => 900, 'expense_date' => now()->subDays(5), 'recorded_by' => $finance->id]);

        $inv = Invoice::create([
            'invoice_number' => 'INV-2026-014',
            'supplier_id' => $suppliers[0]->id,
            'purchase_order_id' => $po1->id,
            'amount' => 2100,
            'invoice_date' => now()->subDays(2),
            'due_date' => now()->addDays(12),
            'status' => 'partial',
        ]);
        Invoice::create([
            'invoice_number' => 'INV-2026-011',
            'supplier_id' => $suppliers[1]->id,
            'amount' => 360,
            'invoice_date' => now()->subDays(18),
            'due_date' => now()->subDays(3),
            'status' => 'paid',
        ]);
        Payment::create([
            'invoice_id' => $inv->id,
            'amount' => 500,
            'paid_at' => now()->subDay(),
            'method' => 'bank_transfer',
            'reference' => 'PAY-441',
        ]);

        $stages = collect([
            'Prospect Identified', 'Contacted', 'Qualified', 'Meeting',
            'Sample', 'Quote', 'Negotiation', 'Won',
        ])->map(fn ($name, $i) => PipelineStage::create([
            'name' => $name,
            'sort_order' => $i + 1,
            'is_won' => $name === 'Won',
        ]));

        $companies = collect([
            ['name' => 'Harborview Hotel', 'category' => 'hotels', 'lifecycle_stage' => 'prospect'],
            ['name' => 'City Supermarket', 'category' => 'supermarkets', 'lifecycle_stage' => 'lead'],
            ['name' => 'Golden Fork Restaurants', 'category' => 'restaurants', 'lifecycle_stage' => 'customer'],
            ['name' => 'Northside Butchers', 'category' => 'butchers', 'lifecycle_stage' => 'prospect'],
            ['name' => 'Savanna Distributors', 'category' => 'distributors', 'lifecycle_stage' => 'lead'],
            ['name' => 'Campus Catering', 'category' => 'caterers', 'lifecycle_stage' => 'subscriber'],
        ])->map(fn ($row) => Company::create($row + [
            'city' => 'Accra',
            'phone' => '+23330'.rand(1000000, 9999999),
            'email' => strtolower(str_replace(' ', '', $row['name'])).'@buyers.gh',
            'hubspot_id' => 'HS-CO-'.rand(1000, 9999),
            'status' => 'active',
        ]));

        foreach ($companies as $company) {
            Contact::create([
                'company_id' => $company->id,
                'name' => fake()->name(),
                'email' => 'buyer'.rand(10, 99).'@'.$company->id.'.gh',
                'phone' => '+23320'.rand(1000000, 9999999),
                'role' => 'Procurement',
                'lifecycle_stage' => $company->lifecycle_stage,
                'hubspot_id' => 'HS-CT-'.rand(1000, 9999),
            ]);
        }

        Deal::create([
            'name' => 'Weekly goat supply - Harborview',
            'company_id' => $companies[0]->id,
            'pipeline_stage_id' => $stages[3]->id,
            'amount' => 4800,
            'close_date' => now()->addDays(18),
            'next_action' => 'Send sample pack',
            'next_action_date' => now()->addDays(2),
            'status' => 'open',
            'hubspot_id' => 'HS-DE-1001',
        ]);
        Deal::create([
            'name' => 'Cattle carcass trial - City Super',
            'company_id' => $companies[1]->id,
            'pipeline_stage_id' => $stages[5]->id,
            'amount' => 9200,
            'close_date' => now()->addDays(30),
            'next_action' => 'Issue quote',
            'next_action_date' => now()->addDays(4),
            'status' => 'open',
            'hubspot_id' => 'HS-DE-1002',
        ]);
        Deal::create([
            'name' => 'Repeat order - Golden Fork',
            'company_id' => $companies[2]->id,
            'pipeline_stage_id' => $stages[7]->id,
            'amount' => 3600,
            'close_date' => now()->subDays(4),
            'next_action' => 'Schedule delivery',
            'status' => 'won',
            'hubspot_id' => 'HS-DE-1003',
        ]);

        $media = MediaAsset::create([
            'title' => 'New kid in Maternity Barn',
            'type' => 'photo',
            'location' => 'Maternity Barn',
            'subject' => 'goat birth',
            'url' => 'https://drive.evolex.local/media/2026/09/barn/birth-01.jpg',
            'tags' => ['goat', 'birth', 'barn'],
            'captured_at' => now()->subDays(3),
            'rights' => 'internal',
        ]);
        ContentItem::create([
            'title' => 'Life on the farm: first kid of September',
            'channel' => 'Instagram',
            'content_type' => 'carousel',
            'status' => 'published',
            'published_at' => now()->subDays(2),
            'media_asset_id' => $media->id,
        ]);
        ContentItem::create([
            'title' => 'Why traceable livestock matters',
            'channel' => 'Website',
            'content_type' => 'article',
            'status' => 'scheduled',
            'scheduled_for' => now()->addDays(2),
        ]);
        ContentItem::create([
            'title' => 'Buyer sample day recap',
            'channel' => 'LinkedIn',
            'content_type' => 'post',
            'status' => 'draft',
            'scheduled_for' => now()->addDays(5),
        ]);
        Campaign::create([
            'name' => 'Traceable Protein Q3',
            'channel' => 'multi',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => 'active',
            'budget' => 1200,
        ]);
        AiPrompt::create([
            'title' => 'Farm event to social pack',
            'tool' => 'ChatGPT',
            'prompt' => 'Turn this farm event into Instagram, LinkedIn and website copy. Human review required before publish.',
            'status' => 'approved',
        ]);

        foreach (['Instagram', 'Facebook', 'Website', 'WhatsApp', 'YouTube'] as $i => $channel) {
            ChannelMetric::create([
                'channel' => $channel,
                'metric_date' => now()->toDateString(),
                'reach' => rand(800, 9000),
                'views' => rand(400, 12000),
                'followers' => rand(200, 4200),
                'website_visits' => $channel === 'Website' ? rand(300, 900) : 0,
                'leads' => rand(1, 12),
                'engagement_rate' => rand(20, 80) / 10,
            ]);
        }

        Risk::create([
            'title' => 'Feed cover below 14 days',
            'category' => 'operations',
            'severity' => 'high',
            'likelihood' => 'likely',
            'owner_id' => $ops->id,
            'status' => 'open',
            'mitigation' => 'Expedite Dairy Mash PO and dual-source hay',
            'identified_at' => now()->subDays(2),
        ]);
        Risk::create([
            'title' => 'Respiratory cases in North Paddock',
            'category' => 'animal_health',
            'severity' => 'medium',
            'likelihood' => 'possible',
            'owner_id' => $vet->id,
            'status' => 'open',
            'mitigation' => 'Isolate, treat, increase observation',
            'identified_at' => now()->subDays(1),
        ]);
        Risk::create([
            'title' => 'Overdue water pump maintenance',
            'category' => 'assets',
            'severity' => 'high',
            'likelihood' => 'likely',
            'owner_id' => $farm->id,
            'status' => 'open',
            'mitigation' => 'Assign technician and hold spare seals',
            'identified_at' => now()->subDays(3),
        ]);

        Decision::create([
            'title' => 'Approve emergency feed supplier switch',
            'description' => 'Savanna Feeds delayed. Need alternate mill.',
            'status' => 'pending_owner',
            'requested_by' => $ops->id,
            'owner_id' => $owner->id,
            'due_date' => now()->addDay(),
        ]);
        Decision::create([
            'title' => 'Capital request for second water pump',
            'description' => 'Single point of failure on South paddock water.',
            'status' => 'pending_owner',
            'requested_by' => $farm->id,
            'owner_id' => $owner->id,
            'due_date' => now()->addDays(5),
        ]);

        foreach ([
            // [title, severity, module, message, type, read, hours ago]
            ['Low feed cover', 'critical', 'feed', 'Dairy mash cover is under 14 days at current consumption.', 'low_feed_cover', false, 2],
            ['Open disease cases', 'critical', 'health', '3 animals currently sick and under treatment.', 'open_disease_cases', false, 5],
            ['Low stock: Ivermectin', 'warning', 'inventory', 'Quantity on hand is below reorder level.', 'low_stock', false, 9],
            ['Overdue maintenance', 'warning', 'maintenance', 'Water pump seal inspection is overdue.', 'overdue_maintenance', false, 14],
            ['Owner decision required', 'info', 'governance', '2 decisions waiting for ownership approval.', 'owner_decision_required', false, 20],
            ['Purchase order approved', 'success', 'procurement', 'PO #1234 has been approved and released to the supplier.', 'purchase_order_approved', false, 26],
            ['Vaccination campaign due', 'warning', 'health', 'Annual clostridial vaccination is due for 12 animals next week.', 'vaccination_due', false, 30],
            ['New employee onboarding', 'info', 'hr', 'A new farm assistant starts Monday — complete onboarding tasks.', 'employee_onboarding', false, 36],
            ['Invoice payment received', 'success', 'finance', 'Payment for invoice INV-2026-041 has been received.', 'payment_received', true, 44],
            ['Heat detection window', 'info', 'breeding', '6 cows are entering the optimal insemination window.', 'heat_detection', true, 52],
            ['Feed delivery scheduled', 'info', 'feed', 'Savanna Feeds delivery of 2.5T layers mash arrives Thursday.', 'feed_delivery', true, 60],
            ['Sales cull approved', 'success', 'sales', 'Cull request for animal AX-0419 has been approved by the owner.', 'sales_cull_approved', true, 72],
        ] as $row) {
            Alert::create([
                'type' => $row[4],
                'severity' => $row[1],
                'title' => $row[0],
                'message' => $row[3],
                'module' => $row[2],
                'is_read' => $row[5],
                'triggered_at' => now()->subHours($row[6]),
            ]);
        }

        AuditLog::create([
            'user_id' => $farm->id,
            'action' => 'animal.registered',
            'auditable_type' => Animal::class,
            'auditable_id' => $animals->first()->id,
            'meta' => ['animal_id' => $animals->first()->animal_id],
        ]);
        AuditLog::create([
            'user_id' => $ops->id,
            'action' => 'purchase.approved',
            'auditable_type' => PurchaseRequest::class,
            'auditable_id' => $pr1->id,
            'meta' => ['request_number' => $pr1->request_number],
        ]);

        foreach ([
            ['SOP-LS-001', 'Livestock', 'Animal identification and tagging'],
            ['SOP-LS-002', 'Livestock', 'Birth recording'],
            ['SOP-LS-003', 'Livestock', 'Death and mortality reporting'],
            ['SOP-HT-001', 'Health', 'Sick animal isolation'],
            ['SOP-FD-001', 'Feed', 'Daily feed issuing'],
            ['SOP-PR-001', 'Procurement', 'Purchase request and approval'],
            ['SOP-IN-001', 'Inventory', 'Goods receiving'],
            ['SOP-AS-001', 'Assets', 'Maintenance ticket workflow'],
            ['SOP-HR-001', 'HR', 'Attendance capture'],
            ['SOP-MK-001', 'Marketing', 'Human review before publish'],
        ] as $sop) {
            Sop::create([
                'code' => $sop[0],
                'title' => $sop[2],
                'category' => $sop[1],
                'version' => '1.0',
                'status' => 'published',
                'summary' => $sop[2].' — controlled process in Evolex Hub.',
            ]);
        }

        foreach ([
            ['Google Workspace', 'Email, Drive, identity'],
            ['Odoo Community', 'Procurement, inventory, maintenance'],
            ['HubSpot', 'CRM and sales pipeline'],
            ['WordPress', 'Public website'],
            ['WhatsApp Business', 'Field and buyer messaging'],
            ['Buffer', 'Social scheduling'],
        ] as $sys) {
            SystemRegistry::create([
                'system_name' => $sys[0],
                'business_purpose' => $sys[1],
                'account_owner' => $owner->name,
                'administrator' => $ops->name,
                'billing_owner' => $finance->name,
                'renewal_period' => 'annual',
                'renewal_date' => now()->addMonths(8),
                'data_owner' => $ops->name,
                'recovery_process' => 'Admin account + documented recovery in Drive.',
            ]);
        }
    }
}
