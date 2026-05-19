<?php
/**
 * SEGURANÇA - Funções de proteção contra CSRF, XSS, etc
 * 
 * @version 1.0
 * @author Sistema de Balanceamento
 */

session_start();

/**
 * Gera um token CSRF
 * 
 * @return string Token CSRF único
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retorna o token CSRF atual
 * 
 * @return string Token CSRF
 */
function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Valida o token CSRF
 * 
 * @param string $token Token a validar
 * @return bool True se válido, false caso contrário
 */
function validate_csrf_token($token) {
    return !empty($token) && hash_equals(get_csrf_token(), $token);
}

/**
 * Escapa HTML para prevenir XSS
 * 
 * @param mixed $data Dados a escapar
 * @return string|array Dados escapados
 */
function escape_html($data) {
    if (is_array($data)) {
        return array_map('escape_html', $data);
    }
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitiza entrada de texto
 * 
 * @param string $data Dados a sanitizar
 * @param int $max_length Comprimento máximo
 * @return string Dados sanitizados
 */
function sanitize_text($data, $max_length = 255) {
    $data = trim((string)$data);
    
    if (strlen($data) > $max_length) {
        $data = substr($data, 0, $max_length);
    }
    
    // Remove caracteres de controle perigosos
    $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
    
    return $data;
}

/**
 * Valida um ID (string ou número)
 * 
 * @param mixed $id ID a validar
 * @return bool True se válido
 */
function is_valid_id($id) {
    if (is_int($id)) {
        return $id > 0;
    }
    if (is_string($id)) {
        // Aceita números, letras, hífens, underscores
        return preg_match('/^[a-zA-Z0-9\-_]{1,50}$/', $id) === 1;
    }
    return false;
}

/**
 * Valida um email
 * 
 * @param string $email Email a validar
 * @return bool True se válido
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida um número (float ou int)
 * 
 * @param mixed $number Número a validar
 * @param float $min Valor mínimo (opcional)
 * @param float $max Valor máximo (opcional)
 * @return bool True se válido
 */
function is_valid_number($number, $min = null, $max = null) {
    if (!is_numeric($number)) {
        return false;
    }
    
    $num = floatval($number);
    
    if ($min !== null && $num < $min) {
        return false;
    }
    
    if ($max !== null && $num > $max) {
        return false;
    }
    
    return true;
}

/**
 * Valida se o valor existe em um array de opções permitidas
 * 
 * @param mixed $value Valor a validar
 * @param array $allowed Array de valores permitidos
 * @return bool True se válido
 */
function is_in_whitelist($value, array $allowed) {
    return in_array($value, $allowed, true);
}

/**
 * Obtém um valor POST com validação básica
 * 
 * @param string $key Chave do POST
 * @param mixed $default Valor padrão
 * @param string $type Tipo: 'text', 'number', 'email', 'id'
 * @return mixed Valor validado ou padrão
 */
function get_post($key, $default = null, $type = 'text') {
    if (!isset($_POST[$key])) {
        return $default;
    }
    
    $value = $_POST[$key];
    
    switch ($type) {
        case 'number':
            return is_valid_number($value) ? floatval($value) : $default;
        
        case 'int':
            return is_numeric($value) ? (int)$value : $default;
        
        case 'email':
            return is_valid_email($value) ? sanitize_text($value) : $default;
        
        case 'id':
            return is_valid_id($value) ? $value : $default;
        
        case 'text':
        default:
            return sanitize_text($value);
    }
}

/**
 * Obtém um valor GET com validação básica
 * 
 * @param string $key Chave do GET
 * @param mixed $default Valor padrão
 * @param string $type Tipo: 'text', 'number', 'email', 'id'
 * @return mixed Valor validado ou padrão
 */
function get_query($key, $default = null, $type = 'text') {
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    $value = $_GET[$key];
    
    switch ($type) {
        case 'number':
            return is_valid_number($value) ? floatval($value) : $default;
        
        case 'int':
            return is_numeric($value) ? (int)$value : $default;
        
        case 'email':
            return is_valid_email($value) ? sanitize_text($value) : $default;
        
        case 'id':
            return is_valid_id($value) ? $value : $default;
        
        case 'text':
        default:
            return sanitize_text($value);
    }
}

/**
 * Log de uma ação no sistema
 * 
 * @param string $action Ação realizada
 * @param string $message Mensagem
 * @param string $level 'info', 'warning', 'error'
 */
function log_action($action, $message, $level = 'info') {
    $log_dir = __DIR__ . '/../logs';
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $log_message = "[{$timestamp}] [{$level}] [{$ip}] {$action}: {$message}\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Redireciona para uma página com mensagem
 * 
 * @param string $page Página destino
 * @param array $params Parâmetros URL
 * @param string $message Mensagem (opcional)
 * @param string $type 'success', 'error', 'warning'
 */
function redirect_with_message($page, $params = [], $message = '', $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    
    $query = http_build_query($params);
    $url = $page . ($query ? '?' . $query : '');
    
    header('Location: ' . $url);
    exit;
}

/**
 * Obtém e limpa a mensagem da sessão
 * 
 * @return array ['message' => string, 'type' => string]
 */
function get_session_message() {
    $message = $_SESSION['message'] ?? '';
    $type = $_SESSION['message_type'] ?? 'info';
    
    unset($_SESSION['message'], $_SESSION['message_type']);
    
    return ['message' => $message, 'type' => $type];
}

/**
 * Renderiza um alerta HTML
 * 
 * @param string $message Mensagem
 * @param string $type 'success', 'error', 'warning', 'info'
 * @return string HTML do alerta
 */
function render_alert($message, $type = 'info') {
    if (empty($message)) {
        return '';
    }
    
    $classes = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $class = $classes[$type] ?? 'alert-info';
    $icons = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];
    $icon = $icons[$type] ?? 'ℹ️';
    
    return sprintf(
        '<div class="alert %s" role="alert">%s %s</div>',
        $class,
        $icon,
        escape_html($message)
    );
}

/**
 * Verifica se a requisição é POST
 * 
 * @return bool
 */
function is_post_request() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Verifica se a requisição é AJAX
 * 
 * @return bool
 */
function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

?>
