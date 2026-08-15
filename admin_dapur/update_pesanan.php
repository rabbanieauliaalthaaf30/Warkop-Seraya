<?php
session_start();
include "../koneksi.php";

// Pastikan request via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "Metode request tidak valid"]);
    exit;
}

// Ambil ID & status baru dari POST
$id     = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($id <= 0 || $status === '') {
    echo json_encode(["success" => false, "error" => "Parameter tidak lengkap"]);
    exit;
}

// Validasi status
$allowed = ['pending', 'diproses', 'selesai'];
if (!in_array($status, $allowed)) {
    echo json_encode(["success" => false, "error" => "Status tidak valid"]);
    exit;
}

// Update status pesanan
$sql  = "UPDATE transaksi SET status_pesanan = ? WHERE id_transaksi = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $status, $id);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(["success" => false, "error" => "Gagal update database: " . mysqli_error($conn)]);
    exit;
}

// ══════════════════════════════════════════════════════
// INVENTORY: Kurangi stok otomatis saat status = selesai
// ══════════════════════════════════════════════════════
if ($status === 'selesai') {
    $id_admin = $_SESSION['id_admin'] ?? 0; // 0 → NULLIF(0,0) = NULL di query

    // Ambil semua item dari transaksi ini beserta qty
    $items = mysqli_query($conn, "
        SELECT d.id_produk, d.quantity
        FROM detail_transaksi d
        WHERE d.id_transaksi = $id
    ");

    while ($item = mysqli_fetch_assoc($items)) {
        $id_produk = (int)$item['id_produk'];
        $qty       = (int)$item['quantity'];

        // Cek apakah ada resep untuk menu ini
        $cek_resep = mysqli_query($conn, "
            SELECT COUNT(*) AS ada FROM resep_menu WHERE id_produk = $id_produk
        ");
        $punya_resep = (int)mysqli_fetch_assoc($cek_resep)['ada'] > 0;

        // ── CASE 1: Barang sachet — hanya jika TIDAK punya resep ──
        if (!$punya_resep) {
            $cek_sachet = mysqli_query($conn, "
                SELECT id_stok, stok_saat_ini, nama_barang, satuan
                FROM stok_barang
                WHERE id_produk = $id_produk
                LIMIT 1
            ");

            if ($row = mysqli_fetch_assoc($cek_sachet)) {
                $stok_sebelum = (float)$row['stok_saat_ini'];
                $stok_sesudah = max(0, $stok_sebelum - $qty);
                $id_stok      = (int)$row['id_stok'];
                $sumber       = 'pesanan';
                $ket          = "Pesanan #$id dikonfirmasi selesai";

                mysqli_query($conn, "
                    UPDATE stok_barang SET stok_saat_ini = $stok_sesudah
                    WHERE id_stok = $id_stok
                ");

                $log = mysqli_prepare($conn, "
                    INSERT INTO riwayat_stok
                        (id_stok, id_admin, id_transaksi, jenis, jumlah, stok_sebelum, stok_sesudah, sumber, keterangan)
                    VALUES (?, NULLIF(?,0), ?, 'keluar', ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($log, 'iiidddss',
                    $id_stok, $id_admin, $id, $qty, $stok_sebelum, $stok_sesudah, $sumber, $ket
                );
                mysqli_stmt_execute($log);
            }
        }

        // ── CASE 2: Menu racikan — kurangi setiap bahan sesuai resep ──
        if ($punya_resep) {
            $resep = mysqli_query($conn, "
                SELECT r.id_stok, r.jumlah_pakai, s.stok_saat_ini, s.nama_barang, s.satuan
                FROM resep_menu r
                JOIN stok_barang s ON r.id_stok = s.id_stok
                WHERE r.id_produk = $id_produk
            ");

            while ($bahan = mysqli_fetch_assoc($resep)) {
                $id_stok      = (int)$bahan['id_stok'];
                $jumlah_pakai = (float)$bahan['jumlah_pakai'] * $qty;
                $stok_sebelum = (float)$bahan['stok_saat_ini'];
                $stok_sesudah = max(0, $stok_sebelum - $jumlah_pakai);
                $sumber       = 'pesanan';
                $ket          = "Resep — Pesanan #$id selesai";

                mysqli_query($conn, "
                    UPDATE stok_barang SET stok_saat_ini = $stok_sesudah
                    WHERE id_stok = $id_stok
                ");

                $log = mysqli_prepare($conn, "
                    INSERT INTO riwayat_stok
                        (id_stok, id_admin, id_transaksi, jenis, jumlah, stok_sebelum, stok_sesudah, sumber, keterangan)
                    VALUES (?, NULLIF(?,0), ?, 'keluar', ?, ?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($log, 'iiidddss',
                    $id_stok, $id_admin, $id, $jumlah_pakai, $stok_sebelum, $stok_sesudah, $sumber, $ket
                );
                mysqli_stmt_execute($log);
            }
        }
    }
}
// ══════════════════════════════════════════════════════

echo json_encode(["success" => true, "nextStatus" => $status]);
?>
