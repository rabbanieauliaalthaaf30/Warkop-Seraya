<?php 
session_start();
include "../koneksi.php"; 

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dapur') {
    header("Location: ../login.php");
    exit;
}
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
  <script src="../js/feather.min.js"></script>
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
      <li><a href="inventory.php"><i data-feather="package"></i> Stok Barang</a></li>
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

  <!-- JavaScript -->
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
  display: none; align-items: center; gap: 14px;
  position: fixed; top: 20px; right: 20px;
  background: rgba(28, 28, 30, 0.95);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-left: 4px solid #ffc107; padding: 16px 20px; border-radius: 12px;
  color: #fff; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  z-index: 9999; font-family: system-ui, -apple-system, sans-serif; width: 320px;
  animation: slideInNotif 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; transition: all 0.3s ease;
}
.notif-toast.hide { animation: fadeOutNotif 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
#stokToastMenuKosong { top: 90px; border-left-color: #ef4444; }
#stokToastMenuKosong.warning { border-left-color: #f59e0b; }
.sidebar-stok-badge {
  display:inline-flex;align-items:center;justify-content:center;
  background:#ef4444;color:#fff;font-size:10px;font-weight:800;
  min-width:18px;height:18px;border-radius:9px;padding:0 5px;margin-left:auto;line-height:1;
  animation:pulse-badge 1.5s infinite;
}
@keyframes pulse-badge{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}
</style>

<!-- Toast stok -->
<div id="stokToastMenuKosong" class="notif-toast" onclick="window.location='inventory.php'" style="cursor:pointer">
  <div id="stokToastMenuKosongIcon" style="background:rgba(239,68,68,.15);color:#ef4444;padding:10px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
      <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
  </div>
  <div style="flex-grow:1;display:flex;flex-direction:column;gap:2px;">
    <div id="stokToastMenuKosongLabel" style="font-size:11px;font-weight:700;color:#ef4444;letter-spacing:.8px;text-transform:uppercase;">Stok Habis</div>
    <div id="stokToastMenuKosongMsg" style="font-size:13px;color:#e5e5ea;font-weight:500;line-height:1.4;"></div>
  </div>
  <div style="color:#94a3b8;font-size:10px;flex-shrink:0;">Tap →</div>
</div>

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

// ════ 🔔 Notifikasi Stok ════
let _stokHashMK = null;
function _showStokMK(d) {
  const t=document.getElementById('stokToastMenuKosong'),l=document.getElementById('stokToastMenuKosongLabel'),m=document.getElementById('stokToastMenuKosongMsg'),ic=document.getElementById('stokToastMenuKosongIcon');
  if(!t)return;
  if(d.habis>0){t.classList.remove('warning');t.style.borderLeftColor='#ef4444';ic.style.background='rgba(239,68,68,.15)';ic.style.color='#ef4444';l.style.color='#ef4444';l.textContent=d.habis+' Stok HABIS';}
  else{t.classList.add('warning');t.style.borderLeftColor='#f59e0b';ic.style.background='rgba(245,158,11,.15)';ic.style.color='#f59e0b';l.style.color='#f59e0b';l.textContent=d.menipis+' Stok Menipis';}
  m.textContent=d.items.slice(0,2).map(i=>i.nama).join(', ')+(d.total>2?' +'+(d.total-2)+' lainnya':'')+' — Tap untuk lihat';
  t.classList.remove('hide');t.style.display='flex';
  setTimeout(()=>{t.classList.add('hide');setTimeout(()=>{t.style.display='none';},400);},6000);
}
function _badgeMK(n){const a=document.querySelector('.sidebar a[href="inventory.php"]');if(!a)return;let b=a.querySelector('.sidebar-stok-badge');if(n>0){if(!b){b=document.createElement('span');b.className='sidebar-stok-badge';a.appendChild(b);}b.textContent=n>99?'99+':n;}else if(b)b.remove();}
async function _checkStokMK(){try{const r=await fetch('../get_stok_alert.php'),d=await r.json(),h=JSON.stringify(d.items.map(i=>i.nama+i.stok));_badgeMK(d.total);if(d.ada_alert){if(_stokHashMK===null){_stokHashMK=h;}else if(h!==_stokHashMK){_stokHashMK=h;_showStokMK(d);}}else _stokHashMK=h;}catch(e){console.warn(e);}}
_checkStokMK();setInterval(_checkStokMK,60000);
</script>
</body>
</html>
