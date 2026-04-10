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
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Kasir</title>
    <!-- CSS -->
    <link rel="stylesheet" href="../css/kasir.css" />
    <link rel="stylesheet" href="../css/logout.css" />

    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  </head>
  <body class="page-dashboard">
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
      <h1>Selamat Datang, Tim Kasir!</h1>

     <!-- 💰 CARD PENDAPATAN -->
<div class="card">
  <h3>Warkop <span>Seraya</span></h3>

  <select id="salesPeriod">
    <option value="today">Hari Ini</option>
    <option value="week">Minggu Ini</option>
    <option value="month">Bulan Ini</option>
    <option value="custom">Pilih Tanggal</option>
  </select>

  <!-- 📅 Input tanggal hanya muncul jika pilih "custom" -->
  <div class="date-range" id="dateRange" style="display: none;">
    <input type="date" id="startDate" />
    <span>s/d</span>
    <input type="date" id="endDate" />
    <button id="applyDate" class="btn-apply">Terapkan</button>
  </div>

  <div class="chart-container">
    <canvas id="salesChart"></canvas>
  </div>

  <div class="chart-summary">
    <div class="summary-item">
      <h4>Total Pendapatan</h4>
      <p id="totalPendapatan">Rp 0</p>
    </div>
    <div class="summary-item">
      <h4>Total Transaksi</h4>
      <p id="totalTransaksi">0 Transaksi</p>
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
     <!-- 🔔 Box Notifikasi -->
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
    <!-- 🎵 Suara Notifikasi -->
    <audio id="notifAudio" src="notif/notif.mp3" preload="auto"></audio>

    <!-- Feather Icons -->
    <script>
      feather.replace();
    </script>
    <!-- Javascript -->
    <script src="../js/admin.js"></script>

    <script>
   // NOTIFIKASI PESANAN MASUK
       let lastOrderId = null;

        // ✅ Izinkan audio setelah interaksi pertama
        document.addEventListener("click", () => {
          const audio = document.getElementById("notifAudio");
          if (audio) {
            audio.play().then(() => {
              audio.pause(); // cukup untuk aktifkan izin
            }).catch(() => {});
          }
        }, { once: true });

        // 🔁 Fungsi cek pesanan baru
        async function checkNewOrderKasir() {
          try {
            const res = await fetch("../admin_kasir/cek_pesanan.php");
            const data = await res.json();
            console.log("Cek pesanan kasir:", data);

            let initiallyFirst = typeof window.isFirstPoll === 'undefined';
            window.isFirstPoll = false;

            if (data.ada_pesanan) {
              if (initiallyFirst && lastOrderId === null) {
                lastOrderId = data.id;
              } else if (lastOrderId === null || Number(data.id) > Number(lastOrderId)) {
                lastOrderId = data.id;
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

          // ✅ Tampilkan box
          box.style.display = "block";
          setTimeout(() => {
            box.style.display = "none";
          }, 5000);

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
  </body>
</html>
