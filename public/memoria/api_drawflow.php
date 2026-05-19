<?php
session_start();
include 'data_store.php';

header('Content-Type: application/json');

// Carregar todas as linhas
$linhas_json = load_json_data('linhas');
$setores_json = load_json_data('setores');

// Função auxiliar para encontrar e retornar a linha por referência
function &get_linha_by_id_ref(&$linhas, $linha_id) {
    foreach ($linhas as &$linha) {
        if ($linha['id'] === $linha_id) {
            return $linha;
        }
    }
    $null = null; // Retorna null por referência se não encontrar
    return $null;
}

function &get_setor_by_id_ref(&$setores, $setor_id) {
    foreach ($setores as &$setor) {
        if ($setor['id'] === $setor_id) return $setor;
    }
    $null = null;
    return $null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $linha_id = $_POST['linha_id'] ?? '';
    
    switch ($action) {
        case 'save_drawflow_data':
            $drawflow_data = $_POST['drawflow_data'] ?? null;
            if ($drawflow_data !== null) {
                $data = json_decode($drawflow_data, true);
                save_json_data('fluxo_' . $linha_id, $data);
                echo json_encode(['status' => 'success', 'message' => 'Dados do Drawflow salvos com sucesso.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Dados do Drawflow ausentes.']);
            }
            break;

        case 'generate_id':
            echo json_encode(['status' => 'success', 'id' => generate_unique_id('posto_')]);
            break;
            
        case 'add_setor':
            $nome = $_POST['nome'] ?? 'Novo Setor';
            $novo_setor = create_default_setor($nome);
            $setores_json[] = $novo_setor;
            save_json_data('setores', $setores_json);
            echo json_encode(['status' => 'success', 'setor' => $novo_setor]);
            break;

        case 'delete_setor':
            $setor_id = $_POST['setor_id'] ?? '';
            $setores_json = array_filter($setores_json, fn($s) => $s['id'] !== $setor_id);
            $linhas_json = array_filter($linhas_json, fn($l) => ($l['setor_id'] ?? '') !== $setor_id);
            save_json_data('setores', array_values($setores_json));
            save_json_data('linhas', array_values($linhas_json));
            echo json_encode(['status' => 'success']);
            break;

        case 'add_linha':
            $nome = $_POST['nome'] ?? 'Nova Linha';
            $setor_id = $_POST['setor_id'] ?? '';
            $nova_linha = create_default_linha($nome, $setor_id);
            $linhas_json[] = $nova_linha;
            save_json_data('linhas', $linhas_json);
            echo json_encode(['status' => 'success', 'linha' => $nova_linha]);
            break;

        case 'delete_linha':
            $linhas_json = array_filter($linhas_json, fn($l) => $l['id'] !== $linha_id);
            save_json_data('linhas', array_values($linhas_json));
            echo json_encode(['status' => 'success']);
            break;

        case 'rename_linha':
            $nome = $_POST['nome'] ?? '';
            $linha_ref = &get_linha_by_id_ref($linhas_json, $linha_id);
            if ($linha_ref) {
                $linha_ref['nome'] = $nome;
                save_json_data('linhas', $linhas_json);
                echo json_encode(['status' => 'success']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Ação desconhecida.']);
            break;
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $linha_id = $_GET['linha_id'] ?? '';

    switch ($action) {
        case 'load_drawflow_data':
            $flow = load_json_data('fluxo_' . $linha_id);
            // Retorna o objeto já decodificado, não dupla-codificado
            echo json_encode(['status' => 'success', 'drawflow_data' => !empty($flow) ? $flow : null]);
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Ação desconhecida.']);
            break;
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método de requisição não permitido.']);
}
?>