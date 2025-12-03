<?php

namespace App\Console\Commands;

use Database\Seeders\ProjectDataSeeder;
use Illuminate\Console\Command;

class SeedProjectDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project:seed-data {email : The email address of the user to seed data for} {--password= : The password for the user (defaults to "password")}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed all project data for a specific user by email address';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: {$email}");
            return Command::FAILURE;
        }

        $this->info("Starting project data seeding for: {$email}");
        $this->newLine();

        try {
            // Create seeder instance and set email
            $seeder = new ProjectDataSeeder();
            $seeder->setEmail($email);
            
            // Set password if provided
            if ($this->option('password')) {
                $seeder->setPassword($this->option('password'));
            }
            
            // Set the command instance so seeder can output messages
            $seeder->setCommand($this);
            
            // Run the seeder
            $seeder->run();

            $this->newLine();
            $this->info('Project data seeding completed successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error seeding project data: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

