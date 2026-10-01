import './bootstrap';
import './mark-grid';
import { initAttendanceGrid } from './modules/attendance-grid';

document.addEventListener('DOMContentLoaded', function () {
    initAttendanceGrid();
    initSidebarToggle();
    initUserMenu();
});

function initUserMenu() {
    const container = document.getElementById('user-menu-container');
    const trigger = document.getElementById('user-menu-trigger');
    const dropdown = document.getElementById('user-menu-dropdown');

    if (!container || !trigger || !dropdown) return;

    let hoverTimeout = null;

    function openMenu() {
        if (hoverTimeout) clearTimeout(hoverTimeout);
        dropdown.style.display = 'block';
        trigger.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
        if (hoverTimeout) clearTimeout(hoverTimeout);
        dropdown.style.display = 'none';
        trigger.setAttribute('aria-expanded', 'false');
    }

    function toggleMenu() {
        const isOpen = dropdown.style.display === 'block';
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        toggleMenu();
    });

    // Desktop hover with smooth close delay
    container.addEventListener('mouseenter', function () {
        if (window.innerWidth > 768) {
            openMenu();
        }
    });

    container.addEventListener('mouseleave', function () {
        if (window.innerWidth > 768) {
            hoverTimeout = setTimeout(function () {
                closeMenu();
            }, 250);
        }
    });

    // Close on click outside
    document.addEventListener('click', function (e) {
        if (!container.contains(e.target)) {
            closeMenu();
        }
    });

    // Close on Escape and return focus to trigger
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dropdown.style.display === 'block') {
            closeMenu();
            trigger.focus();
        }
    });
}

function initSidebarToggle() {
    const sidebar = document.querySelector('.app-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    if (!sidebar || !toggleBtn) return;

    const storageKey = 'app_sidebar_collapsed';

    try {
        const isCollapsed = localStorage.getItem(storageKey) === 'true';
        if (window.innerWidth > 768 && isCollapsed) {
            sidebar.classList.add('collapsed');
            toggleBtn.setAttribute('aria-expanded', 'false');
        } else if (window.innerWidth > 768) {
            sidebar.classList.remove('collapsed');
            toggleBtn.setAttribute('aria-expanded', 'true');
        }
    } catch (e) {}

    document.documentElement.classList.remove('sidebar-collapsed-preload');

    toggleBtn.addEventListener('click', function () {
        const currentlyCollapsed = sidebar.classList.toggle('collapsed');
        toggleBtn.setAttribute('aria-expanded', String(!currentlyCollapsed));
        try {
            if (window.innerWidth > 768) {
                localStorage.setItem(storageKey, String(currentlyCollapsed));
            }
        } catch (e) {}
    });
}

window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
};

window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        const anyOpen = Array.from(document.querySelectorAll('.modal-backdrop')).some(function (m) {
            return m.style.display === 'flex';
        });
        if (!anyOpen) {
            document.body.style.overflow = '';
        }
    }
};

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop').forEach(function (m) {
            m.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
});
