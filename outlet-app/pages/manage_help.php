<?php
require_once '../includes/auth_guard.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$current_user = getCurrentUser();

$user_role = $current_user['role'] ?? 'customer';
$is_admin = in_array($user_role, ['admin', 'outlet_manager', 'super_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Help Content</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css">
        <link rel="stylesheet" href="../css/help.css">

    <style>
        .admin-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .admin-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .admin-header h2 {
            color: #1f2937;
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .help-item-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            background: #f9fafb;
        }

        .help-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .help-item-meta {
            display: flex;
            gap: 16px;
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .section-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .section-badge.faq {
            background: #dbeafe;
            color: #1e40af;
        }

        .section-badge.guide {
            background: #d1fae5;
            color: #065f46;
        }

        .section-badge.contact {
            background: #fef3c7;
            color: #92400e;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 600px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 24px;
        }

        .tab {
            padding: 12px 24px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php if (!$is_admin): ?>
        <div style="padding: 40px; text-align: center;">
            <h1>Access Denied</h1>
            <p>You don't have permission to access this page.</p>
            <a href="help.php" class="btn btn-primary">Go to Help Page</a>
        </div>
    <?php else: ?>

    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>
        <div class="menu-overlay" id="menuOverlay"></div>

        <main class="main-content">
            <div class="content-container">
                <div class="admin-section">
                    <div class="admin-header">
                        <h2>
                            <i class="fas fa-cogs"></i>
                            Manage Help Content
                        </h2>
                        <div style="display: flex; gap: 12px;">
                            <a href="help.php" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> View Help Page
                            </a>
                            <button id="addHelpBtn" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Content
                            </button>
                        </div>
                    </div>

                    <div class="tabs">
                        <button class="tab active" data-tab="faq">
                            <i class="fas fa-question-circle"></i> FAQ
                        </button>
                        <button class="tab" data-tab="guide">
                            <i class="fas fa-book"></i> Guides
                        </button>
                        <button class="tab" data-tab="contact">
                            <i class="fas fa-phone"></i> Contact
                        </button>
                    </div>

                    <div id="faq-content" class="tab-content active">
                        <div id="faq-items"></div>
                    </div>

                    <div id="guide-content" class="tab-content">
                        <div id="guide-items"></div>
                    </div>

                    <div id="contact-content" class="tab-content">
                        <div id="contact-items"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentSection = 'faq';
        let helpItems = {};

        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.dataset.tab;
                switchTab(tabName);
            });
        });

        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(`${tabName}-content`).classList.add('active');

            currentSection = tabName;
        }

        async function loadHelpContent() {
            try {
                const response = await fetch('../api/fetch_help_content.php');
                const data = await response.json();

                if (data.success) {
                    helpItems = {
                        faq: data.data.filter(item => item.section_type === 'faq'),
                        guide: data.data.filter(item => item.section_type === 'guide'),
                        contact: data.data.filter(item => item.section_type === 'contact')
                    };

                    renderHelpItems();
                } else {
                    showMessage('Error loading help content: ' + data.error, 'error');
                }
            } catch (error) {
                showMessage('Failed to load help content', 'error');
            }
        }

        function renderHelpItems() {
            ['faq', 'guide', 'contact'].forEach(section => {
                const container = document.getElementById(`${section}-items`);
                const items = helpItems[section] || [];

                if (items.length === 0) {
                    container.innerHTML = `<p style="color: #6b7280; text-align: center; padding: 40px;">No ${section.toUpperCase()} items found. Click "Add New Content" to create one.</p>`;
                    return;
                }

                container.innerHTML = items.map(item => `
                    <div class="help-item-card">
                        <div class="help-item-header">
                            <div>
                                <h3 style="margin: 0 0 8px 0; color: #1f2937;">${item.title}</h3>
                                <div class="help-item-meta">
                                    <span class="section-badge ${item.section_type}">${item.section_type.toUpperCase()}</span>
                                    <span><i class="fas fa-calendar"></i> ${new Date(item.created_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn btn-secondary" onclick="editHelpItem('${item.id}')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-danger" onclick="deleteHelpItem('${item.id}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div style="color: #6b7280; font-size: 0.875rem;">
                            ${typeof item.content === 'object' && item.content.answer
                                ? item.content.answer.substring(0, 150) + '...'
                                : (typeof item.content === 'string' ? item.content.substring(0, 150) + '...' : '')}
                        </div>
                    </div>
                `).join('');
            });
        }

        document.getElementById('addHelpBtn').addEventListener('click', () => {
            showHelpModal();
        });

        function showHelpModal(item = null) {
            const isEdit = item !== null;

            const modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.innerHTML = `
                <div class="modal">
                    <h2 style="margin: 0 0 24px 0; color: #1f2937;">
                        <i class="fas fa-${isEdit ? 'edit' : 'plus'}"></i>
                        ${isEdit ? 'Edit' : 'Add New'} Help Content
                    </h2>

                    <form id="helpForm">
                        <div class="form-group">
                            <label for="helpTitle">Title *</label>
                            <input type="text" id="helpTitle" class="form-control" value="${item?.title || ''}" required>
                        </div>

                        <div class="form-group">
                            <label for="helpSection">Section Type *</label>
                            <select id="helpSection" class="form-control" required>
                                <option value="faq" ${item?.section_type === 'faq' ? 'selected' : ''}>FAQ</option>
                                <option value="guide" ${item?.section_type === 'guide' ? 'selected' : ''}>Guide</option>
                                <option value="contact" ${item?.section_type === 'contact' ? 'selected' : ''}>Contact</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="helpContent">Content *</label>
                            <textarea id="helpContent" class="form-control" rows="8" required placeholder="Enter the content...">${item?.content?.answer || item?.content?.text || (typeof item?.content === 'string' ? item.content : '') || ''}</textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> ${isEdit ? 'Update' : 'Save'}
                            </button>
                        </div>
                    </form>
                </div>
            `;

            document.body.appendChild(modal);

            document.getElementById('helpForm').addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = {
                    title: document.getElementById('helpTitle').value,
                    section_type: document.getElementById('helpSection').value,
                    content: {
                        answer: document.getElementById('helpContent').value
                    }
                };

                try {
                    let url = '../api/fetch_help_content.php';
                    let method = 'POST';

                    if (isEdit) {
                        url += `?id=${item.id}`;
                        method = 'PATCH';
                    }

                    const response = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(formData)
                    });

                    const result = await response.json();

                    if (result.success) {
                        closeModal();
                        showMessage(isEdit ? 'Content updated successfully!' : 'Content added successfully!', 'success');
                        loadHelpContent();
                    } else {
                        showMessage('Error: ' + result.error, 'error');
                    }
                } catch (error) {
                    showMessage('Failed to save content', 'error');
                }
            });
        }

        function editHelpItem(id) {
            const allItems = [...(helpItems.faq || []), ...(helpItems.guide || []), ...(helpItems.contact || [])];
            const item = allItems.find(i => i.id === id);
            if (item) {
                showHelpModal(item);
            }
        }

        async function deleteHelpItem(id) {
            if (!confirm('Are you sure you want to delete this help item?')) {
                return;
            }

            try {
                const response = await fetch(`../api/fetch_help_content.php?id=${id}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showMessage('Content deleted successfully!', 'success');
                    loadHelpContent();
                } else {
                    showMessage('Error: ' + result.error, 'error');
                }
            } catch (error) {
                showMessage('Failed to delete content', 'error');
            }
        }

        function closeModal() {
            const modal = document.querySelector('.modal-overlay');
            if (modal) {
                document.body.removeChild(modal);
            }
        }

        function showMessage(message, type = 'info') {
            const colors = {
                success: '#10b981',
                error: '#ef4444',
                info: '#3b82f6'
            };

            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type]};
                color: white;
                padding: 16px 20px;
                border-radius: 8px;
                z-index: 1001;
                font-weight: 500;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                max-width: 400px;
                word-wrap: break-word;
            `;
            messageDiv.textContent = message;

            document.body.appendChild(messageDiv);

            setTimeout(() => {
                if (document.body.contains(messageDiv)) {
                    document.body.removeChild(messageDiv);
                }
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadHelpContent();
        });

        const menuBtn = document.getElementById('menuBtn');
        const closeMenu = document.getElementById('closeMenu');
        const sidebar = document.getElementById('sidebar');
        const menuOverlay = document.getElementById('menuOverlay');

        function toggleMenu() {
            sidebar.classList.toggle('show');
            menuOverlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }

        if (menuBtn) menuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu();
        });

        if (closeMenu) closeMenu.addEventListener('click', toggleMenu);
        if (menuOverlay) menuOverlay.addEventListener('click', toggleMenu);

        document.querySelectorAll('.menu-items a').forEach(item => {
            item.addEventListener('click', toggleMenu);
        });
    </script>

    <?php endif; ?>
</body>
</html>
