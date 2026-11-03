<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds tasks for BI-style checks on completion and postponements in the **previous calendar week**
 * (Mon 00:00 – Sun 23:59:59 before this week). All dates are derived from now(), so re-running the
 * seeder keeps data aligned with “last week” instead of drifting to fixed past dates.
 *
 * Creates the tasks listed in {@see self::taskTemplates()} (TCC and household routine)
 * for the admin user ({@see AdminUserSeeder}).
 */
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
        [$weekStart, $weekEnd] = $this->previousCalendarWeekBounds();

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

        foreach ($this->taskTemplates() as $template) {
            $createdAt = $this->randomCarbonBetween($weekStart, $weekEnd->copy()->subDays(1));

            $usePostponementChain = $template['postpone'] || $template['status'] === 'postponed';

            if ($template['status'] === 'completed' && $template['segment'] === 'late') {
                [$attrs, $createdAt] = $this->buildLateCompletedInWeek($createdAt, $usePostponementChain, $weekStart, $weekEnd);
            } else {
                $attrs = $this->buildDateAttributesInWeek($createdAt, $usePostponementChain, $weekStart, $weekEnd);
                if ($template['status'] === 'completed') {
                    $attrs['completed_at'] = $this->pickCompletedAtInWeek(
                        $createdAt,
                        $attrs['original_due_date'],
                        $weekStart,
                        $weekEnd
                    );
                } else {
                    $attrs['completed_at'] = null;
                }
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

    /**
     * Tarefas fixas e realistas (TCC + rotina doméstica).
     *
     * - status: completed | postponed | in_progress | pending | canceled
     * - segment: 'early' (concluída antes do prazo) ou 'late' (concluída após o prazo); só vale para completed
     * - postpone: true gera cadeia de adiamentos (1 a 3 datas)
     * - soft_delete: true exclui a tarefa logo após criar
     *
     * @return array<int, array{
     *     name: string,
     *     description: string,
     *     status: string,
     *     priority: int,
     *     tips: array<int, string>,
     *     segment: string|null,
     *     postpone: bool,
     *     soft_delete: bool
     * }>
     */
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

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousCalendarWeekBounds(): array
    {
        $thisWeekMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $lastWeekMonday = $thisWeekMonday->copy()->subWeek();
        $lastWeekSundayEnd = $thisWeekMonday->copy()->subSecond();

        return [$lastWeekMonday, $lastWeekSundayEnd];
    }

    private function earliestOf(Carbon $a, Carbon $b): Carbon
    {
        return $a->lessThan($b) ? $a : $b;
    }

    private function latestOf(Carbon $a, Carbon $b): Carbon
    {
        return $a->greaterThan($b) ? $a : $b;
    }

    private function randomCarbonBetween(Carbon $from, Carbon $to): Carbon
    {
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return Carbon::createFromTimestamp(
            fake()->numberBetween($from->getTimestamp(), $to->getTimestamp())
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDateAttributesInWeek(
        Carbon $createdAt,
        bool $usePostponementChain,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array {
        $attrs = [
            'postponed_count' => 0,
            'postponed_date_1' => null,
            'postponed_date_2' => null,
            'postponed_date_3' => null,
        ];

        if (! $usePostponementChain) {
            $latestOriginal = $weekEnd->copy()->subHours(2);
            $earliestOriginal = $createdAt->copy()->addHours(2);
            if ($earliestOriginal->greaterThan($latestOriginal)) {
                $original = $createdAt->copy()->addDay();
            } else {
                $original = $this->randomCarbonBetween($earliestOriginal, $latestOriginal);
            }
            $attrs['original_due_date'] = $original;
            $attrs['current_due_date'] = $original->copy();

            return $attrs;
        }

        $postponedCount = fake()->numberBetween(1, 3);
        $attrs['postponed_count'] = $postponedCount;

        $midWeek = $this->randomCarbonBetween(
            $this->latestOf($createdAt, $weekStart)->copy()->addHours(4),
            $weekEnd->copy()->subDays(1)
        );

        $original = $createdAt->copy()->addHours(fake()->numberBetween(6, 48));
        if ($original->greaterThan($midWeek)) {
            $original = $this->randomCarbonBetween($createdAt->copy()->addHours(2), $midWeek);
        }

        $current = $this->randomCarbonBetween(
            $original->copy()->addHours(2),
            $this->earliestOf($weekEnd, $original->copy()->addDays(4))
        );
        if ($current->lessThanOrEqualTo($original)) {
            $current = $original->copy()->addHours(4);
        }

        $attrs['original_due_date'] = $original;
        $attrs['current_due_date'] = $current;

        $origTs = $original->getTimestamp();
        $curTs = $current->getTimestamp();
        $span = max(1, $curTs - $origTs);

        for ($i = 1; $i <= $postponedCount; $i++) {
            $t = $origTs + (int) ($span * $i / ($postponedCount + 1));
            $attrs['postponed_date_'.$i] = Carbon::createFromTimestamp($t);
        }

        return $attrs;
    }

    /**
     * @return array{0: array<string, mixed>, 1: Carbon}
     */
    private function buildLateCompletedInWeek(
        Carbon $createdAt,
        bool $usePostponementChain,
        Carbon $weekStart,
        Carbon $weekEnd
    ): array {
        $attrs = [
            'postponed_count' => 0,
            'postponed_date_1' => null,
            'postponed_date_2' => null,
            'postponed_date_3' => null,
        ];

        $completedAtMoment = $this->randomCarbonBetween(
            $weekStart->copy()->addDays(2),
            $weekEnd->copy()->subHours(2)
        );

        $currentDue = $completedAtMoment->copy()
            ->subDays(fake()->numberBetween(1, 3))
            ->subHours(fake()->numberBetween(1, 8));

        if ($currentDue->lessThanOrEqualTo($createdAt)) {
            $currentDue = $createdAt->copy()->addHours(12);
        }
        if ($currentDue->greaterThanOrEqualTo($completedAtMoment)) {
            $currentDue = $completedAtMoment->copy()->subHours(3);
        }

        if (! $usePostponementChain) {
            $attrs['original_due_date'] = $currentDue->copy();
            $attrs['current_due_date'] = $currentDue->copy();
        } else {
            $postponedCount = fake()->numberBetween(1, 2);
            $attrs['postponed_count'] = $postponedCount;

            $originalDue = $currentDue->copy()->subDays(fake()->numberBetween(1, 3));
            if ($originalDue->lessThanOrEqualTo($createdAt)) {
                $originalDue = $createdAt->copy()->addHours(4);
            }

            $attrs['original_due_date'] = $originalDue;
            $attrs['current_due_date'] = $currentDue;

            $origTs = $originalDue->getTimestamp();
            $curTs = $currentDue->getTimestamp();
            $span = max(1, $curTs - $origTs);

            for ($i = 1; $i <= $postponedCount; $i++) {
                $t = $origTs + (int) ($span * $i / ($postponedCount + 1));
                $attrs['postponed_date_'.$i] = Carbon::createFromTimestamp($t);
            }
        }

        if ($createdAt->greaterThanOrEqualTo($attrs['original_due_date'])) {
            $createdAt = $attrs['original_due_date']->copy()->subHours(6);
            if ($createdAt->lessThan($weekStart)) {
                $createdAt = $weekStart->copy()->addHours(2);
            }
        }

        $attrs['completed_at'] = $completedAtMoment;

        return [$attrs, $createdAt];
    }

    private function pickCompletedAtInWeek(
        Carbon $createdAt,
        Carbon $originalDue,
        Carbon $weekStart,
        Carbon $weekEnd
    ): Carbon {
        if ($originalDue->lessThanOrEqualTo($createdAt)) {
            $originalDue = $this->randomCarbonBetween(
                $createdAt->copy()->addHours(4),
                $weekEnd->copy()->subHours(4)
            );
        }

        $latest = $this->earliestOf($originalDue->copy()->subHour(), $weekEnd->copy()->subHour());
        $earliest = $this->latestOf($createdAt->copy()->addHour(), $weekStart->copy()->addHour());

        if ($latest->lessThanOrEqualTo($earliest)) {
            return $earliest;
        }

        return $this->randomCarbonBetween($earliest, $latest);
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

    /**
     * Anexa à tarefa as etiquetas pelo nome, criando as que ainda não existirem para o usuário.
     *
     * @param  array<int, string>  $tipNames
     */
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