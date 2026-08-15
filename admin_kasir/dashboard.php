<?php 
session_start();
include "../koneksi.php";

// Cek apakah sudah login
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'kasir') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Kasir</title>
    <!-- CSS -->
    <link rel="stylesheet" href="../css/kasir.css" />
    <link rel="stylesheet" href="../css/logout.css" />
    <link rel="stylesheet" href="../css/welcome.css" />

    <!-- Feather Icons -->
    <script src="../js/feather.min.js"></script>
    <!-- Chart.js -->
    <script src="../js/chart.min.js"></script>
  </head>
  <body class="page-dashboard">
    <?php if (isset($_SESSION['show_welcome_anim']) && $_SESSION['show_welcome_anim'] === true): ?>
    <div class="welcome-splash-overlay role-kasir" id="welcomeSplash">
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
          <div class="splash-icon-inner">💰</div>
        </div>
        <h1 class="splash-title">WARKOP <span>SERAYA</span></h1>
        <h2 class="splash-subtitle">Selamat Melayani! Menghubungkan ke sistem kasir Seraya... 💳</h2>
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

    <!-- Sidebar -->
    <div class="sidebar">
      <h1>WARKOP<span> SERAYA</span></h1>
      <h2>KASIR</h2>
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
          <a href="riwayat_pesanan.php"
            ><i data-feather="clock"></i> Riwayat Pesanan</a
          >
        </li>
        <li>
          <a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a>
        </li>
      </ul>
    </div>

    <!-- Main -->
    <div class="main">

      <!-- 📊 STATS CARDS -->
      <div class="stats-grid">
        <div class="stat-card income">
          <div class="stat-icon">
            <i data-feather="dollar-sign"></i>
          </div>
          <div class="stat-info">
            <h4>Total Pendapatan</h4>
            <p id="totalPendapatan">Rp 0</p>
          </div>
        </div>

        <div class="stat-card orders">
          <div class="stat-icon">
            <i data-feather="shopping-cart"></i>
          </div>
          <div class="stat-info">
            <h4>Total Transaksi</h4>
            <p id="totalTransaksi">0 Transaksi</p>
          </div>
        </div>
      </div>

      <!-- 📈 CHART CARD -->
      <div class="card">
        <div class="card-header">
          <h3><i data-feather="trending-up"></i> Statistik Penjualan</h3>
          <select id="salesPeriod">
            <option value="today">Hari Ini</option>
            <option value="week">Minggu Ini</option>
            <option value="month">Bulan Ini</option>
            <option value="custom">Pilih Tanggal</option>
          </select>
        </div>

        <!-- 📅 Input tanggal hanya muncul jika pilih "custom" -->
        <div class="date-range" id="dateRange">
          <input type="date" id="startDate" />
          <span>s/d</span>
          <input type="date" id="endDate" />
          <button id="applyDate" class="btn-apply">Terapkan</button>
        </div>

        <div class="chart-container">
          <canvas id="salesChart"></canvas>
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
     <!-- 🔔 Box Notifikasi Pesanan -->
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

    <!-- Feather Icons -->
    <script>
      feather.replace();
    </script>
    <!-- Javascript -->
    <script src="../js/admin.js"></script>

    <script>
   // NOTIFIKASI PESANAN MASUK
       // ✅ Ambil lastOrderId dari sessionStorage agar tidak reset saat pindah halaman
       let lastOrderId = sessionStorage.getItem('kasir_lastOrderId');
       let isFirstPoll = true;

        // 🔁 Fungsi cek pesanan baru
        async function checkNewOrderKasir() {
          try {
            const res = await fetch("../admin_kasir/cek_pesanan.php");
            const data = await res.json();

            if (data.ada_pesanan) {
              if (isFirstPoll) {
                lastOrderId = String(data.id);
                sessionStorage.setItem('kasir_lastOrderId', lastOrderId);
                isFirstPoll = false;
              } else if (!lastOrderId || Number(data.id) > Number(lastOrderId)) {
                lastOrderId = String(data.id);
                sessionStorage.setItem('kasir_lastOrderId', lastOrderId);
                showNotificationKasir();
              }
            }
          } catch (err) {
            console.error("Error cek pesanan kasir:", err);
          }
        }

        // 🔔 Fungsi tampilkan notifikasi
        function showNotificationKasir() {
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

          // ✅ Pastikan suara diputar
          audio.currentTime = 0;
          const playPromise = audio.play();

          if (playPromise !== undefined) {
            playPromise
              .then(() => console.log("Suara notifikasi diputar"))
              .catch(err => console.warn("Audio gagal diputar:", err));
          }
        }

        // ⏱ Jalankan pengecekan tiap 5 detik
        setInterval(checkNewOrderKasir, 5000);
    </script>
  </body>
</html>
