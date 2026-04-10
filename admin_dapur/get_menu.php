<?php
include "../koneksi.php";
header("Content-Type: application/json; charset=UTF-8");

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["status" => "error", "message" => "ID produk tidak valid"]);
    exit;
}

// ===== Ambil data produk =====
$stmt = $conn->prepare("
    SELECT id_produk, nama_produk, kategori, harga_produk, image_url, status, available
    FROM produk
    WHERE id_produk = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Produk tidak ditemukan"]);
    exit;
}

$produk = $result->fetch_assoc();
$stmt->close();

// ===== Ambil data varian =====
$varian = [];
$vstmt = $conn->prepare("
    SELECT id_varian, nama_varian, harga_varian
    FROM varian_produk
    WHERE id_produk = ?
");
$vstmt->bind_param("i", $id);
$vstmt->execute();
$vres = $vstmt->get_result();

while ($row = $vres->fetch_assoc()) {
    $varian[] = [
        "id_varian"    => (int)$row["id_varian"],
        "nama_varian"  => htmlspecialchars($row["nama_varian"]),
        "harga_varian" => (float)$row["harga_varian"]
    ];
}
$vstmt->close();

// ===== Bentuk data JSON =====
$data = [
    "status"       => "success",
    "message"      => "Data produk berhasil diambil",
    "produk" => [
        "id_produk"   => (int)$produk["id_produk"],
        "nama_produk" => htmlspecialchars($produk["nama_produk"]),
        "kategori"    => htmlspecialchars($produk["kategori"]),
        "harga_dasar" => (float)$produk["harga_produk"],
        "image_url"   => $produk["image_url"] ? "../image_menu/" . $produk["image_url"] : null,
        "status"      => $produk["status"],
        "available"   => (int)$produk["available"],
        "varian"      => $varian
    ]
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
