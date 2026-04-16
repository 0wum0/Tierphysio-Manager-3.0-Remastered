import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';

// Wait for the DOM to load before initializing
document.addEventListener('DOMContentLoaded', () => {
    const importBtn = document.getElementById('importBtn');
    const tableContainer = document.getElementById('tableContainer');
    let table = null;
    let errorContainer = document.getElementById('errorContainer');

    // Initialize the table immediately but keep the container hidden
    table = new SmartTables('myTable', {
        import: true,
        export: true,
        responsive: {
            enabled: true,
            breakpoint: 768
        }
    });

    // Handle import button click
    importBtn.addEventListener('click', () => {

        // Hide the import data container
        const hideImportData = document.getElementById('hide-import-data');
        hideImportData.classList.add('d-none');

        // Show the table container
        tableContainer.classList.remove('d-none');
        
        try {
            // First, create the import modal if it doesn't exist
            if (typeof table.createImportModal === 'function') {
                table.createImportModal();
                
                // Now find the modal and show it using Bootstrap's Modal API
                const importModal = document.getElementById('importModal');
                if (importModal) {
                    // Create a new Bootstrap modal instance and show it
                    const bsModal = new bootstrap.Modal(importModal);
                    bsModal.show();
                    console.log('Import modal opened successfully');
                } else {
                    throw new Error('Import modal element not found after creation');
                }
            } else {
                throw new Error('createImportModal method not found on table instance');
            }
        } catch (error) {
            console.error('Failed to open import modal:', error);
            errorContainer.classList.remove('d-none');
        }
    });
});