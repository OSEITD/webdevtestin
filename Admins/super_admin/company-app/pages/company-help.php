 <?php
$page_title = 'Company - Help Center';
include __DIR__ . '/../includes/header.php';
 ?>
 
<body class="bg-gray-100 min-h-screen">

    <div class="mobile-dashboard">

        <!-- Main Help Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Help Center</h1>
                <p class="subtitle">Find answers and support</p>
            </div>

            <!-- Help Categories -->
            <div class="help-categories">
                <div class="help-card" onclick="window.location.href='#shortcuts'">
                    <i class="fas fa-keyboard"></i>
                    <h3>Keyboard Shortcuts</h3>
                    <p>Learn time-saving hotkeys</p>
                </div>
                <div class="help-card" onclick="window.location.href='#faq'">
                    <i class="fas fa-question-circle"></i>
                    <h3>FAQs</h3>
                    <p>Common questions answered</p>
                </div>
                <div class="help-card" onclick="window.location.href='#vitals'">
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
                            <kbd> Ctrl/Cmd </kbd> + <kbd> 1-5 </kbd>
                        </div>
                        <div class="shortcut-desc">
                            Switch between Dashboard, Outlets, Drivers, Deliveries, and Reports.
                        </div>
                    </div>

                    <div class="shortcut-row">
                        <div class="shortcut-keys">
                            <kbd> Ctrl/Cmd </kbd> + <kbd> K </kbd>
                        </div>
                        <div class="shortcut-desc">
                            Open the search bar.
                        </div>
                    </div>
                   
                      <div class="shortcut-row">
                        <div class="shortcut-keys">
                            <kbd> Ctrl/Cmd </kbd> + <kbd> M </kbd>
                        </div>
                        <div class="shortcut-desc">
                            Open the Sidebar.
                        </div>
                    </div>

                     
                    <div class="shortcut-row">
                        <div class="shortcut-keys">
                            <kbd> Alt </kbd> + <kbd> ← </kbd> / <kbd> → </kbd>
                        </div>
                        <div class="shortcut-desc">
                            Navigate back/forward in history.
                        </div>
                    </div>


                     <div class="shortcut-row">
                        <div class="shortcut-keys">
                            <kbd> Alt </kbd> + <kbd> N </kbd>
                        </div>
                        <div class="shortcut-desc">
                            Toggles Sidebar Notiffications.
                        </div>
                    </div>

                    
                     <div class="shortcut-row">
                        <div class="shortcut-keys">
                           <kbd>Ctrl</kbd> + <kbd> Alt </kbd> + <kbd> N </kbd>
                        </div>
                        <div class="shortcut-desc">
                            Toggles Main Notiffications.
                        </div>
                    </div>





                    <div class="shortcut-row">
                        <div class="shortcut-keys">
                            <kbd> Esc </kbd>
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
                    <h3>How do I add a new outlet?</h3>
                    <p>Go to <strong>Outlets</strong> → Click <strong>"Add New Outlet"</strong> → Fill in the details.</p>
                </div>
                <div class="faq-item">
                    <h3>How do I reset my password?</h3>
                    <p>Navigate to <strong>Settings → Security</strong> and follow the steps.</p>
                </div>
            </div>

            <!-- App Vitals Section -->
            <div class="help-section" id="vitals">
                <h2><i class="fas fa-heartbeat"></i> App Vitals</h2>
                <div class="vitals-card">
                    <h3>Performance</h3>
                    <div class="vitals-metric">
                        <span>Load Time</span>
                        <span class="metric-value">1.2s</span>
                    </div>
                    <div class="vitals-metric">
                        <span>Memory Usage</span>
                        <span class="metric-value">45MB</span>
                    </div>
                </div>
                <div class="vitals-card">
                    <h3>System Status</h3>
                    <div class="status-badge online">
                        <i class="fas fa-check-circle"></i>
                        <span>All Systems Operational</span>
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

    <!-- Scripts -->
    <script src="../assets/js/company-scripts.js"></script>
</body>
</html>

