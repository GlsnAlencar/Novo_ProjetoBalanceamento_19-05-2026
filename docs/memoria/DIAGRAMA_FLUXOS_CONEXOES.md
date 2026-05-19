# 🔗 DIAGRAMA DE FLUXO E RELACIONAMENTOS

## 1️⃣ Arquitetura de Dados

```
┌─────────────────────────────────────────────────────────────────┐
│                         linhas.json                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  "linhas": [                                                    │
│    {                                                            │
│      "id": "linha1",                                            │
│      "nome": "Linha 1",                                         │
│      "postos": [                                                │
│        {                                                        │
│          ┌────────────────────────────────────────┐           │
│          │ "nome": "Embalagem 1"                  │           │
│          │ ┌──────────────────────────────────┐  │           │
│          │ │ "atividades": [                  │  │           │
│          │ │   {                              │  │           │
│          │ │     "descricao": "...",          │  │           │
│          │ │     ┌─ "tempo_total": 120 ──────┼──┼─ TEMPO CICLO
│          │ │     │  (Tempo de Ciclo do Posto) │  │           │
│          │ │     │  (max entre atividades)    │  │           │
│          │ │     "quantidade": 10,            │  │           │
│          │ │     "peso_unidade": 5.0,         │  │           │
│          │ │     ...                          │  │           │
│          │ │   }                              │  │           │
│          │ │ ]                                │  │           │
│          │ └──────────────────────────────────┘  │           │
│          │ ┌──────────────────────────────────┐  │           │
│          │ │ "recursos": {                    │  │           │
│          │ │   ┌─ "num_pessoas": 2 ──────────┼──┼─ QUANTIDADE
│          │ │   │  (Entrada Manual)            │  │  DE PESSOAS
│          │ │ }                                │  │           │
│          │ └──────────────────────────────────┘  │           │
│          └────────────────────────────────────────┘           │
│        }                                                        │
│      ]                                                          │
│    }                                                            │
│  ]                                                              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2️⃣ Fluxo de Cálculos (Backend → Frontend)

```
┌──────────────────────────────────────────────────────────┐
│                    index.php (PHP)                        │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  Para cada POSTO (linha 35-80):                         │
│  ┌────────────────────────────────────────────┐         │
│  │ LEITURA: atividades[].tempo_total          │         │
│  │         (de linhas.json)                    │         │
│  └────┬───────────────────────────────────────┘         │
│       │                                                   │
│       ↓                                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ CÁLCULO 1: tempo_ciclo_por_posto[idx]     │         │
│  │           = MAX(tempo_total)              │         │
│  │           (maior atividade do posto)      │         │
│  └────┬───────────────────────────────────────┘         │
│       │                                                   │
│       ├──────→ Passado ao JavaScript via JSON          │
│       │        tempoCicloPorPosto = {...}               │
│       │                                                   │
│       ↓                                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ LEITURA: recursos.num_pessoas             │         │
│  │         (de linhas.json)                    │         │
│  └────┬───────────────────────────────────────┘         │
│       │                                                   │
│       ↓                                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ CÁLCULO 2 (no Header):                     │         │
│  │ Taxa de Produção = (total_kg / ciclo) × 60│         │
│  │ Total de Pessoas = SUM(num_pessoas)        │         │
│  └────┬───────────────────────────────────────┘         │
│       │                                                   │
│       ↓                                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ HEADER - Indicadores Exibidos:            │         │
│  │ • Tempo de Ciclo: XXX s                   │         │
│  │ • Taxa de Produção: XX kg/min              │         │
│  │ • Pessoas Alocadas: X                      │         │
│  └────────────────────────────────────────────┘         │
│                                                           │
└──────────────────────────────────────────────────────────┘
         ↓
         │ JSON + HTML
         ↓
┌──────────────────────────────────────────────────────────┐
│              index.php (JavaScript)                       │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  renderNodes() - Para cada POSTO:                       │
│  ┌────────────────────────────────────────────┐         │
│  │ RECEBE:                                    │         │
│  │ • tempoCicloPorPosto[idx] (PHP calculado) │         │
│  │ • postos[idx].recursos.num_pessoas        │         │
│  └────┬───────────────────────────────────────┘         │
│       │                                                   │
│       ↓                                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ CÁLCULO 3 (em Tempo Real):                 │         │
│  │                                            │         │
│  │ var ritmo = tempoCiclo / numPessoas       │         │
│  │                                            │         │
│  │ Se numPessoas > 0:                         │         │
│  │   ritmo = (60 / 3).toFixed(2) = "20.00"   │         │
│  │ Senão:                                     │         │
│  │   ritmo = "—"                              │         │
│  └────┬───────────────────────────────────────┘         │
│       │                                                   │
│       ↓                                                   │
│  ┌────────────────────────────────────────────┐         │
│  │ NODE DRAWFLOW - Exibido:                   │         │
│  │                                            │         │
│  │ ┌──────────────────────────────────────┐  │         │
│  │ │     Embalagem 1                       │  │         │
│  │ ├──────────────────────────────────────┤  │         │
│  │ │ ⏱️  Tempo: 120s    [calculado em PHP]│  │         │
│  │ │ 👥 Pessoas: [ 2  ] [entrada manual]  │  │         │
│  │ │ ⚡ Ritmo: 60 s/pes [calculado em JS] │  │         │
│  │ └──────────────────────────────────────┘  │         │
│  └────────────────────────────────────────────┘         │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

---

## 3️⃣ Ciclo de Atualização (quando muda "Pessoas")

```
┌─────────────────────────────────────────────────────────────┐
│  NODE DO DRAWFLOW                                           │
│  Input: 👥 Pessoas                                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Usuário digita nova quantidade (ex: 2 → 3)              │
│                                                              │
│         ↓                                                    │
│                                                              │
│  JavaScript: onchange="updateNumPessoas(idx, value)"   │
│                                                              │
│         ↓                                                    │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ var numPessoas = parseInt(value) = 3               │  │
│  │ postos[idx].recursos.num_pessoas = 3               │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│         ↓ fetch(...) POST                                    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────────────────────────┐
│  index.php (PHP - $_POST)                                  │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  atualizar_pessoas = 1                                    │
│  post_index = 0                                           │
│  num_pessoas = 3                                          │
│                                                              │
│         ↓                                                    │
│                                                              │
│  $linhas_json[$linha_key]['postos'][0]['recursos']    │
│    ['num_pessoas'] = 3                                   │
│                                                              │
│         ↓ save_json_data('linhas', $linhas_json)         │
│                                                              │
│  Salva em linhas.json ✓                                   │
│                                                              │
│         ↓ Retorna JSON(sucesso: true)                      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
         ↓ then()
┌─────────────────────────────────────────────────────────────┐
│  JavaScript: fetch().then(response => {...})             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  if (response.ok):                                        │
│    renderNodes()  ← RE-RENDERIZA NÓS                     │
│                                                              │
│         ↓                                                    │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Para cada nó:                                       │  │
│  │                                                      │  │
│  │ var ritmo = (120 / 3).toFixed(2) = "40.00"         │  │
│  │                                                      │  │
│  │ Atualiza no HTML do nó:                           │  │
│  │ ⚡ Ritmo: 40.00 s/pessoa                          │  │
│  │                                                      │  │
│  │ (antes era: 60.00 s/pessoa)                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ✓ Atualização completa                                   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 4️⃣ Ciclo de Atualização (quando muda "Atividades")

```
┌──────────────────────────────────┐
│  atividades_posto.php            │
│  Usuário adiciona/remove         │
│  atividades do posto             │
└──────────────────┬───────────────┘
                   │
                   ↓ POST save_atividade()
                   │
         ┌─────────────────┐
         │ linhas.json     │
         │ atualizado      │
         └────────┬────────┘
                  │
                  ↓
        Usuário volta ao index.php
                  │
                  ↓
     ┌────────────────────────────┐
     │ index.php carregado        │
     │ Recalcula tempoCiclo       │
     └────────────┬───────────────┘
                  │
                  ↓
     ┌────────────────────────────┐
     │ renderNodes()              │
     │ Novo ritmo calculado       │
     │ (com novo tempo ciclo)     │
     └────────────────────────────┘
```

---

## 5️⃣ Estrutura do Node Drawflow

```javascript
┌─────────────────────────────────────────────────────┐
│                    NODE                              │
├─────────────────────────────────────────────────────┤
│                                                      │
│  nodeId = editor.addNode(                           │
│    'posto',              // tipo                    │
│    1, 1,                 // inputs, outputs         │
│    100 + idx * 280,      // posição X               │
│    150,                  // posição Y               │
│    'posto',              // classe CSS              │
│    { index: idx, nome: 'Embalagem 1' },           │
│    html                  // conteúdo ↓             │
│  )                                                  │
│                                                      │
│  html = <div>                                      │
│    <strong>Embalagem 1</strong>                    │
│    <div style="...">                               │
│      <div>⏱️  Tempo de Ciclo: 120s</div>          │
│      <label>👥 Pessoas:</label><br>               │
│      <input type="number"                          │
│             id="pessoas_0"                         │
│             value="2"                              │
│             onchange="updateNumPessoas(0, value)"> │
│      <div>⚡ Ritmo: 60.00 s/pessoa</div>          │
│    </div>                                           │
│  </div>                                             │
│                                                      │
└─────────────────────────────────────────────────────┘
```

---

## 6️⃣ Validações em Cascata

```
┌────────────────────────────────────┐
│ Entrada Manual (Frontend)          │
│ Input number: min=0, max=100       │
└────────┬─────────────────────────┐ │
         │                         │ │
         ↓                         ↓ │
    ┌─────────┐            ┌─────────┐
    │ JavaScript Validation
    │ parseInt(value) >= 0
    └────┬──────────────────────────┐
         │                          │
         ↓                          │
    ┌──────────────────────────────────┐
    │ PHP Server Validation            │
    │ post_idx >= 0 &&                 │
    │ post_idx < count(postos) &&      │
    │ num_pessoas >= 1   ← IMPORTANTE │
    └────┬─────────────────────────────┘
         │
         ↓
    ┌──────────────────────────────────┐
    │ linhas.json Persistência         │
    │ ✓ Armazenado com segurança       │
    └──────────────────────────────────┘
         │
         ↓
    ┌──────────────────────────────────┐
    │ Cálculo de Ritmo (JS)            │
    │ if (numPessoas > 0) {            │
    │   ritmo = ciclo / pessoas        │
    │ } else {                         │
    │   ritmo = "—"                    │
    │ }                                │
    └──────────────────────────────────┘
```

---

## 7️⃣ Fluxo Completo: Do Usuário ao JSON

```
USUÁRIO
  │
  ├─[Opção 1: Atividades] →────────────────────────────┐
  │  index.php Node → ⚙️ Atividades                     │
  │  ↓                                                    │
  │  atividades_posto.php                               │
  │  Add/Edit/Remove atividades                         │
  │  ↓                                                    │
  │  linhas.json: postos[x].atividades[...]            │
  │  ↓                                                    │
  │  tempo_total atualizado                             │
  │  ↓                                                    │
  │  [Configurar Recursos] →──────────┐                 │
  │                                    │                 │
  └─[Opção 2: Pessoas] ────────────┐   │                 │
     index.php Node → 👥 Pessoas     │   │                 │
     ↓                               │   │                 │
     Input number (manual)           │   │                 │
     ↓                               │   │                 │
     updateNumPessoas()              │   │                 │
     ↓                               │   │                 │
     fetch POST                      │   │                 │
     ↓                               │   │                 │
     linhas.json: postos[x].        │   │                 │
     recursos.num_pessoas           │   │                 │
     ↓                               │   │                 │
     renderNodes()                   │   │                 │
     ↓                               │   │                 │
     Ritmo recalculado              │   │                 │
     ↓                               │   │                 │
     ✓ Completo                      │   │                 │
                                     │   │                 │
                                     └─→─→ recursos.php
                                         │
                                         ├─ single post
                                         │  (se via &post=)
                                         │
                                         ├─ all posts
                                         │  (se sem post)
                                         │
                                         └─ Salvar mudanças
                                            (mesmo fluxo)
```

---

## 8️⃣ Resumo Executivo

| Elemento | Entrada | Processamento | Saída | Persistência |
|----------|---------|---------------|-------|--------------|
| **Tempo Ciclo** | Atividades | MAX(tempo_total) | PHP/JS | linhas.json |
| **Pessoas** | Manual (Input) | parseInt() | JS/PHP | linhas.json |
| **Ritmo** | Calculado | ciclo ÷ pessoas | JS | NÃO (derivado) |
| **Validação** | Frontend | JS + PHP | Erro/OK | N/A |
| **Atualização** | AJAX | fetch POST | JSON | Automática |

---

**Todas as conexões estão ativas e funcionais!** ✅
