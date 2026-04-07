# Partners Module

## Responsabilidade

Camada administrativa B2B para parceiros profissionais. O modulo trata `partner` como uma `Organization` com `organizations.type = partner` e expoe uma fachada administrativa global para super-admin/platform-admin.

## Entidades

- `Organization` como raiz persistida do parceiro
- `PartnerProfile` para segmento e metadados comerciais
- `PartnerStat` como projecao administrativa de listagem

## Endpoints

- `GET /api/v1/partners`
- `POST /api/v1/partners`
- `GET /api/v1/partners/{partner}`
- `PATCH /api/v1/partners/{partner}`
- `DELETE /api/v1/partners/{partner}`
- `POST /api/v1/partners/{partner}/suspend`
- `GET /api/v1/partners/{partner}/events`
- `GET /api/v1/partners/{partner}/clients`
- `GET /api/v1/partners/{partner}/staff`
- `POST /api/v1/partners/{partner}/staff`
- `GET /api/v1/partners/{partner}/grants`
- `POST /api/v1/partners/{partner}/grants`
- `GET /api/v1/partners/{partner}/activity`

## Implementacao atual

- leitura do admin via `ListPartnersQuery` apoiada em `partner_stats`
- escrita via actions dedicadas do modulo
- policy global em `PartnerPolicy`
- activity log para create/update/suspend/delete/staff/grants

## Pendencias

- criar telas/forms web para detalhe, create/edit/suspend, staff e grants
- avaliar job assincrono para rebuild de `partner_stats` quando o volume exigir
