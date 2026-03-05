// NOTE: The scripts is used for all iconography pages and for demo purposes, if you wish you may use any part of the code for your own project

// Global variables (add new ones)
let currentSvgWeight = 'sa-thin'; // Default weight for SVG icons
let isNoFill = false; // Default no-fill state for SVG icons
// Global variables
let allIcons = [];
let searchHistory = new Set();
let searchTimeout = null;
let currentToast = null;
let iconMappings = {};
let currentSearchTerm = '';
let currentIconSet = 'sa';
let isLoadingIconSet = false; // Flag to prevent multiple simultaneous loads
let selectedIcon = null; // Move selectedIcon to global scope so it's shared
let editingLayerIndex = null; // Move editingLayerIndex to global scope
let db; // IndexedDB setup and management



// Semantic matches
function findSemanticMatches(searchTerm) {
    searchTerm = searchTerm.toLowerCase().replace(/\s+/g, '-');
    if (iconMappings[searchTerm]) return iconMappings[searchTerm];

    const partialMatches = Object.keys(iconMappings).filter(key => {
        const normalizedKey = key.toLowerCase().replace(/\s+/g, '-').replace(/^-/, ''); // Strip leading hyphen
        return normalizedKey.includes(searchTerm) || searchTerm.includes(normalizedKey);
    });
    if (partialMatches.length > 0) return partialMatches.flatMap(key => iconMappings[key]);

    for (const category in iconMappings) {
        const categoryMatches = Object.keys(iconMappings[category]).filter(key => {
            const normalizedKey = key.toLowerCase().replace(/\s+/g, '-').replace(/^-/, ''); // Strip leading hyphen
            return normalizedKey.includes(searchTerm) || searchTerm.includes(normalizedKey);
        });
        if (categoryMatches.length > 0) return categoryMatches.flatMap(key => iconMappings[category][key]);
    }
    return null;
}

// Suggestions with Levenshtein distance
function findSuggestions(searchTerm) {
    if (!searchTerm || searchTerm.length < 2) return [];
    searchTerm = searchTerm.replace(/\s+/g, '-').replace(/^-/, ''); // Strip leading hyphen
    const semanticMatches = findSemanticMatches(searchTerm);
    if (semanticMatches) {
        return [...new Set(semanticMatches)].filter(icon =>
            allIcons.includes(`-${icon}`) || allIcons.includes(icon)
        ).map(icon => icon.replace(/^-/, '')); // Clean icons in results
    }
    return allIcons
        .map(icon => {
            const iconName = icon.startsWith('-') ? icon.substring(1) : icon;
            return {
                name: iconName,
                distance: levenshteinDistance(searchTerm.toLowerCase(), iconName.toLowerCase())
            };
        })
        .filter(item => {
            const maxDistance = Math.min(Math.floor(searchTerm.length * 0.4), 3);
            return item.distance > 0 && item.distance <= maxDistance;
        })
        .sort((a, b) => a.distance - b.distance)
        .slice(0, 3)
        .map(item => item.name);
}

function levenshteinDistance(a, b) {
    if (a.length === 0) return b.length;
    if (b.length === 0) return a.length;
    const matrix = Array(b.length + 1).fill(null).map(() => Array(a.length + 1).fill(null));
    for (let i = 0; i <= b.length; i++) matrix[i][0] = i;
    for (let j = 0; j <= a.length; j++) matrix[0][j] = j;
    for (let i = 1; i <= b.length; i++) {
        for (let j = 1; j <= a.length; j++) {
            matrix[i][j] = b.charAt(i - 1) === a.charAt(j - 1) ?
                matrix[i - 1][j - 1] :
                Math.min(matrix[i - 1][j - 1] + 1, matrix[i][j - 1] + 1, matrix[i - 1][j] + 1);
        }
    }
    return matrix[b.length][a.length];
}

// Filter logic
function initializeFilter() {
    const searchInput = document.getElementById('searchIcons');
    searchInput.addEventListener('input', function () {
        currentSearchTerm = this.value.trim().replace(/^-/, ''); // Strip leading hyphen
        filterIcons();
    });
}

function filterIcons() {
    const searchTerms = currentSearchTerm.toLowerCase().split(/\s+/).filter(term => term.length > 0).map(term => term.replace(/^-/, '')); // Strip leading hyphens
    document.querySelectorAll('#iconList li').forEach(item => {
        const text = item.textContent.toLowerCase().replace(/^-/, ''); // Strip leading hyphen
        const matches = searchTerms.every(term => text.includes(term));
        item.classList.toggle('js-filter-hide', !matches);
    });

    updateVisibleCount();

    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => updateSearchHistory(currentSearchTerm), 1000);

    const visibleIcons = document.querySelectorAll('#iconList li:not(.js-filter-hide)').length;
    const suggestionsContainer = document.getElementById('suggestions');
    if (currentSearchTerm.length >= 2 && visibleIcons < 10) {
        const suggestions = findSuggestions(currentSearchTerm);
        suggestionsContainer.innerHTML = suggestions.length > 0 ?
            `<span class="suggest-title">Did you mean?</span> ${suggestions.map(s =>
                `<span class="suggestion px-1" onclick="applySearch('${s}')">${s}</span>`).join(' ')}` :
            '';
    } else {
        suggestionsContainer.innerHTML = '';
    }
}

// Load icon set
async function loadIconSet(iconSet = 'sa') {
    // Prevent multiple simultaneous loads
    if (isLoadingIconSet) {
        console.log('Already loading an icon set, please wait...');
        return;
    }
    
    isLoadingIconSet = true;
    
    try {
        // Show loading indicator
        const iconList = document.getElementById('iconList');
        iconList.innerHTML = '<div class="d-flex justify-content-center w-100 py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        
        // Clear search input and results
        const searchInput = document.getElementById('searchIcons');
        if (searchInput) {
            searchInput.value = '';
            currentSearchTerm = '';
        }
        
        document.getElementById('suggestions').innerHTML = '';
        
        const iconSets = {
            'sa': { icons: 'json/sa-icons.json', mappings: 'json/sa-mappings.json', prefix: 'sa' },
            'base': { icons: 'json/sa-base.json', mappings: 'json/sa-mappings.json', prefix: 'sa' },
            'svg': { icons: 'json/sa-svg-icons.json', mappings: 'json/sa-svg-mappings.json', prefix: 'svg' },
            'fal': { icons: 'json/fa-icons.json', mappings: 'json/fa-mappings.json', prefix: 'fal' },
            'fas': { icons: 'json/fa-icons.json', mappings: 'json/fa-mappings.json', prefix: 'fas' },
            'far': { icons: 'json/fa-icons.json', mappings: 'json/fa-mappings.json', prefix: 'far' },
            'fab': { icons: 'json/fa-brands.json', mappings: 'json/fa-mappings.json', prefix: 'fab' },
            'fad': { icons: 'json/fa-duotone.json', mappings: 'json/fa-mappings.json', prefix: 'fad' },
            'material': { icons: 'json/material-icons.json', mappings: 'json/material-mappings.json', prefix: 'material' }
        };

        const selectedSet = iconSets[iconSet] || iconSets['sa'];
        
        // Always fetch new data when switching icon sets
        try {
            const [iconsResponse, mappingsResponse] = await Promise.all([
                fetch(selectedSet.icons),
                fetch(selectedSet.mappings)
            ]);
            
            if (!iconsResponse.ok || !mappingsResponse.ok) {
                throw new Error('Failed to load resources');
            }
            
            // Clear previous icons and load new ones
            allIcons = (await iconsResponse.json()).map(icon => icon.replace(/^-/, '')); // Strip leading hyphen from all icons
            iconMappings = await mappingsResponse.json();
            
            // Update current icon set and stylesheet
            currentIconSet = iconSet;
            
            // Generate new icon list
            generateIconList(allIcons, selectedSet.prefix);
            
            // Initialize filter if not already done
            if (!document.getElementById('searchIcons').hasAttribute('data-initialized')) {
                initializeFilter();
                document.getElementById('searchIcons').setAttribute('data-initialized', 'true');
            }
            
            console.log(`Successfully loaded icon set: ${iconSet}`);
        } catch (error) {
            console.error('Error loading icon set:', error);
            iconList.innerHTML = `<div class="alert alert-danger m-3">Failed to load icon set: ${error.message}</div>`;
        }
    } finally {
        isLoadingIconSet = false;
    }

    // Re-apply filter if search term exists
    if (currentSearchTerm) filterIcons();
    updateVisibleCount();
}

// Generate icon list
function generateIconList(icons, iconPrefix) {
    const iconList = document.getElementById('iconList');
    iconList.innerHTML = icons.map(icon => {
        const cleanIconName = icon.replace(/^-/, ''); // Ensure no leading hyphen
        const iconClass = getIconClass(iconPrefix, cleanIconName);
        const displayName = cleanIconName;
        const isSvg = iconPrefix === 'svg';
        return `
            <li class="d-flex justify-content-center align-items-center" style="width: 85px;" data-icon-name="${displayName}">
                <a href="#" class="js-showcase-icon rounded color-fusion-300 p-0 m-0 d-flex flex-column w-100 shadow-hover-2 ${isSvg ? 'has-svg' : ''}">
                    <div class="icon-preview rounded-top w-100 position-relative">
                        <div class="icon-container rounded-top d-flex align-items-center justify-content-center w-100 pt-2 pb-2 pe-2 ps-2 position-absolute">
                            ${iconClass}
                        </div>
                    </div>
                    <div class="rounded-bottom p-1 w-100 d-flex justify-content-center align-items-center text-center mt-auto">
                        <span class="nav-link-text small text-muted text-truncate">${displayName}</span>
                    </div>
                </a>
            </li>
        `;
    }).join('');
    updateVisibleCount();
    addIconClickHandlers();
}

// Search history
function updateSearchHistory(term) {
    if (term && term.length >= 2) {
        searchHistory.add(term.replace(/^-/, '')); // Strip leading hyphen
        if (searchHistory.size > 5) searchHistory.delete([...searchHistory][0]);
        renderSearchHistory();
    }
}

function renderSearchHistory() {
    const historyContainer = document.getElementById('searchHistory');
    historyContainer.innerHTML = [...searchHistory].map(term =>
        `<span class="badge bg-secondary me-1" onclick="applySearch('${term}')">
            <span class="text-truncate-xs overflow-hidden">${term}</span>
            <i class="sa sa-close ms-1" onclick="event.stopPropagation(); removeFromHistory('${term}')"></i>
        </span>`
    ).join('');
}

function removeFromHistory(term) {
    searchHistory.delete(term);
    renderSearchHistory();
}

function applySearch(term) {
    const searchIcons = document.getElementById('searchIcons');
    searchIcons.value = term.replace(/^-/, ''); // Strip leading hyphen
    currentSearchTerm = term.replace(/^-/, ''); // Strip leading hyphen
    filterIcons();
}

// Icon utilities
function updateVisibleCount() {
    const visibleIcons = document.querySelectorAll('#iconList li:not(.js-filter-hide)').length;
    const totalIcons = allIcons.length;
    document.querySelector('.results-count').textContent =
        `Showing ${visibleIcons} of ${totalIcons} icons`;
}

// Update getIconClass to handle SVG classes dynamically (add this at the end of the existing getIconClass function)
const getIconClass = (prefix, icon) => {
    const cleanIcon = icon.replace(/^-/, ''); // Ensure no leading hyphen
    switch (prefix) {
        case 'svg':
            const weightClass = currentSvgWeight; // Use the current weight from dropdown
            const fillClass = isNoFill ? ' sa-nofill' : ''; // Add no-fill class if checkbox is checked
            return `<svg class="sa-icon ${weightClass}${fillClass}"><use href="icons/sprite.svg#${cleanIcon}"></use></svg>`;
        case 'fal':
            return `<i class="fal fa-${cleanIcon}"></i>`;
        case 'fas':
            return `<i class="fas fa-${cleanIcon}"></i>`;
        case 'far':
            return `<i class="far fa-${cleanIcon}"></i>`;
        case 'fad':
            return `<i class="fad fa-${cleanIcon}"></i>`;
        case 'fab':
            return `<i class="fab fa-${cleanIcon}"></i>`;
        case 'material':
            return `<i class="material-icons">${cleanIcon}</i>`;
        case 'sa':
            return `<i class="sa sa-${cleanIcon}"></i>`; // SmartAdmin format: sa sa-iconname
        case 'base':
            return `<i class="sa base-${cleanIcon}"></i>`;    
        default:
            return `<i class="${prefix} ${prefix}-${cleanIcon}"></i>`;
    }
};

function addIconClickHandlers() {
    // Get the select button from the modal
    const selectButton = document.querySelector('#example-modal-backdrop-transparent .modal-footer .btn-primary');
    
    // Disable the select button initially
    if (selectButton) {
        selectButton.disabled = true;
        selectButton.classList.add('disabled');
    }
    
    // Remove any existing click handlers to avoid conflicts
    document.querySelectorAll('.js-showcase-icon').forEach(iconElement => {
        const newElement = iconElement.cloneNode(true);
        iconElement.parentNode.replaceChild(newElement, iconElement);
    });
    
    // Add new click handlers for icon selection
    document.querySelectorAll('.js-showcase-icon').forEach(iconElement => {
        iconElement.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            
            // Remove selection from all icons
            document.querySelectorAll('.js-showcase-icon').forEach(icon => {
                icon.classList.remove('selected-icon');
            });
            
            // Add selection to clicked icon
            this.classList.add('selected-icon');
            
            // Store the selected icon
            const iconEl = this.querySelector('svg') || this.querySelector('i');
            const iconContainer = this.querySelector('.icon-container');
            
            if (iconEl && iconContainer) {
                // Store the icon HTML for later use in the global variable
                selectedIcon = iconContainer.innerHTML.trim();
                console.log('Icon selected:', selectedIcon); // Debug log
                
                // Enable the select button
                if (selectButton) {
                    selectButton.disabled = false;
                    selectButton.classList.remove('disabled');
                }
            }
        });
    });
}

function copyToClipboard(text) {
    let copyText = text;
    if (text.includes('sprite.svg#')) {
        // Keep the weight and fill classes when copying SVG markup
        const weightClass = currentSvgWeight;
        const fillClass = isNoFill ? ' sa-nofill' : '';
        copyText = `<svg class="sa-icon ${weightClass}${fillClass}"><use href="${text.split('href="')[1].split('"')[0]}"></use></svg>`;
    }
    navigator.clipboard.writeText(copyText).then(() =>
        console.log('Icon class/markup copied:', copyText)
    ).catch(err => console.error('Failed to copy:', err));
}

function escapeHTML(str) {
    return str.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>')
        .replace(/"/g, '"').replace(/'/g, "'");
}

function showToast(message, type = 'primary') {
    if (currentToast) {
        currentToast.hide();
    }

    // Check if toast container exists, create if not
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.className = `toast hide align-items-center border-0 py-2 px-3 bg-${type} text-white`;
    toast.id = toastId;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.style.setProperty('--bs-toast-max-width', 'auto');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center justify-content-center">
                ${message}
            </div>
            <button type="button" class="btn btn-system ms-auto" data-bs-dismiss="toast" aria-label="Close">
                <svg class="sa-icon sa-icon-light">
                    <use href="icons/sprite.svg#x"></use>
                </svg>
            </button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Initialize and show the toast
    currentToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 3000
    });
    
    // Remove toast after it's hidden
    toast.addEventListener('hidden.bs.toast', function() {
        currentToast = null;
        toast.remove();
    });
    
    currentToast.show();
}

function updateSvgClasses() {
    document.querySelectorAll('#iconList li').forEach(item => {
        const svgElement = item.querySelector('svg');
        if (svgElement) {
            const weightClass = currentSvgWeight;
            const fillClass = isNoFill ? ' sa-nofill' : '';
            // Update classes using classList
            svgElement.setAttribute('class', `sa-icon ${weightClass}${fillClass}`);
        }
    });
}

// Initialize when DOM is loaded
// document.addEventListener('DOMContentLoaded', function() {
//     loadIconSet('sa')
//         .then(() => console.log('Icon set loaded successfully'))
//         .catch(error => console.error('Failed to load icon set:', error));
// });


/// zoom container
// Function to handle zoom-in (incrementing classes)
function adjustZoom(direction) {
    var container = document.getElementById('stackgenerator-container');
    var currentClass = container.className.match(/icon-zoom-(\d+)/); // Extract current zoom level
    var currentLevel = currentClass ? parseInt(currentClass[1], 10) : 0; // Default to 0 if no zoom class

    // Remove existing zoom class
    if (currentLevel > 0) {
        container.className = container.className.replace(/icon-zoom-\d+/, '');
    }

    // Calculate new level based on direction
    var newLevel;
    if (direction === 'in') {
        newLevel = currentLevel < 15 ? currentLevel + 1 : 15; // Zoom in, cap at 10
    } else if (direction === 'out') {
        newLevel = currentLevel > 1 ? currentLevel - 1 : 0; // Zoom out, floor at 0
    } else {
        return; // Invalid direction, do nothing
    }

    // Add the new zoom class (skip if level is 0)
    if (newLevel > 0) {
        container.className += ' icon-zoom-' + newLevel;
    }
}

// Example usage with event listeners
document.getElementById('zoomInBtn').onclick = function() {
    adjustZoom('in');
};

document.getElementById('zoomOutBtn').onclick = function() {
    adjustZoom('out');
};

// Responsive Draggable Stack Control Panel with Grid Snapping
document.addEventListener('DOMContentLoaded', function() {
    const stackControl = document.getElementById('stack-control');
    const container = document.getElementById('stackgenerator-container');
    
    if (!stackControl || !container) return;
    
    // Make the panel draggable only by its header
    const panelHeader = stackControl.querySelector('.panel-hdr > h2');
    if (!panelHeader) return;
    
    // Add draggable cursor and styling
    panelHeader.style.cursor = 'move';
    stackControl.style.position = 'absolute';
    stackControl.style.zIndex = '50';
    
    // Offset for boundaries
    const offsetX = 16;
    const offsetY = 16;
    
    // Grid size for snapping (in pixels)
    const gridSize = 20;
    
    // Variables to track dragging
    let isDragging = false;
    let startX, startY;
    let startPosX, startPosY;
    
    // Initialize save and copy button states
    updateSaveButtonState();
    
    // Function to position the panel within bounds
    function positionPanelWithinBounds(animate = false) {
        const containerRect = container.getBoundingClientRect();
        const panelRect = stackControl.getBoundingClientRect();
        
        // Get current position
        let currentLeft = parseInt(stackControl.style.left) || 0;
        let currentTop = parseInt(stackControl.style.top) || 0;
        
        // Calculate boundaries
        const maxX = containerRect.width - panelRect.width - offsetX;
        const maxY = containerRect.height - panelRect.height - offsetY;
        const minX = offsetX;
        const minY = offsetY;
        
        // Adjust position if needed
        let newLeft = Math.max(minX, Math.min(maxX, currentLeft));
        let newTop = Math.max(minY, Math.min(maxY, currentTop));
        
        // Apply position with or without animation
        if (animate) {
            stackControl.style.transition = 'all 0.3s ease-out';
            setTimeout(() => {
                stackControl.style.transition = '';
            }, 300);
        }
        
        stackControl.style.left = newLeft + 'px';
        stackControl.style.top = newTop + 'px';
    }
    
    // Set initial position in top right corner with offset
    function setInitialPosition() {
        const containerRect = container.getBoundingClientRect();
        const panelRect = stackControl.getBoundingClientRect();
        
        // Position in top right corner with offset
        const rightPosition = containerRect.width - panelRect.width - offsetX;
        
        stackControl.style.top = offsetY + 'px';
        stackControl.style.left = rightPosition + 'px';
    }
    
    // Initialize position
    setInitialPosition();
    
    // Start dragging
    panelHeader.addEventListener('mousedown', function(e) {
        // Only allow left mouse button
        if (e.button !== 0) return;
        
        e.preventDefault();
        
        // Get initial positions
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        
        // Get current panel position
        const rect = stackControl.getBoundingClientRect();
        startPosX = rect.left;
        startPosY = rect.top;
        
        // Add dragging class for visual feedback
        stackControl.classList.add('is-dragging');
        
        // Add temporary overlay to prevent text selection during drag
        const overlay = document.createElement('div');
        overlay.id = 'drag-overlay';
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.zIndex = '999';
        overlay.style.cursor = 'move';
        document.body.appendChild(overlay);
    });
    
    // Handle dragging
    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        
        // Calculate new position
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        
        // Get container boundaries
        const containerRect = container.getBoundingClientRect();
        const panelRect = stackControl.getBoundingClientRect();
        
        // Calculate new position with boundaries
        let newX = startPosX + dx;
        let newY = startPosY + dy;
        
        // Apply boundaries with offset
        const maxX = containerRect.right - panelRect.width - offsetX;
        const maxY = containerRect.bottom - panelRect.height - offsetY;
        const minX = containerRect.left + offsetX;
        const minY = containerRect.top + offsetY;
        
        newX = Math.max(minX, Math.min(maxX, newX));
        newY = Math.max(minY, Math.min(maxY, newY));
        
        // Update position
        stackControl.style.left = (newX - containerRect.left) + 'px';
        stackControl.style.top = (newY - containerRect.top) + 'px';
    });
    
    // End dragging with grid snapping
    document.addEventListener('mouseup', function() {
        if (!isDragging) return;
        
        isDragging = false;
        
        // Remove dragging class
        stackControl.classList.remove('is-dragging');
        
        // Remove overlay
        const overlay = document.getElementById('drag-overlay');
        if (overlay) overlay.remove();
        
        // Apply grid snapping
        const rect = stackControl.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        
        // Calculate position relative to container
        let relativeX = rect.left - containerRect.left;
        let relativeY = rect.top - containerRect.top;
        
        // Snap to grid
        relativeX = Math.round(relativeX / gridSize) * gridSize;
        relativeY = Math.round(relativeY / gridSize) * gridSize;
        
        // Apply snapped position with animation
        stackControl.style.transition = 'all 0.2s ease-out';
        stackControl.style.left = relativeX + 'px';
        stackControl.style.top = relativeY + 'px';
        
        // Remove transition after animation completes
        setTimeout(() => {
            stackControl.style.transition = '';
        }, 200);
    });
    
    // Handle window resize to keep panel in bounds
    window.addEventListener('resize', function() {
        // Use requestAnimationFrame to avoid excessive calculations during resize
        requestAnimationFrame(function() {
            positionPanelWithinBounds(true);
        });
    });
    
    // Handle container size changes (for responsive layouts)
    // Use ResizeObserver if available, fallback to periodic checks
    if (typeof ResizeObserver !== 'undefined') {
        const resizeObserver = new ResizeObserver(function() {
            positionPanelWithinBounds(true);
        });
        resizeObserver.observe(container);
    } else {
        // Fallback for browsers without ResizeObserver
        let lastWidth = container.clientWidth;
        let lastHeight = container.clientHeight;
        
        // Check periodically for size changes
        setInterval(function() {
            if (lastWidth !== container.clientWidth || lastHeight !== container.clientHeight) {
                lastWidth = container.clientWidth;
                lastHeight = container.clientHeight;
                positionPanelWithinBounds(true);
            }
        }, 250);
    }
    
    // Add CSS for visual feedback during dragging
    const style = document.createElement('style');
    style.textContent = `
        #stack-control.is-dragging {
            opacity: 0.8;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        #stack-control .panel-hdr:hover {
            background-color: rgba(0,0,0,0.05);
        }
    `;
    document.head.appendChild(style);
});

// Icon Stack Management System
document.addEventListener('DOMContentLoaded', function() {
    // Global variables for icon stack management are now defined at the top of the file
    const MAX_LAYERS = 4;
    
    // Initialize the icon stack management
    function initIconStackManagement() {
        const modal = document.getElementById('example-modal-backdrop-transparent');
        const selectButton = modal.querySelector('.modal-footer .btn-primary');
        const iconContainer = document.getElementById('my-icon');
        const stackControl = document.getElementById('stack-control');
        const stackControlContent = stackControl.querySelector('.panel-content');
        
        // Add event listener for modal hidden event to reset state
        modal.addEventListener('hidden.bs.modal', function() {
            // Reset selection state when modal is closed
            selectedIcon = null;
            document.querySelectorAll('#iconList li a.js-showcase-icon').forEach(i => {
                i.classList.remove('selected-icon');
            });
            
            // Disable the select button
            if (selectButton) {
                selectButton.disabled = true;
                selectButton.classList.add('disabled');
            }
            
            console.log('Modal closed, reset selection state');
        });
        
        // Add event listener for modal shown event to ensure handlers are attached
        modal.addEventListener('shown.bs.modal', function() {
            console.log('Modal opened, reattaching icon click handlers');
            // Reattach icon click handlers to ensure they work
            setTimeout(addIconClickHandlers, 100);
        });
        
        // Initialize Sortable for drag and drop layer reordering
        let sortableInstance = null;
        
        function initSortable() {
            if (sortableInstance) {
                sortableInstance.destroy();
            }
            
            sortableInstance = new Sortable(stackControlContent, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    // Reorder the actual icon layers based on the new order in the control panel
                    reorderIconLayers();
                }
            });
        }
        
        // Function to reorder icon layers based on the order in the control panel
        function reorderIconLayers() {
            // Get all layers from the control panel
            const controlLayers = stackControlContent.querySelectorAll('.stack-layers');
            
            // Create a new array to hold the reordered layers
            const reorderedLayers = [];
            
            // Store the settings for each layer by its original index
            const layerSettingsByOriginalIndex = {};
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            
            // First, store settings for each layer by its original index
            iconLayers.forEach((layer, index) => {
                const iconElement = layer.querySelector('svg, i');
                
                // Get rotation value
                let rotation = '0';
                if (iconElement) {
                    for (const cls of iconElement.classList) {
                        if (cls.startsWith('rotate-')) {
                            rotation = cls.replace('rotate-', '');
                            break;
                        }
                    }
                }
                
                // Get opacity value
                let opacity = '10';
                if (iconElement) {
                    for (const cls of iconElement.classList) {
                        if (cls.startsWith('alpha-')) {
                            opacity = cls.replace('alpha-', '');
                            break;
                        }
                    }
                }
                
                // Store settings by original index
                layerSettingsByOriginalIndex[index] = {
                    rotation: rotation,
                    opacity: opacity
                };
            });
            
            // Create a mapping of original indices to new positions
            const newPositions = {};
            
            // Get the layers in their new order from the control panel
            controlLayers.forEach((controlLayer, newIndex) => {
                const originalIndex = parseInt(controlLayer.dataset.layerIndex);
                if (originalIndex >= 0 && originalIndex < iconLayers.length) {
                    reorderedLayers.push(iconLayers[originalIndex]);
                    newPositions[originalIndex] = newIndex;
                }
            });
            
            // Clear the icon container
            iconContainer.innerHTML = '';
            
            // Add the layers back in their new order
            reorderedLayers.forEach((layer) => {
                iconContainer.appendChild(layer);
            });
            
            // Update the stack classes
            //updateStackClasses();
            
            // Update the control panel with the new order
            updateStackControlPanel();
            
            // Now restore each layer's settings based on its original index
            const newControlLayers = stackControlContent.querySelectorAll('.stack-layers');
            newControlLayers.forEach((controlLayer) => {
                const newIndex = parseInt(controlLayer.dataset.layerIndex);
                
                // Find which original layer is now at this position
                let originalIndex = null;
                for (const [origIdx, newPos] of Object.entries(newPositions)) {
                    if (parseInt(newPos) === newIndex) {
                        originalIndex = parseInt(origIdx);
                        break;
                    }
                }
                
                if (originalIndex !== null && layerSettingsByOriginalIndex[originalIndex]) {
                    const settings = layerSettingsByOriginalIndex[originalIndex];
                    
                    // Update rotation slider
                    const rotationSlider = controlLayer.querySelector(`.rotation-slider`);
                    if (rotationSlider) {
                        rotationSlider.value = settings.rotation;
                        const rotationValue = rotationSlider.parentElement.querySelector('.rotation-value');
                        if (rotationValue) {
                            rotationValue.textContent = `${settings.rotation}°`;
                        }
                    }
                    
                    // Update opacity slider
                    const opacitySlider = controlLayer.querySelector(`.opacity-slider`);
                    if (opacitySlider) {
                        opacitySlider.value = settings.opacity;
                        const opacityValue = opacitySlider.parentElement.querySelector('.opacity-value');
                        if (opacityValue) {
                            opacityValue.textContent = `${settings.opacity}0%`;
                        }
                    }
                }
            });
        }
        
        // Function to update stack classes based on layer position
        // function updateStackClasses() {
        //     const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            
        //     iconLayers.forEach((layer, idx) => {
        //         const iconElement = layer.querySelector('svg, i');
        //         if (!iconElement) return;

        //         // Only apply stack classes if they don't already exist
        //         if (!iconElement.classList.contains('stack-1x') && 
        //             !iconElement.classList.contains('stack-2x') && 
        //             !iconElement.classList.contains('stack-3x')) {
                    
        //             // Add appropriate stack class based on position
        //             if (idx === 0 && iconLayers.length >= 1) {
        //                 iconElement.classList.add('stack-3x'); // Bottom layer (largest)
        //             } else if (idx === 1 || (idx === 0 && iconLayers.length === 1)) {
        //                 iconElement.classList.add('stack-2x'); // Middle layer or single layer
        //             } else {
        //                 iconElement.classList.add('stack-1x'); // Top layer (smallest)
        //             }
        //         }
        //     });
        // }
        
        // Clear existing layers in stack control panel and add fine-tuning controls
        function updateStackControlPanel() {
            // Clear the existing content
            stackControlContent.innerHTML = '';
            
            // Get all icon layers
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            
            // Create a control panel for each layer
            iconLayers.forEach((layer, index) => {
                const layerContent = layer.innerHTML.trim();
                const layerClass = layer.className;
                
                // Create a new layer in the stack control panel
                const layerElement = document.createElement('div');
                layerElement.className = 'stack-layers d-flex flex-column';
                layerElement.dataset.layerIndex = index;
                
                // Extract icon name for display
                let iconName = 'Unknown Icon';
                if (layerContent.includes('class="')) {
                    const classMatch = layerContent.match(/class="([^"]+)"/);
                    if (classMatch && classMatch[1]) {
                        // For SVG icons, extract name from href attribute
                        if (layerContent.includes('<svg') && layerContent.includes('href="')) {
                            const hrefMatch = layerContent.match(/href="[^#]+#([^"]+)"/);
                            if (hrefMatch && hrefMatch[1]) {
                                iconName = hrefMatch[1];
                            } else {
                                // Filter out common class prefixes and utility classes
                                const classes = classMatch[1].split(' ');
                                const filteredClasses = classes.filter(c => {
                                    // Keep only the meaningful part of the class name
                                    return !['sa-icon', 'sa', 'stack-1x', 'stack-2x', 'stack-3x', 'fal', 'far', 'fas', 'fad', 'fab', 
                                            'text-primary', 'text-secondary', 'text-success', 'text-danger', 'text-warning', 
                                            'text-info', 'text-dark', 'text-light', 'sa-thin', 'sa-regular', 'sa-medium', 'sa-bold', 
                                            'sa-nofill', 'alpha-1', 'alpha-2', 'alpha-3', 'alpha-4', 'alpha-5', 'alpha-6', 'alpha-7', 
                                            'alpha-8', 'alpha-9', 'alpha-10'].includes(c) && 
                                           !c.startsWith('rotate-');
                                });
                                
                                // For sa-prefixed icons, show only the specific icon name
                                if (filteredClasses.some(c => c.startsWith('sa-'))) {
                                    iconName = filteredClasses.find(c => c.startsWith('sa-'));
                                } else if (filteredClasses.some(c => c.startsWith('base-'))) {
                                    iconName = filteredClasses.find(c => c.startsWith('base-'));
                                } else if (filteredClasses.length > 0) {
                                    iconName = filteredClasses.join(' ');
                                }
                            }
                        } else {
                            // For font icons, extract the specific icon name
                            const classes = classMatch[1].split(' ');
                            const filteredClasses = classes.filter(c => {
                                // Keep only the meaningful part of the class name
                                return !['sa-icon', 'sa', 'stack-1x', 'stack-2x', 'stack-3x', 'fal', 'far', 'fas', 'fad', 'fab', 
                                        'text-primary', 'text-secondary', 'text-success', 'text-danger', 'text-warning', 
                                        'text-info', 'text-dark', 'text-light', 'sa-thin', 'sa-regular', 'sa-medium', 'sa-bold', 
                                        'sa-nofill', 'alpha-1', 'alpha-2', 'alpha-3', 'alpha-4', 'alpha-5', 'alpha-6', 'alpha-7', 
                                        'alpha-8', 'alpha-9', 'alpha-10'].includes(c) && 
                                       !c.startsWith('rotate-');
                            });
                            
                            // For sa-prefixed icons, show only the specific icon name
                            if (filteredClasses.some(c => c.startsWith('sa-'))) {
                                iconName = filteredClasses.find(c => c.startsWith('sa-'));
                            } else if (filteredClasses.some(c => c.startsWith('base-'))) {
                                iconName = filteredClasses.find(c => c.startsWith('base-'));
                            } else if (filteredClasses.some(c => c.startsWith('fa-'))) {
                                iconName = filteredClasses.find(c => c.startsWith('fa-'));
                            } else if (filteredClasses.length > 0) {
                                iconName = filteredClasses.join(' ');
                            }
                        }
                    }
                }
                
                // Extract current settings
                const isSvg = layerContent.includes('<svg');
                const iconElement = layer.querySelector('svg, i');
                
                // Extract current size from icon element's stack class
                let currentSize = '2x'; // default
                if (iconElement) {
                    if (iconElement.classList.contains('stack-1x')) currentSize = '1x';
                    else if (iconElement.classList.contains('stack-2x')) currentSize = '2x';
                    else if (iconElement.classList.contains('stack-3x')) currentSize = '3x';
                }
                
                // Extract rotation class if any
                let currentRotation = '0';
                if (iconElement) {
                    for (const cls of iconElement.classList) {
                        if (cls.startsWith('rotate-')) {
                            currentRotation = cls.replace('rotate-', '');
                            break;
                        }
                    }
                }
                
                // Extract opacity class if any
                let currentOpacity = '10';
                if (iconElement) {
                    for (const cls of iconElement.classList) {
                        if (cls.startsWith('alpha-')) {
                            currentOpacity = cls.replace('alpha-', '');
                            break;
                        }
                    }
                }
                
                // Extract color class if any
                let currentColor = '';
                const colorClasses = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'light'];
                for (const color of colorClasses) {
                    if (isSvg && layer.querySelector(`svg.sa-icon-${color}`)) {
                        currentColor = color;
                        break;
                    } else if (!isSvg && layer.querySelector(`i.text-${color}`)) {
                        currentColor = color;
                        break;
                    }
                }
                
                // Extract current settings for SVG
                let currentSvgWeight = '';
                let currentNoFill = false; // Initialize currentNoFill variable
                if (isSvg) {
                    const svgElement = layer.querySelector('svg');
                    if (svgElement) {
                        if (svgElement.classList.contains('sa-thin')) currentSvgWeight = 'sa-thin';
                        else if (svgElement.classList.contains('sa-regular')) currentSvgWeight = 'sa-regular';
                        else if (svgElement.classList.contains('sa-medium')) currentSvgWeight = 'sa-medium';
                        else if (svgElement.classList.contains('sa-bold')) currentSvgWeight = 'sa-bold';
                        
                        currentNoFill = svgElement.classList.contains('sa-nofill');
                    }
                } else {
                    currentSvgWeight = '';
                    currentNoFill = false;
                }
                
                // Create the layer header and controls
                const layerHeader = `
                    <div class="d-flex flex-row border-bottom position-relative">
                        <div class="stack-icon-preview flex-shrink-0 d-flex align-items-center justify-content-center">
                            ${layerContent}
                        </div>
                        <div class="icon-settings d-flex flex-column flex-grow-1 border-start px-2 py-2 pe-4">
                            <div class="d-flex justify-content-between align-items-center flex-grow-1">
                                <div class="fs-sm">
                                    <span class="text-primary fw-bold">${iconName}</span>
                                    <span class="badge badge-icon">
                                        Layer ${index + 1}
                                    </span>
                                </div>
                                <div class="layer-actions d-flex gap-1">
                                    
                                    <button type="button" class="btn btn-icon btn-outline-secondary edit-layer p-1 w-auto h-auto border-0" data-layer="${index}">
                                        <i class="sa sa-reload"></i>
                                    </button>
                                    <button type="button" class="btn btn-icon btn-outline-secondary tune-layer p-1 w-auto h-auto border-0" data-layer="${index}" data-bs-toggle="collapse" data-bs-target="#layer-controls-${index}" aria-expanded="false">
                                        <i class="sa sa-settings"></i>
                                    </button>
                                    <button type="button" class="btn btn-icon btn-outline-danger delete-layer p-1 w-auto h-auto border-0" data-layer="${index}">
                                        <i class="sa sa-close"></i>
                                    </button>
                                    <div class="drag-handle btn btn-icon btn-default-outline">
                                        <svg class="sa-icon sa-thin">
                                            <use href="icons/sprite.svg#more-vertical"></use>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Create the controls panel
                const controlsPanel = `
                    <div class="layer-control-panel collapse" id="layer-controls-${index}" data-layer="${index}">
                        <div class="control-panel-content shadow-inset-2 px-3 pt-1 pb-3" onclick="event.stopPropagation();">
                            <!-- Size controls -->
                            <div class="mb-2">
                                <label class="form-label mb-1 fs-sm">Size</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="size-${index}" id="size-1x-${index}" value="1x" ${currentSize === '1x' ? 'checked' : ''}>
                                    <label class="btn btn-outline-secondary btn-xs" for="size-1x-${index}">Small</label>
                                    
                                    <input type="radio" class="btn-check" name="size-${index}" id="size-2x-${index}" value="2x" ${currentSize === '2x' ? 'checked' : ''}>
                                    <label class="btn btn-outline-secondary btn-xs" for="size-2x-${index}">Medium</label>
                                    
                                    <input type="radio" class="btn-check" name="size-${index}" id="size-3x-${index}" value="3x" ${currentSize === '3x' ? 'checked' : ''}>
                                    <label class="btn btn-outline-secondary btn-xs" for="size-3x-${index}">Large</label>
                                </div>
                            </div>
                            
                            <!-- Rotation controls (slider) -->
                            <div class="mb-2">
                                <label class="form-label mb-1 fs-sm">Rotation: <span class="rotation-value">${currentRotation}°</span></label>
                                <input type="range" class="form-range rotation-slider" min="0" max="360" step="45" value="${currentRotation}" data-layer="${index}">
                            </div>
                            
                            <!-- Opacity slider -->
                            <div class="mb-2">
                                <label class="form-label mb-1 fs-sm">Opacity: <span class="opacity-value">${currentOpacity}0%</span></label>
                                <input type="range" class="form-range opacity-slider" min="1" max="10" step="1" value="${currentOpacity}" data-layer="${index}">
                            </div>
                            
                            <!-- SVG specific controls -->
                            ${isSvg ? `
                            <div class="mb-2">
                                <label class="form-label mb-1 fs-sm">SVG Weight</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="weight-${index}" id="weight-thin-${index}" value="sa-thin" ${currentSvgWeight === 'sa-thin' ? 'checked' : ''}>
                                    <label class="btn btn-outline-secondary btn-xs" for="weight-thin-${index}">Thin</label>
                                    
                                    <input type="radio" class="btn-check" name="weight-${index}" id="weight-regular-${index}" value="sa-regular" ${currentSvgWeight === 'sa-regular' ? 'checked' : ''}>
                                    <label class="btn btn-outline-secondary btn-xs" for="weight-regular-${index}">Normal</label>
                                    
                                    <input type="radio" class="btn-check" name="weight-${index}" id="weight-medium-${index}" value="sa-medium" ${currentSvgWeight === 'sa-medium' ? 'checked' : ''}>
                                    <label class="btn btn-outline-secondary btn-xs" for="weight-medium-${index}">Medium</label>
                                    
                                    <input type="radio" class="btn-check" name="weight-${index}" id="weight-bold-${index}" value="sa-bold" ${currentSvgWeight === 'sa-bold' ? 'checked' : ''}>
                                    <label class="btn btn-outline-secondary btn-xs" for="weight-bold-${index}">Bold</label>
                                </div>
                            </div>
                            
                            <div class="mb-2 form-check">
                                <input type="checkbox" class="form-check-input svg-nofill-check" id="nofill-${index}" ${currentNoFill ? 'checked' : ''} data-layer="${index}">
                                <label class="form-check-label" for="nofill-${index}">No Fill</label>
                            </div>
                            ` : ''}
                            
                            <!-- Color controls -->
                            <div>
                                <label class="form-label mb-1 fs-sm">Color</label>
                                <div class="d-flex flex-wrap gap-1">
                                    <button type="button" class="btn btn-icon btn-outline-default color-btn ${!currentColor ? 'active' : ''}" data-color="" data-layer="${index}">
                                        <i class="sa sa-ban"></i>
                                    </button>
                                    <button type="button" class="btn btn-icon btn-primary color-btn ${currentColor === 'primary' ? 'active' : ''}" data-color="primary" data-layer="${index}"></button>
                                    <button type="button" class="btn btn-icon btn-secondary color-btn ${currentColor === 'secondary' ? 'active' : ''}" data-color="secondary" data-layer="${index}"></button>
                                    <button type="button" class="btn btn-icon btn-success color-btn ${currentColor === 'success' ? 'active' : ''}" data-color="success" data-layer="${index}"></button>
                                    <button type="button" class="btn btn-icon btn-danger color-btn ${currentColor === 'danger' ? 'active' : ''}" data-color="danger" data-layer="${index}"></button>
                                    <button type="button" class="btn btn-icon btn-warning color-btn ${currentColor === 'warning' ? 'active' : ''}" data-color="warning" data-layer="${index}"></button>
                                    <button type="button" class="btn btn-icon btn-info color-btn ${currentColor === 'info' ? 'active' : ''}" data-color="info" data-layer="${index}"></button>
                                    <button type="button" class="btn btn-icon btn-dark color-btn ${currentColor === 'dark' ? 'active' : ''}" data-color="dark" data-layer="${index}"></button>
                                    <button type="button" class="btn btn-icon btn-default color-btn ${currentColor === 'light' ? 'active' : ''}" data-color="light" data-layer="${index}"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Combine the header and controls panel
                layerElement.innerHTML = layerHeader + controlsPanel;
                
                stackControlContent.appendChild(layerElement);
            });
            
            // Add event listeners for edit and delete buttons
            stackControlContent.querySelectorAll('.edit-layer').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const layerIndex = parseInt(this.dataset.layer);
                    editLayer(layerIndex);
                });
            });
            
            stackControlContent.querySelectorAll('.delete-layer').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const layerIndex = parseInt(this.dataset.layer);
                    deleteLayer(layerIndex);
                });
            });
            
            // Add event listeners for the tune-layer buttons to implement accordion behavior
            stackControlContent.querySelectorAll('.tune-layer').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    
                    const targetId = this.getAttribute('data-bs-target');
                    const targetPanel = document.querySelector(targetId);
                    const isExpanded = targetPanel.classList.contains('show');
                    
                    // If this panel is being expanded, close all other panels first
                    if (!isExpanded) {
                        // Close all other open panels
                        document.querySelectorAll('.layer-control-panel.show').forEach(panel => {
                            if (panel.id !== targetId.substring(1)) { // Remove the # from targetId
                                const bsCollapse = bootstrap.Collapse.getInstance(panel);
                                if (bsCollapse) {
                                    bsCollapse.hide();
                                }
                            }
                        });
                    }
                });
            });
            
            // Add click event listeners to all control panel content to stop propagation
            stackControlContent.querySelectorAll('.control-panel-content').forEach(panel => {
                panel.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent clicks inside the panel from bubbling up
                });
            });
            
            // Add event listeners for all form elements inside control panels
            stackControlContent.querySelectorAll('.layer-control-panel input, .layer-control-panel label, .layer-control-panel button').forEach(element => {
                element.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                });
            });
            
            // Initialize Sortable for drag and drop
            initSortable();
            
            // Add event listeners for size controls
            stackControlContent.querySelectorAll('input[type="radio"][name^="size-"]').forEach(radio => {
                radio.addEventListener('change', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const layerIndex = parseInt(this.id.split('-')[2]);
                    const size = this.value;
                    updateLayerSize(layerIndex, size);
                });
            });
            
            // Add event listeners to rotation sliders
            const rotationSliders = document.querySelectorAll('.rotation-slider');
            rotationSliders.forEach(slider => {
                slider.addEventListener('input', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    
                    const layerIndex = parseInt(this.dataset.layer);
                    const rotation = this.value;
                    
                    // Update the displayed value
                    const valueDisplay = this.parentElement.querySelector('.rotation-value');
                    if (valueDisplay) {
                        valueDisplay.textContent = `${rotation}°`;
                    }
                    
                    // If rotation is 360, set it to 0 (full circle = no rotation)
                    const effectiveRotation = rotation === '360' ? '0' : rotation;
                    
                    // Update the layer rotation
                    updateLayerRotation(layerIndex, effectiveRotation);
                });
            });
            
            // Add event listeners for opacity sliders
            stackControlContent.querySelectorAll('.opacity-slider').forEach(slider => {
                slider.addEventListener('input', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const layerIndex = parseInt(this.dataset.layer);
                    const opacity = this.value;
                    // Update the displayed value
                    const valueDisplay = this.parentElement.querySelector('.opacity-value');
                    if (valueDisplay) {
                        valueDisplay.textContent = `${opacity}0%`;
                    }
                    updateLayerOpacity(layerIndex, opacity);
                });
            });
            
            // Add event listeners for SVG weight controls
            stackControlContent.querySelectorAll('input[type="radio"][name^="weight-"]').forEach(radio => {
                radio.addEventListener('change', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const layerIndex = parseInt(this.id.split('-')[2]);
                    const weight = this.value;
                    updateSvgWeight(layerIndex, weight);
                });
            });
            
            // Add event listeners for SVG no-fill checkbox
            stackControlContent.querySelectorAll('.svg-nofill-check').forEach(checkbox => {
                checkbox.addEventListener('change', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const layerIndex = parseInt(this.dataset.layer);
                    const noFill = this.checked;
                    updateSvgFill(layerIndex, noFill);
                });
            });
            
            // Add event listeners for color buttons
            stackControlContent.querySelectorAll('.color-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const layerIndex = parseInt(this.dataset.layer);
                    const color = this.dataset.color;
                    
                    // Remove active class from all color buttons in this group
                    const colorButtons = this.parentElement.querySelectorAll('.color-btn');
                    colorButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to the clicked button
                    this.classList.add('active');
                    
                    // Update the layer color
                    updateLayerColor(layerIndex, color);
                });
            });
        }
        
        // Function to update layer size
        function updateLayerSize(layerIndex, size) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                const layer = iconLayers[layerIndex];
                const iconElement = layer.querySelector('svg, i');
                
                if (iconElement) {
                    // Remove existing size classes
                    iconElement.classList.remove('stack-1x', 'stack-2x', 'stack-3x');
                    
                    // Add the new size class
                    iconElement.classList.add(`stack-${size}`);
                }
            }
        }
        
        // Function to update layer rotation
        function updateLayerRotation(layerIndex, rotation) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                const layer = iconLayers[layerIndex];
                const iconElement = layer.querySelector('svg, i');
                
                if (iconElement) {
                    // Remove existing rotation classes
                    for (let i = 0; i <= 360; i += 45) { // Include 360 in the removal list
                        iconElement.classList.remove(`rotate-${i}`);
                    }
                    
                    // Add the new rotation class if not 0 or 360 (both mean no rotation)
                    if (rotation !== '0' && rotation !== '360') {
                        iconElement.classList.add(`rotate-${rotation}`);
                    }
                    
                    // Update the preview in the control panel
                    const previewContainer = stackControlContent.querySelector(`.stack-layers[data-layer-index="${layerIndex}"] .stack-icon-preview`);
                    if (previewContainer) {
                        const previewIcon = previewContainer.querySelector('svg, i');
                        if (previewIcon) {
                            // Remove existing rotation classes from preview
                            for (let i = 0; i <= 360; i += 45) { // Include 360 in the removal list
                                previewIcon.classList.remove(`rotate-${i}`);
                            }
                            
                            // Add the new rotation class to preview if not 0 or 360
                            if (rotation !== '0' && rotation !== '360') {
                                previewIcon.classList.add(`rotate-${rotation}`);
                            }
                        }
                    }
                }
            }
        }
        
        // Function to update layer opacity
        function updateLayerOpacity(layerIndex, opacity) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                const layer = iconLayers[layerIndex];
                const iconElement = layer.querySelector('svg, i');
                
                if (iconElement) {
                    // Remove existing opacity classes
                    for (let i = 1; i <= 10; i++) {
                        iconElement.classList.remove(`alpha-${i}`);
                    }
                    
                    // Add the new opacity class if not 10 (100%)
                    if (opacity !== '10') {
                        iconElement.classList.add(`alpha-${opacity}`);
                    }
                    
                    // Update the preview in the control panel
                    const previewContainer = stackControlContent.querySelector(`.stack-layers[data-layer-index="${layerIndex}"] .stack-icon-preview`);
                    if (previewContainer) {
                        const previewIcon = previewContainer.querySelector('svg, i');
                        if (previewIcon) {
                            // Remove existing opacity classes from preview
                            for (let i = 1; i <= 10; i++) {
                                previewIcon.classList.remove(`alpha-${i}`);
                            }
                            
                            // Add the new opacity class to preview if not 10 (100%)
                            if (opacity !== '10') {
                                previewIcon.classList.add(`alpha-${opacity}`);
                            }
                        }
                    }
                }
            }
        }
        
        // Function to update layer color
        function updateLayerColor(layerIndex, color) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                const layer = iconLayers[layerIndex];
                const isSvg = layer.innerHTML.includes('<svg');
                
                if (isSvg) {
                    const svgElement = layer.querySelector('svg');
                    if (svgElement) {
                        // Remove existing color classes
                        const colorClasses = ['primary', 'secondary', 'success', 'danger', 
                                            'warning', 'info', 'dark', 'light'].map(c => `sa-icon-${c}`);
                        colorClasses.forEach(cls => {
                            svgElement.classList.remove(cls);
                        });
                        
                        // Add the new color class if specified
                        if (color) {
                            svgElement.classList.add(`sa-icon-${color}`);
                        }
                        
                        // Update the preview in the control panel
                        const previewContainer = stackControlContent.querySelector(`.stack-layers[data-layer-index="${layerIndex}"] .stack-icon-preview`);
                        if (previewContainer) {
                            const previewSvg = previewContainer.querySelector('svg');
                            if (previewSvg) {
                                // Remove existing color classes from preview
                                colorClasses.forEach(cls => {
                                    previewSvg.classList.remove(cls);
                                });
                                
                                // Add the new color class to preview if specified
                                if (color) {
                                    previewSvg.classList.add(`sa-icon-${color}`);
                                }
                            }
                        }
                    }
                } else {
                    const iconElement = layer.querySelector('i');
                    if (iconElement) {
                        // Remove existing color classes
                        const colorClasses = ['text-primary', 'text-secondary', 'text-success', 'text-danger', 
                                            'text-warning', 'text-info', 'text-dark', 'text-light'];
                        colorClasses.forEach(cls => {
                            iconElement.classList.remove(cls);
                        });
                        
                        // Add the new color class if specified
                        if (color) {
                            iconElement.classList.add(`text-${color}`);
                        }
                        
                        // Update the preview in the control panel
                        const previewContainer = stackControlContent.querySelector(`.stack-layers[data-layer-index="${layerIndex}"] .stack-icon-preview`);
                        if (previewContainer) {
                            const previewIcon = previewContainer.querySelector('i');
                            if (previewIcon) {
                                // Remove existing color classes from preview
                                colorClasses.forEach(cls => {
                                    previewIcon.classList.remove(cls);
                                });
                                
                                // Add the new color class to preview if specified
                                if (color) {
                                    previewIcon.classList.add(`text-${color}`);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Function to update SVG weight
        function updateSvgWeight(layerIndex, weight) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                const layer = iconLayers[layerIndex];
                const svgElement = layer.querySelector('svg');
                
                if (svgElement) {
                    // Remove existing weight classes
                    svgElement.classList.remove('sa-thin', 'sa-regular', 'sa-medium', 'sa-bold');
                    
                    // Add the new weight class
                    if (weight) {
                        svgElement.classList.add(weight);
                    }
                    
                    // Update the preview in the control panel
                    const previewContainer = stackControlContent.querySelector(`.stack-layers[data-layer-index="${layerIndex}"] .stack-icon-preview`);
                    if (previewContainer) {
                        const previewSvg = previewContainer.querySelector('svg');
                        if (previewSvg) {
                            // Remove existing weight classes from preview
                            previewSvg.classList.remove('sa-thin', 'sa-regular', 'sa-medium', 'sa-bold');
                            
                            // Add the new weight class to preview
                            if (weight) {
                                previewSvg.classList.add(weight);
                            }
                        }
                    }
                }
            }
        }
        
        // Function to update SVG fill
        function updateSvgFill(layerIndex, noFill) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                const layer = iconLayers[layerIndex];
                const svgElement = layer.querySelector('svg');
                
                if (svgElement) {
                    // Toggle the no-fill class
                    if (noFill) {
                        svgElement.classList.add('sa-nofill');
                    } else {
                        svgElement.classList.remove('sa-nofill');
                    }
                    
                    // Update the preview in the control panel
                    const previewContainer = stackControlContent.querySelector(`.stack-layers[data-layer-index="${layerIndex}"] .stack-icon-preview`);
                    if (previewContainer) {
                        const previewSvg = previewContainer.querySelector('svg');
                        if (previewSvg) {
                            // Toggle the no-fill class on preview
                            if (noFill) {
                                previewSvg.classList.add('sa-nofill');
                            } else {
                                previewSvg.classList.remove('sa-nofill');
                            }
                        }
                    }
                }
            }
        }
        
        // Initialize by updating the stack control panel
        updateStackControlPanel();
        
        // Handle select button click in modal
        selectButton.addEventListener('click', function() {
            console.log('Select button clicked, selectedIcon:', selectedIcon); // Debug log
            
            if (!selectedIcon) {
                // No icon selected
                showToast('Please select an icon first', 'warning');
                return;
            }
            
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            
            if (editingLayerIndex !== null) {
                // Editing existing layer
                if (editingLayerIndex >= 0 && editingLayerIndex < iconLayers.length) {
                    const existingLayer = iconLayers[editingLayerIndex];
                    const existingIcon = existingLayer.querySelector('svg, i');
                    
                    // Store all existing settings
                    const existingSettings = {
                        size: existingIcon ? 
                            (existingIcon.classList.contains('stack-1x') ? 'stack-1x' : 
                             existingIcon.classList.contains('stack-2x') ? 'stack-2x' : 
                             existingIcon.classList.contains('stack-3x') ? 'stack-3x' : 'stack-2x') : 
                            'stack-2x',
                        rotation: existingIcon ? 
                            (existingIcon.classList.contains('rotate-45') ? 'rotate-45' :
                             existingIcon.classList.contains('rotate-90') ? 'rotate-90' :
                             existingIcon.classList.contains('rotate-135') ? 'rotate-135' :
                             existingIcon.classList.contains('rotate-180') ? 'rotate-180' :
                             existingIcon.classList.contains('rotate-225') ? 'rotate-225' :
                             existingIcon.classList.contains('rotate-270') ? 'rotate-270' :
                             existingIcon.classList.contains('rotate-315') ? 'rotate-315' : '') : '',
                        opacity: existingIcon ? 
                            (existingIcon.classList.contains('alpha-1') ? 'alpha-1' :
                             existingIcon.classList.contains('alpha-2') ? 'alpha-2' :
                             existingIcon.classList.contains('alpha-3') ? 'alpha-3' :
                             existingIcon.classList.contains('alpha-4') ? 'alpha-4' :
                             existingIcon.classList.contains('alpha-5') ? 'alpha-5' :
                             existingIcon.classList.contains('alpha-6') ? 'alpha-6' :
                             existingIcon.classList.contains('alpha-7') ? 'alpha-7' :
                             existingIcon.classList.contains('alpha-8') ? 'alpha-8' :
                             existingIcon.classList.contains('alpha-9') ? 'alpha-9' : '') : '',
                        color: existingIcon ? 
                            (existingIcon.classList.contains('text-primary') ? 'text-primary' :
                             existingIcon.classList.contains('text-secondary') ? 'text-secondary' :
                             existingIcon.classList.contains('text-success') ? 'text-success' :
                             existingIcon.classList.contains('text-danger') ? 'text-danger' :
                             existingIcon.classList.contains('text-warning') ? 'text-warning' :
                             existingIcon.classList.contains('text-info') ? 'text-info' :
                             existingIcon.classList.contains('text-dark') ? 'text-dark' :
                             existingIcon.classList.contains('text-light') ? 'text-light' : '') : '',
                        svgWeight: existingIcon && existingIcon.tagName === 'svg' ? 
                            (existingIcon.classList.contains('sa-thin') ? 'sa-thin' :
                             existingIcon.classList.contains('sa-regular') ? 'sa-regular' :
                             existingIcon.classList.contains('sa-medium') ? 'sa-medium' :
                             existingIcon.classList.contains('sa-bold') ? 'sa-bold' : '') : '',
                        svgColor: existingIcon && existingIcon.tagName === 'svg' ?
                            (existingIcon.classList.contains('sa-icon-primary') ? 'sa-icon-primary' :
                             existingIcon.classList.contains('sa-icon-secondary') ? 'sa-icon-secondary' :
                             existingIcon.classList.contains('sa-icon-success') ? 'sa-icon-success' :
                             existingIcon.classList.contains('sa-icon-danger') ? 'sa-icon-danger' :
                             existingIcon.classList.contains('sa-icon-warning') ? 'sa-icon-warning' :
                             existingIcon.classList.contains('sa-icon-info') ? 'sa-icon-info' :
                             existingIcon.classList.contains('sa-icon-dark') ? 'sa-icon-dark' :
                             existingIcon.classList.contains('sa-icon-light') ? 'sa-icon-light' : '') : '',
                        noFill: existingIcon && existingIcon.tagName === 'svg' ? 
                            existingIcon.classList.contains('sa-nofill') : false
                    };
                    
                    // Create temporary container to parse the new icon
                    const temp = document.createElement('div');
                    temp.innerHTML = selectedIcon;
                    const newIcon = temp.querySelector('svg, i');
                    
                    // Apply all previous settings to the "edited" new icon
                    if (newIcon) {
                        // Remove all existing classes that we'll reapply
                        newIcon.classList.remove('stack-1x', 'stack-2x', 'stack-3x');
                        newIcon.classList.remove('rotate-45', 'rotate-90', 'rotate-135', 'rotate-180', 'rotate-225', 'rotate-270', 'rotate-315');
                        newIcon.classList.remove('alpha-1', 'alpha-2', 'alpha-3', 'alpha-4', 'alpha-5', 'alpha-6', 'alpha-7', 'alpha-8', 'alpha-9');
                        newIcon.classList.remove('text-primary', 'text-secondary', 'text-success', 'text-danger', 'text-warning', 'text-info', 'text-dark', 'text-light');
                        newIcon.classList.remove('sa-icon-primary', 'sa-icon-secondary', 'sa-icon-success', 'sa-icon-danger', 'sa-icon-warning', 'sa-icon-info', 'sa-icon-dark', 'sa-icon-light');
                        newIcon.classList.remove('sa-thin', 'sa-regular', 'sa-medium', 'sa-bold', 'sa-nofill');
                        
                        // Remove all sa-* classes from font icons (not the icons)
                        if (newIcon.tagName.toLowerCase() !== 'svg') {
                            const regex = /^(sa-(thin|regular|medium|bold|nofill))$/;
                            Array.from(newIcon.classList).forEach(cls => {
                                if (regex.test(cls)) {
                                    newIcon.classList.remove(cls);
                                }
                            });
                        }
                        
                        // Reapply all settings based on icon type
                        newIcon.classList.add(existingSettings.size);
                        if (existingSettings.rotation) newIcon.classList.add(existingSettings.rotation);
                        if (existingSettings.opacity) newIcon.classList.add(existingSettings.opacity);
                        
                        // Handle color classes based on icon type
                        if (newIcon.tagName.toLowerCase() === 'svg') {
                            // For SVG icons, apply SVG-specific classes
                            if (existingSettings.svgWeight) newIcon.classList.add(existingSettings.svgWeight);
                            if (existingSettings.svgColor) newIcon.classList.add(existingSettings.svgColor);
                            if (existingSettings.noFill) newIcon.classList.add('sa-nofill');
                            
                            // If we have a font color but no SVG color, map it to SVG color
                            if (!existingSettings.svgColor && existingSettings.color) {
                                const colorMap = {
                                    'text-primary': 'sa-icon-primary',
                                    'text-secondary': 'sa-icon-secondary',
                                    'text-success': 'sa-icon-success',
                                    'text-danger': 'sa-icon-danger',
                                    'text-warning': 'sa-icon-warning',
                                    'text-info': 'sa-icon-info',
                                    'text-dark': 'sa-icon-dark',
                                    'text-light': 'sa-icon-light'
                                };
                                if (colorMap[existingSettings.color]) {
                                    newIcon.classList.add(colorMap[existingSettings.color]);
                                }
                            }
                        } else {
                            // For font icons, apply font-specific classes
                            if (existingSettings.color) newIcon.classList.add(existingSettings.color);
                            
                            // If we have an SVG color but no font color, map it to font color
                            if (!existingSettings.color && existingSettings.svgColor) {
                                const colorMap = {
                                    'sa-icon-primary': 'text-primary',
                                    'sa-icon-secondary': 'text-secondary',
                                    'sa-icon-success': 'text-success',
                                    'sa-icon-danger': 'text-danger',
                                    'sa-icon-warning': 'text-warning',
                                    'sa-icon-info': 'text-info',
                                    'sa-icon-dark': 'text-dark',
                                    'sa-icon-light': 'text-light'
                                };
                                if (colorMap[existingSettings.svgColor]) {
                                    newIcon.classList.add(colorMap[existingSettings.svgColor]);
                                }
                            }
                        }
                    }
                    
                    iconLayers[editingLayerIndex].innerHTML = temp.innerHTML;
                }
                editingLayerIndex = null;
            } else {
                // Adding new layer
                if (iconLayers.length >= MAX_LAYERS) {
                    // Show error message if max layers reached
                    showToast(`Maximum of ${MAX_LAYERS} icon layers allowed`, 'danger');
                    return;
                }
                
                // Create new layer
                const newLayer = document.createElement('div');
                newLayer.className = 'icon-layers';
                
                // Create temporary container to parse the new icon
                const temp = document.createElement('div');
                temp.innerHTML = selectedIcon;
                const newIcon = temp.querySelector('svg, i');
                
                // Set default medium size for new icons
                if (newIcon) {
                    newIcon.classList.remove('stack-1x', 'stack-2x', 'stack-3x');
                    newIcon.classList.add('stack-2x');
                }
                
                newLayer.innerHTML = temp.innerHTML;
                iconContainer.appendChild(newLayer);
            }
            
            // Update the stack control panel
            updateStackControlPanel();
            
            // Update save button state
            updateSaveButtonState();
            
            // Reset selection
            selectedIcon = null;
            document.querySelectorAll('#iconList li a.js-showcase-icon').forEach(i => {
                i.classList.remove('selected-icon');
            });
            
            // Disable the select button again
            selectButton.disabled = true;
            selectButton.classList.add('disabled');
            
            // Reset modal title back to default
            const modalTitle = modal.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = 'Select an Icon';
            }
            
            // Close the modal
            const modalInstance = bootstrap.Modal.getInstance(modal);
            modalInstance.hide();
        });
        
        // Function to edit a layer
        function editLayer(layerIndex) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                editingLayerIndex = layerIndex;
                
                // Reset selection state before opening modal
                selectedIcon = null;
                document.querySelectorAll('#iconList li a.js-showcase-icon').forEach(i => {
                    i.classList.remove('selected-icon');
                });
                
                // Disable the select button initially
                if (selectButton) {
                    selectButton.disabled = true;
                    selectButton.classList.add('disabled');
                }
                
                // Update modal title to indicate editing mode
                const modalTitle = modal.querySelector('.modal-title');
                if (modalTitle) {
                    modalTitle.textContent = `Editing Icon for Layer ${layerIndex + 1}`;
                }
                
                // Open the modal
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            }
        }
        
        // Function to delete a layer
        function deleteLayer(layerIndex) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (layerIndex >= 0 && layerIndex < iconLayers.length) {
                // Show confirmation dialog
                if (confirm('Are you sure you want to delete this icon layer?')) {
                    // Remove the layer
                    iconLayers[layerIndex].remove();
                    
                    // Update the stack control panel
                    updateStackControlPanel();
                    
                    // Update save button state
                    updateSaveButtonState();
                }
            }
        }
        
        // Add layer button functionality
        document.getElementById('add-layer').addEventListener('click', function(event) {
            const iconLayers = iconContainer.querySelectorAll('.icon-layers');
            if (iconLayers.length >= MAX_LAYERS) {
                showToast(`Maximum of ${MAX_LAYERS} icon layers allowed`, 'danger');
                
                // Prevent modal from opening
                event.stopPropagation();
                return false;
            }
            
            // Reset editing state
            editingLayerIndex = null;
            selectedIcon = null;
            
            // Reset modal title to default
            const modalTitle = modal.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.textContent = 'Select an Icon';
            }
            
            // Ensure select button is disabled when modal opens
            if (selectButton) {
                selectButton.disabled = true;
                selectButton.classList.add('disabled');
            }
        });
        
        // Reset layers button functionality
        document.getElementById('reset-layers').addEventListener('click', function() {
            if (confirm('Are you sure you want to reset all icon layers?')) {
                // Clear all layers
                iconContainer.innerHTML = '';
                
                // Update the stack control panel
                updateStackControlPanel();
                
                // Update save button state
                updateSaveButtonState();
                
                showToast('All icon layers have been reset', 'primary');
            }
        });
    }
    
    // Custom toast function for notifications
    // function showToast(message, type = 'primary') {
    //     // Check if toast container exists, create if not
    //     let toastContainer = document.querySelector('.toast-container');
    //     if (!toastContainer) {
    //         toastContainer = document.createElement('div');
    //         toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
    //         document.body.appendChild(toastContainer);
    //     }
        
    //     // Create toast element
    //     const toastId = 'toast-' + Date.now();
    //     const toast = document.createElement('div');
    //     toast.className = `toast bg-${type} text-white`;
    //     toast.id = toastId;
    //     toast.setAttribute('role', 'alert');
    //     toast.setAttribute('aria-live', 'assertive');
    //     toast.setAttribute('aria-atomic', 'true');
        
    //     toast.innerHTML = `
    //     <div class="d-flex">
    //         <div class="toast-body d-flex align-items-center justify-content-center">
    //             ${message}
    //             <button type="button" class="btn btn-system ms-auto" data-bs-dismiss="toast" aria-label="Close">
    //                 <svg class="sa-icon">
    //                     <use href="icons/sprite.svg#x"></use>
    //                 </svg>
    //             </button>
    //         </div>
    //     </div>
    //     `;
        
    //     toastContainer.appendChild(toast);
        
    //     // Initialize and show the toast
    //     currentToast = new bootstrap.Toast(toast, {
    //         autohide: true,
    //         delay: 3000
    //     });
        
    //     // Remove toast after it's hidden
    //     toast.addEventListener('hidden.bs.toast', function() {
    //         currentToast = null;
    //         toast.remove();
    //     });
        
    //     currentToast.show();
    // }
    
    // Add CSS for icon selection
    const iconSelectionStyle = document.createElement('style');
    iconSelectionStyle.textContent = `
        .js-showcase-icon.selected-icon {
            border: 2px solid #2196F3;
            box-shadow: 0 0 0 4px rgba(33, 150, 243, 0.3);
            transform: scale(1.05);
        }
        .layer-actions {
            display: flex;
            gap: 4px;
        }
        .stack-icon-preview {
            min-width: 50px;
            min-height: 50px;
        }
        
        /* Drag and drop styles */
        .sortable-ghost {
            opacity: 0.4;
            background-color: #f8f9fa;
        }
        .sortable-chosen {
            background-color: #f0f0f0;
        }
        .sortable-drag {
            opacity: 0.8;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        .drag-handle {
            cursor: move;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        
        /* Fine-tuning controls styles */
        .color-btn {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            position: relative;
        }
        .color-btn.active::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            text-shadow: 0 0 2px rgba(0,0,0,0.5);
        }
        .color-btn[data-color="light"].active::after {
            color: #333;
        }
        
        
        /* Prevent collapse when clicking inside controls */
        .collapse.show {
            pointer-events: auto;
        }
        
        .collapse.show .mt-2.mb-2 {
            pointer-events: auto;
        }
        
        /* Make sure the tune button still works */
        .tune-layer {
            z-index: 2;
        }
        
        /* Prevent text selection when clicking controls */
        .control-panel-content {
            user-select: text;
            pointer-events: auto !important;
        }
        
        /* Ensure labels don't collapse panels */
        .form-label, .form-check-label {
            pointer-events: auto;
        }
        
        /* Ensure buttons in control panel don't collapse */
        .control-panel-content button,
        .control-panel-content input,
        .control-panel-content label {
            pointer-events: auto;
        }
        
        /* Additional fixes for collapse issue */
        .layer-control-panel {
            position: relative;
            z-index: 10;
        }
        
        .layer-control-panel .form-range,
        .layer-control-panel .btn-group,
        .layer-control-panel .form-check,
        .layer-control-panel .d-flex {
            pointer-events: auto !important;
            position: relative;
            z-index: 11;
        }
    `;
    document.head.appendChild(iconSelectionStyle);
    
    // Initialize icon stack management when DOM is loaded
    initIconStackManagement();
    
    // Add event listener for copy icon button
    const copyIconBtn = document.getElementById('copy-icon');
    if (copyIconBtn) {
        copyIconBtn.addEventListener('click', function() {
            copyIconToClipboard();
        });
    }
    
    function copyIconToClipboard() {
        const myIcon = document.getElementById('my-icon');
        if (!myIcon) {
            showToast('No icon found to copy', 'danger');
            return;
        }
        
        // Get all icon-layers
        const iconLayers = myIcon.querySelectorAll('.icon-layers');
        
        // Check if there are at least 2 layers
        if (iconLayers.length < 2) {
            showToast('Incomplete icon. A minimum of 2 layers is required.', 'warning');
            return;
        }
        
        // Start with the outer stack-icon wrapper
        let iconHTML = '<div class="stack-icon">';
        
        // Process each layer
        iconLayers.forEach(layer => {
            // Add the icon-layers wrapper
            iconHTML += layer.outerHTML;
        });
        
        // Close the stack-icon wrapper
        iconHTML += '</div>';
        
        // Copy to clipboard using modern Clipboard API
        navigator.clipboard.writeText(iconHTML)
            .then(() => showToast('Icon copied to clipboard!', 'success'))
            .catch(() => showToast('Failed to copy icon', 'danger'));
    }
});//for mobile view recommended to destryo dragging and change it to stack using grid...

// IndexedDB setup and management
const DB_NAME = 'IconStackDB';
const STORE_NAME = 'savedIcons';
const DB_VERSION = 1;

// Initialize IndexedDB
function initDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            db = request.result;
            resolve(db);
        };

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                store.createIndex('name', 'name', { unique: true });
                store.createIndex('createdAt', 'createdAt', { unique: false });
            }
        };
    });
}

// Function to check if an icon already exists
async function checkDuplicateIcon(iconHTML) {
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const store = transaction.objectStore(STORE_NAME);
    const request = store.getAll();

    return new Promise((resolve, reject) => {
        request.onsuccess = () => {
            const icons = request.result;
            const isDuplicate = icons.some(icon => {
                // Compare the HTML structure and classes
                return icon.html === iconHTML;
            });
            resolve(isDuplicate);
        };
        request.onerror = () => reject(request.error);
    });
}

// Function to check if a name already exists
async function checkDuplicateName(name) {
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const store = transaction.objectStore(STORE_NAME);
    const nameIndex = store.index('name');
    const request = nameIndex.get(name);

    return new Promise((resolve, reject) => {
        request.onsuccess = () => {
            resolve(request.result !== undefined);
        };
        request.onerror = () => reject(request.error);
    });
}

// Function to save icon to IndexedDB
async function saveIconToDB(name, iconHTML) {
    const transaction = db.transaction([STORE_NAME], 'readwrite');
    const store = transaction.objectStore(STORE_NAME);
    
    const iconData = {
        name: name,
        html: iconHTML,
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString()
    };

    return new Promise((resolve, reject) => {
        const request = store.add(iconData);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Create and show the naming modal
function createNamingModal() {
    const modalHTML = `
        <div class="modal fade" id="iconNamingModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Save Icon</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="iconName" class="form-label">Icon Name (no spaces, max 15 characters)</label>
                            <input type="text" class="form-control" id="iconName" maxlength="15" 
                                pattern="[A-Za-z0-9_\\-]+" placeholder="Enter a unique name for your icon">
                            <div class="invalid-feedback">Name must not contain spaces or special characters (except - and _), and must be unique.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="confirmSave" disabled>Save</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to body if it doesn't exist
    if (!document.getElementById('iconNamingModal')) {
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    return new bootstrap.Modal(document.getElementById('iconNamingModal'));
}

// Function to handle icon saving
async function handleIconSave() {
    const myIcon = document.getElementById('my-icon');
    if (!myIcon) {
        showToast('No icon found to save', 'danger');
        return;
    }

    // Get all icon layers
    const iconLayers = myIcon.querySelectorAll('.icon-layers');
    
    // Check if there are at least 2 layers
    if (iconLayers.length < 2) {
        showToast('Incomplete icon. A minimum of 2 layers is required.', 'warning');
        return;
    }

    // Get the icon HTML
    const iconHTML = myIcon.innerHTML;

    // Check for duplicate icon
    const isDuplicate = await checkDuplicateIcon(iconHTML);
    if (isDuplicate) {
        showToast('This icon already exists in your saved icons', 'warning');
        return;
    }

    // Create and show the naming modal
    const namingModal = createNamingModal();
    const modalElement = document.getElementById('iconNamingModal');
    const nameInput = modalElement.querySelector('#iconName');
    const confirmButton = modalElement.querySelector('#confirmSave');
    const invalidFeedback = modalElement.querySelector('.invalid-feedback');

    // Reset form state
    nameInput.value = '';
    nameInput.classList.remove('is-invalid');
    invalidFeedback.style.display = 'none';
    confirmButton.disabled = true;

    // Function to sanitize input
    function sanitizeInput(input) {
        // Replace spaces and remove special characters except - and _
        return input.replace(/\s+/g, '')
                    .replace(/[^\w\-]/g, '')
                    .substring(0, 15); // Limit to 15 characters
    }

    // Handle name validation on input
    nameInput.addEventListener('input', async function() {
        // Sanitize input in real-time
        const sanitizedValue = sanitizeInput(this.value);
        if (this.value !== sanitizedValue) {
            this.value = sanitizedValue;
        }
        
        // Validate the sanitized input
        const name = sanitizedValue.trim();
        
        // Check if name is empty or just contains invalid characters
        if (!name) {
            this.classList.add('is-invalid');
            invalidFeedback.style.display = 'block';
            confirmButton.disabled = true;
            return;
        }
        
        // Check for uniqueness
        const nameExists = await checkDuplicateName(name);
        if (nameExists) {
            this.classList.add('is-invalid');
            invalidFeedback.textContent = 'This name is already taken.';
            invalidFeedback.style.display = 'block';
            confirmButton.disabled = true;
        } else if (!this.checkValidity()) {
            this.classList.add('is-invalid');
            invalidFeedback.textContent = 'Name must not contain spaces or special characters (except - and _).';
            invalidFeedback.style.display = 'block';
            confirmButton.disabled = true;
        } else {
            this.classList.remove('is-invalid');
            invalidFeedback.style.display = 'none';
            confirmButton.disabled = false;
        }
    });

    // Handle save confirmation
    confirmButton.addEventListener('click', async function() {
        const name = nameInput.value.trim();
        if (!name || !nameInput.checkValidity()) return;

        try {
            // Final sanitization before saving
            const sanitizedName = sanitizeInput(name);
            await saveIconToDB(sanitizedName, iconHTML);
            showToast('Icon saved successfully!', 'success');
            namingModal.hide();
        } catch (error) {
            console.error('Error saving icon:', error);
            showToast('Failed to save icon', 'danger');
        }
    });

    namingModal.show();
}

// Function to retrieve all saved icons
async function getAllSavedIcons() {
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const store = transaction.objectStore(STORE_NAME);
    const request = store.getAll();

    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Function to retrieve icon by name
async function getIconByName(name) {
    const transaction = db.transaction([STORE_NAME], 'readonly');
    const store = transaction.objectStore(STORE_NAME);
    const nameIndex = store.index('name');
    const request = nameIndex.get(name);

    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Add function to update save button state based on layer count
function updateSaveButtonState() {
    const saveButton = document.getElementById('save-icon');
    const copyButton = document.getElementById('copy-icon');
    
    const myIcon = document.getElementById('my-icon');
    if (!myIcon) {
        if (saveButton) saveButton.disabled = true;
        if (copyButton) copyButton.disabled = true;
        return;
    }
    
    const iconLayers = myIcon.querySelectorAll('.icon-layers');
    const shouldDisable = iconLayers.length < 2; // Disable if fewer than 2 layers
    
    if (saveButton) saveButton.disabled = shouldDisable;
    if (copyButton) copyButton.disabled = shouldDisable;
}

// Initialize IndexedDB when the page loads
document.addEventListener('DOMContentLoaded', async function() {
    try {
        await initDB();
        console.log('IndexedDB initialized successfully');
        // Initialize save button state
        updateSaveButtonState();
    } catch (error) {
        console.error('Error initializing IndexedDB:', error);
    }
});

// Add event listener for save button
document.getElementById('save-icon').addEventListener('click', handleIconSave);

