
<?php
// Gunakan path absolut yang aman:
include_once __DIR__ . '/../includes/auth.php';

// Batasi akses hanya admin
checkRole('admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
</head>
<body>

<!-- Header sederhana -->
<header>
    <h1>Admin Panel</h1>
    <nav>
        <a href="dashboard.php">Home</a> |
        <a href="manage_users.php">Manage Users</a> |
        <a href="manage_suppliers.php">Manage Suppliers</a> |
        <a href="../index.php">View Products</a> |
        <a href="../logout.php">Logout</a>
    </nav>
    <hr>
</header>

<!-- Konten -->
<main>
    <section>
        <h2>Selamat datang, Admin!</h2>
        <p>Gunakan menu di atas untuk mengelola data pengguna dan supplier.</p>
    </section>

    <hr>

    <section>
        <h3>Kelola Users</h3>
        <p>Tambah, edit, dan hapus akun pengguna (Admin &amp; Supplier).</p>
        <p>manage_users.phpBuka halaman Manage Users</a></p>
    </section>

    <section>
        <h3>Kelola Suppliers</h3>
        <p>Tambah, edit, dan hubungkan supplier dengan akun login.</p>
        <p>manage_suppliers.phpBuka halaman Manage Suppliers</a></p>
    </section>
</main>

<!-- Footer sederhana -->
<footer>
    <hr>
    <small>&copy; <?php echo date('Y'); ?> FnBelaf Store</small>
</footer>

</body>
</html>
