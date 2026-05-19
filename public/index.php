<?php
/**
 * Entrada principal quando o servidor usa public/ como document root.
 *
 * A Reformulacao e a tela atual prioritaria. A Memoria continua preservada em:
 *   public/memoria/index.php
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'reformulacao/fluxo.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;
