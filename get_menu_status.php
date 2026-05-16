<?php
include "koneksi.php";

header('Content-Type: application/json');

$db = null;
if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} elseif (isset($koneksi) && $koneksi instanceof mysqli) {
    $db = $koneksi;
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
}

if (!$db) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$query = "SELECT id_produk, status FROM produk";
$result = mysqli_query($db, $query);

if ($result) {
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[$row['id_produk']] = $row['status'];
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($db)]);
}
?>
