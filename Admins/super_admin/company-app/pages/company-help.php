 <?php
$page_title = 'Company - Help Center';
include __DIR__ . '/../includes/header.php';
 ?>
 
    <!-- Main Help Content -->
    <main class="main-content">
        <div class="content-header">
            <h1>Help Center</h1>
            <p class="subtitle">Find answers and support</p>
        </div>

        <!-- Help Categories -->
        <div class="help-categories">
            <div class="help-card" onclick="smoothScrollTo('shortcuts')">
                <i class="fas fa-keyboard"></i>
                <h3>Keyboard Shortcuts</h3>
                <p>Learn time-saving hotkeys</p>
            </div>
            <div class="help-card" onclick="smoothScrollTo('faq')">
                <i class="fas fa-question-circle"></i>
                <h3>FAQs</h3>
                <p>Common questions answered</p>
            </div>
            <div class="help-card" onclick="smoothScrollTo('vitals')">
                <i class="fas fa-heartbeat"></i>
                <h3>App Vitals</h3>
                <p>Performance & health</p>
            </div>
        </div>

        <!-- Keyboard Shortcuts Section -->
        <div class="help-section" id="shortcuts">
            <h2><i class="fas fa-keyboard"></i> Keyboard Shortcuts</h2>
            <div class="shortcuts-table">
                <div class="shortcut-row">
                    <div class="shortcut-keys">
                        <kbd>Ctrl/Cmd</kbd> + <kbd>1-5</kbd>
                    </div>
                    <div class="shortcut-desc">
                        Switch between Dashboard, Outlets, Drivers, Deliveries, and Reports.
                    </div>
                </div>

                <div class="shortcut-row">
                    <div class="shortcut-keys">
                        <kbd>Ctrl/Cmd</kbd> + <kbd>K</kbd>
                    </div>
                    <div class="shortcut-desc">
                        Open the search bar.
                    </div>
                </div>
               
                <div class="shortcut-row">
                    <div class="shortcut-keys">
                        <kbd>Ctrl/Cmd</kbd> + <kbd>M</kbd>
                    </div>
                    <div class="shortcut-desc">
                        Open the Sidebar.
                    </div>
                </div>

                <div class="shortcut-row">
                    <div class="shortcut-keys">
                        <kbd>Alt</kbd> + <kbd>←</kbd> / <kbd>→</kbd>
                    </div>
                    <div class="shortcut-desc">
                        Navigate back/forward in history.
                    </div>
                </div>

                <div class="shortcut-row">
                    <div class="shortcut-keys">
                        <kbd>Alt</kbd> + <kbd>N</kbd>
                    </div>
                    <div class="shortcut-desc">
                        Toggles Sidebar Notifications.
                    </div>
                </div>

                <div class="shortcut-row">
                    <div class="shortcut-keys">
                        <kbd>Ctrl</kbd> + <kbd>Alt</kbd> + <kbd>N</kbd>
                    </div>
                    <div class="shortcut-desc">
                        Toggles Main Notifications.
                    </div>
                </div>

                <div class="shortcut-row">
                    <div class="shortcut-keys">
                        <kbd>Esc</kbd>
                    </div>
                    <div class="shortcut-desc">
                        Close modals or sidebars.
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQs Section -->
        <div class="help-section" id="faq">
            <h2><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I add a new outlet?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Go to <strong>Outlets</strong> → Click <strong>"Add New Outlet"</strong> → Fill in the details including name, address, and contact information, then save.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I add a new driver?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Navigate to <strong>Drivers</strong> → Click <strong>"Add Driver"</strong> → Fill in the driver's personal details, license information, and assign them to a vehicle.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I create a new trip?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Go to <strong>Trips</strong> → Click <strong>"Create Trip"</strong> → Select the driver, vehicle, route details, and assign parcels to the trip. Save to dispatch the trip.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I reset my password?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Navigate to <strong>Settings → Security</strong> and follow the steps to change your password. You will need to enter your current password and the new password twice to confirm.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>Where can I view delivery reports?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>All delivery, driver, and revenue reports are available in the <strong>Reports</strong> section. You can filter by date range, outlet, or driver to generate custom reports.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I manage vehicles?</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    <p>Go to <strong>Vehicles</strong> to view, add, edit, or remove vehicles. You can assign vehicles to drivers and track their status from this section.</p>
                </div>
            </div>
        </div>

        <!-- App Vitals Section -->
        <div class="help-section" id="vitals">
            <h2><i class="fas fa-heartbeat"></i> App Vitals</h2>
            <div class="vitals-card">
                <h3>Performance</h3>
                <div class="vitals-metric">
                    <span>Page Load Time</span>
                    <span class="metric-value" id="pageLoadTime">Measuring...</span>
                </div>
                <div class="vitals-metric">
                    <span>Browser</span>
                    <span class="metric-value" id="browserInfo">Detecting...</span>
                </div>
            </div>
            <div class="vitals-card">
                <h3>System Status</h3>
                <div class="status-badge online" id="systemStatus">
                    <i class="fas fa-check-circle"></i>
                    <span>All Systems Operational</span>
                </div>
            </div>
            <div class="vitals-card">
                <h3>Connection</h3>
                <div class="vitals-metric">
                    <span>Network Status</span>
                    <span class="metric-value" id="networkStatus">Checking...</span>
                </div>
                <div class="vitals-metric">
                    <span>Connection Type</span>
                    <span class="metric-value" id="connectionType">Detecting...</span>
                </div>
            </div>
        </div>

        <!-- Contact Support Section -->
        <div class="contact-support-section">
            <h2>Still Need Help? Contact Us</h2>
            <p>Our dedicated support team is here to assist you with any advanced queries or issues.</p>
            <div class="contact-buttons">
                <a href="mailto:info@webdevzmt.tech" class="contact-btn">
                    <i class="fas fa-envelope"></i> Email Support
                </a>
                <a href="tel:+260960911672" class="contact-btn">
                    <i class="fas fa-phone-alt"></i> Call Support
                </a>
            </div>
        </div>
    </main>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/company-scripts.js"></script>

    <script>
        // ===== Smooth Scroll =====
        function smoothScrollTo(id) {
            const el = document.getElementById(id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // ===== FAQ Accordion =====
        function toggleFaq(questionEl) {
            const faqItem = questionEl.closest('.faq-item');
            const isActive = faqItem.classList.contains('active');

            // Close all other open FAQs
            document.querySelectorAll('.faq-item.active').forEach(item => {
                if (item !== faqItem) {
                    item.classList.remove('active');
                    const answer = item.querySelector('.faq-answer');
                    answer.style.maxHeight = null;
                }
            });

            // Toggle current
            faqItem.classList.toggle('active');
            const answer = faqItem.querySelector('.faq-answer');
            if (!isActive) {
                answer.style.maxHeight = answer.scrollHeight + 'px';
            } else {
                answer.style.maxHeight = null;
            }
        }

        // ===== App Vitals =====
        window.addEventListener('load', function() {
            // Page Load Time
            setTimeout(function() {
                const perfEntries = performance.getEntriesByType('navigation');
                let loadTime;
                if (perfEntries.length > 0) {
                    loadTime = perfEntries[0].loadEventEnd - perfEntries[0].startTime;
                } else {
                    loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
                }
                const loadTimeEl = document.getElementById('pageLoadTime');
                if (loadTimeEl) {
                    loadTimeEl.textContent = (loadTime / 1000).toFixed(2) + 's';
                }
            }, 100);

            // Browser Info
            const browserEl = document.getElementById('browserInfo');
            if (browserEl) {
                const ua = navigator.userAgent;
                let browser = 'Unknown';
                if (ua.indexOf('Chrome') > -1 && ua.indexOf('Edg') === -1) browser = 'Chrome';
                else if (ua.indexOf('Firefox') > -1) browser = 'Firefox';
                else if (ua.indexOf('Safari') > -1 && ua.indexOf('Chrome') === -1) browser = 'Safari';
                else if (ua.indexOf('Edg') > -1) browser = 'Edge';
                else if (ua.indexOf('Opera') > -1 || ua.indexOf('OPR') > -1) browser = 'Opera';
                browserEl.textContent = browser;
            }

            // Network Status
            function updateNetworkStatus() {
                const statusEl = document.getElementById('networkStatus');
                const connEl = document.getElementById('connectionType');
                const sysEl = document.getElementById('systemStatus');

                if (statusEl) {
                    statusEl.textContent = navigator.onLine ? 'Online' : 'Offline';
                    statusEl.style.color = navigator.onLine ? 'var(--accent-color, #4CAF50)' : '#DC3545';
                }

                if (connEl) {
                    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
                    connEl.textContent = conn ? (conn.effectiveType || 'Unknown').toUpperCase() : 'N/A';
                }

                if (sysEl) {
                    if (navigator.onLine) {
                        sysEl.className = 'status-badge online';
                        sysEl.innerHTML = '<i class="fas fa-check-circle"></i><span>All Systems Operational</span>';
                    } else {
                        sysEl.className = 'status-badge offline';
                        sysEl.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Offline — Check Connection</span>';
                    }
                }
            }

            updateNetworkStatus();
            window.addEventListener('online', updateNetworkStatus);
            window.addEventListener('offline', updateNetworkStatus);
        });
    </script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
