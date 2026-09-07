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
            ['email' => 'diegoalp@gmail.com'],
            [
                'name' => 'Diêgo',
                'lastname' => 'Pessoa',
                'password' => '01360alp',
                'type' => UserType::Master,
            ],
        );
    }
}
