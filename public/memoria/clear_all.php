<?php
session_start();
include 'data_store.php';

// Limpar todos os dados
$linhas_vazia = [
    ['id' => 'linha1', 'nome' => 'Linha 1', 'postos' => []],
    ['id' => 'linha2', 'nome' => 'Linha 2', 'postos' => []],
    ['id' => 'linha3', 'nome' => 'Linha 3', 'postos' => []]
];

save_json_data('linhas', $linhas_vazia);

echo '<html>
<head>
    <title>Limpeza Concluída</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; background: #f0f0f0; }
        .message { 
            background: #d4edda; 
            color: #155724; 
            padding: 20px; 
            border-radius: 8px; 
            margin: 20px auto;
            max-width: 500px;
            border: 1px solid #c3e6cb;
        }
        button { 
            padding: 10px 20px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin: 10px;
        }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="message">
        <h2>✓ Dados Limpos com Sucesso</h2>
        <p>Todas as linhas foram resetadas para o estado vazio.</p>
        <p>Total de linhas: 3 (Linha 1, 2, 3)</p>
    </div>
    <button onclick="window.location.href=\'index.php\'">Ir para o Fluxo Principal</button>
    <button onclick="window.location.href=\'diagnostico.html\'">Voltar ao Diagnóstico</button>
</body>
</html>';
?>
