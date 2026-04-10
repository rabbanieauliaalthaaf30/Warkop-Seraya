<?php
session_start();
include "koneksi.php";

// Cek koneksi database
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        echo "<script>alert('Username dan password harus diisi!'); window.location.href='login.php';</script>";
        exit;
    }

    // Prepare statement untuk menghindari SQL Injection
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? LIMIT 1");
    if (!$stmt) {
        die("Prepare statement gagal: " . $conn->error);
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $db_password = $row['password'];

        // Jika pakai hashing, gunakan password_verify
        // if (password_verify($password, $db_password)) {
        if ($password === $db_password) { // sementara pakai ini jika belum hashing

            // Set session
            $_SESSION['id_admin'] = $row['id_admin'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['level'] = $row['level'];

            // Redirect sesuai level
            if ($row['level'] === 'admin') {
                header("Location: /Project/admin_kasir/dashboard.php");
                exit;
            } elseif ($row['level'] === 'super admin') {
                header("Location: /Project/admin_dapur/dashboard.php");
                exit;
            } else {
                echo "<script>alert('Level tidak dikenali!'); window.location.href='login.php';</script>";
                exit;
            }
        } else {
            // Password salah
            echo "<script>alert('Password salah!'); window.location.href='login.php';</script>";
            exit;
        }
    } else {
        // Username tidak ditemukan
        echo "<script>alert('Username tidak ditemukan!'); window.location.href='login.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login</title>
  <link rel="stylesheet" href="css/login.css" />
</head>
<body>

  <!-- 🌬️ Lapisan angin lembut -->
  <div class="breeze"></div>

  <!-- 🌅 Konten utama -->
  <div class="container">
    <h1 class="title"><span>WARKOP</span> SERAYA</h1>

    <div class="login-box">
      <h2>LOGIN</h2>
      <form method="POST" action="login.php" autocomplete="off">
        <div class="form-group">
          <label for="username">Username</label>
          <input
            type="text"
            id="username"
            name="username"
            placeholder="Masukkan Username"
            required
          />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Masukkan Password"
            required
          />
        </div>

        <div class="form-remember">
          <label>
            <input type="checkbox" name="remember" />
            Remember Me?
          </label>
        </div>

        <button type="submit" class="btn">Login</button>
      </form>
    </div>
  </div>
</body>
</html>
