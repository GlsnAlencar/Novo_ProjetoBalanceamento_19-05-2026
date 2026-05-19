<?php
/**
 * API de Cronoanálise: Lógica de Negócio e Persistência
 * Centraliza os cálculos de Engenharia de Métodos.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/safe_storage.php';

function carregar_dados_fluxo() {
    $path = __DIR__ . '/../../data/reformulacao/fluxo_teste02.json';
    return cod_12_05_safe_load_json($path, fn() => ['setores' => []], fn($d) => isset($d['setores']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_atividade';
    $data = carregar_dados_fluxo();

    $linha_id = $_POST['linha_id'] ?? '';
    $post_index = (int)($_POST['post_index'] ?? 0);
    
    // Localiza a linha e o posto no JSON do Fluxo (Estrutura Unificada)
    $setor_encontrado = false;
    foreach ($data['setores'] as &$setor) {
        foreach ($setor['linhas'] as &$linha) {
            if ($linha['id'] === $linha_id) {
                // Aqui assumimos que o Drawflow salva os nós/postos. 
                // Se o posto for um nó do Drawflow, precisamos navegar no drawflow_data
                if (!isset($linha['atividades_por_posto'])) {
                    $linha['atividades_por_posto'] = [];
                }
                
                if ($action === 'save_atividade') {
                    $tipo = $_POST['tipo_operacao'];
                    $nova_act = [
                        'id' => $_POST['id'] ?: 'act_' . uniqid(),
                        'tipo_operacao' => $tipo,
                        'descricao' => $_POST['descricao'],
                        'criado_em' => date('Y-m-d H:i:s')
                    ];

                    // CÁLCULOS ESPECÍFICOS POR TIPO
                    if ($tipo === 'ESTATICA') {
                        $nova_act['unidade_ref'] = $_POST['unidade_ref'];
                        $nova_act['quantidade_ref'] = (float)$_POST['quantidade_ref'];
                        $nova_act['tempo_total'] = (float)$_POST['tempo_total'];
                        $nova_act['tempo_unitario'] = $nova_act['quantidade_ref'] > 0 ? $nova_act['tempo_total'] / $nova_act['quantidade_ref'] : 0;
                        $nova_act['pessoas'] = (int)$_POST['pessoas'];
                        $nova_act['eficiencia'] = (float)$_POST['eficiencia'];
                    } 
                    elseif ($tipo === 'TRANSPORTE') {
                        $nova_act['distancia_m'] = (float)$_POST['distancia_m'];
                        $nova_act['tempo_total'] = (float)$_POST['tempo_total_transp'];
                        $nova_act['velocidade_media'] = $nova_act['tempo_total'] > 0 ? $nova_act['distancia_m'] / $nova_act['tempo_total'] : 0;
                        $nova_act['meio_transporte'] = $_POST['meio_transporte'];
                        $nova_act['origem'] = $_POST['origem'];
                        $nova_act['destino'] = $_POST['destino'];
                        $nova_act['capacidade_carga'] = $_POST['capacidade_carga'];
                    }
                    elseif ($tipo === 'MISTA') {
                        // Decomposição Operacional
                        $elementos = [];
                        $tempo_acumulado = 0;
                        if (isset($_POST['elem_seq'])) {
                            foreach ($_POST['elem_seq'] as $i => $seq) {
                                $t_elem = (float)$_POST['elem_tempo'][$i];
                                $tempo_acumulado += $t_elem;
                                $elementos[] = [
                                    'sequencia' => $seq,
                                    'tipo' => $_POST['elem_tipo'][$i],
                                    'descricao' => $_POST['elem_desc'][$i],
                                    'distancia' => (float)$_POST['elem_dist'][$i],
                                    'quantidade' => (float)$_POST['elem_qtd'][$i],
                                    'tempo' => $t_elem
                                ];
                            }
                        }
                        $nova_act['elementos_operacionais'] = $elementos;
                        $nova_act['tempo_total'] = $tempo_acumulado;
                        // Campos herdados da estática para referência
                        $nova_act['unidade_ref'] = $_POST['unidade_ref'];
                        $nova_act['quantidade_ref'] = (float)$_POST['quantidade_ref'];
                    }

                    // Persistência
                    $key = "posto_" . $post_index;
                    if (!isset($linha['atividades_por_posto'][$key])) $linha['atividades_por_posto'][$key] = [];
                    
                    // Update ou Create
                    $found = false;
                    foreach($linha['atividades_por_posto'][$key] as &$ex) {
                        if($ex['id'] === $nova_act['id']) { $ex = $nova_act; $found = true; break; }
                    }
                    if(!$found) $linha['atividades_por_posto'][$key][] = $nova_act;
                }
                
                if ($action === 'delete_atividade') {
                    $id = $_POST['id'];
                    $key = "posto_" . $post_index;
                    $linha['atividades_por_posto'][$key] = array_values(array_filter(
                        $linha['atividades_por_posto'][$key], 
                        fn($a) => $a['id'] !== $id
                    ));
                }

                $setor_encontrado = true;
                break 2;
            }
        }
    }

    cod_12_05_safe_write_json(__DIR__ . '/../../data/reformulacao/fluxo_teste02.json', $data);
    echo json_encode(['status' => 'success', 'message' => 'Cronoanálise atualizada']);
    exit;
}