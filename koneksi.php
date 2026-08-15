<?php
$host = "localhost";     
$user = "root";          
$pass = "";             
$db   = "db_seraya";    

// Set timezone PHP ke WIB agar date() konsisten dengan waktu lokal
date_default_timezone_set('Asia/Jakarta');

$conn = mysqli_connect($host, $user, $pass, $db);

// cek koneksi
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set timezone MySQL ke WIB (UTC+7) agar waktu tersimpan sesuai waktu lokal
mysqli_query($conn, "SET time_zone = '+07:00'");
?>
