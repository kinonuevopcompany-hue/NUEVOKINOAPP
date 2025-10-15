<?php
// --- CARGADOR DE CLIENTE ---
$client_id = isset($_GET['client']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['client']) : null;
if (!$client_id) {
    die("Cliente no especificado.");
}
$config_path = __DIR__ . '/../clientes/' . $client_id . '/config.php';
if (!file_exists($config_path)) {
    http_response_code(404);
    die("Cliente no encontrado.");
}
$config    = require $config_path;
$branding  = $config['branding'];
// --- FIN CARGADOR ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Buscador – <?php echo htmlspecialchars($branding['client_name']); ?></title>
  <style>
    :root {
      --color-primary: <?php echo $branding['colors']['primary']; ?>;
      --color-primary-hover: <?php echo $branding['colors']['primary_hover']; ?>;
    }
    .bg-primary { background-color: var(--color-primary); }
    .hover\:bg-primary-hover:hover { background-color: var(--color-primary-hover); }
    .focus\:ring-primary:focus { --tw-ring-color: var(--color-primary); }
    .text-primary { color: var(--color-primary); }
    .relative { position: relative; }
    #suggestions {
      position: absolute; top: 100%; left: 0; right: 0; background: white;
      border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 .375rem .375rem;
      max-height: 12rem; overflow-y: auto; display: none; z-index: 10;
    }
    #suggestions div:hover { background-color: #f3f4f6; }
  </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-start p-4 sm:p-6">
  <div class="w-full max-w-3xl bg-white rounded-2xl shadow-lg">
    <div class="px-6 pt-6 pb-4 text-center">
      <img src="<?php echo htmlspecialchars($branding['logo_path']); ?>" alt="Logo <?php echo htmlspecialchars($branding['client_name']); ?>" class="mx-auto h-20 sm:h-24 mb-6" />
      <p class="text-gray-800 text-lg mb-3 font-medium">Estimados clientes y autoridades competentes:</p>
      <p class="text-gray-700 text-base mb-4 leading-relaxed italic">Utilice esta herramienta para consultar las declaraciones de importación de nuestros productos.</p>
    </div>
    <div class="border-t p-6 sm:p-8">
      <h2 class="text-2xl sm:text-3xl font-semibold mb-4">Búsqueda por Código</h2>
      <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 mb-4">
        <div class="relative flex-1">
          <input id="codeInput" type="text" placeholder="Ingrese el código del producto" class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-primary"/>
          <div id="suggestions"></div>
        </div>
        <button id="btnCodeSearch" class="w-full sm:w-auto bg-primary hover:bg-primary-hover text-white px-6 py-2 rounded-lg transition">Buscar</button>
        <button id="btnCodeClear" class="w-full sm:w-auto bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg transition">Limpiar</button>
      </div>
      <div id="code-alert" class="mb-4 text-primary font-medium"></div>
      <div id="results-code" class="space-y-4"></div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const apiClient = `../api.php?client=<?php echo $client_id; ?>`;
      const codeInput = document.getElementById('codeInput');
      const suggestions = document.getElementById('suggestions');
      const alertBox = document.getElementById('code-alert');
      const results = document.getElementById('results-code');
      let timeoutId;

      codeInput.addEventListener('input', () => {
        clearTimeout(timeoutId);
        const term = codeInput.value.trim().toUpperCase();
        if (!term) { suggestions.style.display = 'none'; return; }
        timeoutId = setTimeout(() => {
          fetch(`${apiClient}&action=suggest&term=${encodeURIComponent(term)}`)
            .then(r => r.json()).then(arr => {
              if (!arr || !arr.length) { suggestions.style.display = 'none'; return; }
              suggestions.innerHTML = arr.map(c => `<div class="px-4 py-2 cursor-pointer" data-code="${c}">${c}</div>`).join('');
              suggestions.style.display = 'block';
            }).catch(() => suggestions.style.display = 'none');
        }, 250);
      });

      suggestions.addEventListener('click', e => {
        if (e.target.dataset.code) {
          codeInput.value = e.target.dataset.code;
          suggestions.style.display = 'none';
          doCodeSearch();
        }
      });

      const doCodeSearch = () => {
        const code = codeInput.value.trim().toUpperCase();
        alertBox.innerText = '';
        if (!code) return;
        results.innerHTML = '<p class="text-gray-500">Buscando...</p>';
        
        const formData = new FormData();
        formData.append('action', 'search_by_code');
        formData.append('code', code);
        
        fetch(apiClient, { method: 'POST', body: formData })
          .then(r => r.json()).then(items => {
            if (items.error) { throw new Error(items.error); }
            if (!items.length) {
              results.innerHTML = `<p class="text-red-500">No se encontraron documentos para el código “${code}”.</p>`;
              return;
            }
            results.innerHTML = items.map(d => `
              <div class="border rounded-lg p-4 bg-white shadow-sm">
                <h3 class="font-semibold text-lg truncate">${d.name}</h3>
                <p class="text-sm text-gray-600 mt-1">${d.date}</p>
                <a href="../uploads/<?php echo $client_id; ?>/${d.path}" target="_blank" class="text-primary hover:underline mt-2 inline-block font-semibold">Ver PDF</a>
              </div>`).join('');
          }).catch(err => {
            alertBox.innerText = 'Error de conexión con el servidor.';
            results.innerHTML = '';
          });
      };
      
      document.getElementById('btnCodeSearch').onclick = doCodeSearch;
      codeInput.addEventListener('keypress', e => { if (e.key === 'Enter') doCodeSearch(); });
      document.getElementById('btnCodeClear').onclick = () => {
        codeInput.value = ''; alertBox.innerText = ''; results.innerHTML = ''; suggestions.style.display = 'none';
      };
      document.addEventListener('click', (e) => {
        if (!suggestions.contains(e.target) && e.target !== codeInput) {
            suggestions.style.display = 'none';
        }
      });
    });
  </script>
</body>
</html>