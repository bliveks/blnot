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

$csrfToken = $_SESSION[CSRF_TOKEN_NAME] ?? generateCSRFToken();
$currentUserId = getCurrentUserId();
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İstifadəçilər</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand">Admin paneli</div>
            <a class="admin-link" href="./"><i class="fa-solid fa-gauge-high"></i> İdarə paneli</a>
            <a class="admin-link active" href="users.php"><i class="fa-solid fa-users"></i> İstifadəçilər</a>
            <a class="admin-link" href="../"><i class="fa-solid fa-note-sticky"></i> Qeydlərə qayıt</a>
        </aside>

        <main class="admin-main">
            <div class="admin-topbar">
                <h1 class="admin-title">İstifadəçilər</h1>
            </div>

            <div class="admin-grid" id="users">
                <section class="admin-panel">
                    <div class="admin-panel-header">
                        <span class="admin-panel-title">Mövcud istifadəçilər</span>
                        <button class="btn" type="button" id="refreshUsers"><i class="fa-solid fa-rotate"></i> Yenilə</button>
                    </div>
                    <div class="admin-panel-body">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>İstifadəçi adı</th>
                                    <th>Rol</th>
                                    <th>Yaradılma tarixi</th>
                                    <th>Əməliyyatlar</th>
                                </tr>
                            </thead>
                            <tbody id="usersBody">
                                <tr><td colspan="5">Yüklənir...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="admin-panel">
                    <div class="admin-panel-header">
                        <span class="admin-panel-title">Yeni istifadəçi</span>
                    </div>
                    <div class="admin-panel-body">
                        <form id="createUserForm">
                            <div class="form-group">
                                <label class="form-label" for="username">İstifadəçi adı</label>
                                <input class="form-input" id="username" name="username" autocomplete="username" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="password">Şifrə</label>
                                <input class="form-input" id="password" name="password" type="password" autocomplete="new-password" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="role">Rol</label>
                                <select class="form-input" id="role" name="role">
                                    <option value="user">İstifadəçi</option>
                                    <?php if ($currentUserId === 1): ?>
                                        <option value="admin">Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <button class="btn primary" type="submit"><i class="fa-solid fa-plus"></i> Yarat</button>
                        </form>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="admin-modal-overlay" id="userModal" hidden>
        <div class="admin-modal">
            <div class="admin-modal-header">
                <span class="admin-modal-title" id="modalTitle">İstifadəçi</span>
                <button class="admin-modal-close" type="button" id="modalClose" aria-label="Bağla">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="admin-modal-body" id="modalBody"></div>
            <div class="admin-modal-actions" id="modalActions"></div>
        </div>
    </div>

    <div class="admin-toast-wrap" id="toastWrap"></div>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;
        const currentUserId = <?php echo json_encode($currentUserId); ?>;
        const usersBody = document.getElementById('usersBody');
        const form = document.getElementById('createUserForm');
        const toastWrap = document.getElementById('toastWrap');
        const modal = document.getElementById('userModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const modalActions = document.getElementById('modalActions');
        const modalClose = document.getElementById('modalClose');
        let currentUsers = [];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char]));
        }

        function roleLabel(role) {
            return role === 'admin' ? 'Admin' : 'İstifadəçi';
        }

        function api(action, options = {}) {
            return fetch('../functions.php?action=' + action, options).then(response => response.json());
        }

        function openModal(title, bodyHtml, actionsHtml) {
            modalTitle.textContent = title;
            modalBody.innerHTML = bodyHtml;
            modalActions.innerHTML = actionsHtml;
            modal.hidden = false;
        }

        function closeModal() {
            modal.hidden = true;
            modal.dataset.userId = '';
            modalBody.innerHTML = '';
            modalActions.innerHTML = '';
        }

        function showToast(text, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `admin-toast ${type}`;
            toast.textContent = text;
            toastWrap.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 220);
            }, 5000);
        }

        function loadUsers() {
            usersBody.innerHTML = '<tr><td colspan="5">Yüklənir...</td></tr>';
            api('getUsers')
                .then(users => {
                    if (!Array.isArray(users)) {
                        usersBody.innerHTML = '<tr><td colspan="5">Siyahı yüklənə bilmədi</td></tr>';
                        return;
                    }
                    currentUsers = users;
                    if (users.length === 0) {
                        usersBody.innerHTML = '<tr><td colspan="5">İstifadəçi yoxdur</td></tr>';
                        return;
                    }
                    usersBody.innerHTML = users.map(user => `
                        <tr>
                            <td>${escapeHtml(user.id)}</td>
                            <td>${escapeHtml(user.username)}</td>
                            <td><span class="admin-role">${escapeHtml(roleLabel(user.role))}</span></td>
                            <td>${escapeHtml(user.created_at)}</td>
                            <td>
                                <div class="admin-actions">
                                    <button class="admin-action-btn" type="button" data-action="edit" data-id="${escapeHtml(user.id)}" ${user.canEdit ? '' : 'disabled'} title="Dəyiş">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button class="admin-action-btn danger" type="button" data-action="delete" data-id="${escapeHtml(user.id)}" ${user.canDelete ? '' : 'disabled'} title="Sil">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                })
                .catch(() => {
                    usersBody.innerHTML = '<tr><td colspan="5">Siyahı yüklənə bilmədi</td></tr>';
                });
        }

        usersBody.addEventListener('click', event => {
            const button = event.target.closest('button[data-action]');
            if (!button || button.disabled) return;

            const id = Number(button.dataset.id);
            const user = currentUsers.find(item => Number(item.id) === id);
            if (!user) return;

            if (button.dataset.action === 'edit') {
                const roleOptions = `
                    <option value="user" ${user.role === 'user' ? 'selected' : ''}>İstifadəçi</option>
                    ${currentUserId === 1 ? `<option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>` : ''}
                `;
                openModal(
                    'İstifadəçini dəyiş',
                    `
                        <div class="form-group">
                            <label class="form-label" for="editUsername">İstifadəçi adı</label>
                            <input class="form-input" id="editUsername" value="${escapeHtml(user.username)}">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editRole">Rol</label>
                            <select class="form-input" id="editRole">
                                ${roleOptions}
                            </select>
                        </div>
                    `,
                    `
                        <button class="btn" type="button" data-modal-action="cancel">Ləğv et</button>
                        <button class="btn primary" type="button" data-modal-action="save-user">Yadda saxla</button>
                    `
                );
                modal.dataset.userId = String(id);
            }

            if (button.dataset.action === 'delete') {
                openModal(
                    'İstifadəçini sil',
                    `<p class="admin-modal-text"><strong>${escapeHtml(user.username)}</strong> adlı istifadəçini silmək istəyirsiniz?</p>`,
                    `
                        <button class="btn" type="button" data-modal-action="cancel">Ləğv et</button>
                        <button class="btn danger" type="button" data-modal-action="confirm-delete">Sil</button>
                    `
                );
                modal.dataset.userId = String(id);
            }
        });

        modalActions.addEventListener('click', event => {
            const action = event.target.closest('button[data-modal-action]')?.dataset.modalAction;
            if (!action) return;

            if (action === 'cancel') {
                closeModal();
                return;
            }

            const id = Number(modal.dataset.userId);

            if (action === 'save-user') {
                const editUsername = document.getElementById('editUsername').value.trim();
                const editRole = document.getElementById('editRole').value;

                api('updateUser', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id,
                        username: editUsername,
                        role: editRole,
                        csrf_token: csrfToken
                    })
                })
                    .then(result => {
                        if (!result.success) {
                            showToast(result.error || 'Əməliyyat uğursuz oldu', 'error');
                            return;
                        }
                        closeModal();
                        showToast(result.message || 'İstifadəçi yeniləndi', 'success');
                        loadUsers();
                    })
                    .catch(() => {
                        showToast('Serverlə əlaqə qurulmadı', 'error');
                    });
            }

            if (action === 'confirm-delete') {
                api('deleteUser', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id,
                        csrf_token: csrfToken
                    })
                })
                    .then(result => {
                        if (!result.success) {
                            showToast(result.error || 'Əməliyyat uğursuz oldu', 'error');
                            return;
                        }
                        closeModal();
                        showToast(result.message || 'İstifadəçi silindi', 'success');
                        loadUsers();
                    })
                    .catch(() => {
                        showToast('Serverlə əlaqə qurulmadı', 'error');
                    });
            }
        });

        modalClose.addEventListener('click', closeModal);
        modal.addEventListener('click', event => {
            if (event.target === modal) closeModal();
        });

        form.addEventListener('submit', event => {
            event.preventDefault();

            const payload = {
                username: form.username.value.trim(),
                password: form.password.value,
                role: form.role.value,
                csrf_token: csrfToken
            };

            api('createUser', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(result => {
                    if (!result.success) {
                        showToast(result.error || 'Əməliyyat uğursuz oldu', 'error');
                        return;
                    }
                    form.reset();
                    showToast(result.message || 'İstifadəçi yaradıldı', 'success');
                    loadUsers();
                })
                .catch(() => {
                    showToast('Serverlə əlaqə qurulmadı', 'error');
                });
        });

        document.getElementById('refreshUsers').addEventListener('click', loadUsers);
        loadUsers();
    </script>
</body>
</html>
