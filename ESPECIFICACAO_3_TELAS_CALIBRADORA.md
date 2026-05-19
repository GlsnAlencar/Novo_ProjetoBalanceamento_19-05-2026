# 📐 ESPECIFICAÇÃO DETALHADA - 3 TELAS CALIBRADORA

**Data:** 16 de Maio de 2026  
**Versão:** 1.0 - Final  
**Status:** Pronto para Implementação

---

## TELA 1: CADASTRO DE FAIXAS DE CALIBRAÇÃO

### 📌 Objetivo
Cadastrar configurações de faixas da calibradora (EXP, MI, CLASSIF, etc.) com intervalos de peso e tipos de embalamento.

### 📋 Componentes

#### 1.1 Cabeçalho
```html
Título: 📏 Cadastro de Faixas de Calibração
```

#### 1.2 Seletor de Configuração (EXISTENTE)
```
Tipo: Formulário GET
Campos:
  - Label: "Selecione ou crie uma configuração"
  - Select: nome_configuracao (carregado de todas as faixas)
  - Botão: "+ Nova Configuração"
Ação ao selecionar: Recarrega página com ?config=NOME
```

#### 1.3 Formulário de Nova Faixa
```
Tipo: POST
Action: criar_faixa

Campos:
  1) Calibre (obrigatório)
     Type: text (texto livre)
     Placeholder: "Ex: REFUGO ou Caixa 12"
     Validação: não vazio

  2) Peso Inicial em Gramas (obrigatório)
     Type: number
     Min: 0
     Max: 999999
     Step: 1
     Placeholder: "Ex: 50"
     Validação: numérico, > 0

  3) Peso Final em Gramas (obrigatório)
     Type: number
     Min: 0
     Max: 999999
     Step: 1
     Placeholder: "Ex: 270"
     Validação: numérico, > peso_inicial

  4) Tipo de Embalamento (NOVO)
     Type: select (dropdown)
     Carregado de: calibradora_tipos_embalamento
     Mostrar: nome (descrição)
     Validação: selecionado obrigatoriamente
     
  5) Sequência (obrigatório)
     Type: number
     Min: 1
     Validação: numérico

Botão: "Salvar Faixa"
```

#### 1.4 Tabela de Faixas
```
Colunas:
  1) Seq          (número, ordenação)
  2) Calibre      (texto)
  3) Peso Inicial (número com unidade g)
  4) Peso Final   (número com unidade g)
  5) Tipo Embal.  (texto - nome embalamento)
  6) Ações        (Editar, Excluir)

Ordenação: Por Seq (crescente)
Comportamento:
  - Editar: Modal com campos preenchidos
  - Excluir: Confirmação antes de deletar
```

### 🔒 Validações

#### Front-End
- ✅ Peso inicial < peso final
- ✅ Campos obrigatórios preenchidos
- ✅ Números válidos (sem negativos)

#### Back-End (CRÍTICO - C-002)
```php
// Safe validation
$peso_inicial = filter_var($_POST['peso_inicial'], 
    FILTER_VALIDATE_FLOAT,
    ['options' => ['min_range' => 0, 'max_range' => 999999]]
);
if ($peso_inicial === false) {
    return erro('Peso inicial inválido');
}

// Validação de sobreposição (A-002)
function validarSobreposicao($configuracao_id, $peso_inicial, $peso_final, $exclude_id = null) {
    $faixas = $this->getFaixasPorConfiguracao($configuracao_id);
    foreach ($faixas as $faixa) {
        if ($exclude_id && $faixa['id'] == $exclude_id) continue;
        if (intervalosSobrepostos($peso_inicial, $peso_final, 
                                   $faixa['peso_inicial'], $faixa['peso_final'])) {
            return false;
        }
    }
    return true;
}

function intervalosSobrepostos($a_ini, $a_fim, $b_ini, $b_fim) {
    return !($a_fim < $b_ini || $b_fim < $a_ini);
}
```

### 💾 Persistência
- Tabela: `calibradora_faixas`
- Campos armazenados: id, configuracao_id, seq, calibre, peso_inicial, peso_final, tipo_embalamento_id
- Operações: CREATE (POST) | READ (GET) | UPDATE (POST/PUT) | DELETE

### 🎨 Estilo Visual
```css
/* Padrão Balanceamento */
- Card com border esquerda azul (#007bff)
- Input com border suave #e0e6ed
- Input:focus border azul #007bff
- Tabela com alternância de linhas (#f8fafc / #fff)
- Botão primário: #007bff, hover mais escuro
- Alert mensagens: success #d4edda, error #f8d7da
```

---

## TELA 2: CADASTRO DE TIPOS DE EMBALAMENTO

### 📌 Objetivo
Cadastrar tipos de embalamento utilizados na calibradora (Caixa 4kg, Caixa 6kg, Refugo, etc.).

### 📋 Componentes

#### 2.1 Cabeçalho
```html
Título: 📦 Cadastro de Tipos de Embalamento
Descrição: "Tipos de embalamento utilizados nas faixas de calibração"
```

#### 2.2 Botão Ação
```
Botão: "+ Novo Tipo de Embalamento"
Ação: Abre modal/formulário
```

#### 2.3 Formulário (Modal ou Inline)
```
Tipo: POST
Action: criar_configuracao ou atualizar_configuracao

Campos:
  1) Nome (obrigatório)
     Type: text
     Placeholder: "Ex: Caixa 4kg EXP"
     Validação: não vazio, < 100 caracteres

  2) Descrição (opcional)
     Type: textarea
     Placeholder: "Detalhes do tipo de embalamento"
     Validação: < 500 caracteres

  3) Peso Nominal em Gramas (obrigatório)
     Type: number
     Placeholder: "Ex: 4000"
     Validação: numérico, > 0

  4) Unidade (obrigatório)
     Type: select
     Options: [ "kg", "g", "unidade" ]
     Validação: selecionado

  5) Status (obrigatório)
     Type: select ou toggle
     Options: [ "Ativo", "Inativo" ]
     Default: "Ativo"
     Validação: sempre preenchido

Botões: 
  - Salvar (POST)
  - Cancelar
```

#### 2.4 Tabela de Tipos
```
Colunas:
  1) ID
  2) Nome
  3) Descrição (truncado 100 chars)
  4) Peso Nominal + Unidade
  5) Status (badge: ✅ Ativo / ❌ Inativo)
  6) Ações (Editar, Deletar)

Filtros (opcional):
  - Radio: [Todos] [Apenas Ativos] [Apenas Inativos]

Ordenação: Por Nome (A-Z)

Comportamento:
  - Editar: Preenche modal com dados
  - Deletar: Confirmação + verifica se em uso
  - Se em uso: "Não é possível deletar, em uso em X faixas"
```

### 🔒 Validações

#### Front-End
- ✅ Nome não vazio
- ✅ Peso nominal > 0
- ✅ Unidade selecionada
- ✅ Descrição < 500 chars

#### Back-End
```php
// Validação de nome único
$existe = $repo->findByNome($_POST['nome']);
if ($existe && $existe['id'] != $id_edicao) {
    return erro('Tipo de embalamento já existe');
}

// Validação de peso
$peso = filter_var($_POST['peso_nominal'],
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 999999]]
);
if ($peso === false) {
    return erro('Peso inválido');
}

// Verificar se em uso antes de deletar
function verificarEmUso($id) {
    // Buscar em calibradora_faixas.tipo_embalamento_id
    return $this->countFaixasComTipo($id) > 0;
}
```

### 💾 Persistência
- Tabela: `calibradora_tipos_embalamento` (CRIAR NOVA)
- Campos:
  ```
  CREATE TABLE calibradora_tipos_embalamento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(500),
    peso_nominal INT NOT NULL,
    unidade VARCHAR(20) NOT NULL,
    status BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  );
  ```
- Operações: CRUD completo

### 🎨 Estilo Visual
```css
/* Padrão Balanceamento */
- Card com border esquerda verde (#28a745)
- Status badge: ✅ verde (#28a745) | ❌ cinza (#6c757d)
- Modal com background semi-transparente
- Input com validação em tempo real
```

### 🔄 Integração com Tela 1
- Ao salvar novo tipo em TELA 2
- Dropdown de "Tipo de Embalamento" em TELA 1 carrega automaticamente
- Listagem de tipos:
  ```php
  SELECT id, nome FROM calibradora_tipos_embalamento 
  WHERE status = 1 
  ORDER BY nome
  ```

---

## TELA 3: LANÇAMENTO OPERACIONAL (Nº CONTROLE)

### 📌 Objetivo
Lançamento operacional da calibradora com cálculo automático de distribuição por percentuais.

### 📋 Componentes

#### 3.1 Cabeçalho Operacional
```
Tipo: Form GET/POST
Ação: Atualizar header sem recarregar

Campos (Linha 1):
  1) Nº Controle (obrigatório)
     Type: text
     Placeholder: "Auto-gerado ou manual"
     Geração: Timestamp unix ou sequencial
     Validação: não vazio, único

  2) Cadastro/Configuração (obrigatório)
     Type: select (dropdown)
     Carregado de: Distinct nome_configuracao de calibradora_faixas
     Ação ao selecionar: AJAX - carrega faixas e tipos automaticamente
     Validação: selecionado

Campos (Linha 2 - Informações do Lote):
  1) Produtor (obrigatório)
     Type: text
     Placeholder: "Nome do produtor"
     Validação: não vazio, < 100 chars

  2) Fazenda/Huerto (obrigatório)
     Type: text
     Placeholder: "Ex: Fazenda Monte Alto"
     Validação: não vazio, < 100 chars

  3) Variedade (obrigatório)
     Type: text
     Placeholder: "Ex: Pera, Fuyu"
     Validação: não vazio, < 100 chars

Campos (Linha 3 - Resultado):
  1) Classe (opcional)
     Type: text
     Placeholder: "Ex: Extra, 1ª, 2ª"

  2) Peso Total em Kg (obrigatório)
     Type: number
     Placeholder: "Ex: 500"
     Step: 0.01
     Min: 0
     Max: 999999
     Validação: numérico, > 0

  3) Observação (opcional)
     Type: textarea
     Placeholder: "Observações gerais da partida"
     Validação: < 1000 chars
```

#### 3.2 Tabela de Distribuição
```
Colunas:
  1) Seq          (do formulário TELA 1)
  2) Calibre      (do formulário TELA 1)
  3) Peso Inicial (do formulário TELA 1)
  4) Peso Final   (do formulário TELA 1)
  5) Tipo Embal.  (do formulário TELA 1)
  6) % Distrib.   (EDITÁVEL - usuário preenche)
  7) Peso Calc.   (CALCULADO - automático)

Comportamento:
  - Linha destacada: #f8fafc
  - Célula % com input de número
  - Input % :change → recalcula Peso Calc.
  - Fórmula: peso_calculado = peso_total × percentual / 100
```

#### 3.3 Resumo de Resultados
```
Tipo: Tabela informativa (somente leitura)

Linhas:
  1) Soma Percentual: XXX%
     - Destaque se ≠ 100%: background #fff3cd (amarelo aviso)
     - Alert message: "⚠️ Soma deve ser 100%"

  2) Peso Total Calculado: XXX.XX kg
     - Validação: Deve ser ≈ peso_total (tolerância 0.1%)

  3) Status da Partida:
     - ✅ Válida (soma = 100%)
     - ⚠️ Incompleta (soma < 100%)
     - ❌ Inválida (soma > 100%)
```

#### 3.4 Preparação para OCR (FUTURO)
```
Botão: 📷 Importar imagem
Ação: 
  - Abre modal de upload
  - Aceita PNG, JPG, PDF
  - Estrutura preparada para:
    * Enviar para API OCR
    * Mapear campos automaticamente
    * Preencher formulário
  
Hoje: Apenas estrutura, sem OCR implementado

Modal:
  - Upload input
  - Botão "Processar imagem"
  - Feedback em tempo real
  - Spinner enquanto processa
```

#### 3.5 Ações Finais
```
Botão 1: "Salvar Partida"
  - POST /processar
  - Action: salvar_partida
  - Validações:
    * Todos campos obrigatórios preenchidos
    * Soma percentual = 100%
    * Peso total ≈ peso calculado
  - Se OK: Salva e mostra "✅ Partida #123 salva"
  - Se erro: Destaca campo com problema

Botão 2: "Limpar Formulário"
  - Reset de todos inputs

Botão 3: "Voltar"
  - Retorna ao hub ou listagem
```

### 🔒 Validações

#### Front-End (JavaScript)
```javascript
// Ao mudar configuração
onChangeConfiguracao() {
  // AJAX: carregar faixas
  fetch('/?action=obter_faixas_config&config=' + config)
    .then(r => r.json())
    .then(d => renderizarTabela(d));
}

// Ao mudar percentual em linha
onChangePercentual(linha, valor) {
  // Validar: 0-100
  if (valor < 0 || valor > 100) {
    alert('Percentual deve ser 0-100');
    return;
  }
  
  // Recalcular peso_calculado
  let peso_calc = (peso_total * valor) / 100;
  linha.querySelector('.peso-calc').value = peso_calc.toFixed(2);
  
  // Recalcular soma
  atualizarSomaPercentual();
}

function atualizarSomaPercentual() {
  let soma = 0;
  document.querySelectorAll('input[name="percentual[]"]').forEach(inp => {
    soma += parseFloat(inp.value) || 0;
  });
  
  let sumElement = document.querySelector('.soma-percentual');
  sumElement.textContent = soma.toFixed(2) + '%';
  
  if (Math.abs(soma - 100) > 0.01) {
    sumElement.classList.add('warning');
  } else {
    sumElement.classList.remove('warning');
  }
}
```

#### Back-End
```php
// Validação de nº controle único (C-002)
$numero_controle = trim($_POST['numero_controle']);
if (empty($numero_controle)) {
    return erro('Nº Controle obrigatório');
}

$existe = $partidaRepo->findByNumeroControle($numero_controle);
if ($existe) {
    return erro('Nº Controle já existente');
}

// Validação de percentuais (C-002)
$percentuais = $_POST['percentual'] ?? [];
$soma = 0;
foreach ($percentuais as $pct) {
    $pct = filter_var($pct, FILTER_VALIDATE_FLOAT,
        ['options' => ['min_range' => 0, 'max_range' => 100]]
    );
    if ($pct === false) {
        return erro('Percentual inválido');
    }
    $soma += $pct;
}

if (abs($soma - 100) > 0.01) {
    return erro('Soma de percentuais deve ser 100%');
}

// Salvar partida e itens
$partida = [
    'numero_controle' => $numero_controle,
    'configuracao_id' => $config_id,
    'produtor' => $produtor,
    'fazenda' => $fazenda,
    'variedade' => $variedade,
    'classe' => $classe,
    'peso_total' => $peso_total,
    'observacao' => $observacao,
    'data' => date('Y-m-d H:i:s')
];

$partida_id = $partidaRepo->create($partida);

// Salvar itens (faixas com percentuais)
foreach ($percentuais as $idx => $pct) {
    $faixa_id = $faixas[$idx]['id'];
    $peso_calc = ($peso_total * $pct) / 100;
    
    $item = [
        'partida_id' => $partida_id,
        'faixa_id' => $faixa_id,
        'percentual' => $pct,
        'peso_calculado' => $peso_calc
    ];
    
    $itemRepo->create($item);
}

return sucesso('Partida #' . $numero_controle . ' salva com sucesso');
```

### 💾 Persistência

#### Tabelas Utilizadas
```
1) calibradora_partidas (RegistroLote)
   - id
   - numero_controle (único)
   - configuracao_id (FK)
   - produtor
   - fazenda
   - variedade
   - classe
   - peso_total
   - observacao
   - data

2) calibradora_partida_itens (DistribuicaoLote)
   - id
   - partida_id (FK)
   - faixa_id (FK)
   - percentual
   - peso_calculado
```

#### Operações
- CREATE: salvar_partida
- READ: obter_partidas, obter_partida por id
- UPDATE: editar_partida (reeditar operação anterior)
- DELETE: deletar_partida

### 🎨 Estilo Visual
```css
/* Padrão Balanceamento */
- Card header com border esquerda roxo (#6f42c1)
- Input com background #eef2ff (foco azul)
- Tabela com alternância cinza/branco
- Soma percentual destaque amarelo se ≠ 100%
- Botões: Salvar (azul), Limpar (cinza), Voltar (cinza)
- Alert success (#d4edda), warning (#fff3cd), error (#f8d7da)
```

### 🔗 Fluxo de Dados

```
Usuário seleciona Configuração
  ↓
AJAX carrega Faixas daquela Configuração
  ↓
Carrega Tipos de Embalamento ativos
  ↓
Renderiza Tabela com Faixas
  ↓
Usuário preenche Percentuais
  ↓
JavaScript recalcula Pesos em tempo real
  ↓
Valida Soma = 100%
  ↓
Usuário clica "Salvar Partida"
  ↓
Back-end valida tudo (C-002 + custom)
  ↓
Salva em calibradora_partidas + calibradora_partida_itens
  ↓
Retorna sucesso e número controle
  ↓
Usuário pode editarcontinuar ou voltar
```

### 📱 Responsividade
- Desktop: Layout em 2-3 colunas
- Tablet: Layout ajustado
- Mobile: Stack vertical, tabela scrollável

---

## 🔐 SEGURANÇA GERAL (Todas as 3 Telas)

### CSRF (C-005)
```php
// Gerar token na view
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// No formulário
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

// Validar no controller
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    return erro('Token CSRF inválido');
}
```

### Sanitização (A-003)
```php
// Todos outputs
echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

// Inputs
$produtor = trim($_POST['produtor'] ?? '');
if (strlen($produtor) > 100) {
    return erro('Produtor muito longo');
}
```

### Rate Limiting (Futuro)
- Preparar estrutura para limitar requests
- Máx 10 requisições por IP em 1 minuto

---

## 📊 Resumo de Campos

| Tela | Campo | Tipo | Validação | Origem |
|------|-------|------|-----------|--------|
| **1** | Calibre | text | não vazio | Usuário |
| **1** | Peso Inicial | number | > 0 | Usuário |
| **1** | Peso Final | number | > peso_ini | Usuário |
| **1** | Tipo Embal. | select | obrigatório | TELA 2 |
| **2** | Nome | text | único, não vazio | Usuário |
| **2** | Descrição | textarea | < 500 chars | Usuário |
| **2** | Peso Nominal | number | > 0 | Usuário |
| **2** | Unidade | select | obrigatório | Hardcoded |
| **2** | Status | toggle | ativo/inativo | Usuário |
| **3** | Nº Controle | text | único, auto | Sistema/Usuário |
| **3** | Configuração | select | obrigatório | TELA 1 |
| **3** | Produtor | text | não vazio | Usuário |
| **3** | Fazenda | text | não vazio | Usuário |
| **3** | Variedade | text | não vazio | Usuário |
| **3** | Classe | text | opcional | Usuário |
| **3** | Peso Total | number | > 0 | Usuário |
| **3** | Observação | textarea | < 1000 chars | Usuário |
| **3** | % Distribuição | number | 0-100 | Usuário |
| **3** | Peso Calculado | number | calculado | Sistema |

---

**Especificação:** Completa e Pronta para Implementação  
**Última Atualização:** 16 de Maio de 2026  
**Status:** ✅ Aprovado para Desenvolvimento
