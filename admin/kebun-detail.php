<?php
require_once 'auth.php';
requireAdminLogin();
require_once '../config/database.php';

$kebun_id = $_GET['id'] ?? 0;
$db = getDBConnection();

// Fetch Kebun, Pekebun, and Tanam Semula details
$stmt = $db->prepare("
    SELECT 
        k.*,
        p.nama AS nama_pekebun, p.no_telefon, p.alamat,
        ts.id AS tanam_semula_id, ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
    FROM kebun k
    JOIN pekebun p ON k.pekebun_id = p.id
    LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
    WHERE k.id = ?
");
$stmt->execute([$kebun_id]);
$kebun = $stmt->fetch();

if (!$kebun) {
    header("Location: dashboard.php");
    exit();
}

$pelan_lot = trim((string)($kebun['pelan_lot'] ?? ''));
$has_pelan_lot = !empty($pelan_lot);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Butiran Kebun - <?= htmlspecialchars($kebun['no_lot']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1b4332;
            --accent-color: #2d6a4f;
            --bg-light: #f4f6f8;
            --card-border: #e9ecef;
        }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
        }
        .navbar-custom { 
            background-color: var(--primary-color); 
        }
        
        /* Card Styling & Consistency */
        .card-custom {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            background: #ffffff;
            padding: 1.25rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-header-custom {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a202c;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 0.75rem;
            margin-bottom: 1.25rem;
        }
        
        /* Typography Consistency */
        .data-label {
            font-size: 0.725rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6c757d;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .data-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3748;
            word-break: break-word;
        }
        
        /* Elements & Utilities */
        .badge-custom {
            display: inline-block;
            padding: 0.35em 0.65em;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .info-box {
            background: #f8f9fa;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 0.85rem;
        }
        .pelan-img {
            width: 100%;
            max-height: 240px;
            object-fit: contain;
            background: #fafafa;
            border: 1px solid var(--card-border);
            border-radius: 8px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
            <img src="../assets/images/logo-risda.png" alt="Logo RISDA" style="height: 40px; width: auto; margin-right: 0.75rem;">
            Pentadbir RISDA
        </a>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
    </div>
</nav>

<div class="container px-4 mb-5" style="max-width: 1100px;">

    <!-- Action Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Butiran Kebun: <?= htmlspecialchars($kebun['no_lot']) ?></h3>
            <p class="text-muted small mb-0">Diselenggara oleh: <strong><?= htmlspecialchars($kebun['nama_pekebun']) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="kebun-edit.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> Kemaskini Rekod
            </a>
            <a href="kebun-qr.php?id=<?= $kebun['id'] ?>" class="btn btn-success" style="background-color: var(--accent-color);">
                <i class="bi bi-qr-code me-1"></i> Cetak / Lihat Kod QR
            </a>
            <a href="kebun-delete.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-danger">
                <i class="bi bi-trash me-1"></i> Padam Rekod
            </a>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="row g-4">
        
        <!-- Kolum Kiri: Pekebun & Tanam Semula -->
        <div class="col-lg-6 d-flex flex-column gap-4">
            
            <!-- Card 1: Maklumat Pekebun -->
            <div class="card card-custom">
                <div class="card-header-custom">
                    <i class="bi bi-person-lines-fill text-success fs-5"></i>
                    <span>Maklumat Pekebun</span>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="data-label">Nama Pekebun</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['nama_pekebun']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">No. Telefon</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['no_telefon']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="data-label">Alamat</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['alamat']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Maklumat Tanam Semula -->
            <div class="card card-custom">
                <div class="card-header-custom">
                    <i class="bi bi-file-text-fill text-success fs-5"></i>
                    <span>Maklumat Tanam Semula</span>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="data-label">No. Tanam Semula</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['no_tanam_semula'] ?? '-') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Tahun Tanam Semula</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['tahun_tanam_semula'] ?? '-') ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Keluasan Diluluskan</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['keluasan_diluluskan'] ?? '-') ?> Hektar</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Status Bantuan Ansuran</div>
                        <div class="data-value">
                            <span class="badge-custom bg-success text-white">
                                <?= htmlspecialchars($kebun['bantuan_ansuran'] ?? '-') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Kolum Kanan: Maklumat Kebun -->
        <div class="col-lg-6">
            <div class="card card-custom">
                <div class="card-header-custom">
                    <i class="bi bi-geo-alt-fill text-success fs-5"></i>
                    <span>Maklumat Kebun</span>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="data-label">No. Lot</div>
                        <div class="data-value">
                            <span class="badge-custom bg-light text-dark border"><?= htmlspecialchars($kebun['no_lot']) ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Keluasan Kebun</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['keluasan_kebun']) ?> Hektar</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Lokasi / Mukim</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['lokasi_kebun']) ?>, <?= htmlspecialchars($kebun['mukim']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Daerah</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['daerah']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Klon Getah</div>
                        <div class="data-value">
                            <span class="badge-custom bg-success-subtle text-success border border-success-subtle">
                                <?= htmlspecialchars($kebun['klon_getah']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Jumlah Pokok</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['jumlah_pokok']) ?> Pokok</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Tahun Tanam / Sulaman</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['tahun_tanam']) ?> / <?= htmlspecialchars($kebun['tahun_sulaman']) ?></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Jarak Tanaman</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['jarak_tanaman']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="data-label">Koordinat</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['koordinat'] ?? '-') ?></div>
                    </div>
                    <div class="col-12">
                        <div class="data-label">Pelan Lot</div>
                        <?php if ($has_pelan_lot): ?>
                            <?php 
                                $is_pdf = (substr($kebun['pelan_lot'], 0, 4) === '%PDF');
                                $cache_v = substr(md5($kebun['pelan_lot']), 0, 8);
                            ?>
                            <?php if ($is_pdf): ?>
                                <div class="p-3 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-2 mt-1">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-1"></i>
                                        <div>
                                            <div class="fw-semibold">Dokumen PDF Pelan Lot</div>
                                            <div class="small text-muted">Pelan lot dimuat naik dalam format PDF.</div>
                                        </div>
                                    </div>
                                    <a href="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Buka / Muat Turun PDF
                                    </a>
                                </div>
                            <?php else: ?>
                                <a href="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" target="_blank" class="d-block mt-1">
                                    <img src="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" class="pelan-img" alt="Pelan Lot" onerror="this.src='../assets/images/logo-risda.png'; this.style.opacity=0.3;">
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="info-box text-muted mt-1 small">Tiada gambar pelan lot dimuat naik.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>