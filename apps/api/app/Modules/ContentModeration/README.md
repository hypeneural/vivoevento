# ContentModeration Module

## Responsabilidade

Camada dedicada para safety moderation da midia:

- configuracao global de safety;
- configuracao por evento;
- avaliacao de nudez, violencia e risco;
- historico de avaliacoes por foto;
- integracao com o pipeline via fila `media-safety`.

## Estrutura atual

Base entregue neste ciclo:

- `ContentModerationGlobalSetting` define o baseline global de safety da plataforma;
- `EventContentModerationSetting` define se o evento usa safety moderation e com qual provider/versionamento;
- `ContentModerationSettingsResolver` resolve a politica efetiva `global -> evento`;
- `EventMediaSafetyEvaluation` guarda o historico de avaliacoes por foto;
- `AnalyzeContentSafetyJob` roda em fila separada e grava a etapa `safety` em `media_processing_runs`;
- `EvaluateContentSafetyAction` isola a borda do provider e so chama provider externo quando o evento esta em `moderation_mode=ai`;
- `ContentModerationProviderManager` resolve o adapter por `provider_key`;
- `OpenAiContentModerationProvider` conecta `omni-moderation-latest` com `input` multimodal e thresholds por categoria;
- `analysis_scope` controla se a safety objetiva usa:
  - apenas imagem
  - imagem + contexto textual
- `NullContentModerationProvider` continua como provider foundation para ambiente local e testes;
- endpoint administrativo global em `/api/v1/content-moderation/global-settings`;
- endpoint administrativo por evento em `/api/v1/events/{event}/content-moderation/settings`;
- card administrativo no detalhe do evento para editar `enabled`, `provider`, `fallback_mode` e thresholds.

## Integracao com MediaProcessing

O modulo nao e chamado diretamente por `MediaProcessing`.

Fluxo atual:

1. `GenerateMediaVariantsJob` conclui as variantes;
2. `MediaVariantsGenerated` e emitido;
3. `ContentModeration` escuta o evento e enfileira `AnalyzeContentSafetyJob`;
4. a etapa `safety` atualiza `event_media.safety_status`;
5. `RunModerationJob` continua como o ponto unico da decisao final.

## Evolucao prevista

- endpoint de reprocessamento seletivo;
- expandir a leitura atual da ultima avaliacao para historico completo na central de moderacao;
- rollout gradual por organizacao/plano para providers e thresholds.
