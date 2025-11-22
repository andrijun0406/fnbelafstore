<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stok Akhir</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
<h2 class="mb-4">Input Stok Akhir (Sore Hari)</h2>

<form method="POST" class="row g-3 mb-4">
  <div class="col-md-6">
    <select name="stok_id" class="form-select">
      <?php
      include 'config.php';
      $stok = mysqli_query($conn, "SELECT st.id, p.nama FROM stok st JOIN produk p ON st.produk_id=p.id");
      while($s = mysqli_fetch_assoc($stok)) {
        echo "<option value='{$s['id']}'>{$s['nama']}</option>";
      }
      ?>
    </select>
  </div>
  <div class="col-md-6"><input type="number" name="jumlah_sisa" class="form-control" placeholder="Jumlah Sisa" required></div>
  <div class="col-md-12"><button type="submit" name="add" class="btn btn-primary w-100">Tambah Stok Akhir</button></div>
</form>
<?php
if (isset($_POST['add'])) {
    $stok_id = $_POST['stok_id'];
    $jumlah_sisa = $_POST['jumlah_sisa'];
    mysqli_query($conn, "INSERT INTO stok_akhir (stok_id, jumlah_sisa) VALUES ('$stok_id', '$jumlah_sisa')");
}
$result = mysqli_query($conn, "SELECT sa.*, st.jumlah_masuk, p.nama AS produk_nama FROM stok_akhir sa JOIN stok st ON sa.stok_id=st.id JOIN produk p ON st.produk_id=p.id");
?>
<table class="table table-striped table-bordered">
<thead class="table-dark"><tr><th>ID</th><th>Produk</th><th>Jumlah Masuk</th><th>Jumlah Sisa</th></tr></thead>
<tbody>
<?php while($row = mysqli_fetch_assoc($result)) { ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['produk_nama'] ?></td>
<td><?= $row['jumlah_masuk'] ?></td>
<td><?= $row['jumlah_sisa'] ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
