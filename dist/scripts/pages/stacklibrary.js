// IndexedDB setup
let db;
const DB_NAME = 'IconStackDB';
const STORE_NAME = 'savedIcons';
const DB_VERSION = 1;

// Toast management
let currentToast = null;

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

// Function to show toast notifications
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

// Function to delete an icon from IndexedDB
async function deleteIcon(iconId) {
    try {
        if (confirm('Are you sure you want to delete this icon?')) {
            const transaction = db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            
            await new Promise((resolve, reject) => {
                const request = store.delete(iconId);
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
            
            showToast('Icon deleted successfully', 'success');
            await populateIconList(); // Refresh the list
        }
    } catch (error) {
        console.error('Error deleting icon:', error);
        showToast('Failed to delete icon', 'danger');
    }
}

// Function to copy icon HTML to clipboard
function copyIconToClipboard(iconHTML) {
    navigator.clipboard.writeText(iconHTML)
        .then(() => showToast('Icon copied to clipboard!', 'success'))
        .catch(() => showToast('Failed to copy icon', 'danger'));
}

// Function to populate the icon list
async function populateIconList() {
    try {
        const icons = await getAllSavedIcons();
        const iconList = document.getElementById('iconList');
        
        // Clear existing content
        iconList.innerHTML = '';
        
        // Sort icons by creation date (newest first)
        icons.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
        
        icons.forEach(icon => {
            const li = document.createElement('li');
            li.className = 'col-4 col-sm-3 col-md-3 col-lg-2 col-xl-2 col-xxl-1 mb-g';
            
            li.innerHTML = `
                <div class="d-flex flex-column align-items-center p-2 m-0 w-100 shadow-hover-2 border rounded position-relative show-child-on-hover overflow-hidden" style="font-size: 4rem;">
                    <div class="show-on-hover-parent bg-secondary bg-opacity-50 position-absolute top-0 start-0 w-100 h-100 z-1">
                        <div class="d-flex flex-row align-items-end justify-content-center h-100 gap-1 pb-2">
                            <button type="button" class="btn btn-xs btn-success copy-btn">
                                COPY
                            </button>
                            <button type="button" class="btn btn-xs btn-danger delete-btn">
                                DEL
                            </button>
                        </div>
                    </div>
                    <div class="pb-1 d-flex icon-container">
                        <div class="stack-icon">
                            ${icon.html}
                        </div>
                    </div>
                    <div class="text-muted fs-nano icon-name">
                        ${icon.name}
                    </div>
                </div>
            `;
            
            iconList.appendChild(li);
            
            // Add event listeners after the element is added to the DOM
            const copyBtn = li.querySelector('.copy-btn');
            const deleteBtn = li.querySelector('.delete-btn');
            const iconContainer = li.querySelector('.icon-container');
            
            // Format icon HTML for copying
            const iconHTML = `<div class="stack-icon">${icon.html}</div>`;
            
            // Add click handlers
            copyBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent event bubbling
                copyIconToClipboard(iconHTML);
            });
            
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent event bubbling
                deleteIcon(icon.id);
            });
            
            // Keep the container click for convenience
            iconContainer.addEventListener('click', () => {
                copyIconToClipboard(iconHTML);
            });
        });
        
        // Show message if no icons found
        if (icons.length === 0) {
            iconList.innerHTML = `
                <div class="col-12 text-center text-muted py-5">
                    <h4>No saved icons found</h4>
                    <p>Create and save some icons using the <a href="stackgenerator.html">Stack Generator</a> to see them here.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading icons:', error);
        showToast('Failed to load icons', 'danger');
    }
}

// Function to filter icons based on search input
function filterIcons() {
    const searchTerm = document.getElementById('searchIcons').value.toLowerCase();
    const icons = document.querySelectorAll('#iconList li');
    
    icons.forEach(icon => {
        const name = icon.querySelector('.icon-name').textContent.toLowerCase();
        const matches = name.includes(searchTerm);
        icon.style.display = matches ? '' : 'none';
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', async function() {
    try {
        await initDB();
        await populateIconList();
        
        // Add search functionality
        const searchInput = document.getElementById('searchIcons');
        searchInput.addEventListener('input', filterIcons);
        
        console.log('Stack Library initialized successfully');
    } catch (error) {
        console.error('Error initializing Stack Library:', error);
        showToast('Failed to initialize Stack Library', 'danger');
    }
});