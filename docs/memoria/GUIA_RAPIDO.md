# 🚀 GUIA DE NAVEGAÇÃO RÁPIDO

## 📍 Telas Principais

### 🎯 Fluxo da Linha (index.php)
**Onde**: Menu → "Fluxo da Linha"
- Visualização gráfica (Drawflow) dos postos em cadeia
- **Indicadores ao topo**:
  - 📊 Postos (quantidade)
  - ⏱️ Tempo de Ciclo (segundos)
  - 📈 Taxa de Produção (kg/min)
  - 👥 Pessoas Alocadas (total)
- **Ações nos nós**:
  - ⚙️ Atividades → Editar atividades do posto
  - 👥 Recursos → Alocar pessoas
  - 🗑️ Remover → Delete o posto

---

### 📋 Gerenciar Postos (postos.php)
**Onde**: Menu → "Configuração dos Postos" → "Postos"
- Tabela de todos os postos da linha
- **Ações**:
  - ➕ Adicionar novo posto
  - ⚙️ Atividades → Configurar atividades do posto
  - 👥 Recursos → Alocar pessoas
  - 🗑️ Remover → Delete o posto

---

### 🎬 Atividades do Posto (atividades_posto.php)
**Onde**: Menu → "Configuração dos Postos" → "Atividades do Posto"
**Ou**: Fluxo → Clique no nó → ⚙️ Atividades
- Tabela com todas as atividades do post
- **Colunas**:
  - Descrição
  - Unidade (dropdown)
  - Quantidade
  - Peso Unitário (auto-calculado)
  - Tempo Total
  - Tempo por Unidade (auto-calculado)
  - Tempo por Peso (auto-calculado)
- **Resumo**: Total de atividades, quantidade, tempo, tempo por peso médio
- **Botões**:
  - ➕ Adicionar Atividade
  - ✏️ Editar
  - 🗑️ Remover
  - 👥 Configurar Recursos → Vai para recursos.php
  - ← Voltar para Postos
  - ↑ Voltar ao Fluxo

---

### 👥 Recursos/Pessoas (recursos.php)
**Onde**: Menu → "Configuração dos Postos" → "Recursos/Pessoas"
**Ou**: Fluxo → Nó → 👥 Recursos
**Ou**: atividades_posto.php → 👥 Configurar Recursos
- Tabela de alocação de pessoas por posto
- **Colunas**:
  - Nome do Posto
  - Número de Pessoas (input numérico)
  - Status de alocação
- **Botões**:
  - ✓ Salvar (por linha/post)
  - ← Voltar para Postos
  - ↑ Voltar ao Fluxo

---

### ⚖️ Unidades (unidades.php)
**Onde**: Menu → "Unidades"
- Cadastro das unidades de medida padrão (ex: caixa, bandeja)
- **Campos**:
  - Nome (ex: "caixa")
  - Peso Padrão (kg) - input tipo `number`
  - Observação
- **Ações**:
  - ➕ Adicionar
  - ✏️ Editar inline
  - 🗑️ Remover

---

### 🚚 Transporte (transporte.php)
**Onde**: Menu → "Transporte"
- Gerenciar movimentações/transportes
- **Campos**:
  - Descrição
  - Tipo
  - Unidade
  - Quantidade
  - Tempo Total
  - Distância
  - Velocidade (calculada)

---

### 🧪 Teste de Cálculos (teste_calculos.php)
**Onde**: Menu → "Utilitários" → "🧪 Teste de Cálculos"
- Verificação de todos os cálculos
- Tabela detalhada com:
  - Todos os postos e atividades
  - Totalizações por linha
  - Indicadores calculados
  - Avisos se faltar configurar pessoas

---

## 📈 INDICADORES CALCULADOS

| Indicador | Fórmula | Unidade |
|-----------|---------|---------|
| **Tempo de Ciclo** | MAX(tempo_total de todas atividades) | segundos |
| **Taxa de Produção** | (total_kg / tempo_ciclo) × 60 | kg/min |
| **Total Kg Produzido** | SUM(quantidade × peso_unitário) | kg |
| **Pessoas Alocadas** | SUM(recursos.num_pessoas) | pessoas |

---

## 🔄 FLUXO DE TRABALHO RECOMENDADO

### Passo 1: Definir Unidades
1. Menu → Unidades
2. Adicione as unidades de medida (caixa, bandeja, etc.)
3. Configure peso padrão para cada

### Passo 2: Configurar Estrutura
1. Fluxo → Clique em "Adicionar Posto"
2. Adicione todos os postos da linha

### Passo 3: Definir Atividades
1. Fluxo → Nó → ⚙️ Atividades
2. Adicione atividades com:
   - Descrição
   - Unidade (escolha das definidas)
   - Quantidade
   - Tempo Total (segundos)
3. Sistema calcula automaticamente:
   - Peso Unitário (da unidade)
   - Tempo por Unidade
   - Tempo por Peso

### Passo 4: Alocar Pessoas
1. Fluxo → Nó → 👥 Recursos
2. Configure número de pessoas por posto
3. Ou use Menu → Recursos/Pessoas para alocar todos

### Passo 5: Validar Cálculos
1. Visualize no Fluxo os indicadores atualizados
2. Ou acesse Menu → Utilitários → Teste de Cálculos

---

## ⌨️ ATALHOS RECOMENDADOS

- **Editar Atividades**: Fluxo → Nó → ⚙️ Atividades
- **Alocar Pessoas**: Fluxo → Nó → 👥 Recursos
- **Ver Cálculos**: Menu → Teste de Cálculos
- **Voltar**: Use os botões ← em cada tela

---

## 🎯 DADOS PERSISTEM EM:

- `/data/linhas.json` - Postos, atividades, recursos
- `/data/unidades.json` - Definição de unidades
- `/data/transporte.json` - Dados de transporte
- `/data/categorias_atividade.json` - Categorias (futura)

**Tudo é salvo automaticamente após submeter formulários!**

---

## ⚠️ NOTAS IMPORTANTES

- **Peso Padrão** em Unidades deve ser > 0
- **Tempo Total** em Atividades é a unidade de tempo
- **Número de Pessoas** deve ser ≥ 1 para balanceamento
- **Taxa de Produção** é calculada em kg/minuto
- Todos os inputs têm **validação numérica** lado cliente + servidor

---

## 🐛 TROUBLESHOOTING

**P: Indicadores não atualizam?**  
R: Acesse Teste de Cálculos para debug. Verifique se atividades têm tempo_total > 0

**P: "Peso padrão deve ser um número"?**  
R: Em Unidades, use input tipo number. Valor deve ser > 0

**P: Não vejo pessoas alocadas?**  
R: Configure em Recursos/Pessoas. Deve ser ≥ 1

**P: Botão ← não volta?**  
R: Todos têm back buttons. Se não vê, acesse pelo Menu

---

**Status**: ✅ Sistema operacional. Todas as telas com navegação completa!
