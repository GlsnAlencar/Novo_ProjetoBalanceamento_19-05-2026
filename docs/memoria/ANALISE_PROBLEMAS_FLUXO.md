# ANÁLISE DETALHADA - FLUXO_TESTE01.php

## Problemas Identificados e Severidade

### 1. **Sobreposição de nós** (CRÍTICA)
**Localização:** Funções `addNodeToFlow()` e `addSimpleNode()`
**Causa:** 
- `suggestedY` é calculado UMA VEZ quando o nó é criado
- Quando derivam múltiplos nós do mesmo pai, todos recebem o mesmo Y
- Não há incremento automático de posição Y

**Impacto:**
- Nós criados sequencialmente sobrepõem-se visualmente
- Impossível ver/selecionar nós sobrepostos
- Conexões ficam difíceis de visualizar

**Código Problemático:**
```javascript
let suggestedY = initialY || 150;
if (parentId) {
    const parentPosto = mockPostos.find(p => p.id == parentId);
    if (parentPosto) {
        suggestedX = (parentPosto.x || 100) + 300;
        suggestedY = (parentPosto.y || 150); // ❌ Sempre mesmo Y
    }
}
```

---

### 2. **Movimento vertical travado** (ALTA)
**Localização:** CSS `#drawflow` e `body`
**Causa:**
- `body { display: flex; flex-direction: column; min-height: 100vh; }`
- `#drawflow { flex-grow: 1; width: 100%; height: 100%; }`
- Pode haver conflitos com altura relativa em flex layout

**Impacto:**
- Usuário não consegue arrastar nós para cima/baixo
- Zoom/pan pode estar limitado
- Canvas pode estar com altura incorreta

---

### 3. **Exclusão de postos falhando** (ALTA)
**Localização:** `removeNodeFromFlow()` e estrutura `mockPostos`
**Causa:**
- IDs no Drawflow são números (parseInt)
- IDs em `mockPostos` podem ser strings (gerados com `uniqid()`)
- Comparações `==` funcionam, mas remoção com `!==` falha

**Impacto:**
- Nós não são removidos corretamente do mock
- Estado JS fica inconsistente com Drawflow visual
- Conexões fantasma permanecem

**Código Problemático:**
```javascript
mockPostos = mockPostos.filter(p => p.id !== id); // ❌ Comparação rigorosa
```

---

### 4. **Edição falhando** (ALTA)
**Localização:** `editNodeInFlow()` e `editor.updateNodeDataFromId()`
**Causa:**
- Drawflow cache pode não ser atualizado
- HTML do nó pode não ser re-renderizado
- Atualizações de data não refletem no HTML

**Impacto:**
- Edições visuais não aparecem
- Estado JS atualizado mas Drawflow não
- Usuário vê valores antigos após editar

---

### 5. **Fluxos de linhas misturando estados** (CRÍTICA)
**Localização:** `initDrawflow()` com `editor.import()`
**Causa:**
- `editor.import()` adiciona nós ao estado existente
- Se houver nós da linha anterior, eles podem misturar-se
- `mockPostos` não é reconstruído após import

**Impacto:**
- Trocar de linha pode mostrar nós de outras linhas
- `mockPostos` fica com dados antigos
- Exclusão/edição afeta nós errados

**Código Problemático:**
```javascript
if (flowData?.drawflow?.Home?.data && Object.keys(flowData.drawflow.Home.data).length > 0) {
    editor.import(flowData); // ❌ Sem clear() antes
    console.log('✅ Fluxo carregado do servidor');
    setupDrawflowEvents();
    updateNodeCount();
    return;
}
```

---

### 6. **Recursos BPMN perdidos** (ALTA)
**Localização:** `generateNodeHtml()` e persistência via `editor.import()`
**Causa:**
- Classes CSS `decision_exclusive`, `decision_parallel` dependem de `type`
- Quando `editor.import()` recria nós, `type` pode não ser preservado
- HTML gerado dinamicamente pode não recriar corretamente

**Impacto:**
- Gateways perdem estilo (losango amarelo/verde)
- Ícones FontAwesome desaparecem
- BPMN visualmente incorreta

---

### 7. **Estado JS inconsistente após import** (CRÍTICA)
**Localização:** `initDrawflow()` após `editor.import()`
**Causa:**
- `mockPostos` array não é reconstruído
- `mockPostos` continua com dados da linha anterior
- Não há sincronização entre Drawflow e mockPostos após load

**Impacto:**
- Operações CRUD no mockPostos afetam nós errados
- Posições (x, y) não são restauradas
- Conexões no mockConnections ficam desincronizadas

**Código Problemático:**
```javascript
editor.import(flowData);
// ❌ mockPostos ainda contém dados antigos
// ❌ Não reconstrói mockPostos do flowData carregado
```

---

### 8. **Salvamentos excessivos / Race Condition** (MÉDIA)
**Localização:** `saveFlowState()` com múltiplos eventos (nodeMoved, connectionCreated, etc.)
**Causa:**
- Cada evento dispara `saveFlowState()` independentemente
- Sem debounce, podem haver múltiplas requisições simultâneas
- Flag `isSaving` previne parcialmente, mas pode haver enfileiramento

**Impacto:**
- Servidor recebe múltiplas requisições redundantes
- Performance degrada
- Possível corrupção de dados se requisições chegarem fora de ordem

---

### 9. **Falta de independência por linha** (ALTA)
**Localização:** Variáveis globais `currentLinhaId`, `mockPostos`, `nextNodeId`
**Causa:**
- Todas as linhas compartilham mesmo `editor` instance
- `mockPostos` é global, não isolado por linha
- localStorage não diferencia zoom/pan por linha

**Impacto:**
- Trocar de linha pode misturar dados
- Zoom de uma linha afeta outra

---

## Estratégia de Correção

### Fase 1: FLUXO_TESTE02.php
1. Copiar `fluxo_teste01.php` → `fluxo_teste02.php`
2. Manter todas as APIs, persistência e backend identicamente
3. Aplicar correções incrementais mantendo funcionalidade

### Correções em Ordem de Aplicação

1. **Clear + Reconstruir mockPostos após import** (Critério)
   - Clear editor antes de import
   - Reconstruir mockPostos do flowData importado
   - Sincronizar mockConnections

2. **Incrementar Y automaticamente** (Crítica)
   - Adicionar offset Y para cada nó derivado
   - Rastrear última posição Y

3. **Standardizar comparações de ID** (Alta)
   - Sempre usar `==` para comparação
   - Converter para string se necessário
   - Validar tipo em todas as operações

4. **Implementar debounce para salvamento** (Média)
   - Usar setTimeout para agrupar salvamentos
   - Evitar múltiplas requisições simultâneas

5. **Validar CSS/Flex layout** (Média)
   - Revisar #drawflow height
   - Testar movimento vertical

6. **Reconstruir HTML de nós após import** (Média)
   - Garantir que generateNodeHtml é chamado
   - Restaurar classes CSS e ícones

---

## Manutenção de Compatibilidade

✅ Mesma persistência JSON  
✅ Mesma API Drawflow  
✅ Mesmas estruturas de dados  
✅ Mesmo backend api_drawflow.php  
✅ Mesma lógica de zoom/pan  
✅ Mesmas funcionalidades BPMN  
✅ Mesmo fluxo de eventos  

❌ Não remover funcionalidades  
❌ Não mudar nomes de variáveis sem necessidade  
❌ Não reescrever seções inteiras  
