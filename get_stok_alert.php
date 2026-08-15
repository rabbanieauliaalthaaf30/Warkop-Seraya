<?php
/**
 * get_stok_alert.php
 * Endpoint JSON untuk cek stok menipis dan habis.
 * Dipanggil oleh semua role (dapur, kasir, manager) via fetch polling.
 */
session_start();
include "koneksi.php";

// Hanya izinkan user yang sudah login
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$result = mysqli_query($conn, "
    SELECT nama_barang, stok_saat_ini, stok_minimum, satuan
    FROM stok_barang
    WHERE stok_saat_ini <= stok_minimum
    ORDER BY stok_saat_ini ASC
    LIMIT 15
");

$items   = [];
$habis   = 0;
$menipis = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $isHabis = (float)$row['stok_saat_ini'] == 0;
    if ($isHabis) $habis++;
    else $menipis++;

    $items[] = [
        'nama'    => $row['nama_barang'],
        'stok'    => (float)$row['stok_saat_ini'],
        'minimum' => (float)$row['stok_minimum'],
        'satuan'  => $row['satuan'],
        'status'  => $isHabis ? 'habis' : 'menipis',
    ];
}

echo json_encode([
    'ada_alert' => count($items) > 0,
    'total'     => count($items),
    'habis'     => $habis,
    'menipis'   => $menipis,
    'items'     => $items,
]);
