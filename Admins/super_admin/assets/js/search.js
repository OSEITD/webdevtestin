// Get the base URL for the admin section
const adminBaseUrl = document.querySelector('meta[name="admin-base-url"]')?.content || '';

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.querySelector('.header-search input');
    const searchResults = document.querySelector('.search-results');
    let debounceTimer;

    // Debounce function to prevent too many API calls
    const debounce = (callback, time) => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(callback, time);
    };

    // Handle search input
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            searchResults.classList.remove('show');
            return;
        }

        debounce(() => performSearch(query), 300);
    });

    // Handle clicking outside search results to close
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.header-search')) {
            searchResults.classList.remove('show');
        }
    });

    // Perform search API call and render results
    async function performSearch(query) {
        try {
            const response = await fetch(`${adminBaseUrl}/api/search.php?q=${encodeURIComponent(query)}`, {
                credentials: 'include'
            });
            if (!response.ok) throw new Error('Search failed');
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Search failed');
            
            renderSearchResults(data.results);
            
        } catch (error) {
            console.error('Search error:', error);
            searchResults.innerHTML = `
                <div class="search-group">
                    <div class="search-item">
                        <div class="search-item-content">
                            <div class="search-item-title">Error: ${error.message}</div>
                        </div>
                    </div>
                </div>`;
            searchResults.classList.add('show');
        }
    }

    // Render search results to the DOM
    function renderSearchResults(data) {
        if (!data.companies.length && !data.users.length && !data.parcels.length && !data.pages.length) {
            searchResults.innerHTML = `
                <div class="search-group">
                    <div class="search-item">
                        <div class="search-item-content">
                            <div class="search-item-title">No results found</div>
                        </div>
                    </div>
                </div>`;
            searchResults.classList.add('show');
            return;
        }

        let html = '';

        // Render companies
        if (data.companies.length) {
            html += `
                <div class="search-group">
                    <div class="search-group-title">Companies</div>
                    ${data.companies.map(company => `
                        <a href="${adminBaseUrl}/pages/companies.php?id=${company.id}" class="search-item">
                            <i class="fas fa-building"></i>
                            <div class="search-item-content">
                                <div class="search-item-title">${escapeHtml(company.name)}</div>
                            </div>
                            <span class="search-badge badge-${company.status === 'active' ? 'active' : 'inactive'}">
                                ${company.status}
                            </span>
                        </a>
                    `).join('')}
                </div>`;
        }

        // Render users
        if (data.users.length) {
            html += `
                <div class="search-group">
                    <div class="search-group-title">Users</div>
                    ${data.users.map(user => `
                        <a href="${adminBaseUrl}/pages/users.php?id=${user.id}" class="search-item">
                            <i class="fas fa-user"></i>
                            <div class="search-item-content">
                                <div class="search-item-title">${escapeHtml(user.name)}</div>
                                <div class="search-item-subtitle">${escapeHtml(user.email)}</div>
                            </div>
                            <span class="search-badge badge-${getRoleBadgeClass(user.role)}">
                                ${user.role}
                            </span>
                        </a>
                    `).join('')}
                </div>`;
        }

        // Render parcels
        if (data.parcels.length) {
            html += `
                <div class="search-group">
                    <div class="search-group-title">Parcels</div>
                    ${data.parcels.map(parcel => `
                        <a href="${adminBaseUrl}/pages/parcels.php?id=${parcel.id}" class="search-item">
                            <i class="fas fa-box"></i>
                            <div class="search-item-content">
                                <div class="search-item-title">${escapeHtml(parcel.tracking)}</div>
                                <div class="search-item-subtitle">Status: ${escapeHtml(parcel.status)}</div>
                            </div>
                            <span class="search-badge badge-${getParcelStatusClass(parcel.status)}">
                                ${parcel.status}
                            </span>
                        </a>
                    `).join('')}
                </div>`;
        }

        // Render system pages
        if (data.pages.length) {
            html += `
                <div class="search-group">
                    <div class="search-group-title">System Pages & Features</div>
                    ${data.pages.map(page => `
                        <a href="${adminBaseUrl}${page.url.replace(/^\.\./,'')}" class="search-item">
                            <i class="fas ${page.icon}"></i>
                            <div class="search-item-content">
                                <div class="search-item-title">${escapeHtml(page.title)}</div>
                                <div class="search-item-subtitle">${escapeHtml(page.description)}</div>
                            </div>
                        </a>
                    `).join('')}
                </div>`;
        }

        searchResults.innerHTML = html;
        searchResults.classList.add('show');
    }

    // Helper function to escape HTML and prevent XSS
    function escapeHtml(unsafe) {
        if (unsafe == null) return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Helper function to get badge class based on parcel status
    function getParcelStatusClass(status) {
        switch (status.toLowerCase()) {
            case 'delivered':
                return 'active';
            case 'in transit':
            case 'processing':
                return 'pending';
            default:
                return 'inactive';
        }
    }

    // Helper function to get badge class based on user role
    function getRoleBadgeClass(role) {
        switch (role.toLowerCase()) {
            case 'super_admin':
                return 'active';
            case 'admin':
                return 'pending';
            default:
                return 'inactive';
        }
    }
});