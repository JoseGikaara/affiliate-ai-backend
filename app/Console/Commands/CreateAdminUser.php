<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {--email=admin@example.com : Admin email address}
                            {--password=password : Admin password}
                            {--name=Admin User : Admin name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->name = $name;
            $user->password = Hash::make($password);
            $user->is_admin = true;
            $user->status = 'active';
            $user->save();

            $this->info("Admin user updated successfully!");
            $this->line("Email: {$email}");
            $this->line("Password: {$password}");
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_admin' => true,
                'status' => 'active',
                'credits' => 1000,
            ]);

            $this->info("Admin user created successfully!");
            $this->line("Email: {$email}");
            $this->line("Password: {$password}");
        }

        return Command::SUCCESS;
    }
}

