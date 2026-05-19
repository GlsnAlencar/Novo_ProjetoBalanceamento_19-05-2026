<?php
// Teste para verificar os dados sendo carregados
include 'data_store.php';

// Carregar dados
$linhas_json = load_json_data('linhas');
$linha_ativa = isset($_GET['linha']) ? $_GET['linha'] : 'linha1';

echo "<h1>Teste de Carregamento de Dados</h1>";
echo "<h2>Linhas JSON:</h2>";
echo "<pre>";
echo json_encode($linhas_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

echo "<h2>Linha Ativa: " . htmlspecialchars($linha_ativa) . "</h2>";

// Encontrar linha selecionada
$linha_selecionada = null;
foreach ($linhas_json as &$linha) {
    if ($linha['id'] === $linha_ativa) {
        $linha_selecionada = &$linha;
        break;
    }
}

if ($linha_selecionada !== null) {
    echo "<h2>Postos da Linha Ativa:</h2>";
    echo "<pre>";
    echo json_encode($linha_selecionada['postos'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    echo "<h2>Total de Postos: " . count($linha_selecionada['postos']) . "</h2>";
    
    echo "<h2>JSON para JavaScript:</h2>";
    $postos_js = [];
    foreach ($linha_selecionada['postos'] as $index => $posto) {
        $postos_js[] = [
            'index' => $index,
            'nome' => $posto['nome'],
            'detalhes' => $posto['detalhes'] ?? []
        ];
    }
    echo "<pre>";
    echo json_encode($postos_js, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    echo "<h2>JavaScript code:</h2>";
    echo "<pre>";
    echo "var postos = " . json_encode($postos_js) . ";";
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Nenhuma linha encontrada com ID: " . htmlspecialchars($linha_ativa) . "</p>";
}
?>
