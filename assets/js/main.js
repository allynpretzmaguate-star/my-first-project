document.addEventListener('DOMContentLoaded', function () {
    // Sidebar toggle (mobile)
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // Confirm before destructive actions
    document.querySelectorAll('[data-confirm]').forEach((el) => {
        const eventName = el.tagName === 'FORM' ? 'submit' : 'click';
        el.addEventListener(eventName, function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Auto-dismiss alerts after 5s
    document.querySelectorAll('.alert').forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // Lightbox for any image with class="lightbox-trigger"
    const overlay = document.createElement('div');
    overlay.className = 'lightbox-overlay';
    overlay.innerHTML = '<span class="lightbox-close">&times;</span><img alt="Full size preview">';
    document.body.appendChild(overlay);
    const overlayImg = overlay.querySelector('img');

    document.querySelectorAll('.lightbox-trigger').forEach((img) => {
        img.addEventListener('click', () => {
            overlayImg.src = img.src;
            overlay.classList.add('open');
        });
    });

    overlay.addEventListener('click', () => overlay.classList.remove('open'));
});