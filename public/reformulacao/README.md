# REFORMULACAO

Arquivos executaveis isolados da Reformulacao.

- `module_routes.php`: contratos oficiais de rotas, APIs, escopos e arquivos de dados dos modulos separados.
- `fluxo.php`: tela do modulo Editor BPM.
- `api_fluxo.php`: endpoint exclusivo do Editor BPM.
- `calibradora.php`: tela cod_12_05 da calibradora.
- `arvore_estrutura.php`: modulo Arvore de Estrutura, isolado do Editor BPM, postos, balanceamento e cronoanalise.
- `api_arvore_estrutura.php`: API somente leitura da Arvore de Estrutura para consulta por outros modulos.
- `tabela_arvore_estrutura.php`: tela somente leitura com a tabela correspondente ao contrato da API.

Estes modulos gravam somente em `data/reformulacao/`. O Editor BPM exige o escopo `REFORMULACAO_COD_12_05` nas operacoes de escrita do fluxo. A Arvore de Estrutura deve ser consultada por outros modulos apenas pela API oficial, evitando dependencia direta do arquivo JSON.

Integridade dos dados:

- As gravacoes JSON passam por `safe_storage.php`.
- Antes de substituir um JSON existente, o sistema cria backup em `data/reformulacao/_backups/`.
- A escrita usa arquivo temporario, trava (`LOCK_EX`) e validacao de JSON antes da substituicao.
- Se um JSON estiver invalido, ele e preservado como backup `.corrupt` antes de recriar a estrutura padrao.
- Nao editar `data/reformulacao/*.json` manualmente enquanto a tela estiver aberta.

Consulta oficial da Arvore de Estrutura:

- Endpoint: `api_arvore_estrutura.php?acao=listar_para_cronoanalise`
- Metodo: `GET`
- Escrita: nenhuma. O endpoint apenas le `data/reformulacao/arvore_estrutura.json`.
- Escopo: retorna somente arvores, vinculos e itens ativos.
- Uso previsto: consulta futura pela Cronoanalise, sem criar regras de Cronoanalise dentro da Arvore.
- Retorno: JSON com uma lista de objetos contendo `id_arvore`, `codigo_arvore`, `produto_pai_id`, `codigo_produto_pai`, `descricao_produto_pai`, `produto_filho_id`, `codigo_item`, `descricao_item`, `unidade`, `quantidade`, `fator_conversao` e `ativo`.
