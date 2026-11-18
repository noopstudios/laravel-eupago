# Changelog

Todas as alterações relevantes deste pacote ficam documentadas aqui. O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-PT/1.1.0/).

## 0.1.0 — 2026-11-18

### Adicionado

- Referências Multibanco e pedidos MB WAY pela API REST do EuPago (`Eupago::multibanco()` e `Eupago::mbway()`), registados como `PaymentAttempt` com um identificador próprio.
- Sem referências duplicadas: lock por payable, reutilização da referência Multibanco pendente do mesmo valor e limite de tentativas. Uma resposta perdida deixa a tentativa em `unknown`, nunca é repetida e bloqueia novos pedidos até ser resolvida.
- Webhooks 2.0: assinatura HMAC-SHA256 verificada sobre o corpo bruto, eventos guardados uma só vez e processados por um job, com validação do valor, da referência e da entidade (`pending`, `processed`, `rejected`, `orphaned`).
- Contrato `HandlesEupagoPayments` (`canReceive`, `received`, `expired`) e eventos `PaymentConfirmed`, `PaymentNeedsReview` e `PaymentAttemptExpired`.
- `eupago:expire` (cada minuto, com 2 minutos de margem e à espera dos eventos `pending` da tentativa) e `eupago:recover` (cada cinco minutos), agendados pelo pacote.
- Plugin Filament v5 opcional: tentativas e eventos, com as ações "Reprocessar" e "Dar como falhada" e permissões configuráveis.
- `Eupago::fake()` com `pay()` e `expire()` para os testes da aplicação, e suite opcional contra a sandbox do EuPago (`--group=sandbox`).
- README em português europeu.

### Notas

- O formato da assinatura do webhook foi confirmado com uma entrega real na sandbox do EuPago. As entregas recusadas por assinatura ficam registadas como warning.
- Requisitos: PHP `^8.3`, Laravel `^13.0`; Filament `^5.0` só para o painel. Licença MIT.
