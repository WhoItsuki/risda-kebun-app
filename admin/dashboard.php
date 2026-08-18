<?php
require_once 'auth.php';
requireAdminLogin();
require_once '../config/database.php';

$db = getDBConnection();

// Search & Sort parameters
$search = trim($_GET['q'] ?? '');
$sort_col = $_GET['sort'] ?? 'k.id';
$sort_order = strtoupper($_GET['order'] ?? 'DESC');

// Allowed sort columns to prevent SQL injection
$allowed_sorts = [
    'nama' => 'p.nama',
    'no_lot' => 'k.no_lot',
    'daerah' => 'k.daerah',
    'keluasan' => 'k.keluasan_kebun',
    'klon' => 'k.klon_getah'
];

$order_by = $allowed_sorts[$sort_col] ?? 'k.id';
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// Query with Search filter across multiple fields
$sql = "
    SELECT 
        k.id AS kebun_id,
        k.qr_code_hash,
        k.no_lot,
        k.lokasi_kebun,
        k.mukim,
        k.daerah,
        k.keluasan_kebun,
        k.klon_getah,
        p.nama AS nama_pekebun,
        p.no_telefon,
        p.alamat
    FROM kebun k
    JOIN pekebun p ON k.pekebun_id = p.id
";

$params = [];
if (!empty($search)) {
    $sql .= " WHERE p.nama LIKE ? 
               OR p.no_telefon LIKE ? 
               OR p.alamat LIKE ? 
               OR k.no_lot LIKE ? 
               OR k.lokasi_kebun LIKE ? 
               OR k.mukim LIKE ? 
               OR k.daerah LIKE ? 
               OR k.klon_getah LIKE ?";
    $term = "%{$search}%";
    $params = array_fill(0, 8, $term);
}

$sql .= " ORDER BY {$order_by} {$sort_order}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$kebun_list = $stmt->fetchAll();

// Helper to toggle sort directions
function getSortUrl($column, $current_col, $current_order, $search_term) {
    $next_order = ($current_col === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';
    return "?sort={$column}&order={$next_order}" . (!empty($search_term) ? "&q=" . urlencode($search_term) : "");
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pentadbir - RISDA Kebun</title>
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
        .clickable-row { cursor: pointer; transition: background-color 0.15s ease-in-out; }
        .clickable-row:hover { background-color: #e9f5ed !important; }
        .sort-header { color: inherit; text-decoration: none; font-weight: 600; }
        .sort-header:hover { color: var(--accent-color); }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="dashboard.php">
            <img src="../assets/images/logo-risda.png" alt="Logo RISDA" style="height: 40px; width: auto; margin-right: 0.75rem;">
            Dashboard Pentadbir RISDA
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white small d-none d-md-inline">
                <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['admin_username']) ?>
            </span>
            <a href="logout.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-right me-1"></i> Log Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mb-5">
    
    <!-- Flash Notification Messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <?php 
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">
            <i class="bi bi-<?= ($flash['type'] === 'success') ? 'check-circle-fill' : 'exclamation-circle-fill' ?> me-2 fs-5"></i>
            <div><?= htmlspecialchars($flash['message'] ?? '') ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header Controls -->
    <div class="card card-custom p-4 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <h4 class="fw-bold mb-0 text-dark">Senarai Kebun Tanam Semula</h4>
                <p class="text-muted small mb-0">Klik pada mana-mana baris untuk melihat maklumat terperinci kebun.</p>
            </div>
            
            <div class="col-md-5">
                <form action="dashboard.php" method="GET" class="d-flex gap-2">
                    <?php if (!empty($sort_col)): ?>
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars($sort_order) ?>">
                    <?php endif; ?>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Cari nama, lot, telefon, lokasi, klon..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <button type="submit" class="btn btn-success px-3" style="background-color: var(--accent-color);">Cari</button>
                    <?php if (!empty($search)): ?>
                        <a href="dashboard.php" class="btn btn-outline-secondary" title="Sifar Carian"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="col-md-2 text-md-end">
                <a href="kebun-add.php" class="btn btn-success w-100 rounded-3" style="background-color: var(--primary-color);">
                    <i class="bi bi-plus-lg me-1"></i> Kebun Baharu
                </a>
            </div>
        </div>
    </div>

    <!-- Kebun Data Table -->
    <div class="card card-custom overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">
                            <a href="<?= getSortUrl('nama', $sort_col, $sort_order, $search) ?>" class="sort-header">
                                Nama Pekebun 
                                <?php if ($sort_col === 'nama'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>-short"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>No. Telefon</th>
                        <th>Alamat Pekebun</th>
                        <th>
                            <a href="<?= getSortUrl('no_lot', $sort_col, $sort_order, $search) ?>" class="sort-header">
                                No. Lot 
                                <?php if ($sort_col === 'no_lot'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>-short"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('daerah', $sort_col, $sort_order, $search) ?>" class="sort-header">
                                Lokasi & Daerah 
                                <?php if ($sort_col === 'daerah'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>-short"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('keluasan', $sort_col, $sort_order, $search) ?>" class="sort-header">
                                Keluasan (Ha) 
                                <?php if ($sort_col === 'keluasan'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>-short"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= getSortUrl('klon', $sort_col, $sort_order, $search) ?>" class="sort-header">
                                Klon Getah 
                                <?php if ($sort_col === 'klon'): ?>
                                    <i class="bi bi-arrow-<?= $sort_order === 'ASC' ? 'up' : 'down' ?>-short"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="text-center pe-4">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kebun_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox display-6 d-block mb-2"></i>
                                Tiada rekod kebun dijumpai.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($kebun_list as $row): ?>
                            <tr class="clickable-row" onclick="window.location.href='kebun-detail.php?id=<?= $row['kebun_id'] ?>'">
                                <td class="ps-4 fw-semibold text-dark"><?= htmlspecialchars($row['nama_pekebun']) ?></td>
                                <td><?= htmlspecialchars($row['no_telefon']) ?></td>
                                <td class="small text-muted" style="max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($row['alamat']) ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['no_lot']) ?></span></td>
                                <td><?= htmlspecialchars($row['lokasi_kebun']) ?>, <?= htmlspecialchars($row['daerah']) ?></td>
                                <td><?= htmlspecialchars($row['keluasan_kebun']) ?> Ha</td>
                                <td><span class="badge bg-success-subtle text-success border border-success-subtle"><?= htmlspecialchars($row['klon_getah']) ?></span></td>
                                <td class="text-center pe-4" onclick="event.stopPropagation();">
                                    <div class="btn-group btn-group-sm">
                                        <a href="kebun-detail.php?id=<?= $row['kebun_id'] ?>" class="btn btn-outline-primary" title="Lihat"><i class="bi bi-eye"></i></a>
                                        <a href="kebun-edit.php?id=<?= $row['kebun_id'] ?>" class="btn btn-outline-secondary" title="Kemaskini"><i class="bi bi-pencil"></i></a>
                                        <a href="kebun-qr.php?id=<?= $row['kebun_id'] ?>" class="btn btn-outline-success" title="Kod QR"><i class="bi bi-qr-code"></i></a>
                                        <a href="kebun-delete.php?id=<?= $row['kebun_id'] ?>" class="btn btn-outline-danger" title="Padam Rekod"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>