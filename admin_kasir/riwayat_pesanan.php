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
      WHERE LOWER(TRIM(t.status_pesanan)) = 'selesai'
        AND LOWER(TRIM(pby.status)) = 'sudah bayar'
      GROUP BY t.id_transaksi, t.nomor_meja, t.nama_pemesan, t.status_pesanan, t.waktu_pemesanan, t.total, pby.metode, pby.status, pby.waktu_bayar, pby.bukti_file
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

            // ✅ Bukti Pembayaran (gambar mini)
            if (!empty($row['bukti_file'])) {
                $bukti_path = "../uploads/" . htmlspecialchars($row['bukti_file']);
                echo "<td>
                        <img src='{$bukti_path}' 
                             alt='Bukti Pembayaran' 
                             class='bukti-img' 
                             style='width:60px; height:auto; border-radius:5px; border:1px solid #ccc; cursor:pointer;'>
                      </td>";
            } else {
                echo "<td><span style='color:gray;'>Bayar di kasir</span></td>";
            }

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
      WHERE p.status = 'sudah bayar' AND $where
      GROUP BY t.id_transaksi, t.nomor_meja, t.nama_pemesan, t.total, p.metode, p.waktu_bayar
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

    echo "<div id='rekapContainer' class='rekap-list'>";
    // tambahkan header ringkas periode di tampilan modal
    echo "<div style='text-align:center; margin-bottom:10px;'>
            <strong>{$periodeInfo}</strong>
          </div>";

    if ($res && mysqli_num_rows($res) > 0) {
        $no = 1;
        while ($r = mysqli_fetch_assoc($res)) {
            echo "<div class='rekap-item'>
                    <p><b>{$no}. Meja {$r['nomor_meja']} - {$r['nama_pemesan']} (" . htmlspecialchars($r['metode']) . ")</b></p>
                    <p style='margin-left:10px;'>".htmlspecialchars($r['pesanan'])."</p>
                    <p style='margin-left:10px;'><b>Total:</b> Rp ".number_format($r['total'],0,',','.')."</p>
                    <p style='margin-left:10px; color:gray; font-size:12px;'>".htmlspecialchars($r['waktu_bayar'])."</p>
                    <hr>
                  </div>";
            $no++;
        }
    } else {
        echo "<p style='text-align:center;'>Tidak ada data transaksi</p>";
    }
    echo "<div class='rekap-summary'>
            <p><b>Total Transaksi:</b> ".($summary['total_transaksi'] ?? 0)."</p>
            <p><b>Total Pendapatan:</b> Rp ".number_format(($summary['total_pendapatan'] ?? 0), 0, ',', '.')."</p>
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
  <title>Admin Kasir - Riwayat Pesanan</title>
  <link rel="stylesheet" href="../css/kasir.css" />
  <link rel="stylesheet" href="../css/logout.css" />
  <script src="https://unpkg.com/feather-icons"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body class="page-riwayatpesanan">
  <button class="menu-toggle" id="menu-toggle"><i data-feather="menu"></i></button>

  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>KASIR</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="pesanan.php"><i data-feather="menu"></i> Pesanan</a></li>
      <li><a href="riwayat_pesanan.php" class="active"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
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

  <!-- Logout Modal -->
  <div id="logoutModal" class="modal">
    <div class="modal-content">
      <div class="icon-box">🚪</div>
      <h2>Yakin ingin logout?</h2>
      <p>Sesi Anda akan diakhiri dan Anda diarahkan kembali ke halaman login.</p>
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
    // =========================
    // Modal Gambar (tetap ada)
    // =========================
    document.addEventListener("click", e => {
      if (e.target.classList.contains("bukti-img")) {
        const modal = document.getElementById("imgModal");
        const modalImg = document.getElementById("modalImage");
        modal.style.display = "flex";
        modalImg.src = e.target.src;
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

  // ✅ Opsi PDF baru
  const opt = {
    margin: 0.5,
    filename: `Laporan_Penjualan_${periodeRaw}_${tanggal.replace(/\s+/g, "_")}.pdf`,
    image: { type: "jpeg", quality: 1 },
    html2canvas: {
      scale: 2,
      useCORS: true,
      scrollX: 0,
      scrollY: 0,
      windowWidth: document.body.scrollWidth,
    },
    jsPDF: { unit: "in", format: "a4", orientation: "portrait" },
    pagebreak: { mode: ["avoid-all", "css", "legacy"] }, // penting biar ga terpotong
  };

  // ✅ Header modern (sertakan pretty periode bila ada)
  const header = `
    <div style="
      text-align:center;
      margin-bottom:20px;
      padding-bottom:10px;
      border-bottom:3px solid #e91e63;
    ">
      <h2 style="margin:0; color:#e91e63; font-family:'Poppins',sans-serif;">
        LAPORAN PENJUALAN WARKOP SERAYA
      </h2>
      <p style="margin:2px 0; font-weight:500; font-size:14px;">
        Periode: <b>${String(pretty).toUpperCase()}</b>
      </p>
      <p style="margin:2px 0; font-size:12px; color:#555;">
        Dicetak: ${tanggal}
      </p>
    </div>
  `;

  // ✅ Footer modern
  const footer = `
    <div style="
      text-align:center;
      font-size:12px;
      color:#777;
      margin-top:20px;
      border-top:1px dashed #ccc;
      padding-top:5px;
    ">
      <em>Warkop Seraya — Laporan Penjualan</em>
    </div>
  `;

  // ✅ Clone isi rekap dan gabungkan
  const clone = element.cloneNode(true);
  const wrapper = document.createElement("div");
  wrapper.style.fontFamily = "Poppins, Arial, sans-serif";
  wrapper.style.fontSize = "13px";
  wrapper.style.lineHeight = "1.5";
  wrapper.style.color = "#333";
  wrapper.style.padding = "10px";
  wrapper.style.background = "#fff";

  // Sisipkan header
  const headerDiv = document.createElement("div");
  headerDiv.innerHTML = header;
  wrapper.appendChild(headerDiv);

  // Sisipkan konten rekap (clone)
  wrapper.appendChild(clone);

  // Sisipkan footer — JANGAN pakai innerHTML+= (akan destroy clone di atas)
  const footerDiv = document.createElement("div");
  footerDiv.innerHTML = footer;
  wrapper.appendChild(footerDiv);

  // ✅ Tambahkan gaya CSS anti-terpotong ke elemen rekap-item
  const rekapItems = wrapper.querySelectorAll(".rekap-item, .rekap-summary");
  rekapItems.forEach((el) => {
    el.style.pageBreakInside = "avoid";
    el.style.background = "#fff";
    el.style.borderRadius = "10px";
    el.style.borderLeft = "4px solid #e91e63";
    el.style.padding = "10px 15px";
    el.style.marginBottom = "12px";
    el.style.boxShadow = "0 1px 4px rgba(0,0,0,0.1)";
  });

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
  </script>
</body>
</html>
