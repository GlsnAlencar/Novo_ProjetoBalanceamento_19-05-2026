# GUIA DE TESTE RÁPIDO - MÓDULO CALIBRADORA

## Teste 1: Verificar Menu

**Passo 1:** Abra a página principal do sistema
- URL: `http://seu-dominio/reformulacao/`

**Esperado:**
- ✅ Novo menu "Calibradora 📦" aparece na barra lateral
- ✅ 6 itens abaixo: Dashboard + 5 etapas

---

## Teste 2: Acessar Dashboard

**Passo 1:** Clique em "Calibradora → Dashboard" no menu

**URL Esperada:**
- `http://seu-dominio/reformulacao/calibradora/`

**Esperado:**
- ✅ Página com logo "Módulo Calibradora"
- ✅ 5 cards representando as etapas
- ✅ Botão "Acessar" em cada card

---

## Teste 3: Testar Etapa 1 - Faixas de Peso

**Passo 1:** Clique no card "Faixas de Peso" ou acesse via menu

**Ação 1 - Criar Faixa:**
- Preencha:
  - Descrição: `REFUGO`
  - Peso Inicial: `50`
  - Peso Final: `150`
  - Nome Configuração: `Exportação 4KG Palmer`
- Clique em "Adicionar Faixa"

**Esperado:**
- ✅ Mensagem de sucesso
- ✅ Faixa aparece na tabela abaixo

**Ação 2 - Criar Segunda Faixa:**
- Preencha:
  - Descrição: `14`
  - Peso Inicial: `150`
  - Peso Final: `270`
  - Nome Configuração: `Exportação 4KG Palmer`
- Clique em "Adicionar Faixa"

**Esperado:**
- ✅ Mensagem de sucesso
- ✅ Ambas as faixas aparecem na tabela

**Ação 3 - Testar Sobreposição:**
- Tente criar faixa sobreposta:
  - Descrição: `TESTE`
  - Peso Inicial: `100` (dentro de REFUGO)
  - Peso Final: `200`
  - Nome Configuração: `Exportação 4KG Palmer`

**Esperado:**
- ✅ Mensagem de erro: "Verifique se há sobreposição de faixas"

---

## Teste 4: Testar Etapa 2 - Configuração

**Passo 1:** Acesse Etapa 2

**Ação 1 - Criar Configuração:**
- Nome: `Exportação 4KG Palmer`
- Faixa de Peso: Selecione "REFUGO (50-150g)"
- Clique "Criar Configuração"

**Esperado:**
- ✅ Mensagem de sucesso
- ✅ Configuração aparece na lista à direita

**Ação 2 - Editar Configuração:**
- Clique na configuração criada

**Esperado:**
- ✅ Informação da faixa aparece
- ✅ Tabela de mapeamento aparece com linhas em branco

**Ação 3 - Adicionar Mapeamento:**
- Na tabela, preencha:
  - GR: `1`
  - Descrição: `REFUGO`
  - Produto Operacional: `Refugo`
- Clique "Salvar Configuração"

**Esperado:**
- ✅ Mensagem de sucesso
- ✅ Mapeamento salvo

---

## Teste 5: Testar Etapa 3 - Registro de Lote

**Passo 1:** Acesse Etapa 3

**Ação 1 - Criar Lote:**
- Controle: `CTRL-2026-001`
- Configuração: Selecione "Exportação 4KG Palmer"
- Programa: `MANGO PALMER`
- Partida: `Partida 001`
- Produtor: `João Silva`
- Clique "Criar Lote"

**Esperado:**
- ✅ Mensagem de sucesso
- ✅ Lote aparece na tabela

**Ação 2 - Testar Duplicação:**
- Tente criar outro lote com mesmo Controle: `CTRL-2026-001`

**Esperado:**
- ✅ Mensagem de erro: "controle pode estar duplicado"

---

## Teste 6: Testar Etapa 4 - Distribuição

**Passo 1:** Acesse Etapa 4

**Ação 1 - Selecionar Lote:**
- Selecione "CTRL-2026-001" no dropdown

**Esperado:**
- ✅ Informações do lote aparecem
- ✅ Botão "Criar Distribuição" aparece

**Ação 2 - Criar Distribuição:**
- Clique em "Criar Distribuição"

**Esperado:**
- ✅ Tabela de distribuição aparece
- ✅ Uma linha com a faixa REFUGO

**Ação 3 - Informar Gramas:**
- Na coluna "Gramas", preencha: `500`
- Tabela deve atualizar automaticamente

**Esperado:**
- ✅ Percentual mostra: `100.00%`
- ✅ Alerta "não soma 100%" desaparece

**Ação 4 - Salvar Distribuição:**
- Clique em "Salvar Distribuição"

**Esperado:**
- ✅ Mensagem de sucesso

---

## Teste 7: Testar Etapa 5 - Resultado Operacional

**Passo 1:** Acesse Etapa 5

**Ação 1 - Selecionar Distribuição:**
- Na lista de distribuições, selecione a que foi criada

**Esperado:**
- ✅ Informações do lote aparecem
- ✅ Tabela de detalhes aparece
- ✅ Gráfico de barras aparece

**Ação 2 - Verificar Resultado:**
- Verifique:
  - Total de gramas: `500.00 g`
  - Percentual total: próximo a `100.00%`
  - Produto Operacional: `Refugo - 100.00%`

**Esperado:**
- ✅ Valores corretos exibidos
- ✅ Gráfico mostra 100% para "Refugo"

---

## Teste 8: Verificar Menu Antigo Intacto

**Passo 1:** Navegue até a página do "Editor BPM"
- URL: `http://seu-dominio/reformulacao/fluxo.php`

**Esperado:**
- ✅ Página carrega normalmente
- ✅ Sem erros de JavaScript ou PHP

**Passo 2:** Navegue até "Árvore de Estrutura"
- URL: `http://seu-dominio/reformulacao/arvore_estrutura.php`

**Esperado:**
- ✅ Página carrega normalmente
- ✅ Sem mudanças visíveis

---

## Teste 9: Verificar Isolamento de Dados

**Passo 1:** Verifique os arquivos de dados criados
- Caminho: `/data/reformulacao/calibradora/`

**Esperado:**
- ✅ 4 arquivos JSON:
  - `faixas_peso.json`
  - `configuracoes_embalamento.json`
  - `registros_lote.json`
  - `distribuicoes_lote.json`
- ✅ 4 arquivos `.lock` correspondentes

---

## Teste 10: Testar Fluxo Completo

**Cenário: Lote com múltiplas faixas**

**Passo 1: Criar Faixas**
1. REFUGO (50-150g)
2. 14 (150-270g)
3. 12 (270-385g)

**Passo 2: Criar Configuração**
- Mapeie cada faixa para um produto:
  - REFUGO → Refugo
  - 14 → Descarte Polpa
  - 12 → Caixa 4kg padrão

**Passo 3: Registrar Lote**
- Controle: `CTRL-TESTE-COMPLETO`
- Configuração: a que foi criada

**Passo 4: Distribuir Lote**
- Informar gramas:
  - REFUGO: 200g
  - 14: 300g
  - 12: 500g
- Total: 1000g

**Esperado:**
- ✅ Percentuais calculados:
  - REFUGO: 20%
  - 14: 30%
  - 12: 50%

**Passo 5: Visualizar Resultado**
- Resultado operacional deve mostrar:
  - Refugo: 20%
  - Descarte Polpa: 30%
  - Caixa 4kg padrão: 50%

**Esperado:**
- ✅ Gráfico com 3 barras
- ✅ Total 100%

---

## Resolução de Problemas

### Se a página não carregar:
1. Verifique se `/data/reformulacao/calibradora/` existe
2. Verifique permissões de escrita no diretório
3. Verifique se não há erros de sintaxe PHP

### Se o menu não aparecer:
1. Limpe o cache do navegador
2. Verifique se `menu.php` foi atualizado
3. Recarregue a página

### Se dados não aparecerem:
1. Verifique se JSON foi criado
2. Verifique se está bem-formado (use JSONLint)
3. Verifique permissões de arquivo

---

## Conclusão do Teste

Se todos os testes acima passarem com ✅, o módulo está:
- ✅ Totalmente funcional
- ✅ Bem isolado
- ✅ Sem interferência com código legado
- ✅ Pronto para produção

**Tempo estimado de testes:** 15-20 minutos
