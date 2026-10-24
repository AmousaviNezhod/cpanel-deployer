/**
 * cPanel Deployer - Client Application Logic
 */
document.addEventListener('DOMContentLoaded', () => {
    // CSRF Token from meta
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // Elements
    const deployModal = document.getElementById('deployModal');
    const updateModal = document.getElementById('updateModal');
    const deleteModal = document.getElementById('deleteModal');
    const openDeployBtn = document.getElementById('openDeployModal');
    const closeButtons = document.querySelectorAll('[data-close-modal]');

    // Forms
    const deployForm = document.getElementById('deployForm');
    const updateForm = document.getElementById('updateForm');
    const deleteForm = document.getElementById('deleteForm');

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
        // Reset forms inside if any
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
    if (openDeployBtn) {
        openDeployBtn.addEventListener('click', () => openModal(deployModal));
    }

    closeButtons.forEach(btn => {
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
     * Slug Generation & Live URL Preview
     */
    const nameInput = document.getElementById('siteNameInput');
    const slugInput = document.getElementById('siteSlugInput');
    const urlPreview = document.getElementById('slugUrlPreview');

    if (slugInput && urlPreview) {
        const baseOrigin = window.location.origin;
        
        function updateUrlPreview() {
            let val = slugInput.value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
            slugInput.value = val;
            urlPreview.textContent = `${baseOrigin}/${val || 'slug'}/`;
        }

        slugInput.addEventListener('input', updateUrlPreview);

        if (nameInput) {
            nameInput.addEventListener('input', () => {
                if (!slugInput.dataset.touched) {
                    let autoSlug = nameInput.value.trim().toLowerCase()
                        .replace(/\s+/g, '-')
                        .replace(/[^a-z0-9_-]/g, '');
                    if (autoSlug) {
                        slugInput.value = autoSlug;
                        updateUrlPreview();
                    }
                }
            });
            slugInput.addEventListener('change', () => {
                slugInput.dataset.touched = 'true';
            });
        }
    }

    /**
     * File Picker & Drag-and-Drop
     */
    function setupDropzone(dropzoneId, inputId, labelId) {
        const dropzone = document.getElementById(dropzoneId);
        const input = document.getElementById(inputId);
        const label = document.getElementById(labelId);
        if (!dropzone || !input) return;

        ['dragenter', 'dragover'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
                updateFileLabel();
            }
        });

        input.addEventListener('change', updateFileLabel);

        function updateFileLabel() {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                if (label) {
                    label.textContent = `فایل انتخاب‌شده: ${file.name} (${sizeMb} MB)`;
                }
            } else if (label) {
                label.textContent = '';
            }
        }
    }

    setupDropzone('deployDropzone', 'deployZipInput', 'deployFilename');
    setupDropzone('updateDropzone', 'updateZipInput', 'updateFilename');

    /**
     * Deploy Form Submission (AJAX + Progress)
     */
    if (deployForm) {
        deployForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const fileInput = document.getElementById('deployZipInput');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showToast('لطفاً ابتدا فایل ZIP سایت را انتخاب کنید.', 'error');
                return;
            }

            const slug = slugInput.value.trim();
            if (!slug) {
                showToast('لطفاً شناسه مسیر (Slug) را وارد کنید.', 'error');
                return;
            }

            uploadWithProgress(
                deployForm,
                './?action=deploy',
                deployModal,
                'در حال دیپلوی و استخراج فایل‌ها...'
            );
        });
    }

    /**
     * Update Form Trigger & Submission
     */
    document.querySelectorAll('[data-action="open-update"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const slug = btn.getAttribute('data-slug');
            const name = btn.getAttribute('data-name');
            document.getElementById('updateSlugInput').value = slug;
            document.getElementById('updateSiteNameDisplay').textContent = `${name} (${slug})`;
            openModal(updateModal);
        });
    });

    if (updateForm) {
        updateForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const fileInput = document.getElementById('updateZipInput');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showToast('لطفاً فایل ZIP جدید را انتخاب کنید.', 'error');
                return;
            }

            uploadWithProgress(
                updateForm,
                './?action=update',
                updateModal,
                'در حال جایگزینی و استخراج فایل‌ها...'
            );
        });
    }

    /**
     * Delete Form Trigger & Submission
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

    if (deleteForm) {
        deleteForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const submitBtn = deleteForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال حذف...';

            const formData = new FormData(deleteForm);
            formData.append('action', 'delete');
            if (csrfToken) formData.append('csrf_token', csrfToken);

            fetch('./?action=delete', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal(deleteModal);
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.error || 'خطا در حذف سایت.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'بله، حذف کن';
                }
            })
            .catch(err => {
                showToast('خطای ارتباط با سرور: ' . err.message, 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'بله، حذف کن';
            });
        });
    }

    /**
     * Generic Upload with Progress via XMLHttpRequest
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
});
