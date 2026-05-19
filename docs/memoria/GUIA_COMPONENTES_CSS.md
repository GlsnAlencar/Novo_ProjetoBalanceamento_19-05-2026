# 📚 GUIA DE USO - COMPONENTES CSS

## 📖 Como Usar os Novos Estilos

Todos os arquivos PHP agora devem incluir o CSS global:

```php
<?php include 'menu.php'; ?>  <!-- Já inclui o CSS global -->
```

---

## 🔘 BOTÕES

### Variações de Cor
```html
<!-- Primário (Azul) -->
<button class="btn btn-primary">Ação Principal</button>

<!-- Sucesso (Verde) -->
<button class="btn btn-success">Confirmar</button>

<!-- Perigo (Vermelho) -->
<button class="btn btn-danger">Deletar</button>

<!-- Info (Ciano) -->
<button class="btn btn-info">Informação</button>

<!-- Secondary (Cinza) -->
<button class="btn btn-secondary">Cancelar</button>
```

### Tamanhos
```html
<!-- Padrão -->
<button class="btn btn-primary">Botão Padrão</button>

<!-- Pequeno -->
<button class="btn btn-sm btn-primary">Pequeno</button>

<!-- Grande -->
<button class="btn btn-lg btn-primary">Grande</button>
```

### Com Ícone
```html
<button class="btn btn-primary btn-icon">
    ➕ Adicionar
</button>

<a href="#" class="btn btn-sm btn-info">
    ✏️ Editar
</a>
```

---

## 🏷️ BADGES

### Cores Disponíveis
```html
<span class="badge badge-primary">Primário</span>
<span class="badge badge-success">Sucesso</span>
<span class="badge badge-danger">Perigo</span>
<span class="badge badge-warning">Aviso</span>
<span class="badge badge-info">Informação</span>
```

### Usar em Contadores
```html
<h1>
    Gerenciamento de Unidades
    <span class="badge badge-primary">5</span>
</h1>
```

### Usar em Tabelas
```html
<table>
    <tbody>
        <tr>
            <td>Unidade A</td>
            <td><span class="badge badge-info">2.5 kg</span></td>
        </tr>
    </tbody>
</table>
```

---

## 📝 FORMULÁRIOS

### Estrutura Básica
```html
<div class="form-section">
    <h3>Título da Seção</h3>
    
    <form method="post" class="form-grid">
        <div class="form-group">
            <label for="campo1" class="required">Campo Obrigatório</label>
            <input type="text" id="campo1" name="campo1" required>
        </div>
        
        <div class="form-group">
            <label for="campo2">Campo Opcional</label>
            <input type="text" id="campo2" name="campo2">
        </div>
        
        <button type="submit" class="btn btn-primary">Enviar</button>
    </form>
</div>
```

### Grid com Múltiplas Colunas
```html
<form method="post" class="form-grid">
    <!-- Automaticamente ajusta para múltiplas colunas -->
    <div class="form-group">...</div>
    <div class="form-group">...</div>
    <div class="form-group">...</div>
</form>
```

### Grid com Uma Coluna
```html
<form method="post" class="form-grid full-width">
    <!-- Sempre em uma coluna -->
    <div class="form-group">...</div>
    <div class="form-group">...</div>
</form>
```

### Formulário em Linha
```html
<form method="post" style="display: flex; gap: 10px; align-items: flex-end;">
    <div>
        <label>Campo</label>
        <input type="text" name="campo">
    </div>
    
    <button type="submit" class="btn btn-primary">Enviar</button>
</form>
```

---

## 🎴 CARDS

### Estrutura Completa
```html
<div class="card">
    <div class="card-header">
        Título do Card
    </div>
    <div class="card-body">
        <p>Conteúdo principal do card.</p>
    </div>
    <div class="card-footer">
        <button class="btn btn-sm btn-primary">Ação</button>
        <button class="btn btn-sm btn-secondary">Cancelar</button>
    </div>
</div>
```

### Card Simples (Sem Footer)
```html
<div class="card">
    <div class="card-header">
        Informação
    </div>
    <div class="card-body">
        <p>Apenas informação sem ações.</p>
    </div>
</div>
```

---

## 📊 KPI CARDS (para Dashboard)

### Usar em Dashboard
```html
<div class="kpi-container">
    <div class="kpi-card primary">
        <div class="kpi-label">Tempo de Ciclo</div>
        <div class="kpi-value">125.5</div>
        <div class="kpi-unit">segundos</div>
    </div>
    
    <div class="kpi-card success">
        <div class="kpi-label">Taxa de Produção</div>
        <div class="kpi-value">85.2</div>
        <div class="kpi-unit">kg/min</div>
    </div>
    
    <div class="kpi-card danger">
        <div class="kpi-label">Gargalos</div>
        <div class="kpi-value">2</div>
        <div class="kpi-unit">postos</div>
    </div>
</div>
```

### Cores Disponíveis
```html
<div class="kpi-card primary">...</div>
<div class="kpi-card success">...</div>
<div class="kpi-card danger">...</div>
<div class="kpi-card info">...</div>
```

---

## 🔔 MENSAGENS / ALERTAS

### Sucesso
```html
<div class="success-message">
    ✅ Operação realizada com sucesso!
</div>
```

### Erro
```html
<div class="error-message">
    ❌ Ocorreu um erro ao processar!
</div>
```

### Aviso
```html
<div class="warning-message">
    ⚠️ Tenha cuidado ao continuar!
</div>
```

### Informação
```html
<div class="info-message">
    ℹ️ Informação importante para você!
</div>
```

---

## 📋 TABELAS

### Estrutura Padrão
```html
<table>
    <thead>
        <tr>
            <th width="30%">Coluna 1</th>
            <th width="40%">Coluna 2</th>
            <th width="30%">Ações</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Dado 1</td>
            <td>Dado 2</td>
            <td>
                <a href="#" class="btn btn-sm btn-info">Editar</a>
                <a href="#" class="btn btn-sm btn-danger">Remover</a>
            </td>
        </tr>
    </tbody>
</table>
```

### Com Seção de Agrupamento
```html
<table>
    <thead>
        <tr>
            <th>Linha</th>
            <th>Posto</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <!-- Header de seção -->
        <tr class="table-section-header">
            <td colspan="3">📍 Linha 1</td>
        </tr>
        
        <!-- Dados da seção -->
        <tr>
            <td>Linha 1</td>
            <td>Posto A</td>
            <td>Ações aqui</td>
        </tr>
        
        <tr>
            <td>Linha 1</td>
            <td>Posto B</td>
            <td>Ações aqui</td>
        </tr>
    </tbody>
</table>
```

---

## 🗂️ ABAS / TABS

### Estrutura
```html
<div class="tabs">
    <button class="tab active">Aba 1</button>
    <button class="tab">Aba 2</button>
    <button class="tab">Aba 3</button>
</div>

<div id="tab1-content">
    Conteúdo da Aba 1
</div>

<div id="tab2-content" style="display: none;">
    Conteúdo da Aba 2
</div>
```

### JavaScript para Funcionalidade
```javascript
document.querySelectorAll('.tab').forEach((tab, index) => {
    tab.addEventListener('click', function() {
        // Remove classe active de todas
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        
        // Adiciona à clicada
        this.classList.add('active');
    });
});
```

---

## 🎭 MODAIS (Básico)

### Estrutura
```html
<div id="meuModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Título do Modal</h2>
            <button class="modal-close" onclick="fecharModal('meuModal')">&times;</button>
        </div>
        
        <div class="modal-body">
            <form method="post">
                <!-- Conteúdo do formulário -->
            </form>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="fecharModal('meuModal')">Cancelar</button>
            <button class="btn btn-primary" onclick="salvar()">Salvar</button>
        </div>
    </div>
</div>
```

### JavaScript
```javascript
function abrirModal(idModal) {
    document.getElementById(idModal).classList.add('show');
}

function fecharModal(idModal) {
    document.getElementById(idModal).classList.remove('show');
}
```

---

## 🎨 CLASSES UTILITÁRIAS

### Texto
```html
<p class="text-center">Texto centralizado</p>
<p class="text-muted">Texto apagado</p>
```

### Espaçamento
```html
<div class="mt-20">Margem superior 20px</div>
<div class="mb-20">Margem inferior 20px</div>
<div class="mt-30">Margem superior 30px</div>
<div class="mb-30">Margem inferior 30px</div>
```

---

## 📱 RESPONSIVIDADE

### Breakpoints
```css
Desktop:  > 768px   (normal)
Tablet:   768px     (ajusta grid)
Mobile:   < 480px   (coluna única)
```

### Grid Responsivo
```html
<!-- Automaticamente ajusta baseado no screen size -->
<form class="form-grid">
    <!-- Desktop: múltiplas colunas -->
    <!-- Tablet: 2 colunas -->
    <!-- Mobile: 1 coluna -->
</form>
```

---

## ✅ EXEMPLO COMPLETO

```html
<?php
session_start();
include 'data_store.php';
$dados = load_json_data('dados');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exemplo</title>
    <?php include 'menu.php'; ?>
</head>
<body>
    <div class="content">
        <div class="container">
            <!-- Título com Badge -->
            <h1>
                📦 Gerenciamento
                <span class="badge badge-primary"><?php echo count($dados); ?></span>
            </h1>
            
            <!-- Mensagem de Sucesso -->
            <?php if (isset($sucesso)): ?>
                <div class="success-message">
                    ✅ Operação realizada com sucesso!
                </div>
            <?php endif; ?>
            
            <!-- Seção de Formulário -->
            <div class="form-section">
                <h3>➕ Adicionar Novo</h3>
                <form method="post" class="form-grid">
                    <div class="form-group">
                        <label for="nome" class="required">Nome</label>
                        <input type="text" id="nome" name="nome" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                </form>
            </div>
            
            <!-- Tabela -->
            <h2>📋 Lista</h2>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dados as $index => $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nome']); ?></td>
                        <td>
                            <a href="#" class="btn btn-sm btn-info">✏️</a>
                            <a href="#" class="btn btn-sm btn-danger">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Botão de Voltar -->
            <a href="index.php" class="btn btn-secondary" style="margin-top: 30px;">← Voltar</a>
        </div>
    </div>
</body>
</html>
```

---

## 🚀 DICAS DE USO

1. **Sempre use classes padronizadas**: `.btn`, `.badge`, `.card`, etc.
2. **Mantenha o espaçamento**: Use `.mt-20`, `.mb-30` para consistência
3. **Use badges para dados numéricos**: Chamam atenção melhor
4. **Cores tem significado**:
   - 🟦 Azul = Ação principal
   - 🟩 Verde = Sucesso/Confirmar
   - 🟥 Vermelho = Perigo/Deletar
   - 🟦 Ciano = Informação/Secundária
5. **Mantenha formulários simples**: Não mais de 3-4 campos por linha
6. **Responsividade automática**: Grid e layouts já se adaptam

---

**Versão**: 1.0
**Data**: 2024
**Manutenção**: Atualize estilos globais em `styles.css`
