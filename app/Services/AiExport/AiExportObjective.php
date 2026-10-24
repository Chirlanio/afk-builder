<?php

namespace App\Services\AiExport;

use App\Services\Optimization\ChallengeTypeMode;
use Closure;

/**
 * Um Objetivo do Export para IA: o que o jogador quer resolver, o Tipo de
 * desafio (ou nenhum), os campos que pede e como o prompt descreve o pedido.
 */
final readonly class AiExportObjective
{
    /**
     * @param  bool  $formation  false no Objetivo sem Formação (ex.: Em quem investir a seguir)
     * @param  ?string  $context  Contexto fixo; null = derivado do Tipo de desafio
     * @param  AiExportField[]  $fields
     * @param  Closure(array): int  $teams  quantas Equipes pedir
     * @param  Closure(array): string  $goal  o que o modelo deve resolver, após "Quero "
     * @param  bool  $uniqueTeams  liga a regra de Equipes exclusivas
     * @param  ?Closure(array): string[]  $notes  regras extras do Tipo de desafio
     */
    public function __construct(
        public string $code,
        public string $label,
        public ?string $challengeType,
        public array $fields,
        private Closure $goal,
        private ?Closure $teams = null,
        public bool $formation = true,
        private ?string $context = null,
        public bool $uniqueTeams = false,
        private ?Closure $notes = null,
    ) {}

    public function context(): string
    {
        return $this->context ?? ChallengeTypeMode::forCode($this->challengeType);
    }

    public function teams(array $params): int
    {
        return $this->teams ? ($this->teams)($params) : 1;
    }

    public function goal(array $params): string
    {
        return ($this->goal)($params);
    }

    /**
     * @return string[]
     */
    public function notes(array $params): array
    {
        return $this->notes ? ($this->notes)($params) : [];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return collect($this->fields)->mapWithKeys(fn (AiExportField $field) => [$field->name => $field->rules()])->all();
    }

    /**
     * O que o modal precisa para desenhar o Objetivo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'formation' => $this->formation,
            'fields' => array_map(fn (AiExportField $field) => $field->toArray(), $this->fields),
        ];
    }
}
