<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanChecklist;
use App\Models\MaintenancePlanPart;
use App\Models\ScheduleMaintenance;
use App\Models\ScheduleMaintenanceAssigned;
use App\Models\MaintenanceChecklistResponse;
use App\Models\PredictiveMaintenance;
use App\Models\Asset;
use App\Models\User;
use App\Models\InventoryPart;
use App\Models\WorkOrderPriority;
use App\Models\WorkOrderCategory;
use Illuminate\Support\Str;

class MaintenanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        
        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Please run CompanySeeder first.');
            return;
        }

        foreach ($companies as $company) {
            $this->command->info("Seeding maintenance data for company: {$company->name}");
            
            // Get company-specific data
            $assets = Asset::where('company_id', $company->id)->get();
            $teamMembers = User::where('company_id', $company->id)
                ->where('user_type', 'team')
                ->where('active', true)
                ->get();
            $inventoryParts = InventoryPart::where('company_id', $company->id)
                ->where('is_archived', false)
                ->get();
            $priorities = WorkOrderPriority::forCompany($company->id)->get();
            $categories = WorkOrderCategory::forCompany($company->id)->get();

            if ($assets->isEmpty()) {
                $this->command->warn("Skipping company {$company->name} - no assets found.");
                continue;
            }

            // Seed maintenance plans
            $plans = $this->seedMaintenancePlans($company, $assets, $priorities, $categories, $teamMembers);
            
            // Seed checklists for each plan
            foreach ($plans as $plan) {
                $this->seedPlanChecklists($plan);
            }
            
            // Seed plan parts
            foreach ($plans as $plan) {
                if ($inventoryParts->isNotEmpty()) {
                    $this->seedPlanParts($plan, $inventoryParts);
                }
            }
            
            // Seed scheduled maintenance
            $schedules = $this->seedSchedules($plans, $assets, $priorities, $teamMembers);
            
            // Seed assignments
            foreach ($schedules as $schedule) {
                if ($teamMembers->isNotEmpty()) {
                    $this->seedAssignments($schedule, $teamMembers);
                }
            }
            
            // Seed checklist responses (history) for completed assignments
            $this->seedChecklistResponses($schedules, $teamMembers);
            
            // Seed predictive maintenance (analytics)
            $this->seedPredictiveMaintenance($company, $assets);
        }

        $this->command->info('Maintenance module seeded successfully!');
    }

    /**
     * Seed maintenance plans for a company
     */
    private function seedMaintenancePlans($company, $assets, $priorities, $categories, $teamMembers)
    {
        $plans = [];
        $planCount = rand(50, 100);
        
        $planTypes = ['preventive', 'predictive', 'condition_based'];
        $frequencyTypes = ['time', 'usage', 'condition'];
        $frequencyUnits = ['days', 'weeks', 'months', 'years'];
        
        $planNames = [
            'Monthly Equipment Inspection',
            'Quarterly HVAC Maintenance',
            'Annual Safety Check',
            'Weekly Machine Lubrication',
            'Bi-annual Filter Replacement',
            'Daily Visual Inspection',
            'Preventive Engine Service',
            'Condition-Based Monitoring',
            'Predictive Maintenance Check',
            'Scheduled Component Replacement',
            'Hydraulic System Service',
            'Electrical Panel Inspection',
            'Compressor Maintenance',
            'Pump System Check',
            'Conveyor Belt Inspection',
            'Motor Alignment Check',
            'Bearing Lubrication',
            'Cooling System Service',
            'Safety Valve Testing',
            'Control System Calibration',
            'Gearbox Inspection',
            'Chain Drive Maintenance',
            'Belt Tension Check',
            'Fluid Level Inspection',
            'Seal Replacement Schedule',
            'Bearing Replacement',
            'Filter Element Change',
            'Battery System Check',
            'Wiring Inspection',
            'Grounding Verification',
            'Thermal Imaging Scan',
            'Vibration Analysis',
            'Oil Analysis Service',
            'Ultrasonic Testing',
            'Pressure Testing',
            'Leak Detection Service',
            'Performance Testing',
            'Efficiency Check',
            'Compliance Inspection',
            'Environmental Check',
            'Fire Safety Inspection',
            'Emergency System Test',
            'Backup System Check',
            'Generator Maintenance',
            'UPS System Service',
            'Network Equipment Check',
            'Security System Inspection',
            'Access Control Maintenance',
            'Lighting System Check',
            'HVAC Filter Replacement',
            'Duct Cleaning Service',
        ];

        for ($i = 0; $i < $planCount; $i++) {
            // Select random assets (1-5 assets per plan)
            $selectedAssets = $assets->random(rand(1, min(5, $assets->count())))->pluck('id')->toArray();
            
            $plan = MaintenancePlan::create([
                'company_id' => $company->id,
                'name' => $planNames[array_rand($planNames)] . ' - ' . Str::random(4),
                'priority_id' => $priorities->isNotEmpty() ? $priorities->random()->id : null,
                'sort' => $i,
                'descriptions' => fake()->paragraph(3),
                'category_id' => $categories->isNotEmpty() ? $categories->random()->id : null,
                'plan_type' => $planTypes[array_rand($planTypes)],
                'estimeted_duration' => rand(30, 480), // 30 minutes to 8 hours
                'instractions' => fake()->paragraph(2),
                'safety_notes' => fake()->sentence(10),
                'asset_ids' => $selectedAssets,
                'frequency_type' => $frequencyTypes[array_rand($frequencyTypes)],
                'frequency_value' => rand(1, 12),
                'frequency_unit' => $frequencyUnits[array_rand($frequencyUnits)],
                'is_active' => rand(0, 10) > 1, // 90% active
                'assigned_user_id' => $teamMembers->isNotEmpty() && rand(0, 10) > 5 ? $teamMembers->random()->id : null,
                'assigned_role_id' => null,
                'assigned_team_id' => null,
            ]);
            
            $plans[] = $plan;
        }
        
        return $plans;
    }

    /**
     * Seed checklists for a maintenance plan
     */
    private function seedPlanChecklists($plan)
    {
        $checklistCount = rand(3, 8);
        $checklistTypes = ['checkbox', 'measurements', 'text_input', 'photo_capture', 'pass_fail'];
        
        $checklistTitles = [
            'Check oil level',
            'Inspect for leaks',
            'Test safety mechanisms',
            'Measure temperature',
            'Check pressure readings',
            'Visual inspection',
            'Test functionality',
            'Clean components',
            'Replace filters',
            'Verify calibration',
            'Check electrical connections',
            'Inspect wear patterns',
            'Check fluid levels',
            'Test emergency stops',
            'Verify safety guards',
            'Check belt tension',
            'Inspect bearings',
            'Test control systems',
            'Check alignment',
            'Verify torque settings',
            'Inspect seals',
            'Check for corrosion',
            'Test sensors',
            'Verify connections',
            'Check for vibrations',
            'Inspect filters',
            'Test alarms',
            'Verify settings',
            'Check documentation',
            'Inspect fasteners',
            'Test switches',
            'Verify readings',
            'Check for damage',
            'Inspect hoses',
            'Test valves',
            'Verify operation',
            'Check lubrication',
            'Inspect chains',
            'Test motors',
            'Verify performance',
        ];

        for ($i = 0; $i < $checklistCount; $i++) {
            $type = $checklistTypes[array_rand($checklistTypes)];
            $isRequired = rand(0, 10) > 3; // 70% required
            $isSafetyCritical = rand(0, 10) > 7; // 30% safety critical
            
            MaintenancePlanChecklist::create([
                'maintenance_plan_id' => $plan->id,
                'title' => $checklistTitles[array_rand($checklistTitles)] . ' - ' . Str::random(3),
                'type' => $type,
                'description' => fake()->sentence(8),
                'is_required' => $isRequired,
                'is_safety_critical' => $isSafetyCritical,
                'is_photo_required' => $type === 'photo_capture' || ($isSafetyCritical && rand(0, 10) > 5),
                'order' => $i,
            ]);
        }
    }

    /**
     * Seed parts for a maintenance plan
     */
    private function seedPlanParts($plan, $inventoryParts)
    {
        $partsCount = rand(1, min(5, $inventoryParts->count()));
        $selectedParts = $inventoryParts->random($partsCount);
        
        foreach ($selectedParts as $part) {
            MaintenancePlanPart::create([
                'maintenance_plan_id' => $plan->id,
                'part_id' => $part->id,
                'default_qty' => rand(1, 10) + (rand(0, 99) / 100), // 1.00 to 10.99
                'is_required' => rand(0, 10) > 2, // 80% required
            ]);
        }
    }

    /**
     * Seed scheduled maintenance
     */
    private function seedSchedules($plans, $assets, $priorities, $teamMembers)
    {
        $schedules = [];
        $totalSchedules = rand(50, 100);
        $statuses = ['scheduled', 'in_progress', 'completed'];
        
        // Ensure we create enough schedules to meet minimum
        $schedulesCreated = 0;
        foreach ($plans as $plan) {
            if ($schedulesCreated >= $totalSchedules) {
                break;
            }
            $remaining = $totalSchedules - $schedulesCreated;
            $schedulesPerPlan = min(rand(2, 5), $remaining);
            
            for ($i = 0; $i < $schedulesPerPlan && $schedulesCreated < $totalSchedules; $i++) {
                // Create schedules with various dates (past, present, future)
                $daysOffset = rand(-60, 60); // -60 to +60 days from now
                $startDate = now()->addDays($daysOffset);
                $dueDate = $startDate->copy()->addHours(rand(2, 48));
                
                // Determine status based on date
                $status = 'scheduled';
                if ($daysOffset < -7) {
                    $status = rand(0, 10) > 3 ? 'completed' : 'scheduled'; // Mostly completed if past
                } elseif ($daysOffset < 0) {
                    $status = rand(0, 10) > 5 ? 'in_progress' : 'completed';
                }
                
                // Select assets from plan's asset_ids or random
                $planAssetIds = $plan->asset_ids ?? [];
                $selectedAssets = !empty($planAssetIds) 
                    ? array_slice($planAssetIds, 0, rand(1, min(3, count($planAssetIds))))
                    : ($assets->isNotEmpty() ? [$assets->random()->id] : []);
                
                $schedule = ScheduleMaintenance::create([
                    'maintenance_plan_id' => $plan->id,
                    'asset_ids' => $selectedAssets,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'priority_id' => $priorities->isNotEmpty() ? $priorities->random()->id : null,
                    'assigned_user_id' => $teamMembers->isNotEmpty() && rand(0, 10) > 4 ? $teamMembers->random()->id : null,
                    'assigned_role_id' => null,
                    'assigned_team_id' => null,
                    'auto_generated_wo_ids' => null,
                ]);
                
                $schedules[] = $schedule;
                $schedulesCreated++;
            }
        }
        
        return $schedules;
    }

    /**
     * Seed assignments for scheduled maintenance
     */
    private function seedAssignments($schedule, $teamMembers)
    {
        $assignmentCount = rand(1, min(3, $teamMembers->count()));
        $selectedMembers = $teamMembers->random($assignmentCount);
        
        foreach ($selectedMembers as $member) {
            // Check if assignment already exists (unique constraint)
            $exists = ScheduleMaintenanceAssigned::where('schedule_maintenance_id', $schedule->id)
                ->where('team_id', $member->id)
                ->exists();
            
            if (!$exists) {
                ScheduleMaintenanceAssigned::create([
                    'schedule_maintenance_id' => $schedule->id,
                    'team_id' => $member->id,
                ]);
            }
        }
    }

    /**
     * Seed checklist responses (history) for completed assignments
     */
    private function seedChecklistResponses($schedules, $teamMembers)
    {
        $responseCount = 0;
        $targetResponses = rand(50, 150);
        
        // First, ensure we have enough completed schedules
        $completedSchedules = collect($schedules)->filter(function($schedule) {
            return $schedule->status === 'completed';
        });
        
        // If we don't have enough completed schedules, mark some as completed
        if ($completedSchedules->count() < 20) {
            $schedulesToComplete = collect($schedules)
                ->where('status', '!=', 'completed')
                ->random(min(20 - $completedSchedules->count(), count($schedules) - $completedSchedules->count()));
            
            foreach ($schedulesToComplete as $schedule) {
                $schedule->update(['status' => 'completed']);
                $completedSchedules->push($schedule);
            }
        }
        
        foreach ($completedSchedules as $schedule) {
            $assignments = ScheduleMaintenanceAssigned::where('schedule_maintenance_id', $schedule->id)->get();
            $plan = $schedule->plan;
            $checklists = $plan->checklists;
            
            if ($checklists->isEmpty() || $assignments->isEmpty()) {
                continue;
            }
            
            foreach ($assignments as $assignment) {
                $user = $teamMembers->where('id', $assignment->team_id)->first();
                if (!$user) {
                    continue;
                }
                
                // Create responses for checklist items (70-100% completion)
                $completionRate = rand(70, 100) / 100;
                $completedChecklists = $checklists->random(rand(
                    max(1, (int)($checklists->count() * $completionRate)),
                    $checklists->count()
                ));
                
                foreach ($completedChecklists as $checklist) {
                    if ($responseCount >= $targetResponses) {
                        break 3; // Break out of all loops
                    }
                    
                    // Check if response already exists (unique constraint)
                    $exists = MaintenanceChecklistResponse::where('schedule_maintenance_assigned_id', $assignment->id)
                        ->where('checklist_item_id', $checklist->id)
                        ->exists();
                    
                    if ($exists) {
                        continue;
                    }
                    
                    $responseValue = $this->generateResponseValue($checklist->type);
                    $completedAt = $schedule->due_date 
                        ? $schedule->due_date->copy()->subHours(rand(1, 24))
                        : now()->subDays(rand(1, 30));
                    
                    MaintenanceChecklistResponse::create([
                        'schedule_maintenance_assigned_id' => $assignment->id,
                        'checklist_item_id' => $checklist->id,
                        'user_id' => $user->id,
                        'response_type' => $checklist->type,
                        'response_value' => $responseValue,
                        'photo_url' => ($checklist->type === 'photo_capture' || $checklist->is_photo_required) 
                            ? '/storage/maintenance/photos/' . Str::random(10) . '.jpg' 
                            : null,
                        'completed_at' => $completedAt,
                    ]);
                    
                    $responseCount++;
                }
            }
        }
    }

    /**
     * Generate response value based on checklist type
     */
    private function generateResponseValue($type)
    {
        switch ($type) {
            case 'checkbox':
                return ['checked' => rand(0, 10) > 1]; // 90% checked
            case 'pass_fail':
                return ['result' => rand(0, 10) > 2 ? 'pass' : 'fail']; // 80% pass
            case 'text_input':
                return ['text' => fake()->sentence(5)];
            case 'measurements':
                return [
                    'measurements' => [
                        ['name' => 'Temperature', 'value' => rand(20, 100), 'unit' => '°C'],
                        ['name' => 'Pressure', 'value' => rand(10, 50), 'unit' => 'PSI'],
                    ]
                ];
            case 'photo_capture':
                return ['photo_captured' => true];
            default:
                return ['value' => 'completed'];
        }
    }

    /**
     * Seed predictive maintenance records (analytics)
     */
    private function seedPredictiveMaintenance($company, $assets)
    {
        $predictiveCount = rand(50, 100);
        $riskLevels = ['high', 'medium', 'low'];
        
        $recommendedActions = [
            'Replace worn components immediately',
            'Schedule preventive maintenance within 30 days',
            'Monitor closely for signs of degradation',
            'Perform diagnostic testing',
            'Replace filters and lubricate',
            'Inspect electrical connections',
            'Calibrate sensors and instruments',
            'Check alignment and balance',
            'Review operating parameters',
            'Conduct thermal imaging scan',
        ];
        
        $factors = [
            ['factor' => 'High operating hours', 'severity' => 'medium'],
            ['factor' => 'Temperature fluctuations', 'severity' => 'high'],
            ['factor' => 'Vibration levels', 'severity' => 'medium'],
            ['factor' => 'Age of equipment', 'severity' => 'low'],
            ['factor' => 'Maintenance history', 'severity' => 'medium'],
        ];
        
        $timeline = [
            'immediate' => 'Schedule inspection within 7 days',
            'short_term' => 'Plan maintenance within 30 days',
            'long_term' => 'Consider replacement in 12 months',
        ];

        for ($i = 0; $i < $predictiveCount; $i++) {
            $asset = $assets->random();
            $riskLevel = $riskLevels[array_rand($riskLevels)];
            
            // Calculate confidence based on risk level
            $confidence = match($riskLevel) {
                'high' => rand(70, 95),
                'medium' => rand(50, 75),
                'low' => rand(30, 55),
            };
            
            // Calculate dates
            $daysAhead = match($riskLevel) {
                'high' => rand(7, 30),
                'medium' => rand(30, 90),
                'low' => rand(90, 365),
            };
            $predictedDate = now()->addDays($daysAhead);
            
            // Calculate costs
            $estimatedCost = rand(500, 10000) + (rand(0, 99) / 100);
            $preventiveCost = $estimatedCost * (rand(20, 50) / 100);
            $savings = $estimatedCost - $preventiveCost;
            
            PredictiveMaintenance::create([
                'asset_id' => $asset->id,
                'risk_level' => $riskLevel,
                'predicted_failure_date' => $predictedDate,
                'confidence' => $confidence,
                'recommended_action' => $recommendedActions[array_rand($recommendedActions)],
                'estimated_cost' => $estimatedCost,
                'preventive_cost' => $preventiveCost,
                'savings' => $savings,
                'factors' => array_slice($factors, 0, rand(2, 4)),
                'timeline' => $timeline,
                'company_id' => $company->id,
            ]);
        }
    }
}

