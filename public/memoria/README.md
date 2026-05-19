# MEMORIA

Area reservada para documentar a separacao dos arquivos legados.

Os executaveis da Memoria permanecem nos caminhos atuais para evitar quebrar links e includes existentes, principalmente:

- `public/memoria/FLUXO_TESTE02.php`
- `public/memoria/fluxo_teste01.php`
- `public/memoria/index.php`

A Reformulacao fica em `public/reformulacao/`.

Integridade dos dados:

- As gravacoes antigas passam por `public/memoria/data_store.php`.
- Antes de substituir um JSON existente, o sistema cria backup em `data/memoria/_backups/`.
- A escrita usa arquivo temporario, trava (`LOCK_EX`) e validacao de JSON antes da substituicao.
- Se um JSON estiver invalido, ele e preservado como backup `.corrupt` antes de recriar uma estrutura vazia.
- Nao editar `data/memoria/*.json` manualmente enquanto telas antigas estiverem abertas.
