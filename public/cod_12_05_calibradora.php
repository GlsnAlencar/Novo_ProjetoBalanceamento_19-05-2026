<?php
/**
 * Ponte de compatibilidade.
 *
 * A tela oficial da Reformulacao fica em:
 *   public/reformulacao/calibradora.php
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'reformulacao/calibradora.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;

