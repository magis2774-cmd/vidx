<?php
// ============================================
// DOODSTREAM FILE MANAGER PRO - CORREGIDO
// ============================================

class DoodStreamAPI {
    private $api_key;
    private $base_url = 'https://doodapi.co/api';
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    private function request($endpoint, $params = []) {
        $params['key'] = $this->api_key;
        $url = $this->base_url . $endpoint . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['status' => 500, 'msg' => 'Error CURL: ' . $error];
        }
        
        return json_decode($response, true) ?? ['status' => $http_code, 'msg' => 'Respuesta inválida'];
    }
    
    public function getAccountInfo() {
        return $this->request('/account/info');
    }
    
    public function listFiles($page = 1, $per_page = 20, $fld_id = '0') {
        $params = ['page' => $page, 'per_page' => $per_page];
        if ($fld_id != '0') $params['fld_id'] = $fld_id;
        return $this->request('/file/list', $params);
    }
    
    public function listFolders($fld_id = '0') {
        return $this->request('/folder/list', ['fld_id' => $fld_id, 'only_folders' => 1]);
    }
    
    public function createFolder($name, $parent_id = '0') {
        return $this->request('/folder/create', ['name' => $name, 'parent_id' => $parent_id]);
    }
    
    public function remoteUpload($url, $fld_id = '0', $title = '') {
        $params = ['url' => $url];
        if ($fld_id != '0') $params['fld_id'] = $fld_id;
        if ($title) $params['new_title'] = $title;
        return $this->request('/upload/url', $params);
    }
    
    public function renameFile($file_code, $title) {
        return $this->request('/file/rename', ['file_code' => $file_code, 'title' => $title]);
    }
    
    public function moveFile($file_code, $fld_id) {
        return $this->request('/file/move', ['file_code' => $file_code, 'fld_id' => $fld_id]);
    }
    
    public function getFileInfo($file_code) {
        return $this->request('/file/info', ['file_code' => $file_code]);
    }
}

// ============================================
// FUNCIONES DE FORMATO
// ============================================
function formatBytes($bytes) {
    if (!is_numeric($bytes)) return '0 B';
    $bytes = (float)$bytes;
    if ($bytes === 0.0) return '0 B';
    
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function formatDuration($seconds) {
    $seconds = (int)$seconds;
    if ($seconds === 0) return '00:00';
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf("%02d:%02d", $minutes, $secs);
}

// ============================================
// PROCESAR PETICIONES AJAX
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $api_key = $_POST['api_key'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!$api_key) {
        echo json_encode(['success' => false, 'message' => 'API Key requerida']);
        exit;
    }
    
    $api = new DoodStreamAPI($api_key);
    
    switch ($action) {
        case 'account_info':
            $result = $api->getAccountInfo();
            echo json_encode(['success' => $result['status'] == 200, 'data' => $result]);
            break;
            
        case 'list_files':
            $page = $_POST['page'] ?? 1;
            $folder = $_POST['folder_id'] ?? '0';
            $result = $api->listFiles($page, 20, $folder);
            echo json_encode(['success' => $result['status'] == 200, 'data' => $result]);
            break;
            
        case 'list_folders':
            $result = $api->listFolders('0');
            echo json_encode(['success' => $result['status'] == 200, 'data' => $result]);
            break;
            
        case 'create_folder':
            $name = $_POST['name'] ?? '';
            $parent = $_POST['parent_id'] ?? '0';
            if (!$name) {
                echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
                break;
            }
            $result = $api->createFolder($name, $parent);
            echo json_encode([
                'success' => $result['status'] == 200,
                'message' => $result['status'] == 200 ? 'Carpeta creada' : 'Error',
                'data' => $result
            ]);
            break;
            
        case 'remote_upload':
            $url = $_POST['url'] ?? '';
            $folder = $_POST['folder_id'] ?? '0';
            $title = $_POST['title'] ?? '';
            if (!$url) {
                echo json_encode(['success' => false, 'message' => 'URL requerida']);
                break;
            }
            $result = $api->remoteUpload($url, $folder, $title);
            echo json_encode([
                'success' => $result['status'] == 200,
                'message' => $result['status'] == 200 ? 'Subida iniciada' : 'Error',
                'data' => $result
            ]);
            break;
            
        case 'rename_file':
            $file_code = $_POST['file_code'] ?? '';
            $title = $_POST['title'] ?? '';
            if (!$file_code || !$title) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                break;
            }
            $result = $api->renameFile($file_code, $title);
            echo json_encode([
                'success' => $result['status'] == 200,
                'message' => $result['status'] == 200 ? 'Archivo renombrado' : 'Error',
                'data' => $result
            ]);
            break;
            
        case 'move_file':
            $file_code = $_POST['file_code'] ?? '';
            $folder = $_POST['folder_id'] ?? '0';
            if (!$file_code) {
                echo json_encode(['success' => false, 'message' => 'File code requerido']);
                break;
            }
            $result = $api->moveFile($file_code, $folder);
            echo json_encode([
                'success' => $result['status'] == 200,
                'message' => $result['status'] == 200 ? 'Archivo movido' : 'Error',
                'data' => $result
            ]);
            break;
            
        case 'file_info':
            $file_code = $_POST['file_code'] ?? '';
            if (!$file_code) {
                echo json_encode(['success' => false, 'message' => 'File code requerido']);
                break;
            }
            $result = $api->getFileInfo($file_code);
            echo json_encode(['success' => $result['status'] == 200, 'data' => $result]);
            break;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DoodStream Manager</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        /* API Section */
        .api-section {
            background: #f8f9fa;
            padding: 20px;
            display: flex;
            gap: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .api-section input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-family: monospace;
        }
        
        .api-section button {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .api-section button:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        /* Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 25px;
            background: #f8f9fa;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .info-card h3 {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .info-value {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
        }
        
        /* Toolbar */
        .toolbar {
            display: flex;
            gap: 15px;
            padding: 20px 25px;
            background: white;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; transform: translateY(-2px); }
        
        .btn-success { background: #48bb78; color: white; }
        .btn-success:hover { background: #38a169; transform: translateY(-2px); }
        
        .btn-info { background: #4299e1; color: white; }
        .btn-info:hover { background: #3182ce; transform: translateY(-2px); }
        
        .btn-outline {
            background: white;
            border: 2px solid #e2e8f0;
            color: #4a5568;
        }
        .btn-outline:hover { border-color: #667eea; color: #667eea; }
        
        /* Search */
        .search-box {
            flex: 1;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* Folder Navigation */
        .folder-nav {
            padding: 15px 25px;
            background: #f8fafc;
            display: flex;
            gap: 15px;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .folder-nav select {
            flex: 1;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            min-width: 250px;
        }
        
        /* Files Grid */
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 25px;
            min-height: 400px;
        }
        
        .file-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            cursor: context-menu;
        }
        
        .file-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
            border-color: #667eea;
        }
        
        .file-thumbnail {
            height: 180px;
            background: #f1f5f9;
            position: relative;
            overflow: hidden;
        }
        
        .file-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .file-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .file-info {
            padding: 20px;
        }
        
        .file-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 16px;
            max-height: 44px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .file-meta {
            display: flex;
            gap: 15px;
            color: #718096;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .file-meta i {
            margin-right: 5px;
            color: #667eea;
        }
        
        /* Context Menu */
        .context-menu {
            position: fixed;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 1px solid #e2e8f0;
            z-index: 10000;
            min-width: 250px;
            overflow: hidden;
        }
        
        .context-menu-header {
            padding: 12px 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: #2d3748;
        }
        
        .context-menu-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .context-menu-item:hover {
            background: #f1f5f9;
        }
        
        .context-menu-item i {
            width: 20px;
            color: #667eea;
        }
        
        .context-menu-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 5px 0;
        }
        
        .context-menu-item.danger { color: #e53e3e; }
        .context-menu-item.danger i { color: #e53e3e; }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 25px;
            background: #f8fafc;
            border-top: 2px solid #e2e8f0;
        }
        
        .page-info {
            font-weight: 600;
            color: #4a5568;
        }
        
        /* Loading */
        .loading-spinner {
            text-align: center;
            padding: 50px;
            color: #718096;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f1f5f9;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 25px;
            color: #a0aec0;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-cloud-upload-alt"></i> DoodStream Manager</h1>
            <p>Gestiona tus archivos de forma sencilla</p>
        </div>
        
        <!-- API Section -->
        <div class="api-section">
            <input type="text" id="apiKey" placeholder="Tu API Key" value="<?php echo htmlspecialchars($_GET['api_key'] ?? ''); ?>">
            <button onclick="connect()"><i class="fas fa-plug"></i> Conectar</button>
        </div>
        
        <!-- Info Cards -->
        <div id="infoGrid" class="info-grid" style="display: none;"></div>
        
        <!-- Toolbar -->
        <div id="toolbar" class="toolbar" style="display: none;">
            <button class="btn btn-primary" onclick="showUploadModal()">
                <i class="fas fa-plus-circle"></i> Subir Archivo
            </button>
            <button class="btn btn-success" onclick="showFolderModal()">
                <i class="fas fa-folder-plus"></i> Nueva Carpeta
            </button>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Buscar archivos...">
                <button class="btn btn-outline" onclick="searchFiles()">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            <button class="btn btn-outline" onclick="refreshFiles()">
                <i class="fas fa-sync-alt"></i> Actualizar
            </button>
        </div>
        
        <!-- Folder Navigation -->
        <div id="folderNav" class="folder-nav" style="display: none;">
            <i class="fas fa-folder-open" style="color: #667eea;"></i>
            <select id="folderSelect" onchange="changeFolder()">
                <option value="0">📁 Raíz</option>
            </select>
        </div>
        
        <!-- Files Container -->
        <div id="filesContainer">
            <div class="empty-state">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Conecta tu API Key</h3>
                <p>Ingresa tu API key y haz clic en "Conectar"</p>
            </div>
        </div>
        
        <!-- Pagination -->
        <div id="pagination" class="pagination" style="display: none;">
            <button class="btn btn-outline" onclick="changePage('prev')" id="prevBtn">
                <i class="fas fa-chevron-left"></i> Anterior
            </button>
            <span id="pageInfo" class="page-info">Página 1 de 1</span>
            <button class="btn btn-outline" onclick="changePage('next')" id="nextBtn">
                Siguiente <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <!-- Context Menu -->
    <div id="contextMenu" class="context-menu" style="display: none;"></div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Variables globales
        let apiKey = document.getElementById('apiKey').value;
        let currentPage = 1;
        let currentFolder = '0';
        let totalPages = 1;
        let folders = [];
        
        // Conectar a la API
        async function connect() {
            apiKey = document.getElementById('apiKey').value;
            
            if (!apiKey) {
                Swal.fire('Error', 'Ingresa tu API Key', 'error');
                return;
            }
            
            Swal.fire({
                title: 'Conectando...',
                text: 'Verificando API Key',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            const result = await callAPI('account_info', {});
            
            if (result.success && result.data.status === 200) {
                updateAccountInfo(result.data);
                await loadFolders();
                await loadFiles();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Conectado',
                    text: 'API Key válida',
                    timer: 1500
                });
            } else {
                Swal.fire('Error', 'API Key inválida', 'error');
            }
        }
        
        // Llamada a la API
        async function callAPI(action, data) {
            const formData = new FormData();
            formData.append('ajax', 'true');
            formData.append('api_key', apiKey);
            formData.append('action', action);
            
            for (let key in data) {
                formData.append(key, data[key]);
            }
            
            const response = await fetch('', { method: 'POST', body: formData });
            return await response.json();
        }
        
        // Actualizar información de cuenta
        function updateAccountInfo(data) {
            const info = data.result;
            document.getElementById('infoGrid').style.display = 'grid';
            document.getElementById('infoGrid').innerHTML = `
                <div class="info-card">
                    <h3><i class="fas fa-envelope"></i> Email</h3>
                    <div class="info-value">${info.email || 'N/A'}</div>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-coins"></i> Balance</h3>
                    <div class="info-value">$${info.balance || '0'}</div>
                </div>
                <div class="info-card">
                    <h3><i class="fas fa-database"></i> Almacenamiento</h3>
                    <div class="info-value">${formatBytes(info.storage_used)}</div>
                </div>
            `;
            
            document.getElementById('toolbar').style.display = 'flex';
            document.getElementById('folderNav').style.display = 'flex';
        }
        
        // Cargar carpetas
        async function loadFolders() {
            const result = await callAPI('list_folders', {});
            
            if (result.success && result.data.status === 200) {
                folders = result.data.result?.folders || [];
                updateFolderSelect();
            }
        }
        
        // Actualizar select de carpetas
        function updateFolderSelect() {
            const select = document.getElementById('folderSelect');
            select.innerHTML = '<option value="0">📁 Raíz</option>';
            
            folders.forEach(folder => {
                const option = document.createElement('option');
                option.value = folder.fld_id;
                option.textContent = `📁 ${folder.name}`;
                select.appendChild(option);
            });
            
            select.value = currentFolder;
        }
        
        // Cargar archivos
        async function loadFiles() {
            const container = document.getElementById('filesContainer');
            
            container.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Cargando archivos...</p>
                </div>
            `;
            
            const result = await callAPI('list_files', {
                page: currentPage,
                folder_id: currentFolder
            });
            
            if (result.success && result.data.status === 200) {
                displayFiles(result.data.result);
            }
        }
        
        // Mostrar archivos - CORREGIDO usando splash_img
        function displayFiles(data) {
            const container = document.getElementById('filesContainer');
            
            if (!data.files || data.files.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No hay archivos</h3>
                    </div>
                `;
                document.getElementById('pagination').style.display = 'none';
                return;
            }
            
            totalPages = data.total_pages || 1;
            
            let html = '<div class="files-grid">';
            
            data.files.forEach(file => {
                // Usamos splash_img para la imagen principal (como en tu ejemplo)
                const imageUrl = file.splash_img || file.single_img || 'https://via.placeholder.com/400x200?text=No+Image';
                
                html += `
                    <div class="file-card" 
                         oncontextmenu="showContextMenu(event, '${file.file_code}', '${file.title.replace(/'/g, "\\'")}')">
                        <div class="file-thumbnail">
                            <img src="${imageUrl}" alt="${file.title}" onerror="this.src='https://via.placeholder.com/400x200?text=Error'">
                            <span class="file-badge"><i class="fas fa-clock"></i> ${formatDuration(file.length)}</span>
                        </div>
                        <div class="file-info">
                            <div class="file-title">${file.title || 'Sin título'}</div>
                            <div class="file-meta">
                                <span><i class="fas fa-eye"></i> ${file.views || 0}</span>
                                <span><i class="fas fa-calendar"></i> ${new Date(file.uploaded).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
            
            // Paginación
            document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage === totalPages;
            document.getElementById('pagination').style.display = 'flex';
        }
        
        // Menú contextual
        function showContextMenu(event, fileCode, title) {
            event.preventDefault();
            
            const menu = document.getElementById('contextMenu');
            const x = event.clientX;
            const y = event.clientY;
            
            menu.style.display = 'block';
            menu.style.left = Math.min(x, window.innerWidth - 300) + 'px';
            menu.style.top = Math.min(y, window.innerHeight - 400) + 'px';
            
            menu.innerHTML = `
                <div class="context-menu-header">
                    <i class="fas fa-file"></i> ${title.substring(0, 30)}${title.length > 30 ? '...' : ''}
                </div>
                <div class="context-menu-item" onclick="viewFileInfo('${fileCode}')">
                    <i class="fas fa-info-circle"></i> Información
                </div>
                <div class="context-menu-item" onclick="downloadFile('${fileCode}')">
                    <i class="fas fa-download"></i> Descargar
                </div>
                <div class="context-menu-divider"></div>
                <div class="context-menu-item" onclick="showRenameModal('${fileCode}', '${title.replace(/'/g, "\\'")}')">
                    <i class="fas fa-edit"></i> Renombrar
                </div>
                <div class="context-menu-item" onclick="showMoveModal('${fileCode}')">
                    <i class="fas fa-folder-open"></i> Mover a...
                </div>
                <div class="context-menu-divider"></div>
                <div class="context-menu-item" onclick="getEmbedCode('${fileCode}')">
                    <i class="fas fa-code"></i> Código Embed
                </div>
                <div class="context-menu-item" onclick="getDirectLink('${fileCode}')">
                    <i class="fas fa-link"></i> Enlace Directo
                </div>
                <div class="context-menu-divider"></div>
                <div class="context-menu-item danger" onclick="deleteFile('${fileCode}')">
                    <i class="fas fa-trash"></i> Eliminar
                </div>
            `;
            
            setTimeout(() => document.addEventListener('click', closeContextMenu), 100);
        }
        
        function closeContextMenu() {
            document.getElementById('contextMenu').style.display = 'none';
            document.removeEventListener('click', closeContextMenu);
        }
        
        // Ver información del archivo
        async function viewFileInfo(fileCode) {
            closeContextMenu();
            
            const result = await callAPI('file_info', { file_code: fileCode });
            
            if (result.success && result.data.status === 200) {
                const file = result.data.result[0];
                
                Swal.fire({
                    title: 'Información del Archivo',
                    html: `
                        <div style="text-align: left;">
                            <p><strong>Título:</strong> ${file.title}</p>
                            <p><strong>Código:</strong> ${file.filecode}</p>
                            <p><strong>Tamaño:</strong> ${formatBytes(file.size)}</p>
                            <p><strong>Duración:</strong> ${formatDuration(file.length)}</p>
                            <p><strong>Vistas:</strong> ${file.views || 0}</p>
                            <p><strong>Subido:</strong> ${file.uploaded}</p>
                        </div>
                    `,
                    confirmButtonColor: '#667eea'
                });
            }
        }
        
        // Descargar archivo
        function downloadFile(fileCode) {
            closeContextMenu();
            window.open(`https://dood.to/d/${fileCode}`, '_blank');
        }
        
        // Modal de subida
        function showUploadModal() {
            let folderOptions = '<option value="0">📁 Raíz</option>';
            folders.forEach(folder => {
                folderOptions += `<option value="${folder.fld_id}">📁 ${folder.name}</option>`;
            });
            
            Swal.fire({
                title: 'Subir Archivo Remoto',
                html: `
                    <input type="url" id="uploadUrl" class="swal2-input" placeholder="URL del archivo">
                    <select id="uploadFolder" class="swal2-input">
                        ${folderOptions}
                    </select>
                    <input type="text" id="uploadTitle" class="swal2-input" placeholder="Título (opcional)">
                `,
                showCancelButton: true,
                confirmButtonText: 'Subir',
                confirmButtonColor: '#48bb78',
                preConfirm: () => {
                    const url = document.getElementById('uploadUrl').value;
                    if (!url) {
                        Swal.showValidationMessage('URL requerida');
                        return false;
                    }
                    return {
                        url: url,
                        folder: document.getElementById('uploadFolder').value,
                        title: document.getElementById('uploadTitle').value
                    };
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Subiendo...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    
                    const response = await callAPI('remote_upload', {
                        url: result.value.url,
                        folder_id: result.value.folder,
                        title: result.value.title
                    });
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Subida iniciada',
                            timer: 1500
                        });
                        setTimeout(loadFiles, 1500);
                    }
                }
            });
        }
        
        // Modal crear carpeta
        function showFolderModal() {
            let folderOptions = '<option value="0">📁 Raíz</option>';
            folders.forEach(folder => {
                folderOptions += `<option value="${folder.fld_id}">📁 ${folder.name}</option>`;
            });
            
            Swal.fire({
                title: 'Crear Carpeta',
                html: `
                    <input type="text" id="folderName" class="swal2-input" placeholder="Nombre de la carpeta">
                    <select id="parentFolder" class="swal2-input">
                        ${folderOptions}
                    </select>
                `,
                showCancelButton: true,
                confirmButtonText: 'Crear',
                confirmButtonColor: '#48bb78',
                preConfirm: () => {
                    const name = document.getElementById('folderName').value;
                    if (!name) {
                        Swal.showValidationMessage('Nombre requerido');
                        return false;
                    }
                    return {
                        name: name,
                        parent: document.getElementById('parentFolder').value
                    };
                }
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const response = await callAPI('create_folder', {
                        name: result.value.name,
                        parent_id: result.value.parent
                    });
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Carpeta creada',
                            timer: 1500
                        });
                        await loadFolders();
                    }
                }
            });
        }
        
        // Modal renombrar
        function showRenameModal(fileCode, currentTitle) {
            closeContextMenu();
            
            Swal.fire({
                title: 'Renombrar Archivo',
                input: 'text',
                inputValue: currentTitle,
                showCancelButton: true,
                confirmButtonText: 'Renombrar',
                confirmButtonColor: '#f59e0b',
                inputValidator: (value) => !value ? 'Título requerido' : null
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const response = await callAPI('rename_file', {
                        file_code: fileCode,
                        title: result.value
                    });
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Renombrado',
                            timer: 1500
                        });
                        loadFiles();
                    }
                }
            });
        }
        
        // Modal mover
        function showMoveModal(fileCode) {
            closeContextMenu();
            
            let folderOptions = '<option value="0">📁 Raíz</option>';
            folders.forEach(folder => {
                folderOptions += `<option value="${folder.fld_id}">📁 ${folder.name}</option>`;
            });
            
            Swal.fire({
                title: 'Mover Archivo',
                html: `<select id="moveFolder" class="swal2-input">${folderOptions}</select>`,
                showCancelButton: true,
                confirmButtonText: 'Mover',
                confirmButtonColor: '#4299e1',
                preConfirm: () => document.getElementById('moveFolder').value
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const response = await callAPI('move_file', {
                        file_code: fileCode,
                        folder_id: result.value
                    });
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Movido',
                            timer: 1500
                        });
                        loadFiles();
                    }
                }
            });
        }
        
        // Eliminar archivo
        async function deleteFile(fileCode) {
            closeContextMenu();
            
            const result = await Swal.fire({
                title: '¿Eliminar archivo?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e53e3e',
                confirmButtonText: 'Eliminar'
            });
            
            if (result.isConfirmed) {
                const response = await callAPI('delete_file', { file_code: fileCode });
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        timer: 1500
                    });
                    loadFiles();
                }
            }
        }
        
        // Código Embed
        function getEmbedCode(fileCode) {
            closeContextMenu();
            const embedCode = `<iframe src="https://dood.to/e/${fileCode}" width="100%" height="400" frameborder="0" allowfullscreen></iframe>`;
            
            Swal.fire({
                title: 'Código Embed',
                html: `<textarea class="swal2-textarea" readonly rows="4">${embedCode}</textarea>`,
                confirmButtonColor: '#667eea'
            });
        }
        
        // Enlace directo
        function getDirectLink(fileCode) {
            closeContextMenu();
            const link = `https://dood.to/d/${fileCode}`;
            
            Swal.fire({
                title: 'Enlace Directo',
                html: `<input class="swal2-input" value="${link}" readonly>`,
                confirmButtonColor: '#667eea'
            });
        }
        
        // Buscar archivos
        async function searchFiles() {
            const term = document.getElementById('searchInput').value;
            if (!term) return;
            
            // Implementar búsqueda cuando la API lo soporte
            Swal.fire('Info', 'Función de búsqueda próximamente', 'info');
        }
        
        // Navegación
        function changeFolder() {
            currentFolder = document.getElementById('folderSelect').value;
            currentPage = 1;
            loadFiles();
        }
        
        function changePage(direction) {
            if (direction === 'prev' && currentPage > 1) {
                currentPage--;
            } else if (direction === 'next' && currentPage < totalPages) {
                currentPage++;
            }
            loadFiles();
        }
        
        function refreshFiles() {
            loadFiles();
        }
        
        // Formatos
        function formatBytes(bytes) {
            if (!bytes || bytes === 'unlimited') return '∞';
            bytes = parseFloat(bytes);
            if (isNaN(bytes) || bytes === 0) return '0 B';
            
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function formatDuration(seconds) {
            seconds = parseInt(seconds) || 0;
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        
        // Cerrar menú con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeContextMenu();
        });
        
        // Auto-conectar
        if (apiKey) setTimeout(connect, 500);
    </script>
</body>
</html>