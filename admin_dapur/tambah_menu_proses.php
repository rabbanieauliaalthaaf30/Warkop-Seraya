<?php
include "../koneksi.php";
header('Content-Type: application/json');

// ===== Ambil & validasi data dasar =====
$nama         = trim($_POST['nama_produk'] ?? "");
$kategori     = trim($_POST['kategori'] ?? "");
$harga_dasar  = floatval($_POST['harga_dasar'] ?? 0);

if ($nama === "" || $kategori === "" || $harga_dasar <= 0) {
    echo json_encode(["status" => "error", "message" => "Nama produk, kategori, dan harga dasar wajib diisi!"]);
    exit;
}

// ===== Upload gambar (jika ada) =====
$gambar = "";
if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
    $upload_dir = "../image_menu/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext)) {
        echo json_encode(["status" => "error", "message" => "Format gambar tidak valid! (Hanya: jpg, jpeg, png, gif, webp)"]);
        exit;
    }

    // Buat nama file aman & unik
    $safe_name = preg_replace("/[^a-zA-Z0-9_\.-]/", "_", pathinfo($_FILES['gambar']['name'], PATHINFO_FILENAME));
    $gambar = time() . "_" . $safe_name . "." . $ext;
    $target_path = $upload_dir . $gambar;

    if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $target_path)) {
        echo json_encode(["status" => "error", "message" => "Gagal mengunggah gambar!"]);
        exit;
    }
}

// ===== Simpan produk utama =====
// Tambahkan quantity_produk default = 1 agar tidak 0
$stmt = $conn->prepare("
    INSERT INTO produk (nama_produk, harga_produk, quantity_produk, image_url, kategori, available, status)
    VALUES (?, ?, 1, ?, ?, 1, 'tersedia')
");
$stmt->bind_param("sdss", $nama, $harga_dasar, $gambar, $kategori);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan produk: " . $stmt->error]);
    exit;
}

$id_produk = $stmt->insert_id;
$stmt->close();

// ===== Simpan varian produk (jika ada) =====
if (!empty($_POST['varian_nama']) && !empty($_POST['varian_harga'])) {
    $varian_nama  = $_POST['varian_nama'];
    $varian_harga = $_POST['varian_harga'];

    for ($i = 0; $i < count($varian_nama); $i++) {
        $nama_varian  = trim($varian_nama[$i] ?? "");
        $harga_varian = floatval($varian_harga[$i] ?? 0);

        if ($nama_varian !== "" && $harga_varian > 0) {
            $vstmt = $conn->prepare("
                INSERT INTO varian_produk (id_produk, nama_varian, harga_varian)
                VALUES (?, ?, ?)
            ");
            $vstmt->bind_param("isd", $id_produk, $nama_varian, $harga_varian);
            $vstmt->execute();
            $vstmt->close();
        }
    }
}

// ===== Respons sukses =====
echo json_encode([
    "status"  => "success",
    "message" => "Menu berhasil ditambahkan!",
    "id_produk" => $id_produk
]);
?>
