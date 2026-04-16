/**
 * Navigation v1.6.1
 * Enhanced secure version with improved performance, accessibility, usability,
 * security, and maintainability - Fixed null error and adjusted to flex display
 */
class Navigation {
    static instances = new WeakMap();

    static getInstance(element) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        return el instanceof Element ? Navigation.instances.get(el) : null;
    }

    static initAll(selector, options = {}) {
        if (typeof selector !== 'string') throw new Error('Invalid selector');
        return Array.from(document.querySelectorAll(selector))
            .map(element => new Navigation(element, options));
    }

    static initOnLoad(options = {}) {
        document.addEventListener('DOMContentLoaded', () => {
            Navigation.initAll('.navigation', options);
        });
    }

    static destroyAll() {
        Navigation.instances.forEach(instance => instance.destroy());
    }

    static sanitizeHTML(str) {
        if (typeof str !== 'string') return '';
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML.replace(/(on\w+)=["'][^"']*["']/gi, '');
    }

    static isValidUrl(url) {
        if (typeof url !== 'string') return false;
        if (url === '#') return true;
        try {
            new URL(url, window.location.origin);
            return true;
        } catch {
            return false;
        }
    }

    constructor(element, options = {}) {
        if (!(element instanceof Element)) throw new Error('Invalid element provided');
        if (!element.querySelector('ul')) throw new Error('Navigation element must contain a <ul>');

        this.defaults = Object.freeze({
            accordion: true,
            slideUpSpeed: 200,
            slideDownSpeed: 200,
            closedSign: '<i class="sa sa-chevron-down"></i>',
            openedSign: '<i class="sa sa-chevron-up"></i>',
            initClass: 'js-nav-built',
            debug: false,
            instanceId: `nav-${this.generateUniqueId()}`,
            maxDepth: 10,
            sanitize: true,
            animationTiming: 'easeOutExpo',
            debounceTime: 0,
            onError: null,
            showMore: {
                enabled: true,
                itemsToShow: 15, //has a bug when you show parent in upper tree
                minItemsForToggle: 2,
                moreText: 'Show {count} more',
                lessText: 'Show less'
            }
        });

        this.options = Object.freeze(this.validateOptions({...this.defaults, ...options}));
        this.element = element;
        this.state = new Proxy({
            isInitialized: false,
            lastInteraction: 0,
            activeSubmenu: null,
            isAnimating: false
        }, {
            set: (target, prop, value) => {
                target[prop] = value;
                this._debug(`State updated: ${prop}`, 'info', {value});
                return true;
            }
        });

        this.cache = new WeakMap();
        if (Navigation.instances.has(element)) return Navigation.instances.get(element);
        Navigation.instances.set(element, this);

        this.handleClick = this.debounce(this.handleClick.bind(this), this.options.debounceTime);
        this.handleKeydown = this.handleKeydown.bind(this);

        this.init();
    }

    validateOptions(options) {
        const validators = {
            accordion: v => typeof v === 'boolean',
            slideUpSpeed: v => typeof v === 'number' && v >= 0 && v <= 1000,
            slideDownSpeed: v => typeof v === 'number' && v >= 0 && v <= 1000,
            closedSign: v => typeof v === 'string' && !/<script/i.test(v),
            openedSign: v => typeof v === 'string' && !/<script/i.test(v),
            initClass: v => typeof v === 'string',
            debug: v => typeof v === 'boolean',
            maxDepth: v => typeof v === 'number' && v > 0,
            sanitize: v => typeof v === 'boolean',
            animationTiming: v => typeof v === 'string',
            debounceTime: v => typeof v === 'number' && v >= 0,
            onError: v => typeof v === 'function' || v === null
        };

        for (const [key, value] of Object.entries(options)) {
            if (!(key in validators)) continue;
            if (!validators[key](value)) {
                throw new TypeError(`Invalid ${key}: ${value}`);
            }
        }
        return options;
    }

    _debug(message, level = 'log', data = {}) {
        if (!this.options.debug) return;
        const timestamp = new Date().toISOString();
        const secureData = JSON.parse(JSON.stringify(data, (k, v) =>
            typeof v === 'string' ? Navigation.sanitizeHTML(v) : v));
        const prefix = `[Navigation ${this.options.instanceId} ${timestamp}]`;
        const logMethod = {error: console.error, warn: console.warn, info: console.info}[level] || console.log;
        logMethod(prefix, message, secureData, level === 'error' ? new Error().stack : undefined);
    }

    init() {
        if (this.state.isInitialized) return this._debug('Already initialized', 'warn');
        if (!this.element) throw new Error('Navigation element is null');
        try {
            this._debug('Initializing navigation', 'info');
            Object.assign(this.element, {
                role: 'navigation',
                tabIndex: 0,
                'aria-label': 'Main navigation'
            });
            // Find the top-level menu
            const topMenu = this.element.querySelector('ul');
            if (topMenu) {
                // Set the top-level menu to have role="menu"
                topMenu.setAttribute('role', 'menu');
            }
            this.element.classList.add(this.options.initClass);
            this.element.dataset.instanceId = this.options.instanceId;
            this.setupSecureCache();
            this.setupTitleSeparators();
            this.setupMenuItems();
            this.setupActiveItems();

            // Ensure at least one menu is visible if none are currently displayed
            this._ensureMenuVisibility();

            this.bindSecureEvents();
            this.state.isInitialized = true;
        } catch (error) {
            this._debug('Initialization failed', 'error', {error});
            this.options.onError?.(error);
            throw error;
        }
    }

    setupSecureCache() {
        const cache = this.buildCache(this.element);
        if (this.validateDOMStructure(cache)) {
            this.cache.set(this.element, cache);
        } else {
            throw new Error('Invalid DOM structure');
        }
    }

    buildCache(root) {
        const items = [], links = [], subMenus = [];
        const traverse = (el, depth = 0) => {
            if (depth > this.options.maxDepth) return;
            if (el.tagName === 'LI') items.push(el);
            if (el.tagName === 'A') links.push(el);
            if (el.tagName === 'UL') subMenus.push(el);
            Array.from(el.children).forEach(child => traverse(child, depth + 1));
        };
        traverse(root);
        return {items, links, subMenus};
    }

    validateDOMStructure(cache) {
        return cache.items.every(item => this.getElementDepth(item) <= this.options.maxDepth) &&
            cache.links.every(link => Navigation.isValidUrl(link.getAttribute('href') || '#'));
    }

    getElementDepth(element) {
        let depth = 0, current = element;
        while (current && current !== this.element && depth <= this.options.maxDepth) {
            depth++;
            current = current.parentElement;
        }
        return depth;
    }

    setupMenuItems() {
        const cache = this.cache.get(this.element);
        const showMoreConfig = this.options.showMore;

        if (!showMoreConfig?.enabled) return;

        const ITEMS_TO_SHOW = showMoreConfig.itemsToShow || 15;
        const MIN_ITEMS_FOR_TOGGLE = showMoreConfig.minItemsForToggle || 2;

        cache?.items.forEach(item => {
            // Add proper accessibility attributes to nav-title items
            if (item.classList.contains('nav-title')) {
                const titleSpan = item.querySelector('span');
                const titleText = titleSpan?.textContent || 'Section';

                // Set role="separator" and aria-label with the title text
                item.setAttribute('role', 'separator');
                item.setAttribute('aria-label', titleText);

                // Remove menuitem role from nav-title items since they're separators
                return;
            }

            const subMenu = item.querySelector('ul');
            if (subMenu && this.getElementDepth(subMenu) <= this.options.maxDepth) {
                item.classList.add('has-ul');
                Object.assign(subMenu, {
                    role: 'menu',
                    'data-depth': this.getElementDepth(subMenu)
                    // Remove the display: none so menus are visible by default
                });

                // Only hide the menu if explicitly needed
                if (!item.classList.contains('active') && !this._hasActiveDescendant(item)) {
                    subMenu.style.display = 'none';
                } else {
                    // If item is active or has active children, show it
                    subMenu.style.display = 'flex';
                    subMenu.style.height = 'auto';
                    item.classList.add('open');
                    item.setAttribute('aria-expanded', 'true');
                }

                // Filter out parent items (items with submenus) from being hidden
                const children = Array.from(subMenu.children);
                const nonParentItems = children.filter(child => !child.querySelector('ul'));
                const parentItems = children.filter(child => child.querySelector('ul'));

                // Calculate remaining items only from non-parent items
                const visibleNonParentItems = nonParentItems.slice(0, ITEMS_TO_SHOW);
                const hiddenNonParentItems = nonParentItems.slice(ITEMS_TO_SHOW);
                const remainingItems = hiddenNonParentItems.length;

                if (remainingItems >= MIN_ITEMS_FOR_TOGGLE) {
                    // Create container for hidden items
                    const hiddenContainer = document.createElement('div');
                    hiddenContainer.className = 'nav-hidden-container';

                    // Check if any hidden items are active
                    const hasActiveItem = hiddenNonParentItems.some(child =>
                        child.classList.contains('active')
                    );

                    // Set initial state based on active items
                    if (hasActiveItem) {
                        hiddenContainer.style.display = 'flex';
                        hiddenContainer.style.height = 'auto';
                    } else {
                        hiddenContainer.style.display = 'none';
                        hiddenContainer.style.height = '0';
                    }
                    hiddenContainer.style.overflow = 'hidden';

                    // Reorder items: visible non-parents, parent items, then hidden non-parents
                    subMenu.innerHTML = ''; // Clear submenu
                    visibleNonParentItems.forEach(child => subMenu.appendChild(child));
                    parentItems.forEach(child => subMenu.appendChild(child));
                    hiddenNonParentItems.forEach(child => hiddenContainer.appendChild(child));
                    subMenu.appendChild(hiddenContainer);

                    // Create "Show more" link with collapse sign
                    const showMoreLi = document.createElement('li');
                    showMoreLi.className = 'nav-show-more';
                    showMoreLi.setAttribute('role', 'presentation');

                    const showMoreLink = document.createElement('a');
                    showMoreLink.href = '#';
                    showMoreLink.className = 'nav-more-link';
                    showMoreLink.setAttribute('role', 'button');
                    showMoreLink.setAttribute('aria-expanded', hasActiveItem ? 'true' : 'false');
                    showMoreLink.setAttribute('aria-controls', `nav-toggle-${this.generateUniqueId().substring(0, 8)}`);

                    // Add text and collapse sign
                    const textSpan = document.createElement('span');
                    const moreText = showMoreConfig.moreText.replace('{count}', remainingItems);
                    textSpan.textContent = hasActiveItem ? showMoreConfig.lessText : moreText;
                    showMoreLink.appendChild(textSpan);

                    const collapseSign = document.createElement('span');
                    collapseSign.className = 'collapse-sign';
                    collapseSign.setAttribute('aria-hidden', 'true');
                    const icon = document.createElement('i');
                    icon.className = hasActiveItem ? 'sa sa-chevron-up' : 'sa sa-chevron-down';
                    collapseSign.appendChild(icon);
                    showMoreLink.appendChild(collapseSign);

                    if (hasActiveItem) {
                        showMoreLink.classList.add('showing-more');
                    }

                    showMoreLi.appendChild(showMoreLink);
                    subMenu.appendChild(showMoreLi);

                    // Set ID on the hidden container for aria-controls reference
                    const containerId = showMoreLink.getAttribute('aria-controls');
                    hiddenContainer.id = containerId;

                    // Add click handler with animation
                    showMoreLink.addEventListener('click', (e) => {
                        e.preventDefault();
                        const isShowingMore = showMoreLink.classList.contains('showing-more');

                        if (isShowingMore) {
                            this.animateHeight(hiddenContainer, 'up', () => {
                                hiddenContainer.style.display = 'none';
                                textSpan.textContent = moreText;
                                showMoreLink.classList.remove('showing-more');
                                showMoreLink.setAttribute('aria-expanded', 'false');
                                icon.className = 'sa sa-chevron-down';
                            });
                        } else {
                            hiddenContainer.style.display = 'flex';
                            this.animateHeight(hiddenContainer, 'down', () => {
                                textSpan.textContent = showMoreConfig.lessText;
                                showMoreLink.classList.add('showing-more');
                                showMoreLink.setAttribute('aria-expanded', 'true');
                                icon.className = 'sa sa-chevron-up';
                            });
                        }
                    });
                }

                const link = item.querySelector('a:first-child');
                if (link) this.setupSecureLink(link);
            }
            // Only set menuitem role for non-separator items
            if (!item.classList.contains('nav-title')) {
                item.role = 'menuitem';
            }
        });
    }

    setupSecureLink(link) {
        // Create collapse sign container
        const collapseSign = document.createElement('span');
        collapseSign.className = 'collapse-sign';
        collapseSign.setAttribute('aria-hidden', 'true');

        // Create icon element
        const icon = document.createElement('i');
        icon.className = 'sa sa-chevron-down';
        collapseSign.appendChild(icon);

        // Get the parent li element
        const parentLi = link.closest('li');

        // Set ARIA attributes on the LI instead of the link
        if (parentLi) {
            parentLi.setAttribute('aria-expanded', 'false');
            parentLi.setAttribute('aria-haspopup', 'true');
        }

        // Add click prevention for empty links
        if (link.getAttribute('href') === '#') {
            link.addEventListener('click', e => e.preventDefault());
        }

        link.appendChild(collapseSign);
    }

    setupActiveItems() {
        const cache = this.cache.get(this.element);

        // Process all active items to set up expanded state
        cache?.items.forEach(item => {
            if (item.classList.contains('active')) {
                // First expand all parent ULs
                let parent = item.parentElement;
                while (parent && parent !== this.element) {
                    if (parent.tagName === 'UL') {
                        parent.style.display = 'flex';
                        parent.style.height = 'auto';

                        // Handle the parent LI
                        const parentLi = parent.parentElement;
                        if (parentLi && parentLi.tagName === 'LI') {
                            parentLi.classList.add('open', 'has-ul');

                            // Set ARIA attributes on the LI
                            parentLi.setAttribute('aria-expanded', 'true');
                            parentLi.setAttribute('aria-haspopup', 'true');

                            const parentLink = parentLi.querySelector('a');
                            if (parentLink) {
                                const collapseSign = parentLink.querySelector('.collapse-sign i');
                                if (collapseSign) {
                                    collapseSign.className = 'sa sa-chevron-up';
                                }
                            }
                        }
                    }
                    parent = parent.parentElement;
                }

                // Then handle the active item's own submenu if it exists
                const submenu = item.querySelector('ul');
                if (submenu) {
                    submenu.style.display = 'flex';
                    submenu.style.height = 'auto';
                    item.classList.add('open', 'has-ul');

                    // Set ARIA attributes on the LI
                    item.setAttribute('aria-expanded', 'true');
                    item.setAttribute('aria-haspopup', 'true');

                    const link = item.querySelector('a');
                    if (link) {
                        const collapseSign = link.querySelector('.collapse-sign i');
                        if (collapseSign) {
                            collapseSign.className = 'sa sa-chevron-up';
                        }
                    }
                    this.state.activeSubmenu = submenu;
                } else {
                    // This is a leaf item (no submenu)
                    item.setAttribute('aria-current', 'page');
                }
            }
        });
    }

    bindSecureEvents() {
        this.element.removeEventListener('mousedown', this.handleClick);
        this.element.removeEventListener('keydown', this.handleKeydown);
        this.element.addEventListener('mousedown', this.handleClick, {passive: true});
        this.element.addEventListener('keydown', this.handleKeydown);
    }

    handleClick(event) {
        const link = event.target.closest('a');
        if (!link || !this.element.contains(link)) return;
        const li = link.parentElement;
        const ul = li.querySelector('ul');
        if (!ul) return;
        event.preventDefault();
        this.toggleSubmenu(li, link, ul);
    }

    handleKeydown(event) {
        const link = event.target.closest('a');
        if (!link || !this.element.contains(link)) return;

        switch (event.key) {
            case 'Enter':
            case ' ':
                event.preventDefault();
                this.handleClick(event);
                break;
            case 'Escape':
                event.preventDefault();
                this.closeAllSubmenus();
                link.focus();
                break;
            case 'ArrowDown':
            case 'ArrowUp':
                event.preventDefault();
                this.navigateMenu(link, event.key === 'ArrowDown' ? 'next' : 'prev');
                break;
        }
    }

    toggleSubmenu(li, link, ul) {
        if (this.state.isAnimating) {
            this._debug('Animation in progress, ignoring toggle request');
            return;
        }

        // Check the expanded state from the LI, not the link
        const isExpanded = li.getAttribute('aria-expanded') === 'true';

        if (this.options.accordion) {
            this.handleAccordion(li);
        }

        const icon = link.querySelector('.collapse-sign i');
        this.state.isAnimating = true;

        if (isExpanded) {
            ul.style.display = 'flex';
            this.animateHeight(ul, 'up', () => {
                li.classList.remove('open');
                // Update aria-expanded on the LI
                li.setAttribute('aria-expanded', 'false');
                if (icon) {
                    icon.className = 'sa sa-chevron-down';
                }
                this.state.activeSubmenu = null;
                this.state.isAnimating = false;
            });
        } else {
            ul.style.display = 'flex';
            this.animateHeight(ul, 'down', () => {
                li.classList.add('open');
                // Update aria-expanded on the LI
                li.setAttribute('aria-expanded', 'true');
                if (icon) {
                    icon.className = 'sa sa-chevron-up';
                }
                this.state.activeSubmenu = ul;
                this.state.isAnimating = false;
            });
        }
    }

    animateHeight(element, direction, callback) {
        const existingListener = element._transitionEndListener;
        if (existingListener) {
            element.removeEventListener('transitionend', existingListener);
        }

        const duration = direction === 'up' ? this.options.slideUpSpeed : this.options.slideDownSpeed;
        const timing = this.options.animationTiming === 'easeOutExpo' ?
            'cubic-bezier(0.16, 1, 0.3, 1)' : this.options.animationTiming;

        element.style.removeProperty('transition');
        element.style.removeProperty('height');
        element.style.removeProperty('overflow');

        if (direction === 'down') {
            element.style.display = 'flex';
            element.style.overflow = 'hidden';
            element.style.height = '0';

            element.offsetHeight;

            const height = element.scrollHeight;
            element.style.transition = `height ${duration}ms ${timing}`;
            element.style.height = `${height}px`;

            const onTransitionEnd = () => {
                element.removeEventListener('transitionend', onTransitionEnd);
                element._transitionEndListener = null;
                element.style.removeProperty('height');
                element.style.removeProperty('overflow');
                element.style.removeProperty('transition');
                callback();
            };

            element._transitionEndListener = onTransitionEnd;
            element.addEventListener('transitionend', onTransitionEnd, {once: true});
        } else {
            element.style.overflow = 'hidden';
            element.style.height = `${element.scrollHeight}px`;

            element.offsetHeight;

            element.style.transition = `height ${duration}ms ${timing}`;
            element.style.height = '0';

            const onTransitionEnd = () => {
                element.removeEventListener('transitionend', onTransitionEnd);
                element._transitionEndListener = null;
                element.style.display = 'none';
                element.style.removeProperty('height');
                element.style.removeProperty('overflow');
                element.style.removeProperty('transition');
                callback();
            };

            element._transitionEndListener = onTransitionEnd;
            element.addEventListener('transitionend', onTransitionEnd, {once: true});
        }
    }

    handleAccordion(activeLi) {
        const siblings = Array.from(activeLi.parentElement.children);
        siblings.forEach(sibling => {
            if (sibling !== activeLi) {
                const submenu = sibling.querySelector('ul');
                const link = sibling.querySelector('a');
                if (submenu && getComputedStyle(submenu).display !== 'none') {
                    this.animateHeight(submenu, 'up', () => {
                        sibling.classList.remove('open');
                        // Update aria-expanded on the LI
                        sibling.setAttribute('aria-expanded', 'false');
                        const icon = link?.querySelector('.collapse-sign i');
                        if (icon) {
                            icon.className = 'sa sa-chevron-down';
                        }
                    });
                }
            }
        });
    }

    navigateMenu(currentLink, direction) {
        const links = Array.from(this.element.querySelectorAll('li a'));
        const currentIndex = links.indexOf(currentLink);
        const nextIndex = direction === 'next'
            ? (currentIndex + 1) % links.length
            : (currentIndex - 1 + links.length) % links.length;
        links[nextIndex].focus();
    }

    closeAllSubmenus() {
        const cache = this.cache.get(this.element);
        cache?.subMenus.forEach(submenu => {
            if (submenu.style.display !== 'none') {
                const parent = submenu.parentElement;
                this.animateHeight(submenu, 'up', () => {
                    parent.classList.remove('open');
                    // Update aria-expanded on the LI
                    parent.setAttribute('aria-expanded', 'false');
                    const link = parent.querySelector('a');
                    if (link) {
                        const icon = link.querySelector('.collapse-sign i');
                        if (icon) {
                            icon.className = 'sa sa-chevron-down';
                        }
                    }
                });
            }
        });
        this.state.activeSubmenu = null;
    }

    debounce(fn, wait) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), wait);
        };
    }

    destroy() {
        if (!this.state.isInitialized) return this._debug('Cannot destroy - not initialized', 'warn');
        try {
            this._debug('Starting destruction', 'info');
            this.element.removeEventListener('click', this.handleClick);
            this.element.removeEventListener('keydown', this.handleKeydown);
            this.resetItems();
            this.cleanLinks();
            this.resetSubmenus();
            this.element.removeAttribute('role');
            this.element.removeAttribute('tabIndex');
            this.element.removeAttribute('aria-label');
            this.element.removeAttribute('data-instance-id');
            this.element.classList.remove(this.options.initClass);
            this.cache.delete(this.element);
            Navigation.instances.delete(this.element);
            this.state.isInitialized = false;
            this._debug('Navigation destroyed', 'info');
        } catch (error) {
            this._debug('Destruction failed', 'error', {error});
            this.options.onError?.(error);
            throw error;
        }
    }

    resetItems() {
        const cache = this.cache.get(this.element);
        cache?.items.forEach(item => {
            item.classList.remove('active', 'open');
            item.removeAttribute('role');
        });
    }

    cleanLinks() {
        const cache = this.cache.get(this.element);
        cache?.links.forEach(link => {
            const newLink = link.cloneNode(true);
            link.parentNode?.replaceChild(newLink, link);
            newLink.classList.remove('active');
            const sign = newLink.querySelector('.collapse-sign');
            sign?.remove();
        });
    }

    resetSubmenus() {
        const cache = this.cache.get(this.element);
        cache?.subMenus.forEach(menu => {
            menu.removeAttribute('style');
            menu.removeAttribute('role');
        });
    }

    generateUniqueId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    setupTitleSeparators() {
        // Find all nav-title items and add appropriate accessibility attributes
        const navTitles = this.element.querySelectorAll('li.nav-title');
        navTitles.forEach(item => {
            const titleSpan = item.querySelector('span');
            const titleText = titleSpan?.textContent || 'Section';

            // Set role="separator" and aria-label with the title text
            item.setAttribute('role', 'separator');
            item.setAttribute('aria-label', titleText);

            // Remove any previously set menuitem role
            item.removeAttribute('role');
            item.setAttribute('role', 'separator');
        });
    }

    // Helper to check if an item has any active descendant items
    _hasActiveDescendant(item) {
        return !!item.querySelector('li.active');
    }

    // Ensures at least one menu is visible if none are currently displayed
    _ensureMenuVisibility() {
        const cache = this.cache.get(this.element);
        if (!cache) return;

        // Check if any submenus are already visible
        const visibleSubmenu = cache.subMenus.find(menu =>
            menu.style.display === 'flex' || menu.style.display === 'block'
        );

        // If no submenus are visible, show the first one
        if (!visibleSubmenu && cache.subMenus.length > 0) {
            const firstSubmenu = cache.subMenus[0];
            firstSubmenu.style.display = 'flex';
            firstSubmenu.style.height = 'auto';

            const parentLi = firstSubmenu.closest('li');
            if (parentLi) {
                parentLi.classList.add('open', 'has-ul');
                parentLi.setAttribute('aria-expanded', 'true');

                const link = parentLi.querySelector('a');
                if (link) {
                    const collapseSign = link.querySelector('.collapse-sign i');
                    if (collapseSign) {
                        collapseSign.className = 'sa sa-chevron-up';
                    }
                }
            }

            this.state.activeSubmenu = firstSubmenu;
        }
    }
}