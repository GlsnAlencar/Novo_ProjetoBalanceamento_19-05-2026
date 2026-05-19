# 📋 GUIA DE DESENVOLVIMENTO - SISTEMA DE BALANCEAMENTO

> **Documento crítico para Dev Senior e Dev Júnior**
> Versão: 1.0 | Data: Maio/2026

---

## 🎯 VISÃO GERAL DO PROJETO

O **Sistema de Balanceamento** é uma aplicação web para gerenciamento de postos de trabalho, análise de atividades e otimização de fluxos produtivos em uma linha de embalagem (packing house).

**Objetivo Principal:** Balancear carga de trabalho entre postos, analisar tempos de ciclo e recursos necessários.

**Stack Tecnológico:**
- **Backend:** PHP 7.0+
- **Frontend:** HTML5, CSS3, JavaScript (Drawflow para visualização de fluxos)
- **Dados:** JSON (arquivos em `data/` com persistência local)
- **UI Components:** Bootstrap-like, CSS customizado

---

## 📐 ARQUITETURA DE DADOS

### 1. Estrutura de Arquivo JSON - `linhas.json`

```json
{
  "id": "linha_001",
  "nome": "Setor 1",
  "setor_id": "setor_001",
  "unidade_basica": "Caixa",
  "postos": [
    {
      "nome": "Puxar fruta",
      "id": "posto_001",
      "atividades": [
        {
          "descricao": "Empacotar itens",
          "unidade": "Caixa",
          "quantidade": 100,
          "peso_unidade": 25,
          "tempo_total": 3600,
          "tempo_por_unidade": 36,
          "tempo_por_peso": 1.44
        }
      ],
      "recursos": {
        "num_pessoas": 2
      },
      "configs": {},
      "detalhes": {}
    }
  ],
  "conexoes": []
}
```

### 2. Estrutura de Arquivo JSON - `setores.json`

```json
{
  "id": "setor_001",
  "nome": "Setor 1",
  "descricao": "Fluxo Principal",
  "linhas_ids": ["linha_001"]
}
```

### 3. Estrutura de Arquivo JSON - `unidades.json`

```json
{
  "nome": "Caixa",
  "peso_padrao": 25,
  "descricao": "Caixa padrão de embalagem"
}
```

**IMPORTANTE:** Sempre incluir unidade "Contentor" com peso em kg para cálculos de tempo/contentor.

---

## 🔄 FLUXO DE NAVEGAÇÃO E PARÂMETROS

### Convenção de Parâmetros URL

Todos os arquivos usam **parâmetros de query string** para comunicação. **NUNCA quebrar esta convenção:**

| Arquivo | Parâmetros | Descrição |
|---------|-----------|-----------|
| `index.php` | `setor_id`, `linha_id` | Fluxo visual com Drawflow |
| `postos.php` | `setor_id` | Gerenciamento de postos por setor |
| `atividades_posto.php` | `post`, `linha`, `back` | Edição de atividades do posto |
| `recursos.php` | `linha`, `post`, `back` | Configuração de recursos (pessoas) |
| `fluxos.php` | `linha_id` | Conexões entre postos |
| `setores.php` | — | Gerenciamento de setores |
| `unidades.php` | — | Cadastro de unidades |

### Fluxo de Links Críticos

```
index.php (linha_id)
  ↓
  ├→ atividades_posto.php (post, linha=linha_id, back=index)
  │   ↓
  │   ← Voltar: index.php?linha=linha_id&back=atividades_posto
  │
  ├→ recursos.php (linha, post, back=index)
  │
  └→ postos.php (setor_id)
      ↓
      ├→ atividades_posto.php (post, linha=linha.id, back=postos)
      │   ↓
      │   ← Voltar: postos.php?setor_id=setor_id
      │
      └→ fluxos.php (linha_id)
```

---

## 🚨 PARÂMETROS CRÍTICOS - NÃO QUEBRAR

### 1. **IDs e Identificadores**

- **`linha_id`** = ID único da LINHA (não do setor!)
  - Gerado: `uniqid()` ou UUID
  - Usado em: URLs, JSON, JavaScript
  - **RISCO:** Confundir com `setor_id` causa erro "Posto não encontrado"

- **`setor_id`** = ID único do SETOR
  - Usado em: `postos.php`, redirecionamentos
  - Sempre vinculado a uma linha via `setor_id` em `linhas.json`

- **`post_index`** = Índice do posto no array (0-indexed)
  - Usado em: Operações CRUD, JavaScript
  - **RISCO:** Após remover posto, índices precisam ser recalculados com `array_values()`

### 2. **Variáveis PHP Obrigatórias**

```php
// Em atividades_posto.php
$linha_id = $_GET['linha'] ?? null;  // Recebe ID da linha
$post_index = $_GET['post'] ?? null; // Recebe índice do posto
$back_page = $_GET['back'] ?? 'postos'; // Página de retorno

// IMPORTANTE: Validar SEMPRE
if (!$linha_id || $post_index === null) {
    die('❌ Parâmetros inválidos');
}

// Em postos.php
$setor_ativo_id = $_GET['setor_id'] ?? null; // ID do setor
$linha_selecionada_ref = &$linha; // Referência à linha para modificações
```

### 3. **Variáveis JavaScript Obrigatórias**

```javascript
// Em index.php
var linhaId = '<?php echo htmlspecialchars($linha_id ?? ''); ?>';
var postos = <?php echo json_encode($postos); ?>;

// Em postos.php
var linhaId = <?php echo json_encode($linha_selecionada_ref['id'] ?? null); ?>;

// Nunca use setorAtivoId para chamar atividades_posto.php!
// Sempre use linhaId!
```

---

## 📊 CÁLCULOS CONSOLIDADOS

### Fórmulas Utilizadas

#### 1. Tempo de Ciclo por Posto
```
tempo_ciclo = SOMA(tempo_total de todas as atividades do posto)
```

#### 2. Tempo por Unidade
```
tempo_por_unidade = tempo_total / quantidade
```

#### 3. Tempo por Peso (kg)
```
tempo_por_peso = tempo_por_unidade / peso_unidade
```

#### 4. Tempo por Contentor
```
tempo_contentor = tempo_por_peso_médio × peso_contentor
                = (SOMA(tempo_por_peso × quantidade) / quantidade_total) × peso_contentor
```

#### 5. Ritmo por Pessoa
```
ritmo = tempo_contentor / num_pessoas
```

### Locais onde esses cálculos são realizados:
- **Backend:** `index.php` (linhas 96-144)
- **Backend:** `atividades_posto.php` (linhas 100-130)
- **Frontend:** `index.php` JavaScript (renderizarNode)

---

## 🔐 REGRAS DE INTEGRIDADE DE DADOS

### 1. Reindexação Obrigatória Após Exclusão

```php
// CORRETO
unset($array[$index]);
$array = array_values($array); // ⚠️ OBRIGATÓRIO!

// ERRADO - CAUSA ÍNDICES DISPAROS
unset($array[$index]);
// sem array_values()
```

**Onde é crítico:**
- `postos.php` (remover posto)
- `atividades_posto.php` (remover atividade)
- `index.php` (remover posto da linha)

### 2. Referências PHP

```php
// Para modificar arrays dentro de loops
foreach ($linhas_json as $key => &$linha) {
    $linha['nome'] = 'Novo Nome'; // Modifica original
}
unset($linha); // SEMPRE desfazer referência ao final!
```

### 3. Validação de Entrada

```php
// SEMPRE validar
$input = trim($_POST['campo'] ?? '');
if (empty($input)) {
    die('❌ Campo obrigatório');
}

// SEMPRE escapar para HTML
echo htmlspecialchars($valor);

// SEMPRE urlencode para URLs
echo urlencode($valor);
```

---

## 📝 CONVENÇÕES DE CODIFICAÇÃO

### 1. Nomes de Arquivos
- Lowercase com underscore: `atividades_posto.php`
- Nunca use CamelCase ou hífens

### 2. Comentários de Seção
```php
// ========== DESCRIÇÃO DA SEÇÃO ==========
// Use esta convenção para separar lógica
```

### 3. Estrutura de Arquivo PHP
```php
<?php
// 1. Session e includes
session_start();
include 'data_store.php';

// 2. Validações
if (!isset($_GET['parametro'])) { die('Erro'); }

// 3. Carregamento de dados
$dados = load_json_data('arquivo');

// 4. Processamento (POST/GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST') { }

// 5. HTML
?>
<!DOCTYPE html>
```

### 4. Classe CSS para Botões
```html
<!-- Links (não formulário) -->
<a href="#" class="btn-editar-detalhes">Atividades</a>
<a href="#" class="btn-remover">Remover</a>
<a href="#" class="back-button">Voltar</a>

<!-- Botões de ação em grupo -->
<button class="btn-grupo-acao">Ação</button>
```

---

## ⚙️ FUNÇÕES CRÍTICAS EM `data_store.php`

```php
load_json_data($filename)         // Carrega JSON do arquivo
save_json_data($filename, $data)  // Salva dados em JSON

find_linha_by_id($linhas, $id)    // Localiza linha por ID
find_setor_by_id($setores, $id)   // Localiza setor por ID
find_posto_in_linha($linha, $id)  // Localiza posto dentro de uma linha
```

**IMPORTANTE:** Sempre verificar se retorno é `null` antes de usar:
```php
$linha = find_linha_by_id($linhas_json, $linha_id);
if ($linha === null) {
    die('❌ Linha não encontrada');
}
```

---

## 🛠️ CHECKLIST PARA NOVO DESENVOLVEDOR

### Antes de fazer qualquer alteração:

- [ ] Entendi a diferença entre `linha_id` e `setor_id`
- [ ] Entendi que `post_index` é sempre 0-indexed
- [ ] Conheço o fluxo de navegação entre arquivos
- [ ] Sei que após `unset()` preciso fazer `array_values()`
- [ ] Sei usar `htmlspecialchars()` para output
- [ ] Sei usar `urlencode()` para URLs
- [ ] Sei que `$back_page` precisa de validação
- [ ] Entendo os cálculos de tempo/ciclo
- [ ] Testei em pelo menos 2 cenários
- [ ] Validei a integridade dos dados no JSON

### Ao fazer alterações:

1. **Sempre backup:** Copie `data/*.json` antes
2. **Teste localmente:** Use `localhost:8000` ou equivalente
3. **Valide JSON:** Use um validador JSON online
4. **Teste fluxos:** Clique em todos os links
5. **Valide cálculos:** Use calculadora para verificar valores
6. **Documente mudanças:** Adicione comentário explicativo

---

## 🚀 ADIÇÕES/ALTERAÇÕES RECENTES

### v1.0 (Maio/2026)

#### ✅ Requisitos Alinhados
- Sistema multilinhas com múltiplos setores
- Gerenciamento de postos e atividades
- Cálculo automático de tempos de ciclo
- Gestão de recursos (pessoas por posto)
- Visualização de fluxo com Drawflow
- Análise de balanceamento de carga

#### ✅ Parâmetros Configurados
- Navegação entre telas via query string
- Validação de `linha_id` vs `setor_id`
- Cálculos consolidados por posto
- Atalho de atividades em ambas as telas (fluxo e postos)
- Redirecionamento correto ao voltar de atividades

#### 🔧 Correções Realizadas
1. Link "← Voltar" em atividades_posto.php usa parâmetro correto
2. Botão "⚙️ Atividades" adicionado no fluxo da linha
3. Uso de `linha_id` em vez de `setor_id` em chamadas para atividades
4. Função `carregarAtividades()` corrigida em postos.php

---

## 📞 CONTATOS CRÍTICOS

**Pontos de atenção para suporte técnico:**

1. **Erro "Posto não encontrado"** 
   - Verificar: Está passando `linha_id` correto? Não passou `setor_id` por engano?

2. **Índices disparos após remover**
   - Verificar: Está usando `array_values()` após `unset()`?

3. **Valores de cálculo incorretos**
   - Verificar: Unidade "Contentor" foi cadastrada com peso correto?

4. **Atividades não carregam**
   - Verificar: URL tem `post`, `linha` e `back` corretos?

---

## 📚 DOCUMENTAÇÃO RELACIONADA

- [ESTRUTURA_DADOS_V2.md](ESTRUTURA_DADOS_V2.md) - Estrutura detalhada de dados
- [GUIA_RAPIDO.md](GUIA_RAPIDO.md) - Quick reference
- [RESUMO_FINAL.md](RESUMO_FINAL.md) - Visão geral do projeto
- [README.md](README.md) - Setup inicial

---

**Última atualização:** 05/05/2026
**Responsável:** Arquitetura de Sistema
**Status:** ✅ ESTÁVEL

