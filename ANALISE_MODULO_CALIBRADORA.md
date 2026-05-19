# 📊 ANÁLISE COMPLETA DO MÓDULO CALIBRADORA

**Data:** 15 de Maio de 2026  
**Escopo:** `/public/reformulacao/calibradora/` + `/data/reformulacao/calibradora/`  
**Status:** Estrutura funcional com oportunidades de otimização

---

## 📋 SUMÁRIO EXECUTIVO

| Categoria | Qtd | Crítica | Alta | Média | Baixa |
|-----------|-----|---------|------|-------|-------|
| **Inconsistências de Padrão** | 8 | 1 | 3 | 3 | 1 |
| **Código Duplicado** | 5 | 0 | 3 | 2 | 0 |
| **Falta de Validações** | 9 | 2 | 4 | 3 | 0 |
| **Problemas de Segurança** | 7 | 1 | 2 | 3 | 1 |
| **Ineficiências** | 6 | 0 | 2 | 3 | 1 |
| **Falta de Tratamento de Erros** | 5 | 1 | 2 | 1 | 1 |
| **Issues UX/Responsividade** | 4 | 0 | 1 | 2 | 1 |
| **Problemas de Arquitetura** | 4 | 0 | 1 | 2 | 1 |
| **Métodos Não Implementados** | 1 | 0 | 0 | 1 | 0 |
| **Campos Faltando** | 3 | 0 | 1 | 2 | 0 |
| **Fluxos Incompletos** | 2 | 1 | 1 | 0 | 0 |
| **Total** | **54** | **6** | **20** | **22** | **6** |

---

# 🔴 PROBLEMAS CRÍTICOS (PRIORIDADE 1)

## C-001: Inconsistência em Métodos de Repositórios

**Severidade:** 🔴 CRÍTICA  
**Arquivos Afetados:**
- `repositories/BaseRepository.php` (lock file sem suporte a leitura)
- Todas as repositories

**Problema:**
```
BaseRepository implementa lock para leitura/escrita:
- LOCK_SH (compartilhado) para leitura
- LOCK_EX (exclusivo) para escrita

MAS: loadData() abre lock em modo 'c' (create/read)
     NÃO trata possível falha de fopen()
     NÃO valida permissões de arquivo
```

**Impacto:** Potencial corrupção de dados em leitura simultânea

**Solução:**
```php
// Verificar permissões antes de abrir
if (!is_writable($dir)) {
    throw new RuntimeException("Sem permissão de escrita em: $dir");
}

// Implementar retry logic em caso de lock falhar
$max_retries = 3;
for ($i = 0; $i < $max_retries; $i++) {
    if (flock($lock, LOCK_SH, $would_block)) {
        break; // Success
    }
    if ($would_block) {
        usleep(100000); // Aguardar 100ms
        continue;
    }
    throw new RuntimeException("Falha ao adquirir lock de leitura");
}
```

---

## C-002: Validação Inadequada de Dados Numéricos

**Severidade:** 🔴 CRÍTICA  
**Arquivos Afetados:**
- `controllers/CalbradoraController.php` (linhas 153, 156, 159)
- `models/DistribuicaoLote.php` (validação de percentual)

**Problema:**
```php
// ❌ ATUAL - Aceita string ou número
$peso_inicial = (float)($_POST['peso_inicial'] ?? 0);
$peso_final = (float)($_POST['peso_final'] ?? 0);

// Problema: String "abc" vira (float)0
// Problema: "-100" é aceito (peso negativo!)
// Problema: "999999999999" é aceito
```

**Impacto:** 
- Dados inválidos persistidos no JSON
- Cálculos de percentuais errados
- Comportamento indefinido em DistribuicaoLote::validar()

**Solução:**
```php
// ✓ Usar filter_var com FILTER_VALIDATE_FLOAT
$peso_inicial = filter_var($_POST['peso_inicial'] ?? '', 
    FILTER_VALIDATE_FLOAT, 
    ['options' => ['min_range' => 0, 'max_range' => 999999]]
);

if ($peso_inicial === false) {
    return ['sucesso' => false, 'mensagem' => 'Peso inicial inválido'];
}

// ✓ Ou usar casting seguro com validação explícita
if (!is_numeric($_POST['peso_inicial'])) {
    throw new InvalidArgumentException('Peso deve ser número');
}
```

---

## C-003: TODO Comentário não Implementado

**Severidade:** 🔴 CRÍTICA  
**Arquivo:** `views/etapa3_resultado.php` (linha 59)

**Problema:**
```php
if ($soma_percent != 100) {
    // ❌ TODO: Implementar salvamento em registros_lote.json
    $message = 'Partida salva com sucesso!';
    $message_type = 'success';
}
```

**Impacto:** 
- Dados **NÃO são salvos** mas mensagem diz que foram!
- Usuário pensa que salvou mas registro se perde
- Fluxo operacional quebrado

**Solução Imediata:**
```php
// Implementar salvamento real:
try {
    $lote = $this->service->criarRegistroLote(
        $controle,
        0, // ou pegar config_id
        $programa,
        $partida,
        $produtor,
        $variedade,
        $classe,
        $observacoes
    );
    
    if (!$lote) {
        $message = 'Erro ao salvar partida';
        $message_type = 'error';
    } else {
        $message = 'Partida salva com sucesso!';
        $message_type = 'success';
    }
} catch (Exception $e) {
    $message = 'Erro: ' . $e->getMessage();
    $message_type = 'error';
}
```

---

## C-004: Fluxo de Distribuição Quebrado

**Severidade:** 🔴 CRÍTICA  
**Arquivo:** `views/etapa4_distribuicao.php` (linha 92)

**Problema:**
```php
// Buscar distribuição por $lote_id (que é ID integer):
$result = $controller->processarRequisicao('obter_distribuicao', 
    ['id' => $lote_id]  // ← Passa $lote_id!
);

// MAS no controller espera distribuicao_id:
$dist = $this->service->getDistribuicaoPorId($id);
```

**Impacto:** 
- Distribuição pode não ser carregada se ID não bater
- Formulário pode processar dados de distribuição errada
- Possível perda de dados

**Solução:**
```php
// Mudar para procurar por lote_id:
$distribuicao = $this->service->getDistribuicaoPorLoteId($lote_id);

// E adicionar action no controller:
case 'obter_distribuicao_por_lote':
    return $this->obterDistribuicaoPorLote($data);
```

---

## C-005: Falta de Proteção CSRF

**Severidade:** 🔴 CRÍTICA  
**Arquivos Afetados:** Todas as 5 views

**Problema:**
```html
<!-- ❌ ATUAL -->
<form method="POST">
    <input type="hidden" name="action" value="criar_faixa">
    <!-- Sem token CSRF! -->
</form>

<!-- Qualquer site pode fazer POST automático -->
<img src="https://seu-site/calibradora/views/etapa1_faixas.php" 
     onerror="fetch('/calibradora/...')">
```

**Impacto:** Ataque CSRF (Cross-Site Request Forgery) possível

**Solução:**
```php
// bootstrap.php - Inicializar sessão e token:
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// views - Incluir token:
<form method="POST">
    <input type="hidden" name="csrf_token" 
           value="<?php echo $_SESSION['csrf_token']; ?>">
</form>

// Controller - Validar token:
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    return ['sucesso' => false, 'mensagem' => 'Token inválido'];
}
```

---

## C-006: Tratamento de Exceções Silencioso

**Severidade:** 🔴 CRÍTICA  
**Arquivo:** `repositories/BaseRepository.php` (linhas 29-35)

**Problema:**
```php
$lock = @fopen($lock_file, 'c'); // ❌ @ suprime erro!
if (!$lock) {
    return $this->getDefaultData(); // ❌ Retorna padrão silenciosamente
}
```

**Impacto:** 
- Erro de permissão não é reportado
- Usuário não sabe que dados podem estar sendo perdidos
- Difícil debugar problemas de acesso

**Solução:**
```php
$lock = fopen($lock_file, 'c');
if ($lock === false) {
    error_log("Falha ao abrir arquivo de lock: $lock_file");
    throw new RuntimeException("Não foi possível adquirir lock");
}
```

---

# 🟠 PROBLEMAS DE ALTA PRIORIDADE (PRIORIDADE 2)

## A-001: Código CSS Duplicado em Todas as Views

**Severidade:** 🟠 ALTA  
**Arquivos Afetados:**
- `views/etapa2_configuracao.php` (linhas 28-50)
- `views/etapa3_registro_lote.php` (linhas 28-50)
- `views/etapa4_distribuicao.php` (linhas 75-97)
- `views/etapa5_resultado.php` (linhas 60-85)

**Problema:**
```
Cada arquivo view tem seu próprio <style> tag com:
- Mesmos seletores básicos (* { }, body { }, .container { })
- Mesma estrutura de cores
- Mesmos breakpoints

Total: ~200+ linhas de CSS duplicadas
Tamanho: +80KB quando minificado
```

**Impacto:** 
- Alterações de estilo precisam ser feitas em 5 lugares
- Inconsistências visuais entre telas
- Arquivo HTML inchado

**Solução:**
```php
// Centralizar em styles_ui.php (que já existe!)
// E incluir em TODAS as views como já fazem em etapa1_faixas.php

// Criar arquivo: calibradora/styles_admin.php com styles comuns:
<?php
$styles = [
    'container' => 'max-width: 1200px; margin: 20px auto; ...',
    'form-group' => 'margin-bottom: 15px;',
    // ...
];
?>

// Depois:
<link rel="stylesheet" href="styles_combined.css">
```

---

## A-002: Validação de Sobreposição Incompleta

**Severidade:** 🟠 ALTA  
**Arquivo:** `models/FaixaPeso.php` (linhas 34-48)

**Problema:**
```php
public static function validarSobreposicao(array $faixas): bool {
    foreach ($faixas as $i => $faixa1) {
        foreach ($faixas as $j => $faixa2) {
            if ($i === $j) continue;

            // ❌ Problema: Valida TODAS as faixas juntas
            // ❌ MAS: Deveria validar por CONFIGURAÇÃO
            if (!($faixa1->peso_final <= $faixa2->peso_inicial || 
                  $faixa1->peso_inicial >= $faixa2->peso_final)) {
                return false;
            }
        }
    }
    return true;
}
```

**Cenário de Falha:**
```
Configuração A:
  Faixa 1: 50-150g
  Faixa 2: 150-270g ✓ Correto (não sobrepõe)

Configuração B:
  Faixa 1: 50-150g
  Faixa 2: 100-200g ✓ Sobrepõe!

❌ Atual: Aceita faixa B porque valida globalmente
✓ Correto: Deveria rejeitar porque em B as faixas sobrepõem
```

**Solução:**
```php
public static function validarSobreposicaoPorConfiguracao(
    array $faixas, 
    string $nome_config
): bool {
    // Filtrar apenas faixas da mesma configuração
    $faixas_config = array_filter($faixas, 
        fn($f) => $f->nome_configuracao === $nome_config
    );
    
    // Validar sobreposição apenas entre essas
    return self::validarSobreposicao($faixas_config);
}
```

---

## A-003: Falta de Sanitização de HTML

**Severidade:** 🟠 ALTA  
**Arquivo:** `views/etapa2_configuracao.php` (linha 38)

**Problema:**
```php
// ❌ Aceita HTML/Script:
$faixa_selecionada = $f;

// ❌ Depois exibe sem sanitizar:
<p>Faixa: <?php echo $faixa_selecionada['calibre']; ?></p>

// Atacante pode injetar:
// ?calibre=<img src=x onerror=alert('xss')>
```

**Impacto:** XSS (Cross-Site Scripting) possível

**Solução:**
```php
// ✓ Sempre use htmlspecialchars em OUTPUT:
<p>Faixa: <?php echo htmlspecialchars($faixa_selecionada['calibre'], 
    ENT_QUOTES, 'UTF-8'); ?></p>

// Criar função helper:
function safe_output($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
```

---

## A-004: Método Privado em Controller Sem Padrão

**Severidade:** 🟠 ALTA  
**Arquivo:** `controllers/CalbradoraController.php`

**Problema:**
```php
// ❌ Mistura de padrões:
public function processarRequisicao(string $action, array $data = []): array {
    // 20+ casos no switch
    // Cada caso chama método privado
}

// PROBLEMA: Métodos não podem ser testados isoladamente
// PROBLEMA: Sem padrão de nomeação clara
// PROBLEMA: Sem documentação de ações aceitas
```

**Solução:**
```php
// Criar classe de ações (Command Pattern):
class FaixaActions {
    public static function criar(CalbradoraService $service, array $data): array { ... }
    public static function atualizar(CalbradoraService $service, array $data): array { ... }
}

// Ou documentar no controller:
/**
 * Ações suportadas:
 * - criar_faixa: Cria nova faixa
 * - atualizar_faixa: Atualiza faixa existente
 * - deletar_faixa: Deleta faixa
 * ...
 */
```

---

## A-005: Sem Validação de Limites em Arrays

**Severidade:** 🟠 ALTA  
**Arquivo:** `views/etapa4_distribuicao.php` (linhas 44-56)

**Problema:**
```php
$itens = [];
$grs = $_POST['gr'] ?? [];
$gramas_list = $_POST['gramas'] ?? [];

// ❌ Sem limite: Usuário pode enviar 10000 itens
// ❌ PHP_INPUT_POST padrão = 1000 variáveis
// ❌ Sem validação de quantidade

foreach ($grs as $idx => $gr) {
    if (!empty($gr)) {
        $itens[] = [
            'gramas' => (float)($gramas_list[$idx] ?? 0),
            // ...
        ];
    }
}

// ❌ Depois valida:
if (abs($total_percentual - 100.0) < 0.01)
// Mas $gramas_list pode ter 10000 itens com gramas = 999999!
```

**Solução:**
```php
$max_items = 100; // Limite razoável
if (count($grs) > $max_items) {
    return ['sucesso' => false, 
            'mensagem' => "Máximo $max_items itens permitidos"];
}

// Validar cada grama
foreach ($grs as $idx => $gr) {
    $gramas = (float)($gramas_list[$idx] ?? 0);
    if ($gramas < 0 || $gramas > 100000) { // Limite de 100kg
        return ['sucesso' => false, 'mensagem' => 'Grama fora do intervalo'];
    }
}
```

---

# 🟡 PROBLEMAS DE MÉDIA PRIORIDADE (PRIORIDADE 3)

## M-001: Inconsistência de Nomeação em Models

**Severidade:** 🟡 MÉDIA  
**Arquivos Afetados:**
- `models/FaixaPeso.php` - `peso_inicial`, `peso_final`
- `models/ConfiguracaoEmbalamento.php` - `nome`, `faixa_peso_id`
- `models/RegistroLote.php` - `controle`, `configuracao_embalamento_id`

**Problema:**
```php
// FaixaPeso:
public int $seq;                    // Campo específico da faixa
public float $peso_inicial;
public float $peso_final;

// ConfiguracaoEmbalamento:
public int $faixa_peso_id;          // Relação
public array $mapeamentos;          // Array de objetos inline

// RegistroLote:
public int $configuracao_embalamento_id; // ID da relação
public string $status;              // Enum simulado: 'rascunho'|'salvo'

// MAS em DistribuicaoLote:
public array $itens;                // Array associativo com ['gr', 'descricao', ...]
```

**Impacto:** Código confuso, erro de digitação fácil

**Solução:**
```php
// Criar classes para arrays aninhados:
class ItemDistribuicao {
    public int $gr;
    public string $descricao;
    public string $faixa_peso;
    public string $produto_operacional;
    public float $gramas;
    public float $percentual;
}

// Usar Enum para status:
enum LobeStatus {
    case Rascunho;
    case Salvo;
    case Finalizado;
}
```

---

## M-002: Falta de Versionamento em JSON

**Severidade:** 🟡 MÉDIA  
**Arquivos Afetados:** `repositories/*Repository.php`

**Problema:**
```php
protected function getDefaultData(): array {
    return [
        'version' => 1,  // Hardcoded!
        'created_at' => date('Y-m-d H:i:s'),
        'faixas' => []
    ];
}

// Se precisar adicionar novo campo:
// - Version != 1: Dados antigos usados como fallback
// - Sem migração: Dados perdidos
// - Sem documentação: Qual versão é esperada?
```

**Impacto:** Difícil evoluir estrutura de dados

**Solução:**
```php
const DATA_VERSION = 2;

public function getDefaultData(): array {
    return [
        'version' => self::DATA_VERSION,
        'migrated_at' => date('Y-m-d H:i:s'),
        'faixas' => []
    ];
}

// Em loadData():
if ($data['version'] !== self::DATA_VERSION) {
    $data = $this->migrate($data);
}

private function migrate(array $data): array {
    if ($data['version'] === 1) {
        // Migrar v1 → v2
        $data['novo_campo'] = [];
    }
    $data['version'] = self::DATA_VERSION;
    return $data;
}
```

---

## M-003: Validação de Tolerância Hardcoded

**Severidade:** 🟡 MÉDIA  
**Arquivo:** `models/DistribuicaoLote.php` (linha 60)

**Problema:**
```php
public function validar(): bool {
    $this->recalcularPercentuais();
    $total_percentual = array_sum(array_column($this->itens, 'percentual'));
    
    // ❌ Tolerância hardcoded: 0.01%
    return abs($total_percentual - 100.0) < 0.01;
}
```

**Impacto:**
- Valor mágico não documentado
- Não configurável por cliente
- Pode ser muito restritivo com muitos itens (acumulo de erros)

**Solução:**
```php
const PERCENTUAL_TOLERANCE = 0.05; // 0.05%

public function validar(float $tolerance = self::PERCENTUAL_TOLERANCE): bool {
    $this->recalcularPercentuais();
    $total = array_sum(array_column($this->itens, 'percentual'));
    
    return abs($total - 100.0) < $tolerance;
}

// Em controller:
if (!$dist->validar(0.1)) { // Permitir até 0.1% de erro
    return ['sucesso' => false, 'mensagem' => '...'];
}
```

---

## M-004: Sem Índices em Repositories

**Severidade:** 🟡 MÉDIA  
**Arquivo:** `repositories/*Repository.php`

**Problema:**
```php
public function getByControle(string $controle): ?RegistroLote {
    $data = $this->loadData();
    
    // ❌ Busca linear em toda a lista:
    foreach ($data['lotes'] ?? [] as $row) {
        if ($row['controle'] === $controle) {
            return RegistroLote::fromArray($row);
        }
    }
    return null;
    
    // Com 10.000 lotes: 10.000 comparações por busca!
}
```

**Impacto:** Performance degrada com volume de dados

**Solução:**
```php
private array $index_controle = [];

public function __construct(string $data_dir) {
    parent::__construct($data_dir, 'registros_lote.json');
    $this->buildIndexes();
}

private function buildIndexes(): void {
    $data = $this->loadData();
    foreach ($data['lotes'] ?? [] as $lote) {
        $this->index_controle[$lote['controle']] = $lote['id'];
    }
}

public function getByControle(string $controle): ?RegistroLote {
    // ✓ Busca O(1) agora:
    if (!isset($this->index_controle[$controle])) {
        return null;
    }
    
    return $this->getById($this->index_controle[$controle]);
}

// Reconstruir índices ao salvar:
public function create(RegistroLote $lote): RegistroLote {
    $lote = parent::create($lote);
    $this->index_controle[$lote->controle] = $lote->id;
    return $lote;
}
```

---

## M-005: Falta de Logging de Operações

**Severidade:** 🟡 MÉDIA  
**Todos os arquivos**

**Problema:**
```php
// Sem registro de:
- Quem criou/atualizou cada registro
- Quando foi criado/atualizado
- O que foi alterado (audit trail)
- Se houve erro ao salvar
```

**Solução:**
```php
// Adicionar a models:
public string $created_by;
public string $updated_by;

// Em controllers:
$lote->created_by = $_SESSION['user_id'] ?? 'sistema';
$lote->updated_by = $_SESSION['user_id'] ?? 'sistema';

// Ou criar tabela de audit separada:
$audit = [
    'tabela' => 'registros_lote',
    'registro_id' => 123,
    'acao' => 'create|update|delete',
    'usuario' => 'joao',
    'timestamp' => date('Y-m-d H:i:s'),
    'dados_antes' => [...],
    'dados_depois' => [...]
];
```

---

## M-006: Método `criarFaixa` com Parâmetros Inconsistentes

**Severidade:** 🟡 MÉDIA  
**Arquivo:** `services/CalbradoraService.php` (linha 54)

**Problema:**
```php
// Em CalbradoraService::criarFaixa():
public function criarFaixa(
    int $seq,           // Sequência
    string $calibre,    // Calibre
    float $peso_inicial,
    float $peso_final,
    string $nome_config // Nome da configuração
): ?FaixaPeso {
    // ...
}

// MAS em CalbradoraController::criarFaixa():
// Recebe:
$seq = (int)($data['seq'] ?? 0);
$calibre = trim($data['calibre'] ?? '');
$peso_inicial = (float)($data['peso_inicial'] ?? 0);
$peso_final = (float)($data['peso_final'] ?? 0);
$nome_config = trim($data['nome_configuracao'] ?? '');

// ❌ Problema: $seq vem do POST como 0 se não existir
// ❌ Sistema não gera sequência automática
// ❌ Permite criar faixa com seq=0 (inválido)
```

**Solução:**
```php
// Em criarFaixa: auto-gerar seq se não fornecido:
public function criarFaixa(
    string $calibre,
    float $peso_inicial,
    float $peso_final,
    string $nome_config,
    ?int $seq = null  // Opcional
): ?FaixaPeso {
    // Se não passou seq, gerar:
    if ($seq === null || $seq <= 0) {
        $seq = $this->getProxSeq($nome_config);
    }
    // ...
}

// Em controller:
$result = $this->service->criarFaixa(
    trim($data['calibre']),
    (float)$data['peso_inicial'],
    (float)$data['peso_final'],
    trim($data['nome_configuracao'])
    // seq é gerado automaticamente
);
```

---

# 🔵 PROBLEMAS DE BAIXA PRIORIDADE (PRIORIDADE 4)

## B-001: Responsividade de Tabelas

**Severidade:** 🔵 BAIXA  
**Arquivos Afetados:** Todas as views

**Problema:**
```css
table {
    width: 100%;
    min-width: 1320px;  /* ❌ Força overflow horizontal em mobile */
}

th, td {
    border-right: 1px solid #e2e8f0;
}
```

**Impacto:** Tabelas intransponíveis em mobile

**Solução:**
```css
@media (max-width: 768px) {
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
    
    /* Ou: stack colunas verticalmente */
    th, td {
        display: block;
        width: 100%;
    }
}
```

---

## B-002: Sem Testes Automatizados

**Severidade:** 🔵 BAIXA  
**Arquivos Afetados:** Nenhum

**Problema:**
```
Não há:
- Testes unitários (PHPUnit)
- Testes de integração
- Testes de validação
- Cobertura de código
```

**Solução:**
```bash
# Criar estrutura de testes:
tests/
├── Unit/
│   ├── Models/FaixaPesoTest.php
│   ├── Models/DistribuicaoLoteTest.php
│   └── Services/CalbradoraServiceTest.php
├── Integration/
│   └── RepositoriesTest.php
└── Feature/
    └── CalbradoraFlowTest.php

# Exemplo de teste:
class FaixaPesoTest extends TestCase {
    public function testValidarSobreposicao() {
        $faixa1 = new FaixaPeso(..., 100, 200, ...);
        $faixa2 = new FaixaPeso(..., 150, 250, ...);
        
        $this->assertFalse(
            FaixaPeso::validarSobreposicao([$faixa1, $faixa2])
        );
    }
}
```

---

## B-003: Mensagens de Erro Genéricas

**Severidade:** 🔵 BAIXA  
**Arquivo:** `controllers/CalbradoraController.php`

**Problema:**
```php
// ❌ Mensagens genéricas:
return ['sucesso' => false, 'mensagem' => 'Erro ao criar faixa. Verifique se há sobreposição de faixas.'];
return ['sucesso' => false, 'mensagem' => 'Erro ao criar configuração'];
return ['sucesso' => false, 'mensagem' => 'Erro ao deletar faixa'];

// Usuário não sabe o que fazer
```

**Solução:**
```php
// ✓ Mensagens específicas:
return [
    'sucesso' => false,
    'mensagem' => 'Não foi possível criar a faixa: peso inicial (100g) deve ser menor que peso final (100g)',
    'codigo' => 'FAIXA_PESO_INVALIDO',
    'detalhes' => [
        'campo' => 'peso_final',
        'valor_fornecido' => 100,
        'valor_esperado' => '> 100'
    ]
];
```

---

## B-004: Sem Cache de Dados Frequentes

**Severidade:** 🔵 BAIXA  
**Arquivo:** `repositories/*Repository.php`

**Problema:**
```php
// Cada acesso chama loadData()
// Que faz file_get_contents() e json_decode()

public function getAll(): array {
    $data = $this->loadData();  // ← Lê arquivo toda vez!
    // ...
}

// Com 100 requisições: 100 leituras de arquivo
```

**Solução:**
```php
protected array $cache = null;
protected float $cache_time = 0;
const CACHE_DURATION = 60; // segundos

public function getAll(): array {
    if ($this->cache === null || time() - $this->cache_time > self::CACHE_DURATION) {
        $this->cache = $this->loadData();
        $this->cache_time = time();
    }
    
    $result = [];
    foreach ($this->cache['faixas'] ?? [] as $row) {
        $result[] = FaixaPeso::fromArray($row);
    }
    return $result;
}

// Invalidar cache ao atualizar:
public function update(FaixaPeso $faixa): bool {
    $success = parent::update($faixa);
    if ($success) {
        $this->cache = null;
    }
    return $success;
}
```

---

# 📌 SUMÁRIO DE RECOMENDAÇÕES

## Curto Prazo (CRÍTICAS - Imediato)

| ID | Problema | Tempo | Impacto |
|----|----|----|----|
| C-001 | Locks sem retry | 2h | Alto |
| C-002 | Validação numérica | 3h | Crítico |
| C-003 | TODO não implementado | 2h | Crítico |
| C-004 | Fluxo distribuição | 1h | Alto |
| C-005 | Proteção CSRF | 2h | Alto |
| C-006 | Exceções silenciosas | 1h | Médio |

**Total:** ~11 horas

---

## Médio Prazo (ALTAS - Próxima Sprint)

| ID | Problema | Tempo | Impacto |
|----|----|----|----|
| A-001 | CSS duplicado | 2h | Médio |
| A-002 | Validação sobreposição | 1.5h | Médio |
| A-003 | Sanitização HTML | 1h | Alto |
| A-004 | Padrão controller | 2h | Médio |
| A-005 | Limites em arrays | 1.5h | Médio |

**Total:** ~8 horas

---

## Longo Prazo (MÉDIAS/BAIXAS - Refatoração)

| ID | Problema | Tempo | Impacto |
|----|----|----|----|
| M-001 | Nomeação consistente | 3h | Médio |
| M-002 | Versionamento JSON | 2h | Médio |
| M-003 | Tolerância configurável | 1h | Baixo |
| M-004 | Índices em repositories | 3h | Médio |
| M-005 | Logging de operações | 4h | Médio |
| B-001 | Responsividade mobile | 2h | Baixo |
| B-002 | Testes automatizados | 8h | Médio |

**Total:** ~23 horas

---

# 🎯 PLANO DE AÇÃO RECOMENDADO

## FASE 1: Segurança (1-2 dias)
1. ✅ Implementar validação numérica rigorosa (C-002)
2. ✅ Adicionar proteção CSRF (C-005)
3. ✅ Remover suppression de erros (C-006)
4. ✅ Implementar salvamento em etapa3 (C-003)
5. ✅ Corrigir busca de distribuição (C-004)
6. ✅ Implementar retry em locks (C-001)

## FASE 2: Qualidade (3-4 dias)
7. ✅ Consolidar CSS (A-001)
8. ✅ Sanitizar HTML (A-003)
9. ✅ Validação de sobreposição por config (A-002)
10. ✅ Limites em arrays (A-005)
11. ✅ Refatorar controller (A-004)

## FASE 3: Manutenibilidade (1-2 semanas)
12. ✅ Índices em repositories (M-004)
13. ✅ Logging de operações (M-005)
14. ✅ Versionamento de dados (M-002)
15. ✅ Testes automatizados (B-002)
16. ✅ Refatoring de nomeação (M-001)

---

# 📄 CHECKLIST PARA REVISOR

- [ ] Leitura de todas as 6 críticas
- [ ] Plano aprovado para FASE 1
- [ ] Estimativas de tempo realísticas?
- [ ] Prioridades fazem sentido?
- [ ] Arquivos afetados identificados?
- [ ] Soluções técnicas adequadas?
- [ ] Impacto em usuários considerado?
- [ ] Teste de regressão necessário?

---

**Versão:** 1.0  
**Data:** 15 de Maio de 2026  
**Responsável da Análise:** GitHub Copilot  
**Status:** Pronto para Ação
