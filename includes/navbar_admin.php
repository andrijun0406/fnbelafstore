<?php
// Helper untuk menentukan tautan aktif
if (!function_exists('nav_active')) {
  function nav_active(string $path): string {
    $current = $_SERVER['SCRIPT_NAME'] ?? '';
    return (strlen($path) > 0 && substr($current, -strlen($path)) === $path) ? ' active' : '';
  }
}

/**
 * Render navbar admin yang konsisten
 * Path absolut mengarah ke halaman di direktori /admin dan root
 */
if (!function_exists('render_admin_navbar')) {
  function render_admin_navbar(): void { ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container-fluid">
        <a class="navbar-brand" href="/admin/dashboard.php">F &amp; B ELAF Store</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNav">
          <ul class="navbar-nav me-auto">
            <li class="nav-item">
              <a class="nav-link<?= nav_active('/admin/dashboard.php') ?>" href="/admin/dashboard.php">
                <i class="bi bi-house-door me-1"></i> Home
              </a>
            </li>
            <li class="nav-item"><a class="nav-link<?= nav_active('/admin/manage_users.php') ?>" href="/admin/manage_users.php">Manage Users</a></li>
            <li class="nav-item"><a class="nav-link<?= nav_active('/admin/manage_suppliers.php') ?>" href="/admin/manage_suppliers.php">Manage Suppliers</a></li>
            <li class="nav-item"><a class="nav-link<?= nav_active('/admin/manage_stok.php') ?>" href="/admin/manage_stok.php">Manage Stok</a></li>
            <li class="nav-item"><a class="nav-link<?= nav_active('/admin/manage_produk.php') ?>" href="/admin/manage_produk.php">Manage Produk</a></li>
            <li class="nav-item"><a class="nav-link" href="/index.php">View Products</a></li>
          </ul>
          <a class="btn btn-outline-light btn-sm" href="/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
      </div>
    </nav>
  <?php }
}