<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TaskSeeder extends Seeder
{
    /** All seeded tasks are owned by this user (frontend test account). */
    private const TASK_OWNER_EMAIL = 'email@example.com';

    public function run(): void
    {
        $user = User::query()->where('email', self::TASK_OWNER_EMAIL)->first();
        if ($user === null) {
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => self::TASK_OWNER_EMAIL,
            ]);
        }

        $this->ensureTaskTypesForUser($user->id);
        $userId = $user->id;

        $total = random_int(300, 500);

        $nCompleted = (int) floor($total * 0.60);
        $nInProgress = (int) floor($total * 0.10);
        $nPending = (int) floor($total * 0.10);
        $nCanceled = (int) floor($total * 0.05);
        $nSoftDeleted = (int) floor($total * 0.05);
        $nPostponedStatus = $total - $nCompleted - $nInProgress - $nPending - $nCanceled - $nSoftDeleted;

        $specs = [];
        foreach (range(1, $nCompleted) as $_) {
            $specs[] = [
                'status' => 'completed',
                'completed_segment' => fake()->boolean(50) ? 'early' : 'late',
                'soft_delete' => false,
            ];
        }
        foreach (range(1, $nInProgress) as $_) {
            $specs[] = ['status' => 'in_progress', 'completed_segment' => null, 'soft_delete' => false];
        }
        foreach (range(1, $nPending) as $_) {
            $specs[] = ['status' => 'pending', 'completed_segment' => null, 'soft_delete' => false];
        }
        foreach (range(1, $nCanceled) as $_) {
            $specs[] = ['status' => 'canceled', 'completed_segment' => null, 'soft_delete' => false];
        }
        foreach (range(1, $nSoftDeleted) as $_) {
            $specs[] = [
                'status' => fake()->randomElement(['pending', 'in_progress', 'canceled']),
                'completed_segment' => null,
                'soft_delete' => true,
            ];
        }
        foreach (range(1, $nPostponedStatus) as $_) {
            $specs[] = ['status' => 'postponed', 'completed_segment' => null, 'soft_delete' => false];
        }

        shuffle($specs);

        $postponeFlags = array_merge(
            array_fill(0, (int) round($total * 0.30), true),
            array_fill(0, $total - (int) round($total * 0.30), false)
        );
        shuffle($postponeFlags);

        foreach ($specs as $index => $spec) {
            $createdAt = Carbon::parse(fake()->dateTimeBetween('-6 months', 'now'));

            $usePostponementChain = $postponeFlags[$index] ?? false;

            if (($spec['status'] ?? '') === 'postponed') {
                $usePostponementChain = true;
            }

            if ($spec['status'] === 'completed' && ($spec['completed_segment'] ?? '') === 'late') {
                [$attrs, $createdAt] = $this->buildLateCompletedScenario($createdAt, $usePostponementChain);
            } else {
                $attrs = $this->buildDateAttributes($createdAt, $usePostponementChain);
                if ($spec['status'] === 'completed') {
                    $attrs['completed_at'] = $this->pickCompletedAt(
                        $createdAt,
                        $attrs['original_due_date'],
                    );
                } else {
                    $attrs['completed_at'] = null;
                }
            }

            $attrs['user_id'] = $userId;
            $attrs['name'] = fake()->sentence(3);
            $attrs['description'] = fake()->optional(0.65)->paragraph();
            $attrs['status'] = $spec['status'];
            $attrs['priority'] = fake()->numberBetween(1, 5);
            $attrs['is_being_viewed'] = false;
            $attrs['last_viewed_at'] = null;
            $attrs['created_at'] = $createdAt;
            $attrs['updated_at'] = $createdAt;

            $task = Task::factory()->create($attrs);

            $this->attachRandomTaskTypes($task, $userId);

            if (! empty($spec['soft_delete'])) {
                $task->delete();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDateAttributes(Carbon $createdAt, bool $usePostponementChain): array
    {
        $attrs = [
            'postponed_count' => 0,
            'postponed_date_1' => null,
            'postponed_date_2' => null,
            'postponed_date_3' => null,
        ];

        if (! $usePostponementChain) {
            $original = $createdAt->copy()->addDays(fake()->numberBetween(5, 75));
            $attrs['original_due_date'] = $original;
            $attrs['current_due_date'] = $original->copy();

            return $attrs;
        }

        $postponedCount = fake()->numberBetween(1, 3);
        $attrs['postponed_count'] = $postponedCount;

        $original = $createdAt->copy()->addDays(fake()->numberBetween(3, 45));
        $extraDays = fake()->numberBetween(8, 55);
        $current = $original->copy()->addDays($extraDays);

        $attrs['original_due_date'] = $original;
        $attrs['current_due_date'] = $current;

        $origTs = $original->getTimestamp();
        $curTs = $current->getTimestamp();
        $span = $curTs - $origTs;

        for ($i = 1; $i <= $postponedCount; $i++) {
            $t = $origTs + (int) ($span * $i / ($postponedCount + 1));
            $attrs['postponed_date_'.$i] = Carbon::createFromTimestamp($t);
        }

        return $attrs;
    }

    /**
     * Late completion: completed_at is strictly after current_due_date; all timeline fields stay coherent.
     *
     * @return array{0: array<string, mixed>, 1: Carbon}
     */
    private function buildLateCompletedScenario(Carbon $createdAt, bool $usePostponementChain): array
    {
        $attrs = [
            'postponed_count' => 0,
            'postponed_date_1' => null,
            'postponed_date_2' => null,
            'postponed_date_3' => null,
        ];

        $completedAtMoment = Carbon::now()->subDays(fake()->numberBetween(2, 150))
            ->subMinutes(fake()->numberBetween(0, 180));

        $currentDue = $completedAtMoment->copy()
            ->subDays(fake()->numberBetween(2, 45))
            ->subHours(fake()->numberBetween(1, 12));

        if ($currentDue->greaterThanOrEqualTo($completedAtMoment)) {
            $currentDue = $completedAtMoment->copy()->subHours(2);
        }

        if (! $usePostponementChain) {
            $attrs['original_due_date'] = $currentDue->copy();
            $attrs['current_due_date'] = $currentDue->copy();
        } else {
            $postponedCount = fake()->numberBetween(1, 3);
            $attrs['postponed_count'] = $postponedCount;

            $originalDue = $currentDue->copy()->subDays(fake()->numberBetween(10, 60));
            if ($originalDue->greaterThanOrEqualTo($currentDue)) {
                $originalDue = $currentDue->copy()->subDays(7);
            }

            $attrs['original_due_date'] = $originalDue;
            $attrs['current_due_date'] = $currentDue;

            $origTs = $originalDue->getTimestamp();
            $curTs = $currentDue->getTimestamp();
            $span = $curTs - $origTs;

            for ($i = 1; $i <= $postponedCount; $i++) {
                $t = $origTs + (int) ($span * $i / ($postponedCount + 1));
                $attrs['postponed_date_'.$i] = Carbon::createFromTimestamp($t);
            }
        }

        if ($createdAt->greaterThanOrEqualTo($attrs['original_due_date'])) {
            $createdAt = $attrs['original_due_date']->copy()->subDays(fake()->numberBetween(14, 90));
        }

        $attrs['completed_at'] = $completedAtMoment;

        return [$attrs, $createdAt];
    }

    private function pickCompletedAt(Carbon $createdAt, Carbon $originalDue): Carbon
    {
        if ($originalDue->lessThanOrEqualTo($createdAt)) {
            $originalDue = $createdAt->copy()->addDays(fake()->numberBetween(7, 40));
        }

        $latest = $originalDue->copy()->subHour();
        $earliest = $createdAt->copy()->addHour();
        if ($latest->lessThanOrEqualTo($earliest)) {
            return $earliest;
        }

        return Carbon::createFromTimestamp(fake()->numberBetween($earliest->getTimestamp(), $latest->getTimestamp()));
    }

    private function ensureTaskTypesForUser(string $userId): void
    {
        if (TaskType::query()->where('user_id', $userId)->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Trabalho', 'color' => '#FF5733'],
            ['name' => 'Estudos', 'color' => '#33FF57'],
            ['name' => 'Pessoal', 'color' => '#3357FF'],
        ];

        foreach ($defaults as $type) {
            TaskType::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $type['name']],
                ['color' => $type['color']]
            );
        }
    }

    private function attachRandomTaskTypes(Task $task, string $userId): void
    {
        $types = TaskType::query()->where('user_id', $userId)->get();
        if ($types->isEmpty()) {
            $this->ensureTaskTypesForUser($userId);
            $types = TaskType::query()->where('user_id', $userId)->get();
        }

        $take = min($types->count(), fake()->numberBetween(1, 3));
        $ids = $types->shuffle()->take($take)->pluck('id')->all();
        if ($ids !== []) {
            $task->taskTypes()->attach($ids);
        }
    }
}
