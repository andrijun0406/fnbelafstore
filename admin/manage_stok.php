<?php
declare(strict_types=1);

/**
 * Admin - Manage Stok
 * Fitur:
 * - Input stok masuk (produk, tanggal_masuk, jumlah_masuk, opsi override shelf_life_days)
 * - Daftar stok hari ini dan stok aktif
 * - Pencatatan stok akhir harian (jumlah_sisa, status otomatis, jumlah_terjual)
 * - Cegah duplikasi stok_akhir per hari via unique key (stok_id, processed_date)
 */

// Debug sementara (MATIKAN di production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Mulai sesi lebih awal
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../includes/auth.php';
if (function_exists('requireRole')) {
  requireRole('admin'); // Hanya admin
} else {
  checkRole('admin');
}
require_once __DIR__ . '/../includes/koneksi.php';

// Muat partial navbar admin konsisten
include_once __DIR__ . '/../includes/navbar_admin.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post($k, $d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function get($k, $d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

$flash = null;
$today = date('Y-m-d');

// Aksi POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid, silakan muat ulang.'];
  } else {
    $action = post('action');

    if ($action === 'create_stok') {
      $produk_id = post('produk_id');
      $tanggal_masuk = post('tanggal_masuk', $today);
      $jumlah_masuk = post('jumlah_masuk');
      $shelf_life_days = post('shelf_life_days'); // opsional override

      if (!ctype_digit($produk_id) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_masuk) || !ctype_digit($jumlah_masuk)) {
        $flash = ['type'=>'danger','msg'=>'Input stok tidak valid.'];
      } else {
        $pid = (int)$produk_id;
        $jm  = (int)$jumlah_masuk;
        $sld = ($shelf_life_days !== '' && ctype_digit($shelf_life_days)) ? (int)$shelf_life_days : null;

        // Ambil jenis produk untuk default shelf life jika tidak diisi
        $stmtJ = $conn->prepare("SELECT jenis FROM produk WHERE id = ? LIMIT 1");
        $stmtJ->bind_param("i", $pid);
        $stmtJ->execute();
        $rowJ = $stmtJ->get_result()->fetch_assoc();
        $stmtJ->close();

        if (!$rowJ) {
          $flash = ['type'=>'danger','msg'=>'Produk tidak ditemukan.'];
        } else {
          // Hitung expired_at
          if ($sld === null || $sld <= 0) {
            $sld = ($rowJ['jenis'] === 'kudapan') ? 1 : 3; // default bisnis
          }
          $exp = date('Y-m-d', strtotime("$tanggal_masuk +$sld day"));

          // Insert stok
          $stmt = $conn->prepare("INSERT INTO stok (produk_id, tanggal_masuk, jumlah_masuk, shelf_life_days, expired_at) VALUES (?, ?, ?, ?, ?)");
          $stmt->bind_param("isiss", $pid, $tanggal_masuk, $jm, $sld, $exp);
          if ($stmt->execute()) {
            $flash = ['type'=>'success','msg'=>'Stok masuk berhasil ditambahkan.'];
          } else {
            $flash = ['type'=>'danger','msg'=>'Gagal menambah stok: ' . htmlspecialchars($stmt->error)];
          }
          $stmt->close();
        }
      }

    } elseif ($action === 'create_stok_akhir') {
      $stok_id = post('stok_id');
      $jumlah_sisa = post('jumlah_sisa');

      if (!ctype_digit($stok_id) || !ctype_digit($jumlah_sisa)) {
        $flash = ['type'=>'danger','msg'=>'Input stok akhir tidak valid.'];
      } else {
        $sid = (int)$stok_id;
        $js  = (int)$jumlah_sisa;

        // Ambil stok untuk validasi dan hitung status
        $stmtS = $conn->prepare("SELECT jumlah_masuk, expired_at FROM stok WHERE id = ? LIMIT 1");
        $stmtS->bind_param("i", $sid);
        $stmtS->execute();
        $rowS = $stmtS->get_result()->fetch_assoc();
        $stmtS->close();

        if (!$rowS) {
          $flash = ['type'=>'danger','msg'=>'Data stok tidak ditemukan.'];
        } else {
          $jm = (int)$rowS['jumlah_masuk'];
          if ($js < 0 || $js > $jm) {
            $flash = ['type'=>'danger','msg'=>'Jumlah sisa harus antara 0 dan jumlah masuk.'];
          } else {
            $jt = $jm - $js; // jumlah terjual
            $expired_at = $rowS['expired_at'];
            $status = 'carried_over';
            if ($js === 0) {
              $status = 'sold_out';
            } elseif ($today >= $expired_at && $js > 0) {
              $status = 'expired';
            }

            // Insert/Upsert stok_akhir untuk tanggal hari ini
            $stmtA = $conn->prepare("
              INSERT INTO stok_akhir (stok_id, jumlah_sisa, processed_date, jumlah_terjual, status)
              VALUES (?, ?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
                jumlah_sisa = VALUES(jumlah_sisa),
                jumlah_terjual = VALUES(jumlah_terjual),
                status = VALUES(status)
            ");
            $stmtA->bind_param("iisis", $sid, $js, $today, $jt, $status);
            if ($stmtA->execute()) {
              $flash = ['type'=>'success','msg'=>'Stok akhir berhasil dicatat.'];
            } else {
              $flash = ['type'=>'danger','msg'=>'Gagal mencatat stok akhir: ' . htmlspecialchars($stmtA->error)];
            }
            $stmtA->close();
          }
        }
      }
    }
  }
}

// Data untuk tampilan
// Produk daftar (untuk input stok)
$products = $conn->query("
  SELECT p.id, p.nama, p.jenis, s.nama AS supplier
  FROM produk p
  JOIN supplier s ON s.id = p.supplier_id
  ORDER BY s.nama ASC, p.nama ASC
")->fetch_all(MYSQLI_ASSOC);

// Stok hari ini
$stmtToday = $conn->prepare("
  SELECT st.id, st.produk_id, st.tanggal_masuk, st.jumlah_masuk, st.expired_at,
         p.nama AS produk_nama, p.jenis,
         sa.jumlah_sisa, sa.jumlah_terjual, sa.status
  FROM stok st
  JOIN produk p ON p.id = st.produk_id
  LEFT JOIN stok_akhir sa ON sa.stok_id = st.id AND sa.processed_date = ?
  WHERE st.tanggal_masuk = ?
  ORDER BY st.id DESC
");
$stmtToday->bind_param("ss", $today, $today);
$stmtToday->execute();
$stok_today = $stmtToday->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtToday->close();

// Stok aktif (belum expired, sisa > 0 kemarin-kemarin)
$stmtActive = $conn->prepare("
  SELECT st.id, st.produk_id, st.tanggal_masuk, st.jumlah_masuk, st.expired_at,
         p.nama AS produk_nama, p.jenis,
         COALESCE(sa.jumlah_sisa, st.jumlah_masuk) AS sisa_terkini,
         sa.status, sa.processed_date
  FROM stok st
  JOIN produk p ON p.id = st.produk_id
  LEFT JOIN stok_akhir sa 
         ON sa.stok_id = st.id 
         AND sa.processed_date = (
           SELECT MAX(sa2.processed_date) FROM stok_akhir sa2 WHERE sa2.stok_id = st.id
         )
  WHERE CURDATE() < st.expired_at
  ORDER BY st.expired_at ASC, st.id DESC
");
$stmtActive->execute();
$stok_active = $stmtActive->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtActive->close();

?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Stok - Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<?php render_admin_navbar(); ?>

<main class="container py-4">
  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <!-- Input Stok Masuk -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">Stok Masuk (Hari Ini)</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="create_stok">

        <div class="col-md-6">
          <label class="form-label">Produk</label>
          <select name="produk_id" class="form-select" required>
            <option value="">-- Pilih Produk --</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>">
                <?= htmlspecialchars($p['supplier']) ?> — <?= htmlspecialchars($p['nama']) ?> (<?= htmlspecialchars($p['jenis']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <div class="form-floating">
            <input type="date" class="form-control" id="tanggalMasuk" name="tanggal_masuk" value="<?= htmlspecialchars($today) ?>" required>
            <label for="tanggalMasuk">Tanggal masuk</label>
          </div>
        </div>

        <div class="col-md-3">
          <div class="form-floating">
            <input type="number" class="form-control" id="jumlahMasuk" name="jumlah_masuk" min="1" step="1" placeholder="Jumlah" required>
            <label for="jumlahMasuk">Jumlah masuk</label>
          </div>
        </div>

        <div class="col-md-4">
          <div class="form-floating">
            <input type="number" class="form-control" id="shelfLifeDays" name="shelf_life_days" min="1" step="1" placeholder="Override masa expired (hari)">
            <label for="shelfLifeDays">Masa expired (hari, opsional)</label>
          </div>
          <div class="form-text">Kosongkan untuk default: 1 hari (kudapan), 3 hari (minuman).</div>
        </div>

        <div class="col-12">
          <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle me-1"></i> Tambah Stok</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Daftar Stok Hari Ini -->
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Stok Hari Ini</span>
      <span class="text-muted small"><?= htmlspecialchars($today) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Produk</th>
              <th>Jenis</th>
              <th class="text-end">Jumlah Masuk</th>
              <th>Expired At</th>
              <th>Stok Akhir (Hari Ini)</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($stok_today)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Belum ada stok masuk hari ini.</td></tr>
            <?php else: foreach ($stok_today as $st): ?>
              <tr>
                <td><?= $st['id'] ?></td>
                <td><?= htmlspecialchars($st['produk_nama']) ?></td>
                <td><span class="badge text-bg-secondary"><?= htmlspecialchars($st['jenis']) ?></span></td>
                <td class="text-end"><?= (int)$st['jumlah_masuk'] ?></td>
                <td><?= htmlspecialchars($st['expired_at']) ?></td>
                <td>
                  <?php if ($st['jumlah_sisa'] !== null): ?>
                    <span class="badge text-bg-info">Sisa: <?= (int)$st['jumlah_sisa'] ?></span>
                    <span class="badge text-bg-secondary">Terjual: <?= (int)$st['jumlah_terjual'] ?></span>
                    <span class="badge text-bg-<?= $st['status']==='expired'?'danger':($st['status']==='sold_out'?'success':'warning') ?>">
                      <?= htmlspecialchars($st['status']) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">Belum diproses</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stokAkhirModal<?= $st['id'] ?>">
                    Catat Stok Akhir
                  </button>
                </td>
              </tr>

              <!-- Modal stok akhir -->
              <div class="modal fade" id="stokAkhirModal<?= $st['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                  <form method="post">
                    <div class="modal-header">
                      <h5 class="modal-title">Stok Akhir — <?= htmlspecialchars($st['produk_nama']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="create_stok_akhir">
                      <input type="hidden" name="stok_id" value="<?= $st['id'] ?>">

                      <div class="form-floating">
                        <input type="number" class="form-control" id="jumlahSisa<?= $st['id'] ?>"
                               name="jumlah_sisa" min="0" max="<?= (int)$st['jumlah_masuk'] ?>" step="1" placeholder="Jumlah sisa" required>
                        <label for="jumlahSisa<?= $st['id'] ?>">Jumlah sisa</label>
                      </div>
                      <div class="form-text mt-2">
                        Terjual = jumlah masuk − jumlah sisa. Status otomatis: sold_out, expired, atau carried_over.
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                      <button class="btn btn-primary" type="submit">Simpan</button>
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

  <!-- Stok Aktif (Carry-over sebelum expired) -->
  <div class="card shadow-sm">
    <div class="card-header">Stok Aktif (Belum expired, sisa ada)</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Produk</th>
              <th>Jenis</th>
              <th>Tanggal Masuk</th>
              <th>Expired At</th>
              <th class="text-end">Sisa Terkini</th>
              <th>Status Terakhir</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($stok_active)): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada stok aktif.</td></tr>
            <?php else: foreach ($stok_active as $sa): ?>
              <tr>
                <td><?= $sa['id'] ?></td>
                <td><?= htmlspecialchars($sa['produk_nama']) ?></td>
                <td><span class="badge text-bg-secondary"><?= htmlspecialchars($sa['jenis']) ?></span></td>
                <td><?= htmlspecialchars($sa['tanggal_masuk']) ?></td>
                <td><?= htmlspecialchars($sa['expired_at']) ?></td>
                <td class="text-end"><?= (int)$sa['sisa_terkini'] ?></td>
                <td>
                  <?php if ($sa['processed_date']): ?>
                    <span class="badge text-bg-<?= $sa['status']==='expired'?'danger':($sa['status']==='sold_out'?'success':'warning') ?>">
                      <?= htmlspecialchars($sa['status']) ?>
                    </span>
                    <small class="text-muted">pada <?= htmlspecialchars($sa['processed_date']) ?></small>
                  <?php else: ?>
                    <span class="text-muted">Belum pernah dicatat</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</main>

<footer class="border-top mt-4">
  <div class="container py-3">
    <small class="text-muted">&copy; <?= date('Y'); ?> F &amp; B ELAF Store</small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>