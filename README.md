# FarolJuris

Consulta, monitoramento e exportação de comunicações processuais (intimações,
citações e editais) publicadas no **DJEN – Diário de Justiça Eletrônico
Nacional**, a partir da Comunica API do PJe (CNJ).

![Consulta](docs/img/consulta.png)

| Aba               | Acesso  | O que faz                                                                                                                                                                |
| ----------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Consulta**      | pública | Busca por OAB + UF, parte, advogado, processo (máscara CNJ), tribunal e período. Filtros na URL (link compartilhável), paginação, inteiro teor em modal.                 |
| **Monitoramento** | login   | Alvos por OAB, parte ou processo. Verificação diária agendada, aviso por e-mail (e WhatsApp, plugável) só das comunicações novas, painel de lidas/não lidas e histórico. |
| **Administração** | admin   | Área interna (`/admin`): buscas feitas, visitantes, contas, atividade, termos e tribunais mais buscados e a saúde da API do CNJ, com dashboard e filtros por período.    |
| **Exportações**   | login   | Seleção em lote (Consulta ou Monitoramento) para CSV, Excel ou PDF. Lotes grandes vão para a fila e avisam por e-mail.                                                   |

> [!IMPORTANT]
> O FarolJuris chama a Comunica API do CNJ **pelo backend**, e a API **bloqueia IPs fora
> do Brasil**. Rode o servidor (e os workers) no Brasil. Para desenvolver fora do
> país, use `DJEN_GATEWAY=fake` (45 comunicações fictícias).

## Sumário

- [Stack](#stack)
- [Rodando do zero (Sail)](#rodando-do-zero-sail)
- [Testes e qualidade](#testes-e-qualidade)
- [Configuração](#configuração)
- [Monitoramento](#monitoramento)
- [Exportação](#exportação)
- [Dados reais e verificação dos filtros](#dados-reais-e-verificação-dos-filtros)
- [WhatsApp](#whatsapp)
- [Área administrativa](#área-administrativa)
- [LGPD](#lgpd)
- [Deploy em produção](#deploy-em-produção)
- [Solução de problemas](#solução-de-problemas)

## Stack

- PHP 8.3+ · Laravel 13 · Fortify (Vue Starter Kit oficial)
- Vue 3 + Inertia 3 + TypeScript + Tailwind 4 + Vite+ · Ziggy para rotas nomeadas
- PostgreSQL 18 · Redis (cache, sessão, filas) · Mailpit (e-mail local) via Laravel Sail
- `maatwebsite/excel` (CSV/XLSX) · `barryvdh/laravel-dompdf` (PDF)
- Pest 4 (back-end) · Vitest + Vue Test Utils (front-end)

Documentação completa (arquitetura, integração com o CNJ, cada aba, dados/LGPD,
front-end, desenvolvimento e operação): [`docs/`](docs/README.md). Resumo técnico
para quem mexe no código: [CLAUDE.md](CLAUDE.md).

## Rodando do zero (Sail)

Requisitos: Docker e Git.

```bash
git clone git@github.com:samuelsonbrito/FarolJuris.git
cd FarolJuris
cp .env.example .env
```

Instale as dependências PHP com um container descartável:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs
```

Suba os serviços e prepare o app:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Em outros dois terminais, a fila e o agendador:

```bash
./vendor/bin/sail artisan queue:work redis
```

```bash
./vendor/bin/sail artisan schedule:work
```

Pronto:

- App: http://localhost (a página inicial é a Consulta)
- E-mails: http://localhost:8025 (Mailpit)
- Login de demonstração: `demo@faroljuris.test` / `password`, já com dois alvos
- Administrador: `admin@faroljuris.test` / `password` (acesso a `/admin`)

O `.env.example` vem com `DJEN_GATEWAY=api` (dados reais; exige estar no Brasil). Fora do
Brasil, troque para `fake`. Para gerar os primeiros alertas da conta de demonstração
(com `fake`, os alvos dela têm resultados):

```bash
./vendor/bin/sail artisan djen:monitorar --sync
```

<details>
<summary>Sem Docker</summary>

Com PHP 8.3+, Composer, Node 22+, PostgreSQL e Redis instalados: ajuste no `.env`
`DB_HOST=127.0.0.1`, `REDIS_HOST=127.0.0.1` e `MAIL_MAILER=log`, crie os bancos
`faroljuris` e `testing` e rode `composer install`, `php artisan key:generate`,
`php artisan migrate --seed`, `npm install` e `composer dev`.
</details>

## Testes e qualidade

Os testes de back-end usam PostgreSQL (banco `testing`, criado pelo container `pgsql`).

```bash
./vendor/bin/sail test
```

```bash
./vendor/bin/sail npm test
```

Tudo o que o CI roda (Pint, lint/format, tipos, Vitest, build, Pest):

```bash
./vendor/bin/sail composer ci:check
```

## Configuração

Tudo em `config/djen.php`, com variáveis no `.env`:

| Variável                                                               | Padrão                                  | Para quê                                                     |
| ---------------------------------------------------------------------- | --------------------------------------- | ------------------------------------------------------------ |
| `DJEN_GATEWAY`                                                         | `api`                                   | `api` = Comunica API (via backend); `fake` = dados fictícios |
| `DJEN_BASE_URL`                                                        | `https://comunicaapi.pje.jus.br/api/v1` | Homologação: `https://hcomunicaapi.cnj.jus.br/api/v1`        |
| `DJEN_TIMEOUT` / `DJEN_CONNECT_TIMEOUT`                                | 20 / 5 s                                | Timeouts das chamadas                                        |
| `DJEN_RETRY_TIMES` / `DJEN_RETRY_SLEEP_MS` / `DJEN_RETRY_MAX_SLEEP_MS` | 3 / 500 / 10000                         | Novas tentativas com backoff (conexão, 429, 5xx)             |
| `DJEN_ITENS_POR_PAGINA`                                                | 20                                      | Resultados por página na Consulta                            |
| `DJEN_CACHE_TTL`                                                       | 600 s                                   | Cache das consultas                                          |
| `DJEN_RATE_LIMIT_PER_MINUTE`                                           | 15                                      | Chamadas ao CNJ por minuto feitas pelos jobs                 |
| `DJEN_PUBLIC_RATE_LIMIT_PER_MINUTE`                                    | 30                                      | Consultas públicas por minuto, por IP                        |
| `DJEN_MONITORING_TIME` / `DJEN_MONITORING_TIMEZONE`                    | `07:00` / `America/Manaus`              | Horário do monitoramento diário                              |
| `DJEN_MONITORING_LOOKBACK_DAYS`                                        | 3                                       | Quantos dias para trás cada verificação olha                 |
| `DJEN_MONITORING_MAX_ALVOS`                                            | 50                                      | Alvos por usuário                                            |
| `DJEN_EXPORT_LIMITE_SINCRONO`                                          | 100                                     | Até quantos itens a exportação é gerada na hora              |
| `DJEN_EXPORT_MAX_ITENS`                                                | 2000                                    | Máximo de itens por exportação                               |
| `DJEN_EXPORT_DIAS_RETENCAO`                                            | 7                                       | Dias até apagar arquivos exportados                          |
| `DJEN_WHATSAPP_DRIVER`                                                 | `log`                                   | Driver do WhatsApp (só `log` por enquanto)                   |

## Monitoramento

![Monitoramento](docs/img/monitoramento.png)

1. Cadastre um alvo (OAB + UF, nome da parte ou número do processo).
2. Todo dia, no horário configurado, o agendador enfileira uma verificação por alvo
   ativo, que busca as comunicações dos últimos N dias.
3. Comunicações são guardadas uma única vez (índice único no `hash`) e cada uma gera no
   máximo um alerta por alvo. O e-mail lista só o que ainda não foi avisado; uma nova
   execução não repete avisos.

Para disparar a verificação agendada sem esperar o horário (critério de aceite):

```bash
./vendor/bin/sail artisan schedule:test --name=djen:monitorar
```

Use `--name=djen:monitorar`: o filtro compara com o comando, não com a descrição. O
e-mail aparece no Mailpit depois que o `queue:work` processar a fila. Rode o mesmo
comando de novo: nenhum aviso repetido.

## Exportação

![Exportações](docs/img/exportacoes.png)

Com login, marque as comunicações na Consulta ou no Monitoramento (ou "Selecionar
página") e use a barra **Exportar**:

- até 100 itens selecionados: o download começa na hora;
- mais que isso, ou **Exportar todas**: o arquivo é gerado na fila e chega um e-mail.
  A aba Exportações atualiza o status sozinha.

O CSV usa `;` e BOM (abre direto no Excel em português). Células que começam com
`=`, `+`, `-` ou `@` são gravadas como texto, contra injeção de fórmula.

## Dados reais e verificação dos filtros

A resposta da API foi mapeada a partir de uma resposta real (fixture anonimizada em
`tests/Fixtures/djen/`). Os nomes dos parâmetros de filtro ainda precisam ser
confirmados contra a API real. No servidor (no Brasil), depois do deploy:

```bash
php artisan djen:verificar-filtros --oab=SUA_OAB --uf=UF --processo=UM_PROCESSO --parte="UM NOME"
```

O comando faz poucas chamadas pequenas e confere se cada filtro realmente filtra. Se
algum aparecer como **FALHOU**, a API está ignorando aquele parâmetro: ajuste o nome em
`DjenApiGateway::parametros()`. Para atualizar a fixture com uma resposta nova:
`php artisan djen:capturar-fixture` (anonimize antes de commitar).

## WhatsApp

As notificações usam um `WhatsAppChannel` que delega a uma interface
(`App\Notifications\WhatsApp\WhatsAppDriver`). Só o driver `log` existe: ele registra a
mensagem (com o número mascarado) sem enviar. Para Evolution API, Z-API ou Meta Cloud
API, implemente a interface e registre o driver em
`AppServiceProvider::registerWhatsApp()`.

## Área administrativa

![Administração](docs/img/admin.png)

Em `/admin`, só para administradores: buscas por dia, visitantes únicos, resultado e tempo
das buscas, termos, tribunais e UFs mais buscados, dispositivos e origem do tráfego,
contas e uso de cada uma, trilha de atividade e cada chamada à API do CNJ (latência,
falhas e limite restante). Para dar acesso a alguém:

```bash
php artisan admin:promover pessoa@exemplo.com
```

O **cadastro pelo site fica fechado** por padrão (`APP_CADASTRO_ABERTO=false`): o link
"Criar conta" some e as contas são criadas pelo administrador:

```bash
php artisan usuario:criar pessoa@exemplo.com --nome="Nome" [--admin]
```

O rastreio mascara o IP, pseudonimiza o visitante e apaga os registros após 180 dias
(`DJEN_RASTREIO_DIAS_RETENCAO`). Detalhes em
[docs/11-administracao-e-rastreio.md](docs/11-administracao-e-rastreio.md).

## LGPD

- Guardamos só o necessário para o monitoramento: alvos, comunicações encontradas,
  estado de leitura e, se informado, o WhatsApp.
- O usuário exclui um alvo, todos os dados de monitoramento (com senha) ou a conta
  inteira. Comunicações que nenhum alvo usa mais são apagadas na hora.
- Uma limpeza diária (`model:prune`) remove comunicações órfãs e exportações expiradas,
  junto com os arquivos.
- O driver de WhatsApp `log` mascara o número.
- O rastreio da área administrativa guarda IP mascarado e visitante pseudonimizado, e é
  apagado após 180 dias.

## Deploy em produção

Produção: **https://faroljuris.com.br**. Com o painel **Ploi** (VPS da Hostinger, junto de
outros sites), siga [docs/13-deploy-ploi.md](docs/13-deploy-ploi.md). Os arquivos de
`deploy/` abaixo são para configurar um VPS sem painel.

> [!WARNING]
> **O servidor precisa ter IP no Brasil.** A Comunica API responde `403` para acessos
> de fora do país, e isso vale para o site e para os jobs de monitoramento. Use um
> provedor com região brasileira (por exemplo, AWS `sa-east-1`, em São Paulo).

Em uma VPS (Ubuntu) com PHP 8.3+ (FPM), Composer, Node 22+, PostgreSQL, Redis, Nginx e
Supervisor, os arquivos de referência ficam em [`deploy/`](deploy):

| Arquivo                                    | Uso                                                                                                         |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| `deploy/env.production.example`            | Valores de produção do `.env` (sem segredos)                                                                |
| `deploy/nginx/faroljuris.conf`             | Site no Nginx (HTTPS com certbot)                                                                           |
| `deploy/supervisor/faroljuris-worker.conf` | Workers da fila                                                                                             |
| `deploy/cron/faroljuris`                   | Agendador (`schedule:run` a cada minuto)                                                                    |
| `deploy/deploy.sh`                         | Atualização: manutenção, `git pull`, dependências, build, migrations, caches, `queue:restart` e diagnóstico |

Primeira instalação, resumida:

1. Clone em `/var/www/faroljuris`, copie `.env.example` para `.env`, aplique
   `deploy/env.production.example` e defina senhas e SMTP.
2. `composer install --no-dev -o`, `php artisan key:generate`, `npm ci`,
   `npm run build`, `php artisan migrate --force`, `php artisan optimize`.
3. Instale o Nginx, o Supervisor e o cron com os arquivos de `deploy/`.
4. Confira o ambiente:

```bash
php artisan djen:diagnostico
```

O diagnóstico verifica banco, cache, fila, e-mail e se **este servidor** consegue
falar com a Comunica API; se a API responder `403`, o servidor está fora do Brasil.
O endpoint `/up` serve de health check.

Para as próximas atualizações:

```bash
./deploy/deploy.sh main
```

## Solução de problemas

| Sintoma                                                          | Causa provável                                                       |
| ---------------------------------------------------------------- | -------------------------------------------------------------------- |
| Consulta mostra "O CNJ recusou o acesso a partir deste servidor" | IP fora do Brasil (`403`). Rode `php artisan djen:diagnostico`.      |
| A busca traz resultados que não batem com o filtro               | Nome de parâmetro errado: rode `php artisan djen:verificar-filtros`. |
| E-mail do monitoramento ou da exportação não chega               | `queue:work` parado, ou `MAIL_MAILER` de teste.                      |
| `schedule:test` diz "No matching scheduled command found"        | Use `--name=djen:monitorar`.                                         |
| Exportação fica "Na fila"                                        | `queue:work` parado.                                                 |

## Licença

MIT.
