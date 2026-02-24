<?php

ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/security_headers.php';
SecurityHeaders::apply();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - WebDev Parcel Management</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
    <link rel="stylesheet" href="../css/help.css">
    <style>
        /* Page container */
        .content-container { max-width: 1400px; margin: 20px auto; padding: 0 12px; }

        /* match parcel pool header look */
        .page-header {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin: 20px auto;
            box-shadow: 0 10px 30px rgba(46, 13, 42, 0.3);
            max-width: 1400px;
            text-align: center;
        }
        .page-header .page-title-section h1,
        .page-header .page-title-section .page-subtitle {
            color: white;
        }
        .page-header .page-title-section h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0;
        }
        .page-header .page-title-section .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
            margin: 0;
        }

        /* Search input (use site-wide classes for consistent look) */
        .search-input-container { position: relative; }
        .search-input-container i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6b7280; }
        .search-input-container input { width: 100%; padding: 12px 16px 12px 44px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 16px; background: #fff; }
        .search-input-container input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.06); }

        /* FAQ/Guide styles */
        .faq-item.hidden { display: none; }
        .no-results { text-align: center; padding: 30px 20px; color: #6b7280; }

        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #e5e7eb; }
        .section-header h2 { margin: 0; color: #1f2937; }

        .contact-btn { background: #f3f4f6; color: #374151; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 12px; font-weight: 500; transition: all 0.12s ease; border: 1px solid #e5e7eb; }
        .contact-btn:hover { background: #e5e7eb; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); }

        /* Larger FAQ expansion and smoother animation */
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.35s ease, padding 0.25s ease; }
        .faq-item.active .faq-answer { max-height: 1000px; padding-top: 15px; }

        /* Guides styling */
        .guide-section { margin-top: 24px; }
        .guide-section .faq-item { background: #f0f9ff; border: 1px solid #e0f2fe; }
        .guide-section .faq-question { background: #e0f2fe; }

        /* Stats cards */
        .stats-container { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-card { background: white; padding: 16px 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06); min-width: 120px; text-align: center; }
        .stat-number { font-size: 22px; font-weight: 700; color: #3b82f6; }
        .stat-label { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    </style> 
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/sidebar.php'; ?>
    <div class="menu-overlay" id="menuOverlay"></div>

    <main class="main-content">
        <div class="page-header">
            <div class="page-header-content" style="display:flex;justify-content:center;align-items:center;flex-wrap:wrap;gap:1rem;position:relative;">
                <div class="page-title-section">
                    <h1 class="page-title">
                        <i class="fas fa-question-circle"></i>
                        Help & Support
                    </h1>
                    <p class="page-subtitle">Find answers to common questions or contact our support team</p>
                </div>

            </div>
        </div>
<?php if (in_array($current_user['role'] ?? 'customer', ['admin', 'outlet_manager', 'super_admin'])): ?>
    <div class="manage-btn-container" style="text-align:right; margin: 20px auto 20px auto; max-width:1400px; padding: 0 24px;">
        <a href="manage_help.php" class="btn btn-primary">
            <i class="fas fa-cogs"></i> Manage Content
        </a>
    </div>
<?php endif; ?>

        <div class="content-container">

                <div class="filter-bar" style="margin-bottom:16px;">
                    <div class="search-input-container" style="position:relative; flex:1;">
                        <i class="fas fa-search" style="position:absolute; left:16px; top:50%; transform:translateY(-50%); color:#6b7280;"></i>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search help articles...">
                    </div>
                </div>

                <?php if (in_array($current_user['role'] ?? 'customer', ['admin', 'outlet_manager', 'super_admin'])): ?>
                <div class="stats-container" id="helpStats" style="display: none;">
                    <div class="stat-card">
                        <div class="stat-number" id="faqCount">0</div>
                        <div class="stat-label">FAQ Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="guideCount">0</div>
                        <div class="stat-label">Guides</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="contactCount">0</div>
                        <div class="stat-label">Contact Items</div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="faq-section">
                    <div class="section-header">
                        <i class="fas fa-question-circle"></i>
                        <h2>Frequently Asked Questions</h2>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I create a new parcel?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To create a new parcel, navigate to the "Create New Parcel" section from the sidebar. Fill in all the required sender and recipient details, parcel weight, and description. Select a delivery option and click "Create Parcel".</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How can I track a parcel?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>You can track a parcel by its unique tracking ID. Go to the "Parcels at Outlet" page, find the parcel in the list, and click "View Details" to see its current status and history.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I assign parcels to a driver?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>On the "Assign Parcels" page, first select an available driver from the dropdown list. Then, check the boxes next to the parcels you wish to assign from the "Available Parcels" list. Finally, click "Assign Selected Parcels".</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What do I do if a parcel is returned?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>If a parcel is returned, you should update its status to "Returned" in the system. You can do this from the parcel's detail page. Further actions, such as contacting the sender, can then be initiated.</p>
                        </div>
                    </div>

                </div>

                <div class="contact-support-section">
                    <div class="section-header">
                        <i class="fas fa-headset"></i>
                        <h2>Still Need Help? Contact Support</h2>
                    </div>
                    <p>Our support team is available to assist you with any issues or questions.</p>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="mailto:support@deliverypro.com" class="contact-btn">
                            <i class="fas fa-envelope"></i> Email Support
                        </a>
                        <a href="tel:+15551234567" class="contact-btn">
                            <i class="fas fa-phone-alt"></i> Call Support
                        </a>
                    </div>
                </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');
        const menuOverlay = document.getElementById('menuOverlay');

        function toggleMenu() {
            sidebar.classList.toggle('show');
            menuOverlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }

        menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu();
        });

        closeMenu.addEventListener('click', toggleMenu);
        menuOverlay.addEventListener('click', toggleMenu);

        document.querySelectorAll('.menu-items a').forEach(item => {
            item.addEventListener('click', toggleMenu);
        });

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
                background-color: #3b82f6;
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 0.375rem;
                font-weight: bold;
                cursor: pointer;
                transition: background-color 0.2s;
            `;
            closeButton.onmouseover = () => closeButton.style.backgroundColor = '#2563eb';
            closeButton.onmouseout = () => closeButton.style.backgroundColor = '#3b82f6';
            closeButton.addEventListener('click', () => {
                document.body.removeChild(overlay);
            });

            messageBox.appendChild(messageParagraph);
            messageBox.appendChild(closeButton);
            overlay.appendChild(messageBox);
            document.body.appendChild(overlay);
        }

        async function loadHelpContent(sectionType = null) {
            try {
                const url = sectionType
                    ? `../api/fetch_help_content.php?section_type=${sectionType}`
                    : '../api/fetch_help_content.php';

                const response = await fetch(url);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response');
                }

                const data = await response.json();

                if (data.success) {
                    renderHelpContent(data.data);
                    updateStatistics(data.data);
                } else {
                    showMessageBox('Error loading help content: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Network Error:', error);
                if (error.message.includes('HTTP error! status: 404')) {
                    showMessageBox('Help content API not found. Please contact administrator.');
                } else if (error.message.includes('non-JSON response')) {
                    showMessageBox('Server error: Invalid response format. Please try again.');
                } else {
                    showMessageBox('Failed to load help content. Please check your connection and try again.');
                }
            }
        }

        function renderHelpContent(helpItems) {
            const faqSection = document.querySelector('.faq-section');
            const contactSection = document.querySelector('.contact-support-section');

            faqSection.innerHTML = `
                <div class="section-header">
                    <i class="fas fa-question-circle"></i>
                    <h2>Frequently Asked Questions</h2>
                </div>
            `;

            const faqItems = helpItems.filter(item => item.section_type === 'faq');
            const guideItems = helpItems.filter(item => item.section_type === 'guide');
            const contactItems = helpItems.filter(item => item.section_type === 'contact');

            if (faqItems.length > 0) {
                faqItems.forEach(item => {
                    const faqItem = createFaqItem(item);
                    faqSection.appendChild(faqItem);
                });
            } else {
                faqSection.innerHTML += `
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I create a new parcel?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>To create a new parcel, navigate to the "Create New Parcel" section from the sidebar. Fill in all the required sender and recipient details, parcel weight, and description. Select a delivery option and click "Create Parcel".</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How can I track a parcel?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>You can track a parcel by its unique tracking ID. Go to the "Parcels at Outlet" page, find the parcel in the list, and click "View Details" to see its current status and history.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>How do I assign parcels to a driver?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>On the "Assign Parcels" page, first select an available driver from the dropdown list. Then, check the boxes next to the parcels you wish to assign from the "Available Parcels" list. Finally, click "Assign Selected Parcels".</p>
                        </div>
                    </div>
                `;
            }

            if (guideItems.length > 0) {
                const guideSection = document.createElement('div');
                guideSection.className = 'faq-section guide-section';
                guideSection.innerHTML = `
                    <div class="section-header">
                        <i class="fas fa-book"></i>
                        <h2>User Guides</h2>
                    </div>
                `;

                guideItems.forEach(item => {
                    const guideItem = createFaqItem(item);
                    guideSection.appendChild(guideItem);
                });

                faqSection.parentNode.insertBefore(guideSection, contactSection);
            }

            if (contactItems.length > 0) {
                const contactItem = contactItems[0];
                updateContactSection(contactItem);
            }

            attachFaqListeners();
        }

        function createFaqItem(item) {
            const faqItem = document.createElement('div');
            faqItem.className = 'faq-item';
            faqItem.setAttribute('data-id', item.id);

            const question = document.createElement('div');
            question.className = 'faq-question';
            question.innerHTML = `
                <span>${item.title}</span>
                <i class="fas fa-chevron-down"></i>
            `;

            const answer = document.createElement('div');
            answer.className = 'faq-answer';

            let content = '';
            if (item.content) {
                if (typeof item.content === 'object') {
                    if (item.content.answer) {
                        content = item.content.answer;
                    } else if (item.content.text) {
                        content = item.content.text;
                    } else if (item.content.description) {
                        content = item.content.description;
                    } else if (item.content.content) {
                        content = item.content.content;
                    } else {
                        const values = Object.values(item.content).filter(v => typeof v === 'string');
                        content = values.length > 0 ? values[0] : JSON.stringify(item.content);
                    }
                } else if (typeof item.content === 'string') {
                    try {
                        const parsed = JSON.parse(item.content);
                        if (parsed.answer) content = parsed.answer;
                        else if (parsed.text) content = parsed.text;
                        else content = item.content;
                    } catch {
                        content = item.content;
                    }
                }
            }

            answer.innerHTML = `<p>${content}</p>`;

            if (item.updated_at || item.created_at) {
                const timestamp = item.updated_at || item.created_at;
                const date = new Date(timestamp).toLocaleDateString();
                answer.innerHTML += `<small style="color: #6b7280; font-style: italic; margin-top: 8px; display: block;">Last updated: ${date}</small>`;
            }

            faqItem.appendChild(question);
            faqItem.appendChild(answer);

            return faqItem;
        }

        function updateContactSection(contactItem) {
            const contactSection = document.querySelector('.contact-support-section');

            if (contactItem.content) {
                const content = contactItem.content;

                const title = contactSection.querySelector('h2');
                if (content.title) {
                    title.textContent = content.title;
                } else {
                    title.innerHTML = '<i class="fas fa-headset"></i> ' + (contactItem.title || 'Still Need Help? Contact Support');
                }

                const description = contactSection.querySelector('p');
                if (content.description) {
                    description.textContent = content.description;
                } else if (content.text) {
                    description.textContent = content.text;
                }

                let buttons = contactSection.querySelectorAll('.contact-btn');

                buttons.forEach(btn => btn.remove());

                const buttonContainer = document.createElement('div');
                buttonContainer.style.cssText = 'display: flex; flex-direction: column; gap: 12px; margin-top: 16px;';

                if (content.email) {
                    const emailBtn = document.createElement('a');
                    emailBtn.href = `mailto:${content.email}`;
                    emailBtn.className = 'contact-btn';
                    emailBtn.innerHTML = `<i class="fas fa-envelope"></i> ${content.email_label || content.email}`;
                    buttonContainer.appendChild(emailBtn);
                }

                if (content.phone) {
                    const phoneBtn = document.createElement('a');
                    phoneBtn.href = `tel:${content.phone}`;
                    phoneBtn.className = 'contact-btn';
                    phoneBtn.innerHTML = `<i class="fas fa-phone-alt"></i> ${content.phone_label || content.phone}`;
                    buttonContainer.appendChild(phoneBtn);
                }

                if (content.whatsapp) {
                    const whatsappBtn = document.createElement('a');
                    whatsappBtn.href = `https://wa.me/${content.whatsapp.replace(/\D/g, '')}`;
                    whatsappBtn.target = '_blank';
                    whatsappBtn.className = 'contact-btn';
                    whatsappBtn.style.background = '#25d366';
                    whatsappBtn.innerHTML = `<i class="fab fa-whatsapp"></i> ${content.whatsapp_label || 'WhatsApp'}`;
                    buttonContainer.appendChild(whatsappBtn);
                }

                if (content.website) {
                    const websiteBtn = document.createElement('a');
                    websiteBtn.href = content.website;
                    websiteBtn.target = '_blank';
                    websiteBtn.className = 'contact-btn';
                    websiteBtn.innerHTML = `<i class="fas fa-globe"></i> ${content.website_label || 'Visit Website'}`;
                    buttonContainer.appendChild(websiteBtn);
                }

                if (buttonContainer.children.length === 0) {
                    const defaultEmail = document.createElement('a');
                    defaultEmail.href = 'mailto:support@deliverypro.com';
                    defaultEmail.className = 'contact-btn';
                    defaultEmail.innerHTML = '<i class="fas fa-envelope"></i> Email Support';
                    buttonContainer.appendChild(defaultEmail);

                    const defaultPhone = document.createElement('a');
                    defaultPhone.href = 'tel:+15551234567';
                    defaultPhone.className = 'contact-btn';
                    defaultPhone.innerHTML = '<i class="fas fa-phone-alt"></i> Call Support';
                    buttonContainer.appendChild(defaultPhone);
                }

                contactSection.appendChild(buttonContainer);
            }
        }

        function attachFaqListeners() {
            document.querySelectorAll('.faq-question').forEach(question => {
                question.addEventListener('click', () => {
                    const faqItem = question.closest('.faq-item');
                    faqItem.classList.toggle('active');
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadHelpContent();
            setupSearch();
        });

        function setupSearch() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                filterHelpItems(searchTerm);
            });
        }

        function filterHelpItems(searchTerm) {
            const faqItems = document.querySelectorAll('.faq-item');
            let visibleCount = 0;

            faqItems.forEach(item => {
                const title = item.querySelector('.faq-question span')?.textContent.toLowerCase() || '';
                const content = item.querySelector('.faq-answer')?.textContent.toLowerCase() || '';

                if (searchTerm === '' || title.includes(searchTerm) || content.includes(searchTerm)) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                }
            });

            updateNoResultsMessage(visibleCount, searchTerm);
        }

        function updateNoResultsMessage(visibleCount, searchTerm) {
            const existingMessage = document.querySelector('.no-results');
            if (existingMessage) existingMessage.remove();

            if (visibleCount === 0 && searchTerm !== '') {
                const noResultsDiv = document.createElement('div');
                noResultsDiv.className = 'no-results';
                noResultsDiv.innerHTML = `
                    <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 16px; opacity: 0.5;"></i>
                    <p style="font-size: 1.1rem; margin-bottom: 8px;">No results found for "${searchTerm}"</p>
                    <p style="font-size: 0.9rem; opacity: 0.7;">Try different keywords or browse the categories below.</p>
                `;

                const faqSection = document.querySelector('.faq-section');
                faqSection.appendChild(noResultsDiv);
            }
        }

        function updateStatistics(helpItems) {
            const faqCount = helpItems.filter(item => item.section_type === 'faq').length;
            const guideCount = helpItems.filter(item => item.section_type === 'guide').length;
            const contactCount = helpItems.filter(item => item.section_type === 'contact').length;

            const statsContainer = document.getElementById('helpStats');
            if (statsContainer) {
                document.getElementById('faqCount').textContent = faqCount;
                document.getElementById('guideCount').textContent = guideCount;
                document.getElementById('contactCount').textContent = contactCount;

                if (faqCount > 0 || guideCount > 0 || contactCount > 0) {
                    statsContainer.style.display = 'flex';
                }
            }
        }
    </script>
    
    <script src="../assets/js/sidebar-toggle.js"></script>
</main>

</body>
</html>
<?php ob_end_flush(); ?>