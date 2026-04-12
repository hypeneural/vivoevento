# EventOperations Module

## Ownership

O modulo `EventOperations` concentra a projection operacional da control room por evento.

## Escopo Atual

Este scaffold inicial cobre apenas:

- registro formal do modulo;
- tabelas append-only e snapshot;
- models e factories de base.

## Fora Deste Slice

Este PR ainda nao inclui:

- projectors/listeners;
- actions de append ou rebuild;
- endpoints HTTP do modulo;
- broadcasting realtime;
- replay.

## Tabelas

- `event_operation_events`
- `event_operation_snapshots`

## Fonte de Verdade

As fontes continuam nos modulos existentes como `Events`, `InboundMedia`, `MediaProcessing`, `Gallery`, `Wall` e `Audit`.

`EventOperations` nasce como camada derivada, nunca como dona do pipeline original.
