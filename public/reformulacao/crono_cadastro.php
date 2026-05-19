<?php
session_start();

$tipo = $_GET['tipo'] ?? 'atividades_padrao';
if ($tipo === 'atividades') {
    $tipo = 'atividades_padrao';
}
$titulos = [
    'atividades_padrao' => 'Atividades Padrao',
    'estrutura_embalagem' => 'Estrutura embalagem',
    'setores' => 'Setores',
    'linhas' => 'Linhas',
    'postos' => 'Postos',
    'linha_postos' => 'Linha x Postos',
    'tipos_calculo' => 'Tipos de calculo',
    'fatores_tolerancia' => 'Fatores tolerancia',
];
$dados = [
    'atividades_padrao' => [],
    'estrutura_embalagem' => [
        ['Caixa 4kg', 'Sem papel', '05, 06, 07, 08, 09, 10, 12, P, M, G', 'Caixa4_SemPapel_08'],
        ['Caixa 6kg', 'Papel simples', '05, 06, 07, 08, 09, 10, 12, P, M, G', 'Caixa6_PapelSimples_08'],
        ['Caixa 18kg', 'Fraldinha', '05, 06, 07, 08, 09, 10, 12, P, M, G', 'Caixa18_Fraldinha_08'],
        ['IFCO', 'Com touca', '05, 06, 07, 08, 09, 10, 12, P, M, G', 'IFCO_ComTouca_08'],
    ],
    'tipos_calculo' => [
        ['TR', 'Tempo Real', 'Tempo total dividido pela quantidade base', 'Ativo'],
        ['TN', 'Tempo Normal', 'TR ajustado pelo fator de ritmo', 'Ativo'],
        ['TP', 'Tempo Padrao', 'TN ajustado pelas tolerancias', 'Ativo'],
        ['TRANSPORTE', 'Movimentacao', 'Tempo logistico por movimentacao', 'Ativo'],
    ],
    'fatores_tolerancia' => [
        ['Pessoal', 'Necessidades pessoais', '0,00%', 'Ativo'],
        ['Fadiga', 'Esforco, postura, repetitividade', '0,00%', 'Ativo'],
        ['Ambiente', 'Calor, ruido, umidade, iluminacao', '0,00%', 'Ativo'],
        ['Espera inevitavel', 'Pequenas paradas inevitaveis', '0,00%', 'Ativo'],
        ['Operacional especifica', 'Condicao especial da atividade', '0,00%', 'Ativo'],
    ],
];

require_once __DIR__ . '/module_routes.php';
require_once __DIR__ . '/safe_storage.php';
require_once __DIR__ . '/shared/CadastrosBasicosRepository.php';

function cad_seed_if_empty($catalog, $names) {
    if (!empty(cb_list($catalog))) {
        return;
    }
    foreach ($names as $name) {
        cb_upsert($catalog, ['nome' => $name, 'ativo' => 1, 'origem' => 'cronoanalise']);
    }
}

cad_seed_if_empty('tipos_embalagem', ['Caixa 4kg', 'Caixa 6kg', 'Caixa 18kg', 'IFCO']);
cad_seed_if_empty('faixas_calibradora', ['05', '06', '07', '08', '09', '10', '12', 'P', 'M', 'G']);

$cadastro_comum_tipo = [
    'atividades_padrao' => 'atividades_padrao',
    'estrutura_embalagem' => 'tipos_embalagem',
    'setores' => 'setores',
    'linhas' => 'linhas',
    'postos' => 'postos',
    'linha_postos' => 'linhas',
];

$cadastro_comum_links = [
    'linha_postos' => [
        ['tipo' => 'linhas', 'label' => 'Abrir Linhas'],
        ['tipo' => 'postos', 'label' => 'Abrir Postos'],
    ],
];

if (isset($cadastro_comum_tipo[$tipo])) {
    $dados[$tipo] = [];
    $setores_por_id = [];
    foreach (cb_list('setores') as $setor) {
        $setores_por_id[$setor['id'] ?? ''] = $setor['nome'] ?? '';
    }

    if ($tipo === 'linhas') {
        foreach (cb_list('linhas') as $linha) {
            $dados[$tipo][] = [
                $linha['codigo'] ?? '',
                $linha['nome'] ?? '',
                $setores_por_id[$linha['setor_id'] ?? ''] ?? ($linha['setor_id'] ?? ''),
                (int)($linha['ativo'] ?? 1) === 1 ? 'Ativo' : 'Inativo'
            ];
        }
    } elseif ($tipo === 'linha_postos') {
        $linhas = cb_list('linhas');
        $postos = cb_list('postos');
        foreach ($linhas as $linha) {
            foreach ($postos as $posto) {
                $dados[$tipo][] = [
                    $linha['nome'] ?? '',
                    $posto['nome'] ?? '',
                    $setores_por_id[$linha['setor_id'] ?? ''] ?? ($linha['setor_id'] ?? ''),
                    (int)($linha['ativo'] ?? 1) === 1 && (int)($posto['ativo'] ?? 1) === 1 ? 'Ativo' : 'Inativo'
                ];
            }
        }
    } else {
        foreach (cb_list($cadastro_comum_tipo[$tipo]) as $row) {
            $dados[$tipo][] = [
                $row['codigo'] ?? '',
                $row['nome'] ?? '',
                $row['descricao'] ?? '',
                (int)($row['ativo'] ?? 1) === 1 ? 'Ativo' : 'Inativo'
            ];
        }
    }
}

$headers = ['Codigo', 'Nome/Descricao', 'Origem/Relacionamento', 'Status'];
if ($tipo === 'linhas') {
    $headers = ['Codigo', 'Linha', 'Setor', 'Status'];
} elseif ($tipo === 'linha_postos') {
    $headers = ['Linha', 'Posto', 'Setor', 'Status'];
}

function cad_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

include __DIR__ . '/menu.php';
?>

<div class="content crono-page">
    <div class="crono-shell">
        <section class="crono-list-card">
            <div class="crono-list-header"><?php echo cad_h($titulos[$tipo] ?? 'Cadastro'); ?></div>
            <div class="crono-table-wrap">
                <?php if (isset($cadastro_comum_tipo[$tipo])): ?>
                    <div class="crono-feedback success" style="margin-bottom:12px;">
                        Esta lista esta conectada aos Cadastros Basicos compartilhados.
                        <?php foreach (($cadastro_comum_links[$tipo] ?? [['tipo' => $cadastro_comum_tipo[$tipo], 'label' => 'Abrir cadastro mestre']]) as $link): ?>
                            <a href="cadastros-basicos/index.php?tipo=<?php echo cad_h($link['tipo']); ?>"><?php echo cad_h($link['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <table class="crono-table">
                    <thead>
                        <tr>
                            <?php foreach ($headers as $header): ?>
                                <th><?php echo cad_h($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dados[$tipo] as $row): ?>
                            <tr>
                                <td><?php echo cad_h($row[0] ?? ''); ?></td>
                                <td><?php echo cad_h($row[1] ?? ''); ?></td>
                                <td><?php echo cad_h($row[2] ?? ''); ?></td>
                                <td><?php echo cad_h($row[3] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($dados[$tipo])): ?>
                            <tr><td colspan="4" class="crono-empty">Nenhum registro operacional encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
