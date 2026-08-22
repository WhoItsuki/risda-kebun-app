<?php
require_once 'auth.php';
requireAdminLogin();
require_once '../config/database.php';

$db = getDBConnection();
$error = '';
$success = '';

// Get kebun ID from GET or POST
$kebun_id = (int)($_GET['id'] ?? $_POST['kebun_id'] ?? 0);

if ($kebun_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Fetch existing Kebun, Pekebun, and Tanam Semula details
$stmt = $db->prepare("
    SELECT 
        k.*,
        p.id AS pekebun_id, p.nama AS nama_pekebun, p.no_telefon, p.alamat,
        ts.id AS tanam_semula_id, ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
    FROM kebun k
    JOIN pekebun p ON k.pekebun_id = p.id
    LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
    WHERE k.id = ?
");
$stmt->execute([$kebun_id]);
$kebun = $stmt->fetch();

if (!$kebun) {
    header('Location: dashboard.php');
    exit();
}

// Fetch existing Bantuan Lain details
$stmt_bantuan = $db->prepare("SELECT * FROM bantuan_lain WHERE kebun_id = ? OR (kebun_id IS NULL AND pekebun_id = ?) ORDER BY id DESC LIMIT 1");
$stmt_bantuan->execute([$kebun_id, $kebun['pekebun_id']]);
$bantuan_lain = $stmt_bantuan->fetch();

// Check if current kebun has a pelan lot image/file
$pelan_lot_blob = $kebun['pelan_lot'] ?? null;
$has_pelan_lot = !empty($pelan_lot_blob);

// Get current klon getah as array
$current_klon_array = [];
if (!empty($kebun['klon_getah'])) {
    $current_klon_array = array_map('trim', explode(',', $kebun['klon_getah']));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $pekebun_id = $kebun['pekebun_id'];

        // Update current pekebun's details
        $nama_pekebun = trim($_POST['nama_pekebun_current'] ?? '');
        $no_telefon = trim($_POST['no_telefon_current'] ?? '');
        $alamat = trim($_POST['alamat_current'] ?? '');

        if (empty($nama_pekebun) || empty($no_telefon) || empty($alamat)) {
            throw new Exception('Sila isi semua maklumat pekebun.');
        }

        $stmt = $db->prepare("UPDATE pekebun SET nama = ?, no_telefon = ?, alamat = ? WHERE id = ?");
        $stmt->execute([$nama_pekebun, $no_telefon, $alamat, $pekebun_id]);

        // 2. Handle Kebun Data
        $no_lot = trim($_POST['no_lot'] ?? '');
        $keluasan_kebun = trim($_POST['keluasan_kebun'] ?? '');
        $lokasi_kebun = trim($_POST['lokasi_kebun'] ?? '');
        $mukim = trim($_POST['mukim'] ?? '');
        $daerah = trim($_POST['daerah'] ?? '');
        
        // Get selected klon getah from checkboxes
        $klon_getah_selected = $_POST['klon_getah'] ?? [];
        if (empty($klon_getah_selected)) {
            throw new Exception('Sila pilih sekurang-kurangnya satu klon getah.');
        }
        $klon_getah = implode(', ', $klon_getah_selected); // Store as comma-separated string
        
        $jumlah_pokok = !empty($_POST['jumlah_pokok']) ? (int)$_POST['jumlah_pokok'] : null;
        $tahun_tanam = !empty($_POST['tahun_tanam']) ? (strlen(trim((string)$_POST['tahun_tanam'])) == 4 ? trim((string)$_POST['tahun_tanam']) . '-01-01' : trim((string)$_POST['tahun_tanam'])) : null;
        $tahun_sulaman = !empty($_POST['tahun_sulaman']) ? (strlen(trim((string)$_POST['tahun_sulaman'])) == 4 ? trim((string)$_POST['tahun_sulaman']) . '-01-01' : trim((string)$_POST['tahun_sulaman'])) : null;
        $jarak_tanaman = trim($_POST['jarak_tanaman'] ?? '');
        $koordinat = trim($_POST['koordinat'] ?? '');
        $pegawai_risda_kawasan = trim($_POST['pegawai_risda_kawasan'] ?? '');

        if (empty($no_lot) || empty($keluasan_kebun) || empty($lokasi_kebun) || empty($daerah)) {
            throw new Exception('Sila isi semua medan wajib (*) untuk maklumat kebun.');
        }

        // Handle Pelan Lot Image File Upload / Removal
        $remove_pelan_lot = isset($_POST['remove_pelan_lot']) && $_POST['remove_pelan_lot'] === '1';
        $new_pelan_lot_blob = $pelan_lot_blob;

        if ($remove_pelan_lot) {
            $new_pelan_lot_blob = null;
        }

        if (!empty($_FILES['pelan_lot_file']['name'])) {
            $max_file_size = 5 * 1024 * 1024; // 5MB
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
            $allowed_mimes = [
                'image/jpeg', 'image/pjpeg', 'image/jpg', 'image/jfif',
                'image/png', 'image/x-png',
                'image/gif',
                'image/webp',
                'application/pdf', 'application/x-pdf', 'application/octet-stream'
            ];

            $file = $_FILES['pelan_lot_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            $file_type = $file['type'];

            if ($file_error !== UPLOAD_ERR_OK) {
                throw new Exception('Ralat semasa memuat naik fail pelan lot. Sila cuba lagi.');
            }

            if ($file_size > $max_file_size) {
                throw new Exception('Saiz fail pelan lot terlalu besar. Maksimum 5MB.');
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Jenis fail tidak dibenarkan. Sila gunakan JPG, PNG, GIF, WEBP, atau PDF.');
            }

            // Inspect actual MIME type from file content
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            $valid_mime = in_array($detected_mime, $allowed_mimes) || in_array($file_type, $allowed_mimes);
            if (!$valid_mime) {
                throw new Exception('Jenis MIME fail tidak sah (' . htmlspecialchars($detected_mime ?: $file_type) . ').');
            }

            $file_content = file_get_contents($file_tmp);
            if ($file_content === false) {
                throw new Exception('Gagal membaca kandungan fail pelan lot.');
            }

            $new_pelan_lot_blob = $file_content;
        }

        // Update Kebun Record
        $stmt = $db->prepare("
            UPDATE kebun SET
                pekebun_id = ?,
                no_lot = ?,
                keluasan_kebun = ?,
                lokasi_kebun = ?,
                mukim = ?,
                daerah = ?,
                klon_getah = ?,
                jumlah_pokok = ?,
                tahun_tanam = ?,
                tahun_sulaman = ?,
                jarak_tanaman = ?,
                koordinat = ?,
                pelan_lot = ?,
                pegawai_risda_kawasan = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $pekebun_id,
            $no_lot,
            (float)$keluasan_kebun,
            $lokasi_kebun,
            $mukim,
            $daerah,
            $klon_getah,
            $jumlah_pokok,
            $tahun_tanam,
            $tahun_sulaman,
            $jarak_tanaman,
            $koordinat,
            $new_pelan_lot_blob,
            $pegawai_risda_kawasan,
            $kebun_id
        ]);

        // 3. Handle Tanam Semula Data
        $no_tanam_semula = trim($_POST['no_tanam_semula'] ?? '');
        $tahun_tanam_semula = !empty($_POST['tahun_tanam_semula']) ? (int)$_POST['tahun_tanam_semula'] : null;
        $keluasan_diluluskan = !empty($_POST['keluasan_diluluskan']) ? (float)$_POST['keluasan_diluluskan'] : null;
        $bantuan_ansuran = trim($_POST['bantuan_ansuran'] ?? '');

        $has_tanam_semula_input = (!empty($no_tanam_semula) || !empty($tahun_tanam_semula) || !empty($keluasan_diluluskan) || !empty($bantuan_ansuran));

        if (!empty($kebun['tanam_semula_id'])) {
            // Update existing Tanam Semula
            $stmt = $db->prepare("
                UPDATE tanam_semula SET
                    no_tanam_semula = ?,
                    tahun_tanam_semula = ?,
                    keluasan_diluluskan = ?,
                    bantuan_ansuran = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $no_tanam_semula,
                $tahun_tanam_semula,
                $keluasan_diluluskan,
                $bantuan_ansuran,
                $kebun['tanam_semula_id']
            ]);
        } elseif ($has_tanam_semula_input) {
            // Insert new Tanam Semula if created
            $stmt = $db->prepare("
                INSERT INTO tanam_semula (kebun_id, no_tanam_semula, tahun_tanam_semula, keluasan_diluluskan, bantuan_ansuran)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $kebun_id,
                $no_tanam_semula,
                $tahun_tanam_semula,
                $keluasan_diluluskan,
                $bantuan_ansuran
            ]);
        }

        // 4. Handle Bantuan Lain Data
        $bantuan_lain_id = (int)($_POST['bantuan_lain_id'] ?? 0);
        $nama_bantuan = trim($_POST['nama_bantuan'] ?? '');
        $jenis_tanaman_bantuan = trim($_POST['jenis_tanaman_bantuan'] ?? '');
        $tahun_bantuan = !empty($_POST['tahun_bantuan']) ? (int)$_POST['tahun_bantuan'] : null;
        $nilai_bantuan = !empty($_POST['nilai_bantuan']) ? (float)$_POST['nilai_bantuan'] : null;

        $has_bantuan_input = (!empty($nama_bantuan) || !empty($jenis_tanaman_bantuan) || !empty($tahun_bantuan) || !empty($nilai_bantuan));

        if ($bantuan_lain_id > 0) {
            if ($has_bantuan_input) {
                // Update existing Bantuan Lain
                $stmt_upd_bantuan = $db->prepare("
                    UPDATE bantuan_lain SET
                        pekebun_id = ?,
                        kebun_id = ?,
                        nama_bantuan = ?,
                        jenis_tanaman = ?,
                        tahun_bantuan = ?,
                        nilai_bantuan = ?
                    WHERE id = ?
                ");
                $stmt_upd_bantuan->execute([
                    $pekebun_id,
                    $kebun_id,
                    !empty($nama_bantuan) ? $nama_bantuan : 'Bantuan RISDA',
                    $jenis_tanaman_bantuan,
                    $tahun_bantuan,
                    $nilai_bantuan,
                    $bantuan_lain_id
                ]);
            } else {
                // Delete if cleared
                $stmt_del_bantuan = $db->prepare("DELETE FROM bantuan_lain WHERE id = ?");
                $stmt_del_bantuan->execute([$bantuan_lain_id]);
            }
        } elseif ($has_bantuan_input) {
            // Insert new Bantuan Lain
            $stmt_ins_bantuan = $db->prepare("
                INSERT INTO bantuan_lain (pekebun_id, kebun_id, nama_bantuan, jenis_tanaman, tahun_bantuan, nilai_bantuan)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_ins_bantuan->execute([
                $pekebun_id,
                $kebun_id,
                !empty($nama_bantuan) ? $nama_bantuan : 'Bantuan RISDA',
                $jenis_tanaman_bantuan,
                $tahun_bantuan,
                $nilai_bantuan
            ]);
        }

        $db->commit();

        $success = "Rekod kebun bagi No. Lot: " . htmlspecialchars($no_lot) . " telah berjaya dikemaskini!";

        // Refresh kebun data to show updated values in form
        $stmt = $db->prepare("
            SELECT 
                k.*,
                p.id AS pekebun_id, p.nama AS nama_pekebun, p.no_telefon, p.alamat,
                ts.id AS tanam_semula_id, ts.no_tanam_semula, ts.tahun_tanam_semula, ts.keluasan_diluluskan, ts.bantuan_ansuran
            FROM kebun k
            JOIN pekebun p ON k.pekebun_id = p.id
            LEFT JOIN tanam_semula ts ON k.id = ts.kebun_id
            WHERE k.id = ?
        ");
        $stmt->execute([$kebun_id]);
        $kebun = $stmt->fetch();

        // Refresh Bantuan Lain
        $stmt_bantuan = $db->prepare("SELECT * FROM bantuan_lain WHERE kebun_id = ? OR (kebun_id IS NULL AND pekebun_id = ?) ORDER BY id DESC LIMIT 1");
        $stmt_bantuan->execute([$kebun_id, $kebun['pekebun_id']]);
        $bantuan_lain = $stmt_bantuan->fetch();

        $pelan_lot_blob = $kebun['pelan_lot'] ?? null;
        $has_pelan_lot = !empty($pelan_lot_blob);
        
        // Update current klon array
        $current_klon_array = [];
        if (!empty($kebun['klon_getah'])) {
            $current_klon_array = array_map('trim', explode(',', $kebun['klon_getah']));
        }

        header("Refresh: 2; url=kebun-detail.php?id={$kebun_id}");

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kemaskini Kebun - <?= htmlspecialchars($kebun['no_lot']) ?></title>
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
        .card-custom {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            background: #ffffff;
            padding: 1.5rem;
        }
        .form-label {
            font-size: 0.875rem;
            font-weight: 700;
            color: #2d3748;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border-color: var(--card-border);
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(45, 106, 79, 0.15);
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a202c;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 0.75rem;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        .info-box {
            background: #f8f9fa;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 0.85rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        .current-img-preview {
            max-width: 220px;
            max-height: 180px;
            object-fit: contain;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            background: #fafafa;
            padding: 4px;
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.5rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid var(--card-border);
        }
        .checkbox-group .form-check {
            margin: 0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .checkbox-group .form-check:hover {
            background-color: #e9ecef;
        }
        .checkbox-group .form-check-input:checked {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        .checkbox-group .form-check-label {
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .selected-klon-badge {
            display: inline-block;
            background-color: var(--accent-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            margin: 0.25rem;
            font-weight: 500;
        }
        .selected-klon-container {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: #ffffff;
            border-radius: 8px;
            border: 1px dashed var(--card-border);
            min-height: 40px;
        }
        .pekebun-info-box {
            background: #f0f7f4;
            border-left: 4px solid var(--accent-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .pekebun-info-box .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        .pekebun-info-box .info-value {
            font-weight: 600;
            color: var(--primary-color);
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
        <div class="d-flex gap-2">
            <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-eye me-1"></i> Lihat Butiran
            </a>
            <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container px-4 mb-5" style="max-width: 900px;">

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1 text-dark">
                <i class="bi bi-pencil-square text-success me-2"></i> Kemaskini Rekod Kebun
            </h3>
            <p class="text-muted small mb-0">No. Lot: <strong><?= htmlspecialchars($kebun['no_lot']) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="kebun-delete.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i> Padam Kebun
            </a>
            <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>
                <?= htmlspecialchars($success) ?><br>
                <small class="text-muted">Mengalihkan ke halaman butiran dalam masa 2 saat...</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="card card-custom">
        <form action="kebun-edit.php?id=<?= $kebun['id'] ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="kebun_id" value="<?= $kebun['id'] ?>">

            <!-- Pekebun Section -->
            <div class="section-header">
                <i class="bi bi-person-lines-fill text-success"></i>
                <span>Maklumat Pekebun</span>
            </div>


            <!-- Edit Pekebun Fields -->
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nama_pekebun_current" class="form-label">Nama Pekebun *</label>
                    <input type="text" class="form-control" id="nama_pekebun_current" name="nama_pekebun_current" value="<?= htmlspecialchars($kebun['nama_pekebun']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="no_telefon_current" class="form-label">No. Telefon *</label>
                    <input type="tel" class="form-control" id="no_telefon_current" name="no_telefon_current" value="<?= htmlspecialchars($kebun['no_telefon']) ?>" required>
                </div>
                <div class="col-12">
                    <label for="alamat_current" class="form-label">Alamat *</label>
                    <textarea class="form-control" id="alamat_current" name="alamat_current" rows="3" required><?= htmlspecialchars($kebun['alamat']) ?></textarea>
                </div>
            </div>

            <!-- Kebun Section -->
            <div class="section-header mt-4">
                <i class="bi bi-geo-alt-fill text-success"></i>
                <span>Maklumat Kebun</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="no_lot" class="form-label">No. Lot *</label>
                    <input type="text" class="form-control" id="no_lot" name="no_lot" value="<?= htmlspecialchars($kebun['no_lot']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="keluasan_kebun" class="form-label">Keluasan Kebun (Hektar) *</label>
                    <input type="number" class="form-control" id="keluasan_kebun" name="keluasan_kebun" step="0.01" value="<?= htmlspecialchars($kebun['keluasan_kebun']) ?>" required>
                </div>

                <div class="col-md-6">
                    <label for="lokasi_kebun" class="form-label">Lokasi Kebun *</label>
                    <input type="text" class="form-control" id="lokasi_kebun" name="lokasi_kebun" value="<?= htmlspecialchars($kebun['lokasi_kebun']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="mukim" class="form-label">Mukim</label>
                    <input type="text" class="form-control" id="mukim" name="mukim" value="<?= htmlspecialchars($kebun['mukim']) ?>">
                </div>

                <div class="col-md-6">
                    <label for="daerah" class="form-label">Daerah *</label>
                    <input type="text" class="form-control" id="daerah" name="daerah" value="<?= htmlspecialchars($kebun['daerah']) ?>" required>
                </div>
                
                <!-- Klon Getah - Checkbox Group -->
                <div class="col-12">
                    <label class="form-label">Klon Getah * <span class="text-muted fw-normal">(Pilih satu atau lebih)</span></label>
                    <div class="checkbox-group" id="klonGetahGroup">
                        <?php 
                        $klon_list = ['PB 260', 'PB 350', 'RRIM 928', 'RRIM 2001', 'RRIM 2002', 'RRIM 2023', 'RRIM 2024'];
                        foreach ($klon_list as $klon):
                            $checked = in_array($klon, $current_klon_array) ? 'checked' : '';
                        ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="<?= $klon ?>" id="klon_<?= str_replace(' ', '_', $klon) ?>" <?= $checked ?> onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_<?= str_replace(' ', '_', $klon) ?>"><?= $klon ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="selected-klon-container" id="selectedKlonContainer">
                        <span class="text-muted small" id="selectedKlonText">
                            <?php if (!empty($current_klon_array)): ?>
                                <?php foreach ($current_klon_array as $klon): ?>
                                    <span class="selected-klon-badge"><?= htmlspecialchars($klon) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                Tiada klon dipilih
                            <?php endif; ?>
                        </span>
                    </div>
                    <div id="klonValidationError" class="text-danger small mt-1" style="display: none;">Sila pilih sekurang-kurangnya satu klon getah.</div>
                </div>

                <div class="col-md-6">
                    <label for="jumlah_pokok" class="form-label">Jumlah Pokok</label>
                    <input type="number" class="form-control" id="jumlah_pokok" name="jumlah_pokok" value="<?= htmlspecialchars($kebun['jumlah_pokok'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="tahun_tanam" class="form-label">Tahun Tanam</label>
                    <input type="date" class="form-control" id="tahun_tanam" name="tahun_tanam" value="<?= htmlspecialchars($kebun['tahun_tanam'] ?? '') ?>" min="1900" max="2100">
                </div>

                <div class="col-md-6">
                    <label for="tahun_sulaman" class="form-label">Tahun Sulaman</label>
                    <input type="date" class="form-control" id="tahun_sulaman" name="tahun_sulaman" value="<?= htmlspecialchars($kebun['tahun_sulaman'] ?? '') ?>" min="1900" max="2100">
                </div>
                <div class="col-md-6">
                    <label for="jarak_tanaman" class="form-label">Jarak Tanaman</label>
                    <input type="text" class="form-control" id="jarak_tanaman" name="jarak_tanaman" value="<?= htmlspecialchars($kebun['jarak_tanaman'] ?? '') ?>">
                </div>

                <div class="col-12">
                    <label for="koordinat" class="form-label">Koordinat (Latitude, Longitude)</label>
                    <input type="text" class="form-control" id="koordinat" name="koordinat" value="<?= htmlspecialchars($kebun['koordinat'] ?? '') ?>" placeholder="Contoh: 3.1357, 101.6880">
                </div>
                
                <div class="col-md-6">
                    <label for="pegawai_risda_kawasan" class="form-label">Pegawai RISDA Kawasan *</label>
                    <select class="form-select" id="pegawai_risda_kawasan" name="pegawai_risda_kawasan" required>
                        <option value="">-- Pilih Pegawai RISDA Kawasan --</option>
                        <option value="Mazlan bin Jusoh" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Mazlan bin Jusoh' ? 'selected' : '' ?>>Mazlan bin Jusoh</option>
                        <option value="Ahmad Kharsani bin Ariffin" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Ahmad Kharsani bin Ariffin' ? 'selected' : '' ?>>Ahmad Kharsani bin Ariffin</option>
                        <option value="Wan Shariman bin Wan Mamat" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Wan Shariman bin Wan Mamat' ? 'selected' : '' ?>>Wan Shariman bin Wan Mamat</option>
                        <option value="Mohd Shahrul bin Yusop" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Mohd Shahrul bin Yusop' ? 'selected' : '' ?>>Mohd Shahrul bin Yusop</option>
                        <option value="Muhamad Ezri bin Rosli" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Muhamad Ezri bin Rosli' ? 'selected' : '' ?>>Muhamad Ezri bin Rosli</option>
                        <option value="Amirul Khusairi bin Dzu" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Amirul Khusairi bin Dzu' ? 'selected' : '' ?>>Amirul Khusairi bin Dzu</option>
                        <option value="Nik Mohd Abdul Hakim bin Zalani" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Nik Mohd Abdul Hakim bin Zalani' ? 'selected' : '' ?>>Nik Mohd Abdul Hakim bin Zalani</option>
                        <option value="Mohammad Izzat bin Saidi" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Mohammad Izzat bin Saidi' ? 'selected' : '' ?>>Mohammad Izzat bin Saidi</option>
                        <option value="Mohamad Faizul bi Hashim" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Mohamad Faizul bi Hashim' ? 'selected' : '' ?>>Mohamad Faizul bi Hashim</option>
                        <option value="Ahmad Firdaus bin Teh" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Ahmad Firdaus bin Teh' ? 'selected' : '' ?>>Ahmad Firdaus bin Teh</option>
                        <option value="Mohd Rhitaudin bin Ahmad" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Mohd Rhitaudin bin Ahmad' ? 'selected' : '' ?>>Mohd Rhitaudin bin Ahmad</option>
                        <option value="Muhammad Nur Akif bin Jumaat" <?= ($kebun['pegawai_risda_kawasan'] ?? '') == 'Muhammad Nur Akif bin Jumaat' ? 'selected' : '' ?>>Muhammad Nur Akif bin Jumaat</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <label for="pelan_lot_file" class="form-label">Pelan Lot (Muat Naik Fail Baharu / Tukar)</label>
                    
                    <?php if ($has_pelan_lot): ?>
                        <?php 
                            $is_pdf = (!empty($pelan_lot_blob) && substr($pelan_lot_blob, 0, 4) === '%PDF');
                            $cache_v = substr(md5($pelan_lot_blob ?? ''), 0, 8);
                        ?>
                        <div class="mb-3 p-3 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($is_pdf): ?>
                                    <div class="p-3 bg-white rounded border text-center" style="min-width: 90px;">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-1"></i>
                                        <div class="small fw-semibold mt-1">Dokumen PDF</div>
                                    </div>
                                <?php else: ?>
                                    <img src="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" class="current-img-preview" alt="Pelan Lot Semasa" onerror="this.style.display='none';">
                                <?php endif; ?>
                                <div>
                                    <span class="badge bg-success mb-1">Fail Sedia Ada</span>
                                    <div class="small text-muted"><?= $is_pdf ? 'Dokumen PDF pelan lot telah dimuat naik.' : 'Gambar pelan lot telah dimuat naik.' ?></div>
                                    <a href="get-pelan-image.php?id=<?= $kebun['id'] ?>&v=<?= $cache_v ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> <?= $is_pdf ? 'Buka Fail PDF' : 'Lihat Gambar Penuh' ?>
                                    </a>
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remove_pelan_lot" name="remove_pelan_lot" value="1">
                                <label class="form-check-label text-danger fw-semibold" for="remove_pelan_lot">
                                    <i class="bi bi-trash me-1"></i> Padam Pelan Lot Semasa
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="file" class="form-control" id="pelan_lot_file" name="pelan_lot_file" accept=".jpg,.jpeg,.png,.gif,.pdf,.webp" onchange="displayFileName(this)">
                    <small class="text-muted d-block mt-1">
                        <i class="bi bi-info-circle me-1"></i>
                        Format dibenarkan: JPG, PNG, GIF, WEBP, PDF | Saiz maksimum: 5MB. Biarkan kosong jika tidak mahu menukar fail semasa.
                    </small>
                    <div id="file-preview" class="mt-2"></div>
                </div>
            </div>

            <!-- Tanam Semula Section -->
            <div class="section-header mt-4">
                <i class="bi bi-file-text-fill text-success"></i>
                <span>Maklumat Tanam Semula</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="no_tanam_semula" class="form-label">No. Tanam Semula</label>
                    <input type="text" class="form-control" id="no_tanam_semula" name="no_tanam_semula" value="<?= htmlspecialchars($kebun['no_tanam_semula'] ?? '') ?>" placeholder="Contoh: TS001">
                </div>
                <div class="col-md-6">
                    <label for="tahun_tanam_semula" class="form-label">Tahun Tanam Semula</label>
                    <input type="number" class="form-control" id="tahun_tanam_semula" name="tahun_tanam_semula" value="<?= htmlspecialchars($kebun['tahun_tanam_semula'] ?? '') ?>" min="1900" max="2100">
                </div>

                <div class="col-md-6">
                    <label for="keluasan_diluluskan" class="form-label">Keluasan Diluluskan (Hektar)</label>
                    <input type="number" class="form-control" id="keluasan_diluluskan" name="keluasan_diluluskan" step="0.01" value="<?= htmlspecialchars($kebun['keluasan_diluluskan'] ?? '') ?>" placeholder="Contoh: 1.5">
                </div>
                <div class="col-md-6">
                    <label for="bantuan_ansuran" class="form-label">Status Bantuan Ansuran</label>
                    <?php 
                        $ansuran_options = [
                            'Lulus - Ansuran 1' => 'Diluluskan - Ansuran 1',
                            'Lulus - Ansuran 2' => 'Diluluskan - Ansuran 2',
                            'Lulus - Ansuran 3' => 'Diluluskan - Ansuran 3',
                            'Lulus - Ansuran 4' => 'Diluluskan - Ansuran 4',
                            'Lulus - Ansuran 5' => 'Diluluskan - Ansuran 5',
                            'Ditolak'           => 'Ditolak'
                        ];
                        $current_ansuran = $kebun['bantuan_ansuran'] ?? '';
                    ?>
                    <select class="form-select" id="bantuan_ansuran" name="bantuan_ansuran">
                        <option value="">Pilih Status Ansuran</option>
                        <?php foreach ($ansuran_options as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($current_ansuran === $val) ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!array_key_exists($current_ansuran, $ansuran_options) && !empty($current_ansuran)): ?>
                            <option value="<?= htmlspecialchars($current_ansuran) ?>" selected><?= htmlspecialchars($current_ansuran) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Bantuan Lain Section -->
            <div class="section-header mt-4">
                <i class="bi bi-gift-fill text-success"></i>
                <span>Maklumat Bantuan Lain</span>
            </div>

            <input type="hidden" name="bantuan_lain_id" value="<?= htmlspecialchars($bantuan_lain['id'] ?? '') ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nama_bantuan" class="form-label">Nama Bantuan</label>
                    <select class="form-select" id="nama_bantuan" name="nama_bantuan">
                        <option value="">Pilih Nama Bantuan</option>
                        <option value="Agro@TS" <?= ($bantuan_lain['nama_bantuan'] ?? '') == 'Agro@TS' ? 'selected' : '' ?>>Agro@TS</option>
                        <option value="TSG-i" <?= ($bantuan_lain['nama_bantuan'] ?? '') == 'TSG-i' ? 'selected' : '' ?>>TSG-i</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="jenis_tanaman_bantuan" class="form-label">Jenis Tanaman</label>
                    <input type="text" class="form-control" id="jenis_tanaman_bantuan" name="jenis_tanaman_bantuan" value="<?= htmlspecialchars($bantuan_lain['jenis_tanaman'] ?? '') ?>" placeholder="Contoh: Getah / Kelapa Sawit / Kontan">
                </div>
                <div class="col-md-6">
                    <label for="tahun_bantuan" class="form-label">Tahun Bantuan</label>
                    <input type="number" class="form-control" id="tahun_bantuan" name="tahun_bantuan" value="<?= htmlspecialchars($bantuan_lain['tahun_bantuan'] ?? '') ?>" placeholder="Contoh: 2024" min="1900" max="2100">
                </div>
                <div class="col-md-6">
                    <label for="nilai_bantuan" class="form-label">Nilai Bantuan (RM)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-semibold text-muted">RM</span>
                        <input type="number" class="form-control" id="nilai_bantuan" name="nilai_bantuan" step="0.01" value="<?= htmlspecialchars($bantuan_lain['nilai_bantuan'] ?? '') ?>" placeholder="Contoh: 800.00">
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box mt-4">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Catatan:</strong> Medan bertanda * adalah wajib diisi. Anda hanya boleh mengemaskini maklumat pekebun semasa untuk kebun ini.
            </div>

            <!-- Submit Buttons -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 pt-3 border-top gap-2">
                <a href="kebun-delete.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-danger rounded-3 px-3">
                    <i class="bi bi-trash me-1"></i> Padam Rekod Kebun
                </a>
                <div class="d-flex gap-2">
                    <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-secondary rounded-3 px-4">
                        <i class="bi bi-x-circle me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success rounded-3 px-4" style="background-color: var(--accent-color);" onclick="return validateKlonSelection()">
                        <i class="bi bi-check-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>

        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateSelectedKlon() {
    const checkboxes = document.querySelectorAll('input[name="klon_getah[]"]:checked');
    const container = document.getElementById('selectedKlonContainer');
    const textElement = document.getElementById('selectedKlonText');
    const errorElement = document.getElementById('klonValidationError');
    
    if (checkboxes.length === 0) {
        textElement.innerHTML = 'Tiada klon dipilih';
        textElement.className = 'text-muted small';
        errorElement.style.display = 'none';
        return;
    }
    
    let html = '';
    checkboxes.forEach((cb, index) => {
        html += `<span class="selected-klon-badge">${cb.value}</span>`;
    });
    
    textElement.innerHTML = html;
    textElement.className = '';
    errorElement.style.display = 'none';
}

function validateKlonSelection() {
    const checkboxes = document.querySelectorAll('input[name="klon_getah[]"]:checked');
    const errorElement = document.getElementById('klonValidationError');
    
    if (checkboxes.length === 0) {
        errorElement.style.display = 'block';
        // Scroll to the klon section
        document.getElementById('klonGetahGroup').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    
    return true;
}

function displayFileName(input) {
    const preview = document.getElementById('file-preview');
    preview.innerHTML = '';
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2);
        const fileType = file.type;
        const fileExt = fileName.split('.').pop().toLowerCase();
        const isPdf = fileType === 'application/pdf' || fileExt === 'pdf';
        
        let previewHTML = `
            <div class="alert alert-info py-2 small d-flex align-items-center mb-2">
                <i class="bi bi-file-earmark-check me-2 fs-5"></i>
                <div>
                    <strong>Fail Baharu Dipilih: ${fileName}</strong><br>
                    Saiz: ${fileSize} MB
                </div>
            </div>
        `;
        
        if (fileType.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '220px';
                img.style.maxHeight = '200px';
                img.style.borderRadius = '8px';
                img.style.marginTop = '0.5rem';
                img.style.objectFit = 'contain';
                img.style.border = '1px solid #ced4da';
                img.className = 'img-thumbnail d-block';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        } else if (isPdf) {
            previewHTML += `
                <div class="p-2 border rounded bg-light d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf-fill text-danger fs-4"></i>
                    <span class="small text-muted">Dokumen PDF akan dipaparkan dan boleh dibuka selepas disimpan.</span>
                </div>
            `;
        }
        
        preview.innerHTML = previewHTML;
    }
}

// Initialize selected klon display on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedKlon();
});
</script>
</body>
</html>