class GlobalSearch {
    constructor() {
        this.isSearching = false;
        this.searchTimeout = null;
        this.currentResults = [];
        this.selectedIndex = -1;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupKeyboardNavigation();
    }
    
    bindEvents() {
        
        const desktopInput = document.getElementById('globalSearchInput');
        const submitBtn = document.getElementById('searchSubmitBtn');
        
        if (desktopInput) {
            desktopInput.addEventListener('input', (e) => {
                this.handleSearchInput(e.target.value);
            });
            
            desktopInput.addEventListener('focus', () => {
                this.showSearchResults();
            });
            
            desktopInput.addEventListener('blur', (e) => {
                
                setTimeout(() => {
                    if (!this.isClickingResult) {
                        this.hideSearchResults();
                    }
                }, 150);
            });
        }
        
        if (submitBtn) {
            submitBtn.addEventListener('click', () => {
                this.performSearch();
            });
        }
        
        
        const searchToggle = document.getElementById('searchToggleBtn');
        const mobileInput = document.getElementById('mobileSearchInput');
        const mobileSubmit = document.getElementById('mobileSearchSubmit');
        const mobileClose = document.getElementById('mobileSearchClose');
        
        if (searchToggle) {
            searchToggle.addEventListener('click', () => {
                this.toggleMobileSearch();
            });
        }
        
        if (mobileInput) {
            mobileInput.addEventListener('input', (e) => {
                this.handleMobileSearchInput(e.target.value);
            });
        }
        
        if (mobileSubmit) {
            mobileSubmit.addEventListener('click', () => {
                this.performMobileSearch();
            });
        }
        
        if (mobileClose) {
            mobileClose.addEventListener('click', () => {
                this.closeMobileSearch();
            });
        }
        
        
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.global-search-container')) {
                this.hideSearchResults();
            }
        });
    }
    
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            if (this.isSearchResultsVisible()) {
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.navigateResults(1);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.navigateResults(-1);
                        break;
                    case 'Enter':
                        e.preventDefault();
                        this.selectCurrentResult();
                        break;
                    case 'Escape':
                        this.hideSearchResults();
                        break;
                }
            }
        });
    }
    
    handleSearchInput(query) {
        clearTimeout(this.searchTimeout);
        
        if (query.trim().length < 2) {
            this.hideSearchResults();
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.performSearch(query);
        }, 300);
    }
    
    handleMobileSearchInput(query) {
        clearTimeout(this.searchTimeout);
        
        if (query.trim().length < 2) {
            this.hideMobileSearchResults();
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.performMobileSearch(query);
        }, 300);
    }
    
    async performSearch(query) {
        if (!query) {
            const input = document.getElementById('globalSearchInput');
            query = input ? input.value.trim() : '';
        }
        
        if (query.length < 2) return;
        
        this.isSearching = true;
        this.showSearchLoading();
        
        try {
            const response = await fetch('./api/global_search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    query: query,
                    types: ['parcels', 'deliveries', 'customers', 'notifications']
                })
            });
            
            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                
                const text = await response.text();
                this.showSearchError('Search failed: Invalid response from server.');
                return;
            }
            if (data.success) {
                this.displaySearchResults(data.results, query);
            } else {
                this.showSearchError(data.error || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showSearchError('Search failed. Please try again.');
        } finally {
            this.isSearching = false;
        }
    }
    
    async performMobileSearch(query) {
        if (!query) {
            const input = document.getElementById('mobileSearchInput');
            query = input ? input.value.trim() : '';
        }
        
        if (query.length < 2) return;
        
        this.isSearching = true;
        this.showMobileSearchLoading();
        
        try {
            const response = await fetch('./api/global_search.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    query: query,
                    types: ['parcels', 'deliveries', 'customers', 'notifications']
                })
            });
            
            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                
                const text = await response.text();
                this.showMobileSearchError('Search failed: Invalid response from server.');
                return;
            }
            if (data.success) {
                this.displayMobileSearchResults(data.results, query);
            } else {
                this.showMobileSearchError(data.error || 'Search failed');
            }
        } catch (error) {
            console.error('Mobile search error:', error);
            this.showMobileSearchError('Search failed. Please try again.');
        } finally {
            this.isSearching = false;
        }
    }
    
    displaySearchResults(results, query) {
        const dropdown = document.getElementById('searchResultsDropdown');
        if (!dropdown) return;
        
        this.currentResults = results;
        this.selectedIndex = -1;
        
        if (results.length === 0) {
            dropdown.innerHTML = `
                <div class="search-no-results">
                    <i class="fas fa-search"></i>
                    <p>No results found for "${this.escapeHtml(query)}"</p>
                </div>
            `;
        } else {
            const groupedResults = this.groupResults(results);
            let html = '<div class="search-results-header">Search Results</div>';
            
            
            html += this.renderQuickFilters(groupedResults);
            
            html += '<div class="search-results-list">';
            
            Object.keys(groupedResults).forEach(type => {
                if (groupedResults[type].length > 0) {
                    groupedResults[type].forEach((result, index) => {
                        html += this.renderSearchResult(result, index);
                    });
                }
            });
            
            html += '</div>';
            html += '<div class="advanced-search-trigger" onclick="globalSearch.openAdvancedSearch()">Advanced Search</div>';
            
            dropdown.innerHTML = html;
        }
        
        this.showSearchResults();
    }
    
    displayMobileSearchResults(results, query) {
        const container = document.getElementById('mobileSearchResults');
        if (!container) return;
        
        if (results.length === 0) {
            container.innerHTML = `
                <div class="search-no-results">
                    <i class="fas fa-search"></i>
                    <p>No results found for "${this.escapeHtml(query)}"</p>
                </div>
            `;
        } else {
            let html = '';
            results.forEach((result, index) => {
                html += this.renderSearchResult(result, index);
            });
            container.innerHTML = html;
        }
        
        this.showMobileSearchResults();
    }
    
    renderQuickFilters(groupedResults) {
        const filters = [
            { key: 'parcels', label: 'Parcels', count: groupedResults.parcels?.length || 0 },
            { key: 'deliveries', label: 'Deliveries', count: groupedResults.deliveries?.length || 0 },
            { key: 'customers', label: 'Customers', count: groupedResults.customers?.length || 0 },
            { key: 'notifications', label: 'Notifications', count: groupedResults.notifications?.length || 0 }
        ];
        
        let html = '<div class="search-quick-filters">';
        filters.forEach(filter => {
            if (filter.count > 0) {
                html += `
                    <button class="search-filter-btn" data-filter="${filter.key}">
                        ${filter.label} (${filter.count})
                    </button>
                `;
            }
        });
        html += '</div>';
        
        return html;
    }
    
    renderSearchResult(result, index) {
        const icon = this.getResultIcon(result.type);
        const title = this.highlightSearchTerms(result.title);
        const subtitle = this.highlightSearchTerms(result.subtitle);
        
        return `
            <div class="search-result-item" data-index="${index}" onclick="globalSearch.selectResult(${index})">
                <div class="search-result-icon ${result.type}">
                    <i class="${icon}"></i>
                </div>
                <div class="search-result-content">
                    <h4 class="search-result-title">${title}</h4>
                    <p class="search-result-subtitle">${subtitle}</p>
                    <span class="search-result-type">${result.type}</span>
                </div>
            </div>
        `;
    }
    
    getResultIcon(type) {
        const icons = {
            'parcel': 'fas fa-box',
            'delivery': 'fas fa-truck',
            'customer': 'fas fa-user',
            'notification': 'fas fa-bell'
        };
        return icons[type] || 'fas fa-search';
    }
    
    groupResults(results) {
        return results.reduce((groups, result) => {
            const type = result.type;
            if (!groups[type]) groups[type] = [];
            groups[type].push(result);
            return groups;
        }, {});
    }
    
    selectResult(index) {
        if (index < 0 || index >= this.currentResults.length) return;
        
        const result = this.currentResults[index];
        this.navigateToResult(result);
        this.hideSearchResults();
        this.closeMobileSearch();
    }
    
    navigateToResult(result) {
        // Check if result has custom onclick handler
        if (result.onclick) {
            eval(result.onclick);
            return;
        }

        // Fallback to URL navigation
        const urls = {
            'parcel': `parcel_details.php?id=${result.id}`,
            'delivery': `delivery_details.php?id=${result.id}`,
            'customer': `customer_details.php?id=${result.id}`,
            'notification': `notifications.php?highlight=${result.id}`
        };

        const url = urls[result.type];
        if (url) {
            window.location.href = url;
        }
    }
    
    
    toggleMobileSearch() {
        const searchBar = document.getElementById('mobileSearchBar');
        const input = document.getElementById('mobileSearchInput');
        
        if (searchBar) {
            searchBar.classList.toggle('show');
            if (searchBar.classList.contains('show') && input) {
                setTimeout(() => input.focus(), 100);
            }
        }
    }
    
    closeMobileSearch() {
        const searchBar = document.getElementById('mobileSearchBar');
        const results = document.getElementById('mobileSearchResults');
        const input = document.getElementById('mobileSearchInput');
        
        if (searchBar) searchBar.classList.remove('show');
        if (results) results.classList.remove('show');
        if (input) input.value = '';
    }
    
    showSearchResults() {
        const dropdown = document.getElementById('searchResultsDropdown');
        if (dropdown) {
            dropdown.classList.add('show');
        }
    }
    
    hideSearchResults() {
        const dropdown = document.getElementById('searchResultsDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }
    
    showMobileSearchResults() {
        const results = document.getElementById('mobileSearchResults');
        if (results) {
            results.classList.add('show');
        }
    }
    
    hideMobileSearchResults() {
        const results = document.getElementById('mobileSearchResults');
        if (results) {
            results.classList.remove('show');
        }
    }
    
    isSearchResultsVisible() {
        const dropdown = document.getElementById('searchResultsDropdown');
        return dropdown && dropdown.classList.contains('show');
    }
    
    showSearchLoading() {
        const dropdown = document.getElementById('searchResultsDropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Searching...</p>
                </div>
            `;
            dropdown.classList.add('show');
        }
    }
    
    showMobileSearchLoading() {
        const results = document.getElementById('mobileSearchResults');
        if (results) {
            results.innerHTML = `
                <div class="search-loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Searching...</p>
                </div>
            `;
            results.classList.add('show');
        }
    }
    
    showSearchError(message) {
        const dropdown = document.getElementById('searchResultsDropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-no-results">
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
        }
    }
    
    showMobileSearchError(message) {
        const results = document.getElementById('mobileSearchResults');
        if (results) {
            results.innerHTML = `
                <div class="search-no-results">
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `;
        }
    }
    
    
    highlightSearchTerms(text) {
        
        
        return this.escapeHtml(text);
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    
    navigateResults(direction) {
        const items = document.querySelectorAll('.search-result-item');
        if (items.length === 0) return;
        
        
        items.forEach(item => item.classList.remove('selected'));
        
        
        this.selectedIndex += direction;
        
        if (this.selectedIndex < 0) {
            this.selectedIndex = items.length - 1;
        } else if (this.selectedIndex >= items.length) {
            this.selectedIndex = 0;
        }
        
        
        items[this.selectedIndex].classList.add('selected');
        items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
    }
    
    selectCurrentResult() {
        if (this.selectedIndex >= 0) {
            this.selectResult(this.selectedIndex);
        }
    }
    
    openAdvancedSearch() {
        
        window.location.href = 'advanced_search.php';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.globalSearch = new GlobalSearch();
});

const style = document.createElement('style');
style.textContent = `
    .search-result-item.selected {
        background: #eff6ff !important;
        border-left: 3px solid var(--primary-color);
    }
`;
document.head.appendChild(style);
