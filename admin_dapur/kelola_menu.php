<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dapur') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dapur - Kelola Menu</title>
  <link rel="stylesheet" href="../css/dapur.css">
  <link rel="stylesheet" href="../css/logout.css">
</head>

<body class="page-kelolamenu">
  <button class="menu-toggle" id="menu-toggle">
    <i data-feather="menu"></i>
  </button>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>DAPUR</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="pesanan.php"><i data-feather="menu"></i> Pesanan</a></li>
      <li><a href="menu_kosong.php"><i data-feather="x-circle"></i> Menu Tidak Tersedia</a></li>
      <li><a href="kelola_menu.php" class="active"><i data-feather="settings"></i> Kelola Menu</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <!-- KONTEN UTAMA -->
  <div class="content">
    <h2 class="judul-halaman">Kelola <span>Menu</span></h2>

    <div class="tambah-container">
      <button class="btn-tambah" id="openPopup">+ Tambah Menu</button>
    </div>

    <!-- GRID MENU -->
    <div class="menu-grid">
      <?php
      $result = $conn->query("
        SELECT 
            p.*, 
            a.username AS nama_pembuat,
            e.username AS nama_pengedit
        FROM produk p 
        LEFT JOIN admin a ON p.id_admin = a.id_admin 
        LEFT JOIN admin e ON p.updated_by = e.id_admin
        ORDER BY p.id_produk DESC
      ");
      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $gambar = !empty($row['image_url']) ? "../image_menu/" . $row['image_url'] : "../image_menu/default.jpg";
          $kategori = htmlspecialchars($row['kategori'] ?? '-');
          $harga = number_format($row['harga_produk'], 0, ',', '.');
          $nama_safe = htmlspecialchars($row['nama_produk']);
          $id = (int)$row['id_produk'];
          $nama_pembuat = !empty($row['nama_pembuat']) ? htmlspecialchars($row['nama_pembuat']) : 'Sistem';
          
          // Tampilkan info pengedit jika ada
          $info_edit = "";
          if (!empty($row['nama_pengedit'])) {
              $info_edit = "<br><i data-feather='edit-2' style='width: 12px; height: 12px;'></i> Diedit oleh: <strong>" . htmlspecialchars($row['nama_pengedit']) . "</strong>";
          }

          echo "
          <div class='menu-card' id='menu-{$id}'>
            <img src='{$gambar}' alt='{$nama_safe}'>
            <h3>{$nama_safe}</h3>
            <p style='font-size: 12px; color: #666; margin: -5px 0 15px 0; text-align: center; line-height: 1.4;'>
              <i data-feather='user' style='width: 12px; height: 12px;'></i> Ditambahkan oleh: <strong>{$nama_pembuat}</strong>
              {$info_edit}
            </p>
            <div class='card-actions'>
              <button class='btn btn-edit' onclick=\"openEditPopup({$id})\">Edit</button>
              <button class='btn btn-delete' onclick=\"confirmDeleteMenu({$id})\">Hapus</button>
            </div>
          </div>
          ";
        }
      } else {
        echo "<p class='no-data'>Belum ada menu yang tersedia.</p>";
      }
      ?>
    </div>
  </div>

  <!-- POPUP TAMBAH MENU -->
  <div class="popup-kelolamenu" id="popupTambah">
    <div class="popup-content">
      <span class="close-popup" id="closeTambah">&times;</span>
      <div class="icon-box"><i data-feather="plus-square"></i></div>
      <h3>Tambah Menu Baru</h3>

      <form id="formTambah" enctype="multipart/form-data">
        <div class="form-group">
          <label>Nama Produk</label>
          <input type="text" name="nama_produk" placeholder="Nama Produk" required>
        </div>

        <div class="form-group">
          <label>Kategori</label>
          <select name="kategori" required>
            <option value="" disabled selected>Pilih Kategori Produk</option>
            <option value="Kopi Series">Kopi Series</option>
            <option value="Good Day">Good Day</option>
            <option value="Nutrisari">Nutrisari</option>
            <option value="Susu Series">Susu Series</option>
            <option value="Signature">Signature</option>
            <option value="Cemilan">Cemilan</option>
            <option value="Aneka Nasi">Aneka Nasi</option>
            <option value="Aneka Mie">Aneka Mie</option>
          </select>
        </div>

        <div class="form-group">
          <label>Harga Dasar</label>
          <input type="number" name="harga_dasar" placeholder="0" required>
        </div>

        <div class="varian-section-label">Varian & Harga</div>
        <div class="varian-header">
          <span>Nama Varian</span>
          <span>Harga</span>
          <span></span>
        </div>
        <div id="varian-container">
          <div class="varian-row">
            <input type="text" name="varian_nama[]" placeholder="Varian Produk">
            <input type="number" name="varian_harga[]" placeholder="0">
            <button type="button" class="remove-varian"><i data-feather="trash-2"></i></button>
          </div>
        </div>

        <button type="button" class="btn-tambah-varian" id="addVarian">
          <i data-feather="plus-circle"></i> Tambah Varian
        </button>

        <div class="form-group">
          <label>Upload Gambar</label>
          <input type="file" name="gambar" accept="image/*">
        </div>

        <div class="popup-actions">
          <button type="submit" class="btn-simpan">Simpan Menu</button>
          <button type="button" class="btn-cancel" id="cancelTambah">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- POPUP EDIT MENU -->
  <div class="popup-kelolamenu" id="popupEdit">
    <div class="popup-content">
      <span class="close-popup" id="closeEdit">&times;</span>
      <div class="icon-box"><i data-feather="edit"></i></div>
      <h3>Edit Menu</h3>

      <form id="formEdit" enctype="multipart/form-data">
        <input type="hidden" name="id_produk" id="edit_id">

        <div class="form-group">
          <label>Nama Produk</label>
          <input type="text" name="nama_produk" id="edit_nama" required>
        </div>

        <div class="form-group">
          <label>Kategori</label>
          <select name="kategori" id="edit_kategori" required>
            <option value="" disabled selected>Pilih Kategori Produk</option>
            <option value="Kopi Series">Kopi Series</option>
            <option value="Good Day">Good Day</option>
            <option value="Nutrisari">Nutrisari</option>
            <option value="Susu Series">Susu Series</option>
            <option value="Signature">Signature</option>
            <option value="Cemilan">Cemilan</option>
            <option value="Aneka Nasi">Aneka Nasi</option>
            <option value="Aneka Mie">Aneka Mie</option>
          </select>
        </div>

        <div class="form-group">
          <label>Harga Dasar</label>
          <input type="number" name="harga_dasar" id="edit_harga_dasar" required>
        </div>

        <div class="varian-section-label">Varian & Harga</div>
        <div class="varian-header">
          <span>Nama Varian</span>
          <span>Harga</span>
          <span></span>
        </div>
        <div id="edit-varian-container"></div>
        <button type="button" class="btn-tambah-varian" id="addEditVarian">
          <i data-feather="plus-circle"></i> Tambah Varian
        </button>

        <div class="form-group">
          <label>Ganti Gambar</label>
          <input type="file" name="gambar" accept="image/*">
        </div>

        <div class="popup-actions">
          <button type="submit" class="btn-update">Update Menu</button>
          <button type="button" class="btn-cancel" id="cancelEdit">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- POPUP HAPUS MENU -->
  <div id="popupDelete" class="modal">
    <div class="modal-content">
      <div class="icon-box" style="background: rgba(220, 20, 60, 0.1); color: var(--primary);"><i data-feather="alert-triangle" style="width:32px; height:32px; color:#dc143c; stroke-width:2;"></i></div>
      <h2>Hapus Menu?</h2>
      <p>Tindakan ini tidak dapat dibatalkan. Menu ini akan dihapus secara permanen dari sistem.</p>
      <div class="modal-actions">
        <button id="btnConfirmDelete" class="btn-confirm" style="background: var(--primary); color: white; font-family: 'Poppins', sans-serif;">Ya, Hapus</button>
        <button id="btnCancelDelete" class="btn-cancel" style="background: #f1f5f9; color: #1e293b; font-family: 'Poppins', sans-serif;">Batal</button>
      </div>
    </div>
  </div>

  <!-- Logout -->
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

  <script>
  // ---------- Helper ----------
  async function parseResponse(res) {
    const txt = await res.text();
    try { return JSON.parse(txt); } catch { return { status: 'error', message: txt || 'Response tidak valid' }; }
  }

  function showNotification(status, message) {
    const notif = document.createElement("div");
    notif.className = `notification ${status === 'success' ? 'success' : 'error'}`;
    notif.textContent = message;
    document.body.appendChild(notif);
    setTimeout(() => notif.classList.add("show"), 10);
    setTimeout(() => notif.classList.remove("show"), 2200);
    setTimeout(() => notif.remove(), 2600);
  }

  // === Popup Tambah ===
  const popupTambah = document.getElementById('popupTambah');
  document.getElementById('openPopup').onclick = () => popupTambah.classList.add('active');
  document.getElementById('closeTambah').onclick = () => popupTambah.classList.remove('active');
  document.getElementById('cancelTambah').onclick = () => popupTambah.classList.remove('active');
  popupTambah.addEventListener('click', e => { if (e.target === popupTambah) popupTambah.classList.remove('active'); });

  // === Tambah Varian ===
  document.getElementById('addVarian').onclick = () => {
    const c = document.getElementById('varian-container');
    const r = document.createElement('div');
    r.classList.add('varian-row');
    r.innerHTML = `<input type="text" name="varian_nama[]" placeholder="Varian Produk">
                   <input type="number" name="varian_harga[]" placeholder="0">
                   <button type="button" class="remove-varian"><i data-feather="trash-2"></i></button>`;
    c.appendChild(r);
    feather.replace();
  };
  document.addEventListener('click', e => {
    const removeBtn = e.target.closest('.remove-varian');
    if (removeBtn) {
      removeBtn.closest('.varian-row').remove();
    }
  });

  // === Simpan Menu Baru ===
  document.getElementById('formTambah').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    try {
      const res = await fetch('tambah_menu_proses.php', { method: 'POST', body: fd });
      const data = await parseResponse(res);
      showNotification(data.status, data.message || 'Gagal menyimpan menu');
      if (data.status === 'success') setTimeout(() => location.reload(), 800);
    } catch {
      showNotification('error', 'Terjadi kesalahan jaringan');
    }
  });

  // === Edit Menu ===
  const popupEdit = document.getElementById('popupEdit');
  document.getElementById('closeEdit').onclick = () => popupEdit.classList.remove('active');
  document.getElementById('cancelEdit').onclick = () => popupEdit.classList.remove('active');
  popupEdit.addEventListener('click', e => { if (e.target === popupEdit) popupEdit.classList.remove('active'); });

  async function openEditPopup(id){
    try {
      const res = await fetch('get_menu.php?id=' + id);
      const d = await res.json();
      const p = d.produk ?? d.data ?? d; // adaptasi struktur JSON

      if (!p || !p.id_produk) {
        showNotification('error', 'Data produk tidak ditemukan');
        return;
      }

      document.getElementById('edit_id').value = p.id_produk;
      document.getElementById('edit_nama').value = p.nama_produk ?? '';
      document.getElementById('edit_kategori').value = p.kategori ?? '';
      document.getElementById('edit_harga_dasar').value = p.harga_dasar ?? '';

      const c = document.getElementById('edit-varian-container');
      c.innerHTML = '';
      if (Array.isArray(p.varian)) {
        p.varian.forEach(v=>{
          const r = document.createElement('div');
          r.classList.add('varian-row');
          r.innerHTML = `<input type="text" name="varian_nama[]" value="${v.nama_varian}">
                         <input type="number" name="varian_harga[]" value="${v.harga_varian}">
                         <button type="button" class="remove-varian"><i data-feather="trash-2"></i></button>`;
          c.appendChild(r);
        });
      }
      feather.replace();
      popupEdit.classList.add('active');
    } catch {
      showNotification('error', 'Gagal mengambil data menu');
    }
  }

  document.getElementById('addEditVarian').onclick = () => {
    const c = document.getElementById('edit-varian-container');
    const r = document.createElement('div');
    r.classList.add('varian-row');
    r.innerHTML = `<input type="text" name="varian_nama[]" placeholder="Varian">
                   <input type="number" name="varian_harga[]" placeholder="0">
                   <button type="button" class="remove-varian"><i data-feather="trash-2"></i></button>`;
    c.appendChild(r);
    feather.replace();
  };

  // === Submit Edit Menu ===
  document.getElementById('formEdit').addEventListener('submit', async e => {
    e.preventDefault();
    const idVal = document.getElementById('edit_id')?.value || "";
    if (!idVal) {
      showNotification('error', 'ID produk kosong, buka ulang menu edit.');
      return;
    }
    const fd = new FormData(e.target);
    fd.set('id_produk', idVal);

    try {
      const res = await fetch('edit_menu_proses.php', { method: 'POST', body: fd });
      const data = await parseResponse(res);
      showNotification(data.status, data.message || 'Gagal memperbarui menu');
      if (data.status === 'success') setTimeout(() => location.reload(), 800);
    } catch {
      showNotification('error', 'Kesalahan jaringan saat memperbarui');
    }
  });

  // === Popup Hapus ===
  let selectedMenuId = null;
  const popupDelete = document.getElementById("popupDelete");
  const btnConfirmDelete = document.getElementById("btnConfirmDelete");
  const btnCancelDelete = document.getElementById("btnCancelDelete");

  function confirmDeleteMenu(id) {
    selectedMenuId = id;
    popupDelete.classList.remove("hide");
    popupDelete.classList.add("show");
  }
  btnCancelDelete.onclick = () => {
    popupDelete.classList.add("hide");
    setTimeout(() => {
      popupDelete.classList.remove("show");
      popupDelete.classList.remove("hide");
    }, 280);
  };
  popupDelete.addEventListener('click', e => {
    if (e.target === popupDelete) {
      popupDelete.classList.add("hide");
      setTimeout(() => {
        popupDelete.classList.remove("show");
        popupDelete.classList.remove("hide");
      }, 280);
    }
  });

  btnConfirmDelete.onclick = async () => {
    if (!selectedMenuId) return;
    const fd = new FormData();
    fd.append('id', selectedMenuId);
    try {
      const res = await fetch("hapus_menu.php", { method: "POST", body: fd });
      const data = await parseResponse(res);
      
      popupDelete.classList.add("hide");
      setTimeout(() => {
        popupDelete.classList.remove("show");
        popupDelete.classList.remove("hide");
      }, 280);

      showNotification(data.status, data.message || "Terjadi kesalahan!");
      if (data.status === "success") setTimeout(()=> location.reload(), 800);
    } catch {
      popupDelete.classList.add("hide");
      setTimeout(() => {
        popupDelete.classList.remove("show");
        popupDelete.classList.remove("hide");
      }, 280);
      showNotification('error', 'Kesalahan jaringan saat menghapus');
    }
  };
  </script>
  <script src="../js/feather.min.js"></script>
  <script>feather.replace();</script>
  <script src="../js/admin.js"></script>
<!-- 🔔 Notifikasi Pesanan -->
<audio id="notifAudio" src="notif/notif.mp3" preload="auto"></audio>
<style>
@keyframes slideInNotif {
  from { transform: translateX(120%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
@keyframes fadeOutNotif {
  from { transform: translateX(0); opacity: 1; }
  to { transform: translateX(120%); opacity: 0; }
}
.notif-toast {
  display: none;
  align-items: center;
  gap: 14px;
  position: fixed;
  top: 20px;
  right: 20px;
  background: rgba(28, 28, 30, 0.95);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-left: 4px solid #ffc107;
  padding: 16px 20px;
  border-radius: 12px;
  color: #fff;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  z-index: 9999;
  font-family: system-ui, -apple-system, sans-serif;
  width: 320px;
  animation: slideInNotif 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  transition: all 0.3s ease;
}
.notif-toast.hide {
  animation: fadeOutNotif 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>

<div id="notifBox" class="notif-toast">
  <div style="background: rgba(255, 193, 7, 0.15); color: #ffc107; padding: 10px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
      <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </svg>
  </div>
  <div style="flex-grow: 1; display: flex; flex-direction: column; gap: 2px;">
    <div style="font-size: 11px; font-weight: 700; color: #ffc107; letter-spacing: 0.8px; text-transform: uppercase;">Pesanan Baru</div>
    <div style="font-size: 14px; color: #e5e5ea; font-weight: 500; line-height: 1.3;">Ada pesanan baru masuk!</div>
  </div>
</div>

<script>
// ✅ Ambil lastOrderId dari sessionStorage agar tidak reset saat pindah halaman
let lastOrderId = sessionStorage.getItem('dapur_lastOrderId');
let isFirstPoll = true;

// 🔁 Cek pesanan baru tiap 5 detik
async function checkNewOrder() {
  try {
    const res = await fetch("../admin_dapur/cek_pesanan.php");
    const data = await res.json();

    if (data.ada_pesanan) {
      if (isFirstPoll) {
        lastOrderId = String(data.id);
        sessionStorage.setItem('dapur_lastOrderId', lastOrderId);
        isFirstPoll = false;
      } else if (!lastOrderId || Number(data.id) > Number(lastOrderId)) {
        lastOrderId = String(data.id);
        sessionStorage.setItem('dapur_lastOrderId', lastOrderId);
        playOrderNotification();
      }
    }
  } catch (err) {
    console.error("Error cek pesanan:", err);
  }
}

// 🔔 Tampilkan notifikasi dan bunyi
function playOrderNotification() {
  const box = document.getElementById("notifBox");
  const audio = document.getElementById("notifAudio");

  if (!box || !audio) return;

  box.classList.remove("hide");
  box.style.display = "flex";
  
  setTimeout(() => {
    box.classList.add("hide");
    setTimeout(() => {
      box.style.display = "none";
    }, 400);
  }, 4600);

  audio.currentTime = 0;
  audio.play()
    .then(() => console.log("Suara notifikasi diputar"))
    .catch(err => console.warn("Audio gagal diputar:", err));
}

// 🔁 Jalankan cek otomatis
setInterval(checkNewOrder, 5000);
</script>
</body>
</html>
