<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Manager - Kelola Akun</title>
  <link rel="stylesheet" href="../css/kasir.css">
  <link rel="stylesheet" href="../css/logout.css">
  <link href="../css/fonts.css" rel="stylesheet">
  <style>
    :root {
      --primary: #dc143c;
      --primary-hover: #b71c1c;
      --secondary: #1e293b;
      --bg-main: #e2e8f0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--bg-main);
      margin: 0;
      overflow-x: hidden;
      max-width: 100%;
    }

    .page-kelolaakun .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: center;
      min-height: 100vh;
      padding: 40px 30px;
      box-sizing: border-box;
      min-width: 0;
    }

    .page-kelolaakun .account-grid {
      width: 90%;
      max-width: 1000px;
      margin-top: 0;
    }

    @media (max-width: 840px) {
      .page-kelolaakun .main {
        padding: 30px 20px;
      }
      .page-kelolaakun .account-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)) !important;
        gap: 20px !important;
        width: 90% !important;
      }
      .account-card {
        padding: 25px 15px !important;
        border-radius: 20px !important;
      }
      .avatar-circle {
        width: 70px !important;
        height: 70px !important;
        font-size: 30px !important;
        margin-bottom: 15px !important;
      }
      .account-name {
        font-size: 18px !important;
        margin-bottom: 20px !important;
      }
      .btn-acc {
        padding: 8px !important;
        border-radius: 10px !important;
        font-size: 13px !important;
      }
    }

    @media (max-width: 480px) {
      .page-kelolaakun .main {
        padding: 80px 15px 40px;
      }
      .page-kelolaakun .main > div:first-child {
        flex-direction: column !important;
        align-items: center !important;
        gap: 15px !important;
        text-align: center !important;
      }
      .page-kelolaakun .account-grid {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
        width: 95% !important;
      }
    }

    /* Styling khusus untuk halaman kelola akun agar lebih modern & keren */
    .account-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 30px;
      margin-top: 20px;
    }
    .account-card {
      background: white;
      border-radius: 28px;
      padding: 40px 25px;
      box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      position: relative;
      border: 1px solid #f1f5f9;
      overflow: hidden;
      
      /* Entrance Animation */
      opacity: 0;
      animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(24px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInScale {
      from {
        opacity: 0;
        transform: scale(0.98) translateY(12px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }

    .main > .card {
      animation: fadeInScale 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .account-card:nth-child(1) { animation-delay: 0.15s; }
    .account-card:nth-child(2) { animation-delay: 0.25s; }
    .account-card:nth-child(3) { animation-delay: 0.35s; }
    .account-card:nth-child(4) { animation-delay: 0.45s; }
    .account-card:nth-child(5) { animation-delay: 0.55s; }
    .account-card:nth-child(6) { animation-delay: 0.65s; }
    .account-card:nth-child(7) { animation-delay: 0.75s; }
    .account-card:nth-child(8) { animation-delay: 0.85s; }

    .account-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; width: 100%; height: 5px;
      background: linear-gradient(90deg, var(--primary), var(--primary-hover));
      opacity: 0;
      transition: opacity 0.3s;
    }
    .account-card:hover {
      transform: translateY(-12px);
      box-shadow: 0 25px 60px -15px rgba(220, 20, 60, 0.15);
      border-color: rgba(220, 20, 60, 0.2);
    }
    .account-card:hover::before {
      opacity: 1;
    }
    .avatar-circle {
      width: 90px;
      height: 90px;
      background: linear-gradient(135deg, var(--primary), var(--primary-hover));
      border-radius: 35%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 38px;
      font-weight: 700;
      margin-bottom: 25px;
      transform: rotate(-6deg);
      border: 4px solid white;
      box-shadow: 0 10px 20px rgba(220, 20, 60, 0.2);
      transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .account-card:hover .avatar-circle {
      transform: rotate(0deg) scale(1.1);
    }
    .role-badge {
      padding: 6px 16px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      margin-bottom: 15px;
    }
    .role-kasir { background: #fee2e2; color: var(--primary); }
    .role-dapur { background: #fff3e0; color: #f57c00; }
    .role-manager { background: #e0f2fe; color: #0284c7; }
    
    .account-name {
      font-size: 22px;
      font-weight: 700;
      color: var(--secondary);
      margin-bottom: 30px;
    }
    .account-actions {
      display: flex;
      gap: 15px;
      width: 100%;
    }
    .btn-acc {
      flex: 1;
      padding: 12px;
      border: none;
      border-radius: 14px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
      font-family: 'Poppins', sans-serif;
    }
    .btn-edit-acc { background: #f1f5f9; color: var(--secondary); }
    .btn-delete-acc { background: #fff1f2; color: #e11d48; }
    .btn-acc:hover { transform: translateY(-3px); }
    .btn-edit-acc:hover { background: #fee2e2; color: var(--primary); }
    .btn-delete-acc:hover { background: #e11d48; color: white; }

    /* Modal Styling */
    .popup-overlay {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(15, 23, 42, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      backdrop-filter: blur(8px);
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .popup-overlay.active { 
      opacity: 1; 
      visibility: visible; 
      pointer-events: auto; 
    }
    .popup-box {
      background: white;
      width: 90%;
      max-width: 440px;
      border-radius: 30px;
      padding: 40px;
      box-shadow: 0 30px 60px rgba(0,0,0,0.25);
      transform: translateY(30px) scale(0.95);
      opacity: 0;
      transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .popup-overlay.active .popup-box {
      transform: translateY(0) scale(1);
      opacity: 1;
    }
    .popup-box h3 { font-size: 24px; margin-bottom: 30px; text-align: center; color: var(--secondary); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
    .form-group input, .form-group select {
      width: 100%;
      padding: 14px;
      border: 2px solid #f1f5f9;
      border-radius: 14px;
      font-size: 14px;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s;
    }
    .form-group input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(220, 20, 60, 0.1); }
    .btn-submit {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, var(--primary), var(--primary-hover));
      color: white;
      border: none;
      border-radius: 14px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 10px;
      font-family: 'Poppins', sans-serif;
      box-shadow: 0 10px 20px rgba(220, 20, 60, 0.2);
    }
    .btn-close-modal {
      width: 100%;
      padding: 14px;
      background: #f1f5f9;
      color: #64748b;
      border: none;
      border-radius: 14px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 15px;
      font-family: 'Poppins', sans-serif;
    }

    /* Notification Redesign */
    .notification {
      position: fixed;
      top: 30px;
      right: 30px;
      min-width: 320px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.5);
      border-radius: 24px;
      padding: 20px 25px;
      display: flex;
      align-items: center;
      gap: 18px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.1);
      transform: translateX(120%);
      transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      z-index: 10005;
      overflow: hidden;
    }
    .notification.show { transform: translateX(0); }
    .notif-icon {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .notification.success .notif-icon { background: #dcfce7; color: #10b981; }
    .notification.error .notif-icon { background: #fee2e2; color: #ef4444; }
    .notif-content h4 { margin: 0; font-size: 16px; font-weight: 700; color: rgba(253, 247, 247, 0.93); }
    .notif-content p { margin: 2px 0 0; font-size: 13px; color: #f8f2f2ff; }
    .notif-progress { position: absolute; bottom: 0; left: 0; height: 4px; width: 100%; background: rgba(0,0,0,0.05); }
    .notif-progress-bar { height: 100%; width: 100%; background: var(--primary); transform-origin: left; transition: transform linear; }

    /* Custom styles for delete modal buttons */
    #confirmDeleteBtn { background: var(--primary); color: white; font-family: 'Poppins', sans-serif; }
    #cancelDeleteBtn { background: #f1f5f9; color: var(--secondary); font-family: 'Poppins', sans-serif; }
  </style>
</head>

<body class="page-kelolaakun">
  <button class="menu-toggle" id="menu-toggle">
    <i data-feather="menu"></i>
  </button>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>MANAGER</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="kelola_akun.php" class="active"><i data-feather="users"></i> Kelola Akun</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <!-- KONTEN UTAMA -->
  <div class="main">
    <div class="header-card" style="
      background: white;
      border-radius: 24px;
      padding: 20px 30px;
      box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05);
      border: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 90%;
      max-width: 1000px;
      margin-bottom: 35px;
      position: relative;
      overflow: hidden;
      box-sizing: border-box;
    ">
      <h2 style="margin:0; font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
        <i data-feather="users" style="color: var(--primary); width: 28px; height: 28px;"></i>
        <span>Kelola <span style="color: var(--primary);">Akun Admin</span></span>
      </h2>
      <button class="btn-submit" style="width: auto; padding: 12px 24px; margin: 0;" id="btnTambahAkun">+ Tambah Akun</button>
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
            <option value="manager">Manager</option>
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
      <div class="icon-box"><i data-feather="log-out"></i></div>
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
      <div class="icon-box" style="background: rgba(220, 20, 60, 0.1); color: var(--primary);"><i data-feather="alert-triangle" style="width:32px; height:32px; color:#dc143c; stroke-width:2;"></i></div>
      <h2>Hapus Akun?</h2>
      <p>Tindakan ini tidak dapat dibatalkan. Akun ini akan dihapus secara permanen dari sistem.</p>
      <div class="modal-actions">
        <button id="confirmDeleteBtn">Ya, Hapus</button>
        <button id="cancelDeleteBtn">Batal</button>
      </div>
    </div>
  </div>

  <script src="../js/feather.min.js"></script>
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
      const oldNotif = document.querySelector('.notification');
      if (oldNotif) oldNotif.remove();

      const notif = document.createElement('div');
      notif.className = `notification ${status}`;
      
      const iconName = status === 'success' ? 'check-circle' : 'alert-circle';
      const title = status === 'success' ? 'Berhasil!' : 'Terjadi Kesalahan';

      notif.innerHTML = `
        <div class="notif-icon"><i data-feather="${iconName}"></i></div>
        <div class="notif-content">
          <h4>${title}</h4>
          <p>${message}</p>
        </div>
        <div class="notif-progress">
          <div class="notif-progress-bar"></div>
        </div>
      `;

      document.body.appendChild(notif);
      feather.replace();

      setTimeout(() => notif.classList.add('show'), 10);
      
      const progressBar = notif.querySelector('.notif-progress-bar');
      progressBar.style.transition = 'transform 3s linear';
      progressBar.style.transform = 'scaleX(0)';

      setTimeout(() => {
        notif.classList.remove('show');
        setTimeout(() => notif.remove(), 500);
      }, 3000);
    }
  </script>
</body>
</html>
