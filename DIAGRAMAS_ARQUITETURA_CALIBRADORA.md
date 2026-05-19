# 📊 DIAGRAMAS - ARQUITETURA CALIBRADORA

## ARQUITETURA ATUAL (6 Etapas)

```
┌─────────────────────────────────────────────────────────────────┐
│                      MÓDULO CALIBRADORA                         │
│                    /reformulacao/calibradora/                   │
└─────────────────────────────────────────────────────────────────┘

                           📑 INDEX.PHP (HUB)
                                  │
                 ┌────────────────┼────────────────┐
                 │                │                │
            ┌─────────────┐  ┌──────────────┐  ┌──────────────┐
            │  Etapa 1    │  │  Etapa 2     │  │  Etapa 3     │
            │  Faixas     │  │  Config      │  │  Registro    │
            │  (views/)   │  │  Embalamento │  │  Lote        │
            └─────────────┘  └──────────────┘  └──────────────┘
                 │                │                │
                 │                │                └──────┐
                 │                │                       │
            ┌─────────────┐  ┌──────────────┐  ┌──────────────┐
            │  Etapa 3b   │  │  Etapa 4     │  │  Etapa 5     │
            │  Resultado  │  │  Distribuição│  │  Resultado   │
            │  (intermedia)   │             │  │  Operacional │
            └─────────────┘  └──────────────┘  └──────────────┘

    📂 MODELOS              📂 REPOSITÓRIOS          📂 SERVIÇOS
    ├─ FaixaPeso           ├─ BaseRepository        ├─ CalbradoraService
    ├─ ConfiguracaoEmbal.  ├─ FaixaPesoRepository
    ├─ RegistroLote        ├─ ConfiguracaoEmbalRep.
    └─ DistribuicaoLote    ├─ RegistroLoteRepository
                           └─ DistribuicaoLoteRep.

    💾 DADOS JSON
    ├─ /data/reformulacao/calibradora.json
    ├─ faixas_peso.json
    ├─ configuracoes_embalamento.json
    ├─ registros_lote.json
    └─ distribuicoes_lote.json
```

---

## ARQUITETURA FUTURA (3 Telas Simplificadas)

```
┌─────────────────────────────────────────────────────────────────┐
│                      MÓDULO CALIBRADORA                         │
│                      (OTIMIZADO)                                │
└─────────────────────────────────────────────────────────────────┘

                           📑 INDEX.PHP (HUB)
                                  │
                 ┌────────────────┼────────────────┐
                 │                │                │
            ┌─────────────┐  ┌──────────────┐  ┌──────────────┐
            │  TELA 1     │  │  TELA 2      │  │  TELA 3      │
            │  Cadastro   │  │  Cadastro    │  │  Lançamento  │
            │  Faixas     │  │  Tipos       │  │  Operacional │
            │             │  │  Embalamento │  │              │
            └─────────────┘  └──────────────┘  └──────────────┘
                 │                │                │
                 │                │                │
            ┌────────────────┐────────────────────┼─────────────┐
            │                │                    │             │
       Tabela Faixas    Tabela Embal.        Formulário +    Resultados
       CRUD completo    CRUD completo       Tabela + Cálcs  Automáticos

    📂 MODELOS (SEM MUDANÇA)
    ├─ FaixaPeso (novo campo: tipo_embalamento_id)
    ├─ TipoEmbalamento (NOVO)
    ├─ RegistroLote (mesma)
    └─ DistribuicaoLote (mesma)

    📂 REPOSITÓRIOS (SEM MUDANÇA)
    ├─ BaseRepository (corrigido: locks + validação)
    ├─ FaixaPesoRepository (mesma)
    ├─ TipoEmbalamentoRepository (NOVO)
    ├─ RegistroLoteRepository (mesma)
    └─ DistribuicaoLoteRepository (mesma)

    📂 SERVIÇOS (SEM MUDANÇA)
    └─ CalbradoraService (otimizada)

    💾 DADOS JSON (MESMA ESTRUTURA)
    ├─ /data/reformulacao/calibradora/
    ├─ faixas_peso.json (estrutura estendida)
    ├─ tipos_embalamento.json (NOVO)
    ├─ registros_lote.json (mesma)
    └─ distribuicoes_lote.json (mesma)
```

---

## FLUXO DE DADOS - TELA 1 (Cadastro Faixas)

```
┌─────────────────────────────────────────────────────────────────┐
│              TELA 1: CADASTRO DE FAIXAS                         │
└─────────────────────────────────────────────────────────────────┘

    1. Usuário seleciona Configuração
                    │
                    ▼
    2. GET /etapa1_faixas.php?config=EXP
                    │
        ┌───────────┴───────────┐
        │                       │
    Listar Configs         Carregar Faixas
    (DISTINCT)            (WHERE config=EXP)
        │                       │
        └───────────┬───────────┘
                    │
                    ▼
    3. Renderizar Seletor + Tabela Faixas
                    │
        ┌───────────┴───────────┐
        │                       │
    Dropdown Configs       Tabela Faixas
    (Select)              (with Tipos Embal.)
        │                       │
        └───────────┬───────────┘
                    │
                    ▼
    4. Usuário preenche novo Formulário
        - Calibre
        - Peso Inicial
        - Peso Final
        - Tipo Embalamento (carreg. TELA 2)
        - Seq
                    │
                    ▼
    5. POST /etapa1_faixas.php action=criar_faixa
                    │
        ┌───────────┴──────────────────────┐
        │                                  │
    Validação Front                  Controller
    - Peso Ini < Fim              - CSRF Token
    - Sem negativos               - filter_var() ← SAFE
    - Sem vazio                   - Validar sobreposição
                    │                  │
                    └──────────┬────────┘
                               │
                    ✅ Se válido
                               │
                               ▼
                    6. CalbradoraService->criarFaixa()
                               │
                               ▼
                    7. FaixaPesoRepository->create()
                               │
                               ▼
                    8. SafeStorage->saveJSON()
                    (com LOCK_EX)
                               │
                               ▼
                    9. Tabela recarrega (GET)
                               │
                               ▼
                    10. Alert "✅ Faixa salva"
```

---

## FLUXO DE DADOS - TELA 2 (Tipos Embalamento)

```
┌─────────────────────────────────────────────────────────────────┐
│         TELA 2: CADASTRO DE TIPOS DE EMBALAMENTO                │
└─────────────────────────────────────────────────────────────────┘

    1. Usuário abre TELA 2
                    │
                    ▼
    2. GET /etapa2_tipos_embalamento.php
                    │
                    ▼
    3. Carregar Tipos Embalamento (status=1)
    SELECT * FROM calibradora_tipos_embalamento
    WHERE status = 1 ORDER BY nome
                    │
                    ▼
    4. Renderizar Tabela
        - Nome
        - Descrição (truncado)
        - Peso Nominal + Unidade
        - Status (badge)
        - Ações (Editar, Deletar)
                    │
                    ▼
    5. Usuário clica "+ Novo Tipo"
                    │
                    ▼
    6. Modal/Formulário abre
        - Nome (text)
        - Descrição (textarea)
        - Peso Nominal (number)
        - Unidade (select: kg, g, unidade)
        - Status (toggle: ativo/inativo)
                    │
                    ▼
    7. POST action=criar_configuracao
                    │
        ┌───────────┴───────────────────────┐
        │                                   │
    Validação Front                  Controller
    - Nome não vazio              - CSRF Token
    - Peso > 0                    - Validar nome único
    - Unidade selecionada         - Sanitizar HTML (A-003)
                    │                  │
                    └──────────┬────────┘
                               │
                    ✅ Se válido
                               │
                               ▼
                    8. TipoEmbalamentoRepository->create()
                               │
                               ▼
                    9. SafeStorage->saveJSON()
                    (tipos_embalamento.json)
                               │
                               ▼
                    10. Tabela recarrega
                               │
                               ▼
                    11. Dropdown em TELA 1 atualiza
                    (carregamento automático via AJAX)
```

---

## FLUXO DE DADOS - TELA 3 (Lançamento Operacional)

```
┌─────────────────────────────────────────────────────────────────┐
│     TELA 3: LANÇAMENTO OPERACIONAL (Nº CONTROLE)               │
└─────────────────────────────────────────────────────────────────┘

    1. Usuário abre TELA 3
                    │
                    ▼
    2. GET /etapa3_lancamento_operacional.php
                    │
                    ▼
    3. Renderizar Formulário Cabeçalho
        - Nº Controle (auto-gerado)
        - Configuração (dropdown vazio - TELA 1)
        - Produtor
        - Fazenda
        - Variedade
        - Classe
        - Peso Total
        - Observação
                    │
                    ▼
    4. Usuário seleciona Configuração
                    │
                    ▼
    5. AJAX fetch /?action=obter_faixas_config&config=EXP
                    │
        ┌───────────┴──────────┐
        │                      │
    FaixaPesoRepository    Resposta JSON
    getFaixasByConfig()    [faixa1, faixa2...]
        │                      │
        └───────────┬──────────┘
                    │
                    ▼
    6. JavaScript renderiza Tabela de Faixas
        - Seq, Calibre, Peso Ini, Peso Fim, Tipo Embal.
        - EDITABLE: % Distribuição
        - CALCULATED: Peso Calculado
                    │
                    ▼
    7. Usuário preenche % em cada linha
                    │
                    ▼
    8. JavaScript recalcula:
        - Soma percentual
        - Peso calculado (peso_total × % / 100)
        - Validação: destaca se soma ≠ 100%
                    │
                    ▼
    9. Usuário preenche Cabeçalho + clica "Salvar Partida"
                    │
                    ▼
    10. Validação Front-End
        - Todos obrigatórios preenchidos? ✓
        - Soma = 100%? ✓
        - Peso total ≈ peso calculado? ✓
                    │
                    ▼
    11. POST /etapa3_lancamento_operacional.php
        action=salvar_partida
                    │
        ┌───────────┴──────────────────────────────┐
        │                                          │
    Validação Back-End (C-002)                     │
    - filter_var() todos números              Controller
    - CSRF Token validado                  
    - Nº Controle único?
    - Soma = 100%?
    - Sem negativos?
    - Sem valores "abc"?
        │                                          │
        └───────────┬──────────────────────────────┘
                    │
                    ▼
        ✅ Se válido
                    │
        ┌───────────┴──────────────────────────────┐
        │                                          │
    RegistroLoteRepository                 DistribuicaoLoteRep.
    ->create($partida)                     ->create($item)
         │                                      │
         ▼                                      ▼
    INSERT partida                         INSERT partida_itens
                    │                           │
                    └───────────┬───────────────┘
                               │
                               ▼
                    12. SafeStorage->saveJSON()
                    - registros_lote.json
                    - distribuicoes_lote.json
                               │
                               ▼
                    13. Alert "✅ Partida #123 salva"
                               │
                               ▼
                    14. Histórico atualizado
                    (lista de partidas salvas)
```

---

## FLUXO DE VALIDAÇÃO - CRÍTICAS (C-001 a C-006)

```
REQUEST
  │
  ├─→ CSRF Token Validation (C-005)
  │   ├─ Se inválido: Retorna erro 403
  │   └─ Se válido: Continua
  │
  ├─→ Input Validation (C-002 ← CRÍTICO)
  │   ├─ filter_var(FILTER_VALIDATE_FLOAT)
  │   ├─ filter_var(FILTER_VALIDATE_INT)
  │   ├─ Valida ranges (min, max)
  │   ├─ Sem valores negativos
  │   └─ Se falhar: Retorna erro
  │
  ├─→ Business Logic Validation
  │   ├─ Sobreposição faixas (A-002)
  │   ├─ Unicidade (nº controle)
  │   ├─ Soma percentuais = 100%
  │   └─ Se falhar: Retorna erro
  │
  ├─→ Repository Operation (C-001 ← CRÍTICO)
  │   ├─ Acquire LOCK_SH (read) ou LOCK_EX (write)
  │   ├─ Se falha: Retry até 3x, sleep 100ms (C-001)
  │   ├─ Se após 3x falha: Retorna erro
  │   └─ Se sucesso: Processa
  │
  ├─→ Sanitization (A-003)
  │   ├─ htmlspecialchars() em strings
  │   ├─ Trim whitespace
  │   └─ Remove tags perigosas
  │
  ├─→ Save JSON
  │   ├─ Escreve em arquivo temporário
  │   ├─ Valida JSON sintaxe
  │   ├─ Move para arquivo final (atomicidade)
  │   └─ Release LOCK_EX
  │
  └─→ Response
      ├─ Se sucesso: 200 + JSON sucesso
      └─ Se erro: 400/403 + mensagem erro


SEGURANÇA IMPLEMENTADA
├─ C-001: Retry logic em locks
├─ C-002: filter_var strict + ranges
├─ C-003: Salvamento real implementado
├─ C-004: Query corrigida (distribuição)
├─ C-005: CSRF tokens em session
└─ C-006: try/catch ao invés de @
```

---

## MATRIZ DE RELACIONAMENTOS

```
calibradora_configuracoes
    │
    ├─→ calibradora_faixas (1:N)
    │   │
    │   ├─→ calibradora_tipos_embalamento (N:1)
    │   │   └─ via tipo_embalamento_id
    │   │
    │   └─→ calibradora_partida_itens (1:N)
    │       └─ via faixa_id
    │
    └─→ calibradora_partidas (1:N)
        │
        └─→ calibradora_partida_itens (1:N)
            └─ via partida_id


OPERAÇÕES TÍPICAS
┌──────────────────────────────────────────────────────────┐
│ TELA 1 → Criar Faixa                                     │
├──────────────────────────────────────────────────────────┤
│ 1. SELECT * FROM calibradora_tipos_embalamento           │
│    WHERE status = 1                                      │
│ 2. INSERT INTO calibradora_faixas                        │
│    (configuracao_id, seq, calibre, peso_inicial,         │
│     peso_final, tipo_embalamento_id)                     │
│ 3. VALIDATE: Sem sobreposição de pesos                   │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ TELA 3 → Salvar Partida                                  │
├──────────────────────────────────────────────────────────┤
│ 1. INSERT INTO calibradora_partidas                      │
│    (numero_controle, configuracao_id, produtor,          │
│     fazenda, variedade, classe, peso_total,              │
│     observacao, data)                                    │
│ 2. SELECT * FROM calibradora_faixas                      │
│    WHERE configuracao_id = ?                             │
│ 3. INSERT INTO calibradora_partida_itens                 │
│    (partida_id, faixa_id, percentual, peso_calculado)    │
│    para cada faixa                                       │
│ 4. VALIDATE: Soma percentual = 100%                      │
└──────────────────────────────────────────────────────────┘
```

---

**Diagramas:** Arquitetura Calibradora  
**Data:** 16 de Maio de 2026  
**Próximo:** Implementar FASE 1 (Críticas)
