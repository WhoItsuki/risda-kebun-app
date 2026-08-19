<?php
require_once 'auth.php';
requireAdminLogin();
require_once '../config/database.php';

$kebun_id = $_GET['id'] ?? 0;
$db = getDBConnection();

// Ambil maklumat Kebun & Pekebun
$stmt = $db->prepare("
    SELECT k.*, p.nama AS nama_pekebun, p.no_telefon, p.alamat 
    FROM kebun k 
    JOIN pekebun p ON k.pekebun_id = p.id 
    WHERE k.id = ?
");
$stmt->execute([$kebun_id]);
$kebun = $stmt->fetch();

if (!$kebun) {
    header("Location: dashboard.php");
    exit();
}

// Kawalan Host/IP untuk ujian imbasan telefon bimbit
$current_host = $_GET['host'] ?? $_SERVER['HTTP_HOST'];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";

// Tentukan URL sasaran pengimbasan QR
$scan_url = "{$protocol}://{$current_host}/risda-kebun-app/view.php?hash=" . urlencode($kebun['qr_code_hash']);

// API Penjana Kod QR
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=" . urlencode($scan_url);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plat Kod QR - Lot <?= htmlspecialchars($kebun['no_lot']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1b4332;
            --accent-color: #2d6a4f;
            --bg-light: #f4f6f8;
        }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-custom { background-color: var(--primary-color); }
        
        /* Kad Plat QR Rasmi */
        .qr-plate {
            background: #ffffff;
            border: 3px solid #1b4332;
            border-radius: 16px;
            max-width: 450px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        .qr-header {
            background-color: var(--primary-color);
            color: #ffffff;
            padding: 1.2rem 1rem;
            text-align: center;
        }
        .qr-frame {
            background: #f8f9fa;
            border: 2px dashed #2d6a4f;
            border-radius: 12px;
            padding: 12px;
            display: inline-block;
        }
        .qr-img {
            width: 220px;
            height: 220px;
            border-radius: 6px;
        }
        .info-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        /* Tetapan Cetakan (A4 / Plat) */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; padding: 0; margin: 0; }
            .container { max-width: 100% !important; width: 100% !important; padding: 0; }
            .qr-plate {
                border: 3px solid #000;
                box-shadow: none !important;
                margin: 40px auto;
                page-break-inside: avoid;
            }
            .qr-header { background-color: #1b4332 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- Bar Navigasi Admin -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow-sm no-print">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
            <img src="../assets/images/logo-risda.png" alt="Logo RISDA" style="height: 40px; width: auto; margin-right: 0.75rem;">
            Pentadbir RISDA
        </a>
        <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Butiran Kebun
        </a>
    </div>
</nav>

<div class="container pb-5">

    <!-- Modul Ujian IP & Alat Cetak (Bukan Cetak) -->
    <div class="card border-0 shadow-sm mb-4 no-print mx-auto" style="max-width: 750px; border-radius: 12px;">
        <div class="card-body p-3 bg-white rounded-3">
            <div class="row g-3 align-items-center">
                <!-- Pilihan Penyesuaian Host/IP -->
                <div class="col-md-7">
                    <form method="GET" action="kebun-qr.php" class="d-flex gap-2">
                        <input type="hidden" name="id" value="<?= $kebun['id'] ?>">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-wifi me-1"></i> Host / IP Local</span>
                            <input type="text" name="host" class="form-control" value="<?= htmlspecialchars($current_host) ?>" placeholder="Contoh: 192.168.1.15">
                            <button type="submit" class="btn btn-primary btn-sm">Set IP</button>
                        </div>
                    </form>
                    <small class="text-muted d-block mt-1" style="font-size: 0.725rem;">
                        <i class="bi bi-info-circle me-1"></i> Tukar ke IP PC anda (cth: <code>192.168.x.x</code>) untuk imbasan telefon di Wi-Fi sama.
                    </small>
                </div>

                <!-- Butang Tindakan Cetak / Muat Turun -->
                <div class="col-md-5 d-flex justify-content-md-end gap-2">
                    <button onclick="window.print()" class="btn btn-success btn-sm px-3 d-flex align-items-center" style="background-color: var(--accent-color);">
                        <i class="bi bi-printer-fill me-1"></i> Cetak Plat
                    </button>
                    <a href="<?= $qr_api_url ?>" download="QR_Lot_<?= htmlspecialchars($kebun['no_lot']) ?>.png" target="_blank" class="btn btn-outline-secondary btn-sm px-3 d-flex align-items-center">
                        <i class="bi bi-download me-1"></i> Muat Turun
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Antaramuka Plat Kod QR Rasmi -->
    <div class="qr-plate text-center">
        <!-- Header Plat -->
        <div class="qr-header">
            <img src="../assets/images/logo-risda.png" alt="Logo RISDA" style="height: 40px; width: auto; margin-right: 0.75rem;">
            <h5 class="fw-bold tracking-wide mb-0 text-uppercase">E-KEBUN GETAH
            </h5>
            <small class="opacity-75 font-monospace" style="font-size: 0.75rem;">SISTEM MAKLUMAT KEBUN TANAM SEMULA (RISDA)</small>
        </div>

        <div class="p-4">
            <!-- Bingkai Kod QR -->
            <div class="qr-frame mb-3">
                <img src="<?= $qr_api_url ?>" alt="Kod QR Kebun Lot <?= htmlspecialchars($kebun['no_lot']) ?>" class="qr-img bg-white">
            </div>

            <p class="text-muted small mb-3">Imbas Kod QR ini untuk menyemak maklumat rasmi kebun & panduan penyelenggaraan.</p>

            <!-- Kotak Ringkasan Maklumat Kebun -->
            <div class="info-box p-3 text-start mb-3">
                <div class="row g-2">
                    <div class="col-6">
                        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">NO. LOT KEBUN</span>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($kebun['no_lot']) ?></strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">KELUASAN</span>
                        <strong class="text-dark fs-6"><?= htmlspecialchars($kebun['keluasan_kebun']) ?> Hektar</strong>
                    </div>
                    <div class="col-12 border-top pt-1 mt-1">
                        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">NAMA PEKEBUN</span>
                        <strong class="text-dark"><?= htmlspecialchars($kebun['nama_pekebun']) ?></strong>
                    </div>
                    <div class="col-12 border-top pt-1 mt-1">
                        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">LOKASI / MUKIM / DAERAH</span>
                        <span class="text-dark fw-semibold"><?= htmlspecialchars($kebun['lokasi_kebun']) ?>, <?= htmlspecialchars($kebun['mukim']) ?>, <?= htmlspecialchars($kebun['daerah']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer Plat -->
            <div class="border-top pt-2">
                <span class="text-muted font-monospace d-block" style="font-size: 0.7rem;">
                    HASH TOKEN: <?= htmlspecialchars($kebun['qr_code_hash']) ?>
                </span>
                <span class="text-muted d-block" style="font-size: 0.65rem;">
                    HAK CIPTA TERPELIHARA &copy; RISDA MALAYSIA
                </span>
            </div>
        </div>
    </div>

</div>

</body>
</html>