<?php 
session_start();
include "../koneksi.php";

// Cek apakah sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dapur</title>
    <!-- CSS -->
    <link rel="stylesheet" href="../css/dapur.css" />
    <link rel="stylesheet" href="../css/logout.css" />

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
  </head>
  <body class="page-dashboard">
    <!-- Tombol toggle untuk mobile -->
    <button class="menu-toggle" id="menu-toggle">
      <i data-feather="menu"></i>
    </button>

    <div class="sidebar">
      <h1>WARKOP<span> SERAYA</span></h1>
      <h2>DAPUR</h2>
      <ul>
        <li>
          <a href="dashboard.php" class="active"
            ><i data-feather="home"></i> Beranda</a
          >
        </li>
        <li>
          <a href="pesanan.php"><i data-feather="menu"></i> Pesanan</a>
        </li>
        <li>
          <a href="menu_kosong.php"
            ><i data-feather="x-circle"></i> Menu Tidak Tersedia</a
          >
        </li>
        <li><a href="kelola_menu.php"><i data-feather="settings"></i> Kelola Menu</a></li>
        <li>
          <a href="riwayat_pesanan.php"
            ><i data-feather="clock"></i> Riwayat Pesanan</a
          >
        </li>
        <li>
          <!-- ✅ tambahin id="logoutBtn" -->
          <a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a>
        </li>
      </ul>
    </div>

    <div class="main">
      <h1>Selamat Datang, Tim Dapur!</h1>
    </div>

    <!-- Logout -->
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

    <!-- Feather Icons -->
    <script>
      feather.replace();
    </script>
    <!-- Javascript -->
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

    let initiallyFirst = typeof window.isFirstPoll === 'undefined';
    window.isFirstPoll = false;

    if (data.ada_pesanan) {
      if (initiallyFirst && lastOrderId === null) {
        lastOrderId = data.id;
      } else if (lastOrderId === null || Number(data.id) > Number(lastOrderId)) {
        lastOrderId = data.id;
        playOrderNotification();
      }
    }
  } catch (err) {
    console.error("❌ Error cek pesanan:", err);
  }
}

// 🔔 Tampilkan notifikasi dan bunyi
function playOrderNotification() {
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
