<?php
require_once '../config/database.php';
require_once 'auth.php';

// Redirect if already authenticated
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Sila isi semua ruangan.';
    } else {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // Set session details
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Nama pengguna atau kata laluan tidak sah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk Pentadbir - RISDA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1b4332;
            --accent-color: #2d6a4f;
            --bg-light: #f8f9fa;
        }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            width: 100%;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            background: #ffffff;
        }
        .card-header-custom {
            background-color: var(--primary-color);
            color: #ffffff;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.5rem 1rem;
        }
        .btn-success-custom {
            background-color: var(--accent-color);
            border: none;
            color: #fff;
            padding: 0.65rem;
            font-weight: 600;
        }
        .btn-success-custom:hover {
            background-color: var(--primary-color);
            color: #fff;
        }
    </style>
</head>
<body>

<div class="container p-3 d-flex justify-content-center">
    <div class="card login-card overflow-hidden">
        <div class="card-header-custom text-center">
            <h5 class="fw-bold mb-0">Portal Pentadbir RISDA</h5>
            <small class="opacity-75">Sila log masuk untuk meneruskan</small>
        </div>
        <div class="card-body p-4">
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold">Nama Pengguna</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required placeholder="Contoh: admin">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label small fw-semibold">Kata Laluan</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn btn-success-custom w-100 rounded-3 mb-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Log Masuk
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="../index.php" class="text-decoration-none small text-muted">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Utama
                </a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>