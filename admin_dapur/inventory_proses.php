<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['dapur', 'manager'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_admin = $_SESSION['id_admin'] ?? null;
header('Content-Type: application/json');

// ════════════════════════════════
// GET: ambil resep per bahan
// ════════════════════════════════
if (isset($_GET['action']) && $_GET['action'] === 'get_resep') {
    $id_stok = intval($_GET['id_stok'] ?? 0);
    $res = $conn->prepare("
        SELECT r.id_resep, r.jumlah_pakai, p.nama_produk, s.satuan
        FROM resep_menu r
        JOIN produk p ON r.id_produk = p.id_produk
        JOIN stok_barang s ON r.id_stok = s.id_stok
        WHERE r.id_stok = ?
        ORDER BY p.nama_produk ASC
    ");
    $res->bind_param('i', $id_stok);
    $res->execute();
    echo json_encode($res->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// ════════════════════════════════
// GET: riwayat per barang
// ════════════════════════════════
if (isset($_GET['action']) && $_GET['action'] === 'get_riwayat') {
    $id_stok = intval($_GET['id_stok'] ?? 0);
    $res = $conn->prepare("
        SELECT r.*, a.username AS nama_admin
        FROM riwayat_stok r
        LEFT JOIN admin a ON r.id_admin = a.id_admin
        WHERE r.id_stok = ?
        ORDER BY r.waktu DESC
        LIMIT 30
    ");
    $res->bind_param('i', $id_stok);
    $res->execute();
    echo json_encode($res->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// ════════════════════════════════
// POST actions
// ════════════════════════════════
$action = trim($_POST['action'] ?? '');

// Helper: catat riwayat stok
function catatRiwayat($conn, $id_stok, $id_admin, $id_transaksi, $jenis, $jumlah, $stok_sebelum, $stok_sesudah, $sumber, $keterangan) {
    // Gunakan NULLIF trick agar integer nullable bisa disimpan sebagai NULL
    $stmt = $conn->prepare("
        INSERT INTO riwayat_stok
            (id_stok, id_admin, id_transaksi, jenis, jumlah, stok_sebelum, stok_sesudah, sumber, keterangan)
        VALUES (?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?, ?)
    ");
    // Kirim 0 jika null — NULLIF(0,0) akan menjadi NULL di MySQL
    $aid = $id_admin    ?? 0;
    $tid = $id_transaksi ?? 0;
    $stmt->bind_param('iiisdddss',
        $id_stok, $aid, $tid,
        $jenis, $jumlah, $stok_sebelum, $stok_sesudah,
        $sumber, $keterangan
    );
    $stmt->execute();
}

// ────────────────────────────
// TAMBAH BARANG BARU
// ────────────────────────────
if ($action === 'tambah_barang') {
    $nama      = trim($_POST['nama_barang'] ?? '');
    $satuan    = $_POST['satuan'] ?? 'sachet';
    $min       = floatval($_POST['stok_minimum'] ?? 5);
    $awal      = floatval($_POST['stok_awal'] ?? 0);
    $id_produk = !empty($_POST['id_produk']) ? intval($_POST['id_produk']) : null;
    $ket       = trim($_POST['keterangan'] ?? '');

    if (empty($nama)) {
        echo json_encode(['status' => 'error', 'message' => 'Nama barang wajib diisi.']);
        exit;
    }

    // INSERT: nama_barang(s), satuan(s), stok_saat_ini(d), stok_minimum(d), id_produk(i nullable), keterangan(s)
    $stmt = $conn->prepare("
        INSERT INTO stok_barang (nama_barang, satuan, stok_saat_ini, stok_minimum, id_produk, keterangan)
        VALUES (?, ?, ?, ?, NULLIF(?, 0), ?)
    ");
    $id_produk_val = $id_produk ?? 0;
    $stmt->bind_param('ssddis', $nama, $satuan, $awal, $min, $id_produk_val, $ket);

    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . $conn->error]);
        exit;
    }

    $id_stok_baru = $conn->insert_id;
    if ($awal > 0) {
        catatRiwayat($conn, $id_stok_baru, $id_admin, null, 'masuk', $awal, 0, $awal, 'belanja', 'Stok awal saat barang ditambahkan');
    }

    echo json_encode(['status' => 'success', 'message' => 'Barang berhasil ditambahkan.']);
    exit;
}

// ────────────────────────────
// BARANG MASUK (BELANJA)
// ────────────────────────────
if ($action === 'barang_masuk') {
    $id_stok = intval($_POST['id_stok'] ?? 0);
    $jumlah  = floatval($_POST['jumlah'] ?? 0);
    $ket     = trim($_POST['keterangan'] ?? 'Barang masuk');

    if ($id_stok <= 0 || $jumlah <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pilih barang dan isi jumlah dengan benar.']);
        exit;
    }

    $cek = $conn->prepare("SELECT stok_saat_ini FROM stok_barang WHERE id_stok = ?");
    $cek->bind_param('i', $id_stok);
    $cek->execute();
    $row = $cek->get_result()->fetch_assoc();
    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Barang tidak ditemukan.']);
        exit;
    }

    $stok_sebelum = (float)$row['stok_saat_ini'];
    $stok_sesudah = $stok_sebelum + $jumlah;

    $upd = $conn->prepare("UPDATE stok_barang SET stok_saat_ini = ? WHERE id_stok = ?");
    $upd->bind_param('di', $stok_sesudah, $id_stok);
    $upd->execute();

    catatRiwayat($conn, $id_stok, $id_admin, null, 'masuk', $jumlah, $stok_sebelum, $stok_sesudah, 'belanja', $ket);

    echo json_encode(['status' => 'success', 'message' => 'Stok berhasil ditambah.']);
    exit;
}

// ────────────────────────────
// EDIT BARANG
// ────────────────────────────
if ($action === 'edit_barang') {
    $id_stok   = intval($_POST['id_stok'] ?? 0);
    $nama      = trim($_POST['nama_barang'] ?? '');
    $satuan    = $_POST['satuan'] ?? 'sachet';
    $min       = floatval($_POST['stok_minimum'] ?? 5);
    $id_produk = !empty($_POST['id_produk']) ? intval($_POST['id_produk']) : null;
    $ket       = trim($_POST['keterangan'] ?? '');

    // UPDATE: nama_barang(s), satuan(s), stok_minimum(d), id_produk(i nullable), keterangan(s), id_stok(i)
    $stmt = $conn->prepare("
        UPDATE stok_barang SET nama_barang=?, satuan=?, stok_minimum=?, id_produk=NULLIF(?,0), keterangan=?
        WHERE id_stok=?
    ");
    $id_produk_val = $id_produk ?? 0;
    $stmt->bind_param('ssdisi', $nama, $satuan, $min, $id_produk_val, $ket, $id_stok);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Barang berhasil diupdate.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal update: ' . $conn->error]);
    }
    exit;
}

// ────────────────────────────
// KOREKSI STOK
// ────────────────────────────
if ($action === 'koreksi_stok') {
    $id_stok   = intval($_POST['id_stok'] ?? 0);
    $jenis_kor = $_POST['jenis_koreksi'] ?? 'tambah';
    $jumlah    = floatval($_POST['jumlah_koreksi'] ?? 0);
    $ket       = trim($_POST['keterangan'] ?? '');

    if ($id_stok <= 0 || empty($ket)) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak valid atau keterangan kosong.']);
        exit;
    }
    // Untuk jenis tambah/kurang jumlah harus > 0; untuk sesuaikan boleh 0
    if (in_array($jenis_kor, ['tambah', 'kurang']) && $jumlah <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Jumlah harus lebih dari 0.']);
        exit;
    }
    if ($jenis_kor === 'sesuaikan' && $jumlah < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Jumlah tidak boleh negatif.']);
        exit;
    }

    $cek = $conn->prepare("SELECT stok_saat_ini FROM stok_barang WHERE id_stok = ?");
    $cek->bind_param('i', $id_stok);
    $cek->execute();
    $row = $cek->get_result()->fetch_assoc();
    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Barang tidak ditemukan.']);
        exit;
    }

    $stok_sebelum = (float)$row['stok_saat_ini'];

    if ($jenis_kor === 'tambah') {
        $stok_sesudah = $stok_sebelum + $jumlah;
        $jenis_log    = 'masuk';
    } elseif ($jenis_kor === 'kurang') {
        $stok_sesudah = max(0, $stok_sebelum - $jumlah);
        $jenis_log    = 'keluar';
    } else {
        $stok_sesudah = $jumlah;
        $jenis_log    = 'koreksi';
    }

    $upd = $conn->prepare("UPDATE stok_barang SET stok_saat_ini = ? WHERE id_stok = ?");
    $upd->bind_param('di', $stok_sesudah, $id_stok);
    $upd->execute();

    catatRiwayat($conn, $id_stok, $id_admin, null, $jenis_log, $jumlah, $stok_sebelum, $stok_sesudah, 'koreksi_manual', $ket);

    echo json_encode(['status' => 'success', 'message' => 'Koreksi stok berhasil.']);
    exit;
}

// ────────────────────────────
// TAMBAH RESEP
// ────────────────────────────
if ($action === 'tambah_resep') {
    $id_stok   = intval($_POST['id_stok'] ?? 0);
    $id_produk = intval($_POST['id_produk'] ?? 0);
    $jumlah    = floatval($_POST['jumlah_pakai'] ?? 1);

    if ($id_stok <= 0 || $id_produk <= 0 || $jumlah <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO resep_menu (id_produk, id_stok, jumlah_pakai)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE jumlah_pakai = VALUES(jumlah_pakai)
    ");
    $stmt->bind_param('iid', $id_produk, $id_stok, $jumlah);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Resep ditambahkan.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal: ' . $conn->error]);
    }
    exit;
}

// ────────────────────────────
// HAPUS RESEP
// ────────────────────────────
if ($action === 'hapus_resep') {
    $id_resep = intval($_POST['id_resep'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM resep_menu WHERE id_resep = ?");
    $stmt->bind_param('i', $id_resep);
    echo json_encode($stmt->execute()
        ? ['status' => 'success']
        : ['status' => 'error', 'message' => $conn->error]
    );
    exit;
}

// ────────────────────────────
// HAPUS BARANG (dapur only, stok harus 0)
// ────────────────────────────
if ($action === 'hapus_barang') {
    $id_stok = intval($_POST['id_stok'] ?? 0);

    if ($id_stok <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
        exit;
    }

    // Cek stok masih ada
    $cek = $conn->prepare("SELECT nama_barang, stok_saat_ini FROM stok_barang WHERE id_stok = ?");
    $cek->bind_param('i', $id_stok);
    $cek->execute();
    $row = $cek->get_result()->fetch_assoc();

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Barang tidak ditemukan.']);
        exit;
    }

    if ((float)$row['stok_saat_ini'] > 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Stok ' . $row['nama_barang'] . ' masih ada (' . $row['stok_saat_ini'] . '). Koreksi ke 0 dulu sebelum hapus.'
        ]);
        exit;
    }

    $del = $conn->prepare("DELETE FROM stok_barang WHERE id_stok = ?");
    $del->bind_param('i', $id_stok);

    if ($del->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Barang berhasil dihapus.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus: ' . $conn->error]);
    }
    exit;
}

// ────────────────────────────
// HAPUS RIWAYAT (manager only)
// ────────────────────────────
if ($action === 'hapus_riwayat') {
    // Hanya manager yang boleh hapus riwayat
    if ($_SESSION['role'] !== 'manager') {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
        exit;
    }
    $id_riwayat = intval($_POST['id_riwayat'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM riwayat_stok WHERE id_riwayat = ?");
    $stmt->bind_param('i', $id_riwayat);
    echo json_encode($stmt->execute()
        ? ['status' => 'success']
        : ['status' => 'error', 'message' => $conn->error]
    );
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali.']);
