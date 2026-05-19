# ✅ CHECKLIST DE CODE REVIEW & VALIDAÇÃO

> **Use isto antes de fazer merge/commit!**
> **Para Dev Senior fazer Review + Dev Júnior ser revisado**

---

## 📋 PHASE 1: ANÁLISE ESTÁTICA (5 min)

### 1.1 Sintaxe PHP
```
□ Sem erros de sintaxe (rodar com php -l arquivo.php)
□ Todas as chaves { } estão fechadas
□ Todos os pontos-e-vírgula presentes
□ Aspas abertas/fechadas corretamente
```

### 1.2 Parâmetros de Entrada
```
□ Todos os $_GET validados com ?? (null coalescing)
□ Todos os $_POST validados com ?? 
□ Sem acessar diretamente sem trim()/validation
□ Comparações seguras (=== not ==)
```

### 1.3 Segurança
```
□ Todos os echos com htmlspecialchars()
□ Todos os URLs com urlencode()
□ Sem uso de eval() ou exec()
□ Sem senha/chave exposta no código
```

---

## 📍 PHASE 2: VALIDAÇÃO DE PARÂMETROS (10 min)

### 2.1 Se a tela acessa POSTOS

Verificar URL:
```php
// ❌ ERRADO
?post=0&linha=setor_001          // passou setor_id!
?post=0                           // faltou linha
?post=X&setor_id=Y               // setor_id não é linha_id

// ✅ CORRETO
?post=0&linha=linha_001&back=index
?post=0&linha=linha_001&back=postos
```

### 2.2 Se a tela acessa ATIVIDADES

Verificar código:
```php
// ❌ ERRADO
$linha_id = $_GET['setor_id'] ?? null;   // nome errado
$linha_id = $_GET['id'] ?? null;         // nome genérico

// ✅ CORRETO
$linha_id = $_GET['linha'] ?? null;      // nome consistente
$post_index = $_GET['post'] ?? null;
$back_page = $_GET['back'] ?? 'postos';
```

### 2.3 Validação

```php
// ❌ ERRADO
$linha = find_linha_by_id($linhas, $linha_id);
$posts = $linha['postos']; // Pode falhar se $linha === null

// ✅ CORRETO
$linha = find_linha_by_id($linhas, $linha_id);
if ($linha === null) {
    die('❌ Linha não encontrada');
}
$posts = $linha['postos'];
```

---

## 🔄 PHASE 3: OPERAÇÕES DE ARRAY (10 min)

### 3.1 Após remover elemento
```php
// ❌ ERRADO (Índices ficam disparos!)
unset($array[$index]);
save_json_data('linhas', $linhas_json);

// ✅ CORRETO
unset($array[$index]);
$array = array_values($array);  // ⚠️ OBRIGATÓRIO!
save_json_data('linhas', $linhas_json);
```

### 3.2 Após modificar dentro de loop
```php
// ❌ ERRADO (Não modifica original)
foreach ($linhas as $linha) {
    $linha['nome'] = 'Novo';
}

// ✅ CORRETO
foreach ($linhas as &$linha) {
    $linha['nome'] = 'Novo';
}
unset($linha); // Desfazer referência!
```

### 3.3 Após adicionar elemento
```php
// ❌ ERRADO (Pode quebrar índices)
$array[] = $novo_elemento;
// sem verificar duplicatas

// ✅ CORRETO
$existe = false;
foreach ($array as $item) {
    if ($item['id'] === $novo['id']) {
        $existe = true;
        break;
    }
}
if (!$existe) {
    $array[] = $novo_elemento;
}
```

---

## 📊 PHASE 4: CÁLCULOS (15 min)

### 4.1 Valores de Entrada

```
□ Verificar se unidades existem em unidades.json
□ Verificar se "Contentor" está cadastrado
□ Verificar peso_padrao > 0
□ Verificar quantidade > 0
□ Verificar tempo_total > 0
```

### 4.2 Fórmulas

Verificar manualmente:
```
Tempo/Unidade = tempo_total / quantidade
Exemplo: 3600 / 100 = 36 ✓

Tempo/Peso = tempo_por_unidade / peso_unidade  
Exemplo: 36 / 25 = 1.44 ✓

Tempo/Contentor = tempo_por_peso_médio × peso_contentor
Exemplo: 1.44 × 1000 = 1440 ✓
```

### 4.3 Arredondamento

```php
// ❌ ERRADO
$tempo = 3600 / 100 / 25;  // 1.44000000 (muitos decimais)

// ✅ CORRETO
$tempo = round(3600 / 100 / 25, 4);  // 1.44 (4 casas)
number_format($tempo, 4, ',', '.');  // Exibição
```

---

## 🎨 PHASE 5: HTML & CSS (5 min)

### 5.1 Estrutura

```
□ Tem <!DOCTYPE html>
□ Meta charset="UTF-8"
□ Meta viewport="width=device-width"
□ Título da página preenchido
□ Include menu.php incluído
```

### 5.2 Responsividade

```
□ Classes CSS existem em styles.css
□ Media queries testadas (mobile, tablet, desktop)
□ Botões têm hover estados
□ Links têm cursor: pointer
```

### 5.3 Acessibilidade

```
□ Formulários têm <label>
□ Inputs têm id e name
□ Botões têm type="button" ou type="submit"
□ Links têm title=""
□ Cores contrastantes
```

---

## 🔗 PHASE 6: NAVEGAÇÃO (10 min)

### 6.1 Links Internos

```
□ Todos os links têm href= com valor completo
□ Todos os parâmetros estão urlencode()
□ Back button vai para tela correta
□ Sem loops de redirecionamento
```

### 6.2 Redirecionamentos

```php
// ❌ ERRADO
header('Location: postos.php?linha=' . $setor_id);  // setor_id!

// ✅ CORRETO  
header('Location: postos.php?setor_id=' . $setor_id);

// ❌ ERRADO
header('Location: /atividades_posto.php'); // Path absoluto

// ✅ CORRETO
header('Location: atividades_posto.php?post=0&linha=...');
```

### 6.3 Mensagens de Retorno

```php
// ✅ CORRETO
header('Location: postos.php?setor_id=' . urlencode($setor_id) . '&sucesso=1');
header('Location: postos.php?setor_id=' . urlencode($setor_id) . '&erro=duplicado');

// Em postos.php
if ($_GET['sucesso'] ?? false) {
    echo '<div class="success-message">✓ Sucesso!</div>';
}
```

---

## 🧪 PHASE 7: TESTES (30 min)

### 7.1 Cenário: Criar Posto

```
□ Abrir postos.php?setor_id=X
□ Preencher nome
□ Clicar "Adicionar"
□ Verificar JSON foi atualizado
□ Verificar volta com ?sucesso=1
□ F5 para ver persistência
□ Remover e verificar reindexação
```

### 7.2 Cenário: Adicionar Atividade

```
□ De postos.php, clicar ⚙️ Atividades
□ Verificar URL: atividades_posto.php?post=0&linha=LINHA_ID&back=postos
□ Preencher formulário
□ Clicar "Adicionar"
□ Verificar cálculos (tempo/unidade, tempo/peso)
□ Verificar volta para postos.php?setor_id=X
□ F5 para ver persistência
```

### 7.3 Cenário: Ir de index.php para Atividades

```
□ Abrir index.php?setor_id=X&linha_id=Y
□ Clicar ⚙️ Atividades no fluxo
□ Verificar URL: atividades_posto.php?post=INDEX&linha=Y&back=index
□ Fazer alterações
□ Clicar "Voltar ao Fluxo"
□ Verificar volta para index.php?linha=Y&back=atividades_posto
□ Verificar Drawflow atualizado
```

### 7.4 Erro Esperado: Parâmetro Faltando

```
□ Tentar acessar atividades_posto.php sem ?post=
□ Deve exibir: "❌ Parâmetros inválidos"
□ Tentar com ?linha= errado
□ Deve exibir: "❌ Linha não encontrada"
```

### 7.5 Validação JSON

```
□ Abrir data/linhas.json
□ Executar em https://jsonlint.com/
□ Deve estar ✓ Valid JSON
□ Sem quebras de integridade
```

---

## 📱 PHASE 8: BROWSER TESTS (15 min)

### 8.1 Console
```
□ Abrir DevTools (F12)
□ Ir em "Console"
□ Não deve haver ❌ erros vermelhos
□ Não deve haver ⚠️ warnings (exceto 3rd party)
□ Limpar localStorage/sessionStorage
□ Recarregar e testar novamente
```

### 8.2 Network
```
□ Abrir "Network" tab
□ Nenhuma requisição com status ❌ 404 ou 500
□ Tempo de carregamento < 2s
□ Sem requisições bloqueadas por CORS
```

### 8.3 Responsividade

```
□ Device Toolbar: Mobile (iPhone)
□ Device Toolbar: Tablet (iPad)
□ Device Toolbar: Desktop
□ Layout se adapta sem quebrar
□ Textos legíveis em todos
```

### 8.4 Performance

```
□ Lighthouses Score > 70
□ Sem memory leaks (abrir DevTools → Memory)
□ Sem console.log() de debug
```

---

## 🔐 PHASE 9: SEGURANÇA (10 min)

### 9.1 Injeção

```
□ Entrada como: "); DROP TABLE linhas;--
□ Deve ser escapada (não quebrar banco)
□ Saída renderiza segura (não executa JS)
```

### 9.2 CSRF/Session

```
□ session_start() está no topo
□ Formulários validam origem
□ Sem acesso cruzado entre abas
```

### 9.3 Path Traversal

```
□ Sem aceitar: ../../etc/passwd
□ Sem aceitar: /var/www/html
□ Validar caminhos de arquivo
```

---

## 💾 PHASE 10: PERSISTÊNCIA (10 min)

### 10.1 Salvar

```
□ save_json_data() é chamada
□ Arquivo JSON é atualizado
□ Timestamp do arquivo muda
□ F5 recarrega dados corrigidos
```

### 10.2 Backup

```
□ Fazer backup de data/*.json antes
□ Após testes, comparar com original
□ Sem dados perdidos
□ Sem corrupção de caracteres especiais (acentos)
```

---

## 🎯 RESUMO DO CHECKLIST

### ❌ REJEITAR SE:
- [ ] Parâmetros inconsistentes (setor_id vs linha_id)
- [ ] Falta reindexação após unset()
- [ ] Sem validação de dados
- [ ] Sem htmlspecialchars() em echos
- [ ] JSON inválido após operação
- [ ] Erro "Posto não encontrado"
- [ ] Links quebrados
- [ ] Cálculos inconsistentes

### ✅ APROVAR SE:
- [ ] Todos os parâmetros corretos
- [ ] Arrays reindexados
- [ ] Dados validados
- [ ] Saída escapada
- [ ] JSON válido
- [ ] Testes manuais passam
- [ ] Browser sem erros
- [ ] Cálculos corretos

---

## 📝 TEMPLATE: COMENTÁRIO DE CODE REVIEW

```markdown
## Code Review: [Nome da Feature]

### ✅ Aprovações
- [x] Parâmetros validados
- [x] Arrays reindexados
- [x] Segurança OK

### ⚠️ Observações
- Verificar cálculos em caso de X
- Documentar nova função em GUIA_DESENVOLVIMENTO.md

### 🔴 Rejeitado por
- Parâmetro [X] incorreto
- Falta validação de [Y]
- JSON retorna inválido

---

Solicitação: Corrigir itens 🔴 e reenviar para review.
```

---

## 🔗 REFERÊNCIAS RÁPIDAS

- [GUIA_DESENVOLVIMENTO.md](GUIA_DESENVOLVIMENTO.md) - Completo
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Rápido
- [ARQUITETURA_NAVEGACAO.md](ARQUITETURA_NAVEGACAO.md) - Navegação
- [README.md](README.md) - Setup inicial

---

**Tempo Total de Review:** ~90 minutos
**Criticidade:** ⚠️ Usar SEMPRE antes de merge!
**Última atualização:** 05/05/2026

