<?php
require_once 'auth.php';
requireAdminLogin();
require_once '../config/database.php';

// Generate CSRF token if not already created
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = getDBConnection();
$error = '';

// Get Kebun ID from GET or POST
$kebun_id = (int)($_GET['id'] ?? $_POST['kebun_id'] ?? 0);

if ($kebun_id <= 0) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'ID kebun tidak sah.'
    ];
    header('Location: dashboard.php');
    exit();
}

// Fetch existing Kebun, Pekebun, and Tanam Semula details
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
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Rekod kebun tidak dijumpai atau telah pun dipadam.'
    ];
    header('Location: dashboard.php');
    exit();
}

// Handle deletion submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted_token)) {
        $error = 'Sesi pengesahan tamat tempoh atau tidak sah. Sila muat semula halaman dan cuba lagi.';
    } else {
        try {
            $db->beginTransaction();

            $no_lot_deleted = $kebun['no_lot'];
            $nama_pekebun_deleted = $kebun['nama_pekebun'];

            // 1. Delete associated Tanam Semula records (also enforced via FK ON DELETE CASCADE)
            $stmt_ts = $db->prepare("DELETE FROM tanam_semula WHERE kebun_id = ?");
            $stmt_ts->execute([$kebun_id]);

            // 2. Delete associated Bantuan Lain records (also enforced via FK ON DELETE CASCADE)
            $stmt_bl = $db->prepare("DELETE FROM bantuan_lain WHERE kebun_id = ?");
            $stmt_bl->execute([$kebun_id]);

            // 3. Delete the Kebun record
            $stmt_k = $db->prepare("DELETE FROM kebun WHERE id = ?");
            $stmt_k->execute([$kebun_id]);

            $db->commit();

            // Refresh CSRF token after sensitive action
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Set flash success message for dashboard
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => "Rekod kebun No. Lot: {$no_lot_deleted} ({$nama_pekebun_deleted}) telah berjaya dipadam secara kekal."
            ];

            header('Location: dashboard.php');
            exit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Ralat semasa memadam rekod: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengesahan Padam Kebun - <?= htmlspecialchars($kebun['no_lot']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1b4332;
            --accent-color: #2d6a4f;
            --bg-light: #f4f6f8;
            --card-border: #e9ecef;
            --danger-color: #dc3545;
            --danger-bg: #fff5f5;
        }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
        }
        .navbar-custom { 
            background-color: var(--primary-color); 
        }
        
        .card-delete {
            border: 1px solid #f5c2c7;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(220, 53, 69, 0.08);
            background: #ffffff;
            overflow: hidden;
        }
        .card-header-danger {
            background-color: var(--danger-bg);
            border-bottom: 2px solid #f5c2c7;
            padding: 1.25rem 1.5rem;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffe69c;
            color: #664d03;
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        .summary-card {
            background-color: #f8f9fa;
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 1.25rem;
        }
        .data-label {
            font-size: 0.75rem;
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
        .badge-custom {
            display: inline-block;
            padding: 0.35em 0.65em;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
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

<div class="container px-3 mb-5" style="max-width: 800px;">

    <!-- Page Title -->
    <div class="mb-4 text-center">
        <h3 class="fw-bold text-danger mb-1">
            <i class="bi bi-trash3-fill me-2"></i> Pengesahan Padam Rekod Kebun
        </h3>
        <p class="text-muted small mb-0">Sila semak maklumat di bawah dengan teliti sebelum meneruskan pemadaman.</p>
    </div>

    <!-- Error Alert -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-octagon-fill fs-5 me-2"></i>
            <div><?= htmlspecialchars($error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Delete Confirmation Card -->
    <div class="card card-delete mb-4">
        
        <div class="card-header-danger d-flex align-items-center gap-3">
            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            </div>
            <div>
                <h5 class="fw-bold text-danger mb-0">Adakah anda pasti ingin memadam rekod ini?</h5>
                <small class="text-danger-emphasis">Tindakan ini adalah <strong>kekal</strong> dan <strong>tidak boleh diundur</strong>.</small>
            </div>
        </div>

        <div class="card-body p-4">

            <!-- Critical Warning Box -->
            <div class="warning-box mb-4 d-flex align-items-start gap-3">
                <i class="bi bi-info-circle-fill fs-4 flex-shrink-0 text-warning"></i>
                <div class="small">
                    <strong>Kesan Pemadaman:</strong>
                    <ul class="mb-0 ps-3 mt-1">
                        <li>Semua data kebun (No. Lot: <strong><?= htmlspecialchars($kebun['no_lot']) ?></strong>) akan dipadam daripada pangkalan data.</li>
                        <li>Rekod permohonan <strong>Tanam Semula</strong> yang berpaut dengan kebun ini akan dipadam secara automatik.</li>
                        <li>Fail <strong>Pelan Lot</strong> dan pautan <strong>Kod QR</strong> untuk kebun ini tidak lagi boleh diakses oleh pekebun.</li>
                    </ul>
                </div>
            </div>

            <!-- Rekod Details Summary -->
            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center">
                <i class="bi bi-clipboard-data text-secondary me-2"></i> Ringkasan Rekod Yang Akan Dipadam
            </h6>

            <div class="summary-card mb-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="data-label">No. Lot</div>
                        <div class="data-value">
                            <span class="badge-custom bg-light text-dark border"><?= htmlspecialchars($kebun['no_lot']) ?></span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="data-label">Nama Pekebun</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['nama_pekebun']) ?></div>
                    </div>

                    <div class="col-sm-6">
                        <div class="data-label">No. Telefon</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['no_telefon']) ?></div>
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
                        <div class="data-label">No. Tanam Semula</div>
                        <div class="data-value"><?= htmlspecialchars($kebun['no_tanam_semula'] ?? 'Tiada') ?></div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Form -->
            <form action="kebun-delete.php" method="POST" id="deleteForm" onsubmit="return confirmFinalDelete();">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="kebun_id" value="<?= $kebun['id'] ?>">

                <!-- Checkbox Confirmation -->
                <div class="form-check p-3 bg-light rounded-3 border border-danger-subtle mb-4">
                    <input class="form-check-input" type="checkbox" id="confirmCheckbox" onchange="toggleDeleteButton(this)">
                    <label class="form-check-label fw-semibold text-dark user-select-none" for="confirmCheckbox">
                        Saya faham dan mengesahkan bahawa saya ingin memadam rekod kebun 
                        <span class="text-danger fw-bold">Lot <?= htmlspecialchars($kebun['no_lot']) ?></span> ini secara kekal.
                    </label>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-2">
                    <a href="kebun-detail.php?id=<?= $kebun['id'] ?>" class="btn btn-outline-secondary px-4 rounded-3">
                        <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
                    </a>
                    <button type="submit" id="btnDeleteSubmit" class="btn btn-danger px-4 rounded-3 fw-semibold shadow-sm" disabled>
                        <i class="bi bi-trash-fill me-1"></i> Ya, Sahkan Padam Rekod
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleDeleteButton(checkbox) {
    const btn = document.getElementById('btnDeleteSubmit');
    if (checkbox.checked) {
        btn.removeAttribute('disabled');
        btn.classList.add('shadow');
    } else {
        btn.setAttribute('disabled', 'disabled');
        btn.classList.remove('shadow');
    }
}

function confirmFinalDelete() {
    const checkbox = document.getElementById('confirmCheckbox');
    if (!checkbox.checked) {
        alert('Sila tandakan kotak pengesahan terlebih dahulu.');
        return false;
    }
    return confirm('AMARAN AKHIR: Adakah anda benar-benar pasti ingin memadam rekod kebun ini? Data yang dipadam tidak boleh dipulihkan.');
}
</script>
</body>
</html>
