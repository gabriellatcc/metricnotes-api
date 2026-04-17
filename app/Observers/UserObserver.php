<?php

namespace App\Observers;

use App\Models\User;
use App\Models\TaskType;

class UserObserver
{
    public function created(User $user): void
    {
        $defaultTypes = [
            ['name' => 'Trabalho', 'color' => '#FF0000'],
            ['name' => 'Estudo', 'color' => '#00FF00'],
            ['name' => 'Lazer', 'color' => '#0000FF'],
        ];

        foreach ($defaultTypes as $type) {
            TaskType::create([
                'user_id' => $user->id,
                'name' => $type['name'],
                'color' => $type['color'],
            ]);
        }
    }
}