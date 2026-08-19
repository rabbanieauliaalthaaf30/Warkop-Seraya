<?php 
session_start();
include "../koneksi.php";

// Cek apakah sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dapur') {
    header("Location: ../login.php");
    exit;
}

// Tambahkan query helper agar data dapat diakses langsung oleh PHP saat reload halaman pertama kali
function getKitchenStats($conn) {
    // Pesanan Pending (Hanya hitung yang memiliki detail item pesanan)
    $qPending = "SELECT COUNT(DISTINCT t.id_transaksi) as total 
                 FROM transaksi t 
                 INNER JOIN detail_transaksi d ON t.id_transaksi = d.id_transaksi
                 WHERE t.status_pesanan = 'pending'";
    $rPending = mysqli_query($conn, $qPending);
    $pending = $rPending ? mysqli_fetch_assoc($rPending)['total'] : 0;
    
    // Pesanan Diproses
    $qDiproses = "SELECT COUNT(DISTINCT t.id_transaksi) as total 
                  FROM transaksi t 
                  INNER JOIN detail_transaksi d ON t.id_transaksi = d.id_transaksi
                  WHERE t.status_pesanan = 'diproses'";
    $rDiproses = mysqli_query($conn, $qDiproses);
    $diproses = $rDiproses ? mysqli_fetch_assoc($rDiproses)['total'] : 0;
    
    // Selesai Hari Ini
    $qSelesai = "SELECT COUNT(DISTINCT t.id_transaksi) as total 
                 FROM transaksi t 
                 INNER JOIN detail_transaksi d ON t.id_transaksi = d.id_transaksi
                 WHERE t.status_pesanan = 'selesai' AND DATE(t.waktu_pemesanan) = CURDATE()";
    $rSelesai = mysqli_query($conn, $qSelesai);
    $selesai = $rSelesai ? mysqli_fetch_assoc($rSelesai)['total'] : 0;
    
    // Total Menu Tersedia
    $qMenu = "SELECT COUNT(*) as total FROM produk WHERE status = 'tersedia'";
    $rMenu = mysqli_query($conn, $qMenu);
    $menuRow = $rMenu ? mysqli_fetch_assoc($rMenu) : null;
    $totalMenu = $menuRow ? (int)$menuRow['total'] : 0;
    
    // Fallback: jika kolom status tidak ada, hitung semua produk
    if (!$rMenu) {
        $qMenu2 = "SELECT COUNT(*) as total FROM produk";
        $rMenu2 = mysqli_query($conn, $qMenu2);
        $totalMenu = $rMenu2 ? (int)mysqli_fetch_assoc($rMenu2)['total'] : 0;
    }
    
    return [
        'pending'   => (int)$pending,
        'diproses'  => (int)$diproses,
        'selesai'   => (int)$selesai,
        'total_menu'=> (int)$totalMenu,
    ];
}

// Jika ada request get_stats dari javascript
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json');
    echo json_encode(getKitchenStats($conn));
    exit;
}

// Ambil stats awal untuk render HTML pertama kali agar tidak berkedip angka 0
$stats = getKitchenStats($conn);
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
    <link rel="stylesheet" href="../css/welcome.css" />

    <!-- Feather Icons -->
    <script src="../js/feather.min.js"></script>
  </head>
  <body class="page-dashboard">
    <?php if (isset($_SESSION['show_welcome_anim']) && $_SESSION['show_welcome_anim'] === true): ?>
    <div class="welcome-splash-overlay role-dapur" id="welcomeSplash">
      <div class="splash-bg-glow"></div>
      <div class="splash-particles">
        <span class="particle p1"></span>
        <span class="particle p2"></span>
        <span class="particle p3"></span>
        <span class="particle p4"></span>
        <span class="particle p5"></span>
      </div>
      <div class="splash-card">
        <div class="splash-icon-container">
          <div class="splash-icon-pulse"></div>
          <div class="splash-icon-inner">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chef-hat">
              <path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/>
              <path d="M6 17h12"/>
            </svg>
          </div>
        </div>
        <h1 class="splash-title">WARKOP <span>SERAYA</span></h1>
        <h2 class="splash-subtitle">Selamat Bekerja, Tim Dapur! Mempersiapkan pesanan lezat pelanggan... 🔥</h2>
        <div class="splash-progress">
          <div class="splash-progress-bar"></div>
        </div>
      </div>
    </div>
    <?php unset($_SESSION['show_welcome_anim']); endif; ?>

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
        <li><a href="inventory.php"><i data-feather="package"></i> Stok Barang</a></li>
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
      <!-- 🍳 Welcome Header -->
      <div class="dapur-welcome-header">
        <div class="dapur-welcome-icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chef-hat">
            <path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/>
            <path d="M6 17h12"/>
          </svg>
        </div>
        <div class="dapur-welcome-text">
          <h1>Selamat Datang, <span>Tim Dapur!</span></h1>
          <p>Pantau pesanan masuk dan kelola aktivitas dapur dari sini.</p>
        </div>
      </div>

      <!-- 📊 STATS CARDS -->
      <div class="dapur-stats-grid">
        <div class="dapur-stat-card pending">
          <div class="dapur-stat-icon">
            <i data-feather="clock"></i>
          </div>
          <div class="dapur-stat-info">
            <h4>Pesanan Pending</h4>
            <p id="statPending">0</p>
            <span>Menunggu diproses</span>
          </div>
        </div>

        <div class="dapur-stat-card diproses">
          <div class="dapur-stat-icon">
            <i data-feather="zap"></i>
          </div>
          <div class="dapur-stat-info">
            <h4>Sedang Diproses</h4>
            <p id="statDiproses">0</p>
            <span>Sedang dimasak</span>
          </div>
        </div>

        <div class="dapur-stat-card selesai">
          <div class="dapur-stat-icon">
            <i data-feather="check-circle"></i>
          </div>
          <div class="dapur-stat-info">
            <h4>Selesai Hari Ini</h4>
            <p id="statSelesai">0</p>
            <span>Pesanan tuntas</span>
          </div>
        </div>

        <div class="dapur-stat-card menu">
          <div class="dapur-stat-icon">
            <i data-feather="book-open"></i>
          </div>
          <div class="dapur-stat-info">
            <h4>Total Menu</h4>
            <p id="statMenu">0</p>
            <span>Menu tersedia</span>
          </div>
        </div>
      </div>

      <!-- ⚡ Quick Actions -->
      <div class="dapur-quick-actions">
        <h3><i data-feather="grid"></i> Akses Cepat</h3>
        <div class="dapur-action-grid">
          <a href="pesanan.php" class="dapur-action-btn">
            <i data-feather="list"></i>
            <span>Lihat Pesanan</span>
          </a>
          <a href="menu_kosong.php" class="dapur-action-btn">
            <i data-feather="x-circle"></i>
            <span>Menu Tidak Tersedia</span>
          </a>
          <a href="kelola_menu.php" class="dapur-action-btn">
            <i data-feather="settings"></i>
            <span>Kelola Menu</span>
          </a>
          <a href="riwayat_pesanan.php" class="dapur-action-btn">
            <i data-feather="archive"></i>
            <span>Riwayat Pesanan</span>
          </a>
          <a href="inventory.php" class="dapur-action-btn">
            <i data-feather="package"></i>
            <span>Inventory</span>
          </a>
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
    <script>
      feather.replace();
    </script>
    <!-- Javascript -->
    <script src="../js/admin.js"></script>
    <!-- 🔔 Notifikasi Pesanan & Stok -->
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
/* Stok toast: stacked di bawah toast pesanan */
#stokToast { top: 90px; border-left-color: #ef4444; }
#stokToast.warning { border-left-color: #f59e0b; }

/* Badge stok di sidebar */
.sidebar-stok-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #ef4444;
  color: #fff;
  font-size: 10px;
  font-weight: 800;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  padding: 0 5px;
  margin-left: auto;
  line-height: 1;
  animation: pulse-badge 1.5s infinite;
}
@keyframes pulse-badge {
  0%,100% { transform: scale(1); }
  50%      { transform: scale(1.15); }
}
</style>

<!-- Toast pesanan baru -->
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

<!-- Toast stok menipis / habis -->
<div id="stokToast" class="notif-toast" onclick="window.location='inventory.php'" style="cursor:pointer">
  <div id="stokToastIcon" style="background: rgba(239,68,68,.15); color: #ef4444; padding: 10px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
      <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
  </div>
  <div style="flex-grow: 1; display: flex; flex-direction: column; gap: 2px;">
    <div id="stokToastLabel" style="font-size: 11px; font-weight: 700; color: #ef4444; letter-spacing: 0.8px; text-transform: uppercase;">Stok Habis</div>
    <div id="stokToastMsg" style="font-size: 13px; color: #e5e5ea; font-weight: 500; line-height: 1.4;"></div>
  </div>
  <div style="color:#94a3b8; font-size:10px; flex-shrink:0;">Tap →</div>
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
        // Poll pertama: simpan ID tanpa bunyi (mencegah bug saat pindah halaman)
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

<script>
// ════════════════════════════════════════
// 🔔 Notifikasi Stok Menipis / Habis
// ════════════════════════════════════════
let lastStokAlertHash = null;

function showStokToast(data) {
  const toast  = document.getElementById('stokToast');
  const label  = document.getElementById('stokToastLabel');
  const msg    = document.getElementById('stokToastMsg');
  const icon   = document.getElementById('stokToastIcon');
  if (!toast) return;

  const adaHabis   = data.habis > 0;
  const adaMenipis = data.menipis > 0;

  // Pilih warna & label berdasarkan kondisi terburuk
  if (adaHabis) {
    toast.classList.remove('warning');
    toast.style.borderLeftColor = '#ef4444';
    icon.style.background = 'rgba(239,68,68,.15)';
    icon.style.color = '#ef4444';
    label.style.color = '#ef4444';
    label.textContent = data.habis + ' Stok HABIS';
  } else {
    toast.classList.add('warning');
    toast.style.borderLeftColor = '#f59e0b';
    icon.style.background = 'rgba(245,158,11,.15)';
    icon.style.color = '#f59e0b';
    label.style.color = '#f59e0b';
    label.textContent = data.menipis + ' Stok Menipis';
  }

  // Ringkas nama barang pertama
  const contoh = data.items.slice(0, 2).map(i => i.nama).join(', ');
  const sisa   = data.total > 2 ? ' +' + (data.total - 2) + ' lainnya' : '';
  msg.textContent = contoh + sisa + ' — Tap untuk lihat';

  // Tampilkan
  toast.classList.remove('hide');
  toast.style.display = 'flex';
  setTimeout(() => {
    toast.classList.add('hide');
    setTimeout(() => { toast.style.display = 'none'; }, 400);
  }, 6000);
}

function updateInventoryBadge(total) {
  const link = document.querySelector('.sidebar a[href="inventory.php"]');
  if (!link) return;
  let badge = link.querySelector('.sidebar-stok-badge');
  if (total > 0) {
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'sidebar-stok-badge';
      link.appendChild(badge);
    }
    badge.textContent = total > 99 ? '99+' : total;
  } else {
    if (badge) badge.remove();
  }
}

async function checkStokAlert() {
  try {
    const res  = await fetch('../get_stok_alert.php');
    const data = await res.json();

    // Buat hash sederhana untuk detect perubahan
    const hash = JSON.stringify(data.items.map(i => i.nama + i.stok));

    updateInventoryBadge(data.total);

    if (data.ada_alert) {
      if (lastStokAlertHash === null) {
        // Poll pertama: simpan hash saja, badge sudah ditampilkan, jangan popup toast
        lastStokAlertHash = hash;
      } else if (hash !== lastStokAlertHash) {
        // Ada perubahan stok sejak poll terakhir → tampilkan toast
        lastStokAlertHash = hash;
        showStokToast(data);
      }
    } else {
      lastStokAlertHash = hash;
    }

  } catch (e) {
    console.warn('Stok alert check error:', e);
  }
}

// Cek langsung saat halaman dimuat (untuk badge sidebar)
checkStokAlert();
// Polling tiap 60 detik — stok tidak berubah secepat pesanan
setInterval(checkStokAlert, 60000);
</script>

<!-- 📊 Stats Dapur Script -->
<script>
// Animasi angka naik
function animateCount(elId, target) {
  const el = document.getElementById(elId);
  if (!el) return;
  const current = parseInt(el.textContent) || 0;
  if (current === target) return;
  const step = target > current ? 1 : -1;
  let val = current;
  const interval = setInterval(() => {
    val += step;
    el.textContent = val;
    if (val === target) clearInterval(interval);
  }, 50);
}

// Update UI stats dengan animasi dan highlight
function updateStatsUI(data) {
  animateCount('statPending',  data.pending);
  animateCount('statDiproses', data.diproses);
  animateCount('statSelesai',  data.selesai);
  animateCount('statMenu',     data.total_menu);

  // Highlight card jika ada pesanan pending
  const pendingCard = document.querySelector('.dapur-stat-card.pending');
  if (pendingCard) {
    pendingCard.classList.toggle('has-alert', data.pending > 0);
  }
}

// Fetch stats dari server secara real-time
async function loadStatsDapur() {
  try {
    const res = await fetch('dashboard.php?action=get_stats');
    const data = await res.json();
    updateStatsUI(data);
  } catch (err) {
    console.error('❌ Gagal load stats dapur:', err);
  }
}

// 🚀 Jalankan animasi menghitung dari 0 secara INSTAN saat halaman dimuat menggunakan data awal dari PHP (Zero delay!)
updateStatsUI({
  pending: <?= (int)$stats['pending'] ?>,
  diproses: <?= (int)$stats['diproses'] ?>,
  selesai: <?= (int)$stats['selesai'] ?>,
  total_menu: <?= (int)$stats['total_menu'] ?>
});

// 🔁 Lakukan polling setiap 10 detik untuk data live
setInterval(loadStatsDapur, 10000);
</script>
  </body>
</html>
