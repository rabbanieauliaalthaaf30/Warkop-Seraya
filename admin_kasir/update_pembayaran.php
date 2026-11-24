<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include "../koneksi.php";

// Validasi role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'kasir') {
    echo json_encode(["status" => "error", "message" => "Akses ditolak."]);
    exit;
}

// Ambil data dari POST
$id_transaksi = isset($_POST['id_transaksi']) ? intval($_POST['id_transaksi']) : 0;
$status       = isset($_POST['status']) ? trim($_POST['status']) : '';
$id_admin     = $_SESSION['id_admin'] ?? null;

if ($id_transaksi <= 0) {
    echo json_encode(["status" => "error", "message" => "ID transaksi tidak valid."]);
    exit;
}

// ============================================================
// AKSI: "dibayar" — kasir konfirmasi pembayaran (cash maupun 
//                    setelah verifikasi bukti transfer/QRIS)
// ============================================================
if ($status === 'dibayar' || $status === 'selesai') {

    // 1. Catat kasir yang memproses pada tabel transaksi
    $stmtTrx = $conn->prepare("UPDATE transaksi SET id_admin = ? WHERE id_transaksi = ?");
    $stmtTrx->bind_param("ii", $id_admin, $id_transaksi);

    if ($stmtTrx->execute()) {
        $stmtTrx->close();

        // 2. Cek apakah sudah ada record di tabel pembayaran
        $checkPay = $conn->prepare("SELECT id_pembayaran FROM pembayaran WHERE id_transaksi = ?");
        $checkPay->bind_param("i", $id_transaksi);
        $checkPay->execute();
        $resPay = $checkPay->get_result();
        $checkPay->close();

        if ($resPay->num_rows > 0) {
            // Update status pembayaran yang sudah ada menjadi sudah bayar
            $updatePay = $conn->prepare(
                "UPDATE pembayaran 
                 SET status = 'sudah bayar', waktu_bayar = CURRENT_TIMESTAMP 
                 WHERE id_transaksi = ?"
            );
            $updatePay->bind_param("i", $id_transaksi);
            $updatePay->execute();
            $updatePay->close();
        } else {
            // Belum ada record pembayaran — insert baru (kasus cash tanpa form online)
            $insertPay = $conn->prepare(
                "INSERT INTO pembayaran (id_transaksi, metode, jumlah, status, waktu_bayar) 
                 SELECT id_transaksi, 'cash', total, 'sudah bayar', CURRENT_TIMESTAMP 
                 FROM transaksi WHERE id_transaksi = ?"
            );
            $insertPay->bind_param("i", $id_transaksi);
            $insertPay->execute();
            $insertPay->close();
        }

        echo json_encode([
            "status"  => "success",
            "message" => "Pembayaran berhasil dikonfirmasi oleh Kasir ID: $id_admin"
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal mengupdate transaksi: " . $conn->error]);
        $stmtTrx->close();
    }

// ============================================================
// AKSI: "tolak" — kasir menolak bukti transfer/QRIS
//                 kembalikan status ke 'belum bayar' agar
//                 customer bisa upload ulang
// ============================================================
} elseif ($status === 'tolak') {

    // Ambil nama file bukti yang ada dulu sebelum di-NULL-kan
    $stmtGetBukti = $conn->prepare(
        "SELECT bukti_file FROM pembayaran WHERE id_transaksi = ? LIMIT 1"
    );
    $stmtGetBukti->bind_param("i", $id_transaksi);
    $stmtGetBukti->execute();
    $resBukti = $stmtGetBukti->get_result()->fetch_assoc();
    $stmtGetBukti->close();
    $old_bukti = $resBukti['bukti_file'] ?? null;

    $updatePay = $conn->prepare(
        "UPDATE pembayaran 
         SET status = 'belum bayar', bukti_file = NULL 
         WHERE id_transaksi = ?"
    );
    $updatePay->bind_param("i", $id_transaksi);

    if ($updatePay->execute()) {
        $updatePay->close();

        // Hapus file fisik agar tidak menumpuk di folder uploads/
        if (!empty($old_bukti)) {
            $filePath = __DIR__ . "/../uploads/" . basename($old_bukti);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Kembalikan status pesanan ke pending agar tetap muncul di antrian
        $updateTrx = $conn->prepare(
            "UPDATE transaksi SET status_pesanan = 'pending' WHERE id_transaksi = ?"
        );
        $updateTrx->bind_param("i", $id_transaksi);
        $updateTrx->execute();
        $updateTrx->close();

        echo json_encode([
            "status"  => "success",
            "aksi"    => "tolak",
            "message" => "Bukti pembayaran ditolak. Customer perlu upload ulang."
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menolak bukti: " . $conn->error]);
        $updatePay->close();
    }

} else {
    echo json_encode(["status" => "error", "message" => "Parameter tidak valid."]);
}
?>
