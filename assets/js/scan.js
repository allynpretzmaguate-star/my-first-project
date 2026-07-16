(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('documentInput');
        const uploadPrompt = document.getElementById('uploadPrompt');
        const previewImage = document.getElementById('previewImage');
        const scanButton = document.getElementById('scanButton');
        const scanStatus = document.getElementById('scanStatus');
        const extractedFormCard = document.getElementById('extractedFormCard');
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        const csrfToken = csrfInput ? csrfInput.value : '';
        const safeStatus = scanStatus || { innerHTML: '', textContent: '' };

        let selectedFile = null;

        function handleFile(file) {
            if (!file || !file.type || !file.type.match(/image\/(jpeg|png|webp)/)) {
                alert('Please select a JPG, PNG, or WEBP image.');
                return;
            }
            selectedFile = file;
            const reader = new FileReader();
            reader.onload = (e) => {
                if (previewImage) {
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                }
                if (uploadPrompt) {
                    uploadPrompt.style.display = 'none';
                }
            };
            reader.readAsDataURL(file);
            if (scanButton) {
                scanButton.disabled = false;
            }
            if (scanStatus) {
                scanStatus.textContent = '';
            }
        }

        if (dropZone) {
            dropZone.addEventListener('click', () => {
                if (fileInput) {
                    fileInput.click();
                }
            });
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    handleFile(e.dataTransfer.files[0]);
                }
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length) handleFile(fileInput.files[0]);
            });
        }

        if (scanButton) {
            scanButton.addEventListener('click', async () => {
                if (!selectedFile) return;
                if (!csrfToken) {
                    if (safeStatus) {
                        safeStatus.innerHTML = '<span class="text-error">⚠ CSRF token missing. Please refresh the page and try again.</span>';
                    }
                    return;
                }

                if (scanButton) {
                    scanButton.disabled = true;
                    scanButton.textContent = 'Extracting…';
                }
                if (safeStatus) {
                    safeStatus.innerHTML = '<span class="spinner"></span> Running OCR on document, please wait…';
                }

                const formData = new FormData();
                formData.append('document', selectedFile);
                formData.append('csrf_token', csrfToken);

                try {
                    const res = await fetch('scan.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (!data.success) {
                        if (safeStatus) {
                            safeStatus.innerHTML = `<span class="text-error">⚠ ${escapeHtml(data.message || 'OCR failed.')}</span>`;
                        }
                        if (scanButton) {
                            scanButton.disabled = false;
                            scanButton.textContent = 'Extract Information';
                        }
                        return;
                    }

                    if (safeStatus) {
                        safeStatus.innerHTML = '<span class="text-success">✓ Text extracted. Redirecting to the review form…</span>';
                    }
                    if (extractedFormCard) {
                        extractedFormCard.style.display = 'block';
                        extractedFormCard.scrollIntoView({ behavior: 'smooth' });
                    }

                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 600);
                    }
                } catch (err) {
                    if (safeStatus) {
                        safeStatus.innerHTML = `<span class="text-error">⚠ Network or server error: ${escapeHtml(err.message)}</span>`;
                    }
                } finally {
                    if (scanButton) {
                        scanButton.disabled = false;
                        scanButton.textContent = 'Extract Information';
                    }
                }
            });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    });
})();
