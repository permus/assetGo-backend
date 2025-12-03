<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateUserTypesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:update-types 
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing user types: owner => admin, manager => user, team => user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $this->info('Updating user types...');
        $this->newLine();

        // Count users by type before update
        $ownerCount = User::where('user_type', 'owner')->count();
        $managerCount = User::where('user_type', 'manager')->count();
        $teamCount = User::where('user_type', 'team')->count();

        $totalToUpdate = $ownerCount + $managerCount + $teamCount;

        if ($totalToUpdate === 0) {
            $this->info('No users found with legacy user types (owner, manager, team).');
            $this->info('All users already have valid types (admin or user).');
            return Command::SUCCESS;
        }

        // Display what will be updated
        $this->table(
            ['Current Type', 'New Type', 'Count'],
            [
                ['owner', 'admin', $ownerCount],
                ['manager', 'user', $managerCount],
                ['team', 'user', $teamCount],
            ]
        );

        $this->newLine();
        $this->info("Total users to update: {$totalToUpdate}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (!$this->confirm('Do you want to proceed with updating these user types?', true)) {
            $this->info('Update cancelled.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('Updating users...');

        try {
            DB::beginTransaction();

            // Update owner => admin
            if ($ownerCount > 0) {
                $updated = User::where('user_type', 'owner')
                    ->update(['user_type' => 'admin']);
                $this->info("✓ Updated {$updated} user(s) from 'owner' to 'admin'");
            }

            // Update manager => user
            if ($managerCount > 0) {
                $updated = User::where('user_type', 'manager')
                    ->update(['user_type' => 'user']);
                $this->info("✓ Updated {$updated} user(s) from 'manager' to 'user'");
            }

            // Update team => user
            if ($teamCount > 0) {
                $updated = User::where('user_type', 'team')
                    ->update(['user_type' => 'user']);
                $this->info("✓ Updated {$updated} user(s) from 'team' to 'user'");
            }

            DB::commit();

            $this->newLine();
            $this->info('✓ User type update completed successfully!');
            
            // Verify the update
            $remainingOwner = User::where('user_type', 'owner')->count();
            $remainingManager = User::where('user_type', 'manager')->count();
            $remainingTeam = User::where('user_type', 'team')->count();

            if ($remainingOwner + $remainingManager + $remainingTeam > 0) {
                $this->warn('Warning: Some legacy user types still exist:');
                if ($remainingOwner > 0) $this->warn("  - {$remainingOwner} user(s) with type 'owner'");
                if ($remainingManager > 0) $this->warn("  - {$remainingManager} user(s) with type 'manager'");
                if ($remainingTeam > 0) $this->warn("  - {$remainingTeam} user(s) with type 'team'");
            } else {
                $this->info('✓ All users now have valid types (admin or user).');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error updating user types: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

