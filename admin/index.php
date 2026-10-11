<?php
require_once __DIR__ . '/../functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo 'Giriş qadağandır';
    exit;
}

initDatabase();
$stats = getAdminStats();
$userDetails = getUserDetailsList();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function roleLabel($role) {
    return $role === 'admin' ? 'Admin' : 'İstifadəçi';
}

function statusLabel($active) {
    return (int)$active === 1 ? 'Aktiv' : 'Deaktiv';
}

function dateOrDash($value) {
    if (!$value) {
        return '-';
    }

    $ts = strtotime((string)$value);
    return $ts ? date('Y-m-d H:i', $ts) : '-';
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin paneli</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand">Admin paneli</div>
            <a class="admin-link active" href="./"><i class="fa-solid fa-gauge-high"></i> İdarə paneli</a>
            <a class="admin-link" href="users.php"><i class="fa-solid fa-users"></i> İstifadəçilər</a>
            <a class="admin-link" href="../"><i class="fa-solid fa-note-sticky"></i> Qeydlərə qayıt</a>
        </aside>

        <main class="admin-main">
            <div class="admin-topbar">
                <h1 class="admin-title">İdarə paneli</h1>
            </div>

            <div class="admin-stat-grid">
                <section class="admin-stat-card">
                    <span class="admin-stat-label">Ümumi istifadəçi</span>
                    <strong class="admin-stat-value"><?php echo e($stats['totalUsers']); ?></strong>
                </section>
                <section class="admin-stat-card">
                    <span class="admin-stat-label">Admin sayı</span>
                    <strong class="admin-stat-value"><?php echo e($stats['adminUsers']); ?></strong>
                </section>
                <section class="admin-stat-card">
                    <span class="admin-stat-label">İstifadəçi sayı</span>
                    <strong class="admin-stat-value"><?php echo e($stats['regularUsers']); ?></strong>
                </section>
                <section class="admin-stat-card">
                    <span class="admin-stat-label">Ümumi qeyd</span>
                    <strong class="admin-stat-value"><?php echo e($stats['totalNotes']); ?></strong>
                </section>
                <section class="admin-stat-card">
                    <span class="admin-stat-label">Son 7 gün qeydləri</span>
                    <strong class="admin-stat-value"><?php echo e($stats['notesLast7Days']); ?></strong>
                </section>
            </div>

            <section class="admin-panel">
                <div class="admin-panel-header">
                    <span class="admin-panel-title">İstifadəçi detalları</span>
                    <a class="btn" href="users.php"><i class="fa-solid fa-users"></i> İdarə et</a>
                </div>
                <div class="admin-panel-body">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>İstifadəçi adı</th>
                                <th>Rol</th>
                                <th>Qeyd sayı</th>
                                <th>Kateqoriya sayı</th>
                                <th>Yaradılma tarixi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($userDetails) === 0): ?>
                                <tr><td colspan="7">İstifadəçi yoxdur</td></tr>
                            <?php endif; ?>
                            <?php foreach ($userDetails as $user): ?>
                                <tr>
                                    <td><?php echo e($user['id']); ?></td>
                                    <td><?php echo e($user['username']); ?></td>
                                    <td><span class="admin-role"><?php echo e(roleLabel($user['role'])); ?></span></td>
                                    <td><?php echo e($user['notes_count']); ?></td>
                                    <td><?php echo e($user['categories_count']); ?></td>
                                    <td><?php echo e(dateOrDash($user['created_at'])); ?></td>
                                    <td><span class="admin-status <?php echo (int)$user['active'] === 1 ? 'active' : 'inactive'; ?>"><?php echo e(statusLabel($user['active'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
