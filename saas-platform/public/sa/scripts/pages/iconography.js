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
    const iconSets = {
        'sa': { icons: 'json/sa-icons.json', mappings: 'json/sa-mappings.json', prefix: 'sa' },
        'base': { icons: 'json/sa-base.json', mappings: 'json/sa-mappings.json', prefix: 'sa' },
        'svg': { icons: 'json/sa-svg-icons.json', mappings: 'json/sa-svg-mappings.json', prefix: 'svg' },
        'fal': { icons: 'json/fa-icons.json', mappings: 'json/fa-mappings.json', prefix: 'fal' },
        'fas': { icons: 'json/fa-icons.json', mappings: 'json/fa-mappings.json', prefix: 'fas' },
        'far': { icons: 'json/fa-icons.json', mappings: 'json/fa-mappings.json', prefix: 'far' },
        'fad': { icons: 'json/fa-duotone.json', mappings: 'json/fa-mappings.json', prefix: 'fad' },
        'fab': { icons: 'json/fa-brands.json', mappings: 'json/fa-mappings.json', prefix: 'fab' },
        'material': { icons: 'json/material-icons.json', mappings: 'json/material-mappings.json', prefix: 'material' }
    };

    const selectedSet = iconSets[iconSet] || iconSets['sa'];

    // If first load or switching between different icon families, fetch new data
    if (!allIcons.length || (currentIconSet === 'sa' || currentIconSet === 'svg') !== (iconSet === 'sa' || iconSet === 'svg')) {
        try {
            const [iconsResponse, mappingsResponse] = await Promise.all([
                fetch(selectedSet.icons),
                fetch(selectedSet.mappings)
            ]);
            if (!iconsResponse.ok || !mappingsResponse.ok) throw new Error('Failed to load resources');
            allIcons = (await iconsResponse.json()).map(icon => icon.replace(/^-/, '')); // Strip leading hyphen from all icons
            iconMappings = await mappingsResponse.json();
            currentIconSet = iconSet;
            generateIconList(allIcons, selectedSet.prefix);
            initializeFilter();
        } catch (error) {
            console.error('Error loading icon set:', error);
        }
    } else {
        // Update style for same icon family
        currentIconSet = iconSet;
        document.querySelectorAll('#iconList li').forEach(item => {
            const iconName = item.dataset.iconName.replace(/^-/, ''); // Strip leading hyphen
            const iconElement = item.querySelector('.icon-container');
            iconElement.innerHTML = getIconClass(iconSet, iconName);
        });
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
            <li class="col-4 col-sm-3 col-md-3 col-lg-2 col-xl-2 col-xxl-1 d-flex justify-content-center align-items-center mb-g" data-icon-name="${displayName}">
                <a href="#" class="js-showcase-icon rounded color-fusion-300 p-0 m-0 d-flex flex-column w-100 shadow-hover-2 ${isSvg ? 'has-svg' : ''}">
                    <div class="icon-preview rounded-top w-100 position-relative">
                        <div class="icon-container rounded-top d-flex align-items-center justify-content-center w-100 pt-3 pb-3 pe-2 ps-2 position-absolute">
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
    document.querySelector('.results-count').textContent =
        `Showing ${visibleIcons} of ${allIcons.length} icons`;
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
    document.querySelectorAll('.js-showcase-icon').forEach(iconElement => {
        iconElement.addEventListener('click', function (event) {
            event.preventDefault();
            const iconEl = this.querySelector('svg') || this.querySelector('i');
            if (iconEl) {
                if (iconEl.tagName.toLowerCase() === 'svg') {
                    const useEl = iconEl.querySelector('use');
                    const iconClass = useEl ? useEl.getAttribute('href') : null;
                    if (iconClass) {
                        // Include current weight and fill classes in the copied SVG
                        const weightClass = currentSvgWeight;
                        const fillClass = isNoFill ? ' sa-nofill' : '';
                        const svgMarkup = `<svg class="sa-icon ${weightClass}${fillClass}"><use href="${iconClass}"></use></svg>`;
                        copyToClipboard(svgMarkup);
                        showToast(svgMarkup);
                    }
                } else {
                    const iconClass = iconEl.className;
                    copyToClipboard(iconClass);
                    showToast(iconClass);
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

function showToast(iconClass) {
    if (currentToast) {
        currentToast.hide();
    }

    const toastElement = document.getElementById('liveToast');
    const toastBody = toastElement.querySelector('.toast-body');

    const isSvg = iconClass.includes('sprite.svg#');
    // For SVGs, we'll only show the icon once in the message
    if (isSvg) {
        const iconName = iconClass.split('#')[1].split('"')[0]; // Get "iconname"

        toastBody.innerHTML = `${iconClass}<span class="fw-bold ms-2"><span class="disabled">'#${iconName}'</span>  &#x2714;copied</span>`;
    } else {
        const iconDisplay = `<i class="${escapeHTML(iconClass)} display-1 p-1 m-0 me-2 text-primary"></i>`;
        toastBody.innerHTML = `${iconDisplay}<span class="fw-bold"><span class="disabled">'${escapeHTML(iconClass)}'</span> &#x2714;copied</span>`;
    }


    currentToast = new bootstrap.Toast(toastElement, {
        delay: 2500,
        animation: true,
        autohide: true
    });

    toastElement.addEventListener('hidden.bs.toast', () => {
        currentToast = null;
    }, { once: true });

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
