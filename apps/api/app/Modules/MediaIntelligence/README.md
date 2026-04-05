# MediaIntelligence Module

## Responsabilidade

`MediaIntelligence` encapsula a camada semantica de VLM do pipeline de fotos.

Ele e responsavel por:

- manter configuracao por evento para `enrich_only` e `gate`;
- falar com providers de raciocinio visual por contrato proprio;
- padronizar saida em JSON estruturado;
- persistir historico das avaliacoes do VLM.

Ele nao deve:

- executar safety moderation;
- executar indexacao facial;
- decidir publicacao fora da matriz final do `MediaProcessing`.

## Componentes principais

- `EventMediaIntelligenceSetting` define provider, modelo, prompt, schema e timeout por evento;
- `EventMediaVlmEvaluation` guarda o historico das inferencias do VLM;
- `VisualReasoningProviderInterface` protege o dominio de acoplamento com `vLLM` ou outros providers;
- `VllmVisualReasoningProvider` usa serving OpenAI-compatible com `response_format` em JSON schema;
- `NullVisualReasoningProvider` continua disponivel para local/testes;
- `EventMediaIntelligenceSettingsController` expoe a configuracao administrativa por evento;
- `EvaluateMediaPromptAction` e `EvaluateMediaPromptJob` conectam o provider ao pipeline real.

## Contrato atual

O provider retorna uma unica resposta estruturada para:

- `decision`
- `reason`
- `short_caption`
- `tags`

## Integracao atual com o pipeline

- `AnalyzeContentSafetyJob` decide se o VLM precisa rodar depois do safety;
- `EvaluateMediaPromptJob` consome `fast_preview`, respeita `mode=enrich_only/gate` e persiste `event_media_vlm_evaluations`;
- em `gate`, o job devolve a midia para `RunModerationJob`;
- em `enrich_only`, a publicacao nao fica bloqueada;
- a central de moderacao consome a ultima avaliacao estruturada no detalhe da midia.

## Proximos passos

- endpoint de reprocessamento seletivo do VLM;
- exposicao de override manual e rerun por etapa no backoffice;
- rollout por plano para `3B` vs `7B`.
