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
    const fmNewFileModal = document.getElementById('fmNewFileModal');
    const fmNewDirModal = document.getElementById('fmNewDirModal');
    const fmRenameModal = document.getElementById('fmRenameModal');
    const fmDeleteModal = document.getElementById('fmDeleteModal');
    const fmUploadModal = document.getElementById('fmUploadModal');
    const fmImageModal = document.getElementById('fmImageModal');

    // Forms
    const deployForm = document.getElementById('deployForm');
    const updateForm = document.getElementById('updateForm');
    const deleteForm = document.getElementById('deleteForm');
    const sqliteForm = document.getElementById('sqliteForm');
    const mysqlForm = document.getElementById('mysqlForm');
    const quotaForm = document.getElementById('quotaForm');
    const fmNewFileForm = document.getElementById('fmNewFileForm');
    const fmNewDirForm = document.getElementById('fmNewDirForm');
    const fmRenameForm = document.getElementById('fmRenameForm');
    const fmDeleteForm = document.getElementById('fmDeleteForm');
    const fmUploadForm = document.getElementById('fmUploadForm');

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
    setupDropzone('fmUploadDropzone', 'fmUploadFileInput', 'fmUploadFilename');

    /**
     * Open File Manager from Sites Table
     */
    document.querySelectorAll('[data-action="open-files"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const filesTabBtn = document.querySelector('[data-tab="tab-files"]');
            if (filesTabBtn) filesTabBtn.click();
            const projectSelect = document.getElementById('fmProjectSelect');
            if (projectSelect) {
                projectSelect.value = slug;
                loadFmDirectory(slug, '');
            }
        });
    });

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
     * Set Primary / Root Website
     */
    document.querySelectorAll('[data-action="set-primary"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            if (!confirm(`آیا مایلید سایت "${name}" به عنوان صفحه اصلی دامنه (/) تنظیم شود؟\n\nاز این پس هر کاربری آدرس اصلی سایت را باز کند، مستقیماً این پروژه را مشاهده خواهد کرد.`)) {
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'در حال تنظیم...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('slug', slug);

            fetch('./?action=set_primary', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    showToast(data.error || 'خطا در تنظیم سایت اصلی.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('خطای شبکه.', 'error');
            });
        });
    });

    /**
     * Unset Primary / Root Website
     */
    document.querySelectorAll('[data-action="unset-primary"]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('آیا مایلید این سایت را از صفحه اصلی دامنه لغو کنید و صفحه پیش‌فرض سرور فعال شود؟')) {
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'در حال لغو...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);

            fetch('./?action=unset_primary', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'info');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    showToast(data.error || 'خطا در لغو سایت اصلی.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('خطای شبکه.', 'error');
            });
        });
    });

    /**
     * 1-Click Sync from GitHub
     */
    document.querySelectorAll('[data-action="sync-github"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            if (!confirm(`آیا مایل به دریافت آخرین نسخه از گیت‌هاب و همگام‌سازی سایت "${name}" هستید؟`)) {
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '🔄 در حال دریافت...';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('slug', slug);

            fetch('./?action=sync_github', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.innerHTML = '✅ همگام شد!';
                    showToast(data.message || 'سایت با موفقیت با گیت‌هاب همگام‌سازی شد.', 'success', 2500);
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    showToast(data.error || 'خطا در همگام‌سازی با گیت‌هاب.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('خطای شبکه در همگام‌سازی با گیت‌هاب.', 'error');
            });
        });
    });

    /**
     * GitHub vs ZIP Mode Switcher in Deploy Modal
     */
    const deployTabBtns = document.querySelectorAll('.deploy-tab-btn');
    const deployZipSection = document.getElementById('deployZipSection');
    const deployGithubSection = document.getElementById('deployGithubSection');
    const deployModeInput = document.getElementById('deployModeInput');
    const deployZipInput = document.getElementById('deployZipInput');
    const deployGhUrlInput = document.getElementById('deployGhUrlInput');
    const deploySubmitBtn = document.getElementById('deploySubmitBtn');
    const siteNameInput = document.getElementById('siteNameInput');

    deployTabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-deploy-tab');
            deployTabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (deployModeInput) deployModeInput.value = mode;

            if (mode === 'github') {
                if (deployZipSection) deployZipSection.style.display = 'none';
                if (deployGithubSection) deployGithubSection.style.display = 'block';
                if (deployZipInput) deployZipInput.removeAttribute('required');
                if (deployGhUrlInput) deployGhUrlInput.setAttribute('required', 'required');
                if (deploySubmitBtn) deploySubmitBtn.textContent = '🚀 دریافت و دیپلوی از گیت‌هاب';
            } else {
                if (deployZipSection) deployZipSection.style.display = 'block';
                if (deployGithubSection) deployGithubSection.style.display = 'none';
                if (deployZipInput) deployZipInput.setAttribute('required', 'required');
                if (deployGhUrlInput) deployGhUrlInput.removeAttribute('required');
                if (deploySubmitBtn) deploySubmitBtn.textContent = 'شروع دیپلوی';
            }
        });
    });

    /**
     * Auto-detect and suggest slug & name from GitHub URL
     */
    if (deployGhUrlInput) {
        deployGhUrlInput.addEventListener('input', () => {
            const rawUrl = deployGhUrlInput.value.trim();
            const match = rawUrl.match(/(?:github\.com\/|git@github\.com:)(?:[^\/]+)\/([^\/\.]+)(?:\.git)?/i);
            if (match && match[1]) {
                const repo = match[1];
                if (slugInput && (!slugInput.value || slugInput.dataset.autofilled === '1')) {
                    const cleanSlug = repo.toLowerCase().replace(/[^a-z0-9_-]/g, '-').replace(/^[-_]+/, '');
                    slugInput.value = cleanSlug;
                    slugInput.dataset.autofilled = '1';
                    if (slugPreview) {
                        const baseHref = slugPreview.textContent.replace(/\/slug\/$/, '/');
                        slugPreview.textContent = `${baseHref}${cleanSlug}/`;
                    }
                }
                if (siteNameInput && (!siteNameInput.value || siteNameInput.dataset.autofilled === '1')) {
                    siteNameInput.value = repo;
                    siteNameInput.dataset.autofilled = '1';
                }
            }
        });
    }

    if (slugInput) {
        slugInput.addEventListener('keydown', () => {
            slugInput.dataset.autofilled = '0';
        });
    }
    if (siteNameInput) {
        siteNameInput.addEventListener('keydown', () => {
            siteNameInput.dataset.autofilled = '0';
        });
    }

    /**
     * Handle Deploy Form Submission (ZIP or GitHub)
     */
    if (deployForm) {
        deployForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const mode = deployModeInput ? deployModeInput.value : 'zip';
            if (mode === 'github') {
                deployFromGitHub();
            } else {
                uploadWithProgress(deployForm, './?action=deploy', deployModal, 'در حال بررسی امنیتی و استخراج...');
            }
        });
    }

    function deployFromGitHub() {
        const submitBtn = deploySubmitBtn || deployForm.querySelector('button[type="submit"]');
        const progressContainer = deployForm.querySelector('.progress-container');
        const progressFill = deployForm.querySelector('.progress-bar-fill');
        const progressStatus = document.getElementById('deployProgressStatus');
        const progressPercent = document.getElementById('deployProgressPercent');

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال کلون و دریافت از گیت‌هاب...';
        }

        if (progressContainer) {
            progressContainer.style.display = 'block';
            if (progressFill) progressFill.style.width = '40%';
            if (progressPercent) progressPercent.textContent = '40%';
            if (progressStatus) progressStatus.textContent = 'در حال دانلود مخزن از گیت‌هاب...';
        }

        const formData = new FormData(deployForm);
        if (csrfToken && !formData.has('csrf_token')) {
            formData.append('csrf_token', csrfToken);
        }

        fetch('./?action=deploy_github', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (progressFill) progressFill.style.width = '100%';
                if (progressPercent) progressPercent.textContent = '100%';
                if (progressStatus) progressStatus.textContent = 'دیپلوی با موفقیت کامل شد!';
                showToast(data.message || 'پروژه با موفقیت از گیت‌هاب مستقر شد.', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '🚀 دریافت و دیپلوی از گیت‌هاب';
                }
                if (progressContainer) progressContainer.style.display = 'none';
                showToast(data.error || 'خطا در دریافت و دیپلوی از گیت‌هاب.', 'error');
            }
        })
        .catch(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 دریافت و دیپلوی از گیت‌هاب';
            }
            if (progressContainer) progressContainer.style.display = 'none';
            showToast('خطای شبکه در ارتباط با سرور.', 'error');
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

    // =========================================================================
    // Service File Manager & Code Editor
    // =========================================================================
    let fmActiveSlug = null;
    let fmCurrentPath = '';
    let fmActiveFile = null;
    let fmIsDirty = false;

    const fmProjectSelect = document.getElementById('fmProjectSelect');
    const fmBreadcrumbs = document.getElementById('fmBreadcrumbs');
    const fmTableBody = document.getElementById('fmTableBody');
    const fmExplorerView = document.getElementById('fmExplorerView');
    const fmEditorView = document.getElementById('fmEditorView');
    const fmEditorFilename = document.getElementById('fmEditorFilename');
    const fmEditorSyntaxBadge = document.getElementById('fmEditorSyntaxBadge');
    const fmEditorSize = document.getElementById('fmEditorSize');
    const fmEditorDirty = document.getElementById('fmEditorDirty');
    const fmEditorTextarea = document.getElementById('fmEditorTextarea');
    const fmEditorLineNumbers = document.getElementById('fmEditorLineNumbers');
    const fmEditorSaveBtn = document.getElementById('fmEditorSaveBtn');
    const fmEditorCloseBtn = document.getElementById('fmEditorCloseBtn');
    const fmEditorStatusText = document.getElementById('fmEditorStatusText');
    const fmEditorCursorPos = document.getElementById('fmEditorCursorPos');

    // Auto-load files on tab click or project select
    document.querySelector('[data-tab="tab-files"]')?.addEventListener('click', () => {
        if (!fmActiveSlug && fmProjectSelect && fmProjectSelect.value) {
            fmActiveSlug = fmProjectSelect.value;
            loadFmDirectory(fmActiveSlug, '');
        }
    });

    if (fmProjectSelect) {
        fmProjectSelect.addEventListener('change', () => {
            fmActiveSlug = fmProjectSelect.value;
            fmCurrentPath = '';
            closeFmEditor();
            loadFmDirectory(fmActiveSlug, '');
        });
    }

    const fmRefreshBtn = document.getElementById('fmRefreshBtn');
    if (fmRefreshBtn) {
        fmRefreshBtn.addEventListener('click', () => {
            if (fmActiveSlug) loadFmDirectory(fmActiveSlug, fmCurrentPath);
        });
    }

    // Modal triggers for FM
    document.getElementById('fmNewFileBtn')?.addEventListener('click', () => {
        if (!fmActiveSlug) {
            showToast('لطفاً ابتدا یک پروژه را انتخاب نمایید.', 'warning');
            return;
        }
        openModal(fmNewFileModal);
    });

    document.getElementById('fmNewDirBtn')?.addEventListener('click', () => {
        if (!fmActiveSlug) {
            showToast('لطفاً ابتدا یک پروژه را انتخاب نمایید.', 'warning');
            return;
        }
        openModal(fmNewDirModal);
    });

    document.getElementById('fmOpenUploadModalBtn')?.addEventListener('click', () => {
        if (!fmActiveSlug) {
            showToast('لطفاً ابتدا یک پروژه را انتخاب نمایید.', 'warning');
            return;
        }
        openModal(fmUploadModal);
    });

    /**
     * Load Directory via AJAX
     */
    function loadFmDirectory(slug, path) {
        if (!slug) return;
        fmActiveSlug = slug;
        fmCurrentPath = path;

        if (fmTableBody) {
            fmTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">در حال بارگذاری فایل‌ها...</td></tr>`;
        }

        fetch(`./?action=fm_list&slug=${encodeURIComponent(slug)}&path=${encodeURIComponent(path)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderFmDirectory(data);
            } else {
                if (fmTableBody) {
                    fmTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--danger); padding: 2rem;">خطا: ${escapeHtml(data.error || 'عدم دسترسی')}</td></tr>`;
                }
            }
        })
        .catch(() => {
            if (fmTableBody) {
                fmTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--danger); padding: 2rem;">خطای شبکه در دریافت لیست فایل‌ها.</td></tr>`;
            }
        });
    }

    /**
     * Render Directory Table & Breadcrumbs
     */
    function renderFmDirectory(data) {
        // Render Breadcrumbs
        if (fmBreadcrumbs && data.breadcrumbs) {
            fmBreadcrumbs.innerHTML = '';
            data.breadcrumbs.forEach((crumb, idx) => {
                if (idx > 0) {
                    const sep = document.createElement('span');
                    sep.className = 'fm-crumb-sep';
                    sep.textContent = '/';
                    fmBreadcrumbs.appendChild(sep);
                }
                const item = document.createElement('span');
                item.className = 'fm-crumb-item' + (idx === data.breadcrumbs.length - 1 ? ' active' : '');
                item.textContent = crumb.name;
                if (idx !== data.breadcrumbs.length - 1) {
                    item.addEventListener('click', () => loadFmDirectory(data.slug, crumb.path));
                }
                fmBreadcrumbs.appendChild(item);
            });
        }

        if (!fmTableBody) return;
        fmTableBody.innerHTML = '';

        // Parent directory row (..)
        if (data.parent_path !== null && data.parent_path !== undefined) {
            const tr = document.createElement('tr');
            tr.className = 'fm-item-row';
            tr.innerHTML = `
                <td colspan="4">
                    <div class="fm-item-name" style="color: var(--accent);">
                        <span class="fm-icon">📁</span>
                        <span>.. (پوشه والد)</span>
                    </div>
                </td>
            `;
            tr.addEventListener('click', () => loadFmDirectory(data.slug, data.parent_path));
            fmTableBody.appendChild(tr);
        }

        if (data.folders.length === 0 && data.files.length === 0) {
            const emptyTr = document.createElement('tr');
            emptyTr.innerHTML = `<td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">این پوشه خالی است.</td>`;
            fmTableBody.appendChild(emptyTr);
            return;
        }

        // Render Folders
        data.folders.forEach(f => {
            const tr = document.createElement('tr');
            tr.className = 'fm-item-row';
            tr.innerHTML = `
                <td>
                    <div class="fm-item-name">
                        <span class="fm-icon">📁</span>
                        <strong style="color: var(--text-primary);">${escapeHtml(f.name)}</strong>
                    </div>
                </td>
                <td style="color: var(--text-muted); font-size: 0.8rem;">پوشه</td>
                <td style="color: var(--text-secondary); font-size: 0.8rem; direction: ltr;">${escapeHtml(f.mtime)}</td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.3rem; justify-content: center;" onclick="event.stopPropagation();">
                        <button class="copy-btn" data-fm-rename="${escapeHtml(f.path)}" data-fm-name="${escapeHtml(f.name)}" title="تغییر نام">🏷️</button>
                        <button class="copy-btn" style="color: var(--danger); border-color: rgba(239,68,68,0.3);" data-fm-delete="${escapeHtml(f.path)}" title="حذف پوشه">🗑️</button>
                    </div>
                </td>
            `;
            tr.addEventListener('click', () => loadFmDirectory(data.slug, f.path));
            fmTableBody.appendChild(tr);
        });

        // Render Files
        data.files.forEach(f => {
            const tr = document.createElement('tr');
            tr.className = 'fm-item-row';

            let icon = '📄';
            if (f.ext === 'php' || f.ext === 'phtml') icon = '⚡';
            else if (f.ext === 'html' || f.ext === 'htm') icon = '🌐';
            else if (f.ext === 'css' || f.ext === 'scss') icon = '🎨';
            else if (f.ext === 'js' || f.ext === 'ts') icon = '📜';
            else if (f.ext === 'json') icon = '📦';
            else if (f.is_image) icon = '🖼️';

            tr.innerHTML = `
                <td>
                    <div class="fm-item-name">
                        <span class="fm-icon">${icon}</span>
                        <span>${escapeHtml(f.name)}</span>
                    </div>
                </td>
                <td style="color: var(--text-secondary); font-size: 0.82rem; direction: ltr;">${escapeHtml(f.formatted_size)}</td>
                <td style="color: var(--text-secondary); font-size: 0.8rem; direction: ltr;">${escapeHtml(f.mtime)}</td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.3rem; justify-content: center;" onclick="event.stopPropagation();">
                        ${f.is_text ? `<button class="copy-btn" data-fm-edit="${escapeHtml(f.path)}" title="ویرایش کد">✏️ ویرایش</button>` : ''}
                        ${f.is_image ? `<button class="copy-btn" data-fm-image="${escapeHtml(f.path)}" data-fm-name="${escapeHtml(f.name)}" title="پیش‌نمایش تصویر">👁️ نمایش</button>` : ''}
                        <button class="copy-btn" data-fm-rename="${escapeHtml(f.path)}" data-fm-name="${escapeHtml(f.name)}" title="تغییر نام">🏷️</button>
                        <button class="copy-btn" style="color: var(--danger); border-color: rgba(239,68,68,0.3);" data-fm-delete="${escapeHtml(f.path)}" title="حذف">🗑️</button>
                    </div>
                </td>
            `;

            tr.addEventListener('click', () => {
                if (f.is_text) {
                    openFmEditor(data.slug, f.path);
                } else if (f.is_image) {
                    previewFmImage(data.slug, f.path, f.name);
                }
            });

            fmTableBody.appendChild(tr);
        });

        // Wire Action Buttons inside table
        fmTableBody.querySelectorAll('[data-fm-edit]').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                openFmEditor(data.slug, b.getAttribute('data-fm-edit'));
            });
        });

        fmTableBody.querySelectorAll('[data-fm-image]').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                previewFmImage(data.slug, b.getAttribute('data-fm-image'), b.getAttribute('data-fm-name'));
            });
        });

        fmTableBody.querySelectorAll('[data-fm-rename]').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const path = b.getAttribute('data-fm-rename');
                const name = b.getAttribute('data-fm-name');
                document.getElementById('fmRenameOldPathInput').value = path;
                document.getElementById('fmRenameNewNameInput').value = name;
                openModal(fmRenameModal);
            });
        });

        fmTableBody.querySelectorAll('[data-fm-delete]').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const path = b.getAttribute('data-fm-delete');
                document.getElementById('fmDeletePathInput').value = path;
                document.getElementById('fmDeleteTargetDisplay').textContent = `${data.slug}/${path}`;
                openModal(fmDeleteModal);
            });
        });
    }

    /**
     * Preview Image in Modal
     */
    function previewFmImage(slug, filePath, filename) {
        const img = document.getElementById('fmImagePreview');
        const title = document.getElementById('fmImageTitle');
        const dl = document.getElementById('fmImageDownloadBtn');
        const url = `/${slug}/${filePath}`;
        if (img) img.src = url;
        if (title) title.textContent = `پیش‌نمایش: ${filename}`;
        if (dl) dl.href = url;
        openModal(fmImageModal);
    }

    /**
     * Open Code Editor
     */
    function openFmEditor(slug, filePath) {
        fmActiveSlug = slug;
        fmActiveFile = filePath;
        fmIsDirty = false;

        fetch(`./?action=fm_read&slug=${encodeURIComponent(slug)}&file=${encodeURIComponent(filePath)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast(data.error || 'خطا در باز کردن فایل.', 'error');
                return;
            }

            fmExplorerView.style.display = 'none';
            fmEditorView.style.display = 'block';

            if (fmEditorFilename) fmEditorFilename.textContent = data.filename;
            if (fmEditorSyntaxBadge) fmEditorSyntaxBadge.textContent = (data.syntax || 'TEXT').toUpperCase();
            if (fmEditorSize) fmEditorSize.textContent = data.formatted_size;
            if (fmEditorDirty) fmEditorDirty.style.display = 'none';
            if (fmEditorStatusText) fmEditorStatusText.textContent = `ویرایش ${data.filename} (${data.mtime})`;

            if (fmEditorTextarea) {
                fmEditorTextarea.value = data.content || '';
                updateLineNumbers();
                fmEditorTextarea.focus();
            }
        })
        .catch(() => {
            showToast('خطای شبکه در دریافت محتوای فایل.', 'error');
        });
    }

    /**
     * Close Code Editor
     */
    function closeFmEditor() {
        if (fmIsDirty && !confirm('تغییرات ذخیره‌نشده دارید. آیا از خروج مطمئن هستید؟')) {
            return;
        }
        fmActiveFile = null;
        fmIsDirty = false;
        if (fmEditorView) fmEditorView.style.display = 'none';
        if (fmExplorerView) fmExplorerView.style.display = 'block';
        if (fmActiveSlug) loadFmDirectory(fmActiveSlug, fmCurrentPath);
    }

    if (fmEditorCloseBtn) {
        fmEditorCloseBtn.addEventListener('click', closeFmEditor);
    }

    /**
     * Save File in Editor
     */
    function saveFmEditor() {
        if (!fmActiveSlug || !fmActiveFile) return;

        const content = fmEditorTextarea ? fmEditorTextarea.value : '';
        const saveBtn = fmEditorSaveBtn;
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'در حال ذخیره...';
        }

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('slug', fmActiveSlug);
        formData.append('file', fmActiveFile);
        formData.append('content', content);

        fetch('./?action=fm_save', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 ذخیره فایل (Ctrl+S)';
            }
            if (data.success) {
                fmIsDirty = false;
                if (fmEditorDirty) fmEditorDirty.style.display = 'none';
                if (fmEditorSize) fmEditorSize.textContent = data.formatted_size;
                if (fmEditorStatusText) fmEditorStatusText.textContent = `ذخیره شد در ${data.mtime}`;
                showToast(data.message, 'success', 2000);
            } else {
                showToast(data.error || 'خطا در ذخیره فایل.', 'error');
            }
        })
        .catch(() => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 ذخیره فایل (Ctrl+S)';
            }
            showToast('خطای شبکه در ذخیره‌سازی.', 'error');
        });
    }

    if (fmEditorSaveBtn) {
        fmEditorSaveBtn.addEventListener('click', saveFmEditor);
    }

    // Line Numbers & Tab Key in Editor
    if (fmEditorTextarea) {
        fmEditorTextarea.addEventListener('input', () => {
            fmIsDirty = true;
            if (fmEditorDirty) fmEditorDirty.style.display = 'inline';
            updateLineNumbers();
            updateCursorPos();
        });

        fmEditorTextarea.addEventListener('click', updateCursorPos);
        fmEditorTextarea.addEventListener('keyup', updateCursorPos);

        fmEditorTextarea.addEventListener('scroll', () => {
            if (fmEditorLineNumbers) {
                fmEditorLineNumbers.scrollTop = fmEditorTextarea.scrollTop;
            }
        });

        // Tab Indentation (4 spaces)
        fmEditorTextarea.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = fmEditorTextarea.selectionStart;
                const end = fmEditorTextarea.selectionEnd;
                fmEditorTextarea.value = fmEditorTextarea.value.substring(0, start) + '    ' + fmEditorTextarea.value.substring(end);
                fmEditorTextarea.selectionStart = fmEditorTextarea.selectionEnd = start + 4;
                fmIsDirty = true;
                if (fmEditorDirty) fmEditorDirty.style.display = 'inline';
                updateLineNumbers();
            }

            // Ctrl+S / Cmd+S Shortcut
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                saveFmEditor();
            }
        });
    }

    function updateLineNumbers() {
        if (!fmEditorTextarea || !fmEditorLineNumbers) return;
        const lineCount = fmEditorTextarea.value.split('\n').length;
        let nums = '';
        for (let i = 1; i <= lineCount; i++) {
            nums += i + '\n';
        }
        fmEditorLineNumbers.textContent = nums;
    }

    function updateCursorPos() {
        if (!fmEditorTextarea || !fmEditorCursorPos) return;
        const pos = fmEditorTextarea.selectionStart;
        const lines = fmEditorTextarea.value.substring(0, pos).split('\n');
        const ln = lines.length;
        const col = lines[lines.length - 1].length + 1;
        fmEditorCursorPos.textContent = `Ln ${ln}, Col ${col}`;
    }

    // Modal Form Submissions
    if (fmNewFileForm) {
        fmNewFileForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const filename = document.getElementById('fmNewFileNameInput').value.trim();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('slug', fmActiveSlug);
            formData.append('path', fmCurrentPath);
            formData.append('filename', filename);

            fetch('./?action=fm_create_file', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(fmNewFileModal);
                    loadFmDirectory(fmActiveSlug, fmCurrentPath);
                } else {
                    showToast(data.error || 'خطا در ایجاد فایل.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    }

    if (fmNewDirForm) {
        fmNewDirForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const dirname = document.getElementById('fmNewDirNameInput').value.trim();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('slug', fmActiveSlug);
            formData.append('path', fmCurrentPath);
            formData.append('dirname', dirname);

            fetch('./?action=fm_create_dir', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(fmNewDirModal);
                    loadFmDirectory(fmActiveSlug, fmCurrentPath);
                } else {
                    showToast(data.error || 'خطا در ایجاد پوشه.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    }

    if (fmRenameForm) {
        fmRenameForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const oldPath = document.getElementById('fmRenameOldPathInput').value;
            const newName = document.getElementById('fmRenameNewNameInput').value.trim();
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('slug', fmActiveSlug);
            formData.append('old_path', oldPath);
            formData.append('new_name', newName);

            fetch('./?action=fm_rename', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(fmRenameModal);
                    loadFmDirectory(fmActiveSlug, fmCurrentPath);
                } else {
                    showToast(data.error || 'خطا در تغییر نام.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    }

    if (fmDeleteForm) {
        fmDeleteForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const itemPath = document.getElementById('fmDeletePathInput').value;
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('slug', fmActiveSlug);
            formData.append('item_path', itemPath);

            fetch('./?action=fm_delete', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(fmDeleteModal);
                    loadFmDirectory(fmActiveSlug, fmCurrentPath);
                } else {
                    showToast(data.error || 'خطا در حذف.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    }

    if (fmUploadForm) {
        fmUploadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = fmUploadForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال آپلود...';

            const formData = new FormData(fmUploadForm);
            formData.append('csrf_token', csrfToken);
            formData.append('slug', fmActiveSlug);
            formData.append('path', fmCurrentPath);

            fetch('./?action=fm_upload', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'آپلود';
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(fmUploadModal);
                    loadFmDirectory(fmActiveSlug, fmCurrentPath);
                } else {
                    showToast(data.error || 'خطا در آپلود.', 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'آپلود';
                showToast('خطای شبکه.', 'error');
            });
        });
    }

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
