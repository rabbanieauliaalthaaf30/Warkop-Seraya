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

// Update status
$sql = "UPDATE transaksi SET status_pesanan = ? WHERE id_transaksi = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $status, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["success" => true, "nextStatus" => $status]);
} else {
    echo json_encode(["success" => false, "error" => "Gagal update database"]);
}
?>
