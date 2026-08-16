<?php
require_once 'config/database.php';

$hash = $_GET['hash'] ?? '';
$kebun = null;

if (!empty($hash)) {
    $db = getDBConnection();
    
    // Hanya ambil maklumat Kebun, Pekebun, dan Tanam Semula
    $stmt = $db->prepare("
        SELECT 
            p.nama AS nama_pekebun, p.no_telefon, p.alamat,
            k.no_lot, k.lokasi_kebun, k.mukim, k.daerah, k.keluasan_kebun, k.tahun_tanam, k.tahun_sulaman, k.klon_getah, k.jarak_tanaman, k.jumlah_pokok,
            ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
        FROM kebun k
        JOIN pekebun p ON k.pekebun_id = p.id
        LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
        WHERE k.qr_code_hash = ?
    ");
    $stmt->execute([$hash]);
    $kebun = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maklumat Kebun - RISDA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1b4332;
            --accent-color: #2d6a4f;
            --bg-light: #f8f9fa;
        }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-banner { background-color: var(--primary-color); color: #ffffff; padding: 1.25rem 1rem; border-bottom: 4px solid #40916c; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 1rem; }
        .nav-pills .nav-link.active { background-color: var(--accent-color); }
        .nav-pills .nav-link { color: var(--primary-color); font-weight: 600; border: 1px solid #d8f3dc; font-size: 0.85rem; }
        .static-img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>

<div class="header-banner text-center mb-3">
    <h6 class="text-uppercase tracking-wider mb-1 opacity-75 small">Sistem Maklumat Kebun</h6>
    <h5 class="fw-bold mb-0">RISDA Tanam Semula</h5>
</div>

<div class="container pb-5" style="max-width: 600px;">
    <?php if (!$kebun): ?>
        <div class="card card-custom p-4 text-center">
            <i class="bi bi-exclamation-triangle text-warning display-4 mb-2"></i>
            <h5 class="fw-bold">Maklumat Tidak Dijumpai</h5>
            <p class="text-muted small mb-0">Sila pastikan imbasan Kod QR adalah betul.</p>
        </div>
    <?php else: ?>

        <!-- Maklumat Kebun & Pekebun -->
        <div class="card card-custom p-3">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-geo-alt-fill text-success fs-5 me-2"></i>
                <h6 class="fw-bold mb-0 text-dark">Maklumat Kebun & Pekebun</h6>
            </div>
            <hr class="my-2">
            <div class="row g-2 small">
                <div class="col-6"><span class="text-muted">Nama Pekebun:</span><br><strong><?= htmlspecialchars($kebun['nama_pekebun']) ?></strong></div>[cite: 1]
                <div class="col-6"><span class="text-muted">No. Telefon:</span><br><strong><?= htmlspecialchars($kebun['no_telefon']) ?></strong></div>[cite: 1]
                <div class="col-12"><span class="text-muted">Alamat:</span><br><strong><?= htmlspecialchars($kebun['alamat']) ?></strong></div>[cite: 1]
                <div class="col-6"><span class="text-muted">No. Lot:</span><br><strong><?= htmlspecialchars($kebun['no_lot']) ?></strong></div>[cite: 1]
                <div class="col-6"><span class="text-muted">Keluasan:</span><br><strong><?= htmlspecialchars($kebun['keluasan_kebun']) ?> Hektar</strong></div>[cite: 1]
                <div class="col-6"><span class="text-muted">Lokasi / Mukim:</span><br><strong><?= htmlspecialchars($kebun['lokasi_kebun']) ?>, <?= htmlspecialchars($kebun['mukim']) ?></strong></div>[cite: 1]
                <div class="col-6"><span class="text-muted">Daerah:</span><br><strong><?= htmlspecialchars($kebun['daerah']) ?></strong></div>[cite: 1]
                <div class="col-6"><span class="text-muted">Klon Getah:</span><br><strong><?= htmlspecialchars($kebun['klon_getah']) ?></strong></div>[cite: 1]
            </div>
        </div>

        <!-- Panduan Pengurusan Kebun (Statik) -->
        <div class="card card-custom p-3">
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-journal-bookmark-fill text-success me-2"></i>Panduan Pengurusan Kebun</h6>[cite: 1]
            
            <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto py-1 gap-1" id="panduanTab" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-tumbesaran">1. Tumbesaran</button></li>[cite: 1]
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cantasan">2. Cantasan Dahan</button></li>[cite: 1]
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pembajaan">3. Pembajaan</button></li>[cite: 1]
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rumpai">4. Kawalan Rumpai</button></li>[cite: 1]
            </ul>

            <div class="tab-content" id="panduanTabContent">
                
                <!-- 1. Panduan Tumbesaran Pokok -->
                <div class="tab-pane fade show active" id="tab-tumbesaran">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Pengukuran Lilitan Batang</h6>
                        <p class="small text-muted mb-2">Lilitan batang sasaran sekurang-kurangnya 10-12 cm pada peringkat awal. Pastikan tiada serangan penyakit daun.</p>
                        
                        <div class="row g-2">
                            <div class="col-4">
                                <a href="assets/images/tumbesaran1.jpg" target="_blank">
                                    <img src="assets/images/tumbesaran1.jpg" class="static-img" alt="Tumbesaran 1" onerror="this.src='https://via.placeholder.com/150?text=Gambar+1';">
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="assets/images/tumbesaran2.jpg" target="_blank">
                                    <img src="assets/images/tumbesaran2.jpg" class="static-img" alt="Tumbesaran 2" onerror="this.src='https://via.placeholder.com/150?text=Gambar+2';">
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="assets/images/tumbesaran3.jpg" target="_blank">
                                    <img src="assets/images/tumbesaran3.jpg" class="static-img" alt="Tumbesaran 3" onerror="this.src='https://via.placeholder.com/150?text=Gambar+3';">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Panduan Pembajaan Cantasan Dahan -->
                <div class="tab-pane fade" id="tab-cantasan">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Cantasan Dahan & Tunas Air</h6>
                        <p class="small text-muted mb-2">Lakukan cantasan dahan di bawah ketinggian 1.5 meter. Buang tunas air secara berkala untuk galakkan pembentukan tajuk yang seimbang.</p>
                        
                        <div class="row g-2">
                            <div class="col-4">
                                <a href="assets/images/cantasan1.jpg" target="_blank">
                                    <img src="assets/images/cantasan1.jpg" class="static-img" alt="Cantasan 1" onerror="this.src='https://via.placeholder.com/150?text=Gambar+1';">
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="assets/images/cantasan2.jpg" target="_blank">
                                    <img src="assets/images/cantasan2.jpg" class="static-img" alt="Cantasan 2" onerror="this.src='https://via.placeholder.com/150?text=Gambar+2';">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Panduan Pembajaan -->
                <div class="tab-pane fade" id="tab-pembajaan">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Penggunaan & Sukatan Baja RISDA</h6>
                        <p class="small text-muted mb-2">Gunakan Baja Sebatian RISDA mengikut sukatan 250g hingga 500g bagi setiap pokok. Sapu baja secara bulatan mengelilingi kanopi pokok.</p>
                        
                        <div class="row g-2">
                            <div class="col-4">
                                <a href="assets/images/pembajaan1.jpg" target="_blank">
                                    <img src="assets/images/pembajaan1.jpg" class="static-img" alt="Pembajaan 1" onerror="this.src='https://via.placeholder.com/150?text=Gambar+1';">
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="assets/images/pembajaan2.jpg" target="_blank">
                                    <img src="assets/images/pembajaan2.jpg" class="static-img" alt="Pembajaan 2" onerror="this.src='https://via.placeholder.com/150?text=Gambar+2';">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Panduan Kawalan Rumpai -->
                <div class="tab-pane fade" id="tab-rumpai">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Penyelenggaraan Lorong & Rumpai</h6>
                        <p class="small text-muted mb-2">Lakukan kawalan rumpai dan lalang secara kimia atau manual setiap 2 bulan di kawasan lorong pokok bagi mengelakkan persaingan nutrisi.</p>
                        
                        <div class="row g-2">
                            <div class="col-4">
                                <a href="assets/images/rumpai1.jpg" target="_blank">
                                    <img src="assets/images/rumpai1.jpg" class="static-img" alt="Rumpai 1" onerror="this.src='https://via.placeholder.com/150?text=Gambar+1';">
                                </a>
                            </div>
                            <div class="col-4">
                                <a href="assets/images/rumpai2.jpg" target="_blank">
                                    <img src="assets/images/rumpai2.jpg" class="static-img" alt="Rumpai 2" onerror="this.src='https://via.placeholder.com/150?text=Gambar+2';">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
