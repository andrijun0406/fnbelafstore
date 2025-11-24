<?php
declare(strict_types=1);

// Debug (matikan di production)
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../includes/auth.php';
if (function_exists('requireRole')) {
  requireRole('supplier');
} else {
  checkRole('supplier');
}
require_once __DIR__ . '/../includes/koneksi.php';

// Navbar supplier
include_once __DIR__ . '/../includes/navbar_supplier.php';

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function current_user_id(): int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; }

$flash = null;
$user_id = current_user_id();

// Ambil supplier tertaut
$stmtSup = $conn->prepare("SELECT id, nama FROM supplier WHERE user_id = ? LIMIT 1");
$stmtSup->bind_param("i", $user_id);
$stmtSup->execute();
$supplier = $stmtSup->get_result()->fetch_assoc();
$stmtSup->close();

// Reset password (tetap boleh)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'reset_password') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid, silakan muat ulang.'];
  } else {
    $current = post('current_password');
    $newpass = post('new_password');
    $confirm = post('confirm_password');

    if ($newpass === '' || $confirm === '' || $newpass !== $confirm) {
      $flash = ['type'=>'danger','msg'=>'Password baru dan konfirmasi harus diisi dan sama.'];
    } else {
      $stmtU = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
      $stmtU->bind_param("i", $user_id);
      $stmtU->execute();
      $resU = $stmtU->get_result()->fetch_assoc();
      $stmtU->close();

      if (!$resU || !password_verify($current, $resU['password'])) {
        $flash = ['type'=>'danger','msg'=>'Password saat ini tidak sesuai.'];
      } else {
        $hash = password_hash($newpass, PASSWORD_BCRYPT);
        $stmtUp = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmtUp->bind_param("si", $hash, $user_id);
        $stmtUp->execute();
        $stmtUp->close();
        session_regenerate_id(true);
        $flash = ['type'=>'success','msg'=>'Password berhasil diperbarui.'];
      }
    }
  }
}

// Ambil produk terbaru (hanya kolom yang boleh dilihat supplier)
$products = [];
if ($supplier) {
  $supId = (int)$supplier['id'];
  $limitPreview = 5;
  // Jika server tidak mendukung parameter di LIMIT, ubah ke LIMIT 5 langsung
  $stmtP = $conn->prepare("
    SELECT id, nama, jenis, harga_supplier
    FROM produk
    WHERE supplier_id = ?
    ORDER BY id DESC
    LIMIT ?
  ");
  $stmtP->bind_param("ii", $supId, $limitPreview);
  $stmtP->execute();
  $products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtP->close();
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/brand.css">
    <link rel="icon" type="image/png" href="/assets/images/logo/logo_image_only.png">
    <style>
      .stat-card .card-body { display: flex; align-items: center; justify-content: space-between; }
    </style>
  </head>
  <body class="bg-light">

    <?php render_supplier_navbar(); ?>

    <main class="container py-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>

      <?php if (!$supplier): ?>
        <div class="alert alert-warning">
          Akun Anda belum ditautkan ke data supplier. Mohon hubungi admin untuk penautan agar dapat mengelola produk.
        </div>
      <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h1 class="h4 mb-0">Halo, <?= htmlspecialchars($supplier['nama']) ?></h1>
            <small class="text-muted">Ringkasan dan pengaturan akun.</small>
          </div>
          <div class="btn-group">
            <a class="btn btn-primary" href="/supplier/manage_produk.php"><i class="bi bi-box-seam me-1"></i> Kelola Produk</a>
          </div>
        </div>

        <!-- Ringkasan (tetap) -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card shadow-sm">
              <div class="card-body">
                <div>
                  <h6 class="text-muted mb-1">Total Produk</h6>
                  <h4 class="mb-0"><?= number_format(count($products)) ?></h4>
                </div>
                <i class="bi bi-bag text-primary fs-2"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Keamanan Akun -->
        <div class="card shadow-sm mb-4">
          <div class="card-header">Keamanan Akun</div>
          <div class="card-body">
            <form method="post" class="row g-3">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="reset_password">
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Password saat ini" required>
                  <label for="current_password">Password saat ini</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Password baru" required>
                  <label for="new_password">Password baru</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Konfirmasi password baru" required>
                  <label for="confirm_password">Konfirmasi password baru</label>
                </div>
              </div>
              <div class="col-12">
                <button class="btn btn-outline-primary" type="submit">Perbarui Password</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Produk Terbaru (hanya harga supplier) -->
        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Produk Terbaru</span>
            <a class="btn btn-sm btn-outline-primary" href="/supplier/manage_produk.php">
              <i class="bi bi-pencil-square me-1"></i> Kelola
            </a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Jenis</th>
                    <th class="text-end">Harga Supplier</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($products)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada produk. Mulai tambah di halaman Kelola Produk.</td></tr>
                  <?php else: foreach ($products as $p): ?>
                    <tr>
                      <td><?= $p['id'] ?></td>
                      <td><?= htmlspecialchars($p['nama']) ?></td>
                      <td><span class="badge text-bg-secondary"><?= htmlspecialchars($p['jenis']) ?></span></td>
                      <td class="text-end">Rp <?= number_format((float)$p['harga_supplier'], 2, ',', '.') ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>