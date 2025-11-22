<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><img src="logo.png" alt="Logo" style="height:40px;"> F&B ELAF STORE</a>
    <div class="d-flex">
      <a href="index.php" class="btn btn-outline-light">Home</a>
    </div>
  </div>
</nav>
<div class="container">
<h2 class="mb-4">Laporan Penjualan & Pembayaran Supplier</h2>
<form method="GET" class="row g-3 mb-4">
  <div class="col-md-3"><input type="date" name="tanggal" class="form-control"></div>
  <div class="col-md-3"><input type="text" name="produk" class="form-control" placeholder="Nama Produk"></div>
  <div class="col-md-3"><input type="text" name="supplier" class="form-control" placeholder="Nama Supplier"></div>
  <div class="col-md-3"><button type="submit" class="btn btn-secondary w-100">Filter</button></div>
</form>
<form method="POST" action="export_excel.php">
  <button type="submit" class="btn btn-success mb-3">Export ke Excel</button>
</form>
<form method="POST" action="export_pdf.php">
  <button type="submit" class="btn btn-danger mb-3">Export ke PDF</button>
</form>
<div class="mb-4">
  <canvas id="monthlyChart" height="100"></canvas>
</div>
<script>
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        datasets: [{
            label: 'Total Penjualan (Rp)',
            data: [1200000, 1500000, 1800000, 2000000, 2200000, 2500000, 2300000, 2400000, 2600000, 2700000, 3000000, 3200000],
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: 'Rekap Penjualan Bulanan' }
        }
    }
});
</script>
<footer class="bg-dark text-white text-center py-3 mt-4">&copy; 2025 F&B ELAF STORE. All rights reserved.</footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>