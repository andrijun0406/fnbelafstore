<?php
declare(strict_types=1);

include_once __DIR__ . '/../includes/auth.php';
checkRole('supplier'); // hanya supplier
require_once __DIR__ . '/../includes/koneksi.php';

session_start();
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Cari supplier yang tertaut ke user ini
$stmtSup = $conn->prepare("SELECT id, nama FROM supplier WHERE user_id = ? LIMIT 1");
$stmtSup->bind_param("i", $user_id);
$stmtSup->execute();
$resSup = $stmtSup->get_result();
$supplier = $resSup->fetch_assoc();
$stmtSup->close();

$flash = null;
function post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }

// Tangani aksi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid, silakan muat ulang.'];
  } elseif (!$supplier) {
    $flash = ['type'=>'danger','msg'=>'Akun supplier belum ditautkan. Hubungi admin.'];
  } else {
    $supplier_id = (int)$supplier['id'];
    $action = post('action');

    if ($action === 'create_produk') {
      $nama = post('nama');
      $jenis = post('jenis');
      $harga_supplier = post('harga_supplier');
      $margin_fnb = post('margin_fnb');

      if ($nama === '' || !in_array($jenis, ['kudapan','minuman'], true)) {
        $flash = ['type'=>'danger','msg'=>'Nama/jenis tidak valid.'];
      } elseif (!is_numeric($harga_supplier) || !is_numeric($margin_fnb)) {
        $flash = ['type'=>'danger','msg'=>'Harga dan margin harus numerik.'];
      } else {
        $hs = (float)$harga_supplier;
        $mf = (float)$margin_fnb;
        $stmt = $conn->prepare("INSERT INTO produk (nama, jenis, harga_supplier, margin_fnb, supplier_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddi", $nama, $jenis, $hs, $mf, $supplier_id);
        if ($stmt->execute()) {
          $flash = ['type'=>'success','msg'=>'Produk berhasil ditambahkan.'];
        } else {
          $flash = ['type'=>'danger','msg'=>'Gagal menambah produk: ' . htmlspecialchars($stmt->error)];
        }
        $stmt->close();
      }
    } elseif ($action === 'update_produk') {
      $id = post('id');
      $nama = post('nama');
      $jenis = post('jenis');
      $harga_supplier = post('harga_supplier');
      $margin_fnb = post('margin_fnb');

      if (!ctype_digit($id)) {
        $flash = ['type'=>'danger','msg'=>'ID produk tidak valid.'];
      } elseif ($nama === '' || !in_array($jenis, ['kudapan','minuman'], true)) {
        $flash = ['type'=>'danger','msg'=>'Nama/jenis tidak valid.'];
      } elseif (!is_numeric($harga_supplier) || !is_numeric($margin_fnb)) {
        $flash = ['type'=>'danger','msg'=>'Harga dan margin harus numerik.'];
      } else {
        $pid = (int)$id;
        $hs = (float)$harga_supplier;
        $mf = (float)$margin_fnb;
        // Pastikan hanya update produk milik supplier ini
        $stmt = $conn->prepare("UPDATE produk SET nama = ?, jenis = ?, harga_supplier = ?, margin_fnb = ? WHERE id = ? AND supplier_id = ?");
        $stmt->bind_param("ssddii", $nama, $jenis, $hs, $mf, $pid, $supplier_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
          $flash = ['type'=>'success','msg'=>'Produk diperbarui.'];
        } else {
          $flash = ['type'=>'warning','msg'=>'Tidak ada perubahan atau produk bukan milik Anda.'];
        }
        $stmt->close();
      }
    } elseif ($action === 'delete_produk') {
      $id = post('id');
      if (!ctype_digit($id)) {
        $flash = ['type'=>'danger','msg'=>'ID produk tidak valid.'];
      } else {
        $pid = (int)$id;
        // Cek stok terkait; jika ada, cegah delete
        $stmtC = $conn->prepare("SELECT COUNT(*) AS c FROM stok WHERE produk_id = ?");
        $stmtC->bind_param("i", $pid);
        $stmtC->execute();
        $resC = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();

        if (($resC['c'] ?? 0) > 0) {
          $flash = ['type'=>'danger','msg'=>'Tidak dapat menghapus: ada stok terkait produk ini.'];
        } else {
          $stmt = $conn->prepare("DELETE FROM produk WHERE id = ? AND supplier_id = ?");
          $stmt->bind_param("ii", $pid, $supplier_id);
          if ($stmt->execute() && $stmt->affected_rows > 0) {
            $flash = ['type'=>'success','msg'=>'Produk dihapus.'];
          } else {
            $flash = ['type'=>'danger','msg'=>'Gagal menghapus produk.'];
          }
          $stmt->close();
        }
      }
    } elseif ($action === 'reset_password') {
      $current = post('current_password');
      $newpass = post('new_password');
      $confirm = post('confirm_password');

      if ($newpass === '' || $confirm === '' || $newpass !== $confirm) {
        $flash = ['type'=>'danger','msg'=>'Password baru dan konfirmasi harus diisi dan sama.'];
      } else {
        // Ambil hash password user sekarang
        $stmtU = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmtU->bind_param("i", $user_id);
        $stmtU->execute();
        $resU = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();

        if (!$resU || !password_verify($current, $resU['password'])) {
          $flash = ['type'=>'danger','msg'=>'Password saat ini tidak sesuai.'];
        } else {
          $hash = password_hash($newpass, PASSWORD_BCRYPT);
          $stmtUp = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
          $stmtUp->bind_param("si", $hash, $user_id);
          $stmtUp->execute();
          $stmtUp->close();
          session_regenerate_id(true);
          $flash = ['type'=>'success','msg'=>'Password berhasil diperbarui.'];
        }
      }
    }
  }
}

// Jika supplier belum tertaut, tampilkan pesan dan hentikan
if ($supplier) {
  // Ambil produk milik supplier ini
  $stmtP = $conn->prepare("
    SELECT id, nama, jenis, harga_supplier, margin_fnb, harga_jual
    FROM produk
    WHERE supplier_id = ?
    ORDER BY id DESC
  ");
  $stmtP->bind_param("i", (int)$supplier['id']);
  $stmtP->execute();
  $products = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmtP->close();
} else {
  $products = [];
}
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  </head>
  <body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container-fluid">
        <a class="navbar-brand" href="#">FnBelaf Store Supplier</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNav">
          <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
          </ul>
          <a class="btn btn-outline-light btn-sm" href="../logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
        </div>
      </div>
    </nav>

    <main class="container py-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>

      <?php if (!$supplier): ?>
        <div class="alert alert-warning">
          Akun Anda belum ditautkan ke data supplier. Mohon hubungi admin untuk penautan agar dapat mengelola produk.
        </div>
      <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h1 class="h4 mb-0">Halo, <?= htmlspecialchars($supplier['nama']) ?></h1>
            <small class="text-muted">Kelola produk Anda di sini.</small>
          </div>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProdukModal">
            <i class="bi bi-plus-circle"></i> Tambah Produk
          </button>
        </div>

        <!-- Reset Password Card -->
        <div class="card shadow-sm mb-4">
          <div class="card-header">Keamanan Akun</div>
          <div class="card-body">
            <form method="post" class="row g-3">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="reset_password">
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Password saat ini" required>
                  <label for="current_password">Password saat ini</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Password baru" required>
                  <label for="new_password">Password baru</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Konfirmasi password baru" required>
                  <label for="confirm_password">Konfirmasi password baru</label>
                </div>
              </div>
              <div class="col-12">
                <button class="btn btn-outline-primary" type="submit">Perbarui Password</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Daftar Produk -->
        <div class="card shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Jenis</th>
                    <th class="text-end">Harga Supplier</th>
                    <th class="text-end">Margin FnB</th>
                    <th class="text-end">Harga Jual</th>
                    <th class="text-end">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($products as $p): ?>
                    <tr>
                      <td><?= $p['id'] ?></td>
                      <td><?= htmlspecialchars($p['nama']) ?></td>
                      <td><span class="badge text-bg-secondary"><?= htmlspecialchars($p['jenis']) ?></span></td>
                      <td class="text-end">Rp <?= number_format((float)$p['harga_supplier'], 2, ',', '.') ?></td>
                      <td class="text-end">Rp <?= number_format((float)$p['margin_fnb'], 2, ',', '.') ?></td>
                      <td class="text-end">Rp <?= number_format((float)$p['harga_jual'], 2, ',', '.') ?></td>
                      <td class="text-end">
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProdukModal<?= $p['id'] ?>">Edit</button>
                          <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProdukModal<?= $p['id'] ?>">Delete</button>
                        </div>
                      </td>
                    </tr>

                    <!-- Edit Produk Modal -->
                    <div class="modal fade" id="editProdukModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                          <div class="modal-header">
                            <h5 class="modal-title">Edit Produk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="update_produk">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">

                            <div class="form-floating mb-3">
                              <input type="text" class="form-control" id="nama<?= $p['id'] ?>" name="nama" value="<?= htmlspecialchars($p['nama']) ?>" required>
                              <label for="nama<?= $p['id'] ?>">Nama</label>
                            </div>

                            <div class="mb-3">
                              <label class="form-label">Jenis</label>
                              <select class="form-select" name="jenis" required>
                                <option value="kudapan" <?= $p['jenis']==='kudapan'?'selected':'' ?>>Kudapan</option>
                                <option value="minuman" <?= $p['jenis']==='minuman'?'selected':'' ?>>Minuman</option>
                              </select>
                            </div>

                            <div class="row g-3">
                              <div class="col-md-6">
                                <div class="form-floating">
                                  <input type="number" step="0.01" class="form-control" id="hs<?= $p['id'] ?>" name="harga_supplier" value="<?= htmlspecialchars((string)$p['harga_supplier']) ?>" required>
                                  <label for="hs<?= $p['id'] ?>">Harga Supplier</label>
                                </div>
                              </div>
                              <div class="col-md-6">
                                <div class="form-floating">
                                  <input type="number" step="0.01" class="form-control" id="mf<?= $p['id'] ?>" name="margin_fnb" value="<?= htmlspecialchars((string)$p['margin_fnb']) ?>" required>
                                  <label for="mf<?= $p['id'] ?>">Margin FnB</label>
                                </div>
                              </div>
                            </div>
                            <div class="form-text mt-2">
                              Harga jual dihitung otomatis dari harga supplier + margin FnB (kolom generated). :llmCitationRef[3]
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button class="btn btn-primary" type="submit">Simpan</button>
                          </div>
                        </form>
                      </div></div>
                    </div>

                    <!-- Delete Produk Modal -->
                    <div class="modal fade" id="deleteProdukModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                          <div class="modal-header">
                            <h5 class="modal-title">Hapus Produk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <p class="mb-2">Tindakan ini tidak dapat dibatalkan. Jika ada stok terkait, penghapusan akan ditolak.</p>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="action" value="delete_produk">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                          </div>
                          <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button class="btn btn-danger" type="submit">Hapus</button>
                          </div>
                        </form>
                      </div></div>
                    </div>

                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Create Produk Modal -->
        <div class="modal fade" id="createProdukModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg"><div class="modal-content">
            <form method="post">
              <div class="modal-header">
                <h5 class="modal-title">Tambah Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="create_produk">

                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="text" class="form-control" id="namaBaru" name="nama" placeholder="Nama produk" required>
                      <label for="namaBaru">Nama produk</label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Jenis</label>
                    <select class="form-select" name="jenis" required>
                      <option value="kudapan">Kudapan</option>
                      <option value="minuman">Minuman</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="number" step="0.01" class="form-control" id="hsBaru" name="harga_supplier" placeholder="Harga supplier" required>
                      <label for="hsBaru">Harga supplier</label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-floating">
                      <input type="number" step="0.01" class="form-control" id="mfBaru" name="margin_fnb" placeholder="Margin FnB" required>
                      <label for="mfBaru">Margin FnB</label>
                    </div>
                  </div>
                </div>
                <div class="form-text mt-2">
                  Harga jual akan otomatis tersimpan sebagai penjumlahan harga supplier + margin FnB (kolom generated). :llmCitationRef[4]
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" type="submit">Simpan</button>
              </div>
            </form>
          </div></div>
        </div>
      <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>