// ListFilter - A JavaScript plugin for filtering lists with fuzzy search
;(function(window) {
    'use strict';

    // Cache frequently used selectors
    var SELECTORS = {
        NAV_MENU: 'nav-menu',
        NAV_TITLE: 'nav-title',
        NAV_LINK_TEXT: '.nav-link-text',
        FILTER_HIDE: 'js-filter-hide',
        FILTER_SHOW: 'js-filter-show',
        LIST_FILTER: 'js-list-filter',
        LIST_ACTIVE: 'js-list-active'
    };

    // Constants for configuration
    var CONSTANTS = {
        MAX_INPUT_LENGTH: 100,
        RATE_LIMIT_MS: 100,
        MAX_MATCHES: 1000,
        MAX_CACHE_SIZE: 10000,
        MATCH_WEIGHTS: {
            EXACT: 3,
            SUBSTRING: 2,
            CONSECUTIVE: 0.5,
            CONSECUTIVE_BONUS: 0.1,
            NON_CONSECUTIVE: 0.3,
            PREFIX_BONUS: 0.2,
            CASE_MATCH_BONUS: 0.1
        }
    };

    // Debounce function implementation with immediate option and rate limiting
    function debounce(func, wait, immediate) {
        var timeout;
        var lastRun = 0;
        
        return function executedFunction() {
            var context = this;
            var args = arguments;
            var now = Date.now();
            
            // Rate limiting
            if (now - lastRun < CONSTANTS.RATE_LIMIT_MS) {
                return;
            }
            
            var later = function() {
                timeout = null;
                if (!immediate) {
                    lastRun = Date.now();
                    func.apply(context, args);
                }
            };
            
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            
            if (callNow) {
                lastRun = Date.now();
                func.apply(context, args);
            }
        };
    }

    // Improved fuzzy search implementation with caching and enhanced scoring
    var fuzzyMatchCache = new Map();
    function fuzzyMatch(pattern, str) {
        try {
            if (!pattern || !str) return 0;
            
            // Check cache first
            var cacheKey = pattern + '|' + str;
            if (fuzzyMatchCache.has(cacheKey)) {
                return fuzzyMatchCache.get(cacheKey);
            }
            
            // Clear cache if it gets too large
            if (fuzzyMatchCache.size > CONSTANTS.MAX_CACHE_SIZE) {
                fuzzyMatchCache.clear();
            }
            
            var lowerPattern = pattern.toLowerCase();
            var lowerStr = str.toLowerCase();
            var score = 0;
            
            // Exact match gets highest score
            if (str === pattern) {
                score = CONSTANTS.MATCH_WEIGHTS.EXACT;
                fuzzyMatchCache.set(cacheKey, score);
                return score;
            }
            
            // Substring match gets second highest score
            if (lowerStr.includes(lowerPattern)) {
                score = CONSTANTS.MATCH_WEIGHTS.SUBSTRING;
                // Bonus for case-sensitive match
                if (str.includes(pattern)) {
                    score += CONSTANTS.MATCH_WEIGHTS.CASE_MATCH_BONUS;
                }
                // Bonus for prefix match
                if (lowerStr.startsWith(lowerPattern)) {
                    score += CONSTANTS.MATCH_WEIGHTS.PREFIX_BONUS;
                }
                fuzzyMatchCache.set(cacheKey, score);
                return score;
            }
            
            var patternIdx = 0;
            var lastMatchIdx = -1;
            var consecutiveMatches = 0;
            var firstMatch = true;
            
            for (var strIdx = 0; strIdx < str.length && patternIdx < pattern.length; strIdx++) {
                if (lowerPattern[patternIdx] === lowerStr[strIdx]) {
                    // First match bonus
                    if (firstMatch) {
                        score += CONSTANTS.MATCH_WEIGHTS.PREFIX_BONUS * (1 - strIdx / str.length);
                        firstMatch = false;
                    }
                    
                    // Consecutive matches get bonus
                    if (lastMatchIdx === strIdx - 1) {
                        consecutiveMatches++;
                        score += CONSTANTS.MATCH_WEIGHTS.CONSECUTIVE + 
                                (consecutiveMatches * CONSTANTS.MATCH_WEIGHTS.CONSECUTIVE_BONUS);
                    } else {
                        consecutiveMatches = 0;
                        score += CONSTANTS.MATCH_WEIGHTS.NON_CONSECUTIVE;
                    }
                    
                    // Case matching bonus
                    if (pattern[patternIdx] === str[strIdx]) {
                        score += CONSTANTS.MATCH_WEIGHTS.CASE_MATCH_BONUS;
                    }
                    
                    lastMatchIdx = strIdx;
                    patternIdx++;
                }
            }
            
            // Only count as match if all pattern characters were found
            var finalScore = patternIdx === pattern.length ? score : 0;
            
            // Normalize score based on string lengths
            if (finalScore > 0) {
                finalScore = finalScore * (pattern.length / str.length);
            }
            
            fuzzyMatchCache.set(cacheKey, finalScore);
            return finalScore;
            
        } catch (error) {
            console.error('Fuzzy match error:', error);
            return 0;
        }
    }

    function getNavItemText(element) {
        try {
            if (!element || !(element instanceof Element)) {
                throw new Error('Invalid element provided to getNavItemText');
            }
            var navText = element.querySelector(SELECTORS.NAV_LINK_TEXT);
            return navText ? navText.textContent.trim() : '';
        } catch (error) {
            console.error('getNavItemText error:', error);
            return '';
        }
    }

    // Main plugin constructor with validation
    function ListFilter(list, input, options) {
        try {
            if (!(this instanceof ListFilter)) {
                throw new Error('ListFilter must be called with new keyword');
            }

            // Validate parameters
            if (!list || !input) {
                throw new Error('List and input parameters are required');
            }

            this.list = typeof list === 'string' ? document.querySelector(list) : list;
            this.input = typeof input === 'string' ? document.querySelector(input) : input;
            
            if (!this.list || !this.input) {
                throw new Error('Required elements not found. Check your selectors.');
            }

            if (!(this.list instanceof Element) || !(this.input instanceof Element)) {
                throw new Error('Invalid DOM elements provided');
            }

            var defaults = {
                anchor: null,
                messageSelector: '.js-filter-message',
                debounceWait: 250,
                minLength: 1,
                maxLength: CONSTANTS.MAX_INPUT_LENGTH,
                caseSensitive: false,
                onFilter: null,
                onReset: null,
                onError: null
            };
            
            // Validate options
            this.settings = Object.assign({}, defaults, this._validateOptions(options));
            
            this.anchor = typeof this.settings.anchor === 'string' ? 
                document.querySelector(this.settings.anchor) : this.settings.anchor;

            // Initialize cache with size monitoring
            this._cache = {
                items: null,
                titles: null,
                messageElement: null,
                size: 0
            };

            this._errorCount = 0;
            this._lastErrorTime = 0;

            this.init();
        } catch (error) {
            console.error('ListFilter constructor error:', error);
            if (options && typeof options.onError === 'function') {
                options.onError(error);
            }
            throw error;
        }
    }

    // Plugin prototype with improved methods
    ListFilter.prototype = {
        _validateOptions: function(options) {
            if (!options) return {};
            
            var validated = {};
            try {
                if (typeof options !== 'object') {
                    throw new Error('Options must be an object');
                }

                // Validate each option
                if (options.minLength !== undefined) {
                    validated.minLength = Math.max(1, parseInt(options.minLength, 10) || 1);
                }
                
                if (options.maxLength !== undefined) {
                    validated.maxLength = Math.min(
                        CONSTANTS.MAX_INPUT_LENGTH,
                        parseInt(options.maxLength, 10) || CONSTANTS.MAX_INPUT_LENGTH
                    );
                }
                
                if (options.debounceWait !== undefined) {
                    validated.debounceWait = Math.max(50, parseInt(options.debounceWait, 10) || 250);
                }
                
                validated.caseSensitive = !!options.caseSensitive;
                
                // Validate callbacks
                if (options.onFilter && typeof options.onFilter === 'function') {
                    validated.onFilter = options.onFilter;
                }
                
                if (options.onReset && typeof options.onReset === 'function') {
                    validated.onReset = options.onReset;
                }
                
                if (options.onError && typeof options.onError === 'function') {
                    validated.onError = options.onError;
                }
                
                // Validate selectors
                if (options.messageSelector && typeof options.messageSelector === 'string') {
                    validated.messageSelector = options.messageSelector;
                }
                
                if (options.anchor) {
                    validated.anchor = options.anchor;
                }
            } catch (error) {
                console.error('Options validation error:', error);
                return {};
            }
            
            return validated;
        },

        init: function() {
            try {
                if (this.anchor) {
                    this.anchor.classList.add(SELECTORS.LIST_FILTER);
                } else {
                    this.list.classList.add(SELECTORS.LIST_FILTER);
                }

                // Pre-cache elements with validation
                var items = this.list.getElementsByTagName('a');
                var titles = this.list.getElementsByTagName('li');
                
                if (!items.length || !titles.length) {
                    throw new Error('No filterable items found in the list');
                }

                this._cache.items = items;
                this._cache.titles = titles;
                this._cache.messageElement = document.querySelector(this.settings.messageSelector);
                this._updateCacheSize();

                this.bindEvents();
            } catch (error) {
                this._handleError(error);
                throw error;
            }
        },

        _updateCacheSize: function() {
            try {
                this._cache.size = 0;
                if (this._cache.items) this._cache.size += this._cache.items.length;
                if (this._cache.titles) this._cache.size += this._cache.titles.length;
                
                // Clear fuzzy match cache if total cache size is too large
                if (this._cache.size > CONSTANTS.MAX_CACHE_SIZE) {
                    fuzzyMatchCache.clear();
                }
            } catch (error) {
                this._handleError(error);
            }
        },

        _handleError: function(error) {
            console.error('ListFilter error:', error);
            
            var now = Date.now();
            if (now - this._lastErrorTime > 60000) { // Reset error count after 1 minute
                this._errorCount = 0;
            }
            
            this._errorCount++;
            this._lastErrorTime = now;
            
            if (this.settings.onError) {
                this.settings.onError(error);
            }
            
            // Auto-recovery if too many errors
            if (this._errorCount > 10) {
                this.reset();
                this._errorCount = 0;
            }
        },

        bindEvents: function() {
            try {
                var self = this;
                this.handleFilter = debounce(this.filter.bind(this), this.settings.debounceWait);
                
                // Use passive event listeners for better performance
                this.input.addEventListener('input', this._validateEvent.bind(this), { passive: true });
                this.input.addEventListener('change', this._validateEvent.bind(this), { passive: true });

                // Add ESC key handler
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' || e.key === 'Esc') {
                        self.reset();
                    }
                });

                if (this._cache.messageElement) {
                    this._cache.messageElement.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (e.target === self._cache.messageElement || self._cache.messageElement.contains(e.target)) {
                            // Get the Bootstrap tooltip instance and dispose of it
                            var tooltip = bootstrap.Tooltip.getInstance(self._cache.messageElement);
                            if (tooltip) {
                                tooltip.dispose();
                                // Reinitialize the tooltip
                                new bootstrap.Tooltip(self._cache.messageElement);
                            }
                            self.reset();
                        }
                    });
                }

                // Handle window resize for responsive layouts
                this._resizeHandler = debounce(function() {
                    if (self.input.value.length > self.settings.minLength) {
                        self.filter();
                    }
                }, 250);
                
                window.addEventListener('resize', this._resizeHandler, { passive: true });
                
                // Add input maxlength attribute
                this.input.setAttribute('maxlength', this.settings.maxLength.toString());
            } catch (error) {
                this._handleError(error);
            }
        },

        _validateEvent: function(event) {
            try {
                // Validate event source
                if (!event || !(event instanceof Event) || event.target !== this.input) {
                    return;
                }
                
                var value = this.input.value;
                
                // Validate input length
                if (value.length > this.settings.maxLength) {
                    this.input.value = value.slice(0, this.settings.maxLength);
                    return;
                }
                
                this.handleFilter(event);
            } catch (error) {
                this._handleError(error);
            }
        },

        findNavTitle: function(element) {
            try {
                if (!element || !(element instanceof Element)) {
                    throw new Error('Invalid element provided to findNavTitle');
                }

                var current = element;
                while (current && !current.classList.contains(SELECTORS.NAV_MENU)) {
                    var prev = current.previousElementSibling;
                    while (prev) {
                        if (prev.classList.contains(SELECTORS.NAV_TITLE)) {
                            return prev;
                        }
                        prev = prev.previousElementSibling;
                    }
                    current = current.parentElement;
                }
                return null;
            } catch (error) {
                this._handleError(error);
                return null;
            }
        },

        showParents: function(element) {
            try {
                if (!element || !(element instanceof Element)) {
                    throw new Error('Invalid element provided to showParents');
                }

                var parent = element.parentElement;
                while (parent && !parent.classList.contains(SELECTORS.NAV_MENU)) {
                    if (parent.tagName === 'LI') {
                        parent.classList.remove(SELECTORS.FILTER_HIDE);
                        parent.classList.add(SELECTORS.FILTER_SHOW);
                    }
                    parent = parent.parentElement;
                }
            } catch (error) {
                this._handleError(error);
            }
        },

        showChildren: function(element) {
            try {
                if (!element || !(element instanceof Element)) {
                    throw new Error('Invalid element provided to showChildren');
                }

                var children = element.querySelectorAll('li');
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    if (!child.classList.contains(SELECTORS.NAV_TITLE)) {
                        child.classList.remove(SELECTORS.FILTER_HIDE);
                        child.classList.add(SELECTORS.FILTER_SHOW);
                    }
                }
            } catch (error) {
                this._handleError(error);
            }
        },

        filter: function() {
            try {
                var filter = this.input.value;
                
                // Input validation
                if (filter.length > this.settings.maxLength) {
                    filter = filter.slice(0, this.settings.maxLength);
                    this.input.value = filter;
                }

                if (!this.settings.caseSensitive) {
                    filter = filter.toLowerCase();
                }

                if (filter.length > this.settings.minLength) {
                    // Add active class when filter is active
                    this.list.classList.add(SELECTORS.LIST_ACTIVE);
                    this._performFilter(filter);
                } else {
                    // Remove active class and reset
                    this.list.classList.remove(SELECTORS.LIST_ACTIVE);
                    this._resetFilter();
                }

                // Callback
                if (typeof this.settings.onFilter === 'function') {
                    this.settings.onFilter.call(this, filter);
                }
            } catch (error) {
                this._handleError(error);
                this._resetFilter(); // Recover from error
            }
        },

        _performFilter: function(filter) {
            try {
                // Hide all items first
                for (var i = 0; i < this._cache.titles.length; i++) {
                    this._cache.titles[i].classList.remove(SELECTORS.FILTER_SHOW);
                    this._cache.titles[i].classList.add(SELECTORS.FILTER_HIDE);
                }

                var matchCount = 0;
                var matchedElements = new Set();
                var relevantTitles = new Set();
                var matches = [];
                
                // Collect matches with limit
                for (var j = 0; j < this._cache.items.length && matches.length < CONSTANTS.MAX_MATCHES; j++) {
                    var item = this._cache.items[j];
                    var text = getNavItemText(item);
                    var title = item.getAttribute('title') || '';
                    
                    var textScore = fuzzyMatch(filter, text);
                    var titleScore = fuzzyMatch(filter, title);
                    var score = Math.max(textScore, titleScore);
                    
                    if (score > 0) {
                        matches.push({
                            element: item,
                            score: score
                        });
                    }
                }
                
                // Sort by score
                matches.sort(function(a, b) { return b.score - a.score; });
                
                // Process matches
                for (var k = 0; k < matches.length; k++) {
                    var match = matches[k];
                    var parentLi = match.element.closest('li');
                    if (parentLi && !matchedElements.has(parentLi)) {
                        parentLi.classList.remove(SELECTORS.FILTER_HIDE);
                        parentLi.classList.add(SELECTORS.FILTER_SHOW);
                        this.showParents(parentLi);
                        this.showChildren(parentLi);
                        
                        var navTitle = this.findNavTitle(parentLi);
                        if (navTitle && !relevantTitles.has(navTitle)) {
                            navTitle.classList.remove(SELECTORS.FILTER_HIDE);
                            navTitle.classList.add(SELECTORS.FILTER_SHOW);
                            relevantTitles.add(navTitle);
                        }
                        
                        matchedElements.add(parentLi);
                        matchCount++;
                    }
                }

                // Update message
                if (this._cache.messageElement) {
                    this._cache.messageElement.innerHTML = matchCount;
                    this._cache.messageElement.style.cursor = 'pointer';
                }

                // Clear match cache if it's too large
                if (fuzzyMatchCache.size > CONSTANTS.MAX_CACHE_SIZE) {
                    fuzzyMatchCache.clear();
                }
            } catch (error) {
                this._handleError(error);
                this._resetFilter(); // Recover from error
            }
        },

        _resetFilter: function() {
            try {
                // Remove active class when resetting
                this.list.classList.remove(SELECTORS.LIST_ACTIVE);
                
                for (var i = 0; i < this._cache.titles.length; i++) {
                    this._cache.titles[i].classList.remove(SELECTORS.FILTER_HIDE, SELECTORS.FILTER_SHOW);
                }

                if (this._cache.messageElement) {
                    this._cache.messageElement.textContent = '';
                }
            } catch (error) {
                this._handleError(error);
            }
        },

        reset: function() {
            try {
                this.input.value = '';
                this._resetFilter();
                
                // Callback
                if (typeof this.settings.onReset === 'function') {
                    this.settings.onReset.call(this);
                }
            } catch (error) {
                this._handleError(error);
            }
        },

        // Public method to destroy the plugin and clean up
        destroy: function() {
            try {
                // Remove event listeners
                this.input.removeEventListener('input', this._validateEvent);
                this.input.removeEventListener('change', this._validateEvent);
                window.removeEventListener('resize', this._resizeHandler);
                
                if (this._cache.messageElement) {
                    this._cache.messageElement.removeEventListener('click', this.reset);
                }
                
                // Reset state
                this._resetFilter();
                
                // Clear caches
                fuzzyMatchCache.clear();
                this._cache = null;
                
                // Remove attributes
                this.input.removeAttribute('maxlength');
                
                // Remove classes including active class
                if (this.anchor) {
                    this.anchor.classList.remove(SELECTORS.LIST_FILTER, SELECTORS.LIST_ACTIVE);
                } else {
                    this.list.classList.remove(SELECTORS.LIST_FILTER, SELECTORS.LIST_ACTIVE);
                }
            } catch (error) {
                this._handleError(error);
            }
        }
    };

    // Add to global namespace
    window.ListFilter = ListFilter;
})(window);