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
    <script src="../js/feather.min.js"></script>
    <!-- Chart.js -->
    <script src="../js/chart.min.js"></script>
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
          <a href="inventory.php"><i data-feather="package"></i> Inventory</a>
        </li>
        <li>
          <a href="laporan_stok.php"><i data-feather="bar-chart-2"></i> Laporan Stok</a>
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

    <!-- 🔔 Notifikasi Stok Menipis / Habis (Manager Dashboard) -->
    <style>
    @keyframes slideInNotif {
      from { transform: translateX(120%); opacity: 0; }
      to   { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOutNotif {
      from { transform: translateX(0); opacity: 1; }
      to   { transform: translateX(120%); opacity: 0; }
    }
    .stok-notif-toast {
      display: none; align-items: center; gap: 14px;
      position: fixed; top: 20px; right: 20px;
      background: rgba(28,28,30,.95);
      backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,.08);
      border-left: 4px solid #ef4444;
      padding: 16px 20px; border-radius: 12px; color: #fff;
      box-shadow: 0 10px 30px rgba(0,0,0,.3);
      z-index: 9999; width: 340px; cursor: pointer;
      animation: slideInNotif .4s cubic-bezier(.16,1,.3,1) forwards;
    }
    .stok-notif-toast.warning { border-left-color: #f59e0b; }
    .stok-notif-toast.hide { animation: fadeOutNotif .4s cubic-bezier(.16,1,.3,1) forwards; }
    .sidebar-stok-badge {
      display: inline-flex; align-items: center; justify-content: center;
      background: #ef4444; color: #fff;
      font-size: 10px; font-weight: 800;
      min-width: 18px; height: 18px; border-radius: 9px;
      padding: 0 5px; margin-left: auto; line-height: 1;
      animation: pulse-badge 1.5s infinite;
    }
    @keyframes pulse-badge {
      0%,100% { transform: scale(1); }
      50%      { transform: scale(1.15); }
    }
    </style>

    <div id="stokToastMgrDash" class="stok-notif-toast" onclick="window.location='inventory.php'">
      <div id="stokToastMgrDashIcon" style="background:rgba(239,68,68,.15);color:#ef4444;padding:10px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div style="flex-grow:1;display:flex;flex-direction:column;gap:3px;">
        <div id="stokToastMgrDashLabel" style="font-size:11px;font-weight:700;color:#ef4444;letter-spacing:.8px;text-transform:uppercase;">Stok Habis</div>
        <div id="stokToastMgrDashMsg" style="font-size:13px;color:#e5e5ea;font-weight:500;line-height:1.4;"></div>
      </div>
      <div style="color:#94a3b8;font-size:10px;flex-shrink:0;">Tap →</div>
    </div>

    <script>
    let _stokHashMgrDash   = null;

    function _showStokMgrDash(data) {
      const toast = document.getElementById('stokToastMgrDash');
      const label = document.getElementById('stokToastMgrDashLabel');
      const msg   = document.getElementById('stokToastMgrDashMsg');
      const icon  = document.getElementById('stokToastMgrDashIcon');
      if (!toast) return;

      if (data.habis > 0) {
        toast.classList.remove('warning');
        toast.style.borderLeftColor = '#ef4444';
        icon.style.background = 'rgba(239,68,68,.15)'; icon.style.color = '#ef4444';
        label.style.color = '#ef4444';
        label.textContent = data.habis + ' Stok HABIS';
      } else {
        toast.classList.add('warning');
        toast.style.borderLeftColor = '#f59e0b';
        icon.style.background = 'rgba(245,158,11,.15)'; icon.style.color = '#f59e0b';
        label.style.color = '#f59e0b';
        label.textContent = data.menipis + ' Stok Menipis';
      }
      const contoh = data.items.slice(0, 2).map(i => i.nama).join(', ');
      msg.textContent = contoh + (data.total > 2 ? ' +' + (data.total - 2) + ' lainnya' : '') + ' — Tap untuk lihat';

      toast.classList.remove('hide');
      toast.style.display = 'flex';
      setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => { toast.style.display = 'none'; }, 400);
      }, 7000);
    }

    function _updateBadgeMgrDash(total) {
      const link = document.querySelector('.sidebar a[href="inventory.php"]');
      if (!link) return;
      let badge = link.querySelector('.sidebar-stok-badge');
      if (total > 0) {
        if (!badge) { badge = document.createElement('span'); badge.className = 'sidebar-stok-badge'; link.appendChild(badge); }
        badge.textContent = total > 99 ? '99+' : total;
      } else { if (badge) badge.remove(); }
    }

    async function _checkStokMgrDash() {
      try {
        const res  = await fetch('../get_stok_alert.php');
        const data = await res.json();
        const hash = JSON.stringify(data.items.map(i => i.nama + i.stok));
        _updateBadgeMgrDash(data.total);
        if (data.ada_alert) {
          if (_stokHashMgrDash === null) {
            _stokHashMgrDash = hash;
          } else if (hash !== _stokHashMgrDash) {
            _stokHashMgrDash = hash;
            _showStokMgrDash(data);
          }
        } else {
          _stokHashMgrDash = hash;
        }
      } catch(e) { console.warn('stok alert mgr dash:', e); }
    }
    _checkStokMgrDash();
    setInterval(_checkStokMgrDash, 60000);
    </script>
  </body>
</html>
