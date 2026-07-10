<?php 
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'kasir') {
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
            } elseif ($metode === 'qris') {
                $metode_text = "Qris";
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
            echo "<td>";
            if (!empty($row['bukti_file'])) {
                $bukti_path = "../uploads/" . htmlspecialchars($row['bukti_file']);
                echo "<img src='{$bukti_path}' 
                             alt='Bukti Pembayaran' 
                             class='bukti-img' 
                             style='width:60px; height:auto; border-radius:5px; border:1px solid #ccc; cursor:pointer; display:block; margin:0 auto;'>";
            } else {
                if ($stat_bayar !== 'sudah bayar') {
                    echo "<span style='color:gray; display:block; margin-bottom:8px;'>Bayar dikasir</span>";
                }
            }

            // Tampilkan tombol struk hanya jika metode adalah cash/belum diisi (bayar di kasir) DAN statusnya sudah bayar
            $metode_clean = strtolower(trim($row['metode'] ?? ''));
            if (($metode_clean === 'cash' || empty($metode_clean)) && $stat_bayar === 'sudah bayar') {
                echo "<button type='button' class='btn-cetak-struk' data-id='{$id}'><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"feather feather-printer\"><polyline points=\"6 9 6 2 18 2 18 9\"></polyline><path d=\"M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2\"></path><rect x=\"6\" y=\"14\" width=\"12\" height=\"8\"></rect></svg>Cetak Struk</button>";
            }
            echo "</td>";

            echo "</tr>";
        }
    } else {
        echo '<tr class="empty-row"><td colspan="9">
                <div class="empty-state-table">
                  <img src="https://cdn-icons-png.flaticon.com/512/11329/11329060.png" alt="Empty">
                  <p>Belum ada pesanan masuk saat ini.</p>
                </div>
              </td></tr>';
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
    <script src="../js/feather.min.js"></script>
    <!-- jsPDF for Struk Print -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
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

    <!-- Popup Struk Kasir (Identik dengan Struk Pembayaran Pembeli) -->
    <div id="receiptModal" class="receipt-overlay" style="display:none">
      <div class="receipt-card" id="receiptContent">
        <div class="receipt-header">
          <h2><span class="warkop">Warkop</span> <span class="seraya">Seraya</span></h2>
          <div class="receipt-line"></div>
          <p class="title">Bukti Pembayaran</p>
        </div>
        <div id="receiptDetails"></div>
        <div class="receipt-footer">
          <button id="printReceiptBtn" class="print-btn" style="background:#10b981; color:#fff;">Cetak</button>
          <button class="close-btn" onclick="closeReceipt()">Tutup</button>
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
        <div class="icon-box"><i data-feather="log-out"></i></div>
        <h2>Yakin ingin logout?</h2>
        <p>Sesi Anda akan diakhiri dan Anda diarahkan kembali ke halaman login.</p>
        <div class="modal-actions">
          <button id="confirmLogout">Ya, Logout</button>
          <button id="cancelLogout">Batal</button>
        </div>
      </div>
    </div>

    <!-- 🔔 Box Notifikasi -->
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

    <script>feather.replace();</script>
    <script src="../js/admin.js"></script>
    <script>
      function loadPesanan() {
        fetch("pesanan.php?ajax=1&page=<?= $page ?>")
          .then(res => res.text())
          .then(data => {
            document.getElementById("pesanan-table-body").innerHTML = data;
            feather.replace();
          });
      }
      setInterval(loadPesanan, 5000);

      // Escape HTML (sederhana)
      function escapeHtml(str) {
        if (str === null || str === undefined) return "";
        return String(str)
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      }

      // Format tanggal/waktu kecil
      function formatDate(date) {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, "0");
        const month = String(d.getMonth() + 1).padStart(2, "0");
        const year = d.getFullYear();
        const time = d.toLocaleTimeString("id-ID", {
          hour: "2-digit",
          minute: "2-digit",
        });
        return `${day}/${month}/${year} ${time}`;
      }

      function showReceiptKasir(order) {
        const dateTime = formatDate(order.waktu_bayar || order.waktu_pemesanan || Date.now());
        const detailsBox = document.getElementById("receiptDetails");
        if (!detailsBox) return;

        let itemsHtml = "<table class='receipt-items'>";
        itemsHtml += "<tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr>";

        if (order.items && order.items.length > 0) {
          order.items.forEach((it) => {
            itemsHtml += `
              <tr>
                <td>${escapeHtml(it.nama_produk)}</td>
                <td style='text-align:center;'>${escapeHtml(it.quantity)}</td>
                <td>Rp ${Number(it.subtotal).toLocaleString("id-ID")}</td>
              </tr>`;
          });
        }
        itemsHtml += "</table>";

        const methodText =
          order.metode === "cash"
            ? "Bayar di Kasir"
            : order.metode === "transfer"
            ? "Transfer Bank"
            : "QRIS";

        detailsBox.innerHTML = `
          <div class="receipt-info"><strong>ID:</strong> ${escapeHtml(order.id)}</div>
          <div class="receipt-info"><strong>Nama:</strong> ${escapeHtml(
            order.nama_pemesan
          )}</div>
          <div class="receipt-info"><strong>Meja:</strong> ${escapeHtml(
            order.nomor_meja
          )}</div>
          <div class="receipt-info"><strong>Metode:</strong> ${escapeHtml(
            methodText
          )}</div>
          <div class="receipt-info"><strong>Waktu:</strong> ${escapeHtml(
            dateTime
          )}</div>
          <div class="receipt-info"><strong>Kasir:</strong> ${escapeHtml(
            order.nama_kasir
          )}</div>
          <div class="receipt-line"></div>
          ${itemsHtml}
          <div class="receipt-line"></div>
          <div class="total-line"><strong>Total: Rp ${Number(
            order.total
          ).toLocaleString("id-ID")}</strong></div>
        `;

        const modal = document.getElementById("receiptModal");
        if (modal) modal.style.display = "flex";

        // Print Struk (Window Print)
        const printBtn = document.getElementById("printReceiptBtn");
        if (printBtn) {
          printBtn.onclick = function () {
            window.print();
          };
        }

      }

      function closeReceipt() {
        const modal = document.getElementById("receiptModal");
        if (modal) modal.style.display = "none";
      }

      document.addEventListener("click", function(e) {
        if (e.target.classList.contains("btn-ajax")) {
          const id = e.target.getAttribute("data-id");
          const row = document.querySelector("tr[data-id='" + id + "']");
          fetch("update_pembayaran.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id_transaksi=" + id + "&status=dibayar"
          })
          .then(res => res.json())
          .then(data => {
            if (data.status === 'success') {
              // Langsung refresh tabel agar status terbaru muncul (Sudah Bayar)
              loadPesanan();
            } else {
              alert(data.message);
            }
          });
        }
        
        // ✅ Klik Cetak Struk
        const cetakBtn = e.target.closest(".btn-cetak-struk");
        if (cetakBtn) {
          const id = cetakBtn.getAttribute("data-id");
          fetch("get_detail_pesanan.php?id=" + id)
            .then(res => res.json())
            .then(order => {
              if (order.status === 'error') {
                alert(order.message);
                return;
              }
              showReceiptKasir(order);
            })
            .catch(err => {
              console.error("Error fetching order details:", err);
              alert("Gagal memuat detail pesanan.");
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
