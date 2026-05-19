# 🗺️ ARQUITETURA DE NAVEGAÇÃO E FLUXOS

> **Documento detalhado sobre como dados e parâmetros fluem entre telas**

---

## 🔄 FLUXO COMPLETO DO SISTEMA

### DIAGRAMA: Entrada → Edição → Saída

```
┌─────────────────────────────────────────────────────────────────┐
│                         MENU PRINCIPAL                           │
│  [Setores] [Postos] [Fluxos] [Unidades] [Transporte]           │
└────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    index.php (FLUXO VISUAL)                      │
│   Parâmetros: setor_id=X, linha_id=Y                            │
│   Exibe: Drawflow com nós (postos)                              │
│   Ações disponíveis por posto:                                   │
│     • ⚙️ Atividades → atividades_posto.php                       │
│     • 👥 Recursos → recursos.php                                │
│     • ➕ Após / ⇄ Paralelo (criar novo)                         │
└────────────────────────────────────────────────────────────────┘
                         ↙ ↓ ↘
    ┌────────────────────┘ │ └──────────────────┐
    │                      │                     │
    ↓                      ↓                      ↓
 [Atividades]        [Recursos]         [Fluxos/Conexões]
    │                      │                     │
```

---

## 📋 TELA: index.php (Fluxo Visual)

### Entrada
```php
GET: ?setor_id=setor_001&linha_id=linha_001
GET: ?setor_id=setor_001 (redireciona para primeira linha)
```

### Processamento
```php
// Carrega setores
$setores_json = load_json_data('setores');

// Carrega linhas
$linhas_json = load_json_data('linhas');

// Encontra linha pelo setor_id
foreach ($linhas_json as $l) {
    if ($l['setor_id'] === $setor_id) {
        $linha_selecionada = $l;
        $linha_id = $l['id'];
        break;
    }
}

// Calcula indicadores
foreach ($linha_selecionada['postos'] as $posto) {
    $tempo_ciclo += soma($posto['atividades'][tempo_total]);
    $tempo_ciclo_por_posto[$idx] = tempo_ciclo;
}
```

### Saída/Links
| Ação | Destino | Parâmetros |
|------|---------|-----------|
| 🔄 Atualizar | `index.php` | `setor_id=X&linha_id=Y` |
| ⚙️ Atividades | `atividades_posto.php` | `post=INDEX&linha=Y&back=index` |
| 👥 Recursos | `recursos.php` | `linha=Y&post=INDEX&back=index` |
| 📋 Voltar | `postos.php` | `setor_id=X` |
| 🎛️ Fluxos | `fluxos.php` | `linha_id=Y` |

---

## 📍 TELA: postos.php (Gerenciar Postos)

### Entrada
```php
GET: ?setor_id=setor_001
GET: ?setor_id=setor_001&sucesso=1
GET: ?setor_id=setor_001&erro=posto_existente
```

### Processamento
```php
// Recebe setor_id
$setor_ativo_id = $_GET['setor_id'] ?? null;

// Encontra a LINHA correspondente ao setor
foreach ($linhas_json as $key => &$linha) {
    if ($linha['setor_id'] === $setor_ativo_id) {
        $linha_selecionada_ref = &$linha; // ⚠️ REFERÊNCIA!
        $linha_selecionada_index = $key;
        break;
    }
}

// Acessa postos desta linha
$postos_do_setor = $linha_selecionada_ref['postos'] ?? [];
```

### Saída/Links
| Ação | Destino | Parâmetros |
|------|---------|-----------|
| ⚙️ Atividades | `atividades_posto.php` | `post=INDEX&linha=LINHA_ID&back=postos` |
| 🗑️ Remover | `postos.php` | `remover_posto=INDEX&setor_id=X` (POST) |
| ➕ Adicionar | `postos.php` | `setor_id=X` (POST) |
| ✏️ Atualizar | `postos.php` | `setor_id=X` (POST) |
| ↑ Menu | `menu.php` | — |

---

## ⚙️ TELA: atividades_posto.php (Editar Atividades)

### Entrada - CRÍTICO!
```php
GET: ?post=0&linha=linha_001&back=postos
GET: ?post=0&linha=linha_001&back=index
```

### Validação - IMPORTANTE!
```php
// ❌ ERRADO (vai dar erro "Posto não encontrado")
GET: ?post=0&linha=setor_001&back=postos  // Passou setor_id!

// ✅ CORRETO
GET: ?post=0&linha=linha_001&back=postos  // Passou linha_id!
```

### Processamento
```php
$linha_id = $_GET['linha'] ?? null;          // ← ID da LINHA
$post_index = $_GET['post'] ?? null;         // ← ÍNDICE do posto
$back_page = $_GET['back'] ?? 'postos';      // ← Página retorno

// Encontra a linha
foreach ($linhas_json as $key => $linha) {
    if ($linha['id'] === $linha_id) {        // ← Compara com LINHA_ID!
        $linha_selecionada = $linha;
        $linha_key = $key;
        break;
    }
}

// Acessa o posto específico
$posto = &$linhas_json[$linha_key]['postos'][$post_index];

// Acessa atividades do posto
foreach ($posto['atividades'] as $atividade) {
    // edita atividade
}
```

### Saída/Links
| Ação | Destino | Parâmetros |
|------|---------|-----------|
| ✓ Adicionar | `atividades_posto.php` | `post=INDEX&linha=LINHA_ID&sucesso=1` |
| 🗑️ Remover | `atividades_posto.php` | `post=INDEX&linha=LINHA_ID&remover_atividade=IDX` |
| ← Voltar | `{back_page}.php` | Dinâmico: `setor_id=X` (postos) ou `linha=Y` (index) |
| 👥 Recursos | `recursos.php` | `linha=LINHA_ID&post=INDEX&back=atividades_posto` |

---

## 👥 TELA: recursos.php (Definir Pessoas)

### Entrada
```php
GET: ?linha=linha_001&post=0&back=atividades_posto
GET: ?linha=linha_001&post=0&back=index
```

### Processamento
```php
$linha_id = $_GET['linha'] ?? null;     // ← ID da LINHA
$post_index = $_GET['post'] ?? null;    // ← ÍNDICE do posto
$back_page = $_GET['back'] ?? 'index';  // ← Voltar para

// Encontra a linha (mesmo padrão que atividades_posto.php)
$linha = find_linha_by_id($linhas_json, $linha_id);

// Acessa o posto e seus recursos
$num_pessoas = $linha['postos'][$post_index]['recursos']['num_pessoas'] ?? 1;
```

### Saída/Links
| Ação | Destino | Parâmetros |
|------|---------|-----------|
| ← Voltar | `{back_page}.php` | Dinâmico |
| ⚙️ Atividades | `atividades_posto.php` | `post=INDEX&linha=LINHA_ID&back=recursos` |

---

## 🔗 TELA: fluxos.php (Conexões Entre Postos)

### Entrada
```php
GET: ?linha_id=linha_001
```

### Processamento
```php
$linha_id = $_GET['linha_id'] ?? null;  // ← ID da LINHA

// Encontra a linha
$linha = find_linha_by_id($linhas, $linha_id);

// Trabalha com conexões
$conexoes = $linha['conexoes'] ?? [];
```

### Saída/Links
```php
// Adicionar/remover conexões dentro da mesma linha
POST/GET: ?linha_id=LINHA_ID&{acao}
```

---

## 🔀 PADRÃO: Voltar Dinâmico

### Em atividades_posto.php
```php
// Recebe de onde veio
$back_page = $_GET['back'] ?? 'postos';  // 'postos' ou 'index'

// Ao voltar, precisa passar o parâmetro correto
if ($back_page === 'postos') {
    // Volta para postos.php com setor_id
    echo '<a href="postos.php?setor_id=' . $setor_id . '">';
} else {
    // Volta para index.php com linha_id
    echo '<a href="index.php?linha=' . $linha_id . '">';
}
```

---

## 📊 TABELA: Correspondência de Parâmetros

| Tela | Usa qual? | Origem | Destino |
|------|-----------|--------|---------|
| **index.php** | `setor_id` `linha_id` | URL | atividades_posto, recursos, postos |
| **postos.php** | `setor_id` | URL | atividades_posto, fluxos, menu |
| **atividades_posto.php** | `linha` (= linha_id) `post` (= post_index) `back` | URL | postos.php, index.php, recursos.php |
| **recursos.php** | `linha` (= linha_id) `post` (= post_index) `back` | URL | atividades_posto.php, index.php |
| **fluxos.php** | `linha_id` | URL | — |

---

## ⚠️ ERROS MAIS FREQUENTES & CAUSAS

### Erro 1: "Posto não encontrado"
```
CAUSA: Passou setor_id em vez de linha_id
    
CÓDIGO QUEBRADO:
    <a href="atividades_posto.php?post=0&linha=<?= $setor_id ?>">

CÓDIGO CORRETO:
    <a href="atividades_posto.php?post=0&linha=<?= $linha_id ?>">
```

### Erro 2: "Parâmetros inválidos: linha e post são obrigatórios"
```
CAUSA: Um dos parâmetros faltou ou está vazio

DEBUG:
    var_dump($_GET);  // Verificar se chegou
    
SOLUÇÃO:
    <a href="atividades_posto.php?post=<?= $idx ?>&linha=<?= urlencode($linha_id) ?>">
```

### Erro 3: Dados não salvam / JSON corrompido
```
CAUSA: Não há validação antes de save_json_data()

DEBUG:
    var_dump($dados); // Ver estrutura
    file_exists('data/linhas.json'); // Ver permissões
    
SOLUÇÃO:
    Validar com json_encode() antes de salvar
```

### Erro 4: Valores de cálculo estão 0 ou NaN
```
CAUSA: Unidade "Contentor" não existe ou peso_padrao é 0

DEBUG:
    var_dump($unidades_json); // Ver unidades cadastradas
    
SOLUÇÃO:
    Ir em unidades.php e cadastrar "Contentor" com peso_padrao > 0
```

---

## 🧪 TESTE MANUAL - FLUXO COMPLETO

### Cenário: Adicionar atividade e verificar cálculos

1. **Abrir Fluxo:** `http://localhost:8000/index.php?setor_id=setor_001`
2. **Clicar em ⚙️ Atividades** de um posto
3. **Verificar URL:**
   ```
   http://localhost:8000/atividades_posto.php?post=0&linha=linha_001&back=index
   ```
4. **Adicionar atividade:**
   - Descrição: "Test"
   - Unidade: "Caixa" (ou outra)
   - Quantidade: 100
   - Tempo Total: 3600
5. **Verificar cálculos:**
   - Tempo/Unidade = 3600 / 100 = 36 ✓
   - Tempo/Peso = 36 / 25 = 1.44 ✓
6. **Voltar ao Fluxo:**
   - Clicar "← Voltar ao Fluxo"
   - Verificar URL volta para `index.php?linha=linha_001&back=atividades_posto`
7. **Verificar Drawflow:**
   - Tempo de Ciclo atualizado ✓
   - Tempo/Contentor recalculado ✓

---

## 🔐 CHECKLIST: Validação de Parâmetros

```php
// SEMPRE validar assim:

// 1. Receber
$linha_id = $_GET['linha'] ?? null;
$post_index = $_GET['post'] ?? null;

// 2. Validar
if (!$linha_id || $post_index === null) {
    die('❌ Parâmetros inválidos: linha e post são obrigatórios');
}

// 3. Sanitizar
$linha_id = trim($linha_id);
$post_index = (int)$post_index; // Converter para int

// 4. Carregar dados
$linhas = load_json_data('linhas');
$linha = find_linha_by_id($linhas, $linha_id);

// 5. Verificar resultado
if ($linha === null) {
    die('❌ Linha não encontrada: ' . htmlspecialchars($linha_id));
}

// 6. Verificar posto dentro da linha
if (!isset($linha['postos'][$post_index])) {
    die('❌ Posto não encontrado no índice: ' . $post_index);
}

// 7. OK, prosseguir
$posto = &$linha['postos'][$post_index];
```

---

## 📈 FLUXO DE DADOS NA CRIAÇÃO DE NOVO POSTO

```
index.php (POST "Adicionar Posto")
    ↓
JavaScript: addPostApos() ou addPostParalelo()
    ↓
server.php (função PHP)
    ↓
Novo POST object: { nome: "Novo Posto", atividades: [], recursos: { num_pessoas: 1 } }
    ↓
Insere em $linha_selecionada['postos']
    ↓
save_json_data('linhas', $linhas_json)
    ↓
Redireciona para index.php?setor_id=X&linha_id=Y
    ↓
Drawflow renderiza novo nó
```

---

## 🎯 REGRA DE OURO

```
┌─────────────────────────────────────────────────────┐
│ SE A PÁGINA TRABALHA COM POSTOS:                    │
│                                                      │
│   ├─ Se veio de "index.php" → usar "linha_id"      │
│   ├─ Se veio de "postos.php" → usar "linha_id"     │
│   │                                                  │
│   └─ NUNCA USAR "setor_id" para acessar            │
│                                                      │
│ SEMPRE validar que $linha_id existe antes de       │
│ tentar acessar $linha['postos'][$post_index]       │
└─────────────────────────────────────────────────────┘
```

---

**Última atualização:** 05/05/2026
**Versão:** 1.0 | Status: ✅ ESTÁVEL

