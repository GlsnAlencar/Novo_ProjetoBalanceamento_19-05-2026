# 🎯 QUICK REFERENCE CARD - PARÂMETROS & FLUXOS

> **Cole esta página na parede do seu monitor!**

---

## 🔑 PARÂMETROS ESSENCIAIS

### NÃO CONFUNDA!

```
linha_id     = ID ÚNICO da LINHA          (ex: "linha_001")
setor_id     = ID ÚNICO do SETOR          (ex: "setor_001")
post_index   = ÍNDICE do posto (0-based)  (ex: 0, 1, 2...)
post_nome    = NOME do posto              (ex: "Puxar fruta")
```

---

## 📍 MAPA DE NAVEGAÇÃO

```
START
  ↓
index.php?setor_id=X&linha_id=Y
  │
  ├─ [⚙️ Atividades] → atividades_posto.php?post=INDEX&linha=Y&back=index
  │                      ↓
  │                      [← Voltar] → index.php?linha=Y&back=atividades_posto
  │
  ├─ [👥 Recursos] → recursos.php?linha=Y&post=INDEX&back=index
  │
  ├─ [Menu] → postos.php?setor_id=X
  │             ↓
  │             [⚙️ Atividades] → atividades_posto.php?post=INDEX&linha=Y&back=postos
  │                                  ↓
  │                                  [← Voltar] → postos.php?setor_id=X
  │
  └─ [Fluxos] → fluxos.php?linha_id=Y
```

---

## ⚡ SNIPPETS RÁPIDOS

### Receber parâmetro e validar
```php
$linha_id = $_GET['linha'] ?? null;
if (!$linha_id) {
    die('❌ Parâmetro "linha" obrigatório');
}
```

### Carregar dados JSON
```php
$linhas = load_json_data('linhas');
$linha = find_linha_by_id($linhas, $linha_id);
if ($linha === null) {
    die('❌ Linha não encontrada');
}
```

### Remover elemento e reindexar
```php
unset($array[$index]);
$array = array_values($array); // ⚠️ SEMPRE!
save_json_data('linhas', $linhas_json);
```

### Link para atividades (CORRETO)
```html
<a href="atividades_posto.php?post=<?= $post_index; ?>&linha=<?= urlencode($linha_id); ?>&back=postos">
    ⚙️ Atividades
</a>
```

### Escape para HTML
```php
echo htmlspecialchars($valor);
```

### URL encode
```php
echo urlencode($valor);
```

---

## 📊 CÁLCULOS MAIS USADOS

| Cálculo | Fórmula | Onde Usar |
|---------|---------|-----------|
| **Tempo/Unidade** | `tempo_total ÷ quantidade` | atividades_posto.php L66 |
| **Tempo/Peso** | `tempo_por_unidade ÷ peso_unidade` | atividades_posto.php L67 |
| **Tempo Ciclo** | `Σ tempo_total` | index.php L100 |
| **Ritmo** | `tempo_contentor ÷ num_pessoas` | index.php L1026 |

---

## 🚨 TOP 5 ERROS MAIS COMUNS

| # | Erro | Causa | Solução |
|---|------|-------|---------|
| 1 | "Posto não encontrado" | Passou `setor_id` em vez de `linha_id` | Use `$linha_id` não `$setor_ativo_id` |
| 2 | Índices disparos | Não reindexou array | `$array = array_values($array);` |
| 3 | PHP errors | Não validou null | `if ($var === null) { die(...); }` |
| 4 | XSS risk | Não escapou HTML | `htmlspecialchars($value)` |
| 5 | URL quebrada | Não urlencodou | `urlencode($value)` |

---

## 📂 ESTRUTURA DE ARQUIVOS

```
Balanceamento_02/
├── public/
│   ├── index.php               ← FLUXO VISUAL
│   ├── postos.php              ← GERENCIAR POSTOS
│   ├── atividades_posto.php    ← EDITAR ATIVIDADES
│   ├── recursos.php            ← DEFINIR PESSOAS
│   ├── fluxos.php              ← CONEXÕES
│   ├── setores.php             ← GERENCIAR SETORES
│   ├── unidades.php            ← CADASTRAR UNIDADES
│   ├── data_store.php          ← FUNÇÕES DE DATA
│   ├── menu.php                ← MENU LATERAL
│   ├── security.php            ← SEGURANÇA
│   └── styles.css              ← ESTILOS
│
├── data/
│   ├── linhas.json             ← POSTOS + ATIVIDADES
│   ├── setores.json            ← SETORES
│   ├── unidades.json           ← UNIDADES
│   └── transporte.json         ← TRANSPORTE
│
└── GUIA_DESENVOLVIMENTO.md     ← ESTE ARQUIVO
```

---

## 🔍 ESTRUTURA DE DADOS

### linhas.json
```json
{
  "id": "linha_001",
  "setor_id": "setor_001",
  "postos": [
    {
      "nome": "Puxar fruta",
      "atividades": [
        {
          "descricao": "...",
          "tempo_total": 3600,
          "tempo_por_unidade": 36,
          "tempo_por_peso": 1.44
        }
      ],
      "recursos": { "num_pessoas": 2 }
    }
  ]
}
```

---

## ✅ PRÉ-COMMIT CHECKLIST

Antes de fazer commit:

```
□ Testei em 2+ cenários
□ Validei JSON com lint
□ Removi console.log() de debug
□ Escapei todos os echos com htmlspecialchars()
□ Urlencodei todos os URLs
□ Reindexei arrays após unset()
□ Documentei em GUIA_DESENVOLVIMENTO.md
□ Backup dos arquivos antes
□ Sem erros no browser console
□ Sem erros no PHP error log
□ Dados persistem após F5
```

---

## 🆘 TROUBLESHOOTING

### Problema: "Parâmetro obrigatório faltando"
```
Solução:
1. Verificar URL na barra de navegação
2. Confirmar que todos os parâmetros estão presentes
3. Usar var_dump($_GET) para debug
```

### Problema: Atividades não salva
```
Solução:
1. Conferir permissions em data/*.json
2. Testar save_json_data() com var_dump
3. Validar JSON com https://jsonlint.com/
```

### Problema: Cálculos errados
```
Solução:
1. Verificar se unidade "Contentor" existe
2. Confirmar peso_padrao da unidade
3. Recalcular manualmente com calculadora
```

---

## 💾 FUNÇÕES DISPONÍVEIS

### Em data_store.php

```php
load_json_data($filename)
save_json_data($filename, $data)
find_linha_by_id($linhas, $id)
find_setor_by_id($setores, $id)
find_posto_in_linha($linha, $id)
create_default_setor($nome, $descricao)
create_default_linha($nome, $setor_id)
add_conexao($linha, $origem_id, $destino_id, $tipo)
remove_conexao($linha, $conexao_id)
```

---

## 🎨 ESTILOS CSS PRINCIPAIS

```css
.btn-editar-detalhes  { background: #17a2b8; }
.btn-remover          { background: #dc3545; }
.btn-grupo-acao       { background: #007bff; }
.back-button          { background: #6c757d; }
.success-message      { background: #d4edda; }
.error-message        { background: #f8d7da; }
```

---

## 📱 RESPONSIVIDADE

```css
/* Mobile: < 480px */
.sidebar { width: 200px; }
.content { margin-left: 200px; }

/* Tablet: < 768px */
.sidebar { width: 250px; }
.content { margin-left: 250px; }

/* Desktop: >= 768px */
.sidebar { width: 290px; }
.content { margin-left: var(--menu-width); }
```

---

## 🔐 SEGURANÇA

- ✅ SEMPRE validar `$_GET` e `$_POST`
- ✅ SEMPRE escapar output com `htmlspecialchars()`
- ✅ SEMPRE urlencode URLs
- ✅ Usar `session_start()` no topo
- ⚠️ Nunca fazer `eval()` ou `exec()`
- ⚠️ Nunca expor caminhos de arquivo

---

## 📞 QUANDO CHAMAR O ARQUITETO

- Sistema inteiro não carrega
- Erro de persistência em JSON
- Cálculos discrepantes (verificar 3x antes!)
- Grandes refatorações de estrutura
- Mudanças em `data_store.php`

---

**Imprime isto! Cole no monitor!**
**Atualizado:** 05/05/2026

