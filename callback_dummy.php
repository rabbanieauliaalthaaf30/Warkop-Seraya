<?php
session_start();
include "koneksi.php";
date_default_timezone_set('Asia/Jakarta');

// === DEBUG LOG ===
file_put_contents(__DIR__ . '/debug_callback.log',
    "[" . date('Y-m-d H:i:s') . "] POST DATA: " . print_r($_POST, true) . "\n" .
    "[" . date('Y-m-d H:i:s') . "] FILES DATA: " . print_r($_FILES, true) . "\n\n",
    FILE_APPEND
);

// --- Validasi request ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['orderId'])) {
    echo json_encode(["success" => false, "message" => "Request tidak valid"]);
    exit;
}

$id_transaksi = (int) $_POST['orderId'];
$method       = isset($_REQUEST['method']) ? strtolower(trim($_REQUEST['method'])) : '';
$email        = isset($_POST['email']) ? trim($_POST['email']) : '';

$total = isset($_POST['total']) && $_POST['total'] > 0 
    ? (float) $_POST['total'] 
    : (float)mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT total FROM transaksi WHERE id_transaksi = {$id_transaksi}")
      )['total'];

// --- Upload bukti transfer jika ada ---
$bukti_file = null;
$upload_dir = __DIR__ . '/uploads/bukti_transfer/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!empty($_FILES['bukti_file']['name'])) {
    $ext = pathinfo($_FILES['bukti_file']['name'], PATHINFO_EXTENSION);
    $file_name = 'bukti_' . $id_transaksi . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['bukti_file']['tmp_name'], $target_path)) {
        $bukti_file = $file_name;
        file_put_contents(__DIR__ . '/debug_callback.log',
            "[" . date('Y-m-d H:i:s') . "] Upload sukses: {$file_name}\n",
            FILE_APPEND
        );
    } else {
        file_put_contents(__DIR__ . '/debug_callback.log',
            "[" . date('Y-m-d H:i:s') . "] Upload gagal\n",
            FILE_APPEND
        );
    }
}

// --- Pastikan transaksi ada ---
$q = mysqli_query($conn, "SELECT * FROM transaksi WHERE id_transaksi = {$id_transaksi}");
if (!$q || mysqli_num_rows($q) === 0) {
    echo json_encode(["success" => false, "message" => "Transaksi tidak ditemukan"]);
    exit;
}

// --- Validasi metode pembayaran ---
if (!in_array($method, ['cash', 'transfer', 'qris'])) {
    $method = 'cash';
}

$email  = mysqli_real_escape_string($conn, $email);
$status = ($method === 'cash') ? 'belum bayar' : 'sudah bayar';

// --- Cek apakah pembayaran sudah ada ---
$cek_bayar = mysqli_query($conn, "SELECT id_pembayaran FROM pembayaran WHERE id_transaksi = {$id_transaksi} LIMIT 1");

// --- Query Update ---
$sql_update = "
    UPDATE pembayaran 
    SET status = '{$status}',
        metode = '{$method}',
        email = '{$email}',
        jumlah = {$total},
        waktu_bayar = CURRENT_TIMESTAMP" .
        ($bukti_file ? ", bukti_file = '{$bukti_file}'" : "") . "
    WHERE id_transaksi = {$id_transaksi}
";
file_put_contents(__DIR__ . '/debug_callback.log', "[" . date('Y-m-d H:i:s') . "] SQL_UPDATE: {$sql_update}\n", FILE_APPEND);

$run_update = mysqli_query($conn, $sql_update);
if (!$run_update) {
    file_put_contents(__DIR__ . '/debug_callback.log', "[" . date('Y-m-d H:i:s') . "] ERROR_UPDATE: " . mysqli_error($conn) . "\n", FILE_APPEND);
}

// --- Jika belum ada (UPDATE gagal ubah baris), INSERT baru ---
if (mysqli_affected_rows($conn) === 0) {
    $sql_insert = "
        INSERT INTO pembayaran (id_transaksi, metode, jumlah, status, waktu_bayar, email, bukti_file)
        VALUES ({$id_transaksi}, '{$method}', {$total}, '{$status}', CURRENT_TIMESTAMP, '{$email}', " .
        ($bukti_file ? "'{$bukti_file}'" : "NULL") . ")
    ";
    file_put_contents(__DIR__ . '/debug_callback.log', "[" . date('Y-m-d H:i:s') . "] SQL_INSERT: {$sql_insert}\n", FILE_APPEND);
    mysqli_query($conn, $sql_insert);
}

// --- Update status pesanan di tabel transaksi ---
$sql_update_status = "
    UPDATE transaksi 
    SET status_pesanan = 'pending',
        waktu_pemesanan = CURRENT_TIMESTAMP
    WHERE id_transaksi = {$id_transaksi}
";
mysqli_query($conn, $sql_update_status);

// --- Response ke frontend ---
echo json_encode([
    "success" => true,
    "message" => "Pesanan berhasil diproses",
    "id_transaksi" => $id_transaksi,
    "method" => $method,
    "bukti_file" => $bukti_file,
    "status_pesanan" => "pending"
]);
exit;
?>
