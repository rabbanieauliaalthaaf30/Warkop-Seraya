<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../koneksi.php';

// Ambil ID Admin dari session
$id_admin = $_SESSION['id_admin'] ?? null;

if (isset($_POST['id_produk'])) {
    $id = intval($_POST['id_produk']); 

    // Ambil status saat ini
    $result = mysqli_query($conn, "SELECT status FROM produk WHERE id_produk = $id");
    $row = mysqli_fetch_assoc($result);

    if ($row) {
        $statusLama = $row['status'];
        $statusBaru = ($statusLama === 'tersedia') ? 'tidak tersedia' : 'tersedia';

        // Update ke status baru + simpan id_admin yang mengubahnya
        $query = "UPDATE produk SET status='$statusBaru', id_admin='$id_admin' WHERE id_produk=$id";
        if (mysqli_query($conn, $query)) {
            echo json_encode(["success" => true, "status" => ($statusBaru === 'tersedia' ? 1 : 0)]);
        } else {
            echo json_encode(["success" => false, "error" => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Data tidak ditemukan"]);
    }
} else {
    echo json_encode(["success" => false, "error" => "ID tidak diberikan"]);
}
?>
