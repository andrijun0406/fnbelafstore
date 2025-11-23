
<?php
include '../includes/auth.php';
checkRole('admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboardn class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">dashboard.phpHome</a></li>
        <li class="nav-item">manage_users.phpManage Users</a></li>
        <li class="nav-item"><a class="nav-link" href="manage_sup</a></li>
        <li class="nav-item">../index.phpView Products</a></li>
      </ul>
      <a href="../logout.php" class="btn btn-outlinenav>

<!-- Content -->
<div class="container mt-4">
    <div class="text-center">
        <h1 class="mb-4">Selamat datang, Admin!</h1>
        <p class="lead">Gunakan menu di atas untuk mengelola data pengguna dan supplier.</p>
    </div>

    <!-- Cards -->
    <div class="row mt-5">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Kelola Users</h5>
                    <p class="card-text">Tambah, edit, dan hapus akun pengguna (Admin & Supplier).</p>
                    manage_users.phpManage Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
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

https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js</script>
</body>
</html>
