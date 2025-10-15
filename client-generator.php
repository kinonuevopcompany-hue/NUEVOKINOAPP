<?php
header('Content-Type: application/json');

try {
    // 1. VALIDACIÓN DE DATOS
    $required_fields = ['client_name', 'client_id', 'db_name', 'db_user', 'db_pass', 'db_host', 'color_primary'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo '$field' es obligatorio.");
        }
    }
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("El logo es obligatorio y debe subirse correctamente.");
    }

    // 2. SANITIZACIÓN DEL ID DEL CLIENTE
    $client_id = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['client_id'])));
    if (empty($client_id)) {
        throw new Exception("El Identificador URL (ID) no es válido.");
    }

    // 3. DEFINICIÓN DE RUTAS Y VERIFICACIÓN
    $clientes_dir = __DIR__ . '/clientes/';
    $client_dir   = $clientes_dir . $client_id . '/';
    $uploads_dir  = __DIR__ . '/uploads/' . $client_id . '/';

    if (is_dir($client_dir)) {
        throw new Exception("El cliente con ID '$client_id' ya existe.");
    }

    // 4. CREACIÓN DE CARPETAS
    if (!mkdir($client_dir, 0775, true) || !mkdir($uploads_dir, 0775, true)) {
        throw new Exception("No se pudieron crear las carpetas para el cliente. Verifica los permisos del servidor.");
    }

    // 5. GESTIÓN DEL LOGO
    $logo_info = getimagesize($_FILES['logo']['tmp_name']);
    if (!$logo_info || $logo_info['mime'] !== 'image/png') {
        throw new Exception("El logo debe ser un archivo .png válido.");
    }
    $logo_path = $client_dir . 'logo.png';
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
        throw new Exception("No se pudo guardar el logo.");
    }
    
    // 6. GENERACIÓN DEL ARCHIVO config.php
    function darken_color($hex, $percent = 20) {
        $hex = ltrim($hex, '#');
        $rgb = hexdec($hex);
        $r = max(0, (($rgb >> 16) & 0xFF) * (1 - $percent / 100));
        $g = max(0, (($rgb >> 8) & 0xFF) * (1 - $percent / 100));
        $b = max(0, ($rgb & 0xFF) * (1 - $percent / 100));
        return '#' . str_pad(dechex(($r << 16) | ($g << 8) | $b), 6, '0', STR_PAD_LEFT);
    }
    
    $color_primary = $_POST['color_primary'];
    $color_hover   = darken_color($color_primary);
    
    $config_content = "<?php
// Configuración para el Cliente \"" . htmlspecialchars($_POST['client_name']) . "\"
// Generado automáticamente el " . date('Y-m-d H:i:s') . "

return [
    'db' => [
        'host'   => '" . addslashes($_POST['db_host']) . "',
        'dbname' => '" . addslashes($_POST['db_name']) . "',
        'user'   => '" . addslashes($_POST['db_user']) . "',
        'pass'   => '" . addslashes($_POST['db_pass']) . "',
        'port'   => 3306
    ],
    'branding' => [
        'client_name' => '" . addslashes($_POST['client_name']) . "',
        'logo_path'   => '../clientes/" . $client_id . "/logo.png',
        'colors' => [
            'primary'       => '" . addslashes($color_primary) . "',
            'primary_hover' => '" . addslashes($color_hover) . "',
        ]
    ]
];
";
    if (file_put_contents($client_dir . 'config.php', $config_content) === false) {
        throw new Exception("No se pudo escribir el archivo de configuración.");
    }
    
    // 7. RESPUESTA DE ÉXITO
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host     = $_SERVER['HTTP_HOST'];
    $base_uri = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
    
    echo json_encode([
        'success' => true,
        'message' => 'Cliente creado exitosamente.',
        'url'     => $protocol . $host . $base_uri . '/bc/' . $client_id . '/'
    ]);

} catch (Exception $e) {
    // MANEJO DE ERRORES
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}