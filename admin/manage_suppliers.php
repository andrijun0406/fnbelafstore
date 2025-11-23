<?php
declare(strict_types=1);

// Debug sementara (MATIKAN di production)
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Mulai sesi lebih awal
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

include_once __DIR__ . '/../includes/auth.php';
if (function_exists('requireRole')) {
  requireRole('admin'); // admin bisa kelola supplier & buat user supplier
} else {
  checkRole('admin');
}

require_once __DIR__ . '/../includes/koneksi.php';

// Muat partial navbar admin konsisten
include_once __DIR__ . '/../includes/navbar_admin.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function post($k,$d=''){ return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = post('csrf_token');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $flash = ['type'=>'danger','msg'=>'Sesi tidak valid.'];
  } else {
    $action = post('action');
    if ($action === 'create_supplier') {
      $nama = post('nama');
      $hp   = post('handphone');
      $make_user = post('make_user'); // 'yes' or ''
      $username  = post('username');
      $password  = post('password');

      if ($nama === '' || $hp === '') {
        $flash = ['type'=>'danger','msg'=>'Nama dan handphone wajib diisi.'];
      } else {
        // Jika juga membuat user
        $new_user_id = null;
        if ($make_user === 'yes') {
          if ($username === '' || $password === '') {
            $flash = ['type'=>'danger','msg'=>'Username dan password user supplier wajib diisi.'];
          } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtU = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'supplier')");
            $stmtU->bind_param("ss", $username, $hash);
            if ($stmtU->execute()) {
              $new_user_id = $stmtU->insert_id;
            } else {
              $flash = ['type'=>'danger','msg'=>'Gagal membuat user: ' . htmlspecialchars($stmtU->error)];
            }
            $stmtU->close();
          }
        }

        // Buat supplier (dengan user_id jika ada)
        if ($flash === null) {
          if ($new_user_id) {
            $stmt = $conn->prepare("INSERT INTO supplier (nama, handphone, user_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $nama, $hp, $new_user_id);
          } else {
            $stmt = $conn->prepare("INSERT INTO supplier (nama, handphone) VALUES (?, ?)");
            $stmt->bind_param("ss", $nama, $hp);
          }
          if ($stmt->execute()) {
            $flash = ['type'=>'success','msg'=>'Supplier berhasil ditambahkan.'];
          } else {
            $flash = ['type'=>'danger','msg'=>'Gagal menambah supplier: ' . htmlspecialchars($stmt->error)];
          }
          $stmt->close();
        }
      }
    } elseif ($action === 'update_supplier') {
      $id = post('id');
      $nama = post('nama');
      $hp   = post('handphone');
      if (ctype_digit($id) && $nama !== '' && $hp !== '') {
        $stmt = $conn->prepare("UPDATE supplier SET nama = ?, handphone = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nama, $hp, $id);
        $stmt->execute();
        $stmt->close();
        $flash = ['type'=>'success','msg'=>'Supplier diperbarui.'];
      } else {
        $flash = ['type'=>'danger','msg'=>'Input tidak valid.'];
      }
    } elseif ($action === 'delete_supplier') {
      $id = post('id');
      if (ctype_digit($id)) {
        // Pastikan tidak terkait ke produk jika ingin membatasi
        $stmtC = $conn->prepare("SELECT COUNT(*) AS c FROM produk WHERE supplier_id = ?");
        $stmtC->bind_param("i", $id);
        $stmtC->execute();
        $resC = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();
        if (($resC['c'] ?? 0) > 0) {
          $flash = ['type'=>'danger','msg'=>'Tidak dapat menghapus, ada produk terkait.'];
        } else {
          // Unlink user dulu jika ada
          $stmtU = $conn->prepare("UPDATE supplier SET user_id = NULL WHERE id = ?");
          $stmtU->bind_param("i", $id);
          $stmtU->execute();
          $stmtU->close();

          $stmt = $conn->prepare("DELETE FROM supplier WHERE id = ?");
          $stmt->bind_param("i", $id);
          if ($stmt->execute()) {
            $flash = ['type'=>'success','msg'=>'Supplier dihapus.'];
          } else {
            $flash = ['type'=>'danger','msg'=>'Gagal menghapus supplier.'];
          }
          $stmt->close();
        }
      }
    } elseif ($action === 'link_user') {
      $id = post('id'); // supplier id
      $user_id = post('user_id');
      if (ctype_digit($id) && ctype_digit($user_id)) {
        // hanya link jika supplier belum punya user
        $stmt = $conn->prepare("UPDATE supplier SET user_id = ? WHERE id = ? AND user_id IS NULL");
        $stmt->bind_param("ii", $user_id, $id);
        $stmt->execute();
        $stmt->close();
        $flash = ['type'=>'success','msg'=>'User ditautkan ke supplier.'];
      }
    } elseif ($action === 'unlink_user') {
      $id = post('id'); // supplier id
      if (ctype_digit($id)) {
        $stmt = $conn->prepare("UPDATE supplier SET user_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $flash = ['type'=>'success','msg'=>'Tautan user dihapus dari supplier.'];
      }
    }
  }
}

// Ambil data supplier
$suppliers = $conn->query("
  SELECT s.id, s.nama, s.handphone, s.user_id, u.username
  FROM supplier s
  LEFT JOIN users u ON u.id = s.user_id
  ORDER BY s.nama ASC
")->fetch_all(MYSQLI_ASSOC);

// Ambil users role supplier untuk pilihan link (opsional)
$supplier_users = $conn->query("SELECT id, username FROM users WHERE role = 'supplier' ORDER BY username ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Suppliers</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<?php render_admin_navbar(); ?>

<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Manage Suppliers</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSupplierModal">
      <i class="bi bi-building-add"></i> Tambah Supplier
    </button>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>Handphone</th>
            <th>User Terkait</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($suppliers as $s): ?>
            <tr>
              <td><?= $s['id'] ?></td>
              <td><?= htmlspecialchars($s['nama']) ?></td>
              <td><?= htmlspecialchars($s['handphone']) ?></td>
              <td>
                <?php if ($s['user_id']): ?>
                  <span class="badge text-bg-success">
                    <?= htmlspecialchars($s['username'] ?? ('User#'.$s['user_id'])) ?>
                  </span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Belum terkait</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editSupplierModal<?= $s['id'] ?>">Edit</button>
                  <?php if ($s['user_id']): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="unlink_user">
                      <input type="hidden" name="id" value="<?= $s['id'] ?>">
                      <button class="btn btn-outline-warning" type="submit">Unlink User</button>
                    </form>
                  <?php else: ?>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#linkUserModal<?= $s['id'] ?>">Link User</button>
                  <?php endif; ?>
                  <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSupplierModal<?= $s['id'] ?>">Delete</button>
                </div>
              </td>
            </tr>

            <!-- Edit supplier modal -->
            <div class="modal fade" id="editSupplierModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog"><div class="modal-content">
                <form method="post">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_supplier">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <div class="form-floating mb-3">
                      <input type="text" class="form-control" id="nama<?= $s['id'] ?>" name="nama" value="<?= htmlspecialchars($s['nama']) ?>" required>
                      <label for="nama<?= $s['id'] ?>">Nama</label>
                    </div>
                    <div class="form-floating mb-3">
                      <input type="text" class="form-control" id="hp<?= $s['id'] ?>" name="handphone" value="<?= htmlspecialchars($s['handphone']) ?>" required>
                      <label for="hp<?= $s['id'] ?>">Handphone</label>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan</button>
                  </div>
                </form>
              </div></div>
            </div>

            <!-- Link user modal -->
            <div class="modal fade" id="linkUserModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog"><div class="modal-content">
                <form method="post">
                  <div class="modal-header">
                    <h5 class="modal-title">Link User ke Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="link_user">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <label class="form-label">Pilih User (role supplier)</label>
                    <select name="user_id" class="form-select" required>
                      <option value="">-- Pilih --</option>
                      <?php foreach ($supplier_users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text">Pastikan user belum tertaut ke supplier lain.</div>
                  </div>
                  <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary" type="submit">Link</button>
                  </div>
                </form>
              </div></div>
            </div>

            <!-- Delete supplier modal -->
            <div class="modal fade" id="deleteSupplierModal<?= $s['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog"><div class="modal-content">
                <form method="post">
                  <div class="modal-header">
                    <h5 class="modal-title">Hapus Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <p class="mb-0">Tindakan ini tidak dapat dibatalkan. Pastikan tidak ada produk terkait.</p>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete_supplier">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
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

  <!-- Modal tambah supplier -->
  <div class="modal fade" id="createSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Supplier</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="action" value="create_supplier">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="namaNew" name="nama" placeholder="Nama" required>
                <label for="namaNew">Nama</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="hpNew" name="handphone" placeholder="Handphone" required>
                <label for="hpNew">Handphone</label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="yes" id="makeUser" name="make_user">
                <label class="form-check-label" for="makeUser">
                  Sekaligus buat user supplier
                </label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="usernameNew" name="username" placeholder="Username">
                <label for="usernameNew">Username (user supplier)</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="password" class="form-control" id="passwordNew" name="password" placeholder="Password">
                <label for="passwordNew">Password (user supplier)</label>
              </div>
            </div>
            <div class="col-12">
              <div class="alert alert-info">
                Username harus unik, sesuai constraint di tabel users.
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-primary" type="submit">Simpan</button>
        </div>
      </form>
    </div></div>
  </div>
</main>

<footer class="border-top mt-4">
  <div class="container py-3">
    <small class="text-muted">&copy; <?= date('Y'); ?> F &amp; B ELAF Store</small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>