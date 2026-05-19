<?php
/**
 * Entrada principal do projeto.
 *
 * A Reformulacao e o trabalho atual prioritario; a Memoria segue acessivel
 * pelos caminhos de compatibilidade em public/ e por public/memoria/.
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'public/reformulacao/fluxo.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;

