<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'kasir') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Kasir - Kelola Akun</title>
  <link rel="stylesheet" href="../css/kasir.css">
  <link rel="stylesheet" href="../css/logout.css">
  <style>
    /* Styling khusus untuk halaman kelola akun agar lebih premium */
    .account-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    .account-card {
      background: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      position: relative;
    }
    .account-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .avatar-circle {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, #6e8efb, #a777e3);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 30px;
      margin-bottom: 15px;
    }
    .role-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      margin-bottom: 10px;
    }
    .role-kasir { background: #e3f2fd; color: #1976d2; }
    .role-dapur { background: #fff3e0; color: #f57c00; }
    
    .account-name {
      font-size: 18px;
      font-weight: 700;
      color: #333;
      margin-bottom: 5px;
    }
    .account-actions {
      display: flex;
      gap: 10px;
      margin-top: 20px;
      width: 100%;
    }
    .btn-acc {
      flex: 1;
      padding: 10px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: filter 0.2s;
    }
    .btn-edit-acc { background: #6e8efb; color: white; }
    .btn-delete-acc { background: #ff5252; color: white; }
    .btn-acc:hover { filter: brightness(0.9); }

    /* Modal Styling */
    .popup-overlay {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      backdrop-filter: blur(5px);
    }
    .popup-overlay.active { display: flex; }
    .popup-box {
      background: white;
      width: 90%;
      max-width: 400px;
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .popup-box h3 { margin-bottom: 20px; text-align: center; color: #333; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
    .form-group input, .form-group select {
      width: 100%;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 10px;
      font-size: 14px;
    }
    .btn-submit {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #6e8efb, #a777e3);
      color: white;
      border: none;
      border-radius: 10px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 10px;
    }
    .btn-close-modal {
      width: 100%;
      padding: 12px;
      background: #f5f5f5;
      color: #777;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 10px;
    }

    .notification {
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: 10px;
      color: white;
      font-weight: 600;
      z-index: 10001;
      transform: translateY(100px);
      transition: transform 0.3s ease;
    }
    .notification.show { transform: translateY(0); }
    .notification.success { background: #4caf50; }
    .notification.error { background: #f44336; }

    /* Custom styles for delete modal buttons */
    #confirmDeleteBtn {
      background: #ff5252;
      color: white;
      box-shadow: 0 8px 24px rgba(255, 82, 82, 0.3);
    }
    #confirmDeleteBtn:hover {
      background: #e34545;
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(255, 82, 82, 0.45);
    }
    #cancelDeleteBtn {
      background: #f8f9fa;
      color: #495057;
      border: 1px solid #e9ecef;
    }
    #cancelDeleteBtn:hover {
      background: #e9ecef;
      transform: translateY(-3px);
    }
  </style>
</head>

<body class="page-kelolaakun">
  <button class="menu-toggle" id="menu-toggle">
    <i data-feather="menu"></i>
  </button>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>KASIR</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="pesanan.php"><i data-feather="menu"></i> Pesanan</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="kelola_akun.php" class="active"><i data-feather="users"></i> Kelola Akun</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <!-- KONTEN UTAMA -->
  <div class="main">
    <div class="card" style="padding: 30px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin:0;">Kelola <span>Akun Admin</span></h2>
        <button class="btn-submit" style="width: auto; padding: 10px 20px;" id="btnTambahAkun">+ Tambah Akun</button>
      </div>

      <div class="account-grid" id="accountGrid">
        <?php
        $res = $conn->query("SELECT id_admin, username, role FROM admin ORDER BY role ASC, username ASC");
        while ($row = $res->fetch_assoc()) {
          $initial = strtoupper(substr($row['username'], 0, 1));
          $roleClass = "role-" . $row['role'];
          echo "
          <div class='account-card' data-id='{$row['id_admin']}'>
            <div class='avatar-circle'>{$initial}</div>
            <div class='role-badge {$roleClass}'>{$row['role']}</div>
            <div class='account-name'>".htmlspecialchars($row['username'])."</div>
            <div class='account-actions'>
              <button class='btn-acc btn-edit-acc' onclick=\"openEditModal({$row['id_admin']}, '".htmlspecialchars($row['username'])."', '{$row['role']}')\">Edit</button>
              <button class='btn-acc btn-delete-acc' onclick=\"confirmDelete({$row['id_admin']})\">Hapus</button>
            </div>
          </div>
          ";
        }
        ?>
      </div>
    </div>
  </div>

  <!-- MODAL TAMBAH/EDIT -->
  <div class="popup-overlay" id="modalAccount">
    <div class="popup-box">
      <h3 id="modalTitle">Tambah Akun</h3>
      <form id="formAccount">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="id_admin" id="formId">
        
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" id="formUsername" required placeholder="Username">
        </div>
        
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" id="formPassword" placeholder="Password">
          <small id="passHint" style="color: #999; display: none;">Kosongkan jika tidak ingin mengubah password</small>
        </div>
        
        <div class="form-group">
          <label>Role</label>
          <select name="role" id="formRole" required>
            <option value="kasir">Kasir</option>
            <option value="dapur">Dapur</option>
          </select>
        </div>
        
        <button type="submit" class="btn-submit">Simpan</button>
        <button type="button" class="btn-close-modal" id="btnCloseModal">Batal</button>
      </form>
    </div>
  </div>

  <!-- MODAL LOGOUT -->
  <div id="logoutModal" class="modal">
    <div class="modal-content">
      <div class="icon-box">🚪</div>
      <h2>Yakin ingin logout?</h2>
      <p>Sesi Anda akan diakhiri dan Anda diarahkan kembali ke halaman login.</p>
      <div class="modal-actions">
        <button id="confirmLogout">Ya, Logout</button>
        <button id="cancelLogout">Batal</button>
      </div>
    </div>
  </div>

  <!-- MODAL KONFIRMASI HAPUS (MODERN) -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="icon-box" style="background: rgba(255, 82, 82, 0.1); color: #ff5252;">⚠️</div>
      <h2>Hapus Akun?</h2>
      <p>Tindakan ini tidak dapat dibatalkan. Akun ini akan dihapus secara permanen dari sistem.</p>
      <div class="modal-actions">
        <button id="confirmDeleteBtn" style="background: #ff5252;">Ya, Hapus</button>
        <button id="cancelDeleteBtn">Batal</button>
      </div>
    </div>
  </div>

  <script src="https://unpkg.com/feather-icons"></script>
  <script>feather.replace();</script>
  <script src="../js/admin.js"></script>
  
  <script>
    const modal = document.getElementById('modalAccount');
    const form = document.getElementById('formAccount');
    
    // Open Add Modal
    document.getElementById('btnTambahAkun').onclick = () => {
      document.getElementById('modalTitle').textContent = 'Tambah Akun';
      document.getElementById('formAction').value = 'add';
      document.getElementById('formId').value = '';
      document.getElementById('formUsername').value = '';
      document.getElementById('formPassword').value = '';
      document.getElementById('formPassword').required = true;
      document.getElementById('passHint').style.display = 'none';
      modal.classList.add('active');
    };

    // Open Edit Modal
    function openEditModal(id, username, role) {
      document.getElementById('modalTitle').textContent = 'Edit Akun';
      document.getElementById('formAction').value = 'edit';
      document.getElementById('formId').value = id;
      document.getElementById('formUsername').value = username;
      document.getElementById('formPassword').value = '';
      document.getElementById('formPassword').required = false;
      document.getElementById('passHint').style.display = 'block';
      document.getElementById('formRole').value = role;
      modal.classList.add('active');
    }

    // Close Modal
    document.getElementById('btnCloseModal').onclick = () => modal.classList.remove('active');

    // Handle Form Submit
    form.onsubmit = async (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      
      try {
        const res = await fetch('kelola_akun_proses.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        
        showNotification(data.status, data.message);
        if (data.status === 'success') {
          setTimeout(() => location.reload(), 1000);
        }
      } catch (err) {
        showNotification('error', 'Terjadi kesalahan jaringan');
      }
    };

    // Handle Delete (Modern Modal)
    let deleteId = null;
    const deleteModal = document.getElementById('deleteModal');

    function confirmDelete(id) {
      deleteId = id;
      deleteModal.classList.add('show');
    }

    document.getElementById('cancelDeleteBtn').onclick = () => {
      deleteModal.classList.remove('show');
      deleteId = null;
    };

    document.getElementById('confirmDeleteBtn').onclick = async () => {
      if (!deleteId) return;
      
      const formData = new FormData();
      formData.append('action', 'delete');
      formData.append('id_admin', deleteId);
      
      deleteModal.classList.remove('show');
      
      try {
        const res = await fetch('kelola_akun_proses.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        showNotification(data.status, data.message);
        if (data.status === 'success') {
          setTimeout(() => location.reload(), 1000);
        }
      } catch (err) {
        showNotification('error', 'Gagal menghapus akun');
      }
    };

    function showNotification(status, message) {
      const notif = document.createElement('div');
      notif.className = `notification ${status}`;
      notif.textContent = message;
      document.body.appendChild(notif);
      setTimeout(() => notif.classList.add('show'), 10);
      setTimeout(() => {
        notif.classList.remove('show');
        setTimeout(() => notif.remove(), 300);
      }, 3000);
    }
  </script>
</body>
</html>
