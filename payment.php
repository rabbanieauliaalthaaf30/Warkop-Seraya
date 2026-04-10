<?php
include "koneksi.php";

function esc($conn, $val) {
    return mysqli_real_escape_string($conn, trim($val));
}

// ==========================================================
// 1️⃣ HANDLE POST DARI MENU.JS (BUAT TRANSAKSI BARU)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['keranjang'])) {
    $nama_pemesan = esc($conn, $_POST['nama_pemesan'] ?? '');
    $nomor_meja   = esc($conn, $_POST['nomor_meja'] ?? '');
    $total        = (float)($_POST['total'] ?? 0);

    $keranjang_raw = $_POST['keranjang'];
    $keranjang = json_decode($keranjang_raw, true);
    if (!is_array($keranjang) || empty($keranjang)) die("Keranjang kosong atau format tidak valid.");

    // ⏳ Insert transaksi status awal: DRAFT
    $sql = "INSERT INTO transaksi (nama_pemesan, nomor_meja, status_pesanan, total)
            VALUES ('$nama_pemesan', '$nomor_meja', 'draft', '$total')";
    if (!mysqli_query($conn, $sql)) die("Error insert transaksi: " . mysqli_error($conn));
    $id_transaksi = mysqli_insert_id($conn);

    // Insert detail transaksi
    foreach ($keranjang as $item) {
        $id_produk = (int)($item['id_produk'] ?? $item['id'] ?? 0);
        $id_varian = isset($item['id_varian']) ? (int)$item['id_varian'] : "NULL";
        $qty = (int)($item['qty'] ?? 1);
        $price = (float)($item['price'] ?? 0);
        $note = esc($conn, $item['note'] ?? '');
        $subtotal = $qty * $price;

        $sqlDetail = "INSERT INTO detail_transaksi (id_transaksi, id_produk, id_varian, quantity, catatan, subtotal)
                      VALUES ('$id_transaksi', '$id_produk', " . ($id_varian === "NULL" ? "NULL" : "'$id_varian'") . ", '$qty', '$note', '$subtotal')";
        if (!mysqli_query($conn, $sqlDetail)) die("Error insert detail_transaksi: " . mysqli_error($conn));
    }

    // Insert pembayaran awal
    $sqlBayar = "INSERT INTO pembayaran (id_transaksi, metode, jumlah, status, bukti_file)
                 VALUES ('$id_transaksi', '', '$total', 'belum bayar', NULL)";
    if (!mysqli_query($conn, $sqlBayar)) die("Error insert pembayaran: " . mysqli_error($conn));

    header("Location: payment.php?id=" . $id_transaksi);
    exit;
}

// ==========================================================
// 2️⃣ HANDLE KLIK “BAYAR” (cash / transfer / qris)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orderId']) && !isset($_POST['keranjang'])) {
    $id_transaksi = (int)$_POST['orderId'];
    $metode = esc($conn, $_POST['method'] ?? 'cash');
    $email = esc($conn, $_POST['email'] ?? '');
    $bukti_file = null;

    // ===== Upload bukti (jika ada) =====
    if (isset($_FILES['buktiBayar']) && $_FILES['buktiBayar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . "/uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES['buktiBayar']['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['buktiBayar']['tmp_name'], $targetFile)) {
            $bukti_file = $fileName;
        }
    }

    // Tentukan status pembayaran
    $status_bayar = ($metode === 'cash') ? 'belum bayar' : 'sudah bayar';

    // ===== Update tabel pembayaran =====
    if (!empty($bukti_file)) {
        $update = "
            UPDATE pembayaran 
            SET metode = '$metode',
                status = '$status_bayar',
                email = '$email',
                bukti_file = '$bukti_file'
            WHERE id_transaksi = '$id_transaksi'
        ";
    } else {
        $update = "
            UPDATE pembayaran 
            SET metode = '$metode',
                status = '$status_bayar',
                email = '$email'
            WHERE id_transaksi = '$id_transaksi'
        ";
    }

    if (!mysqli_query($conn, $update)) {
        die('Error update pembayaran: ' . mysqli_error($conn));
    }

    // ===== Update status pesanan =====
    $updateStatus = "
        UPDATE transaksi 
        SET status_pesanan = 'pending', waktu_pemesanan = CURRENT_TIMESTAMP
        WHERE id_transaksi = '$id_transaksi'
    ";
    if (!mysqli_query($conn, $updateStatus)) {
        die('❌ Gagal update status pesanan: ' . mysqli_error($conn));
    }

    echo "<script>alert('✅ Pembayaran berhasil diproses!'); window.location='payment.php?id=$id_transaksi';</script>";
    exit;
}

// ==========================================================
// 3️⃣ TAMPILKAN DATA TRANSAKSI
// ==========================================================
$id_transaksi = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_transaksi <= 0) die("ID transaksi tidak ditemukan!");

$qTrans = mysqli_query($conn, "SELECT * FROM transaksi WHERE id_transaksi=$id_transaksi");
if (!$qTrans) die("Query transaksi gagal: " . mysqli_error($conn));
$transaksi = mysqli_fetch_assoc($qTrans);
if (!$transaksi) die("Transaksi tidak ditemukan!");

$qDetail = mysqli_query($conn, "
    SELECT 
        COALESCE(p.nama_produk, d.catatan, 'Produk tidak terdaftar') AS nama_produk,
        d.quantity,
        d.subtotal,
        CASE 
            WHEN p.harga_produk IS NOT NULL THEN p.harga_produk
            WHEN d.quantity > 0 THEN d.subtotal / d.quantity
            ELSE 0
        END AS harga_produk,
        d.id_varian
    FROM detail_transaksi d
    LEFT JOIN produk p ON d.id_produk = p.id_produk
    WHERE d.id_transaksi=$id_transaksi
");
$detailPesanan = [];
while ($row = mysqli_fetch_assoc($qDetail)) $detailPesanan[] = $row;

$orderData = [
    "id" => $transaksi['id_transaksi'],
    "nama_pemesan" => $transaksi['nama_pemesan'],
    "nomor_meja" => $transaksi['nomor_meja'],
    "status_pesanan" => $transaksi['status_pesanan'],
    "waktu_pemesanan" => $transaksi['waktu_pemesanan'],
    "total" => (float)$transaksi['total'],
    "items" => $detailPesanan
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Warkop Seraya - Payment</title>
  <script src="https://unpkg.com/feather-icons"></script>
  <link rel="stylesheet" href="css/payment.css" />
</head>
<body>
  <div class="pg-container">
    <div class="card">
      <h2>Metode Pembayaran</h2>

      <div id="orderInfo" class="info-box">
        <p><strong>Nama Pemesan:</strong> <?= htmlspecialchars($transaksi['nama_pemesan']) ?></p>
        <p><strong>No. Meja:</strong> <?= htmlspecialchars($transaksi['nomor_meja']) ?></p>
        <p><strong>Status Pesanan:</strong> <?= htmlspecialchars($transaksi['status_pesanan']) ?></p>
        <p><strong>Waktu:</strong> <?= htmlspecialchars($transaksi['waktu_pemesanan']) ?></p>
      </div>

      <form method="POST" action="" id="paymentForm" enctype="multipart/form-data">
        <input type="hidden" name="orderId" value="<?= (int)$id_transaksi ?>" />
        <input type="hidden" name="method" id="method" />
        <input type="hidden" name="total" value="<?= (float)$transaksi['total'] ?>" />


        <div class="payment-methods">
          <h3>Virtual Account (Bank)</h3>
          <div class="method-grid">
            <div class="method" data-method="transfer">
              <img src="image_logo/bca.jpg" alt="BCA" /><span>BCA</span>
            </div>
          </div>
        </div>

        <div class="payment-methods">
          <h3>QRIS</h3>
          <div class="method-grid">
            <div class="method" data-method="qris">
              <img src="image_logo/qris.jpg" alt="QRIS" /><span>QRIS</span>
            </div>
          </div>
        </div>

        <div class="payment-methods">
          <h3>Bayar Langsung</h3>
          <div class="method-grid">
            <div class="method kasir" data-method="cash">
              <img src="image_logo/kasir.jpg" alt="Kasir" /><span>Bayar di Kasir</span>
            </div>
          </div>
        </div>

        <!-- REKENING --> 
        <div id="rekeningInfo" class="rekening-card"> 
        <div class="rekening-title">Nomor Rekening</div> 
        <div class="rekening-box"> <div class="bank-row"> <span class="bank-name" id="bankName">BCA - Warkop Seraya</span> </div> 
        <div class="rekening-row"> <span class="bank-number" id="bankNumber">4731720686</span> 
        <button type="button" id="copyRekeningBtn" class="copy-btn">Salin</button> </div> 
        <div class="bank-owner" id="bankOwner">Warkop Seraya</div> </div> </div>

        <!-- QR CODE -->
        <div id="qrisImage" style="margin-top:20px; display:none; text-align:center;">
          <img src="image_logo/barcode.jpeg" alt="QRIS Barcode" style="max-width:250px; border:1px solid #ccc; border-radius:8px; padding:5px;">
        </div>

        <div id="emailInput" style="margin-top:15px; display:none;">
          <label for="email">Alamat Email</label>
          <input type="email" name="email" id="email" placeholder="Masukkan alamat email" />
        </div>

        <div id="uploadBukti" class="upload-bukti" style="margin-top:15px;">
          <label for="buktiBayar">Upload Bukti Pembayaran</label>
          <input type="file" id="buktiBayar" name="buktiBayar" accept="image/*" />
        </div>

        <div class="total-box">
          <h3>Total Pesanan:</h3>
          <div id="totalAmount" class="total-amount">
            Rp <?= number_format((float)$transaksi['total'], 0, ',', '.') ?>
          </div>
        </div>

        <div class="btn-row">
          <button class="btn danger" type="button" onclick="history.back();">
            <i data-feather="x-circle"></i> Batal
          </button>
          <button class="btn success" type="submit">
            <i data-feather="check-circle"></i> Bayar
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const orderFromDB = <?= json_encode($orderData) ?>;
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
  <script src="js/payment.js"></script>
  <script>feather.replace();</script>
  <!-- Popup Struk -->
  <div id="receiptModal" class="receipt-overlay" style="display:none">
    <div class="receipt-card" id="receiptContent">
      <div class="receipt-header">
        <h2><span class="warkop">Warkop</span> <span class="seraya">Seraya</span></h2>
        <div class="receipt-line"></div>
        <p class="title">Bukti Pembayaran</p>
      </div>
      <div id="receiptDetails"></div>
      <div class="receipt-footer">
        <button id="downloadReceiptBtn" class="download-btn">Unduh</button>
        <button class="close-btn" onclick="closeReceipt()">Tutup</button>
      </div>
    </div>
  </div>
</body>
</html>
