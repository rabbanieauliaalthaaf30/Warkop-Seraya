<?php
session_start();
include "../koneksi.php";
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Akses tidak sah"]);
    exit;
}

// ===== DEBUG: simpan semua data POST ke file =====
file_put_contents(__DIR__ . '/debug_edit.txt', print_r($_POST, true));

// ===== Ambil ID (terima fallback 'id' juga) =====
$id = intval($_POST['id_produk'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["status" => "error", "message" => "ID produk tidak valid"]);
    exit;
}

// ===== Ambil data lama =====
$stmt = $conn->prepare("SELECT * FROM produk WHERE id_produk = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Produk tidak ditemukan"]);
    exit;
}
$existing = $res->fetch_assoc();
$stmt->close();

// ===== Ambil & validasi input =====
$nama_produk  = trim($_POST['nama_produk'] ?? "");
$kategori     = trim($_POST['kategori'] ?? "");
$harga_dasar  = isset($_POST['harga_dasar']) && $_POST['harga_dasar'] !== "" ? floatval($_POST['harga_dasar']) : floatval($existing['harga_produk']);
$status       = trim($_POST['status'] ?? $existing['status']);
$quantity     = isset($_POST['quantity_produk']) ? intval($_POST['quantity_produk']) : intval($existing['quantity_produk']);

if ($nama_produk === "" || $kategori === "") {
    echo json_encode(["status" => "error", "message" => "Nama produk dan kategori wajib diisi."]);
    exit;
}

// ===== Upload gambar baru (opsional) =====
$new_image_name = $existing['image_url'];
if (isset($_FILES['gambar']) && isset($_FILES['gambar']['error']) && $_FILES['gambar']['error'] === 0) {
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $orig = $_FILES['gambar']['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(["status" => "error", "message" => "Format gambar tidak didukung."]);
        exit;
    }

    $upload_dir = "../image_menu/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $safe = preg_replace("/[^a-zA-Z0-9_\.-]/", "_", pathinfo($orig, PATHINFO_FILENAME));
    $new_image_name = time() . "_" . $safe . "." . $ext;
    $target = $upload_dir . $new_image_name;

    if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $target)) {
        echo json_encode(["status" => "error", "message" => "Gagal mengunggah gambar baru."]);
        exit;
    }

    // hapus gambar lama jika ada
    if (!empty($existing['image_url'])) {
        $old_path = $upload_dir . $existing['image_url'];
        if (file_exists($old_path)) @unlink($old_path);
    }
}

// ===== Update produk (pilih query sesuai ada/tidaknya gambar baru) =====
if ($new_image_name !== $existing['image_url']) {
    $up = $conn->prepare("UPDATE produk SET nama_produk = ?, kategori = ?, harga_produk = ?, image_url = ?, status = ?, quantity_produk = ? WHERE id_produk = ?");
    $up->bind_param("ssdssii", $nama_produk, $kategori, $harga_dasar, $new_image_name, $status, $quantity, $id);
} else {
    $up = $conn->prepare("UPDATE produk SET nama_produk = ?, kategori = ?, harga_produk = ?, status = ?, quantity_produk = ? WHERE id_produk = ?");
    $up->bind_param("ssdsii", $nama_produk, $kategori, $harga_dasar, $status, $quantity, $id);
}

if (!$up->execute()) {
    echo json_encode(["status" => "error", "message" => "Gagal memperbarui produk: " . $up->error]);
    exit;
}
$up->close();

// ===== Update varian (hapus lalu insert ulang) =====
$del = $conn->prepare("DELETE FROM varian_produk WHERE id_produk = ?");
$del->bind_param("i", $id);
$del->execute();
$del->close();

if (!empty($_POST['varian_nama']) && !empty($_POST['varian_harga'])) {
    $varian_nama  = $_POST['varian_nama'];
    $varian_harga = $_POST['varian_harga'];

    $ins = $conn->prepare("INSERT INTO varian_produk (id_produk, nama_varian, harga_varian) VALUES (?, ?, ?)");
    for ($i = 0; $i < count($varian_nama); $i++) {
        $vn = trim($varian_nama[$i] ?? "");
        $vh = floatval($varian_harga[$i] ?? 0);
        if ($vn === "" || $vh <= 0) continue;
        $ins->bind_param("isd", $id, $vn, $vh);
        $ins->execute();
    }
    $ins->close();
}

echo json_encode(["status" => "success", "message" => "Menu berhasil diperbarui."]);
exit;
?>
