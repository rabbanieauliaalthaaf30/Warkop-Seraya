<?php
session_start();
include "koneksi.php";

// =============================
// Normalisasi koneksi
// =============================
$db = null;
if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} elseif (isset($koneksi) && $koneksi instanceof mysqli) {
    $db = $koneksi;
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
}

if (!$db) {
    die("Error: koneksi database tidak ditemukan. Pastikan file koneksi.php mendefinisikan \$conn atau \$koneksi (mysqli).");
}


// =============================
// Ambil kategori unik dari tabel produk
// =============================
$categories = [];
$qKategori = mysqli_query($db, "SELECT DISTINCT kategori FROM produk ORDER BY kategori ASC");
if ($qKategori) {
    while ($row = mysqli_fetch_assoc($qKategori)) {
        if (!empty($row['kategori'])) {
            $categories[] = $row['kategori'];
        }
    }
}

// =============================
// Urutan kategori custom
// =============================
$customOrder = [
    "Kopi Series",
    "Good Day",
    "Nutrisari",
    "Susu Series",
    "Signature",
    "Cemilan",
    "Aneka Nasi",
    "Aneka Mie"
];

// =============================
// Susun ulang kategori sesuai urutan di atas
// =============================
$orderedCategories = [];
foreach ($customOrder as $wanted) {
    foreach ($categories as $cat) {
        if (strcasecmp($cat, $wanted) === 0) { // case-insensitive match
            $orderedCategories[] = $cat;
            break;
        }
    }
}

// Tambahkan kategori lain (yang tidak termasuk dalam urutan custom)
foreach ($categories as $cat) {
    if (!in_array($cat, $orderedCategories)) {
        $orderedCategories[] = $cat;
    }
}

$categories = $orderedCategories;


$displayed = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Warkop Seraya - Menu</title>

  <!-- Fonts & CSS -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/menu.css" />

  <!-- Feather Icons -->
  <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>
  <!-- Tombol Keranjang -->
  <div class="cart-icon" onclick="toggleCart()">
    <i data-feather="shopping-cart"></i><span id="cart-count">0</span>
  </div>

  <section id="menu" class="menu">
    <h1>Menu <span>Kami</span></h1>

    <?php
    // =============================
    // Loop kategori
    // =============================
    foreach ($categories as $kategori) {
        echo "<h2>" . htmlspecialchars($kategori) . "</h2>";
        echo '<div class="menu-scroll"><div class="row">';

        $sql = "SELECT * FROM produk WHERE kategori = '" . mysqli_real_escape_string($db, $kategori) . "' ORDER BY id_produk ASC";
        $res = mysqli_query($db, $sql);

        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $id    = $row['id_produk'];
                if (in_array($id, $displayed, true)) continue;

                $nama  = $row['nama_produk'];
                $harga = isset($row['harga_produk']) ? $row['harga_produk'] : 0;
                $img   = $row['image_url'];
                $status = isset($row['status']) ? $row['status'] : 'tersedia';
                $isAvailable = ($status === 'tersedia');

                // Ambil varian
                $varianData = [];
                $qVar = mysqli_query($db, "SELECT * FROM varian_produk WHERE id_produk = " . (int)$id);
                if ($qVar && mysqli_num_rows($qVar) > 0) {
                    while ($v = mysqli_fetch_assoc($qVar)) {
                        $varianData[] = [
                            "id_varian" => $v['id_varian'],
                            "nama_varian" => $v['nama_varian'],
                            "harga_varian" => (float)$v['harga_varian']
                        ];
                    }
                }

                $jsVarian = json_encode($varianData, JSON_UNESCAPED_UNICODE);
                $jsName   = json_encode($nama, JSON_UNESCAPED_UNICODE);
                $jsImg    = json_encode("image_menu/" . $img, JSON_UNESCAPED_UNICODE);

                $onclick = $isAvailable
                    ? "onclick='openPopup({$jsName}, null, null, {$jsImg}, {$jsVarian}, " . (float)$harga . ", {$id})'"
                    : "";

                $cardClass = $isAvailable ? 'menu-card' : 'menu-card unavailable';
                ?>
                <div class="<?= $cardClass ?>" data-product-id="<?= $id ?>" <?= $onclick ?>>
                    <img src="image_menu/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($nama) ?>" class="menu-card-img" />
                    <h3 class="menu-card-tittle"><?= htmlspecialchars($nama) ?></h3>
                    <p class="menu-card-price">Rp <?= number_format((float)$harga, 0, ',', '.') ?></p>
                    <?php if (!$isAvailable): ?>
                        <span class="menu-unavailable">Habis</span>
                    <?php endif; ?>
                </div>
                <?php
                $displayed[] = $id;
            }
        }

        echo '</div></div>';
    }

    // =============================
    // Produk tanpa kategori
    // =============================
    $leftoverSql = "SELECT * FROM produk WHERE kategori IS NULL OR kategori = ''";
    $leftRes = mysqli_query($db, $leftoverSql);
    if ($leftRes && mysqli_num_rows($leftRes) > 0) {
        echo "<h2>Lainnya</h2>";
        echo '<div class="menu-scroll"><div class="row">';
        while ($row = mysqli_fetch_assoc($leftRes)) {
            $id    = $row['id_produk'];
            $nama  = $row['nama_produk'];
            $harga = isset($row['harga_produk']) ? $row['harga_produk'] : 0;
            $img   = $row['image_url'];
            $status = isset($row['status']) ? $row['status'] : 'tersedia';
            $isAvailable = ($status === 'tersedia');

            $varianData = [];
            $qVar = mysqli_query($db, "SELECT * FROM varian_produk WHERE id_produk = " . (int)$id);
            if ($qVar && mysqli_num_rows($qVar) > 0) {
                while ($v = mysqli_fetch_assoc($qVar)) {
                    $varianData[] = [
                        "id_varian" => $v['id_varian'],
                        "nama_varian" => $v['nama_varian'],
                        "harga_varian" => (float)$v['harga_varian']
                    ];
                }
            }

            $jsVarian = json_encode($varianData, JSON_UNESCAPED_UNICODE);
            $jsName   = json_encode($nama, JSON_UNESCAPED_UNICODE);
            $jsImg    = json_encode("image_menu/" . $img, JSON_UNESCAPED_UNICODE);

            $onclick = $isAvailable
                ? "onclick='openPopup({$jsName}, null, null, {$jsImg}, {$jsVarian}, " . (float)$harga . ", {$id})'"
                : "";
            $cardClass = $isAvailable ? 'menu-card' : 'menu-card unavailable';
            ?>
            <div class="<?= $cardClass ?>" data-product-id="<?= $id ?>" <?= $onclick ?>>
                <img src="image_menu/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($nama) ?>" class="menu-card-img" />
                <h3 class="menu-card-tittle"><?= htmlspecialchars($nama) ?></h3>
                <p class="menu-card-price">Rp <?= number_format((float)$harga, 0, ',', '.') ?></p>
                <?php if (!$isAvailable): ?>
                    <span class="menu-unavailable">Habis</span>
                <?php endif; ?>
            </div>
            <?php
        }
        echo '</div></div>';
    }
    ?>
  </section>

  <!-- POPUP -->
  <div class="popup-overlay" id="popup" style="display: none">
    <div class="popup-card">
      <button type="button" class="popup-close" id="popup-close">×</button>
      <h3 class="popup-title">Tambahkan <span>Menu</span></h3>
      <div class="popup-content">
        <img src="" alt="Menu" class="popup-img" id="popup-img" />
        <h4 class="popup-menu-name" id="popup-subtitle">Nama Menu</h4>
        <p class="popup-price" id="popup-price">Rp 0</p>
        <div class="popup-option" id="popup-options"></div>
        <div class="popup-quantity">
          <button type="button" id="btn-minus">-</button>
          <span id="qty-display">0</span>
          <button type="button" id="btn-plus">+</button>
        </div>
        <textarea class="popup-note" id="note" placeholder="Catatan pesanan"></textarea>
      </div>
      <div class="popup-spacer"></div>
      <button class="popup-add-button" id="add-to-cart-btn">Masukkan ke Keranjang</button>
    </div>
  </div>

  <!-- KERANJANG -->
  <div class="cart" id="cart" style="display:none">
    <div class="cart-header">
      <span>Keranjang Saya</span>
      <button onclick="closeCart()">x</button>
    </div>
    <div class="cart-items" id="cart-items"></div>
    <div class="cart-total">
      <p>Total: <span id="cart-total">Rp0</span></p>
      <button class="checkout-btn">Checkout</button>
    </div>
  </div>

  <!-- PESANAN -->
  <div class="order-popup" id="order-popup" style="display: none">
    <div class="order-header">
      <span>Pesanan Anda</span>
      <button onclick="closeOrderPopup()">x</button>
    </div>
    <div class="order-body">
      <input type="text" id="customer-name" placeholder="Nama Pemesan" />
      <input type="text" id="table-number" placeholder="Nomor Meja" class="short-input" />
      <p class="section-title">Rincian Pesanan</p>
      <div class="order-items" id="order-items"></div>
      <div class="order-total">
        <p>Total: <span id="order-total">Rp0</span></p>
      </div>
    </div>
    <div class="order-footer">
      <form id="checkout-form" method="POST">
        <input type="hidden" name="nama_pemesan" id="form-nama">
        <input type="hidden" name="nomor_meja" id="form-meja">
        <input type="hidden" name="total" id="form-total">
        <input type="hidden" name="keranjang" id="form-keranjang">
        <button type="button" class="order-btn" onclick="confirmOrder()">Pesan</button>
      </form>
    </div>
  </div>

  <script>feather.replace();</script>
  <script src="js/menu.js?v=<?php echo time(); ?>"></script>
</body>
</html>
