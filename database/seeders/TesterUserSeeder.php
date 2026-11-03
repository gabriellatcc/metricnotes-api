<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TesterUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'teste@metricnotes.com'],
            [
                'name' => 'Teste',
                'password' => 'teste123456',
                'is_admin' => true,
            ]
        );

        if ($this->command !== null) {
            $this->command->info('Usuário teste garantido: teste@metricnotes.com (senha: teste123456).');
        }
    }
}
