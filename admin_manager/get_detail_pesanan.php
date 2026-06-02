<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Akses ditolak."]);
    exit;
}
include "../koneksi.php";

$id_transaksi = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_transaksi <= 0) {
    echo json_encode(["status" => "error", "message" => "ID transaksi tidak valid."]);
    exit;
}

// 1. Ambil info transaksi & pembayaran terakhir
$sql = "
  SELECT 
    t.id_transaksi, 
    t.nomor_meja, 
    t.nama_pemesan, 
    t.status_pesanan,
    t.waktu_pemesanan,
    t.total,
    pm.metode,
    pm.status AS status_pembayaran,
    pm.waktu_bayar,
    a.username AS nama_kasir
  FROM transaksi t
  LEFT JOIN (
      SELECT p1.*
      FROM pembayaran p1
      INNER JOIN (
          SELECT id_transaksi, MAX(id_pembayaran) AS last_id
          FROM pembayaran
          GROUP BY id_transaksi
      ) p2 ON p1.id_transaksi = p2.id_transaksi AND p1.id_pembayaran = p2.last_id
  ) pm ON t.id_transaksi = pm.id_transaksi
  LEFT JOIN admin a ON t.id_admin = a.id_admin
  WHERE t.id_transaksi = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_transaksi);
$stmt->execute();
$res = $stmt->get_result();
$transaksi = $res->fetch_assoc();
$stmt->close();

if (!$transaksi) {
    echo json_encode(["status" => "error", "message" => "Transaksi tidak ditemukan."]);
    exit;
}

// 2. Ambil detail produk pesanan
$sqlDetail = "
  SELECT 
    pr.nama_produk,
    vp.nama_varian,
    d.quantity,
    d.subtotal,
    d.catatan
  FROM detail_transaksi d
  JOIN produk pr ON d.id_produk = pr.id_produk
  LEFT JOIN varian_produk vp ON d.id_varian = vp.id_varian
  WHERE d.id_transaksi = ?
  ORDER BY d.id_detail ASC
";

$stmtDetail = $conn->prepare($sqlDetail);
$stmtDetail->bind_param("i", $id_transaksi);
$stmtDetail->execute();
$resDetail = $stmtDetail->get_result();

$items = [];
while ($row = $resDetail->fetch_assoc()) {
    $item_name = $row['nama_produk'];
    if (!empty($row['nama_varian'])) {
        $item_name .= " - " . $row['nama_varian'];
    }
    $items[] = [
        "nama_produk" => $item_name,
        "quantity" => intval($row['quantity']),
        "subtotal" => floatval($row['subtotal']),
        "catatan" => $row['catatan']
    ];
}
$stmtDetail->close();

// 3. Gabungkan data
$orderData = [
    "id" => $transaksi['id_transaksi'],
    "nama_pemesan" => $transaksi['nama_pemesan'],
    "nomor_meja" => $transaksi['nomor_meja'],
    "status_pesanan" => $transaksi['status_pesanan'],
    "waktu_pemesanan" => $transaksi['waktu_pemesanan'],
    "waktu_bayar" => $transaksi['waktu_bayar'] ? $transaksi['waktu_bayar'] : $transaksi['waktu_pemesanan'],
    "total" => floatval($transaksi['total']),
    "metode" => $transaksi['metode'] ? $transaksi['metode'] : 'cash',
    "status_pembayaran" => $transaksi['status_pembayaran'] ? $transaksi['status_pembayaran'] : 'belum bayar',
    "nama_kasir" => $transaksi['nama_kasir'] ? $transaksi['nama_kasir'] : '-',
    "items" => $items
];

header('Content-Type: application/json');
echo json_encode($orderData);
?>
