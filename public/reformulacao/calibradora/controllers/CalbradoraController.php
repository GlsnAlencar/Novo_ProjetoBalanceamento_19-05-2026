<?php
/**
 * Controller: CalbradoraController
 * 
 * Processa requisições HTTP para o módulo.
 * Completamente isolado do módulo legado.
 */

namespace CalbradoraModule\Controllers;

use CalbradoraModule\Services\CalbradoraService;
use CalbradoraModule\Models\FaixaPeso;
use CalbradoraModule\Models\ConfiguracaoEmbalamento;
use CalbradoraModule\Models\RegistroLote;
use CalbradoraModule\Models\DistribuicaoLote;
use CalbradoraModule\Models\ConfiguracaoCalibradora;
use CalbradoraModule\Models\ConfiguracaoCalbradoraFaixa;

class CalbradoraController {
    private CalbradoraService $service;

    public function __construct(CalbradoraService $service) {
        $this->service = $service;
    }

    /**
     * Processar requisição HTTP
     */
    public function processarRequisicao(string $action, array $data = []): array {
        $response = ['sucesso' => false, 'mensagem' => '', 'dados' => null];

        try {
            switch ($action) {
                // FAIXA DE PESO
                case 'criar_faixa':
                    return $this->criarFaixa($data);
                case 'atualizar_faixa':
                    return $this->atualizarFaixa($data);
                case 'deletar_faixa':
                    return $this->deletarFaixa($data);
                case 'obter_faixas':
                    return $this->obterFaixas();
                case 'obter_faixas_config':
                    return $this->obterFaixasPorConfiguracao($data);

                // CONFIGURAÇÃO DE EMBALAMENTO
                case 'criar_configuracao':
                    return $this->criarConfiguracao($data);
                case 'atualizar_configuracao':
                    return $this->atualizarConfiguracao($data);
                case 'deletar_configuracao':
                    return $this->deletarConfiguracao($data);
                case 'obter_configuracoes':
                    return $this->obterConfiguracoes();
                case 'obter_configuracao':
                    return $this->obterConfiguracao($data);

                // REGISTRO DE LOTE
                case 'criar_lote':
                    return $this->criarRegistroLote($data);
                case 'atualizar_lote':
                    return $this->atualizarRegistroLote($data);
                case 'salvar_lote':
                    return $this->salvarRegistroLote($data);
                case 'deletar_lote':
                    return $this->deletarRegistroLote($data);
                case 'obter_lotes':
                    return $this->obterRegistrosLote();
                case 'obter_lote':
                    return $this->obterRegistroLote($data);

                // DISTRIBUIÇÃO DE LOTE
                case 'criar_distribuicao':
                    return $this->criarDistribuicaoLote($data);
                case 'atualizar_distribuicao':
                    return $this->atualizarDistribuicaoLote($data);
                case 'salvar_distribuicao':
                    return $this->salvarDistribuicaoLote($data);
                case 'deletar_distribuicao':
                    return $this->deletarDistribuicaoLote($data);
                case 'obter_distribuicao':
                    return $this->obterDistribuicaoLote($data);

                // RESULTADO OPERACIONAL
                case 'resultado_operacional':
                    return $this->gerarResultadoOperacional($data);

                // CONFIGURAÇÃO CALIBRADORA
                case 'criar_configuracao_calibradora':
                    return $this->criarConfiguracaoCalibradora($data);
                case 'atualizar_configuracao_calibradora':
                    return $this->atualizarConfiguracaoCalibradora($data);
                case 'deletar_configuracao_calibradora':
                    return $this->deletarConfiguracaoCalibradora($data);
                case 'obter_configuracoes_calibradora':
                    return $this->obterConfiguracoesCalbradora();
                case 'obter_configuracao_calibradora':
                    return $this->obterConfiguracaoCalibradora($data);

                // FAIXA CONFIGURAÇÃO CALIBRADORA
                case 'criar_faixa_configuracao':
                    return $this->criarFaixaConfiguracao($data);
                case 'atualizar_faixa_configuracao':
                    return $this->atualizarFaixaConfiguracao($data);
                case 'deletar_faixa_configuracao':
                    return $this->deletarFaixaConfiguracao($data);
                case 'obter_faixas_configuracao':
                    return $this->obterFaixasConfiguracao($data);
                case 'atualizar_sequencias_faixas':
                    return $this->atualizarSequenciasFaixas($data);

                default:
                    $response['mensagem'] = 'Ação desconhecida: ' . $action;
            }
        } catch (\Exception $e) {
            $response['mensagem'] = 'Erro: ' . $e->getMessage();
        }

        return $response;
    }

    /**
     * ========== FAIXA DE PESO ==========
     */

    private function criarFaixa(array $data): array {
        $seq = (int)($data['seq'] ?? 0);
        $calibre = trim($data['calibre'] ?? '');
        $peso_inicial = (float)($data['peso_inicial'] ?? 0);
        $peso_final = (float)($data['peso_final'] ?? 0);
        $nome_config = trim($data['nome_configuracao'] ?? '');

        if (empty($calibre) || empty($nome_config) || $seq <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Seq, calibre e configuração são obrigatórios'];
        }

        if ($peso_inicial >= $peso_final) {
            return ['sucesso' => false, 'mensagem' => 'Peso inicial deve ser menor que peso final'];
        }

        $faixa = $this->service->criarFaixa($seq, $calibre, $peso_inicial, $peso_final, $nome_config);

        if (!$faixa) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar faixa. Verifique se há sobreposição de faixas.'];
        }

        return ['sucesso' => true, 'mensagem' => 'Faixa criada com sucesso', 'dados' => $faixa->toArray()];
    }

    private function atualizarFaixa(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $faixa = $this->service->getFaixaPorId($id);
        if (!$faixa) {
            return ['sucesso' => false, 'mensagem' => 'Faixa não encontrada'];
        }

        $faixa->seq = (int)($data['seq'] ?? $faixa->seq);
        $faixa->calibre = trim($data['calibre'] ?? $faixa->calibre);
        $faixa->peso_inicial = (float)($data['peso_inicial'] ?? $faixa->peso_inicial);
        $faixa->peso_final = (float)($data['peso_final'] ?? $faixa->peso_final);

        if ($faixa->peso_inicial >= $faixa->peso_final) {
            return ['sucesso' => false, 'mensagem' => 'Peso inicial deve ser menor que peso final'];
        }

        if (!$this->service->atualizarFaixa($faixa)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar faixa. Verifique se há sobreposição.'];
        }

        return ['sucesso' => true, 'mensagem' => 'Faixa atualizada com sucesso', 'dados' => $faixa->toArray()];
    }

    private function deletarFaixa(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->deletarFaixa($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao deletar faixa'];
        }

        return ['sucesso' => true, 'mensagem' => 'Faixa deletada com sucesso'];
    }

    private function obterFaixas(): array {
        $faixas = $this->service->getFaixas();
        return [
            'sucesso' => true,
            'dados' => array_map(fn($f) => $f->toArray(), $faixas)
        ];
    }

    private function obterFaixasPorConfiguracao(array $data): array {
        $nome_config = trim($data['nome_configuracao'] ?? '');
        if (empty($nome_config)) {
            return ['sucesso' => false, 'mensagem' => 'Nome de configuração obrigatório'];
        }

        $faixas = $this->service->getFaixasPorConfiguracao($nome_config);
        return [
            'sucesso' => true,
            'dados' => array_map(fn($f) => $f->toArray(), $faixas)
        ];
    }

    /**
     * ========== CONFIGURAÇÃO DE EMBALAMENTO ==========
     */

    private function criarConfiguracao(array $data): array {
        $nome = trim($data['nome'] ?? '');
        $faixa_id = (int)($data['faixa_peso_id'] ?? 0);

        if (empty($nome) || $faixa_id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Nome e faixa de peso são obrigatórios'];
        }

        $config = $this->service->criarConfiguracao($nome, $faixa_id);
        if (!$config) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar configuração'];
        }

        return ['sucesso' => true, 'mensagem' => 'Configuração criada com sucesso', 'dados' => $config->toArray()];
    }

    private function atualizarConfiguracao(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $config = $this->service->getConfiguracaoPorId($id);
        if (!$config) {
            return ['sucesso' => false, 'mensagem' => 'Configuração não encontrada'];
        }

        $config->nome = trim($data['nome'] ?? $config->nome);
        $config->mapeamentos = $data['mapeamentos'] ?? $config->mapeamentos;

        if (!$this->service->atualizarConfiguracao($config)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar configuração'];
        }

        return ['sucesso' => true, 'mensagem' => 'Configuração atualizada com sucesso', 'dados' => $config->toArray()];
    }

    private function deletarConfiguracao(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->deletarConfiguracao($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao deletar configuração'];
        }

        return ['sucesso' => true, 'mensagem' => 'Configuração deletada com sucesso'];
    }

    private function obterConfiguracoes(): array {
        $configs = $this->service->getConfiguracoes();
        return [
            'sucesso' => true,
            'dados' => array_map(fn($c) => $c->toArray(), $configs)
        ];
    }

    private function obterConfiguracao(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $config = $this->service->getConfiguracaoPorId($id);
        if (!$config) {
            return ['sucesso' => false, 'mensagem' => 'Configuração não encontrada'];
        }

        return ['sucesso' => true, 'dados' => $config->toArray()];
    }

    /**
     * ========== REGISTRO DE LOTE ==========
     */

    private function criarRegistroLote(array $data): array {
        $controle = trim($data['controle'] ?? '');
        if (empty($controle)) {
            return ['sucesso' => false, 'mensagem' => 'Controle é obrigatório'];
        }

        $lote = $this->service->criarRegistroLote(
            $controle,
            (int)($data['configuracao_embalamento_id'] ?? 0),
            trim($data['programa'] ?? ''),
            trim($data['partida'] ?? ''),
            trim($data['produtor'] ?? ''),
            trim($data['variedade'] ?? ''),
            trim($data['classe'] ?? ''),
            trim($data['observacoes'] ?? '')
        );

        if (!$lote) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar lote (controle pode estar duplicado)'];
        }

        return ['sucesso' => true, 'mensagem' => 'Lote criado com sucesso', 'dados' => $lote->toArray()];
    }

    private function atualizarRegistroLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $lote = $this->service->getLotePorId($id);
        if (!$lote) {
            return ['sucesso' => false, 'mensagem' => 'Lote não encontrado'];
        }

        $lote->configuracao_embalamento_id = (int)($data['configuracao_embalamento_id'] ?? $lote->configuracao_embalamento_id);
        $lote->programa = trim($data['programa'] ?? $lote->programa);
        $lote->partida = trim($data['partida'] ?? $lote->partida);
        $lote->produtor = trim($data['produtor'] ?? $lote->produtor);
        $lote->variedade = trim($data['variedade'] ?? $lote->variedade);
        $lote->classe = trim($data['classe'] ?? $lote->classe);
        $lote->observacoes = trim($data['observacoes'] ?? $lote->observacoes);

        if (!$this->service->atualizarRegistroLote($lote)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar lote'];
        }

        return ['sucesso' => true, 'mensagem' => 'Lote atualizado com sucesso', 'dados' => $lote->toArray()];
    }

    private function salvarRegistroLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->salvarRegistroLote($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao salvar lote'];
        }

        return ['sucesso' => true, 'mensagem' => 'Lote salvo com sucesso'];
    }

    private function deletarRegistroLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->deletarRegistroLote($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao deletar lote'];
        }

        return ['sucesso' => true, 'mensagem' => 'Lote deletado com sucesso'];
    }

    private function obterRegistrosLote(): array {
        $lotes = $this->service->getLotes();
        return [
            'sucesso' => true,
            'dados' => array_map(fn($l) => $l->toArray(), $lotes)
        ];
    }

    private function obterRegistroLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $lote = $this->service->getLotePorId($id);
        if (!$lote) {
            return ['sucesso' => false, 'mensagem' => 'Lote não encontrado'];
        }

        return ['sucesso' => true, 'dados' => $lote->toArray()];
    }

    /**
     * ========== DISTRIBUIÇÃO DE LOTE ==========
     */

    private function criarDistribuicaoLote(array $data): array {
        $lote_id = (int)($data['lote_id'] ?? 0);
        $config_id = (int)($data['configuracao_embalamento_id'] ?? 0);

        if ($lote_id <= 0 || $config_id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Lote e configuração são obrigatórios'];
        }

        $dist = $this->service->criarDistribuicaoLote($lote_id, $config_id);
        if (!$dist) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar distribuição'];
        }

        return ['sucesso' => true, 'mensagem' => 'Distribuição criada com sucesso', 'dados' => $dist->toArray()];
    }

    private function atualizarDistribuicaoLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $dist = $this->service->getDistribuicaoPorId($id);
        if (!$dist) {
            return ['sucesso' => false, 'mensagem' => 'Distribuição não encontrada'];
        }

        $dist->itens = $data['itens'] ?? $dist->itens;
        $dist->recalcularPercentuais();

        if (!$this->service->atualizarDistribuicaoLote($dist)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar distribuição'];
        }

        return ['sucesso' => true, 'mensagem' => 'Distribuição atualizada com sucesso', 'dados' => $dist->toArray()];
    }

    private function salvarDistribuicaoLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->salvarDistribuicaoLote($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao salvar distribuição (não soma 100%)'];
        }

        return ['sucesso' => true, 'mensagem' => 'Distribuição salva com sucesso'];
    }

    private function deletarDistribuicaoLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->deletarDistribuicaoLote($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao deletar distribuição'];
        }

        return ['sucesso' => true, 'mensagem' => 'Distribuição deletada com sucesso'];
    }

    private function obterDistribuicaoLote(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $dist = $this->service->getDistribuicaoPorId($id);
        if (!$dist) {
            return ['sucesso' => false, 'mensagem' => 'Distribuição não encontrada'];
        }

        return ['sucesso' => true, 'dados' => $dist->toArray()];
    }

    /**
     * ========== RESULTADO OPERACIONAL ==========
     */

    private function gerarResultadoOperacional(array $data): array {
        $dist_id = (int)($data['distribuicao_id'] ?? 0);
        if ($dist_id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID de distribuição inválido'];
        }

        $resultado = $this->service->gerarResultadoOperacional($dist_id);
        if (empty($resultado)) {
            return ['sucesso' => false, 'mensagem' => 'Distribuição não encontrada'];
        }

        return ['sucesso' => true, 'dados' => $resultado];
    }

    /**
     * ========== CONFIGURAÇÃO CALIBRADORA ==========
     */

    private function criarConfiguracaoCalibradora(array $data): array {
        $nome = trim($data['nome'] ?? '');
        $descricao = trim($data['descricao'] ?? '');
        $ativo = (bool)($data['ativo'] ?? true);

        if (empty($nome)) {
            return ['sucesso' => false, 'mensagem' => 'Nome da configuração é obrigatório'];
        }

        if ($this->service->existeConfiguracaoCalbradora($nome)) {
            return ['sucesso' => false, 'mensagem' => 'Já existe uma configuração com este nome'];
        }

        $config = $this->service->criarConfiguracaoCalibradora($nome, $descricao, $ativo);
        if (!$config) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar configuração'];
        }

        return ['sucesso' => true, 'mensagem' => 'Configuração criada com sucesso', 'dados' => $config->toArray()];
    }

    private function atualizarConfiguracaoCalibradora(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $config = $this->service->getConfiguracaoCalbradoraPorId($id);
        if (!$config) {
            return ['sucesso' => false, 'mensagem' => 'Configuração não encontrada'];
        }

        $novo_nome = trim($data['nome'] ?? $config->nome);
        
        // Verificar se o novo nome já existe (excluindo o próprio ID)
        if (strcasecmp($novo_nome, $config->nome) !== 0 && 
            $this->service->existeConfiguracaoCalbradora($novo_nome)) {
            return ['sucesso' => false, 'mensagem' => 'Já existe uma configuração com este nome'];
        }

        $config->nome = $novo_nome;
        $config->descricao = trim($data['descricao'] ?? $config->descricao);
        $config->ativo = (bool)($data['ativo'] ?? $config->ativo);

        if (!$this->service->atualizarConfiguracaoCalibradora($config)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar configuração'];
        }

        return ['sucesso' => true, 'mensagem' => 'Configuração atualizada com sucesso', 'dados' => $config->toArray()];
    }

    private function deletarConfiguracaoCalibradora(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->deletarConfiguracaoCalibradora($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao deletar configuração'];
        }

        return ['sucesso' => true, 'mensagem' => 'Configuração deletada com sucesso'];
    }

    private function obterConfiguracoesCalbradora(): array {
        $configs = $this->service->getConfiguracoesCalibradora();
        return [
            'sucesso' => true,
            'dados' => array_map(fn($c) => $c->toArray(), $configs)
        ];
    }

    private function obterConfiguracaoCalibradora(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $config = $this->service->getConfiguracaoCalbradoraPorId($id);
        if (!$config) {
            return ['sucesso' => false, 'mensagem' => 'Configuração não encontrada'];
        }

        return ['sucesso' => true, 'dados' => $config->toArray()];
    }

    /**
     * ========== FAIXA CONFIGURAÇÃO CALIBRADORA ==========
     */

    private function criarFaixaConfiguracao(array $data): array {
        $configuracao_id = (int)($data['configuracao_id'] ?? 0);
        $sequencia_grupo = (int)($data['sequencia_grupo'] ?? 0);
        $descricao = trim($data['descricao'] ?? '');
        $peso_inicial = (float)($data['peso_inicial'] ?? 0);
        $peso_final = (float)($data['peso_final'] ?? 0);

        if ($configuracao_id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Configuração inválida'];
        }

        if (empty($descricao)) {
            return ['sucesso' => false, 'mensagem' => 'Descrição é obrigatória'];
        }

        if ($peso_inicial < 0 || $peso_final < 0) {
            return ['sucesso' => false, 'mensagem' => 'Pesos não podem ser negativos'];
        }

        if ($peso_inicial >= $peso_final) {
            return ['sucesso' => false, 'mensagem' => 'Peso inicial deve ser menor que peso final'];
        }

        $faixa = $this->service->criarFaixaConfiguracao(
            $configuracao_id,
            $sequencia_grupo,
            $descricao,
            $peso_inicial,
            $peso_final
        );

        if (!$faixa) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao criar faixa. Verifique se há sobreposição de faixas.'];
        }

        return ['sucesso' => true, 'mensagem' => 'Faixa criada com sucesso', 'dados' => $faixa->toArray()];
    }

    private function atualizarFaixaConfiguracao(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        $faixa = $this->service->getFaixaConfiguracaoPorId($id);
        if (!$faixa) {
            return ['sucesso' => false, 'mensagem' => 'Faixa não encontrada'];
        }

        $faixa->sequencia_grupo = (int)($data['sequencia_grupo'] ?? $faixa->sequencia_grupo);
        $faixa->descricao = trim($data['descricao'] ?? $faixa->descricao);
        $faixa->peso_inicial = (float)($data['peso_inicial'] ?? $faixa->peso_inicial);
        $faixa->peso_final = (float)($data['peso_final'] ?? $faixa->peso_final);

        if ($faixa->peso_inicial < 0 || $faixa->peso_final < 0) {
            return ['sucesso' => false, 'mensagem' => 'Pesos não podem ser negativos'];
        }

        if ($faixa->peso_inicial >= $faixa->peso_final) {
            return ['sucesso' => false, 'mensagem' => 'Peso inicial deve ser menor que peso final'];
        }

        if (!$this->service->atualizarFaixaConfiguracao($faixa)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar faixa. Verifique se há sobreposição.'];
        }

        return ['sucesso' => true, 'mensagem' => 'Faixa atualizada com sucesso', 'dados' => $faixa->toArray()];
    }

    private function deletarFaixaConfiguracao(array $data): array {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'ID inválido'];
        }

        if (!$this->service->deletarFaixaConfiguracao($id)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao deletar faixa'];
        }

        return ['sucesso' => true, 'mensagem' => 'Faixa deletada com sucesso'];
    }

    private function obterFaixasConfiguracao(array $data): array {
        $configuracao_id = (int)($data['configuracao_id'] ?? 0);
        if ($configuracao_id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Configuração inválida'];
        }

        $faixas = $this->service->getFaixasConfiguracao($configuracao_id);
        return [
            'sucesso' => true,
            'dados' => array_map(fn($f) => $f->toArray(), $faixas)
        ];
    }

    private function atualizarSequenciasFaixas(array $data): array {
        $configuracao_id = (int)($data['configuracao_id'] ?? 0);
        $sequencias = $data['sequencias'] ?? [];

        if ($configuracao_id <= 0) {
            return ['sucesso' => false, 'mensagem' => 'Configuração inválida'];
        }

        if (empty($sequencias)) {
            return ['sucesso' => false, 'mensagem' => 'Nenhuma faixa informada'];
        }

        if (!$this->service->atualizarSequenciasFaixas($configuracao_id, $sequencias)) {
            return ['sucesso' => false, 'mensagem' => 'Erro ao atualizar sequências'];
        }

        return ['sucesso' => true, 'mensagem' => 'Sequências atualizadas com sucesso'];
    }
}
