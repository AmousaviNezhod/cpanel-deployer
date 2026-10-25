/**
 * cPanel Deployer - Client Application Controller
 * High-performance vanilla JS with guided stepper, live stage terminal, and interactive studio
 */

document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // Global state & CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const baseUrl = window.location.origin;

    // Toast Notification System
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }

    const showToast = (message, type = 'info', duration = 3500) => {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        toast.innerHTML = `<span style="font-weight: 700;">${icons[type] || 'ℹ'}</span><div style="flex-grow: 1;">${escapeHtml(message)}</div>`;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(8px)';
            toast.style.transition = 'all 0.2s ease';
            setTimeout(() => toast.remove(), 200);
        }, duration);
    };

    // Modal Helpers
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
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

    // Navigation Tabs
    const tabButtons = document.querySelectorAll('.nav-tabs .tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetTab = btn.getAttribute('data-tab');
            tabButtons.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));

            btn.classList.add('active');
            const activePane = document.getElementById(targetTab);
            if (activePane) {
                activePane.classList.add('active');
            }
        });
    });

    // =========================================================================
    // Toolbar Live Search & Type Filters
    // =========================================================================
    const siteSearchInput = document.getElementById('siteSearchInput');
    const filterButtons = document.querySelectorAll('.toolbar .mode-toggle-btn');
    let currentFilter = 'all';

    function applySiteFilters() {
        const query = siteSearchInput ? siteSearchInput.value.trim().toLowerCase() : '';
        const rows = document.querySelectorAll('tr[data-site-row]');

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const slug = row.getAttribute('data-slug') || '';
            const type = row.getAttribute('data-type') || '';

            const matchesQuery = !query || name.includes(query) || slug.includes(query);
            const matchesFilter = (currentFilter === 'all') || (type === currentFilter);

            row.style.display = (matchesQuery && matchesFilter) ? '' : 'none';
        });
    }

    if (siteSearchInput) {
        siteSearchInput.addEventListener('input', applySiteFilters);
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter') || 'all';
            applySiteFilters();
        });
    });

    // =========================================================================
    // Guided Deployment Wizard
    // =========================================================================
    const deployModal = document.getElementById('deployModal');
    const deployForm = document.getElementById('deployForm');
    const openDeployBtns = [
        document.getElementById('openNewDeployBtn'),
        document.getElementById('toolbarDeployBtn'),
        document.getElementById('emptyStateDeployBtn')
    ].filter(Boolean);

    let currentWizardStep = 1;
    const wizardPanes = document.querySelectorAll('.wizard-pane');
    const wizardSteps = document.querySelectorAll('#deployStepper .wizard-step');
    const wizardPrevBtn = document.getElementById('wizardPrevBtn');
    const wizardNextBtn = document.getElementById('wizardNextBtn');
    const wizardSubmitBtn = document.getElementById('wizardSubmitBtn');
    const wizardCancelBtn = document.getElementById('wizardCancelBtn');
    const deployFormBody = document.getElementById('deployFormBody');
    const deployModalFooter = document.getElementById('deployModalFooter');
    const deployProgressView = document.getElementById('deployProgressView');

    const deployModeInput = document.getElementById('deployModeInput');
    const wizardZipSource = document.getElementById('wizardZipSource');
    const wizardGithubSource = document.getElementById('wizardGithubSource');
    const deployZipInput = document.getElementById('deployZipInput');
    const deployGhUrlInput = document.getElementById('deployGhUrlInput');
    const siteNameInput = document.getElementById('siteNameInput');
    const siteSlugInput = document.getElementById('siteSlugInput');
    const slugPreview = document.getElementById('slugUrlPreview');

    openDeployBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            resetDeployWizard();
            openModal(deployModal);
        });
    });

    function resetDeployWizard() {
        currentWizardStep = 1;
        setWizardStep(1);
        if (deployForm) deployForm.reset();
        if (deployFormBody) deployFormBody.style.display = 'block';
        if (deployModalFooter) deployModalFooter.style.display = 'flex';
        if (deployProgressView) deployProgressView.style.display = 'none';

        const filenameBox = document.getElementById('deployFilename');
        if (filenameBox) {
            filenameBox.style.display = 'none';
            filenameBox.textContent = '';
        }

        // Default to ZIP source
        setDeploySource('zip');
    }

    function setDeploySource(source) {
        if (deployModeInput) deployModeInput.value = source;
        document.querySelectorAll('.deploy-source-tab-btn').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-deploy-source') === source);
        });

        if (source === 'github') {
            if (wizardZipSource) wizardZipSource.style.display = 'none';
            if (wizardGithubSource) wizardGithubSource.style.display = 'block';
            if (deployZipInput) deployZipInput.removeAttribute('required');
            if (deployGhUrlInput) deployGhUrlInput.setAttribute('required', 'required');
        } else {
            if (wizardZipSource) wizardZipSource.style.display = 'block';
            if (wizardGithubSource) wizardGithubSource.style.display = 'none';
            if (deployZipInput) deployZipInput.setAttribute('required', 'required');
            if (deployGhUrlInput) deployGhUrlInput.removeAttribute('required');
        }
    }

    document.querySelectorAll('.deploy-source-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const source = btn.getAttribute('data-deploy-source') || 'zip';
            setDeploySource(source);
        });
    });

    function setWizardStep(step) {
        currentWizardStep = step;
        wizardPanes.forEach(pane => {
            pane.classList.toggle('active', parseInt(pane.getAttribute('data-pane'), 10) === step);
        });

        wizardSteps.forEach(s => {
            const sNum = parseInt(s.getAttribute('data-step'), 10);
            s.classList.toggle('active', sNum === step);
            s.classList.toggle('completed', sNum < step);
        });

        if (step === 1) {
            if (wizardPrevBtn) wizardPrevBtn.style.display = 'none';
            if (wizardNextBtn) {
                wizardNextBtn.style.display = 'inline-flex';
                wizardNextBtn.textContent = 'مرحله بعد: مشخصات پروژه →';
            }
            if (wizardSubmitBtn) wizardSubmitBtn.style.display = 'none';
        } else if (step === 2) {
            if (wizardPrevBtn) wizardPrevBtn.style.display = 'inline-flex';
            if (wizardNextBtn) {
                wizardNextBtn.style.display = 'inline-flex';
                wizardNextBtn.textContent = 'مرحله بعد: بازبینی و تایید →';
            }
            if (wizardSubmitBtn) wizardSubmitBtn.style.display = 'none';
        } else if (step === 3) {
            if (wizardPrevBtn) wizardPrevBtn.style.display = 'inline-flex';
            if (wizardNextBtn) wizardNextBtn.style.display = 'none';
            if (wizardSubmitBtn) wizardSubmitBtn.style.display = 'inline-flex';

            // Populate Review Summary
            const mode = deployModeInput ? deployModeInput.value : 'zip';
            const sName = siteNameInput ? siteNameInput.value.trim() : '-';
            const sSlug = siteSlugInput ? siteSlugInput.value.trim() : '-';

            document.getElementById('summaryProjectName').textContent = sName || sSlug;
            document.getElementById('summarySource').textContent = (mode === 'github')
                ? (deployGhUrlInput ? deployGhUrlInput.value.trim() : 'GitHub')
                : (deployZipInput && deployZipInput.files[0] ? deployZipInput.files[0].name : 'ZIP Package');
            document.getElementById('summaryUrl').textContent = `/${sSlug}/`;
        }
    }

    if (wizardNextBtn) {
        wizardNextBtn.addEventListener('click', () => {
            if (currentWizardStep === 1) {
                const mode = deployModeInput ? deployModeInput.value : 'zip';
                if (mode === 'zip') {
                    if (!deployZipInput || !deployZipInput.files.length) {
                        showToast('لطفاً ابتدا فایل ZIP پروژه را انتخاب نمایید.', 'warning');
                        return;
                    }
                    // Auto-detect project name and slug from zip name if empty
                    const zipName = deployZipInput.files[0].name.replace(/\.zip$/i, '');
                    autoFillProjectMetadata(zipName);
                } else {
                    if (!deployGhUrlInput || !deployGhUrlInput.value.trim()) {
                        showToast('لطفاً آدرس مخزن گیت‌هاب را وارد نمایید.', 'warning');
                        return;
                    }
                    // Auto-detect from GitHub URL
                    const ghMatch = deployGhUrlInput.value.trim().match(/(?:github\.com\/|git@github\.com:)(?:[^\/]+)\/([^\/\.]+)(?:\.git)?/i);
                    if (ghMatch && ghMatch[1]) {
                        autoFillProjectMetadata(ghMatch[1]);
                    }
                }
                setWizardStep(2);
            } else if (currentWizardStep === 2) {
                const sName = siteNameInput ? siteNameInput.value.trim() : '';
                const sSlug = siteSlugInput ? siteSlugInput.value.trim().toLowerCase() : '';

                if (!sName) {
                    showToast('لطفاً نام نمایشی پروژه را وارد نمایید.', 'warning');
                    siteNameInput?.focus();
                    return;
                }
                if (!sSlug || !/^[a-z0-9][a-z0-9_-]*$/.test(sSlug)) {
                    showToast('شناسه مسیر (Slug) نامعتبر است. فقط حروف انگلیسی کوچک، اعداد و خط تیره مجاز است.', 'error');
                    siteSlugInput?.focus();
                    return;
                }
                setWizardStep(3);
            }
        });
    }

    if (wizardPrevBtn) {
        wizardPrevBtn.addEventListener('click', () => {
            if (currentWizardStep > 1) {
                setWizardStep(currentWizardStep - 1);
            }
        });
    }

    function autoFillProjectMetadata(rawIdentifier) {
        const cleanSlug = rawIdentifier.toLowerCase().replace(/[^a-z0-9_-]/g, '-').replace(/^[-_]+/, '');
        if (siteSlugInput && (!siteSlugInput.value || siteSlugInput.dataset.autofilled === '1')) {
            siteSlugInput.value = cleanSlug;
            siteSlugInput.dataset.autofilled = '1';
            updateSlugPreview(cleanSlug);
        }
        if (siteNameInput && (!siteNameInput.value || siteNameInput.dataset.autofilled === '1')) {
            siteNameInput.value = rawIdentifier.replace(/[-_]+/g, ' ');
            siteNameInput.dataset.autofilled = '1';
        }
    }

    function updateSlugPreview(val) {
        if (!slugPreview) return;
        const root = slugPreview.textContent.replace(/\/slug\/$/, '/');
        slugPreview.textContent = `${root}${val || 'slug'}/`;
    }

    if (siteSlugInput) {
        siteSlugInput.addEventListener('input', () => {
            let val = siteSlugInput.value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
            if (val.startsWith('-') || val.startsWith('_')) val = val.substring(1);
            siteSlugInput.value = val;
            siteSlugInput.dataset.autofilled = '0';
            updateSlugPreview(val);
        });
    }

    if (siteNameInput) {
        siteNameInput.addEventListener('keydown', () => {
            siteNameInput.dataset.autofilled = '0';
        });
    }

    // Dropzone Helper
    function setupDropzone(dropzoneId, inputId, filenameBoxId) {
        const dropzone = document.getElementById(dropzoneId);
        const input = document.getElementById(inputId);
        const filenameBox = document.getElementById(filenameBoxId);

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
            const files = e.dataTransfer.files;
            if (files.length) {
                input.files = files;
                updateFilenameDisplay(files[0]);
            }
        });

        input.addEventListener('change', () => {
            if (input.files.length) {
                updateFilenameDisplay(input.files[0]);
            }
        });

        function updateFilenameDisplay(file) {
            if (!filenameBox) return;
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            filenameBox.style.display = 'flex';
            filenameBox.innerHTML = `<span>📄 <strong>${escapeHtml(file.name)}</strong> (${sizeMb} MB)</span><span style="color: var(--success);">آماده ارسال ✓</span>`;
        }
    }

    setupDropzone('deployDropzone', 'deployZipInput', 'deployFilename');
    setupDropzone('updateDropzone', 'updateZipInput', 'updateFilename');
    setupDropzone('fmUploadDropzone', 'fmUploadFileInput', 'fmUploadFilename');

    // =========================================================================
    // Execution Engine: Deploy with Real Stages & Live Terminal
    // =========================================================================
    const stageUpload = document.getElementById('stageUpload');
    const stageVerify = document.getElementById('stageVerify');
    const stageExtract = document.getElementById('stageExtract');
    const stageRoute = document.getElementById('stageRoute');
    const stageComplete = document.getElementById('stageComplete');
    const deployTerminalOutput = document.getElementById('deployTerminalOutput');
    const terminalStatusIndicator = document.getElementById('terminalStatusIndicator');
    const deploySuccessActions = document.getElementById('deploySuccessActions');
    const deployErrorActions = document.getElementById('deployErrorActions');
    const deployErrorMessageText = document.getElementById('deployErrorMessageText');
    const deploySuccessOpenLink = document.getElementById('deploySuccessOpenLink');
    const deploySuccessFilesBtn = document.getElementById('deploySuccessFilesBtn');

    function logTerminal(msg) {
        if (!deployTerminalOutput) return;
        const time = new Date().toLocaleTimeString();
        deployTerminalOutput.textContent += `\n[${time}] ${msg}`;
        deployTerminalOutput.scrollTop = deployTerminalOutput.scrollHeight;
    }

    function setStageState(el, state) {
        if (!el) return;
        el.classList.remove('running', 'completed', 'failed');
        const dot = el.querySelector('.stage-dot');

        if (state === 'running') {
            el.classList.add('running');
            if (dot) dot.innerHTML = `<span class="stage-icon-spinner"></span>`;
        } else if (state === 'completed') {
            el.classList.add('completed');
            if (dot) dot.textContent = '✓';
        } else if (state === 'failed') {
            el.classList.add('failed');
            if (dot) dot.textContent = '✕';
        }
    }

    if (deployForm) {
        deployForm.addEventListener('submit', (e) => {
            e.preventDefault();

            // Transition UI into Live Progress View
            if (deployFormBody) deployFormBody.style.display = 'none';
            if (deployModalFooter) deployModalFooter.style.display = 'none';
            if (deployProgressView) deployProgressView.style.display = 'block';
            if (deploySuccessActions) deploySuccessActions.style.display = 'none';
            if (deployErrorActions) deployErrorActions.style.display = 'none';

            if (deployTerminalOutput) deployTerminalOutput.textContent = '> آماده‌سازی پکیج استقرار...';
            if (terminalStatusIndicator) terminalStatusIndicator.textContent = 'در حال اجرا';

            const mode = deployModeInput ? deployModeInput.value : 'zip';
            const actionUrl = (mode === 'github') ? './?action=deploy_github' : './?action=deploy';
            const slug = siteSlugInput ? siteSlugInput.value.trim().toLowerCase() : '';

            // Stage 1: Uploading / Fetching
            setStageState(stageUpload, 'running');
            logTerminal(mode === 'github' ? 'ارسال درخواست کلون و دریافت مخزن از GitHub...' : 'ارسال فایل ZIP به سرور cPanel...');

            const formData = new FormData(deployForm);
            if (csrfToken && !formData.has('csrf_token')) {
                formData.append('csrf_token', csrfToken);
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', actionUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.upload.onprogress = (evt) => {
                if (evt.lengthComputable && mode === 'zip') {
                    const pct = Math.round((evt.loaded / evt.total) * 100);
                    logTerminal(`ارسال پکیج: ${pct}% (${(evt.loaded / 1024 / 1024).toFixed(2)} MB)`);
                    if (pct >= 100) {
                        setStageState(stageUpload, 'completed');
                        setStageState(stageVerify, 'running');
                        logTerminal('فایل در هاست ذخیره شد. بررسی هدر ZIP و بازرسی ضد Zip Slip...');
                    }
                }
            };

            xhr.onload = () => {
                let data = null;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (err) {
                    data = { success: false, error: 'پاسخ نامعتبر از سرور: ' + xhr.responseText.substring(0, 120) };
                }

                if (xhr.status === 200 && data.success) {
                    setStageState(stageUpload, 'completed');
                    setStageState(stageVerify, 'completed');
                    setStageState(stageExtract, 'completed');
                    setStageState(stageRoute, 'completed');
                    setStageState(stageComplete, 'completed');

                    logTerminal('استخراج فایل‌ها با موفقیت پایان یافت.');
                    logTerminal('پیکربندی RewriteBase و تزریق base href کامل شد.');
                    logTerminal('سرویس اکنون در آدرس live فعال است: /' + slug + '/');

                    if (terminalStatusIndicator) terminalStatusIndicator.textContent = 'کامل شد ✓';
                    if (deploySuccessActions) deploySuccessActions.style.display = 'block';

                    if (deploySuccessOpenLink) {
                        deploySuccessOpenLink.href = `${baseUrl}/${slug}/`;
                    }
                    if (deploySuccessFilesBtn) {
                        deploySuccessFilesBtn.onclick = () => {
                            closeModal(deployModal);
                            const filesTabBtn = document.querySelector('[data-tab="tab-files"]');
                            if (filesTabBtn) filesTabBtn.click();
                            const pSelect = document.getElementById('fmProjectSelect');
                            if (pSelect) {
                                pSelect.value = slug;
                                pSelect.dispatchEvent(new Event('change'));
                            }
                        };
                    }

                    showToast(data.message || 'پروژه با موفقیت مستقر شد.', 'success');
                } else {
                    setStageState(stageVerify, 'failed');
                    setStageState(stageExtract, 'failed');
                    logTerminal('خطا در دیپلوی: ' + (data.error || 'خطای سرور'));

                    if (terminalStatusIndicator) terminalStatusIndicator.textContent = 'خطا ✕';
                    if (deployErrorActions) deployErrorActions.style.display = 'block';
                    if (deployErrorMessageText) deployErrorMessageText.textContent = data.error || 'خطا در انجام فرایند استقرار.';

                    showToast(data.error || 'خطا در فرایند استقرار.', 'error');
                }
            };

            xhr.onerror = () => {
                setStageState(stageUpload, 'failed');
                logTerminal('خطای شبکه در ارتباط با هاست cPanel.');
                if (terminalStatusIndicator) terminalStatusIndicator.textContent = 'خطای اتصال';
                if (deployErrorActions) deployErrorActions.style.display = 'block';
                if (deployErrorMessageText) deployErrorMessageText.textContent = 'ارتباط با سرور برقرار نشد. لطفاً وضعیت هاست را بررسی نمایید.';
            };

            xhr.send(formData);
        });
    }

    // Recover from error without losing inputs
    const deployRetryBtn = document.getElementById('deployRetryBtn');
    if (deployRetryBtn) {
        deployRetryBtn.addEventListener('click', () => {
            if (deployFormBody) deployFormBody.style.display = 'block';
            if (deployModalFooter) deployModalFooter.style.display = 'flex';
            if (deployProgressView) deployProgressView.style.display = 'none';
            setWizardStep(2);
        });
    }

    // =========================================================================
    // Project Actions: Root Domain, GitHub Sync, Delete, Update
    // =========================================================================

    // Set Root Domain
    document.querySelectorAll('[data-action="set-primary"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            if (!confirm(`آیا مایلید سایت "${name}" به عنوان صفحه اصلی دامنه (/) تنظیم شود؟\n\nاز این پس هر بازدیدکننده‌ای آدرس اصلی سایت را باز کند، مستقیماً این پروژه را مشاهده خواهد کرد.`)) {
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.textContent = 'در حال تنظیم...';

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
                    showToast(data.error || 'خطا در تنظیم ریشه دامنه.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('خطای شبکه.', 'error');
            });
        });
    });

    // Unset Root Domain
    document.querySelectorAll('[data-action="unset-primary"]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('آیا مایلید این سایت را از ریشه دامنه لغو کنید تا صفحه پیش‌فرض هاست فعال شود؟')) {
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.textContent = 'در حال لغو...';

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
                    showToast(data.error || 'خطا در لغو ریشه دامنه.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('خطای شبکه.', 'error');
            });
        });
    });

    // 1-Click Sync GitHub
    document.querySelectorAll('[data-action="sync-github"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            if (!confirm(`آیا مایلید آخرین نسخه از مخزن گیت‌هاب برای پروژه "${name}" دریافت و مستقر شود؟`)) {
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
                    btn.innerHTML = '✓ همگام شد!';
                    showToast(data.message || 'سایت با موفقیت همگام‌سازی شد.', 'success', 2500);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    showToast(data.error || 'خطا در همگام‌سازی با گیت‌هاب.', 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                showToast('خطای شبکه در همگام‌سازی.', 'error');
            });
        });
    });

    // Open Update Modal
    const updateModal = document.getElementById('updateModal');
    const updateForm = document.getElementById('updateForm');
    document.querySelectorAll('[data-action="open-update"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            const fixRoutes = btn.getAttribute('data-fix-routes') === '1';
            document.getElementById('updateSlugInput').value = slug;
            document.getElementById('updateSiteNameDisplay').textContent = `${name} (/${slug}/)`;
            const fixRoutesCb = document.getElementById('updateFixRoutesCheckbox');
            if (fixRoutesCb) fixRoutesCb.checked = fixRoutes;
            openModal(updateModal);
        });
    });

    if (updateForm) {
        updateForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = updateForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال به‌روزرسانی...';

            const formData = new FormData(updateForm);
            if (csrfToken && !formData.has('csrf_token')) {
                formData.append('csrf_token', csrfToken);
            }

            fetch('./?action=update', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'شروع به‌روزرسانی';
                if (data.success) {
                    showToast(data.message || 'پروژه به‌روزرسانی شد.', 'success');
                    closeModal(updateModal);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || 'خطا در به‌روزرسانی.', 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'شروع به‌روزرسانی';
                showToast('خطای شبکه.', 'error');
            });
        });
    }

    // Open Delete Modal
    const deleteModal = document.getElementById('deleteModal');
    const deleteForm = document.getElementById('deleteForm');
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
                    showToast(data.message || 'پروژه حذف شد.', 'success');
                    closeModal(deleteModal);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || 'خطا در حذف پروژه.', 'error');
                }
            })
            .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'بله، حذف کن';
                showToast('خطای شبکه.', 'error');
            });
        });
    }

    // =========================================================================
    // File Manager & In-Browser Code Studio
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

    // Modals
    const fmNewFileModal = document.getElementById('fmNewFileModal');
    const fmNewDirModal = document.getElementById('fmNewDirModal');
    const fmRenameModal = document.getElementById('fmRenameModal');
    const fmDeleteModal = document.getElementById('fmDeleteModal');
    const fmUploadModal = document.getElementById('fmUploadModal');
    const fmImageModal = document.getElementById('fmImageModal');

    // Forms
    const fmNewFileForm = document.getElementById('fmNewFileForm');
    const fmNewDirForm = document.getElementById('fmNewDirForm');
    const fmRenameForm = document.getElementById('fmRenameForm');
    const fmDeleteForm = document.getElementById('fmDeleteForm');
    const fmUploadForm = document.getElementById('fmUploadForm');

    // Open File Manager from Sites Table
    document.querySelectorAll('[data-action="open-files"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const filesTabBtn = document.querySelector('[data-tab="tab-files"]');
            if (filesTabBtn) filesTabBtn.click();
            if (fmProjectSelect) {
                fmProjectSelect.value = slug;
                loadFmDirectory(slug, '');
            }
        });
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

    function renderFmDirectory(data) {
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
            emptyTr.innerHTML = `<td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">این پوشه خالی است.</td>`;
            fmTableBody.appendChild(emptyTr);
            return;
        }

        // Folders
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
                <td style="color: var(--text-secondary); font-size: 0.8rem; direction: ltr; font-family: var(--font-mono);">${escapeHtml(f.mtime)}</td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.3rem; justify-content: center;" onclick="event.stopPropagation();">
                        <button class="btn btn-ghost btn-sm" data-fm-rename="${escapeHtml(f.path)}" data-fm-name="${escapeHtml(f.name)}" title="تغییر نام">🏷️</button>
                        <button class="btn btn-ghost btn-sm" style="color: var(--danger);" data-fm-delete="${escapeHtml(f.path)}" title="حذف پوشه">✕</button>
                    </div>
                </td>
            `;
            tr.addEventListener('click', () => loadFmDirectory(data.slug, f.path));
            fmTableBody.appendChild(tr);
        });

        // Files
        data.files.forEach(f => {
            const tr = document.createElement('tr');
            tr.className = 'fm-item-row';

            let icon = '📄';
            if (['php', 'phtml'].includes(f.ext)) icon = '⚡';
            else if (['html', 'htm'].includes(f.ext)) icon = '🌐';
            else if (['css', 'scss'].includes(f.ext)) icon = '🎨';
            else if (['js', 'ts'].includes(f.ext)) icon = '📜';
            else if (f.ext === 'json') icon = '📦';
            else if (f.is_image) icon = '🖼️';

            tr.innerHTML = `
                <td>
                    <div class="fm-item-name">
                        <span class="fm-icon">${icon}</span>
                        <span>${escapeHtml(f.name)}</span>
                    </div>
                </td>
                <td style="color: var(--text-secondary); font-size: 0.82rem; direction: ltr; font-family: var(--font-mono);">${escapeHtml(f.formatted_size)}</td>
                <td style="color: var(--text-secondary); font-size: 0.8rem; direction: ltr; font-family: var(--font-mono);">${escapeHtml(f.mtime)}</td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.3rem; justify-content: center;" onclick="event.stopPropagation();">
                        ${f.is_text ? `<button class="btn btn-secondary btn-sm" data-fm-edit="${escapeHtml(f.path)}" title="ویرایش کد">ویرایش</button>` : ''}
                        ${f.is_image ? `<button class="btn btn-secondary btn-sm" data-fm-image="${escapeHtml(f.path)}" data-fm-name="${escapeHtml(f.name)}" title="نمایش تصویر">نمایش</button>` : ''}
                        <button class="btn btn-ghost btn-sm" data-fm-rename="${escapeHtml(f.path)}" data-fm-name="${escapeHtml(f.name)}" title="تغییر نام">🏷️</button>
                        <button class="btn btn-ghost btn-sm" style="color: var(--danger);" data-fm-delete="${escapeHtml(f.path)}" title="حذف">✕</button>
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

        // Wire Action Buttons
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

            if (fmExplorerView) fmExplorerView.style.display = 'none';
            if (fmEditorView) fmEditorView.style.display = 'block';

            if (fmEditorFilename) fmEditorFilename.textContent = data.filename;
            if (fmEditorSyntaxBadge) fmEditorSyntaxBadge.textContent = (data.syntax || 'TEXT').toUpperCase();
            if (fmEditorSize) fmEditorSize.textContent = data.formatted_size;
            if (fmEditorDirty) fmEditorDirty.style.display = 'none';
            if (fmEditorStatusText) fmEditorStatusText.textContent = `ویرایش ${data.filename}`;

            if (fmEditorTextarea) {
                fmEditorTextarea.value = data.content || '';
                updateLineNumbers();
                fmEditorTextarea.focus();
            }
        })
        .catch(() => {
            showToast('خطای شبکه در دریافت فایل.', 'error');
        });
    }

    function closeFmEditor() {
        if (fmIsDirty && !confirm('تغییرات ذخیره‌نشده دارید. آیا از خروج اطمینان دارید؟')) {
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

    // Modal Submissions
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

    // =========================================================================
    // Database Studio Controller
    // =========================================================================
    let activeDbId = null;
    let activeDbType = null;
    let activeDbName = null;
    const activeDbTitle = document.getElementById('activeDbTitle');
    const activeDbBadgeContainer = document.getElementById('activeDbBadgeContainer');
    const dbTablesStrip = document.getElementById('dbTablesStrip');
    const sqlQueryTextarea = document.getElementById('sqlQueryTextarea');
    const runSqlQueryBtn = document.getElementById('runSqlQueryBtn');
    const sqlResultsContainer = document.getElementById('sqlResultsContainer');

    const sqliteModal = document.getElementById('sqliteModal');
    const mysqlModal = document.getElementById('mysqlModal');
    const sqliteForm = document.getElementById('sqliteForm');
    const mysqlForm = document.getElementById('mysqlForm');

    document.getElementById('openNewSqliteModal')?.addEventListener('click', () => openModal(sqliteModal));
    document.getElementById('openNewMysqlModal')?.addEventListener('click', () => openModal(mysqlModal));

    document.querySelectorAll('.db-item-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="delete-db"]')) return;
            document.querySelectorAll('.db-item-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            activeDbType = btn.getAttribute('data-db-type');
            activeDbId = btn.getAttribute('data-db-id');
            activeDbName = btn.getAttribute('data-db-name');

            selectDatabase(activeDbType, activeDbId, activeDbName);
        });
    });

    function selectDatabase(type, id, name) {
        if (activeDbTitle) activeDbTitle.textContent = name;
        if (activeDbBadgeContainer) {
            activeDbBadgeContainer.innerHTML = (type === 'sqlite')
                ? `<span class="status-pill status-pill-info">SQLite</span>`
                : `<span class="status-pill status-pill-warning">MySQL</span>`;
        }
        if (runSqlQueryBtn) runSqlQueryBtn.disabled = false;

        if (dbTablesStrip) {
            dbTablesStrip.innerHTML = '<span style="font-size: 0.8rem; color: var(--text-muted);">در حال دریافت جداول...</span>';
        }

        fetch(`./?action=db_tables&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && dbTablesStrip) {
                if (data.tables.length === 0) {
                    dbTablesStrip.innerHTML = '<span style="font-size: 0.8rem; color: var(--text-muted);">هیچ جدولی در این پایگاه داده یافت نشد.</span>';
                } else {
                    dbTablesStrip.innerHTML = '';
                    data.tables.forEach(t => {
                        const chip = document.createElement('div');
                        chip.className = 'db-table-chip';
                        chip.textContent = `${t.name} (${t.rows})`;
                        chip.title = `${t.rows} رکورد - برای مشاهده کلیک کنید`;
                        chip.addEventListener('click', () => {
                            if (sqlQueryTextarea) {
                                sqlQueryTextarea.value = `SELECT * FROM ${t.name} LIMIT 50;`;
                                executeSqlQuery();
                            }
                        });
                        dbTablesStrip.appendChild(chip);
                    });
                }
            }
        })
        .catch(() => {
            if (dbTablesStrip) {
                dbTablesStrip.innerHTML = '<span style="font-size: 0.8rem; color: var(--danger);">خطا در دریافت جداول.</span>';
            }
        });
    }

    document.querySelectorAll('.sql-quick-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!sqlQueryTextarea) return;
            const tpl = btn.getAttribute('data-template');
            let tableName = 'table_name';
            const firstChip = dbTablesStrip?.querySelector('.db-table-chip');
            if (firstChip) {
                tableName = firstChip.textContent.split(' ')[0];
            }
            sqlQueryTextarea.value = tpl.replace('{table}', tableName);
            sqlQueryTextarea.focus();
        });
    });

    if (runSqlQueryBtn) {
        runSqlQueryBtn.addEventListener('click', executeSqlQuery);
    }

    if (sqlQueryTextarea) {
        sqlQueryTextarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                executeSqlQuery();
            }
        });
    }

    function executeSqlQuery() {
        if (!activeDbType || !activeDbId) {
            showToast('ابتدا یک دیتابیس را انتخاب نمایید.', 'warning');
            return;
        }

        const query = sqlQueryTextarea ? sqlQueryTextarea.value.trim() : '';
        if (!query) {
            showToast('لطفاً دستور SQL را وارد نمایید.', 'warning');
            return;
        }

        if (runSqlQueryBtn) {
            runSqlQueryBtn.disabled = true;
            runSqlQueryBtn.textContent = 'در حال اجرا...';
        }
        if (sqlResultsContainer) {
            sqlResultsContainer.innerHTML = '<div style="padding: 1.5rem; text-align: center; color: var(--text-muted);">در حال اجرای کوئری...</div>';
        }

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('type', activeDbType);
        formData.append('id', activeDbId);
        formData.append('query', query);

        fetch('./?action=db_query', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (runSqlQueryBtn) {
                runSqlQueryBtn.disabled = false;
                runSqlQueryBtn.textContent = '▶ اجرای کوئری (Ctrl+Enter)';
            }

            if (data.success) {
                renderSqlResults(data);
                if (activeDbType && activeDbId && activeDbName) {
                    selectDatabase(activeDbType, activeDbId, activeDbName);
                }
            } else {
                if (sqlResultsContainer) {
                    sqlResultsContainer.innerHTML = `
                        <div style="padding: 1rem; background: var(--danger-subtle); border: 1px solid var(--danger-border); border-radius: var(--radius-sm); color: #fca5a5; font-family: var(--font-mono); font-size: 0.82rem; direction: ltr; text-align: left;">
                            Error: ${escapeHtml(data.error)}
                        </div>
                    `;
                }
            }
        })
        .catch(() => {
            if (runSqlQueryBtn) {
                runSqlQueryBtn.disabled = false;
                runSqlQueryBtn.textContent = '▶ اجرای کوئری (Ctrl+Enter)';
            }
            if (sqlResultsContainer) {
                sqlResultsContainer.innerHTML = '<div style="padding: 1rem; color: var(--danger);">خطای شبکه در اجرای کوئری.</div>';
            }
        });
    }

    function renderSqlResults(data) {
        if (!sqlResultsContainer) return;

        let metaHtml = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; font-size: 0.78rem; color: var(--text-muted); font-family: var(--font-mono); direction: ltr;">
                <span>Type: <strong>${data.command || 'QUERY'}</strong> | Execution: <strong>${data.execution_time_ms} ms</strong></span>
                <span>Affected: <strong>${data.affected_rows}</strong> | Records: <strong>${data.count}</strong></span>
            </div>
        `;

        if (data.is_select && data.columns && data.columns.length > 0) {
            let tableHtml = `
                <div style="overflow-x: auto; max-height: 380px; border: 1px solid var(--border); border-radius: var(--radius-sm);">
                    <table class="data-table" style="direction: ltr;">
                        <thead>
                            <tr>
                                ${data.columns.map(col => `<th>${escapeHtml(col)}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.rows.map(row => `
                                <tr>
                                    ${data.columns.map(col => `<td>${escapeHtml(String(row[col] !== null ? row[col] : 'NULL'))}</td>`).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
            sqlResultsContainer.innerHTML = metaHtml + tableHtml;
        } else {
            sqlResultsContainer.innerHTML = metaHtml + `
                <div style="padding: 1rem; background: var(--success-subtle); border: 1px solid var(--success-border); border-radius: var(--radius-sm); color: #34d399; font-size: 0.84rem;">
                    ✓ دستور با موفقیت اجرا شد. (تعداد ردیف‌های تغییر یافته: ${data.affected_rows})
                </div>
            `;
        }
    }

    // Delete Database Action
    document.querySelectorAll('[data-action="delete-db"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const type = btn.getAttribute('data-db-type');
            const id = btn.getAttribute('data-db-id');

            if (!confirm(`آیا از حذف این پایگاه داده اطمینان دارید؟`)) return;

            const action = (type === 'sqlite') ? 'db_delete_sqlite' : 'db_delete_mysql';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('id', id);

            fetch(`./?action=${action}`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.error || 'خطا در حذف دیتابیس.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    });

    if (sqliteForm) {
        sqliteForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(sqliteForm);
            formData.append('csrf_token', csrfToken);

            fetch('./?action=db_create_sqlite', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(sqliteModal);
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.error || 'خطا در ساخت SQLite.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    }

    if (mysqlForm) {
        mysqlForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(mysqlForm);
            formData.append('csrf_token', csrfToken);

            fetch('./?action=db_save_mysql', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(mysqlModal);
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.error || 'خطا در ذخیره اتصال MySQL.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    }

    // =========================================================================
    // Quota Management
    // =========================================================================
    const quotaModal = document.getElementById('quotaModal');
    const quotaForm = document.getElementById('quotaForm');
    document.getElementById('openQuotaEditBtn')?.addEventListener('click', () => openModal(quotaModal));

    if (quotaForm) {
        quotaForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(quotaForm);
            formData.append('csrf_token', csrfToken);

            fetch('./?action=update_quota', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(quotaModal);
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(data.error || 'خطا در ثبت سهمیه.', 'error');
                }
            })
            .catch(() => showToast('خطای شبکه.', 'error'));
        });
    }

    // =========================================================================
    // Refresh Logs Tab
    // =========================================================================
    const refreshLogsBtn = document.getElementById('refreshLogsBtn');
    const logsTimeline = document.getElementById('logsTimeline');

    if (refreshLogsBtn && logsTimeline) {
        refreshLogsBtn.addEventListener('click', () => {
            refreshLogsBtn.disabled = true;
            refreshLogsBtn.textContent = 'در حال دریافت...';

            fetch('./?action=logs&limit=35', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                refreshLogsBtn.disabled = false;
                refreshLogsBtn.textContent = '🔄 به‌روزرسانی لاگ‌ها';

                if (data.success && data.logs) {
                    if (data.logs.length === 0) {
                        logsTimeline.innerHTML = '<div style="padding: 2.5rem; text-align: center; color: var(--text-muted);">هنوز رویدادی ثبت نشده است.</div>';
                    } else {
                        logsTimeline.innerHTML = data.logs.map(log => `
                            <div class="log-item-row">
                                <span class="log-item-time">${escapeHtml(log.time)}</span>
                                <span class="log-item-ip">${escapeHtml(log.ip)}</span>
                                <span class="log-item-msg">${escapeHtml(log.message)}</span>
                            </div>
                        `).join('');
                    }
                    showToast('لاگ‌ها با موفقیت به‌روزرسانی شدند.', 'info', 1800);
                }
            })
            .catch(() => {
                refreshLogsBtn.disabled = false;
                refreshLogsBtn.textContent = '🔄 به‌روزرسانی لاگ‌ها';
                showToast('خطای شبکه در دریافت لاگ‌ها.', 'error');
            });
        });
    }

    // Copy to clipboard helper
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', () => {
            const textToCopy = btn.getAttribute('data-copy');
            if (!textToCopy) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const original = btn.textContent;
                    btn.textContent = 'کپی شد!';
                    showToast('آدرس در کلیپ‌بورد کپی شد.', 'info', 1800);
                    setTimeout(() => { btn.textContent = original; }, 1600);
                }).catch(() => fallbackCopy(textToCopy, btn));
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
            showToast('آدرس کپی شد.', 'info', 1800);
            setTimeout(() => { btn.textContent = 'کپی'; }, 1600);
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
