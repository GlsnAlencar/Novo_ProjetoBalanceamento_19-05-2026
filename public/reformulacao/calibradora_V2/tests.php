<?php
/**
 * Testes Unitários do Módulo Calibradora
 * 
 * Execute via CLI: php tests.php
 */

require_once __DIR__ . '/services/CalbradoraService.php';
require_once __DIR__ . '/models/FaixaPeso.php';
require_once __DIR__ . '/models/ConfiguracaoEmbalamento.php';
require_once __DIR__ . '/models/RegistroLote.php';
require_once __DIR__ . '/models/DistribuicaoLote.php';
require_once __DIR__ . '/repositories/FaixaPesoRepository.php';
require_once __DIR__ . '/repositories/ConfiguracaoEmbalamentoRepository.php';
require_once __DIR__ . '/repositories/RegistroLoteRepository.php';
require_once __DIR__ . '/repositories/DistribuicaoLoteRepository.php';

use CalbradoraModule\Services\CalbradoraService;
use CalbradoraModule\Models\FaixaPeso;

class CalbradoraTester {
    private CalbradoraService $service;
    private int $test_count = 0;
    private int $pass_count = 0;

    public function __construct() {
        $data_dir = __DIR__ . '/../../../data/reformulacao/calibradora';
        $this->service = new CalbradoraService($data_dir);
    }

    public function runAllTests() {
        echo "\n=== TESTES DO MÓDULO CALIBRADORA ===\n\n";

        $this->testFaixaPeso();
        $this->testConfiguracao();
        $this->testRegistroLote();
        $this->testDistribuicao();
        $this->testResultado();

        echo "\n=== RESUMO ===\n";
        echo "Total de testes: " . $this->test_count . "\n";
        echo "Passou: " . $this->pass_count . "\n";
        echo "Falhou: " . ($this->test_count - $this->pass_count) . "\n";
        echo "\n";
    }

    private function test(string $name, bool $result) {
        $this->test_count++;
        $status = $result ? "✓ PASSOU" : "✗ FALHOU";
        echo "  [$status] $name\n";
        if ($result) {
            $this->pass_count++;
        }
    }

    private function testFaixaPeso() {
        echo "Testando Faixa de Peso...\n";

        // Teste 1: Criar faixa
        $faixa = $this->service->criarFaixa('TEST', 100, 200, 'TestConfig');
        $this->test("Criar faixa com sucesso", $faixa !== null);

        // Teste 2: Validar sobreposição
        $faixa_sobreposta = $this->service->criarFaixa('TEST2', 150, 250, 'TestConfig');
        $this->test("Detectar sobreposição", $faixa_sobreposta === null);

        // Teste 3: Obter faixa por ID
        if ($faixa) {
            $retrieved = $this->service->getFaixaPorId($faixa->id);
            $this->test("Obter faixa por ID", $retrieved !== null && $retrieved->id === $faixa->id);
        }

        // Teste 4: Listar faixas
        $faixas = $this->service->getFaixas();
        $this->test("Listar faixas", is_array($faixas) && count($faixas) > 0);
    }

    private function testConfiguracao() {
        echo "\nTestando Configuração de Embalamento...\n";

        // Teste 1: Criar configuração
        $config = $this->service->criarConfiguracao('TestConfig', 1);
        $this->test("Criar configuração", $config !== null);

        // Teste 2: Obter configuração
        if ($config) {
            $retrieved = $this->service->getConfiguracaoPorId($config->id);
            $this->test("Obter configuração por ID", $retrieved !== null);
        }

        // Teste 3: Validar dados inválidos
        $invalid = $this->service->criarConfiguracao('', 0);
        $this->test("Rejeitar configuração inválida", $invalid === null);
    }

    private function testRegistroLote() {
        echo "\nTestando Registro de Lote...\n";

        // Teste 1: Criar lote
        $lote = $this->service->criarRegistroLote('CTRL-TEST-001');
        $this->test("Criar lote com controle obrigatório", $lote !== null);

        // Teste 2: Validar duplicação
        $duplicado = $this->service->criarRegistroLote('CTRL-TEST-001');
        $this->test("Rejeitar controle duplicado", $duplicado === null);

        // Teste 3: Criar lote com campos opcionais
        $lote_completo = $this->service->criarRegistroLote(
            'CTRL-TEST-002',
            0,
            'MANGO PALMER',
            'Partida 001',
            'João Silva',
            'Palmer',
            'Extra',
            'Teste'
        );
        $this->test("Criar lote com campos opcionais", $lote_completo !== null);

        // Teste 4: Listar lotes
        $lotes = $this->service->getLotes();
        $this->test("Listar lotes", is_array($lotes) && count($lotes) > 0);
    }

    private function testDistribuicao() {
        echo "\nTestando Distribuição de Lote...\n";

        // Teste 1: Validar distribuição
        $lotes = $this->service->getLotes();
        if (count($lotes) > 0) {
            $lote = $lotes[0];
            $dist = $this->service->criarDistribuicaoLote($lote->id, 1);
            $this->test("Criar distribuição", $dist !== null);

            // Teste 2: Validar percentual
            if ($dist) {
                $dist->adicionarItem(1, 'TEST', '100-200', 'Produto Test', 100);
                $dist->recalcularPercentuais();
                $this->test("Calcular percentuais", $dist->total_gramas === 100.0);

                // Teste 3: Validar 100%
                $dist2 = clone $dist;
                $dist2->adicionarItem(2, 'TEST2', '200-300', 'Produto Test 2', 100);
                $dist2->recalcularPercentuais();
                $valid = $dist2->validar();
                $this->test("Validar soma a 100%", $valid);
            }
        } else {
            echo "  [SKIP] Sem lotes para testar distribuição\n";
        }
    }

    private function testResultado() {
        echo "\nTestando Resultado Operacional...\n";

        // Teste simples de geração
        $resultado = $this->service->gerarResultadoOperacional(1);
        $this->test("Gerar resultado operacional (pode estar vazio)", is_array($resultado));
    }
}

// ============================================================

if (php_sapi_name() === 'cli') {
    $tester = new CalbradoraTester();
    $tester->runAllTests();
} else {
    echo "Este script deve ser executado via CLI.\n";
    echo "Use: php tests.php\n";
}
?>
