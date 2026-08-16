<?php
require_once 'config/database.php';

$hash = $_GET['hash'] ?? '';
$kebun = null;
$ansuran_list = [];

if (!empty($hash)) {
    $db = getDBConnection();
    
    // Fetch Pekebun, Kebun, and Tanam Semula details
    $stmt = $db->prepare("
        SELECT 
            p.nama AS nama_pekebun, p.no_telefon, p.alamat,
            k.no_lot, k.lokasi_kebun, k.mukim, k.daerah, k.keluasan_kebun, k.tahun_tanam, k.tahun_sulaman, k.klon_getah, k.jarak_tanaman, k.jumlah_pokok,
            ts.id AS tanam_semula_id, ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
        FROM kebun k
        JOIN pekebun p ON k.pekebun_id = p.id
        LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
        WHERE k.qr_code_hash = ?
    ");
    $stmt->execute([$hash]);
    $kebun = $stmt->fetch();

    if ($kebun && !empty($kebun['tanam_semula_id'])) {
        // Fetch Panduan Ansuran 1 to 5
        $stmt_ansuran = $db->prepare("
            SELECT * FROM panduan_ansuran 
            WHERE tanam_semula_id = ? 
            ORDER BY no_ansuran ASC
        ");
        $stmt_ansuran->execute([$kebun['tanam_semula_id']]);
        $ansuran_list = $stmt_ansuran->fetchAll();
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
            --bg-light: #f8f9fa;
        }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-banner { background-color: var(--primary-color); color: #ffffff; padding: 1.25rem 1rem; border-bottom: 4px solid #40916c; }
        .card-custom { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 1rem; }
        .nav-pills .nav-link.active { background-color: var(--accent-color); }
        .nav-pills .nav-link { color: var(--primary-color); font-weight: 600; border: 1px solid #d8f3dc; margin-right: 4px; }
        .badge-status { background-color: #d8f3dc; color: #1b4332; font-weight: 600; }
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
            <p class="text-muted small mb-0">Sila pastikan imbasan Kod QR adalah betul atau sah.</p>
        </div>
    <?php else: ?>

        <!-- Maklumat Pekebun & Kebun -->
        <div class="card card-custom p-3">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-geo-alt-fill text-success fs-5 me-2"></i>
                <h6 class="fw-bold mb-0 text-dark">Maklumat Kebun & Pekebun</h6>
            </div>
            <hr class="my-2">
            <div class="row g-2 small">
                <div class="col-6"><span class="text-muted">Nama Pekebun:</span><br><strong><?= htmlspecialchars($kebun['nama_pekebun']) ?></strong></div>
                <div class="col-6"><span class="text-muted">No. Telefon:</span><br><strong><?= htmlspecialchars($kebun['no_telefon']) ?></strong></div>
                <div class="col-12"><span class="text-muted">Alamat:</span><br><strong><?= htmlspecialchars($kebun['alamat']) ?></strong></div>
                <div class="col-6"><span class="text-muted">No. Lot:</span><br><strong><?= htmlspecialchars($kebun['no_lot']) ?></strong></div>
                <div class="col-6"><span class="text-muted">Keluasan:</span><br><strong><?= htmlspecialchars($kebun['keluasan_kebun']) ?> Hektar</strong></div>
                <div class="col-6"><span class="text-muted">Lokasi / Mukim:</span><br><strong><?= htmlspecialchars($kebun['lokasi_kebun']) ?>, <?= htmlspecialchars($kebun['mukim']) ?></strong></div>
                <div class="col-6"><span class="text-muted">Daerah:</span><br><strong><?= htmlspecialchars($kebun['daerah']) ?></strong></div>
                <div class="col-6"><span class="text-muted">Klon Getah:</span><br><strong><?= htmlspecialchars($kebun['klon_getah']) ?></strong></div>
                <div class="col-6"><span class="text-muted">Jumlah Pokok:</span><br><strong><?= htmlspecialchars($kebun['jumlah_pokok']) ?> Pokok</strong></div>
                <div class="col-6"><span class="text-muted">Tahun Tanam / Sulaman:</span><br><strong><?= htmlspecialchars($kebun['tahun_tanam']) ?> / <?= htmlspecialchars($kebun['tahun_sulaman']) ?></strong></div>
                <div class="col-6"><span class="text-muted">Jarak Tanaman:</span><br><strong><?= htmlspecialchars($kebun['jarak_tanaman']) ?></strong></div>
            </div>
        </div>

        <!-- Maklumat Tanam Semula -->
        <div class="card card-custom p-3">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-file-earmark-text-fill text-success fs-5 me-2"></i>
                <h6 class="fw-bold mb-0 text-dark">Maklumat Tanam Semula</h6>
            </div>
            <hr class="my-2">
            <div class="row g-2 small">
                <div class="col-6"><span class="text-muted">No. Tanam Semula:</span><br><strong><?= htmlspecialchars($kebun['no_tanam_semula'] ?? '-') ?></strong></div>
                <div class="col-6"><span class="text-muted">Tahun Tanam Semula:</span><br><strong><?= htmlspecialchars($kebun['tahun_tanam_semula'] ?? '-') ?></strong></div>
                <div class="col-6"><span class="text-muted">Keluasan Diluluskan:</span><br><strong><?= htmlspecialchars($kebun['keluasan_diluluskan'] ?? '-') ?> Hektar</strong></div>
                <div class="col-6"><span class="text-muted">Bantuan Ansuran:</span><br><span class="badge badge-status"><?= htmlspecialchars($kebun['bantuan_ansuran'] ?? '-') ?></span></div>
            </div>
        </div>

        <!-- Panduan Ansuran Tabs (1 - 5) -->
        <div class="card card-custom p-3">
            <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-journal-check text-success me-2"></i>Panduan & Aktiviti Ansuran</h6>
            
            <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto py-1" id="ansuranTab" role="tablist">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $i === 1 ? 'active' : '' ?> py-1 px-3 small" id="tab-<?= $i ?>" data-bs-toggle="tab" data-bs-target="#ansuran-<?= $i ?>" type="button" role="tab">Ansuran <?= $i ?></button>
                    </li>
                <?php endfor; ?>
            </ul>

            <div class="tab-content" id="ansuranTabContent">
                <?php 
                for ($i = 1; $i <= 5; $i++): 
                    $current_ansuran = array_filter($ansuran_list, fn($item) => $item['no_ansuran'] == $i);
                    $data = reset($current_ansuran) ?: null;
                ?>
                    <div class="tab-pane fade <?= $i === 1 ? 'show active' : '' ?>" id="ansuran-<?= $i ?>" role="tabpanel">
                        <div class="accordion accordion-flush" id="accordionAnsuran<?= $i ?>">
                            
                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 px-1 small fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#tumbesaran-<?= $i ?>">
                                        Panduan Tumbesaran Pokok
                                    </button>
                                </h2>
                                <div id="tumbesaran-<?= $i ?>" class="accordion-collapse collapse show">
                                    <div class="accordion-body px-1 py-2 text-muted small">
                                        <?= nl2br(htmlspecialchars($data['tumbesaran_pokok'] ?? 'Tiada maklumat.')) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 px-1 small fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#cantasan-<?= $i ?>">
                                        Panduan Pembajaan Cantasan Dahan
                                    </button>
                                </h2>
                                <div id="cantasan-<?= $i ?>" class="accordion-collapse collapse">
                                    <div class="accordion-body px-1 py-2 text-muted small">
                                        <?= nl2br(htmlspecialchars($data['pembajaan_cantasan'] ?? 'Tiada maklumat.')) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item border-bottom">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 px-1 small fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#pembajaan-<?= $i ?>">
                                        Panduan Pembajaan
                                    </button>
                                </h2>
                                <div id="pembajaan-<?= $i ?>" class="accordion-collapse collapse">
                                    <div class="accordion-body px-1 py-2 text-muted small">
                                        <?= nl2br(htmlspecialchars($data['panduan_pembajaan'] ?? 'Tiada maklumat.')) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 px-1 small fw-semibold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#rumpai-<?= $i ?>">
                                        Panduan Kawalan Rumpai
                                    </button>
                                </h2>
                                <div id="rumpai-<?= $i ?>" class="accordion-collapse collapse">
                                    <div class="accordion-body px-1 py-2 text-muted small">
                                        <?= nl2br(htmlspecialchars($data['kawalan_rumpai'] ?? 'Tiada maklumat.')) ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>