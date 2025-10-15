<?php
session_start();
$client_id = $_GET['client'] ?? null;
if (!$client_id) die("Cliente no especificado.");
$config_path = __DIR__ . '/../clientes/' . $client_id . '/config.php';
if (!file_exists($config_path)) die("Cliente no encontrado.");
$config = require $config_path;
$branding = $config['branding'];

$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true && $_SESSION['client_id'] === $client_id;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – <?php echo htmlspecialchars($branding['client_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>:root{--c-pri:<?php echo $branding['colors']['primary'];?>;--c-pri-h:<?php echo $branding['colors']['primary_hover'];?>;}.bg-primary{background-color:var(--c-pri);}.hover\:bg-primary-hover:hover{background-color:var(--c-pri-h);}.focus\:ring-primary:focus{--tw-ring-color:var(--c-pri);}.text-primary{color:var(--c-pri);}.tab.active{border-bottom-color:var(--c-pri);color:var(--c-pri);}</style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php if (!$is_logged_in): ?>
    <div id="login-container" class="flex items-center justify-center h-screen">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
            <img src="<?php echo htmlspecialchars($branding['logo_path']); ?>" alt="Logo" class="mx-auto h-20 mb-6">
            <h1 class="text-2xl font-bold text-center mb-4">Acceso de Administración</h1>
            <form id="login-form">
                <input type="text" id="username" placeholder="Usuario" required class="w-full border rounded px-3 py-2 mb-3">
                <input type="password" id="password" placeholder="Contraseña" required class="w-full border rounded px-3 py-2 mb-4">
                <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white font-bold py-2 px-4 rounded-lg">Ingresar</button>
            </form>
            <p id="login-error" class="text-red-500 text-center mt-4 hidden"></p>
        </div>
    </div>
<?php else: ?>
    <div id="admin-panel" class="p-4 sm:p-6">
        <div class="w-full max-w-5xl mx-auto bg-white rounded-2xl shadow-lg">
            <header class="flex justify-between items-center p-4 border-b">
                <img src="<?php echo htmlspecialchars($branding['logo_path']); ?>" alt="Logo" class="h-12">
                <h1 class="text-xl font-bold text-gray-700">Panel de Gestión de Documentos</h1>
            </header>
            <nav class="border-b"><ul id="tabs" class="flex"><li class="tab active cursor-pointer p-4" data-tab="list">Consultar</li><li class="tab cursor-pointer p-4" data-tab="upload">Subir / Editar</li></ul></nav>
            <main class="p-6">
                <div id="tab-list" class="tab-content"><h2 class="text-xl font-semibold mb-4">Mis Documentos</h2><div id="results-list"></div></div>
                <div id="tab-upload" class="tab-content hidden"><h2 class="text-xl font-semibold mb-4">Subir o Editar Documento</h2>
                    <form id="form-upload" class="space-y-4">
                        <input type="hidden" id="docId" name="id">
                        <div><label class="block">Nombre</label><input type="text" id="name" name="name" required class="w-full border rounded px-3 py-2"></div>
                        <div><label class="block">Fecha</label><input type="date" id="date" name="date" required class="w-full border rounded px-3 py-2"></div>
                        <div><label class="block">Archivo PDF</label><input type="file" id="file" name="file" accept="application/pdf" class="w-full"></div>
                        <div><label class="block">Códigos (uno por línea)</label><textarea id="codes" name="codes" rows="4" class="w-full border rounded px-3 py-2"></textarea></div>
                        <button type="submit" class="bg-primary hover:bg-primary-hover text-white font-bold py-2 px-4 rounded">Guardar Documento</button>
                    </form>
                </div>
            </main>
        </div>
    </div>
<?php endif; ?>

<script>
    const apiClient = `../api.php?client=<?php echo $client_id; ?>`;
    
    <?php if (!$is_logged_in): ?>
    document.getElementById('login-form').addEventListener('submit', e => {
        e.preventDefault();
        const user = document.getElementById('username').value;
        const pass = document.getElementById('password').value;
        const errorEl = document.getElementById('login-error');
        
        const fd = new FormData();
        fd.append('action', 'login');
        fd.append('user', user);
        fd.append('pass', pass);
        
        fetch(apiClient, { method: 'POST', body: fd })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    errorEl.textContent = data.error || 'Error al iniciar sesión.';
                    errorEl.classList.remove('hidden');
                }
            });
    });
    <?php else: ?>
    
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.add('hidden'));
            tab.classList.add('active');
            document.getElementById(`tab-${tab.dataset.tab}`).classList.remove('hidden');
        });
    });

    const resultsList = document.getElementById('results-list');
    const form = document.getElementById('form-upload');

    async function refreshList() {
        const res = await fetch(`${apiClient}&action=list`);
        const data = await res.json();
        if (data.error) { alert(data.error); return; }
        
        resultsList.innerHTML = '';
        if (!data.data.length) {
            resultsList.innerHTML = '<p class="text-gray-500">No tienes documentos subidos.</p>';
            return;
        }
        
        data.data.forEach(doc => {
            const el = document.createElement('div');
            el.className = 'border rounded p-4 mb-3';
            el.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="font-bold">${doc.name}</h3>
                        <p class="text-sm text-gray-600">${doc.date} | ${doc.path}</p>
                        <p class="text-xs text-gray-500 mt-2">Códigos: ${(doc.codes || []).join(', ')}</p>
                    </div>
                    <div>
                        <button class="edit-btn text-sm bg-yellow-500 text-white p-1 rounded" data-id="${doc.id}">Editar</button>
                        <button class="delete-btn text-sm bg-red-600 text-white p-1 rounded" data-id="${doc.id}">Eliminar</button>
                    </div>
                </div>
            `;
            resultsList.appendChild(el);
            el.querySelector('.edit-btn').addEventListener('click', () => editDoc(doc));
            el.querySelector('.delete-btn').addEventListener('click', () => deleteDoc(doc.id));
        });
    }

    function editDoc(doc) {
        document.querySelector('[data-tab="upload"]').click();
        form.docId.value = doc.id;
        form.name.value = doc.name;
        form.date.value = doc.date;
        form.codes.value = (doc.codes || []).join('\n');
    }

    async function deleteDoc(id) {
        if (!confirm('¿Estás seguro de que quieres eliminar este documento?')) return;
        const res = await fetch(`${apiClient}&action=delete&id=${id}`);
        const data = await res.json();
        alert(data.message || data.error);
        refreshList();
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const action = form.docId.value ? 'edit' : 'upload';
        const fd = new FormData(form);
        fd.append('action', action);

        const res = await fetch(apiClient, { method: 'POST', body: fd });
        const data = await res.json();
        alert(data.message || data.error);
        
        form.reset();
        form.docId.value = '';
        document.querySelector('[data-tab="list"]').click();
        refreshList();
    });
    
    refreshList();
    <?php endif; ?>
</script>
</body>
</html>