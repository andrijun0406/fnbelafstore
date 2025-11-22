<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Supplier</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
<h2 class="mb-4">Data Supplier</h2>

<form method="POST" class="row g-3 mb-4">
  <div class="col-md-4"><input type="text" name="nama" class="form-control" placeholder="Nama Supplier" required></div>
  <div class="col-md-4"><input type="text" name="handphone" class="form-control" placeholder="No Handphone" required></div>
  <div class="col-md-4"><button type="submit" name="add" class="btn btn-primary w-100">Tambah Supplier</button></div>
</form>
<?php
include 'config.php';
if (isset($_POST['add'])) {
    $nama = $_POST['nama'];
    $hp = $_POST['handphone'];
    mysqli_query($conn, "INSERT INTO supplier (nama, handphone) VALUES ('$nama', '$hp')");
}
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM supplier WHERE id=$id");
}
$result = mysqli_query($conn, "SELECT * FROM supplier");
?>
<table class="table table-striped table-bordered">
<thead class="table-dark"><tr><th>ID</th><th>Nama</th><th>Handphone</th><th>Aksi</th></tr></thead>
<tbody>
<?php while($row = mysqli_fetch_assoc($result)) { ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['nama'] ?></td>
<td><?= $row['handphone'] ?></td>
<td><a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm">Hapus</a></td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
