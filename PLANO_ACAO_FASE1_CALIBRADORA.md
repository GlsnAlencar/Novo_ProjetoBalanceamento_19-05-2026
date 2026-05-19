# 🔧 PLANO DE AÇÃO - FASE 1: SEGURANÇA CRÍTICA

**Objetivo:** Corrigir 6 problemas críticos que afetam integridade de dados e segurança.  
**Tempo Estimado:** 11 horas (2-3 dias de desenvolvimento)  
**Prioridade:** 🔴 MÁXIMA

---

## CORREÇÃO 1: C-002 Validação Numérica Inadequada ⏱️ 3h

### Localização
- `public/reformulacao/calibradora/controllers/CalbradoraController.php`
  - Linhas 103-107 (criarFaixa)
  - Linhas 126-130 (atualizarFaixa)
  - Linhas 277 (criarRegistroLote)
  - Linhas 295-298 (atualizarRegistroLote)
  - Linhas 46-49 (etapa4_distribuicao.php)

### Problema Atual
```php
$peso_inicial = (float)($_POST['peso_inicial'] ?? 0);
$peso_final = (float)($_POST['peso_final'] ?? 0);

// ❌ String "abc" vira 0
// ❌ String "-100" vira -100
// ❌ String "999999999" é aceita
```

### Solução 1: Função Helper (Recomendado)

```php
// Adicionar no bootstrap.php após includes:
function safe_float($value, $min = 0, $max = 999999) {
    // Validar tipo
    if (!is_numeric($value)) {
        return false;
    }
    
    // Converter
    $float_val = (float)str_replace(',', '.', $value);
    
    // Validar range
    if ($float_val < $min || $float_val > $max) {
        return false;
    }
    
    return $float_val;
}

function safe_int($value, $min = 0, $max = 999999) {
    if (!is_numeric($value)) {
        return false;
    }
    
    $int_val = (int)$value;
    
    if ($int_val < $min || $int_val > $max) {
        return false;
    }
    
    return $int_val;
}
```

### Solução 2: Implementar em Controller

```php
private function criarFaixa(array $data): array {
    // ✓ Validação segura:
    $seq = safe_int($data['seq'] ?? null, 1, 1000);
    $peso_inicial = safe_float($data['peso_inicial'] ?? null, 0, 999999);
    $peso_final = safe_float($data['peso_final'] ?? null, 1, 999999);
    $calibre = trim($data['calibre'] ?? '');
    $nome_config = trim($data['nome_configuracao'] ?? '');
    
    // ✓ Verificar falhas de validação:
    if ($seq === false) {
        return ['sucesso' => false, 'mensagem' => 'Seq deve ser número entre 1-1000'];
    }
    
    if ($peso_inicial === false) {
        return ['sucesso' => false, 
                'mensagem' => 'Peso inicial deve ser número entre 0-999999'];
    }
    
    if ($peso_final === false) {
        return ['sucesso' => false, 
                'mensagem' => 'Peso final deve ser número entre 1-999999'];
    }
    
    if (empty($calibre)) {
        return ['sucesso' => false, 'mensagem' => 'Calibre é obrigatório'];
    }
    
    if (empty($nome_config)) {
        return ['sucesso' => false, 'mensagem' => 'Configuração é obrigatória'];
    }
    
    // Validar lógica de negócio
    if ($peso_inicial >= $peso_final) {
        return ['sucesso' => false, 
                'mensagem' => "Peso inicial ($peso_inicial) deve ser menor que final ($peso_final)"];
    }
    
    // ... resto da lógica
}
```

### Checklist
- [ ] Criar funções safe_float e safe_int em bootstrap.php
- [ ] Aplicar em TODOS os campos numéricos do controller
- [ ] Testar com valores inválidos (-100, "abc", 999999999)
- [ ] Testar com valores válidos (100, 200.5, "100,5")
- [ ] Verificar mensagens de erro específicas

---

## CORREÇÃO 2: C-003 TODO Não Implementado ⏱️ 2h

### Localização
- `public/reformulacao/calibradora/views/etapa3_resultado.php`
  - Linhas 48-59

### Problema Atual
```php
if ($soma_percent == 100) {
    // ❌ TODO: Implementar salvamento em registros_lote.json
    $message = 'Partida salva com sucesso!';  // Mas NÃO salva!
    $message_type = 'success';
}
```

### Solução

```php
// SUBSTITUIR linhas 48-59 com:

if ($soma_percent != 100) {
    $message = sprintf("Soma dos percentuais é %.1f%% (deve ser 100%%)", $soma_percent);
    $message_type = 'warning';
} else {
    // ✓ Salvar efetivamente
    try {
        // Extrair dados da forma
        $controle = trim($_POST['controle'] ?? '');
        $config = trim($_POST['configuracao'] ?? '');
        $peso_total = safe_float($_POST['peso_total'] ?? null);
        
        // Validar
        if (empty($controle)) {
            throw new Exception('Controle é obrigatório');
        }
        if (empty($config)) {
            throw new Exception('Configuração é obrigatória');
        }
        if ($peso_total === false || $peso_total <= 0) {
            throw new Exception('Peso total deve ser número > 0');
        }
        
        // Buscar ID da configuração
        $result = $controller->processarRequisicao('obter_configuracoes');
        $configs = $result['dados'] ?? [];
        $config_id = null;
        
        foreach ($configs as $cfg) {
            if ($cfg['nome'] === $config) {
                $config_id = $cfg['id'];
                break;
            }
        }
        
        if ($config_id === null) {
            throw new Exception('Configuração não encontrada');
        }
        
        // SALVAR lote
        $result = $controller->processarRequisicao('criar_lote', [
            'controle' => $controle,
            'configuracao_embalamento_id' => $config_id,
            'programa' => trim($_POST['programa'] ?? ''),
            'partida' => trim($_POST['partida'] ?? ''),
            'produtor' => trim($_POST['produtor'] ?? ''),
            'variedade' => trim($_POST['variedade'] ?? ''),
            'classe' => trim($_POST['classe'] ?? ''),
            'observacoes' => trim($_POST['observacoes'] ?? '')
        ]);
        
        if ($result['sucesso']) {
            $lote_id = $result['dados']['id'];
            
            // ✓ Salvar distribuição também
            $dist_result = $controller->processarRequisicao('criar_distribuicao', [
                'lote_id' => $lote_id,
                'configuracao_embalamento_id' => $config_id
            ]);
            
            if ($dist_result['sucesso']) {
                $message = "Partida salva com sucesso! (Controle: $controle)";
                $message_type = 'success';
                
                // Redirecionar para etapa 4 (distribuição)
                header('Location: etapa4_distribuicao.php?lote_id=' . $lote_id);
                exit;
            } else {
                throw new Exception('Erro ao criar distribuição');
            }
        } else {
            throw new Exception($result['mensagem']);
        }
    } catch (Exception $e) {
        $message = 'Erro ao salvar: ' . $e->getMessage();
        $message_type = 'error';
    }
}
```

### Checklist
- [ ] Remover TODO comentário
- [ ] Implementar salvamento real com try/catch
- [ ] Testar salvamento efetivo em JSON
- [ ] Verificar redirect para etapa 4
- [ ] Validar controle duplicado é rejeitado
- [ ] Verificar arquivo data/reformulacao/calibradora/registros_lote.json foi atualizado

---

## CORREÇÃO 3: C-005 Proteção CSRF ⏱️ 2h

### Localização
- Todas as 5 views (`etapa1_faixas.php`, `etapa2_configuracao.php`, etc)
- `bootstrap.php` (inicializar session)

### Passo 1: Inicializar Sessão

```php
// NO INÍCIO do bootstrap.php, ANTES de require_once:
<?php
// Inicializar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gerar token CSRF se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// require_once __DIR__ . '/safe_storage.php';
// ... resto do código
```

### Passo 2: Adicionar Token em Formulários

```php
// EM CADA <form method="POST">:

<form method="POST">
    <!-- ✓ Adicionar campo escondido com token -->
    <input type="hidden" name="csrf_token" 
           value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
    
    <input type="hidden" name="action" value="criar_faixa">
    <!-- resto do formulário -->
</form>
```

### Passo 3: Validar Token no Controller

```php
// NO INÍCIO de CalbradoraController::processarRequisicao():

public function processarRequisicao(string $action, array $data = []): array {
    $response = ['sucesso' => false, 'mensagem' => '', 'dados' => null];

    try {
        // ✓ VALIDAR CSRF ANTES DE QUALQUER AÇÃO
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token_form = $data['csrf_token'] ?? '';
            $token_session = $_SESSION['csrf_token'] ?? '';
            
            if (empty($token_form) || empty($token_session) || 
                !hash_equals($token_form, $token_session)) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Token de segurança inválido. Tente novamente.'
                ];
            }
        }
        
        // Continuar com resto do código...
        switch ($action) {
            // ...
        }
```

### Checklist
- [ ] Sessão iniciada no bootstrap.php
- [ ] Token gerado se não existir
- [ ] Campo csrf_token adicionado em TODOS os formulários
- [ ] Validação CSRF no controller antes de processar
- [ ] Testar com token inválido (deve ser rejeitado)
- [ ] Testar com POST sem token (deve ser rejeitado)
- [ ] Testar com token válido (deve funcionar)

---

## CORREÇÃO 4: C-004 Fluxo de Distribuição ⏱️ 1h

### Localização
- `views/etapa4_distribuicao.php` (linhas 87-95)

### Problema
```php
// Linha 92:
$result = $controller->processarRequisicao('obter_distribuicao', 
    ['id' => $lote_id]  // ❌ Passa $lote_id, não $dist_id
);
```

### Solução 1: Buscar por Lote ID

```php
// SUBSTITUIR linhas 87-95:

// ✓ Buscar distribuição pelo LOTE ID
$distribuicao_selecionada = null;
if (isset($_GET['lote_id'])) {
    $lote_id = (int)$_GET['lote_id'];
    $result = $controller->processarRequisicao('obter_lote', ['id' => $lote_id]);
    if ($result['sucesso']) {
        $lote_selecionado = $result['dados'];
        
        // Buscar distribuição DESTE lote
        // (usando service method que retorna distribuição para um lote)
        $dist_result = $controller->processarRequisicao('obter_distribuicao_por_lote', 
            ['lote_id' => $lote_id]
        );
        
        if ($dist_result['sucesso']) {
            $distribuicao_selecionada = $dist_result['dados'];
        }
        
        // Obter configuração associada
        if ($lote_selecionado['configuracao_embalamento_id'] > 0) {
            $config_result = $controller->processarRequisicao('obter_configuracao', 
                ['id' => $lote_selecionado['configuracao_embalamento_id']]
            );
            if ($config_result['sucesso']) {
                $config_selecionada = $config_result['dados'];
            }
        }
    }
}
```

### Solução 2: Adicionar Ação no Controller

```php
// NO SWITCH do CalbradoraController::processarRequisicao():

case 'obter_distribuicao_por_lote':
    return $this->obterDistribuicaoPorLote($data);

// E implementar método:
private function obterDistribuicaoPorLote(array $data): array {
    $lote_id = (int)($data['lote_id'] ?? 0);
    if ($lote_id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'ID de lote inválido'];
    }
    
    $dist = $this->service->getDistribuicaoPorLoteId($lote_id);
    if (!$dist) {
        return ['sucesso' => false, 'mensagem' => 'Nenhuma distribuição encontrada'];
    }
    
    return ['sucesso' => true, 'dados' => $dist->toArray()];
}
```

### Checklist
- [ ] Mudar busca para usar lote_id
- [ ] Adicionar novo método no controller
- [ ] Testar busca de distribuição por lote
- [ ] Verificar que distribuição correta é carregada
- [ ] Testar com lote_id inválido (deve mostrar erro)

---

## CORREÇÃO 5: C-006 Exceções Silenciosas ⏱️ 1h

### Localização
- `repositories/BaseRepository.php`
  - Linhas 29-31 (loadData)
  - Linhas 50-52 (saveData)

### Problema
```php
// ❌ Atual:
$lock = @fopen($lock_file, 'c');
if (!$lock) {
    return $this->getDefaultData();  // Falha silenciosa!
}
```

### Solução

```php
// SUBSTITUIR no BaseRepository:

protected function loadData(): array {
    $path = $this->getDataPath();
    
    if (!file_exists($path)) {
        return $this->getDefaultData();
    }
    
    // ✓ Validar permissão de leitura
    if (!is_readable($path)) {
        error_log("ERRO: Arquivo não é legível: $path");
        throw new RuntimeException("Arquivo de dados não está acessível para leitura");
    }

    // Lock para leitura
    $lock_file = $path . '.lock';
    $lock = fopen($lock_file, 'c');
    
    // ✓ Sem suprimir erro
    if ($lock === false) {
        error_log("ERRO: Não foi possível adquirir lock de leitura: $lock_file");
        throw new RuntimeException("Falha ao adquirir lock de leitura");
    }
    
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException("Falha ao bloquear arquivo para leitura");
        }
        
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Falha ao ler arquivo de dados");
        }
        
        flock($lock, LOCK_UN);
        
        $data = json_decode($content, true);
        if (!is_array($data)) {
            error_log("AVISO: JSON corrompido em $path, usando padrão");
            return $this->getDefaultData();
        }
        
        return $data;
    } finally {
        @fclose($lock);
    }
}

protected function saveData(array $data): bool {
    $path = $this->getDataPath();

    // ✓ Validar permissão de escrita
    $dir = dirname($path);
    if (!is_writable($dir)) {
        error_log("ERRO: Diretório não é gravável: $dir");
        throw new RuntimeException("Sem permissão de escrita no diretório de dados");
    }

    // Lock para escrita
    $lock_file = $path . '.lock';
    $lock = fopen($lock_file, 'c');
    
    // ✓ Sem suprimir erro
    if ($lock === false) {
        error_log("ERRO: Não foi possível adquirir lock de escrita: $lock_file");
        throw new RuntimeException("Falha ao adquirir lock de escrita");
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException("Falha ao bloquear arquivo para escrita");
        }

        // Codificar JSON
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException("Falha ao serializar JSON: " . json_last_error_msg());
        }

        // Validar JSON gerado
        json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("JSON gerado inválido: " . json_last_error_msg());
        }

        // Escrever em arquivo temporário
        $tmp = tempnam($dir, '.tmp_json_');
        if ($tmp === false) {
            throw new RuntimeException("Falha ao criar arquivo temporário");
        }

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException("Falha ao gravar arquivo temporário");
        }

        // Backup se arquivo existe
        if (file_exists($path) && filesize($path) > 0) {
            $backup_path = dirname($path) . '/_backups/' . 
                          basename($path) . '_' . 
                          date('Ymd_His') . '_' . 
                          random_int(1000, 9999) . '.json';
            
            @mkdir(dirname($backup_path), 0755, true);
            @copy($path, $backup_path);
        }

        // Substituir arquivo oficial
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Falha ao substituir arquivo oficial");
        }

        return true;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}
```

### Checklist
- [ ] Remover todos os @ (error suppression operators)
- [ ] Usar try/finally para garantir limpeza
- [ ] Adicionar error_log() para debugging
- [ ] Testar com permissões incorretas
- [ ] Verificar que exceções são lançadas corretamente

---

## CORREÇÃO 6: C-001 Locks sem Retry ⏱️ 2h

### Localização
- `repositories/BaseRepository.php` (loadData e saveData)

### Problema
```php
// Sem retry: Se lock falhar, perde dados
if (!flock($lock, LOCK_SH)) {
    // Falha imediata, sem retry
}
```

### Solução

```php
// Adicionar métodos auxiliares no BaseRepository:

/**
 * Tentar adquirir lock com retry
 */
private function acquireLock($resource, int $type, int $max_retries = 3): bool {
    for ($attempt = 0; $attempt < $max_retries; $attempt++) {
        if (@flock($resource, $type | LOCK_NB)) {  // LOCK_NB = non-blocking
            return true;
        }
        
        // Se falhou, aguardar e tentar novamente
        if ($attempt < $max_retries - 1) {
            usleep(100000 * ($attempt + 1)); // 100ms, 200ms, 300ms
        }
    }
    
    return false;
}

// Usar em loadData():
if (!flock($lock, LOCK_SH)) {
    if (!$this->acquireLock($lock, LOCK_SH)) {
        throw new RuntimeException("Timeout ao adquirir lock de leitura após 3 tentativas");
    }
}

// Usar em saveData():
if (!flock($lock, LOCK_EX)) {
    if (!$this->acquireLock($lock, LOCK_EX)) {
        throw new RuntimeException("Timeout ao adquirir lock de escrita após 3 tentativas");
    }
}
```

### Checklist
- [ ] Implementar acquireLock() com retry logic
- [ ] Testar com múltiplas requisições simultâneas
- [ ] Verificar que retry aguarda corretamente
- [ ] Testar timeout após 3 tentativas
- [ ] Monitorar performance (retries não devem ser lentos)

---

## 🧪 TESTE FINAL - Validação Completa

Após implementar todas as 6 correções:

```bash
# 1. Syntax check
php -l public/reformulacao/calibradora/bootstrap.php
php -l public/reformulacao/calibradora/controllers/CalbradoraController.php
php -l public/reformulacao/calibradora/repositories/BaseRepository.php

# 2. Testar em browser
# http://localhost/reformulacao/calibradora/views/etapa1_faixas.php
# - Criar faixa com seq=0 (deve ser rejeitado)
# - Criar faixa com peso_inicial=-100 (deve ser rejeitado)
# - Criar faixa com peso_inicial="abc" (deve ser rejeitado)
# - Criar faixa válida (deve funcionar)

# 3. Testar CSRF
# - Fazer POST sem csrf_token (deve ser rejeitado)
# - Fazer POST com token inválido (deve ser rejeitado)
# - Fazer POST com token válido (deve funcionar)

# 4. Testar etapa3 (salvamento)
# - Inserir dados de partida
# - Verificar que registros_lote.json foi criado
# - Verificar que distribuicoes_lote.json foi criado

# 5. Testar locks e concorrência
# - Abrir 2 browsers e tentar salvar simultaneamente
# - Não deve haver corrupção de dados
```

---

## ✅ Checklist Final FASE 1

**ANTES de dar como concluído:**

- [ ] C-001: Locks têm retry logic implementado
- [ ] C-002: safe_float() e safe_int() em bootstrap.php
- [ ] C-002: Validação em todos os campos numéricos do controller
- [ ] C-003: TODO removido, salvamento implementado em etapa3
- [ ] C-003: Testes confirmam dados salvos em JSON
- [ ] C-004: etapa4 busca distribuição por lote_id
- [ ] C-005: CSRF token implementado em TODAS as 5 views
- [ ] C-005: Validação CSRF no controller
- [ ] C-005: Testes confirmam rejeição de tokens inválidos
- [ ] C-006: Todas as exceções são lançadas (não silenciosas)
- [ ] C-006: error_log() registra erros para debugging
- [ ] Syntax check passou em todos os arquivos
- [ ] Testes manuais completados
- [ ] Sem quebra de funcionalidades existentes
- [ ] Pronto para deploy

---

**Responsável:** [Nome do desenvolvedor]  
**Data Início:** [Data]  
**Data Conclusão:** [Data]  
**Revisado Por:** [Nome do revisor]
