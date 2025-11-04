<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset-password 
                            {email : The email address of the user}
                            {--password= : The new password (if not provided, will be prompted)}
                            {--show : Show the password after resetting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset a user\'s password';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        $password = $this->option('password');

        if (!$password) {
            $password = $this->secret('Enter new password (min 8 characters):');
            
            if (strlen($password) < 8) {
                $this->error('Password must be at least 8 characters long.');
                return Command::FAILURE;
            }

            $passwordConfirmation = $this->secret('Confirm new password:');
            
            if ($password !== $passwordConfirmation) {
                $this->error('Passwords do not match.');
                return Command::FAILURE;
            }
        }

        $user->password = Hash::make($password);
        $user->save();

        // Invalidate all existing tokens for security
        $user->tokens()->delete();

        $this->info("Password reset successfully for user: {$user->name} ({$user->email})");

        if ($this->option('show')) {
            $this->line("New password: {$password}");
        } else {
            $this->line("Password has been reset. Use --show flag to display the password.");
        }

        return Command::SUCCESS;
    }
}

