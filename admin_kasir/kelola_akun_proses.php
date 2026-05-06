<?php
session_start();
include "../koneksi.php";

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'kasir') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';

    if (empty($user) || empty($pass) || empty($role)) {
        echo json_encode(['status' => 'error', 'message' => 'Semua field harus diisi']);
        exit;
    }

    // Cek username sudah ada atau belum
    $check = $conn->prepare("SELECT id_admin FROM admin WHERE username = ?");
    $check->bind_param("s", $user);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username sudah terdaftar']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO admin (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user, $pass, $role);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Akun berhasil ditambahkan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan akun: ' . $conn->error]);
    }

} elseif ($action === 'edit') {
    $id   = $_POST['id_admin'] ?? '';
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';

    if (empty($id) || empty($user) || empty($role)) {
        echo json_encode(['status' => 'error', 'message' => 'Username dan Role harus diisi']);
        exit;
    }

    if (!empty($pass)) {
        $stmt = $conn->prepare("UPDATE admin SET username = ?, password = ?, role = ? WHERE id_admin = ?");
        $stmt->bind_param("sssi", $user, $pass, $role, $id);
    } else {
        $stmt = $conn->prepare("UPDATE admin SET username = ?, role = ? WHERE id_admin = ?");
        $stmt->bind_param("ssi", $user, $role, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Akun berhasil diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui akun']);
    }

} elseif ($action === 'delete') {
    $id = $_POST['id_admin'] ?? '';

    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
        exit;
    }

    // Jangan biarkan hapus diri sendiri (opsional tapi disarankan)
    if ($id == $_SESSION['id_admin']) {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak bisa menghapus akun Anda sendiri']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM admin WHERE id_admin = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Akun berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus akun']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali']);
}
