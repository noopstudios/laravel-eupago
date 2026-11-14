# laravel-eupago

Referências Multibanco e pedidos MB WAY através da API REST do [EuPago](https://eupago.pt), com confirmação por Webhooks 2.0. O pacote não conhece encomendas nem faturação: recebe um modelo Eloquent (o "payable"), um valor em cêntimos e devolve uma tentativa de pagamento; quando o EuPago confirma, chama a tua aplicação.

Requisitos: PHP `^8.3`, Laravel 13. Filament v5 é opcional (só para o painel).

## Instalação

```bash
composer require noopstudios/laravel-eupago
php artisan migrate   # as migrações são carregadas pelo pacote
php artisan vendor:publish --tag=eupago-config   # opcional
```

## Configuração

```dotenv
EUPAGO_API_KEY=
EUPAGO_WEBHOOK_SECRET=   # a "chave criptográfica" dos Webhooks 2.0 do canal
EUPAGO_SANDBOX=false     # true usa sandbox.eupago.pt em vez de clientes.eupago.pt
EUPAGO_PREFIX=           # prefixo do identificador ("PREFIXO-ULID"); permite várias apps no mesmo canal
```

Opções só em `config/eupago.php`:

| Chave | Por omissão | Significado |
| --- | --- | --- |
| `multibanco_days` | `3` | Dias de validade da referência (até ao fim desse dia, Europe/Lisbon) |
| `mbway_minutes` | `5` | Minutos que o cliente tem para aprovar o pedido MB WAY |
| `max_attempts` | `5` | Tentativas por payable |
| `webhook_path` | `eupago/webhook` | Caminho do webhook |
| `permissions` | ver [Filament](#filament-opcional) | Abilities do painel |

## O que a aplicação tem de ter

- **Um queue worker**: os eventos do webhook são processados por um job.
- **O scheduler a correr**: o pacote agenda `eupago:expire` (cada minuto) e `eupago:recover` (cada cinco minutos). Sem eles as tentativas não expiram e os eventos perdidos não são recuperados.
- **Uma cache com locks partilhada por todos os servidores e processos** (Redis, base de dados…): o lock por payable evita referências duplicadas. `array` e `file` não servem com mais de um processo.
- **Nada de `Eupago::multibanco()` / `mbway()` dentro de uma transação de base de dados**: o pacote lança uma exceção, porque um rollback apagaria o registo que impede uma segunda referência para o mesmo payable.
- **Trusted proxies configurados**, se estiveres atrás de um proxy ou load balancer: a rota do webhook tem throttle por IP (120 pedidos por minuto).

## Pedir pagamentos

```php
use Noop\Eupago\Facades\Eupago;

$attempt = Eupago::multibanco($order, 1250);            // 12,50 €
$attempt = Eupago::mbway($order, 1250, '912345678');    // o pedido segue para a app MB WAY do cliente
```

Ambos devolvem um `PaymentAttempt` com `entity`, `reference`, `amount_cents`, `identifier`, `expires_at` e `status`, e `payable` para voltar ao modelo. Só devolvem quando a tentativa está `pending`.

Regras:

- Uma tentativa Multibanco `pending` do mesmo valor e ainda válida é reutilizada; um valor diferente cria uma nova referência.
- MB WAY envia sempre um pedido novo.
- Se a resposta se perder, a tentativa fica `unknown`: pode existir uma referência no EuPago, por isso nunca é repetida automaticamente e **bloqueia novos pedidos desse payable** até o webhook a completar, expirar, ou um operador a marcar como falhada.
- Um payable não passa de `max_attempts` tentativas. Recusas explícitas sem referência (chave errada, telemóvel inválido) deixam de contar ao fim de 15 minutos.

Os erros são `EupagoException`, com `->type` (`FailureType`) e, quando existe, `->attempt`:

```php
use Noop\Eupago\Enums\FailureType;
use Noop\Eupago\Exceptions\EupagoException;

try {
    $attempt = Eupago::mbway($order, 1250, $phone);
} catch (EupagoException $e) {
    match ($e->type) {
        FailureType::Rejected => /* recusado (ou valor/telemóvel inválido): podes tentar outra vez */,
        FailureType::Unknown => /* resultado incerto: não repitas, pode já existir referência */,
        FailureType::Blocked => /* pedido em curso, tentativa por resolver ou limite atingido */,
    };
}
```

## Receber pagamentos

Implementa `HandlesEupagoPayments` e regista-o no container:

```php
use Noop\Eupago\Contracts\HandlesEupagoPayments;
use Noop\Eupago\Data\ConfirmedPayment;
use Noop\Eupago\Models\PaymentAttempt;

class OrderPayments implements HandlesEupagoPayments
{
    public function canReceive(PaymentAttempt $attempt): bool
    {
        return $attempt->payable->isAwaitingPayment();
    }

    public function received(PaymentAttempt $attempt, ConfirmedPayment $payment): void
    {
        $attempt->payable->markPaid($payment->amountCents, $payment->paidAt);
    }

    public function expired(PaymentAttempt $attempt): void
    {
        // a tentativa terminou sem pagamento (expirou, foi cancelada ou falhou)
    }
}

// AppServiceProvider::register()
$this->app->bind(HandlesEupagoPayments::class, OrderPayments::class);
```

- `canReceive()` devolve `false` quando a aplicação já não aceita o pagamento (encomenda cancelada, por exemplo). A tentativa fica na mesma `paid` e é despachado `PaymentNeedsReview`: o dinheiro entrou e alguém tem de o tratar.
- `received()` e `expired()` correm dentro da transação do pacote: se lançarem uma exceção, tudo é revertido e o evento é repetido.
- Eventos (todos com `->attempt`, despachados depois do commit): `PaymentConfirmed`, `PaymentNeedsReview`, `PaymentAttemptExpired`.

## Webhook

No backoffice do EuPago, no canal, em **Webhooks 2.0**:

- Endpoint: `https://a-tua-app.pt/eupago/webhook` (tem de ser acessível publicamente por HTTPS).
- **Encriptar Webhook: Não**. Corpos encriptados são recusados com 422.
- Tipos de evento: Pagamento, Cancelamento, Expiração, Erro.
- Copia a chave criptográfica do canal para `EUPAGO_WEBHOOK_SECRET`.

Verificação: o cabeçalho `X-Signature` é o HMAC-SHA256 do corpo bruto, com essa chave, em base64. Respostas: `200` (guardado, ou duplicado), `401` assinatura inválida, `422` corpo não suportado, `503` segredo não configurado. O mesmo corpo é guardado uma só vez, por isso reenvios são inofensivos; o EuPago volta a tentar durante 24 h.

> **Nota:** o formato da assinatura segue a documentação do EuPago e o plugin oficial. Enquanto não for confirmado com uma entrega real na tua conta, verifica o primeiro webhook (na lista de eventos ou nos logs) depois de ires para produção.

Estados de um evento, úteis para quem opera:

| Estado | Significado |
| --- | --- |
| `pending` | À espera de processamento (ou a falhar e a ser repetido) |
| `processed` | Aplicado à tentativa |
| `rejected` | Pagamento com valor, referência ou entidade diferentes da tentativa; é comunicado ao exception handler da aplicação |
| `orphaned` | Nenhuma tentativa com esse identificador |

## Filament (opcional)

```php
->plugins([\Noop\Eupago\Filament\EupagoPlugin::make()])
```

Mostra as tentativas e os eventos. O acesso segue as quatro abilities de `config('eupago.permissions')`, que concedes via Gate ou um pacote de permissões:

| Chave | Ability por omissão |
| --- | --- |
| `view_attempts` | `view eupago attempts` |
| `view_events` | `view eupago events` |
| `reprocess_events` | `reprocess eupago events` |
| `abandon_attempts` | `abandon eupago attempts` |

Uma permissão `null` não concede nada. Duas ações:

- **Reprocessar** um evento `pending`, `rejected` ou `orphaned`: volta a `pending` e é processado de novo. Um evento `processed` nunca é reprocessado.
- **Dar como falhada** uma tentativa `unknown`: só depois de confirmares no backoffice do EuPago que não existe referência nem pedido para esse identificador. Liberta o payable e fecha a tentativa como as outras (`expired()` e `PaymentAttemptExpired`). Se afinal existir e for paga mais tarde, o webhook continua a marcá-la como paga.

## Testes

Nos testes da tua aplicação, `Eupago::fake()` impede qualquer pedido à rede mas mantém todas as regras; `pay()` e `expire()` simulam o webhook através do processamento real, por isso o teu handler e os eventos disparam como em produção:

```php
$fake = Eupago::fake();

$attempt = Eupago::multibanco($order, 1250);
$fake->pay($attempt);   // ou $fake->expire($attempt)

expect($order->fresh()->isPaid())->toBeTrue();
```

A suite do pacote: `vendor/bin/pest`. A suite contra a sandbox real (cria uma referência Multibanco e um pedido MB WAY reais na sandbox; ver `.env.sandbox.example`):

```bash
EUPAGO_SANDBOX_ENV=/caminho/para/ficheiro vendor/bin/pest --group=sandbox
```

## Fora do âmbito da v0.1

Cartão, débito direto, reembolsos, consulta do estado à API (polling) e webhooks encriptados.

## Licença

MIT.
