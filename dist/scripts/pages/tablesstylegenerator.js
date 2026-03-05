/**
 * Table Style Generator
 * Handles real-time style updates for Bootstrap tables
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const tableStyleForm = document.getElementById('tableStyleForm');
    const stylePreviewTable = document.getElementById('stylePreviewTable');
    const generatedClasses = document.getElementById('generatedClasses');
    const codeClasses = document.getElementById('codeClasses');
    const copyStyleBtn = document.getElementById('copyStyleBtn');
    const resetStylesBtn = document.getElementById('resetStylesBtn');
    const showRowClasses = document.getElementById('showRowClasses');
    const collapseColumnsBtn = document.getElementById('collapseColumnsBtn');
    const expandColumnsBtn = document.getElementById('expandColumnsBtn');
    const tableContainer = document.getElementById('tableContainer');
    const headerAccent = document.getElementById('headerAccent');
    const tableHeader = stylePreviewTable.querySelector('thead tr');
    const styleBordered = document.getElementById('styleBordered');
    const styleBorderless = document.getElementById('styleBorderless');

    // Handle conflicts between bordered and borderless
    styleBordered.addEventListener('change', function() {
        if (this.checked && styleBorderless.checked) {
            styleBorderless.checked = false;
        }
        updateTableStyle();
    });

    styleBorderless.addEventListener('change', function() {
        if (this.checked && styleBordered.checked) {
            styleBordered.checked = false;
        }
        updateTableStyle();
    });

    // Table rows with contextual classes
    const tableRows = stylePreviewTable.querySelectorAll('tbody tr');
    const contextualClasses = ['table-primary', 'table-secondary', 'table-success', 'table-danger', 'table-warning', 'table-info'];
    const rowClasses = Array.from(tableRows).map((row, index) => {
        // Assign a specific contextual class based on index
        return contextualClasses[index % contextualClasses.length];
    });

    // Function to update the table style based on form values
    function updateTableStyle() {
        // Start with base class
        const classes = ['table'];
        let tableWrapperClass = '';
        
        // Get theme (table-dark, table-light)
        const theme = document.querySelector('input[name="tableTheme"]:checked').value;
        if (theme) {
            classes.push(theme);
        }
        
        // Get styles (striped, hover, bordered, borderless)
        const styleCheckboxes = document.querySelectorAll('input[name="tableStyle"]:checked');
        styleCheckboxes.forEach(checkbox => {
            if (checkbox.value === 'table-responsive') {
                tableWrapperClass = 'table-responsive';
            } else {
                classes.push(checkbox.value);
            }
        });
        
        // Get size (sm, nano)
        const size = document.querySelector('input[name="tableSize"]:checked').value;
        if (size) {
            classes.push(size);
        }
        
        // Get accent color for the whole table
        const accent = document.getElementById('tableAccent').value;
        if (accent) {
            classes.push(accent);
        }
        
        // Apply header accent using classes only, not inline styles
        const headerAccentValue = headerAccent.value;
        // First remove any existing background classes
        tableHeader.classList.remove(
            'bg-primary', 'bg-secondary', 'bg-success', 
            'bg-danger', 'bg-warning', 'bg-info', 
            'bg-dark', 'bg-light', 'text-white'
        );
        // Add the new one if selected
        if (headerAccentValue) {
            tableHeader.classList.add(headerAccentValue);
            // If we're using a dark background, make the text white
            if (['bg-primary', 'bg-secondary', 'bg-success', 'bg-danger', 'bg-dark'].includes(headerAccentValue)) {
                tableHeader.classList.add('text-white');
            }
        }
        
        // Get caption position (now using radio buttons)
        const captionPosition = document.querySelector('input[name="captionPosition"]:checked').value;
        if (captionPosition) {
            stylePreviewTable.classList.remove('caption-top', 'caption-bottom');
            stylePreviewTable.classList.add(captionPosition);
        } else {
            stylePreviewTable.classList.remove('caption-top', 'caption-bottom');
        }

        // Check if we should show row contextual classes
        if (showRowClasses.checked) {
            tableRows.forEach((row, index) => {
                // First remove any existing contextual classes
                row.classList.remove('table-primary', 'table-secondary', 'table-success', 
                                     'table-danger', 'table-warning', 'table-info');
                // Add the contextual class based on index
                if (index < rowClasses.length) {
                    row.classList.add(rowClasses[index]);
                }
            });
        } else {
            // Remove all contextual classes from rows
            tableRows.forEach(row => {
                row.classList.remove('table-primary', 'table-secondary', 'table-success', 
                                     'table-danger', 'table-warning', 'table-info');
            });
        }
        
        // Update the table classes
        stylePreviewTable.className = classes.join(' ');
        
        // Update wrapper for responsiveness
        if (tableWrapperClass && !tableContainer.classList.contains(tableWrapperClass)) {
            tableContainer.className = tableWrapperClass;
        } else if (!tableWrapperClass) {
            tableContainer.className = '';
        }
        
        // Update the generated classes display and note about additional header styling if applied
        const displayClasses = classes.join(' ');
        let displayText = displayClasses;
        
        // Add note about header styling if applied
        if (headerAccentValue) {
            displayText += `\n\n<!-- Additional header styling -->\n<thead>\n  <tr class="${headerAccentValue}${['bg-primary', 'bg-secondary', 'bg-success', 'bg-danger', 'bg-dark'].includes(headerAccentValue) ? ' text-white' : ''}">\n    <!-- your header cells here -->\n  </tr>\n</thead>`;
        }
        
        // Add note about contextual row classes if enabled
        if (showRowClasses.checked) {
            displayText += `\n\n<!-- Row contextual classes -->\n<tbody>\n  <tr class="table-primary">...</tr>\n  <tr class="table-secondary">...</tr>\n  <tr class="table-success">...</tr>\n  <tr class="table-danger">...</tr>\n  <tr class="table-warning">...</tr>\n  <tr class="table-info">...</tr>\n</tbody>`;
        }
        
        generatedClasses.textContent = displayText;
        codeClasses.textContent = displayClasses;
    }

    // Add event listeners to all form controls
    tableStyleForm.addEventListener('change', updateTableStyle);
    
    // Copy button functionality
    copyStyleBtn.addEventListener('click', function() {
        // Create a textarea element to copy from
        const textarea = document.createElement('textarea');
        textarea.value = generatedClasses.textContent;
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            // Execute copy command
            document.execCommand('copy');
            
            // Show success feedback
            const originalText = copyStyleBtn.innerHTML;
            copyStyleBtn.innerHTML = '<svg class="sa-icon me-1"><use href="icons/sprite.svg#check"/></svg> Copied!';
            
            // Reset button text after delay
            setTimeout(() => {
                copyStyleBtn.innerHTML = originalText;
            }, 2000);
        } catch (err) {
            console.error('Could not copy text: ', err);
        }
        
        // Remove the temporary textarea
        document.body.removeChild(textarea);
    });
    
    // Reset button functionality
    resetStylesBtn.addEventListener('click', function() {
        // Reset form controls
        document.getElementById('themeDefault').checked = true;
        document.getElementById('sizeDefault').checked = true;
        document.getElementById('tableAccent').value = '';
        document.getElementById('headerAccent').value = '';
        document.getElementById('captionDefault').checked = true;
        document.getElementById('showRowClasses').checked = false;
        document.getElementById('expandColumnsBtn').checked = true;
        
        // Reset checkboxes
        const checkboxes = document.querySelectorAll('input[name="tableStyle"]');
        checkboxes.forEach(checkbox => {
            if (checkbox.id === 'styleResponsive') {
                checkbox.checked = true;
            } else {
                checkbox.checked = false;
            }
        });
        
        // Restore columns to default view
        expandColumns();
        
        // Update table style
        updateTableStyle();
    });

    // Function to hide columns
    function collapseColumns() {
        // Hide columns with d-md-table-cell and d-lg-table-cell classes
        const mdCells = document.querySelectorAll('.d-md-table-cell');
        mdCells.forEach(cell => {
            cell.classList.add('d-none');
            cell.classList.remove('d-md-table-cell');
        });
        
        const lgCells = document.querySelectorAll('.d-lg-table-cell');
        lgCells.forEach(cell => {
            cell.classList.add('d-none');
            cell.classList.remove('d-lg-table-cell');
        });
    }
    
    // Function to show all columns
    function expandColumns() {
        // Restore hidden columns
        const hiddenCells = document.querySelectorAll('td.d-none, th.d-none');
        hiddenCells.forEach(cell => {
            if (cell.textContent.includes('Address') || cell.textContent.includes('City') || 
                cell.parentElement.cells[4] === cell || cell.parentElement.cells[5] === cell) {
                cell.classList.remove('d-none');
                cell.classList.add('d-md-table-cell');
            } else if (cell.textContent.includes('State') || cell.textContent.includes('Country') || 
                       cell.parentElement.cells[6] === cell || cell.parentElement.cells[7] === cell) {
                cell.classList.remove('d-none');
                cell.classList.add('d-lg-table-cell');
            }
        });
    }

    // Toggle columns for responsive preview - updated for radio buttons
    collapseColumnsBtn.addEventListener('change', function() {
        if (this.checked) {
            collapseColumns();
        }
    });
    
    expandColumnsBtn.addEventListener('change', function() {
        if (this.checked) {
            expandColumns();
        }
    });

    // Hide row contextual classes by default
    if (!showRowClasses.checked) {
        tableRows.forEach(row => {
            row.classList.remove('table-primary', 'table-secondary', 'table-success', 
                                'table-danger', 'table-warning', 'table-info');
        });
    }

    // Initialize the table with default styles
    updateTableStyle();
});
