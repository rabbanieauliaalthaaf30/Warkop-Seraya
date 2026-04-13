<?php
session_start();
include "koneksi.php";

// Cek koneksi database
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error_msg = 'Username dan password harus diisi!';
    } else {
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
                $_SESSION['level']    = $row['level'];

                // Redirect sesuai level
                if ($row['level'] === 'admin') {
                    header("Location: /seraya/admin_kasir/dashboard.php");
                    exit;
                } elseif ($row['level'] === 'super admin') {
                    header("Location: /seraya/admin_dapur/dashboard.php");
                    exit;
                } else {
                    $error_msg = 'Level tidak dikenali!';
                }
            } else {
                $error_msg = 'Password salah!';
            }
        } else {
            $error_msg = 'Username tidak ditemukan!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Login Admin Warkop Seraya" />
  <title>Admin Login — Warkop Seraya</title>
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

      <?php if ($error_msg): ?>
      <div class="alert-error">
        ⚠️ <?= htmlspecialchars($error_msg) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="login.php" autocomplete="off" id="login-form">

        <div class="form-group">
          <label for="username">Username</label>
          <div class="input-wrap">
            <span class="input-icon">👤</span>
            <input
              type="text"
              id="username"
              name="username"
              placeholder="Masukkan Username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              required
            />
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Masukkan Password"
              required
            />
            <button type="button" class="toggle-pass" id="toggle-pass" title="Tampilkan Password">
              <span id="eye-icon">👁️</span>
            </button>
          </div>
        </div>

        <div class="form-remember">
          <label for="remember">
            <input type="checkbox" id="remember" name="remember" />
            Remember Me?
          </label>
        </div>

        <button type="submit" class="btn">
          <span class="btn-text">Login</span>
        </button>

      </form>
    </div>

  </div>

  <script>
    // Toggle show/hide password
    const toggleBtn = document.getElementById('toggle-pass');
    const passInput = document.getElementById('password');
    const eyeIcon   = document.getElementById('eye-icon');

    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        const show = passInput.type === 'password';
        passInput.type = show ? 'text' : 'password';
        eyeIcon.textContent = show ? '🙈' : '👁️';
      });
    }
  </script>

</body>
</html>
