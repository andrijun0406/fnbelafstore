
<?php
include '../includes/auth.php';
checkRole('admin');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        nav { background: #333; padding: 10px; }
        nav a { color: white; margin: 0 10px; text-decoration: none; }
        nav a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<h1>Selamat datang, Admin!</h1>
<nav>
    dashboard.phpHome</a>
    <anage_users.phpManage Users</a>
    manage_suppliers.phpManage Suppliers</a>
    ../index.phpView Products</a>
    <a href="../logout.php>
</nav>
<p>Pilih menu di atas untuk mengelola data.</p>
</body>
</html>
