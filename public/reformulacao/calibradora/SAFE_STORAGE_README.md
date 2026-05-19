# safe_storage.php - Módulo Calibradora

## 📦 O que é?

Arquivo de persistência **completamente isolado** para o módulo Calibradora. Fornece funções seguras de leitura/escrita de JSON com backup automático.

## 🎯 Por que isolado?

- ✅ **Independência total** do legado
- ✅ **Sem dependências externas** (exceto PHP padrão)
- ✅ **Backup automático** de cada gravação
- ✅ **Lock para evitar conflitos** (gravações simultâneas)
- ✅ **Validação de JSON** antes de sobrescrever
- ✅ **Fallback para padrão** se arquivo corromper

## 📋 Funções Disponíveis

### Leitura Segura
```php
$data = calibradora_safe_load_json(
    $path,                    // Caminho do arquivo JSON
    $default_factory,         // Callable que retorna dados padrão
    $validator                // Callable que valida a estrutura
);
```

**Exemplo:**
```php
$faixas = calibradora_safe_load_json(
    __DIR__ . '/data/faixas.json',
    fn() => ['faixas' => []],
    fn($data) => isset($data['faixas']) && is_array($data['faixas'])
);
```

### Escrita Segura
```php
calibradora_safe_write_json($path, $data);
```

**Exemplo:**
```php
$data = ['faixas' => [...]];
calibradora_safe_write_json(__DIR__ . '/data/faixas.json', $data);
```

### Validadores de Tipo
```php
calibradora_number($value, $default)      // Float com fallback
calibradora_int($value, $default)         // Integer com fallback
calibradora_string($value, $default)      // String trimmed com fallback
```

### Validadores de Estrutura
```php
calibradora_validate_faixa($data)         // Valida FaixaPeso
calibradora_validate_configuracao($data)  // Valida ConfiguracaoEmbalamento
calibradora_validate_lote($data)          // Valida RegistroLote
calibradora_validate_distribuicao($data)  // Valida DistribuicaoLote
```

## 🔒 Segurança

### Backup Automático
Toda gravação que sobrescreve um arquivo existente cria backup:
```
data/reformulacao/calibradora/
├── faixas.json
└── _backups/
    ├── faixas.json_20260514_143022_5847.json
    ├── faixas.json_20260514_143055_2934.json
    └── ...
```

### Lock de Escrita
Usa `.lock` file para evitar que duas gravações aconteçam simultaneamente.

### Validação de JSON
1. Encoda para JSON
2. Valida se JSON é válido
3. Escreve em arquivo temporário
4. Valida arquivo temporário
5. Substitui arquivo original apenas se tudo OK

Se algo falhar:
- Arquivo temporário é deletado
- Arquivo original permanece intacto
- Exceção é lançada

## 📁 Estrutura de Dados

Os dados são armazenados em:
```
/data/reformulacao/calibradora/
├── faixas.json
├── configuracoes.json
├── lotes.json
├── distribuicoes.json
└── _backups/
    ├── (backups automáticos)
```

## 🔗 Como Usar

### Em um Controller/Repository
```php
<?php
require_once __DIR__ . '/safe_storage.php';

class FaixaPesoRepository {
    private string $data_path;
    
    public function __construct(string $data_dir) {
        $this->data_path = $data_dir . '/faixas.json';
    }
    
    public function getAll(): array {
        return calibradora_safe_load_json(
            $this->data_path,
            fn() => ['version' => 1, 'faixas' => []],
            fn($data) => isset($data['faixas']) && is_array($data['faixas'])
        );
    }
    
    public function save(array $data): void {
        calibradora_safe_write_json($this->data_path, $data);
    }
}
```

### Em uma View
```php
<?php
require_once __DIR__ . '/../safe_storage.php';

$data_dir = __DIR__ . '/../../../../data/reformulacao/calibradora';
$faixas_data = calibradora_safe_load_json(
    $data_dir . '/faixas.json',
    fn() => ['version' => 1, 'faixas' => []],
    fn($data) => isset($data['faixas'])
);

$faixas = $faixas_data['faixas'] ?? [];
?>
```

## ✅ Quando Funciona

- ✅ Arquivo não existe (cria com padrão)
- ✅ Arquivo existe e é válido (lê normalmente)
- ✅ Arquivo existe mas é inválido (recupera do backup)
- ✅ Múltiplas gravações (lock evita conflitos)
- ✅ Servidor cai durante escrita (arquivo temporário evita corrupção)

## ❌ Quando Falha

- ❌ Permissão insuficiente no diretório
- ❌ Disco cheio
- ❌ Caracteres não-UTF8 nos dados
- ❌ JSON inválido após tratamento

## 📊 Performance

- Leitura: O(1) - um file_get_contents
- Escrita: O(1) - validação + um rename atômico
- Backup: O(1) - um copy em primeiro plano

Para grandes volumes, considere migrar para banco de dados.

## 🔄 Diferenças do safe_storage.php Legado

| Aspecto | Legado | Isolado |
|---------|--------|---------|
| Prefixo | `cod_12_05_` | `calibradora_` |
| Localização | `/public/reformulacao/` | `/public/reformulacao/calibradora/` |
| Dependência | Múltiplos módulos | Apenas Calibradora |
| Validadores | Genéricos | Específicos |
| Modificação | Afeta todo sistema | Afeta apenas Calibradora |

## 📌 Resumo

Este arquivo é a **espinha dorsal** do isolamento do módulo Calibradora. Qualquer modificação aqui afeta apenas Calibradora, nunca o sistema legado.
