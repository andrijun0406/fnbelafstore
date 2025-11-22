
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    dashboard.phpF&B Elaf Store</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">dashboard.phpDashboard</a></li>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item">manage_products.phpProduk</a></li>
            <li class="nav-item">manage_suppliers.phpSupplier</a></li>
        <?php elseif ($_SESSION['role'] === 'supplier'): ?>
            <li class="nav-item">supplier_dashboard.phpProduk Saya</a></li>
            <li class="nav-item">update_profile.phpProfil</a></li>
        <?php endif; ?>
        <li class="nav-item">logout.phpLogout</a></li>
      </ul>
    </div>
  </div>
</nav>
