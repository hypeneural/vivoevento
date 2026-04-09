<?php

namespace App\Modules\MediaIntelligence\Services;

class ContextualModerationPromptBuilder
{
    /**
     * @param array<string, mixed> $policySnapshot
     * @return array{
     *   policy_json: array<string, mixed>,
     *   prompt_template: string,
     *   prompt_resolved: string,
     *   variables_json: array<string, string>
     * }
     */
    public function build(
        string $eventName,
        array $policySnapshot,
        ?string $contextTextContext = null,
        ?string $replyInstruction = null,
        ?string $replyTextContext = null,
    ): array {
        $lines = array_filter([
            'Avalie a imagem considerando a politica estruturada do evento.',
            $eventName !== '' ? 'nome do evento: ' . $eventName : null,
            'preset ativo: ' . ($policySnapshot['contextual_policy_preset_label'] ?? $policySnapshot['contextual_policy_preset_key'] ?? 'nao informado'),
            'versao da politica: ' . ($policySnapshot['policy_version'] ?? 'contextual-policy-v1'),
            'escopo de analise: ' . ($policySnapshot['context_scope'] ?? 'image_only'),
            'alcool permitido: ' . ((bool) ($policySnapshot['allow_alcohol'] ?? false) ? 'sim' : 'nao'),
            'tabaco permitido: ' . ((bool) ($policySnapshot['allow_tobacco'] ?? false) ? 'sim' : 'nao'),
            'presenca de pessoas: ' . (($policySnapshot['required_people_context'] ?? 'optional') === 'required' ? 'obrigatoria' : 'opcional'),
            $this->joinListLine('bloqueios adicionais', $policySnapshot['blocked_terms_json'] ?? []),
            $this->joinListLine('excecoes permitidas', $policySnapshot['allowed_exceptions_json'] ?? []),
            $contextTextContext ? 'Texto associado ao envio considerado na analise: ' . $contextTextContext : null,
            $replyTextContext ? 'Contexto textual disponivel para orientar a resposta automatica: ' . $replyTextContext : null,
            $policySnapshot['freeform_instruction'] ? 'instrucao complementar do operador: ' . trim((string) $policySnapshot['freeform_instruction']) : null,
            $replyInstruction ? 'instrucao de resposta automatica: ' . trim($replyInstruction) : null,
            'Nao invente contexto que nao esteja visivel. Quando houver incerteza relevante, use review.',
        ]);

        $promptTemplate = 'Avalie a imagem considerando a politica estruturada do evento e responda estritamente com o schema solicitado.';

        return [
            'policy_json' => $policySnapshot,
            'prompt_template' => $promptTemplate,
            'prompt_resolved' => implode("\n", $lines),
            'variables_json' => [
                'nome_do_evento' => $eventName,
            ],
        ];
    }

    /**
     * @param mixed $values
     */
    private function joinListLine(string $label, mixed $values): ?string
    {
        if (! is_array($values)) {
            return null;
        }

        $items = array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
            $values,
        )));

        if ($items === []) {
            return null;
        }

        return $label . ': ' . implode(', ', $items);
    }
}
