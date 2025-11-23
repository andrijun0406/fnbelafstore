
<?php
session_start();
session_destroy();
header("Location: login.php");
exit();
?>
php
include '../includes/auth.php';
checkRole('admin');
?>
<!DOCTYPE html>
<html>
<head><title>Admin Dashboard</title></head>
<body>
<h1>Selamat datang, Admin!</h1>
<ul>
    <li>../index.phpLihat Produk (Public)</a></li>
    <li>../logout.phpLogout</a></li>
</ul>
</body>
</html>
