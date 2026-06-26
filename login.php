<?php

ob_start();

session_start();
include "koneksi.php";




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PAYROLL - Login</title>
    <link rel="shortcut icon" href="images/favicon_round.png" type="image/png">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom Config for Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-brand-50 to-brand-100 min-h-screen flex items-center justify-center p-4">

    <!-- Login Container -->
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
        
        <!-- Header -->
        <div class="px-8 pt-8 pb-6 text-center">
            <h2 class="text-2xl font-bold text-gray-800 tracking-tight">PAYROLL HR</h2>
            <p class="text-sm text-gray-500 mt-1">Sign in to your account to continue</p>
        </div>
        
        <!-- Logo -->
        <div class="flex justify-center mb-6">
            <div class="w-24 h-24 rounded-full bg-brand-50 flex items-center justify-center p-2 shadow-sm border border-brand-100">
                <img src="images/iconhr.jpeg" alt="HR Logo" class="w-full h-full object-cover rounded-full"> 
            </div>
        </div>
        
        <!-- Form -->
        <div class="px-8 pb-8">
            <form method="POST" action="">
                <!-- Username -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="username">Username</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" id="username" name="tusername" class="pl-10 w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-colors duration-200 ease-in-out outline-none" placeholder="Enter your username" required>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="password" id="password" name="tpass" class="pl-10 w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white transition-colors duration-200 ease-in-out outline-none" placeholder="••••••••" required>
                    </div>
                </div>
                
                <!-- Submit -->
                <button type="submit" name="login" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 px-4 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200 ease-in-out transform hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                    Login Securely
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-8 py-4 text-center border-t border-gray-100">
            <p class="text-xs text-gray-400">&copy; <?php echo date('Y'); ?> Application Payroll System</p>
        </div>
    </div>

</body>
</html>

<?php
 if (isset($_POST['login'])) {
    $username = trim($_POST['tusername']); 
    $pass = $_POST['tpass'];

    $sql = $koneksi->query("SELECT * FROM ms_login WHERE username = '$username' AND password = '$pass'");
    
    $ketemu = $sql->num_rows;
    $tampil = $sql->fetch_assoc();

    if ($ketemu > 0) {
        $_SESSION['iduser'] = $tampil['id_login'];
        $_SESSION['nama'] = $tampil['username'];
        $_SESSION['passuser'] = $tampil['password'];
        $_SESSION['role'] = $tampil['role'];

        header("location:index.php"); 
    } else {
        echo "<script>alert('Data Not Found! Pastikan Username & Password benar.');</script>";
    }
}
?>