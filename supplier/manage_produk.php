<?php
declare(strict_types=1);

/**
 * Manage Produk (Supplier)
 * - Hanya untuk role supplier
 * - CRUD produk milik supplier yang tertaut ke user login
 * - Supplier hanya boleh melihat/mengubah: nama, jenis, harga_supplier
 * - Margin FnB TIDAK ditampilkan ke supplier dan TIDAK bisa diubah oleh supplier
 * - Cegah delete jika ada stok terkait
 */

// Debug mode (hidupkan saat dev, matikan di production)
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

// Mulai sesi
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Auth & koneksi
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

// Helpers
function post($k, $d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function get($k, $d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function current_user_id(): int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; }

$flash = null;
$user_id = current_user_id();

// Ambil supplier tertaut
$supplier = null;
if ($user_id > 0) {
  $stmtSup = $conn->prepare("SELECT id, nama FROM supplier WHERE user_id = ? LIMIT 1");
  $stmtSup->bind_param("i", $user_id);
  $stmtSup->execute();
  $supplier = $stmtSup->get_result()->fetch_assoc();
  $stmtSup->close();
}

// Aksi CRUD (tanpa margin_fnb)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid, silakan muat ulang.'];
  } elseif (!$supplier) {
    $flash = ['type'=>'danger','msg'=>'Akun supplier belum ditautkan. Mohon hubungi admin.'];
  } else {
    $supplier_id = (int)$supplier['id'];
    $action = post('action');

    if ($action === 'create_produk') {
      $nama = post('nama');
      $jenis = post('jenis');
      $harga_supplier = post('harga_supplier');

      if ($nama === '' || !in_array($jenis, ['kudapan','minuman'], true)) {
        $flash = ['type'=>'danger','msg'=>'Nama/jenis tidak valid.'];
      } elseif (!is_numeric($harga_supplier)) {
        $flash = ['type'=>'danger','msg'=>'Harga supplier harus numerik.'];
      } else {
        $hs = (float)$harga_supplier;
        // margin_fnb diset 0 — hanya admin yang boleh mengisi margin
        $stmt = $conn->prepare("INSERT INTO produk (nama, jenis, harga_supplier, margin_fnb, supplier_id) VALUES (?, ?, ?, 0, ?)");
        $stmt->bind_param("ssdi", $nama, $jenis, $hs, $supplier_id);
        if ($stmt->execute()) {
          $flash = ['type'=>'success','msg'=>'Produk berhasil ditambahkan.'];
        } else {
          $flash = ['type'=>'danger','msg'=>'Gagal menambah produk: ' . htmlspecialchars($stmt->error)];
        }
        $stmt->close();
      }

    } elseif ($action === 'update_produk') {
      $id = post('id');
      $nama = post('nama');
      $jenis = post('jenis');
      $harga_supplier = post('harga_supplier');

      if (!ctype_digit($id)) {
        $flash = ['type'=>'danger','msg'=>'ID produk tidak valid.'];
      } elseif ($nama === '' || !in_array($jenis, ['kudapan','minuman'], true)) {
        $flash = ['type'=>'danger','msg'=>'Nama/jenis tidak valid.'];
      } elseif (!is_numeric($harga_supplier)) {
        $flash = ['type'=>'danger','msg'=>'Harga supplier harus numerik.'];
      } else {
        $pid = (int)$id;
        $hs  = (float)$harga_supplier;

        // Supplier tidak boleh mengubah margin_fnb
        $stmt = $conn->prepare("UPDATE produk SET nama = ?, jenis = ?, harga_supplier = ? WHERE id = ? AND supplier_id = ?");
        $stmt->bind_param("ssdii", $nama, $jenis, $hs, $pid, $supplier_id);
        $stmt->execute();
        $flash = ($stmt->affected_rows > 0)
          ? ['type'=>'success','msg'=>'Produk diperbarui.']
          : ['type'=>'warning','msg'=>'Tidak ada perubahan atau produk bukan milik Anda.'];
        $stmt->close();
      }

    } elseif ($action === 'delete_produk') {
      $id = post('id');
      if (!ctype_digit($id)) {
        $flash = ['type'=>'danger','msg'=>'ID produk tidak valid.'];
      } else {
        $pid = (int)$id;

        // Cek stok terkait
        $stmtC = $conn->prepare("SELECT COUNT(*) AS c FROM stok WHERE produk_id = ?");
        $stmtC->bind_param("i", $pid);
        $stmtC->execute();
        $resC = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();

        if (($resC['c'] ?? 0) > 0) {
          $flash = ['type'=>'danger','msg'=>'Tidak dapat menghapus: ada stok terkait produk ini.'];
        } else {
          $stmt = $conn->prepare("DELETE FROM produk WHERE id = ? AND supplier_id = ?");
          $stmt->bind_param("ii", $pid, $supplier_id);
          if ($stmt->execute() && $stmt->affected_rows > 0) {
            $flash = ['type'=>'success','msg'=>'Produk dihapus.'];
          } else {
            $flash = ['type'=>'danger','msg'=>'Gagal menghapus produk.'];
          }
          $stmt->close();
        }
      }
    }
  }
}

// Filter & pagination
$q = get('q', '');
$page = max(1, (int)get('page', '1'));
$limit = 10;
$offset = ($page - 1) * $limit;

// Ambil produk (tanpa harga_jual dan margin_fnb)
$products = [];
$total = 0;
if ($supplier) {
  if ($q !== '') {
    $like = '%' . $q . '%';
    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM produk WHERE supplier_id = ? AND (nama LIKE ? OR jenis LIKE ?)");
    $stmtCnt->bind_param("iss", $supplier['id'], $like, $like);
    $stmtCnt->execute();
    $total = (int)$stmtCnt->get_result()->fetch_assoc()['c'];
    $stmtCnt->close();

    $stmtP = $conn->prepare("
      SELECT id, nama, jenis, harga_supplier
      FROM produk
      WHERE supplier_id = ? AND (nama LIKE ? OR jenis LIKE ?)
      ORDER BY id DESC
      LIMIT ? OFFSET ?
    ");
    $stmtP->bind_param("issii", $supplier['id'], $like, $like, $limit, $offset);
  } else {
    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM produk WHERE supplier_id = ?");
    $stmtCnt->bind_param("i", $supplier['id']);
    $stmtCnt->execute();
    $total = (int)$stmtCnt->get_result()->fetch_assoc()['c'];
    $stmtCnt->close();

    $stmtP = $conn->prepare("
      SELECT id, nama, jenis, harga_supplier
      FROM produk
      WHERE supplier_id = ?
      ORDER BY id DESC
      LIMIT ? OFFSET ?
    ");
    $stmtP->bind_param("iii", $supplier['id'], $limit, $offset);
  }
  $stmtP->execute();
  $products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtP->close();
}

$total_pages = (int)ceil($total / $limit);
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Produk - Supplier</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/brand.css">
    <link rel="icon" type="image/png" href="/assets/images/logo/logo_image_only.png">
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
            <h1 class="h4 mb-0">Manage Produk</h1>
            <small class="text-muted">Supplier: <?= htmlspecialchars($supplier['nama']) ?></small>
          </div>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProdukModal">
            <i class="bi bi-plus-circle"></i> Tambah Produk
          </button>
        </div>

        <!-- Search -->
        <form class="d-flex mb-3" method="get">
          <input type="text" name="q" class="form-control me-2" placeholder="Cari nama/jenis..." value="<?= htmlspecialchars($q) ?>">
          <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Cari</button>
        </form>

        <!-- Tabel Produk (tanpa margin & harga_jual) -->
        <div class="card shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Jenis</th>
                    <th class="text-end">Harga Supplier</th>
                    <th class="text-end">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($products)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                  <?php else: foreach ($products as $p): ?>
                    <tr>
                      <td><?= $p['id'] ?></td>
                      <td><?= htmlspecialchars($p['nama']) ?></td>
                      <td><span class="badge text-bg-secondary"><?= htmlspecialchars($p['jenis']) ?></span></td>
                      <td class="text-end">Rp <?= number_format((float)$p['harga_supplier'], 2, ',', '.') ?></td>
                      <td class="text-end">
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProdukModal<?= $p['id'] ?>">Edit</button>
                          <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProdukModal<?= $p['id'] ?>">Delete</button>
                        </div>
                      </td>
                    </tr>

                    <!-- Edit Produk Modal (tanpa field margin/harga_jual) -->
                    <div class="modal fade" id="editProdukModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                          <div class="modal-header">
                            <h5 class="modal-title">Edit Produk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="update_produk">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">

                            <div class="form-floating mb-3">
                              <input type="text" class="form-control" id="nama<?= $p['id'] ?>" name="nama" value="<?= htmlspecialchars($p['nama']) ?>" required>
                              <label for="nama<?= $p['id'] ?>">Nama</label>
                            </div>

                            <div class="mb-3">
                              <label class="form-label">Jenis</label>
                              <select class="form-select" name="jenis" required>
                                <option value="kudapan" <?= $p['jenis']==='kudapan'?'selected':'' ?>>Kudapan</option>
                                <option value="minuman" <?= $p['jenis']==='minuman'?'selected':'' ?>>Minuman</option>
                              </select>
                            </div>

                            <div class="form-floating">
                              <input type="number" step="0.01" class="form-control" id="hs<?= $p['id'] ?>" name="harga_supplier" value="<?= htmlspecialchars((string)$p['harga_supplier']) ?>" required>
                              <label for="hs<?= $p['id'] ?>">Harga Supplier</label>
                            </div>

                            <div class="form-text mt-2">
                              Jika Anda perlu mengubah margin FnB atau harga jual, silakan hubungi Admin F&B.
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button class="btn btn-primary" type="submit">Simpan</button>
                          </div>
                        </form>
                      </div></div>
                    </div>

                    <!-- Delete Produk Modal -->
                    <div class="modal fade" id="deleteProdukModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                          <div class="modal-header">
                            <h5 class="modal-title">Hapus Produk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <p class="mb-2">Tindakan ini tidak dapat dibatalkan. Jika ada stok terkait, penghapusan akan ditolak.</p>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="delete_produk">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                          </div>
                          <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button class="btn btn-danger" type="submit">Hapus</button>
                          </div>
                        </form>
                      </div></div>
                    </div>

                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <nav class="mt-3">
            <ul class="pagination pagination-sm">
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>

        <!-- Create Produk Modal (tanpa margin/harga_jual) -->
        <div class="modal fade" id="createProdukModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg"><div class="modal-content">
            <form method="post">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="create_produk">

                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="namaBaru" name="nama" placeholder="Nama produk" required>
                      <label for="namaBaru">Nama produk</label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Jenis</label>
                    <select class="form-select" name="jenis" required>
                      <option value="kudapan">Kudapan</option>
                      <option value="minuman">Minuman</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="number" step="0.01" class="form-control" id="hsBaru" name="harga_supplier" placeholder="Harga supplier" required>
                      <label for="hsBaru">Harga supplier</label>
                    </div>
                  </div>
                </div>

                <div class="form-text mt-2">
                  Margin FnB dan harga jual diatur oleh Admin F&B.
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit">Simpan</button>
              </div>
            </form>
          </div></div>
        </div>
      <?php endif; ?>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>