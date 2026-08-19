<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit;
}

// ── Ringkasan ──
$total_item        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM stok_barang"))['c'];
$stok_tipis        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM stok_barang WHERE stok_saat_ini <= stok_minimum AND stok_saat_ini > 0"))['c'];
$stok_habis        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM stok_barang WHERE stok_saat_ini = 0"))['c'];
$total_masuk_bulan = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(jumlah),0) c FROM riwayat_stok WHERE jenis='masuk' AND MONTH(waktu)=MONTH(CURDATE()) AND YEAR(waktu)=YEAR(CURDATE())"
))['c'];

// ── Semua stok ──
$stok_list = mysqli_query($conn, "
    SELECT s.*, p.nama_produk
    FROM stok_barang s
    LEFT JOIN produk p ON s.id_produk = p.id_produk
    ORDER BY s.stok_saat_ini ASC, s.nama_barang ASC
");

// ── Riwayat terbaru (50 record) ──
$riwayat = mysqli_query($conn, "
    SELECT r.*, s.nama_barang, s.satuan, a.username AS nama_admin
    FROM riwayat_stok r
    JOIN stok_barang s ON r.id_stok = s.id_stok
    LEFT JOIN admin a ON r.id_admin = a.id_admin
    ORDER BY r.waktu DESC
    LIMIT 50
");

// ── Laporan Stok: Periode dari GET ──
$periode   = $_GET['periode'] ?? 'bulanan';
$today     = date('Y-m-d');
$tgl_input = $today;

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

// ── Query ringkasan laporan ──
$q_summary = $conn->prepare("
    SELECT
        SUM(CASE WHEN jenis='masuk'   THEN jumlah ELSE 0 END) AS total_masuk,
        SUM(CASE WHEN jenis='keluar'  THEN jumlah ELSE 0 END) AS total_keluar,
        SUM(CASE WHEN jenis='koreksi' THEN jumlah ELSE 0 END) AS total_koreksi,
        COUNT(*) AS total_transaksi
    FROM riwayat_stok
    WHERE " . ($periode === 'harian' ? "DATE(waktu) = ?" : "waktu BETWEEN ? AND ?") . "
");
if ($periode === 'harian') $q_summary->bind_param('s', $tgl_input);
else $q_summary->bind_param('ss', $date_from, $date_to);
$q_summary->execute();
$summary = $q_summary->get_result()->fetch_assoc();

// ── Query rekap per barang ──
if ($periode === 'harian') {
    $q_rekap = $conn->prepare("
        SELECT s.id_stok, s.nama_barang, s.satuan, s.stok_saat_ini AS stok_sekarang,
            COALESCE(SUM(CASE WHEN r.jenis='masuk'   THEN r.jumlah ELSE 0 END),0) AS total_masuk,
            COALESCE(SUM(CASE WHEN r.jenis='keluar'  THEN r.jumlah ELSE 0 END),0) AS total_keluar,
            COALESCE(SUM(CASE WHEN r.jenis='koreksi' THEN r.jumlah ELSE 0 END),0) AS total_koreksi,
            COUNT(r.id_riwayat) AS total_transaksi,
            MIN(CASE WHEN r.waktu=(SELECT MIN(r2.waktu) FROM riwayat_stok r2 WHERE r2.id_stok=s.id_stok AND DATE(r2.waktu)=?) THEN r.stok_sebelum END) AS stok_awal_periode
        FROM stok_barang s
        LEFT JOIN riwayat_stok r ON s.id_stok=r.id_stok AND DATE(r.waktu)=?
        GROUP BY s.id_stok, s.nama_barang, s.satuan, s.stok_saat_ini
        ORDER BY total_transaksi DESC, s.nama_barang ASC
    ");
    $q_rekap->bind_param('ss', $tgl_input, $tgl_input);
} else {
    $q_rekap = $conn->prepare("
        SELECT s.id_stok, s.nama_barang, s.satuan, s.stok_saat_ini AS stok_sekarang,
            COALESCE(SUM(CASE WHEN r.jenis='masuk'   THEN r.jumlah ELSE 0 END),0) AS total_masuk,
            COALESCE(SUM(CASE WHEN r.jenis='keluar'  THEN r.jumlah ELSE 0 END),0) AS total_keluar,
            COALESCE(SUM(CASE WHEN r.jenis='koreksi' THEN r.jumlah ELSE 0 END),0) AS total_koreksi,
            COUNT(r.id_riwayat) AS total_transaksi,
            MIN(CASE WHEN r.waktu=(SELECT MIN(r2.waktu) FROM riwayat_stok r2 WHERE r2.id_stok=s.id_stok AND r2.waktu BETWEEN ? AND ?) THEN r.stok_sebelum END) AS stok_awal_periode
        FROM stok_barang s
        LEFT JOIN riwayat_stok r ON s.id_stok=r.id_stok AND r.waktu BETWEEN ? AND ?
        GROUP BY s.id_stok, s.nama_barang, s.satuan, s.stok_saat_ini
        ORDER BY total_transaksi DESC, s.nama_barang ASC
    ");
    $q_rekap->bind_param('ssss', $date_from, $date_to, $date_from, $date_to);
}
$q_rekap->execute();
$rekap_rows = $q_rekap->get_result()->fetch_all(MYSQLI_ASSOC);

// Tab aktif
$active_tab = $_GET['tab'] ?? 'stok';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manager - Inventory</title>
  <link rel="stylesheet" href="../css/kasir.css"/>
  <link rel="stylesheet" href="../css/logout.css"/>
  <script src="https://unpkg.com/feather-icons"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
  <style>
    .page-inventory-mgr .main {
      display: block;
      padding: 28px 32px;
      min-height: 100vh;
      box-sizing: border-box;
    }

    /* ── Stats ── */
    .inv-stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
      animation: fadeIn .5s ease;
    }
    .inv-stat-card {
      background: #fff; border-radius: 16px;
      padding: 20px 22px; display: flex;
      align-items: center; gap: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,.06);
      border: 1.5px solid #f1f5f9;
    }
    .inv-stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .inv-stat-icon svg { width:22px; height:22px; stroke-width:2; }
    .inv-stat-card.total .inv-stat-icon { background:rgba(220,20,60,.09); color:#dc143c; }
    .inv-stat-card.tipis .inv-stat-icon { background:rgba(245,158,11,.1); color:#d97706; }
    .inv-stat-card.habis .inv-stat-icon { background:rgba(239,68,68,.1);  color:#ef4444; }
    .inv-stat-card.masuk .inv-stat-icon { background:rgba(16,185,129,.1); color:#059669; }
    .inv-stat-info p    { font-size:28px; font-weight:800; color:#1e293b; margin:0; line-height:1; }
    .inv-stat-info span { font-size:12.5px; color:#64748b; font-weight:500; }

    /* ── Tab navigation ── */
    .inv-tabs {
      display: flex;
      gap: 4px;
      background: #f1f5f9;
      border-radius: 14px;
      padding: 5px;
      margin-bottom: 20px;
      width: fit-content;
      animation: fadeIn .5s ease;
    }
    .inv-tab-btn {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 20px; border-radius: 10px; border: none;
      font-size: 13.5px; font-weight: 600; cursor: pointer;
      transition: all .2s ease; color: #64748b; background: transparent;
    }
    .inv-tab-btn svg { width: 15px; height: 15px; stroke-width: 2.2; }
    .inv-tab-btn.active {
      background: #fff; color: #1e293b;
      box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }
    .inv-tab-btn .tab-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 20px; height: 20px; border-radius: 10px; padding: 0 6px;
      font-size: 11px; font-weight: 800;
    }
    .inv-tab-btn.active .tab-badge { background: rgba(220,20,60,.1); color: #dc143c; }
    .inv-tab-btn:not(.active) .tab-badge { background: #e2e8f0; color: #64748b; }

    /* ── Tab content ── */
    .inv-tab-content { display: none; animation: fadeIn .3s ease; }
    .inv-tab-content.active { display: block; }

    /* ── Panel wrapper ── */
    .inv-panel {
      background: #fff; border-radius: 18px;
      box-shadow: 0 2px 12px rgba(0,0,0,.06);
      border: 1.5px solid #f1f5f9; overflow: hidden;
    }
    .inv-panel-toolbar {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border-bottom: 1.5px solid #f1f5f9;
      flex-wrap: wrap; gap: 10px;
    }
    .inv-panel-toolbar h3 {
      font-size: 15px; font-weight: 700; color: #1e293b; margin: 0;
      display: flex; align-items: center; gap: 8px;
    }
    .inv-panel-toolbar h3 svg { width: 16px; height: 16px; color: #dc143c; }
    .inv-search {
      padding: 8px 13px; border: 1.5px solid #e2e8f0; border-radius: 9px;
      font-size: 13px; outline: none; width: 200px; transition: .2s;
    }
    .inv-search:focus { border-color: #dc143c; box-shadow: 0 0 0 3px rgba(220,20,60,.07); }

    /* ── Tabel stok ── */
    .inv-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .inv-table thead th {
      background: linear-gradient(135deg, #dc143c, #be1234);
      color: #fff; padding: 11px 14px;
      font-size: 11px; font-weight: 700; letter-spacing: .6px;
      text-transform: uppercase; text-align: center; white-space: nowrap;
    }
    .inv-table thead th:first-child { text-align: left; padding-left: 20px; }
    .inv-table tbody tr { border-bottom: 1px solid #f8fafc; transition: .15s; }
    .inv-table tbody tr:nth-child(even) { background: #fafbfc; }
    .inv-table tbody tr:hover { background: #fff5f7; }
    .inv-table tbody tr:last-child { border-bottom: none; }
    .inv-table td {
      padding: 12px 14px; font-size: 13px; color: #334155;
      text-align: center; vertical-align: middle;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .inv-table td:first-child { text-align: left; padding-left: 20px; font-weight: 600; color: #1e293b; }

    /* Kolom stok */
    .th-nama   { width: 24%; }
    .th-satuan { width: 9%; }
    .th-stok   { width: 12%; }
    .th-min    { width: 10%; }
    .th-bar    { width: 18%; }
    .th-status { width: 10%; }
    .th-menu   { width: 17%; }

    /* Progress bar */
    .stok-bar-wrap { display: flex; align-items: center; gap: 8px; justify-content: center; }
    .stok-bar { flex: 1; height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden; max-width: 100px; }
    .stok-bar-fill { height: 100%; border-radius: 10px; }

    /* Badge status */
    .stok-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 11px; border-radius: 20px;
      font-size: 11.5px; font-weight: 700; white-space: nowrap;
    }
    .stok-badge::before { content:''; width:6px; height:6px; border-radius:50%; flex-shrink:0; }
    .stok-badge.aman  { color:#059669; background:rgba(16,185,129,.1); border:1.5px solid rgba(16,185,129,.2); }
    .stok-badge.aman::before  { background:#10b981; }
    .stok-badge.tipis { color:#d97706; background:rgba(245,158,11,.1); border:1.5px solid rgba(245,158,11,.2); }
    .stok-badge.tipis::before { background:#f59e0b; }
    .stok-badge.habis { color:#dc2626; background:rgba(239,68,68,.1); border:1.5px solid rgba(239,68,68,.2); }
    .stok-badge.habis::before { background:#ef4444; }

    /* ── Tabel riwayat ── */
    .th-rw-barang { width: 18%; }
    .th-rw-jenis  { width: 9%; }
    .th-rw-jumlah { width: 10%; }
    .th-rw-sumber { width: 13%; }
    .th-rw-ket    { width: 22%; }
    .th-rw-admin  { width: 12%; }
    .th-rw-waktu  { width: 13%; }
    .th-rw-aksi   { width: 7%; }

    .jenis-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 3px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;
    }
    .jenis-badge.masuk   { color:#059669; background:rgba(16,185,129,.1); }
    .jenis-badge.keluar  { color:#dc2626; background:rgba(239,68,68,.1); }
    .jenis-badge.koreksi { color:#d97706; background:rgba(245,158,11,.1); }

    .jumlah-val.masuk   { color:#059669; font-weight:700; }
    .jumlah-val.keluar  { color:#dc2626; font-weight:700; }
    .jumlah-val.koreksi { color:#d97706; font-weight:700; }

    .btn-del-riwayat {
      width:28px; height:28px; border-radius:8px; border:none;
      background:rgba(239,68,68,.1); color:#dc2626; cursor:pointer;
      display:flex; align-items:center; justify-content:center; transition:.2s; margin:auto;
    }
    .btn-del-riwayat:hover { background:#ef4444; color:#fff; }
    .btn-del-riwayat svg { width:13px; height:13px; stroke-width:2.5; }

    /* Notif stok */
    @keyframes slideInNotif { from{transform:translateX(120%);opacity:0;} to{transform:translateX(0);opacity:1;} }
    @keyframes fadeOutNotif { from{transform:translateX(0);opacity:1;} to{transform:translateX(120%);opacity:0;} }
    .notif-toast {
      display:none; align-items:center; gap:14px;
      position:fixed; top:20px; right:20px;
      background:rgba(28,28,30,.95); backdrop-filter:blur(10px);
      border:1px solid rgba(255,255,255,.08); border-left:4px solid #ef4444;
      padding:16px 20px; border-radius:12px; color:#fff;
      box-shadow:0 10px 30px rgba(0,0,0,.3); z-index:9999; width:340px; cursor:pointer;
      animation:slideInNotif .4s cubic-bezier(.16,1,.3,1) forwards;
    }
    .notif-toast.warning { border-left-color:#f59e0b; }
    .notif-toast.hide { animation:fadeOutNotif .4s cubic-bezier(.16,1,.3,1) forwards; }
    .sidebar-stok-badge {
      display:inline-flex; align-items:center; justify-content:center;
      background:#ef4444; color:#fff; font-size:10px; font-weight:800;
      min-width:18px; height:18px; border-radius:9px; padding:0 5px; margin-left:auto; line-height:1;
      animation:pulse-badge 1.5s infinite;
    }
    @keyframes pulse-badge { 0%,100%{transform:scale(1)} 50%{transform:scale(1.15)} }

    @keyframes fadeIn {
      from { opacity:0; transform:translateY(8px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* ══ CSS Laporan Stok (Tab ke-3) ══ */
    .filter-bar {
      background:#fff; border-radius:16px; padding:18px 22px;
      display:flex; align-items:center; gap:14px; flex-wrap:wrap;
      box-shadow:0 2px 10px rgba(0,0,0,.05); border:1.5px solid #f1f5f9;
      margin-bottom:24px;
    }
    .filter-group { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .filter-label { font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
    .filter-select, .filter-input {
      padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:9px;
      font-size:13px; color:#334155; background:#f8fafc; outline:none; transition:.2s;
    }
    .filter-select:focus, .filter-input:focus { border-color:#dc143c; box-shadow:0 0 0 3px rgba(220,20,60,.07); }
    .filter-input { width:150px; }
    .filter-divider { width:1px; height:28px; background:#f1f5f9; }
    .btn-filter {
      display:inline-flex; align-items:center; gap:6px;
      padding:8px 16px; border-radius:9px; border:none;
      font-size:13px; font-weight:700; cursor:pointer; transition:.2s;
    }
    .btn-filter svg { width:14px; height:14px; stroke-width:2.5; }
    .btn-filter.apply  { background:linear-gradient(135deg,#dc143c,#ff4d6d); color:#fff; box-shadow:0 3px 10px rgba(220,20,60,.25); }
    .btn-filter.apply:hover { transform:translateY(-1px); }
    .btn-filter.pdf    { background:rgba(239,68,68,.1);  color:#dc2626; }
    .btn-filter.pdf:hover   { background:#ef4444; color:#fff; }
    .btn-filter.excel  { background:rgba(16,185,129,.1); color:#059669; }
    .btn-filter.excel:hover { background:#10b981; color:#fff; }

    .summary-grid {
      display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;
    }
    .summary-card {
      background:#fff; border-radius:16px; padding:18px 20px;
      display:flex; align-items:center; gap:14px;
      box-shadow:0 2px 10px rgba(0,0,0,.05); border:1.5px solid #f1f5f9;
    }
    .summary-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .summary-icon svg { width:20px; height:20px; stroke-width:2; }
    .summary-card.masuk   .summary-icon { background:rgba(16,185,129,.1);  color:#059669; }
    .summary-card.keluar  .summary-icon { background:rgba(239,68,68,.1);   color:#dc2626; }
    .summary-card.koreksi .summary-icon { background:rgba(245,158,11,.1);  color:#d97706; }
    .summary-card.total   .summary-icon { background:rgba(99,102,241,.1);  color:#4f46e5; }
    .summary-info p    { font-size:26px; font-weight:800; color:#1e293b; margin:0; line-height:1; }
    .summary-info span { font-size:12px; color:#64748b; font-weight:500; margin-top:3px; display:block; }

    .rekap-table-wrap {
      background:#fff; border-radius:18px;
      box-shadow:0 2px 12px rgba(0,0,0,.05); border:1.5px solid #f1f5f9;
      overflow:hidden; width:100%;
    }
    .rekap-table-header {
      display:flex; align-items:center; justify-content:space-between;
      padding:16px 20px; border-bottom:1.5px solid #f1f5f9;
    }
    .rekap-table-header h3 { font-size:15px; font-weight:700; color:#1e293b; margin:0; display:flex; align-items:center; gap:8px; }
    .rekap-table-header h3 svg { width:16px; height:16px; color:#dc143c; }
    .rekap-table { width:100%; border-collapse:collapse; table-layout:auto; }
    .rekap-table thead th {
      background:linear-gradient(135deg,#dc143c,#be1234); color:#fff;
      padding:11px 12px; font-size:10.5px; font-weight:700;
      letter-spacing:.4px; text-transform:uppercase; text-align:center; white-space:nowrap;
    }
    .rekap-table thead th:first-child { text-align:left; padding-left:18px; min-width:160px; }
    .rekap-table thead th:nth-child(2) { min-width:70px; }
    .rekap-table thead th:nth-child(3) { min-width:110px; }
    .rekap-table thead th:nth-child(4) { min-width:80px; }
    .rekap-table thead th:nth-child(5) { min-width:80px; }
    .rekap-table thead th:nth-child(6) { min-width:85px; }
    .rekap-table thead th:nth-child(7) { min-width:100px; }
    .rekap-table thead th:nth-child(8) { min-width:80px; }
    .rekap-table tbody tr { border-bottom:1px solid #f8fafc; transition:.15s; }
    .rekap-table tbody tr:hover { background:#fff5f7; }
    .rekap-table tbody tr:last-child { border-bottom:none; }
    .rekap-table td { padding:11px 12px; font-size:13px; color:#334155; text-align:center; vertical-align:middle; white-space:nowrap; }
    .rekap-table td:first-child { text-align:left; padding-left:18px; font-weight:600; color:#1e293b; }
    .rekap-table td.masuk   { color:#059669; font-weight:700; }
    .rekap-table td.keluar  { color:#dc2626; font-weight:700; }
    .rekap-table td.koreksi { color:#d97706; font-weight:700; }
    .no-activity { color:#cbd5e1 !important; font-weight:400 !important; }
    .rekap-search { padding:7px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:12.5px; outline:none; width:180px; transition:.2s; }
    .rekap-search:focus { border-color:#dc143c; }
  </style>
</head>
<body class="page-inventory-mgr">
  <button class="menu-toggle" id="menu-toggle"><i data-feather="menu"></i></button>

  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>MANAGER</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="inventory.php" class="active"><i data-feather="package"></i> Stok Barang</a></li>
      <li><a href="kelola_akun.php"><i data-feather="users"></i> Kelola Akun</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main">

    <!-- Stats -->
    <div class="inv-stats">
      <div class="inv-stat-card total">
        <div class="inv-stat-icon"><i data-feather="package"></i></div>
        <div class="inv-stat-info"><p><?= $total_item ?></p><span>Total Item Barang</span></div>
      </div>
      <div class="inv-stat-card tipis">
        <div class="inv-stat-icon"><i data-feather="alert-triangle"></i></div>
        <div class="inv-stat-info"><p><?= $stok_tipis ?></p><span>Stok Tipis</span></div>
      </div>
      <div class="inv-stat-card habis">
        <div class="inv-stat-icon"><i data-feather="x-circle"></i></div>
        <div class="inv-stat-info"><p><?= $stok_habis ?></p><span>Stok Habis</span></div>
      </div>
      <div class="inv-stat-card masuk">
        <div class="inv-stat-icon"><i data-feather="trending-up"></i></div>
        <div class="inv-stat-info">
          <p><?= number_format($total_masuk_bulan, 0, ',', '.') ?></p>
          <span>Total Masuk Bulan Ini</span>
        </div>
      </div>
    </div>

    <!-- Tab navigation -->
    <div class="inv-tabs">
      <button class="inv-tab-btn <?= $active_tab==='stok'?'active':'' ?>" onclick="switchTab('stok', this)">
        <i data-feather="package"></i>
        Kondisi Stok Saat Ini
        <span class="tab-badge"><?= $total_item ?></span>
      </button>
      <button class="inv-tab-btn <?= $active_tab==='riwayat'?'active':'' ?>" onclick="switchTab('riwayat', this)">
        <i data-feather="clock"></i>
        Riwayat Perubahan Stok
        <span class="tab-badge">50</span>
      </button>
      <button class="inv-tab-btn <?= $active_tab==='laporan'?'active':'' ?>" onclick="switchTab('laporan', this)">
        <i data-feather="bar-chart-2"></i>
        Laporan Stok
      </button>
    </div>

    <!-- Tab: Kondisi Stok -->
    <div class="inv-tab-content active" id="tab-stok">
      <div class="inv-panel">
        <div class="inv-panel-toolbar">
          <h3><i data-feather="package"></i> Kondisi Stok Saat Ini</h3>
          <input type="text" class="inv-search" id="searchStok"
            placeholder="Cari barang..." oninput="filterTable('stokTable', this.value)">
        </div>
        <div style="overflow-x:auto;">
          <table class="inv-table" id="stokTable">
            <thead>
              <tr>
                <th class="th-nama">Nama Barang</th>
                <th class="th-satuan">Satuan</th>
                <th class="th-stok">Stok Saat Ini</th>
                <th class="th-min">Stok Min</th>
                <th class="th-bar">Progress</th>
                <th class="th-status">Status</th>
                <th class="th-menu">Link Menu</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($stok_list)):
                $stok = (float)$row['stok_saat_ini'];
                $min  = (float)$row['stok_minimum'];
                if ($stok == 0)       { $badge = 'habis'; $label = 'Habis'; $barColor = '#ef4444'; }
                elseif ($stok <= $min){ $badge = 'tipis'; $label = 'Tipis'; $barColor = '#f59e0b'; }
                else                  { $badge = 'aman';  $label = 'Aman';  $barColor = '#10b981'; }
                $pct = $min > 0 ? min(100, round(($stok / ($min * 3)) * 100)) : 100;
              ?>
              <tr data-nama="<?= htmlspecialchars(strtolower($row['nama_barang'])) ?>">
                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                <td><?= htmlspecialchars($row['satuan']) ?></td>
                <td><strong><?= number_format($stok, 0, ',', '.') ?></strong></td>
                <td><?= number_format($min, 0, ',', '.') ?></td>
                <td>
                  <div class="stok-bar-wrap">
                    <div class="stok-bar">
                      <div class="stok-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                    </div>
                    <span style="font-size:11px;color:#94a3b8;white-space:nowrap;"><?= $pct ?>%</span>
                  </div>
                </td>
                <td><span class="stok-badge <?= $badge ?>"><?= $label ?></span></td>
                <td style="font-size:12px;color:#64748b;">
                  <?= $row['nama_produk'] ? htmlspecialchars($row['nama_produk']) : '<span style="color:#cbd5e1">—</span>' ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab: Riwayat -->
    <div class="inv-tab-content" id="tab-riwayat">
      <div class="inv-panel">
        <div class="inv-panel-toolbar">
          <h3><i data-feather="clock"></i> Riwayat Perubahan Stok</h3>
          <input type="text" class="inv-search" id="searchRiwayat"
            placeholder="Cari barang..." oninput="filterTable('riwayatTable', this.value)">
        </div>
        <div style="overflow-x:auto;">
          <table class="inv-table" id="riwayatTable">
            <thead>
              <tr>
                <th class="th-rw-barang">Nama Barang</th>
                <th class="th-rw-jenis">Jenis</th>
                <th class="th-rw-jumlah">Jumlah</th>
                <th class="th-rw-sumber">Sumber</th>
                <th class="th-rw-ket">Keterangan</th>
                <th class="th-rw-admin">Admin</th>
                <th class="th-rw-waktu">Waktu</th>
                <th class="th-rw-aksi">Hapus</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = mysqli_fetch_assoc($riwayat)):
                $sign  = $r['jenis'] === 'masuk' ? '+' : ($r['jenis'] === 'keluar' ? '-' : '~');
                $waktu = date('d/m/Y H:i', strtotime($r['waktu']));
                $sumber_label = [
                  'pesanan'        => 'Pesanan',
                  'belanja'        => 'Belanja',
                  'koreksi_manual' => 'Koreksi Manual',
                ][$r['sumber']] ?? ucfirst($r['sumber']);
              ?>
              <tr data-nama="<?= htmlspecialchars(strtolower($r['nama_barang'])) ?>">
                <td><?= htmlspecialchars($r['nama_barang']) ?></td>
                <td>
                  <span class="jenis-badge <?= $r['jenis'] ?>">
                    <?= ucfirst($r['jenis']) ?>
                  </span>
                </td>
                <td>
                  <span class="jumlah-val <?= $r['jenis'] ?>">
                    <?= $sign ?><?= number_format($r['jumlah'], 0, ',', '.') ?>
                    <small style="font-size:10px"><?= $r['satuan'] ?></small>
                  </span>
                </td>
                <td><?= $sumber_label ?></td>
                <td style="font-size:12px;color:#64748b;white-space:normal;">
                  <?= $r['keterangan'] ? htmlspecialchars($r['keterangan']) : '<span style="color:#cbd5e1">—</span>' ?>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['nama_admin'] ?? 'Sistem') ?></td>
                <td style="font-size:12px;color:#64748b;white-space:nowrap;"><?= $waktu ?></td>
                <td>
                  <button class="btn-del-riwayat" title="Hapus"
                    onclick="hapusRiwayat(<?= $r['id_riwayat'] ?>)">
                    <i data-feather="trash-2"></i>
                  </button>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Tab: Laporan Stok -->
    <div class="inv-tab-content <?= $active_tab==='laporan'?'active':'' ?>" id="tab-laporan">

      <!-- Filter Bar -->
      <form method="GET" class="filter-bar" id="filterForm">
        <input type="hidden" name="tab" value="laporan">
        <div class="filter-group">
          <span class="filter-label">Periode</span>
          <select name="periode" class="filter-select" id="periodeSelect" onchange="toggleFilterInputs()">
            <option value="harian"   <?= $periode==='harian'   ?'selected':'' ?>>Harian</option>
            <option value="mingguan" <?= $periode==='mingguan' ?'selected':'' ?>>Mingguan</option>
            <option value="bulanan"  <?= $periode==='bulanan'  ?'selected':'' ?>>Bulanan</option>
            <option value="custom"   <?= $periode==='custom'   ?'selected':'' ?>>Periode Kustom</option>
          </select>
        </div>
        <div class="filter-group" id="inputHarian" style="display:<?= $periode==='harian'?'flex':'none' ?>">
          <input type="date" name="tgl" class="filter-input"
            value="<?= htmlspecialchars($_GET['tgl'] ?? $today) ?>" max="<?= $today ?>">
        </div>
        <div class="filter-group" id="inputMingguan" style="display:<?= $periode==='mingguan'?'flex':'none' ?>">
          <input type="week" name="week" class="filter-input"
            value="<?= htmlspecialchars($_GET['week'] ?? date('Y-\WW')) ?>">
        </div>
        <div class="filter-group" id="inputBulanan" style="display:<?= $periode==='bulanan'?'flex':'none' ?>">
          <input type="month" name="bulan" class="filter-input"
            value="<?= htmlspecialchars($_GET['bulan'] ?? date('Y-m')) ?>" max="<?= date('Y-m') ?>">
        </div>
        <div class="filter-group" id="inputCustom" style="display:<?= $periode==='custom'?'flex':'none' ?>">
          <input type="date" name="from" class="filter-input"
            value="<?= htmlspecialchars($_GET['from'] ?? $today) ?>" max="<?= $today ?>">
          <span style="font-size:12px;color:#94a3b8;">s/d</span>
          <input type="date" name="to" class="filter-input"
            value="<?= htmlspecialchars($_GET['to'] ?? $today) ?>" max="<?= $today ?>">
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

      <!-- Summary Cards -->
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

      <!-- Tabel Rekap -->
      <div class="rekap-table-wrap">
        <div class="rekap-table-header">
          <h3><i data-feather="list"></i> Rekap Per Barang</h3>
          <input type="text" class="rekap-search" id="rekapSearch"
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
                <td><?= $aktif ? number_format($stok_awal,0,',','.') : '<span class="no-activity">—</span>' ?></td>
                <td class="masuk <?= !$aktif?'no-activity':'' ?>">
                  <?= $row['total_masuk'] > 0 ? '+'.number_format($row['total_masuk'],0,',','.') : '—' ?>
                </td>
                <td class="keluar <?= !$aktif?'no-activity':'' ?>">
                  <?= $row['total_keluar'] > 0 ? '-'.number_format($row['total_keluar'],0,',','.') : '—' ?>
                </td>
                <td class="koreksi <?= !$aktif?'no-activity':'' ?>">
                  <?= $row['total_koreksi'] > 0 ? '~'.number_format($row['total_koreksi'],0,',','.') : '—' ?>
                </td>
                <td><strong><?= number_format((float)$row['stok_sekarang'],0,',','.') ?></strong></td>
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

    </div><!-- /tab-laporan -->

  </div><!-- /main -->

  <!-- Modal Konfirmasi Hapus Riwayat -->
  <div id="modalHapusRiwayat" style="
    display:none; position:fixed; inset:0; z-index:10000;
    background:rgba(0,0,0,.45); backdrop-filter:blur(4px);
    align-items:center; justify-content:center;
  ">
    <div style="
      background:#fff; border-radius:20px; padding:32px 28px; width:340px;
      box-shadow:0 20px 60px rgba(0,0,0,.2); text-align:center;
      animation: popInModal .3s cubic-bezier(.16,1,.3,1);
    ">
      <div style="
        width:56px; height:56px; border-radius:14px; margin:0 auto 16px;
        background:rgba(239,68,68,.1); display:flex; align-items:center; justify-content:center;
      ">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
          <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
        </svg>
      </div>
      <h3 style="margin:0 0 8px;font-size:17px;font-weight:800;color:#1e293b;">Hapus Riwayat?</h3>
      <p style="margin:0 0 24px;font-size:13.5px;color:#64748b;line-height:1.5;">
        Tindakan ini <strong>tidak dapat dibatalkan</strong>.<br>Riwayat akan dihapus permanen.
      </p>
      <div style="display:flex;gap:10px;">
        <button id="hapusRiwayatBatal" style="
          flex:1; padding:11px; border:1.5px solid #e2e8f0; background:#fff;
          border-radius:11px; font-size:14px; font-weight:600; color:#64748b; cursor:pointer; transition:.2s;
        " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
          Batal
        </button>
        <button id="hapusRiwayatOk" style="
          flex:1; padding:11px; border:none;
          background:linear-gradient(135deg,#dc2626,#ef4444);
          border-radius:11px; font-size:14px; font-weight:700; color:#fff; cursor:pointer;
          box-shadow:0 4px 12px rgba(220,38,38,.3); transition:.2s;
        " onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
          Ya, Hapus
        </button>
      </div>
    </div>
  </div>
  <style>
    @keyframes popInModal {
      from { transform: scale(.88); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }
  </style>
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

  <!-- Notif Stok -->
  <div id="stokToastMgr" class="notif-toast" onclick="window.location='inventory.php'">
    <div id="stokToastMgrIcon" style="background:rgba(239,68,68,.15);color:#ef4444;padding:10px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>
    <div style="flex-grow:1;display:flex;flex-direction:column;gap:3px;">
      <div id="stokToastMgrLabel" style="font-size:11px;font-weight:700;color:#ef4444;letter-spacing:.8px;text-transform:uppercase;">Stok Habis</div>
      <div id="stokToastMgrMsg" style="font-size:13px;color:#e5e5ea;font-weight:500;line-height:1.4;"></div>
    </div>
    <div style="color:#94a3b8;font-size:10px;flex-shrink:0;">Tap →</div>
  </div>

  <script>feather.replace();</script>
  <script src="../js/admin.js"></script>
  <script>
  // ── Tab switch ──
  function switchTab(name, btn) {
    document.querySelectorAll('.inv-tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.inv-tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
  }

  // Saat halaman dimuat, aktifkan tab sesuai URL
  (function() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab && document.getElementById('tab-' + tab)) {
      document.querySelectorAll('.inv-tab-content').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.inv-tab-btn').forEach(el => el.classList.remove('active'));
      document.getElementById('tab-' + tab).classList.add('active');
      // Aktifkan tombol tab yang sesuai
      document.querySelectorAll('.inv-tab-btn').forEach(btn => {
        if (btn.getAttribute('onclick')?.includes("'" + tab + "'")) btn.classList.add('active');
      });
    }
  })();

  // ── Filter tabel stok/riwayat ──
  function filterTable(tableId, q) {
    const query = q.toLowerCase().trim();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(tr => {
      tr.style.display = (!query || tr.dataset.nama.includes(query)) ? '' : 'none';
    });
  }

  // ── Filter tabel rekap laporan ──
  function filterRekap() {
    const q = document.getElementById('rekapSearch').value.toLowerCase();
    document.querySelectorAll('#rekapTable tbody tr').forEach(tr => {
      tr.style.display = (!q || tr.dataset.nama.includes(q)) ? '' : 'none';
    });
  }

  // ── Toggle filter input laporan ──
  function toggleFilterInputs() {
    const p = document.getElementById('periodeSelect').value;
    ['Harian','Mingguan','Bulanan','Custom'].forEach(n => {
      const el = document.getElementById('input' + n);
      if (el) el.style.display = p === n.toLowerCase() ? 'flex' : 'none';
    });
  }

  // ── Export PDF (jsPDF + autoTable) ──
  function exportPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const periodeLabel = <?= json_encode($label_periode) ?>;
    const tanggal = new Date().toLocaleDateString('id-ID', { day:'2-digit', month:'long', year:'numeric' });
    const namaFile = 'Laporan_Stok_' + periodeLabel.replace(/[^a-z0-9]/gi,'_') + '.pdf';

    doc.setFontSize(16); doc.setFont('helvetica','bold'); doc.setTextColor(30,41,59);
    doc.text('LAPORAN STOK WARKOP SERAYA', 148.5, 16, { align:'center' });
    doc.setFontSize(9); doc.setFont('helvetica','normal'); doc.setTextColor(100,116,139);
    doc.text(periodeLabel + '   |   Dicetak: ' + tanggal, 148.5, 22, { align:'center' });
    doc.setDrawColor(220,20,60); doc.setLineWidth(0.5); doc.line(14, 25, 283, 25);

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
      const x = bx + i*(bw+gap);
      doc.setFillColor(...b.bg); doc.setDrawColor(220,232,240);
      doc.roundedRect(x, by, bw, bh, 3, 3, 'FD');
      doc.setFontSize(14); doc.setFont('helvetica','bold'); doc.setTextColor(...b.color);
      doc.text(b.val, x+bw/2, by+8, { align:'center' });
      doc.setFontSize(7); doc.setFont('helvetica','normal'); doc.setTextColor(100,116,139);
      doc.text(b.label, x+bw/2, by+12, { align:'center' });
    });

    const rows = [];
    document.querySelectorAll('#rekapTable tbody tr').forEach(tr => {
      if (tr.style.display === 'none') return;
      const c = tr.querySelectorAll('td');
      rows.push([c[0]?.innerText.trim()||'',c[1]?.innerText.trim()||'',c[2]?.innerText.trim()||'—',
        c[3]?.innerText.trim()||'—',c[4]?.innerText.trim()||'—',c[5]?.innerText.trim()||'—',
        c[6]?.innerText.trim()||'',c[7]?.innerText.trim()||'—']);
    });

    doc.autoTable({
      head: [['Nama Barang','Satuan','Stok Awal','+ Masuk','- Keluar','~ Koreksi','Stok Saat Ini','Transaksi']],
      body: rows,
      startY: by+bh+6,
      styles: { fontSize:9, cellPadding:3, valign:'middle' },
      headStyles: { fillColor:[220,20,60], textColor:255, fontStyle:'bold', fontSize:8.5, halign:'center' },
      columnStyles: {
        0:{halign:'left',cellWidth:50}, 1:{halign:'center',cellWidth:20},
        2:{halign:'center',cellWidth:28}, 3:{halign:'center',cellWidth:22,textColor:[5,150,105]},
        4:{halign:'center',cellWidth:22,textColor:[220,38,38]}, 5:{halign:'center',cellWidth:24,textColor:[217,119,6]},
        6:{halign:'center',cellWidth:30}, 7:{halign:'center',cellWidth:22},
      },
      alternateRowStyles: { fillColor:[250,251,252] },
      margin: { left:14, right:14 },
    });

    const pages = doc.internal.getNumberOfPages();
    for (let i=1; i<=pages; i++) {
      doc.setPage(i); doc.setFontSize(7); doc.setTextColor(148,163,184);
      doc.text('WARKOP SERAYA — LAPORAN STOK INVENTORY', 14, doc.internal.pageSize.height-6);
      doc.text('Hal '+i+' / '+pages, 283, doc.internal.pageSize.height-6, { align:'right' });
    }
    doc.save(namaFile);
  }

  // ── Export Excel ──
  function exportExcel() {
    const periode = <?= json_encode($label_periode) ?>;
    const tanggal = '<?= date('d/m/Y H:i') ?>';
    const rows = document.querySelectorAll('#rekapTable tbody tr');
    let tableRows = '';
    rows.forEach(tr => {
      if (tr.style.display === 'none') return;
      tableRows += '<tr>';
      tr.querySelectorAll('td').forEach(td => { tableRows += '<td>' + td.innerText.trim() + '</td>'; });
      tableRows += '</tr>';
    });
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
      <head><meta charset="UTF-8">
      <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Laporan Stok</x:Name>
      <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
      <style>td{border:1px solid #ccc;padding:6px 10px;font-size:12px;}th{background:#dc143c;color:#fff;font-weight:bold;padding:8px 10px;border:1px solid #be1234;}.title{font-size:16px;font-weight:bold;}</style>
      </head><body><table>
      <tr><td class="title" colspan="8">Warkop Seraya — Laporan Stok</td></tr>
      <tr><td colspan="8">${periode} | Dicetak: ${tanggal}</td></tr><tr><td colspan="8"></td></tr>
      <tr><th>Nama Barang</th><th>Satuan</th><th>Stok Awal</th><th>+ Masuk</th><th>- Keluar</th><th>~ Koreksi</th><th>Stok Saat Ini</th><th>Transaksi</th></tr>
      ${tableRows}</table></body></html>`;
    const blob = new Blob([html], { type:'application/vnd.ms-excel;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'laporan_stok_' + periode.replace(/[^a-z0-9]/gi,'_') + '.xls';
    a.click(); URL.revokeObjectURL(url);
  }

  // ── Hapus riwayat ──
  let _hapusRiwayatId = null;

  function hapusRiwayat(id) {
    _hapusRiwayatId = id;
    const modal = document.getElementById('modalHapusRiwayat');
    modal.style.display = 'flex';
  }

  document.getElementById('hapusRiwayatBatal').addEventListener('click', () => {
    document.getElementById('modalHapusRiwayat').style.display = 'none';
    _hapusRiwayatId = null;
  });

  document.getElementById('modalHapusRiwayat').addEventListener('click', e => {
    if (e.target === e.currentTarget) {
      e.currentTarget.style.display = 'none';
      _hapusRiwayatId = null;
    }
  });

  document.getElementById('hapusRiwayatOk').addEventListener('click', () => {
    if (!_hapusRiwayatId) return;
    const btn = document.getElementById('hapusRiwayatOk');
    btn.textContent = 'Menghapus...';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'hapus_riwayat');
    fd.append('id_riwayat', _hapusRiwayatId);
    fetch('../admin_dapur/inventory_proses.php', { method:'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        document.getElementById('modalHapusRiwayat').style.display = 'none';
        btn.textContent = 'Ya, Hapus';
        btn.disabled = false;
        _hapusRiwayatId = null;
        if (d.status === 'success') location.reload();
        else alert(d.message);
      })
      .catch(() => {
        btn.textContent = 'Ya, Hapus';
        btn.disabled = false;
      });
  });

  // ── Notifikasi stok ──
  let lastStokHashMgr = null;
  function showStokToastMgr(data) {
    const toast = document.getElementById('stokToastMgr');
    const label = document.getElementById('stokToastMgrLabel');
    const msg   = document.getElementById('stokToastMgrMsg');
    const icon  = document.getElementById('stokToastMgrIcon');
    if (!toast) return;
    if (data.habis > 0) {
      toast.classList.remove('warning'); toast.style.borderLeftColor='#ef4444';
      icon.style.background='rgba(239,68,68,.15)'; icon.style.color='#ef4444';
      label.style.color='#ef4444'; label.textContent=data.habis+' Stok HABIS';
    } else {
      toast.classList.add('warning'); toast.style.borderLeftColor='#f59e0b';
      icon.style.background='rgba(245,158,11,.15)'; icon.style.color='#f59e0b';
      label.style.color='#f59e0b'; label.textContent=data.menipis+' Stok Menipis';
    }
    const contoh = data.items.slice(0,2).map(i=>i.nama).join(', ');
    msg.textContent = contoh + (data.total>2?' +'+(data.total-2)+' lainnya':'') + ' — Tap untuk lihat';
    toast.classList.remove('hide'); toast.style.display='flex';
    setTimeout(()=>{toast.classList.add('hide');setTimeout(()=>{toast.style.display='none';},400);},7000);
  }
  function updateInventoryBadgeMgr(total) {
    const link = document.querySelector('.sidebar a[href="inventory.php"]');
    if (!link) return;
    let badge = link.querySelector('.sidebar-stok-badge');
    if (total > 0) {
      if (!badge) { badge=document.createElement('span'); badge.className='sidebar-stok-badge'; link.appendChild(badge); }
      badge.textContent = total > 99 ? '99+' : total;
    } else if (badge) badge.remove();
  }
  async function checkStokAlertMgr() {
    try {
      const res=await fetch('../get_stok_alert.php'), data=await res.json();
      const hash=JSON.stringify(data.items.map(i=>i.nama+i.stok));
      updateInventoryBadgeMgr(data.total);
      if (data.ada_alert) {
        if (lastStokHashMgr===null) { lastStokHashMgr=hash; }
        else if (hash!==lastStokHashMgr) { lastStokHashMgr=hash; showStokToastMgr(data); }
      } else lastStokHashMgr=hash;
    } catch(e) { console.warn(e); }
  }
  checkStokAlertMgr();
  setInterval(checkStokAlertMgr, 60000);
  </script>
</body>
</html>
