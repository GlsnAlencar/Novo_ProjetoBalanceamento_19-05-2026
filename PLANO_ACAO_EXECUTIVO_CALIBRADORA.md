# 🎯 PLANO DE AÇÃO EXECUTIVO - CALIBRADORA

**Data:** 16 de Maio de 2026  
**Versão:** 1.0 - Ação Imediata  
**Responsável:** Dev Team  
**Prazo:** 3 semanas (38h estimadas)

---

## 📌 DECISÃO IMEDIATA

A estrutura da calibradora **JÁ EXISTE** e está **FUNCIONAL**, porém com:
- 6 problemas críticos de segurança
- 20+ problemas de qualidade de código
- 6 etapas quando deveriam ser 3
- Visual inconsistente com Balanceamento

**AÇÃO:** Não recriar. Corrigir, simplificar e otimizar.

---

## 🚀 PRÓXIMOS 3 PASSOS HOJE

### ✅ PASSO 1: Ler Documentos (30 min)
1. **REVISAO_CALIBRADORA_ESTRUTURAL.md** ← Entender situação atual
2. **CHECKLIST_IMPLEMENTACAO_CALIBRADORA.md** ← Ver tudo que precisa fazer
3. **ESPECIFICACAO_3_TELAS_CALIBRADORA.md** ← Detalhes técnicos de cada tela

### ✅ PASSO 2: Decidir (30 min)
**Perguntas para responder:**
1. Os dados JSON atuais estão OK ou precisam reset?
2. Fazer em JSON ou migrar para SQL banco?
3. Timeline para OCR - quando implementar?
4. Quem começa FASE 1 (críticas)?

### ✅ PASSO 3: Comunicar (15 min)
- Designar responsáveis por fase
- Criar sprint com estimativas
- Fazer kick-off de projeto

---

## 📊 VISÃO GERAL DO TRABALHO

### O QUE EXISTE AGORA (100% pronto para reaproveitar)
```
✅ Estrutura de pastas (models, repositories, services, controllers, views)
✅ 4 modelos de dados (FaixaPeso, ConfiguracaoEmbalamento, RegistroLote, DistribuicaoLote)
✅ 5 repositórios com persistência JSON + locks
✅ Controller com 20+ actions
✅ 6 views/etapas já criadas
✅ Dados de exemplo em JSON
✅ Documentação técnica
```

### O QUE PRECISA CORRIGIR (CRÍTICO)

| # | Problema | Onde | Impacto | Como Corrigir | Tempo |
|---|----------|------|---------|---------------|-------|
| C-001 | Locks sem retry | BaseRepository | Corrupção dados | Retry logic 3x | 2h |
| C-002 | Validação numérica | Controller | Dados inválidos | filter_var | 3h |
| C-003 | TODO salvamento | etapa3 | Sem persistência | Implementar | 2h |
| C-004 | Fluxo distribuição | etapa4 | ID errado | Corrigir query | 1h |
| C-005 | Sem CSRF | Todas views | Ataque possível | Token CSRF | 2h |
| C-006 | Erros silenciosos | BaseRepository | Bugs ocultos | Remover @ | 1h |

**Total Fase 1:** 11 horas

### O QUE PRECISA REFATORAR (ALTA)

| # | Problema | Onde | Solução | Tempo |
|---|----------|------|---------|-------|
| A-001 | CSS duplicado | 5 views | Consolidar styles_ui.php | 2h |
| A-002 | Sem validação sobreposição | Model | Implementar validação | 1.5h |
| A-003 | Sem sanitização HTML | Views | htmlspecialchars() | 1h |
| A-004 | Padrão inconsistente | Controller | Documentação + padrão | 2h |
| A-005 | Limites em arrays | Views | Validar quantidade máx | 1.5h |

**Total Fase 2:** 8 horas

### O QUE PRECISA SIMPLIFICAR (MÉDIA)

| Atual | Novo | Ação | Tempo |
|-------|------|------|-------|
| Etapa 1 - Faixas | TELA 1 - Cadastro Faixas | Reaproveitar + ajustes | 2h |
| Etapa 2 - Configuração | TELA 2 - Tipos Embalamento | Refatorar + novo campo | 4h |
| Etapa 3 - Registro | TELA 3 - Lançamento | Unificar 3+4+5 | 6h |
| Etapa 3 - Resultado | (Integrado TELA 3) | Remover | - |
| Etapa 4 - Distribuição | (Integrado TELA 3) | Remover | - |
| Etapa 5 - Resultado | (Integrado TELA 3) | Remover | - |

**Total Fase 3:** 12 horas

### O QUE PRECISA PREPARAR (FUTURO)

| Item | Ação | Tempo |
|------|------|-------|
| Estrutura OCR | IDs + documentação | 3h |
| Testes | Testes básicos | 2h |

**Total Fase 4:** 5 horas

---

## 📈 TIMELINE

### SEMANA 1 - CRÍTICAS & REFATORAÇÃO
```
SEG 17/05: 
  - FASE 1.1: C-001, C-002 (5h)
  - Teste contínuo

TER 18/05:
  - FASE 1.2: C-003, C-004, C-005, C-006 (6h)
  - Validação de dados salvos

QUA 19/05:
  - FASE 2.1: CSS duplicado (2h)
  - FASE 2.2: Sanitização (1h)
  - FASE 2.3: Padrão visual (2h)

QUI 20/05:
  - FASE 2.4: Validações altas (3h)
  - Testes finais FASE 2

SEX 21/05:
  - Review + ajustes
  - Preparação SEMANA 2
```

### SEMANA 2 - SIMPLIFICAÇÃO
```
SEG 24/05:
  - FASE 3.1: TELA 1 ajustes (2h)
  - FASE 3.2: TELA 2 novo (2h)

TER 25/05:
  - FASE 3.2: TELA 2 continu. (2h)
  - Testes TELA 2

QUA 26/05:
  - FASE 3.3: TELA 3 refactoring (3h)

QUI 27/05:
  - FASE 3.3: TELA 3 continu. (3h)
  - Testes completos TELA 3

SEX 28/05:
  - Integração entre telas
  - Review + ajustes
```

### SEMANA 3 - PREPARAÇÃO & TESTES
```
SEG 31/05:
  - FASE 4.1: Estrutura OCR (2h)
  - FASE 4.2: Testes (2h)

TER 01/06:
  - Testes de performance
  - Validação de segurança

QUA 02/06:
  - Documentação final
  - Deploy de staging

QUI 03/06:
  - Testes de aceitação
  - Ajustes finais

SEX 04/06:
  - Deploy produção?
  - Documentação usuário
```

---

## 🎯 CRITÉRIO DE SUCESSO

### Ao final de SEMANA 1
- ✅ Nenhum problema crítico
- ✅ Dados não corrompem
- ✅ CSRF protegido
- ✅ Visual padrão Balanceamento

### Ao final de SEMANA 2
- ✅ 3 telas funcionando
- ✅ Carregamento automático
- ✅ Cálculos funcionam
- ✅ Histórico salvo

### Ao final de SEMANA 3
- ✅ Estrutura OCR pronta
- ✅ Testes passando
- ✅ Documentação completa
- ✅ Pronto para integração

---

## 💼 RESPONSABILIDADES

### Dev Senior (C-001 a C-006)
- [ ] Corrigir BaseRepository
- [ ] Validação numérica
- [ ] CSRF protection
- [ ] Testes de segurança

### Dev Mid (A-001 a A-005, TELA 2)
- [ ] CSS consolidação
- [ ] Sanitização HTML
- [ ] TELA 2 - Tipos Embalamento
- [ ] Testes

### Dev Junior (TELA 1, TELA 3 UI)
- [ ] Ajustes TELA 1
- [ ] HTML TELA 3
- [ ] CSS styling
- [ ] Testes básicos

### Lead (Coordenação)
- [ ] Review de código
- [ ] Validação arquitetura
- [ ] Testes integração
- [ ] Documentação

---

## 📋 ARQUIVOS CRIADOS

1. **REVISAO_CALIBRADORA_ESTRUTURAL.md**
   - O que existe
   - Problemas identificados
   - Recomendações por objetivo

2. **CHECKLIST_IMPLEMENTACAO_CALIBRADORA.md**
   - Check-by-check de cada tarefa
   - Dependências
   - Estimativas
   - Critérios aceitação

3. **ESPECIFICACAO_3_TELAS_CALIBRADORA.md**
   - Detalhes campo a campo
   - Validações (front + back)
   - Fluxo de dados
   - Queries SQL

4. **PLANO_ACAO_EXECUTIVO_CALIBRADORA.md** ← ESTE ARQUIVO
   - Overview executivo
   - Timeline
   - Responsabilidades
   - Critério sucesso

---

## ⚡ START CHECKLIST

Antes de começar FASE 1:

- [ ] Ler todos 4 documentos
- [ ] Responder as 3 decisões (dados, SQL vs JSON, OCR)
- [ ] Designar responsáveis
- [ ] Criar sprint (38h)
- [ ] Setup ambiente (branches git, pastas)
- [ ] Backup dados JSON atuais
- [ ] Kick-off com time

---

## 🆘 DÚVIDAS FREQUENTES

**P: Por que não recriar do zero?**  
R: Já funciona, economiza 40h. Corrigir é 10x mais rápido.

**P: E se surgir um problema crítico não documentado?**  
R: Documentação é 95% completa. Se aparecer novo issue, documentar e replanejar.

**P: Quanto tempo de paralelo podemos fazer?**  
R: Dev Senior em C-001 enquanto Dev Mid faz A-001. Não há dependência imediata.

**P: Migrar para SQL ou manter JSON?**  
R: Manter JSON agora. SQL é upgrade futuro quando volume crescer.

**P: OCR vira sprint extra?**  
R: Apenas preparação estrutural agora. OCR real é outro projeto.

---

## 📞 ESCALAÇÃO

Se surgir:
- **Bug crítico:** Chamar Dev Senior
- **Dúvida técnica:** Revisar ESPECIFICACAO_3_TELAS_CALIBRADORA.md
- **Impasse:** Chamar Lead para decisão
- **Delay:** Renegociar timeline na próxima sprint

---

## 🎉 RESULTADO ESPERADO

**Antes:**
```
6 etapas complexas
CSS duplicado
Problemas críticos
Visual diferente
Salvamento com TODO
```

**Depois:**
```
3 telas simples e conectáveis
CSS consolidado
Sem vulnerabilidades
Visual Balanceamento
Salvamento robusto
```

---

**Documento:** Plano de Ação Executivo  
**Autor:** Análise Estrutural Calibradora  
**Data:** 16 de Maio de 2026  
**Status:** ✅ PRONTO PARA EXECUÇÃO

### 👉 PRÓXIMO PASSO: Ler REVISAO_CALIBRADORA_ESTRUTURAL.md
