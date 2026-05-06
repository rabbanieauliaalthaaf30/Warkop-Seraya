<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include "../koneksi.php";

// Ambil data dari POST
$id_transaksi = isset($_POST['id_transaksi']) ? intval($_POST['id_transaksi']) : 0;
$status       = isset($_POST['status']) ? trim($_POST['status']) : '';

// Ambil ID Admin (Kasir) dari session
$id_admin = $_SESSION['id_admin'] ?? null;

if ($id_transaksi > 0 && ($status === 'dibayar' || $status === 'selesai')) {
    
    // 1. Update status transaksi dan catat id_admin (Kasir) yang memproses
    $queryTrx = "UPDATE transaksi SET status_pesanan = 'selesai', id_admin = ? WHERE id_transaksi = ?";
    $stmtTrx = $conn->prepare($queryTrx);
    $stmtTrx->bind_param("ii", $id_admin, $id_transaksi);
    
    if ($stmtTrx->execute()) {
        
        // 2. Update atau Insert status di tabel pembayaran
        $checkPay = $conn->prepare("SELECT id_pembayaran FROM pembayaran WHERE id_transaksi = ?");
        $checkPay->bind_param("i", $id_transaksi);
        $checkPay->execute();
        $resPay = $checkPay->get_result();
        
        if ($resPay->num_rows > 0) {
            // Update status pembayaran yang sudah ada
            $updatePay = $conn->prepare("UPDATE pembayaran SET status = 'sudah bayar', waktu_bayar = CURRENT_TIMESTAMP WHERE id_transaksi = ?");
            $updatePay->bind_param("i", $id_transaksi);
            $updatePay->execute();
        } else {
            // Jika belum ada record pembayaran (bayar cash di kasir tanpa input form)
            $insertPay = $conn->prepare("INSERT INTO pembayaran (id_transaksi, metode, jumlah, status, waktu_bayar) 
                                         SELECT id_transaksi, 'cash', total, 'sudah bayar', CURRENT_TIMESTAMP 
                                         FROM transaksi WHERE id_transaksi = ?");
            $insertPay->bind_param("i", $id_transaksi);
            $insertPay->execute();
        }
        
        echo json_encode(["status" => "success", "message" => "Pembayaran berhasil dikonfirmasi oleh Kasir ID: $id_admin"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal mengupdate transaksi: " . $conn->error]);
    }
    
    $stmtTrx->close();
} else {
    echo json_encode(["status" => "error", "message" => "Parameter tidak valid atau ID transaksi kosong."]);
}
?>
