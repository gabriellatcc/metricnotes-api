<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Estatísticas da última semana fechada (Seg–Dom) para o dashboard de BI.
 * Considera apenas tarefas do usuário autenticado com status "completed".
 */
class WeeklyAnalyticsController extends Controller
{
    /** Faixas de horário exibidas no mapa de calor (hora inicial inclusiva, final exclusiva). */
    private const TIME_BLOCKS = [
        ['start' => 6, 'end' => 9],
        ['start' => 9, 'end' => 12],
        ['start' => 12, 'end' => 15],
        ['start' => 15, 'end' => 18],
        ['start' => 18, 'end' => 21],
    ];

    /** Índice 0 = segunda-feira. */
    private const WEEKDAYS = [
        ['short' => 'Seg', 'full' => 'Segunda-feira'],
        ['short' => 'Ter', 'full' => 'Terça-feira'],
        ['short' => 'Qua', 'full' => 'Quarta-feira'],
        ['short' => 'Qui', 'full' => 'Quinta-feira'],
        ['short' => 'Sex', 'full' => 'Sexta-feira'],
        ['short' => 'Sáb', 'full' => 'Sábado'],
        ['short' => 'Dom', 'full' => 'Domingo'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeek();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $completedAtList = Task::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->pluck('completed_at');

        $blockCount = count(self::TIME_BLOCKS);
        $heatmapCounts = array_fill(0, 7, array_fill(0, $blockCount, 0));
        $perWeekday = array_fill(0, 7, 0);
        $perBlock = array_fill(0, $blockCount, 0);

        foreach ($completedAtList as $value) {
            $completedAt = Carbon::parse($value);
            $day = $completedAt->dayOfWeekIso - 1;
            $block = $this->timeBlockIndex($completedAt->hour);

            $perWeekday[$day]++;

            // Conclusões fora de 06:00–21:00 entram no total e no gráfico por dia,
            // mas não aparecem no mapa de calor nem na distribuição por horário.
            if ($block !== null) {
                $heatmapCounts[$day][$block]++;
                $perBlock[$block]++;
            }
        }

        $total = $completedAtList->count();

        $bestDayIndex = $total > 0 ? array_search(max($perWeekday), $perWeekday, true) : null;
        $bestBlockIndex = max($perBlock) > 0 ? array_search(max($perBlock), $perBlock, true) : null;

        return response()->json([
            'timeBlocks' => array_map(
                fn (int $i) => $this->blockRangeLabel($i),
                array_keys(self::TIME_BLOCKS)
            ),
            'heatmap' => $this->buildHeatmap($heatmapCounts),
            'tasksPerWeekday' => array_map(
                fn (int $i) => [
                    'dayIndex' => $i,
                    'shortLabel' => self::WEEKDAYS[$i]['short'],
                    'totalCompleted' => $perWeekday[$i],
                ],
                array_keys(self::WEEKDAYS)
            ),
            'distributionByTimeBlock' => array_map(
                fn (int $i) => [
                    'blockId' => sprintf('%02d-%02d', self::TIME_BLOCKS[$i]['start'], self::TIME_BLOCKS[$i]['end']),
                    'label' => sprintf('%02d–%02d', self::TIME_BLOCKS[$i]['start'], self::TIME_BLOCKS[$i]['end']),
                    'totalCompleted' => $perBlock[$i],
                ],
                array_keys(self::TIME_BLOCKS)
            ),
            'summary' => [
                'totalTasksWeek' => $total,
                'dailyAverage' => round($total / 7, 1),
                'bestDay' => $bestDayIndex === null ? null : [
                    'label' => self::WEEKDAYS[$bestDayIndex]['full'],
                    'total' => $perWeekday[$bestDayIndex],
                ],
                'bestTimeBlock' => $bestBlockIndex === null ? null : [
                    'label' => $this->blockRangeLabel($bestBlockIndex),
                    'total' => $perBlock[$bestBlockIndex],
                ],
                'insights' => $this->buildInsights($total, $perWeekday, $bestDayIndex, $bestBlockIndex),
            ],
            'recordingMeta' => [
                'daysSinceLastCompletion' => $this->daysSinceLastCompletion($userId),
            ],
        ]);
    }

    private function timeBlockIndex(int $hour): ?int
    {
        foreach (self::TIME_BLOCKS as $index => $block) {
            if ($hour >= $block['start'] && $hour < $block['end']) {
                return $index;
            }
        }

        return null;
    }

    private function blockRangeLabel(int $index): string
    {
        $block = self::TIME_BLOCKS[$index];

        return sprintf('%02d:00–%02d:00', $block['start'], $block['end']);
    }

    /**
     * Lista completa (7 dias x 5 faixas = 35 células), inclusive as zeradas.
     *
     * @param  array<int, array<int, int>>  $counts
     * @return array<int, array{dayIndex: int, timeBlockIndex: int, completedCount: int}>
     */
    private function buildHeatmap(array $counts): array
    {
        $cells = [];

        foreach ($counts as $dayIndex => $blocks) {
            foreach ($blocks as $timeBlockIndex => $completedCount) {
                $cells[] = [
                    'dayIndex' => $dayIndex,
                    'timeBlockIndex' => $timeBlockIndex,
                    'completedCount' => $completedCount,
                ];
            }
        }

        return $cells;
    }

    /**
     * @param  array<int, int>  $perWeekday
     * @return array<int, string>
     */
    private function buildInsights(int $total, array $perWeekday, ?int $bestDay, ?int $bestBlock): array
    {
        if ($total === 0) {
            return ['Nenhuma tarefa foi concluída nessa semana. Comece por uma tarefa pequena para retomar o ritmo.'];
        }

        $insights = [];

        if ($bestBlock !== null) {
            $insights[] = sprintf(
                'O período %s concentra o maior volume de conclusões — proteja essa janela para trabalho focado.',
                $this->blockRangeLabel($bestBlock)
            );
        }

        if ($bestDay !== null) {
            $insights[] = sprintf(
                '%s foi o seu dia mais forte; considere agendar revisões nos dias mais leves.',
                self::WEEKDAYS[$bestDay]['full']
            );
        }

        $weekend = $perWeekday[5] + $perWeekday[6];
        $insights[] = ($weekend / $total) <= 0.25
            ? 'O fim de semana foi mais tranquilo; agrupe tarefas pequenas no sábado se precisar zerar o backlog.'
            : 'Você manteve um bom ritmo no fim de semana; reserve também um tempo para descansar.';

        return $insights;
    }

    /** Dias desde a última conclusão (qualquer data). Null se o usuário nunca concluiu nada. */
    private function daysSinceLastCompletion(string $userId): ?int
    {
        $last = Task::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->max('completed_at');

        if ($last === null) {
            return null;
        }

        return abs((int) Carbon::parse($last)->startOfDay()->diffInDays(Carbon::now()->startOfDay()));
    }
}