<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/includes/koneksi.php';

// Deteksi role untuk menentukan navbar
$role = $_SESSION['role'] ?? null;
if ($role === 'admin') {
  include_once __DIR__ . '/includes/navbar_admin.php';
} elseif ($role === 'supplier') {
  include_once __DIR__ . '/includes/navbar_supplier.php';
}

$today = date('Y-m-d');

// Stok hari ini (exclude yang sudah dicatat EOD dengan status expired)
$stmtToday = $conn->prepare("
  SELECT st.id AS stok_id,
         p.id AS produk_id,
         p.nama AS produk_nama,
         p.jenis,
         p.harga_jual,
         s.nama AS supplier_nama,
         st.jumlah_masuk,
         st.expired_at,
         COALESCE(sa.jumlah_sisa, st.jumlah_masuk) AS sisa_hari_ini
  FROM stok st
  JOIN produk p ON p.id = st.produk_id
  JOIN supplier s ON s.id = p.supplier_id
  LEFT JOIN stok_akhir sa 
         ON sa.stok_id = st.id 
        AND sa.processed_date = ?
  WHERE st.tanggal_masuk = ?
    AND (sa.status IS NULL OR sa.status <> 'expired')
  ORDER BY p.nama ASC
");
$stmtToday->bind_param("ss", $today, $today);
$stmtToday->execute();
$stok_today = $stmtToday->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtToday->close();

// Stok carry-over (belum expired, ada sisa, dan bukan status expired pada catatan terakhir)
$stmtCarry = $conn->prepare("
  SELECT st.id AS stok_id,
         p.id AS produk_id,
         p.nama AS produk_nama,
         p.jenis,
         p.harga_jual,
         s.nama AS supplier_nama,
         st.jumlah_masuk,
         st.expired_at,
         COALESCE(sa.jumlah_sisa, st.jumlah_masuk) AS sisa_terkini,
         sa.processed_date AS terakhir_diproses,
         sa.status AS status_terakhir
  FROM stok st
  JOIN produk p ON p.id = st.produk_id
  JOIN supplier s ON s.id = p.supplier_id
  LEFT JOIN stok_akhir sa 
         ON sa.stok_id = st.id 
        AND sa.processed_date = (
             SELECT MAX(sa2.processed_date) 
             FROM stok_akhir sa2 
             WHERE sa2.stok_id = st.id
           )
  WHERE CURDATE() < st.expired_at
    AND COALESCE(sa.jumlah_sisa, st.jumlah_masuk) > 0
    AND (sa.status IS NULL OR sa.status <> 'expired')
  ORDER BY st.expired_at ASC, p.nama ASC
");
$stmtCarry->execute();
$stok_carry = $stmtCarry->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCarry->close();

// Utility kecil
function jenis_badge(string $jenis): string {
  return $jenis === 'kudapan' ? 'secondary' : 'info';
}
// Berlaku s.d. = expired_at - 1 hari (tanggal terakhir boleh jual)
function berlaku_sd(string $expiredAt): string {
  return date('Y-m-d', strtotime($expiredAt.' -1 day'));
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>F &amp; B ELAF Store</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
      body { background-color: #f8f9fa; }
      .brand { font-weight: 700; letter-spacing: .5px; }
      .hero { background: #ffffff; border-radius: .75rem; }
      .card-title { font-weight: 600; }
    </style>
  </head>
  <body>
    <?php if ($role === 'admin'): ?>
      <?php render_admin_navbar(); ?>
    <?php elseif ($role === 'supplier'): ?>
      <?php render_supplier_navbar(); ?>
    <?php else: ?>
      <!-- Header publik dengan tombol Login -->
      <header class="py-3 border-bottom bg-white">
        <div class="container">
          <div class="d-flex align-items-center justify-content-between">
            <div class="brand h5 mb-0">F &amp; B ELAF Store</div>
            <div class="d-flex align-items-center gap-3">
              <span class="text-muted small"><?= htmlspecialchars($today) ?></span>
              <a href="/login.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
              </a>
            </div>
          </div>
        </div>
      </header>
    <?php endif; ?>

    <main class="container py-4">
      <!-- Penjelasan -->
      <section class="hero p-4 shadow-sm mb-4">
        <h1 class="h5 mb-3">Tentang F &amp; B ELAF Store</h1>
        <p>
          F &amp; B ELAF Store adalah Toko atau Warung berupa Wakaf Produktif di lingkungan Kuttab Al Fatih Bekasi yang menyediakan kudapan dan minuman sehari-hari untuk para ustadz dan ustadzah, santri, orang tua santri, serta masyarakat umum. Supplier berasal dari para orang tua santri dan hasil penjualan selain keuntungan para supplier, terdapat marjin yang digunakan untuk Bilistiwa — kebutuhan kegiatan dan wakaf Kuttab Al Fatih Bekasi.
        </p>
      </section>

      <!-- Etalase Hari Ini -->
      <section class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Etalase Hari Ini</h2>
          <span class="text-muted small">Produk tersedia per <?= htmlspecialchars($today) ?></span>
        </div>
        <div class="row g-3">
          <?php
            $count_available_today = 0;
            foreach ($stok_today as $item):
              $sisa = (int)$item['sisa_hari_ini'];
              if ($sisa <= 0) { continue; }
              $count_available_today++;
          ?>
            <div class="col-12 col-sm-6 col-lg-4">
              <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="card-title h6 mb-0"><?= htmlspecialchars($item['produk_nama']) ?></h3>
                    <span class="badge text-bg-<?= jenis_badge($item['jenis']) ?>"><?= htmlspecialchars($item['jenis']) ?></span>
                  </div>
                  <div class="text-muted mb-2">Supplier: <?= htmlspecialchars($item['supplier_nama']) ?></div>
                  <div class="mt-auto">
                    <div class="d-flex justify-content-between">
                      <span class="fw-semibold">Harga</span>
                      <span>Rp <?= number_format((float)$item['harga_jual'], 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                      <span class="fw-semibold">Tersedia</span>
                      <span><?= $sisa ?></span>
                    </div>
                    <small class="text-muted">Berlaku s.d.: <?= htmlspecialchars(berlaku_sd($item['expired_at'])) ?></small>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if ($count_available_today === 0): ?>
            <div class="col-12">
              <div class="alert alert-secondary">Belum ada stok yang tersedia untuk hari ini. Silakan cek kembali nanti.</div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Etalase Carry-over -->
      <section class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Tersedia dari Hari Sebelumnya</h2>
          <span class="text-muted small">Dibawa hingga masa expired</span>
        </div>
        <div class="row g-3">
          <?php
            $count_carry = 0;
            foreach ($stok_carry as $item):
              $sisa = (int)$item['sisa_terkini'];
              if ($sisa <= 0) { continue; }
              $count_carry++;
          ?>
            <div class="col-12 col-sm-6 col-lg-4">
              <div class="card shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <h3 class="card-title h6 mb-0"><?= htmlspecialchars($item['produk_nama']) ?></h3>
                    <span class="badge text-bg-<?= jenis_badge($item['jenis']) ?>"><?= htmlspecialchars($item['jenis']) ?></span>
                  </div>
                  <div class="text-muted mb-2">Supplier: <?= htmlspecialchars($item['supplier_nama']) ?></div>
                  <div class="mt-auto">
                    <div class="d-flex justify-content-between">
                      <span class="fw-semibold">Harga</span>
                      <span>Rp <?= number_format((float)$item['harga_jual'], 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                      <span class="fw-semibold">Tersedia</span>
                      <span><?= $sisa ?></span>
                    </div>
                    <small class="text-muted">
                      Berlaku s.d.: <?= htmlspecialchars(berlaku_sd($item['expired_at'])) ?><?= $item['terakhir_diproses'] ? ' • update: ' . htmlspecialchars($item['terakhir_diproses']) : '' ?>
                    </small>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if ($count_carry === 0): ?>
            <div class="col-12">
              <div class="alert alert-secondary">Tidak ada stok yang dibawa dari hari sebelumnya.</div>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <footer class="border-top bg-white">
      <div class="container py-3">
        <small class="text-muted">&copy; <?= date('Y'); ?> F &amp; B ELAF Store</small>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>