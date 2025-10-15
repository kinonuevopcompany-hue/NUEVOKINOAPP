<?php
header('Content-Type: application/json');
try {
    // Lista de campos requeridos en el formulario
    $required = ['client_name', 'client_id', 'admin_user', 'admin_pass', 'db_name', 'db_user', 'db_pass', 'db_host', 'color_primary'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo '$field' es obligatorio.");
        }
    }
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("El logo es obligatorio.");
    }

    // Limpia el ID del cliente para que sea seguro para URL
    $client_id = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['client_id'])));
    if (empty($client_id)) {
        throw new Exception("El ID de cliente no es válido.");
    }

    // Define las rutas y comprueba si el cliente ya existe
    $client_dir = __DIR__ . '/clientes/' . $client_id . '/';
    if (is_dir($client_dir)) {
        throw new Exception("El cliente con ID '$client_id' ya existe.");
    }

    // Crea las carpetas de configuración y de uploads
    mkdir($client_dir, 0775, true);
    mkdir(__DIR__ . '/uploads/' . $client_id . '/', 0775, true);

    // Mueve el logo a la carpeta del cliente
    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $client_dir . 'logo.png')) {
        throw new Exception("No se pudo guardar el logo.");
    }
    
    // Función para oscurecer el color para el efecto hover del botón
    function darken_color($h, $p=20){$h=ltrim($h,'#');$c=hexdec($h);$r=max(0,($c>>16&0xFF)*(1-$p/100));$g=max(0,($c>>8&0xFF)*(1-$p/100));$b=max(0,($c&0xFF)*(1-$p/100));return'#'.str_pad(dechex($r<<16|$g<<8|$b),6,'0',STR_PAD_LEFT);}
    $color_primary = $_POST['color_primary']; 
    $color_hover = darken_color($color_primary);
    
    // Crea un hash seguro para la contraseña del admin
    $admin_pass_hash = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
    
    // Genera el contenido del archivo de configuración
    $config_content = "<?php
// Configuración para el Cliente \"" . htmlspecialchars($_POST['client_name']) . "\"
return [
    'db' => ['host'=>'" . addslashes($_POST['db_host']) . "','dbname'=>'" . addslashes($_POST['db_name']) . "','user'=>'" . addslashes($_POST['db_user']) . "','pass'=>'" . addslashes($_POST['db_pass']) . "'],
    'branding' => ['client_name'=>'" . addslashes($_POST['client_name']) . "','logo_path'=>'../clientes/" . $client_id . "/logo.png','colors'=>['primary'=>'" . $color_primary . "','primary_hover'=>'" . $color_hover . "']],
    'admin' => ['user'=>'" . addslashes($_POST['admin_user']) . "','pass_hash'=>'" . $admin_pass_hash . "']
];";
    
    // Escribe el archivo de configuración
    file_put_contents($client_dir . 'config.php', $config_content);
    
    // Genera las URLs de respuesta
    $protocol = (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?"https":"http://";
    $host = $_SERVER['HTTP_HOST']; 
    $base_uri = rtrim(dirname($_SERVER['REQUEST_URI']),'/\\');
    
    // Envía la respuesta de éxito
    echo json_encode([
        'success' => true,
        'public_url' => $protocol . $host . $base_uri . '/bc/' . $client_id . '/',
        'admin_url' => $protocol . $host . $base_uri . '/admin/' . $client_id . '/'
    ]);
} catch (Exception $e) {
    // Envía la respuesta de error si algo falla
    http_response_code(400); 
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}