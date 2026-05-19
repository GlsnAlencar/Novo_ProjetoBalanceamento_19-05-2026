<?php
/**
 * MÓDULO CALIBRADORA - Tela Principal
 * 
 * Hub central do módulo isolado Calibradora.
 * Todas as funcionalidades são completamente isoladas do módulo legado.
 */
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Calibradora</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }

        .header h1 {
            font-size: 48px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            font-size: 18px;
            opacity: 0.9;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .module-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }

        .module-card h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 24px;
        }

        .module-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .module-card a {
            display: inline-block;
            padding: 12px 25px;
            background-color: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .module-card a:hover {
            background-color: #764ba2;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            background-color: #d4edda;
            color: #155724;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stage-number {
            display: inline-block;
            width: 32px;
            height: 32px;
            background-color: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 32px;
            font-weight: bold;
            margin-right: 10px;
        }

        .footer {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            margin-top: 50px;
        }

        .footer h3 {
            margin-bottom: 15px;
        }

        .footer p {
            margin-bottom: 10px;
            line-height: 1.8;
        }

        .breadcrumb {
            color: white;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .breadcrumb a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .info-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="breadcrumb">
        <a href="../">← Voltar para REFORMULAÇÃO</a>
    </div>

    <div class="header">
        <h1>📦 Módulo Calibradora</h1>
        <p>Motor de distribuição operacional da calibradora</p>
        <span class="info-badge">Ambiente isolado - REFORMULAÇÃO</span>
    </div>

    <div class="modules-grid">
        <!-- ETAPA 1 -->
        <div class="module-card">
            <span class="status-badge">ETAPA 1</span>
            <h2><span class="stage-number">1</span>Faixas de Peso</h2>
            <p>Cadastre o conjunto fixo de faixas da calibradora. Defina intervalos de peso para cada categoria de saída.</p>
            <p style="font-size: 12px; color: #999;">
                <strong>Funções:</strong> Criar, editar e listar faixas de peso com validação de sobreposição.
            </p>
            <a href="views/etapa1_faixas.php">Acessar</a>
        </div>

        <!-- ETAPA 2 -->
        <div class="module-card">
            <span class="status-badge">ETAPA 2</span>
            <h2><span class="stage-number">2</span>Configuração de Embalamento</h2>
            <p>Defina o Produto Operacional gerado por cada faixa de peso. Mapeie GR para produtos específicos.</p>
            <p style="font-size: 12px; color: #999;">
                <strong>Funções:</strong> Criar configurações, adicionar mapeamentos de faixas para produtos.
            </p>
            <a href="views/etapa2_configuracao.php">Acessar</a>
        </div>

        <!-- ETAPA 3 -->
        <div class="module-card">
            <span class="status-badge">ETAPA 3</span>
            <h2><span class="stage-number">3</span>Registro do Lote</h2>
            <p>Registre os lotes processados pela calibradora. Associe informações de programa, partida e produtor.</p>
            <p style="font-size: 12px; color: #999;">
                <strong>Funções:</strong> Criar, salvar em rascunho ou finalizar registros de lote.
            </p>
            <a href="views/etapa3_registro_lote.php">Acessar</a>
        </div>

        <!-- ETAPA 4 -->
        <div class="module-card">
            <span class="status-badge">ETAPA 4</span>
            <h2><span class="stage-number">4</span>Distribuição do Lote</h2>
            <p>Informe os percentuais/pesos produzidos pela calibradora. Registre gramas por faixa com cálculo automático de percentuais.</p>
            <p style="font-size: 12px; color: #999;">
                <strong>Funções:</strong> Entrada manual de gramas, validação de 100%, salvamento de distribuição.
            </p>
            <a href="views/etapa4_distribuicao.php">Acessar</a>
        </div>

        <!-- ETAPA 5 -->
        <div class="module-card">
            <span class="status-badge">ETAPA 5</span>
            <h2><span class="stage-number">5</span>Resultado Operacional</h2>
            <p>Gere o perfil operacional do lote. Visualize os percentuais agregados por Produto Operacional.</p>
            <p style="font-size: 12px; color: #999;">
                <strong>Funções:</strong> Resumo visual de produtos, preparação para integração futura.
            </p>
            <a href="views/etapa5_resultado.php">Acessar</a>
        </div>
    </div>

    <div class="footer">
        <h3>📋 Sobre este Módulo</h3>
        <p>
            <strong>Isolamento Total:</strong> O módulo Calibradora é totalmente isolado dos módulos legados (Cronoanálise, Árvore de Estrutura, Balanceamento).<br>
            <strong>Responsabilidade:</strong> Cadastro de faixas, configuração de embalamento, registro e distribuição de lotes.<br>
            <strong>Dados:</strong> Armazenados em /data/reformulacao/calibradora/ em arquivos JSON separados.<br>
            <strong>Preparação Futura:</strong> Estrutura preparada para integração com outros módulos através de referências e IDs.
        </p>
        <p style="margin-top: 20px; font-size: 12px; opacity: 0.8;">
            Última atualização: <?php echo date('d/m/Y H:i:s'); ?>
        </p>
    </div>
</div>
</body>
</html>
