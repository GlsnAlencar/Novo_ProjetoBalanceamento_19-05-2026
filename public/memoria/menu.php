<?php $menu_base = $menu_base ?? ''; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($menu_base); ?>styles.css">

<div class="sidebar">
    <h2>Sistema de Balanceamento</h2>
    <ul>
        <li class="categoria">1 - Reformulacao</li>
        <li class="sub-categoria">Fluxos isolados</li>
        <li><a href="../reformulacao/fluxo.php" style="color: #1f6feb; font-weight: 700;">REFORMULACAO - Editor BPM</a></li>
        <li><a href="../reformulacao/calibradora.php" style="color: #1f6feb; font-weight: 700;">REFORMULACAO - Calibradora</a></li>

        <li class="categoria">2 - Memoria</li>
        <li class="categoria">Parametros</li>

        <li class="sub-categoria">Linha</li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>setores.php">Setores</a></li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>postos.php">Postos</a></li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>fluxos.php" style="color: #ff6f00; font-weight: 600;">Fluxos/Conexoes</a></li>

        <li class="sub-categoria">Cadastros</li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>unidades.php">Unidades</a></li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>transporte.php">Transporte</a></li>

        <li class="categoria">Estrutura da Linha</li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>index.php">Fluxo da Linha</a></li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>fluxo_teste01.php?setor_id=setor_1&linha_id=linha_1">Fluxo Teste01 (Editor)</a></li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>FLUXO_TESTE02.php?setor_id=setor_1&linha_id=linha_1">Fluxo Teste02 (Memoria)</a></li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>configuracao_linha.php">Configuracao da Linha</a></li>

        <li class="categoria">Balanceamento</li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>painel_balanceamento.php">Painel de Balanceamento</a></li>

        <li class="categoria">Utilitarios</li>
        <li><a href="<?php echo htmlspecialchars($menu_base); ?>teste_calculos.php">Teste de Calculos</a></li>
    </ul>
</div>
