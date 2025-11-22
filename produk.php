<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Produk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
<h2 class="mb-4">Data Produk</h2>

<form method="POST" class="row g-3 mb-4">
  <div class="col-md-3"><input type="text" name="nama" class="form-control" placeholder="Nama Produk" required></div>
  <div class="col-md-2">
    <select name="jenis" class="form-select">
      <option value="kudapan">Kudapan</option>
      <option value="minuman">Minuman</option>
    </select>
  </div>
  <div class="col-md-2"><input type="number" name="harga_supplier" class="form-control" placeholder="Harga Supplier" required></div>
  <div class="col-md-2"><input type="number" name="margin_fnb" class="form-control" placeholder="Margin F&B" required></div>
  <div class="col-md-2">
    <select name="supplier_id" class="form-select">
      <?php
      include 'config.php';
      $suppliers = mysqli_query($conn, "SELECT * FROM supplier");
      while($sup = mysqli_fetch_assoc($suppliers)) {
        echo "<option value='{$sup['id']}'>{$sup['nama']}</option>";
      }
      ?>
    </select>
  </div>
  <div class="col-md-1"><button type="submit" name="add" class="btn btn-primary w-100">Tambah</button></div>
</form>
<?php
if (isset($_POST['add'])) {
    $nama = $_POST['nama'];
    $jenis = $_POST['jenis'];
    $harga_supplier = $_POST['harga_supplier'];
    $margin_fnb = $_POST['margin_fnb'];
    $supplier_id = $_POST['supplier_id'];
    mysqli_query($conn, "INSERT INTO produk (nama, jenis, harga_supplier, margin_fnb, supplier_id) VALUES ('$nama', '$jenis', '$harga_supplier', '$margin_fnb', '$supplier_id')");
}
$result = mysqli_query($conn, "SELECT p.*, s.nama AS supplier_nama FROM produk p JOIN supplier s ON p.supplier_id=s.id");
?>
<table class="table table-striped table-bordered">
<thead class="table-dark"><tr><th>ID</th><th>Nama</th><th>Jenis</th><th>Harga Supplier</th><th>Margin F&B</th><th>Harga Jual</th><th>Supplier</th></tr></thead>
<tbody>
<?php while($row = mysqli_fetch_assoc($result)) { ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['nama'] ?></td>
<td><?= $row['jenis'] ?></td>
<td><?= $row['harga_supplier'] ?></td>
<td><?= $row['margin_fnb'] ?></td>
<td><?= $row['harga_jual'] ?></td>
<td><?= $row['supplier_nama'] ?></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
