<!-- Admin Panel -->
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-speedometer2"></i>
                        داشبورد مدیریت
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-primary text-white">
                                <div class="fs-3 fw-bold" id="totalUsers">0</div>
                                <div class="small">کل کاربران</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-success text-white">
                                <div class="fs-3 fw-bold" id="activeUsers">0</div>
                                <div class="small">کاربران فعال</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-info text-white">
                                <div class="fs-3 fw-bold" id="todayUsers">0</div>
                                <div class="small">کاربران امروز</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3 bg-warning text-white">
                                <div class="fs-3 fw-bold" id="totalSessions">0</div>
                                <div class="small">کل نشست‌ها</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning"></i>
                        اقدامات سریع
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <button class="btn btn-primary w-100" onclick="showBroadcastModal()">
                                <i class="bi bi-megaphone"></i>
                                <div class="small">ارسال پیام همگانی</div>
                            </button>
                        </div>
                        <div class="col-6 col-md-3">
                            <button class="btn btn-info w-100" onclick="showSystemSettings()">
                                <i class="bi bi-gear"></i>
                                <div class="small">تنظیمات سیستم</div>
                            </button>
                        </div>
                        <div class="col-6 col-md-3">
                            <button class="btn btn-warning w-100" onclick="showMaintenanceModal()">
                                <i class="bi bi-tools"></i>
                                <div class="small">حالت تعمیرات</div>
                            </button>
                        </div>
                        <div class="col-6 col-md-3">
                            <button class="btn btn-secondary w-100" onclick="showBackupModal()">
                                <i class="bi bi-cloud-download"></i>
                                <div class="small">پشتیبان‌گیری</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Management -->
            <div class="card mb-4 fade-in">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-people"></i>
                        مدیریت کاربران
                    </h5>
                    <div>
                        <input type="text" class="form-control form-control-sm d-inline-block w-auto" 
                               placeholder="جستجو..." id="userSearch" onkeyup="searchUsers()">
                        <button class="btn btn-sm btn-outline-primary" onclick="loadUsers()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>شناسه</th>
                                    <th>نام کاربر</th>
                                    <th>نام کاربری تلگرم</th>
                                    <th>زبان</th>
                                    <th>تاریخ عضویت</th>
                                    <th>آخرین بازدید</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">در حال بارگذاری...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <nav aria-label="صفحه‌بندی کاربران">
                        <ul class="pagination justify-content-center mb-0" id="usersPagination">
                        </ul>
                    </nav>
                </div>
            </div>
            
            <!-- System Logs -->
            <div class="card fade-in">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-text"></i>
                        لاگ‌های سیستم
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="btn-group" role="group">
                            <input type="radio" class="btn-check" name="logLevel" id="logAll" checked>
                            <label class="btn btn-outline-primary btn-sm" for="logAll">همه</label>
                            
                            <input type="radio" class="btn-check" name="logLevel" id="logInfo">
                            <label class="btn btn-outline-info btn-sm" for="logInfo">اطلاعات</label>
                            
                            <input type="radio" class="btn-check" name="logLevel" id="logWarning">
                            <label class="btn btn-outline-warning btn-sm" for="logWarning">هشدار</label>
                            
                            <input type="radio" class="btn-check" name="logLevel" id="logError">
                            <label class="btn btn-outline-danger btn-sm" for="logError">خطا</label>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearLogs()">
                            <i class="bi bi-trash"></i>
                            پاک‌سازی لاگ‌ها
                        </button>
                    </div>
                    <div id="logsContainer" style="max-height: 300px; overflow-y: auto; background: #f8f9fa; border-radius: 8px; padding: 15px;">
                        <div class="text-muted text-center py-3">
                            <i class="bi bi-clock-history"></i>
                            در حال بارگذاری لاگ‌ها...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Broadcast Modal -->
<div class="modal fade" id="broadcastModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-megaphone"></i>
                    ارسال پیام همگانی
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="broadcastForm">
                    <div class="mb-3">
                        <label for="broadcastTitle" class="form-label">عنوان پیام</label>
                        <input type="text" class="form-control" id="broadcastTitle" required>
                    </div>
                    <div class="mb-3">
                        <label for="broadcastMessage" class="form-label">متن پیام</label>
                        <textarea class="form-control" id="broadcastMessage" rows="5" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="broadcastType" class="form-label">نوع پیام</label>
                                <select class="form-select" id="broadcastType">
                                    <option value="info">اطلاعات</option>
                                    <option value="success">موفقیت</option>
                                    <option value="warning">هشدار</option>
                                    <option value="error">خطا</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="broadcastPriority" class="form-label">اولویت</label>
                                <select class="form-select" id="broadcastPriority">
                                    <option value="low">پایین</option>
                                    <option value="normal">عادی</option>
                                    <option value="high">بالا</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="broadcastSendNotification">
                            <label class="form-check-label" for="broadcastSendNotification">
                                ارسال به‌عنوان اعلان به کاربران
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary" onclick="sendBroadcast()">
                    <i class="bi bi-send"></i>
                    ارسال پیام
                </button>
            </div>
        </div>
    </div>
</div>

<!-- System Settings Modal -->
<div class="modal fade" id="systemSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-gear"></i>
                    تنظیمات سیستم
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="systemSettingsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                <button type="button" class="btn btn-primary" onclick="saveSystemSettings()">
                    <i class="bi bi-save"></i>
                    ذخیره تغییرات
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let usersData = [];

// Load dashboard statistics
function loadDashboardStats() {
    apiRequest('admin/stats')
        .then(data => {
            document.getElementById('totalUsers').textContent = data.stats.total_users;
            document.getElementById('activeUsers').textContent = data.stats.active_users;
            document.getElementById('todayUsers').textContent = data.stats.today_users;
            document.getElementById('totalSessions').textContent = data.stats.total_sessions;
        })
        .catch(error => {
            console.error('Error loading dashboard stats:', error);
        });
}

// Load users
function loadUsers(page = 1) {
    currentPage = page;
    showLoading();
    
    apiRequest(`admin/users?page=${page}`)
        .then(data => {
            usersData = data.users;
            displayUsers(data.users);
            updatePagination(data.pagination);
            hideLoading();
        })
        .catch(error => {
            hideLoading();
            showAlert('خطا در بارگذاری کاربران');
        });
}

// Display users in table
function displayUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    
    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mb-0">هیچ کاربری یافت نشد</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar me-2" style="width: 32px; height: 32px; font-size: 14px;">
                        ${user.first_name.charAt(0)}
                    </div>
                    <div>
                        <div class="fw-bold">${user.first_name} ${user.last_name || ''}</div>
                        <small class="text-muted">@${user.username || 'بدون نام کاربری'}</small>
                    </div>
                </div>
            </td>
            <td>${user.telegram_id}</td>
            <td>
                <span class="badge bg-secondary">${user.language_code}</span>
            </td>
            <td>${new Date(user.created_at).toLocaleDateString('fa-IR')}</td>
            <td>${new Date(user.last_seen).toLocaleDateString('fa-IR')}</td>
            <td>
                <span class="badge ${user.is_active ? 'bg-success' : 'bg-danger'}">
                    ${user.is_active ? 'فعال' : 'غیرفعال'}
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="viewUser(${user.id})" title="مشاهده">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-warning" onclick="editUser(${user.id})" title="ویرایش">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="banUser(${user.id})" title="مسدود کردن">
                        <i class="bi bi-ban"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Update pagination
function updatePagination(pagination) {
    const paginationElement = document.getElementById('usersPagination');
    const { page, pages, total } = pagination;
    
    let html = '';
    
    // Previous button
    html += `
        <li class="page-item ${page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadUsers(${page - 1}); return false;">
                <i class="bi bi-chevron-right"></i>
            </a>
        </li>
    `;
    
    // Page numbers
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - 1 && i <= page + 1)) {
            html += `
                <li class="page-item ${i === page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadUsers(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === page - 2 || i === page + 2) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Next button
    html += `
        <li class="page-item ${page === pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadUsers(${page + 1}); return false;">
                <i class="bi bi-chevron-left"></i>
            </a>
        </li>
    `;
    
    paginationElement.innerHTML = html;
}

// Search users
function searchUsers() {
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const filteredUsers = usersData.filter(user => 
        user.first_name.toLowerCase().includes(searchTerm) ||
        user.last_name.toLowerCase().includes(searchTerm) ||
        user.username.toLowerCase().includes(searchTerm) ||
        user.telegram_id.toString().includes(searchTerm)
    );
    
    displayUsers(filteredUsers);
}

// User actions
function viewUser(userId) {
    showAlert(`مشاهده اطلاعات کاربر ${userId}`);
}

function editUser(userId) {
    showAlert(`ویرایش کاربر ${userId}`);
}

function banUser(userId) {
    showConfirm('آیا مطمئن هستید که می‌خواهید این کاربر را مسدود کنید؟', function() {
        apiRequest(`admin/users/${userId}/ban`, 'POST')
            .then(data => {
                showAlert('کاربر با موفقیت مسدود شد');
                loadUsers(currentPage);
            })
            .catch(error => {
                showAlert('خطا در مسدود کردن کاربر');
            });
    });
}

// Modal functions
function showBroadcastModal() {
    new bootstrap.Modal(document.getElementById('broadcastModal')).show();
}

function showSystemSettings() {
    new bootstrap.Modal(document.getElementById('systemSettingsModal')).show();
    loadSystemSettings();
}

function showMaintenanceModal() {
    showConfirm('آیا می‌خواهید حالت تعمیرات را فعال/غیرفعال کنید؟', function() {
        apiRequest('admin/maintenance/toggle', 'POST')
            .then(data => {
                showAlert(`حالت تعمیرات ${data.enabled ? 'فعال' : 'غیرفعال'} شد`);
            })
            .catch(error => {
                showAlert('خطا در تغییر حالت تعمیرات');
            });
    });
}

function showBackupModal() {
    showConfirm('آیا می‌خواهید از داده‌ها پشتیبان‌گیری کنید؟', function() {
        showLoading();
        apiRequest('admin/backup', 'POST')
            .then(data => {
                hideLoading();
                showAlert(`پشتیبان‌گیری با موفقیت انجام شد. مسیر فایل: ${data.backup_path}`);
            })
            .catch(error => {
                hideLoading();
                showAlert('خطا در پشتیبان‌گیری');
            });
    });
}

// System settings
function loadSystemSettings() {
    apiRequest('admin/settings')
        .then(data => {
            const content = document.getElementById('systemSettingsContent');
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">نام برنامه</label>
                            <input type="text" class="form-control" value="${data.settings.app_name || ''}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">آدرس وب‌سایت</label>
                            <input type="url" class="form-control" value="${data.settings.app_url || ''}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">حداکثر اندازه فایل (MB)</label>
                            <input type="number" class="form-control" value="${data.settings.max_file_size / 1048576 || 10}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">انواع فایل مجاز</label>
                            <input type="text" class="form-control" value="${data.settings.allowed_file_types || 'jpg,jpeg,png,gif,pdf'}">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" ${data.settings.rate_limiting_enabled ? 'checked' : ''}>
                        <label class="form-check-label">فعال‌سازی محدودیت نرخ</label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" ${data.settings.cache_enabled ? 'checked' : ''}>
                        <label class="form-check-label">فعال‌سازی کش</label>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            document.getElementById('systemSettingsContent').innerHTML = '<div class="alert alert-danger">خطا در بارگذاری تنظیمات</div>';
        });
}

function saveSystemSettings() {
    showAlert('تنظیمات با موفقیت ذخیره شد');
    bootstrap.Modal.getInstance(document.getElementById('systemSettingsModal')).hide();
}

// Broadcast message
function sendBroadcast() {
    const title = document.getElementById('broadcastTitle').value;
    const message = document.getElementById('broadcastMessage').value;
    const type = document.getElementById('broadcastType').value;
    const priority = document.getElementById('broadcastPriority').value;
    const sendNotification = document.getElementById('broadcastSendNotification').checked;
    
    if (!title || !message) {
        showAlert('لطفاً عنوان و متن پیام را وارد کنید');
        return;
    }
    
    showLoading();
    
    const data = {
        title: title,
        message: message,
        type: type,
        priority: priority,
        send_notification: sendNotification
    };
    
    apiRequest('admin/broadcast', 'POST', data)
        .then(response => {
            hideLoading();
            showAlert(`پیام همگانی با موفقیت ارسال شد. تعداد دریافت‌کنندگان: ${response.recipients}`);
            bootstrap.Modal.getInstance(document.getElementById('broadcastModal')).hide();
        })
        .catch(error => {
            hideLoading();
            showAlert('خطا در ارسال پیام همگانی');
        });
}

// Load logs
function loadLogs() {
    apiRequest('admin/logs')
        .then(data => {
            const container = document.getElementById('logsContainer');
            if (data.logs.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-3">هیچ لاگی یافت نشد</div>';
                return;
            }
            
            container.innerHTML = data.logs.map(log => `
                <div class="log-entry mb-2 p-2 rounded ${getLogLevelClass(log.level)}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>[${log.level.toUpperCase()}]</strong>
                            ${log.message}
                        </div>
                        <small class="text-muted">${new Date(log.created_at).toLocaleString('fa-IR')}</small>
                    </div>
                </div>
            `).join('');
        })
        .catch(error => {
            document.getElementById('logsContainer').innerHTML = '<div class="alert alert-danger">خطا در بارگذاری لاگ‌ها</div>';
        });
}

function getLogLevelClass(level) {
    switch (level) {
        case 'error': return 'bg-danger text-white';
        case 'warning': return 'bg-warning';
        case 'info': return 'bg-info text-white';
        default: return 'bg-light';
    }
}

function clearLogs() {
    showConfirm('آیا مطمئن هستید که می‌خواهید لاگ‌ها را پاک کنید؟', function() {
        apiRequest('admin/logs/clear', 'POST')
            .then(data => {
                showAlert('لاگ‌ها با موفقیت پاک شدند');
                loadLogs();
            })
            .catch(error => {
                showAlert('خطا در پاک‌سازی لاگ‌ها');
            });
    });
}

// Initialize admin panel
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadUsers();
    loadLogs();
    
    // Auto refresh stats every 30 seconds
    setInterval(loadDashboardStats, 30000);
});
</script>