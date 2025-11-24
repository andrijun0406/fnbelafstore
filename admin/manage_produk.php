<?php
declare(strict_types=1);

/**
 * Admin - Manage Produk
 * - CRUD produk untuk supplier mana pun (termasuk supplier tanpa user)
 * - Harga_jual adalah kolom generated dari harga_supplier + margin_fnb (tidak diinput manual)
 * - Cegah delete jika ada stok terkait (stok.produk_id mereferensikan produk.id)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../includes/auth.php';
if (function_exists('requireRole')) {
  requireRole('admin');
} else {
  checkRole('admin');
}
require_once __DIR__ . '/../includes/koneksi.php';

// Muat partial navbar admin konsisten
include_once __DIR__ . '/../includes/navbar_admin.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function get($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

$flash = null;

// Aksi CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid, silakan muat ulang.'];
  } else {
    $action = post('action');

    if ($action === 'create_produk') {
      $supplier_id     = post('supplier_id');
      $nama            = post('nama');
      $jenis           = post('jenis');
      $harga_supplier  = post('harga_supplier');
      $margin_fnb      = post('margin_fnb');

      if (!ctype_digit($supplier_id) || $nama === '' || !in_array($jenis, ['kudapan','minuman'], true) || !is_numeric($harga_supplier) || !is_numeric($margin_fnb)) {
        $flash = ['type'=>'danger','msg'=>'Input produk tidak valid.'];
      } else {
        $sid = (int)$supplier_id;
        $hs  = (float)$harga_supplier;
        $mf  = (float)$margin_fnb;

        // Pastikan supplier ada
        $stmtS = $conn->prepare("SELECT id FROM supplier WHERE id = ? LIMIT 1");
        $stmtS->bind_param("i", $sid);
        $stmtS->execute();
        $existsS = $stmtS->get_result()->fetch_assoc();
        $stmtS->close();

        if (!$existsS) {
          $flash = ['type'=>'danger','msg'=>'Supplier tidak ditemukan.'];
        } else {
          $stmt = $conn->prepare("INSERT INTO produk (nama, jenis, harga_supplier, margin_fnb, supplier_id) VALUES (?, ?, ?, ?, ?)");
          $stmt->bind_param("ssddi", $nama, $jenis, $hs, $mf, $sid);
          if ($stmt->execute()) {
            $flash = ['type'=>'success','msg'=>'Produk berhasil ditambahkan.'];
          } else {
            $flash = ['type'=>'danger','msg'=>'Gagal menambah produk: ' . htmlspecialchars($stmt->error)];
          }
          $stmt->close();
        }
      }

    } elseif ($action === 'update_produk') {
      $id              = post('id');
      $supplier_id     = post('supplier_id');
      $nama            = post('nama');
      $jenis           = post('jenis');
      $harga_supplier  = post('harga_supplier');
      $margin_fnb      = post('margin_fnb');

      if (!ctype_digit($id) || !ctype_digit($supplier_id) || $nama === '' || !in_array($jenis, ['kudapan','minuman'], true) || !is_numeric($harga_supplier) || !is_numeric($margin_fnb)) {
        $flash = ['type'=>'danger','msg'=>'Input produk tidak valid.'];
      } else {
        $pid = (int)$id;
        $sid = (int)$supplier_id;
        $hs  = (float)$harga_supplier;
        $mf  = (float)$margin_fnb;

        // Pastikan produk ada
        $stmtP = $conn->prepare("SELECT id FROM produk WHERE id = ? LIMIT 1");
        $stmtP->bind_param("i", $pid);
        $stmtP->execute();
        $exP = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();

        // Pastikan supplier ada
        $stmtS = $conn->prepare("SELECT id FROM supplier WHERE id = ? LIMIT 1");
        $stmtS->bind_param("i", $sid);
        $stmtS->execute();
        $exS = $stmtS->get_result()->fetch_assoc();
        $stmtS->close();

        if (!$exP || !$exS) {
          $flash = ['type'=>'danger','msg'=>'Produk/Supplier tidak ditemukan.'];
        } else {
          $stmt = $conn->prepare("UPDATE produk SET nama = ?, jenis = ?, harga_supplier = ?, margin_fnb = ?, supplier_id = ? WHERE id = ?");
          $stmt->bind_param("ssddii", $nama, $jenis, $hs, $mf, $sid, $pid);
          $stmt->execute();
          if ($stmt->affected_rows >= 0) {
            $flash = ['type'=>'success','msg'=>'Produk diperbarui.'];
          } else {
            $flash = ['type'=>'warning','msg'=>'Tidak ada perubahan.'];
          }
          $stmt->close();
        }
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
          $stmt = $conn->prepare("DELETE FROM produk WHERE id = ?");
          $stmt->bind_param("i", $pid);
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

// Data tampilan
$q     = get('q', '');
$page  = max(1, (int)get('page', '1'));
$limit = 10;
$offset = ($page - 1) * $limit;

// Suppliers untuk dropdown (tampilkan semua, beri label jika belum punya user)
$suppliers = $conn->query("
  SELECT s.id, s.nama, s.user_id, u.username
  FROM supplier s
  LEFT JOIN users u ON u.id = s.user_id
  ORDER BY s.nama ASC
")->fetch_all(MYSQLI_ASSOC);

// Hitung total produk
if ($q !== '') {
  $like = '%' . $q . '%';
  $stmtCnt = $conn->prepare("
    SELECT COUNT(*) AS c
    FROM produk p
    JOIN supplier s ON s.id = p.supplier_id
    WHERE p.nama LIKE ? OR s.nama LIKE ?
  ");
  $stmtCnt->bind_param("ss", $like, $like);
} else {
  $stmtCnt = $conn->prepare("SELECT COUNT(*) AS c FROM produk");
}
$stmtCnt->execute();
$total = (int)$stmtCnt->get_result()->fetch_assoc()['c'];
$stmtCnt->close();

// Ambil produk (gunakan embed limit/offset numerik aman)
if ($q !== '') {
  $like = '%' . $q . '%';
  $sql = "
    SELECT p.id, p.nama, p.jenis, p.harga_supplier, p.margin_fnb, p.harga_jual, 
           s.id AS supplier_id, s.nama AS supplier_nama, u.username AS supplier_user
    FROM produk p
    JOIN supplier s ON s.id = p.supplier_id
    LEFT JOIN users u ON u.id = s.user_id
    WHERE p.nama LIKE ? OR s.nama LIKE ?
    ORDER BY p.id DESC
    LIMIT $limit OFFSET $offset
  ";
  $stmtP = $conn->prepare($sql);
  $stmtP->bind_param("ss", $like, $like);
} else {
  $sql = "
    SELECT p.id, p.nama, p.jenis, p.harga_supplier, p.margin_fnb, p.harga_jual, 
           s.id AS supplier_id, s.nama AS supplier_nama, u.username AS supplier_user
    FROM produk p
    JOIN supplier s ON s.id = p.supplier_id
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY p.id DESC
    LIMIT $limit OFFSET $offset
  ";
  $stmtP = $conn->prepare($sql);
}
$stmtP->execute();
$products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtP->close();

$total_pages = (int)ceil($total / $limit);
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Produk - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/brand.css">
    <link rel="icon" type="image/png" href="/assets/images/logo/logo_image_only.png">
  </head>
  <body class="bg-light">
    <?php render_admin_navbar(); ?>

    <main class="container py-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 class="h4 mb-0">Manage Produk</h1>
          <small class="text-muted">Admin dapat mengelola produk untuk semua supplier.</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProdukModal">
          <i class="bi bi-plus-circle"></i> Tambah Produk
        </button>
      </div>

      <!-- Pencarian -->
      <form class="d-flex mb-3" method="get">
        <input type="text" name="q" class="form-control me-2" placeholder="Cari nama produk / supplier..." value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Cari</button>
      </form>

      <!-- Tabel Produk -->
      <div class="card shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Produk</th>
                  <th>Supplier</th>
                  <th>Jenis</th>
                  <th class="text-end">Harga Supplier</th>
                  <th class="text-end">Margin FnB</th>
                  <th class="text-end">Harga Jual</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($products)): ?>
                  <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                <?php else: foreach ($products as $p): ?>
                  <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['nama']) ?></td>
                    <td>
                      <?= htmlspecialchars($p['supplier_nama']) ?>
                      <?php if (!$p['supplier_user']): ?>
                        <span class="badge text-bg-secondary ms-1">No user</span>
                      <?php else: ?>
                        <span class="badge text-bg-info ms-1">@<?= htmlspecialchars($p['supplier_user']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars($p['jenis']) ?></span></td>
                    <td class="text-end">Rp <?= number_format((float)$p['harga_supplier'], 2, ',', '.') ?></td>
                    <td class="text-end">Rp <?= number_format((float)$p['margin_fnb'], 2, ',', '.') ?></td>
                    <td class="text-end">Rp <?= number_format((float)$p['harga_jual'], 2, ',', '.') ?></td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProdukModal<?= $p['id'] ?>">Edit</button>
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProdukModal<?= $p['id'] ?>">Delete</button>
                      </div>
                    </td>
                  </tr>

                  <!-- Edit Modal -->
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
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" required>
                              <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === (int)$p['supplier_id']) ? 'selected' : '' ?>>
                                  <?= htmlspecialchars($s['nama']) ?> <?= $s['username'] ? '@'.htmlspecialchars($s['username']) : '(no user)' ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="mb-3">
                            <label class="form-label">Jenis</label>
                            <select class="form-select" name="jenis" required>
                              <option value="kudapan" <?= $p['jenis']==='kudapan'?'selected':'' ?>>Kudapan</option>
                              <option value="minuman" <?= $p['jenis']==='minuman'?'selected':'' ?>>Minuman</option>
                            </select>
                          </div>

                          <div class="row g-3">
                            <div class="col-md-6">
                              <div class="form-floating">
                                <input type="number" step="0.01" class="form-control" id="hs<?= $p['id'] ?>" name="harga_supplier" value="<?= htmlspecialchars((string)$p['harga_supplier']) ?>" required>
                                <label for="hs<?= $p['id'] ?>">Harga Supplier</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-floating">
                                <input type="number" step="0.01" class="form-control" id="mf<?= $p['id'] ?>" name="margin_fnb" value="<?= htmlspecialchars((string)$p['margin_fnb']) ?>" required>
                                <label for="mf<?= $p['id'] ?>">Margin FnB</label>
                              </div>
                            </div>
                          </div>
                          <div class="form-text mt-2">
                            Harga jual dihitung otomatis dari harga supplier + margin FnB (kolom generated).
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                          <button class="btn btn-primary" type="submit">Simpan</button>
                        </div>
                      </form>
                    </div></div>
                  </div>

                  <!-- Delete Modal -->
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

      <!-- Create Modal -->
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
                  <label class="form-label">Supplier</label>
                  <select class="form-select" name="supplier_id" required>
                    <option value="">-- Pilih Supplier --</option>
                    <?php foreach ($suppliers as $s): ?>
                      <option value="<?= $s['id'] ?>">
                        <?= htmlspecialchars($s['nama']) ?> <?= $s['username'] ? '@'.htmlspecialchars($s['username']) : '(no user)' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Supplier tanpa user akan ditandai (no user).</div>
                </div>
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
                <div class="col-md-6">
                  <div class="form-floating">
                    <input type="number" step="0.01" class="form-control" id="mfBaru" name="margin_fnb" placeholder="Margin FnB" required>
                    <label for="mfBaru">Margin FnB</label>
                  </div>
                </div>
              </div>
              <div class="form-text mt-2">
                Harga jual akan otomatis dihitung oleh database dari harga supplier + margin FnB (kolom generated).
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button class="btn btn-primary" type="submit">Simpan</button>
            </div>
          </form>
        </div></div>
      </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>