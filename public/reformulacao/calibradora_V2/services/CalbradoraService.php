<?php
/**
 * Service: CalbradoraService
 * 
 * Orquestra toda a lógica de negócio do módulo.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Services;

use CalbradoraModule\Models\FaixaPeso;
use CalbradoraModule\Models\ConfiguracaoEmbalamento;
use CalbradoraModule\Models\RegistroLote;
use CalbradoraModule\Models\DistribuicaoLote;
use CalbradoraModule\Repositories\FaixaPesoRepository;
use CalbradoraModule\Repositories\ConfiguracaoEmbalamentoRepository;
use CalbradoraModule\Repositories\RegistroLoteRepository;
use CalbradoraModule\Repositories\DistribuicaoLoteRepository;

class CalbradoraService {
    private FaixaPesoRepository $faixa_repo;
    private ConfiguracaoEmbalamentoRepository $config_repo;
    private RegistroLoteRepository $lote_repo;
    private DistribuicaoLoteRepository $dist_repo;

    public function __construct(string $data_dir) {
        $this->faixa_repo = new FaixaPesoRepository($data_dir);
        $this->config_repo = new ConfiguracaoEmbalamentoRepository($data_dir);
        $this->lote_repo = new RegistroLoteRepository($data_dir);
        $this->dist_repo = new DistribuicaoLoteRepository($data_dir);
    }

    /**
     * ========== FAIXA DE PESO ==========
     */

    /**
     * Obter todas as faixas de peso
     */
    public function getFaixas(): array {
        return $this->faixa_repo->getAll();
    }

    /**
     * Obter faixas de uma configuração específica
     */
    public function getFaixasPorConfiguracao(string $nome_config): array {
        return $this->faixa_repo->getByConfiguracao($nome_config);
    }

    /**
     * Obter faixa por ID
     */
    public function getFaixaPorId(int $id): ?FaixaPeso {
        return $this->faixa_repo->getById($id);
    }

    /**
     * Obter próxima sequência para uma configuração
     */
    public function getProxSeq(string $nome_config): int {
        return $this->faixa_repo->getProxSeq($nome_config);
    }

    /**
     * Criar nova faixa de peso com validação
     */
    public function criarFaixa(int $seq, string $calibre, float $peso_inicial, float $peso_final, string $nome_config): ?FaixaPeso {
        if ($peso_inicial >= $peso_final) {
            return null; // Erro: faixa inválida
        }

        if (empty($calibre) || empty($nome_config)) {
            return null; // Erro: campos obrigatórios
        }

        $faixa = new FaixaPeso(
            null,
            $seq,
            $calibre,
            $peso_inicial,
            $peso_final,
            $nome_config
        );

        // Validar sobreposição com outras faixas da mesma configuração
        $faixas_existentes = $this->getFaixasPorConfiguracao($nome_config);
        $faixas_existentes[] = $faixa;

        if (!FaixaPeso::validarSobreposicao($faixas_existentes)) {
            return null; // Erro: sobreposição detectada
        }

        return $this->faixa_repo->create($faixa);
    }

    /**
     * Atualizar faixa de peso
     */
    public function atualizarFaixa(FaixaPeso $faixa): bool {
        if ($faixa->peso_inicial >= $faixa->peso_final) {
            return false; // Erro: faixa inválida
        }

        if (empty($faixa->calibre)) {
            return false; // Erro: calibre obrigatório
        }

        // Validar sobreposição
        $faixas_existentes = $this->getFaixasPorConfiguracao($faixa->nome_configuracao);
        $faixas_existentes = array_filter($faixas_existentes, fn($f) => $f->id !== $faixa->id);
        $faixas_existentes[] = $faixa;

        if (!FaixaPeso::validarSobreposicao($faixas_existentes)) {
            return false; // Erro: sobreposição detectada
        }

        return $this->faixa_repo->update($faixa);
    }

    /**
     * Deletar faixa de peso
     */
    public function deletarFaixa(int $id): bool {
        return $this->faixa_repo->delete($id);
    }

    /**
     * ========== CONFIGURAÇÃO DE EMBALAMENTO ==========
     */

    /**
     * Obter todas as configurações
     */
    public function getConfiguracoes(): array {
        return $this->config_repo->getAll();
    }

    /**
     * Obter configuração por ID
     */
    public function getConfiguracaoPorId(int $id): ?ConfiguracaoEmbalamento {
        return $this->config_repo->getById($id);
    }

    /**
     * Obter configuração por nome
     */
    public function getConfiguracaoPorNome(string $nome): ?ConfiguracaoEmbalamento {
        return $this->config_repo->getByNome($nome);
    }

    /**
     * Criar nova configuração de embalamento
     */
    public function criarConfiguracao(string $nome, int $faixa_peso_id, array $mapeamentos = []): ?ConfiguracaoEmbalamento {
        if (empty($nome) || $faixa_peso_id <= 0) {
            return null; // Erro: dados inválidos
        }

        $config = new ConfiguracaoEmbalamento(
            null,
            $nome,
            $faixa_peso_id,
            $mapeamentos
        );

        return $this->config_repo->create($config);
    }

    /**
     * Atualizar configuração de embalamento
     */
    public function atualizarConfiguracao(ConfiguracaoEmbalamento $config): bool {
        if (empty($config->nome) || $config->faixa_peso_id <= 0) {
            return false; // Erro: dados inválidos
        }

        return $this->config_repo->update($config);
    }

    /**
     * Deletar configuração de embalamento
     */
    public function deletarConfiguracao(int $id): bool {
        return $this->config_repo->delete($id);
    }

    /**
     * ========== REGISTRO DE LOTE ==========
     */

    /**
     * Obter todos os lotes
     */
    public function getLotes(): array {
        return $this->lote_repo->getAll();
    }

    /**
     * Obter lote por ID
     */
    public function getLotePorId(int $id): ?RegistroLote {
        return $this->lote_repo->getById($id);
    }

    /**
     * Obter lote por Controle
     */
    public function getLotePorControle(string $controle): ?RegistroLote {
        return $this->lote_repo->getByControle($controle);
    }

    /**
     * Criar novo registro de lote
     */
    public function criarRegistroLote(
        string $controle,
        int $config_id = 0,
        string $programa = '',
        string $partida = '',
        string $produtor = '',
        string $variedade = '',
        string $classe = '',
        string $observacoes = ''
    ): ?RegistroLote {
        if (empty($controle)) {
            return null; // Erro: controle obrigatório
        }

        // Validar se já existe lote com este controle
        if ($this->getLotePorControle($controle)) {
            return null; // Erro: controle já existe
        }

        $lote = new RegistroLote(
            null,
            $controle,
            $config_id,
            $programa,
            $partida,
            $produtor,
            $variedade,
            $classe,
            $observacoes,
            'rascunho'
        );

        return $this->lote_repo->create($lote);
    }

    /**
     * Atualizar registro de lote
     */
    public function atualizarRegistroLote(RegistroLote $lote): bool {
        if (!$lote->validar()) {
            return false; // Erro: controle obrigatório
        }

        return $this->lote_repo->update($lote);
    }

    /**
     * Salvar registro de lote (finalizar rascunho)
     */
    public function salvarRegistroLote(int $lote_id): bool {
        $lote = $this->lote_repo->getById($lote_id);
        if (!$lote || !$lote->validar()) {
            return false;
        }

        $lote->status = 'salvo';
        return $this->lote_repo->update($lote);
    }

    /**
     * Deletar registro de lote
     */
    public function deletarRegistroLote(int $id): bool {
        return $this->lote_repo->delete($id);
    }

    /**
     * ========== DISTRIBUIÇÃO DE LOTE ==========
     */

    /**
     * Obter distribuição por ID
     */
    public function getDistribuicaoPorId(int $id): ?DistribuicaoLote {
        return $this->dist_repo->getById($id);
    }

    /**
     * Obter distribuição por Lote ID
     */
    public function getDistribuicaoPorLoteId(int $lote_id): ?DistribuicaoLote {
        return $this->dist_repo->getByLoteId($lote_id);
    }

    /**
     * Criar distribuição para um lote
     */
    public function criarDistribuicaoLote(int $lote_id, int $config_id, array $itens = []): ?DistribuicaoLote {
        if ($lote_id <= 0 || $config_id <= 0) {
            return null; // Erro: dados inválidos
        }

        $dist = new DistribuicaoLote(
            null,
            $lote_id,
            $config_id,
            $itens,
            0.0,
            'rascunho'
        );

        return $this->dist_repo->create($dist);
    }

    /**
     * Atualizar distribuição de lote
     */
    public function atualizarDistribuicaoLote(DistribuicaoLote $dist): bool {
        return $this->dist_repo->update($dist);
    }

    /**
     * Salvar distribuição de lote (finalizar rascunho)
     */
    public function salvarDistribuicaoLote(int $dist_id): bool {
        $dist = $this->dist_repo->getById($dist_id);
        if (!$dist || !$dist->validar()) {
            return false; // Erro: distribuição não soma 100%
        }

        $dist->status = 'salvo';
        return $this->dist_repo->update($dist);
    }

    /**
     * Deletar distribuição
     */
    public function deletarDistribuicaoLote(int $id): bool {
        return $this->dist_repo->delete($id);
    }

    /**
     * ========== RESULTADO OPERACIONAL ==========
     */

    /**
     * Gerar resultado operacional (resumo de produtos)
     */
    public function gerarResultadoOperacional(int $dist_id): array {
        $dist = $this->dist_repo->getById($dist_id);
        if (!$dist) {
            return []; // Distribuição não encontrada
        }

        // Agrupar por produto operacional e somar percentuais
        $resultado = [];
        foreach ($dist->itens as $item) {
            $produto = $item['produto_operacional'];
            if (!isset($resultado[$produto])) {
                $resultado[$produto] = 0;
            }
            $resultado[$produto] += $item['percentual'];
        }

        // Ordenar por percentual descendente
        arsort($resultado);

        return $resultado;
    }
}
