<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskType\DeleteTaskTypeRequest;
use App\Http\Requests\TaskType\IndexTaskTypeRequest;
use App\Http\Requests\TaskType\ShowTaskTypeRequest;
use App\Http\Requests\TaskType\StoreTaskTypeRequest;
use App\Http\Requests\TaskType\UpdateTaskTypeRequest;
use App\Services\TaskTypeService;

class TaskTypeController extends Controller
{
    public function __construct(private readonly TaskTypeService $taskTypeService) {}

    public function index(IndexTaskTypeRequest $request)
    {
        try {
            $tasks = $this->taskTypeService->index($request->validated());
            return $this->respondSuccess($tasks, 'Lista de tipos de tarefas exibida com sucesso!');
        } catch (\Exception $e) {
            return $this->respondError('Erro ao listar tipo de tarefas: ' . $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function show(ShowTaskTypeRequest $request)
    {
        try {
            $task = $this->taskTypeService->show($request->validated());
            return $this->respondSuccess($task, 'Tipo de tarefa exibido com sucesso!');
        } catch (\Exception $e) {
            return $this->respondError('Erro ao exibir tipo de tarefa: ' . $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function store(StoreTaskTypeRequest $request)
    {
        try {
            $task = $this->taskTypeService->store($request->validated());
            return $this->respondSuccess($task, 'Tipo de tarefa criado com sucesso!');
        } catch (\Exception $e) {
            return $this->respondError('Erro ao criar tipo de tarefa: ' . $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function update(UpdateTaskTypeRequest $request)
    {
        try {
            $task = $this->taskTypeService->update($request->validated());
            return $this->respondSuccess($task, 'Tipo de tarefa atualizada com sucesso!');
        } catch (\Exception $e) {
            return $this->respondError('Erro ao atualizar tipo de tarefa: ' . $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }

    public function delete(DeleteTaskTypeRequest $request)
    {
        try {
            $task = $this->taskTypeService->delete($request->validated());
            return $this->respondSuccess($task, 'Tipo de tarefa excluída com sucesso!');
        } catch (\Exception $e) {
            return $this->respondError('Erro ao excluir tipo de tarefa: ' . $e->getMessage(), null, $e->getCode() ?: 500);
        }
    }
}