<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TesteUserAnalyticsTaskSeeder extends Seeder
{
    private const ADMIN_EMAIL = 'admin@metricnotes.com';

    private const TIP_COLORS = [
        'Trabalho' => '#FF5733',
        'Estudos' => '#33FF57',
        'Pessoal' => '#3357FF',
    ];

    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Admin',
                'password' => 'admin123456',
                'is_admin' => true,
            ]
        );

        $this->ensureTipsForUser($user->id);
        $userId = $user->id;

        $templates = $this->taskTemplates();
        $totalTasks = count($templates);
        $oneThirdCount = (int) round($totalTasks / 3);

        foreach ($templates as $index => $template) {
            if ($index < $oneThirdCount) {
                $createdAt = Carbon::now();
                $due = Carbon::now();
            } else {
                $createdAt = Carbon::now()->addDays(5);
                $due = Carbon::now()->addDays(5);
            }

            $attrs = [
                'postponed_count' => 0,
                'postponed_date_1' => null,
                'postponed_date_2' => null,
                'postponed_date_3' => null,
                'original_due_date' => $due,
                'current_due_date' => $due->copy(),
            ];

            if ($template['status'] === 'completed') {
                $attrs['completed_at'] = $due->copy()->addHours(1);
            } else {
                $attrs['completed_at'] = null;
            }

            $attrs['user_id'] = $userId;
            $attrs['name'] = $template['name'];
            $attrs['description'] = $template['description'];
            $attrs['status'] = $template['status'];
            $attrs['priority'] = $template['priority'];
            $attrs['is_being_viewed'] = false;
            $attrs['last_viewed_at'] = null;
            $attrs['created_at'] = $createdAt;
            $attrs['updated_at'] = $createdAt;

            $task = Task::factory()->create($attrs);

            $this->attachTips($task, $userId, $template['tips']);

            if ($template['soft_delete']) {
                $task->delete();
            }
        }
    }

    private function taskTemplates(): array
    {
        return [
            [
                'name' => 'Revisar o referencial teórico do TCC',
                'description' => 'Reler os capítulos do livro, conferir as citações e deixar as referências no formato ABNT.',
                'status' => 'completed',
                'priority' => 4,
                'tips' => ['Estudos'],
                'segment' => 'early',
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Escrever a fundamentação teórica do TCC',
                'description' => 'Explicar o conceito de Design Thinking com palavras próprias e preparar a ligação com a metodologia.',
                'status' => 'completed',
                'priority' => 5,
                'tips' => ['Estudos'],
                'segment' => 'late',
                'postpone' => true,
                'soft_delete' => false,
            ],
            [
                'name' => 'Preparar os slides da apresentação para a banca',
                'description' => 'Montar a estrutura da apresentação: problema, objetivos, metodologia, resultados e trabalhos futuros.',
                'status' => 'postponed',
                'priority' => 5,
                'tips' => ['Estudos'],
                'segment' => null,
                'postpone' => true,
                'soft_delete' => false,
            ],
            [
                'name' => 'Revisar ortografia e figuras da documentação',
                'description' => 'Conferir a numeração das figuras, as legendas e as fontes, e corrigir erros de digitação.',
                'status' => 'in_progress',
                'priority' => 3,
                'tips' => ['Estudos', 'Trabalho'],
                'segment' => null,
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Lavar e estender a roupa da semana',
                'description' => 'Separar roupas claras e escuras e deixar tudo pronto para guardar no fim do dia.',
                'status' => 'completed',
                'priority' => 2,
                'tips' => ['Pessoal'],
                'segment' => 'early',
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Fazer as compras do mês no mercado',
                'description' => 'Montar a lista de itens de limpeza e alimentos antes de sair, para não esquecer nada.',
                'status' => 'postponed',
                'priority' => 3,
                'tips' => ['Pessoal'],
                'segment' => null,
                'postpone' => true,
                'soft_delete' => false,
            ],
            [
                'name' => 'Limpar o banheiro e passar pano na casa',
                'description' => 'Faxina semanal: banheiro, cozinha e quartos.',
                'status' => 'pending',
                'priority' => 2,
                'tips' => ['Pessoal'],
                'segment' => null,
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Organizar a escrivaninha de estudos',
                'description' => 'Tarefa cancelada e removida: a mesa foi arrumada durante a revisão do TCC.',
                'status' => 'canceled',
                'priority' => 1,
                'tips' => ['Pessoal', 'Estudos'],
                'segment' => null,
                'postpone' => false,
                'soft_delete' => true,
            ],
            [
                'name' => 'Marcar reunião de alinhamento com a orientadora',
                'description' => 'Combinar data e horário para tirar dúvidas sobre a metodologia e o formato final da documentação.',
                'status' => 'completed',
                'priority' => 4,
                'tips' => ['Estudos'],
                'segment' => 'early',
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Corrigir as referências que faltam na documentação',
                'description' => 'Incluir as obras citadas no texto que ainda não aparecem na lista de referências e padronizar tudo em ABNT.',
                'status' => 'completed',
                'priority' => 4,
                'tips' => ['Estudos'],
                'segment' => 'late',
                'postpone' => true,
                'soft_delete' => false,
            ],
            [
                'name' => 'Ensaiar a apresentação do TCC em voz alta',
                'description' => 'Cronometrar a fala e ajustar os slides que ficarem longos demais.',
                'status' => 'pending',
                'priority' => 4,
                'tips' => ['Estudos'],
                'segment' => null,
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Testar todas as rotas da API antes da entrega',
                'description' => 'Rodar os testes de cadastro, login, tarefas, etiquetas e dashboards, registrando o que falhar.',
                'status' => 'completed',
                'priority' => 5,
                'tips' => ['Estudos', 'Trabalho'],
                'segment' => 'early',
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Gravar o vídeo de demonstração do sistema',
                'description' => 'Mostrar o fluxo principal: anotar uma ideia, convertê-la em tarefa e acompanhar os gráficos.',
                'status' => 'postponed',
                'priority' => 3,
                'tips' => ['Estudos', 'Trabalho'],
                'segment' => null,
                'postpone' => true,
                'soft_delete' => false,
            ],
            [
                'name' => 'Exportar a documentação final em PDF',
                'description' => 'Conferir sumário, numeração de páginas, figuras e margens antes de gerar o arquivo definitivo.',
                'status' => 'in_progress',
                'priority' => 5,
                'tips' => ['Estudos'],
                'segment' => null,
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Passar o aspirador e tirar o pó dos móveis',
                'description' => 'Aproveitar o fim de semana para limpar a sala e o quarto.',
                'status' => 'completed',
                'priority' => 2,
                'tips' => ['Pessoal'],
                'segment' => 'late',
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Preparar as marmitas da semana',
                'description' => 'Cozinhar arroz, feijão e uma proteína, e separar em porções para congelar.',
                'status' => 'pending',
                'priority' => 3,
                'tips' => ['Pessoal'],
                'segment' => null,
                'postpone' => false,
                'soft_delete' => false,
            ],
            [
                'name' => 'Pagar a conta de luz',
                'description' => 'Conferir o valor no aplicativo da distribuidora e programar o pagamento antes do vencimento.',
                'status' => 'postponed',
                'priority' => 3,
                'tips' => ['Pessoal'],
                'segment' => null,
                'postpone' => true,
                'soft_delete' => false,
            ],
            [
                'name' => 'Trocar a lâmpada da sala',
                'description' => 'Tarefa cancelada e removida: o problema era no soquete e já foi resolvido de outra forma.',
                'status' => 'canceled',
                'priority' => 1,
                'tips' => ['Pessoal'],
                'segment' => null,
                'postpone' => false,
                'soft_delete' => true,
            ],
        ];
    }

    private function ensureTipsForUser(string $userId): void
    {
        if (Tip::query()->where('user_id', $userId)->exists()) {
            return;
        }

        foreach (self::TIP_COLORS as $name => $color) {
            Tip::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $name],
                ['color' => $color]
            );
        }
    }

    private function attachTips(Task $task, string $userId, array $tipNames): void
    {
        $ids = [];

        foreach ($tipNames as $name) {
            $tip = Tip::query()->firstOrCreate(
                ['user_id' => $userId, 'name' => $name],
                ['color' => self::TIP_COLORS[$name] ?? '#888888']
            );
            $ids[] = $tip->id;
        }

        if ($ids !== []) {
            $task->tips()->attach($ids);
        }
    }
}