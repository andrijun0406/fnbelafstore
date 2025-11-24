<?php
declare(strict_types=1);

// Debug sementara (matikan di production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../includes/auth.php';
(function_exists('requireRole') ? requireRole('supplier') : checkRole('supplier'));
require_once __DIR__ . '/../includes/koneksi.php';
include_once __DIR__ . '/../includes/navbar_supplier.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars((string)$s); }

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Dapatkan supplier yang tertaut ke user login
$stmtSup = $conn->prepare("SELECT id, nama FROM supplier WHERE user_id = ? LIMIT 1");
$stmtSup->bind_param("i", $user_id);
$stmtSup->execute();
$supplier = $stmtSup->get_result()->fetch_assoc();
$stmtSup->close();

$flash = null;
$today = date('Y-m-d');

// Filter harian
$daily_date = get('date', $today);

// Filter bulanan
$month = (int) get('month', date('n'));
$year  = (int) get('year',  date('Y'));
$month = ($month >=1 && $month <=12) ? $month : (int)date('n');
$year  = ($year >=1970 && $year <=2100) ? $year : (int)date('Y');

// Filter referensi minggu untuk panel pembayaran mingguan
$ref_date = get('ref_date', $today);
$ref = DateTime::createFromFormat('Y-m-d', $ref_date) ?: new DateTime($today);
$week_start = (clone $ref)->modify('monday this week')->format('Y-m-d');
$week_end   = (clone $ref)->modify('sunday this week')->format('Y-m-d');

if (!$supplier) {
  $flash = ['type'=>'danger','msg'=>'Akun supplier belum ditautkan.'];
} else {
  $supId = (int)$supplier['id'];

  // Daily aggregates untuk supplier (hanya unit & keuntungan supplier)
  $stmtDaily = $conn->prepare("
    SELECT 
      COALESCE(SUM(sa.jumlah_terjual), 0) AS units,
      COALESCE(SUM(sa.jumlah_terjual * p.harga_supplier), 0) AS supplier_profit
    FROM stok_akhir sa
    JOIN stok st ON st.id = sa.stok_id
    JOIN produk p ON p.id = st.produk_id
    WHERE sa.processed_date = ? AND p.supplier_id = ?
  ");
  $stmtDaily->bind_param("si", $daily_date, $supId);
  $stmtDaily->execute();
  $daily = $stmtDaily->get_result()->fetch_assoc();
  $stmtDaily->close();

  // Monthly aggregates untuk supplier (hanya unit & keuntungan supplier)
  $stmtMonthly = $conn->prepare("
    SELECT 
      COALESCE(SUM(sa.jumlah_terjual), 0) AS units,
      COALESCE(SUM(sa.jumlah_terjual * p.harga_supplier), 0) AS supplier_profit
    FROM stok_akhir sa
    JOIN stok st ON st.id = sa.stok_id
    JOIN produk p ON p.id = st.produk_id
    WHERE MONTH(sa.processed_date) = ? AND YEAR(sa.processed_date) = ? AND p.supplier_id = ?
  ");
  $stmtMonthly->bind_param("iii", $month, $year, $supId);
  $stmtMonthly->execute();
  $monthly = $stmtMonthly->get_result()->fetch_assoc();
  $stmtMonthly->close();

  // Per produk (harian: unit & keuntungan supplier)
  $stmtDailyProducts = $conn->prepare("
    SELECT p.nama AS produk_nama, p.jenis,
           COALESCE(SUM(sa.jumlah_terjual), 0) AS units,
           COALESCE(SUM(sa.jumlah_terjual * p.harga_supplier), 0) AS supplier_profit
    FROM stok_akhir sa
    JOIN stok st ON st.id = sa.stok_id
    JOIN produk p ON p.id = st.produk_id
    WHERE sa.processed_date = ? AND p.supplier_id = ?
    GROUP BY p.id, p.nama, p.jenis
    ORDER BY p.nama ASC
  ");
  $stmtDailyProducts->bind_param("si", $daily_date, $supId);
  $stmtDailyProducts->execute();
  $daily_products = $stmtDailyProducts->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtDailyProducts->close();

  // Per produk (bulanan: unit & keuntungan supplier)
  $stmtMonthlyProducts = $conn->prepare("
    SELECT p.nama AS produk_nama, p.jenis,
           COALESCE(SUM(sa.jumlah_terjual), 0) AS units,
           COALESCE(SUM(sa.jumlah_terjual * p.harga_supplier), 0) AS supplier_profit
    FROM stok_akhir sa
    JOIN stok st ON st.id = sa.stok_id
    JOIN produk p ON p.id = st.produk_id
    WHERE MONTH(sa.processed_date) = ? AND YEAR(sa.processed_date) = ? AND p.supplier_id = ?
    GROUP BY p.id, p.nama, p.jenis
    ORDER BY p.nama ASC
  ");
  $stmtMonthlyProducts->bind_param("iii", $month, $year, $supId);
  $stmtMonthlyProducts->execute();
  $monthly_products = $stmtMonthlyProducts->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtMonthlyProducts->close();

  // Panel pembayaran mingguan (pending & histori)
  $stmtWeeklyPending = $conn->prepare("
    SELECT period_date, amount, status, paid_at
    FROM supplier_payments
    WHERE supplier_id = ? AND period_type = 'weekly' AND status = 'pending'
    ORDER BY period_date DESC
    LIMIT 20
  ");
  $stmtWeeklyPending->bind_param("i", $supId);
  $stmtWeeklyPending->execute();
  $weekly_pending = $stmtWeeklyPending->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtWeeklyPending->close();

  $stmtWeeklyHistory = $conn->prepare("
    SELECT period_date, amount, status, paid_at
    FROM supplier_payments
    WHERE supplier_id = ? AND period_type = 'weekly' AND status = 'paid'
    ORDER BY period_date DESC
    LIMIT 20
  ");
  $stmtWeeklyHistory->bind_param("i", $supId);
  $stmtWeeklyHistory->execute();
  $weekly_history = $stmtWeeklyHistory->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtWeeklyHistory->close();
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan - Supplier</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/brand.css">
    <link rel="icon" type="image/png" href="/assets/images/logo/logo_image_only.png">
  </head>
  <body class="bg-light">
    <?php render_supplier_navbar(); ?>

    <main class="container py-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
      <?php endif; ?>

      <?php if ($supplier): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h1 class="h4 mb-0">Laporan - Supplier</h1>
          <span class="text-muted">Supplier: <?= h($supplier['nama']) ?></span>
        </div>

        <!-- Laporan Harian (hanya Unit & Keuntungan Supplier) -->
        <div class="card shadow-sm mb-3">
          <div class="card-header">Laporan Harian</div>
          <div class="card-body">
            <form class="row g-3" method="get">
              <div class="col-md-4">
                <label class="form-label">Tanggal</label>
                <input type="date" name="date" value="<?= h($daily_date) ?>" class="form-control" required>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-outline-secondary" type="submit">Terapkan</button>
              </div>
            </form>

            <div class="row g-3 mt-2">
              <div class="col-md-6">
                <div class="card"><div class="card-body">
                  <div class="text-muted">Unit Terjual</div>
                  <div class="fs-5"><?= (int)($daily['units'] ?? 0) ?></div>
                </div></div>
              </div>
              <div class="col-md-6">
                <div class="card"><div class="card-body">
                  <div class="text-muted">Keuntungan Supplier</div>
                  <div class="fs-5">Rp <?= number_format((float)($daily['supplier_profit'] ?? 0), 2, ',', '.') ?></div>
                </div></div>
              </div>
            </div>

            <div class="table-responsive mt-3">
              <table class="table table-striped table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Produk</th>
                    <th>Jenis</th>
                    <th class="text-end">Unit</th>
                    <th class="text-end">Keuntungan Supplier</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($daily_products)): ?>
                    <tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>
                  <?php else: foreach ($daily_products as $r): ?>
                    <tr>
                      <td><?= h($r['produk_nama']) ?></td>
                      <td><span class="badge text-bg-secondary"><?= h($r['jenis']) ?></span></td>
                      <td class="text-end"><?= (int)$r['units'] ?></td>
                      <td class="text-end">Rp <?= number_format((float)$r['supplier_profit'], 2, ',', '.') ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Laporan Bulanan (hanya Unit & Keuntungan Supplier) -->
        <div class="card shadow-sm mb-3">
          <div class="card-header">Laporan Bulanan</div>
          <div class="card-body">
            <form class="row g-3" method="get">
              <div class="col-md-2">
                <label class="form-label">Bulan</label>
                <select name="month" class="form-select">
                  <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= $m ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Tahun</label>
                <input type="number" name="year" value="<?= (int)$year ?>" class="form-control" required>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-outline-secondary" type="submit">Terapkan</button>
              </div>
            </form>

            <div class="row g-3 mt-2">
              <div class="col-md-6">
                <div class="card"><div class="card-body">
                  <div class="text-muted">Unit Terjual</div>
                  <div class="fs-5"><?= (int)($monthly['units'] ?? 0) ?></div>
                </div></div>
              </div>
              <div class="col-md-6">
                <div class="card"><div class="card-body">
                  <div class="text-muted">Keuntungan Supplier</div>
                  <div class="fs-5">Rp <?= number_format((float)($monthly['supplier_profit'] ?? 0), 2, ',', '.') ?></div>
                </div></div>
              </div>
            </div>

            <div class="table-responsive mt-3">
              <table class="table table-striped table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Produk</th>
                    <th>Jenis</th>
                    <th class="text-end">Unit</th>
                    <th class="text-end">Keuntungan Supplier</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($monthly_products)): ?>
                    <tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>
                  <?php else: foreach ($monthly_products as $r): ?>
                    <tr>
                      <td><?= h($r['produk_nama']) ?></td>
                      <td><span class="badge text-bg-secondary"><?= h($r['jenis']) ?></span></td>
                      <td class="text-end"><?= (int)$r['units'] ?></td>
                      <td class="text-end">Rp <?= number_format((float)$r['supplier_profit'], 2, ',', '.') ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Panel Pembayaran Mingguan (tetap amount & status) -->
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span>Pembayaran Mingguan</span>
            <form class="d-flex align-items-center gap-2" method="get">
              <input type="hidden" name="date" value="<?= h($daily_date) ?>">
              <input type="hidden" name="month" value="<?= (int)$month ?>">
              <input type="hidden" name="year" value="<?= (int)$year ?>">
              <label class="form-label mb-0">Referensi Minggu</label>
              <input type="date" name="ref_date" value="<?= h($ref_date) ?>" class="form-control" style="max-width:180px">
              <button class="btn btn-outline-secondary" type="submit">Terapkan</button>
            </form>
          </div>
          <div class="card-body">
            <div class="mb-2 text-muted">
              Periode minggu ini: <?= h($week_start) ?> s.d. <?= h($week_end) ?>.
            </div>

            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <div class="card">
                  <div class="card-header">Pending</div>
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                          <tr>
                            <th>Tanggal (Akhir Minggu)</th>
                            <th class="text-end">Jumlah</th>
                            <th>Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if (empty($weekly_pending)): ?>
                            <tr><td colspan="3" class="text-center text-muted">Tidak ada pembayaran pending.</td></tr>
                          <?php else: foreach ($weekly_pending as $pp): ?>
                            <tr>
                              <td><?= h($pp['period_date']) ?></td>
                              <td class="text-end">Rp <?= number_format((float)$pp['amount'], 2, ',', '.') ?></td>
                              <td><span class="badge text-bg-warning"><?= h($pp['status']) ?></span></td>
                            </tr>
                          <?php endforeach; endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-lg-6">
                <div class="card">
                  <div class="card-header">Histori (Paid)</div>
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-striped table-hover mb-0 align-middle">
                        <thead class="table-light">
                          <tr>
                            <th>Tanggal (Akhir Minggu)</th>
                            <th class="text-end">Jumlah</th>
                            <th>Status</th>
                            <th>Dibayar Pada</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if (empty($weekly_history)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Belum ada histori paid.</td></tr>
                          <?php else: foreach ($weekly_history as $pp): ?>
                            <tr>
                              <td><?= h($pp['period_date']) ?></td>
                              <td class="text-end">Rp <?= number_format((float)$pp['amount'], 2, ',', '.') ?></td>
                              <td><span class="badge text-bg-success"><?= h($pp['status']) ?></span></td>
                              <td><?= h($pp['paid_at'] ?? '-') ?></td>
                            </tr>
                          <?php endforeach; endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

            </div>

          </div>
        </div>

      <?php endif; ?>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>