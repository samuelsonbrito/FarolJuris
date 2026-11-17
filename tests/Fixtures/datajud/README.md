# Fixtures da API Pública do DataJud (CNJ)

Respostas **reais** de `POST https://api-publica.datajud.cnj.jus.br/api_publica_tjam/_search`
(capturadas em 16 e 17/11/2026):

- `processos.json`: dois processos do TJAM (`match_all`, `size: 2`). O DataJud não traz partes nem
  advogados; por cautela, só os números dos processos foram trocados por fictícios (em `id` e
  `numeroProcesso`). Nomes de chaves, tipos, formatos de data (`dataAjuizamento` em `YmdHis`,
  `dataHora` em ISO), órgãos, classes, assuntos e movimentos foram preservados.
- `processo.json`: o primeiro processo de `processos.json`, como responde a busca por `numeroProcesso`.
- `sobrecarga-429.json`: resposta 429 do DataJud sobrecarregado (`es_rejected_execution_exception`).
