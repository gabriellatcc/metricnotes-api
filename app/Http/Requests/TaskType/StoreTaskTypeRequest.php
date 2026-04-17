<?php

namespace App\Http\Requests\TaskType;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'nome do tipo de tarefa',
            'color' => 'cor do tipo de tarefa',
        ];
    }
}
