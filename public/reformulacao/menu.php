<?php require_once __DIR__ . '/module_routes.php'; ?>
<?php
$rf_menu_href_prefix = $rf_menu_href_prefix ?? '';
function rf_menu_href($path) {
    global $rf_menu_href_prefix;
    if (preg_match('/^(https?:)?\/\//', (string)$path)) {
        return $path;
    }
    return $rf_menu_href_prefix . $path;
}
?>
<link rel="stylesheet" href="<?php echo rf_menu_href('styles.css'); ?>">
<style>
    .sidebar .rf-menu-group {
        margin-bottom: 8px;
    }

    .sidebar .rf-menu-subgroup {
        margin: 8px 0 10px;
    }

    .sidebar .rf-menu-toggle {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 20px;
        margin-bottom: 8px;
        padding: 8px 10px;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 6px;
        background: rgba(255, 255, 255, .08);
        color: rgba(255, 255, 255, .84);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .5px;
        text-align: left;
        text-transform: uppercase;
        cursor: pointer;
    }

    .sidebar .rf-menu-toggle:hover {
        background: rgba(255, 255, 255, .14);
        color: #fff;
    }

    .sidebar .rf-menu-subgroup > .rf-menu-toggle {
        margin-top: 8px;
        margin-bottom: 6px;
        padding: 7px 8px;
        border-color: transparent;
        background: transparent;
        color: rgba(255, 255, 255, .58);
        font-size: 11px;
        letter-spacing: .4px;
    }

    .sidebar .rf-menu-subgroup > .rf-menu-toggle:hover {
        border-color: rgba(255, 255, 255, .14);
        background: rgba(255, 255, 255, .08);
        color: rgba(255, 255, 255, .86);
    }

    .sidebar .rf-menu-toggle::after {
        content: "v";
        flex: 0 0 auto;
        font-size: 12px;
        line-height: 1;
        transition: transform .18s ease;
    }

    .sidebar .rf-menu-group.collapsed .rf-menu-toggle::after {
        transform: rotate(-90deg);
    }

    .sidebar .rf-menu-items {
        display: block;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }

    .sidebar .rf-menu-group.collapsed .rf-menu-items {
        display: none;
    }

    .sidebar .rf-menu-items li {
        margin-bottom: 8px;
    }

    .sidebar .rf-menu-subgroup > .rf-menu-items {
        padding-left: 8px;
    }
</style>

<div class="sidebar">
    <h2>REFORMULACAO</h2>
    <ul>
        <li class="rf-menu-group" data-rf-menu-group="cadastros-basicos">
            <button class="rf-menu-toggle" type="button" aria-expanded="true">Cadastros Basicos</button>
            <ul class="rf-menu-items">
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=atividades_padrao'); ?>" style="color: #1f6feb; font-weight: 700;">Atividades Padrao</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=faixas_calibradora'); ?>" style="color: #1f6feb; font-weight: 700;">Faixas da Calibradora</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=linhas'); ?>" style="color: #1f6feb; font-weight: 700;">Linhas</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=maquinas'); ?>" style="color: #1f6feb; font-weight: 700;">Maquinas</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=postos'); ?>" style="color: #1f6feb; font-weight: 700;">Postos</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=produtos_itens'); ?>" style="color: #1f6feb; font-weight: 700;">Produtos / Itens</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=setores'); ?>" style="color: #1f6feb; font-weight: 700;">Setores</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=tipos_embalagem'); ?>" style="color: #1f6feb; font-weight: 700;">Tipos de Embalagem</a></li>
                <li><a href="<?php echo rf_menu_href('cadastros-basicos/index.php?tipo=unidades'); ?>" style="color: #1f6feb; font-weight: 700;">Unidades</a></li>
            </ul>
        </li>

        <li class="rf-menu-group" data-rf-menu-group="editor-bpm">
            <button class="rf-menu-toggle" type="button" aria-expanded="true">Editor BPM</button>
            <ul class="rf-menu-items">
                <li><a href="<?php echo rf_menu_href(rf_route('editor_bpm', 'page')); ?>" style="color: #1f6feb; font-weight: 700;">Fluxos de Processo</a></li>
            </ul>
        </li>

        <li class="rf-menu-group" data-rf-menu-group="arvore-estrutura">
            <button class="rf-menu-toggle" type="button" aria-expanded="true">Arvore de Estrutura</button>
            <ul class="rf-menu-items">
                <li><a href="<?php echo rf_menu_href(rf_route('arvore_estrutura', 'page')); ?>" style="color: #1f6feb; font-weight: 700;">Cadastro da Arvore</a></li>
                <li><a href="<?php echo rf_menu_href(rf_route('arvore_estrutura', 'table')); ?>" style="color: #1f6feb; font-weight: 700;">Tabela da Arvore</a></li>
                <li><a href="<?php echo rf_menu_href(rf_route('arvore_estrutura', 'items_report')); ?>" style="color: #1f6feb; font-weight: 700;">Relatorio de Itens</a></li>
            </ul>
        </li>

        <li class="rf-menu-group" data-rf-menu-group="cronoanalise">
            <button class="rf-menu-toggle" type="button" aria-expanded="true">Cronoanalise</button>
            <ul class="rf-menu-items">
                <li class="rf-menu-group rf-menu-subgroup" data-rf-menu-group="cronoanalise-cadastros">
                    <button class="rf-menu-toggle" type="button" aria-expanded="true">Cadastros</button>
                    <ul class="rf-menu-items">
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=atividades_padrao'); ?>" style="color: #1f6feb; font-weight: 700;">Atividades Padrao</a></li>
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=estrutura_embalagem'); ?>" style="color: #1f6feb; font-weight: 700;">Estrutura embalagem</a></li>
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=linha_postos'); ?>" style="color: #1f6feb; font-weight: 700;">Linha x Postos</a></li>
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=linhas'); ?>" style="color: #1f6feb; font-weight: 700;">Linhas</a></li>
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=postos'); ?>" style="color: #1f6feb; font-weight: 700;">Postos</a></li>
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=setores'); ?>" style="color: #1f6feb; font-weight: 700;">Setores</a></li>
                    </ul>
                </li>
                <li class="rf-menu-group rf-menu-subgroup" data-rf-menu-group="cronoanalise-operacao">
                    <button class="rf-menu-toggle" type="button" aria-expanded="true">Operacao</button>
                    <ul class="rf-menu-items">
                        <li><a href="<?php echo rf_menu_href('consultar_cronoanalises.php'); ?>" style="color: #1f6feb; font-weight: 700;">Consultar Cronoanalises</a></li>
                        <li><a href="<?php echo rf_menu_href('atividades_posto.php'); ?>" style="color: #1f6feb; font-weight: 700;">Nova Cronoanalise</a></li>
                        <li><a href="<?php echo rf_menu_href('transporte.php'); ?>" style="color: #1f6feb; font-weight: 700;">Transporte</a></li>
                    </ul>
                </li>
                <li class="rf-menu-group rf-menu-subgroup" data-rf-menu-group="cronoanalise-configuracoes">
                    <button class="rf-menu-toggle" type="button" aria-expanded="true">Configuracoes</button>
                    <ul class="rf-menu-items">
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=fatores_tolerancia'); ?>" style="color: #1f6feb; font-weight: 700;">Fatores tolerancia</a></li>
                        <li><a href="<?php echo rf_menu_href('crono_cadastro.php?tipo=tipos_calculo'); ?>" style="color: #1f6feb; font-weight: 700;">Tipos de calculo</a></li>
                    </ul>
                </li>
            </ul>
        </li>

        <li class="rf-menu-group" data-rf-menu-group="calibradora-v1">
            <button class="rf-menu-toggle" type="button" aria-expanded="true">Calibradora</button>
            <ul class="rf-menu-items">
                <li><a href="<?php echo rf_menu_href('calibradora.php'); ?>" style="color: #1f6feb; font-weight: 700;">Painel Operacional</a></li>
            </ul>
        </li>

        <li class="rf-menu-group" data-rf-menu-group="calibradora-v2">
            <button class="rf-menu-toggle" type="button" aria-expanded="true">Calibradora V2</button>
            <ul class="rf-menu-items">
                <li><a href="<?php echo rf_menu_href('calibradora/views/etapa1_faixas.php'); ?>" style="color: #1f6feb; font-weight: 700;">1. Faixas de Peso</a></li>
                <li><a href="<?php echo rf_menu_href('calibradora/views/etapa2_configuracao.php'); ?>" style="color: #1f6feb; font-weight: 700;">2. Configuração de Embalagem</a></li>
                <li><a href="<?php echo rf_menu_href('calibradora/views/etapa3_registro_lote.php'); ?>" style="color: #1f6feb; font-weight: 700;">3. Registro do Lote</a></li>
                <li><a href="<?php echo rf_menu_href('calibradora/views/etapa4_distribuicao.php'); ?>" style="color: #1f6feb; font-weight: 700;">4. Distribuição do Lote</a></li>
                <li><a href="<?php echo rf_menu_href('calibradora/views/etapa5_resultado.php'); ?>" style="color: #1f6feb; font-weight: 700;">5. Resultado Operacional</a></li>
            </ul>
        </li>

        <li class="categoria">Memoria</li>
        <li><a href="<?php echo rf_menu_href('../memoria/FLUXO_TESTE02.php?setor_id=setor_1&linha_id=linha_1'); ?>">Fluxo Teste02</a></li>
        <li><a href="<?php echo rf_menu_href('../memoria/index.php'); ?>">Fluxo da Linha</a></li>
        <li><a href="<?php echo rf_menu_href('../memoria/setores.php'); ?>">Setores</a></li>
        <li><a href="<?php echo rf_menu_href('../memoria/postos.php'); ?>">Postos</a></li>
    </ul>
</div>
<script>
    (function setupRfExpandableMenu() {
        const storagePrefix = 'rf-menu-group-collapsed:';
        const sidebarScrollKey = 'rf-menu-sidebar-scroll-top';
        const sidebar = document.querySelector('.sidebar');

        function getSavedSidebarScroll() {
            try {
                return parseInt(sessionStorage.getItem(sidebarScrollKey) || '0', 10) || 0;
            } catch (error) {
                return 0;
            }
        }

        function saveSidebarScroll() {
            if (!sidebar) return;
            try {
                sessionStorage.setItem(sidebarScrollKey, String(sidebar.scrollTop || 0));
            } catch (error) {
                // Navegacao do menu continua funcionando mesmo sem storage disponivel.
            }
        }

        document.querySelectorAll('.sidebar .rf-menu-group').forEach(function(group) {
            const key = group.getAttribute('data-rf-menu-group');
            const button = group.querySelector('.rf-menu-toggle');
            if (!key || !button) return;

            function applyState(collapsed) {
                group.classList.toggle('collapsed', collapsed);
                button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                button.title = collapsed ? 'Expandir ' + button.textContent.trim() : 'Recolher ' + button.textContent.trim();
            }

            applyState(localStorage.getItem(storagePrefix + key) === '1');

            button.addEventListener('click', function() {
                const collapsed = !group.classList.contains('collapsed');
                localStorage.setItem(storagePrefix + key, collapsed ? '1' : '0');
                applyState(collapsed);
            });
        });

        if (sidebar) {
            requestAnimationFrame(function() {
                sidebar.scrollTop = getSavedSidebarScroll();
            });
            sidebar.addEventListener('scroll', saveSidebarScroll, { passive: true });
            sidebar.querySelectorAll('a[href]').forEach(function(link) {
                link.addEventListener('click', saveSidebarScroll);
            });
            window.addEventListener('beforeunload', saveSidebarScroll);
        }
    })();
</script>
