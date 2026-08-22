<?php
require_once 'auth.php';
requireAdminLogin();
require_once '../config/database.php';

$db = getDBConnection();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // New Pekebun Data
        $nama = trim($_POST['nama_pekebun'] ?? '');
        $no_telefon = trim($_POST['no_telefon'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        
        if (empty($nama) || empty($no_telefon) || empty($alamat)) {
            throw new Exception('Sila isi semua maklumat pekebun.');
        }
        
        $stmt = $db->prepare("INSERT INTO pekebun (nama, no_telefon, alamat) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $no_telefon, $alamat]);
        $pekebun_id = $db->lastInsertId();
        
        // Kebun Data
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
        
        $jumlah_pokok = (int)($_POST['jumlah_pokok'] ?? 0);
        $tahun_tanam = trim($_POST['tahun_tanam'] ?? '');
        $tahun_sulaman = trim($_POST['tahun_sulaman'] ?? '');
        $jarak_tanaman = trim($_POST['jarak_tanaman'] ?? '');
        $koordinat = trim($_POST['koordinat'] ?? '');
        $pegawai_risda_kawasan = trim($_POST['pegawai_risda_kawasan'] ?? '');
        $pelan_lot_blob = null;
        
        // Validate kebun fields
        if (empty($no_lot) || empty($keluasan_kebun) || empty($lokasi_kebun) || empty($daerah)) {
            throw new Exception('Sila isi semua maklumat kebun yang diperlukan.');
        }
        
        // Handle Pelan Lot File Upload to Database
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
            
            // Check for upload errors
            if ($file_error !== UPLOAD_ERR_OK) {
                throw new Exception('Ralat semasa memuat naik fail. Sila cuba lagi.');
            }
            
            // Check file size
            if ($file_size > $max_file_size) {
                throw new Exception('Saiz fail terlalu besar. Maksimum 5MB.');
            }
            
            // Validate file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Jenis fail tidak dibenarkan. Sila gunakan JPG, PNG, GIF, WEBP, atau PDF.');
            }
            
            // Validate MIME type with finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);

            $valid_mime = in_array($detected_mime, $allowed_mimes) || in_array($file_type, $allowed_mimes);
            if (!$valid_mime) {
                throw new Exception('Jenis MIME fail tidak sah (' . htmlspecialchars($detected_mime ?: $file_type) . ').');
            }
            
            // Read file content as binary
            $file_content = file_get_contents($file_tmp);
            if ($file_content === false) {
                throw new Exception('Gagal membaca fail yang dimuat naik.');
            }
            
            $pelan_lot_blob = $file_content;
        }
        
        // Generate unique QR hash
        $qr_hash = bin2hex(random_bytes(16));
        
        // Insert Kebun with BLOB image
        $stmt = $db->prepare("
            INSERT INTO kebun (
                pekebun_id, no_lot, keluasan_kebun, lokasi_kebun, mukim, daerah,
                klon_getah, jumlah_pokok, tahun_tanam, tahun_sulaman, jarak_tanaman,
                koordinat, pelan_lot, qr_code_hash, pegawai_risda_kawasan
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $tahun_tanam_val = !empty($tahun_tanam) ? (strlen($tahun_tanam) == 4 ? $tahun_tanam . '-01-01' : $tahun_tanam) : null;
        $tahun_sulaman_val = !empty($tahun_sulaman) ? (strlen($tahun_sulaman) == 4 ? $tahun_sulaman . '-01-01' : $tahun_sulaman) : null;

        $stmt->execute([
            $pekebun_id, $no_lot, $keluasan_kebun, $lokasi_kebun, $mukim, $daerah,
            $klon_getah, $jumlah_pokok, $tahun_tanam_val, $tahun_sulaman_val, $jarak_tanaman,
            $koordinat, $pelan_lot_blob, $qr_hash, $pegawai_risda_kawasan
        ]);
        
        $kebun_id = $db->lastInsertId();
        
        // Insert Tanam Semula if provided
        $no_tanam_semula = trim($_POST['no_tanam_semula'] ?? '');
        $tahun_tanam_semula = trim($_POST['tahun_tanam_semula'] ?? '');
        $keluasan_diluluskan = trim($_POST['keluasan_diluluskan'] ?? '');
        $bantuan_ansuran = trim($_POST['bantuan_ansuran'] ?? '');
        
        // Only insert tanam semula if at least one field is filled
        if (!empty($no_tanam_semula) || !empty($tahun_tanam_semula) || !empty($keluasan_diluluskan) || !empty($bantuan_ansuran)) {
            $stmt = $db->prepare("
                INSERT INTO tanam_semula (kebun_id, no_tanam_semula, tahun_tanam_semula, keluasan_diluluskan, bantuan_ansuran)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $kebun_id,
                $no_tanam_semula,
                !empty($tahun_tanam_semula) ? (int)$tahun_tanam_semula : null,
                !empty($keluasan_diluluskan) ? (float)$keluasan_diluluskan : null,
                $bantuan_ansuran
            ]);
        }

        // Insert Bantuan Lain if provided
        $nama_bantuan = trim($_POST['nama_bantuan'] ?? '');
        $jenis_tanaman_bantuan = trim($_POST['jenis_tanaman_bantuan'] ?? '');
        $tahun_bantuan = trim($_POST['tahun_bantuan'] ?? '');
        $nilai_bantuan = trim($_POST['nilai_bantuan'] ?? '');

        if (!empty($nama_bantuan) || !empty($jenis_tanaman_bantuan) || !empty($tahun_bantuan) || !empty($nilai_bantuan)) {
            $stmt_bantuan = $db->prepare("
                INSERT INTO bantuan_lain (pekebun_id, kebun_id, nama_bantuan, jenis_tanaman, tahun_bantuan, nilai_bantuan)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_bantuan->execute([
                $pekebun_id,
                $kebun_id,
                !empty($nama_bantuan) ? $nama_bantuan : 'Bantuan RISDA',
                !empty($jenis_tanaman_bantuan) ? $jenis_tanaman_bantuan : null,
                !empty($tahun_bantuan) ? (int)$tahun_bantuan : null,
                !empty($nilai_bantuan) ? (float)$nilai_bantuan : null
            ]);
        }
        
        $success = "Kebun baru telah ditambah dengan berjaya! No. Lot: " . htmlspecialchars($no_lot);
        
        // Redirect after 2 seconds
        header("Refresh: 2; url=kebun-detail.php?id={$kebun_id}");
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kebun Baharu - RISDA</title>
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

<div class="container px-4 mb-5" style="max-width: 900px;">

    <!-- Header -->
    <div class="mb-4">
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-plus-circle-fill text-success me-2"></i> Tambah Kebun Baharu
        </h3>
        <p class="text-muted small mb-0">Lengkapkan borang di bawah untuk mendaftar kebun dan pekebun baru.</p>
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
            <div><?= htmlspecialchars($success) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="card card-custom">
        <form action="kebun-add.php" method="POST" enctype="multipart/form-data">
            
            <!-- Pekebun Section -->
            <div class="section-header">
                <i class="bi bi-person-lines-fill text-success"></i>
                <span>Maklumat Pekebun</span>
            </div>

            <!-- New Pekebun Fields -->
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nama_pekebun" class="form-label">Nama Pekebun *</label>
                    <input type="text" class="form-control" id="nama_pekebun" name="nama_pekebun" required placeholder="Nama pekebun">
                </div>
                <div class="col-md-6">
                    <label for="no_telefon" class="form-label">No. Telefon *</label>
                    <input type="tel" class="form-control" id="no_telefon" name="no_telefon" required placeholder="Contoh: 0123456789">
                </div>
                <div class="col-12">
                    <label for="alamat" class="form-label">Alamat *</label>
                    <textarea class="form-control" id="alamat" name="alamat" rows="3" required placeholder="Alamat pekebun"></textarea>
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
                    <input type="text" class="form-control" id="no_lot" name="no_lot" required placeholder="Contoh: L001">
                </div>
                <div class="col-md-6">
                    <label for="keluasan_kebun" class="form-label">Keluasan Kebun (Hektar) *</label>
                    <input type="number" class="form-control" id="keluasan_kebun" name="keluasan_kebun" required step="0.0001" placeholder="Contoh: 2.5">
                </div>

                <div class="col-md-6">
                    <label for="lokasi_kebun" class="form-label">Lokasi Kebun *</label>
                    <input type="text" class="form-control" id="lokasi_kebun" name="lokasi_kebun" required placeholder="Nama lokasi">
                </div>
                <div class="col-md-6">
                    <label for="mukim" class="form-label">Mukim</label>
                    <input type="text" class="form-control" id="mukim" name="mukim" placeholder="Nama mukim">
                </div>

                <div class="col-md-6">
                    <label for="daerah" class="form-label">Daerah *</label>
                    <input type="text" class="form-control" id="daerah" name="daerah" required placeholder="Nama daerah">
                </div>
                
                <!-- Klon Getah - Checkbox Group -->
                <div class="col-12">
                    <label class="form-label">Klon Getah * <span class="text-muted fw-normal">(Pilih satu atau lebih)</span></label>
                    <div class="checkbox-group" id="klonGetahGroup">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="PB 260" id="klon_pb260" onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_pb260">PB 260</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="PB 350" id="klon_pb350" onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_pb350">PB 350</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="RRIM 928" id="klon_rrim928" onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_rrim928">RRIM 928</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="RRIM 2001" id="klon_rrim2001" onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_rrim2001">RRIM 2001</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="RRIM 2002" id="klon_rrim2002" onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_rrim2002">RRIM 2002</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="RRIM 2023" id="klon_rrim2023" onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_rrim2023">RRIM 2023</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="klon_getah[]" value="RRIM 2024" id="klon_rrim2024" onchange="updateSelectedKlon()">
                            <label class="form-check-label" for="klon_rrim2024">RRIM 2024</label>
                        </div>
                    </div>
                    <div class="selected-klon-container" id="selectedKlonContainer">
                        <span class="text-muted small" id="selectedKlonText">Tiada klon dipilih</span>
                    </div>
                    <div id="klonValidationError" class="text-danger small mt-1" style="display: none;">Sila pilih sekurang-kurangnya satu klon getah.</div>
                </div>

                <div class="col-md-6">
                    <label for="jumlah_pokok" class="form-label">Jumlah Pokok</label>
                    <input type="number" class="form-control" id="jumlah_pokok" name="jumlah_pokok" placeholder="Contoh: 500">
                </div>
                <div class="col-md-6">
                    <label for="tahun_tanam" class="form-label">Tahun Tanam</label>
                    <input type="date" class="form-control" id="tahun_tanam" name="tahun_tanam" placeholder="Contoh: 2020" min="1900" max="2100">
                </div>

                <div class="col-md-6">
                    <label for="tahun_sulaman" class="form-label">Tahun Sulaman</label>
                    <input type="date" class="form-control" id="tahun_sulaman" name="tahun_sulaman" placeholder="Contoh: 2023" min="1900" max="2100">
                </div>
                <div class="col-md-6">
                    <label for="jarak_tanaman" class="form-label">Jarak Tanaman</label>
                    <input type="text" class="form-control" id="jarak_tanaman" name="jarak_tanaman" placeholder="Contoh: 4m x 6m">
                </div>

                <div class="col-12">
                    <label for="koordinat" class="form-label">Koordinat (Latitude, Longitude)</label>
                    <input type="text" class="form-control" id="koordinat" name="koordinat" placeholder="Contoh: 3.1357, 101.6880">
                </div>

                <div class="col-md-6">
                    <label for="pegawai_risda_kawasan" class="form-label">Pegawai RISDA Kawasan</label>
                    <select class="form-select" id="pegawai_risda_kawasan" name="pegawai_risda_kawasan" required>
                        <option value="">-- Pilih Pegawai RISDA Kawasan --</option>
                        <option value="Mazlan bin Jusoh">Mazlan bin Jusoh</option>
                        <option value="Ahmad Kharsani bin Ariffin">Ahmad Kharsani bin Ariffin</option>
                        <option value="Wan Shariman bin Wan Mamat">Wan Shariman bin Wan Mamat</option>
                        <option value="Mohd Shahrul bin Yusop">Mohd Shahrul bin Yusop</option>
                        <option value="Muhamad Ezri bin Rosli">Muhamad Ezri bin Rosli</option>
                        <option value="Amirul Khusairi bin Dzu">Amirul Khusairi bin Dzu</option>
                        <option value="Nik Mohd Abdul Hakim bin Zalani">Nik Mohd Abdul Hakim bin Zalani</option>
                        <option value="Mohammad Izzat bin Saidi">Mohammad Izzat bin Saidi</option>
                        <option value="Mohamad Faizul bi Hashim">Mohamad Faizul bi Hashim</option>
                        <option value="Ahmad Firdaus bin Teh">Ahmad Firdaus bin Teh</option>
                        <option value="Mohd Rhitaudin bin Ahmad">Mohd Rhitaudin bin Ahmad</option>
                        <option value="Muhammad Nur Akif bin Jumaat">Muhammad Nur Akif bin Jumaat</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="pelan_lot_file" class="form-label">Pelan Lot (Muat Naik Fail)</label>
                    <input type="file" class="form-control" id="pelan_lot_file" name="pelan_lot_file" accept=".jpg,.jpeg,.png,.gif,.pdf,.webp" onchange="displayFileName(this)">
                    <small class="text-muted d-block mt-1">
                        <i class="bi bi-info-circle me-1"></i>
                        Format dibenarkan: JPG, PNG, GIF, WEBP, PDF | Saiz maksimum: 5MB
                    </small>
                    <div id="file-preview" class="mt-2"></div>
                </div>
            </div>

            <!-- Tanam Semula Section -->
            <div class="section-header mt-4">
                <i class="bi bi-file-text-fill text-success"></i>
                <span>Maklumat Tanam Semula (Pilihan)</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="no_tanam_semula" class="form-label">No. Tanam Semula</label>
                    <input type="text" class="form-control" id="no_tanam_semula" name="no_tanam_semula" placeholder="Contoh: TS001">
                </div>
                <div class="col-md-6">
                    <label for="tahun_tanam_semula" class="form-label">Tahun Tanam Semula</label>
                    <input type="number" class="form-control" id="tahun_tanam_semula" name="tahun_tanam_semula" placeholder="Contoh: 2023" min="1900" max="2100">
                </div>

                <div class="col-md-6">
                    <label for="keluasan_diluluskan" class="form-label">Keluasan Diluluskan (Hektar)</label>
                    <input type="number" class="form-control" id="keluasan_diluluskan" name="keluasan_diluluskan" step="0.0001" placeholder="Contoh: 1.5">
                </div>
                <div class="col-md-6">
                    <label for="bantuan_ansuran" class="form-label">Status Bantuan Ansuran</label>
                    <select class="form-select" id="bantuan_ansuran" name="bantuan_ansuran">
                        <option value="">Pilih Status Ansuran</option>
                        <option value="Lulus - Ansuran 1">Diluluskan - Ansuran 1</option>
                        <option value="Lulus - Ansuran 2">Diluluskan - Ansuran 2</option>
                        <option value="Lulus - Ansuran 3">Diluluskan - Ansuran 3</option>
                        <option value="Lulus - Ansuran 4">Diluluskan - Ansuran 4</option>
                        <option value="Lulus - Ansuran 5">Diluluskan - Ansuran 5</option>
                        <option value="Ditolak">Ditolak</option>    
                    </select>
                </div>
            </div>

            <!-- Bantuan Lain Section -->
            <div class="section-header mt-4">
                <i class="bi bi-gift-fill text-success"></i>
                <span>Maklumat Bantuan Lain (Pilihan)</span>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nama_bantuan" class="form-label">Nama Bantuan</label>
                    <select class="form-select" id="nama_bantuan" name="nama_bantuan">
                        <option value="">Pilih Nama Bantuan</option>
                        <option value="Agro@TS">Agro@TS</option>
                        <option value="TSG-i">TSG-i</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="jenis_tanaman_bantuan" class="form-label">Jenis Tanaman</label>
                    <input type="text" class="form-control" id="jenis_tanaman_bantuan" name="jenis_tanaman_bantuan" placeholder="Contoh: Getah / Kelapa Sawit / Kontan">
                </div>
                <div class="col-md-6">
                    <label for="tahun_bantuan" class="form-label">Tahun Bantuan</label>
                    <input type="number" class="form-control" id="tahun_bantuan" name="tahun_bantuan" placeholder="Contoh: 2024" min="1900" max="2100">
                </div>
                <div class="col-md-6">
                    <label for="nilai_bantuan" class="form-label">Nilai Bantuan (RM)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-semibold text-muted">RM</span>
                        <input type="number" class="form-control" id="nilai_bantuan" name="nilai_bantuan" step="0.01" placeholder="Contoh: 800.00">
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box mt-4">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Catatan:</strong> Medan bertanda * adalah wajib diisi. Kod QR akan dijana secara automatik selepas kebun ditambah.
            </div>

            <!-- Submit Buttons -->
            <div class="d-flex gap-2 justify-content-end mt-4">
                <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 px-4">
                    <i class="bi bi-x-circle me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-success rounded-3 px-4" style="background-color: var(--accent-color);" onclick="return validateKlonSelection()">
                    <i class="bi bi-check-circle me-1"></i> Simpan Kebun Baru
                </button>
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
    preview.innerHTML = ''; // Clear previous preview
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB
        const fileType = file.type;
        const fileExt = fileName.split('.').pop().toLowerCase();
        const isPdf = fileType === 'application/pdf' || fileExt === 'pdf';
        
        let previewHTML = `
            <div class="alert alert-info py-2 small d-flex align-items-center mb-2">
                <i class="bi bi-file-earmark-check me-2 fs-5"></i>
                <div>
                    <strong>Fail Dipilih: ${fileName}</strong><br>
                    Saiz: ${fileSize} MB
                </div>
            </div>
        `;
        
        // Show image preview if it's an image
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
        
        preview.innerHTML += previewHTML;
    }
}
</script>
</body>
</html>