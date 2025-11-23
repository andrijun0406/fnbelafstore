<?php
declare(strict_types=1);

// Debug sementara
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/../includes/auth.php';
(function_exists('requireRole') ? requireRole('admin') : checkRole('admin'));
require_once __DIR__ . '/../includes/koneksi.php';
include_once __DIR__ . '/../includes/navbar_admin.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function get($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

$flash = null;
$today = date('Y-m-d');

// Aksi: tandai pembayaran supplier sebagai paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'mark_paid') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid.'];
  } else {
    $payment_id = post('payment_id');
    if (ctype_digit($payment_id)) {
      $stmt = $conn->prepare("UPDATE supplier_payments SET status='paid', paid_at=CURDATE() WHERE id=? AND status='pending'");
      $stmt->bind_param("i", $payment_id);
      $stmt->execute();
      $stmt->close();
      $flash = ['type'=>'success','msg'=>'Pembayaran ditandai sebagai paid.'];
    }
  }
}

// Aksi: generate payment harian (supplier_profit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'generate_payment_daily') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid.'];
  } else {
    $date = post('date', $today);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      $flash = ['type'=>'danger','msg'=>'Tanggal harian tidak valid.'];
    } else {
      // Agregasi keuntungan supplier per supplier untuk tanggal harian
      $stmtAgg = $conn->prepare("
        SELECT s.id AS supplier_id,
               SUM(sa.jumlah_terjual * p.harga_supplier) AS supplier_profit
        FROM stok_akhir sa
        JOIN stok st ON st.id = sa.stok_id
        JOIN produk p ON p.id = st.produk_id
        JOIN supplier s ON s.id = p.supplier_id
        WHERE sa.processed_date = ?
        GROUP BY s.id
      ");
      $stmtAgg->bind_param("s", $date);
      $stmtAgg->execute();
      $rows = $stmtAgg->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmtAgg->close();

      // Upsert ke supplier_payments (pending)
      $stmtUpsert = $conn->prepare("
        INSERT INTO supplier_payments (supplier_id, period_type, period_date, amount, status)
        VALUES (?, 'daily', ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), status='pending', paid_at = NULL
      ");
      $count = 0;
      foreach ($rows as $r) {
        $sid = (int)$r['supplier_id'];
        $amt = (float)($r['supplier_profit'] ?? 0);
        $stmtUpsert->bind_param("isd", $sid, $date, $amt);
        $stmtUpsert->execute();
        $count++;
      }
      $stmtUpsert->close();
      $flash = ['type'=>'success','msg'=>"Generate payment harian untuk $count supplier pada $date berhasil."];
    }
  }
}

// Aksi: generate payment bulanan (supplier_profit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'generate_payment_monthly') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid.'];
  } else {
    $month = (int)post('month', date('n'));
    $year  = (int)post('year',  date('Y'));
    if ($month < 1 || $month > 12 || $year < 1970 || $year > 2100) {
      $flash = ['type'=>'danger','msg'=>'Bulan/tahun tidak valid.'];
    } else {
      // Tanggal periode diset ke hari terakhir bulan tsb
      $periodDateObj = DateTime::createFromFormat('Y-n-j', "$year-$month-1");
      $periodDateObj->modify('last day of this month');
      $period_date = $periodDateObj->format('Y-m-d');

      // Agregasi keuntungan supplier per supplier untuk bulan/tahun
      $stmtAgg = $conn->prepare("
        SELECT s.id AS supplier_id,
               SUM(sa.jumlah_terjual * p.harga_supplier) AS supplier_profit
        FROM stok_akhir sa
        JOIN stok st ON st.id = sa.stok_id
        JOIN produk p ON p.id = st.produk_id
        JOIN supplier s ON s.id = p.supplier_id
        WHERE MONTH(sa.processed_date) = ? AND YEAR(sa.processed_date) = ?
        GROUP BY s.id
      ");
      $stmtAgg->bind_param("ii", $month, $year);
      $stmtAgg->execute();
      $rows = $stmtAgg->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmtAgg->close();

      // Upsert ke supplier_payments (pending)
      $stmtUpsert = $conn->prepare("
        INSERT INTO supplier_payments (supplier_id, period_type, period_date, amount, status)
        VALUES (?, 'monthly', ?, ?, 'pending')
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), status='pending', paid_at = NULL
      ");
      $count = 0;
      foreach ($rows as $r) {
        $sid = (int)$r['supplier_id'];
        $amt = (float)($r['supplier_profit'] ?? 0);
        $stmtUpsert->bind_param("isd", $sid, $period_date, $amt);
        $stmtUpsert->execute();
        $count++;
      }
      $stmtUpsert->close();
      $flash = ['type'=>'success','msg'=>"Generate payment bulanan ($month/$year) untuk $count supplier berhasil."];
    }
  }
}

// Filters untuk tampilan laporan
$daily_date = get('date', $today);
$month = (int) get('month', date('n'));
$year  = (int) get('year',  date('Y'));
$month = ($month >= 1 && $month <= 12) ? $month : (int)date('n');
$year  = ($year >= 1970 && $year <= 2100) ? $year : (int)date('Y');

// Daily aggregates
$stmtDaily = $conn->prepare("
  SELECT 
    SUM(sa.jumlah_terjual) AS units,
    SUM(sa.jumlah_terjual * p.harga_jual) AS revenue,
    SUM(sa.jumlah_terjual * p.margin_fnb) AS fnb_profit,
    SUM(sa.jumlah_terjual * p.harga_supplier) AS supplier_profit
  FROM stok_akhir sa
  JOIN stok st ON st.id = sa.stok_id
  JOIN produk p ON p.id = st.produk_id
  WHERE sa.processed_date = ?
");
$stmtDaily->bind_param("s", $daily_date);
$stmtDaily->execute();
$daily = $stmtDaily->get_result()->fetch_assoc();
$stmtDaily->close();

// Daily per supplier
$stmtDailySup = $conn->prepare("
  SELECT s.id AS supplier_id, s.nama AS supplier_nama,
         SUM(sa.jumlah_terjual) AS units,
         SUM(sa.jumlah_terjual * p.harga_jual) AS revenue,
         SUM(sa.jumlah_terjual * p.margin_fnb) AS fnb_profit,
         SUM(sa.jumlah_terjual * p.harga_supplier) AS supplier_profit
  FROM stok_akhir sa
  JOIN stok st ON st.id = sa.stok_id
  JOIN produk p ON p.id = st.produk_id
  JOIN supplier s ON s.id = p.supplier_id
  WHERE sa.processed_date = ?
  GROUP BY s.id, s.nama
  ORDER BY s.nama ASC
");
$stmtDailySup->bind_param("s", $daily_date);
$stmtDailySup->execute();
$daily_suppliers = $stmtDailySup->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtDailySup->close();

// Monthly aggregates
$stmtMonthly = $conn->prepare("
  SELECT 
    SUM(sa.jumlah_terjual) AS units,
    SUM(sa.jumlah_terjual * p.harga_jual) AS revenue,
    SUM(sa.jumlah_terjual * p.margin_fnb) AS fnb_profit,
    SUM(sa.jumlah_terjual * p.harga_supplier) AS supplier_profit
  FROM stok_akhir sa
  JOIN stok st ON st.id = sa.stok_id
  JOIN produk p ON p.id = st.produk_id
  WHERE MONTH(sa.processed_date) = ? AND YEAR(sa.processed_date) = ?
");
$stmtMonthly->bind_param("ii", $month, $year);
$stmtMonthly->execute();
$monthly = $stmtMonthly->get_result()->fetch_assoc();
$stmtMonthly->close();

// Monthly per supplier
$stmtMonthlySup = $conn->prepare("
  SELECT s.id AS supplier_id, s.nama AS supplier_nama,
         SUM(sa.jumlah_terjual) AS units,
         SUM(sa.jumlah_terjual * p.harga_jual) AS revenue,
         SUM(sa.jumlah_terjual * p.margin_fnb) AS fnb_profit,
         SUM(sa.jumlah_terjual * p.harga_supplier) AS supplier_profit
  FROM stok_akhir sa
  JOIN stok st ON st.id = sa.stok_id
  JOIN produk p ON p.id = st.produk_id
  JOIN supplier s ON s.id = p.supplier_id
  WHERE MONTH(sa.processed_date) = ? AND YEAR(sa.processed_date) = ?
  GROUP BY s.id, s.nama
  ORDER BY s.nama ASC
");
$stmtMonthlySup->bind_param("ii", $month, $year);
$stmtMonthlySup->execute();
$monthly_suppliers = $stmtMonthlySup->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMonthlySup->close();

// Pending payments
$pending_payments = $conn->query("
  SELECT sp.id, sp.supplier_id, s.nama AS supplier_nama, sp.period_type, sp.period_date, sp.amount, sp.status
  FROM supplier_payments sp
  JOIN supplier s ON s.id = sp.supplier_id
  WHERE sp.status='pending'
  ORDER BY sp.period_date DESC, s.nama ASC
")->fetch_all(MYSQLI_ASSOC);

?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  </head>
  <body class="bg-light">
    <?php render_admin_navbar(); ?>

    <main class="container py-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Laporan - Admin</h1>
      </div>

      <!-- Generate Payment -->
      <div class="card shadow-sm mb-3">
        <div class="card-header">Generate Payment Supplier</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="generate_payment_daily">
                <div class="col-md-8">
                  <label class="form-label">Tanggal (Harian)</label>
                  <input type="date" name="date" value="<?= htmlspecialchars($daily_date) ?>" class="form-control" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-cash-coin me-1"></i> Generate Daily
                  </button>
                </div>
              </form>
            </div>
            <div class="col-md-6">
              <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="generate_payment_monthly">
                <div class="col-md-4">
                  <label class="form-label">Bulan</label>
                  <select name="month" class="form-select">
                    <?php for ($m=1;$m<=12;$m++): ?>
                      <option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= $m ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tahun</label>
                  <input type="number" name="year" value="<?= (int)$year ?>" class="form-control" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                  <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-calendar2-check me-1"></i> Generate Monthly
                  </button>
                </div>
              </form>
            </div>
          </div>
          <div class="form-text mt-2">
            Generate akan membuat/menimpa catatan pembayaran supplier (status pending) berdasarkan keuntungan supplier dari stok_akhir (jumlah_terjual × harga_supplier).<br>
            Gunakan tombol “Mark as Paid” di tabel pending bila pembayaran sudah dilakukan.
          </div>
        </div>
      </div>

      <!-- Laporan Harian -->
      <div class="card shadow-sm mb-3">
        <div class="card-header">Laporan Harian</div>
        <div class="card-body">
          <form class="row g-3" method="get">
            <div class="col-md-4">
              <label class="form-label">Tanggal</label>
              <input type="date" name="date" value="<?= htmlspecialchars($daily_date) ?>" class="form-control" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button class="btn btn-outline-secondary" type="submit">Terapkan</button>
            </div>
          </form>

          <div class="row g-3 mt-2">
            <div class="col-md-3">
              <div class="card"><div class="card-body">
                <div class="text-muted">Unit Terjual</div>
                <div class="fs-5"><?= (int)($daily['units'] ?? 0) ?></div>
              </div></div>
            </div>
            <div class="col-md-3">
              <div class="card"><div class="card-body">
                <div class="text-muted">Pendapatan</div>
                <div class="fs-5">Rp <?= number_format((float)($daily['revenue'] ?? 0), 2, ',', '.') ?></div>
              </div></div>
            </div>
            <div class="col-md-3">
              <div class="card"><div class="card-body">
                <div class="text-muted">Keuntungan F&B (Marjin)</div>
                <div class="fs-5">Rp <?= number_format((float)($daily['fnb_profit'] ?? 0), 2, ',', '.') ?></div>
              </div></div>
            </div>
            <div class="col-md-3">
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
                  <th>Supplier</th>
                  <th class="text-end">Unit</th>
                  <th class="text-end">Pendapatan</th>
                  <th class="text-end">Keuntungan F&B</th>
                  <th class="text-end">Keuntungan Supplier</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($daily_suppliers)): ?>
                  <tr><td colspan="5" class="text-center text-muted">Tidak ada data.</td></tr>
                <?php else: foreach ($daily_suppliers as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['supplier_nama']) ?></td>
                    <td class="text-end"><?= (int)$r['units'] ?></td>
                    <td class="text-end">Rp <?= number_format((float)$r['revenue'], 2, ',', '.') ?></td>
                    <td class="text-end">Rp <?= number_format((float)$r['fnb_profit'], 2, ',', '.') ?></td>
                    <td class="text-end">Rp <?= number_format((float)$r['supplier_profit'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Laporan Bulanan -->
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
              <input type="number" name="year" value="<?= (int)$year ?>" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button class="btn btn-outline-secondary" type="submit">Terapkan</button>
            </div>
          </form>

          <div class="row g-3 mt-2">
            <div class="col-md-3">
              <div class="card"><div class="card-body">
                <div class="text-muted">Unit Terjual</div>
                <div class="fs-5"><?= (int)($monthly['units'] ?? 0) ?></div>
              </div></div>
            </div>
            <div class="col-md-3">
              <div class="card"><div class="card-body">
                <div class="text-muted">Pendapatan</div>
                <div class="fs-5">Rp <?= number_format((float)($monthly['revenue'] ?? 0), 2, ',', '.') ?></div>
              </div></div>
            </div>
            <div class="col-md-3">
              <div class="card"><div class="card-body">
                <div class="text-muted">Keuntungan F&B (Marjin)</div>
                <div class="fs-5">Rp <?= number_format((float)($monthly['fnb_profit'] ?? 0), 2, ',', '.') ?></div>
              </div></div>
            </div>
            <div class="col-md-3">
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
                  <th>Supplier</th>
                  <th class="text-end">Unit</th>
                  <th class="text-end">Pendapatan</th>
                  <th class="text-end">Keuntungan F&B</th>
                  <th class="text-end">Keuntungan Supplier</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($monthly_suppliers)): ?>
                  <tr><td colspan="5" class="text-center text-muted">Tidak ada data.</td></tr>
                <?php else: foreach ($monthly_suppliers as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['supplier_nama']) ?></td>
                    <td class="text-end"><?= (int)$r['units'] ?></td>
                    <td class="text-end">Rp <?= number_format((float)$r['revenue'], 2, ',', '.') ?></td>
                    <td class="text-end">Rp <?= number_format((float)$r['fnb_profit'], 2, ',', '.') ?></td>
                    <td class="text-end">Rp <?= number_format((float)$r['supplier_profit'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Pembayaran Supplier (Pending) + Mark Paid -->
      <div class="card shadow-sm">
        <div class="card-header">Pembayaran Supplier (Pending)</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>Supplier</th>
                  <th>Periode</th>
                  <th>Tanggal</th>
                  <th class="text-end">Jumlah</th>
                  <th>Status</th>
                  <th class="text-end">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pending_payments)): ?>
                  <tr><td colspan="6" class="text-center text-muted">Tidak ada pembayaran pending.</td></tr>
                <?php else: foreach ($pending_payments as $pp): ?>
                  <tr>
                    <td><?= htmlspecialchars($pp['supplier_nama']) ?></td>
                    <td><?= htmlspecialchars($pp['period_type']) ?></td>
                    <td><?= htmlspecialchars($pp['period_date']) ?></td>
                    <td class="text-end">Rp <?= number_format((float)$pp['amount'], 2, ',', '.') ?></td>
                    <td><span class="badge text-bg-warning"><?= htmlspecialchars($pp['status']) ?></span></td>
                    <td class="text-end">
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="payment_id" value="<?= (int)$pp['id'] ?>">
                        <button class="btn btn-sm btn-success" type="submit">Mark as Paid</button>
                      </form>
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