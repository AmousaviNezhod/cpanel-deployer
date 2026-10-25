/**
 * cPanel Deployer - Client Application Logic
 */
document.addEventListener('DOMContentLoaded', () => {
    // CSRF Token from meta
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // Modals
    const deployModal = document.getElementById('deployModal');
    const updateModal = document.getElementById('updateModal');
    const deleteModal = document.getElementById('deleteModal');
    const sqliteModal = document.getElementById('sqliteModal');
    const mysqlModal = document.getElementById('mysqlModal');
    const quotaModal = document.getElementById('quotaModal');

    // Forms
    const deployForm = document.getElementById('deployForm');
    const updateForm = document.getElementById('updateForm');
    const deleteForm = document.getElementById('deleteForm');
    const sqliteForm = document.getElementById('sqliteForm');
    const mysqlForm = document.getElementById('mysqlForm');
    const quotaForm = document.getElementById('quotaForm');

    // Database Studio elements
    let activeDbId = null;
    let activeDbType = null;
    let activeDbName = null;
    const activeDbTitle = document.getElementById('activeDbTitle');
    const activeDbBadgeContainer = document.getElementById('activeDbBadgeContainer');
    const dbTablesStrip = document.getElementById('dbTablesStrip');
    const sqlQueryTextarea = document.getElementById('sqlQueryTextarea');
    const runSqlQueryBtn = document.getElementById('runSqlQueryBtn');
    const sqlResultsContainer = document.getElementById('sqlResultsContainer');

    // Toast Container
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }

    /**
     * Show Toast Notification
     */
    window.showToast = function(message, type = 'info', duration = 4000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            success: '✓',
            error: '✕',
            info: 'ℹ'
        };

        toast.innerHTML = `<span>${icons[type] || 'ℹ'}</span><div>${message}</div>`;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };

    /**
     * Modal Helpers
     */
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            const filenameEl = modal.querySelector('.selected-filename');
            if (filenameEl) filenameEl.textContent = '';
            const progress = modal.querySelector('.progress-container');
            if (progress) progress.style.display = 'none';
        }
    }

    // Modal Triggers
    const openDeployBtn = document.getElementById('openDeployModal');
    if (openDeployBtn) {
        openDeployBtn.addEventListener('click', () => openModal(deployModal));
    }

    const openNewSqliteBtn = document.getElementById('openNewSqliteModal');
    if (openNewSqliteBtn) {
        openNewSqliteBtn.addEventListener('click', () => openModal(sqliteModal));
    }

    const openNewMysqlBtn = document.getElementById('openNewMysqlModal');
    if (openNewMysqlBtn) {
        openNewMysqlBtn.addEventListener('click', () => openModal(mysqlModal));
    }

    const openQuotaEditBtn = document.getElementById('openQuotaEditBtn');
    if (openQuotaEditBtn) {
        openQuotaEditBtn.addEventListener('click', () => openModal(quotaModal));
    }

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            closeModal(modal);
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(closeModal);
        }
    });

    /**
     * Navigation Tabs
     */
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const targetPane = document.getElementById(tabId);
            if (targetPane) {
                targetPane.classList.add('active');
            }
        });
    });

    /**
     * Slug input live validation & URL preview
     */
    const slugInput = document.getElementById('siteSlugInput');
    const slugPreview = document.getElementById('slugUrlPreview');

    if (slugInput && slugPreview) {
        const baseHref = slugPreview.textContent.replace(/\/slug\/$/, '/');
        slugInput.addEventListener('input', () => {
            let val = slugInput.value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
            if (val.startsWith('-') || val.startsWith('_')) {
                val = val.substring(1);
            }
            slugInput.value = val;
            slugPreview.textContent = `${baseHref}${val || 'slug'}/`;
        });
    }

    /**
     * Drag and Drop File Handlers
     */
    function setupDropzone(dropzoneId, inputId, filenameId) {
        const dropzone = document.getElementById(dropzoneId);
        const input = document.getElementById(inputId);
        const filenameDisplay = document.getElementById(filenameId);

        if (!dropzone || !input) return;

        ['dragenter', 'dragover'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                input.files = files;
                updateFileDisplay(files[0]);
            }
        });

        input.addEventListener('change', () => {
            if (input.files.length > 0) {
                updateFileDisplay(input.files[0]);
            }
        });

        function updateFileDisplay(file) {
            if (!file) return;
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            if (filenameDisplay) {
                filenameDisplay.textContent = `فایل انتخاب‌شده: ${file.name} (${sizeMb} MB)`;
            }
        }
    }

    setupDropzone('deployDropzone', 'deployZipInput', 'deployFilename');
    setupDropzone('updateDropzone', 'updateZipInput', 'updateFilename');

    /**
     * Open Update Modal
     */
    document.querySelectorAll('[data-action="open-update"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            const fixRoutes = btn.getAttribute('data-fix-routes') === '1';
            document.getElementById('updateSlugInput').value = slug;
            document.getElementById('updateSiteNameDisplay').textContent = `${name} (/${slug}/)`;
            const fixRoutesCb = document.getElementById('updateFixRoutesCheckbox');
            if (fixRoutesCb) {
                fixRoutesCb.checked = fixRoutes;
            }
            openModal(updateModal);
        });
    });

    /**
     * Open Delete Modal
     */
    document.querySelectorAll('[data-action="open-delete"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            document.getElementById('deleteSlugInput').value = slug;
            document.getElementById('deleteTargetName').textContent = name;
            document.getElementById('deleteTargetPath').textContent = `/${slug}/`;
            openModal(deleteModal);
        });
    });

    /**
     * Handle Deploy Form Submission
     */
    if (deployForm) {
        deployForm.addEventListener('submit', (e) => {
            e.preventDefault();
            uploadWithProgress(deployForm, './?action=deploy', deployModal, 'در حال بررسی امنیتی و استخراج...');
        });
    }

    /**
     * Handle Update Form Submission
     */
    if (updateForm) {
        updateForm.addEventListener('submit', (e) => {
            e.preventDefault();
            uploadWithProgress(updateForm, './?action=update', updateModal, 'در حال جایگزینی ایمن و به‌روزرسانی...');
        });
    }

    /**
     * Handle Delete Form Submission
     */
    if (deleteForm) {
        deleteForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = deleteForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال حذف...';

            const formData = new FormData(deleteForm);
            if (csrfToken && !formData.has('csrf_token')) {
                formData.append('csrf_token', csrfToken);
            }

            fetch('./?action=delete', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'بله، حذف کن';

                if (data.success) {
                    showToast(data.message || 'سایت با موفقیت حذف شد.', 'success');
                    closeModal(deleteModal);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || 'خطا در حذف سایت.', 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'بله، حذف کن';
                showToast('خطای شبکه در ارتباط با سرور.', 'error');
            });
        });
    }

    /**
     * Database Studio: Selection & Table Explorer
     */
    document.querySelectorAll('.db-item-card').forEach(card => {
        card.addEventListener('click', (e) => {
            // Ignore if click was on delete button
            if (e.target.closest('[data-delete-db]') || e.target.closest('[data-delete-mysql]')) {
                return;
            }

            document.querySelectorAll('.db-item-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');

            activeDbId = card.getAttribute('data-db-id');
            activeDbType = card.getAttribute('data-db-type');
            activeDbName = card.getAttribute('data-db-name');

            selectDatabase(activeDbId, activeDbType, activeDbName);
        });
    });

    function selectDatabase(id, type, name) {
        if (activeDbTitle) {
            activeDbTitle.textContent = name;
        }

        if (activeDbBadgeContainer) {
            const badgeClass = type === 'sqlite' ? 'badge-sqlite' : 'badge-mysql';
            const badgeText = type === 'sqlite' ? 'SQLite' : 'MySQL';
            activeDbBadgeContainer.innerHTML = `<span class="badge ${badgeClass}">${badgeText}</span>`;
        }

        if (runSqlQueryBtn) {
            runSqlQueryBtn.disabled = false;
        }

        // Fetch tables
        if (dbTablesStrip) {
            dbTablesStrip.innerHTML = '<span style="font-size: 0.8rem; color: var(--text-muted);">در حال دریافت لیست جداول...</span>';
        }

        fetch(`./?action=db_tables&id=${encodeURIComponent(id)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.tables) {
                renderTableChips(data.tables);
            } else {
                dbTablesStrip.innerHTML = `<span style="font-size: 0.8rem; color: var(--danger);">${data.error || 'خطا در دریافت جداول'}</span>`;
            }
        })
        .catch(() => {
            dbTablesStrip.innerHTML = '<span style="font-size: 0.8rem; color: var(--danger);">خطای شبکه در دریافت جداول.</span>';
        });
    }

    function renderTableChips(tables) {
        if (!dbTablesStrip) return;
        if (tables.length === 0) {
            dbTablesStrip.innerHTML = '<span style="font-size: 0.8rem; color: var(--text-muted);">هیچ جدولی در این پایگاه داده وجود ندارد.</span>';
            return;
        }

        dbTablesStrip.innerHTML = '';
        tables.forEach(t => {
            const chip = document.createElement('div');
            chip.className = 'db-table-chip';
            chip.innerHTML = `<strong>${escapeHtml(t.name)}</strong><span>(${t.rows} ردیف)</span>`;
            chip.addEventListener('click', () => {
                const query = `SELECT * FROM ${t.name} LIMIT 25;`;
                if (sqlQueryTextarea) {
                    sqlQueryTextarea.value = query;
                    runQuery();
                }
            });
            dbTablesStrip.appendChild(chip);
        });
    }

    // SQL Template Buttons
    document.querySelectorAll('.sql-quick-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tpl = btn.getAttribute('data-template');
            if (sqlQueryTextarea) {
                sqlQueryTextarea.value = tpl;
                sqlQueryTextarea.focus();
            }
        });
    });

    // Run SQL Query
    if (runSqlQueryBtn) {
        runSqlQueryBtn.addEventListener('click', runQuery);
    }

    if (sqlQueryTextarea) {
        sqlQueryTextarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                runQuery();
            }
        });
    }

    function runQuery() {
        if (!activeDbId) {
            showToast('لطفاً ابتدا یک دیتابیس را انتخاب کنید.', 'error');
            return;
        }

        const sql = sqlQueryTextarea ? sqlQueryTextarea.value.trim() : '';
        if (!sql) {
            showToast('لطفاً دستور SQL را وارد کنید.', 'error');
            return;
        }

        if (runSqlQueryBtn) {
            runSqlQueryBtn.disabled = true;
            runSqlQueryBtn.innerHTML = '<span>⏳</span> در حال اجرا...';
        }

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('id', activeDbId);
        formData.append('sql', sql);

        fetch('./?action=db_query', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (runSqlQueryBtn) {
                runSqlQueryBtn.disabled = false;
                runSqlQueryBtn.innerHTML = '<span>▶</span> اجرای کوئری (Ctrl+Enter)';
            }
            renderQueryResults(data);
        })
        .catch(() => {
            if (runSqlQueryBtn) {
                runSqlQueryBtn.disabled = false;
                runSqlQueryBtn.innerHTML = '<span>▶</span> اجرای کوئری (Ctrl+Enter)';
            }
            if (sqlResultsContainer) {
                sqlResultsContainer.innerHTML = '<div class="alert alert-danger">خطای شبکه در اجرای کوئری.</div>';
            }
        });
    }

    function renderQueryResults(res) {
        if (!sqlResultsContainer) return;

        if (!res.success) {
            sqlResultsContainer.innerHTML = `
                <div class="alert alert-danger" style="margin: 0.5rem 0;">
                    <span>✕</span>
                    <div><strong>خطای SQL:</strong> ${escapeHtml(res.error || 'خطای ناشناخته')}</div>
                </div>
            `;
            return;
        }

        let html = `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: #0c1220; border-bottom: 1px solid var(--border); font-size: 0.8rem;">
                <span style="color: var(--success); font-weight: 600;">✓ ${escapeHtml(res.message)}</span>
                <span style="color: var(--text-muted); font-family: ui-monospace, monospace;">${res.duration_ms} ms</span>
            </div>
        `;

        if (res.is_select && res.rows && res.rows.length > 0) {
            html += '<div class="table-responsive"><table class="data-table" style="font-size: 0.8rem;"><thead><tr>';
            res.columns.forEach(col => {
                html += `<th>${escapeHtml(col)}</th>`;
            });
            html += '</tr></thead><tbody>';

            res.rows.forEach(row => {
                html += '<tr>';
                res.columns.forEach(col => {
                    const val = row[col] !== null ? String(row[col]) : '<em style="color: var(--text-muted);">NULL</em>';
                    html += `<td>${escapeHtml(val)}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';
        } else if (res.is_select) {
            html += '<div style="padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">کوئری اجرا شد اما هیچ ردیفی یافت نشد (0 Rows).</div>';
        }

        sqlResultsContainer.innerHTML = `<div class="sql-results-box">${html}</div>`;
    }

    /**
     * Create SQLite DB Submission
     */
    if (sqliteForm) {
        sqliteForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = sqliteForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال ایجاد...';

            const formData = new FormData(sqliteForm);
            formData.append('csrf_token', csrfToken);

            fetch('./?action=db_create_sqlite', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ایجاد دیتابیس';
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(sqliteModal);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || 'خطا در ایجاد دیتابیس.', 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ایجاد دیتابیس';
                showToast('خطای شبکه.', 'error');
            });
        });
    }

    /**
     * Connect MySQL Submission
     */
    if (mysqlForm) {
        mysqlForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = mysqlForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال تست و ذخیره...';

            const formData = new FormData(mysqlForm);
            formData.append('csrf_token', csrfToken);

            fetch('./?action=db_save_mysql', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'تست و ذخیره اتصال';
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(mysqlModal);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || 'خطا در اتصال به MySQL.', 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'تست و ذخیره اتصال';
                showToast('خطای شبکه.', 'error');
            });
        });
    }

    /**
     * Set Custom Quota Submission
     */
    if (quotaForm) {
        quotaForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = quotaForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال ذخیره...';

            const formData = new FormData(quotaForm);
            formData.append('csrf_token', csrfToken);

            fetch('./?action=update_quota', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ذخیره سهمیه';
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(quotaModal);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || 'خطا در ذخیره سهمیه.', 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ذخیره سهمیه';
                showToast('خطای شبکه.', 'error');
            });
        });
    }

    /**
     * Delete SQLite DB button handler
     */
    document.querySelectorAll('[data-delete-db]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const dbId = btn.getAttribute('data-delete-db');
            if (!confirm(`آیا از حذف دیتابیس SQLite مطمئن هستید؟`)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('id', dbId);

            fetch('./?action=db_delete_sqlite', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    showToast(data.error || 'خطا در حذف دیتابیس.', 'error');
                }
            });
        });
    });

    /**
     * Delete MySQL Connection profile handler
     */
    document.querySelectorAll('[data-delete-mysql]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const connId = btn.getAttribute('data-delete-mysql');
            if (!confirm(`آیا از حذف این اتصال MySQL مطمئن هستید؟`)) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('id', connId);

            fetch('./?action=db_delete_mysql', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                } else {
                    showToast(data.error || 'خطا در حذف پروفایل MySQL.', 'error');
                }
            });
        });
    });

    /**
     * Auto select first DB if available in tab
     */
    const firstDbCard = document.querySelector('.db-item-card');
    if (firstDbCard) {
        firstDbCard.click();
    }

    /**
     * Upload File with XHR Progress
     */
    function uploadWithProgress(form, url, modal, workingText) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const progressContainer = modal.querySelector('.progress-container');
        const progressBar = modal.querySelector('.progress-bar-fill');
        const progressStatus = modal.querySelector('.progress-status');
        const progressPercent = modal.querySelector('.progress-percent');

        const formData = new FormData(form);
        if (csrfToken && !formData.has('csrf_token')) {
            formData.append('csrf_token', csrfToken);
        }

        submitBtn.disabled = true;
        const originalBtnText = submitBtn.textContent;
        submitBtn.textContent = 'در حال ارسال...';

        if (progressContainer) {
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressPercent.textContent = '0%';
            progressStatus.textContent = 'در حال آپلود فایل ZIP...';
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                if (progressBar) progressBar.style.width = `${percent}%`;
                if (progressPercent) progressPercent.textContent = `${percent}%`;
                if (percent >= 100 && progressStatus) {
                    progressStatus.textContent = workingText;
                }
            }
        });

        xhr.onload = function() {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;

            let response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (err) {
                response = { success: false, error: 'پاسخ سرور نامعتبر بود: ' + xhr.responseText.substring(0, 100) };
            }

            if (xhr.status >= 200 && xhr.status < 300 && response.success) {
                showToast(response.message || 'عملیات با موفقیت انجام شد.', 'success');
                closeModal(modal);
                setTimeout(() => window.location.reload(), 900);
            } else {
                showToast(response.error || 'خطایی در پردازش رخ داد.', 'error');
                if (progressContainer) progressContainer.style.display = 'none';
            }
        };

        xhr.onerror = function() {
            submitBtn.disabled = false;
            submitBtn.textContent = originalBtnText;
            showToast('خطای شبکه در ارتباط با سرور.', 'error');
            if (progressContainer) progressContainer.style.display = 'none';
        };

        xhr.send(formData);
    }

    /**
     * Copy URL Button
     */
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const textToCopy = btn.getAttribute('data-copy');
            if (!textToCopy) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const original = btn.textContent;
                    btn.textContent = 'کپی شد!';
                    showToast('آدرس سایت با موفقیت در کلیپ‌بورد کپی شد.', 'info', 2000);
                    setTimeout(() => { btn.textContent = original; }, 1800);
                }).catch(() => {
                    fallbackCopy(textToCopy, btn);
                });
            } else {
                fallbackCopy(textToCopy, btn);
            }
        });
    });

    function fallbackCopy(text, btn) {
        const input = document.createElement('textarea');
        input.value = text;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.focus();
        input.select();
        try {
            document.execCommand('copy');
            btn.textContent = 'کپی شد!';
            showToast('آدرس کپی شد.', 'info', 2000);
            setTimeout(() => { btn.textContent = 'کپی'; }, 1800);
        } catch (err) {
            showToast('امکان کپی خودکار وجود ندارد.', 'error');
        }
        document.body.removeChild(input);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
