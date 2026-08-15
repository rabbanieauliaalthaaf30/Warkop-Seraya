-- ============================================
-- INVENTORY MODULE - db_seraya
-- Jalankan file ini di phpMyAdmin:
-- Database: db_seraya → Import → pilih file ini
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- TABEL 1: stok_barang
-- ============================================
CREATE TABLE IF NOT EXISTS `stok_barang` (
  `id_stok`       INT NOT NULL AUTO_INCREMENT,
  `id_produk`     INT DEFAULT NULL COMMENT 'Link ke produk jika sachet/1:1',
  `nama_barang`   VARCHAR(100) NOT NULL,
  `satuan`        ENUM('sachet','bungkus','butir','botol','liter','ml','gram','kg','porsi','pcs') NOT NULL DEFAULT 'sachet',
  `stok_saat_ini` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `stok_minimum`  DECIMAL(10,2) NOT NULL DEFAULT 5,
  `keterangan`    VARCHAR(255) DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_stok`),
  KEY `fk_stok_produk` (`id_produk`),
  CONSTRAINT `fk_stok_produk`
    FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================
-- TABEL 2: resep_menu
-- Hanya untuk menu yang butuh beberapa bahan (racikan)
-- Menu sachet tidak perlu diisi di sini
-- ============================================
CREATE TABLE IF NOT EXISTS `resep_menu` (
  `id_resep`     INT NOT NULL AUTO_INCREMENT,
  `id_produk`    INT NOT NULL,
  `id_stok`      INT NOT NULL,
  `jumlah_pakai` DECIMAL(10,2) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_resep`),
  UNIQUE KEY `uq_produk_stok` (`id_produk`, `id_stok`),
  KEY `fk_resep_produk` (`id_produk`),
  KEY `fk_resep_stok` (`id_stok`),
  CONSTRAINT `fk_resep_produk`
    FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_resep_stok`
    FOREIGN KEY (`id_stok`) REFERENCES `stok_barang` (`id_stok`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================
-- TABEL 3: riwayat_stok
-- Log semua perubahan stok
-- ============================================
CREATE TABLE IF NOT EXISTS `riwayat_stok` (
  `id_riwayat`   INT NOT NULL AUTO_INCREMENT,
  `id_stok`      INT NOT NULL,
  `id_admin`     INT DEFAULT NULL,
  `id_transaksi` INT DEFAULT NULL,
  `jenis`        ENUM('masuk','keluar','koreksi') NOT NULL,
  `jumlah`       DECIMAL(10,2) NOT NULL,
  `stok_sebelum` DECIMAL(10,2) NOT NULL,
  `stok_sesudah` DECIMAL(10,2) NOT NULL,
  `sumber`       ENUM('pesanan','belanja','koreksi_manual') NOT NULL,
  `keterangan`   VARCHAR(255) DEFAULT NULL,
  `waktu`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_riwayat`),
  KEY `fk_riwayat_stok` (`id_stok`),
  KEY `fk_riwayat_admin` (`id_admin`),
  KEY `fk_riwayat_transaksi` (`id_transaksi`),
  CONSTRAINT `fk_riwayat_stok`
    FOREIGN KEY (`id_stok`) REFERENCES `stok_barang` (`id_stok`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_riwayat_admin`
    FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_riwayat_transaksi`
    FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


SET FOREIGN_KEY_CHECKS = 1;
