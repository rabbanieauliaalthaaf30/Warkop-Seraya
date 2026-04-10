<?php 
session_start();
include "../koneksi.php"; 

// Handler AJAX untuk toggle status menu
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    $menuId = intval($_POST['id']);

    $cek = mysqli_query($conn, "SELECT status FROM produk WHERE id_produk = $menuId");
    $row = mysqli_fetch_assoc($cek);

    if ($row) {
        $statusBaru = ($row['status'] == 'tersedia') ? 'tidak tersedia' : 'tersedia';

        $update = mysqli_query($conn, "UPDATE produk SET status = '$statusBaru' WHERE id_produk = $menuId");
        if ($update) {
            echo json_encode(["success" => true, "status" => ($statusBaru == 'tersedia' ? 1 : 0)]);
        } else {
            echo json_encode(["success" => false, "error" => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Menu tidak ditemukan"]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dapur - Menu Tidak Tersedia</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../css/dapur.css" />
  <link rel="stylesheet" href="../css/logout.css" />

  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons"></script>
</head>
<body class="page-menukosong">
  <!-- Tombol toggle untuk mobile -->
  <button class="menu-toggle" id="menu-toggle">
    <i data-feather="menu"></i>
  </button>

  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>DAPUR</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="pesanan.php"><i data-feather="menu"></i> Pesanan</a></li>
      <li><a href="menu_kosong.php" class="active"><i data-feather="x-circle"></i> Menu Tidak Tersedia</a></li>
      <li><a href="kelola_menu.php"><i data-feather="settings"></i> Kelola Menu</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main">
    <h1>Menu Tidak <span>Tersedia</span></h1>
    <div class="menu-grid">
      <?php
      $result = mysqli_query($conn, "SELECT * FROM produk");

      while ($row = mysqli_fetch_assoc($result)) {
          $statusClass = ($row['status'] == 'tidak tersedia') ? "unavailable" : "";
          $buttonText = ($row['status'] == 'tersedia') ? "Tandai Tidak Tersedia" : "Tandai Tersedia";

          echo '
          <div class="menu-card '.$statusClass.'">
            <img src="../image_menu/'.$row['image_url'].'" alt="'.$row['nama_produk'].'" />
            <h3>'.$row['nama_produk'].'</h3>
            <button class="btn-tandai" data-id="'.$row['id_produk'].'">'.$buttonText.'</button>
          </div>';
      }
      ?>
    </div>
  </div>

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal">
    <div class="modal-content">
      <h2>Yakin ingin logout?</h2>
      <div class="modal-actions">
        <button id="confirmLogout">Ya, Logout</button>
        <button id="cancelLogout">Batal</button>
      </div>
    </div>
  </div>

  <!-- Feather Icons -->
  <script>feather.replace();</script>

  <!-- JavaScript -->
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
