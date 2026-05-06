<?php

declare(strict_types=1);

namespace App\Domain\Combat\Data;

/**
 * @phpstan-type CombatPhaseResolutionItem array{
 *     action_id: int,
 *     position: int,
 *     result: array<string, mixed>
 * }
 */
final readonly class CombatPhaseResolutionResult
{
    /**
     * @param  list<CombatPhaseResolutionItem>  $results
     * @param  list<string>  $summaryLines
     */
    public function __construct(
        public int $phaseId,
        public int $phaseNumber,
        public int $actionCount,
        public string $resolvedAt,
        public array $results,
        public string $summary,
        public array $summaryLines,
    ) {}

    /**
     * @return array{
     *     phase_id: int,
     *     phase_number: int,
     *     action_count: int,
     *     resolved_at: string,
     *     results: list<CombatPhaseResolutionItem>,
     *     summary: string,
     *     summary_lines: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'phase_id' => $this->phaseId,
            'phase_number' => $this->phaseNumber,
            'action_count' => $this->actionCount,
            'resolved_at' => $this->resolvedAt,
            'results' => $this->results,
            'summary' => $this->summary,
            'summary_lines' => $this->summaryLines,
        ];
    }
}
