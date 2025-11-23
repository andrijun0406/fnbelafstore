
<?php
include '../includes/auth.php';
checkRole('admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/cssin.css
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    dashboard.phpAdmin Panel</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
            <a class="nav-link active" href="i>
        <li class="nav-item">
            <a href="manage_users.php">Kelola Users</a>
        </li>
        <li class="nav-item">
            <a href="manage_suppliers.php">Kelola Suppliers</a>
        </li>
        <li class="nav-item">
            <a href="../index.php">View Products</a>
        </li>
      </ul>
      <a href="/logout.php">Logout</a>
    </div>
  </div>
</nav>

<!-- Content -->
<div class="container mt-4">
    < class="text-center">
        <h1 class="mb-4">Selamat datang, Admin!</h1>
        <p class="lead">Gunakan menu di atas untuk mengelola data pengguna dan supplier.</p>
    </div>

    <!-- Cards -->
    <div class="row mt-5">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Kelola Users</h5>
                    <p class="card-text">Tambah, edit, dan hapus akun pengguna (Admin & Supplier).</p>
                    manage_users.phpManage Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Kelola Suppliers</h5>
                    <p class="card-text">Tambah, edit, dan hubungkan supplier dengan akun login.</p>
                    manage_suppliers.phpManage Suppliers</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js</script>
</body>
</html>
