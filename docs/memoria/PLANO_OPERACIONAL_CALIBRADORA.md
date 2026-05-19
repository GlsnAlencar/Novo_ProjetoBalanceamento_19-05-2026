# 🎯 PLANO OPERACIONAL - CALIBRADORA

**Objetivo:** Estruturar módulo CALIBRADORA em 3 telas conectáveis funcionais  
**Restrições:** Reaproveitar código, seguir padrão Balanceamento, não alterar outros módulos

---

## 1. TELA 1 — CADASTRO DE FAIXAS

### 📋 O que já existe
- ✅ Model FaixaPeso.php com validação de sobreposição
- ✅ Repository FaixaPesoRepository.php com CRUD
- ✅ Service CalbradoraService.php com métodos
- ✅ View parcial etapa1_faixas.php (HTML + formulário)

### 🎯 O que precisa ser feito

#### A. Melhorar Layout Visual
```
┌────────────────────────────────────────────┐
│ 📦 CADASTRO DE FAIXAS                      │
├────────────────────────────────────────────┤
│ Configuração: [EXP ▼]  [+ Nova Config]     │
├────────────────────────────────────────────┤
│ FORMULÁRIO:                                │
│  Calibre: [_____]                          │
│  Peso Inicial: [_____]  Peso Final: [____] │
│  [SALVAR]                                  │
├────────────────────────────────────────────┤
│ TABELA EXISTENTE:                          │
│ Seq │ Calibre │ Peso Ini │ Peso Fim │ Ação │
│ 1   │ 50      │ 50       │ 150      │ E D  │
│ 2   │ 10      │ 150      │ 270      │ E D  │
└────────────────────────────────────────────┘
```

**Tarefas:**
- [ ] Aplicar CSS do Balanceamento (styles.css)
- [ ] Cards com box-shadow
- [ ] Tabela com header azul (#0b7bec)
- [ ] Botões de editar (modal ou inline) e deletar
- [ ] Mensagens de sucesso/erro com cor
- [ ] Validação visual de peso_ini < peso_fim

#### B. Implementar Editar Inline ou Modal
```
OPÇÃO 1: Modal (Recomendado)
- Clique em "Editar" abre modal
- Preenche dados atuais
- Salva sem recarregar página

OPÇÃO 2: Inline (Mais rápido)
- Clique em "Editar" transforma linha em formulário
- Campos viram editáveis
- Salva com AJAX
```

**Estrutura esperada:**
```html
<tr>
  <td>1</td>
  <td>50</td>
  <td>50</td>
  <td>150</td>
  <td>
    <a onclick="editarFaixa(1)">Editar</a> |
    <a onclick="deletarFaixa(1)">Deletar</a>
  </td>
</tr>
```

#### C. Validações
- [ ] Peso inicial < peso final
- [ ] Não permitir sobreposição (já implementado no service)
- [ ] Mensagem clara se houver erro
- [ ] Destaque visual de conflito

#### D. Sequência Automática
```
Quando usuário seleciona "EXP":
- Se primeira faixa → seq = 1
- Se segunda faixa → seq = 2
- Automático, não pedir ao usuário
```

---

## 2. TELA 2 — CADASTRO DE TIPOS DE EMBALAMENTO

### 📋 O que já existe
- ✅ Model ConfiguracaoEmbalamento.php
- ✅ Repository ConfiguracaoEmbalamentoRepository.php
- ✅ View parcial etapa2_configuracao.php

### 🎯 O que precisa ser feito

#### A. Completar Estrutura de Dados

**Campo novo necessário em ConfiguracaoEmbalamento.php:**
```php
class ConfiguracaoEmbalamento {
    public ?int $id;
    public string $nome;                    // ✅ "Caixa 4kg EXP"
    public string $descricao;               // ⚠️ NOVO
    public float $peso_nominal;             // ⚠️ NOVO (em gramas)
    public string $unidade;                 // ⚠️ NOVO ("g", "kg", etc.)
    public string $status;                  // ⚠️ NOVO ("ativo", "inativo")
    public int $faixa_peso_id;              // ✅ Referência
}
```

**Ação:** Atualizar Model, Repository, Service, Controller com novos campos

#### B. Criar Formulário Completo
```
┌────────────────────────────────────────────┐
│ 📦 TIPOS DE EMBALAMENTO                    │
├────────────────────────────────────────────┤
│ Nome: [_____________________]              │
│ Descrição: [_____________________]         │
│ Peso Nominal: [_____] [Unidade ▼]         │
│ Status: [Ativo ▼]                         │
│ [SALVAR]                                  │
├────────────────────────────────────────────┤
│ TIPOS CADASTRADOS:                         │
│ Nome │ Descrição │ Peso │ Unid. │ Status │ Ação
│ Caixa 4kg EXP │ ... │ 4000 │ g │ Ativo │ E D
└────────────────────────────────────────────┘
```

**Tarefas:**
- [ ] Layout responsivo
- [ ] Validação obrigatório: nome, peso, unidade
- [ ] Status: dropdown com "Ativo" e "Inativo"
- [ ] Editar/Deletar com confirmação
- [ ] Tabela clara

---

## 3. TELA 3 — RESULTADO DA CALIBRADORA (PRINCIPAL)

### 🎯 Estrutura Completa

```
┌────────────────────────────────────────────────────────┐
│ 📦 LANÇAMENTO DE PARTIDA - CALIBRADORA                 │
├────────────────────────────────────────────────────────┤
│ CABEÇALHO:                                             │
│  Nº Controle: [CTRL-001________]                      │
│  Configuração: [EXP ▼]                                │
│  Peso Total: [1000000 g]                              │
├────────────────────────────────────────────────────────┤
│ DADOS OPCIONAIS:                                       │
│  Produtor: [_____________]  Variedade: [_____________]│
│  Fazenda: [_____________]   Classe: [_____________]   │
│  Observação: [_____________________________________]  │
├────────────────────────────────────────────────────────┤
│ TABELA AUTOMÁTICA (carrega faixas de EXP):            │
│ Seq │ Calibre │ P.Ini │ P.Fim │ Embal. │ % │ P.Calc. │
│  1  │  50     │ 50    │ 150   │ Caixa4 │[_]│ 10000   │
│  2  │  10     │ 150   │ 270   │ Caixa6 │[_]│ 20000   │
│  3  │  12     │ 270   │ 385   │ Caixa8 │[_]│ 30000   │
│  ... soma %: [100 %] ← Destaque se ≠ 100% (vermelho) │
├────────────────────────────────────────────────────────┤
│ [SALVAR PARTIDA] [CANCELAR]                            │
└────────────────────────────────────────────────────────┘
```

### 📋 Fluxo de Funcionamento

#### 1. Carregar faixas automaticamente
```javascript
// Quando usuário seleciona "EXP"
fetch('api.php?action=obter_faixas&config=EXP')
  .then(res => res.json())
  .then(data => renderTabelaFaixas(data))
```

#### 2. Cálculo de peso
```javascript
// Quando usuário digita percentual
peso_calculado = peso_total * percentual / 100

Exemplo:
- peso_total = 1.000.000g
- percentual = 10%
- peso_calculado = 1.000.000 * 10 / 100 = 100.000g
```

#### 3. Validação de percentuais
```javascript
// Soma todos os %
let soma = array_faixas.reduce((s, f) => s + f.percentual, 0)

if (soma !== 100) {
    // Destaque vermelho
    elemento.style.color = '#dc3545'
    elemento.textContent = 'TOTAL: ' + soma + '% ⚠️ DEVE SER 100%'
}
```

### 📝 Tarefas

- [ ] Criar API endpoint para obter faixas por configuração
- [ ] Renderizar tabela dinamicamente com campos editáveis (%)
- [ ] Implementar cálculo automático em JavaScript
- [ ] Validação visual de soma percentual = 100%
- [ ] Botão SALVAR persistir no histórico (registros_lote.json)
- [ ] Aplicar CSS padrão Balanceamento

### 💾 Estrutura de Salvamento

```json
{
  "id": 1,
  "controle": "CTRL-001",
  "configuracao": "EXP",
  "peso_total": 1000000,
  "produtor": "João Silva",
  "variedade": "Palmer",
  "classe": "A",
  "observacao": "",
  "distribuicao": [
    { "faixa_id": 1, "percentual": 10, "peso_calculado": 100000 },
    { "faixa_id": 2, "percentual": 20, "peso_calculado": 200000 },
    ...
  ],
  "created_at": "2026-05-15 10:30:00"
}
```

---

## 4. TELAS 4 E 5 (Distribuição e Resultado)

### 📋 Tela 4 — Distribuição (Simples)
- Carregar dados da Tela 3
- Permitir ajustes finos
- Visualização clara

### 📋 Tela 5 — Resultado (Resumo + Histórico)
- Resumo visual da última partida
- Tabela de histórico de lotes
- Preparação para OCR futuro

---

## 5. INTEGRAÇÃO COM TELA 1

### 🔗 Vínculo Faixa → Embalamento

**Na Tela 1, adicionar coluna:**
```
Seq │ Calibre │ P.Ini │ P.Fim │ Tipo Embalamento │ Ação
 1  │  50     │ 50    │ 150   │ [Caixa 4kg ▼]   │ E D
```

**Quando salvar:**
```php
$controller->processarRequisicao('atualizar_faixa', [
    'id' => $faixa_id,
    'tipo_embalamento_id' => $_POST['tipo_embalamento_id']
]);
```

---

## 6. PADRÃO VISUAL CONSOLIDADO

### Cores e Componentes (do Balanceamento)
```css
/* Header */
background: linear-gradient(90deg, #0b7bec, #0759b4);
color: #fff;

/* Tabela */
th { background: #0b70d7; color: #fff; }
tr:hover { background: #f5f7fa; }

/* Botões */
.btn-primary { background: #0b7bec; }
.btn-danger { background: #dc3545; }
.btn-success { background: #22a846; }

/* Feedback */
.alert-error { background: #f8d7da; color: #842029; }
.alert-success { background: #d1e7dd; color: #0f5132; }
```

### Layout Padrão
```html
<div class="container">
  <div class="header">
    <h1>📦 Título</h1>
  </div>
  <div class="form-card">
    <!-- Formulário -->
  </div>
  <div class="list-card">
    <!-- Tabela -->
  </div>
  <div class="feedback" id="msg"></div>
</div>
```

---

## 7. CHECKLIST DE IMPLEMENTAÇÃO

### Tela 1 — Cadastro de Faixas
- [ ] Layout visual melhorado
- [ ] Editar modal/inline funcional
- [ ] Deletar com confirmação
- [ ] Sequência automática
- [ ] Mensagens de feedback
- [ ] Validação visual de sobreposição
- [ ] Coluna de Tipo Embalamento

### Tela 2 — Tipos de Embalamento
- [ ] Model atualizado com novos campos
- [ ] Repository atualizado
- [ ] Service atualizado
- [ ] Controller atualizado
- [ ] Formulário completo
- [ ] Tabela com dados
- [ ] Editar/Deletar funcional

### Tela 3 — Resultado (PRINCIPAL)
- [ ] Seletor de configuração com carregamento automático
- [ ] Tabela dinâmica com faixas
- [ ] Campos de percentual editáveis
- [ ] Cálculo automático de peso
- [ ] Validação visual de soma %
- [ ] Salvamento em histórico
- [ ] Campos opcionais (produtor, variedade, etc.)

### Tela 4 e 5
- [ ] Estrutura básica
- [ ] Carregamento de dados
- [ ] Histórico visível

### Visual Geral
- [ ] CSS padrão Balanceamento aplicado
- [ ] Responsividade testada
- [ ] Cores e componentes consistentes
- [ ] Feedback visual em todas as ações

---

## 8. ARQUIVOS A CRIAR/MODIFICAR

### Criar (novas views melhoradas)
- [ ] `views/etapa1_faixas.php` → Reescrever com visual completo
- [ ] `views/etapa2_configuracao.php` → Completar campos
- [ ] `views/etapa3_registro_lote.php` → Implementar do zero
- [ ] `views/etapa4_distribuicao.php` → Simples, carrega de Tela 3
- [ ] `views/etapa5_resultado.php` → Resumo + histórico

### Modificar (modelos/lógica)
- [ ] `models/ConfiguracaoEmbalamento.php` → Adicionar campos
- [ ] `repositories/ConfiguracaoEmbalamentoRepository.php` → Suportar novos campos
- [ ] `services/CalbradoraService.php` → Novos métodos se necessário
- [ ] `controllers/CalbradoraController.php` → Novos handlers

### Criar (API/suporte)
- [ ] `api_calibradora.php` → Endpoints AJAX para Tela 3 (opcional)

---

## 9. ORDEM RECOMENDADA DE IMPLEMENTAÇÃO

```
FASE 1 (Tela 1):
1. Atualizar HTML/CSS da etapa1_faixas.php
2. Implementar editar (modal)
3. Testar salvar/editar/deletar
4. Validações visuais

FASE 2 (Tela 2):
1. Atualizar Model ConfiguracaoEmbalamento
2. Atualizar Repository e Service
3. Criar formulário em etapa2_configuracao.php
4. Testar CRUD

FASE 3 (Tela 3 - Principal):
1. Criar estructura HTML em etapa3_registro_lote.php
2. Implementar seletor e carregamento automático de faixas
3. Adicionar campos de entrada (peso, percentual)
4. Implementar cálculo em JavaScript
5. Validação de soma percentual
6. Salvamento em histórico

FASE 4 (Telas 4 e 5):
1. Carregamento simples de dados
2. Visualização e histórico

FASE 5 (Finalizações):
1. Aplicar CSS padrão em todas
2. Testar responsividade
3. Testar fluxo completo
4. Documentação
```

---

## 10. OBSERVAÇÕES IMPORTANTES

### ✅ Reaproveitar

```
✅ Models: FaixaPeso, ConfiguracaoEmbalamento, RegistroLote
✅ Repositories: Toda lógica de acesso
✅ Service: Métodos de validação
✅ Controller: Padrão de resposta
✅ Bootstrap: Carregamento de classes
✅ CSS: Padrão do Balanceamento
```

### ❌ Não Alterar

```
❌ Módulos legados (Balanceamento, Cronoanálise, Árvore)
❌ /public/reformulacao/safe_storage.php
❌ Arquivo menu.php (apenas se precisar add link, sem quebra)
❌ Outras telas fora de Calibradora
```

### 🔐 Segurança

```
🔐 Usar sempre controller.processarRequisicao()
🔐 Nunca acessar repository direto
🔐 Validar entrada em controller
🔐 Usar prepared statements (já feito em repository)
```

---

**Plano pronto para execução!**
