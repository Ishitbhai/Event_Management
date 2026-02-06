// Sidebar hamburger/overlay logic for small screens
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar');
    const backdropClass = 'sidebar-backdrop';
    let backdrop = document.querySelector('.' + backdropClass);

    // Ensure toggle button
    function ensureToggleBtn() {
        let btn = document.querySelector('.sidebar-toggle-btn');
        if (window.innerWidth <= 520) {
            if (!btn) {
                btn = document.createElement('button');
                btn.className = 'sidebar-toggle-btn';
                btn.type = 'button';
                btn.title = 'Open or close sidebar';
                btn.setAttribute('aria-label', 'Toggle sidebar');
                btn.innerHTML = '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><rect x="4" y="6" width="16" height="2" fill="currentColor"/><rect x="4" y="11" width="16" height="2" fill="currentColor"/><rect x="4" y="16" width="16" height="2" fill="currentColor"/></svg>';
                document.body.appendChild(btn);

                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    // Toggle open/closed state on click
                    if (sidebar.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }
        } else {
            if (btn) {
                btn.remove();
            }
        }
    }

    // Create backdrop if not present
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = backdropClass;
        document.body.appendChild(backdrop);
    }

    function openSidebar() {
        sidebar.classList.add('open');
        if (backdrop) {
            backdrop.style.display = 'block';
            // For fade-in
            setTimeout(function() {
                backdrop.style.opacity = '1';
            }, 10);
        }
        document.body.classList.add('no-scroll');
        // When sidebar opens as overlay, push page-header down
        setPageHeaderMargin();
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        if (backdrop) {
            backdrop.style.opacity = '0';
            setTimeout(function () {
                backdrop.style.display = 'none';
            }, 220);
        }
        document.body.classList.remove('no-scroll');
        // When closed, reset page-header margin
        setPageHeaderMargin();
    }

    // Adjust margin of .page-header for mobile overlay
    function setPageHeaderMargin() {
        var pageHeader = document.querySelector('.page-header');
        if (!pageHeader) return;
        if (window.innerWidth <= 520 && sidebar.classList.contains('open')) {
            pageHeader.style.marginTop = '48px';
        } else {
            pageHeader.style.marginTop = '';
        }
    }

    // Backdrop closes the sidebar
    backdrop.addEventListener('click', closeSidebar);

    // Responsive: Ensure toggle button exists/disappears on resize
    ensureToggleBtn();
    window.addEventListener('resize', function () {
        // Always close overlay on resize to desktop
        if (window.innerWidth > 520) {
            closeSidebar();
        }
        ensureToggleBtn();
        setPageHeaderMargin();
    });

    // Keyboard accessibility: ESC closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            // Only close if open and in mobile mode
            if (window.innerWidth <= 520 && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        }
    });

    // On click outside (on main content), close the sidebar on mobile
    document.addEventListener('click', function(e){
        const btn = document.querySelector('.sidebar-toggle-btn');
        // Only if sidebar is open and we're on mobile
        if (
            window.innerWidth <= 520 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            (!btn || !btn.contains(e.target)) &&
            !backdrop.contains(e.target)
        ) {
            closeSidebar();
        }
    });

    // Initialize pageHeader margin after DOM
    setPageHeaderMargin();
});
