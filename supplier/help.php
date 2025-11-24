<?php
declare(strict_types=1);

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

?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bantuan - Supplier</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/brand.css">
    <link rel="icon" type="image/png" href="/assets/images/logo/logo_image_only.png">
  </head>
  <body class="bg-light">
    <?php render_supplier_navbar(); ?>

    <main class="container py-4">
      <div class="mb-3">
        <h1 class="h4">Bantuan Supplier</h1>
        <p class="text-muted mb-0">Panduan ringkas untuk reset password, kelola produk, melihat etalase, laporan, dan status pembayaran mingguan.</p>
      </div>

      <!-- Mulai -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Mulai</div>
        <div class="card-body">
          <ul>
            <li>Masuk melalui /login.php dengan akun supplier Anda.</li>
            <li>Setelah login, gunakan menu Dashboard dan Manage Produk di navigasi atas.</li>
          </ul>
        </div>
      </section>

      <!-- Reset Password -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Reset Password</div>
        <div class="card-body">
          <ul>
            <li>Buka Dashboard, isi Password saat ini, Password baru, dan Konfirmasi, lalu Perbarui Password.</li>
            <li>Untuk keamanan, sesi akan diperbarui (session_regenerate_id) setelah berhasil.</li>
          </ul>
        </div>
      </section>

      <!-- Produk -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Kelola Produk</div>
        <div class="card-body">
          <ul>
            <li>Tambah produk: klik Tambah Produk, isi Nama, Jenis, Harga supplier, Margin FnB.</li>
            <li>Edit/Hapus produk: gunakan tombol Edit/Delete di tabel. Produk yang memiliki stok aktif tidak bisa dihapus sampai stoknya dibersihkan sesuai aturan operasional.</li>
            <li>Harga jual ditampilkan otomatis oleh sistem sebagai harga supplier + margin FnB, tidak perlu dihitung manual. :llmCitationRef[2]</li>
          </ul>
        </div>
      </section>

      <!-- Etalase & Masa Berlaku -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Etalase Publik & Masa Berlaku</div>
        <div class="card-body">
          <ul>
            <li>Etalase Hari Ini: menampilkan stok hari ini yang belum ditandai expired.</li>
            <li>Etalase Carry-over: menampilkan stok aktif lintas hari (CURDATE() &lt; expired_at) dan bukan status expired.</li>
            <li>Jika stok akhir hari ini mencatat sisa untuk kudapan masa 1 hari, status akan expired dan item tidak muncul di etalase lagi.</li>
            <li>Label “Berlaku s.d.” menampilkan expired_at − 1 hari (tanggal terakhir boleh jual).</li>
          </ul>
        </div>
      </section>

      <!-- Laporan -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Laporan Penjualan & Keuntungan</div>
        <div class="card-body">
          <ul>
            <li>Harian: pilih tanggal untuk melihat Unit Terjual, Pendapatan, dan Keuntungan Supplier per produk dan total.</li>
            <li>Bulanan: pilih bulan/tahun untuk agregasi yang sama secara bulanan.</li>
          </ul>
        </div>
      </section>

      <!-- Pembayaran Mingguan -->
      <section class="card shadow-sm mb-4">
        <div class="card-header">Pembayaran Mingguan</div>
        <div class="card-body">
          <ul>
            <li>Lihat panel Pembayaran Mingguan di Laporan Supplier untuk status “pending” dan histori “paid”.</li>
            <li>Admin akan generate pembayaran mingguan dan menandai sebagai paid setelah transaksi dilakukan; panel ini memudahkan Anda memantau status terbaru.</li>
          </ul>
        </div>
      </section>
    </main>

    <?php include_once __DIR__ . '/../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>