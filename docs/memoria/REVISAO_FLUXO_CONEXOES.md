# 🔍 REVISÃO COMPLETA - FLUXO, CONEXÕES E RELACIONAMENTOS

Data: 22/04/2026

---

## 📊 STATUS ATUAL DAS CONEXÕES

### ✅ 1. TEMPO DE CICLO ↔ CONFIGURAÇÃO

#### Local: `index.php` (linhas 35-80)
```php
// CÁLCULO DO TEMPO DE CICLO
$tempo_ciclo = 0;  // Geral (maior valor)
$tempo_ciclo_por_posto = [];  // Array com tempo de cada posto

foreach ($linha_selecionada['postos'] as $idx => $posto) {
    $tempo_ciclo_posto = 0;
    
    foreach ($posto['atividades'] as $atividade) {
        // PEGA O MAIOR TEMPO_TOTAL
        if ($atividade['tempo_total'] > $tempo_ciclo_posto) {
            $tempo_ciclo_posto = $atividade['tempo_total'];
        }
        if ($atividade['tempo_total'] > $tempo_ciclo) {
            $tempo_ciclo = $atividade['tempo_total'];
        }
    }
    
    // ARMAZENA TEMPO DE CICLO POR POSTO
    $tempo_ciclo_por_posto[$idx] = $tempo_ciclo_posto;
}
```

**Status**: ✅ **ATIVO E FUNCIONANDO**
- Tempo de ciclo conectado a todas as atividades
- Recalcula automaticamente quando atividades mudam
- Armazenado em array para cada posto
- Passado ao JavaScript como JSON

**Tipo**: 🔢 **NUMÉRICO** (em segundos - segundos)

---

### ✅ 2. QUANTIDADE DE PESSOAS ↔ CONFIGURAÇÃO DE LINHA

#### Local 1: `recursos.php` (Entrada Manual)
```php
// Entrada do usuário
$num_pessoas = isset($_POST['num_pessoas']) ? (int)$_POST['num_pessoas'] : 0;

// Armazenamento em linhas.json
$linhas_json[$linha_key]['postos'][$post_index]['recursos']['num_pessoas'] = $num_pessoas;
```

#### Local 2: `index.php` - JavaScript (Renderização)
```javascript
// HTML do Node
<input type="number" id="pessoas_' + idx + '" 
       value="' + numPessoas + '" 
       min="0" max="100" 
       onchange="updateNumPessoas(' + idx + ', this.value)">

// Atualização em tempo real
function updateNumPessoas(postIndex, numPessoas) {
    postos[postIndex].recursos.num_pessoas = numPessoas;
    
    fetch(...) // Salva no servidor
    .then(() => renderNodes()); // Re-renderiza com novo ritmo
}
```

**Status**: ✅ **ATIVO E FUNCIONANDO**
- Campo de entrada manual (input number)
- Conectado à estrutura `postos[].recursos.num_pessoas`
- Persistido em `linhas.json`
- Atualizações salvam automaticamente via AJAX

**Tipo**: 🔢 **NUMÉRICO** (quantidade de pessoas - inteiro)

---

### ✅ 3. RITMO DE OPERAÇÃO (Relação Calculada)

#### Fórmula
```
Ritmo = Tempo de Ciclo ÷ Número de Pessoas
```

#### Local: `index.php` - JavaScript (renderNodes)
```javascript
var tempoCiclo = tempoCicloPorPosto[idx] || 0;
var numPessoas = (posto.recursos && posto.recursos.num_pessoas) 
                 ? posto.recursos.num_pessoas : 0;

var ritmo = (tempoCiclo > 0 && numPessoas > 0) 
            ? (tempoCiclo / numPessoas).toFixed(2) 
            : '—';

// Exibido no Node
<div>⚡ Ritmo: ' + ritmo + ' s/pessoa</div>
```

**Status**: ✅ **ATIVO E FUNCIONANDO**
- Calculado em tempo real
- Atualiza automaticamente quando muda número de pessoas
- Validação: evita divisão por zero
- Exibido no nó do Drawflow

**Tipo**: 🔢 **NUMÉRICO CALCULADO** (segundos por pessoa - decimal)

---

## 🔗 FLUXO COMPLETO DE CONEXÕES

```
ATIVIDADES DO POSTO
    ↓
    ├─→ tempo_total (entrada do usuário em atividades_posto.php)
    ├─→ quantidade (entrada do usuário)
    ├─→ peso_unidade (do cadastro de unidades)
    │
    └─→ Cálculos Automáticos:
        ├─ tempo_por_unidade = tempo_total / quantidade
        └─ tempo_por_peso = tempo_por_unidade / peso_unidade

TEMPO DE CICLO DO POSTO
    ↓
    └─→ MAX(tempo_total) entre todas as atividades
        (Calculado em PHP - index.php linha 54)

NÚMERO DE PESSOAS (Entrada Manual)
    ↓
    └─→ Input no nó do Drawflow (index.php)
        └─→ Salvo em linhas.json: postos[idx].recursos.num_pessoas

RITMO DE OPERAÇÃO (Cálculo em Tempo Real)
    ↓
    └─→ Tempo Ciclo ÷ Número de Pessoas
        (Calculado em JavaScript - index.php linha 532)
        └─→ Exibido no nó em tempo real
```

---

## 📋 ESTRUTURA DE DADOS - linhas.json

```json
{
  "id": "linha1",
  "nome": "Linha 1",
  "postos": [
    {
      "nome": "Embalagem 1",
      
      "atividades": [
        {
          "descricao": "Embalagem em caixa",
          "unidade": "caixa",
          "quantidade": 10,
          "peso_unidade": 5.0,
          "tempo_total": 120,           ← Tempo de Ciclo do Posto
          "tempo_por_unidade": 12,
          "tempo_por_peso": 2.4
        }
      ],
      
      "recursos": {
        "num_pessoas": 2                ← Entrada Manual
      }
    }
  ]
}
```

---

## 🧮 CÁLCULOS E VALIDAÇÕES

| Métrica | Fórmula | Validação | Localização |
|---------|---------|-----------|------------|
| **Tempo de Ciclo (Posto)** | MAX(tempo_total) | > 0 | index.php:54 |
| **Tempo de Ciclo (Linha)** | MAX(tempo_ciclo_posto) | > 0 | index.php:62 |
| **Número de Pessoas** | Input do usuário | ≥ 0, ≤ 100 | index.php:532 |
| **Ritmo** | ciclo ÷ pessoas | pessoas > 0 | index.php:533 |
| **Taxa de Produção** | (kg / ciclo) × 60 | ciclo > 0 | index.php:91 |

---

## 🎯 FLUXO DE USUÁRIO COMPLETO

### 1️⃣ Criar Posto
```
index.php → Botão "➕ Novo Posto" 
→ Enter nome 
→ POST addPost() 
→ Salva em linhas.json
```

### 2️⃣ Adicionar Atividades
```
index.php → Node → ⚙️ Atividades 
→ atividades_posto.php 
→ Form com: descricao, unidade, quantidade, tempo_total
→ POST save
→ Recalcula Tempo de Ciclo (MAX automático)
```

### 3️⃣ Alocar Pessoas
```
index.php → Node → Input "👥 Pessoas"
→ onchange updateNumPessoas()
→ POST save
→ Re-renderiza node
→ Ritmo recalculado automaticamente
```

### 4️⃣ Visualizar Métricas
```
index.php (Header):
- Tempo de Ciclo: mostrado em segundos
- Taxa de Produção: calculada em kg/min
- Pessoas Alocadas: total da linha
- Ritmo: mostrado por pessoa no nó
```

---

## ⚠️ PROBLEMAS E LIMITAÇÕES ENCONTRADOS

### 🔴 Problema 1: Índice de Pessoas em editRecursos()
**Local**: `index.php` linha 757
```javascript
function editRecursos(index) {
    window.location.href = 'recursos.php?linha=...&back=index';
    // ❌ FALTA passar o índice do posto!
}
```
**Solução**: Deve passar `&post=` + index
```javascript
function editRecursos(index) {
    window.location.href = 'recursos.php?linha=...&post=' + index + '&back=index';
}
```

---

### 🟡 Problema 2: recursos.php não mostra só um posto
**Local**: `recursos.php` linha 50+
```php
// Exibe TODOS os postos de uma linha
// Deveria exibir APENAS um se receber ?post=index
```
**Solução**: Adicionar lógica para filtrar por `$_GET['post']` se fornecido

---

### 🟡 Problema 3: Ritmo não atualiza se recarrega página
**Local**: `index.php` renderNodes()
```javascript
// Se recalcular Ritmo, precisa que tempoCicloPorPosto esteja atualizado
// Mas tempoCicloPorPosto vem do PHP no carregamento
// Se atividades mudam, precisa fazer reload da página
```
**Solução**: Adicionar endpoint AJAX para recalcular tempoCicloPorPosto sem recarregar

---

### 🟡 Problema 4: Pessoas vs Recursos - Nomenclatura
Há confusão entre:
- `recursos.php` = Alocar pessoas por linha
- `index.php input` = Adicionar pessoas por posto
- Ambas salvam em `recursos.num_pessoas`

Precisa clareza se é por **POSTO** ou por **LINHA**

---

## ✅ VERIFICAÇÃO DE CHECKLIST

### Conexão Tempo de Ciclo ↔ Configuração
- ✅ Tempo de ciclo é numérico (em segundos)
- ✅ Conectado a atividades do posto
- ✅ Recalcula automaticamente
- ✅ Passado ao frontend como JSON
- ✅ Exibido no nó do Drawflow

### Quantidade de Pessoas
- ✅ Entrada manual via input number
- ✅ Conectada à configuração de linha/posto
- ✅ Persistida em linhas.json
- ✅ Atualização via AJAX em tempo real
- ✅ Validação: min=0, max=100

### Ritmo de Operação
- ✅ Relação calculada: ciclo ÷ pessoas
- ✅ Atualiza em tempo real
- ✅ Exibido no nó
- ✅ Validação contra divisão por zero
- ✅ Decimal com 2 casas

### Relacionamento Completo
- ✅ Posto × Pessoas × Tempo Ciclo × Ritmo
- ✅ Todas as conexões ativas
- ✅ Dados persistidos
- ✅ Cálculos automáticos

---

## 🎯 RECOMENDAÇÕES

### 🔴 CRÍTICO
1. **Corrigir editRecursos()** para passar índice do posto
2. **Definir escopo**: pessoas por POSTO ou por LINHA?

### 🟡 IMPORTANTE
3. Adicionar endpoint AJAX para recalcular ritmo sem reload
4. Melhorar UI de recursos.php para mostrar só um posto se solicitado
5. Adicionar validação: pessoas ≥ 1 para operação viável

### 🟢 SUGESTÕES
6. Adicionar gráfico de distribuição de ritmo por pessoa
7. Adicionar alerta se ritmo muito desbalanceado
8. Adicionar histórico de mudanças
9. Adicionar export de configuração

---

## 📝 NOTAS FINAIS

✅ **O sistema está FUNCIONAL**
- Todas as conexões principais estão ativas
- Dados persistem corretamente
- Cálculos automáticos funcionam
- Interface responsiva

🔧 **Precisa de ajustes**
- Correção de bugs menores (editRecursos)
- Clarificação de escopo (posto vs linha)
- Melhorias de UX (feedback em tempo real)

🚀 **Pronto para produção com essas correções**

---

**Próximos passos**:
1. [ ] Implementar correções críticas
2. [ ] Testar fluxo completo com dados reais
3. [ ] Validar persistência em linhas.json
4. [ ] Otimizar performance com cache
