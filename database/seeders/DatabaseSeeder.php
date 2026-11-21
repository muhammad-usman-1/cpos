<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, MeasurementFieldSeeder::class, MessageTemplateSeeder::class]);

        User::query()->firstOrCreate(['username' => 'owner'], [
            'name' => 'Shop Owner',
            'password' => 'password',
            'role_id' => Role::query()->where('slug', Role::OWNER)->value('id'),
            'is_active' => true,
        ]);
    }
}
