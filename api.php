<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// --- CARGADOR DE CONFIGURACIÓN DE CLIENTE ---
$client_id = isset($_REQUEST['client']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_REQUEST['client']) : null;
if (!$client_id) {
    echo json_encode(['error' => 'Identificador de cliente no proporcionado.']);
    exit;
}

$config_path = __DIR__ . '/clientes/' . $client_id . '/config.php';
if (!file_exists($config_path)) {
    echo json_encode(['error' => 'Configuración para el cliente no encontrada.']);
    exit;
}
$config = require $config_path;

// --- CONEXIÓN A BASE DE DATOS DEL CLIENTE ---
$db_config = $config['db'];
$dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['dbname']};charset=utf8";
try {
    $db = new PDO($dsn, $db_config['user'], $db_config['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de conexión a la base de datos del cliente.']);
    exit;
}

// Directorio de subidas específico para el cliente
$uploads_dir = __DIR__ . '/uploads/' . $client_id . '/';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'suggest':
        $term = trim($_GET['term'] ?? '');
        if ($term === '') {
            echo json_encode([]);
            exit;
        }
        $stmt = $db->prepare("SELECT DISTINCT code FROM codes WHERE code LIKE ? ORDER BY code ASC LIMIT 10");
        $stmt->execute([$term . '%']);
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        break;

    case 'search_by_code':
        $code = trim($_POST['code'] ?? '');
        if (!$code) {
            echo json_encode([]);
            exit;
        }
        $stmt = $db->prepare("\n            SELECT d.id, d.name, d.date, d.path, GROUP_CONCAT(c2.code SEPARATOR '\n') AS codes\n            FROM documents d\n            JOIN codes c1 ON d.id = c1.document_id\n            LEFT JOIN codes c2 ON d.id = c2.document_id\n            WHERE UPPER(c1.code) = UPPER(?)\n            GROUP BY d.id\n        ");
        $stmt->execute([$code]);
        $rows = $stmt->fetchAll();
        $docs = array_map(function($r){
            return [
                'id'    => (int)$r['id'],
                'name'  => $r['name'],
                'date'  => $r['date'],
                'path'  => $r['path'],
                'codes' => $r['codes'] ? explode("\n", $r['codes']) : []
            ];
        }, $rows);
        echo json_encode($docs);
        break;
    
    // Aquí se añadirían las demás acciones (upload, list, edit, delete) para el panel de administración (index.html)
    // Cada una deberá usar la variable $client_id para saber en qué BD operar y $uploads_dir para los archivos.

    default:
        echo json_encode(['error' => 'Acción inválida.']);
        break;
}