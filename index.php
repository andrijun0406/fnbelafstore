<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>F&B ELAF STORE Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { display: flex; min-height: 100vh; }
  .sidebar { width: 250px; background-color: #343a40; color: white; transition: width 0.3s; }
  .sidebar.collapsed { width: 70px; }
  .sidebar a { color: white; text-decoration: none; display: block; padding: 10px; }
  .sidebar a:hover { background-color: #495057; }
  .content { flex: 1; padding: 20px; }
  .logo { max-width: 150px; margin: 20px auto; display: block; }
  .toggle-btn { background-color: #212529; color: white; border: none; width: 100%; padding: 10px; cursor: pointer; }
</style>
</head>
<body>
<div class="sidebar" id="sidebar">
  <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
  <img src="Designer.png" alt="F&B ELAF STORE Logo" class="logo">
  <h4 class="text-center">Menu</h4>
  <a href="index.php">🏠 Dashboard</a>
  <a href="supplier.php">📦 Kelola Supplier</a>
  <a href="produk.php">🍽 Kelola Produk</a>
  <a href="stok.php">📥 Input Stok Masuk</a>
  <a href="stok_akhir.php">📤 Input Stok Akhir</a>
  <a href="laporan.php">📊 Lihat Laporan</a>
</div>
<div class="content">
  <div class="text-center">
    <h1 class="display-5">Selamat Datang, F&B ELAF STORE Admin</h1>
    <p class="lead">Silahkan pilih menu di sidebar untuk mengelola data.</p>
  </div>
  <footer class="bg-dark text-white text-center py-3 mt-4">&copy; 2025 F&B ELAF STORE. All rights reserved.</footer>
</div>
<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
