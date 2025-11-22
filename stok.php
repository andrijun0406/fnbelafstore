<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stok Masuk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
<h2 class="mb-4">Input Stok Masuk (Pagi Hari)</h2>

<form method="POST" class="row g-3 mb-4">
  <div class="col-md-4">
    <select name="produk_id" class="form-select">
      <?php
      include 'config.php';
      $produk = mysqli_query($conn, "SELECT * FROM produk");
      while($p = mysqli_fetch_assoc($produk)) {
        echo "<option value='{$p['id']}'>{$p['nama']}</option>";
      }
      ?>
    </select>
  </div>
  <div class="col-md-4"><input type="date" name="tanggal_masuk" class="form-control" required></div>
  <div class="col-md-4"><input type="number" name="jumlah_masuk" class="form-control" placeholder="Jumlah Masuk" required></div>
  <div class="col-md-12"><button type="submit" name="add" class="btn btn-primary w-100">Tambah Stok</button></div>
</form>
<?php
if (isset($_POST['add'])) {
    $produk_id = $_POST['produk_id'];
    $tanggal_masuk = $_POST['tanggal_masuk'];
    $jumlah_masuk = $_POST['jumlah_masuk'];
    mysqli_query($conn, "INSERT INTO stok (produk_id, tanggal_masuk, jumlah_masuk) VALUES ('$produk_id', '$tanggal_masuk', '$jumlah_masuk')");
}
$result = mysqli_query($conn, "SELECT st.*, p.nama AS produk_nama FROM stok st JOIN produk p ON st.produk_id=p.id");
?>
<table class="table table-striped table-bordered">
<thead class="table-dark"><tr><th>ID</th><th>Produk</th><th>Tanggal Masuk</th><th>Jumlah Masuk</th></tr></thead>
<tbody>
<?php while($row = mysqli_fetch_assoc($result)) { ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['produk_nama'] ?></td>
<td><?= $row['tanggal_masuk'] ?></td>
<td><?= $row['jumlah_masuk'] ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
