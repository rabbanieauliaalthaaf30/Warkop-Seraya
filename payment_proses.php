<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

include_once __DIR__ . '/koneksi.php';

// ================== HELPER ==================
function json_error($msg) {
    echo json_encode(["success" => false, "message" => $msg]);
    exit;
}
function columnExists($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return ($res && mysqli_num_rows($res) > 0);
}

// ================== SETUP ==================
$paymentStatuses = ['sudah bayar','belum bayar','dibayar'];
$orderStatuses   = ['pending','diproses','selesai'];

// ================== 1) REQUEST DARI FORM PAYMENT ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orderId'])) {
    $orderId      = (int) ($_POST['orderId'] ?? 0);
    $method_raw   = trim($_POST['method'] ?? '');
    $account_raw  = trim($_POST['accountNumber'] ?? '');
    $email_raw    = trim($_POST['email'] ?? '');

    if ($orderId <= 0) json_error("ID transaksi tidak valid.");

    // Ambil total dari transaksi
    $q = mysqli_query($conn, "SELECT total FROM transaksi WHERE id_transaksi = $orderId LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) json_error("Transaksi tidak ditemukan.");
    $trx = mysqli_fetch_assoc($q);
    $total = (float)$trx['total'];

    // Tentukan status pembayaran
    $method = strtolower($method_raw);
    $kasirAliases = ['kasir','bayar di kasir','pay at cashier','pay_at_cashier'];
    $status_bayar = (in_array($method, $kasirAliases, true) || $method === '') ? 'belum bayar' : 'sudah bayar';

    $method_safe  = mysqli_real_escape_string($conn, $method);
    $account_safe = mysqli_real_escape_string($conn, $account_raw);
    $email_safe   = mysqli_real_escape_string($conn, $email_raw);

    // Cek kolom tambahan
    $hasBuktiCol = columnExists($conn, 'pembayaran', 'bukti_file');
    $hasNomorRek = columnExists($conn, 'pembayaran', 'nomor_rekening');
    $hasEmailCol = columnExists($conn, 'pembayaran', 'email');

    // ================== UPLOAD BUKTI TRANSFER ==================
    $bukti_file = null;
    if ($hasBuktiCol && isset($_FILES['bukti']) && $_FILES['bukti']['error'] === UPLOAD_ERR_OK) {
        $targetDir = __DIR__ . "/uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES['bukti']['name']);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['bukti']['tmp_name'], $targetFile)) {
            $bukti_file = $fileName;
        }
    }

    // ================== INSERT / UPDATE PEMBAYARAN ==================
    $check = mysqli_query($conn, "SELECT id_pembayaran FROM pembayaran WHERE id_transaksi = $orderId LIMIT 1");

    if ($check && mysqli_num_rows($check) > 0) {
        // UPDATE
        $sets = [
            "metode = '{$method_safe}'",
            "jumlah = {$total}",
            "status = '{$status_bayar}'",
            ($status_bayar === 'sudah bayar') ? "waktu_bayar = CURRENT_TIMESTAMP" : "waktu_bayar = NULL"
        ];
        if ($hasNomorRek) $sets[] = "nomor_rekening = '{$account_safe}'";
        if ($hasEmailCol) $sets[] = "email = '{$email_safe}'";
        if ($hasBuktiCol && $bukti_file) $sets[] = "bukti_file = '{$bukti_file}'";

        $sql = "UPDATE pembayaran SET " . implode(", ", $sets) . " WHERE id_transaksi = $orderId";
    } else {
        // INSERT
        $cols = ["id_transaksi","metode","jumlah","status","waktu_bayar"];
        $vals = [$orderId,"'{$method_safe}'",$total,"'{$status_bayar}'",
                ($status_bayar === 'sudah bayar') ? "CURRENT_TIMESTAMP" : "NULL"];
        if ($hasNomorRek) { $cols[] = 'nomor_rekening'; $vals[] = "'{$account_safe}'"; }
        if ($hasEmailCol) { $cols[] = 'email'; $vals[] = "'{$email_safe}'"; }
        if ($hasBuktiCol) { $cols[] = 'bukti_file'; $vals[] = $bukti_file ? "'{$bukti_file}'" : "NULL"; }

        $sql = "INSERT INTO pembayaran (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
    }

    if (!mysqli_query($conn, $sql)) json_error("Gagal menyimpan pembayaran: " . mysqli_error($conn));

    // ================== UPDATE STATUS PESANAN (agar tetap muncul) ==================
    // Jika metode transfer → status pembayaran otomatis “sudah bayar”,
    // tapi status pesanan tetap “pending” (belum selesai).
    $updateTransaksi = "UPDATE transaksi 
                        SET status_pesanan = 'pending' 
                        WHERE id_transaksi = $orderId 
                        AND status_pesanan NOT IN ('diproses','selesai')";
    mysqli_query($conn, $updateTransaksi);

    echo json_encode([
        "success" => true,
        "aksi"    => "payment_form",
        "id"      => $orderId,
        "method"  => $method,
        "status"  => $status_bayar,
        "message" => ($status_bayar === 'sudah bayar')
            ? "Pembayaran berhasil dicatat (transfer otomatis sudah bayar)."
            : "Pembayaran dicatat sebagai belum bayar (kasir harus konfirmasi)."
    ]);
    exit;
}

// ================== 2) ADMIN UPDATE STATUS PEMBAYARAN/PESANAN ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['status'])) {
    $id   = (int) $_POST['id'];
    $stat = strtolower(trim($_POST['status']));
    if ($id <= 0 || $stat === '') json_error("Parameter tidak valid.");
    $stat_safe = mysqli_real_escape_string($conn, $stat);

    $cek = mysqli_query($conn, "SELECT id_transaksi FROM transaksi WHERE id_transaksi = $id LIMIT 1");
    if (!$cek || mysqli_num_rows($cek) === 0) json_error("Transaksi tidak ditemukan.");

    // Jika admin update status pembayaran
    if (in_array($stat, $paymentStatuses, true)) {
        $check = mysqli_query($conn, "SELECT id_pembayaran FROM pembayaran WHERE id_transaksi = $id LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $sql = "UPDATE pembayaran 
                    SET status = 'sudah bayar', waktu_bayar = CURRENT_TIMESTAMP 
                    WHERE id_transaksi = $id";
        } else {
            $sql = "INSERT INTO pembayaran (id_transaksi, metode, jumlah, status, waktu_bayar)
                    VALUES ($id, 'kasir', 0, 'sudah bayar', CURRENT_TIMESTAMP)";
        }
        if (!mysqli_query($conn, $sql)) json_error("Gagal update pembayaran: " . mysqli_error($conn));

        // Pastikan transaksi tetap muncul
        mysqli_query($conn, "UPDATE transaksi SET status_pesanan = 'pending' 
                            WHERE id_transaksi = $id AND status_pesanan NOT IN ('diproses','selesai')");

        echo json_encode([
            "success" => true,
            "aksi"    => "update_pembayaran",
            "id"      => $id,
            "status"  => "sudah bayar",
            "message" => "Status pembayaran diperbarui menjadi sudah bayar"
        ]);
        exit;
    }

    // Jika admin update status pesanan (dapur)
    if (in_array($stat, $orderStatuses, true)) {
        $sql = "UPDATE transaksi SET status_pesanan = '{$stat_safe}' WHERE id_transaksi = $id";
        if (!mysqli_query($conn, $sql)) json_error("Gagal update status pesanan: " . mysqli_error($conn));

        echo json_encode([
            "success" => true,
            "aksi"    => "update_pesanan",
            "id"      => $id,
            "status"  => $stat,
            "message" => "Status pesanan diupdate menjadi {$stat}"
        ]);
        exit;
    }

    json_error("Status tidak dikenali.");
}

// ================== 3) GET Fallback ==================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && isset($_GET['status'])) {
    $id   = (int) $_GET['id'];
    $stat = strtolower(trim($_GET['status']));
    if ($id <= 0 || $stat === '') json_error("Parameter tidak valid.");

    if (in_array($stat, $paymentStatuses, true)) {
        $sql = "UPDATE pembayaran 
                SET status = 'sudah bayar', waktu_bayar = CURRENT_TIMESTAMP 
                WHERE id_transaksi = $id";
        mysqli_query($conn, $sql);
        echo json_encode(["success" => true, "aksi" => "update_pembayaran", "id" => $id]);
        exit;
    }

    if (in_array($stat, $orderStatuses, true)) {
        $sql = "UPDATE transaksi SET status_pesanan = '{$stat}' WHERE id_transaksi = $id";
        mysqli_query($conn, $sql);
        echo json_encode(["success" => true, "aksi" => "update_pesanan", "id" => $id]);
        exit;
    }

    json_error("Status tidak dikenali.");
}

json_error("Request tidak dikenali.");
?>
