<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit;
}

// ── Periode dari GET ──
$periode   = $_GET['periode'] ?? 'bulanan';
$today     = date('Y-m-d');
$tgl_input = $today; // fallback untuk semua periode

switch ($periode) {
    case 'harian':
        $tgl_input = $_GET['tgl'] ?? $today;
        $tgl_input = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_input) ? $tgl_input : $today;
        $date_from = $tgl_input . ' 00:00:00';
        $date_to   = $tgl_input . ' 23:59:59';
        $label_periode = 'Harian — ' . date('d/m/Y', strtotime($tgl_input));
        break;
    case 'mingguan':
        $week_input = $_GET['week'] ?? date('Y-\WW');
        if (preg_match('/^(\d{4})-W(\d{2})$/', $week_input, $m)) {
            $dt = new DateTime();
            $dt->setISODate((int)$m[1], (int)$m[2]);
            $date_from = $dt->format('Y-m-d') . ' 00:00:00';
            $dt->modify('+6 days');
            $date_to = $dt->format('Y-m-d') . ' 23:59:59';
            $label_periode = 'Mingguan — ' . date('d/m/Y', strtotime($date_from)) . ' s/d ' . date('d/m/Y', strtotime($date_to));
        } else {
            $date_from = date('Y-m-d', strtotime('monday this week')) . ' 00:00:00';
            $date_to   = date('Y-m-d', strtotime('sunday this week')) . ' 23:59:59';
            $label_periode = 'Mingguan — ' . date('d/m/Y', strtotime($date_from)) . ' s/d ' . date('d/m/Y', strtotime($date_to));
        }
        break;

    case 'custom':
        $from_input = $_GET['from'] ?? $today;
        $to_input   = $_GET['to']   ?? $today;
        $from_input = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_input) ? $from_input : $today;
        $to_input   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_input)   ? $to_input   : $today;
        if ($from_input > $to_input) [$from_input, $to_input] = [$to_input, $from_input];
        $date_from = $from_input . ' 00:00:00';
        $date_to   = $to_input   . ' 23:59:59';
        $label_periode = 'Periode — ' . date('d/m/Y', strtotime($from_input)) . ' s/d ' . date('d/m/Y', strtotime($to_input));
        break;

    default: // bulanan
        $bulan_input = $_GET['bulan'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $bulan_input)) $bulan_input = date('Y-m');
        $date_from = $bulan_input . '-01 00:00:00';
        $date_to   = date('Y-m-t', strtotime($date_from)) . ' 23:59:59';
        $label_periode = 'Bulanan — ' . date('F Y', strtotime($date_from));
        break;
}

// ── Query ringkasan ──
$q_summary = $conn->prepare("
    SELECT
        SUM(CASE WHEN jenis='masuk'   THEN jumlah ELSE 0 END) AS total_masuk,
        SUM(CASE WHEN jenis='keluar'  THEN jumlah ELSE 0 END) AS total_keluar,
        SUM(CASE WHEN jenis='koreksi' THEN jumlah ELSE 0 END) AS total_koreksi,
        COUNT(*) AS total_transaksi
    FROM riwayat_stok
    WHERE " . ($periode === 'harian'
        ? "DATE(waktu) = ?"
        : "waktu BETWEEN ? AND ?") . "
");
if ($periode === 'harian') {
    $q_summary->bind_param('s', $tgl_input);
} else {
    $q_summary->bind_param('ss', $date_from, $date_to);
}
$q_summary->execute();
$summary = $q_summary->get_result()->fetch_assoc();

// ── Query rekap per barang ──
if ($periode === 'harian') {
    $q_rekap = $conn->prepare("
        SELECT
            s.id_stok,
            s.nama_barang,
            s.satuan,
            s.stok_saat_ini AS stok_sekarang,
            COALESCE(SUM(CASE WHEN r.jenis='masuk'   THEN r.jumlah ELSE 0 END), 0) AS total_masuk,
            COALESCE(SUM(CASE WHEN r.jenis='keluar'  THEN r.jumlah ELSE 0 END), 0) AS total_keluar,
            COALESCE(SUM(CASE WHEN r.jenis='koreksi' THEN r.jumlah ELSE 0 END), 0) AS total_koreksi,
            COUNT(r.id_riwayat) AS total_transaksi,
            MIN(CASE WHEN r.waktu = (
                SELECT MIN(r2.waktu) FROM riwayat_stok r2
                WHERE r2.id_stok = s.id_stok AND DATE(r2.waktu) = ?
            ) THEN r.stok_sebelum END) AS stok_awal_periode
        FROM stok_barang s
        LEFT JOIN riwayat_stok r ON s.id_stok = r.id_stok
            AND DATE(r.waktu) = ?
        GROUP BY s.id_stok, s.nama_barang, s.satuan, s.stok_saat_ini
        ORDER BY total_transaksi DESC, s.nama_barang ASC
    ");
    $q_rekap->bind_param('ss', $tgl_input, $tgl_input);
} else {
    $q_rekap = $conn->prepare("
        SELECT
            s.id_stok,
            s.nama_barang,
            s.satuan,
            s.stok_saat_ini AS stok_sekarang,
            COALESCE(SUM(CASE WHEN r.jenis='masuk'   THEN r.jumlah ELSE 0 END), 0) AS total_masuk,
            COALESCE(SUM(CASE WHEN r.jenis='keluar'  THEN r.jumlah ELSE 0 END), 0) AS total_keluar,
            COALESCE(SUM(CASE WHEN r.jenis='koreksi' THEN r.jumlah ELSE 0 END), 0) AS total_koreksi,
            COUNT(r.id_riwayat) AS total_transaksi,
            MIN(CASE WHEN r.waktu = (
                SELECT MIN(r2.waktu) FROM riwayat_stok r2
                WHERE r2.id_stok = s.id_stok AND r2.waktu BETWEEN ? AND ?
            ) THEN r.stok_sebelum END) AS stok_awal_periode
        FROM stok_barang s
        LEFT JOIN riwayat_stok r ON s.id_stok = r.id_stok
            AND r.waktu BETWEEN ? AND ?
        GROUP BY s.id_stok, s.nama_barang, s.satuan, s.stok_saat_ini
        ORDER BY total_transaksi DESC, s.nama_barang ASC
    ");
    $q_rekap->bind_param('ssss', $date_from, $date_to, $date_from, $date_to);
}
$q_rekap->execute();
$rekap_rows = $q_rekap->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Laporan Stok — <?= htmlspecialchars($label_periode) ?></title>
  <link rel="stylesheet" href="../css/kasir.css"/>
  <link rel="stylesheet" href="../css/logout.css"/>
  <script src="https://unpkg.com/feather-icons"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
  <style>
    .page-laporan .main {
      display: block;
      padding: 28px 32px;
      min-height: 100vh;
      box-sizing: border-box;
    }

    /* ── Filter bar ── */
    .filter-bar {
      background: #fff;
      border-radius: 16px;
      padding: 18px 22px;
      display: flex;
      align-items: center;
      gap: 14px;
      flex-wrap: wrap;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
      border: 1.5px solid #f1f5f9;
      margin-bottom: 24px;
      animation: fadeIn .4s ease;
    }
    .filter-group {
      display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .filter-label {
      font-size: 12px; font-weight: 700; color: #64748b;
      text-transform: uppercase; letter-spacing: .5px; white-space: nowrap;
    }
    .filter-select, .filter-input {
      padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 9px;
      font-size: 13px; color: #334155; background: #f8fafc; outline: none;
      transition: .2s;
    }
    .filter-select:focus, .filter-input:focus {
      border-color: #dc143c; box-shadow: 0 0 0 3px rgba(220,20,60,.07);
    }
    .filter-input { width: 150px; }
    .filter-divider { width: 1px; height: 28px; background: #f1f5f9; }
    .btn-filter {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 16px; border-radius: 9px; border: none;
      font-size: 13px; font-weight: 700; cursor: pointer; transition: .2s;
    }
    .btn-filter svg { width: 14px; height: 14px; stroke-width: 2.5; }
    .btn-filter.apply  { background: linear-gradient(135deg,#dc143c,#ff4d6d); color:#fff; box-shadow:0 3px 10px rgba(220,20,60,.25); }
    .btn-filter.apply:hover { transform: translateY(-1px); }
    .btn-filter.pdf    { background: rgba(239,68,68,.1); color: #dc2626; }
    .btn-filter.pdf:hover { background:#ef4444; color:#fff; }
    .btn-filter.excel  { background: rgba(16,185,129,.1); color: #059669; }
    .btn-filter.excel:hover { background:#10b981; color:#fff; }

    /* ── Summary cards ── */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
      animation: fadeIn .5s ease;
    }
    .summary-card {
      background: #fff; border-radius: 16px; padding: 18px 20px;
      display: flex; align-items: center; gap: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
      border: 1.5px solid #f1f5f9;
    }
    .summary-icon {
      width: 44px; height: 44px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .summary-icon svg { width: 20px; height: 20px; stroke-width: 2; }
    .summary-card.masuk   .summary-icon { background: rgba(16,185,129,.1);  color: #059669; }
    .summary-card.keluar  .summary-icon { background: rgba(239,68,68,.1);   color: #dc2626; }
    .summary-card.koreksi .summary-icon { background: rgba(245,158,11,.1);  color: #d97706; }
    .summary-card.total   .summary-icon { background: rgba(99,102,241,.1);  color: #4f46e5; }
    .summary-info p    { font-size: 26px; font-weight: 800; color: #1e293b; margin: 0; line-height: 1; }
    .summary-info span { font-size: 12px; color: #64748b; font-weight: 500; margin-top: 3px; display: block; }

    /* ── Tabel rekap ── */
    .rekap-table-wrap {
      background: #fff; border-radius: 18px;
      box-shadow: 0 2px 12px rgba(0,0,0,.05);
      border: 1.5px solid #f1f5f9; overflow: hidden;
      margin-bottom: 24px;
      animation: fadeIn .5s ease;
      width: 100%;
    }
    .rekap-table-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border-bottom: 1.5px solid #f1f5f9;
    }
    .rekap-table-header h3 {
      font-size: 15px; font-weight: 700; color: #1e293b; margin: 0;
      display: flex; align-items: center; gap: 8px;
    }
    .rekap-table-header h3 svg { width: 16px; height: 16px; color: #dc143c; }
    .rekap-table { width: 100%; border-collapse: collapse; table-layout: auto; }
    .rekap-table thead th {
      background: linear-gradient(135deg, #dc143c, #be1234);
      color: #fff; padding: 11px 12px;
      font-size: 10.5px; font-weight: 700;
      letter-spacing: .4px; text-transform: uppercase;
      text-align: center; white-space: nowrap;
    }
    .rekap-table thead th:first-child { text-align: left; padding-left: 18px; min-width: 160px; }
    .rekap-table thead th:nth-child(2) { min-width: 70px; }
    .rekap-table thead th:nth-child(3) { min-width: 110px; }
    .rekap-table thead th:nth-child(4) { min-width: 80px; }
    .rekap-table thead th:nth-child(5) { min-width: 80px; }
    .rekap-table thead th:nth-child(6) { min-width: 85px; }
    .rekap-table thead th:nth-child(7) { min-width: 100px; }
    .rekap-table thead th:nth-child(8) { min-width: 80px; }
    .rekap-table tbody tr { border-bottom: 1px solid #f8fafc; transition: .15s; }
    .rekap-table tbody tr:hover { background: #fff5f7; }
    .rekap-table tbody tr:last-child { border-bottom: none; }
    .rekap-table td {
      padding: 11px 12px; font-size: 13px; color: #334155;
      text-align: center; vertical-align: middle; white-space: nowrap;
    }
    .rekap-table td:first-child { text-align: left; padding-left: 18px; font-weight: 600; color: #1e293b; }
    .rekap-table td.masuk  { color: #059669; font-weight: 700; }
    .rekap-table td.keluar { color: #dc2626; font-weight: 700; }
    .rekap-table td.koreksi { color: #d97706; font-weight: 700; }
    .no-activity { color: #cbd5e1 !important; font-weight: 400 !important; }

    /* ── Search tabel ── */
    .rekap-search {
      padding: 7px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px;
      font-size: 12.5px; outline: none; width: 180px; transition: .2s;
    }
    .rekap-search:focus { border-color: #dc143c; }

    @keyframes fadeIn {
      from { opacity:0; transform:translateY(8px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* ════════════════════════════
       PRINT STYLES
    ════════════════════════════ */
    @media print {
      * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

      body {
        display: block !important;
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
      }

      .sidebar,
      .menu-toggle,
      .filter-bar,
      .no-print,
      #logoutModal,
      .rekap-search {
        display: none !important;
      }

      .main {
        display: block !important;
        padding: 16px 20px !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
      }

      .page-laporan .main {
        padding: 16px 20px !important;
      }

      .print-header { display: block !important; }

      .summary-card,
      .rekap-table-wrap {
        box-shadow: none !important;
        border: 1px solid #e2e8f0 !important;
        break-inside: avoid;
        page-break-inside: avoid;
      }

      .rekap-table thead th {
        background: #dc143c !important;
        color: #fff !important;
      }

      .rekap-table td.masuk  { color: #059669 !important; }
      .rekap-table td.keluar { color: #dc2626 !important; }
      .rekap-table td.koreksi { color: #d97706 !important; }
    }
    .print-header {
      display: none;
      text-align: center; margin-bottom: 20px;
    }
    .print-header h1 { font-size: 18px; font-weight: 800; color: #1e293b; }
    .print-header p  { font-size: 13px; color: #64748b; margin-top: 4px; }
  </style>
</head>
<body class="page-laporan">
  <button class="menu-toggle" id="menu-toggle"><i data-feather="menu"></i></button>

  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>MANAGER</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="inventory.php"><i data-feather="package"></i> Inventory</a></li>
      <li><a href="laporan_stok.php" class="active"><i data-feather="bar-chart-2"></i> Laporan Stok</a></li>
      <li><a href="kelola_akun.php"><i data-feather="users"></i> Kelola Akun</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main">

    <!-- Print header (hanya tampil saat print) -->
    <div class="print-header">
      <h1>Warkop Seraya — Laporan Stok</h1>
      <p><?= htmlspecialchars($label_periode) ?> &bull; Dicetak: <?= date('d/m/Y H:i') ?></p>
    </div>

    <!-- ── Filter Bar ── -->
    <form method="GET" class="filter-bar no-print" id="filterForm">
      <div class="filter-group">
        <span class="filter-label">Periode</span>
        <select name="periode" class="filter-select" id="periodeSelect" onchange="toggleFilterInputs()">
          <option value="harian"   <?= $periode==='harian'   ? 'selected':'' ?>>Harian</option>
          <option value="mingguan" <?= $periode==='mingguan' ? 'selected':'' ?>>Mingguan</option>
          <option value="bulanan"  <?= $periode==='bulanan'  ? 'selected':'' ?>>Bulanan</option>
          <option value="custom"   <?= $periode==='custom'   ? 'selected':'' ?>>Periode Kustom</option>
        </select>
      </div>

      <!-- Input harian -->
      <div class="filter-group" id="inputHarian" style="display:<?= $periode==='harian'?'flex':'none' ?>">
        <input type="date" name="tgl" class="filter-input"
          value="<?= htmlspecialchars($_GET['tgl'] ?? $today) ?>"
          max="<?= $today ?>">
      </div>

      <!-- Input mingguan -->
      <div class="filter-group" id="inputMingguan" style="display:<?= $periode==='mingguan'?'flex':'none' ?>">
        <input type="week" name="week" class="filter-input"
          value="<?= htmlspecialchars($_GET['week'] ?? date('Y-\WW')) ?>">
      </div>

      <!-- Input bulanan -->
      <div class="filter-group" id="inputBulanan" style="display:<?= $periode==='bulanan'?'flex':'none' ?>">
        <input type="month" name="bulan" class="filter-input"
          value="<?= htmlspecialchars($_GET['bulan'] ?? date('Y-m')) ?>"
          max="<?= date('Y-m') ?>">
      </div>

      <!-- Input custom -->
      <div class="filter-group" id="inputCustom" style="display:<?= $periode==='custom'?'flex':'none' ?>">
        <input type="date" name="from" class="filter-input"
          value="<?= htmlspecialchars($_GET['from'] ?? $today) ?>"
          max="<?= $today ?>">
        <span style="font-size:12px;color:#94a3b8;">s/d</span>
        <input type="date" name="to" class="filter-input"
          value="<?= htmlspecialchars($_GET['to'] ?? $today) ?>"
          max="<?= $today ?>">
      </div>

      <div class="filter-divider"></div>

      <button type="submit" class="btn-filter apply">
        <i data-feather="search"></i> Tampilkan
      </button>

      <div class="filter-divider"></div>

      <button type="button" class="btn-filter pdf" onclick="exportPDF()">
        <i data-feather="file-text"></i> Export PDF
      </button>
      <button type="button" class="btn-filter excel" onclick="exportExcel()">
        <i data-feather="file"></i> Export Excel
      </button>
    </form>

    <!-- ── Summary Cards ── -->
    <div class="summary-grid">
      <div class="summary-card masuk">
        <div class="summary-icon"><i data-feather="trending-up"></i></div>
        <div class="summary-info">
          <p><?= number_format((float)($summary['total_masuk'] ?? 0), 0, ',', '.') ?></p>
          <span>Total Masuk</span>
        </div>
      </div>
      <div class="summary-card keluar">
        <div class="summary-icon"><i data-feather="trending-down"></i></div>
        <div class="summary-info">
          <p><?= number_format((float)($summary['total_keluar'] ?? 0), 0, ',', '.') ?></p>
          <span>Total Keluar</span>
        </div>
      </div>
      <div class="summary-card koreksi">
        <div class="summary-icon"><i data-feather="sliders"></i></div>
        <div class="summary-info">
          <p><?= number_format((float)($summary['total_koreksi'] ?? 0), 0, ',', '.') ?></p>
          <span>Total Koreksi</span>
        </div>
      </div>
      <div class="summary-card total">
        <div class="summary-icon"><i data-feather="activity"></i></div>
        <div class="summary-info">
          <p><?= number_format((int)($summary['total_transaksi'] ?? 0), 0, ',', '.') ?></p>
          <span>Total Transaksi</span>
        </div>
      </div>
    </div>

    <!-- ── Tabel Rekap Per Barang ── -->
    <div class="rekap-table-wrap">
      <div class="rekap-table-header">
        <h3><i data-feather="list"></i> Rekap Per Barang</h3>
        <input type="text" class="rekap-search no-print" id="rekapSearch"
          placeholder="Cari barang..." oninput="filterRekap()">
      </div>
      <div style="overflow-x:auto;">
        <table class="rekap-table" id="rekapTable">
          <thead>
            <tr>
              <th>Nama Barang</th>
              <th>Satuan</th>
              <th>Stok Awal Periode</th>
              <th>+ Masuk</th>
              <th>- Keluar</th>
              <th>~ Koreksi</th>
              <th>Stok Saat Ini</th>
              <th>Transaksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rekap_rows as $row):
              $aktif = $row['total_transaksi'] > 0;
              $stok_awal = $row['stok_awal_periode'] !== null
                ? (float)$row['stok_awal_periode']
                : (float)$row['stok_sekarang'];
            ?>
            <tr data-nama="<?= htmlspecialchars(strtolower($row['nama_barang'])) ?>">
              <td><?= htmlspecialchars($row['nama_barang']) ?></td>
              <td><?= htmlspecialchars($row['satuan']) ?></td>
              <td><?= $aktif ? number_format($stok_awal, 0, ',', '.') : '<span class="no-activity">—</span>' ?></td>
              <td class="masuk <?= !$aktif?'no-activity':'' ?>">
                <?= $row['total_masuk'] > 0 ? '+'.number_format($row['total_masuk'],0,',','.') : '—' ?>
              </td>
              <td class="keluar <?= !$aktif?'no-activity':'' ?>">
                <?= $row['total_keluar'] > 0 ? '-'.number_format($row['total_keluar'],0,',','.') : '—' ?>
              </td>
              <td class="koreksi <?= !$aktif?'no-activity':'' ?>">
                <?= $row['total_koreksi'] > 0 ? '~'.number_format($row['total_koreksi'],0,',','.') : '—' ?>
              </td>
              <td><strong><?= number_format((float)$row['stok_sekarang'], 0, ',', '.') ?></strong></td>
              <td>
                <?php if ($aktif): ?>
                <span style="background:rgba(99,102,241,.1);color:#4f46e5;padding:3px 9px;border-radius:6px;font-size:12px;font-weight:700;">
                  <?= $row['total_transaksi'] ?>x
                </span>
                <?php else: ?>
                <span class="no-activity">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /main -->

  <!-- PDF render container — dihapus, pakai jsPDF langsung -->

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
  // ── Toggle input filter sesuai periode ──
  function toggleFilterInputs() {
    const p = document.getElementById('periodeSelect').value;
    ['Harian','Mingguan','Bulanan','Custom'].forEach(n => {
      const el = document.getElementById('input' + n);
      if (el) el.style.display = p === n.toLowerCase() ? 'flex' : 'none';
    });
  }

  // ── Search tabel rekap ──
  function filterRekap() {
    const q = document.getElementById('rekapSearch').value.toLowerCase();
    document.querySelectorAll('#rekapTable tbody tr').forEach(tr => {
      tr.style.display = (!q || tr.dataset.nama.includes(q)) ? '' : 'none';
    });
  }

  // ── Export PDF (jsPDF + autoTable) ──
  function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

    const periodeLabel = <?= json_encode($label_periode) ?>;
    const tanggal = new Date().toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' });
    const namaFile = 'Laporan_Stok_' + periodeLabel.replace(/[^a-z0-9]/gi,'_') + '.pdf';

    // Header
    doc.setFontSize(16); doc.setFont('helvetica','bold'); doc.setTextColor(30,41,59);
    doc.text('LAPORAN STOK WARKOP SERAYA', 148.5, 16, { align:'center' });
    doc.setFontSize(9); doc.setFont('helvetica','normal'); doc.setTextColor(100,116,139);
    doc.text(periodeLabel + '   |   Dicetak: ' + tanggal, 148.5, 22, { align:'center' });
    doc.setDrawColor(220,20,60); doc.setLineWidth(0.5); doc.line(14, 25, 283, 25);

    // Summary boxes
    const masuk   = document.querySelector('.summary-card.masuk p')?.innerText   || '0';
    const keluar  = document.querySelector('.summary-card.keluar p')?.innerText  || '0';
    const koreksi = document.querySelector('.summary-card.koreksi p')?.innerText || '0';
    const trx     = document.querySelector('.summary-card.total p')?.innerText   || '0';
    const boxes = [
      { label:'Total Masuk',     val:masuk,   color:[16,185,129],  bg:[240,253,244] },
      { label:'Total Keluar',    val:keluar,  color:[220,38,38],   bg:[254,242,242] },
      { label:'Total Koreksi',   val:koreksi, color:[217,119,6],   bg:[255,251,235] },
      { label:'Total Transaksi', val:trx,     color:[79,70,229],   bg:[245,243,255] },
    ];
    const bw=62, bh=14, bx=14, by=29, gap=4;
    boxes.forEach((b, i) => {
      const x = bx + i * (bw + gap);
      doc.setFillColor(...b.bg); doc.setDrawColor(220,232,240);
      doc.roundedRect(x, by, bw, bh, 3, 3, 'FD');
      doc.setFontSize(14); doc.setFont('helvetica','bold'); doc.setTextColor(...b.color);
      doc.text(b.val, x+bw/2, by+8, { align:'center' });
      doc.setFontSize(7); doc.setFont('helvetica','normal'); doc.setTextColor(100,116,139);
      doc.text(b.label, x+bw/2, by+12, { align:'center' });
    });

    // Data tabel dari DOM
    const rows = [];
    document.querySelectorAll('#rekapTable tbody tr').forEach(tr => {
      if (tr.style.display === 'none') return;
      const c = tr.querySelectorAll('td');
      rows.push([
        c[0]?.innerText.trim()||'', c[1]?.innerText.trim()||'',
        c[2]?.innerText.trim()||'—', c[3]?.innerText.trim()||'—',
        c[4]?.innerText.trim()||'—', c[5]?.innerText.trim()||'—',
        c[6]?.innerText.trim()||'', c[7]?.innerText.trim()||'—',
      ]);
    });

    doc.autoTable({
      head: [['Nama Barang','Satuan','Stok Awal','+ Masuk','- Keluar','~ Koreksi','Stok Saat Ini','Transaksi']],
      body: rows,
      startY: by + bh + 6,
      styles: { fontSize: 9, cellPadding: 3, valign: 'middle' },
      headStyles: { fillColor:[220,20,60], textColor:255, fontStyle:'bold', fontSize:8.5, halign:'center' },
      columnStyles: {
        0: { halign:'left',   cellWidth:50 },
        1: { halign:'center', cellWidth:20 },
        2: { halign:'center', cellWidth:28 },
        3: { halign:'center', cellWidth:22, textColor:[5,150,105]  },
        4: { halign:'center', cellWidth:22, textColor:[220,38,38]  },
        5: { halign:'center', cellWidth:24, textColor:[217,119,6]  },
        6: { halign:'center', cellWidth:30 },
        7: { halign:'center', cellWidth:22 },
      },
      alternateRowStyles: { fillColor:[250,251,252] },
      margin: { left:14, right:14 },
    });

    // Nomor halaman + footer
    const pages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pages; i++) {
      doc.setPage(i);
      doc.setFontSize(7); doc.setTextColor(148,163,184);
      doc.text('WARKOP SERAYA — LAPORAN STOK INVENTORY', 14, doc.internal.pageSize.height - 6);
      doc.text('Hal ' + i + ' / ' + pages, 283, doc.internal.pageSize.height - 6, { align:'right' });
    }

    doc.save(namaFile);
  }

  // ── Export Excel (.xls HTML table) ──
  function exportExcel() {
    const periode = <?= json_encode($label_periode) ?>;
    const tanggal = '<?= date('d/m/Y H:i') ?>';

    // Ambil data tabel
    const rows = document.querySelectorAll('#rekapTable tbody tr');
    let tableRows = '';
    rows.forEach(tr => {
      if (tr.style.display === 'none') return;
      const cells = tr.querySelectorAll('td');
      tableRows += '<tr>';
      cells.forEach(td => {
        tableRows += '<td>' + td.innerText.trim() + '</td>';
      });
      tableRows += '</tr>';
    });

    const html = `
      <html xmlns:o="urn:schemas-microsoft-com:office:office"
            xmlns:x="urn:schemas-microsoft-com:office:excel"
            xmlns="http://www.w3.org/TR/REC-html40">
      <head>
        <meta charset="UTF-8">
        <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets>
        <x:ExcelWorksheet><x:Name>Laporan Stok</x:Name>
        <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
        </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
        <style>
          td { border: 1px solid #ccc; padding: 6px 10px; font-size: 12px; }
          th { background: #dc143c; color: #fff; font-weight: bold; padding: 8px 10px; border: 1px solid #be1234; }
          .title { font-size: 16px; font-weight: bold; }
        </style>
      </head>
      <body>
        <table>
          <tr><td class="title" colspan="8">Warkop Seraya — Laporan Stok</td></tr>
          <tr><td colspan="8">${periode} | Dicetak: ${tanggal}</td></tr>
          <tr><td colspan="8"></td></tr>
          <tr>
            <th>Nama Barang</th><th>Satuan</th><th>Stok Awal</th>
            <th>+ Masuk</th><th>- Keluar</th><th>~ Koreksi</th>
            <th>Stok Saat Ini</th><th>Transaksi</th>
          </tr>
          ${tableRows}
        </table>
      </body></html>`;

    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'laporan_stok_' + periode.replace(/[^a-z0-9]/gi, '_') + '.xls';
    a.click();
    URL.revokeObjectURL(url);
  }

  </script>
</body>
</html>
