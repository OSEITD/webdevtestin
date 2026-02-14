<!-- PWA Install Button -->
<div id="pwa-install-banner" class="pwa-install-banner" style="display: none;">
    <div class="pwa-banner-content">
        <div class="pwa-banner-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
        </div>
        <div class="pwa-banner-text">
            <strong>Install App</strong>
            <span>Add to home screen for quick access</span>
        </div>
        <button id="pwa-install-btn" class="pwa-install-btn-primary">
            Install
        </button>
        <button id="pwa-install-dismiss" class="pwa-install-btn-dismiss" aria-label="Dismiss">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </button>
    </div>
</div>

<style>
.pwa-install-banner {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    max-width: 90%;
    width: 420px;
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        transform: translateX(-50%) translateY(100px);
        opacity: 0;
    }
    to {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
}

.pwa-banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pwa-banner-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pwa-banner-text {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.pwa-banner-text strong {
    font-size: 15px;
    font-weight: 600;
}

.pwa-banner-text span {
    font-size: 13px;
    opacity: 0.9;
}

.pwa-install-btn-primary {
    padding: 8px 20px;
    background: white;
    color: #667eea;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}

.pwa-install-btn-primary:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.pwa-install-btn-dismiss {
    padding: 4px;
    background: transparent;
    border: none;
    color: white;
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s;
    flex-shrink: 0;
}

.pwa-install-btn-dismiss:hover {
    opacity: 1;
}

/* Mobile responsive */
@media (max-width: 480px) {
    .pwa-install-banner {
        bottom: 10px;
        padding: 12px 16px;
        width: calc(100% - 20px);
    }
    
    .pwa-banner-content {
        gap: 10px;
    }
    
    .pwa-banner-icon {
        width: 36px;
        height: 36px;
    }
    
    .pwa-banner-text strong {
        font-size: 14px;
    }
    
    .pwa-banner-text span {
        font-size: 12px;
    }
    
    .pwa-install-btn-primary {
        padding: 6px 16px;
        font-size: 13px;
    }
}

/* Hide banner animation */
.pwa-install-banner.hiding {
    animation: slideDown 0.3s ease-out forwards;
}

@keyframes slideDown {
    from {
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }
    to {
        transform: translateX(-50%) translateY(100px);
        opacity: 0;
    }
}
</style>
