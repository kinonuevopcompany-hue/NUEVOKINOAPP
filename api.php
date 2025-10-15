<?php
ini_set('display_errors', 1); 
error_reporting(E_ALL);
header('Content-Type: application/json');
session_start();

// --- CARGADOR DE CONFIGURACIÓN DE CLIENTE ---
$client_id = $_REQUEST['client'] ?? null;
if (!$client_id) { 
    die(json_encode(['error' => 'ID de cliente no proporcionado.'])); 
}
$client_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $client_id);
$config_path = __DIR__ . '/clientes/' . $client_id . '/config.php';
if (!file_exists($config_path)) { 
    die(json_encode(['error' => 'Cliente no encontrado.'])); 
}
$config = require $config_path;

// --- CONEXIÓN A BD ---
$db_conf = $config['db'];
$dsn = "mysql:host={$db_conf['host']};dbname={$db_conf['dbname']};charset=utf8";
$db = new PDO($dsn, $db_conf['user'], $db_conf['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$uploads_dir = __DIR__ . '/uploads/' . $client_id . '/';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

$action = $_REQUEST['action'] ?? '';

// --- ACCIONES PÚBLICAS (NO REQUIEREN LOGIN) ---
if ($action === 'suggest') {
    $term = $_GET['term'] ?? '';
    $stmt = $db->prepare("SELECT DISTINCT code FROM codes WHERE code LIKE ? LIMIT 10");
    $stmt->execute([$term . '%']);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
    exit;
}
if ($action === 'search_by_code') {
    $code = $_POST['code'] ?? '';
    $stmt = $db->prepare("SELECT d.id, d.name, d.date, d.path FROM documents d JOIN codes c ON d.id = c.document_id WHERE UPPER(c.code) = UPPER(?) GROUP BY d.id");
    $stmt->execute([$code]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// --- ACCIONES DE ADMIN (REQUIEREN LOGIN) ---

// Acción de Login
if ($action === 'login') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    if (isset($config['admin']['user']) && $user === $config['admin']['user'] && password_verify($pass, $config['admin']['pass_hash'])) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['client_id'] = $client_id;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas.']);
    }
    exit;
}

// Verificación de sesión para las siguientes acciones
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['client_id'] !== $client_id) {
    die(json_encode(['error' => 'Acceso no autorizado. Por favor, inicie sesión.']));
}

switch ($action) {
    case 'list':
        $stmt = $db->query("SELECT d.id, d.name, d.date, d.path, GROUP_CONCAT(c.code) as codes FROM documents d LEFT JOIN codes c ON d.id = c.document_id GROUP BY d.id ORDER BY d.date DESC");
        $docs = array_map(function($r) { $r['codes'] = $r['codes'] ? explode(',', $r['codes']) : []; return $r; }, $stmt->fetchAll());
        echo json_encode(['data' => $docs]);
        break;

    case 'upload':
        $filename = time() . '_' . basename($_FILES['file']['name']);
        $db->prepare('INSERT INTO documents (name, date, path) VALUES (?, ?, ?)')
           ->execute([$_POST['name'], $_POST['date'], $filename]);
        $docId = $db->lastInsertId();
        $stmt = $db->prepare('INSERT INTO codes (document_id, code) VALUES (?, ?)');
        foreach (array_filter(explode("\n", $_POST['codes'])) as $code) {
            $stmt->execute([$docId, trim($code)]);
        }
        move_uploaded_file($_FILES['file']['tmp_name'], $uploads_dir . $filename);
        echo json_encode(['message' => 'Documento guardado.']);
        break;

    case 'edit':
        $id = $_POST['id'];
        $db->prepare('UPDATE documents SET name=?, date=? WHERE id=?')->execute([$_POST['name'], $_POST['date'], $id]);
        $db->prepare('DELETE FROM codes WHERE document_id=?')->execute([$id]);
        $stmt = $db->prepare('INSERT INTO codes (document_id, code) VALUES (?, ?)');
        foreach (array_filter(explode("\n", $_POST['codes'])) as $code) {
            $stmt->execute([$id, trim($code)]);
        }
        // Opcional: manejar re-subida de archivo si es necesario
        echo json_encode(['message' => 'Documento actualizado.']);
        break;

    case 'delete':
        $id = $_GET['id'];
        $path = $db->query("SELECT path FROM documents WHERE id=$id")->fetchColumn();
        if ($path) @unlink($uploads_dir . $path);
        $db->prepare('DELETE FROM codes WHERE document_id=?')->execute([$id]);
        $db->prepare('DELETE FROM documents WHERE id=?')->execute([$id]);
        echo json_encode(['message' => 'Documento eliminado.']);
        break;

    default:
        echo json_encode(['error' => 'Acción de administrador inválida.']);
        break;
}