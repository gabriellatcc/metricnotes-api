<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\TaskType;
use Illuminate\Database\Seeder;

class TaskTypeSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultTypes = [
            ['name' => 'Trabalho', 'color' => '#FF5733'],
            ['name' => 'Estudos', 'color' => '#33FF57'],
            ['name' => 'Pessoal', 'color' => '#3357FF'],
        ];

        User::all()->each(function ($user) use ($defaultTypes) {
            foreach ($defaultTypes as $type) {
                TaskType::firstOrCreate([
                    'user_id' => $user->id,
                    'name'    => $type['name']
                ], [
                    'color'   => $type['color']
                ]);
            }
        });
    }
}