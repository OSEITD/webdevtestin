

window.addEventListener('unhandledrejection', function(event) {
    
    if (event.reason && 
        event.reason.message && 
        event.reason.message.includes('message channel closed')) {
        console.warn('Browser extension message channel error (suppressed):', event.reason.message);
        
        event.preventDefault();
        return;
    }
    
    
    if (event.reason && 
        event.reason.message && 
        (event.reason.message.includes('Extension context invalidated') ||
         event.reason.message.includes('Could not establish connection'))) {
        console.warn('Browser extension error (suppressed):', event.reason.message);
        event.preventDefault();
        return;
    }
    
    
    console.error('Unhandled promise rejection:', event.reason);
});

window.addEventListener('error', function(event) {
    
    if (event.message && 
        (event.message.includes('Extension context invalidated') ||
         event.message.includes('message channel closed') ||
         event.message.includes('Could not establish connection'))) {
        console.warn('Browser extension error (suppressed):', event.message);
        event.preventDefault();
        return true;
    }
    
    
    console.error('Application error:', event.error || event.message);
});

if (typeof chrome !== 'undefined' && chrome.runtime) {
    console.info('%c🛡️ Extension Compatibility Mode Active', 
                'color: #28a745; font-weight: bold;',
                '\nSome browser extensions may cause harmless errors. These are automatically suppressed.');
}
