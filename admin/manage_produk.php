<?php
declare(strict_types=1);

/**
 * Admin - Manage Produk
 * - CRUD produk untuk supplier mana pun
 * - Tampilkan Margin FnB & Harga Jual (kolom generated dari DB)
 * - Nama produk wajib unik (case-insensitive) pada Create & Update
 * - Upload foto produk (create/edit) dan hapus foto saat delete
 */

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
(function_exists('requireRole') ? requireRole('admin') : checkRole('admin'));
require_once __DIR__ . '/../includes/koneksi.php';
require_once __DIR__ . '/../includes/file_upload.php';
include_once __DIR__ . '/../includes/navbar_admin.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function get($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

$flash = null;

/* Actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid, silakan muat ulang.'];
  } else {
    $action = post('action');

    if ($action === 'create_produk') {
      $supplier_id     = post('supplier_id');
      $nama            = ucwords(strtolower(trim(post('nama'))));
      $jenis           = post('jenis');
      $harga_supplier  = post('harga_supplier');
      $margin_fnb      = post('margin_fnb');

      if (!ctype_digit($supplier_id) || $nama === '' || !in_array($jenis, ['kudapan','minuman'], true) || !is_numeric($harga_supplier) || !is_numeric($margin_fnb)) {
        $flash = ['type'=>'danger','msg'=>'Input produk tidak valid.'];
      } else {
        // Cek nama unik
        $stmtDupe = $conn->prepare("SELECT COUNT(*) AS c FROM produk WHERE LOWER(nama) = LOWER(?)");
        $stmtDupe->bind_param("s", $nama);
        $stmtDupe->execute();
        $dupeCount = (int)$stmtDupe->get_result()->fetch_assoc()['c'];
        $stmtDupe->close();

        if ($dupeCount > 0) {
          $flash = ['type'=>'danger','msg'=>'Nama produk sudah digunakan. Gunakan nama lain (unik).'];
        } else {
          $sid = (int)$supplier_id;
          $stmtS = $conn->prepare("SELECT id FROM supplier WHERE id = ? LIMIT 1");
          $stmtS->bind_param("i", $sid);
          $stmtS->execute();
          $exS = $stmtS->get_result()->fetch_assoc();
          $stmtS->close();

          if (!$exS) {
            $flash = ['type'=>'danger','msg'=>'Supplier tidak ditemukan.'];
          } else {
            $hs  = (float)$harga_supplier;
            $mf  = (float)$margin_fnb;
            $foto_path = null;

            try {
              if (!empty($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
                $foto_path = save_product_image($_FILES['foto'], $sid);
              }
            } catch (Throwable $e) {
              $flash = ['type'=>'danger','msg'=>'Upload foto gagal: ' . htmlspecialchars($e->getMessage())];
            }

            $stmt = $conn->prepare("INSERT INTO produk (nama, jenis, harga_supplier, margin_fnb, supplier_id, foto_path) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddis", $nama, $jenis, $hs, $mf, $sid, $foto_path);
            try {
              if ($stmt->execute()) {
                $flash = ['type'=>'success','msg'=>'Produk berhasil ditambahkan.'];
              } else {
                $flash = ['type'=>'danger','msg'=>'Gagal menambah produk: ' . htmlspecialchars($stmt->error)];
              }
            } catch (Throwable $e) {
              if ($foto_path) delete_product_image($foto_path);
              $flash = ['type'=>'danger','msg'=>'Gagal menambah produk (nama duplikat di database). Gunakan nama lain.'];
            }
            $stmt->close();
          }
        }
      }

    } elseif ($action === 'update_produk') {
      $id              = post('id');
      $supplier_id     = post('supplier_id');
      $nama            = ucwords(strtolower(trim(post('nama'))));
      $jenis           = post('jenis');
      $harga_supplier  = post('harga_supplier');
      $margin_fnb      = post('margin_fnb');
      $hapus_foto      = post('hapus_foto'); // 'yes' or ''

      if (!ctype_digit($id) || !ctype_digit($supplier_id) || $nama === '' || !in_array($jenis, ['kudapan','minuman'], true) || !is_numeric($harga_supplier) || !is_numeric($margin_fnb)) {
        $flash = ['type'=>'danger','msg'=>'Input produk tidak valid.'];
      } else {
        $pid = (int)$id;
        $sid = (int)$supplier_id;

        // Cek nama unik exclude dirinya
        $stmtDupe = $conn->prepare("SELECT COUNT(*) AS c FROM produk WHERE LOWER(nama) = LOWER(?) AND id <> ?");
        $stmtDupe->bind_param("si", $nama, $pid);
        $stmtDupe->execute();
        $dupeCount = (int)$stmtDupe->get_result()->fetch_assoc()['c'];
        $stmtDupe->close();

        if ($dupeCount > 0) {
          $flash = ['type'=>'danger','msg'=>'Nama produk sudah digunakan. Gunakan nama lain (unik).'];
        } else {
          // Ambil foto lama
          $stmtOld = $conn->prepare("SELECT foto_path FROM produk WHERE id = ? LIMIT 1");
          $stmtOld->bind_param("i", $pid);
          $stmtOld->execute();
          $old = $stmtOld->get_result()->fetch_assoc();
          $stmtOld->close();
          $oldFoto = $old['foto_path'] ?? null;

          $hs  = (float)$harga_supplier;
          $mf  = (float)$margin_fnb;
          $newFoto = $oldFoto;

          try {
            if (!empty($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
              $newFoto = save_product_image($_FILES['foto'], $sid);
            } elseif ($hapus_foto === 'yes') {
              $newFoto = null;
            }
          } catch (Throwable $e) {
            $flash = ['type'=>'danger','msg'=>'Upload foto gagal: ' . htmlspecialchars($e->getMessage())];
          }

          $stmt = $conn->prepare("UPDATE produk SET nama = ?, jenis = ?, harga_supplier = ?, margin_fnb = ?, supplier_id = ?, foto_path = ? WHERE id = ?");
          $stmt->bind_param("ssddisi", $nama, $jenis, $hs, $mf, $sid, $newFoto, $pid);
          try {
            $stmt->execute();
            // Hapus foto lama jika diganti atau diminta hapus
            if ($newFoto && $oldFoto && $newFoto !== $oldFoto) delete_product_image($oldFoto);
            if ($hapus_foto === 'yes' && $oldFoto) delete_product_image($oldFoto);
            $flash = ['type'=>'success','msg'=>'Produk diperbarui.'];
          } catch (Throwable $e) {
            if ($newFoto && $newFoto !== $oldFoto) delete_product_image($newFoto);
            $flash = ['type'=>'danger','msg'=>'Gagal memperbarui produk (nama duplikat di database). Gunakan nama lain.'];
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
          $flash = ['type'=>'danger','msg'=>'Tidak dapat menghapus, ada stok terkait.'];
        } else {
          // Ambil foto lama
          $stmtOld = $conn->prepare("SELECT foto_path FROM produk WHERE id = ? LIMIT 1");
          $stmtOld->bind_param("i", $pid);
          $stmtOld->execute();
          $old = $stmtOld->get_result()->fetch_assoc();
          $stmtOld->close();
          $oldFoto = $old['foto_path'] ?? null;

          $stmt = $conn->prepare("DELETE FROM produk WHERE id = ?");
          $stmt->bind_param("i", $pid);
          if ($stmt->execute() && $stmt->affected_rows > 0) {
            if ($oldFoto) delete_product_image($oldFoto);
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

/* Listing & pagination */
$q     = get('q', '');
$page  = max(1, (int)get('page', '1'));
$limit = 10;
$offset = ($page - 1) * $limit;

$suppliers = $conn->query("
  SELECT s.id, s.nama, s.user_id, u.username
  FROM supplier s
  LEFT JOIN users u ON u.id = s.user_id
  ORDER BY s.nama ASC
")->fetch_all(MYSQLI_ASSOC);

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

if ($q !== '') {
  $like = '%' . $q . '%';
  $sql = "
    SELECT p.id, p.nama, p.jenis, p.harga_supplier, p.margin_fnb, p.harga_jual, p.foto_path,
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
    SELECT p.id, p.nama, p.jenis, p.harga_supplier, p.margin_fnb, p.harga_jual, p.foto_path,
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

      <form class="d-flex mb-3" method="get">
        <input type="text" name="q" class="form-control me-2" placeholder="Cari nama produk / supplier..." value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i> Cari</button>
      </form>

      <div class="card shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Foto</th>
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
                  <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                <?php else: foreach ($products as $p): ?>
                  <tr>
                    <td><?= $p['id'] ?></td>
                    <td>
                      <?php if (!empty($p['foto_path'])): ?>
                        <img src="<?= htmlspecialchars($p['foto_path']) ?>" alt="<?= htmlspecialchars($p['nama']) ?>" style="height:40px;width:auto;border-radius:4px;">
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
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
                      <form method="post" enctype="multipart/form-data">
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

                          <div class="mb-2">
                            <label class="form-label">Foto Produk (opsional)</label>
                            <input type="file" class="form-control" name="foto" accept="image/*" capture="environment">
                            <div class="form-text">Ambil dari kamera (mobile/iPad) atau pilih dari file explorer (desktop).</div>
                          </div>

                          <?php if (!empty($p['foto_path'])): ?>
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" value="yes" id="hapusFoto<?= $p['id'] ?>" name="hapus_foto">
                              <label class="form-check-label" for="hapusFoto<?= $p['id'] ?>">Hapus foto saat simpan</label>
                            </div>
                          <?php endif; ?>

                          <div class="form-text mt-2">
                            Harga jual ditampilkan otomatis dari harga supplier + margin FnB (kolom generated di database). :llmCitationRef[1]
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
          <form method="post" enctype="multipart/form-data">
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
                      <option value="<?= (int)$s['id'] ?>">
                        <?= htmlspecialchars($s['nama']) ?> <?= $s['username'] ? '@'.htmlspecialchars($s['username']) : '(no user)' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
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
                <div class="col-md-6">
                  <label class="form-label">Foto Produk (opsional)</label>
                  <input type="file" class="form-control" name="foto" accept="image/*" capture="environment">
                </div>
              </div>

              <div class="form-text mt-2">
                Nama produk harus unik (case-insensitive). Harga jual ditampilkan otomatis dari harga supplier + margin FnB (kolom generated di database). :llmCitationRef[2]
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

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input[name="nama"]').forEach(function(input) {
            input.addEventListener('input', function(e) {
                let words = e.target.value.toLowerCase().split(' ');
                for (let i = 0; i < words.length; i++) {
                    if (words[i].length > 0) {
                        words[i] = words[i][0].toUpperCase() + words[i].substr(1);
                    }
                }
                e.target.value = words.join(' ');
            });
        });
    });
    </script>
  </body>
</html>