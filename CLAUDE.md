# CLAUDE.md — FarolJuris

Aplicação Laravel que consulta, monitora e exporta comunicações processuais
(intimações, citações, editais) do **DJEN**, usando a Comunica API do PJe/CNJ.

Documentação detalhada em `docs/` (índice em `docs/README.md`). Ao mudar um
comportamento descrito lá, atualize o documento no mesmo commit.

## Stack

- PHP 8.3+ · Laravel 13 · Fortify (auth do Vue Starter Kit oficial)
- Vue 3 (`<script setup>`) + Inertia 3 + TypeScript + Tailwind 4 + shadcn-vue
- Vite+ (`vp`): build, Vitest (`vp test`), lint/format (Oxlint/Oxfmt via `vp check`)
- Rotas no front: **Ziggy** (`route('nome')`) nas nossas telas; o kit usa Wayfinder nas telas de auth/settings
- PostgreSQL + Redis (cache, sessão, filas) + Mailpit via **Laravel Sail**
- `maatwebsite/excel` (CSV/XLSX), `barryvdh/laravel-dompdf` (PDF)
- Pest 4 (back-end) · Vitest + Vue Test Utils + jsdom (front-end)

## Comandos

```bash
# Ambiente (Sail)
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev

# Filas e scheduler (em terminais separados)
./vendor/bin/sail artisan queue:work redis --tries=3
./vendor/bin/sail artisan schedule:work      # roda o scheduler continuamente
./vendor/bin/sail artisan schedule:test --name=djen:monitorar   # dispara o monitoramento agendado
./vendor/bin/sail artisan djen:monitorar [--alvo=ID] [--sync]   # monitora agora (fila ou síncrono)

# Testes
./vendor/bin/sail test           # Pest no PostgreSQL (banco `testing`)
DB_HOST=127.0.0.1 php artisan test   # Pest fora do Sail, com o pgsql do compose exposto
npm test                         # Vitest (vp test run)

# DJEN
php artisan djen:diagnostico [--sem-api]               # banco, cache, fila, e-mail e acesso à API deste servidor
php artisan djen:verificar-filtros --oab=X --uf=UF [--processo= --parte= --advogado=]  # confere na API real se cada filtro filtra
php artisan migrate --seed                            # usuária demo@faroljuris.test / password (não roda em produção)
php artisan admin:promover email@x.com [--remover]  # acesso a /admin (seeder: admin@faroljuris.test / password)
php artisan usuario:criar email@x.com [--nome=] [--admin]  # cria conta confirmada (cadastro pelo site fechado)
php artisan djen:capturar-fixture --oab=12345 --uf=AM   # salva resposta REAL (precisa de IP no Brasil)
php artisan djen:capturar-fixture --endpoint=comunicacao/tribunal --saida=tests/Fixtures/djen/tribunais.json

# Qualidade
vendor/bin/pint                  # formatação PHP
npm run check                    # lint + format (vp check)
npm run check:fix
npm run types:check              # vue-tsc
npm run build
composer ci:check                # tudo acima, como no CI
```

Ao final de cada fase: Pest, Vitest, Pint, `vp check`, `vue-tsc`, `npm run build`, commit.

## Fluxo de Git (obrigatório)

- Branch padrão: **`main`**. Nunca commitar direto nela.
- Toda mudança nasce em uma branch a partir da `main`, com prefixo pelo tipo e nome do recurso em
  kebab-case: `feature/nome-do-recurso`, `fix/descricao-do-bug`, `docs/assunto`,
  `refactor/...`, `chore/...`, `test/...`.
- Commits em **Conventional Commits**, em português: `tipo(escopo opcional): descrição no imperativo`.
  Tipos: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `style`, `perf`, `ci`, `build`.
  Ex.: `feat(admin): adiciona dashboard de buscas`, `fix(consulta): corrige paginação com debounce`.
  Mudança incompatível: `!` após o tipo e rodapé `BREAKING CHANGE:`.
- Concluído o desenvolvimento (testes e checagens verdes): abrir PR para a `main`, esperar o CI
  passar e fazer o merge. O título do PR também segue Conventional Commits.

## Convenções

- Código e nomes de domínio em português quando refletem o DJEN (`Comunicacao`,
  `Destinatario`, `numeroOab`); infraestrutura Laravel em inglês (tabelas
  `monitoring_targets`, `communications`, ...).
- **Nunca inventar campos da API.** Os nomes vêm da resposta real em
  `tests/Fixtures/djen/` (derivada de uma resposta real, anonimizada; ver o README de lá).
- Toda chamada ao CNJ passa por `App\Services\Djen\DjenClient`. Nada de `Http::`
  espalhado. Respostas viram DTOs tipados (sem arrays soltos).
- Controllers devolvem `Inertia::render` com props via API Resources; os tipos
  em `resources/js/types/` espelham esses Resources.
- Front: `resources/js/pages/{Consulta,Monitoramento,Exportacao}` e componentes
  reutilizáveis em `resources/js/components/`.
- Formulários com `useForm`; busca com `router.get(..., { preserveState, preserveScroll })`
  para manter os filtros na query string.
- Testes de front: `resources/js/**/*.test.ts`, importando de `vite-plus/test`.
- Testes de back: `Http::fake()` com a fixture real; `assertInertia` nas telas.

## DjenClient (`app/Services/Djen/`)

- `get($path, $query)` / `getCached($path, $query)` → `DjenResponse` (status,
  json decodificado, headers, `fromCache`). A tradução para DTOs fica em
  `Mapping\ComunicacaoApiMapper`; o `Gateways\DjenApiGateway` junta os dois.
- **Arquitetura**: o navegador só fala com o nosso backend; o backend chama o CNJ
  (decisão do produto, mesmo a API aceitando CORS). Servidor com IP no Brasil.
- Query normalizada: remove `null`/`''`, apara strings; a chave de cache
  (`djen:` + sha1) ignora a ordem dos parâmetros. Só sucessos vão para o cache.
- Retry (`retry.times` extras) em falha de conexão, 429 e 5xx, com backoff
  exponencial `sleep_ms × 2^(n-1)`, limitado por `max_sleep_ms`; em 429 usa o
  `Retry-After` (também limitado). 4xx não são retentados.
- Exceções (todas herdam de `DjenException`): `DjenUnavailableException`
  (timeout/conexão/5xx), `DjenRateLimitedException` (429, `retryAfterSeconds`),
  `DjenBlockedException` (403 — geobloqueio), `DjenBadRequestException` (outros
  4xx), `DjenUnexpectedResponseException` (2xx que não é JSON).
- Rate limiters em `AppServiceProvider`: `djen` (Jobs, `RateLimited('djen')`)
  e `djen-consulta` (por IP, `throttle:djen-consulta`).
- Testes: `Http::preventStrayRequests()` + `Sleep::fake()`; testes do gateway usam a
  fixture `tests/Fixtures/djen/comunicacao.json`.

## Resposta da Comunica API (confirmada)

- `GET /comunicacao?itensPorPagina=100` (sem filtros) → 200, `{status, message, count, items}`;
  `count` é limitado a 10000. Cabeçalhos: `x-ratelimit-limit: 20`, `x-ratelimit-remaining`,
  `cache-control: max-age=120`, `access-control-allow-origin: *` (o navegador pode chamar direto).
- Item: `id, data_disponibilizacao (Y-m-d), siglaTribunal, tipoComunicacao, nomeOrgao, idOrgao,
  texto, numero_processo (20 dígitos), meio (D|E), link|null, tipoDocumento, nomeClasse,
  codigoClasse (string), numeroComunicacao, ativo, hash, status, motivo_cancelamento,
  data_cancelamento, datadisponibilizacao (d/m/Y), meiocompleto, numeroprocessocommascara,
  destinatarios[{comunicacao_id, nome, polo A|P|D|T}], destinatarioadvogados[{id,
  comunicacao_id, advogado_id, created_at, updated_at, advogado{id, nome, numero_oab, uf_oab}}]`.
- Pegadinhas tratadas em `ComunicacaoApiMapper`: `texto` vem como texto puro (com `\n` e
  entidades HTML) ou HTML (fragmento, documento com `<!DOCTYPE>`/`<?xml?>`/`<style>`);
  destinatários e advogados repetidos; `numero_oab` alfanumérico (`11811N`, `20413-O`);
  advogado placeholder "SISTEMA DE CITAÇÃO E INTIMAÇÃO ELETRÔNICA" (OAB 99999999);
  `nomeClasse` com caixa quebrada (normalizado com `mb_strtoupper` e exibido no painel).
- Parâmetros enviados (`DjenApiGateway::parametros()`): `numeroOab, ufOab, nomeParte,
  nomeAdvogado, numeroProcesso, siglaTribunal, texto, meio, numeroComunicacao,
  dataDisponibilizacaoInicio/Fim (Y-m-d), pagina, itensPorPagina`. **Vistos num request real**
  (10/11/2026): `siglaTribunal, nomeParte, texto, dataDisponibilizacaoInicio/Fim, itensPorPagina`.
  Os demais vêm da documentação pública e são **conferidos no servidor** por
  `djen:verificar-filtros` (um nome errado é ignorado pela API, e o comando detecta).
- Tipo (`tipo`): um de `ConsultaRequest::TIPOS`. A API **ignora** `tipoComunicacao` (produção, 10/11/2026),
  então `DjenApiGateway::buscarPorTipo()` filtra aqui sobre até `djen.filtro_tipo.max_paginas` páginas
  de 100 (cache por página) e pagina localmente; além disso, `meta.filtroParcial` e aviso na tela. O filtro por OAB
  funciona (produção, 10/11/2026: `numeroOab=5055&ufOab=AM` → count 1001, não o teto de 10000).
- `meio`: `D` (Diário de Justiça Eletrônico Nacional) ou `E` (Plataforma Nacional de Editais),
  `ConsultaRequest::MEIOS`. Certidão em PDF: endpoint ainda não visto, não implementada.
- Tribunais: lista estática em `config/tribunais.php`, com siglas no formato de
  `siglaTribunal` (não há endpoint confirmado para isso).
- OAB pode ter letra (`App\Support\Oab`: `^\d{1,8}(-?[A-Z])?$`, normalizada em maiúsculas;
  no front, `normalizarOab`).

## Aba Consulta

- Rota pública `GET /consulta` (`consulta.index`, `throttle:djen-consulta`); `/` redireciona para ela.
- Query string do app: `oab, uf, parte, advogado, processo, tribunal, texto, meio, comunicacao, tipo,
  inicio, fim, pagina`. Atalhos de período em `lib/datas.ts`; filtros avançados recolhidos.
  `ConsultaRequest` normaliza (OAB via `Oab::normalizar`, só dígitos no processo, UF/tribunal em maiúsculas) e,
  em caso de erro, **não redireciona** (evita loop em link compartilhado): a página
  recebe `errors` e não consulta.
- `ConsultaController` → `ComunicacaoGateway::buscar(FiltroConsulta)` → `ResultadoConsulta`
  → `ResultadoConsultaResource`. Falhas viram a prop `erro {tipo, mensagem}`.
- Gateways (`DJEN_GATEWAY`): `api` (padrão; `DjenApiGateway`) e `fake`
  (`FakeComunicacaoGateway`, 45 comunicações fictícias, para desenvolver fora do Brasil).
- DTOs do app em `app/Data/Djen/` (contrato da interface, não da API).
- Inteiro teor: HTML sanitizado no servidor (`App\Support\HtmlSanitizer`, allowlist,
  sem atributos) antes do `v-html`. `linkDocumento` só aceita http(s).
- Front: `pages/Consulta/Index.vue` (layout `PublicLayout`), componentes `FiltroBusca`,
  `LinhaComunicacao` (lista compacta: data/tribunal/meio, processo/classe/órgão, tipo colorido
  via `TipoComunicacaoBadge` + `lib/comunicacao.ts`, ativo × passivo e OABs; o Monitoramento
  segue com `CardComunicacao`), `PainelComunicacao` (Sheet lateral com todos os dados e o teor em
  Formatado/Texto/Dados), `Paginacao`, `EstadoVazio`,
  `consulta/SkeletonResultados`. UF e tribunal usam `<select>` nativo.
- `FiltroBusca`: debounce próprio (700 ms) nos campos de texto; selects/datas buscam na
  hora. Toda busca imediata **cancela** o debounce pendente (senão ele atropelava a
  paginação), e o debounce não dispara se os filtros já são os da URL.

## Aba Monitoramento

- Rotas `auth`+`verified` em `/monitoramento` (`monitoramento.*`); `dashboard` e o
  `fortify.home` redirecionam para lá.
- Tabelas: `monitoring_targets` (tipo `oab|parte|processo` — enum `TipoAlvo`; único por
  usuário+tipo+valor+uf), `communications` (único em `hash`; jsonb para destinatários e
  advogados), `communication_target` (= model pivot `Alerta`; único por
  comunicação+alvo; `lida_em`, `notificada_em`) e `users.whatsapp` (opcional).
- Fluxo: Scheduler (`routes/console.php`, `dailyAt(djen.monitoring.time)` no fuso
  configurado) → `djen:monitorar` → um `MonitorarAlvoJob` por alvo ativo
  (`RateLimited('djen')` + `WithoutOverlapping`) → `MonitorarAlvo::executar()` busca os
  últimos N dias (paginando até `max_paginas`) e cria comunicações/alertas com
  `createOrFirst` → notifica os alertas com `notificada_em` nulo e os marca.
  Deduplicação garantida pelos índices únicos; um aviso que falhou é reenviado na próxima
  execução, um já enviado nunca se repete.
- Erros no job: 429 → `release(Retry-After)`; 403/4xx → `fail()`;
  indisponível → exceção (retry com `backoff` 60/300/900 s, 5 tentativas).
- Notificação `NovasComunicacoes`: `mail` sempre; `WhatsAppChannel` se o usuário tiver
  número. O canal delega a `WhatsAppDriver` (só `log` implementado; registrar novos
  drivers em `AppServiceProvider::registerWhatsApp()`).
- LGPD: excluir alvo, "excluir todos os meus dados" (com senha) e excluir a conta apagam
  alvos/alertas; comunicações órfãs são apagadas na hora (`Communication::apagarOrfas()`)
  e diariamente por `model:prune` (`Communication::prunable()`).
- Traduções dos e-mails do Laravel em `lang/pt_BR.json`.
- Testes usam `Tests\Support\GatewayEmMemoria` para simular comunicações novas entre execuções.
- **Datas de disponibilização são datas de calendário**: compare `toDateString()`, nunca
  instantes (o fuso de Manaus excluía o 1º dia da janela).

## Aba Exportação

- Rotas `auth`+`verified` em `/exportacoes` (`exportacoes.index|store|download|destroy`);
  `store` com `throttle:10,1`. Tabela `exports` = model `Exportacao` (`origem`
  consulta|monitoramento, `formato` csv|xlsx|pdf, `status`
  pendente|processando|concluida|falhou, `parametros` jsonb, `arquivo` no disco privado).
- Seleção no front: `useSelecao` (chave → página; sobrevive à paginação com
  preserveState; limpa quando os filtros mudam), `BarraExportacao` (visitante vê convite
  para entrar), `CardComunicacao` com `selecionavel` + `v-model:selecionada`,
  `useExportar` (POST + toast de erro).
- `parametros`: Consulta → `filtros` (query da Consulta) + `selecao` `[{pagina, hashes}]`
  ou `todos`; Monitoramento → `filtros {alvo, status}` + `selecao` (ids de alertas) ou
  `todos`. `ColetorExportacao` rebusca as páginas da Consulta (cache do DjenClient) e
  revalida a posse dos alertas ao gerar. Limite `djen.exportacao.max_itens`.
- Até `limite_sincrono` itens selecionados: gera na hora e devolve
  `Inertia::location(download)` (o "409" no console é o mecanismo do Inertia). "Todos" ou
  seleção maior: `GerarExportacaoJob` (RateLimited só p/ Consulta) + e-mail
  `ExportacaoConcluida`; a página usa `usePoll` enquanto `emAndamento`.
- Arquivos: `PlanilhaComunicacoes` (maatwebsite/excel v4; CSV com `;` e BOM; células que
  começam com `= + - @` ganham `'` contra injeção de fórmula; texto truncado em 32 mil
  caracteres) e PDF via dompdf com a view `resources/views/exports/comunicacoes.blade.php`
  (não é tela; tudo escapado; `enable_font_subsetting` — sem ele o PDF passa de 1 MB).
- LGPD: arquivo apagado junto com o registro (evento `deleting`), `model:prune` diário após
  `dias_retencao`, e `User::deleting` apaga as exportações por Eloquent (a FK em cascata não
  dispara eventos e deixaria arquivos órfãos).
- `ComunicacaoData::textoPuro()` descarta o conteúdo de `<script>`/`<style>` (o
  `strip_tags` sozinho mantinha o código como texto).

## Área administrativa e rastreio

- `/admin` (`admin.*`): `auth` + `verified` + `can:admin` (gate sobre `users.is_admin`, dado só por
  `admin:promover`). Telas `Admin/{Dashboard,Buscas,Atividade,Usuarios,ChamadasCnj}`; período na URL
  (`App\Support\Admin\Periodo`: hoje|7d|30d|90d|personalizado, dias no fuso de exibição).
- Rastreio via `App\Services\Rastreio\Rastreador` (nunca lança; `DJEN_RASTREIO_ATIVO`):
  `search_logs` (ConsultaController), `djen_api_calls` (evento `ChamadaDjenConcluida` do DjenClient;
  origem pelo `Context`, `Rastreador::origem()`), `activity_logs` (tipos em `TipoEvento`; middleware
  `RegistrarAcesso` para páginas vistas, listener de auth, controllers e jobs).
- LGPD: `Origem` mascara IP e gera `visitante` (HMAC com APP_KEY); sem query string; `dados` só com ids e
  números; `model:prune` após `DJEN_RASTREIO_DIAS_RETENCAO` (180).
- Métricas em `MetricasAdmin` (SQL Postgres, `AT TIME ZONE`, `percentile_cont`). Robôs fora das contagens.
- Gráficos próprios em SVG (`components/admin/*`), cores `--viz-*` validadas; sempre com visão em tabela.
- Nos testes `Unit` o rastreio fica desligado (`tests/Pest.php`): eles não limpam o banco.
- **Cadastro fechado** por padrão (`config('app.cadastro_aberto')`, `APP_CADASTRO_ABERTO=false`):
  `registerView` → 404, `CreateNewUser` → 403, prop compartilhada `cadastroAberto` esconde
  "Criar conta" (PublicLayout, Login). Contas pelo comando `usuario:criar`. No `phpunit.xml` o
  cadastro fica aberto (telas do kit); `tests/Feature/Auth/CadastroFechadoTest.php` fecha por config.
- Detalhes: `docs/11-administracao-e-rastreio.md`.

## Interface, idioma e erros

- Idioma padrão `pt_BR` (`config/app.php`). Traduções do servidor em `lang/pt_BR/*.php`
  (validação, auth, senhas, paginação) e `lang/pt_BR.json` (e-mails, Fortify, toasts).
  As telas do kit (auth, settings, 2FA, passkeys) e os textos de leitor de tela do
  `components/ui` foram traduzidos no próprio Vue. Novos textos: escreva em português.
- Identidade **FarolJuris** (nome sempre junto): `AppLogoIcon` é o farol (torre em `currentColor`,
  luz sempre âmbar; base em gancho = J de Juris); `MarcaNome` escreve "Farol" forte + "Juris" em
  `--marca-juris`; `AppLogo`/`PublicLayout` usam o quadrado `bg-marca`. Tokens em `resources/css/app.css`:
  `--marca-marinho #0f2a44`, `--marca-ambar #f5a524`; claro = primária marinho, escuro = fundo marinho
  profundo e primária âmbar. Favicons em `public/`; logos completas (SVG) em `branding/faroljuris/`.
  Guia: `docs/12-identidade-visual.md`.
- Erros: `bootstrap/app.php` renderiza a página Inertia `Erro` (layout público) para
  403/404/429/500/503; 419 volta com toast; JSON continua JSON; 500 com `APP_DEBUG=true`
  mostra o erro detalhado. O 429 da Consulta mostra o `Retry-After`.
- `djen:monitorar --sync` isola falhas por alvo (um SMTP fora do ar não interrompe os
  demais; o aviso fica pendente e sai na próxima execução) e retorna falha no fim.

## Deploy

- Produção: **https://faroljuris.com.br**, publicada pelo **Ploi** num VPS da Hostinger que
  hospeda outros sites. Guia `docs/13-deploy-ploi.md`; script de deploy do Ploi em
  `deploy/ploi/deploy-script.sh` (mudou o processo de deploy? atualize os dois).
- Servidor **com IP no Brasil**. Arquivos em `deploy/`: `deploy.sh` (manutenção, pull,
  `composer --no-dev`, build, migrate, `optimize`, `queue:restart`, diagnóstico),
  `nginx/faroljuris.conf` (validado com `nginx -t`), `supervisor/faroljuris-worker.conf`,
  `cron/faroljuris` e `env.production.example`.
- O app funciona com `php artisan optimize` (sem `env()` fora de `config/`). Health
  check em `/up`.

## Configuração

`config/djen.php` (variáveis `DJEN_*` no `.env.example`): base URL, timeout,
retry/backoff, itens por página, TTL do cache (600 s), rate limits (jobs e por
IP), horário/fuso/janela/paginação/limite de alvos do monitoramento, gateway
(`DJEN_GATEWAY=api|fake`), exportação (disco, limite síncrono, máximo de itens, dias de
retenção) e driver do WhatsApp (`log`).

## Decisões de arquitetura

- **Vue Starter Kit** (Laravel 13), não Breeze — Breeze foi descontinuado no Laravel 12.
- **Ziggy + Wayfinder convivem**: Wayfinder é exigido pelas telas do kit;
  Ziggy foi pedido para as telas do domínio.
- **Larastan removido**: dependia de download de dist pelo GitHub, bloqueado no
  ambiente de desenvolvimento em nuvem. Pode voltar depois.
- **Fonte local** (`@fontsource-variable/plus-jakarta-sans`) em vez de Bunny Fonts:
  build sem dependência externa.
- **PostgreSQL em todo lugar**: dev (Sail), testes (banco `testing`, criado pelo
  Sail) e CI (service `postgres:18-alpine`). Padrão do `config/database.php` é
  `pgsql`. Pode usar recursos do Postgres (jsonb, índices parciais, `ILIKE`).
- `withoutVite()` no `TestCase`: testes de back não dependem do build.
- **Geobloqueio do CNJ**: a Comunica API responde 403 (CloudFront) para IPs
  fora do Brasil. Produção e workers de monitoramento precisam de IP brasileiro.

## Ambiente de desenvolvimento em nuvem (Claude Code)

- Composer: a API do GitHub (zipballs) é bloqueada; instale com
  `composer config -g use-github-api false` e `--prefer-source`.
- `fonts.bunny.net` e a Comunica API não são acessíveis daqui.
- O daemon do Docker não sobe sozinho: `dockerd &` e depois `docker compose up -d pgsql`
  (a imagem do app do Sail não builda aqui por hosts bloqueados; testes rodam com
  `DB_HOST=127.0.0.1 php artisan test`).
- Prints com Playwright: use `--lang=pt-BR` no Chromium (o `<input type=date>` segue o
  idioma do navegador).
