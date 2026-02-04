<?php
session_start();
require_once '../api/supabase-client.php';

// Check if user is logged in and has super_admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Admin - help';
require_once '../includes/header.php';
?>

    <div class="mobile-dashboard">
        <!-- Main Content Area for Help & Support -->
        <main class="main-content">
            <div class="content-container">
                <h1>Admin Help & Support</h1>
                <p class="subtitle">Find answers to common administrative questions or contact our support team.</p>

                <!-- FAQ Section -->
                <div class="faq-section">
                    <h2>Frequently Asked Questions</h2>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I add a new delivery company?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To add a new delivery company, navigate to the "Companies" section from the sidebar and click on the "Add Company" button. Fill in all the required details and save the new company.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How can I manage user roles and permissions?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>User roles and permissions can be managed from the "Users" section. Click on "View" next to a user to edit their details, including their assigned roles and specific permissions.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Where can I find system performance reports?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>All system performance, revenue, and user activity reports are available in the "Reports" section. You can generate new reports or view historical data from there.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I configure global platform settings?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Global platform settings, such as default currency, timezone, and notification preferences, can be adjusted in the "Settings" section. Remember to save your changes after making any modifications.</p>
                        </div>
                    </div>

                </div>

                <!-- Contact Support Section -->
                <div class="contact-support-section">
                    <h2>Still Need Admin Support? Contact Us</h2>
                    <p>Our dedicated admin support team is here to assist you with any advanced queries or issues.</p>
                    <a href="mailto:admin-support@swiftship.com" class="contact-btn">
                        <i class="fas fa-envelope"></i> Email Admin Support
                    </a>
                    <a href="tel:+18005551234" class="contact-btn">
                        <i class="fas fa-phone-alt"></i> Call Admin Support
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // FAQ Accordion functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const faqItem = question.closest('.faq-item');
                faqItem.classList.toggle('active');
            });
        });

        // Function to display a custom message box instead of alert()
        function showMessageBox(message) {
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            `;
            overlay.id = 'messageBoxOverlay';

            const messageBox = document.createElement('div');
            messageBox.style.cssText = `
                background-color: white;
                padding: 2rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                text-align: center;
                max-width: 90%;
                width: 400px;
            `;

            const messageParagraph = document.createElement('p');
            messageParagraph.textContent = message;
            messageParagraph.style.cssText = `
                font-size: 1.25rem;
                margin-bottom: 1.5rem;
                color: #333;
            `;

            const closeButton = document.createElement('button');
            closeButton.textContent = 'OK';
            closeButton.style.cssText = `
                background-color: #3b82f6; /* Blue-600 */
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 0.375rem;
                font-weight: bold;
                cursor: pointer;
                transition: background-color 0.2s;
            `;
            closeButton.onmouseover = function() { closeButton.style.backgroundColor = '#2563eb'; };
            closeButton.onmouseout = function() { closeButton.style.backgroundColor = '#3b82f6'; };
            closeButton.addEventListener('click', function() {
                document.body.removeChild(overlay);
            });

            messageBox.appendChild(messageParagraph);
            messageBox.appendChild(closeButton);
            overlay.appendChild(messageBox);
            document.body.appendChild(overlay);
        }
    </script>
</body>
</html>
