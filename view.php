<?php
require_once 'config/database.php';

$hash = $_GET['hash'] ?? '';
$kebun = null;

if (!empty($hash)) {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT 
            k.*,
            p.nama AS nama_pekebun, p.no_telefon, p.alamat,
            ts.id AS tanam_semula_id, ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
        FROM kebun k
        JOIN pekebun p ON k.pekebun_id = p.id
        LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
        WHERE k.qr_code_hash = ?
    ");
    $stmt->execute([$hash]);
    $kebun = $stmt->fetch();

    $bantuan_lain_list = [];
    if ($kebun) {
        $stmt_bantuan = $db->prepare("
            SELECT * FROM bantuan_lain 
            WHERE kebun_id = ? OR (kebun_id IS NULL AND pekebun_id = ?)
            ORDER BY tahun_bantuan DESC, id DESC
        ");
        $stmt_bantuan->execute([$kebun['id'], $kebun['pekebun_id']]);
        $bantuan_lain_list = $stmt_bantuan->fetchAll();
    }
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
            --bg-light: #f4f6f8;
            --card-border: #e9ecef;
        }
        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header-banner {
            background-color: var(--primary-color);
            color: #ffffff;
            padding: 1.25rem 1rem;
            border-bottom: 4px solid #40916c;
        }
        .card-custom {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            background: #ffffff;
            padding: 1.25rem;
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
            margin-bottom: 1rem;
        }
        .data-label {
            font-size: 0.675rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6c757d;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .data-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2d3748;
            word-break: break-word;
        }
        .info-box {
            background: #f8f9fa;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 0.85rem;
        }
        .badge-custom {
            display: inline-block;
            padding: 0.35em 0.65em;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .nav-pills .nav-link.active { background-color: var(--accent-color); }
        .nav-pills .nav-link { color: var(--primary-color); font-weight: 600; border: 1px solid #d8f3dc; font-size: 0.85rem; }
        .static-img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>

<div class="header-banner text-center mb-3">
    <img src="assets/images/logo-risda.png" alt="Logo RISDA" class="mb-2" style="height: 60px; width: auto;">
    <h6 class="text-uppercase tracking-wider mb-1 opacity-75 small">e-Kebun Getah</h6>
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

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header-custom">
                        <i class="bi bi-person-lines-fill text-success fs-5"></i>
                        <span>Maklumat Pekebun</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="data-label">Nama Pekebun</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['nama_pekebun'] ?? '-') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">No. Telefon</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['no_telefon'] ?? '-') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="data-label">Alamat</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['alamat'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-custom">
                    <div class="card-header-custom">
                        <i class="bi bi-geo-alt-fill text-success fs-5"></i>
                        <span>Maklumat Kebun</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="data-label">No. Lot</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['no_lot'] ?? '-') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">Keluasan Kebun</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['keluasan_kebun'] ?? '-') ?> Hektar</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">Lokasi / Mukim</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['lokasi_kebun'] ?? '-') ?>, <?= htmlspecialchars($kebun['mukim'] ?? '-') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">Daerah</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['daerah'] ?? '-') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">Klon Getah</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['klon_getah'] ?? '-') ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">Jumlah Pokok</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['jumlah_pokok'] ?? '-') ?> Pokok</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">Tahun Tanam / Sulaman</div>
                            <div class="data-value">
                                <?= !empty($kebun['tahun_tanam']) ? (strlen($kebun['tahun_tanam']) >= 4 ? substr($kebun['tahun_tanam'], 0, 4) : htmlspecialchars($kebun['tahun_tanam'])) : '-' ?> / 
                                <?= !empty($kebun['tahun_sulaman']) ? (strlen($kebun['tahun_sulaman']) >= 4 ? substr($kebun['tahun_sulaman'], 0, 4) : htmlspecialchars($kebun['tahun_sulaman'])) : '-' ?>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="data-label">Jarak Tanaman</div>
                            <div class="data-value"><?= htmlspecialchars($kebun['jarak_tanaman'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-custom mb-3">
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
                        <span class="badge-custom bg-success text-white"><?= htmlspecialchars($kebun['bantuan_ansuran'] ?? '-') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Maklumat Bantuan Lain -->
        <div class="card card-custom mb-3">
            <div class="card-header-custom">
                <i class="bi bi-gift-fill text-success fs-5"></i>
                <span>Maklumat Bantuan Lain</span>
            </div>
            <?php if (!empty($bantuan_lain_list)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($bantuan_lain_list as $index => $bl): ?>
                        <div class="<?= $index > 0 ? 'pt-3 border-top' : '' ?>">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="data-label">Nama Bantuan</div>
                                    <div class="data-value text-dark"><?= htmlspecialchars($bl['nama_bantuan']) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Jenis Tanaman</div>
                                    <div class="data-value">
                                        <span class="badge-custom bg-light text-dark border">
                                            <?= htmlspecialchars($bl['jenis_tanaman'] ?: '-') ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Tahun Bantuan</div>
                                    <div class="data-value"><?= htmlspecialchars($bl['tahun_bantuan'] ?: '-') ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="data-label">Nilai Bantuan</div>
                                    <div class="data-value text-success fw-bold">
                                        <?= $bl['nilai_bantuan'] !== null ? 'RM ' . number_format((float)$bl['nilai_bantuan'], 2) : '-' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="info-box text-muted small">
                    <i class="bi bi-info-circle me-2"></i>
                    Tiada maklumat bantuan lain didaftarkan untuk kebun ini.
                </div>
            <?php endif; ?>
        </div>

        <!-- Pelan Lot Image -->
        <div class="card card-custom mb-3">
            <div class="card-header-custom">
                <i class="bi bi-image text-success fs-5"></i>
                <span>Pelan Lot</span>
            </div>
            <?php 
                $has_pelan = !empty($kebun['pelan_lot']);
                $is_pdf = ($has_pelan && substr($kebun['pelan_lot'], 0, 4) === '%PDF');
                $cache_v = $has_pelan ? substr(md5($kebun['pelan_lot']), 0, 8) : time();
            ?>
            <?php if ($has_pelan): ?>
                <?php if ($is_pdf): ?>
                    <div class="p-3 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-1"></i>
                            <div>
                                <div class="fw-semibold">Dokumen PDF Pelan Lot</div>
                                <div class="small text-muted">Ketik butang di bawah untuk membuka fail PDF.</div>
                            </div>
                        </div>
                        <a href="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3">
                            <i class="bi bi-file-earmark-pdf me-1"></i> Buka PDF
                        </a>
                    </div>
                <?php else: ?>
                    <a href="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" target="_blank" class="d-block text-center">
                        <img src="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" class="img-fluid rounded border" alt="Pelan Lot" style="width: 100%; max-height: 400px; object-fit: contain;" onerror="this.src='assets/images/logo-risda.png'; this.style.opacity=0.3;">
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="info-box text-muted mt-1 small">
                    <i class="bi bi-info-circle me-2"></i>
                    Tiada gambar pelan lot dimuat naik untuk kebun ini.
                </div>
            <?php endif; ?>
        </div>

        <!-- Panduan Pengurusan Kebun (Statik) -->
        <div class="card card-custom p-3">
            <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-journal-bookmark-fill text-success me-2"></i>Panduan Pengurusan Kebun</h6>
            
            <ul class="nav nav-pills mb-3 flex-wrap py-1 gap-1" id="panduanTab" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-menanam">Menanam</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pembajaan-muda">Pembajaan Muda</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-galakan">Galakan Dahan</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cantasan-terkawal">Cantasan Terkawal</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cantasan-pembetulan">Cantasan Pembetulan</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-rumpai">Kawalan Rumpai</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-penyakit">Kawalan Penyakit</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-perosak">Kawalan Perosak</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-baja">Jenis Baja</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-nutrien">Nutrient & Fungsi</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-kadar">Kadar Pembajaan</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-racun">Jenis Racun</button></li>
            </ul>

            <div class="tab-content" id="panduanTabContent">
                
                <!-- 1. Teknik Menanam Getah -->
                <div class="tab-pane fade show active" id="tab-menanam">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Teknik Menanam Getah</h6>
                        <p class="small text-muted mb-2">Panduan lengkap untuk teknik penanaman pokok getah yang betul.</p>
                        <div class="text-center">
                            <a href="assets/images/teknik-menanam-getah.jpeg" target="_blank">
                                <img src="assets/images/teknik-menanam-getah.jpeg" class="img-fluid rounded border" alt="Teknik Menanam Getah" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 2. Pembajaan Getah Muda -->
                <div class="tab-pane fade" id="tab-pembajaan-muda">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Pembajaan Getah Muda</h6>
                        <p class="small text-muted mb-2">Teknik pembajaan yang sesuai untuk pokok getah yang masih muda.</p>
                        <div class="text-center">
                            <a href="assets/images/teknik-pembajaan-getah-muda.jpeg" target="_blank">
                                <img src="assets/images/teknik-pembajaan-getah-muda.jpeg" class="img-fluid rounded border" alt="Pembajaan Getah Muda" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 3. Galakan Dahan -->
                <div class="tab-pane fade" id="tab-galakan">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Teknik Galakan Dahan</h6>
                        <p class="small text-muted mb-2">Panduan untuk membentuk tajuk pokok getah melalui galakan dahan.</p>
                        <div class="text-center">
                            <a href="assets/images/teknik-galakan-dahan.jpeg" target="_blank">
                                <img src="assets/images/teknik-galakan-dahan.jpeg" class="img-fluid rounded border" alt="Galakan Dahan" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4. Cantasan Terkawal -->
                <div class="tab-pane fade" id="tab-cantasan-terkawal">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Cantasan Terkawal</h6>
                        <p class="small text-muted mb-2">Teknik cantasan dahan yang terkawal untuk memastikan pertumbuhan optimal.</p>
                        <div class="text-center">
                            <a href="assets/images/teknik-cantasan-terkawal.jpeg" target="_blank">
                                <img src="assets/images/teknik-cantasan-terkawal.jpeg" class="img-fluid rounded border" alt="Cantasan Terkawal" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 5. Cantasan Pembetulan -->
                <div class="tab-pane fade" id="tab-cantasan-pembetulan">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Cantasan Pembetulan</h6>
                        <p class="small text-muted mb-2">Teknik pembetulan pokok getah melalui cantasan strategis.</p>
                        <div class="text-center">
                            <a href="assets/images/teknik-cantasan-pembetulan.jpeg" target="_blank">
                                <img src="assets/images/teknik-cantasan-pembetulan.jpeg" class="img-fluid rounded border" alt="Cantasan Pembetulan" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 6. Kawalan Rumpai -->
                <div class="tab-pane fade" id="tab-rumpai">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Kawalan Rumpai</h6>
                        <p class="small text-muted mb-2">Panduan penyelenggaraan lorong dan kawalan rumpai yang efektif.</p>
                        <div class="text-center">
                            <a href="assets/images/kawalan-rumpai.jpeg" target="_blank">
                                <img src="assets/images/kawalan-rumpai.jpeg" class="img-fluid rounded border" alt="Kawalan Rumpai" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 7. Kawalan Penyakit -->
                <div class="tab-pane fade" id="tab-penyakit">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Kawalan Penyakit</h6>
                        <p class="small text-muted mb-2">Panduan pengenalan dan kawalan penyakit pada pokok getah.</p>
                        <div class="text-center">
                            <a href="assets/images/kawalan-penyakit.jpeg" target="_blank">
                                <img src="assets/images/kawalan-penyakit.jpeg" class="img-fluid rounded border" alt="Kawalan Penyakit" style="max-height: 400px;">
                            </a>
                        </div>
                        <div class="text-center mt-3">
                            <h6 class="fw-bold text-success mb-1 mt-3">Jenis-Jenis Penyakit</h6>
                            <a href="assets/images/jenis-penyakit.jpeg" target="_blank">
                                <img src="assets/images/jenis-penyakit.jpeg" class="img-fluid rounded border" alt="Jenis Penyakit" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 8. Kawalan Makhluk Perosak -->
                <div class="tab-pane fade" id="tab-perosak">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Kawalan Makhluk Perosak</h6>
                        <p class="small text-muted mb-2">Panduan pengenalan dan kawalan makhluk perosak pada pokok getah.</p>
                        <div class="text-center">
                            <a href="assets/images/kawalan-makhluk-perosak.jpeg" target="_blank">
                                <img src="assets/images/kawalan-makhluk-perosak.jpeg" class="img-fluid rounded border" alt="Kawalan Makhluk Perosak" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 9. Jenis-Jenis Baja -->
                <div class="tab-pane fade" id="tab-baja">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Jenis-Jenis Baja</h6>
                        <p class="small text-muted mb-2">Panduan jenis-jenis baja yang digunakan untuk pembajaan pokok getah.</p>
                        <div class="text-center">
                            <a href="assets/images/jenis-baja.jpeg" target="_blank">
                                <img src="assets/images/jenis-baja.jpeg" class="img-fluid rounded border" alt="Jenis Baja" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 10. Jenis-Jenis Nutrien dan Fungsinya -->
                <div class="tab-pane fade" id="tab-nutrien">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Jenis-Jenis Nutrien dan Fungsinya</h6>
                        <p class="small text-muted mb-2">Panduan lengkap tentang nutrien yang diperlukan pokok getah dan fungsinya.</p>
                        <div class="text-center">
                            <a href="assets/images/jenis-nutrien-dan-fungsi.jpeg" target="_blank">
                                <img src="assets/images/jenis-nutrien-dan-fungsi.jpeg" class="img-fluid rounded border" alt="Jenis Nutrien dan Fungsi" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 11. Kadar Pembajaan -->
                <div class="tab-pane fade" id="tab-kadar">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Kadar Pembajaan Pokok Getah</h6>
                        <p class="small text-muted mb-2">Jadual kadar pembajaan mengikut umur dan jenis baja yang digunakan.</p>
                        <div class="text-center">
                            <a href="assets/images/kadar-pembajaan.jpeg" target="_blank">
                                <img src="assets/images/kadar-pembajaan.jpeg" class="img-fluid rounded border" alt="Kadar Pembajaan" style="max-height: 400px;">
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 12. Jenis-Jenis Racun Rumpai -->
                <div class="tab-pane fade" id="tab-racun">
                    <div class="mb-3">
                        <h6 class="fw-bold text-success mb-1">Jenis-Jenis Racun Rumpai</h6>
                        <p class="small text-muted mb-2">Panduan lengkap jenis-jenis racun rumpai dan kadar penggunaan.</p>
                        <div class="text-center">
                            <a href="assets/images/jenis-racun-rumpai.jpeg" target="_blank">
                                <img src="assets/images/jenis-racun-rumpai.jpeg" class="img-fluid rounded border" alt="Jenis Racun Rumpai" style="max-height: 400px;">
                            </a>
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
