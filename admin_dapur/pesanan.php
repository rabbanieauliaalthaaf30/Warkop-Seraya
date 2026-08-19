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
  WHERE t.status_pesanan IN ('pending','diproses')
";
$countRes = mysqli_query($conn, $countSql);
$totalRow = mysqli_fetch_assoc($countRes)['total'];
$totalPages = ceil($totalRow / $limit);

// =============================
// 🔹 Fungsi render isi tabel
// =============================
function renderPesananTable($conn, $start, $limit) {
  $sql = "
   SELECT 
    t.id_transaksi, 
    t.nomor_meja, 
    t.nama_pemesan, 
    t.status_pesanan,
    GROUP_CONCAT(
        CONCAT(
          '<div class=\"item-line\">',
            p.nama_produk,
            IF(vp.nama_varian IS NOT NULL AND vp.nama_varian <> '', 
              CONCAT(' - ', vp.nama_varian), 
              ''
            ),
            ' (', d.quantity, ')',
            IF(d.catatan IS NOT NULL AND d.catatan <> '', 
              CONCAT('<br><small><i>Note: ', d.catatan, '</i></small>'), 
              ''
            ),
          '</div>'
        ) ORDER BY d.id_detail ASC SEPARATOR ''
    ) AS pesanan
            
    FROM transaksi t
    JOIN detail_transaksi d ON t.id_transaksi = d.id_transaksi
    JOIN produk p ON d.id_produk = p.id_produk
    LEFT JOIN varian_produk vp ON d.id_varian = vp.id_varian
    WHERE t.status_pesanan IN ('pending','diproses')
    GROUP BY t.id_transaksi
    ORDER BY t.waktu_pemesanan ASC
    LIMIT $start, $limit
  ";
  $result = mysqli_query($conn, $sql);

  if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
      echo "<tr data-id='".$row['id_transaksi']."'>";
      echo "<td>".htmlspecialchars($row['nomor_meja'])."</td>";
      echo "<td>".htmlspecialchars($row['nama_pemesan'])."</td>";
      echo "<td>".$row['pesanan']."</td>";
      echo "<td>";

      // 🔹 Button AJAX + CSS class
      if ($row['status_pesanan'] === 'pending') {
        echo "<button class='status-btn status-pending update-status' data-id='".$row['id_transaksi']."' data-status='diproses'>Pending</button>";
      } elseif ($row['status_pesanan'] === 'diproses') {
        echo "<button class='status-btn status-diproses update-status' data-id='".$row['id_transaksi']."' data-status='selesai'>Diproses</button>";
      } else {
        echo "<span class='status-btn status-selesai'>".htmlspecialchars($row['status_pesanan'])."</span>";
      }

      echo "</td>";
      echo "</tr>";
    }
  } else {
    echo '<tr class="empty-row"><td colspan="4">
            <div class="empty-state-table">
              <img src="https://cdn-icons-png.flaticon.com/512/11329/11329060.png" alt="Empty">
              <p>Belum ada pesanan masuk saat ini.</p>
            </div>
          </td></tr>';
  }
}

// =============================
// 🔹 Mode AJAX (auto reload)
// =============================
if (isset($_GET['ajax'])) {
  renderPesananTable($conn, $start, $limit);
  exit;
}
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dapur - Pesanan</title>
  <link rel="stylesheet" href="../css/dapur.css" />
  <link rel="stylesheet" href="../css/logout.css" />
  <script src="../js/feather.min.js"></script>
  <style>
    .fade-out { animation: fadeOutRow 3s forwards; }
    @keyframes fadeOutRow {
      from { opacity: 1; }
      to { opacity: 0; height: 0; padding: 0; margin: 0; }
    }
  </style>
</head>

<body class="page-pesanan">
  <button class="menu-toggle" id="menu-toggle">
    <i data-feather="menu"></i>
  </button>

  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>DAPUR</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="pesanan.php" class="active"><i data-feather="menu"></i> Pesanan</a></li>
      <li><a href="menu_kosong.php"><i data-feather="x-circle"></i> Menu Tidak Tersedia</a></li>
      <li><a href="kelola_menu.php"><i data-feather="settings"></i> Kelola Menu</a></li>
      <li><a href="inventory.php"><i data-feather="package"></i> Stok Barang</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main">
    <div class="content">
      <table class="pesanan-table">
        <thead>
          <tr>
            <th>No. Meja</th>
            <th>Nama</th>
            <th>Pesanan</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="pesanan-table-body">
          <?php renderPesananTable($conn, $start, $limit); ?>
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

  <!-- ✅ Popup Konfirmasi Update Status -->
  <div id="statusConfirmModal" style="
    display:none; position:fixed; inset:0; z-index:10000;
    background:rgba(0,0,0,0.45); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
  ">
    <div style="
      background:#fff; border-radius:20px; padding:32px 28px; width:340px;
      box-shadow:0 20px 60px rgba(0,0,0,0.2); text-align:center;
      animation: popIn 0.3s cubic-bezier(0.16,1,0.3,1);
    ">
      <div id="statusConfirmIcon" style="
        width:64px; height:64px; border-radius:16px; margin:0 auto 16px;
        display:flex; align-items:center; justify-content:center; font-size:28px;
      "></div>
      <h3 style="margin:0 0 8px; font-size:18px; color:#1e293b;">Ubah Status Pesanan?</h3>
      <p id="statusConfirmText" style="margin:0 0 24px; font-size:14px; color:#64748b; line-height:1.5;"></p>
      <div style="display:flex; gap:12px;">
        <button id="statusConfirmCancel" style="
          flex:1; padding:12px; border:1.5px solid #e2e8f0; background:#fff;
          border-radius:12px; font-size:14px; font-weight:600; color:#64748b;
          cursor:pointer; transition:all 0.2s;
        " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
          Batal
        </button>
        <button id="statusConfirmOk" style="
          flex:1; padding:12px; border:none; border-radius:12px;
          font-size:14px; font-weight:700; color:#fff; cursor:pointer;
          transition:all 0.2s;
        ">
          Ya, Ubah
        </button>
      </div>
    </div>
  </div>
  <style>
    @keyframes popIn {
      from { transform: scale(0.88); opacity: 0; }
      to   { transform: scale(1);    opacity: 1; }
    }
  </style>
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
#stokToastPesanan { top: 90px; border-left-color: #ef4444; }
#stokToastPesanan.warning { border-left-color: #f59e0b; }
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
<div id="stokToastPesanan" class="notif-toast" onclick="window.location='inventory.php'" style="cursor:pointer">
  <div id="stokToastPesananIcon" style="background:rgba(239,68,68,.15);color:#ef4444;padding:10px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
      <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
  </div>
  <div style="flex-grow: 1; display: flex; flex-direction: column; gap: 2px;">
    <div id="stokToastPesananLabel" style="font-size:11px;font-weight:700;color:#ef4444;letter-spacing:.8px;text-transform:uppercase;">Stok Habis</div>
    <div id="stokToastPesananMsg" style="font-size:13px;color:#e5e5ea;font-weight:500;line-height:1.4;"></div>
  </div>
  <div style="color:#94a3b8;font-size:10px;flex-shrink:0;">Tap →</div>
</div>
  <script>feather.replace();</script>
  <script src="../js/admin.js"></script>

  <!-- 🔹 AJAX Update Status -->
  <script>
    function loadPesanan() {
      fetch("pesanan.php?ajax=1&page=<?= $page ?>")
        .then(res => res.text())
        .then(data => {
          document.getElementById("pesanan-table-body").innerHTML = data;
        });
    }

    // ── Konfigurasi tampilan per status ──
    const statusConfig = {
      diproses: {
        iconSvg: `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>`,
        iconBg: 'rgba(59,130,246,0.12)',
        btnColor: '#3b82f6',
        label: 'Diproses',
        text: 'Pesanan ini akan ditandai sedang <strong>Diproses</strong>. Lanjutkan?'
      },
      selesai: {
        iconSvg: `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
        iconBg: 'rgba(16,185,129,0.12)',
        btnColor: '#10b981',
        label: 'Selesai',
        text: 'Pesanan ini akan ditandai <strong>Selesai</strong> dan dihapus dari daftar. Lanjutkan?'
      }
    };

    let pendingId     = null;
    let pendingStatus = null;
    let pendingTarget = null;

    const confirmModal  = document.getElementById('statusConfirmModal');
    const confirmOkBtn  = document.getElementById('statusConfirmOk');
    const confirmCancel = document.getElementById('statusConfirmCancel');

    // Tutup modal
    function closeConfirmModal() {
      confirmModal.style.display = 'none';
      pendingId = pendingStatus = pendingTarget = null;
    }
    confirmCancel.addEventListener('click', closeConfirmModal);
    confirmModal.addEventListener('click', e => {
      if (e.target === confirmModal) closeConfirmModal();
    });

    // Eksekusi setelah konfirmasi
    confirmOkBtn.addEventListener('click', function() {
      if (!pendingId || !pendingStatus) return;

      // Simpan dulu sebelum di-reset oleh closeConfirmModal
      const id     = pendingId;
      const status = pendingStatus;
      const target = pendingTarget;

      closeConfirmModal();

      fetch("update_pesanan.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + id + "&status=" + status
      })
      .then(res => res.text())
      .then(() => {
        if (typeof showAdminToast === 'function') {
          showAdminToast("Status berhasil diperbarui!", "success");
        }
        if (status === "selesai" && target) {
          target.outerHTML = "<span class='status-btn status-selesai'>Selesai</span>";
          const row = document.querySelector("tr[data-id='" + id + "']");
          if (row) {
            row.classList.add("fade-out");
            setTimeout(loadPesanan, 3000);
          }
        } else {
          loadPesanan();
        }
      })
      .catch(() => {
        if (typeof showAdminToast === 'function') {
          showAdminToast("Gagal mengupdate status.", "error");
        }
      });
    });

    // Intercept klik tombol status — tampilkan popup dulu
    document.addEventListener("click", function(e) {
      if (e.target.classList.contains("update-status")) {
        const id     = e.target.getAttribute("data-id");
        const status = e.target.getAttribute("data-status");
        const cfg    = statusConfig[status];
        if (!cfg) return;

        pendingId     = id;
        pendingStatus = status;
        pendingTarget = e.target;

        document.getElementById('statusConfirmIcon').innerHTML    = cfg.iconSvg;
        document.getElementById('statusConfirmIcon').style.background = cfg.iconBg;
        document.getElementById('statusConfirmText').innerHTML    = cfg.text;
        confirmOkBtn.style.background = cfg.btnColor;

        confirmModal.style.display = 'flex';
      }
    });

    setInterval(loadPesanan, 5000);

  // NOTIFIKASI PESANAN
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

  // ════════════════════════════════════════
  // 🔔 Notifikasi Stok Menipis / Habis
  // ════════════════════════════════════════
  let _stokHashPesanan = null;

  function _showStokPesanan(data) {
    const toast = document.getElementById('stokToastPesanan');
    const label = document.getElementById('stokToastPesananLabel');
    const msg   = document.getElementById('stokToastPesananMsg');
    const icon  = document.getElementById('stokToastPesananIcon');
    if (!toast) return;
    if (data.habis > 0) {
      toast.classList.remove('warning');
      toast.style.borderLeftColor = '#ef4444';
      icon.style.background = 'rgba(239,68,68,.15)'; icon.style.color = '#ef4444';
      label.style.color = '#ef4444'; label.textContent = data.habis + ' Stok HABIS';
    } else {
      toast.classList.add('warning');
      toast.style.borderLeftColor = '#f59e0b';
      icon.style.background = 'rgba(245,158,11,.15)'; icon.style.color = '#f59e0b';
      label.style.color = '#f59e0b'; label.textContent = data.menipis + ' Stok Menipis';
    }
    const contoh = data.items.slice(0,2).map(i=>i.nama).join(', ');
    msg.textContent = contoh + (data.total > 2 ? ' +' + (data.total-2) + ' lainnya' : '') + ' — Tap untuk lihat';
    toast.classList.remove('hide'); toast.style.display = 'flex';
    setTimeout(() => {
      toast.classList.add('hide');
      setTimeout(() => { toast.style.display = 'none'; }, 400);
    }, 6000);
  }

  function _updateBadgePesanan(total) {
    const link = document.querySelector('.sidebar a[href="inventory.php"]');
    if (!link) return;
    let badge = link.querySelector('.sidebar-stok-badge');
    if (total > 0) {
      if (!badge) { badge = document.createElement('span'); badge.className = 'sidebar-stok-badge'; link.appendChild(badge); }
      badge.textContent = total > 99 ? '99+' : total;
    } else { if (badge) badge.remove(); }
  }

  async function _checkStokPesanan() {
    try {
      const res  = await fetch('../get_stok_alert.php');
      const data = await res.json();
      const hash = JSON.stringify(data.items.map(i => i.nama + i.stok));
      _updateBadgePesanan(data.total);
      if (data.ada_alert) {
        if (_stokHashPesanan === null) { _stokHashPesanan = hash; }
        else if (hash !== _stokHashPesanan) { _stokHashPesanan = hash; _showStokPesanan(data); }
      } else { _stokHashPesanan = hash; }
    } catch(e) { console.warn('stok pesanan:', e); }
  }

  _checkStokPesanan();
  setInterval(_checkStokPesanan, 60000);
  </script>
</body>
</html>
