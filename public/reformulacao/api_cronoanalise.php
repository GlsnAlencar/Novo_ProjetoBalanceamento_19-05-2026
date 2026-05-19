<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/module_routes.php';
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';
require_once __DIR__ . '/cronoanalise/repositories/CronoanaliseRepository.php';
require_once __DIR__ . '/cronoanalise/services/CronoanaliseService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metodo nao permitido.']);
    exit;
}

$service = new CronoanaliseService(
    new CronoanaliseRepository(rf_route('editor_bpm', 'storage'))
);

$action = $_POST['action'] ?? 'save_atividade';

try {
    if ($action === 'save_atividade') {
        $result = $service->salvarCronoanalise($_POST);
    } elseif ($action === 'save_transporte') {
        $result = $service->salvarTransporte($_POST);
    } elseif ($action === 'list_cronoanalises') {
        $result = $service->listarCronoanalises($_POST);
    } elseif ($action === 'delete_atividade') {
        $result = $service->excluirCronoanalise($_POST);
    } elseif ($action === 'desacoplar_transporte_item') {
        $result = $service->desacoplarTransporteItem($_POST);
    } elseif ($action === 'save_cadastro_basico') {
        $catalog = trim((string)($_POST['catalog'] ?? ''));
        $nome = trim((string)($_POST['nome'] ?? ''));
        $allowed = ['setores', 'tipos_embalagem', 'faixas_calibradora', 'unidades', 'atividades_padrao'];
        if (!in_array($catalog, $allowed, true)) {
            $result = ['status' => 'error', 'message' => 'Cadastro basico nao permitido para esta tela.'];
        } elseif ($nome === '') {
            $result = ['status' => 'error', 'message' => 'Nome do cadastro e obrigatorio.'];
        } else {
            $row = cb_upsert($catalog, [
                'nome' => $nome,
                'ativo' => 1,
                'origem' => 'cronoanalise'
            ]);
            $result = ['status' => 'success', 'row' => $row];
        }
    } elseif ($action === 'save_atividade_padrao') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        if ($nome === '') {
            $result = ['status' => 'error', 'message' => 'Nome da atividade e obrigatorio.'];
        } else {
            $row = cb_upsert('atividades_padrao', [
                'nome' => $nome,
                'ativo' => 1,
                'origem' => 'cronoanalise'
            ]);
            $result = ['status' => 'success', 'atividade' => $row];
        }
    } else {
        $result = ['status' => 'error', 'message' => 'Acao desconhecida.'];
    }

    if (($result['status'] ?? '') !== 'success') {
        http_response_code(400);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
}
