<?php
session_start();
include "../koneksi.php";
header('Content-Type: application/json; charset=UTF-8');

// === Cek login ===
if (!isset($_SESSION['username'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Akses ditolak! Silakan login terlebih dahulu."
    ]);
    exit;
}

// === Validasi ID ===
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "ID menu tidak valid!"
    ]);
    exit;
}

// === Ambil data produk untuk hapus gambar ===
$stmt = $conn->prepare("SELECT image_url FROM produk WHERE id_produk = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$produk = $res->fetch_assoc();
$stmt->close();

if (!$produk) {
    echo json_encode([
        "status" => "error",
        "message" => "Menu tidak ditemukan di database!"
    ]);
    exit;
}

// === Hapus file gambar (jika bukan default) ===
if (!empty($produk['image_url'])) {
    $path = "../image_menu/" . $produk['image_url'];
    if (file_exists($path) && basename($path) !== "default.jpg") {
        @unlink($path);
    }
}

// === Mulai proses penghapusan relasi terkait ===
$conn->begin_transaction();

try {
    // Hapus varian produk
    $stmt = $conn->prepare("DELETE FROM varian_produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Hapus dari detail_transaksi
    $stmt = $conn->prepare("DELETE FROM detail_transaksi WHERE id_produk = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Hapus produk utama
    $stmt = $conn->prepare("DELETE FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode([
            "status" => "success",
            "message" => "✅ Menu dan semua data terkait berhasil dihapus!"
        ]);
    } else {
        $conn->rollback();
        echo json_encode([
            "status" => "error",
            "message" => "Menu tidak ditemukan!"
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "status" => "error",
        "message" => "❌ Terjadi kesalahan saat menghapus menu: " . $e->getMessage()
    ]);
}

$conn->close();
?>
