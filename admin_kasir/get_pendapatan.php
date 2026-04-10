<?php
include "../koneksi.php";

$periode = $_GET['periode'] ?? 'today';
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

$labels = [];
$data = [];
$total_pendapatan = 0;
$total_transaksi = 0;

// =======================
// 📅 HARI INI
// =======================
if ($periode == 'today') {
    $sql = "
        SELECT 
            HOUR(p.waktu_bayar) AS jam, 
            SUM(t.total) AS total,
            COUNT(t.id_transaksi) AS transaksi
        FROM transaksi t
        JOIN pembayaran p ON t.id_transaksi = p.id_transaksi
        WHERE p.status = 'sudah bayar'
          AND DATE(p.waktu_bayar) = CURDATE()
        GROUP BY HOUR(p.waktu_bayar)
        ORDER BY HOUR(p.waktu_bayar)
    ";
}

// =======================
// 📆 MINGGU INI
// =======================
elseif ($periode == 'week') {
    $sql = "
        SELECT 
            DAYNAME(p.waktu_bayar) AS hari, 
            SUM(t.total) AS total,
            COUNT(t.id_transaksi) AS transaksi
        FROM transaksi t
        JOIN pembayaran p ON t.id_transaksi = p.id_transaksi
        WHERE p.status = 'sudah bayar'
          AND YEARWEEK(p.waktu_bayar, 1) = YEARWEEK(CURDATE(), 1)
        GROUP BY DAYNAME(p.waktu_bayar)
        ORDER BY p.waktu_bayar ASC
    ";
}

// =======================
// 🗓️ BULAN INI
// =======================
elseif ($periode == 'month') {
    $sql = "
        SELECT 
            CONCAT('Minggu ', WEEK(p.waktu_bayar, 1) - WEEK(DATE_SUB(p.waktu_bayar, INTERVAL DAY(p.waktu_bayar)-1 DAY), 1) + 1) AS minggu,
            SUM(t.total) AS total,
            COUNT(t.id_transaksi) AS transaksi
        FROM transaksi t
        JOIN pembayaran p ON t.id_transaksi = p.id_transaksi
        WHERE p.status = 'sudah bayar'
          AND MONTH(p.waktu_bayar) = MONTH(CURDATE())
          AND YEAR(p.waktu_bayar) = YEAR(CURDATE())
        GROUP BY minggu
        ORDER BY minggu ASC
    ";
}

// =======================
// 🔍 PERIODE CUSTOM (PILIH TANGGAL MANUAL)
// =======================
elseif ($periode == 'custom' && $start && $end) {
    $sql = "
        SELECT 
            DATE(p.waktu_bayar) AS tanggal,
            SUM(t.total) AS total,
            COUNT(t.id_transaksi) AS transaksi
        FROM transaksi t
        JOIN pembayaran p ON t.id_transaksi = p.id_transaksi
        WHERE p.status = 'sudah bayar'
          AND DATE(p.waktu_bayar) BETWEEN '$start' AND '$end'
        GROUP BY DATE(p.waktu_bayar)
        ORDER BY DATE(p.waktu_bayar)
    ";
}

// =======================
// 🚨 Jika tidak ada query valid
// =======================
else {
    echo json_encode([
        'labels' => [],
        'data' => [],
        'total_transaksi' => 0,
        'total_pendapatan' => 0
    ]);
    exit;
}

// =======================
// ✅ Jalankan query dan olah hasil
// =======================
$res = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($res)) {
    $label = $row['jam'] ?? $row['hari'] ?? $row['minggu'] ?? $row['tanggal'] ?? '';
    $labels[] = $label;
    $data[] = (int)$row['total'];

    $total_pendapatan += (int)$row['total'];
    $total_transaksi += (int)$row['transaksi'];
}

// =======================
// 📤 Kembalikan JSON
// =======================
header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'data' => $data,
    'total_transaksi' => $total_transaksi,
    'total_pendapatan' => $total_pendapatan
], JSON_PRETTY_PRINT);
?>
