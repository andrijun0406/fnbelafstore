<?php
// Helper untuk tautan aktif (gunakan yang sama dengan admin)
if (!function_exists('nav_active')) {
  function nav_active(string $path): string {
    $current = $_SERVER['SCRIPT_NAME'] ?? '';
    return (strlen($path) > 0 && substr($current, -strlen($path)) === $path) ? ' active' : '';
  }
}

/**
 * Render navbar supplier yang konsisten
 * Path absolut mengarah ke direktori /supplier dan root
 */
if (!function_exists('render_supplier_navbar')) {
  function render_supplier_navbar(): void { ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container-fluid">
        <a class="navbar-brand" href="/supplier/dashboard.php">F &amp; B ELAF Store</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNav">
          <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link<?= nav_active('/supplier/dashboard.php') ?>" href="/supplier/dashboard.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link<?= nav_active('/supplier/manage_produk.php') ?>" href="/supplier/manage_produk.php">Manage Produk</a></li>
          </ul>
          <a class="btn btn-outline-light btn-sm" href="/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
      </div>
    </nav>
  <?php }
}