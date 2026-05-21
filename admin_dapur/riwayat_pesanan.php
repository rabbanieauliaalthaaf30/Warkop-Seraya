<?php 
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dapur') {
    header("Location: ../login.php");
    exit;
}

// ======================
// ✅ Pagination setup
// ======================
$limit = 10; // max 10 baris
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Hitung total data
$countSql = "
  SELECT COUNT(DISTINCT t.id_transaksi) as total
  FROM transaksi t
  WHERE LOWER(TRIM(t.status_pesanan)) = 'selesai'
";
$countRes = mysqli_query($conn, $countSql);
$totalRow = mysqli_fetch_assoc($countRes)['total'];
$totalPages = ceil($totalRow / $limit);

// ======================
// ✅ Fungsi render isi tabel
// ======================
function renderRiwayatTable($conn, $start, $limit) {
  $sql = "
    SELECT 
      t.id_transaksi, 
      t.nomor_meja, 
      t.nama_pemesan, 
      t.status_pesanan, 
      t.waktu_pemesanan, 
      t.total,
      GROUP_CONCAT(
        CONCAT(
          p.nama_produk,
          IF(vp.nama_varian IS NOT NULL AND vp.nama_varian != '', 
            CONCAT(' - ', vp.nama_varian), 
            ''
          ),
          ' (', d.quantity, ')',
          IF(d.catatan IS NOT NULL AND d.catatan <> '', 
            CONCAT('<br><small><i>Note: ', d.catatan, '</i></small>'), 
            ''
          )
        ) ORDER BY d.id_detail ASC SEPARATOR '<br>'
      ) AS pesanan
    FROM transaksi t
    JOIN detail_transaksi d ON t.id_transaksi = d.id_transaksi
    JOIN produk p ON d.id_produk = p.id_produk
    LEFT JOIN varian_produk vp ON d.id_varian = vp.id_varian
    WHERE LOWER(TRIM(t.status_pesanan)) = 'selesai'
    GROUP BY t.id_transaksi
    ORDER BY t.waktu_pemesanan DESC
    LIMIT $start, $limit
  ";
  $result = mysqli_query($conn, $sql);

  if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
      echo "<tr>";
      echo "<td>".htmlspecialchars($row['waktu_pemesanan'])."</td>";
      echo "<td>".htmlspecialchars($row['nomor_meja'])."</td>";
      echo "<td>".htmlspecialchars($row['nama_pemesan'])."</td>";
      echo "<td>".$row['pesanan']."</td>";
      echo "<td>Rp ".number_format($row['total'], 0, ',', '.')."</td>";
      echo "<td><span class='status-box selesai'>Selesai</span></td>";
      echo "</tr>";
    }
  } else {
    echo "<tr><td colspan='6' class='no-order'>Tidak ada riwayat pesanan</td></tr>";
  }
}

// ======================
// ✅ Mode AJAX → hanya isi <tbody>
// ======================
if (isset($_GET['ajax'])) {
  renderRiwayatTable($conn, $start, $limit);
  exit;
}
?>
 
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dapur - Riwayat Pesanan</title>
    <!-- CSS -->
    <link rel="stylesheet" href="../css/dapur.css" />
    <link rel="stylesheet" href="../css/logout.css" />

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
  </head>

  <body class="page-riwayatpesanan">
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
        <li><a href="menu_kosong.php"><i data-feather="x-circle"></i> Menu Tidak Tersedia</a></li>
        <li><a href="kelola_menu.php"><i data-feather="settings"></i> Kelola Menu</a></li>
        <li><a href="riwayat_pesanan.php" class="active"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
        <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
      </ul>
    </div>

    <div class="main">
      <div class="content">
        <table class="pesanan-table">
          <thead>
            <tr>
              <th>Waktu Selesai</th>
              <th>No. Meja</th>
              <th>Nama</th>
              <th>Pesanan</th>
              <th>Total</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="riwayat-table-body">
            <?php renderRiwayatTable($conn, $start, $limit); ?>
          </tbody>
        </table>

        <!-- ✅ Pagination -->
        <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>">← Prev</a>
        <?php endif; ?>

        <?php
          $range = 2; // jumlah nomor di kiri & kanan halaman aktif
          $startPage = max(1, $page - $range);
          $endPage = min($totalPages, $page + $range);

          if ($startPage > 1) {
              echo '<a href="?page=1">1</a>';
              if ($startPage > 2) echo '<span class="dots">...</span>';
          }

          for ($i = $startPage; $i <= $endPage; $i++) {
              echo '<a href="?page='.$i.'" class="'.($i == $page ? 'active' : '').'">'.$i.'</a>';
          }

          if ($endPage < $totalPages) {
              if ($endPage < $totalPages - 1) echo '<span class="dots">...</span>';
              echo '<a href="?page='.$totalPages.'">'.$totalPages.'</a>';
          }
        ?>

        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>">Next →</a>
        <?php endif; ?>
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

    <!-- Feather Icons -->
    <script>feather.replace();</script>

    <!-- Javascript -->
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
