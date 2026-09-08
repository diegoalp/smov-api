<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('MASTER_EMAIL')],
            [
                'name' => env('MASTER_NAME'),
                'password' => env('MASTER_PASSWORD'),
                'email_verified_at' => now(),
                'type' => UserType::Master,
            ],
        );
    }

}
