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
                $_SESSION['role']     = $row['role'];
                $_SESSION['show_welcome_anim'] = true;

                // Redirect sesuai role
                if ($row['role'] === 'kasir') {
                    header("Location: /seraya/admin_kasir/dashboard.php");
                    exit;
                } elseif ($row['role'] === 'dapur') {
                    header("Location: /seraya/admin_dapur/dashboard.php");
                    exit;
                } elseif ($row['role'] === 'manager') {
                    header("Location: /seraya/admin_manager/dashboard.php");
                    exit;
                } else {
                    $error_msg = 'Role tidak dikenali!';
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

  <!-- 🚀 Page Transition Overlay -->
  <div class="page-transition-overlay" id="pageTransition">
    <div class="transition-wipe"></div>
    <div class="transition-content">
      <div class="transition-logo">WARKOP <span>SERAYA</span></div>
      <div class="transition-loader">
        <div class="transition-loader-dot"></div>
        <div class="transition-loader-dot"></div>
        <div class="transition-loader-dot"></div>
      </div>
    </div>
  </div>

  <!-- 🌬️ Lapisan animasi hidup (Digital Swarm) -->
  <div class="breeze">
    <div class="geom square"></div><div class="geom square"></div><div class="geom square"></div><div class="geom square"></div><div class="geom square"></div>
    <div class="geom square"></div><div class="geom square"></div><div class="geom square"></div><div class="geom square"></div><div class="geom square"></div>
    <div class="geom dot"></div><div class="geom dot"></div><div class="geom dot"></div><div class="geom dot"></div><div class="geom dot"></div>
    <div class="geom dot"></div><div class="geom dot"></div><div class="geom dot"></div><div class="geom dot"></div><div class="geom dot"></div>
    <div class="geom tri"></div><div class="geom tri"></div><div class="geom tri"></div><div class="geom tri"></div><div class="geom tri"></div>
    <div class="geom tri"></div><div class="geom tri"></div><div class="geom tri"></div><div class="geom tri"></div><div class="geom tri"></div>
  </div>

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

    // 🖱️ Mouse Parallax Effect
    document.addEventListener('mousemove', (e) => {
      const x = (window.innerWidth / 2 - e.pageX) / 40;
      const y = (window.innerHeight / 2 - e.pageY) / 40;
      
      const geoms = document.querySelectorAll('.geom');
      geoms.forEach((el, i) => {
        const factor = (i % 5) + 1;
        el.style.transform = `translate(${x * factor}px, ${y * factor}px) rotate(${(x + y) * factor}deg)`;
      });
    });

    // 🚀 Login Form — AJAX + Page Transition
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(loginForm);
        const btn = loginForm.querySelector('.btn');
        const btnText = btn.querySelector('.btn-text');
        
        // Disable button & show loading
        btn.disabled = true;
        btnText.textContent = 'Memproses...';

        fetch('login.php', {
          method: 'POST',
          body: formData
        })
        .then(res => {
          // If redirected (login success), the response URL will differ
          if (res.redirected) {
            // 🎬 Play exit transition then redirect
            playPageTransition(res.url);
          } else {
            // Login failed — reload page to show error
            return res.text().then(html => {
              document.open();
              document.write(html);
              document.close();
            });
          }
        })
        .catch(err => {
          console.error('Login error:', err);
          btn.disabled = false;
          btnText.textContent = 'Login';
        });
      });
    }

    function playPageTransition(redirectUrl) {
      const overlay = document.getElementById('pageTransition');
      const container = document.querySelector('.container');
      const breeze = document.querySelector('.breeze');

      // 1. Animate login card out
      if (container) {
        container.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
        container.style.opacity = '0';
        container.style.transform = 'scale(0.9) translateY(-30px)';
        container.style.filter = 'blur(8px)';
      }

      // 2. Fade out geometric particles
      if (breeze) {
        breeze.style.transition = 'opacity 0.4s ease';
        breeze.style.opacity = '0';
      }

      // 3. After card exits, bring in the wipe overlay
      setTimeout(() => {
        overlay.classList.add('active');
      }, 350);

      // 4. Redirect after full transition completes
      setTimeout(() => {
        window.location.href = redirectUrl;
      }, 1200);
    }
  </script>

</body>
</html>
