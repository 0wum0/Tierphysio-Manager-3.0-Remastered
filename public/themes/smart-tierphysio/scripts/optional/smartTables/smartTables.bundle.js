/**
 * SmartTables - A modern, responsive table library
 * @copyright SmartAdmin WebApp Copyright 2025-2026
 * @author Sunnyat Ahmmed
 * @homepage https://getwebora.com
 * @version 1.2.7
 */

export class SmartTables {
    /**
     * @param {string|HTMLElement} selector - Table element or selector
     * @param {Object} options - Configuration options
     */
    constructor(selector, options) {
        // Default options
        this.defaults = {
            perPage: 10,
            search: true,
            sort: true,
            pagination: true,
            export: false,
            import: false,
            print: false,
            addRecord: false,
            deleteRecord: false,
            editRecord: false, 
            loading: {
                enabled: true,
                duration: 0,
                minDuration: 300
            },
            responsive: {
                enabled: false,
                breakpoint: 768,
                columnPriorities: {
                    0: 1,
                    1: 2,
                    2: 3,
                    3: 4,
                    4: 5,
                    5: 6
                },
                details: {
                    type: 'column',
                    target: 0
                }
            },
            debug: false,
            fuzzyMatch: {
                threshold: 0,
                minMatchLength: 3,
                multiWordThreshold: 0,
                maxDistance: 1
            },
            classes: {
                wrapper: 'st-wrapper',
                table: 'st-table table table-striped table-hover',
                toolbar: 'st-toolbar d-flex justify-content-between mb-3',
                search: 'st-search form-control',
                pagination: 'st-pagination pagination justify-content-center',
                export: 'st-export btn-group'
            },
            data: {
                type: null,
                source: null,
                columns: [],
                processing: false,
                serverSide: false,
                method: 'GET',
                headers: {},
                params: {},
                parser: null,
                cacheDuration: 300000, // 5 minutes cache lifetime
                prefetch: true
            },
            hooks: {
                beforeInit: null,
                afterInit: null,
                beforeDestroy: null,
                afterDestroy: null,
                beforeDataLoad: null,
                afterDataLoad: null,
                beforeDraw: null,
                afterDraw: null,
                beforeSave: null,
                afterSave: null,
                onSort: null,
                onFilter: null,
                onPaginate: null,
                onExport: null,
                onImport: null,
                onResize: null,
                onExpandedRowInit: null,
                beforeDelete: null,
                afterDelete: null,
                beforeEdit: null,
                afterEdit: null,
                onEditModalCreated: null,
                onEditModalBeforeShow: null,
                onEditModalAfterShow: null,
                onEditCancelled: null,
                onEditDataCollected: null,
                beforeApplyEdit: null,
                onProcessEditData: null,
                onEditSuccess: null,
                onEditError: null,
                onDeleteModalCreated: null,
                onDeleteModalBeforeShow: null,
                onDeleteModalAfterShow: null,
                onDeleteCancelled: null,
                beforeProcessDelete: null,
                onDeleteSuccess: null,
                onDeleteError: null,
                onDeleteFetchOptions: null,
                onEditFetchOptions: null,
                beforeAddRecord: null,
                afterAddRecord: null,
                onAddModalCreated: null,
                onAddModalBeforeShow: null,
                onAddModalAfterShow: null,
                onAddCancelled: null,
                onAddDataCollected: null,
                beforeAddRecordSave: null,
                onAddRecordSuccess: null,
                onAddRecordError: null,
                onAddRequestOptions: null
            },
            plugins: []
        };
    
        // Handle responsive option
        if (typeof options.responsive === 'boolean') {
            options.responsive = { enabled: options.responsive };
        }
    
        this.options = { ...this.defaults, ...options };
    
        // Ensure data.columns is always an array
        if (!Array.isArray(this.options.data.columns)) {
            this.options.data.columns = [];
        }
    
        this.table = typeof selector === 'string' ? document.getElementById(selector) : selector;
        this.table.__smartTable = this;
        this.currentPage = 1;
        this.rows = [];
        this.filteredRows = [];
        this.hiddenColumns = [];
        this.columnWidths = [];
        this.plugins = [];
        this.responsiveColumns = [];
        this.ajaxCache = new Map(); // Initialize cache for prefetching
    
        // Server-side specific properties
        this.searchQuery = '';
        this.currentSortColumn = undefined;
            this.currentSortDirection = null;
        this.totalRows = 0;
        this.isLoadingAjax = false; // Add flag to prevent simultaneous AJAX calls
        this.isTableInitialized = false; // Track if table is fully initialized
        this.hasDNoneBeenRemoved = false; // Track if we've removed the d-none class already
    
        // Define handleSearch method for server-side search
        this.serverSideHandleSearch = function(value) {
            this.searchQuery = value;
    
            if (this.options.loading.enabled) {
                this.wrapper.classList.add('st-loading');
                this.wrapper.classList.add('st-ajax-loading');
    
                if (!this.wrapper.querySelector('.st-loading-spinner')) {
                    const spinner = document.createElement('div');
                    spinner.className = 'st-loading-spinner';
                    this.wrapper.appendChild(spinner);
                }
            }
    
            this.currentPage = 1; // Reset to first page on new search
            this.loadAjax(); // Fetch new data from server with search query
            this.callHook('onFilter', value, null); // Call hook without filtered rows (server handles it)
        };
    
        // Apply debouncing using SmartTables.debounce
        this.debouncedServerSideHandleSearch = SmartTables.debounce(this.serverSideHandleSearch.bind(this), 300);
        this.debouncedLoadAjax = SmartTables.debounce(this.loadAjax.bind(this), 250);
        this.lastRequestTime = 0;
    
        // Replace handleSearch with a router function that selects the appropriate implementation
        this.handleSearch = function(value) {
            this.log('SmartTables search initiated with value:', value);
            
            if (this.options.data.serverSide === true) {
                // Use server-side implementation
                this.log('Using server-side search implementation');
                this.debouncedServerSideHandleSearch(value);
            } else {
                // Use client-side advanced implementation from prototype
                this.log('Using client-side search with fuzzy matching');
                // Call the prototype method directly, binding "this" context
                SmartTables.prototype.handleSearch.call(this, value);
            }
        }.bind(this);
    
        this.abortController = null;
    
        // Log the options to verify cacheDuration
        this.log('Final options after constructor:', this.options);
        this.log('cacheDuration after constructor:', this.options.data.cacheDuration);
        this.log('prefetch setting after constructor:', this.options.data.prefetch);
    
        this.init();
    }

    // Utility functions as static methods
    static extend(target, ...sources) {
        sources.forEach(source => {
            if (source) {
                Object.keys(source).forEach(prop => {
                    if (source.hasOwnProperty(prop)) {
                        target[prop] = source[prop];
                    }
                });
            }
        });
        return target;
    }

    static debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    static createNode(html) {
        const div = document.createElement('div');
        div.innerHTML = html.trim();
        return div.firstChild;
    }

    /**
     * Recalculate responsive behavior for all SmartTables instances on the page
     * Useful for tables that were initially hidden (e.g., in tabs) and need to recalculate
     * their responsive layout when they become visible
     * 
     * @param {string|HTMLElement} [selector] - Optional selector to target specific tables
     */
    static recalculateResponsive(selector = null) {
        let tables = [];
        
        if (selector) {
            // Target specific tables
            const elements = typeof selector === 'string' 
                ? document.querySelectorAll(selector)
                : [selector];
            
            elements.forEach(element => {
                if (element && element.__smartTable) {
                    tables.push(element.__smartTable);
                }
            });
        } else {
            // Find all SmartTables instances on the page
            const allTables = document.querySelectorAll('table');
            allTables.forEach(table => {
                if (table.__smartTable) {
                    tables.push(table.__smartTable);
                }
            });
        }
        
        // Recalculate responsive behavior for each table
        tables.forEach(smartTable => {
            try {
                // Force recalculation by clearing hidden state and recalculating
                smartTable.isContainerHidden = false;
                
                if (smartTable.options.responsive && smartTable.options.responsive.enabled) {
                    smartTable.log('Recalculating responsive layout for table:', smartTable.table.id || 'unnamed');
                    smartTable.checkResponsiveDisplay();
                }
            } catch (error) {
                console.error('Error recalculating responsive layout for table:', error);
            }
        });
        
        return tables.length;
    }

    init() {
        this.callHook('beforeInit');
    
        // Ensure prefetch is enabled by default
        if (this.options.data.prefetch === undefined) {
            this.options.data.prefetch = true;
            this.log('🔄 INIT: Setting prefetch to true because it was undefined');
        }
        
        // Set initial table structure
        this.setupWrapper();
    
        // Disable table hover if option is set
        if (this.options.hoverRows === false) {
            this.table.classList.add('table-hover-disable');
        }
    
        // Start loading data
        if (this.options.loading.enabled) {
            this.wrapper.classList.add('st-loading');
            var spinner = document.createElement('div');
            spinner.className = 'st-loading-spinner';
            this.wrapper.appendChild(spinner);
        }
    
        // Load data from type specified in options or existing table
        this.loadDataSource();
    
        // Reset pagination to page 1 on init
        this.currentPage = 1;
    
        // Set up plugin system
        this.initializePlugins();
    
        // Call the afterInit hook
        this.callHook('afterInit');
    
        return this;
    }

    loadDataSource() {
        // If no data type specified and table has data, use existing table content
        if (!this.options.data.type && this.table && this.table.querySelector('tbody') && this.table.querySelector('tbody tr')) {
            this.processExistingTableData();
            return;
        }

        // Load data based on the type
        switch (this.options.data.type) {
            case 'json':
                this.loadJSON();
                break;
            case 'csv':
                this.loadCSV();
                break;
            case 'ajax':
                this.loadAjax();
                break;
            case 'txt':
                this.loadTXT();
                break;
            case 'excel':
                this.loadExcel();
                break;                
            default:
                console.error('SmartTables: Invalid data source type');
                break;
        }
    }

    loadJSON() {
        const self = this;

        if (typeof this.options.data.source === 'string') {
            // Load from URL
            fetch(this.options.data.source)
                .then(response => response.json())
                .then(data => self.processData(data))
                .catch(error => {
                    console.error('SmartTables: Error loading JSON:', error);
                    self.hideLoading();
                });
        } else if (typeof this.options.data.source === 'object') {
            // Direct JSON data
            this.processData(this.options.data.source);
        }
    }

    loadCSV() {
        const self = this;

        if (typeof this.options.data.source === 'string') {
            if (this.options.data.source.startsWith('data:')) {
                // CSV string
                this.parseCSV(this.options.data.source);
            } else {
                // Load from URL
                fetch(this.options.data.source)
                    .then(response => response.text())
                    .then(csv => self.parseCSV(csv))
                    .catch(error => {
                        console.error('SmartTables: Error loading CSV:', error);
                        self.hideLoading();
                    });
            }
        }
    }

    loadAjax() {
        const self = this;

        // Log prefetch setting and current search for debugging
        this.log('loadAjax called - prefetch setting:', this.options.data.prefetch, 
                'current page:', this.currentPage, 
                'search query:', this.searchQuery);
    
        // Generate a unique cache key based on the current request parameters
        const cacheKey = this.generateCacheKey();
    
        // Check if we have cached data for this request
        if (this.ajaxCache.has(cacheKey)) {
            const cachedData = this.ajaxCache.get(cacheKey);
            const cacheAge = Date.now() - cachedData.timestamp;
            
            // Use default cache duration of 5 minutes (300000ms) if not defined
            const cacheDuration = this.options.data.cacheDuration || 300000;
    
            // Log the cache age for debugging
            this.log('📦 CACHE HIT: Age (ms):', cacheAge, 'Cache duration (ms):', cacheDuration, 'For page:', this.currentPage, 'Search:', this.searchQuery);
    
            // Check if the cached data is still valid (within cache duration)
            if (cacheAge < cacheDuration) {
                this.log('📦 CACHE HIT: Using cached data for key:', cacheKey, 'For page:', this.currentPage, 'Search:', this.searchQuery);
                if (this.options.data.serverSide) {
                    this.totalRows = cachedData.total;
                    this.processData(cachedData.data);
                    
                    // We'll let the pagination handler trigger prefetch for the next page
                    // This avoids duplicate prefetching
                    this.log('📦 CACHE HIT: Pagination handler will trigger prefetch if needed');
                } else {
                    this.processData(cachedData.data);
                }
                this.hideLoading(); // Ensure any loading state is cleared
                return; // Exit early since we used cached data
            } else {
                this.log('Cache expired for key:', cacheKey, 'Age:', cacheAge, 'Duration:', cacheDuration, 'For page:', this.currentPage, 'Search:', this.searchQuery);
                this.ajaxCache.delete(cacheKey); // Remove expired cache entry
            }
        } else {
            // Make this more prominent in the logs
            this.log('🔍 CACHE MISS - No cache entry found for key:', cacheKey, 'For page:', this.currentPage, 'Search:', this.searchQuery);
            // Log all existing cache keys to help debug
            this.log('Existing cache keys:', Array.from(this.ajaxCache.keys()));
        }
    
        // If we reach here, we need to fetch new data
        if (this.isLoadingAjax) {
            this.log('loadAjax already in progress, aborting previous request and starting new one');
            if (this.abortController) {
                this.abortController.abort();
            }
        }
    
        this.isLoadingAjax = true;
        this.abortController = new AbortController();
    
        // Show loading indicator for AJAX requests (only if not using cached data)
        if (this.options.loading.enabled) {
            this.wrapper.classList.add('st-ajax-loading');
            this.wrapper.classList.add('st-loading');
            if (!this.wrapper.querySelector('.st-loading-spinner')) {
                const spinner = document.createElement('div');
                spinner.className = 'st-loading-spinner';
                this.wrapper.appendChild(spinner);
            }
        }
    
        const options = {
            method: this.options.data.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept-Encoding': 'gzip, deflate',
                ...this.options.data.headers
            },
            signal: this.abortController.signal
        };

        let url = this.options.data.source;
        const params = { ...this.options.data.params };
    
        if (this.options.data.serverSide) {
            params.page = parseInt(this.currentPage, 10) || 1;
            params.perPage = parseInt(this.options.perPage, 10) || 10;
            
            // Ensure search parameter is always included even if empty
            params.search = this.searchQuery || '';
            
            if (this.currentSortColumn !== undefined) {
                params.sortColumn = this.currentSortColumn;
            }
            if (this.currentSortDirection) {
                params.sortDirection = this.currentSortDirection;
            }
            params.fields = Array.isArray(this.options.data.columns)
                ? this.options.data.columns.map(col => col.data).join(',')
                : '';
            this.log('Server-side params:', params);
        }
    
        if (options.method === 'GET' && Object.keys(params).length > 0) {
            const urlParams = new URLSearchParams();
            Object.keys(params).forEach(key => {
                if (params[key] !== null && params[key] !== undefined) {
                    urlParams.append(key, params[key]);
                }
            });
            url += (url.includes('?') ? '&' : '?') + urlParams.toString();
            this.log('AJAX URL with params:', url);
        } else if (options.method === 'POST') {
            options.body = JSON.stringify(params);
        }
    
        this.callHook('beforeDataLoad', url, options);
    
        const timeoutMs = 30000;
        const fetchPromise = fetch(url, options)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                const contentType = response.headers.get('Content-Type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server response is not JSON');
                }
                return response.json();
            })
            .then(data => {
                if (self.options.data.serverSide) {
                    if (!data || typeof data.data === 'undefined' || !Array.isArray(data.data)) {
                        throw new Error('Server-side response must include a "data" array property');
                    }

                    // Store total row count from server
                    self.totalRows = data.total || data.data.length;
                    self.log('Server returned', data.data.length, 'rows, total:', self.totalRows);
                    
                    // Store the search query that produced this total for consistency checks
                    self.lastSearchQuery = self.searchQuery;
 
                    // Cache the response
                    self.ajaxCache.set(cacheKey, {
                        data: data.data,
                        total: data.total,
                        timestamp: Date.now(),
                        search: self.searchQuery
                    });
                    self.log('Cached response for key:', cacheKey);
                    
                    // Check if we got no results back and display appropriate message
                    if (self.totalRows === 0 && self.searchQuery) {
                        self.showNoResultsMessage(self.searchQuery);
                        // Clear the table body when no results
                        if (self.tableBody) {
                            self.tableBody.innerHTML = '';
                        }
                    } else {
                        self.removeNoResultsMessage();
                    }

                    // Process the data for the current page
                    self.processData(data.data);
                    
                    // Prefetch next page if we're not on the last page and prefetching is enabled
                    if (self.options.data.prefetch && self.currentPage < Math.ceil(self.totalRows / self.options.perPage)) {
                        self.log('Initiating prefetch for next page - current page:', self.currentPage, 
                               'total pages:', Math.ceil(self.totalRows / self.options.perPage),
                               'prefetch enabled:', !!self.options.data.prefetch,
                               'search query:', self.searchQuery);
                        self.prefetchNextPage();
                    } else {
                        self.log('Not prefetching next page - current page:', self.currentPage, 
                               'total pages:', Math.ceil(self.totalRows / self.options.perPage),
                               'prefetch enabled:', !!self.options.data.prefetch,
                               'search query:', self.searchQuery);
                    }
                } else {
                    if (!Array.isArray(data)) {
                        throw new Error('Client-side response must be an array');
                    }

                    // Cache the response
                    self.ajaxCache.set(cacheKey, {
                        data: data,
                        timestamp: Date.now(),
                        search: self.searchQuery
                    });
                    self.log('Cached response for key:', cacheKey);

                    self.processData(data);
                }
                self.callHook('afterDataLoad', data);
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    self.log('Request aborted - user initiated a new request');
                    return;
                }
                console.error('SmartTables: Error loading data:', error);
                self.showNotification(`Error loading data: ${error.message}`, 'danger');
                self.hideLoading();
            })
            .finally(() => {
                if (!self.abortController || self.abortController.signal.aborted) {
                    self.log('Not clearing loading state - request was aborted or is no longer current');
                } else {
                    self.log('Request complete - clearing loading state');
                    self.hideLoading();
                    self.abortController = null;
                    self.isLoadingAjax = false;
                }
            });
    
        const timeoutPromise = new Promise((_, reject) =>
            setTimeout(() => reject(new Error('Request timed out')), timeoutMs)
        );
    
        Promise.race([fetchPromise, timeoutPromise])
            .catch(error => {
                if (error.message !== 'Request timed out') return;
                if (self.abortController && !self.abortController.signal.aborted) {
                    console.error('SmartTables: Request timeout');
                    self.showNotification('Request timed out. Please try again.', 'danger');
                    self.hideLoading();
                    self.abortController = null;
                    self.isLoadingAjax = false;
                } else {
                    self.log('Timed out request was for an old request, ignoring');
                }
            });
    }

    prefetchNextPage() {
        const nextPage = this.currentPage + 1;
        
        // Calculate total pages based on the current total rows count
        const totalPages = Math.ceil(this.totalRows / this.options.perPage);
        
        this.log('🔮 PREFETCH: Checking next page', nextPage, 'of', totalPages, 'total. Search:', this.searchQuery);
        
        // Only prefetch if there are more pages to fetch
        if (nextPage > totalPages) {
            this.log('🔮 PREFETCH: Skipping prefetch - next page', nextPage, 'exceeds total pages', totalPages, 'Search:', this.searchQuery);
            return;
        }
        
        // Save current state
        const originalPage = this.currentPage;
        
        // Temporarily set current page to next page for proper cache key generation
        this.currentPage = nextPage;
        
        // Generate cache key for next page using the same method as loadAjax
        const nextPageCacheKey = this.generateCacheKey();
        
        // Restore original page
        this.currentPage = originalPage;
        
        // Check if next page data is already cached
        if (this.ajaxCache.has(nextPageCacheKey)) {
            this.log('🔮 PREFETCH: Next page already cached, skipping prefetch:', nextPage, 'Search:', this.searchQuery);
            return;
        }
        
        this.log('🔮 PREFETCH: Starting prefetch for page', nextPage, 'with cache key:', nextPageCacheKey, 'Search:', this.searchQuery);
        
        // Build parameters for prefetch request - ensure search is included
        const fetchParams = {
            ...this.options.data.params,
            page: nextPage,
            perPage: this.options.perPage,
            search: this.searchQuery || '',
            sortColumn: this.currentSortColumn !== undefined ? this.currentSortColumn : '',
            sortDirection: this.currentSortDirection || '',
            fields: Array.isArray(this.options.data.columns)
                ? this.options.data.columns.map(col => col.data).join(',')
                : ''
        };
        
        // Build URL with parameters
        const url = this.options.data.source + (Object.keys(fetchParams).length > 0
            ? (this.options.data.source.includes('?') ? '&' : '?') + new URLSearchParams(fetchParams).toString()
            : '');
        
        this.log('🔮 PREFETCH: URL for next page:', url);
        
        // Fetch next page data silently
        fetch(url, {
            method: this.options.data.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept-Encoding': 'gzip, deflate',
                ...this.options.data.headers
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
            .then(data => {
            if (!Array.isArray(data.data)) {
                throw new Error('Server response "data" must be an array');
            }
            
            // Verify the total matches what we expect for consistency
            if (data.total !== this.totalRows) {
                this.log('🔮 PREFETCH: Warning - total rows changed from', this.totalRows, 'to', data.total, 'during prefetch');
            }
            
            // Store the fetched data in cache using the next page cache key
            this.ajaxCache.set(nextPageCacheKey, {
                data: data.data,
                total: data.total, // Store the total from this response
                timestamp: Date.now(),
                search: this.searchQuery // Store the search query with the cache
            });
            
            this.log('🔮 PREFETCH: Successfully cached data for page', nextPage, 'with key:', nextPageCacheKey, 'Search:', this.searchQuery);
            this.log('🔮 PREFETCH: Current cache size:', this.ajaxCache.size, 'entries');
        })
        .catch(error => {
            this.log('🔮 PREFETCH: Failed for page:', nextPage, 'Error:', error, 'Search:', this.searchQuery);
        });
    }

    // for faster loading
    generateCacheKey() {
        // Create parameters object with all relevant data that affects results
        const params = {
            url: this.options.data.source,
            page: this.currentPage,
            perPage: this.options.perPage,
            // Normalize search query to empty string if falsy for consistent caching
            search: (this.searchQuery || '').trim().toLowerCase(),
            sortColumn: this.currentSortColumn !== undefined ? this.currentSortColumn : '',
            sortDirection: this.currentSortDirection || ''
        };
        
        // Create a deterministic string representation
        const key = JSON.stringify(params);
        
        // Log cache key generation with clear details
        this.log('🔑 Generated cache key:', {
            page: this.currentPage,
            search: params.search,
            key: key
        });
        
        return key;
    }

    // Optional: Add method to clear cache when needed
    clearAjaxCache() {
        this.ajaxCache.clear();
    }

    parseCSV(csvText) {
        try {
            // Simple CSV parser
            const lines = csvText.split(/\r\n|\n/);
            if (!lines.length) {
                throw new Error("No data found in CSV");
            }

            // Get headers - properly handle quoted values
            const headerLine = lines[0];
            const headers = this.parseCSVLine(headerLine);

            const result = [];

            for (let i = 1; i < lines.length; i++) {
                if (lines[i].trim() === '') continue;

                // Parse the line properly handling quoted values
                const values = this.parseCSVLine(lines[i]);

                // Skip if we don't have enough values
                if (values.length < headers.length) {
                    console.warn('SmartTables: Line ' + i + ' has fewer values than headers, skipping');
                    continue;
                }

                const obj = {};
                for (let j = 0; j < headers.length; j++) {
                    obj[headers[j]] = values[j];
                }

                result.push(obj);
            }

            return result;
        } catch (error) {
            console.error('CSV parsing error:', error);
            throw new Error('Failed to parse CSV: ' + error.message);
        }
    }

    // Helper method to properly parse CSV lines with quoted values
    parseCSVLine(line) {
        const result = [];
        let inQuote = false;
        let currentValue = '';

        for (let i = 0; i < line.length; i++) {
            const char = line[i];

            if (char === '"' && (i === 0 || line[i - 1] !== '\\')) {
                inQuote = !inQuote;
            } else if (char === ',' && !inQuote) {
                result.push(currentValue.trim().replace(/^"(.*)"$/, '$1'));
                currentValue = '';
            } else {
                currentValue += char;
            }
        }

        // Add the last value
        result.push(currentValue.trim().replace(/^"(.*)"$/, '$1'));

        return result;
    }

    processData(data) {
        this.log(`Processing ${data.length} rows of data`);
        
        // For server-side pagination, we need to check if there are no results
        if (this.options.data.serverSide && data.length === 0 && this.searchQuery) {
            this.showNoResultsMessage(this.searchQuery);
        } else if (data.length > 0) {
            this.removeNoResultsMessage();
        }
        
        // Process data in chunks for better performance with large datasets
        const self = this;
        const startTime = performance.now();
        const totalRecords = data.length;
        
        // Use requestAnimationFrame for DOM updates
        requestAnimationFrame(() => {
        try {
            if (this.options.data.parser) {
                data = this.options.data.parser(data);
            }

            if (!Array.isArray(data)) {
                console.error('SmartTables: Data must be an array of objects');
                this.showNotification('Invalid data format. Expected an array of objects.', 'danger');
                data = [];
            }
    
                this.log('Processing data', {
                    count: data.length,
                    currentPage: this.currentPage,
                    serverSide: this.options.data.serverSide
                });

            // Auto-detect columns if not defined
            if (!this.options.data.columns || this.options.data.columns.length === 0) {
                this.options.data.columns = this.detectColumns(data);
            }

                // For server-side processing with an existing table structure, just update the rows
                if (this.options.data.serverSide && this.table && this.table.querySelector('tbody') && this.isTableInitialized) {
                    this.log('Updating table with new server-side data for page', this.currentPage);
                    const currentSortColumn = this.currentSortColumn;
                    const currentSortDirection = this.currentSortDirection;
                    
                    // Store current hidden columns state to preserve responsive status
                    const currentHiddenColumns = this.hiddenColumns ? [...this.hiddenColumns] : [];
                    this.log('Stored current hidden columns before update:', currentHiddenColumns);
    
                    // Process in chunks
                    const chunkSize = 100;
                    const processChunk = (start) => {
                        const end = Math.min(start + chunkSize, data.length);
                        const chunk = data.slice(start, end);
                        this.updateTableData(chunk, start === 0);
                        if (end < data.length) {
                            requestAnimationFrame(() => processChunk(end));
                        } else {
                            this.rows = Array.from(this.table.querySelector('tbody').rows).map((row, index) => ({
                                element: row,
                                expanded: false,
                                originalIndex: index
                            }));
                            
                            // Ensure hiddenColumns is restored before drawing
                            if (currentHiddenColumns.length > 0) {
                                this.hiddenColumns = currentHiddenColumns;
                                this.log('Restored hidden columns before draw:', this.hiddenColumns);
                            }
                            
                            // Reapply responsive setup for server-side data
                            if (this.options.responsive && this.options.responsive.enabled) {
                                this.log('Reapplying responsive setup for server-side data');
                                this.setupResponsiveRows();
                                this.checkResponsiveDisplay();
                            }
                            
                            this.draw();
                            
                            if (currentSortColumn !== undefined) {
                                this.log(`Reapplying sort column class after data update for column ${currentSortColumn}`);
                                const headers = this.table.querySelectorAll('thead th');
                                if (headers.length > currentSortColumn) {
                                    Array.from(headers).forEach(header => {
                                        header.classList.remove('st-sort-asc', 'st-sort-desc');
                                    });
                                    headers[currentSortColumn].classList.add(
                                        currentSortDirection === 'asc' ? 'st-sort-asc' : 'st-sort-desc'
                                    );
                                }
                                this.addSortColumnClass(currentSortColumn);
                            }
                        }
                    };
                    processChunk(0);
                    return;
                }
    
                // If this is the first time or not server-side, set up the complete table
            if (this.table) {
                var tbody = this.table.querySelector('tbody');
                if (tbody) {
                        tbody.innerHTML = '';
                } else {
                    tbody = document.createElement('tbody');
                    this.table.appendChild(tbody);
                }

                var thead = this.table.querySelector('thead');
                if (!thead) {
                    thead = document.createElement('thead');
                    var headerRow = document.createElement('tr');
                        this.options.data.columns.forEach(function(column) {
                        var th = document.createElement('th');
                        th.textContent = column.title || column.data;
                        if (column.width) th.style.width = column.width;
                        if (column.class) th.className = column.class;
                        headerRow.appendChild(th);
                    });
                    thead.appendChild(headerRow);
                    this.table.insertBefore(thead, tbody);
                } else {
                    thead.innerHTML = '';
                    var headerRow = document.createElement('tr');
                        this.options.data.columns.forEach(function(column) {
                        var th = document.createElement('th');
                        th.textContent = column.title || column.data;
                        if (column.width) th.style.width = column.width;
                        if (column.class) th.className = column.class;
                        headerRow.appendChild(th);
                    });
                    thead.appendChild(headerRow);
                }
            } else {
                this.createTableFromData(data);
            }

                // Process in chunks for large datasets
                const chunkSize = 100;
                const processChunk = (start) => {
                    const end = Math.min(start + chunkSize, data.length);
                    const chunk = data.slice(start, end);
                    this.updateTableData(chunk, start === 0);
                    if (end < data.length) {
                        requestAnimationFrame(() => processChunk(end));
                    } else {
                        this.finalizeDataProcessing();
                    }
                };
                processChunk(0);
        } catch (error) {
            console.error('Error processing data:', error);
            this.showNotification('Error processing data: ' + error.message, 'danger');
                this.hideLoading();
            }
        });
    }

    processExistingTableData() {
        try {
            // Get headers from thead if they exist
            const thead = this.table.querySelector('thead');
            let headers = [];
            
            if (thead) {
                headers = Array.from(thead.querySelectorAll('th')).map(th => ({
                    title: th.textContent.trim(),
                    data: th.textContent.trim().toLowerCase().replace(/\s+/g, '_')
                }));
            }
    
            // Get rows from tbody
            const tbody = this.table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Convert table data to array of objects
            const data = rows.map(row => {
                const cells = Array.from(row.cells);
                const rowData = {};
                
                cells.forEach((cell, index) => {
                    const header = headers[index] || {
                        data: `column_${index}`,
                        title: `Column ${index + 1}`
                    };
                    rowData[header.data] = cell.textContent.trim();
                });
                
                return rowData;
            });
    
            // If no headers were found in thead, detect them from first row
            if (headers.length === 0 && data.length > 0) {
                headers = Object.keys(data[0]).map(key => ({
                    data: key,
                    title: key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
                }));
            }
    
            // Update options with detected columns
            this.options.data.columns = headers;
    
            // Process the data
            this.processData(data);
        } catch (error) {
            console.error('SmartTables: Error processing existing table data:', error);
            this.showNotification('Error processing table data: ' + error.message, 'danger');
            this.hideLoading();
        }
    }

    createTableFromData(data) {
        this.table = document.createElement('table');
        this.wrapper.appendChild(this.table);

        // Create header
        var thead = document.createElement('thead');
        var headerRow = document.createElement('tr');

        this.options.data.columns.forEach(function (column) {
            var th = document.createElement('th');
            th.textContent = column.title || column.data;
            if (column.width) th.style.width = column.width;
            if (column.class) th.className = column.class;
            headerRow.appendChild(th);
        });

        thead.appendChild(headerRow);
        this.table.appendChild(thead);

        // Create tbody
        var tbody = document.createElement('tbody');
        this.table.appendChild(tbody);
    }

    updateTableData(data, isFirstChunk = true) {
        try {
            // Remember hidden columns to restore after update
            const hiddenColumns = this.hiddenColumns ? [...this.hiddenColumns] : [];
            this.log('Stored current hidden columns before update:', hiddenColumns);
            
            // Store current search query before update
            const currentSearch = this.searchQuery;
            this.log('Stored current search query before update:', currentSearch);
            
            const tableBody = this.table.querySelector('tbody');
            if (isFirstChunk) {
                tableBody.innerHTML = ''; // Clear any existing rows only for the first chunk
                // Clear filteredRows for server-side data
                if (this.options.data.serverSide) {
                    this.filteredRows = [];
                }
            }
    
            // Use a DocumentFragment to batch DOM updates
            const fragment = document.createDocumentFragment();
            data.forEach((item, index) => {
                var row = document.createElement('tr');
                row.setAttribute('data-id', item.id); // Add data-id to the row
                this.options.data.columns.forEach(function(column) {
                    var cell = document.createElement('td');
                    if (column.render) {
                        cell.innerHTML = column.render(item[column.data], item);
                    } else {
                        cell.textContent = item[column.data] || '';
                    }
                    cell.classList.remove('st-sort-column');
                    if (column.class) cell.className = column.class;
                    row.appendChild(cell);
                }, this);
                fragment.appendChild(row);

                // Store row data for server-side processing
                if (this.options.data.serverSide) {
                    const pageOffset = (this.currentPage - 1) * this.options.perPage;
                    const rowIndex = pageOffset + index;
                    this.filteredRows[rowIndex] = {
                        element: row,
                        expanded: false,
                        data: item,
                        index: rowIndex
                    };
                    this.log('Stored row data for index:', rowIndex);
                }
            }, this);
            tableBody.appendChild(fragment);
            
            // Rebuild the rows array from the current DOM to ensure consistency
            if (isFirstChunk) {
                // Rebuild the entire rows array with updated DOM references
                this.rows = Array.from(tableBody.rows).map((row, index) => {
                    return {
                        element: row,
                        expanded: false,
                        originalIndex: index
                    };
                });
                
                // Update filteredRows to match rows for client-side data
                if (!this.options.data.serverSide) {
                    this.filteredRows = this.rows.slice();
                }
                
                // Reapply any current sort column if present
                if (this.currentSortColumn !== undefined && this.currentSortDirection) {
                    this.log(`Reapplying sort column class after data update for column ${this.currentSortColumn}`);
                    this.addSortColumnClass(this.currentSortColumn);
                }
                
                // Reapply responsive column visibility if enabled
                if (this.options.responsive && this.options.responsive.enabled) {
                    // Recalculate column widths if needed
                    this.calculateColumnWidths();
                    
                    // Reapply hidden columns from before the update
                    if (hiddenColumns && hiddenColumns.length > 0) {
                        this.log('Reapplying hidden columns after data update:', hiddenColumns);
                        hiddenColumns.forEach(colIndex => {
                            this.hideColumn(colIndex);
                        });
                    } else {
                        // If no columns were hidden, check if we need to hide any based on current width
                        this.checkResponsiveDisplay();
                    }
                    
                    // Update any expanded rows to match new responsive state
                    this.updateExpandedRowsAfterResize();
                }
                
                // Restore the search query after data update
                this.searchQuery = currentSearch;
                this.log('Restored search query after data update:', this.searchQuery);
                
                // If there's an active search, reapply it
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    this.log('Reapplying search after data update with query:', this.searchQuery);
                    // For server-side data, we don't need to reapply the search since the data is already filtered
                    if (!this.options.data.serverSide) {
                        setTimeout(() => {
                            this.handleSearch(this.searchQuery);
                        }, 50);
                    }
                }
            }
        } catch (error) {
            console.error('Error updating table data:', error);
            this.showNotification('Error updating table: ' + error.message, 'danger');
        }
    }

    finalizeDataProcessing() {
        if (!this.isTableInitialized) {
            this.initializeTable();
            this.isTableInitialized = true;
        } else {
            // For server-side data, ensure responsive calculations run when page changes
            if (this.options.data.serverSide && this.options.responsive.enabled) {
                this.log('Ensuring responsive calculations are applied for server-side data');
                // Only recalculate if wrapper width has changed
                const currentWidth = this.wrapper.getBoundingClientRect().width;
                if (!this.lastWrapperWidth || this.lastWrapperWidth !== currentWidth) {
                    this.lastWrapperWidth = currentWidth;
                    this.calculateColumnWidths();
                }
                
                // No need to call checkResponsiveDisplay() here as it's handled in calculateColumnWidths
            }
            
            this.draw();
        }
        this.hideLoading();
    }

    hideLoading() {
        if (this.options.loading.enabled) {
            this.log('🔄 LOADING: Hiding all loading indicators');
            this.wrapper.classList.remove('st-loading');
            // Also remove the ajax-specific loading class
            this.wrapper.classList.remove('st-ajax-loading');
            
            var spinner = this.wrapper.querySelector('.st-loading-spinner');
            if (spinner) {
                spinner.remove();
                this.log('🔄 LOADING: Removed spinner element');
            }
            
            // Ensure the table is visible
            if (this.table) {
                // Only set opacity if not using d-none
                if (!this.table.classList.contains('d-none')) {
                    this.table.style.opacity = '1';
                    this.log('🔄 LOADING: Set table opacity to 1');
                }
                
                // Remove d-none class if present (added by user to prevent initial layout jump)
                if (this.table.classList.contains('d-none') && this.isTableInitialized && !this.hasDNoneBeenRemoved) {
                    // Only remove d-none after all calculations are done
                    setTimeout(() => {
                        this.table.classList.remove('d-none');
                        this.table.style.opacity = '1'; // Set opacity when removing d-none
                        this.hasDNoneBeenRemoved = true;
                        this.log('🔄 LOADING: Removed d-none class from table and set opacity to 1');
                    }, 100); // Slightly longer delay to ensure all rendering is complete
                }
            }
            
            // Reset loading state variables
            this.isLoadingAjax = false;
            if (this.abortController) {
                this.log('🔄 LOADING: Clearing abort controller');
                this.abortController = null;
            }
        }
    }

    setupWrapper() {
        // Create wrapper div that will contain the table
        this.wrapper = document.createElement('div');
        this.wrapper.className = this.options.classes.wrapper;
        this.wrapper.style.position = 'relative';
        this.wrapper.style.width = '100%'; // Ensure wrapper takes full width

        // Insert wrapper before table in the DOM
        this.table.parentNode.insertBefore(this.wrapper, this.table);

        // Move table inside wrapper
        this.wrapper.appendChild(this.table);
        this.table.className = this.options.classes.table;

        // Set table to take full width of wrapper
        this.table.style.width = '100%';
    }

    initializeTable() {
        try {
            // Setup initial table structure
            this.setupToolbar();
            this.setupTable();

            // Get table rows
            var tbody = this.table.querySelector('tbody');
            this.originalRows = Array.from(tbody.rows);

            this.rows = Array.from(tbody.rows).map(function (row, index) {
                return {
                    element: row,
                    expanded: false,
                    originalIndex: index
                };
            });
            this.filteredRows = this.rows.slice();

            // Make table slightly visible during calculations, but keep d-none if present
            if (!this.table.classList.contains('d-none')) {
            this.table.style.opacity = '0.01';
            }

            // Use requestAnimationFrame to ensure DOM is fully rendered before measuring
            requestAnimationFrame(() => {
                // Calculate column widths after DOM is ready
                this.calculateColumnWidths();
                
                // Set table as initialized before drawing
                this.isTableInitialized = true;
                
                this.draw();

                // Remove loading state after calculations are complete
                if (this.options.loading.enabled) {
                    setTimeout(() => {
                        this.wrapper.classList.remove('st-loading');
                        
                        // Only change opacity if table doesn't have d-none
                        if (!this.table.classList.contains('d-none')) {
                        this.table.style.opacity = '1';
                        }

                        // Remove spinner element if it exists
                        var spinner = this.wrapper.querySelector('.st-loading-spinner');
                        if (spinner) spinner.remove();

                        // Show success notification if this was an import operation
                        if (this.isImportOperation) {
                            this.showNotification('Successfully imported ' + this.rows.length + ' rows', 'success');
                            this.isImportOperation = false;
                        }
                    }, this.options.loading.duration || 300);
                }
            });
        } catch (error) {
            console.error('Error initializing table:', error);

            // Clean up if there's an error
            if (this.options.loading.enabled) {
                this.wrapper.classList.remove('st-loading');
                
                // Only change opacity if table doesn't have d-none
                if (!this.table.classList.contains('d-none')) {
                this.table.style.opacity = '1';
                }

                var spinner = this.wrapper.querySelector('.st-loading-spinner');
                if (spinner) spinner.remove();
            }
        }

        // Call afterInit hook if defined
        this.callHook('afterInit');
    }

    calculateColumnWidths() {
        var self = this;

        // Exit early if responsiveColumns is not populated (e.g., responsive is disabled)
        if (!Array.isArray(this.responsiveColumns) || this.responsiveColumns.length === 0) {
            this.log('Skipping column width calculation: responsiveColumns not populated');
            return;
        }

        // Get the container width directly from the wrapper element - use multiple methods for more reliability
        let containerWidth = this.wrapper.getBoundingClientRect().width;
        
        // If container width is 0 (hidden), try alternative methods
        if (containerWidth === 0) {
            containerWidth = Math.max(
                this.wrapper.offsetWidth,
                this.wrapper.clientWidth,
                this.wrapper.scrollWidth
            );
            
            // If still 0, container is hidden - store this state and defer calculation
            if (containerWidth === 0) {
                this.log('Container is hidden (width=0), deferring responsive calculation until visible');
                this.isContainerHidden = true;
                return;
            }
        }
        
        // Container is visible, clear hidden state
        this.isContainerHidden = false;

        // Fix for Firefox width calculation issues
        const isFirefox = navigator.userAgent.indexOf('Firefox') !== -1;
        if (isFirefox) {
            this.log('Firefox detected - ensuring accurate container width');
            
            // DON'T set a minimum width on the wrapper as it prevents responsive behavior
            // Just fix the issue with Firefox's column width calculation
            
            // Get the actual window width to determine if we need to apply responsive behavior
            const windowWidth = window.innerWidth;
            const shouldBeResponsive = this.options.responsive && 
                                      this.options.responsive.breakpoint && 
                                      windowWidth <= this.options.responsive.breakpoint;
            
            // Only set containerWidth differently if we're NOT in responsive mode
            if (!shouldBeResponsive) {
                // Get width using multiple methods and use the largest value
                containerWidth = Math.max(
                    containerWidth,
                    this.wrapper.offsetWidth,
                    this.wrapper.scrollWidth
                );
            }
        }

        // Originally was for firefox but placed here for chrome as well for consistency
        const cols = this.table.querySelectorAll('colgroup col');
        if (cols.length > 0) {
            // Reset colgroup widths to prevent 100% width on first column
            cols.forEach((col, idx) => {
                if (idx === 0) {
                    // First column specifically needs a fixed width, but don't make it too wide
                    col.style.width = '65px';
                } else {
                    col.style.width = 'auto';
                }
            });
        }

        this.log('Container width:', containerWidth);

        // Create a clone for measurement
        var tableClone = this.table.cloneNode(true);

        // Reset any previous styling that might affect width
        tableClone.style.width = 'auto';
        tableClone.style.tableLayout = 'auto';

        // Position the clone off-screen
        tableClone.style.position = 'absolute';
        tableClone.style.top = '-9999px';
        tableClone.style.left = '-9999px';
        tableClone.style.visibility = 'hidden';

        // Make all cells visible for measurement
        var hiddenCells = tableClone.querySelectorAll('th[style*="display: none"], td[style*="display: none"]');
        for (var i = 0; i < hiddenCells.length; i++) {
            hiddenCells[i].style.display = '';
        }

        // Add to DOM for measurement
        document.body.appendChild(tableClone);

        // Calculate total width needed for all columns
        let totalColumnsWidth = 0;

        // Measure each column's natural width
        this.responsiveColumns.forEach(function (column) {
            // Get the header cell
            var headerCell = tableClone.querySelector('thead th:nth-child(' + (column.index + 1) + ')');

            // Measure the natural width
            column.minWidth = headerCell ? headerCell.offsetWidth : 0;

            // Also check cell contents in all rows for better accuracy
            var rows = tableClone.querySelectorAll('tbody tr');

            for (var i = 0; i < rows.length; i++) {
                if (rows[i] && rows[i].cells[column.index]) {
                    column.minWidth = Math.max(column.minWidth, rows[i].cells[column.index].offsetWidth);
                }
            }

            // Add a small buffer
            column.minWidth += 5;

            totalColumnsWidth += column.minWidth;

            self.log('Column', column.index, 'min width:', column.minWidth);
        });

        // Clean up
        document.body.removeChild(tableClone);

        // Store the container width for responsive calculations
        this.containerWidth = containerWidth;
        this.log('Stored container width:', this.containerWidth);
        this.log('Total columns width needed:', totalColumnsWidth);

        // Update the responsive display - hide columns as needed
        this.applyResponsiveDisplay(containerWidth, totalColumnsWidth);
    }

    applyResponsiveDisplay(containerWidth, totalColumnsWidth) {
        // If total width of columns exceeds container, we need to hide columns
        if (totalColumnsWidth > containerWidth) {
            this.log('Applying responsive column visibility');

            // Sort columns by priority (higher number = lower priority = hide first)
            const sortedColumns = [...this.responsiveColumns].sort((a, b) => b.priority - a.priority);

            let currentTotalWidth = totalColumnsWidth;
            let columnsToHide = [];

            // Determine which columns to hide
            for (const column of sortedColumns) {
                if (currentTotalWidth <= containerWidth) {
                    break;
                }

                columnsToHide.push(column.index);
                currentTotalWidth -= column.minWidth;
                this.log('Hiding column', column.index, 'to save', column.minWidth, 'px');
            }

            // Apply visibility to columns
            this.responsiveColumns.forEach((column) => {
                const cells = this.table.querySelectorAll(`tr > *:nth-child(${column.index + 1})`);
                const shouldHide = columnsToHide.includes(column.index);

                cells.forEach(cell => {
                    cell.style.display = shouldHide ? 'none' : '';
                });
            });
        } else {
            // Show all columns if there's enough space
            this.log('Showing all columns');
            this.responsiveColumns.forEach((column) => {
                const cells = this.table.querySelectorAll(`tr > *:nth-child(${column.index + 1})`);
                cells.forEach(cell => {
                    cell.style.display = '';
                });
            });
        }

        // After applying changes, re-setup content observers
        if (this.options.contentObserver) {
            // Use setTimeout to ensure DOM has updated
            setTimeout(() => {
                this.setupContentObservers();
            }, 0);
        }
    }

    setupToolbar() {
        // Create toolbar with flex layout
        this.toolbar = document.createElement('div');
        this.toolbar.className = 'st-toolbar row mb-4';
    
        // Create left column for search
        var leftCol = document.createElement('div');
        leftCol.className = 'col-12 col-sm-6 col-lg-6 col-xl-5 col-xxl-4 order-1 order-sm-0 mt-4 mt-sm-0';

        // Create search wrapper
        if (this.options.search) {
            var searchWrapper = document.createElement('div');
            searchWrapper.className = 'st-search-wrapper';

            var inputGroup = document.createElement('div');
            inputGroup.className = 'input-group flex-nowrap';
            inputGroup.setAttribute('role', 'search');

            var searchIcon = document.createElement('span');
            searchIcon.className = 'input-group-text px-2';
            searchIcon.id = 'search-icon';
            searchIcon.innerHTML = '<svg class="sa-icon sa-bold"><use href="icons/sprite.svg#search"></use></svg>';

            var searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'form-control';
            searchInput.id = 'addon-wrapping-left';
            searchInput.placeholder = 'Search...';
            searchInput.setAttribute('aria-label', 'Search input');
            searchInput.setAttribute('aria-describedby', 'search-icon');
            searchInput.setAttribute('autocomplete', 'off');

            inputGroup.appendChild(searchIcon);
            inputGroup.appendChild(searchInput);
            searchWrapper.appendChild(inputGroup);

            var self = this;
            searchInput.addEventListener('input', function () {
                self.handleSearch(this.value);
            });

            leftCol.appendChild(searchWrapper);
        }
    
        // Create right column for export buttons
        var exportCol = document.createElement('div');
        exportCol.className = 'col d-flex justify-content-end gap-2';
    
        // Add print button if enabled
        if (this.options.print) {
            var printBtn = document.createElement('button');
            printBtn.className = 'btn btn-sm btn-outline-secondary btn-icon h-100 order-1 px-3 fs-xl';
            printBtn.type = 'button';
            printBtn.setAttribute('aria-label', 'Print table');
            printBtn.innerHTML = '<i class="sa sa-printer"></i>';
            printBtn.addEventListener('click', () => {
                this.printTable();
            });
            exportCol.appendChild(printBtn);
        }
    
        // Add import button if enabled
        if (this.options.import) {
            var importBtn = document.createElement('button');
            importBtn.className = 'btn btn-sm btn-outline-success d-flex align-items-center gap-1';
            importBtn.setAttribute('data-bs-toggle', 'modal');
            importBtn.setAttribute('data-bs-target', '#importModal');
            importBtn.type = 'button';
            importBtn.innerHTML = '<i class="sa sa-cloud-upload"></i> Import';
            exportCol.appendChild(importBtn);
            
            // Create import modal only if import is enabled
            this.createImportModal();
        }
        
        // Add 'Add Record' button if enabled
        if (this.options.addRecord) {
            var addBtn = document.createElement('button');
            addBtn.className = 'btn btn-sm btn-outline-success d-flex align-items-center gap-1';
            addBtn.id = 'st-add-record-btn';
            addBtn.type = 'button';
            addBtn.innerHTML = 'Add Record';
            addBtn.addEventListener('click', () => {
                this.addRecord();
            });
            exportCol.appendChild(addBtn);
        }
    
        // Add export dropdown
        if (this.options.export) {
            var btnGroup = document.createElement('div');
            btnGroup.className = 'btn-group';
    
            var exportBtn = document.createElement('button');
            exportBtn.className = 'btn btn-sm btn-outline-primary dropdown-toggle';
            exportBtn.type = 'button';
            exportBtn.setAttribute('data-bs-toggle', 'dropdown');
            exportBtn.textContent = 'Export';
    
            var dropdownMenu = document.createElement('ul');
            dropdownMenu.className = 'dropdown-menu';
    
            var exportOptions = [
                { format: 'excel', label: 'Excel' },
                { format: 'csv', label: 'CSV' },
                { format: 'copy', label: 'Copy' },
                { format: 'pdf', label: 'PDF' },
                { format: 'json', label: 'JSON' },
                { format: 'xml', label: 'XML' },
                { format: 'html', label: 'HTML' }
            ];
    
            exportOptions.forEach(function (option) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.className = 'dropdown-item';
                a.href = '#';
                a.textContent = option.label;
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.exportData(option.format);
                });
                li.appendChild(a);
                dropdownMenu.appendChild(li);
            });
    
            btnGroup.appendChild(exportBtn);
            btnGroup.appendChild(dropdownMenu);
            exportCol.appendChild(btnGroup);
        }
    
        // Add columns to toolbar
        this.toolbar.appendChild(leftCol);
        this.toolbar.appendChild(exportCol);
    
        // Add toolbar to wrapper
        this.wrapper.insertBefore(this.toolbar, this.table);
    }

    createImportModal() {
        var self = this;
        var modalId = 'importModal';

        // Check if modal already exists
        if (document.getElementById(modalId)) {
            return;
        }

        // Create modal structure
        var modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = modalId;
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'importModalLabel');
        modal.setAttribute('aria-hidden', 'true');

        var modalDialog = document.createElement('div');
        modalDialog.className = 'modal-dialog modal-dialog-centered';

        var modalContent = document.createElement('div');
        modalContent.className = 'modal-content';

        // Modal header
        var modalHeader = document.createElement('div');
        modalHeader.className = 'modal-header';

        var modalTitle = document.createElement('h5');
        modalTitle.className = 'modal-title';
        modalTitle.id = 'importModalLabel';
        modalTitle.textContent = 'Import Data';

        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn btn-system ms-auto';
        closeButton.setAttribute('data-bs-dismiss', 'modal');
        closeButton.setAttribute('aria-label', 'Close');
        closeButton.innerHTML = '<svg class="sa-icon sa-icon-2x"><use href="icons/sprite.svg#x"></use></svg>';

        modalHeader.appendChild(modalTitle);
        modalHeader.appendChild(closeButton);

        // Modal body
        var modalBody = document.createElement('div');
        modalBody.className = 'modal-body';

        // Status message (moved above dropzone)
        var statusMessage = document.createElement('div');
        statusMessage.className = 'alert d-none mb-3';
        modalBody.appendChild(statusMessage);

        var dropZone = document.createElement('div');
        dropZone.className = 'st-dropzone p-5 text-center d-flex flex-column align-items-center border border-2 border-dashed rounded bg-faded';
        dropZone.innerHTML = '<svg class="sa-icon sa-icon-success sa-thin sa-icon-5x mb-2"><use href="icons/sprite.svg#upload-cloud"></use></svg><p>Drag and drop your CSV or JSON file here<br>or <b class="text-primary fw-500 cursor-pointer">click to browse</b></p>';

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.className = 'visually-hidden';
        fileInput.accept = '.csv,.json';

        dropZone.appendChild(fileInput);
        modalBody.appendChild(dropZone);

        // Modal footer
        var modalFooter = document.createElement('div');
        modalFooter.className = 'modal-footer';

        var cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-danger';
        cancelButton.setAttribute('data-bs-dismiss', 'modal');
        cancelButton.textContent = 'Cancel';

        var importButton = document.createElement('button');
        importButton.type = 'button';
        importButton.className = 'btn btn-success';
        importButton.textContent = 'Import';
        importButton.disabled = true;

        modalFooter.appendChild(cancelButton);
        modalFooter.appendChild(importButton);

        // Assemble modal
        modalContent.appendChild(modalHeader);
        modalContent.appendChild(modalBody);
        modalContent.appendChild(modalFooter);
        modalDialog.appendChild(modalContent);
        modal.appendChild(modalDialog);

        document.body.appendChild(modal);

        // Setup event listeners
        dropZone.addEventListener('click', function () {
            fileInput.click();
        });

        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropZone.classList.add('border-primary');
        });

        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('border-primary');
        });

        dropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropZone.classList.remove('border-primary');

            if (e.dataTransfer.files.length) {
                handleFile(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', function () {
            if (this.files.length) {
                handleFile(this.files[0]);
            }
        });

        // Clear status message when modal is opened
        modal.addEventListener('show.bs.modal', function () {
            statusMessage.className = 'alert d-none mb-3';
            statusMessage.textContent = '';
            importButton.disabled = true;
            fileInput.value = '';
        });

        var fileData = null;

        function handleFile(file) {
            statusMessage.className = 'alert alert-info mb-3';
            statusMessage.textContent = 'Reading file...';
            importButton.disabled = true;

            var reader = new FileReader();

            reader.onload = function (e) {
                try {
                    var fileName = file.name.toLowerCase();
                    // Truncate filename if too long
                    var displayName = file.name.length > 30 ?
                        file.name.substring(0, 27) + '...' :
                        file.name;

                    if (fileName.endsWith('.json')) {
                        try {
                            fileData = JSON.parse(e.target.result);
                            statusMessage.className = 'alert alert-success mb-3';
                            statusMessage.innerHTML = 'JSON file <b>' + displayName + '</b> loaded successfully. Ready to import.';
                            importButton.disabled = false;
                        } catch (parseError) {
                            statusMessage.className = 'alert alert-danger mb-3';
                            statusMessage.innerHTML = 'Invalid JSON format in file <b>' + displayName + '</b>: ' + parseError.message;
                        }
                    } else if (fileName.endsWith('.csv')) {
                        try {
                            fileData = self.parseCSV(e.target.result);
                            statusMessage.className = 'alert alert-success mb-3';
                            statusMessage.innerHTML = 'CSV file <b>' + displayName + '</b> loaded successfully. Ready to import.';
                            importButton.disabled = false;
                        } catch (parseError) {
                            statusMessage.className = 'alert alert-danger mb-3';
                            statusMessage.innerHTML = 'Invalid CSV format in file <b>' + displayName + '</b>: ' + parseError.message;
                        }
                    } else {
                        statusMessage.className = 'alert alert-danger mb-3';
                        statusMessage.innerHTML = 'Unsupported file format: <b>' + displayName + '</b>. Please use CSV or JSON.';
                    }
                } catch (error) {
                    statusMessage.className = 'alert alert-danger mb-3';
                    statusMessage.innerHTML = 'Error processing file <b>' + file.name + '</b>: ' + error.message;
                }
            };

            reader.onerror = function () {
                statusMessage.className = 'alert alert-danger mb-3';
                statusMessage.innerHTML = 'Error reading file <b>' + file.name + '</b>.';
            };

            // Check file extension before attempting to read
            var fileName = file.name.toLowerCase();
            if (fileName.endsWith('.json') || fileName.endsWith('.csv')) {
                reader.readAsText(file);
            } else {
                statusMessage.className = 'alert alert-danger mb-3';
                statusMessage.innerHTML = 'Unsupported file format: <b>' + file.name + '</b>. Please use CSV or JSON.';
            }
        }

        importButton.addEventListener('click', function () {
            if (fileData) {
                self.importData(fileData);
                var modalInstance = bootstrap.Modal.getInstance(modal);
                modalInstance.hide();
            }
        });
    }

    importData(data) {
        try {
            // Set a flag to indicate this is an import operation
            this.isImportOperation = true;

            // Call beforeDataLoad hook
            this.callHook('beforeDataLoad', data);

            // Show loading screen
            if (this.options.loading.enabled) {
                this.wrapper.classList.add('st-loading');

                // Add spinner if it doesn't exist
                if (!this.wrapper.querySelector('.st-loading-spinner')) {
                    var spinner = document.createElement('div');
                    spinner.className = 'st-loading-spinner';
                    this.wrapper.appendChild(spinner);
                }
            }

            // Completely destroy the current table instance
            this.destroy();

            // Reset our table initialization flag since we're creating a new table
            this.isTableInitialized = false;

            // Reset options that should be regenerated
            this.options.data.columns = null;

            // Validate data format
            if (!data) {
                throw new Error('No data provided for import');
            }

            if (!Array.isArray(data)) {
                throw new Error('Data must be an array of objects');
            }

            if (data.length === 0) {
                throw new Error('Data array is empty');
            }

            // Auto-detect columns from the new data
            this.options.data.columns = this.detectColumns(data);

            // Create a fresh table element if needed
            if (!this.table) {
                this.table = document.createElement('table');
                this.table.className = this.options.classes.table;
                this.wrapper.appendChild(this.table);
            }

            // Reset row tracking arrays to prevent data jumbling
            this.rows = [];
            this.filteredRows = [];
            this.originalRows = [];

            // Process the imported data with a fresh table - force full initialization
            this.options.data.serverSide = false; // Treat imported data as local data
            
            // Ensure prefetch remains enabled even if switching from server-side to local
            if (this.options.data.prefetch === undefined) {
                this.options.data.prefetch = true;
                this.log('🔄 IMPORT: Setting prefetch to true because it was undefined');
            }
            
            this.processData(data);
            
            // Ensure the loading spinner is hidden
            setTimeout(() => {
                this.hideLoading();
            }, 500);

            // Force a clean sort state
            var headers = this.table.querySelectorAll('th');
            headers.forEach(function (header) {
                header.classList.remove('st-sort-asc', 'st-sort-desc');
                header.classList.add('st-sort-neutral');
                header.removeAttribute('data-sort-state');
            });

            // Call onImport hook
            this.callHook('onImport', data);
        } catch (error) {
            console.error('Import error:', error);

            // Hide loading screen if there's an error
            this.hideLoading();

            // Show notification
            this.showNotification('Import failed: ' + error.message, 'danger');

            // Create error message in the table area
            if (this.table) {
                this.table.remove();
            }

            // Get the filename from the error if possible
            var errorMsg = document.createElement('div');
            errorMsg.className = 'alert alert-danger mt-3';

            // Extract filename if it exists in the modal
            var filename = '';
            var fileDisplay = document.querySelector('.modal .alert.alert-success');
            if (fileDisplay) {
                var filenameMatch = fileDisplay.innerHTML.match(/<b>(.*?)<\/b>/);
                if (filenameMatch && filenameMatch[1]) {
                    filename = filenameMatch[1];
                }
            }

            // Create error message with filename if available
            if (filename) {
                errorMsg.innerHTML = '<h4 class="alert-heading">Import Failed</h4>' +
                    '<p>Failed to import <b>' + filename + '</b></p>' +
                    '<hr>' +
                    '<p class="mb-0">Error: ' + error.message + '</p>';
            } else {
                errorMsg.innerHTML = '<h4 class="alert-heading">Import Failed</h4>' +
                    '<p>Error: ' + error.message + '</p>';
            }

            // Add error message to wrapper
            this.wrapper.appendChild(errorMsg);

            // Add a retry button
            var retryBtn = document.createElement('button');
            retryBtn.className = 'btn btn-primary mt-3';
            retryBtn.innerHTML = '<i class="sa sa-refresh"></i> Try Again';
            retryBtn.addEventListener('click', function () {
                // Reopen the import modal
                var importModal = document.getElementById('importModal');
                if (importModal) {
                    var modal = new bootstrap.Modal(importModal);
                    modal.show();
                }

                // Remove the error message
                errorMsg.remove();
                retryBtn.remove();

                // Create an empty table
                this.table = document.createElement('table');
                this.table.className = this.options.classes.table;
                this.wrapper.appendChild(this.table);

                // Initialize with empty data
                this.processData([]);
            }.bind(this));

            this.wrapper.appendChild(retryBtn);
        }
    }

    detectColumns(data) {
        if (!data || !data.length) {
            return [];
        }

        var firstRow = data[0];
        var columns = [];

        // Create columns from the keys in the first data row
        Object.keys(firstRow).forEach(function (key) {
            // For title, only add spaces before capital letters that aren't part of common abbreviations
            // This preserves terms like ID, URL, API, etc.
            let title = key;
            
            // First, capitalize the first letter
            if (title.length > 0) {
                title = title.charAt(0).toUpperCase() + title.slice(1);
            }
            
            // Only add spaces for camelCase, not for abbreviations
            // This will convert "firstName" to "First Name" but preserve "userID" as "UserID"
            if (!/^[A-Z]+$/.test(title)) { // If not all uppercase (abbreviation)
                title = title.replace(/([a-z])([A-Z])/g, '$1 $2');
            }
            
            columns.push({
                data: key,
                title: title
            });
        });

        return columns;
    }

    setupTable() {
        var self = this;
    
        // Add responsive classes
        if (this.options.responsive.enabled) {
            this.table.classList.add('st-responsive');
        }

        // Setup sort handlers
        if (this.options.sort) {
            var headers = this.table.querySelectorAll('thead th');
            headers.forEach(function (header, index) {
                // Skip columns with data-sortable="false"
                if (header.getAttribute('data-sortable') === 'false') {
                    return;
                }

                header.classList.add('st-sort-neutral');
                header.style.cursor = 'pointer';

                // Store the handler function so we can remove it later
                var handler = function () {
                    // Remove sort classes from all other headers and column cells
                    headers.forEach(function (h, i) {
                        if (h !== header) {
                            h.classList.remove('st-sort-asc', 'st-sort-desc');
                            h.classList.add('st-sort-neutral');
                            h.removeAttribute('data-sort-state');

                            // Remove st-sort-column from cells in other columns
                            Array.from(self.table.querySelectorAll('tbody tr')).forEach(function (row) {
                                if (row.cells[i]) {
                                    row.cells[i].classList.remove('st-sort-column');
                                }
                            });
                        }
                    });

                    // Determine next sort state
                    if (!header.hasAttribute('data-sort-state')) {
                        // First click - ascending
                        header.setAttribute('data-sort-state', 'asc');
                        header.classList.remove('st-sort-neutral');
                        header.classList.add('st-sort-asc');
                        self.addSortColumnClass(index);
                    } else if (header.getAttribute('data-sort-state') === 'asc') {
                        // Second click - descending
                        header.setAttribute('data-sort-state', 'desc');
                        header.classList.remove('st-sort-asc');
                        header.classList.add('st-sort-desc');
                        self.addSortColumnClass(index);
                    } else {
                        // Third click - neutral (reset)
                        header.removeAttribute('data-sort-state');
                        header.classList.remove('st-sort-desc');
                        header.classList.add('st-sort-neutral');
                        self.removeSortColumnClass(index);
                    }

                    self.sortBy(index, header.getAttribute('data-sort-state'));
                };

                header._sortHandler = handler;
                header.addEventListener('click', handler);
            });
        }

        // Remove the code that adds expand buttons to rows
        // This section was previously adding expand buttons to first cells
    
        // Setup responsive row handlers
        if (this.options.responsive.enabled) {
            this.setupResponsiveRows();
        }
    
        // Setup responsive column handling
        if (this.options.responsive.enabled) {
            this.setupResponsive();
        }
    }

    handleSearch(value) {
        this.searchQuery = value; // Store the current search term
    
        if (this.options.data.serverSide) {
            // For server-side searches, we'll reset to page 1 and send a new request
            // Any in-progress request will be canceled by loadAjax method
            this.currentPage = 1; // Reset to first page on new search
            
            // Log that we're initiating a new search
            this.log('Initiating server-side search for:', value);
            
            // loadAjax will now handle canceling any in-progress request
            this.loadAjax();
            
            // Call hook without filtered rows (server handles it)
            this.callHook('onFilter', value, null);
        } else {

            var searchText = value.toLowerCase().trim();

            // Early exit if search is empty - show all rows
            if (!searchText) {
                this.filteredRows = this.rows.slice();
                this.removeNoResultsMessage();
                this.currentPage = 1;
                this.draw();
                this.callHook('onFilter', value, this.filteredRows);
                return;
            }

            // Detect search patterns
            var patterns = {
                isDate: /^\d{1,4}[-/.]?\d{1,2}[-/.]?\d{1,4}$/.test(searchText),
                isMoney: /^\$?\d+(?:,\d{3})*(?:\.\d{1,2})?$/.test(searchText),
                isPhone: this.extractPhoneNumber(searchText),
                isEmail: /@/.test(searchText),
                isNumeric: /^[<>]=?|=|!=?\s*\d+(?:\.\d+)?$/.test(searchText),
                isTime: /^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/.test(searchText),
                isURL: /^(https?:\/\/)?([\w.-]+)\.([a-z]{2,})(\/\S*)?$/.test(searchText),
                isIP: /^(\d{1,3}\.){3}\d{1,3}$/.test(searchText),
                isUUID: /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/.test(searchText),
                isBoolean: /^(true|false|yes|no|1|0)$/i.test(searchText),
                isHexColor: /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(searchText),
                isAlphanumeric: /^[a-zA-Z0-9\s-]+$/.test(searchText),
                isSocialSecurity: /^\d{3}-\d{2}-\d{4}$/.test(searchText)
            };

            // Cache numeric comparison if needed
            var numericComparison = this.parseNumericComparison(searchText);

            // Remove any existing no results message
            this.removeNoResultsMessage();

            // Filter rows
            this.filteredRows = this.rows.filter(function (row) {
                return Array.from(row.element.cells).some(function (cell) {
                    var cellText = cell.textContent.toLowerCase();
                    var cellContent = cell.innerHTML.toLowerCase();

                    // Quick check for exact match
                    if (cellText.includes(searchText)) {
                        return true;
                    }

                    // Regular fuzzy search
                    if (this.fuzzyMatch(cellText, searchText)) {
                        return true;
                    }

                    // Handle date searches
                    if (patterns.isDate) {
                        var cellDate = this.parseDate(cellText);
                        var searchDate = this.parseDate(searchText);
                        if (cellDate && searchDate) {
                            return cellDate.getTime() === searchDate.getTime();
                        }
                    }

                    // Handle money/currency searches
                    if (patterns.isMoney) {
                        var numericSearch = parseFloat(searchText.replace(/[$,]/g, ''));
                        var numericCell = parseFloat(cellText.replace(/[$,]/g, ''));
                        if (!isNaN(numericSearch) && !isNaN(numericCell)) {
                            return numericCell === numericSearch;
                        }
                    }

                    // Handle phone number searches
                    if (patterns.isPhone) {
                        var cellPhone = this.extractPhoneNumber(cellText);
                        return cellPhone && cellPhone.includes(patterns.isPhone);
                    }

                    // Handle email searches
                    if (patterns.isEmail) {
                        return this.emailMatch(cellText, searchText);
                    }

                    // Handle numeric comparisons (>, <, =)
                    if (numericComparison) {
                        var cellNumber = parseFloat(cellText.replace(/[^\d.-]/g, ''));
                        if (!isNaN(cellNumber)) {
                            switch (numericComparison.operator) {
                                case '>': return cellNumber > numericComparison.value;
                                case '<': return cellNumber < numericComparison.value;
                                case '>=': return cellNumber >= numericComparison.value;
                                case '<=': return cellNumber <= numericComparison.value;
                                case '!=': return Math.abs(cellNumber - numericComparison.value) > 0.001;
                                default: return Math.abs(cellNumber - numericComparison.value) < 0.001; // Approximate equality for floats
                            }
                        }
                    }

                    // Check for HTML content matches (for cells with formatted content)
                    if (cellContent !== cellText && cellContent.includes(searchText)) {
                        return true;
                    }

                    return false;
                }, this);
            }, this);

            // Show no results message if needed
            if (this.filteredRows.length === 0) {
                this.showNoResultsMessage(value);
            }

            this.currentPage = 1;
            this.draw();

            // Call onFilter hook
            this.callHook('onFilter', value, this.filteredRows);
        }
    }

    parseNumericComparison(searchText) {
        var match = searchText.match(/^([<>]=?|=|!=)?\s*(\d+(?:\.\d+)?)$/);
        if (match) {
            return {
                operator: match[1] || '=',
                value: parseFloat(match[2])
            };
        }
        return null;
    }

    emailMatch(text, search) {
        // Special handling for email searches
        if (!/@/.test(text)) return false;

        // Split email into parts
        var searchParts = search.split('@');
        var searchUser = searchParts[0];
        var searchDomain = searchParts.length > 1 ? searchParts[1] : '';

        if (!searchDomain) {
            // If only searching for username part
            return text.split('@')[0].includes(searchUser);
        }

        // Full email search
        var textParts = text.split('@');
        var textUser = textParts[0];
        var textDomain = textParts.length > 1 ? textParts[1] : '';

        return textUser.includes(searchUser) && textDomain.includes(searchDomain);
    }

    levenshteinDistance(str1, str2) {
        var m = str1.length;
        var n = str2.length;

        // Create a matrix of size (m+1) x (n+1)
        var dp = [];
        for (var i = 0; i <= m; i++) {
            dp[i] = [];
            for (var j = 0; j <= n; j++) {
                dp[i][j] = 0;
            }
        }

        // Initialize the first row and column
        for (var i = 0; i <= m; i++) dp[i][0] = i;
        for (var j = 0; j <= n; j++) dp[0][j] = j;

        // Fill the matrix
        for (var i = 1; i <= m; i++) {
            for (var j = 1; j <= n; j++) {
                var cost = str1[i - 1] === str2[j - 1] ? 0 : 1;
                dp[i][j] = Math.min(
                    dp[i - 1][j] + 1,      // deletion
                    dp[i][j - 1] + 1,      // insertion
                    dp[i - 1][j - 1] + cost // substitution
                );
            }
        }

        return dp[m][n];
    }

    fuzzyMatch(text, search) {
        // Skip if either text is empty or search is too short
        if (!text || !search || search.length < this.options.fuzzyMatch.minMatchLength) {
            return 0;
        }

        // Exact match first (case insensitive)
        if (text.includes(search)) {
            return 1.0; // Perfect match
        }

        // Handle multi-word search (match any word)
        var searchWords = search.split(/\s+/).filter(function (word) {
            return word.length >= this.options.fuzzyMatch.minMatchLength;
        }, this);

        if (searchWords.length > 1) {
            // Calculate match score for each word and return the best match
            var wordScores = searchWords.map(function (word) {
                return this.fuzzyMatch(text, word);
            }, this);

            // Calculate average score across all words
            var sum = 0;
            for (var i = 0; i < wordScores.length; i++) {
                sum += wordScores[i];
            }
            var avgScore = sum / wordScores.length;

            // If any word has a good match or the average is above threshold, consider it a match
            var bestScore = Math.max.apply(null, wordScores);
            if (bestScore > this.options.fuzzyMatch.threshold ||
                avgScore > this.options.fuzzyMatch.multiWordThreshold) {
                return bestScore;
            }

            return 0;
        }

        // Check for typos using Levenshtein distance for short search terms
        if (search.length <= 10) {
            // Find best matching substring
            var bestMatchScore = 0;

            // For short search terms, check against each word in the text
            var textWords = text.split(/\s+/);
            for (var i = 0; i < textWords.length; i++) {
                var word = textWords[i];
                if (Math.abs(word.length - search.length) <= this.options.fuzzyMatch.maxDistance) {
                    var distance = this.levenshteinDistance(word.toLowerCase(), search);
                    if (distance <= this.options.fuzzyMatch.maxDistance) {
                        // Convert distance to a similarity score (0-1)
                        var similarity = 1 - (distance / Math.max(word.length, search.length));
                        bestMatchScore = Math.max(bestMatchScore, similarity);
                    }
                }
            }

            if (bestMatchScore >= this.options.fuzzyMatch.threshold) {
                return bestMatchScore;
            }
        }

        // Sequential character matching (traditional fuzzy search)
        var searchChars = search.split('');
        var currentIndex = 0;
        var matchCount = 0;
        var consecutiveMatches = 0;
        var maxConsecutive = 0;

        // Try to find all characters in sequence
        for (var i = 0; i < searchChars.length; i++) {
            var char = searchChars[i];
            var prevIndex = currentIndex;
            currentIndex = text.indexOf(char, currentIndex);

            if (currentIndex === -1) {
                // Calculate partial match score if we've matched enough characters
                if (matchCount >= this.options.fuzzyMatch.minMatchLength) {
                    var matchRatio = matchCount / searchChars.length;
                    var consecutiveBonus = maxConsecutive / searchChars.length;

                    // Weight the score: 70% for matched chars, 30% for consecutive matches
                    var score = (matchRatio * 0.7) + (consecutiveBonus * 0.3);

                    return score >= this.options.fuzzyMatch.threshold ? score : 0;
                }
                return 0;
            }

            matchCount++;

            // Check if this match is consecutive with the previous one
            if (currentIndex === prevIndex + 1) {
                consecutiveMatches++;
                maxConsecutive = Math.max(maxConsecutive, consecutiveMatches);
            } else {
                consecutiveMatches = 0;
            }

            currentIndex += 1;
        }

        // All characters matched
        return 1.0;
    }

    sortBy(columnIndex, direction) {
        this.currentSortColumn = direction ? columnIndex : undefined;
        this.currentSortDirection = direction;
    
        if (this.options.data.serverSide) {
            // For server-side sorting, log the operation
            this.log(`Sorting server-side data by column ${columnIndex}, direction: ${direction}`);
            
            // loadAjax will now handle canceling any in-progress requests
            // and showing the loading spinner
            this.loadAjax();
    
        this.callHook('onSort', columnIndex, direction);
            } else {
        if (!direction) {
            // Reset to original order using originalIndex
            this.filteredRows.sort(function (a, b) {
                return a.originalIndex - b.originalIndex;
            });
        } else {
                // Use the specialized comparison function which handles phone numbers, dates, etc.
                this.filteredRows.sort(this.getComparisonFunction(columnIndex, direction));
        }
        this.draw();
        this.callHook('onSort', columnIndex, direction);
        }
    }

    getComparisonFunction(columnIndex, direction) {
        var self = this;

        return function (a, b) {
            var aVal = a.element.cells[columnIndex].textContent.trim();
            var bVal = b.element.cells[columnIndex].textContent.trim();

            // Try phone number comparison
            var aPhone = self.extractPhoneNumber(aVal);
            var bPhone = self.extractPhoneNumber(bVal);
            if (aPhone && bPhone) {
                return direction === 'asc' ?
                    aPhone.localeCompare(bPhone) :
                    bPhone.localeCompare(aPhone);
            }

            // Try date comparison
            var aDate = self.parseDate(aVal);
            var bDate = self.parseDate(bVal);
            if (aDate && bDate) {
                return direction === 'asc' ?
                    aDate.getTime() - bDate.getTime() :
                    bDate.getTime() - aDate.getTime();
            }

            // Try numeric comparison (including currency)
            var aNum = self.extractNumber(aVal);
            var bNum = self.extractNumber(bVal);
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            }

            // Default to string comparison
            return direction === 'asc' ?
                aVal.localeCompare(bVal) :
                bVal.localeCompare(aVal);
        };
    }

    extractPhoneNumber(str) {
        // Check if the string contains digits and common phone separators
        if (!/\d/.test(str)) return null;

        // Check for common phone number patterns with various formats
        // This will match formats like: 
        // - 501-240-920
        // - (123) 456-7890
        // - +1 234-567-8901
        // - 123.456.7890
        // - 123 456 7890
        var phonePattern = /^(?:\+?\d{1,3}[-\s.]?)?\(?(\d{3})\)?[-\s.]?(\d{3})[-\s.]?(\d{3,4})$/;
        var match = str.match(phonePattern);

        if (match) {
            // Extract only digits for comparison
            return str.replace(/[^0-9]/g, '');
        }

        // If no standard pattern matches but the string contains mostly digits and separators
        // (useful for non-standard phone formats)
        if (str.replace(/[\d\s\-().+]/g, '').length === 0 &&
            str.replace(/[^0-9]/g, '').length >= 7) {
            return str.replace(/[^0-9]/g, '');
        }

        return null;
    }

    parseDate(str) {
        // Enhanced date parsing to handle more formats
        if (!str) return null;

        // Try standard date formats first
        var date = new Date(str);
        if (!isNaN(date.getTime())) {
            return date;
        }

        // Handle common date formats: MM/DD/YYYY, DD/MM/YYYY, YYYY-MM-DD, etc.
        var patterns = [
            // MM/DD/YYYY or MM-DD-YYYY
            {
                regex: /^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/,
                handler: function (m) { return new Date(parseInt(m[3]), parseInt(m[1]) - 1, parseInt(m[2])); }
            },
            // DD/MM/YYYY or DD-MM-YYYY
            {
                regex: /^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/,
                handler: function (m) { return new Date(parseInt(m[3]), parseInt(m[2]) - 1, parseInt(m[1])); }
            },
            // YYYY/MM/DD or YYYY-MM-DD
            {
                regex: /^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/,
                handler: function (m) { return new Date(parseInt(m[1]), parseInt(m[2]) - 1, parseInt(m[3])); }
            },
            // Month name formats: Jan 1, 2023 or January 1, 2023
            {
                regex: /^([a-zA-Z]{3,9})\s+(\d{1,2}),?\s+(\d{4})$/,
                handler: function (m) {
                    var months = {
                        jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
                        jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11,
                        january: 0, february: 1, march: 2, april: 3, may: 4, june: 5,
                        july: 6, august: 7, september: 8, october: 9, november: 10, december: 11
                    };
                    var month = months[m[1].toLowerCase()];
                    if (month !== undefined) {
                        return new Date(parseInt(m[3]), month, parseInt(m[2]));
                    }
                    return null;
                }
            }
        ];

        for (var i = 0; i < patterns.length; i++) {
            var matches = str.match(patterns[i].regex);
            if (matches) {
                var date = patterns[i].handler(matches);
                if (date && !isNaN(date.getTime())) {
                    return date;
                }
            }
        }

        return null;
    }

    extractNumber(str) {
        // Extract numeric value from string, handling currency and other formats
        return parseFloat(str.replace(/[^0-9.-]+/g, ''));
    }

    draw() {
        // Call beforeDraw hook
        this.callHook('beforeDraw');
    
        if (this.options.data.serverSide) {
            // For server-side, we should not recreate the entire table structure
            // Just update the tbody contents with the latest data
        const tbody = this.table.querySelector('tbody');
            if (!tbody) {
                console.error('SmartTables: No tbody found in table');
                return;
            }
    
        // Clear existing rows
        while (tbody.firstChild) {
            tbody.removeChild(tbody.firstChild);
        }
    
            // Add new rows based on the current data
            this.rows.forEach(rowData => {
                tbody.appendChild(rowData.element);
            });
            
            // After drawing rows, reapply sort column class if needed for server-side data
            if (this.currentSortColumn !== undefined) {
                this.log(`Reapplying sort column class for column ${this.currentSortColumn}`);
                this.addSortColumnClass(this.currentSortColumn);
            }
            
            // Important: Re-apply the responsive column hiding for server-side pagination
            if (this.options.responsive.enabled && this.hiddenColumns.length > 0) {
                this.log('Reapplying responsive column hiding for server-side data');
                
                // Re-hide columns that should be hidden
                this.hiddenColumns.forEach(columnIndex => {
                    // Hide header
                    const header = this.table.querySelector(`thead th:nth-child(${columnIndex + 1})`);
                    if (header) header.style.display = 'none';
                    
                    // Hide all cells in this column
                    const cells = this.table.querySelectorAll(`tbody tr td:nth-child(${columnIndex + 1})`);
                    cells.forEach(cell => cell.style.display = 'none');
                });
                
                // Setup responsive rows after reapplying column hiding
                this.setupResponsiveRows();
            }
            
            // Update pagination if needed
            if (this.options.pagination) {
                this.updatePagination();
            }
        } else {
            const start = (this.currentPage - 1) * this.options.perPage;
            const end = start + this.options.perPage;
            const tbody = this.table.querySelector('tbody');
    
            // Store current hidden columns state
            const currentHiddenColumns = this.hiddenColumns.slice();
    
            // Store expanded states before redrawing
            const expandedStates = {};
            this.filteredRows.forEach(function (rowData) {
                if (rowData.expanded) {
                    expandedStates[rowData.originalIndex] = true;
                }
            });
    
            while (tbody.firstChild) {
                tbody.removeChild(tbody.firstChild);
            }
    
            // Apply hidden state to new rows immediately
            this.filteredRows.slice(start, end).forEach(function (rowData) {
                var row = rowData.element.cloneNode(true);
    
                // Remove any st-sort-column classes before adding the row
                Array.from(row.cells).forEach(function (cell) {
                    cell.classList.remove('st-sort-column');
                });
    
                currentHiddenColumns.forEach(function (index) {
                    if (row.cells[index]) {
                        row.cells[index].style.display = 'none';
                    }
                });
                tbody.appendChild(row);
    
                // Restore expanded state
                if (expandedStates[rowData.originalIndex]) {
                    rowData.expanded = true;
                    row.classList.add('expanded');
                    if (row.cells[0]) {
                        row.cells[0].classList.add('st-expand-active');
                        row.cells[0].classList.add('st-expand');
                    }
                }
            });
    
            // Apply the same hidden state to headers
        var headers = this.table.querySelectorAll('thead th');
        currentHiddenColumns.forEach(function (index) {
            if (headers[index]) {
                headers[index].style.display = 'none';
            }
        });
    
        if (this.options.responsive.enabled) {
            this.setupResponsiveRows();
    
                // Recreate expanded rows
            Array.from(tbody.querySelectorAll('tr.expanded')).forEach(function (row) {
                var rowIndex = Array.from(tbody.querySelectorAll('tr:not(.st-child-row)')).indexOf(row);
                    var rowData = this.filteredRows[start + rowIndex];
                if (rowData) {
                    rowData.element = row;
                    this.updateExpandedRow(rowData);
                }
            }, this);
        }
    
            // After drawing rows, reapply sort column class if needed
        var sortedHeader = this.table.querySelector('th.st-sort-asc, th.st-sort-desc');
        if (sortedHeader) {
            var columnIndex = Array.from(sortedHeader.parentNode.children).indexOf(sortedHeader);
            this.addSortColumnClass(columnIndex);
            }
        }
    
        if (this.options.pagination) {
            this.updatePagination();
        }
    
        // Call afterDraw hook
        this.callHook('afterDraw');
        
        // Only remove d-none class after all calculations are complete
        if (this.table.classList.contains('d-none') && this.isTableInitialized && !this.hasDNoneBeenRemoved) {
            requestAnimationFrame(() => {
                setTimeout(() => {
                    this.table.classList.remove('d-none');
                    this.table.style.opacity = '1'; // Set opacity when removing d-none
                    this.hasDNoneBeenRemoved = true;
                    this.log('TABLE READY: Removed d-none class after all calculations');
                }, 300); // Longer delay to ensure all calculations are complete
            });
        }
    }

    updatePagination() {
        if (this.paginationContainer) {
            this.paginationContainer.remove();
        }
    
        // For server-side, always use the totalRows value from the last server response
        // For client-side, calculate from filtered rows
        const totalRows = this.options.data.serverSide ? this.totalRows : this.filteredRows.length;
        
        // Log the total rows used for pagination
        this.log('Pagination using total rows:', totalRows, 'Current search:', this.searchQuery);
        
        const totalPages = Math.ceil(totalRows / this.options.perPage);
    
        // Create container row
        var container = document.createElement('div');
        container.className = 'row';
    
        // Create left column for entries dropdown and info text
        var infoCol = document.createElement('div');
        infoCol.className = 'col-12 col-sm-6 d-flex align-items-center justify-content-sm-start justify-content-center gap-2 order-1 order-sm-0';
    
        // Move entries dropdown here
        var entriesWrapper = document.createElement('div');
        entriesWrapper.className = 'dropdown';
    
        var entriesBtn = document.createElement('button');
        entriesBtn.className = 'btn btn-sm btn-outline-secondary dropdown-toggle pe-2 ps-2 py-1 no-arrow';
        entriesBtn.setAttribute('data-bs-toggle', 'dropdown');
        entriesBtn.innerHTML = this.options.perPage + ' <i class="sa sa-chevron-down"></i>';
    
        var entriesMenu = document.createElement('ul');
        entriesMenu.className = 'dropdown-menu';
    
        var entriesOptions = [10, 15, 25, 50, 100, 'All'];
        var self = this;
    
        entriesOptions.forEach(function (value) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.className = 'dropdown-item' + (self.options.perPage === value ? ' active' : '');
            a.href = '#';
            a.textContent = value;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var newValue = value === 'All' ? (self.options.data.serverSide ? totalRows : self.filteredRows.length) : value;
                self.options.perPage = newValue;
                entriesBtn.innerHTML = value + ' <i class="sa sa-chevron-down"></i>';
                self.currentPage = 1;
                if (self.options.data.serverSide) {
                    self.loadAjax();
                } else {
                    self.draw();
                }
                // Update active state
                entriesMenu.querySelectorAll('.dropdown-item').forEach(function (item) {
                    item.classList.remove('active');
                });
                this.classList.add('active');
            });
            li.appendChild(a);
            entriesMenu.appendChild(li);
        });
    
        entriesWrapper.appendChild(entriesBtn);
        entriesWrapper.appendChild(entriesMenu);
    
        // Add entries dropdown to info column
        infoCol.appendChild(entriesWrapper);
    
        // Add info text - consistent calculation based on stored totalRows
        var start = ((this.currentPage - 1) * this.options.perPage) + 1;
        var end = Math.min(start + this.options.perPage - 1, totalRows);
        var infoText = document.createElement('div');
        infoText.className = 'text-muted small';
        infoText.textContent = `Showing ${start} to ${end} of ${totalRows} entries`;
        infoCol.appendChild(infoText);
    
        // Create right column for pagination
        var paginationCol = document.createElement('div');
        paginationCol.className = 'col-12 col-sm-6 d-flex align-items-center justify-content-sm-end justify-content-center mb-4 mb-sm-0';
    
        var nav = document.createElement('nav');
        var ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
    
        var firstLi = document.createElement('li');
        firstLi.className = 'page-item st-first-page' + (this.currentPage === 1 ? ' disabled' : '');
        var firstLink = document.createElement('a');
        firstLink.className = 'page-link';
        firstLink.href = '#';
        firstLink.innerHTML = '«';
        firstLi.appendChild(firstLink);
        ul.appendChild(firstLi);
    
        var prevLi = document.createElement('li');
        prevLi.className = 'page-item st-prev-page' + (this.currentPage === 1 ? ' disabled' : '');
        var prevLink = document.createElement('a');
        prevLink.className = 'page-link';
        prevLink.href = '#';
        prevLink.innerHTML = '<span class="d-none d-sm-none d-md-inline-block">Prev</span> <span class="d-inline-block d-sm-inline-block d-md-none">‹</span>';
        prevLi.appendChild(prevLink);
        ul.appendChild(prevLi);
    
        var startPage = Math.max(1, this.currentPage - 1);
        var endPage = Math.min(totalPages, startPage + 2);
        startPage = Math.max(1, endPage - 2);
    
        if (startPage > 1) {
            ul.appendChild(this.createPageItem('1'));
            if (startPage > 2) {
                var ellipsisStart = document.createElement('li');
                ellipsisStart.className = 'page-item disabled';
                ellipsisStart.innerHTML = '<span class="page-link">...</span>';
                ul.appendChild(ellipsisStart);
            }
        }
    
        for (var i = startPage; i <= endPage; i++) {
            ul.appendChild(this.createPageItem(i.toString(), i === this.currentPage));
        }
    
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                var ellipsisEnd = document.createElement('li');
                ellipsisEnd.className = 'page-item disabled';
                ellipsisEnd.innerHTML = '<span class="page-link">...</span>';
                ul.appendChild(ellipsisEnd);
            }
            ul.appendChild(this.createPageItem(totalPages.toString()));
        }
    
        var nextLi = document.createElement('li');
        nextLi.className = 'page-item st-next-page' + (this.currentPage === totalPages ? ' disabled' : '');
        var nextLink = document.createElement('a');
        nextLink.className = 'page-link';
        nextLink.href = '#';
        nextLink.innerHTML = '<span class="d-none d-sm-none d-md-inline-block">Next</span> <span class="d-inline-block d-sm-inline-block d-md-none">›</span>';
        nextLi.appendChild(nextLink);
        ul.appendChild(nextLi);
    
        var lastLi = document.createElement('li');
        lastLi.className = 'page-item st-last-page' + (this.currentPage === totalPages ? ' disabled' : '');
        var lastLink = document.createElement('a');
        lastLink.className = 'page-link';
        lastLink.href = '#';
        lastLink.innerHTML = '»';
        lastLi.appendChild(lastLink);
        ul.appendChild(lastLi);
    
        nav.appendChild(ul);
        paginationCol.appendChild(nav);
    
        // Add columns to container
        container.appendChild(infoCol);
        container.appendChild(paginationCol);
    
        this.paginationContainer = container;
        this.wrapper.appendChild(container);
    
        // Add click handler
        ul.addEventListener('click', function (e) {
            e.preventDefault();
            const link = e.target.closest('.page-link');
            if (!link) return;
            
            // Check for stuck AJAX requests that might be causing pagination issues
            if (self.isLoadingAjax) {
                self.log('🔄 PAGE CHANGE: Detected click while loading AJAX');
                // If loading has been going on for too long, it might be stuck
                self.clearHangingRequests();
            }
    
            var newPage = self.currentPage;
            const parentItem = link.closest('.page-item');
    
            if (parentItem.classList.contains('st-first-page')) {
                newPage = 1;
            } else if (parentItem.classList.contains('st-prev-page')) {
                newPage = self.currentPage - 1;
            } else if (parentItem.classList.contains('st-next-page')) {
                newPage = self.currentPage + 1;
            } else if (parentItem.classList.contains('st-last-page')) {
                newPage = totalPages;
            } else {
                const pageNum = link.getAttribute('data-page');
                if (pageNum) {
                    newPage = parseInt(pageNum);
                }
            }
    
            if (newPage !== self.currentPage && newPage >= 1 && newPage <= totalPages) {
                // Store the old page for debugging
                const oldPage = self.currentPage;
                
                // Update the current page
                self.currentPage = newPage;
                
                // Log the page change including search query
                self.log(`Changing page from ${oldPage} to ${newPage} with search: "${self.searchQuery}"`);
                
                if (self.options.data.serverSide) {
                    // Check if we have cached data for this page
                    const tempPage = self.currentPage;
                    const cacheKey = self.generateCacheKey();
                    if (self.ajaxCache.has(cacheKey)) {
                        self.log(`🔄 PAGE CHANGE: Found cached data for page ${newPage} with key ${cacheKey}, search: "${self.searchQuery}"`);
                        
                        // Manually trigger prefetch for next page when using cached data
                        const cachedData = self.ajaxCache.get(cacheKey);
                        if (cachedData && cachedData.total && self.options.data.prefetch) {
                            const totalPages = Math.ceil(cachedData.total / self.options.perPage);
                            
                            // If we're not on the last page, prefetch the next page
                            if (newPage < totalPages) {
                                self.log(`🔄 PAGE CHANGE: Manually triggering prefetch for page ${newPage + 1} after using cached data, search: "${self.searchQuery}"`);
                                
                                // After loadAjax completes (which will use the cached data), 
                                // we need to manually trigger prefetch for the next page
                                setTimeout(() => {
                                    self.prefetchNextPage();
                                }, 100); // Small delay to ensure page data is loaded first
                            }
                        }
                    } else {
                        self.log(`🔄 PAGE CHANGE: No cache found for page ${newPage}, search: "${self.searchQuery}"`);
                        self.log(`🔄 PAGE CHANGE: Available cache keys:`, Array.from(self.ajaxCache.keys()));
                    }
                    
                    // For server-side processing, trigger a new AJAX request
                    self.log(`Loading server-side data for page ${newPage}, search: "${self.searchQuery}"`);
                    
                    // loadAjax will now handle canceling any in-progress requests
                    // and showing the loading spinner
                    self.loadAjax();
            } else {
                    // For client-side processing, just redraw with the new page slice
                    self.log(`🔄 PAGE CHANGE: Redrawing with client-side data for page ${newPage}, search: "${self.searchQuery}"`);
                self.draw();
                }
                self.callHook('onPaginate', newPage, self);
            }
        });
    }

    createPageItem(text, isActive) {
        var li = document.createElement('li');
        li.className = 'page-item' + (isActive ? ' active' : '');
        var link = document.createElement('a');
        link.className = 'page-link';
        link.href = '#';
        link.textContent = text;
        link.setAttribute('data-page', text); // Add page number as data attribute
        li.appendChild(link);
        return li;
    }

    setupResponsive() {
        if (!this.options.responsive.enabled) return;

        var self = this;

        // Store column information for responsive behavior
        this.responsiveColumns = [];

        // Get all headers
        var headers = Array.from(this.table.querySelectorAll('thead th'));

        // Create colgroup if it doesn't exist
        var colgroup = this.table.querySelector('colgroup');
        if (!colgroup) {
            colgroup = document.createElement('colgroup');
            this.table.insertBefore(colgroup, this.table.firstChild);
        } else {
            // Clear existing cols
            colgroup.innerHTML = '';
        }

        // Create col elements and store column information
        headers.forEach(function (header, index) {
            // Create col element
            var col = document.createElement('col');
            colgroup.appendChild(col);

            // Get priority from data attribute or options
            var priority = header.getAttribute('data-priority') ||
                (self.options.responsive.columnPriorities &&
                    self.options.responsive.columnPriorities[index]) ||
                (index + 1);

            // Store column information
            self.responsiveColumns.push({
                index: index,
                header: header,
                col: col,
                priority: parseInt(priority, 10),
                minWidth: null, // Will be measured
                visible: true,
                alwaysVisible: header.classList.contains('always-visible') || false,
                neverVisible: header.classList.contains('never-visible') || false,
                // Don't store sort-related classes in the column information
                sortClass: false
            });
        });

        // Measure natural column widths
        this.calculateColumnWidths(); // Changed from measureColumnWidths to calculateColumnWidths

        // Set up resize observer (DataTables-style automatic detection)
        if (window.ResizeObserver) {
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
            }

            this.resizeObserver = new ResizeObserver(function (entries) {
                // Check if container went from 0 width to actual width (became visible)
                for (let entry of entries) {
                    const newWidth = entry.contentRect.width;
                    const oldWidth = self.lastObservedWidth || 0;
                    
                    self.log('ResizeObserver: width changed from', oldWidth, 'to', newWidth);
                    
                    // If container became visible (went from 0 to actual width), recalculate everything
                    if (oldWidth === 0 && newWidth > 0) {
                        self.log('Container became visible, recalculating responsive layout');
                        self.calculateColumnWidths();
                    } else if (newWidth > 0) {
                        // Normal resize, just check responsive display
                        self.checkResponsiveDisplay();
                    }
                    
                    self.lastObservedWidth = newWidth;
                }

                // Update expanded rows after responsive changes
                self.updateExpandedRowsAfterResize();

                // Call onResize hook
                self.callHook('onResize', self);
            });

            this.resizeObserver.observe(this.wrapper);
        } else {
            // Fallback for browsers without ResizeObserver
            window.addEventListener('resize', function () {
                self.checkResponsiveDisplay();

                // Update expanded rows after responsive changes
                self.updateExpandedRowsAfterResize();

                // Call onResize hook
                self.callHook('onResize', self);
            });
        }

        // Initial check
        this.checkResponsiveDisplay();
    }

    checkResponsiveDisplay() {
        var self = this;
        var tableWidth = this.wrapper.offsetWidth;
        this.log('checkResponsiveDisplay - Current table width:', tableWidth);

        var requiredWidth = 0;
        var availableWidth = tableWidth;

        // First pass: calculate required width for all columns
        this.responsiveColumns.forEach(function (column) {
            requiredWidth += column.minWidth;
        });

        this.log('Total required width for all columns:', requiredWidth);

        // If all columns fit, show them all
        if (requiredWidth <= tableWidth) {
            this.log('All columns fit, showing all');
            this.responsiveColumns.forEach(function (column) {
                self.showColumn(column.index);
            });

            // Update expand indicators after showing all columns
            this.updateExpandIndicators();
            
            // For server-side data, we need to update any expanded rows that are already open
            if (this.options.data.serverSide) {
                this.log('Server-side data: Updating expanded rows after showing all columns');
                this.updateExpandedRowsAfterResize();
            }
            return;
        }

        // Sort columns by priority (higher number = lower priority)
        var sortedColumns = this.responsiveColumns.slice().sort(function (a, b) {
            return b.priority - a.priority; // Descending order by priority
        });

        // Second pass: hide columns by priority until they fit
        this.log('Hiding columns by priority until they fit');
        sortedColumns.forEach(function (column) {
            // Skip columns that should always be visible
            if (column.alwaysVisible) {
                self.log('Column', column.index, 'is always visible, skipping');
                return;
            }

            // If we still need to hide columns
            if (requiredWidth > tableWidth) {
                // Hide this column
                self.log('Hiding column', column.index, 'to save', column.minWidth, 'px');
                self.hideColumn(column.index);

                // Reduce required width
                requiredWidth -= column.minWidth;
                self.log('Required width now:', requiredWidth);
            } else {
                // Show this column
                self.log('Showing column', column.index);
                self.showColumn(column.index);
            }
        });

        // Update colgroup widths
        this.updateColWidths();

        // Update expand indicators based on hidden columns
        this.updateExpandIndicators();
        
        // For server-side data, we need to update any expanded rows due to column visibility changes
        if (this.options.data.serverSide) {
            this.log('Server-side data: Updating expanded rows after responsive changes');
            this.updateExpandedRowsAfterResize();
        }

        this.log('Final hidden columns:', this.hiddenColumns);
    }

    updateColWidths() {
        var tableWidth = this.wrapper.offsetWidth;
        var visibleColumns = this.responsiveColumns.filter(function (col) {
            return col.visible;
        });

        // Calculate total width of visible columns
        var totalMinWidth = visibleColumns.reduce(function (sum, col) {
            return sum + col.minWidth;
        }, 0);

        // Set width for each visible column
        visibleColumns.forEach(function (column) {
            var percentage = (column.minWidth / totalMinWidth) * 100;
            column.col.style.width = percentage + '%';
        });
    }

    hideColumn(index) {
        var column = this.responsiveColumns.find(function (col) {
            return col.index === index;
        });

        if (!column || !column.visible) return;

        // Mark as hidden
        column.visible = false;

        // Hide header
        column.header.style.display = 'none';

        // Hide all cells in this column
        var cells = this.table.querySelectorAll('tbody tr td:nth-child(' + (index + 1) + ')');
        cells.forEach(function (cell) {
            cell.style.display = 'none';
        });

        // Add to hidden columns array if not already there
        if (this.hiddenColumns.indexOf(index) === -1) {
            this.hiddenColumns.push(index);
        }

        // Emit event
        this.emitEvent('columnHide', [index]);
    }

    showColumn(index) {
        var column = this.responsiveColumns.find(function (col) {
            return col.index === index;
        });

        if (!column || column.visible) return;

        // Mark as visible
        column.visible = true;

        // Show header
        column.header.style.display = '';

        // Show all cells in this column
        var cells = this.table.querySelectorAll('tbody tr td:nth-child(' + (index + 1) + ')');
        cells.forEach(function (cell) {
            cell.style.display = '';
        });

        // Remove from hidden columns array
        var hiddenIndex = this.hiddenColumns.indexOf(index);
        if (hiddenIndex !== -1) {
            this.hiddenColumns.splice(hiddenIndex, 1);
        }

        // Emit event
        this.emitEvent('columnShow', [index]);
    }

    showNotification(message, type) {
        // Create a unique ID for the toast container if it doesn't exist
        var toastContainerId = 'st-toast-container';
        var toastContainer = document.getElementById(toastContainerId);

        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.id = toastContainerId;
            document.body.appendChild(toastContainer);
        }

        // Create a unique ID for this toast
        var toastId = 'st-toast-' + Date.now();

        // Determine the appropriate Bootstrap color and icon based on type
        var colorClass = 'bg-primary';
        var icon = '✓';

        switch (type) {
            case 'success':
                colorClass = 'bg-success text-white';
                icon = '✓';
                break;
            case 'info':
                colorClass = 'bg-info text-white';
                icon = 'ℹ';
                break;
            case 'warning':
                colorClass = 'bg-warning';
                icon = '⚠';
                break;
            case 'danger':
            case 'error':
                colorClass = 'bg-danger text-white';
                icon = '⚠';
                break;
        }

        // Create the toast element with simplified structure
        var toast = document.createElement('div');
        toast.className = 'toast ' + colorClass + ' align-items-center border-0 py-2 px-3';
        toast.id = toastId;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        // Create the inner structure
        var innerDiv = document.createElement('div');
        innerDiv.className = 'd-flex';

        // Create toast body
        var toastBody = document.createElement('div');
        toastBody.className = 'toast-body d-flex align-items-center justify-content-center';
        toastBody.innerHTML = icon + ' ' + message;

        // Create close button
        var closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn btn-system ms-auto';
        closeButton.setAttribute('data-bs-dismiss', 'toast');
        closeButton.setAttribute('aria-label', 'Close');

        // Check if sprite.svg is available
        if (document.querySelector('use[href*="sprite.svg#x"]')) {
            closeButton.innerHTML = '<svg class="sa-icon sa-icon-light"><use href="icons/sprite.svg#x"></use></svg>';
        } else {
            // Fallback to a simple × if the SVG sprite is not available
            closeButton.innerHTML = '×';
        }

        // Assemble the toast
        innerDiv.appendChild(toastBody);
        innerDiv.appendChild(closeButton);
        toast.appendChild(innerDiv);

        // Add the toast to the container
        toastContainer.appendChild(toast);

        // Initialize and show the toast using Bootstrap's Toast API
        var bsToast = new bootstrap.Toast(toast, {
            animation: true,
            autohide: true,
            delay: 3000 // Shorter delay for these compact toasts
        });

        bsToast.show();

        // Remove the toast element after it's hidden
        toast.addEventListener('hidden.bs.toast', function () {
            toast.remove();

            // Remove the container if it's empty
            if (toastContainer.children.length === 0) {
                toastContainer.remove();
            }
        });
    }

    addSortColumnClass(columnIndex) {
        this.toggleSortColumnClass(columnIndex, true);
    }

    removeSortColumnClass(columnIndex) {
        this.toggleSortColumnClass(columnIndex, false);
    }

    toggleSortColumnClass(columnIndex, isAdding) {
        var method = isAdding ? 'add' : 'remove';
        var selector = 'tbody tr td:nth-child(' + (columnIndex + 1) + ')';

        // Handle all visible cells
        var allCells = this.table.querySelectorAll(selector);
        allCells.forEach(function (cell) {
            cell.classList[method]('st-sort-column');
        });

        // Handle hidden columns in expanded rows
        if (this.hiddenColumns.includes(columnIndex)) {
            var headerText = this.table.querySelector('thead th:nth-child(' + (columnIndex + 1) + ')');
            if (!headerText) return;

            var headerContent = headerText.textContent;
            var expandedContent = this.table.querySelectorAll('.st-hidden-column-item');

            expandedContent.forEach(function (item) {
                var label = item.querySelector('.st-hidden-column-label');
                if (label && label.textContent.startsWith(headerContent)) {
                    item.classList[method]('st-sort-column');
                }
            });
        }
    }

    showNoResultsMessage(searchTerm) {
        this.removeNoResultsMessage(); // Remove any existing message first

        var noResults = document.createElement('div');
        noResults.className = 'st-no-results alert alert-info mt-3';
        noResults.innerHTML = '<svg class="sa-icon sa-thin sa-icon-2x sa-bold sa-icon-info hidden-sm"><use href="icons/sprite.svg#frown"></use></svg> <h6 class="mb-0">No search results found for <b>"' + searchTerm + '"</b></h6>';
        this.wrapper.appendChild(noResults);
    }

    removeNoResultsMessage() {
        var existingMessage = this.wrapper.querySelector('.st-no-results');
        if (existingMessage) {
            existingMessage.remove();
        }
    }

    /**
     * Deletes a row from the table by sending a DELETE request to the server.
     * @param {number|string} rowId - The ID of the row to delete.
     * @param {Object} [options={}] - Optional configuration for the delete operation.
     * @returns {Promise<boolean>} - Resolves to true on success, false if aborted.
     */

    async delete(rowId, options = {}) {
        const {
            confirmMessage = 'Are you sure you want to delete this record?',
            showNotifications = true, // Automatically show success/error notifications
        } = options;

        // Call beforeDelete hook
        this.callHook('beforeDelete', rowId, options);

        // Create and show modal instead of using browser confirm
        const confirmed = await new Promise((resolve) => {
            // Create modal element
            const modalId = 'st-delete-modal-' + rowId;
            const modalHtml = `
                <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true" style="--bs-modal-width: 450px;">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark bg-opacity-50 shadow-5 translucent-dark">
                            <div class="modal-header border-bottom-0">
                                <h4 class="modal-title text-white d-flex align-items-center">
                                    Delete record
                                </h4>
                                <button type="button" class="btn btn-system btn-system-light ms-auto" data-bs-dismiss="modal" aria-label="Close">
                                    <svg class="sa-icon sa-icon-2x">
                                        <use href="icons/sprite.svg#x"></use>
                                    </svg>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-danger bg-danger border-danger text-light border-opacity-50 bg-opacity-10 mb-0">
                                    Are you sure you want to delete record <span class="fw-600 text-danger">[${rowId}]</span> <br>
                                    This action cannot be undone.
                                </div>
                            </div>
                            <div class="modal-footer border-top-0">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">No, cancel</button>
                                <button type="button" class="btn btn-danger" id="confirm-${modalId}">Yes, delete</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            
            // Add modal to document
            const modalElement = SmartTables.createNode(modalHtml);
            document.body.appendChild(modalElement);
            
            // Get the modal instance
            const modal = document.getElementById(modalId);
            
            // Handle modal events
            modal.addEventListener('hidden.bs.modal', () => {
                // Clean up modal when hidden
                modal.remove();
            });
            
            // Handle confirm button click
            document.getElementById(`confirm-${modalId}`).addEventListener('click', () => {
                resolve(true);
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal.hide();
            });
            
            // Handle cancel button click
            modal.querySelector('[data-bs-dismiss="modal"]').addEventListener('click', () => {
                resolve(false);
            });
            
            // Show the modal
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        });
        
        // If user cancelled, abort the operation
        if (!confirmed) {
            this.log('Delete operation aborted by user');
            return false;
        }

        // Find the row element for visual feedback
        const rowElement = this.table.querySelector(`tbody tr[data-id="${rowId}"]`);
        
        // Note: Visual feedback (like adding/removing classes) should be handled through 
        // hooks in the application code rather than directly in the library
        
        // Check if we're using client-side data
        if (Array.isArray(this.options.data.source)) {
            this.log('Using client-side delete for row ID:', rowId);
            
            // Find and remove the item from the source array
             const idField = this.options.data.idField || 'id';
            const index = this.options.data.source.findIndex(item => {
                const itemId = item[idField] !== undefined ? item[idField] : (item[idField.toUpperCase()] !== undefined ? item[idField.toUpperCase()] : null);
                return itemId !== null && itemId.toString() === rowId.toString();
            });
            
            if (index === -1) {
                const error = new Error('Record not found for deletion');
                
                // Remove deleting class
                if (rowElement) {
                    rowElement.classList.remove('deleting');
                }
                
                if (showNotifications) {
                    this.showNotification('Failed to delete record: Record not found', 'danger');
                }
                
                this.callHook('afterDelete', rowId, null, false);
                throw error;
            }
            
            // Remove the item
            this.options.data.source.splice(index, 1);
            
            // Check if we need to adjust the current page after deletion
            const totalRows = this.options.data.source.length;
            const totalPages = Math.ceil(totalRows / this.options.perPage);
            if (this.currentPage > totalPages && totalPages > 0) {
                this.currentPage = totalPages; // Move to the last valid page
                this.log('Adjusted current page to', this.currentPage, 'after deletion');
            }
            
            // Update the table
            this.updateTableData(this.options.data.source);
            
            // If there's an active search, reapply it
            if (this.searchQuery && this.searchQuery.trim() !== '') {
                setTimeout(() => this.handleSearch(this.searchQuery), 50);
            }
            
            if (showNotifications) {
                this.showNotification(`Record #${rowId} deleted successfully`, 'success');
            }
            
            this.callHook('afterDelete', rowId, { success: true }, true);
            return true;
        }

        // Construct the DELETE URL (e.g., /api/employees/1)
        const deleteUrl = `${this.options.data.source}/${rowId}`;

        // Use the same headers as the table's Ajax requests
        const fetchOptions = {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept-Encoding': 'gzip, deflate',
                ...this.options.data.headers
            }
        };

        this.log('Sending DELETE request to:', deleteUrl);

        try {
            // Send DELETE request
            const response = await fetch(deleteUrl, fetchOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to delete record');
            }

            this.log('Delete successful for row ID:', rowId);

            // Invalidate cache and refresh the table
            this.clearAjaxCache();
            this.loadAjax();

            // Show success notification
            if (showNotifications) {
                this.showNotification(`Record #${rowId} deleted successfully`, 'success');
            }

            // Call afterDelete hook
            this.callHook('afterDelete', rowId, data, true);

            return true;
        } catch (error) {
            this.log('Delete failed for row ID:', rowId, 'Error:', error.message);

            // Show error notification
            if (showNotifications) {
                this.showNotification(`Failed to delete record: ${error.message}`, 'danger');
            }

            // Call afterDelete hook with failure
            this.callHook('afterDelete', rowId, null, false);

            throw error; // Re-throw for external handling
        }
    }

    /**
     * Edits a row in the table, handling both server-side and client-side data.
     * @param {number|string} rowId - The ID of the row to edit.
     * @param {Object} [initialData=null] - Optional initial data to pre-fill form.
     * @param {Object} [options={}] - Optional configuration for the edit operation.
     * @returns {Promise<boolean>} - Resolves to true on success, false on failure.
     */

    async edit(rowId, initialData = null, options = {}) {
        const {
            showNotifications = true,
            title = 'Edit Record',
            submitButtonText = 'Save Changes',
            cancelButtonText = 'Cancel'
        } = options;

        // Call beforeEdit hook - if it returns false, abort the edit
        if (this.callHook('beforeEdit', rowId, initialData, options) === false) {
            this.log('Edit operation aborted by beforeEdit hook');
            return false;
        }

        // Find the row element
        const rowElement = this.table.querySelector(`tbody tr[data-id="${rowId}"]`);
        if (!rowElement) {
            if (showNotifications) {
                this.showNotification('Error: Row not found', 'danger');
            }
            this.callHook('afterEdit', rowId, null, false);
            return false;
        }

        // Get row data if not provided
        let rowData = initialData;
        if (!rowData) {
            // Find data from source
            if (Array.isArray(this.options.data.source)) {
                const idField = this.options.data.idField || 'id';
                const item = this.options.data.source.find(item => {
                    const itemId = item[idField] !== undefined ? item[idField] : 
                        (item[idField.toUpperCase()] !== undefined ? item[idField.toUpperCase()] : null);
                    return itemId !== null && itemId.toString() === rowId.toString();
                });
                
                if (item) {
                    rowData = { ...item };
                } else {
                    if (showNotifications) {
                        this.showNotification('Error: Record data not found', 'danger');
                    }
                    this.callHook('afterEdit', rowId, null, false);
                    return false;
                }
            } else {
                // For server-side data, extract from row cells
                rowData = {};
                const cells = Array.from(rowElement.cells);
                this.options.data.columns.forEach((column, index) => {
                    if (column.data && cells[index]) {
                        rowData[column.data] = cells[index].textContent.trim();
                    }
                });
            }
        }

        // Create a unique ID for the modal
        const modalId = `st-edit-modal-${Date.now()}`;

        // Create modal HTML
        const modalHTML = `
        <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog" aria-labelledby="${modalId}-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="${modalId}-label">${title}</h5>
                        <button class="btn btn-system ms-auto" type="button" data-bs-dismiss="modal" aria-label="Close">
                            <svg class="sa-icon sa-icon-2x">
                                <use href="icons/sprite.svg#x"></use>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="${modalId}-form">
                            ${this.generateEditFormFields(rowData)}
                            <input type="hidden" name="rowId" value="${rowId}">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${cancelButtonText}</button>
                        <button type="button" class="btn btn-primary" id="${modalId}-submit">${submitButtonText}</button>
                    </div>
                </div>
            </div>
        </div>
        `;

        // Allow hook to customize the modal HTML
        const customModalHTML = this.callHook('onEditModalCreated', modalHTML, rowId, rowData, options);
        
        // Add modal to the DOM (use custom HTML if provided by hook)
        document.body.insertAdjacentHTML('beforeend', customModalHTML || modalHTML);

        // Get the modal element
        const modalElement = document.getElementById(modalId);
        
        // Initialize it with Bootstrap
        const modal = new bootstrap.Modal(modalElement);
        
        // Notify hooks that modal is about to be shown
        this.callHook('onEditModalBeforeShow', modalElement, rowId, rowData, options);
        
        // Show the modal
        modal.show();
        
        // Notify hooks that modal has been shown
        this.callHook('onEditModalAfterShow', modalElement, rowId, rowData, options);

        // Return a promise that resolves when the form is submitted or rejected when canceled
        return new Promise((resolve, reject) => {
            // Handle form submission
            const submitButton = document.getElementById(`${modalId}-submit`);
            submitButton.addEventListener('click', async (event) => {
                // Collect form data
                const form = document.getElementById(`${modalId}-form`);
                
                // Validate the form
                if (!form.checkValidity()) {
                    // Prevent default form submission
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Add validation classes to show feedback
                    form.classList.add('was-validated');
                    
                    // Show notification about validation errors
                    this.showNotification('Please fill all required fields correctly', 'danger');
                    
                    // Do not proceed with submission
                    return;
                }
                
                const formData = new FormData(form);
                
                // Convert FormData to object, skipping the rowId field
                const updatedData = {};
                for (const [key, value] of formData.entries()) {
                    if (key !== 'rowId') {
                        updatedData[key] = value;
                    }
                }
                
                // Allow hooks to modify the updated data
                const modifiedData = this.callHook('onEditDataCollected', updatedData, rowId, rowData, options) || updatedData;
                
                // Close the modal
                modal.hide();
                
                try {
                    // Apply the edit
                    const success = await this.applyEdit(rowId, modifiedData, options);
                    
                    // Remove modal from DOM after animation completes
                    modalElement.addEventListener('hidden.bs.modal', () => {
                        modalElement.remove();
                    });
                    
                    resolve(success);
                } catch (error) {
                    // Show error notification
                    if (showNotifications) {
                        this.showNotification(`Failed to update record: ${error.message}`, 'danger');
                    }
                    
                    // Remove modal from DOM after animation completes
                    modalElement.addEventListener('hidden.bs.modal', () => {
                        modalElement.remove();
                    });
                    
                    reject(error);
                }
            });
            
            // Handle modal dismissal
            modalElement.addEventListener('hidden.bs.modal', () => {
                // Only handle dismissal if not already resolved/rejected by submit button
                if (modalElement.parentNode) {
                    modalElement.remove();
                    this.callHook('onEditCancelled', rowId, rowData, options);
                    resolve(false);
                }
            });
        });
    }

    /**
     * Generates form fields for the edit modal based on row data
     * @private
     * @param {Object} rowData - The data to create form fields for
     * @returns {string} HTML string of form fields
     */
    generateEditFormFields(rowData) {
        let fieldsHTML = '';
        
        for (const column of this.options.data.columns) {
            // Skip columns without a data field or marked as non-editable
            if (!column.data || column.editable === false) continue;
            
            const value = rowData[column.data] || '';
            const label = column.title || column.data;
            const fieldId = `edit-field-${column.data}`;
            
            // Different input types based on data
            let inputHTML;
            if (column.type === 'boolean' || typeof value === 'boolean') {
                // Checkbox for boolean values
                inputHTML = `
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="${fieldId}" name="${column.data}" 
                            ${value === true || value === 'true' || value === '1' ? 'checked' : ''}>
                        <label class="form-check-label" for="${fieldId}">${label}${column.required ? ' <span class="fw-bold text-danger">*</span>' : ''}</label>
                    </div>
                `;
            } else if (column.type === 'select' && column.options) {
                // Select dropdown for options
                const options = column.options.map(opt => {
                    const optValue = typeof opt === 'object' ? opt.value : opt;
                    const optLabel = typeof opt === 'object' ? opt.label : opt;
                    return `<option value="${optValue}" ${optValue == value ? 'selected' : ''}>${optLabel}</option>`;
                }).join('');
                
                inputHTML = `
                    <div class="mb-3">
                        <label for="${fieldId}" class="form-label">${label}${column.required ? ' <span class="fw-bold text-danger">*</span>' : ''}</label>
                        <select class="form-select" id="${fieldId}" name="${column.data}">
                            ${options}
                        </select>
                    </div>
                `;
            } else if (column.type === 'textarea' || (value && value.length > 100)) {
                // Textarea for long text
                inputHTML = `
                    <div class="mb-3">
                        <label for="${fieldId}" class="form-label">${label}${column.required ? ' <span class="fw-bold text-danger">*</span>' : ''}</label>
                        <textarea class="form-control" id="${fieldId}" name="${column.data}" rows="3">${value}</textarea>
                    </div>
                `;
            } else {
                // Map column type to appropriate input type
                let inputType = "text"; // Default input type
                let additionalAttrs = "";
                
                // Map column types to HTML5 input types
                if (column.type === 'number' || column.format === 'number') {
                    inputType = 'number';
                    // Add step, min, max attributes if specified
                    if (column.step !== undefined) additionalAttrs += ` step="${column.step}"`;
                    if (column.min !== undefined) additionalAttrs += ` min="${column.min}"`;
                    if (column.max !== undefined) additionalAttrs += ` max="${column.max}"`;
                } else if (column.type === 'date' || column.format === 'date') {
                    inputType = 'date';
                    // Convert value to YYYY-MM-DD format if it's a valid date
                    try {
                        const date = new Date(value);
                        if (!isNaN(date.getTime())) {
                            const isoDate = date.toISOString().split('T')[0];
                            const value = isoDate;
                        }
                    } catch (e) {
                        // Keep original value if parsing fails
                    }
                } else if (column.type === 'email' || column.format === 'email') {
                    inputType = 'email';
                } else if (column.type === 'tel' || column.format === 'phone') {
                    inputType = 'tel';
                    if (column.pattern) additionalAttrs += ` pattern="${column.pattern}"`;
                } else if (column.type === 'url') {
                    inputType = 'url';
                } else if (column.type === 'password') {
                    inputType = 'password';
                } else if (column.type === 'time') {
                    inputType = 'time';
                } else if (column.type === 'color') {
                    inputType = 'color';
                }
                
                // Add placeholder if specified
                if (column.placeholder) {
                    additionalAttrs += ` placeholder="${column.placeholder}"`;
                }
                
                // Add required attribute if specified
                if (column.required) {
                    additionalAttrs += ` required`;
                }
                
                // Use the inputType to create the appropriate input
                inputHTML = `
                    <div class="mb-3">
                        <label for="${fieldId}" class="form-label">${label}${column.required ? ' <span class="fw-bold text-danger">*</span>' : ''}</label>
                        <input type="${inputType}" class="form-control" id="${fieldId}" name="${column.data}" value="${this.escapeHTML(value.toString())}"${additionalAttrs}>
                    </div>
                `;
            }
            
            fieldsHTML += inputHTML;
        }
        
        return fieldsHTML;
    }

    /**
     * Applies edits to a row, handling both client-side and server-side data
     * @private
     * @param {number|string} rowId - The ID of the row to edit
     * @param {Object} updatedData - The updated data for the row
     * @param {Object} options - Options from the edit method
     * @returns {Promise<boolean>} - Resolves to true on success
     */
    async applyEdit(rowId, updatedData, options = {}) {
        const { showNotifications = true } = options;

        // Call beforeApplyEdit hook - if it returns false, abort the edit
        if (this.callHook('beforeApplyEdit', rowId, updatedData, options) === false) {
            this.log('Apply edit operation aborted by beforeApplyEdit hook');
            return false;
        }

        // Find the row element for notifications
        const rowElement = this.table.querySelector(`tbody tr[data-id="${rowId}"]`);

        // Check if we're using client-side data
        if (Array.isArray(this.options.data.source)) {
            this.log('Using client-side edit for row ID:', rowId);
            
            try {
                // Find the item in the source array
                const idField = this.options.data.idField || 'id';
                const index = this.options.data.source.findIndex(item => {
                    const itemId = item[idField] !== undefined ? item[idField] : 
                        (item[idField.toUpperCase()] !== undefined ? item[idField.toUpperCase()] : null);
                    return itemId !== null && itemId.toString() === rowId.toString();
                });
                
                if (index === -1) {
                    throw new Error('Record not found for editing');
                }
                
                // Allow hooks to modify the data before applying
                const processedData = this.callHook('onProcessEditData', updatedData, rowId, this.options.data.source[index], options) || updatedData;
                
                // Update the item with processed data
                this.options.data.source[index] = { ...this.options.data.source[index], ...processedData };
                this.log('Updated client-side data:', this.options.data.source[index]);
                
                // Update the table
                this.updateTableData(this.options.data.source);
                
                // Reapply search if active
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    setTimeout(() => this.handleSearch(this.searchQuery), 50);
                }
                
                if (showNotifications) {
                    this.showNotification(`Record #${rowId} updated successfully`, 'success');
                }
                
                // Call afterEdit hook with success
                this.callHook('afterEdit', rowId, this.options.data.source[index], true);
                
                // Call onEditSuccess hook with updated data
                this.callHook('onEditSuccess', rowId, this.options.data.source[index], processedData);
                
                return true;
            } catch (error) {
                if (showNotifications) {
                    this.showNotification(`Failed to update record: ${error.message}`, 'danger');
                }
                
                // Call afterEdit hook with failure
                this.callHook('afterEdit', rowId, null, false);
                
                // Call onEditError hook with error
                this.callHook('onEditError', rowId, error, updatedData);
                
                throw error;
            }
        }

        // Server-side edit
        const editUrl = `${this.options.data.source}/${rowId}`;
        
        // Allow hooks to modify the fetch options
        const defaultFetchOptions = {
            method: 'PUT', // Use PUT for updates
            headers: {
                'Content-Type': 'application/json',
                'Accept-Encoding': 'gzip, deflate',
                ...this.options.data.headers
            },
            body: JSON.stringify(updatedData)
        };
        
        // Let hooks modify fetch options
        const fetchOptions = this.callHook('onEditFetchOptions', defaultFetchOptions, rowId, updatedData, options) || defaultFetchOptions;

        this.log('Sending PUT request to:', editUrl);

        try {
            const response = await fetch(editUrl, fetchOptions);
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to update record');
            }

            this.log('Edit successful for row ID:', rowId);

            // Invalidate cache and refresh the table
            this.clearAjaxCache();
            this.loadAjax();

            if (showNotifications) {
                this.showNotification(`Record #${rowId} updated successfully`, 'success');
            }

            // Call afterEdit hook with success
            this.callHook('afterEdit', rowId, data, true);
            
            // Call onEditSuccess hook with server data
            this.callHook('onEditSuccess', rowId, data, updatedData);
            
            return true;
        } catch (error) {
            this.log('Edit failed for row ID:', rowId, 'Error:', error.message);

            if (showNotifications) {
                this.showNotification(`Failed to update record: ${error.message}`, 'danger');
            }

            // Call afterEdit hook with failure
            this.callHook('afterEdit', rowId, null, false);
            
            // Call onEditError hook with error
            this.callHook('onEditError', rowId, error, updatedData);
            
            throw error;
        }
    }

    destroy() {
        // Ensure plugins is an array before calling hooks
        if (!Array.isArray(this.plugins)) {
            this.plugins = [];
        }

        // Call beforeDestroy hook
        this.callHook('beforeDestroy');

        // Reset initialization state
        this.isTableInitialized = false;

        // Remove resize observer
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
            this.resizeObserver = null;
        }

        // Remove event listeners
        if (this.table) {
            var headers = this.table.querySelectorAll('th');
            headers.forEach(function (header) {
                if (header._sortHandler) {
                    header.removeEventListener('click', header._sortHandler);
                    delete header._sortHandler;
                }
            });
        }

        // Clear all data references
        this.rows = [];
        this.filteredRows = [];
        this.originalRows = [];
        this.hiddenColumns = [];
        this.columnWidths = [];

        // Clear pagination state
        this.currentPage = 1;

        // Completely remove and recreate the table element
        if (this.table) {
            // Store reference to parent and table attributes
            var parent = this.table.parentNode;
            var tagName = this.table.tagName;
            var id = this.table.id;
            var className = this.options.classes.table;
            var attributes = {};

            // Store any custom attributes
            for (var i = 0; i < this.table.attributes.length; i++) {
                var attr = this.table.attributes[i];
                if (attr.name !== 'id' && attr.name !== 'class') {
                    attributes[attr.name] = attr.value;
                }
            }

            // Remove the old table completely
            this.table.remove();

            // Create a brand new table element
            this.table = document.createElement(tagName);
            if (id) this.table.id = id;
            this.table.className = className;

            // Restore any custom attributes
            for (var name in attributes) {
                this.table.setAttribute(name, attributes[name]);
            }

            // Add the new table to the wrapper
            if (this.wrapper) {
                this.wrapper.appendChild(this.table);
            } else if (parent) {
                parent.appendChild(this.table);
            }
        }

        // Remove toolbar if it exists
        if (this.toolbar) {
            this.toolbar.remove();
            this.toolbar = null;
        }

        // Remove pagination if it exists
        if (this.paginationContainer) {
            this.paginationContainer.remove();
            this.paginationContainer = null;
        }

        // Remove no results message if it exists
        var existingMessage = this.wrapper.querySelector('.st-no-results');
        if (existingMessage) {
            existingMessage.remove();
        }

        // Remove any child rows
        var childRows = this.wrapper.querySelectorAll('tr.child');
        childRows.forEach(function (row) {
            row.remove();
        });

        // Clear any search input
        var searchInput = this.wrapper.querySelector('.st-search');
        if (searchInput) searchInput.value = '';

        // Reset any other state
        this.sortColumn = null;
        this.sortDirection = null;

        // Force garbage collection of any references
        setTimeout(function () {
            if (window.gc) window.gc();
        }, 100);

        // Destroy plugins
        this.plugins.forEach(function (plugin) {
            if (typeof plugin.destroy === 'function') {
                plugin.destroy();
            }
        });

        // Clear plugins array
        this.plugins = [];

        // Call afterDestroy hook
        this.callHook('afterDestroy');
    }

    // Add plugin system methods
    initializePlugins() {
        this.plugins = [];

        if (Array.isArray(this.options.plugins)) {
            // Initialize each plugin
            this.options.plugins.forEach(function (plugin) {
                this.registerPlugin(plugin);
            }, this);
        }
    }

    registerPlugin(plugin) {
        if (typeof plugin !== 'object' || !plugin.name) {
            console.error('Invalid plugin format. Plugin must be an object with a name property.');
            return;
        }

        // Create plugin instance
        var pluginInstance = {
            name: plugin.name,
            instance: this
        };

        // Copy plugin methods
        for (var key in plugin) {
            if (key !== 'name' && typeof plugin[key] === 'function') {
                pluginInstance[key] = plugin[key].bind(pluginInstance);
            }
        }

        // Call plugin init method if it exists
        if (typeof pluginInstance.init === 'function') {
            pluginInstance.init();
        }

        // Add to plugins array
        this.plugins.push(pluginInstance);

        console.log('Plugin registered:', plugin.name);
    }

    getPlugin(name) {
        return this.plugins.find(function (plugin) {
            return plugin.name === name;
        });
    }

    // Add hook system methods
    callHook(hookName, ...args) {
        let result;

        // Call hook from options if it exists
        if (this.options.hooks && typeof this.options.hooks[hookName] === 'function') {
            // Add this instance as the last argument if not already included
            if (args[args.length - 1] !== this) {
                args.push(this);
            }

            // Call the hook and capture its return value
            result = this.options.hooks[hookName].apply(this, args);
            
            // If the hook returns false explicitly, abort the operation
            if (result === false) {
                return false;
            }
        }

        // Ensure plugins is an array before iterating
        if (!Array.isArray(this.plugins)) {
            this.plugins = [];
        }

        // Call hook method on all plugins if they have it
        for (let i = 0; i < this.plugins.length; i++) {
            const plugin = this.plugins[i];
            if (typeof plugin[hookName] === 'function') {
                const pluginResult = plugin[hookName].apply(plugin, args);
                // If any plugin returns false explicitly, abort the operation
                if (pluginResult === false) {
                    return false;
                }
            }
        }

        // Emit event for external listeners
        this.emitEvent(hookName, args);

        // Return the result from the hook
        return result;
    }

    // Event emitter system
    emitEvent(eventName, args) {
        // Create custom event
        var event = new CustomEvent('st:' + eventName, {
            detail: {
                instance: this,
                args: args
            },
            bubbles: true,
            cancelable: true
        });

        // Dispatch event on the table element
        this.table.dispatchEvent(event);
    }

    setupResponsiveRows() {
        if (!this.options.responsive.enabled) return;

        const self = this;
        const tbody = this.table.querySelector('tbody');
        if (!tbody) return;

        // Remove any existing click handlers to prevent duplicates
        if (tbody._expandClickHandler) {
            tbody.removeEventListener('click', tbody._expandClickHandler);
        }

        // Create and store the handler function
        tbody._expandClickHandler = e => {
            // Check if the click was on the first cell or its child elements
            const cell = e.target.closest('td:first-child');

            // Skip if we clicked inside a child row or if there are no hidden columns
            if (!cell || self.hiddenColumns.length === 0 || e.target.closest('.st-child-row')) {
                return;
            }

            const row = cell.closest('tr');
            if (!row) return;
            
            // For server-side data, use special handling
            if (self.options.data.serverSide) {
                // Toggle the expanded class directly
                const isExpanded = row.classList.contains('expanded');
                
                if (isExpanded) {
                    // If already expanded, collapse it
                    row.classList.remove('expanded');
                    if (cell) cell.classList.remove('st-expand-active');
                    
                    // Remove any existing child row
                    const existingChild = row.nextElementSibling;
                    if (existingChild && existingChild.classList.contains('st-child-row')) {
                        existingChild.remove();
                    }
                } else {
                    // Expand it and create the expanded content
                    row.classList.add('expanded');
                    if (cell) cell.classList.add('st-expand-active');
                    
                    // Create temporary rowData for the expand function
                    const tempRowData = {
                        element: row,
                        expanded: true
                    };
                    
                    // Update the expanded row content
                    self.updateExpandedRow(tempRowData);
                    
                    // Make sure Bootstrap components are initialized - redundant but defensive
                    setTimeout(() => {
                        const childRow = row.nextElementSibling;
                        if (childRow && childRow.classList.contains('st-child-row')) {
                            self.initializeBootstrapComponents(childRow);
                        }
                    }, 0);
                }
                
                return;
            }

            // Get row index and calculate the correct offset for client-side data
            const rowIndex = Array.from(tbody.querySelectorAll('tr:not(.st-child-row)')).indexOf(row);
            const pageOffset = (self.currentPage - 1) * self.options.perPage;
            const rowData = self.filteredRows[pageOffset + rowIndex];

            if (!rowData) {
                console.warn('Could not find row data for index:', pageOffset + rowIndex);
                return;
            }

            // Update the row element reference in case it was cloned
            rowData.element = row;

            // Toggle expanded state
            rowData.expanded = !rowData.expanded;

            // Update the row display
            self.updateExpandedRow(rowData);
        };

        // Add the click handler
        tbody.addEventListener('click', tbody._expandClickHandler);

        // Add expand indicator to first cells only if there are hidden columns
        if (this.hiddenColumns.length > 0) {
            const rows = tbody.querySelectorAll('tr:not(.st-child-row)');
            rows.forEach((row, index) => {
                if (row.cells.length > 0) {
                    const firstCell = row.cells[0];
                    firstCell.classList.add('st-expand');

                    // For server-side data, check the expanded state from the DOM
                    if (self.options.data.serverSide) {
                        if (row.classList.contains('expanded')) {
                            firstCell.classList.add('st-expand-active');
                            // Create temporary rowData
                            const tempRowData = {
                                element: row,
                                expanded: true
                            };
                            self.updateExpandedRow(tempRowData);
                        }
                    } else {
                        // For client-side data, use the rowData state
                        const pageOffset = (self.currentPage - 1) * self.options.perPage;
                        const rowData = self.filteredRows[pageOffset + index];

                        if (rowData && rowData.expanded) {
                            firstCell.classList.add('st-expand-active');
                            self.updateExpandedRow(rowData);
                        }
                    }
                }
            });
        } else {
            // Remove expand classes if no hidden columns
            const rows = tbody.querySelectorAll('tr:not(.st-child-row)');
            rows.forEach(row => {
                if (row.cells.length > 0) {
                    const firstCell = row.cells[0];
                    firstCell.classList.remove('st-expand', 'st-expand-active');
                }
            });

            // Reset expanded state for all rows
            this.filteredRows.forEach(rowData => {
                if (rowData) rowData.expanded = false;
            });

            // Remove any child rows
            const childRows = tbody.querySelectorAll('.st-child-row');
            childRows.forEach(row => row.remove());
        }
    }

    updateExpandedRow(rowData) {
        if (!rowData || !rowData.element) {
            console.warn('Invalid row data');
            return;
        }

        var row = rowData.element;
        if (!row.parentNode) {
            console.warn('Row is not in the DOM');
            return;
        }

        // Don't do anything if there are no hidden columns
        if (this.hiddenColumns.length === 0) {
            rowData.expanded = false;
            return;
        }

        // Update row expanded class
        if (rowData.expanded) {
            row.classList.add('expanded');
        } else {
            row.classList.remove('expanded');
        }

        // Update first cell with visual indicator
        var firstCell = row.cells[0];
        if (firstCell) {
            // Always ensure st-expand class is present for the click handler
            firstCell.classList.add('st-expand');

            if (rowData.expanded) {
                firstCell.classList.add('st-expand-active');
            } else {
                firstCell.classList.remove('st-expand-active');
            }
        }

        // Remove existing child row if any
        var existingChild = row.nextElementSibling;
        if (existingChild && existingChild.classList.contains('st-child-row')) {
            existingChild.remove();
        }

        if (rowData.expanded) {
            // Create new child row
            var childRow = document.createElement('tr');
            childRow.className = 'st-child-row';

            // Create child row content
            var cell = document.createElement('td');
            cell.colSpan = row.cells.length;
            cell.className = 'st-child-content';

            var content = document.createElement('div');
            content.className = 'st-hidden-columns gap-2 gap-md-2';

            // Add hidden column data
            this.hiddenColumns.forEach(function (columnIndex) {
                var header = this.table.querySelector('thead th:nth-child(' + (columnIndex + 1) + ')');
                if (!header) return;

                var headerText = header.textContent;
                // Use innerHTML instead of textContent to preserve any HTML formatting
                var cellValue = (columnIndex < row.cells.length && row.cells[columnIndex]) 
                    ? row.cells[columnIndex].innerHTML 
                    : '';

                var item = document.createElement('div');
                item.className = 'st-hidden-column-item flex-column flex-md-column flex-lg-row';

                var label = document.createElement('span');
                label.className = 'st-hidden-column-label';
                label.textContent = headerText + ': ';

                var value = document.createElement('span');
                value.className = 'st-hidden-column-value';
                value.innerHTML = cellValue; // Use innerHTML to preserve any HTML formatting

                item.appendChild(label);
                item.appendChild(value);
                content.appendChild(item);
            }, this);

            cell.appendChild(content);
            childRow.appendChild(cell);

            // Insert the child row after the parent row
            row.parentNode.insertBefore(childRow, row.nextSibling);
            
            // Reinitialize Bootstrap components
            this.initializeBootstrapComponents(childRow);
        }
    }

    // Helper method to initialize Bootstrap components like popovers, tooltips, etc.
    initializeBootstrapComponents(element) {
        this.log('Initializing interactive components for expanded row content');
        
        // Initialize popovers if Bootstrap is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            const popovers = element.querySelectorAll('[data-bs-toggle="popover"]');
            popovers.forEach(popover => {
                try {
                    new bootstrap.Popover(popover);
                    this.log('Initialized Bootstrap popover');
                } catch (e) {
                    this.log('Error initializing Bootstrap popover:', e);
                }
            });
        }
        
        // Initialize tooltips if Bootstrap is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltips = element.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                try {
                    new bootstrap.Tooltip(tooltip);
                    this.log('Initialized Bootstrap tooltip');
                } catch (e) {
                    this.log('Error initializing Bootstrap tooltip:', e);
                }
            });
        }
        
        // Initialize dropdowns if Bootstrap is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
            const dropdowns = element.querySelectorAll('[data-bs-toggle="dropdown"]');
            dropdowns.forEach(dropdown => {
                try {
                    new bootstrap.Dropdown(dropdown);
                    this.log('Initialized Bootstrap dropdown');
                } catch (e) {
                    this.log('Error initializing Bootstrap dropdown:', e);
                }
            });
        }
        
        // Handle jQuery based Bootstrap (older versions)
        if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined') {
            try {
                // jQuery Popovers
                if (typeof $.fn.popover === 'function') {
                    $(element).find('[data-toggle="popover"]').popover();
                    this.log('Initialized jQuery popovers');
                }
                
                // jQuery Tooltips
                if (typeof $.fn.tooltip === 'function') {
                    $(element).find('[data-toggle="tooltip"]').tooltip();
                    this.log('Initialized jQuery tooltips');
                }
                
                // jQuery Dropdowns
                if (typeof $.fn.dropdown === 'function') {
                    $(element).find('[data-toggle="dropdown"]').dropdown();
                    this.log('Initialized jQuery dropdowns');
                }
            } catch (e) {
                this.log('Error initializing jQuery Bootstrap components:', e);
            }
        }
        
        // Dispatch custom events for other libraries or custom code to hook into
        
        // 1. smarttables:components-init event - fires before initialization
        const initEvent = new CustomEvent('smarttables:components-init', {
            detail: { element: element },
            bubbles: true,
            cancelable: true
        });
        element.dispatchEvent(initEvent);
        
        // 2. smarttables:row-expanded event - fires after initialization
        const expandedEvent = new CustomEvent('smarttables:row-expanded', {
            detail: { element: element },
            bubbles: true
        });
        element.dispatchEvent(expandedEvent);
        
        // Call the onExpandedRowInit hook if defined
        this.callHook('onExpandedRowInit', element);
    }

    updateExpandedRowsAfterResize() {
        var self = this;
        var tbody = this.table.querySelector('tbody');
        if (!tbody) return;

        // Find all expanded rows
        var expandedRows = Array.from(tbody.querySelectorAll('tr.expanded'));

        // Special handling for server-side data
        if (this.options.data.serverSide) {
            expandedRows.forEach(function(expandedRow) {
                // For server-side data, we need to recreate the expanded content directly
                // First, get the row index in the current view
                var allRows = Array.from(tbody.querySelectorAll('tr:not(.st-child-row)'));
                var rowIndex = allRows.indexOf(expandedRow);
                
                // Create temporary row data if we can't find it in filteredRows
                var tempRowData = {
                    element: expandedRow,
                    expanded: true
                };
                
                // Remove existing child row if any
                const existingChild = expandedRow.nextElementSibling;
                if (existingChild && existingChild.classList.contains('st-child-row')) {
                    existingChild.remove();
                }
                
                // Create new child row
                var childRow = document.createElement('tr');
                childRow.className = 'st-child-row';
                
                // Create child row content
                var cell = document.createElement('td');
                cell.colSpan = expandedRow.cells.length;
                cell.className = 'st-child-content';
                
                var content = document.createElement('div');
                content.className = 'st-hidden-columns gap-2 gap-md-2';
                
                // Add hidden column data
                self.hiddenColumns.forEach(function(columnIndex) {
                    var header = self.table.querySelector('thead th:nth-child(' + (columnIndex + 1) + ')');
                    if (!header) return;
                    
                    var headerText = header.textContent;
                    var cellValue = (columnIndex < expandedRow.cells.length && expandedRow.cells[columnIndex]) 
                        ? expandedRow.cells[columnIndex].innerHTML 
                        : '';
                    
                    var item = document.createElement('div');
                    item.className = 'st-hidden-column-item flex-column flex-md-column flex-lg-row';
                    
                    var label = document.createElement('span');
                    label.className = 'st-hidden-column-label';
                    label.textContent = headerText + ': ';
                    
                    var value = document.createElement('span');
                    value.className = 'st-hidden-column-value';
                    value.innerHTML = cellValue;
                    
                    item.appendChild(label);
                    item.appendChild(value);
                    content.appendChild(item);
                });
                
                cell.appendChild(content);
                childRow.appendChild(cell);
                
                // Insert the child row after the parent row
                expandedRow.parentNode.insertBefore(childRow, expandedRow.nextSibling);
                
                // Reinitialize Bootstrap components
                self.initializeBootstrapComponents(childRow);
            });
            
            return;
        }

        // Standard handling for client-side data
        expandedRows.forEach(function (row) {
            // Find the row data
            var rowData = self.filteredRows.find(function (r) {
                return r && r.element && (r.element === row || r.element.isEqualNode(row));
            });

            if (rowData) {
                // Update the expanded row content
                self.updateExpandedRow(rowData);
            }
        });

        // Also update any rows that have expanded state in data but not in DOM
        this.filteredRows.forEach(function (rowData) {
            if (rowData && rowData.expanded && rowData.element && !rowData.element.classList.contains('expanded')) {
                rowData.element.classList.add('expanded');
                self.updateExpandedRow(rowData);
            }
        });
    }

    // Add this new method to update expand indicators
    updateExpandIndicators() {
        if (!this.options.responsive.enabled) return;

        var tbody = this.table.querySelector('tbody');
        if (!tbody) return;

        // Check if we have any hidden columns
        if (this.hiddenColumns.length > 0) {
            // Add expand indicators to first cells
            var rows = tbody.querySelectorAll('tr:not(.st-child-row)');
            rows.forEach(function (row) {
                if (row.cells.length > 0) {
                    var firstCell = row.cells[0];
                    firstCell.classList.add('st-expand');

                    // Make sure cursor style is applied
                    firstCell.style.cursor = 'pointer';
                }
            });
        } else {
            // Remove expand indicators from first cells
            var rows = tbody.querySelectorAll('tr:not(.st-child-row)');
            rows.forEach(function (row) {
                if (row.cells.length > 0) {
                    var firstCell = row.cells[0];
                    firstCell.classList.remove('st-expand', 'st-expand-active');

                    // Reset cursor style
                    firstCell.style.cursor = '';
                }
            });

            // Remove any child rows
            var childRows = tbody.querySelectorAll('.st-child-row');
            childRows.forEach(function (row) {
                row.remove();
            });

            // Reset expanded state for all rows
            this.filteredRows.forEach(function (rowData) {
                rowData.expanded = false;
            });
        }
    }

    // Add debug logging method
    log(...args) {
        if (this.options.debug) {
            console.log('[SmartTables]', ...args);
        }
    }

    exportData(format) {
        const data = this.getExportData();
        
        // Call onExport hook
        this.callHook('onExport', format, data);
        
        switch (format) {
            case 'excel':
                this.exportToExcel(data);
                break;
            case 'csv':
                this.exportToCSV(data);
                break;
            case 'copy':
                this.copyToClipboard(data);
                break;
            case 'pdf':
                this.exportToPDF(data);
                break;
            case 'json':
                this.exportToJSON(data);
                break;
            case 'xml':
                this.exportToXML(data);
                break;
            case 'html':
                this.exportToHTML(data);
                break;
        }
    }

    getExportData() {
        var headers = Array.from(this.table.querySelectorAll('thead th')).map(function (th) {
            return th.textContent.trim();
        });

        var rows = this.filteredRows.map(function (row) {
            return Array.from(row.element.cells).map(function (cell) {
                return cell.textContent.trim();
            });
        });

        return {
            headers: headers,
            rows: rows
        };
    }

    exportToExcel(data) {
        var wb = XLSX.utils.book_new();
        var ws = XLSX.utils.aoa_to_sheet([data.headers].concat(data.rows));

        // Add column widths
        ws['!cols'] = data.headers.map(function () {
            return { wch: 15 }; // Set default column width
        });

        XLSX.utils.book_append_sheet(wb, ws, "Sheet1");

        // Generate filename with timestamp
        var filename = 'export_' + new Date().toISOString().slice(0, 19).replace(/[-:]/g, '') + '.xlsx';

        // Trigger download
        XLSX.writeFile(wb, filename);

        // Show success notification with filename
        this.showNotification('Successfully exported to ' + filename, 'success');
    }

    exportToCSV(data) {
        let csvContent = '';

        // Add headers
        csvContent += data.headers.join(',') + '\n';

        // Add rows
        data.rows.forEach(function (row) {
            // Escape values that contain commas
            const escapedRow = row.map(function (value) {
                if (value.includes(',') || value.includes('"') || value.includes('\n')) {
                    return '"' + value.replace(/"/g, '""') + '"';
                }
                return value;
            });
            csvContent += escapedRow.join(',') + '\n';
        });

        // Generate filename based on table ID or current date
        const filename = (this.table.id || 'table-export') + '-' + new Date().toISOString().slice(0, 10) + '.csv';

        // Create download link
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });

        if (navigator.msSaveBlob) { // IE 10+
            navigator.msSaveBlob(blob, filename);
        } else {
            var link = document.createElement('a');
            if (link.download !== undefined) {
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                // Show success notification with filename
                this.showNotification('Successfully exported to ' + filename, 'success');
            }
        }
    }

    copyToClipboard(data) {
        let textContent = '';

        // Add headers
        textContent += data.headers.join('\t') + '\n';

        // Add rows
        data.rows.forEach(function (row) {
            textContent += row.join('\t') + '\n';
        });

        // Use modern Clipboard API instead of execCommand
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textContent)
                .then(() => {
                    this.showNotification('Copied to clipboard!', 'success');
                })
                .catch(err => {
                    this.showNotification('Copy failed: ' + err, 'error');
                });
        } else {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = textContent;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);

            try {
                textarea.select();
                const success = document.execCommand('copy');
                if (success) {
                    this.showNotification('Copied to clipboard!', 'success');
                } else {
                    this.showNotification('Copy failed. Please try again.', 'error');
                }
            } catch (err) {
                this.showNotification('Copy failed: ' + err, 'error');
            } finally {
                document.body.removeChild(textarea);
            }
        }
    }

    exportToJSON(data) {
        try {
            // Get the header information and format keys (remove spaces or replace with underscores)
            const headers = Array.from(this.table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            
            // Format header keys to be more JSON friendly (remove spaces)
            const formattedHeaders = headers.map(header => 
                header.replace(/\s+/g, '_')  // Replace spaces with underscores
            );
            
            // Check the structure of data and handle appropriately
            let jsonData;
            
            if (data && data.rows && Array.isArray(data.rows)) {
                // If data has a rows property that's an array, use that
                jsonData = data.rows.map(row => {
                    const rowObj = {};
                    row.forEach((cell, i) => {
                        if (i < formattedHeaders.length) {
                            rowObj[formattedHeaders[i]] = cell;
                        }
                    });
                    return rowObj;
                });
            } else if (Array.isArray(data)) {
                // If data is directly an array
                jsonData = data.map(row => {
                    const rowObj = {};
                    row.forEach((cell, i) => {
                        if (i < formattedHeaders.length) {
                            rowObj[formattedHeaders[i]] = cell;
                        }
                    });
                    return rowObj;
                });
            } else {
                // Fallback if data structure is unexpected
                console.log('Unexpected data structure for JSON export:', data);
                jsonData = [];
                
                // Try to extract data from the table directly as a fallback
                const rows = Array.from(this.table.querySelectorAll('tbody tr'));
                jsonData = rows.map(row => {
                    const rowObj = {};
                    const cells = Array.from(row.querySelectorAll('td'));
                    cells.forEach((cell, i) => {
                        if (i < formattedHeaders.length) {
                            rowObj[formattedHeaders[i]] = cell.textContent.trim();
                        }
                    });
                    return rowObj;
                });
            }
            
            // Create and download the JSON file
            const jsonString = JSON.stringify(jsonData, null, 2);
            const blob = new Blob([jsonString], { type: 'application/json' });
            const fileName = `table_export_${new Date().toISOString().slice(0, 10)}.json`;
            
            // Create download link
            if (navigator.msSaveBlob) { // IE 10+
                navigator.msSaveBlob(blob, fileName);
            } else {
                const link = document.createElement('a');
                if (link.download !== undefined) {
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', fileName);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                }
            }
            
            this.showNotification('Table exported to JSON successfully!', 'success');
            
            return jsonString;
        } catch (error) {
            console.error('Error exporting to JSON:', error);
            this.showNotification('Error exporting to JSON: ' + error.message, 'danger');
            return null;
        }
    }
    
    exportToPDF(data) {
        try {
            // Create a hidden div to hold the table
            const container = document.createElement('div');
            container.style.position = 'absolute';
            container.style.left = '-9999px';
            container.style.top = '-9999px';
            document.body.appendChild(container);
            
            // Create a table 
            const table = document.createElement('table');
            table.style.width = '100%';
            table.style.borderCollapse = 'collapse';
            
            // Create header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            data.headers.forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                th.style.backgroundColor = '#f2f2f2';
                th.style.border = '1px solid #ddd';
                th.style.padding = '8px';
                th.style.fontWeight = 'bold';
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Create body
            const tbody = document.createElement('tbody');
            data.rows.forEach(row => {
                const tr = document.createElement('tr');
                row.forEach(cell => {
                    const td = document.createElement('td');
                    td.textContent = cell;
                    td.style.border = '1px solid #ddd';
                    td.style.padding = '8px';
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            container.appendChild(table);
            
            // Use html2pdf library if available, or fallback to print method
            if (typeof html2pdf !== 'undefined') {
                html2pdf().from(table).save('table-export.pdf');
                this.showNotification('Data exported to PDF successfully', 'success');
            } else {
                // If html2pdf isn't available, try jsPDF if available
                if (typeof jspdf !== 'undefined' && typeof jspdf.jsPDF === 'function') {
                    const doc = new jspdf.jsPDF();
                    
                    // Simple conversion with limited styling
                    doc.autoTable({ html: table });
                    doc.save('table-export.pdf');
                    
                    this.showNotification('Data exported to PDF successfully', 'success');
                } else {
                    // Fallback to print method if no PDF library is available
                    const printWindow = window.open('', '_blank');
                    printWindow.document.write('<html><head><title>Export to PDF</title>');
                    printWindow.document.write('<style>table { width: 100%; border-collapse: collapse; } ' +
                                              'th, td { border: 1px solid #ddd; padding: 8px; } ' +
                                              'th { background-color: #f2f2f2; font-weight: bold; }</style>');
                    printWindow.document.write('</head><body>');
                    printWindow.document.write('<p>Please use your browser\'s print function to save as PDF.</p>');
                    printWindow.document.write(table.outerHTML);
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 250);
                    
                    this.showNotification('Please use print dialog to save as PDF', 'info');
                }
            }
            
            // Clean up
            document.body.removeChild(container);
        } catch (error) {
            console.error('Error exporting to PDF:', error);
            this.showNotification('Error exporting to PDF. Check console for details.', 'error');
        }
    }

    exportToXML(data) {
        try {
            // Create XML document
            let xmlContent = '<?xml version="1.0" encoding="UTF-8"?>\n';
            xmlContent += '<table>\n';
            
            // Add headers
            xmlContent += '  <headers>\n';
            data.headers.forEach(header => {
                // Sanitize header to valid XML
                const safeHeader = header.replace(/[<>&'"]/g, char => {
                    switch (char) {
                        case '<': return '&lt;';
                        case '>': return '&gt;';
                        case '&': return '&amp;';
                        case '\'': return '&apos;';
                        case '"': return '&quot;';
                    }
                });
                xmlContent += `    <header>${safeHeader}</header>\n`;
            });
            xmlContent += '  </headers>\n';
            
            // Add rows
            xmlContent += '  <rows>\n';
            data.rows.forEach(row => {
                xmlContent += '    <row>\n';
                row.forEach((cell, index) => {
                    // Use header as element name (sanitized)
                    let elementName = data.headers[index] || `column${index + 1}`;
                    // Make sure element name is valid XML tag (alphanumeric and underscore only)
                    elementName = elementName.replace(/[^a-zA-Z0-9_]/g, '_').replace(/^[^a-zA-Z_]/, '_');
                    
                    // Sanitize cell value
                    const safeValue = String(cell).replace(/[<>&'"]/g, char => {
                        switch (char) {
                            case '<': return '&lt;';
                            case '>': return '&gt;';
                            case '&': return '&amp;';
                            case '\'': return '&apos;';
                            case '"': return '&quot;';
                        }
                    });
                    
                    xmlContent += `      <${elementName}>${safeValue}</${elementName}>\n`;
                });
                xmlContent += '    </row>\n';
            });
            xmlContent += '  </rows>\n';
            xmlContent += '</table>';
            
            // Create Blob and download
            const blob = new Blob([xmlContent], { type: 'application/xml' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'table-export.xml';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            this.showNotification('Data exported to XML successfully', 'success');
        } catch (error) {
            console.error('Error exporting to XML:', error);
            this.showNotification('Error exporting to XML. Check console for details.', 'error');
        }
    }

    exportToHTML(data) {
        try {
            // Start HTML document with styling
            let htmlContent = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .export-info {
            color: #666;
            font-size: 12px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>Exported Table</h1>
    <p class="export-info">Exported on ${new Date().toLocaleString()}</p>
    <table>
        <thead>
            <tr>
`;

            // Add headers
            data.headers.forEach(header => {
                htmlContent += `                <th>${this.escapeHTML(header)}</th>\n`;
            });

            htmlContent += `            </tr>
        </thead>
        <tbody>
`;

            // Add data rows
            data.rows.forEach(row => {
                htmlContent += '            <tr>\n';
                row.forEach(cell => {
                    htmlContent += `                <td>${this.escapeHTML(cell)}</td>\n`;
                });
                htmlContent += '            </tr>\n';
            });

            // Close HTML document
            htmlContent += `        </tbody>
    </table>
</body>
</html>`;

            // Create Blob and download
            const blob = new Blob([htmlContent], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'table-export.html';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            this.showNotification('Data exported to HTML successfully', 'success');
        } catch (error) {
            console.error('Error exporting to HTML:', error);
            this.showNotification('Error exporting to HTML. Check console for details.', 'error');
        }
    }
    
    escapeHTML(unsafe) {
        if (unsafe === null || unsafe === undefined) {
            return '';
        }
        return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Add a method to handle hanging AJAX requests
    clearHangingRequests() {
        // If there's an ongoing request for more than 10 seconds, assume it's stuck
        if (this.isLoadingAjax && this.abortController) {
            this.log('🚨 Clearing potentially hanging request');
            this.abortController.abort();
            this.abortController = null;
            this.isLoadingAjax = false;
            this.hideLoading();
            return true;
        }
        return false;
    }

    // Check for hanging requests before any navigation
    checkAndClearHangingRequests() {
        if (this.clearHangingRequests()) {
            this.showNotification('Cleared a stuck request. Please try again.', 'warning');
            return true;
        }
        return false;
    }

    //Prints the table with proper formatting
    printTable() {
        // Get the current visible data
        const visibleData = this.getExportData();
        
        // Determine if table should be landscape based on column count
        const columnCount = visibleData.headers.length;
        const shouldBeLandscape = columnCount > 5;
        
        // Generate the table HTML directly
        let tableHtml = '<table class="st-print-table"><thead><tr>';
        
        // Add headers
        visibleData.headers.forEach(header => {
            tableHtml += `<th>${header}</th>`;
        });
        
        tableHtml += '</tr></thead><tbody>';
        
        // Add rows
        visibleData.rows.forEach(row => {
            tableHtml += '<tr>';
            row.forEach(cell => {
                tableHtml += `<td>${cell}</td>`;
            });
            tableHtml += '</tr>';
        });
        
        tableHtml += '</tbody>';
        
        // Add footer with headers repeated (will appear at bottom of each page when printed)
        tableHtml += '<tfoot><tr>';
        visibleData.headers.forEach(header => {
            tableHtml += `<th>${header}</th>`;
        });
        tableHtml += '</tr></tfoot>';
        
        tableHtml += '</table>';
        
        // Create a print window with all content already embedded
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Table</title>
                <style>
                    @media print {
                        @page {
                            size: ${shouldBeLandscape ? 'landscape' : 'portrait'};
                            margin: 1cm;
                        }
                        body {
                            -webkit-print-color-adjust: exact !important;
                            print-color-adjust: exact !important;
                        }
                        
                        /* Table header appears at bottom of each page */
                        .st-print-table tfoot {
                            display: table-footer-group;
                        }
                        
                        /* Ensure proper page breaks */
                        .st-print-table thead {
                            display: table-header-group;
                        }
                        .st-print-table tbody {
                            display: table-row-group;
                        }
                        .st-print-table tr {
                            page-break-inside: avoid;
                        }
                    }
                    
                    body {
                        font-family: Arial, sans-serif;
                    }
                    
                    h1 {
                        text-align: center;
                        font-size: 18px;
                        margin-bottom: 10px;
                    }
                    
                    .date {
                        text-align: center;
                        font-size: 12px;
                        margin-bottom: 20px;
                        color: #666;
                    }
                    
                    .st-print-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 20px;
                    }
                    
                    .st-print-table th {
                        padding: 4px 5px;
                        border-bottom: 2px solid #333;
                        text-align: left;
                        font-weight: bold;
                    }
                    
                    /* Style for the repeating header at bottom of page */
                    .st-print-table tfoot th {
                        padding: 4px 5px;
                        border-bottom: 0 !important;
                        border-top: 2px solid #333;
                        text-align: left;
                        font-weight: bold;
                    }
                    
                    .st-print-table td {
                        padding: 3px 5px;
                        border-top: 1px solid #ddd;
                    }
                    
                    .footer {
                        text-align: center;
                        font-size: 12px;
                        color: #666;
                    }
                </style>
            </head>
            <body>
                <h1>Table Export</h1>
                <div class="date">Generated on ${new Date().toLocaleString()}</div>
                ${tableHtml}
                <div class="footer">${visibleData.rows.length} records total</div>
            </body>
            </html>
        `);
        
        // Close the document and trigger print
        printWindow.document.close();
        
        // Listen for load event to ensure content is fully loaded before printing
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
            
            // Optional: Close window after printing
            printWindow.onfocus = function() {
                setTimeout(function() {
                    printWindow.close();
                }, 200);
            };
        };
    }

    /**
     * Creates a new record in the table.
     * @param {Object} [initialData={}] - Optional initial data for the new record.
     * @param {Object} [options={}] - Optional configuration for the add operation.
     * @returns {Promise<boolean|Object>} - Resolves to the new record on success, false if aborted.
     */
    async addRecord(initialData = {}, options = {}) {
        const {
            showNotifications = true,
            title = 'Add New Record',
            submitButtonText = 'Add Record',
            cancelButtonText = 'Cancel'
        } = options;

        // Create empty data object with default values
        const emptyData = {};
        this.options.data.columns.forEach(column => {
            if (column.data && column.data !== 'actions' && column.data !== 'id' && column.editable !== false) {
                // Set default values based on type
                switch (column.type) {
                    case 'number':
                        emptyData[column.data] = column.min || 0;
                        break;
                    case 'boolean':
                        emptyData[column.data] = false;
                        break;
                    case 'date':
                        emptyData[column.data] = new Date().toISOString().split('T')[0];
                        break;
                    case 'select':
                        if (column.options && column.options.length > 0) {
                            // Use first option as default
                            emptyData[column.data] = typeof column.options[0] === 'object' 
                                ? column.options[0].value 
                                : column.options[0];
                        } else {
                            emptyData[column.data] = '';
                        }
                        break;
                    default:
                        emptyData[column.data] = '';
                }
            }
        });

        // Merge provided initial data with defaults
        const mergedData = { ...emptyData, ...initialData };
        
        // Call beforeAddRecord hook - if it returns false, abort the add operation
        const customData = this.callHook('beforeAddRecord', mergedData, options);
        if (customData === false) {
            this.log('Add operation aborted by beforeAddRecord hook');
            return false;
        }
        
        // Use customized data if provided by hook
        const finalData = customData || mergedData;

        // Create a unique ID for the modal
        const modalId = `st-add-record-modal-${Date.now()}`;

        // Create modal HTML similar to edit modal
        const modalHTML = `
        <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog" aria-labelledby="${modalId}-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="${modalId}-label">${title}</h5>
                        <button class="btn btn-system ms-auto" type="button" data-bs-dismiss="modal" aria-label="Close">
                            <svg class="sa-icon sa-icon-2x">
                                <use href="icons/sprite.svg#x"></use>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="${modalId}-form">
                            ${this.generateEditFormFields(finalData)}
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${cancelButtonText}</button>
                        <button type="button" class="btn btn-primary" id="${modalId}-submit">${submitButtonText}</button>
                    </div>
                </div>
            </div>
        </div>
        `;

        // Allow hook to customize the modal HTML
        const customModalHTML = this.callHook('onAddModalCreated', modalHTML, finalData, options);
        
        // Add modal to the DOM (use custom HTML if provided by hook)
        document.body.insertAdjacentHTML('beforeend', customModalHTML || modalHTML);

        // Get the modal element
        const modalElement = document.getElementById(modalId);
        
        // Initialize it with Bootstrap
        const modal = new bootstrap.Modal(modalElement);
        
        // Notify hooks that modal is about to be shown
        this.callHook('onAddModalBeforeShow', modalElement, finalData, options);
        
        // Show the modal
        modal.show();
        
        // Notify hooks that modal has been shown
        this.callHook('onAddModalAfterShow', modalElement, finalData, options);

        // Return a promise that resolves when the form is submitted or rejected when canceled
        return new Promise((resolve, reject) => {
            // Handle form submission
            const submitButton = document.getElementById(`${modalId}-submit`);
            submitButton.addEventListener('click', async (event) => {
                // Collect form data
                const form = document.getElementById(`${modalId}-form`);
                
                // Validate the form
                if (!form.checkValidity()) {
                    // Prevent default form submission
                    event.preventDefault();
                    event.stopPropagation();
                    
                    // Add validation classes to show feedback
                    form.classList.add('was-validated');
                    
                    // Show notification about validation errors
                    this.showNotification('Please fill all required fields correctly', 'danger');
                    
                    // Do not proceed with submission
                    return;
                }
                
                const formData = new FormData(form);
                
                // Convert FormData to object
                const newRecordData = {};
                for (const [key, value] of formData.entries()) {
                    newRecordData[key] = value;
                }
                
                // Allow hooks to modify the collected data
                const processedData = this.callHook('onAddDataCollected', newRecordData, options) || newRecordData;
                
                // Generate a unique ID for the new record
                const idField = this.options.data.idField || 'id';
                processedData[idField] = processedData[idField] || String(Date.now());
                
                // Close the modal
                modal.hide();
                
                try {
                    // Call beforeAddRecordSave hook - if it returns false, abort saving
                    if (this.callHook('beforeAddRecordSave', processedData, options) === false) {
                        this.log('Add save operation aborted by beforeAddRecordSave hook');
                        modalElement.remove();
                        resolve(false);
                        return;
                    }
                    
                    // Handle client-side data
                    if (Array.isArray(this.options.data.source)) {
                        // Add the new record to the data source
                        this.options.data.source.push(processedData);
                        
                        // Update the table
                        this.updateTableData(this.options.data.source);
                        
                        // Reapply search if active
                        if (this.searchQuery && this.searchQuery.trim() !== '') {
                            setTimeout(() => this.handleSearch(this.searchQuery), 50);
                        }
                        
                        // Show notification
                        if (showNotifications) {
                            this.showNotification('Record added successfully', 'success');
                        }
                        
                        // Call afterAddRecord hook with success
                        this.callHook('afterAddRecord', processedData, true);
                        
                        // Call onAddRecordSuccess hook with new data
                        this.callHook('onAddRecordSuccess', processedData);
                        
                        // Remove modal from DOM after animation completes
                        modalElement.addEventListener('hidden.bs.modal', () => {
                            modalElement.remove();
                        });
                        
                        resolve(processedData);
                    } else {
                        // Server-side data handling
                        const addUrl = this.options.data.source;
                        
                        // Prepare fetch options
                        const fetchOptions = {
                            method: 'POST', // Use POST for creating new records
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept-Encoding': 'gzip, deflate',
                                ...this.options.data.headers
                            },
                            body: JSON.stringify(processedData)
                        };
                        
                        // Allow hooks to modify the fetch options
                        const customFetchOptions = this.callHook('onAddRequestOptions', fetchOptions, processedData, options) || fetchOptions;
                        
                        // Send request to server
                        const response = await fetch(addUrl, customFetchOptions);
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }
                        
                        const responseData = await response.json();
                        
                        // Check for success in response
                        if (responseData.error) {
                            throw new Error(responseData.error || 'Failed to add record');
                        }
                        
                        // Clear cache and refresh data
                        this.clearAjaxCache();
                        this.loadAjax();
                        
                        // Show notification
                        if (showNotifications) {
                            this.showNotification('Record added successfully', 'success');
                        }
                        
                        // Call afterAddRecord hook with success
                        this.callHook('afterAddRecord', responseData.data || processedData, true);
                        
                        // Call onAddRecordSuccess hook with new data
                        this.callHook('onAddRecordSuccess', responseData.data || processedData);
                        
                        // Remove modal from DOM after animation completes
                        modalElement.addEventListener('hidden.bs.modal', () => {
                            modalElement.remove();
                        });
                        
                        resolve(responseData.data || processedData);
                    }
                } catch (error) {
                    // Show error notification
                    if (showNotifications) {
                        this.showNotification(`Failed to add record: ${error.message}`, 'danger');
                    }
                    
                    // Call afterAddRecord hook with failure
                    this.callHook('afterAddRecord', processedData, false);
                    
                    // Call onAddRecordError hook with error
                    this.callHook('onAddRecordError', error, processedData);
                    
                    // Remove modal from DOM after animation completes
                    modalElement.addEventListener('hidden.bs.modal', () => {
                        modalElement.remove();
                    });
                    
                    reject(error);
                }
            });
            
            // Handle modal dismissal
            modalElement.addEventListener('hidden.bs.modal', () => {
                // Only handle dismissal if not already resolved/rejected by submit button
                if (modalElement.parentNode) {
                    modalElement.remove();
                    this.callHook('onAddCancelled', options);
                    resolve(false);
                }
            });
        });
    }
}