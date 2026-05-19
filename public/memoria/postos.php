<?php
// Gerenciamento de Postos - Integrado com linhas.json
session_start();
include 'data_store.php';

// Carregar dados dos setores
$setores_json = load_json_data('setores');

// Carregar dados das linhas (que agora representam os fluxos dos setores)
$linhas_json = load_json_data('linhas');

// Determinar o setor ativo
$setor_ativo_id = isset($_GET['setor_id']) ? $_GET['setor_id'] : null;

// Encontrar a linha correspondente ao setor ativo
$linha_selecionada_ref = null; // Usar uma referência para modificar o array original
$linha_selecionada_index = null;
if ($setor_ativo_id) {
    foreach ($linhas_json as $key => &$linha) { // Passar por referência
        if (isset($linha['setor_id']) && $linha['setor_id'] === $setor_ativo_id) {
            $linha_selecionada_ref = &$linha;
            $linha_selecionada_index = $key;
            break;
        }
    }
    unset($linha); // Desfazer referência após o loop
}

// Se nenhum setor ativo for especificado, e houver setores, redirecionar para o primeiro.
if (!$setor_ativo_id && !empty($setores_json)) {
    header('Location: postos.php?setor_id=' . urlencode($setores_json[0]['id']));
    exit;
}

// Se um setor_id foi fornecido mas nenhuma linha correspondente foi encontrada, ou se não existem setores.
if (empty($setores_json) || ($setor_ativo_id && $linha_selecionada_ref === null)) {
    // Isso significa que não podemos exibir postos para um setor específico.
    // O HTML lidará com a exibição de uma mensagem.
    $setor_ativo_id = null; // Limpar setor ativo para indicar que não há seleção válida
    $linha_selecionada_ref = null;
}

// Função auxiliar para redirecionamentos
function redirect_to_postos($success = true, $error = null, $setor_id = null, $nome_posto = null) {
    $url = 'postos.php?';
    if ($success) {
        $url .= 'sucesso=1';
    }
    if ($error) {
        $url .= '&erro=' . urlencode($error);
    }
    if ($setor_id) {
        $url .= '&setor_id=' . urlencode($setor_id);
    }
    if ($nome_posto) {
        $url .= '&nome_posto=' . urlencode($nome_posto);
    }
    header('Location: ' . $url);
    exit;
}

// ========== ADICIONAR NOVO POSTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_posto'])) {
    $nome = trim($_POST['nome_posto']);
    $target_setor_id = trim($_POST['setor_id'] ?? '');

    if (!empty($nome) && !empty($target_setor_id)) {
        // Encontrar a linha (setor) específica para adicionar o posto
        $target_linha_ref = null;
        foreach ($linhas_json as $key => &$linha) {
            if ($linha['id'] === $target_setor_id) {
                $target_linha_ref = &$linha;
                break;
            }
        }
        unset($linha); // Desfazer referência

        if ($target_linha_ref) {
            // Verificar se o posto já existe neste setor
            $existe = false;
            foreach ($target_linha_ref['postos'] as $posto) {
                if ($posto['nome'] === $nome) {
                    $existe = true;
                    break;
                }
            }

            if (!$existe) {
                $target_linha_ref['postos'][] = [
                    'nome' => $nome,
                    'configs' => [],
                    'detalhes' => []
                ];
                save_json_data('linhas', $linhas_json);
                redirect_to_postos(true, null, $target_setor_id);
            } else {
                redirect_to_postos(false, 'posto_existente', $target_setor_id, $nome);
            }
        } else {
            redirect_to_postos(false, 'setor_nao_encontrado', $target_setor_id);
        }
    } else {
        redirect_to_postos(false, 'dados_invalidos', $target_setor_id);
    }
}

// ========== ATUALIZAR NOME DO POSTO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_posto'])) {
    $target_setor_id = $_POST['setor_id'] ?? '';
    $post_index = (int)$_POST['post_index'];
    $novo_nome = trim($_POST['nome_posto_edit'] ?? '');

    if (!empty($novo_nome) && !empty($target_setor_id) && $linha_selecionada_ref !== null) {
        if (isset($linha_selecionada_ref['postos'][$post_index])) {
            $linha_selecionada_ref['postos'][$post_index]['nome'] = $novo_nome;
            save_json_data('linhas', $linhas_json);
            redirect_to_postos(true, null, $target_setor_id);
        } else {
            redirect_to_postos(false, 'posto_nao_encontrado', $target_setor_id);
        }
    } else {
        redirect_to_postos(false, 'dados_invalidos', $target_setor_id);
    }
}

// ========== REMOVER POSTO ==========
if (isset($_GET['remover_posto']) && isset($_GET['setor_id']) && is_numeric($_GET['remover_posto'])) {
    $target_setor_id = $_GET['setor_id'];
    $post_index = (int)$_GET['remover_posto'];

    if ($linha_selecionada_ref !== null) {
        if (isset($linha_selecionada_ref['postos'][$post_index])) {
            unset($linha_selecionada_ref['postos'][$post_index]);
            $linha_selecionada_ref['postos'] = array_values($linha_selecionada_ref['postos']); // Re-indexar
            save_json_data('linhas', $linhas_json);
            redirect_to_postos(true, null, $target_setor_id);
        } else {
            redirect_to_postos(false, 'posto_nao_encontrado', $target_setor_id);
        }
    } else {
        redirect_to_postos(false, 'setor_nao_encontrado', $target_setor_id);
    }
}

$erro = isset($_GET['erro']) ? $_GET['erro'] : null;
$sucesso = isset($_GET['sucesso']) ? true : false;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Postos</title>
    <?php include 'menu.php'; ?>
</head>

<body>
    <div class="content">
        <h1>📍 Gerenciamento de Postos</h1>

        <?php if ($sucesso): ?>
        <div class="success-message">
            ✓ Operação realizada com sucesso!
        </div>
        <?php endif; ?>
        <?php if ($erro === 'posto_existente'): ?>
        <div class="success-message" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;">
            ⚠️ O posto "<?php echo htmlspecialchars($_GET['nome_posto'] ?? 'Nome Desconhecido'); ?>" já existe no setor selecionado.
        </div>
        <?php endif; ?>
        <?php if ($erro === 'setor_nao_encontrado'): ?>
        <div class="success-message" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;">
            ⚠️ O setor selecionado não foi encontrado ou é inválido.
        </div>
        <?php endif; ?>
        <?php if ($erro === 'dados_invalidos'): ?>
        <div class="success-message" style="background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;">
            ⚠️ Dados inválidos fornecidos para a operação.
        </div>
        <?php endif; ?>

        <!-- Seção de Seleção de Setor -->
        <div class="form-section">
            <h2>Escolha um Setor para Gerenciar Postos</h2>
            <?php if (empty($setores_json)): ?>
                <div class="empty-message">
                    Nenhum setor cadastrado. Por favor, <a href="setores.php">cadastre um setor</a> primeiro.
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="select_setor">
                        Setor:
                        <select id="select_setor" onchange="window.location.href = 'postos.php?setor_id=' + this.value;">
                            <option value="">-- Selecione um setor --</option>
                            <?php foreach ($setores_json as $setor): ?>
                                <option value="<?php echo htmlspecialchars($setor['id']); ?>"
                                    <?php echo ($setor_ativo_id === $setor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($setor['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($setor_ativo_id && $linha_selecionada_ref !== null): ?>
            <h2 style="margin-top: 40px;">Postos do Setor: <strong><?php echo htmlspecialchars($linha_selecionada_ref['nome'] ?? 'Setor Desconhecido'); ?></strong></h2>

            <!-- Formulário para Adicionar Novo Posto -->
            <div class="form-section">
                <h2>➕ Adicionar Novo Posto ao Setor</h2>
                <form method="post">
                    <div class="form-group">
                        <input type="hidden" name="setor_id" value="<?php echo htmlspecialchars($setor_ativo_id); ?>">
                        <label for="nome_posto">
                            Nome do Posto:
                            <input type="text" id="nome_posto" name="nome_posto" placeholder="Ex: Embalagem" required>
                        </label>

                        <button type="submit" name="adicionar_posto">➕ Adicionar Posto</button>
                    </div>
                </form>
            </div>

            <!-- Tabela de Postos por Linha -->
            <h2>📋 Postos Cadastrados no Setor</h2>

            <?php
            $postos_do_setor = $linha_selecionada_ref['postos'] ?? [];
            ?>

            <?php if (empty($postos_do_setor)): ?>
                <div class="empty-message">
                    <p>Nenhum posto cadastrado neste setor ainda. Adicione um novo posto acima! 🚀</p>
                </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nome do Posto</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php foreach ($postos_do_setor as $post_index => $posto): ?>
                                    <tr>
                                        <td>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="setor_id" value="<?php echo htmlspecialchars($setor_ativo_id); ?>">
                                                <input type="hidden" name="post_index" value="<?php echo $post_index; ?>">
                                                <input type="text" name="nome_posto_edit" value="<?php echo htmlspecialchars($posto['nome'] ?? 'Posto sem nome'); ?>" required>
                                                <button type="submit" name="atualizar_posto">✓ Atualizar</button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="acoes">
                                                <a href="atividades_posto.php?post=<?php echo $post_index; ?>&linha=<?php echo urlencode($linha_selecionada_ref['id']); ?>&back=postos"
                                                   class="btn-editar-detalhes" title="Gerenciar atividades do posto">
                                                    ⚙️ Atividades
                                                </a>
                                                <a href="?remover_posto=<?php echo $post_index; ?>&setor_id=<?php echo urlencode($setor_ativo_id); ?>"
                                                   class="btn-remover" 
                                                   onclick="return confirm('❌ Tem certeza que deseja remover este posto?')"
                                                   title="Remover este posto">
                                                    🗑️ Remover
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                            <?php endforeach; ?>


                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <hr style="margin-top: 30px; border: none; border-top: 1px solid #dee2e6;">

        <!-- Seção de Edição de Detalhes -->
        <div class="form-section" style="margin-top: 30px;">
            <h2>⚙️ Gerenciar Atividades do Posto</h2>

            <form method="get" style="margin-bottom: 20px;">
                <div class="form-group">
                        <input type="hidden" name="setor_id" value="<?php echo htmlspecialchars((string)$setor_ativo_id); ?>">
                        <label for="select_posto">
                            Selecione um Posto:
                            <select id="select_posto" name="select_posto" onchange="carregarAtividades()">
                                <option value="">-- Selecione um posto --</option>
                                <?php foreach ($postos_do_setor as $post_index => $posto): ?>
                                    <option value="<?php echo $post_index; ?>">
                                        <?php echo htmlspecialchars($posto['nome'] ?? 'Posto sem nome'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
            </form>

            <!-- Container para formulário de atividades -->
            <div id="atividades-container" style="display: none; margin-top: 20px;">
                <iframe id="atividades-frame" src="" style="width: 100%; height: auto; min-height: 600px; border: none; border-radius: 6px;"></iframe>
            </div>

            <div id="no-selection" style="text-align: center; color: #6c757d; padding: 40px 20px;">
                👆 Selecione uma linha e um posto acima para gerenciar suas atividades
            </div>
        </div>

        <hr style="margin-top: 30px;">
        <p style="color: #6c757d; font-size: 14px; margin-top: 20px;">
            💡 <strong>Dica:</strong> Os postos criados aqui aparecem automaticamente no fluxo do setor.
            Use a seção acima para editar os parâmetros operacionais de cada posto.
        </p>

        <div class="buttons-container">
            <a href="index.php?linha=<?php echo urlencode((string)$setor_ativo_id); ?>" class="back-button" style="background-color: #6c757d;">
                ← Voltar ao Fluxo do Setor
            </a>
            <a href="menu.php" class="back-button" style="background-color: #495057;">
                ↑ Menu Principal
            </a>
        </div>
        <?php else: // Nenhum setor selecionado ou setor inválido ?>
            <div class="empty-message" style="margin-top: 30px;">
                <p>Por favor, selecione um setor acima para gerenciar seus postos.</p>
                <?php if (empty($setores_json)): ?>
                    <p>Ainda não há setores cadastrados. <a href="setores.php">Clique aqui para cadastrar um novo setor</a>.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Carregar dados dos postos do setor ativo
        var postosDoSetor = <?php echo json_encode($postos_do_setor ?? []); ?>;
        var setorAtivoId = <?php echo json_encode($setor_ativo_id); ?>;
        var linhaId = <?php echo json_encode($linha_selecionada_ref['id'] ?? null); ?>;

        // Detectar menu e ajustar layout
        function ajustarLayout() {
            var menu = document.querySelector('.sidebar'); // Usar a classe .sidebar
            var content = document.querySelector('.content');

            if (menu && content) {
                var menuWidth = menu.offsetWidth || 270; // Padrão para 270px se não encontrado
                var viewportWidth = window.innerWidth;

                // Ajustar com base nas media queries em styles.css
                if (viewportWidth < 480) { // Mobile
                    content.style.marginLeft = '200px'; // De styles.css
                    content.style.width = 'calc(100% - 200px)';
                } else if (viewportWidth < 768) { // Tablet
                    content.style.marginLeft = '250px'; // De styles.css
                    content.style.width = 'calc(100% - 250px)';
                } else { // Desktop
                    content.style.marginLeft = menuWidth + 'px';
                    content.style.width = 'calc(100% - ' + menuWidth + 'px)';
                }
            }
        }

        // Ajustar no carregamento
        window.addEventListener('load', ajustarLayout);

        // Ajustar ao redimensionar
        window.addEventListener('resize', function() {
            ajustarLayout();
        });

        // Observador de mutação para detectar mudanças no menu (ex: recolher/expandir)
        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                ajustarLayout();
            });

            var menu = document.querySelector('.sidebar'); // Usar a classe .sidebar
            if (menu) {
                observer.observe(menu, {
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            }
        }

        // Funções de gerenciamento de postos
        function carregarAtividades() {
            var postIndex = document.getElementById('select_posto').value;

            if (!linhaId || postIndex === '') {
                document.getElementById('atividades-container').style.display = 'none';
                document.getElementById('no-selection').style.display = 'block';
                return;
            }

            var url = 'atividades_posto.php?linha=' + encodeURIComponent(linhaId) + '&post=' + encodeURIComponent(postIndex) + '&back=postos';
            document.getElementById('atividades-frame').src = url;
            document.getElementById('atividades-container').style.display = 'block';
            document.getElementById('no-selection').style.display = 'none';
        }

        // Ajustar altura do iframe dinamicamente
        document.addEventListener('DOMContentLoaded', function() {
            ajustarLayout();

            var iframe = document.getElementById('atividades-frame');
            if (iframe) {
                iframe.onload = function() {
                    try {
                        // Attempt to get content height if same-origin policy allows
                        if (iframe.contentWindow && iframe.contentWindow.document.body) {
                            // Add a small buffer (e.g., 30px) to account for margins/padding within the iframe content
                            iframe.style.height = (iframe.contentWindow.document.body.scrollHeight + 30) + 'px';
                        } else {
                            // Fallback for cross-origin content or if contentWindow is not accessible
                            iframe.style.height = (window.innerWidth <= 768 ? '500px' : '600px');
                        }
                    } catch (e) {
                        console.warn("Could not dynamically resize iframe, falling back to fixed height:", e);
                        iframe.style.height = (window.innerWidth <= 768 ? '500px' : '600px');
                    }
                };
            }
        });

        // Adicionar feedback visual aos formulários
        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function() {
                var button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.style.opacity = '0.6';
                }
            });
        });
    </script>
</body>
</html>
