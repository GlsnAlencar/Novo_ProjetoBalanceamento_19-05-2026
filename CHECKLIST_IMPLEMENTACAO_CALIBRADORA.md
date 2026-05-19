# 📋 CHECKLIST DETALHADO - IMPLEMENTAÇÃO CALIBRADORA

**Data:** 16 de Maio de 2026  
**Objetivo:** Reaproveitar e otimizar estrutura existente  
**Escopo:** 3 telas principais + preparação OCR

---

## 🔴 FASE 1: CORREÇÕES CRÍTICAS

### C-001: Locks sem Retry
- [ ] Arquivo: `repositories/BaseRepository.php`
- [ ] Implementar retry logic (máx 3x, sleep 100ms)
- [ ] Validar permissões antes de fopen()
- [ ] Testar com acesso simultâneo
- **Status:** ⚪ Planejado

### C-002: Validação Numérica
- [ ] Arquivo: `controllers/CalbradoraController.php`
- [ ] Substituir (float) cast por filter_var
- [ ] Validar ranges (min=0, max=999999)
- [ ] Testar com "-100", "abc", "999999999"
- [ ] Arquivo: `models/DistribuicaoLote.php`
- [ ] Validação de percentuais (0-100)
- **Status:** ⚪ Planejado

### C-003: Salvamento TODO
- [ ] Arquivo: `views/etapa3_resultado.php:59`
- [ ] Implementar persistência real
- [ ] Chamar service->criarRegistroLote()
- [ ] Testar salvamento e recuperação
- **Status:** ⚪ Planejado

### C-004: Fluxo Distribuição
- [ ] Arquivo: `views/etapa4_distribuicao.php:92`
- [ ] Corrigir busca (usar dist_id, não lote_id)
- [ ] Adicionar action no controller se necessário
- [ ] Testar carregamento correto
- **Status:** ⚪ Planejado

### C-005: Proteção CSRF
- [ ] Todas as 5 views (etapa1 a etapa5)
- [ ] Gerar token em $_SESSION
- [ ] Validar em POST antes de processar
- [ ] Implementar ASAP, testar
- **Status:** ⚪ Planejado

### C-006: Exceções Silenciosas
- [ ] Arquivo: `repositories/BaseRepository.php`
- [ ] Remover @ operators
- [ ] Usar try/catch apropriado
- [ ] Logar erros para debug
- **Status:** ⚪ Planejado

---

## 🟠 FASE 2: REFATORAÇÃO VISUAL

### A-001: CSS Duplicado
- [ ] Arquivo: `views/etapa2_configuracao.php`
  - [ ] Mover <style> para styles_ui.php
  - [ ] Remover <style> da view
- [ ] Arquivo: `views/etapa3_registro_lote.php`
  - [ ] Mover <style> para styles_ui.php
- [ ] Arquivo: `views/etapa3_resultado.php`
  - [ ] Mover <style> para styles_ui.php
- [ ] Arquivo: `views/etapa4_distribuicao.php`
  - [ ] Mover <style> para styles_ui.php
- [ ] Arquivo: `views/etapa5_resultado.php`
  - [ ] Mover <style> para styles_ui.php
- **Status:** ⚪ Planejado

### A-003: Sanitização HTML
- [ ] Arquivo: `views/etapa2_configuracao.php:38`
- [ ] Envolver outputs com htmlspecialchars()
- [ ] Verificar todas as outras views
- **Status:** ⚪ Planejado

### Padrão Visual Balanceamento
- [ ] Aplicar cores: --industrial-blue, --industrial-green, --industrial-purple
- [ ] Aplicar cards com border esquerda colorida
- [ ] Inputs com border suave, focus azul
- [ ] Tabelas com alternância de cores
- [ ] Botões com padrão consistent
- [ ] Arquivo: `public/reformulacao/calibradora/styles_ui.php`
- **Status:** ⚪ Planejado

---

## 🟡 FASE 3: SIMPLIFICAÇÃO (3 TELAS)

### Tela 1: Cadastro de Faixas (REAPROVEITAR)
**Arquivo Existente:** `etapa1_faixas.php`

Manter:
- ✅ Seletor de configuração
- ✅ CRUD de faixas
- ✅ Tabela com seq | calibre | peso_inicial | peso_final

Adicionar:
- [ ] Campo "tipo_embalamento_id" em cada linha
- [ ] Dropdown com tipos embalamento (carregados de TELA 2)
- [ ] Validação: sem sobreposição
- [ ] Validação: peso_inicial < peso_final

Remover:
- ❌ Estilos duplicados (já consolidados em styles_ui.php)

**Status:** ⚪ Planejado

### Tela 2: Cadastro de Tipos de Embalamento (NOVO)

**Arquivo:** `views/etapa2_tipos_embalamento.php` (criar novo)

Estrutura:
```html
Header: Cadastro de Tipos de Embalamento

Tabela:
- id | nome | descrição | peso nominal | unidade | status

Formulário:
- Nome (texto)
- Descrição (textarea)
- Peso nominal (número)
- Unidade (select: kg, g, unidade)
- Status (toggle ativo/inativo)

Ações:
- ➕ Novo tipo
- 📝 Editar
- ❌ Deletar
```

Banco:
- [ ] Criar table `calibradora_tipos_embalamento`
- [ ] Campos: id, nome, descricao, peso_nominal, unidade, status

**Status:** ⚪ Planejado

### Tela 3: Lançamento Operacional (REFATORAR + UNIFICAR)

**Arquivo Novo:** `views/etapa3_lancamento_operacional.php`

Integrar:
- ❌ `etapa3_registro_lote.php` (remover)
- ❌ `etapa3_resultado.php` (integrar em nova tela)
- ❌ `etapa4_distribuicao.php` (integrar em nova tela)
- ❌ `etapa5_resultado.php` (integrar em nova tela)

Estrutura:
```html
Header:
- Nº Controle (auto-gerado ou manual)
- Cadastro selecionado (dropdown)
- Produtor
- Fazenda/Huerto
- Variedade
- Classe
- Peso Total
- Observação

[Ao selecionar cadastro, carrega faixas e tipos embalamento automaticamente]

Tabela:
seq | calibre | peso inicial | peso final | tipo embalamento | % | peso calculado

[Usuário edita percentuais]
[Sistema calcula pesos automaticamente]

Resultados:
- Soma percentual (destaca se ≠ 100%)
- Total calculado
- Botão Salvar Partida
```

Banco:
- [ ] Usar `calibradora_partidas` (RegistroLote)
- [ ] Usar `calibradora_partida_itens` (DistribuicaoLote)
- [ ] Adicionar campo `numero_controle` a partidas

**Status:** ⚪ Planejado

---

## 🟦 FASE 4: PREPARAÇÃO PARA OCR

### Estrutura de Importação
- [ ] Arquivo: `views/etapa3_lancamento_operacional.php`
- [ ] Adicionar botão "📷 Importar imagem"
- [ ] Preparar modal para upload
- [ ] Estrutura JSON para metadados OCR

### Documentação de Importação
- [ ] Criar arquivo: `GUIA_IMPORTACAO_OCR.md`
- [ ] Especificar campos OCR esperados
- [ ] Exemplos de payload JSON
- [ ] IDs de campos para mapeamento

### IDs Estruturados
- [ ] Cada campo tem ID único: `ocr_numero_controle`, `ocr_peso_total`, etc.
- [ ] Tabela itens com IDs para mapping OCR
- [ ] Estrutura JSON para relacionamentos

**Status:** ⚪ Planejado

---

## 🔗 DEPENDÊNCIAS ENTRE TAREFAS

```
C-001 (Locks)
├─→ C-002 (Validação) [ambas em BaseRepository]
├─→ C-003 (Salvamento)
├─→ C-004 (Fluxo)
└─→ C-005 (CSRF)

Fase 1 (Críticas)
└─→ Fase 2 (Refatoração)
    └─→ Fase 3 (Simplificação)
        └─→ Fase 4 (OCR)
```

---

## 📊 ESTIMATIVAS DE TEMPO

| Fase | Tarefa | Tempo | Status |
|------|--------|-------|--------|
| **1** | C-001 a C-006 | 11h | ⚪ Planejado |
| **2** | CSS duplicado | 2h | ⚪ Planejado |
| **2** | A-001 a A-005 | 6h | ⚪ Planejado |
| **2** | Padrão visual | 2h | ⚪ Planejado |
| **3** | Tela 1 (ajustes) | 2h | ⚪ Planejado |
| **3** | Tela 2 (novo) | 4h | ⚪ Planejado |
| **3** | Tela 3 (refatorar + unificar) | 6h | ⚪ Planejado |
| **4** | Preparação OCR | 3h | ⚪ Planejado |
| **4** | Testes finais | 2h | ⚪ Planejado |
| | **TOTAL** | **38h** | |

---

## ✅ CRITÉRIOS DE ACEITAÇÃO

### Fase 1 Completa
- ✅ Nenhuma vulnerabilidade crítica
- ✅ Dados persistem corretamente
- ✅ Acesso simultâneo sem corrupção
- ✅ Testes de validação passam

### Fase 2 Completa
- ✅ CSS consolidado em styles_ui.php
- ✅ Padrão visual Balanceamento aplicado
- ✅ Sem duplicação
- ✅ Sanitização em todos outputs

### Fase 3 Completa
- ✅ 3 telas funcionando
- ✅ Dados carregam automaticamente
- ✅ Cálculos funcionam
- ✅ Histórico de partidas salvo

### Fase 4 Completa
- ✅ Estrutura OCR documentada
- ✅ Botão importar imagem presente
- ✅ IDs estruturados para mapeamento
- ✅ Documentação completa

---

## 📝 NOTAS IMPORTANTES

1. **Não criar do zero** - Reaproveitar tudo existente
2. **Sem duplicação** - CSS consolidado, rotas reutilizadas
3. **Não alterar Balanceamento** - Apenas aplicar padrão visual
4. **Teste contínuo** - Validar após cada fase
5. **Documentação** - Manter atualizada

---

**Documento:** Checklist Detalhado  
**Última Atualização:** 16 de Maio de 2026  
**Status:** ✅ Pronto para Execução
