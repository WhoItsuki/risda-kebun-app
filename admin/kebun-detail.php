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
            --bg-light: #f8f9fa;
        }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-custom { background-color: var(--primary-color); }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
        .nav-pills .nav-link.active { background-color: var(--accent-color); }
        .nav-pills .nav-link { color: var(--primary-color); font-weight: 600; border: 1px solid #d8f3dc; }
        .data-label { font-size: 0.825rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; font-weight: 600; }
        .data-value { font-size: 1rem; font-weight: 600; color: #212529; }
        .static-img-admin { width: 100%; height: 160px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
            <i class="bi bi-tree-fill text-warning me-2 fs-4"></i> Pentadbir RISDA
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
            <h3 class="fw-bold mb-1 text-dark">Butiran Kebun: <?= htmlspecialchars($kebun['no_lot']) ?></h3>[cite: 1]
            <p class="text-muted small mb-0">Diselenggara oleh: <strong><?= htmlspecialchars($kebun['nama_pekebun']) ?></strong></p>[cite: 1]
        </div>
        <div class="d-flex gap-2">
            <a href="kebun-edit.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> Kemaskini Rekod
            </a>
            <a href="kebun-qr.php?id=<?= $kebun['id'] ?>" class="btn btn-success" style="background-color: var(--accent-color);">
                <i class="bi bi-qr-code me-1"></i> Cetak / Lihat Kod QR
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Maklumat Pekebun & Kebun -->
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                    <i class="bi bi-person-lines-fill text-success me-2"></i>Maklumat Pekebun & Kebun[cite: 1]
                </h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="data-label">Nama Pekebun[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['nama_pekebun']) ?></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">No. Telefon[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['no_telefon']) ?></div>[cite: 1]
                    </div>
                    <div class="col-12">
                        <div class="data-label">Alamat[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['alamat']) ?></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">No. Lot[cite: 1]</div>
                        <div class="data-value"><span class="badge bg-light text-dark border"><?= htmlspecialchars($kebun['no_lot']) ?></span></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Keluasan Kebun[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['keluasan_kebun']) ?> Hektar</div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Lokasi / Mukim[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['lokasi_kebun']) ?>, <?= htmlspecialchars($kebun['mukim']) ?></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Daerah[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['daerah']) ?></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Klon Getah[cite: 1]</div>
                        <div class="data-value"><span class="badge bg-success-subtle text-success border border-success-subtle"><?= htmlspecialchars($kebun['klon_getah']) ?></span></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Jumlah Pokok[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['jumlah_pokok']) ?> Pokok</div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Tahun Tanam / Sulaman[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['tahun_tanam']) ?> / <?= htmlspecialchars($kebun['tahun_sulaman']) ?></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Jarak Tanaman[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['jarak_tanaman']) ?></div>[cite: 1]
                    </div>
                </div>
            </div>
        </div>

        <!-- Maklumat Tanam Semula -->
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                    <i class="bi bi-file-text-fill text-success me-2"></i>Maklumat Tanam Semula[cite: 1]
                </h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="data-label">No. Tanam Semula[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['no_tanam_semula'] ?? '-') ?></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Tahun Tanam Semula[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['tahun_tanam_semula'] ?? '-') ?></div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Keluasan Diluluskan[cite: 1]</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['keluasan_diluluskan'] ?? '-') ?> Hektar</div>[cite: 1]
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Status Bantuan Ansuran[cite: 1]</div>
                        <div class="data-value"><span class="badge bg-success text-white"><?= htmlspecialchars($kebun['bantuan_ansuran'] ?? '-') ?></span></div>[cite: 1]
                    </div>
                    <div class="col-12 mt-4">
                        <div class="p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-qr-code-scan me-1 text-success"></i> Token QR Hash</h6>
                            <p class="small text-muted font-monospace mb-0"><?= htmlspecialchars($kebun['qr_code_hash']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panduan Pengurusan Kebun (Statik) -->
        <div class="col-12">
            <div class="card card-custom p-4">
                <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">
                    <i class="bi bi-journal-bookmark-fill text-success me-2"></i>Panduan Pengurusan Kebun (Standard RISDA)
                </h5>

                <ul class="nav nav-pills mb-4 gap-2" id="panduanAdminTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active px-3" data-bs-toggle="tab" data-bs-target="#admin-tab-tumbesaran" type="button" role="tab">
                            1. Tumbesaran Pokok
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-3" data-bs-toggle="tab" data-bs-target="#admin-tab-cantasan" type="button" role="tab">
                            2. Pembajaan Cantasan Dahan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-3" data-bs-toggle="tab" data-bs-target="#admin-tab-pembajaan" type="button" role="tab">
                            3. Pembajaan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-3" data-bs-toggle="tab" data-bs-target="#admin-tab-rumpai" type="button" role="tab">
                            4. Kawalan Rumpai
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="panduanAdminTabContent">
                    
                    <!-- 1. Tumbesaran Pokok -->
                    <div class="tab-pane fade show active" id="admin-tab-tumbesaran" role="tabpanel">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <h6 class="fw-bold text-success mb-1">Pengukuran Lilitan Batang & Kesihatan Pokok</h6>
                            <p class="text-dark small mb-3">Lilitan batang sasaran sekurang-kurangnya 10-12 cm pada peringkat awal. Pastikan tiada serangan penyakit daun dan sulaman dilakukan tepat pada waktunya.</p>
                            
                            <div class="row g-3">
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/tumbesaran1.jpg" target="_blank">
                                        <img src="../assets/images/tumbesaran1.jpg" class="static-img-admin" alt="Tumbesaran 1" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+1';">
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/tumbesaran2.jpg" target="_blank">
                                        <img src="../assets/images/tumbesaran2.jpg" class="static-img-admin" alt="Tumbesaran 2" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+2';">
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/tumbesaran3.jpg" target="_blank">
                                        <img src="../assets/images/tumbesaran3.jpg" class="static-img-admin" alt="Tumbesaran 3" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+3';">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Cantasan Dahan -->
                    <div class="tab-pane fade" id="admin-tab-cantasan" role="tabpanel">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <h6 class="fw-bold text-success mb-1">Cantasan Dahan & Penyelenggaraan Tunas Air</h6>
                            <p class="text-dark small mb-3">Lakukan cantasan dahan di bawah ketinggian 1.5 meter. Buang tunas air secara berkala untuk membolehkan batang utama membesar secara optimum dan seimbang.</p>
                            
                            <div class="row g-3">
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/cantasan1.jpg" target="_blank">
                                        <img src="../assets/images/cantasan1.jpg" class="static-img-admin" alt="Cantasan 1" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+1';">
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/cantasan2.jpg" target="_blank">
                                        <img src="../assets/images/cantasan2.jpg" class="static-img-admin" alt="Cantasan 2" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+2';">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Pembajaan -->
                    <div class="tab-pane fade" id="admin-tab-pembajaan" role="tabpanel">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <h6 class="fw-bold text-success mb-1">Jadual Penggunaan & Sukatan Baja RISDA</h6>
                            <p class="text-dark small mb-3">Gunakan Baja Sebatian RISDA mengikut sukatan 250g hingga 500g bagi setiap pokok. Taburkan baja secara bulatan mengelilingi bawah kanopi pokok.</p>
                            
                            <div class="row g-3">
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/pembajaan1.jpg" target="_blank">
                                        <img src="../assets/images/pembajaan1.jpg" class="static-img-admin" alt="Pembajaan 1" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+1';">
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/pembajaan2.jpg" target="_blank">
                                        <img src="../assets/images/pembajaan2.jpg" class="static-img-admin" alt="Pembajaan 2" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+2';">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Kawalan Rumpai -->
                    <div class="tab-pane fade" id="admin-tab-rumpai" role="tabpanel">
                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <h6 class="fw-bold text-success mb-1">Penyelenggaraan Lorong Kebun & Kawalan Rumpai</h6>
                            <p class="text-dark small mb-3">Lakukan kawalan rumpai dan lalang secara kimia atau manual setiap 2 bulan di kawasan lorong pokok bagi mengelakkan persaingan pemakanan pokok getah.</p>
                            
                            <div class="row g-3">
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/rumpai1.jpg" target="_blank">
                                        <img src="../assets/images/rumpai1.jpg" class="static-img-admin" alt="Rumpai 1" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+1';">
                                    </a>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <a href="../assets/images/rumpai2.jpg" target="_blank">
                                        <img src="../assets/images/rumpai2.jpg" class="static-img-admin" alt="Rumpai 2" onerror="this.src='https://via.placeholder.com/300x200?text=Gambar+2';">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>