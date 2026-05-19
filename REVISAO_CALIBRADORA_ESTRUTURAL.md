# 🔍 REVISÃO ESTRUTURAL - MÓDULO CALIBRADORA

**Data:** 16 de Maio de 2026  
**Escopo:** Análise completa do módulo existente  
**Status:** Pronto para ação

---

## 📊 SUMÁRIO EXECUTIVO

| Aspecto | Status | Observação |
|---------|--------|-----------|
| **Estrutura** | ✅ Adequada | 6 etapas mapeadas, isolamento ok |
| **Dados** | ✅ Parcial | JSON estruturado, sem banco dados |
| **Segurança** | 🔴 Crítica | 6 problemas críticos (C-001 a C-006) |
| **Código** | 🟠 Alto | 20+ problemas de média/alta prioridade |
| **Visual** | 🟡 Inconsistente | CSS duplicado, diferente do Balanceamento |
| **Performance** | 🟡 Média | Sem índices, O(n) lookups |

---

## ✅ O QUE JÁ FOI CRIADO

### 1️⃣ Estrutura de Arquivos

```
📁 /public/reformulacao/calibradora/
├── ✅ index.php                       (Hub central com cards)
├── ✅ bootstrap.php                   (Autoload namespace)
├── ✅ init.php                        (Inicialização)
├── ✅ safe_storage.php                (Gerenciador JSON com locks)
├── ✅ styles_ui.php                   (CSS padrão calibradora)
├── ✅ controllers/CalbradoraController.php
├── ✅ services/CalbradoraService.php
├── ✅ models/
│   ├── ✅ FaixaPeso.php               (5 faixas exemplo)
│   ├── ✅ ConfiguracaoEmbalamento.php
│   ├── ✅ RegistroLote.php
│   └── ✅ DistribuicaoLote.php
├── ✅ repositories/
│   ├── ✅ BaseRepository.php          (com locks)
│   ├── ✅ FaixaPesoRepository.php
│   ├── ✅ ConfiguracaoEmbalamentoRepository.php
│   ├── ✅ RegistroLoteRepository.php
│   └── ✅ DistribuicaoLoteRepository.php
└── ✅ views/ (6 etapas)
    ├── ✅ etapa1_faixas.php           (Cadastro faixas)
    ├── ✅ etapa2_configuracao.php     (Embalamento)
    ├── ✅ etapa3_registro_lote.php    (Lote)
    ├── ✅ etapa3_resultado.php        (Resultado)
    ├── ✅ etapa4_distribuicao.php     (Distribuição)
    └── ✅ etapa5_resultado.php        (Resultado operacional)
```

### 2️⃣ Modelos de Dados

```
📊 FaixaPeso
├── id
├── nome_configuracao
├── calibre
├── peso_inicial
├── peso_final
└── saida

📊 ConfiguracaoEmbalamento
├── id
├── nome
├── faixa_peso_id
├── mapeamentos (JSON)

📊 RegistroLote
├── id
├── numero_lote
├── data_partida
├── produtor
├── observacao

📊 DistribuicaoLote
├── id
├── lote_id
├── faixa_peso_id
├── percentual
└── peso_calculado
```

### 3️⃣ Rotas/Actions Implementadas

**FaixaPeso:**
- ✅ criar_faixa
- ✅ atualizar_faixa
- ✅ deletar_faixa
- ✅ obter_faixas
- ✅ obter_faixas_config

**Configuração:**
- ✅ criar_configuracao
- ✅ atualizar_configuracao
- ✅ deletar_configuracao
- ✅ obter_configuracoes
- ✅ obter_configuracao

**Registro Lote:**
- ✅ criar_lote
- ✅ atualizar_lote
- ✅ salvar_lote
- ✅ deletar_lote
- ✅ obter_lotes
- ✅ obter_lote

**Distribuição:**
- ✅ criar_distribuicao
- ✅ atualizar_distribuicao
- ✅ salvar_distribuicao
- ✅ deletar_distribuicao
- ✅ obter_distribuicao

### 4️⃣ Dados JSON Existentes

**Exemplos de dados já criados:**
- 10 faixas de calibração (REFUGO, Caixa 12, Caixa 10, etc.)
- Embalagens configuradas (Caixa 4kg, Caixa 6kg, etc.)
- Registros de lotes de teste

---

## 🔴 PROBLEMAS CRÍTICOS (Corrigir ANTES de continuar)

### C-001: Locks sem Retry Logic
**Arquivo:** `repositories/BaseRepository.php`  
**Problema:** Lock file falha sem retry  
**Impacto:** Possível corrupção em concorrência  
**Solução:** Implementar retry até 3x com sleep 100ms

### C-002: Validação Numérica Inadequada
**Arquivo:** `controllers/CalbradoraController.php`  
**Problema:** Aceita "-100", "abc" via cast direto  
**Impacto:** Dados inválidos persistidos  
**Solução:** filter_var com FILTER_VALIDATE_FLOAT

### C-003: TODO não Implementado
**Arquivo:** `views/etapa3_resultado.php:59`  
**Problema:** Não salva mas mostra "sucesso"  
**Impacto:** Salvamento fantasma  
**Solução:** Implementar persistência real

### C-004: Fluxo Distribuição Quebrado
**Arquivo:** `views/etapa4_distribuicao.php:92`  
**Problema:** Busca por lote_id em vez de dist_id  
**Impacto:** Distribuição errada carregada  
**Solução:** Corrigir query e action

### C-005: Sem Proteção CSRF
**Arquivo:** Todas as 5 views  
**Problema:** Formulários vulneráveis  
**Impacto:** Ataque CSRF possível  
**Solução:** Token CSRF em POST

### C-006: Exceções Silenciosas (@)
**Arquivo:** `repositories/BaseRepository.php`  
**Problema:** @ supprime erros  
**Impacto:** Erros ocultos  
**Solução:** Remover @ e usar try/catch

---

## 🟠 PROBLEMAS ALTOS (Próxima Sprint)

### A-001: CSS Duplicado
**Problema:** 200+ linhas repetidas em etapa2,3,4,5  
**Solução:** Consolidar em styles_ui.php

### A-002: Validação Sobreposição Incompleta
**Problema:** Permite faixas sobrepostas  
**Solução:** Validar por configuração

### A-003: Sem Sanitização HTML
**Problema:** Risco XSS em outputs  
**Solução:** htmlspecialchars()

### A-004: Padrão Controller Inconsistente
**Problema:** 20+ métodos privados sem docs  
**Solução:** Documentação e padrão consistente

### A-005: Sem Validação de Limites
**Problema:** DoS possível com 10000 itens  
**Solução:** Validar quantidade máxima

---

## 🎯 RECOMENDAÇÕES POR OBJETIVO

### Objetivo 1: Simplificar para 3 Telas

**Etapa Atual:** 6 etapas  
**Etapa Proposta:** 3 telas principais

| Atual | Proposto | Ação |
|-------|----------|------|
| Etapa 1 - Faixas | **TELA 1 - Cadastro Faixas** | ✅ Reaproveitar |
| Etapa 2 - Config | **TELA 2 - Tipos Embalamento** | 🔄 Refatorar |
| Etapa 3 - Registro | **TELA 3 - Lançamento Partida** | 🔄 Simplificar |
| Etapa 3 - Resultado | (Integrado Tela 3) | ❌ Remover |
| Etapa 4 - Distribuição | (Integrado Tela 3) | ❌ Remover |
| Etapa 5 - Resultado | (Integrado Tela 3) | ❌ Remover |

### Objetivo 2: Unificar Visual com Balanceamento

**Padrão Balanceamento:**
- Cores: --industrial-blue (#007bff), --industrial-green (#28a745), --industrial-purple (#6f42c1)
- Cards com borda esquerda colorida
- Inputs com border suave, hover azul
- Tabelas com background alternado
- Botões com background sólido, hover mais escuro

**Padrão Calibradora (Atual):**
- Gradiente roxo (#667eea → #764ba2)
- Cards brancos com shadow
- Estilos dispersos em 6 arquivos

**Ação:** Aplicar padrão Balanceamento em styles_ui.php

### Objetivo 3: Evitar Duplicidade

**CSS Duplicado (200+ linhas):**
```
❌ etapa2_configuracao.php - <style> próprio
❌ etapa3_registro_lote.php - <style> próprio
❌ etapa3_resultado.php - <style> próprio
❌ etapa4_distribuicao.php - <style> próprio
❌ etapa5_resultado.php - <style> próprio

✅ styles_ui.php - Centralizado
```

**Ação:** Remover styles das views, usar styles_ui.php

---

## 📈 ESTRUTURA FINAL PROPOSTA

### TELA 1: Cadastro de Faixas

**Cabeçalho:**
- Campo "Descrição/Nome" (EXP, MI, CLASSIF, etc.)

**Tabela:**
- seq | calibre | peso inicial | peso final | tipo embalamento

**Funcionalidades:**
- ✅ Adicionar linha
- ✅ Editar linha
- ✅ Excluir linha
- ✅ Validar: peso_inicial < peso_final
- ✅ Validar: sem sobreposição na mesma config
- ✅ Ordenar por seq

**Banco:** `calibradora_faixas` (já existe, reaproveitar)

### TELA 2: Cadastro de Tipos de Embalamento

**Campos:**
- nome
- descrição
- peso nominal
- unidade
- status ativo/inativo

**Funcionalidades:**
- ✅ CRUD completo
- ✅ Listar apenas ativos por padrão
- ✅ Campo status true/false

**Banco:** `calibradora_tipos_embalamento` (criar novo)

### TELA 3: Lançamento Operacional (Nº Controle)

**Cabeçalho:**
- Nº Controle (auto ou manual)
- Cadastro selecionado (dropdown das TELA 1)
- Produtor
- Fazenda/Huerto
- Variedade
- Classe
- Peso Total
- Observação

**Ao selecionar cadastro:**
- Sistema carrega automaticamente as faixas (TELA 1)
- Sistema carrega tipos embalamento (TELA 2)

**Tabela:**
- seq | calibre | peso inicial | peso final | tipo embalamento | % | peso calculado

**Funcionalidades:**
- ✅ Editar percentuais
- ✅ Recalcular pesos automaticamente
- ✅ Mostrar soma percentual
- ✅ Destacar se soma ≠ 100%
- ✅ Mostrar total calculado
- ✅ Salvar histórico

**Banco:** 
- `calibradora_partidas` (RegistroLote)
- `calibradora_partida_itens` (DistribuicaoLote)

**Preparação para Futuro:**
- IDs estruturados para OCR
- Botão "Importar imagem" (não implementar OCR agora)
- Estrutura de importação pronta

---

## 🗂️ ESTRUTURA DE DADOS FINAL

```
calibradora_configuracoes
├── id (PK)
├── nome_descricao
└── status

calibradora_faixas (REAPROVEITAR)
├── id (PK)
├── configuracao_id (FK)
├── seq
├── calibre
├── peso_inicial
├── peso_final
└── tipo_embalamento_id (FK) ← NOVO CAMPO

calibradora_tipos_embalamento (NOVO)
├── id (PK)
├── nome
├── descricao
├── peso_nominal
├── unidade
└── status

calibradora_partidas (REAPROVEITAR - RegistroLote)
├── id (PK)
├── numero_controle
├── configuracao_id (FK)
├── produtor
├── fazenda
├── variedade
├── classe
├── peso_total
├── observacao
└── data

calibradora_partida_itens (REAPROVEITAR - DistribuicaoLote)
├── id (PK)
├── partida_id (FK)
├── faixa_id (FK)
├── percentual
└── peso_calculado
```

---

## 🚀 PLANO DE AÇÃO RECOMENDADO

### FASE 1: CRÍTICAS (Esta semana)
- [ ] Corrigir C-001 a C-006
- [ ] Validar persistência de dados
- [ ] Testar operações básicas
- **Tempo:** ~11h

### FASE 2: REFATORAÇÃO (Próxima semana)
- [ ] Remover CSS duplicado de views
- [ ] Aplicar padrão Balanceamento
- [ ] Consolidar em styles_ui.php
- [ ] Corrigir A-001 a A-005
- **Tempo:** ~8h

### FASE 3: SIMPLIFICAÇÃO (2ª semana)
- [ ] Criar TELA 2 (Tipos Embalamento)
- [ ] Refatorar TELA 3 para unificar etapas 3,4,5
- [ ] Remover etapas desnecessárias (3 orig, 4, 5)
- [ ] Documentação atualizada
- **Tempo:** ~12h

### FASE 4: PREPARAÇÃO FUTURA (3ª semana)
- [ ] Preparar estrutura para OCR
- [ ] Documentar IDs e relacionamentos
- [ ] Adicionar botão "Importar imagem" (sem OCR)
- [ ] Testes de integração
- **Tempo:** ~6h

---

## ✅ CHECKLIST DE VALIDAÇÃO FINAL

Antes de considerar "Revisão Completa":

### Segurança
- [ ] Sem vulnerabilidades C-001 a C-006
- [ ] CSRF tokens em todos POST
- [ ] Sanitização de inputs/outputs
- [ ] Sem exceções silenciosas

### Estrutura
- [ ] 3 telas funcionando
- [ ] Dados persistidos corretamente
- [ ] Sem CSS duplicado
- [ ] Padrão Balanceamento aplicado

### Funcionalidade
- [ ] TELA 1: CRUD faixas, validação, sem sobreposição
- [ ] TELA 2: CRUD tipos embalamento
- [ ] TELA 3: Carregamento automático, cálculos, salvamento histórico

### Preparação Futura
- [ ] IDs estruturados para OCR
- [ ] Botão importar imagem presente
- [ ] Documentação de importação preparada

### Código
- [ ] Sem duplicação CSS
- [ ] Métodos documentados
- [ ] Padrão consistente
- [ ] Performance adequada

---

## 📝 PRÓXIMOS PASSOS

1. **HOJE:** Revisar este documento, validar plano
2. **AMANHÃ:** Iniciar FASE 1 (críticas)
3. **Dia 3:** Completar FASE 1, passar em testes
4. **Semana 2:** FASE 2 (refatoração) + FASE 3 (simplificação)
5. **Semana 3:** FASE 4 (preparação futura) + validação final

---

## 📞 DÚVIDAS / DECISÕES NECESSÁRIAS

1. **Dados existentes corrompidos?**
   - Se SIM: Fazer reset dos dados JSON antes de FASE 1
   - Se NÃO: Proceder normalmente

2. **Banco de dados SQL vs. JSON?**
   - Manter JSON durante desenvolvimento?
   - Migrar para SQL depois?

3. **OCR - Timeline realista?**
   - Apenas preparar estrutura agora?
   - Quando implementar OCR real?

4. **Integração com outros módulos:**
   - Quando integrar com Balanceamento, Cronoanálise, MES/APS?
   - Documentar APIs de integração?

---

**Status:** ✅ Revisão Completa - Pronto para Ação  
**Próximo Passo:** Implementação FASE 1 (Segurança - Críticas)
