<?php
session_start();
include "../koneksi.php";
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
      $result = $conn->query("SELECT * FROM produk ORDER BY id_produk DESC");
      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $gambar = !empty($row['image_url']) ? "../image_menu/" . $row['image_url'] : "../image_menu/default.jpg";
          $kategori = htmlspecialchars($row['kategori'] ?? '-');
          $harga = number_format($row['harga_produk'], 0, ',', '.');
          $nama_safe = htmlspecialchars($row['nama_produk']);
          $id = (int)$row['id_produk'];
          echo "
          <div class='menu-card' id='menu-{$id}'>
            <img src='{$gambar}' alt='{$nama_safe}'>
            <h3>{$nama_safe}</h3>
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
      <h3>Tambah Menu Baru</h3>

      <form id="formTambah" enctype="multipart/form-data">
        <div class="form-group">
          <label>Nama Produk</label>
          <input type="text" name="nama_produk" required>
        </div>

        <div class="form-group">
          <label>Kategori</label>
          <input type="text" name="kategori" required>
        </div>

        <div class="form-group">
          <label>Harga Dasar</label>
          <input type="number" name="harga_dasar" required>
        </div>

        <label>Varian & Harga</label>
        <div id="varian-container">
          <div class="varian-row">
            <input type="text" name="varian_nama[]" placeholder="Nama varian">
            <input type="number" name="varian_harga[]" placeholder="Harga varian">
            <button type="button" class="remove-varian">&times;</button>
          </div>
        </div>

        <button type="button" class="btn-tambah-varian" id="addVarian">+ Tambah Varian</button>

        <div class="form-group">
          <label>Upload Gambar</label>
          <input type="file" name="gambar" accept="image/*">
        </div>

        <div class="popup-actions">
          <button type="submit" class="btn-simpan">Simpan</button>
          <button type="button" class="btn-cancel" id="cancelTambah">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- POPUP EDIT MENU -->
  <div class="popup-kelolamenu" id="popupEdit">
    <div class="popup-content">
      <span class="close-popup" id="closeEdit">&times;</span>
      <h3>Edit Menu</h3>

      <form id="formEdit" enctype="multipart/form-data">
        <input type="hidden" name="id_produk" id="edit_id">

        <div class="form-group">
          <label>Nama Produk</label>
          <input type="text" name="nama_produk" id="edit_nama" required>
        </div>

        <div class="form-group">
          <label>Kategori</label>
          <input type="text" name="kategori" id="edit_kategori" required>
        </div>

        <div class="form-group">
          <label>Harga Dasar</label>
          <input type="number" name="harga_dasar" id="edit_harga_dasar" required>
        </div>

        <label>Varian & Harga</label>
        <div id="edit-varian-container"></div>
        <button type="button" class="btn-tambah-varian" id="addEditVarian">+ Tambah Varian</button>

        <div class="form-group">
          <label>Ganti Gambar</label>
          <input type="file" name="gambar" accept="image/*">
        </div>

        <div class="popup-actions">
          <button type="submit" class="btn-update">Update</button>
          <button type="button" class="btn-cancel" id="cancelEdit">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- POPUP HAPUS MENU -->
  <div id="popupDelete" class="popup-delete">
    <div class="popup-content">
      <h3>Konfirmasi Hapus</h3>
      <p>Apakah kamu yakin ingin menghapus menu ini?</p>
      <div class="popup-actions">
        <button id="btnConfirmDelete" class="btn-confirm">Ya, Hapus</button>
        <button id="btnCancelDelete" class="btn-cancel">Batal</button>
      </div>
    </div>
  </div>

  <!-- Logout -->
  <div id="logoutModal" class="modal">
    <div class="modal-content">
      <h2>Yakin ingin logout?</h2>
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
    r.innerHTML = `<input type="text" name="varian_nama[]" placeholder="Nama varian">
                   <input type="number" name="varian_harga[]" placeholder="Harga varian">
                   <button type="button" class="remove-varian">&times;</button>`;
    c.appendChild(r);
  };
  document.addEventListener('click', e => {
    if (e.target.classList.contains('remove-varian')) e.target.parentElement.remove();
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
                         <button type="button" class="remove-varian">&times;</button>`;
          c.appendChild(r);
        });
      }
      popupEdit.classList.add('active');
    } catch {
      showNotification('error', 'Gagal mengambil data menu');
    }
  }

  document.getElementById('addEditVarian').onclick = () => {
    const c = document.getElementById('edit-varian-container');
    const r = document.createElement('div');
    r.classList.add('varian-row');
    r.innerHTML = `<input type="text" name="varian_nama[]" placeholder="Nama varian">
                   <input type="number" name="varian_harga[]" placeholder="Harga">
                   <button type="button" class="remove-varian">&times;</button>`;
    c.appendChild(r);
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
    popupDelete.classList.add("active");
  }
  btnCancelDelete.onclick = () => popupDelete.classList.remove("active");

  btnConfirmDelete.onclick = async () => {
    if (!selectedMenuId) return;
    const fd = new FormData();
    fd.append('id', selectedMenuId);
    try {
      const res = await fetch("hapus_menu.php", { method: "POST", body: fd });
      const data = await parseResponse(res);
      popupDelete.classList.remove("active");
      showNotification(data.status, data.message || "Terjadi kesalahan!");
      if (data.status === "success") setTimeout(()=> location.reload(), 800);
    } catch {
      popupDelete.classList.remove("active");
      showNotification('error', 'Kesalahan jaringan saat menghapus');
    }
  };
  </script>
  <script src="https://unpkg.com/feather-icons"></script>
  <script>feather.replace();</script>
  <script src="../js/admin.js"></script>
<!-- 🔔 Notifikasi Pesanan -->
<audio id="notifAudio" src="notif/notif.mp3" preload="auto"></audio>
<div id="notifBox" style="
  display:none;
  position:fixed;
  top:20px;
  right:20px;
  background:#ffc107;
  padding:15px 20px;
  border-radius:10px;
  font-weight:bold;
  color:#222;
  box-shadow:0 4px 8px rgba(0,0,0,0.3);
  z-index:9999;
">
  Pesanan baru masuk!
</div>

<script>
let lastOrderId = null;

// ✅ Aktifkan izin audio setelah user klik pertama kali di halaman
document.addEventListener("click", () => {
  const audio = document.getElementById("notifAudio");
  if (audio) {
    audio.play().then(() => audio.pause()).catch(() => {});
    console.log("✅ Izin audio aktif");
  }
}, { once: true });

// 🔁 Cek pesanan baru tiap 5 detik
async function checkNewOrder() {
  try {
    const res = await fetch("../admin_dapur/cek_pesanan.php");
    const data = await res.json();
    console.log("Cek pesanan:", data);

    if (data.ada_pesanan) {
      if (lastOrderId === null) {
        lastOrderId = data.id;
      } else if (data.id !== lastOrderId) {
        lastOrderId = data.id;
        showNotification();
      }
    }
  } catch (err) {
    console.error("❌ Error cek pesanan:", err);
  }
}

// 🔔 Tampilkan notifikasi dan bunyi
function showNotification() {
  const box = document.getElementById("notifBox");
  const audio = document.getElementById("notifAudio");

  if (!box || !audio) return;

  box.style.display = "block";
  setTimeout(() => box.style.display = "none", 5000);

  audio.currentTime = 0;
  audio.play()
    .then(() => console.log("✅ Suara notifikasi diputar"))
    .catch(err => console.warn("⚠️ Audio gagal diputar:", err));
}

// 🔁 Jalankan cek otomatis
setInterval(checkNewOrder, 5000);
</script>
</body>
</html>
