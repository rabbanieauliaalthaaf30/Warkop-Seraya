<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include "../koneksi.php";

// Validasi session — hanya dapur yang boleh akses
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dapur') {
    echo json_encode(["ada_pesanan" => false, "id" => null]);
    exit;
}

// 🔍 Ambil transaksi terakhir yang SUDAH MASUK KE KASIR/DAPUR
$sql = "
  SELECT id_transaksi
  FROM transaksi
  WHERE status_pesanan = 'pending'
  ORDER BY id_transaksi DESC
  LIMIT 1
";

$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

$response = [
  "ada_pesanan" => $row ? true : false,
  "id" => $row ? $row["id_transaksi"] : null
];

echo json_encode($response);
?>
