<?php 
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../login.php");
    exit;
}
include "../koneksi.php"; 

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
  LEFT JOIN (
      SELECT p1.*
      FROM pembayaran p1
      INNER JOIN (
          SELECT id_transaksi, MAX(id_pembayaran) AS last_id
          FROM pembayaran
          GROUP BY id_transaksi
      ) p2 ON p1.id_transaksi = p2.id_transaksi AND p1.id_pembayaran = p2.last_id
  ) pm ON t.id_transaksi = pm.id_transaksi
  WHERE 
    t.status_pesanan IN ('pending','diproses')
    OR (t.status_pesanan = 'selesai' AND (pm.status IS NULL OR pm.status <> 'sudah bayar'))
";
$countRes = mysqli_query($conn, $countSql);
$totalRow = mysqli_fetch_assoc($countRes)['total'];
$totalPages = ceil($totalRow / $limit);

// ======================
// ✅ Fungsi render isi tabel
// ======================
function renderPesananTable($conn, $start, $limit) {
    $sql = "
      SELECT 
        t.id_transaksi, 
        t.nomor_meja, 
        t.nama_pemesan, 
        t.status_pesanan,
        t.total, 
       GROUP_CONCAT(
            CONCAT(
              '<div class=\"item-line\">',
                pr.nama_produk,
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
        ) AS pesanan,

        pm.metode,
        pm.status AS status_pembayaran,
        pm.bukti_file
      FROM transaksi t
      JOIN detail_transaksi d ON t.id_transaksi = d.id_transaksi
      JOIN produk pr ON d.id_produk = pr.id_produk
      LEFT JOIN varian_produk vp ON d.id_varian = vp.id_varian
      LEFT JOIN (
          SELECT p1.*
          FROM pembayaran p1
          INNER JOIN (
              SELECT id_transaksi, MAX(id_pembayaran) AS last_id
              FROM pembayaran
              GROUP BY id_transaksi
          ) p2 ON p1.id_transaksi = p2.id_transaksi AND p1.id_pembayaran = p2.last_id
      ) pm ON t.id_transaksi = pm.id_transaksi
      WHERE 
        t.status_pesanan IN ('pending','diproses')
        OR (t.status_pesanan = 'selesai' AND (pm.status IS NULL OR pm.status <> 'sudah bayar'))
      GROUP BY t.id_transaksi
      ORDER BY t.waktu_pemesanan ASC
      LIMIT $start, $limit
    ";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int)$row['id_transaksi'];
            echo "<tr data-id='{$id}'>";
            echo "<td>".htmlspecialchars($row['nomor_meja'])."</td>";
            echo "<td>".htmlspecialchars($row['nama_pemesan'])."</td>";
            echo "<td>".$row['pesanan']."</td>";

            // ✅ Kolom Total
            echo "<td style='text-align:center; color:balck;'>Rp ".number_format($row['total'], 0, ',', '.')."</td>";

            // Status Pesanan
            $status = strtolower($row['status_pesanan']);
            if ($status === 'diproses') {
                $status_class = 'badge-warning';
            } elseif ($status === 'selesai') {
                $status_class = 'badge-success';
            } else {
                $status_class = 'badge-secondary';
            }
            echo "<td class='status-cell'><span class='badge {$status_class}'>".htmlspecialchars($row['status_pesanan'])."</span></td>";

            // Metode pembayaran
            $metode = $row['metode'];
            if ($metode === null || $metode === '') {
                $metode_text = "Belum ada";
            } elseif ($metode === 'cash') {
                $metode_text = "Cash";
            } elseif ($metode === 'transfer') {
                $metode_text = "Transfer";
            } else {
                $metode_text = htmlspecialchars($metode);
            }
            echo "<td>". $metode_text ."</td>";

            // Status pembayaran
            $stat_bayar = strtolower(trim($row['status_pembayaran'] ?? ''));
            if ($stat_bayar === 'sudah bayar') {
                $status_bayar_text = 'Sudah Bayar';
                $status_bayar_class = 'badge-success';
            } else {
                $status_bayar_text = 'Belum Bayar';
                $status_bayar_class = 'badge-danger';
            }
            echo "<td class='status-bayar'><span class='badge {$status_bayar_class}'>".$status_bayar_text."</span></td>";

            // Tombol aksi
            echo "<td class='aksi-bayar'>";
            if ($stat_bayar !== 'sudah bayar') {
                echo "<button type='button' class='btn btn-success btn-ajax' data-id='{$id}' data-status='dibayar'>Tandai Dibayar</button>";
            } else {
                echo "<span style='color: gray; font-weight: bold;'>Telah Dibayar</span>";
            }
            echo "</td>";

            // ✅ Bukti Pembayaran dengan modal preview
            if (!empty($row['bukti_file'])) {
                $bukti_path = "../uploads/" . htmlspecialchars($row['bukti_file']);
                echo "<td>
                        <img src='{$bukti_path}' 
                             alt='Bukti Pembayaran' 
                             class='bukti-img' 
                             style='width:60px; height:auto; border-radius:5px; border:1px solid #ccc; cursor:pointer;'>
                      </td>";
            } else {
                echo "<td><span style='color:gray;'>Bayar dikasir</span></td>";
            }

            echo "</tr>";
        }
    } else {
        echo '<tr><td colspan="9" class="no-order">Tidak ada pesanan</td></tr>';
    }
}

// ======================
// ✅ Mode AJAX → hanya isi <tbody>
// ======================
if (isset($_GET['ajax'])) {
    renderPesananTable($conn, $start, $limit);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Kasir - Pesanan</title>
    <link rel="stylesheet" href="../css/kasir.css" />
    <link rel="stylesheet" href="../css/logout.css" />
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
      .fade-out { animation: fadeOutRow 3s forwards; }
      @keyframes fadeOutRow {
        from { opacity: 1; }
        to { opacity: 0; height: 0; padding: 0; margin: 0; }
      }
    </style>
  </head>
  <body class="page-pesanan">
  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>KASIR</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="pesanan.php" class="active"><i data-feather="menu"></i> Pesanan</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main">
    <button class="menu-toggle" id="menu-toggle"><i data-feather="menu"></i></button>
    <div class="content">
      <table class="pesanan-table">
        <thead>
          <tr>
            <th>No. Meja</th>
            <th>Nama</th>
            <th>Pesanan</th>
            <th>Total</th> 
            <th>Status Pesanan</th>
            <th>Metode Pembayaran</th>
            <th>Status Pembayaran</th>
            <th>Aksi</th>
            <th>Bukti Pembayaran</th>
          </tr>
        </thead>
        <tbody id="pesanan-table-body">
          <?php renderPesananTable($conn, $start, $limit); ?>
        </tbody>
      </table>

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

    <!-- ✅ Modal preview -->
    <div id="imgModal" class="img-modal">
      <span class="close">&times;</span>
      <img id="modalImage" src="" alt="Preview">
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

    <script>feather.replace();</script>
    <script src="../js/admin.js"></script>
    <script>
      function loadPesanan() {
        fetch("pesanan.php?ajax=1&page=<?= $page ?>")
          .then(res => res.text())
          .then(data => {
            document.getElementById("pesanan-table-body").innerHTML = data;
          });
      }
      setInterval(loadPesanan, 5000);

      document.addEventListener("click", function(e) {
        if (e.target.classList.contains("btn-ajax")) {
          const id = e.target.getAttribute("data-id");
          const row = document.querySelector("tr[data-id='" + id + "']");
          fetch("update_pembayaran.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id_transaksi=" + id + "&status=dibayar"
          })
          .then(res => res.text())
          .then(() => {
            if (row) {
              row.classList.add("fade-out");
              setTimeout(loadPesanan, 3000);
            } else {
              loadPesanan();
            }
          });
        }

        // ✅ Gambar bukti klik untuk modal
        if (e.target.classList.contains("bukti-img")) {
          const modal = document.getElementById("imgModal");
          const modalImg = document.getElementById("modalImage");
          modal.style.display = "flex";
          modalImg.src = e.target.src;
        }
      });

      // ✅ Tutup modal
      document.querySelector(".img-modal .close").addEventListener("click", () => {
        document.getElementById("imgModal").style.display = "none";
      });
      document.getElementById("imgModal").addEventListener("click", e => {
        if (e.target === e.currentTarget) {
          e.currentTarget.style.display = "none";
        }
      });

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

            if (data.ada_pesanan) {
              if (lastOrderId === null) {
                lastOrderId = data.id;
              } else if (data.id !== lastOrderId) {
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
    </script>
  </body>
</html>
