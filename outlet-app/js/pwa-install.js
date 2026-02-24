

(function() {
    let deferredPrompt = null;
    const installBanner = document.getElementById('pwa-install-banner');
    const installBtn = document.getElementById('pwa-install-btn');
    const dismissBtn = document.getElementById('pwa-install-dismiss');

    const isDismissed = localStorage.getItem('pwa-install-dismissed');
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches 
        || window.navigator.standalone 
        || document.referrer.includes('android-app://');
    
    console.log('[PWA Install] Initializing...', {
        isDismissed,
        isStandalone,
        bannerExists: !!installBanner
    });
    
    if (isStandalone) {
        console.log('[PWA Install] App is already installed/standalone mode');
        return;
    }
    
    // Listen for the beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', (e) => {
        console.log('[PWA Install] Before install prompt event fired');
        
        // Prevent the default mini-infobar
        e.preventDefault();
        
        // Store the event for later use
        deferredPrompt = e;
        
        // Show the custom install banner if not dismissed
        if (!isDismissed && installBanner) {
            setTimeout(() => {
                installBanner.style.display = 'block';
                console.log('[PWA Install] Banner displayed');
            }, 2000); // Show after 2 seconds
        }
    });
    
    // Handle install button click
    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            console.log('[PWA Install] Install button clicked');
            
            if (!deferredPrompt) {
                console.warn('[PWA Install] No deferred prompt available');
                return;
            }
            
            // Hide the banner
            hideBanner();
            
            // Show the install prompt
            deferredPrompt.prompt();
            
            // Wait for the user's response
            const { outcome } = await deferredPrompt.userChoice;
            console.log('[PWA Install] User choice:', outcome);
            
            if (outcome === 'accepted') {
                console.log('[PWA Install] User accepted the install prompt');
            } else {
                console.log('[PWA Install] User dismissed the install prompt');
            }
            
            // Clear the deferred prompt
            deferredPrompt = null;
        });
    }
    
    // Handle dismiss button click
    if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
            console.log('[PWA Install] Dismiss button clicked');
            hideBanner();
            
            // Remember dismissal for 7 days
            const dismissedUntil = Date.now() + (7 * 24 * 60 * 60 * 1000);
            localStorage.setItem('pwa-install-dismissed', dismissedUntil);
        });
    }
    
    // Function to hide the banner with animation
    function hideBanner() {
        if (installBanner) {
            installBanner.classList.add('hiding');
            setTimeout(() => {
                installBanner.style.display = 'none';
                installBanner.classList.remove('hiding');
            }, 300);
        }
    }
    
    // Check if dismissal has expired
    if (isDismissed) {
        const dismissedUntil = parseInt(isDismissed);
        if (Date.now() > dismissedUntil) {
            localStorage.removeItem('pwa-install-dismissed');
            console.log('[PWA Install] Dismissal period expired');
        }
    }
    
    // Listen for successful app installation
    window.addEventListener('appinstalled', () => {
        console.log('[PWA Install] App successfully installed');
        hideBanner();
        
        // Clear any dismissal flags
        localStorage.removeItem('pwa-install-dismissed');
        
        // Optional: Show success message
        if (typeof showToast === 'function') {
            showToast('App installed successfully!', 'success');
        }
    });
    
    // Expose method to manually trigger install prompt
    window.triggerPWAInstall = function() {
        if (deferredPrompt && installBtn) {
            installBtn.click();
        } else if (!isStandalone && installBanner) {
            // If no prompt available but not installed, at least show the banner
            localStorage.removeItem('pwa-install-dismissed');
            installBanner.style.display = 'block';
        } else {
            console.warn('[PWA Install] Cannot trigger install - either already installed or prompt not available');
        }
    };
    
    console.log('[PWA Install] Manager initialized successfully');
})();
