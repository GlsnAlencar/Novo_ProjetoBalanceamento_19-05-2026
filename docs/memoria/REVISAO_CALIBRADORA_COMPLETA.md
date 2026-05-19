# 📦 REVISÃO COMPLETA - MÓDULO CALIBRADORA

**Data de Revisão:** 15/05/2026  
**Status:** Estrutura base criada, pronta para ajustes e complementação  
**Objetivo:** Estruturar em telas simples, conectáveis e funcionais

---

## 1. RESUMO DO QUE JÁ FOI CRIADO

### ✅ Arquitetura MVC Implementada

#### Models (Entidades do Domínio)
```
/public/reformulacao/calibradora/models/
├── FaixaPeso.php              ✅ Representa faixa de peso (seq, calibre, peso_ini, peso_fim)
├── ConfiguracaoEmbalamento.php ✅ Define tipo de embalamento por faixa
├── RegistroLote.php            ✅ Registro de lote processado (controle, produtor, etc.)
└── DistribuicaoLote.php        ✅ Distribuição percentual do lote
```

**Características:**
- Validações básicas implementadas
- Métodos `toArray()` e `fromArray()` para persistência
- Lógica de sobreposição de faixas já prevista

#### Repositories (Acesso a Dados)
```
/public/reformulacao/calibradora/repositories/
├── BaseRepository.php                      ✅ Classe base com lock para JSON
├── FaixaPesoRepository.php                 ✅ CRUD de faixas
├── ConfiguracaoEmbalamentoRepository.php   ✅ CRUD de configurações
├── RegistroLoteRepository.php              ✅ CRUD de registros
└── DistribuicaoLoteRepository.php          ✅ CRUD de distribuições
```

**Características:**
- Sistema de lock para evitar conflito de escrita
- Persistência em JSON com validação
- Métodos seguro para read/write

#### Service (Lógica de Negócio)
```
/public/reformulacao/calibradora/services/
└── CalbradoraService.php  ✅ Orquestra toda lógica com validações
```

**Métodos Disponíveis:**
- `getFaixas()`, `getFaixasPorConfiguracao()`, `criarFaixa()`, `atualizarFaixa()`, `deletarFaixa()`
- `getConfiguracoes()`, `criarConfiguracao()`, `atualizarConfiguracao()`, `deletarConfiguracao()`
- `criarRegistroLote()`, `salvarRegistroLote()`, `obterRegistrosLote()`
- `criarDistribuicaoLote()`, `salvarDistribuicaoLote()`, `gerarResultadoOperacional()`

#### Controller (Processamento HTTP)
```
/public/reformulacao/calibradora/controllers/
└── CalbradoraController.php  ✅ Processa requisições HTTP com validação
```

**Padrão:**
- Todos os requests passam por `processarRequisicao($action, $data)`
- Retorna estrutura padrão: `['sucesso' => bool, 'mensagem' => str, 'dados' => mixed]`

### ✅ Persistência em JSON

```
/data/reformulacao/calibradora/
├── faixas_peso.json                    (faixas cadastradas)
├── configuracoes_embalamento.json      (tipos de embalamento)
├── registros_lote.json                 (histórico de lotes)
├── distribuicoes_lote.json             (cálculos de distribuição)
└── *.lock                              (controle de concorrência)
```

### ✅ Views (5 Etapas Estruturadas)

```
/public/reformulacao/calibradora/views/
├── etapa1_faixas.php              ✅ Cadastro de faixas (PARCIAL)
├── etapa2_configuracao.php        ✅ Cadastro de embalamento (PARCIAL)
├── etapa3_registro_lote.php       ⚠️  Registro de lote (STUB)
├── etapa4_distribuicao.php        ⚠️  Distribuição percentual (STUB)
└── etapa5_resultado.php           ⚠️  Resultado operacional (STUB)
```

### ✅ Hub Principal

```
/public/reformulacao/calibradora/
├── index.php  ✅ Dashboard com cards para cada etapa
├── init.php   ✅ Inicialização de rotas
├── bootstrap.php  ✅ Inclusão de todas as classes
└── safe_storage.php  ✅ Funções de persistência segura
```

---

## 2. O QUE PRECISA SER CORRIGIDO / COMPLEMENTADO

### 🔧 Tela 1 — Cadastro de Faixas (etapa1_faixas.php)

**Status:** Parcialmente implementada

**Já tem:**
- ✅ Seletor dinâmico de configurações
- ✅ Formulário de entrada de faixa
- ✅ Validação peso_ini < peso_fin
- ✅ Tabela com linhas existentes

**Falta:**
- ⚠️ Editar linhas inline (com modal ou toggle)
- ⚠️ Deletar linhas com confirmação
- ⚠️ Melhorar visual para seguir padrão do Balanceamento
- ⚠️ Sequência automática por configuração
- ⚠️ Validação visual de sobreposição

### 🔧 Tela 2 — Cadastro de Tipos de Embalamento (etapa2_configuracao.php)

**Status:** Parcialmente implementada

**Já tem:**
- ✅ Formulário para criar configuração
- ✅ Vinculação com faixa de peso

**Falta:**
- ⚠️ Campos completos: nome, descrição, peso nominal, unidade, status ativo/inativo
- ⚠️ Tabela com tipos cadastrados
- ⚠️ Editar/deletar com interface clara
- ⚠️ Melhorar visual conforme padrão Balanceamento

### 🔧 Tela 3 — Registro da Calibradora (etapa3_registro_lote.php)

**Status:** Stub (esqueleto vazio)

**Precisa:**
- 📋 Seletor de cadastro/configuração de faixas (EXP, MI, CLASSIF, etc.)
- 📋 Campos: Nº controle, produtor, fazenda, variedade, classe, peso total, observação
- 📋 Tabela carregada automaticamente das faixas selecionadas
- 📋 Colunas: seq | calibre | peso_ini | peso_fim | tipo_embalamento | % | peso_calculado
- 📋 Entrada de percentuais por linha
- 📋 Cálculo automático: `peso_calculado = peso_total × % / 100`
- 📋 Validação: soma percentual = 100%
- 📋 Destaque visual se total ≠ 100%

### 🔧 Tela 4 — Distribuição (etapa4_distribuicao.php)

**Status:** Stub

**Precisa:**
- 📋 Carregamento automático dos dados da Tela 3
- 📋 Visualização tabular de pesos calculados
- 📋 Possibilidade de ajuste fino de percentuais
- 📋 Validação contínua

### 🔧 Tela 5 — Resultado (etapa5_resultado.php)

**Status:** Stub

**Precisa:**
- 📋 Resumo visual da partida
- 📋 Histórico de lotes processados
- 📋 Preparação futura para OCR/importação por foto

---

## 3. PADRÃO VISUAL A SEGUIR (Balanceamento)

### Cores e Estilos
```css
--primary-color: #007bff;
--primary-dark: #0056b3;
--success-color: #28a745;
--danger-color: #dc3545;
--warning-color: #ffc107;
--light-bg: #f8f9fa;
--border-color: #ced4da;
--sidebar-width: 270px;
```

### Componentes Reutilizáveis
- Cards com shadow
- Tabelas com header azul
- Botões com transição
- Formulários em grid
- Mensagens de sucesso/erro com cores
- Breadcrumb para navegação

**Arquivo CSS compartilhado:**
```
/public/reformulacao/styles.css  (usar como referência)
```

---

## 4. FLUXO DE FUNCIONALIDADE ESPERADO

### Sequência de Uso:

```
1. TELA 1 (Cadastro de Faixas)
   └─> Usuário cria cadastros como EXP, MI, CLASSIF
   └─> Cada cadastro tem sua sequência independente
   └─> Define faixas: seq | calibre | peso_ini | peso_fim

2. TELA 2 (Tipos de Embalamento)
   └─> Usuário cadastra tipos: "Caixa 4kg EXP", "Caixa 6kg MI", "Refugo", etc.
   └─> Cada tipo tem nome, descrição, peso nominal, unidade, status

3. TELA 1 (Link tá faixa com embalamento)
   └─> Ao visitar novamente, adiciona tipo_embalamento em cada linha

4. TELA 3 (Resultado da Calibradora)
   └─> Seleciona configuração (EXP, MI, etc.)
   └─> Faixas carregam automaticamente com seus tipos
   └─> Preenche peso total da partida
   └─> Preenche percentuais por linha
   └─> Sistema calcula pesos automaticamente
   └─> Valida se total % = 100%
   └─> Salva no histórico

5. TELA 4 (Distribuição/Controle)
   └─> Ajustes finos se necessário
   └─> Visualização de resultados

6. TELA 5 (Resultado)
   └─> Resumo da partida
   └─> Histórico para consulta
```

---

## 5. PREPARAÇÃO PARA FUTURO (OCR/Foto)

**Deixar estrutura pronta para:**
```
Foto da tela da calibradora 📷
    ↓
[Leitura por OCR/programa]
    ↓
Extrai: programa, faixas, pesos, percentuais
    ↓
Preenche automaticamente Tela 3
    ↓
Usuário confirma e salva
```

**Arquitetura suporta:** A persistência em JSON e a separação entre dados permite fácil integração com APIs de OCR/visão no futuro.

---

## 6. ESTRUTURA DE DADOS (JSON)

### faixas_peso.json
```json
{
  "version": 1,
  "dados": [
    {
      "id": 1,
      "seq": 1,
      "calibre": "50",
      "peso_inicial": 50,
      "peso_final": 150,
      "nome_configuracao": "EXP"
    },
    ...
  ]
}
```

### configuracoes_embalamento.json
```json
{
  "version": 1,
  "dados": [
    {
      "id": 1,
      "nome": "Caixa 4kg EXP",
      "descricao": "Embalagem padrão para exportação",
      "peso_nominal": 4000,
      "unidade": "g",
      "status": "ativo",
      "faixa_peso_id": 1
    },
    ...
  ]
}
```

### registros_lote.json
```json
{
  "version": 1,
  "dados": [
    {
      "id": 1,
      "controle": "CTRL-001",
      "configuracao": "EXP",
      "produtor": "João Silva",
      "variedade": "Palmer",
      "classe": "A",
      "peso_total": 1000000,
      "observacao": "...",
      "created_at": "2026-05-15 10:30:00"
    },
    ...
  ]
}
```

---

## 7. PRÓXIMAS AÇÕES RECOMENDADAS

### Fase 1: Completar Telas 1 e 2 (Cadastros Base)
- [ ] Melhorar visual de ambas as telas
- [ ] Implementar editar/deletar com confirmação
- [ ] Adicionar validações visuais mais claras
- [ ] Testar relação entre faixas e embalamento

### Fase 2: Implementar Tela 3 (Resultado)
- [ ] Criar form com seletor de configuração
- [ ] Carregamento automático de faixas
- [ ] Entrada de peso total e percentuais
- [ ] Cálculo automático em JavaScript
- [ ] Validação visual de total = 100%

### Fase 3: Complementar Telas 4 e 5
- [ ] Fluxo de distribuição
- [ ] Histórico visual
- [ ] Preparação para OCR

### Fase 4: Padrão Visual Consistente
- [ ] Aplicar CSS do Balanceamento
- [ ] Testar responsividade
- [ ] Melhorar UX com feedbacks

---

## 8. CHECKLIST DE VALIDAÇÃO

### Funcionalidade Base
- [ ] Faixas criadas com sucesso
- [ ] Sobreposição impedida
- [ ] Embalamento vinculado à faixa
- [ ] Lotes registrados com histórico
- [ ] Cálculos corretos (peso_calc = peso_total × % / 100)

### Isolamento
- [ ] Nenhum arquivo legado foi modificado
- [ ] Dados separados em /data/reformulacao/calibradora/
- [ ] Namespace dedicado (CalbradoraModule)
- [ ] Sem dependências do Balanceamento

### Visual e UX
- [ ] Cores seguem padrão Balanceamento
- [ ] Tabelas com visual claro
- [ ] Botões com transição
- [ ] Mensagens de feedback visíveis
- [ ] Formulários bem organizados

---

## 9. ARQUIVOS E ROTAS PRINCIPAIS

### Entry Points
- `index.php` → Hub do módulo
- `views/etapa1_faixas.php` → Cadastro de faixas
- `views/etapa2_configuracao.php` → Cadastro de tipos
- `views/etapa3_registro_lote.php` → Resultado
- `views/etapa4_distribuicao.php` → Distribuição
- `views/etapa5_resultado.php` → Resumo

### Bootstrap
```php
require_once __DIR__ . '/bootstrap.php';
// Carrega todos os models, repositories, services, controllers
```

### Padrão de Uso em Views
```php
// Incluir bootstrap
require_once __DIR__ . '/../bootstrap.php';

// $service e $controller já estão disponíveis

// Processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->processarRequisicao('acao_desejada', $_POST);
    if ($result['sucesso']) {
        // Sucesso
    }
}

// Obter dados
$dados = $controller->processarRequisicao('obter_dados')['dados'];
```

---

## 10. NOTAS IMPORTANTES

### ⚠️ Não Altere
- Módulos legados (Balanceamento, Cronoanálise, Árvore)
- Arquivo `/public/reformulacao/safe_storage.php`
- Menu principal (apenas se necessário add link Calibradora)

### ✅ Foco Calibradora
- Completar as 5 telas
- Testar integrações
- Validar cálculos
- Preparar para OCR

### 🔐 Segurança
- Lock em arquivos JSON já implementado
- Validação de entrada nos controllers
- Use sempre via controller, nunca direto ao repository

---

**Documentação pronta para iniciar ajustes!**
