<?php
declare(strict_types=1);

// Debug sementara (MATIKAN di production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include_once __DIR__ . '/../includes/auth.php';

// Batasi akses hanya admin (dukung dua gaya)
if (function_exists('requireRole')) {
    requireRole('admin');
} else {
    checkRole('admin');
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
      :root { --brand-primary: #0d6efd; }
      body { background-color: #f8f9fa; }
      .navbar-brand { font-weight: 600; }
    </style>
  </head>
  <body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">F &amp; B ELAF Store</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="topNav">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link active" href="dashboard.php"><i class="bi bi-house-door me-1"></i> Home</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_users.php">Manage Users</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_suppliers.php">Manage Suppliers</a></li>
            <li class="nav-item"><a class="nav-link" href="manage_stok.php">Manage Stok</a></li>
            <!-- Tambahan: Kelola Produk -->
            <li class="nav-item"><a class="nav-link" href="manage_produk.php">Manage Produk</a></li>
            <li class="nav-item"><a class="nav-link" href="../index.php">View Products</a></li>
          </ul>
          <a class="btn btn-outline-light btn-sm" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
      </div>
    </nav>

    <!-- Konten -->
    <main class="container py-4">
      <div class="mb-4">
        <h1 class="h3 mb-1">Admin Panel</h1>
        <p class="text-muted mb-0">Gunakan menu di atas untuk mengelola users, suppliers, stok, dan produk.</p>
      </div>

      <div class="row g-4">
        <!-- Kelola Users -->
        <div class="col-12 col-lg-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Kelola Users</h5>
              <p class="card-text">Tambah, edit, dan hapus akun (Admin &amp; Supplier).</p>
              <a href="manage_users.php" class="btn btn-primary"><i class="bi bi-people me-1"></i> Buka Manage Users</a>
            </div>
          </div>
        </div>

        <!-- Kelola Suppliers -->
        <div class="col-12 col-lg-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Kelola Suppliers</h5>
              <p class="card-text">Tambah, edit, dan tautkan supplier ke akun login.</p>
              <a href="manage_suppliers.php" class="btn btn-primary"><i class="bi bi-building me-1"></i> Buka Manage Suppliers</a>
            </div>
          </div>
        </div>

        <!-- Kelola Stok -->
        <div class="col-12 col-lg-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Kelola Stok</h5>
              <p class="card-text">Catat stok masuk, expired, dan stok akhir harian.</p>
              <a href="manage_stok.php" class="btn btn-primary"><i class="bi bi-box-seam me-1"></i> Buka Manage Stok</a>
            </div>
          </div>
        </div>

        <!-- Kelola Produk (admin) -->
        <div class="col-12 col-lg-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Kelola Produk</h5>
              <p class="card-text">Kelola produk untuk supplier mana pun, termasuk yang belum punya user.</p>
              <a href="manage_produk.php" class="btn btn-primary"><i class="bi bi-clipboard-data me-1"></i> Buka Manage Produk</a>
            </div>
          </div>
        </div>
      </div>
    </main>

    <footer class="border-top mt-4">
      <div class="container py-3">
        <small class="text-muted">&copy; <?= date('Y'); ?> FnBelaf Store</small>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>