<?php
include "../koneksi.php";

$periode = $_GET['periode'] ?? 'today';

$where = "WHERE p.status = 'sudah bayar'";

if ($periode == 'today') {
    $where .= " AND DATE(p.waktu_bayar) = CURDATE()";
} elseif ($periode == 'week') {
    $where .= " AND YEARWEEK(p.waktu_bayar, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($periode == 'month') {
    $where .= " AND MONTH(p.waktu_bayar) = MONTH(CURDATE()) 
                AND YEAR(p.waktu_bayar) = YEAR(CURDATE())";
}

$sql = "
    SELECT 
        CONCAT(pr.nama_produk, ' ', IFNULL(v.nama_varian, '')) AS nama_menu,
        SUM(d.quantity) AS total_terjual
    FROM detail_transaksi d
    JOIN varian_produk v ON d.id_varian = v.id_varian
    JOIN produk pr ON v.id_produk = pr.id_produk
    JOIN transaksi t ON d.id_transaksi = t.id_transaksi
    JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
    $where
    GROUP BY v.id_varian
    ORDER BY total_terjual DESC
    LIMIT 5
";

$res = mysqli_query($conn, $sql);
$data = [];

while ($row = mysqli_fetch_assoc($res)) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
?>
