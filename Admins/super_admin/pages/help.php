<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - Help & Support';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Help & Support -->
        <main class="main-content">
            <div class="content-container">
                <div class="content-header">
                    <h1>Help & Support</h1>
                    <p class="subtitle">Find answers to common administrative questions or contact our support team.</p>
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
                                Switch between Dashboard, Companies, Users, Outlets, and Reports.
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

                <!-- FAQ Section -->
                <div class="help-section" id="faq">
                    <h2><i class="fas fa-question-circle"></i> Frequently Asked Questions</h2>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I add a new delivery company?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To add a new delivery company, navigate to the "Companies" section from the sidebar and click on the "Add Company" button. Fill in all the required details and save the new company.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How can I manage user roles and permissions?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>User roles and permissions can be managed from the "Users" section. Click on "View" next to a user to edit their details, including their assigned roles and specific permissions.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Where can I find system performance reports?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>All system performance, revenue, and user activity reports are available in the "Reports" section. You can generate new reports or view historical data from there.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I configure global platform settings?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Global platform settings, such as default currency, timezone, and notification preferences, can be adjusted in the "Settings" section. Remember to save your changes after making any modifications.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I add a new admin user?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Go to <strong>Users</strong> → Click <strong>"Add User"</strong> → Select the admin role and fill in the required details. The new admin will receive an email with their login credentials.</p>
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
            </div>
        </main>
    </div>

    <footer class="footer footer-help">
        <p>For CRITICAL SYSTEM HELP: Immediately Contact Webdev.Tech @ 0960911672 / info@webdevzmt.tech</p>
        <div></div>
        <p class="typing">&copy; 2025 WebDevTech. All rights reserved.</p>
    </footer>

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
</body>
</html>
