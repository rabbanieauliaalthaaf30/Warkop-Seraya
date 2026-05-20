<?php 
session_start();
include "../koneksi.php";

// Cek apakah sudah login sebagai manager
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Manager</title>
    <!-- CSS -->
    <link rel="stylesheet" href="../css/kasir.css" />
    <link rel="stylesheet" href="../css/logout.css" />
    <link rel="stylesheet" href="../css/welcome.css" />

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  </head>
  <body class="page-dashboard">
    <?php if (isset($_SESSION['show_welcome_anim']) && $_SESSION['show_welcome_anim'] === true): ?>
    <div class="welcome-splash-overlay role-manager" id="welcomeSplash">
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
          <div class="splash-icon-inner">📈</div>
        </div>
        <h1 class="splash-title">WARKOP <span>SERAYA</span></h1>
        <h2 class="splash-subtitle">Selamat Datang, Manager! Membuka panel kendali Warkop Seraya... 👑</h2>
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
      <h2>MANAGER</h2>
      <ul>
        <li>
          <a href="dashboard.php" class="active"
            ><i data-feather="home"></i> Beranda</a
          >
        </li>
        <li>
          <a href="riwayat_pesanan.php"
            ><i data-feather="clock"></i> Riwayat Pesanan</a
          >
        </li>
        <li>
          <a href="kelola_akun.php"><i data-feather="users"></i> Kelola Akun</a>
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
    <!-- Feather Icons -->
    <script>
      feather.replace();
    </script>
    <!-- Javascript -->
    <script src="../js/admin.js"></script>
  </body>
</html>
