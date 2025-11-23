<?php
declare(strict_types=1);

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
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bantuan - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  </head>
  <body class="bg-light">
    <?php render_admin_navbar(); ?>

    <main class="container py-4">
      <div class="mb-3">
        <h1 class="h4">Bantuan Admin</h1>
        <p class="text-muted mb-0">Panduan ringkas mengelola users, suppliers, produk, stok, laporan, dan pembayaran.</p>
      </div>

      <!-- Mulai -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Mulai</div>
        <div class="card-body">
          <ul>
            <li>Masuk melalui menu Login di halaman utama atau langsung ke /login.php.</li>
            <li>Setelah login sebagai Admin, gunakan navigasi di atas untuk memilih modul.</li>
          </ul>
        </div>
      </section>

      <!-- Users -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Kelola Users</div>
        <div class="card-body">
          <ul>
            <li>Tambah user: buka Manage Users, klik Tambah User, isi Username, Password, Role.</li>
            <li>Jika role Supplier, opsional memilih supplier yang sudah ada atau sekaligus membuat supplier baru.</li>
            <li>Ubah role dan reset password user melalui tombol di tabel.</li>
            <li>Hapus user: jika tertaut ke supplier, tautan akan dihapus dahulu sebelum penghapusan.</li>
          </ul>
        </div>
      </section>

      <!-- Suppliers -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Kelola Suppliers</div>
        <div class="card-body">
          <ul>
            <li>Tambah supplier: isi Nama dan Handphone. Opsional buat user supplier sekaligus.</li>
            <li>Edit, Unlink/Link user supplier, dan Delete (pastikan tidak ada produk terkait untuk penghapusan).</li>
          </ul>
        </div>
      </section>

      <!-- Produk -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Kelola Produk</div>
        <div class="card-body">
          <ul>
            <li>Admin dapat membuat, mengubah, dan menghapus produk untuk supplier mana pun.</li>
            <li>Harga jual dibaca dari sistem sebagai penjumlahan harga supplier + margin FnB (kolom generated, tidak diinput manual). :llmCitationRef[0]</li>
            <li>Produk yang memiliki stok aktif tidak bisa dihapus sampai stoknya dibersihkan sesuai aturan operasional.</li>
          </ul>
        </div>
      </section>

      <!-- Stok -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Kelola Stok</div>
        <div class="card-body">
          <ul>
            <li>Stok Masuk: pilih produk, tanggal, jumlah; masa expired default (kudapan=1 hari, minuman=3 hari), bisa override bila perlu.</li>
            <li>Stok Akhir (EOD): isi jumlah sisa; sistem otomatis menandai sold_out, carried_over, atau expired sesuai kebijakan (kudapan/shelf_life=1 langsung expired bila ada sisa).</li>
            <li>Clear Stok (pra go-live): tombol Clear Stok menghapus stok_akhir (child) lalu stok (parent) secara aman, dengan opsi hapus sebagian sebelum tanggal dan reset nomor urut.</li>
            <li>Urutan penghapusan harus child → parent karena ada relasi stok_akhir.stok_id → stok.id. :llmCitationRef[1]</li>
          </ul>
        </div>
      </section>

      <!-- Etalase -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Etalase (Publik)</div>
        <div class="card-body">
          <ul>
            <li>Etalase Hari Ini: menampilkan stok hari ini yang belum ditandai expired.</li>
            <li>Etalase Carry-over: menampilkan stok aktif lintas hari (CURDATE() &lt; expired_at) dan bukan status expired.</li>
            <li>Label “Berlaku s.d.” menampilkan expired_at − 1 hari sebagai tanggal terakhir boleh jual.</li>
          </ul>
        </div>
      </section>

      <!-- Laporan -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Laporan Penjualan dan Keuntungan</div>
        <div class="card-body">
          <ul>
            <li>Harian: pilih tanggal untuk melihat Unit Terjual, Pendapatan, Keuntungan F&B (marjin), Keuntungan Supplier, serta rinci per supplier.</li>
            <li>Bulanan: pilih bulan/tahun untuk agregasi yang sama secara bulanan.</li>
          </ul>
        </div>
      </section>

      <!-- Pembayaran Mingguan -->
      <section class="card shadow-sm mb-3">
        <div class="card-header">Pembayaran Supplier (Mingguan)</div>
        <div class="card-body">
          <ul>
            <li>Generate Weekly: pilih Tanggal Referensi (Senin s.d. Minggu dihitung otomatis), sistem membuat catatan pembayaran “pending” per supplier berdasarkan Σ(jumlah_terjual × harga_supplier) minggu tersebut.</li>
            <li>Mark as Paid: ubah status pending menjadi paid saat pembayaran dilakukan, tanggal paid_at otomatis terisi.</li>
            <li>Clear Pembayaran: tombol Clear Pembayaran menghapus catatan pembayaran (opsional sebelum tanggal tertentu) untuk kebutuhan go-live, dengan opsi reset nomor urut.</li>
          </ul>
        </div>
      </section>

      <!-- Keamanan -->
      <section class="card shadow-sm mb-4">
        <div class="card-header">Keamanan & Konsistensi</div>
        <div class="card-body">
          <ul>
            <li>Setiap form menggunakan CSRF token dan prepared statements.</li>
            <li>Gunakan path absolut di navbar (/admin/..., /supplier/..., /index.php, /login.php, /logout.php) agar konsisten dari subfolder mana pun.</li>
          </ul>
        </div>
      </section>
    </main>

    <footer class="border-top mt-4">
      <div class="container py-3">
        <small class="text-muted">&copy; <?= date('Y'); ?> F &amp; B ELAF Store</small>
      </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>