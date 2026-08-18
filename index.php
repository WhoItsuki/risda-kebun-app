<?php
session_start();

// Redirect to admin dashboard if session is already active
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Maklumat Kebun - RISDA</title>
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
        .portal-card {
            max-width: 460px;
            width: 100%;
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            background: #ffffff;
        }
        .brand-header {
            background-color: var(--primary-color);
            color: #ffffff;
            border-radius: 16px 16px 0 0;
            padding: 2rem 1.5rem;
        }
        .btn-primary-custom {
            background-color: var(--accent-color);
            border: none;
            color: #fff;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-primary-custom:hover {
            background-color: var(--primary-color);
            color: #fff;
        }
    </style>
</head>
<body>

<div class="container p-3 d-flex justify-content-center">
    <div class="card portal-card overflow-hidden">
        <div class="brand-header text-center">
            <img src="assets/images/logo-risda.png" alt="Logo RISDA" class="mb-2" style="height: 80px; width: auto;">
            <h4 class="fw-bold mb-1">Sistem Maklumat Kebun</h4>
            <p class="small mb-0 opacity-75">RISDA Tanam Semula</p>
        </div>
        <div class="card-body p-4 text-center">
            
            <div class="mb-4">
                <div class="p-3 bg-light rounded-3 text-start border mb-3">
                    <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-qr-code-scan text-success fs-5 me-2"></i>
                        <h6 class="fw-bold mb-0 text-dark">Pengunjung / Pekebun</h6>
                    </div>
                    <p class="text-muted small mb-0">
                        Sila imbas Kod QR di lokasi kebun menggunakan kamera telefon anda untuk melihat maklumat kebun dan panduan ansuran.
                    </p>
                </div>

                <div class="p-3 bg-light rounded-3 text-start border">
                    <div class="d-flex align-items-center mb-1">
                        <i class="bi bi-shield-lock-fill text-primary fs-5 me-2"></i>
                        <h6 class="fw-bold mb-0 text-dark">Portal Pentadbir</h6>
                    </div>
                    <p class="text-muted small mb-0">
                        Log masuk untuk mengurus rekod kebun, mengemaskini maklumat, dan menjana Kod QR baharu.
                    </p>
                </div>
            </div>

            <a href="admin/login.php" class="btn btn-primary-custom w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Log Masuk Pentadbir
            </a>

        </div>
        <div class="card-footer bg-white border-0 text-center pb-3">
            <small class="text-muted">&copy; <?= date('Y') ?> RISDA - Hak Cipta Terpelihara</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>