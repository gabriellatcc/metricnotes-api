<?php

namespace App\Services;

use App\Http\Resources\TaskType\TaskTypeCollection;
use App\Http\Resources\TaskType\TaskTypeResource;
use App\Models\TaskType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Exception;

class TaskTypeService
{
    public function index(array $data): TaskTypeCollection
    {
        $perPage = (int) ($data['per_page'] ?? 15);
        $search = $data['search'] ?? null;
        $userId = Auth::id();

        if (! $userId) {
            throw new Exception('Usuário não autenticado.', 401);
        }

        $query = TaskType::query()
            ->with('user')
            ->where('user_id', $userId)
            ->latest();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $taskTypes = $query->paginate($perPage);

        return new TaskTypeCollection($taskTypes);
    }

    public function show(array $data): TaskTypeResource
    {
        $taskType = TaskType::with('user')->find($data['id']);

        if (! $taskType) {
            throw new Exception('Tipo de tarefa não encontrado', 404);
        }

        Gate::authorize('show', $taskType);

        return new TaskTypeResource($taskType);
    }

    public function store(array $data): TaskTypeResource
    {
        $userId = Auth::id();

        if (! $userId) {
            throw new Exception('Usuário não autenticado.', 401);
        }

        $taskType = new TaskType();
        $taskType->user_id = $userId;
        $taskType->name = $data['name'];
        $taskType->color = $data['color'] ?? null;
        $taskType->save();
        $taskType->load('user');

        return new TaskTypeResource($taskType);
    }

    public function update(array $data): TaskTypeResource
    {
        $taskType = TaskType::find($data['id']);

        if (! $taskType) {
            throw new Exception('Tipo de tarefa não encontrado', 404);
        }

        Gate::authorize('update', $taskType);

        $taskType->update([
            'name' => $data['name'] ?? $taskType->name,
            'color' => $data['color'] ?? $taskType->color,
        ]);

        return new TaskTypeResource($taskType->refresh()->load('user'));
    }

    public function delete(array $data): bool
    {
        $taskType = TaskType::find($data['id']);

        if (! $taskType) {
            throw new Exception('Tipo de tarefa não encontrado', 404);
        }

        Gate::authorize('delete', $taskType);

        return $taskType->delete();
    }
}