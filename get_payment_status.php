<?php
// Endpoint polling status pembayaran untuk customer
// Dipanggil oleh payment.js setiap 3 detik
header('Content-Type: application/json');
include "koneksi.php";

$id_transaksi = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_transaksi <= 0) {
    echo json_encode(["success" => false, "message" => "ID tidak valid"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT p.status, p.metode, t.status_pesanan
    FROM pembayaran p
    JOIN transaksi t ON p.id_transaksi = t.id_transaksi
    WHERE p.id_transaksi = ?
    ORDER BY p.id_pembayaran DESC
    LIMIT 1
");
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(["success" => false, "message" => "Transaksi tidak ditemukan"]);
    exit;
}

echo json_encode([
    "success"         => true,
    "status_bayar"    => $row['status'],
    "metode"          => $row['metode'],
    "status_pesanan"  => $row['status_pesanan']
]);
?>
