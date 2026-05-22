# Armazenamento modular

Esta pasta e um espelho dos JSONs ativos da Reformulacao, criado para reduzir conflitos em sincronizacao via GitHub.

Fonte de verdade atual:

- `data/ativos/cadastros_basicos.json`
- `data/ativos/fluxo_teste02.json`
- `data/ativos/arvore_estrutura.json`

Espelho gerado:

- `cadastros/`: cadastros compartilhados separados por catalogo e registro.
- `cronoanalises/`: uma cronoanalise por arquivo.
- `fluxos/`: um arquivo por linha com `drawflow_data`.
- `arvore_estrutura/`: itens, arvores, composicoes e conversoes separados por registro.
- `eventos/`: log JSONL append-only para auditoria e reconciliacao.
- `manifest.json`: contagens e data da ultima sincronizacao.

Pastas da calibradora nao entram neste processo.

Comandos:

```bash
php scripts/sincronizar_armazenamento_modular.php
php scripts/validar_armazenamento_modular.php
```

Fluxo recomendado antes de compartilhar:

```bash
git pull
php scripts/sincronizar_armazenamento_modular.php
php scripts/validar_armazenamento_modular.php
git add data public scripts
git commit -m "Atualiza dados"
git push
```
