
(function() {
    if (window.__sidebarToggleLoaded) {
        console.log('Sidebar toggle script already loaded, skipping re-initialization.');
        return;
    }
    window.__sidebarToggleLoaded = true;
    console.log('=== SIDEBAR SCRIPT LOADING ===');

    function initializeSidebar() {
    console.log('Attempting to initialize sidebar...');
    
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenu = document.getElementById('closeMenu');
    
    console.log('Element search results:', {
        menuBtn: menuBtn ? 'FOUND' : 'MISSING',
        sidebar: sidebar ? 'FOUND' : 'MISSING', 
        menuOverlay: menuOverlay ? 'FOUND' : 'MISSING',
        closeMenu: closeMenu ? 'FOUND' : 'MISSING'
    });
    
    if (!menuBtn || !sidebar || !menuOverlay) {
        console.log('⚠️  Some elements missing, will retry...');
        return false;
    }
    
    
    const newMenuBtn = menuBtn.cloneNode(true);
    menuBtn.parentNode.replaceChild(newMenuBtn, menuBtn);
    
    console.log('✅ All elements found, setting up event listeners...');
    
    newMenuBtn.addEventListener('click', function(e) {
        console.log('🎯 MENU BUTTON CLICKED!');
        e.preventDefault();
        e.stopPropagation();
        
        const isOpen = sidebar.classList.contains('show');
        console.log('Current state:', isOpen ? 'OPEN' : 'CLOSED');
        
        if (isOpen) {
            sidebar.classList.remove('show');
            menuOverlay.classList.remove('show');
            console.log('📤 Sidebar CLOSED');
        } else {
            sidebar.classList.add('show');
            menuOverlay.classList.add('show');
            console.log('📥 Sidebar OPENED');
        }
    });
    
    
    menuOverlay.addEventListener('click', function() {
        console.log('Overlay clicked - closing sidebar');
        sidebar.classList.remove('show');
        menuOverlay.classList.remove('show');
    });
    
    
    if (closeMenu) {
        closeMenu.addEventListener('click', function(e) {
            console.log('Close button clicked');
            e.preventDefault();
            sidebar.classList.remove('show');
            menuOverlay.classList.remove('show');
        });
    }
    
    
    const menuLinks = document.querySelectorAll('.sidebar .menu-items a');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            console.log('Menu link clicked - closing sidebar');
            sidebar.classList.remove('show');
            menuOverlay.classList.remove('show');
        });
    });
    
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
            console.log('Escape key pressed - closing sidebar');
            sidebar.classList.remove('show');
            menuOverlay.classList.remove('show');
        }
    });
    
    console.log('✅ Sidebar initialization complete!');
    return true;
    }

    
    window.__sidebarAttempts = window.__sidebarAttempts || 0;
    const maxAttempts = 20;

    function tryInit() {
        window.__sidebarAttempts++;
        console.log(`Sidebar initialization attempt ${window.__sidebarAttempts}/${maxAttempts}`);

        if (initializeSidebar()) {
            console.log('🎉 Sidebar successfully initialized!');
            return;
        }

        if (window.__sidebarAttempts < maxAttempts) {
            setTimeout(tryInit, 100);
        } else {
            console.error('❌ Failed to initialize sidebar after', maxAttempts, 'attempts');
            console.log('Available elements with ID:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
        }
    }

    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }

    
    window.addEventListener('load', function() {
        console.log('Window loaded, making final sidebar attempt...');
        const sidebarEl = document.getElementById('sidebar');
        if (sidebarEl && !sidebarEl.classList.contains('sidebar-initialized')) {
            initializeSidebar();
            sidebarEl.classList.add('sidebar-initialized');
        }
    });

})();
