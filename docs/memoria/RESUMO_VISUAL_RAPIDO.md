# 🎯 RESUMO VISUAL - FLUXO E CONEXÕES

---

## 📊 Mapa Mental das Conexões

```
                    ┌─────────────────────────────┐
                    │   LINHA DE PRODUÇÃO         │
                    │   (linhas.json)             │
                    └────────────┬────────────────┘
                                 │
                    ┌────────────────────────────┐
                    │   POSTO (Node Drawflow)    │
                    └────────────┬────────────────┘
                                 │
            ┌────────────────────┼────────────────────┐
            │                    │                    │
            ↓                    ↓                    ↓
    ┌─────────────────┐ ┌──────────────┐ ┌──────────────┐
    │ ATIVIDADES      │ │ RECURSOS     │ │ CONFIGURAÇÃO │
    │ (tempo_total)   │ │ (pessoas)    │ │              │
    │ ┌─────────────┐ │ │ ┌──────────┐ │ │              │
    │ │ 60s         │ │ │ │ 2 input  │ │ │              │
    │ │ 45s    ←MAX │ │ │ │ manual   │ │ │              │
    │ │ 30s         │ │ │ └──────────┘ │ │              │
    │ └─────────────┘ │ └──────────────┘ │              │
    └────────┬────────┘        │         └──────────────┘
             │                 │
             └─────────┬───────┘
                       │
                       ↓ CÁLCULO
            ┌──────────────────────┐
            │ RITMO = 120 ÷ 2      │
            │ RITMO = 60 s/pessoa  │
            └──────────────────────┘
```

---

## 🔄 Ciclo de Operação

### Usuário Altera Número de Pessoas

```
Input: 2 → 3
   │
   ↓
onchange event
   │
   ↓
JavaScript: updateNumPessoas(0, 3)
   │
   ├─ Atualiza objeto local: postos[0].recursos.num_pessoas = 3
   │
   ├─ fetch POST → index.php
   │   • atualizar_pessoas = 1
   │   • post_index = 0
   │   • num_pessoas = 3
   │
   └─ PHP Server
      ├─ Valida: num_pessoas >= 1 ✓
      │
      ├─ Atualiza: linhas.json
      │  └─ postos[0].recursos.num_pessoas = 3
      │
      └─ Response: { sucesso: true }
         │
         ↓
      JavaScript: .then()
         │
         └─ renderNodes()
            │
            ├─ Recalcula ritmo:
            │  Novo: 120 ÷ 3 = 40 s/pessoa
            │
            └─ Re-renderiza Node
               └─ ⚡ Ritmo: 40.00 s/pessoa
```

---

## 📍 Mapa de Arquivos

```
projeto-balanceamento/
│
├── public/
│   ├── index.php                    ← Fluxo visual + Cálculos
│   │   ├─ Renderiza nodes
│   │   ├─ Calcula tempo de ciclo (PHP)
│   │   ├─ Calcula ritmo (JS)
│   │   └─ updateNumPessoas() AJAX
│   │
│   ├── atividades_posto.php         ← Editar atividades
│   │   ├─ Define tempo_total
│   │   └─ Link → recursos.php &post=
│   │
│   ├── recursos.php                 ← Alocar pessoas
│   │   ├─ Single post (se ?post=)
│   │   ├─ All posts (se sem post)
│   │   └─ Salva num_pessoas
│   │
│   └── postos.php                   ← Gerenciar postos
│
└── data/
    └── linhas.json                  ← Persistência
        └─ Estrutura:
           └─ postos[x]
              ├─ atividades[y].tempo_total
              └─ recursos.num_pessoas
```

---

## 🔌 Conexões em Ação

### Cenário 1: Adicionar Atividade

```
atividades_posto.php
│
├─ Input: descricao, unidade, quantidade, tempo_total
│
└─ POST save
   │
   └─ linhas.json atualizado
      └─ postos[0].atividades[] += nova
         └─ tempo_total = 120
            │
            ↓ (ao voltar ao index.php)
            │
            └─ Recalcula tempo_ciclo
               └─ 120s exibido no Node
                  └─ ⏱️ Tempo de Ciclo: 120s
```

### Cenário 2: Alterar Pessoas

```
index.php (Node Input)
│
├─ Usuário muda: 2 → 3
│
└─ updateNumPessoas()
   │
   ├─ fetch POST
   │
   └─ linhas.json atualizado
      └─ postos[0].recursos.num_pessoas = 3
         │
         ↓ renderNodes()
         │
         ├─ Recalcula ritmo: 120 ÷ 3 = 40
         │
         └─ Node atualizado
            └─ ⚡ Ritmo: 40.00 s/pessoa
```

---

## ✅ Validações em Cascata

```
Frontend (HTML)          Backend (PHP)              Storage
─────────────────────────────────────────────────────────────

Input number             POST validation            Persistence
min=0, max=100    →      >= 1                  →    linhas.json
                        (rejeita 0)

JavaScript                                        Success
parseInt()        →      Salva dados           →   { sucesso: true }

                        Re-renderiza na UI
```

---

## 📈 Indicadores em Tempo Real

```
┌──────────────────────────────────────────────────┐
│                    HEADER                         │
├──────────────────────────────────────────────────┤
│                                                   │
│  Postos: 3          ← count(postos)             │
│  Tempo de Ciclo: 120.0 s    ← MAX(tempo_total) │
│  Taxa de Produção: 45.25 kg/min ← (kg/ciclo)×60
│  Pessoas Alocadas: 5 👥     ← SUM(num_pessoas) │
│                                                   │
└──────────────────────────────────────────────────┘
         ↑
         └─ Atualiza quando:
            • Atividades mudam (reload)
            • Pessoas mudam (AJAX → renderNodes)
```

---

## 🎯 Fluxos de Navegação

### Fluxo A: Drawflow → Recursos
```
index.php
   │
   ├─ Clique Node
   │
   ├─ Clique "👥 Recursos"
   │
   ├─ editRecursos(0)
   │
   └─ → recursos.php?linha=xxx&post=0&back=index
      │
      ├─ Exibe APENAS Pos 0
      │
      ├─ Altera pessoas
      │
      └─ [← Voltar] → index.php
         └─ Ritmo atualizado ✓
```

### Fluxo B: Atividades → Recursos
```
atividades_posto.php?post=0&linha=xxx
   │
   ├─ Clique "👥 Configurar Recursos"
   │
   └─ → recursos.php?linha=xxx&post=0&back=atividades_posto
      │
      ├─ Exibe APENAS Pos 0
      │
      ├─ Altera pessoas
      │
      └─ [← Voltar] → atividades_posto.php?post=0
         └─ Contexto preservado ✓
```

### Fluxo C: Menu → Recursos Completo
```
menu.php → "Recursos/Pessoas"
   │
   └─ → recursos.php?linha=xxx
      │
      ├─ Exibe TODOS os postos
      │
      ├─ Altera pessoas (múltiplos)
      │
      └─ [← Voltar] → postos.php
         └─ Todas mudanças salvas ✓
```

---

## 🧮 Fórmulas Usadas

| Nome | Fórmula | Onde | Atualiza |
|------|---------|------|----------|
| **Tempo Ciclo Posto** | MAX(tempo_total) | PHP | Ao salvar atividades |
| **Tempo Ciclo Linha** | MAX(todos postos) | PHP | Ao salvar atividades |
| **Ritmo** | tempo ÷ pessoas | JS | Ao alterar pessoas |
| **Taxa Produção** | (kg ÷ ciclo) × 60 | PHP | Ao calcular |
| **Total Pessoas** | SUM(num_pessoas) | PHP | Ao alterar |

---

## 🚨 Casos Extremos Tratados

```
Pessoas = 0
   └─ Ritmo = "—" (indefinido)
   └─ Input prevents entry (min=1)

Pessoas = -5
   └─ Input prevents entry (min=0)
   └─ PHP rejects (>= 1)

Pessoas = 101
   └─ Input max=50 prevents

Tempo = 0
   └─ Ritmo = "—" (indefinido)
   └─ Tempo Total deve ser > 0

Taxa com ciclo = 0
   └─ Retorna 0 kg/min
```

---

## 📊 Estrutura JSON (Simplificada)

```javascript
linhas.json = {
  "id": "linha1",
  "nome": "Linha 1",
  "postos": [
    {
      "nome": "Pos 1",
      "atividades": [
        {
          "descricao": "...",
          "tempo_total": 120,    ← TEMPO CICLO
          "quantidade": 10,
          "peso_unidade": 5.0,
          ...
        }
      ],
      "recursos": {
        "num_pessoas": 2         ← PESSOAS (entrada manual)
      }
      // RITMO = 120 ÷ 2 = 60 (calculado em JS)
    }
  ]
}
```

---

## ✨ Melhorias Realizadas

### ✅ Bug #1 Fixado
```javascript
// ANTES: ❌
editRecursos(index) {
  window.location = 'recursos.php?linha=...&back=index';
}

// DEPOIS: ✅
editRecursos(index) {
  window.location = 'recursos.php?linha=...&post=' + index + '&back=index';
}
```

### ✅ Feature #2 Adicionada
```php
// recursos.php agora suporta
if ($post_index !== null) {
  $modo = 'single'; // Exibe um posto
} else {
  $modo = 'all';    // Exibe todos
}
```

### ✅ Feature #3 Adicionada
```php
// atividades_posto.php agora passa contexto
<a href="recursos.php?...&post=<?php echo $post_index; ?>">
  👥 Configurar Recursos
</a>
```

---

## 🎓 Checklist de Validação Rápida

### Teste Rápido (5 minutos)
- [ ] Criar 2 postos
- [ ] Adicionar atividade (tempo: 60s)
- [ ] Alterar pessoas: 2 → 3
- [ ] Verificar: Ritmo = 20 s/pessoa
- [ ] Voltar → Verificar persistência

### Teste Completo (15 minutos)
- [ ] Criar 3 postos
- [ ] Adicionar múltiplas atividades
- [ ] Testar todos 3 fluxos (A, B, C)
- [ ] Validar linhas.json
- [ ] Verificar Header indicators

---

## 📞 Suporte Rápido

**Erro: Pessoas não se atualiza**
```
→ Verificar: updateNumPessoas() é chamado?
→ Verificar: fetch POST retorna sucesso?
→ Verificar: linhas.json foi atualizado?
```

**Erro: Ritmo não recalcula**
```
→ Verificar: renderNodes() é chamado?
→ Verificar: numPessoas > 0?
→ Verificar: tempoCiclo > 0?
```

**Erro: Pessoas = 0 aceito**
```
→ Verificar: PHP input min=1?
→ Verificar: Backend valida >= 1?
```

---

## 🎯 Status Final

```
✅ TEMPO CICLO      ← Ativo como número
✅ CONFIGURAÇÃO     ← Conectada a atividades  
✅ PESSOAS (Manual) ← Entrada validada
✅ PERSISTÊNCIA     ← linhas.json atualizado
✅ RITMO            ← Calculado em tempo real
✅ VALIDAÇÕES       ← Frontend + Backend
✅ NAVEGAÇÃO        ← Breadcrumb com contexto
✅ UI/UX            ← Intuitiva e responsiva

🚀 PRONTO PARA TESTES
```

---

**Criado em**: 22/04/2026 | **Versão**: 1.1 | **Status**: ✅ COMPLETO
