<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dapur') {
    header("Location: ../login.php");
    exit;
}

$id_admin = $_SESSION['id_admin'] ?? null;

// ── Ambil semua stok ──
$stok_list = mysqli_query($conn, "
    SELECT s.*, p.nama_produk
    FROM stok_barang s
    LEFT JOIN produk p ON s.id_produk = p.id_produk
    ORDER BY s.nama_barang ASC
");

// ── Ambil semua produk untuk dropdown ──
$produk_list = mysqli_query($conn, "SELECT id_produk, nama_produk FROM produk ORDER BY nama_produk ASC");

// ── Hitung ringkasan ──
$total_item   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM stok_barang"))['c'];
$stok_tipis   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM stok_barang WHERE stok_saat_ini <= stok_minimum AND stok_saat_ini > 0"))['c'];
$stok_habis   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM stok_barang WHERE stok_saat_ini = 0"))['c'];

// ── Ambil barang tipis/habis untuk notifikasi ──
$notif_stok = mysqli_query($conn, "
    SELECT nama_barang, stok_saat_ini, stok_minimum, satuan
    FROM stok_barang
    WHERE stok_saat_ini <= stok_minimum
    ORDER BY stok_saat_ini ASC
    LIMIT 10
");
$notif_list = [];
while ($n = mysqli_fetch_assoc($notif_stok)) $notif_list[] = $n;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dapur - Inventory</title>
  <link rel="stylesheet" href="../css/dapur.css"/>
  <link rel="stylesheet" href="../css/logout.css"/>
  <script src="../js/feather.min.js"></script>
  <style>
    /* ── Layout ── */
    .page-inventory .main {
      display: block;
      padding: 28px 32px;
      min-height: 100vh;
      box-sizing: border-box;
    }

    /* ── Stats ── */
    .inv-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 24px;
      animation: fadeIn .5s ease;
    }
    .inv-stat-card {
      background: #fff;
      border-radius: 16px;
      padding: 20px 22px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,.06);
      border: 1.5px solid #f1f5f9;
    }
    .inv-stat-icon {
      width: 48px; height: 48px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .inv-stat-icon svg { width: 22px; height: 22px; stroke-width: 2; }
    .inv-stat-card.total  .inv-stat-icon { background: rgba(220,20,60,.09); color: #dc143c; }
    .inv-stat-card.tipis  .inv-stat-icon { background: rgba(245,158,11,.1); color: #d97706; }
    .inv-stat-card.habis  .inv-stat-icon { background: rgba(239,68,68,.1);  color: #ef4444; }
    .inv-stat-info p  { font-size: 28px; font-weight: 800; color: #1e293b; margin: 0; line-height: 1; }
    .inv-stat-info span { font-size: 12.5px; color: #64748b; font-weight: 500; }

    /* ── Toolbar ── */
    .inv-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 16px;
      gap: 12px;
      flex-wrap: wrap;
    }
    .inv-toolbar h2 {
      font-size: 17px; font-weight: 700; color: #1e293b; margin: 0;
    }
    .inv-toolbar .actions { display: flex; gap: 10px; }
    .btn-inv {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 16px; border-radius: 10px; border: none;
      font-size: 13px; font-weight: 700; cursor: pointer;
      transition: all .2s ease;
    }
    .btn-inv svg { width: 15px; height: 15px; stroke-width: 2.5; }
    .btn-inv.primary { background: linear-gradient(135deg, #dc143c, #ff4d6d); color: #fff; box-shadow: 0 4px 12px rgba(220,20,60,.25); }
    .btn-inv.primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220,20,60,.35); }
    .btn-inv.success { background: linear-gradient(135deg, #10b981, #059669); color: #fff; box-shadow: 0 4px 12px rgba(16,185,129,.25); }
    .btn-inv.success:hover { transform: translateY(-2px); }
    .btn-inv.warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 4px 12px rgba(245,158,11,.25); }
    .btn-inv.warning:hover { transform: translateY(-2px); }
    .btn-inv.secondary { background: #f1f5f9; color: #475569; border: 1.5px solid #e2e8f0; }
    .btn-inv.secondary:hover { background: #e2e8f0; }

    /* ── Search ── */
    .inv-search {
      padding: 9px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px;
      font-size: 13.5px; width: 220px; outline: none; transition: .2s;
    }
    .inv-search:focus { border-color: #dc143c; box-shadow: 0 0 0 3px rgba(220,20,60,.08); }

    /* ── Table ── */
    .inv-table-wrap {
      background: #fff; border-radius: 18px;
      box-shadow: 0 2px 16px rgba(0,0,0,.06);
      border: 1px solid #f1f5f9; overflow: hidden;
      animation: fadeIn .5s ease;
    }
    .inv-table { width: 100%; border-collapse: collapse; }
    .inv-table thead th {
      background: linear-gradient(135deg, #dc143c, #be1234);
      color: #fff; padding: 13px 16px;
      font-size: 11.5px; font-weight: 700;
      letter-spacing: .7px; text-transform: uppercase;
      text-align: center; border: none; white-space: nowrap;
    }
    .inv-table thead th:first-child { text-align: left; padding-left: 20px; }
    .inv-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
    .inv-table tbody tr:last-child { border-bottom: none; }
    .inv-table tbody tr:nth-child(even) { background: #fafbfc; }
    .inv-table tbody tr:hover { background: #fff5f7; }
    .inv-table td {
      padding: 13px 16px; font-size: 13.5px;
      color: #334155; text-align: center; vertical-align: middle;
    }
    .inv-table td:first-child { text-align: left; padding-left: 20px; font-weight: 600; color: #1e293b; }

    /* ── Badge stok ── */
    .stok-badge {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 4px 12px; border-radius: 20px;
      font-size: 12px; font-weight: 700; white-space: nowrap;
    }
    .stok-badge::before { content:''; width:7px; height:7px; border-radius:50%; flex-shrink:0; }
    .stok-badge.aman  { color:#059669; background:rgba(16,185,129,.1); border:1.5px solid rgba(16,185,129,.2); }
    .stok-badge.aman::before { background:#10b981; }
    .stok-badge.tipis { color:#d97706; background:rgba(245,158,11,.1); border:1.5px solid rgba(245,158,11,.2); }
    .stok-badge.tipis::before { background:#f59e0b; }
    .stok-badge.habis { color:#dc2626; background:rgba(239,68,68,.1); border:1.5px solid rgba(239,68,68,.2); }
    .stok-badge.habis::before { background:#ef4444; }

    /* ── Action buttons in table ── */
    .tbl-actions { display:flex; gap:6px; justify-content:center; }
    .tbl-btn {
      width:30px; height:30px; border-radius:8px; border:none;
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:.2s ease;
    }
    .tbl-btn svg { width:14px; height:14px; stroke-width:2.2; }
    .tbl-btn.add  { background:rgba(16,185,129,.1);  color:#059669; }
    .tbl-btn.add:hover  { background:#10b981; color:#fff; }
    .tbl-btn.edit { background:rgba(59,130,246,.1);  color:#2563eb; }
    .tbl-btn.edit:hover { background:#3b82f6; color:#fff; }
    .tbl-btn.del  { background:rgba(239,68,68,.1);   color:#dc2626; }
    .tbl-btn.del:hover  { background:#ef4444; color:#fff; }
    .tbl-btn.resep { background:rgba(139,92,246,.1); color:#7c3aed; }
    .tbl-btn.resep:hover { background:#8b5cf6; color:#fff; }
    .tbl-btn.history { background:rgba(100,116,139,.1); color:#475569; }
    .tbl-btn.history:hover { background:#475569; color:#fff; }
    .tbl-btn.hapus { background:rgba(239,68,68,.1); color:#dc2626; }
    .tbl-btn.hapus:hover { background:#ef4444; color:#fff; }
    /* Disable state untuk hapus jika stok > 0 */
    .tbl-btn.hapus:disabled {
      background:rgba(100,116,139,.07); color:#cbd5e1;
      cursor:not-allowed; pointer-events:none;
    }

    /* ── Modals ── */
    .inv-modal-overlay {
      display:none; position:fixed; inset:0; z-index:9999;
      background:rgba(0,0,0,.45); backdrop-filter:blur(4px);
      align-items:center; justify-content:center;
    }
    .inv-modal-overlay.show { display:flex; }
    .inv-modal {
      background:#fff; border-radius:22px; padding:32px 28px;
      width:100%; max-width:460px; max-height:90vh; overflow-y:auto;
      box-shadow:0 24px 60px rgba(0,0,0,.2);
      animation: popIn .3s cubic-bezier(.16,1,.3,1);
      position:relative;
    }
    .inv-modal h3 {
      font-size:18px; font-weight:800; color:#1e293b;
      margin:0 0 20px; padding-bottom:14px;
      border-bottom:1px solid #f1f5f9;
      display:flex; align-items:center; gap:10px;
    }
    .inv-modal h3 svg { width:20px; height:20px; color:#dc143c; }
    .inv-modal-close {
      position:absolute; top:18px; right:18px;
      width:30px; height:30px; border-radius:50%;
      background:#f1f5f9; border:none; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      font-size:16px; color:#64748b; transition:.2s;
    }
    .inv-modal-close:hover { background:#dc143c; color:#fff; }

    /* ── Form ── */
    .form-row { margin-bottom:16px; }
    .form-row label {
      display:block; font-size:11.5px; font-weight:700;
      text-transform:uppercase; letter-spacing:.5px;
      color:#64748b; margin-bottom:7px;
    }
    .form-row input, .form-row select, .form-row textarea {
      width:100%; padding:11px 14px;
      border:1.5px solid #e2e8f0; border-radius:12px;
      font-size:14px; color:#334155; background:#f8fafc;
      outline:none; transition:.2s; box-sizing:border-box;
    }
    .form-row input:focus, .form-row select:focus, .form-row textarea:focus {
      border-color:#dc143c; background:#fff;
      box-shadow:0 0 0 3px rgba(220,20,60,.08);
    }
    .form-row textarea { resize:vertical; min-height:70px; }
    .form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .modal-actions { display:flex; gap:10px; margin-top:22px; }
    .modal-actions button { flex:1; padding:12px; border-radius:12px; font-size:14px; font-weight:700; cursor:pointer; border:none; transition:.2s; }
    .modal-actions .btn-submit { background:linear-gradient(135deg,#dc143c,#ff4d6d); color:#fff; box-shadow:0 4px 14px rgba(220,20,60,.25); }
    .modal-actions .btn-submit:hover { transform:translateY(-2px); }
    .modal-actions .btn-cancel { background:#f1f5f9; color:#64748b; }
    .modal-actions .btn-cancel:hover { background:#e2e8f0; }

    /* ── Resep list ── */
    .resep-item {
      display:flex; align-items:center; justify-content:space-between;
      padding:10px 14px; background:#f8fafc;
      border-radius:10px; margin-bottom:8px;
      border:1px solid #f1f5f9;
    }
    .resep-item span { font-size:13.5px; color:#334155; }
    .resep-item strong { font-size:13px; color:#dc143c; }
    .resep-del-btn {
      width:26px; height:26px; border-radius:50%; border:none;
      background:rgba(239,68,68,.1); color:#dc2626;
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:.2s;
    }
    .resep-del-btn:hover { background:#ef4444; color:#fff; }
    .resep-del-btn svg { width:13px; height:13px; stroke-width:2.5; }

    /* ── Riwayat ── */
    .riwayat-item {
      display:flex; align-items:center; gap:14px;
      padding:10px 0; border-bottom:1px solid #f1f5f9;
    }
    .riwayat-item:last-child { border-bottom:none; }
    .riwayat-icon {
      width:36px; height:36px; border-radius:10px; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
    }
    .riwayat-icon svg { width:16px; height:16px; stroke-width:2.2; }
    .riwayat-icon.masuk  { background:rgba(16,185,129,.12); color:#059669; }
    .riwayat-icon.keluar { background:rgba(239,68,68,.1);   color:#dc2626; }
    .riwayat-icon.koreksi{ background:rgba(245,158,11,.1);  color:#d97706; }
    .riwayat-info { flex:1; }
    .riwayat-info p  { margin:0; font-size:13.5px; font-weight:600; color:#1e293b; }
    .riwayat-info small { font-size:12px; color:#94a3b8; }
    .riwayat-jumlah { font-size:14px; font-weight:700; }
    .riwayat-jumlah.masuk  { color:#10b981; }
    .riwayat-jumlah.keluar { color:#ef4444; }
    .riwayat-jumlah.koreksi{ color:#f59e0b; }

    @keyframes popIn {
      from { transform:scale(.88); opacity:0; }
      to   { transform:scale(1);   opacity:1; }
    }
    @keyframes fadeIn {
      from { opacity:0; transform:translateY(10px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* loading spinner */
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Alert bar stok tipis ── */
    .inv-alert-bar {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      background: #fffbeb;
      border: 1.5px solid #fde68a;
      border-left: 4px solid #f59e0b;
      border-radius: 14px;
      padding: 14px 18px;
      margin-bottom: 20px;
      animation: fadeIn .4s ease;
    }
    .inv-alert-icon {
      color: #d97706;
      flex-shrink: 0;
      margin-top: 2px;
    }
    .inv-alert-icon svg { width:18px; height:18px; stroke-width:2.2; }
    .inv-alert-content {
      flex: 1;
      font-size: 13.5px;
      color: #92400e;
      line-height: 1.8;
      flex-wrap: wrap;
    }
    .inv-alert-chip {
      display: inline-flex;
      align-items: center;
      padding: 2px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      margin: 2px 4px;
    }
    .inv-alert-chip.tipis {
      background: rgba(245,158,11,.15);
      color: #b45309;
      border: 1px solid rgba(245,158,11,.3);
    }
    .inv-alert-chip.habis {
      background: rgba(239,68,68,.12);
      color: #b91c1c;
      border: 1px solid rgba(239,68,68,.25);
    }
    .inv-alert-close {
      background: none;
      border: none;
      cursor: pointer;
      color: #d97706;
      padding: 2px;
      flex-shrink: 0;
      opacity: .7;
      transition: .2s;
    }
    .inv-alert-close:hover { opacity: 1; }
    .inv-alert-close svg { width:16px; height:16px; }
  </style>
</head>
<body class="page-inventory">
  <button class="menu-toggle" id="menu-toggle"><i data-feather="menu"></i></button>

  <div class="sidebar">
    <h1>WARKOP<span> SERAYA</span></h1>
    <h2>DAPUR</h2>
    <ul>
      <li><a href="dashboard.php"><i data-feather="home"></i> Beranda</a></li>
      <li><a href="pesanan.php"><i data-feather="menu"></i> Pesanan</a></li>
      <li><a href="menu_kosong.php"><i data-feather="x-circle"></i> Menu Tidak Tersedia</a></li>
      <li><a href="kelola_menu.php"><i data-feather="settings"></i> Kelola Menu</a></li>
      <li><a href="inventory.php" class="active"><i data-feather="package"></i> Inventory</a></li>
      <li><a href="riwayat_pesanan.php"><i data-feather="clock"></i> Riwayat Pesanan</a></li>
      <li><a href="#" id="logoutBtn"><i data-feather="log-out"></i> Logout</a></li>
    </ul>
  </div>

  <div class="main">

    <!-- ── Notifikasi stok tipis/habis ── -->
    <?php if (!empty($notif_list)): ?>
    <div class="inv-alert-bar">
      <div class="inv-alert-icon">
        <i data-feather="alert-triangle"></i>
      </div>
      <div class="inv-alert-content">
        <strong><?= count($notif_list) ?> barang</strong> perlu diperhatikan:
        <?php foreach ($notif_list as $n):
          $cls = $n['stok_saat_ini'] == 0 ? 'habis' : 'tipis';
          $lbl = $n['stok_saat_ini'] == 0 ? 'HABIS' : 'TIPIS';
        ?>
        <span class="inv-alert-chip <?= $cls ?>">
          <?= htmlspecialchars($n['nama_barang']) ?>
          (<?= number_format($n['stok_saat_ini'],0,',','.') ?>/<?= number_format($n['stok_minimum'],0,',','.') ?> <?= $n['satuan'] ?>)
          — <?= $lbl ?>
        </span>
        <?php endforeach; ?>
      </div>
      <button class="inv-alert-close" onclick="this.closest('.inv-alert-bar').remove()">
        <i data-feather="x"></i>
      </button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="inv-stats">
      <div class="inv-stat-card total">
        <div class="inv-stat-icon"><i data-feather="package"></i></div>
        <div class="inv-stat-info">
          <p><?= $total_item ?></p>
          <span>Total Item</span>
        </div>
      </div>
      <div class="inv-stat-card tipis">
        <div class="inv-stat-icon"><i data-feather="alert-triangle"></i></div>
        <div class="inv-stat-info">
          <p><?= $stok_tipis ?></p>
          <span>Stok Tipis</span>
        </div>
      </div>
      <div class="inv-stat-card habis">
        <div class="inv-stat-icon"><i data-feather="x-circle"></i></div>
        <div class="inv-stat-info">
          <p><?= $stok_habis ?></p>
          <span>Stok Habis</span>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="inv-toolbar">
      <h2>Daftar Stok Barang</h2>
      <div class="actions">
        <input type="text" class="inv-search" id="searchInput" placeholder="Cari barang...">
        <button class="btn-inv success" onclick="openModal('modalTambahStok')">
          <i data-feather="plus-circle"></i> Barang Masuk
        </button>
        <button class="btn-inv primary" onclick="openModal('modalTambahBarang')">
          <i data-feather="plus"></i> Tambah Barang
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="inv-table-wrap">
      <table class="inv-table" id="invTable">
        <thead>
          <tr>
            <th>Nama Barang</th>
            <th>Satuan</th>
            <th>Stok Saat Ini</th>
            <th>Stok Min</th>
            <th>Status</th>
            <th>Link Menu</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php
          mysqli_data_seek($stok_list, 0);
          while ($row = mysqli_fetch_assoc($stok_list)):
            $stok = (float)$row['stok_saat_ini'];
            $min  = (float)$row['stok_minimum'];
            if ($stok == 0) {
              $badge = 'habis'; $label = 'Habis';
            } elseif ($stok <= $min) {
              $badge = 'tipis'; $label = 'Tipis';
            } else {
              $badge = 'aman';  $label = 'Aman';
            }
          ?>
          <tr>
            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
            <td><?= htmlspecialchars($row['satuan']) ?></td>
            <td><strong><?= number_format($stok, 0, ',', '.') ?></strong></td>
            <td><?= number_format($min, 0, ',', '.') ?></td>
            <td><span class="stok-badge <?= $badge ?>"><?= $label ?></span></td>
            <td style="font-size:12.5px; color:#64748b;">
              <?= $row['nama_produk'] ? htmlspecialchars($row['nama_produk']) : '<span style="color:#cbd5e1">—</span>' ?>
            </td>
            <td>
              <div class="tbl-actions">
                <button class="tbl-btn add" title="Tambah Stok"
                  onclick="openTambahStokItem(<?= $row['id_stok'] ?>, <?= htmlspecialchars(json_encode($row['nama_barang'])) ?>, <?= htmlspecialchars(json_encode($row['satuan'])) ?>)">
                  <i data-feather="plus"></i>
                </button>
                <button class="tbl-btn resep" title="Resep"
                  onclick="openResep(<?= $row['id_stok'] ?>, <?= htmlspecialchars(json_encode($row['nama_barang'])) ?>)">
                  <i data-feather="book-open"></i>
                </button>
                <button class="tbl-btn edit" title="Edit"
                  onclick="openEdit(<?= $row['id_stok'] ?>, <?= htmlspecialchars(json_encode($row['nama_barang'])) ?>, <?= htmlspecialchars(json_encode($row['satuan'])) ?>, <?= $row['stok_minimum'] ?>, <?= $row['id_produk'] ?? 'null' ?>, <?= htmlspecialchars(json_encode($row['keterangan'] ?? '')) ?>)">
                  <i data-feather="edit-2"></i>
                </button>
                <button class="tbl-btn del" title="Koreksi Stok"
                  onclick="openKoreksi(<?= $row['id_stok'] ?>, <?= htmlspecialchars(json_encode($row['nama_barang'])) ?>, <?= $stok ?>, <?= htmlspecialchars(json_encode($row['satuan'])) ?>)">
                  <i data-feather="sliders"></i>
                </button>
                <button class="tbl-btn history" title="Riwayat Stok"
                  onclick="openRiwayat(<?= $row['id_stok'] ?>, <?= htmlspecialchars(json_encode($row['nama_barang'])) ?>)">
                  <i data-feather="clock"></i>
                </button>
                <button class="tbl-btn hapus"
                  title="<?= $stok > 0 ? 'Stok masih ada, koreksi ke 0 dulu' : 'Hapus Barang' ?>"
                  <?= $stok > 0 ? 'disabled' : '' ?>
                  onclick="openHapus(<?= $row['id_stok'] ?>, <?= htmlspecialchars(json_encode($row['nama_barang'])) ?>)">
                  <i data-feather="trash-2"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       MODAL: Tambah Barang Baru
  ══════════════════════════════════════════ -->
  <div class="inv-modal-overlay" id="modalTambahBarang">
    <div class="inv-modal">
      <button class="inv-modal-close" onclick="closeModal('modalTambahBarang')">&times;</button>
      <h3><i data-feather="plus-circle"></i> Tambah Barang Baru</h3>
      <form id="formTambahBarang">
        <div class="form-row">
          <label>Nama Barang</label>
          <input type="text" name="nama_barang" placeholder="Contoh: Kopi Liong, Indomie" required>
        </div>
        <div class="form-row-2">
          <div class="form-row" style="margin:0">
            <label>Satuan</label>
            <select name="satuan" required>
              <option value="sachet">Sachet</option>
              <option value="bungkus">Bungkus</option>
              <option value="butir">Butir</option>
              <option value="botol">Botol</option>
              <option value="liter">Liter</option>
              <option value="ml">ml</option>
              <option value="gram">Gram</option>
              <option value="kg">Kg</option>
              <option value="porsi">Porsi</option>
              <option value="pcs">Pcs</option>
            </select>
          </div>
          <div class="form-row" style="margin:0">
            <label>Stok Minimum (Notifikasi)</label>
            <input type="number" name="stok_minimum" placeholder="5" value="5" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <label>Stok Awal</label>
          <input type="number" name="stok_awal" placeholder="0" value="0" min="0" required>
        </div>
        <div class="form-row">
          <label>Link ke Menu (Opsional — untuk sachet)</label>
          <select name="id_produk">
            <option value="">— Tidak dilink —</option>
            <?php
            mysqli_data_seek($produk_list, 0);
            while ($p = mysqli_fetch_assoc($produk_list)):
            ?>
            <option value="<?= $p['id_produk'] ?>"><?= htmlspecialchars($p['nama_produk']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Keterangan (Opsional)</label>
          <textarea name="keterangan" placeholder="Catatan tambahan..."></textarea>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn-submit">Simpan Barang</button>
          <button type="button" class="btn-cancel" onclick="closeModal('modalTambahBarang')">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       MODAL: Barang Masuk (Belanja)
  ══════════════════════════════════════════ -->
  <div class="inv-modal-overlay" id="modalTambahStok">
    <div class="inv-modal">
      <button class="inv-modal-close" onclick="closeModal('modalTambahStok')">&times;</button>
      <h3><i data-feather="shopping-cart"></i> Input Barang Masuk</h3>
      <form id="formTambahStok">
        <div class="form-row">
          <label>Pilih Barang</label>
          <select name="id_stok" id="stokItemSelect" required>
            <option value="">— Pilih Barang —</option>
            <?php
            // Query fresh untuk dropdown barang masuk
            $q_dropdown = "SELECT id_stok, nama_barang, satuan FROM stok_barang ORDER BY nama_barang ASC";
            $r_dropdown  = mysqli_query($conn, $q_dropdown);
            if ($r_dropdown && mysqli_num_rows($r_dropdown) > 0):
              while ($s = mysqli_fetch_assoc($r_dropdown)):
            ?>
            <option value="<?= (int)$s['id_stok'] ?>" data-satuan="<?= htmlspecialchars($s['satuan']) ?>">
              <?= htmlspecialchars($s['nama_barang']) ?> (<?= htmlspecialchars($s['satuan']) ?>)
            </option>
            <?php
              endwhile;
            else:
            ?>
            <option value="" disabled>Belum ada barang — tambah barang dulu</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Jumlah Masuk</label>
          <input type="number" name="jumlah" id="stokJumlah" placeholder="0" min="1" required>
        </div>
        <div class="form-row">
          <label>Keterangan</label>
          <input type="text" name="keterangan" placeholder="Contoh: Belanja Alfamart, Belanja pasar">
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn-submit">Simpan</button>
          <button type="button" class="btn-cancel" onclick="closeModal('modalTambahStok')">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       MODAL: Edit Barang
  ══════════════════════════════════════════ -->
  <div class="inv-modal-overlay" id="modalEdit">
    <div class="inv-modal">
      <button class="inv-modal-close" onclick="closeModal('modalEdit')">&times;</button>
      <h3><i data-feather="edit-2"></i> Edit Barang</h3>
      <form id="formEdit">
        <input type="hidden" name="id_stok" id="editId">
        <div class="form-row">
          <label>Nama Barang</label>
          <input type="text" name="nama_barang" id="editNama" required>
        </div>
        <div class="form-row-2">
          <div class="form-row" style="margin:0">
            <label>Satuan</label>
            <select name="satuan" id="editSatuan" required>
              <option value="sachet">Sachet</option>
              <option value="bungkus">Bungkus</option>
              <option value="butir">Butir</option>
              <option value="botol">Botol</option>
              <option value="liter">Liter</option>
              <option value="ml">ml</option>
              <option value="gram">Gram</option>
              <option value="kg">Kg</option>
              <option value="porsi">Porsi</option>
              <option value="pcs">Pcs</option>
            </select>
          </div>
          <div class="form-row" style="margin:0">
            <label>Stok Minimum</label>
            <input type="number" name="stok_minimum" id="editMin" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <label>Link ke Menu (Opsional)</label>
          <select name="id_produk" id="editProduk">
            <option value="">— Tidak dilink —</option>
            <?php
            mysqli_data_seek($produk_list, 0);
            while ($p = mysqli_fetch_assoc($produk_list)):
            ?>
            <option value="<?= $p['id_produk'] ?>"><?= htmlspecialchars($p['nama_produk']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-row">
          <label>Keterangan</label>
          <textarea name="keterangan" id="editKeterangan" placeholder="Catatan..."></textarea>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn-submit">Update</button>
          <button type="button" class="btn-cancel" onclick="closeModal('modalEdit')">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       MODAL: Koreksi Stok
  ══════════════════════════════════════════ -->
  <div class="inv-modal-overlay" id="modalKoreksi">
    <div class="inv-modal">
      <button class="inv-modal-close" onclick="closeModal('modalKoreksi')">&times;</button>
      <h3><i data-feather="sliders"></i> Koreksi Stok</h3>
      <p id="koreksiInfo" style="font-size:13.5px; color:#64748b; margin:-8px 0 16px;"></p>
      <form id="formKoreksi">
        <input type="hidden" name="id_stok" id="koreksiId">
        <div class="form-row">
          <label>Jenis Koreksi</label>
          <select name="jenis_koreksi" id="jenisKoreksi" required>
            <option value="tambah">Tambah Stok (barang ditemukan/salah hitung)</option>
            <option value="kurang">Kurangi Stok (rusak/kadaluarsa/tumpah)</option>
            <option value="sesuaikan">Sesuaikan ke Angka Tertentu (stok opname)</option>
          </select>
        </div>
        <div class="form-row">
          <label id="koreksiJumlahLabel">Jumlah</label>
          <input type="number" name="jumlah_koreksi" id="jumlahKoreksi" placeholder="0" min="0" step="0.01" required>
        </div>
        <div class="form-row">
          <label>Keterangan Wajib</label>
          <textarea name="keterangan" placeholder="Contoh: Tumpah saat persiapan, Kadaluarsa, Stok opname..." required></textarea>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn-submit">Simpan Koreksi</button>
          <button type="button" class="btn-cancel" onclick="closeModal('modalKoreksi')">Batal</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       MODAL: Riwayat Stok per Barang
  ══════════════════════════════════════════ -->
  <div class="inv-modal-overlay" id="modalRiwayat">
    <div class="inv-modal" style="max-width:520px">
      <button class="inv-modal-close" onclick="closeModal('modalRiwayat')">&times;</button>
      <h3><i data-feather="clock"></i> Riwayat Stok</h3>
      <div id="riwayatContent" style="max-height:360px; overflow-y:auto;">
        <p style="color:#94a3b8; text-align:center; padding:20px 0;">Memuat...</p>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       MODAL: Resep Menu
  ══════════════════════════════════════════ -->
  <div class="inv-modal-overlay" id="modalResep">
    <div class="inv-modal" style="max-width:520px">
      <button class="inv-modal-close" onclick="closeModal('modalResep')">&times;</button>
      <h3><i data-feather="book-open"></i> Resep — <span id="resepNamaBarang"></span></h3>
      <p style="font-size:13px; color:#94a3b8; margin:-10px 0 16px;">
        Daftar menu yang menggunakan bahan ini beserta jumlah yang dipakai per porsi.
      </p>
      <div id="resepList" style="margin-bottom:20px;"></div>
      <hr style="border:none; border-top:1px solid #f1f5f9; margin-bottom:20px;">
      <h4 style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 14px;">Tambah Resep</h4>
      <form id="formResep">
        <input type="hidden" name="id_stok" id="resepIdStok">
        <div class="form-row-2">
          <div class="form-row" style="margin:0">
            <label>Menu</label>
            <select name="id_produk" required>
              <option value="">— Pilih Menu —</option>
              <?php
              mysqli_data_seek($produk_list, 0);
              while ($p = mysqli_fetch_assoc($produk_list)):
              ?>
              <option value="<?= $p['id_produk'] ?>"><?= htmlspecialchars($p['nama_produk']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-row" style="margin:0">
            <label>Jumlah per Porsi</label>
            <input type="number" name="jumlah_pakai" placeholder="1" min="0.1" step="0.1" required>
          </div>
        </div>
        <div class="modal-actions" style="margin-top:14px;">
          <button type="submit" class="btn-submit">Tambah Resep</button>
          <button type="button" class="btn-cancel" onclick="closeModal('modalResep')">Tutup</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       MODAL: Hapus Barang — Step 1 (konfirmasi)
  ══════════════════════════════════════════ -->
  <div class="inv-modal-overlay" id="modalHapus">
    <div class="inv-modal" style="max-width:420px">
      <button class="inv-modal-close" onclick="closeModal('modalHapus')">&times;</button>
      <h3 style="color:#dc2626;">
        <i data-feather="trash-2"></i> Hapus Barang
      </h3>

      <!-- Peringatan -->
      <div style="background:rgba(239,68,68,.06);border:1.5px solid rgba(239,68,68,.2);border-radius:12px;padding:14px 16px;margin-bottom:20px;">
        <div style="display:flex;gap:10px;align-items:flex-start;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
          <div>
            <p style="font-size:13.5px;font-weight:700;color:#b91c1c;margin:0 0 4px;">Tindakan ini tidak bisa dibatalkan</p>
            <p style="font-size:13px;color:#64748b;margin:0;line-height:1.5;">
              Semua <strong>riwayat perubahan stok</strong> barang ini juga akan ikut terhapus permanen.
            </p>
          </div>
        </div>
      </div>

      <p style="font-size:14px;color:#334155;margin:0 0 20px;">
        Yakin ingin menghapus barang
        <strong id="hapusNama" style="color:#1e293b;"></strong>?
      </p>

      <!-- Input konfirmasi ketik nama -->
      <div class="form-row">
        <label>Ketik nama barang untuk konfirmasi</label>
        <input type="text" id="hapusKonfirmInput"
          placeholder="Ketik nama barang..."
          oninput="cekKonfirmasiHapus()"
          autocomplete="off">
        <p id="hapusKonfirmHint" style="font-size:11.5px;color:#94a3b8;margin:6px 0 0;"></p>
      </div>

      <div class="modal-actions">
        <button id="hapusConfirmBtn" class="btn-submit"
          style="background:linear-gradient(135deg,#dc2626,#ef4444);box-shadow:0 4px 14px rgba(220,38,38,.25);"
          disabled onclick="eksekusiHapus()">
          Ya, Hapus Permanen
        </button>
        <button type="button" class="btn-cancel" onclick="closeModal('modalHapus')">Batal</button>
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
  // ── Helpers ──
  function openModal(id) {
    document.getElementById(id).classList.add('show');
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    const form = document.getElementById(id)?.querySelector('form');
    if (form) form.reset();
    // Reset riwayat content agar tidak tampil data lama saat buka ulang
    if (id === 'modalRiwayat') {
      document.getElementById('riwayatContent').innerHTML =
        '<p style="color:#94a3b8;text-align:center;padding:20px 0;">Memuat...</p>';
    }
  }

  // Tutup modal klik overlay
  document.querySelectorAll('.inv-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.remove('show');
    });
  });

  // ── Search ──
  document.getElementById('searchInput').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#invTable tbody tr').forEach(row => {
      row.style.display = row.cells[0].textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });

  // ── Koreksi: ubah label sesuai jenis ──
  document.getElementById('jenisKoreksi').addEventListener('change', function() {
    const label = document.getElementById('koreksiJumlahLabel');
    if (this.value === 'sesuaikan') {
      label.textContent = 'Sesuaikan Stok ke Angka (hasil hitung fisik)';
    } else {
      label.textContent = 'Jumlah';
    }
  });

  function openTambahStokItem(id, nama, satuan) {
    openModal('modalTambahStok');
    const sel = document.getElementById('stokItemSelect');
    sel.value = id;
  }

  function openEdit(id, nama, satuan, min, idProduk, ket) {
    document.getElementById('editId').value = id;
    document.getElementById('editNama').value = nama;
    document.getElementById('editSatuan').value = satuan;
    document.getElementById('editMin').value = min;
    document.getElementById('editProduk').value = idProduk || '';
    document.getElementById('editKeterangan').value = ket || '';
    openModal('modalEdit');
  }

  function openKoreksi(id, nama, stok, satuan) {
    document.getElementById('koreksiId').value = id;
    document.getElementById('koreksiInfo').textContent =
      nama + ' — Stok saat ini: ' + stok + ' ' + satuan;
    openModal('modalKoreksi');
  }

  function openResep(idStok, nama) {
    document.getElementById('resepIdStok').value = idStok;
    document.getElementById('resepNamaBarang').textContent = nama;
    loadResep(idStok);
    openModal('modalResep');
  }

  // ── Riwayat Stok ──
  function openRiwayat(idStok, nama) {
    const content = document.getElementById('riwayatContent');
    // Update judul modal
    document.querySelector('#modalRiwayat h3').innerHTML =
      '<i data-feather="clock"></i> Riwayat — ' + escapeHtml(nama);
    // Reset ke loading state
    content.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:center;gap:10px;padding:32px 0;color:#94a3b8;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 1s linear infinite">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        Memuat riwayat...
      </div>`;
    openModal('modalRiwayat');
    feather.replace(); // setelah openModal agar icon di judul ter-render
    loadRiwayat(idStok);
  }

  function loadRiwayat(idStok) {
    fetch('inventory_proses.php?action=get_riwayat&id_stok=' + idStok)
      .then(r => r.json())
      .then(data => {
        const el = document.getElementById('riwayatContent');
        if (!data.length) {
          el.innerHTML = `
            <div style="text-align:center;padding:32px 0;">
              <div style="font-size:32px;margin-bottom:10px;">📋</div>
              <p style="color:#94a3b8;font-size:13.5px;margin:0;">Belum ada riwayat stok untuk barang ini.</p>
            </div>`;
          return;
        }

        const jenis_icon = {
          masuk:   { icon: 'trending-up',   color: '#10b981', bg: 'rgba(16,185,129,.12)',  sign: '+' },
          keluar:  { icon: 'trending-down',  color: '#ef4444', bg: 'rgba(239,68,68,.10)',   sign: '-' },
          koreksi: { icon: 'sliders',        color: '#f59e0b', bg: 'rgba(245,158,11,.10)',  sign: '~' },
        };

        const sumber_label = {
          pesanan:        'Pesanan',
          belanja:        'Belanja / Masuk',
          koreksi_manual: 'Koreksi Manual',
        };

        el.innerHTML = data.map(r => {
          const cfg   = jenis_icon[r.jenis] || jenis_icon.koreksi;
          const waktu = new Date(r.waktu);
          const tgl   = waktu.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
          const jam   = waktu.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
          const admin = r.nama_admin || 'Sistem';
          const sumber = sumber_label[r.sumber] || r.sumber;
          const ket   = r.keterangan ? r.keterangan : '';

          return `
            <div class="riwayat-item" style="display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid #f1f5f9;">
              <div style="width:36px;height:36px;border-radius:10px;background:${cfg.bg};color:${cfg.color};display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                <i data-feather="${cfg.icon}" style="width:15px;height:15px;stroke-width:2.2;"></i>
              </div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                  <span style="font-size:13px;font-weight:700;color:#1e293b;">${sumber}</span>
                  <span style="font-size:14px;font-weight:800;color:${cfg.color};white-space:nowrap;">
                    ${cfg.sign}${parseFloat(r.jumlah).toLocaleString('id-ID')}
                  </span>
                </div>
                <div style="font-size:11.5px;color:#94a3b8;margin-top:3px;line-height:1.5;">
                  ${ket ? '<span style="color:#64748b;">' + escapeHtml(ket) + '</span> &bull; ' : ''}
                  ${escapeHtml(admin)} &bull; ${tgl}, ${jam}
                </div>
                <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                  <span style="font-size:11px;color:#94a3b8;">Stok:</span>
                  <span style="font-size:11.5px;color:#64748b;font-weight:600;">${parseFloat(r.stok_sebelum).toLocaleString('id-ID')}</span>
                  <span style="font-size:11px;color:#cbd5e1;">→</span>
                  <span style="font-size:11.5px;color:#1e293b;font-weight:700;">${parseFloat(r.stok_sesudah).toLocaleString('id-ID')}</span>
                </div>
              </div>
            </div>`;
        }).join('');

        // hapus border bawah item terakhir
        const items = el.querySelectorAll('.riwayat-item');
        if (items.length) items[items.length - 1].style.borderBottom = 'none';

        feather.replace();
      })
      .catch(() => {
        document.getElementById('riwayatContent').innerHTML =
          '<p style="color:#ef4444;text-align:center;padding:20px 0;">Gagal memuat riwayat.</p>';
      });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function loadResep(idStok) {
    fetch('inventory_proses.php?action=get_resep&id_stok=' + idStok)
      .then(r => r.json())
      .then(data => {
        const el = document.getElementById('resepList');
        if (!data.length) {
          el.innerHTML = '<p style="color:#94a3b8; font-size:13px;">Belum ada resep untuk bahan ini.</p>';
          return;
        }
        el.innerHTML = data.map(r => `
          <div class="resep-item">
            <div>
              <span>${r.nama_produk}</span>
              <strong style="display:block; font-size:12px; margin-top:2px;">
                ${r.jumlah_pakai} ${r.satuan} per porsi
              </strong>
            </div>
            <button class="resep-del-btn" onclick="hapusResep(${r.id_resep}, ${idStok})">
              <i data-feather="trash-2"></i>
            </button>
          </div>
        `).join('');
        feather.replace();
      });
  }

  function hapusResep(idResep, idStok) {
    if (!confirm('Hapus resep ini?')) return;
    const fd = new FormData();
    fd.append('action', 'hapus_resep');
    fd.append('id_resep', idResep);
    fetch('inventory_proses.php', { method:'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.status === 'success') loadResep(idStok);
        else alert(d.message);
      });
  }

  // ── AJAX helper ──
  async function submitForm(formId, action, onSuccess) {
    const form = document.getElementById(formId);
    const fd = new FormData(form);
    fd.append('action', action);
    const res  = await fetch('inventory_proses.php', { method:'POST', body: fd });
    const data = await res.json();
    if (data.status === 'success') {
      onSuccess(data);
    } else {
      alert(data.message || 'Terjadi kesalahan.');
    }
  }

  // ── Submit: Tambah Barang ──
  document.getElementById('formTambahBarang').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('formTambahBarang', 'tambah_barang', () => {
      closeModal('modalTambahBarang');
      location.reload();
    });
  });

  // ── Submit: Barang Masuk ──
  document.getElementById('formTambahStok').addEventListener('submit', e => {
    e.preventDefault();
    const sel = document.getElementById('stokItemSelect');
    if (!sel.value) {
      alert('Pilih barang terlebih dahulu.');
      return;
    }
    submitForm('formTambahStok', 'barang_masuk', () => {
      closeModal('modalTambahStok');
      location.reload();
    });
  });

  // ── Submit: Edit ──
  document.getElementById('formEdit').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('formEdit', 'edit_barang', () => {
      closeModal('modalEdit');
      location.reload();
    });
  });

  // ── Submit: Koreksi ──
  document.getElementById('formKoreksi').addEventListener('submit', e => {
    e.preventDefault();
    submitForm('formKoreksi', 'koreksi_stok', () => {
      closeModal('modalKoreksi');
      location.reload();
    });
  });

  // ── Submit: Tambah Resep ──
  document.getElementById('formResep').addEventListener('submit', e => {
    e.preventDefault();
    const idStok = document.getElementById('resepIdStok').value;
    submitForm('formResep', 'tambah_resep', () => {
      document.getElementById('formResep').reset();
      document.getElementById('resepIdStok').value = idStok;
      loadResep(idStok);
    });
  });

  // ── Hapus Barang ──
  let _hapusId   = null;
  let _hapusNama = '';

  function openHapus(id, nama) {
    _hapusId   = id;
    _hapusNama = nama;
    document.getElementById('hapusNama').textContent = nama;
    document.getElementById('hapusKonfirmInput').value = '';
    document.getElementById('hapusKonfirmHint').textContent =
      'Ketik: ' + nama;
    document.getElementById('hapusConfirmBtn').disabled = true;
    openModal('modalHapus');
    setTimeout(() => document.getElementById('hapusKonfirmInput').focus(), 200);
  }

  function cekKonfirmasiHapus() {
    const val = document.getElementById('hapusKonfirmInput').value.trim();
    const cocok = val.toLowerCase() === _hapusNama.toLowerCase();
    const btn  = document.getElementById('hapusConfirmBtn');
    const hint = document.getElementById('hapusKonfirmHint');
    btn.disabled = !cocok;
    if (val === '') {
      hint.textContent = 'Ketik: ' + _hapusNama;
      hint.style.color = '#94a3b8';
    } else if (cocok) {
      hint.textContent = '✓ Nama cocok';
      hint.style.color = '#10b981';
    } else {
      hint.textContent = 'Nama belum cocok';
      hint.style.color = '#ef4444';
    }
  }

  async function eksekusiHapus() {
    if (!_hapusId) return;
    const btn = document.getElementById('hapusConfirmBtn');
    btn.disabled = true;
    btn.textContent = 'Menghapus...';

    const fd = new FormData();
    fd.append('action', 'hapus_barang');
    fd.append('id_stok', _hapusId);

    try {
      const res  = await fetch('inventory_proses.php', { method:'POST', body:fd });
      const data = await res.json();
      if (data.status === 'success') {
        closeModal('modalHapus');
        location.reload();
      } else {
        alert(data.message || 'Gagal menghapus.');
        btn.disabled = false;
        btn.textContent = 'Ya, Hapus Permanen';
      }
    } catch(e) {
      alert('Terjadi kesalahan jaringan.');
      btn.disabled = false;
      btn.textContent = 'Ya, Hapus Permanen';
    }
  }

  // ── Badge sidebar inventory (konsistensi antar halaman) ──
  (async function() {
    try {
      const r = await fetch('../get_stok_alert.php');
      const d = await r.json();
      const link = document.querySelector('.sidebar a[href="inventory.php"]');
      if (!link || d.total === 0) return;
      const badge = document.createElement('span');
      badge.className = 'sidebar-stok-badge';
      badge.textContent = d.total > 99 ? '99+' : d.total;
      badge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;font-size:10px;font-weight:800;min-width:18px;height:18px;border-radius:9px;padding:0 5px;margin-left:auto;line-height:1;';
      link.appendChild(badge);
    } catch(e) {}
  })();
  </script>
</body>
</html>
