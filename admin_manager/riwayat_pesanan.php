<?php 
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit;
}
include "../koneksi.php"; 

// ======================
// ✅ Pagination setup
// ======================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// Hitung total data
$countSql = "
  SELECT COUNT(DISTINCT t.id_transaksi) as total
  FROM transaksi t
  JOIN pembayaran pby ON t.id_transaksi = pby.id_transaksi
  WHERE LOWER(TRIM(t.status_pesanan)) = 'selesai'
    AND LOWER(TRIM(pby.status)) = 'sudah bayar'
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
        t.id_admin,
        a.username AS nama_kasir,
        pby.metode,
        pby.status AS status_bayar,
        pby.waktu_bayar,
        pby.bukti_file,
        GROUP_CONCAT(
          CONCAT(
            pr.nama_produk,
            IF(vp.nama_varian IS NOT NULL AND vp.nama_varian <> '', 
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
      JOIN produk pr ON d.id_produk = pr.id_produk
      LEFT JOIN varian_produk vp ON d.id_varian = vp.id_varian
      JOIN pembayaran pby ON t.id_transaksi = pby.id_transaksi
      LEFT JOIN admin a ON t.id_admin = a.id_admin
      WHERE LOWER(TRIM(t.status_pesanan)) = 'selesai'
        AND LOWER(TRIM(pby.status)) = 'sudah bayar'
      GROUP BY t.id_transaksi, t.nomor_meja, t.nama_pemesan, t.status_pesanan, t.waktu_pemesanan, t.total, pby.metode, pby.status, pby.waktu_bayar, pby.bukti_file, a.username
      ORDER BY pby.waktu_bayar DESC
      LIMIT $start, $limit
    ";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int)$row['id_transaksi'];
            echo "<tr data-id='{$id}'>";
            echo "<td>".htmlspecialchars($row['waktu_bayar'])."</td>";
            echo "<td>".htmlspecialchars($row['nomor_meja'])."</td>";
            echo "<td>".htmlspecialchars($row['nama_pemesan'])."</td>";
            echo "<td>".$row['pesanan']."</td>";
            echo "<td style='text-align:left;'>Rp ".number_format($row['total'], 0, ',', '.')."</td>";
            echo "<td><span class='badge-kasir'>".htmlspecialchars($row['nama_kasir'] ?? 'System')."</span></td>";

            // ✅ Bukti Pembayaran (gambar mini)
            echo "<td>";
            if (!empty($row['bukti_file'])) {
                $bukti_path = "../uploads/" . htmlspecialchars($row['bukti_file']);
                echo "<img src='{$bukti_path}' 
                             alt='Bukti Pembayaran' 
                             class='bukti-img' 
                             style='width:60px; height:auto; border-radius:5px; border:1px solid #ccc; cursor:pointer; display:block; margin:0 auto;'>";
            }

            // Tampilkan tombol struk hanya jika metode adalah cash/belum diisi (bayar di kasir)
            $metode_clean = strtolower(trim($row['metode'] ?? ''));
            if ($metode_clean === 'cash' || empty($metode_clean)) {
                echo "<button type='button' class='btn-cetak-struk' data-id='{$id}'><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" class=\"feather feather-printer\"><polyline points=\"6 9 6 2 18 2 18 9\"></polyline><path d=\"M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2\"></path><rect x=\"6\" y=\"14\" width=\"12\" height=\"8\"></rect></svg>Cetak Struk</button>";
            }
            echo "</td>";

            // ✅ Status
            echo "<td>
                    <span class='status-box selesai'>Selesai</span><br>
                    <span class='status-box bayar'>".htmlspecialchars($row['metode'])."</span>
                  </td>";

            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='7' class='no-order'>Tidak ada riwayat pesanan</td></tr>";
    }
}

// ======================
// ✅ AJAX untuk rekap laporan (detail + metode pembayaran + custom tanggal)
// ======================
if (isset($_GET['rekap'])) {
    $periode = $_GET['rekap'];
    $where = "";

    // jika periode custom, harap kirimkan 'dari' & 'sampai' berupa YYYY-MM-DD
    if ($periode == 'harian') {
        $where = "DATE(p.waktu_bayar) = CURDATE()";
    } elseif ($periode == 'mingguan') {
        $where = "YEARWEEK(p.waktu_bayar, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($periode == 'bulanan') {
        $where = "YEAR(p.waktu_bayar) = YEAR(CURDATE()) AND MONTH(p.waktu_bayar) = MONTH(CURDATE())";
    } elseif ($periode == 'custom') {
        // ambil parameter tanggal dari GET, sanitize
        $dari_raw = isset($_GET['dari']) ? $_GET['dari'] : '';
        $sampai_raw = isset($_GET['sampai']) ? $_GET['sampai'] : '';
        $dari = mysqli_real_escape_string($conn, $dari_raw);
        $sampai = mysqli_real_escape_string($conn, $sampai_raw);

        // validasi format YYYY-MM-DD sederhana (regex)
        $valid_date = function($d) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        };

        if ($valid_date($dari) && $valid_date($sampai)) {
            // pastikan dari <= sampai
            if (strtotime($dari) > strtotime($sampai)) {
                // swap jika terbalik
                $tmp = $dari; $dari = $sampai; $sampai = $tmp;
            }
            $where = "DATE(p.waktu_bayar) BETWEEN '$dari' AND '$sampai'";
        } else {
            // jika format tidak valid, set where ke kondisi false agar tidak menghasilkan data
            $where = "1=0";
        }
    } else {
        // default: jangan menampilkan apapun (aman)
        $where = "1=0";
    }

    $sql = "
      SELECT 
        t.id_transaksi, 
        t.nomor_meja,
        t.nama_pemesan,
        t.total,
        p.metode,
        p.waktu_bayar,
        a.username AS nama_kasir,
        GROUP_CONCAT(
          CONCAT(pr.nama_produk,
            IF(vp.nama_varian IS NOT NULL AND vp.nama_varian <> '', 
               CONCAT(' - ', vp.nama_varian), ''),' (', d.quantity, ')'
          ) SEPARATOR ', '
        ) AS pesanan
      FROM transaksi t
      JOIN detail_transaksi d ON t.id_transaksi = d.id_transaksi
      JOIN produk pr ON d.id_produk = pr.id_produk
      LEFT JOIN varian_produk vp ON d.id_varian = vp.id_varian
      JOIN pembayaran p ON t.id_transaksi = p.id_transaksi
      LEFT JOIN admin a ON t.id_admin = a.id_admin
      WHERE p.status = 'sudah bayar' AND $where
      GROUP BY t.id_transaksi, t.nomor_meja, t.nama_pemesan, t.total, p.metode, p.waktu_bayar, a.username
      ORDER BY p.waktu_bayar DESC
    ";
    $res = mysqli_query($conn, $sql);

    $sumSql = "
        SELECT COUNT(DISTINCT t.id_transaksi) AS total_transaksi,
               SUM(t.total) AS total_pendapatan
        FROM transaksi t
        JOIN pembayaran p ON t.id_transaksi = p.id_transaksi
        WHERE p.status = 'sudah bayar' AND $where
    ";
    $sumRes = mysqli_query($conn, $sumSql);
    $summary = mysqli_fetch_assoc($sumRes);

    // tampilkan header kecil info periode jika custom (supaya PDF/preview lebih jelas)
    $periodeInfo = htmlspecialchars(strtoupper($periode));
    if ($periode === 'custom' && isset($dari) && isset($sampai) && $where !== "1=0") {
        $periodeInfo = htmlspecialchars($dari) . " → " . htmlspecialchars($sampai);
    }

    echo "<div id='rekapContainer' class='rekap-list' style='padding: 10px; background: #fff;'>";
    // tambahkan header ringkas periode di tampilan modal
    echo "<div style='text-align:center; margin-bottom:20px; font-family:\"Poppins\", sans-serif;'>
            <span style='background: #f1f5f9; padding: 5px 15px; border-radius: 20px; font-size: 13px; color: #64748b; font-weight: 600;'>
              Periode: {$periodeInfo}
            </span>
          </div>";

    if ($res && mysqli_num_rows($res) > 0) {
        $no = 1;
        while ($r = mysqli_fetch_assoc($res)) {
            echo "<div class='rekap-item' style='margin-bottom: 15px; border-radius: 12px; border: 1px solid #f1f5f9; padding: 15px;'>
                    <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;'>
                      <span style='font-weight: 700; color: #1e293b; font-size: 14px;'>{$no}. Meja {$r['nomor_meja']} - {$r['nama_pemesan']}</span>
                      <span style='font-size: 11px; background: #fdf2f2; color: #dc143c; padding: 2px 8px; border-radius: 6px; font-weight: 600;'>" . htmlspecialchars(strtoupper($r['metode'])) . "</span>
                    </div>
                    <p style='margin: 5px 0; color: #475569; font-size: 13px;'>".htmlspecialchars($r['pesanan'])."</p>
                    <div style='display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:10px; border-top: 1px dashed #e2e8f0;'>
                      <span style='font-size: 12px; color: #94a3b8;'>Waktu: " . date('d/m/Y H:i', strtotime($r['waktu_bayar'])) . "</span>
                      <span style='font-weight: 700; color: #dc143c; font-size: 15px;'>Rp ".number_format($r['total'],0,',','.')."</span>
                    </div>
                  </div>";
            $no++;
        }
    } else {
        echo "<div style='text-align:center; padding: 40px 0;'>
                <p style='color: #94a3b8; font-size: 14px;'>Tidak ada data transaksi pada periode ini.</p>
              </div>";
    }
    
    echo "<div class='rekap-summary' style='margin-top: 25px; background: #1e293b; color: #fff; border-radius: 15px; padding: 20px;'>
            <div style='display:flex; justify-content:space-between; align-items:flex-end;'>
              <div>
                <span style='color: #94a3b8; font-size: 12px; display:block; margin-bottom:4px;'>Total Transaksi</span>
                <span style='font-weight: 700; font-size: 18px;'>".($summary['total_transaksi'] ?? 0)."</span>
              </div>
              <div style='text-align:right;'>
                <span style='color: #94a3b8; font-size: 12px; display:block; margin-bottom:4px;'>Total Pendapatan</span>
                <span style='font-weight: 800; font-size: 18px; color: #f8fafc;'>Rp ".number_format(($summary['total_pendapatan'] ?? 0), 0, ',', '.')."</span>
              </div>
            </div>
          </div>";
    echo "</div>";
    exit;
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
  <title>Admin Manager - Riwayat Pesanan</title>
  <link rel="stylesheet" href="../css/kasir.css" />
  <link rel="stylesheet" href="../css/logout.css" />
  <script src="https://unpkg.com/feather-icons"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body class="page-riwayatpesanan">
  <button class="menu-toggle" id="menu-toggle"><i data-feather="menu"></i></button>

  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>MANAGER</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="riwayat_pesanan.php" class="active"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="kelola_akun.php"><i data-feather="users"></i> Kelola Akun</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main">
    <div class="content">
      <div class="rekap-filter">
        <label for="periode">Laporan Penjualan</label>
        <select id="periode">
          <option value="harian">Harian</option>
          <option value="mingguan">Mingguan</option>
          <option value="bulanan">Bulanan</option>
          <option value="custom">Pilih Tanggal</option>
        </select>

        <!-- input tanggal custom (disembunyikan sampai dipilih) -->
        <div id="tanggalRange" class="tanggal-range">
          <input type="date" id="dari" />
          <span class="separator">-</span>
          <input type="date" id="sampai" />
        </div>
        <button id="tampilkanRekap">Tampilkan</button>
      </div>

      <table class="pesanan-table">
        <thead>
          <tr>
            <th>Waktu Bayar</th>
            <th>No. Meja</th>
            <th>Nama</th>
            <th>Pesanan</th>
            <th>Total</th>
            <th>Kasir</th>
            <th>Bukti Pembayaran</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="riwayat-table-body">
          <?php renderRiwayatTable($conn, $start, $limit); ?>
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

  <!-- ✅ Modal Rekap -->
  <div id="rekapModal">
    <div class="modal-content">
      <h3>Laporan Penjualan</h3>
      <div id="rekapContent"></div>
      <button id="btnCetakPDF" class="btn-cetak">Unduh PDF</button>
      <button id="closeRekapModal">Tutup</button>
    </div>
  </div>

  <!-- ✅ Modal preview gambar -->
  <div id="imgModal" class="img-modal">
    <span class="close">&times;</span>
    <img id="modalImage" src="" alt="Preview">
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

  <script>feather.replace();</script>
  <script src="../js/admin.js"></script>

  <script>
    // =========================
    // Modal Gambar (tetap ada)
    // =========================
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

    document.addEventListener("click", e => {
      if (e.target.classList.contains("bukti-img")) {
        const modal = document.getElementById("imgModal");
        const modalImg = document.getElementById("modalImage");
        modal.style.display = "flex";
        modalImg.src = e.target.src;
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
    });
    document.querySelector(".img-modal .close").addEventListener("click", () => {
      document.getElementById("imgModal").style.display = "none";
    });
    document.getElementById("imgModal").addEventListener("click", e => {
      if (e.target === e.currentTarget) e.currentTarget.style.display = "none";
    });

    // =========================
    // Tampilkan input tanggal saat "custom" dipilih
    // =========================
    document.getElementById("periode").addEventListener("change", function() {
      const tanggalRange = document.getElementById("tanggalRange");

      if (this.value === "custom") {
        tanggalRange.classList.add("show");
      } else {
        tanggalRange.classList.remove("show");
      }
    });

    // =========================
    // Rekap Handler (kirimkan juga tanggal jika custom)
    // =========================
    document.getElementById("tampilkanRekap").addEventListener("click", () => {
      const periode = document.getElementById("periode").value;
      let url = "riwayat_pesanan.php?rekap=" + periode;

      if (periode === "custom") {
        const dari = document.getElementById("dari").value;
        const sampai = document.getElementById("sampai").value;
        if (!dari || !sampai) {
          showNotification("Pilih tanggal awal dan akhir terlebih dahulu!", "warning");
          return;
        }
        // validasi sederhana format YYYY-MM-DD
        const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
       if (!dateRegex.test(dari) || !dateRegex.test(sampai)) {
          showNotification("Format tanggal tidak valid. Gunakan format YYYY-MM-DD.", "error");
          return;
        }
        url += "&dari=" + encodeURIComponent(dari) + "&sampai=" + encodeURIComponent(sampai);
        // agar PDF nama file lebih rapi, set periode kustom yang mencakup range
        document.getElementById("btnCetakPDF").dataset.periode = `custom_${dari}_to_${sampai}`;
        // juga set label readable
        document.getElementById("btnCetakPDF").dataset.pretty = `${dari} sampai ${sampai}`;
      } else {
        document.getElementById("btnCetakPDF").dataset.periode = periode;
        document.getElementById("btnCetakPDF").dataset.pretty = periode;
      }

      fetch(url)
        .then(res => res.text())
        .then(data => {
          document.getElementById("rekapContent").innerHTML = data;
          document.getElementById("rekapModal").style.display = "flex";
        });
    });

    // =========================
    // Tutup Modal
    // =========================
    document.getElementById("closeRekapModal").addEventListener("click", () => {
      document.getElementById("rekapModal").style.display = "none";
    });
    document.getElementById("rekapModal").addEventListener("click", e => {
      if (e.target === e.currentTarget) {
        e.currentTarget.style.display = "none";
      }
    });

   // =========================
   // CETAK PDF (versi rapi + tidak terpotong + tampilan modern)
   // tetap menggunakan html2pdf yang sudah di-include
   // =========================
document.getElementById("btnCetakPDF").addEventListener("click", () => {
  const element = document.getElementById("rekapContainer");
  if (!element) {
    alert("Tidak ada konten rekap untuk dicetak.");
    return;
  }

  // ambil periode dari dataset (di-set ketika rekap dipanggil)
  const periodeRaw = document.getElementById("btnCetakPDF").dataset.periode || "rekap";
  // readable label (jika tersedia)
  const pretty = document.getElementById("btnCetakPDF").dataset.pretty || periodeRaw;

  const tanggal = new Date().toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "long",
    year: "numeric",
  });

  // ✅ Opsi PDF (Final Fix untuk Terpotong)
  const opt = {
    margin: [10, 0, 10, 0], // [top, left, bottom, right] dalam mm
    filename: `Laporan_Penjualan_${periodeRaw}_${tanggal.replace(/\s+/g, "_")}.pdf`,
    image: { type: "jpeg", quality: 1 },
    html2canvas: {
      scale: 2, 
      useCORS: true,
      x: 0,
      y: 0,
      scrollX: 0,
      scrollY: 0,
      windowWidth: 800, 
    },
    jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
    pagebreak: { mode: ["avoid-all", "css", "legacy"] },
  };

  // ✅ Header modern (sertakan pretty periode bila ada)
  const header = `
    <div style="
      text-align:center;
      margin-bottom:25px;
      padding-bottom:15px;
      border-bottom:2px solid #dc143c;
    ">
      <h2 style="margin:0; color:#1e293b; font-family:'Poppins',sans-serif; letter-spacing: 1px; font-size: 22px;">
        LAPORAN PENJUALAN <span style="color:#dc143c;">WARKOP SERAYA</span>
      </h2>
      <div style="display:flex; justify-content:center; gap:15px; margin-top:8px;">
        <span style="font-weight:600; font-size:12px; color:#64748b; background:#f1f5f9; padding:2px 10px; border-radius:12px;">
          Periode: ${String(pretty).toUpperCase()}
        </span>
        <span style="font-size:12px; color:#64748b; background:#f1f5f9; padding:2px 10px; border-radius:12px;">
          Dicetak: ${tanggal}
        </span>
      </div>
    </div>
  `;

  // ✅ Footer modern
  const footer = `
    <div style="
      text-align:center;
      font-size:10px;
      color:#94a3b8;
      margin-top:30px;
      border-top:1px dashed #e2e8f0;
      padding-top:10px;
      letter-spacing: 1px;
    ">
      WARKOP SERAYA — DIGITAL SALES REPORT
    </div>
  `;

  // ✅ Clone isi rekap dan gabungkan
  const clone = element.cloneNode(true);
  
  // Sembunyikan elemen periode yang ada di dalam klon agar tidak duplikat dengan header PDF
  const duplicatePeriode = clone.querySelector('div[style*="text-align:center"]');
  if (duplicatePeriode) duplicatePeriode.style.display = 'none';

  const wrapper = document.createElement("div");
  wrapper.style.fontFamily = "'Poppins', Arial, sans-serif";
  wrapper.style.fontSize = "12px";
  wrapper.style.lineHeight = "1.6";
  wrapper.style.color = "#1e293b";
  wrapper.style.padding = "40px";
  wrapper.style.background = "#fff";
  wrapper.style.width = "794px";
  wrapper.style.boxSizing = "border-box";

  // Sisipkan header
  const headerDiv = document.createElement("div");
  headerDiv.innerHTML = header;
  wrapper.appendChild(headerDiv);

  // Sisipkan konten rekap (clone)
  wrapper.appendChild(clone);

  // Sisipkan footer
  const footerDiv = document.createElement("div");
  footerDiv.innerHTML = footer;
  wrapper.appendChild(footerDiv);

  // ✅ Tambahkan gaya CSS anti-terpotong ke elemen rekap-item
  const rekapItems = wrapper.querySelectorAll(".rekap-item");
  rekapItems.forEach((el) => {
    el.style.pageBreakInside = "avoid";
    el.style.background = "#fff";
    el.style.borderRadius = "12px";
    el.style.border = "1px solid #f1f5f9";
    el.style.padding = "15px";
    el.style.marginBottom = "15px";
    el.style.width = "100%";
    el.style.boxSizing = "border-box";
  });

  const summary = wrapper.querySelector(".rekap-summary");
  if (summary) {
    summary.style.pageBreakInside = "avoid";
    summary.style.background = "#1e293b";
    summary.style.color = "#fff";
    summary.style.borderRadius = "15px";
    summary.style.padding = "20px";
    summary.style.marginTop = "25px";
  }

  // ✅ Cetak PDF
  html2pdf().set(opt).from(wrapper).save();
});

// =========================
// Fungsi Notifikasi Modern
// =========================
function showNotification(message, type = "error") {
  const notif = document.createElement("div");
  notif.className = `notification ${type}`;
  notif.textContent = message;
  document.body.appendChild(notif);

  // Animasi muncul
  setTimeout(() => notif.classList.add("show"), 10);

  // Hilang otomatis
  setTimeout(() => {
    notif.classList.remove("show");
    setTimeout(() => notif.remove(), 400);
  }, 3000);
}


  </script>
</body>
</html>
