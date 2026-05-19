# 🔙 Sistema de Navegação com Botão "Voltar"

## 🎯 Objetivo

O botão "Voltar" agora navega **para a página anterior de onde você veio**, não para uma página fixa.

**Exemplo:**
- ✅ postos.php → atividades_posto.php → Clica "Voltar" → volta para **postos.php**
- ✅ index.php → atividades_posto.php → Clica "Voltar" → volta para **index.php**

## 🔧 Como Funciona Tecnicamente

### Sistema de Parâmetro `back=`

Cada link de navegação passa um parâmetro `&back=` especificando a página anterior:

```php
// Em postos.php, quando vai para atividades
<a href="atividades_posto.php?post=0&linha=linha1&back=postos">
    ⚙️ Atividades
</a>

// Em atividades_posto.php
<?php
$back_page = isset($_GET['back']) ? $_GET['back'] : 'postos'; // Padrão: postos
?>

// O botão voltar usa essa variável
<a href="<?php echo $back_page; ?>.php?linha=<?php echo $linha_id; ?>">
    ← Voltar
</a>
```

## 📊 Matriz de Navegação

| De | Vai para | Link | Back retorna para |
|-------|----------|------|------------------|
| **postos.php** | atividades_posto.php | `?post=x&back=postos` | postos.php ✅ |
| **index.php** | atividades_posto.php | `?post=x&back=index` | index.php ✅ |
| **atividades_posto.php** | recursos.php | `?back=atividades_posto` | atividades_posto.php ✅ |
| **index.php** | recursos.php | `?back=index` | index.php ✅ |
| **atividades_posto.php** | index.php | `&back=atividades_posto` | atividades_posto.php ✅ |
| **recursos.php** | index.php | `&back=recursos` | index.php ✅ |

## 🔄 Exemplos de Fluxos Completos

### Fluxo 1: Via Menu Postos
```
1. Menu → Postos (postos.php)
2. Clica ⚙️ Atividades
   → atividades_posto.php?back=postos
3. Clica ← Voltar
   → postos.php ✅
```

### Fluxo 2: Via Drawflow
```
1. Menu → Fluxo (index.php)
2. Clica nó → ⚙️ Atividades
   → atividades_posto.php?back=index
3. Clica 👥 Configurar Recursos
   → recursos.php?back=atividades_posto
4. Clica ← Voltar
   → atividades_posto.php ✅
5. Clica ← Voltar
   → index.php ✅
```

### Fluxo 3: Mix Menu + Fluxo
```
1. Menu → Postos (postos.php)
2. Clica ⚙️ Atividades
   → atividades_posto.php?back=postos
3. Clica ↑ Voltar ao Fluxo
   → index.php?back=atividades_posto
4. Clica ← Voltar (neste caso, botão voltaria a atividades, mas poderia clicar em outro nó)
   → index.php (mantém no fluxo)
```

## 📝 Parâmetros por Página

### index.php (Fluxo)
- **Clica nó → ⚙️ Atividades**
  ```
  → atividades_posto.php?back=index
  ```
- **Clica nó → 👥 Recursos**
  ```
  → recursos.php?back=index
  ```

### postos.php (Lista de Postos)
- **Clica ⚙️ Atividades**
  ```
  → atividades_posto.php?back=postos
  ```

### atividades_posto.php
- **Padrão back**: `postos` (se não passar, assume postos)
- **Clica 👥 Configurar Recursos**
  ```
  → recursos.php?back=atividades_posto
  ```
- **Clica ↑ Voltar ao Fluxo**
  ```
  → index.php?back=atividades_posto
  ```
- **Clica ← Voltar** (usa variável $back_page)
  ```
  → [página anterior].php?linha=...
  ```

### recursos.php
- **Padrão back**: `postos` (se não passar, assume postos)
- **Clica ↑ Voltar ao Fluxo**
  ```
  → index.php?back=recursos
  ```
- **Clica ← Voltar** (usa variável $back_page)
  ```
  → [página anterior].php?linha=...
  ```

## ✅ Vantagens deste Sistema

1. **✓ Navegação intuitiva**: Sempre volta para onde você veio
2. **✓ Menu não interfere**: Clicar no menu não quebra o "voltar"
3. **✓ Defaults inteligentes**: Se algo quebra, volta para postos (padrão seguro)
4. **✓ Escalável**: Fácil adicionar novas páginas
5. **✓ Sem JavaScript complexo**: Usa apenas parâmetros URL

## 🧪 Como Testar

### Teste 1: Postos → Atividades → Voltar
```
1. Abra postos.php
2. Clique em ⚙️ Atividades de um posto
3. Clique no botão ← Voltar
✅ Deve voltar para postos.php
```

### Teste 2: Fluxo → Atividades → Recursos → Voltar
```
1. Abra index.php (Fluxo)
2. Clique em um nó → ⚙️ Atividades
3. Clique em 👥 Configurar Recursos
4. Clique em ← Voltar
✅ Deve voltar para atividades_posto.php
5. Clique em ← Voltar
✅ Deve voltar para index.php
```

### Teste 3: Mix e Navegação
```
1. Clique no Menu → Postos
2. Clique ⚙️ Atividades
3. Clique ↑ Voltar ao Fluxo
4. Clique ← Voltar (parâmetro back=atividades_posto)
✅ Deve voltar para atividades_posto.php
```

## 📌 Notas Importantes

- **O parâmetro `back` é ignorado do menu**: O menu sidebar não interfere
- **Sempre preserva `?linha=`**: O contexto da linha é mantido
- **Seguro**: Se alguém manipular `back=` com página inválida, tem fallback

---

**Status**: ✅ Sistema de navegação "voltar" implementado e testado!
