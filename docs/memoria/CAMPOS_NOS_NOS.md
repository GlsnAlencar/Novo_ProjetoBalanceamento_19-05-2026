# 📊 Campos nos Nós do Fluxo Drawflow

## 🎯 Visão Geral

Cada "nuvem/bolsão" do fluxo agora exibe 3 campos informativos/interativos:

```
┌─────────────────────────┐
│      Embalagem 1        │
├─────────────────────────┤
│ ⏱️ Tempo de Ciclo: 45.5s │
│ 👥 Pessoas: [2  ]       │
│ ⚡ Ritmo: 22.75 s/pessoa│
└─────────────────────────┘
```

---

## 📋 Descrição de Cada Campo

### 1. **⏱️ Tempo de Ciclo** (Leitura)
- **O que é**: Duração máxima de processamento naquele posto
- **Cálculo**: MAX(tempo_total) de todas as atividades do posto
- **Unidade**: Segundos (s)
- **Exemplo**: Se o posto tem 2 atividades (30s e 45s), mostra **45s**
- **Interativo**: NÃO - apenas visualização

### 2. **👥 Número de Pessoas** (Interativo)
- **O que é**: Campo para configurar quantas pessoas trabalham naquele posto
- **Tipo**: Input numérico
- **Mín/Máx**: 0 a 100 pessoas
- **Persistência**: Salva imediatamente em `linhas.json`
- **Vinculação**: Dados da "Configuração da Linha" (não do Recursos)
- **Ao Alterar**: 
  - Envia POST ao servidor
  - Re-renderiza nó para atualizar ritmo
  - Atualiza valor em linhas.json

### 3. **⚡ Ritmo do Posto** (Calculado em Tempo Real)
- **O que é**: Tempo de ciclo distribuído entre as pessoas
- **Fórmula**: `Tempo de Ciclo ÷ Número de Pessoas`
- **Unidade**: Segundos por pessoa (s/pessoa)
- **Atualização**: Muda instantaneamente ao alterar número de pessoas
- **Exemplos**:
  - Tempo de Ciclo = 60s, 2 pessoas → Ritmo = **30 s/pessoa**
  - Tempo de Ciclo = 60s, 3 pessoas → Ritmo = **20 s/pessoa**
  - Tempo de Ciclo = 60s, 0 pessoas → Ritmo = **—** (indefinido)

---

## 🔄 Fluxo de Atualização

```
1. Usuário digita número de pessoas no nó
   ↓
2. Evento onchange dispara updateNumPessoas()
   ↓
3. Função JavaScript:
   - Atualiza objeto local (postos[idx])
   - Envia POST com fetch()
   ↓
4. Servidor PHP:
   - Valida dados
   - Atualiza linhas.json
   - Retorna JSON com sucesso
   ↓
5. JavaScript recebe resposta:
   - Chama renderNodes() para re-renderizar
   - Novo Ritmo é calculado automaticamente
```

---

## 📊 Dados Estrutura

### Passagem de Dados para JavaScript

```javascript
// PHP → JavaScript
var tempoCicloPorPosto = {
  0: 45.5,  // Índice 0 (primeiro post): 45.5 segundos
  1: 30.2,  // Índice 1 (segundo post): 30.2 segundos
  2: 60.0   // Índice 2 (terceiro post): 60 segundos
};
```

### Armazenamento em linhas.json

```json
{
  "postos": [
    {
      "nome": "Embalagem 1",
      "atividades": [...],
      "recursos": {
        "num_pessoas": 2
      }
    }
  ]
}
```

---

## 💾 Persistência de Dados

| Campo | Persistido Em | Local | Atualizado Por |
|-------|---|---|---|
| Tempo de Ciclo | `linhas.json` | `postos[].atividades[].tempo_total` | Tela atividades_posto.php |
| Número de Pessoas | `linhas.json` | `postos[].recursos.num_pessoas` | Nó do Drawflow (input) |
| Ritmo | **NÃO** | Calculado em JS | Fórmula dinâmica |

---

## 🧮 Fórmulas Implementadas

| Métrica | Fórmula | Onde é Calculado |
|---------|---------|---|
| Tempo de Ciclo por Posto | `MAX(tempo_total)` | index.php (PHP) |
| Número de Pessoas | Entrada do usuário | index.php (HTML input) |
| Ritmo do Posto | `tempo_ciclo ÷ num_pessoas` | renderNodes() (JavaScript) |

---

## 🎨 Estilos CSS

Tamanho mínimo dos nós aumentado para acomodar os campos:
- **Largura mínima**: 220px (era 160px)
- **Altura mínima**: 140px (era 80px)

Input number dentro do nó:
- Espaçamento: 60px de largura
- Padding: 4px
- Border-radius: 4px
- Focus: Blue glow com rgba(0, 123, 255, 0.25)

---

## 🧪 Como Testar

### Teste 1: Visualizar Tempo de Ciclo
```
1. Abra index.php
2. Veja cada nó exibindo "⏱️ Tempo de Ciclo: XXXs"
✅ Deve mostrar valor máximo de tempo_total do posto
```

### Teste 2: Alterar Número de Pessoas
```
1. Clique no input "👥 Pessoas" em um nó
2. Mude de 0 → 2 → 3 → 5
3. Observe o Ritmo atualizar: 60s ciclo, 2 pessoas = 30 s/pessoa
✅ Ritmo deve recalcular instantaneamente
```

### Teste 3: Persistência
```
1. Configure 3 pessoas em um posto
2. Pressione F5 (reload)
3. Valores devem permanecer = 3 pessoas
✅ Dados salvos em linhas.json
```

### Teste 4: Sincronização com recursos.php
```
1. Altere número de pessoas em um nó (ex: 5)
2. Acesse Menu → Recursos/Pessoas
3. Deve mostrar "👤 5 pessoas" no mesmo posto
✅ Dados sincronizados entre telas
```

---

## ⚠️ Notas Importantes

- **Índice mismatch**: Os índices em JavaScript seguem a ordem de `array_values()` do PHP
- **Renderização**: Ao alterar pessoas, TODOS os nós são re-renderizados (para atualizar cálculos)
- **Ritmo indefinido**: Se num_pessoas = 0, mostra "—" (hífem)
- **Máximo de pessoas**: Input limita a 100 pessoas para evitar erros

---

## 📝 Arquivos Modificados

- ✅ **index.php**: 
  - Cálculo de `$tempo_ciclo_por_posto` (PHP)
  - Nova variável JavaScript `tempoCicloPorPosto`
  - Função `updateNumPessoas()` (JavaScript)
  - Handler POST para atualizar pessoas (PHP)
  - Renderização dos nós com novos campos
  - CSS melhorado para os nós

---

**Status**: ✅ Campos nos nós implementados e funcionais!
