# Sistema de Balanceamento

Este é um sistema web simples em PHP para modelar o fluxo de postos de trabalho em uma linha de produção.

## Estrutura do Projeto

- `public/index.php`: Tela principal com fluxo visual (Drawflow), resumos de config por posto e geral da linha.
- `public/tabela_posto.php`: Tela de cadastro detalhado para um posto específico.
- `public/unidades.php`: Gerenciamento de unidades (cadastros-base para listas prévias).- `public/postos.php`: Gerenciamento de postos.
- `public/categorias_atividade.php`: Placeholder para categorias de atividade.
- `public/tipos_item.php`: Placeholder para tipos de item.
- `public/configuracao_linha.php`: Placeholder para configuração da linha.
- `public/painel_balanceamento.php`: Placeholder para painel de balanceamento.
- `public/menu.php`: Menu lateral reutilizável com categorias.
## Funcionalidades

- Adicionar/remover postos no fluxo.
- Visualização interativa do fluxo com Drawflow.
- Resumo de detalhes em cada node (nome, unidade típica, tempo por item, peso).
- Edição detalhada de parâmetros em tela separada (unidade típica, quantidade, tempo total, tempo por item calculado, fator correlação).
- Indicadores visuais: Pessoas, Tempo de Ciclo, Taxa de Produção (simulados).
- Gerenciamento de unidades (cadastros-base).
- Configuração geral resumida (total de postos).

## Como Executar

1. Certifique-se de ter o PHP instalado (versão 7.4 ou superior).
2. Navegue até a pasta raiz do projeto.
3. Execute o servidor embutido do PHP: `php -S localhost:8000 -t public`
4. Abra o navegador e acesse: `http://localhost:8000`

## Tecnologias Utilizadas

- PHP (para lógica server-side e sessões)
- HTML/CSS (para estrutura e estilos)
- JavaScript/Drawflow (para visualização interativa do fluxo)

## Próximos Passos

- Integração com banco de dados.
- Adição de cálculos de balanceamento.
- Sistema de login.
- Melhorias na interface (ex: drag-and-drop avançado).