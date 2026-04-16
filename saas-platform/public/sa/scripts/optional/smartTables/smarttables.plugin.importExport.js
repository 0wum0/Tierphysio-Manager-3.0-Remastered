/* SmartTables Import & Export Table Plugin
   Copyright SmartAdmin WebApp Copyright 2025-2026.

    To use the export functionality now, you'll need to:
    1. Import the plugin in your application
    2. The plugin will automatically register itself with SmartTables
    3. All export methods will be available as before
*/

export const ImportExportPlugin = {
    name: 'importExport',
    
    init() {
        // Store reference to the SmartTables instance
        this.instance = this.instance || {};
        
        // Bind methods to the instance
        this.instance.exportData = this.exportData.bind(this);
        this.instance.getExportData = this.getExportData.bind(this);
        this.instance.exportToExcel = this.exportToExcel.bind(this);
        this.instance.exportToCSV = this.exportToCSV.bind(this);
        this.instance.copyToClipboard = this.copyToClipboard.bind(this);
        this.instance.exportToJSON = this.exportToJSON.bind(this);
        this.instance.exportToPDF = this.exportToPDF.bind(this);
        this.instance.exportToXML = this.exportToXML.bind(this);
        this.instance.exportToHTML = this.exportToHTML.bind(this);
        this.instance.importData = this.importData.bind(this);

        // Create import modal
        this.createImportModal();
        
        // Check if we're being initialized after the toolbar is already set up
        const findExistingToolbar = () => {
            // First check if instance has a toolbar property
            if (this.instance.toolbar) {
                console.log('ImportExportPlugin: Found toolbar in instance');
                return this.instance.toolbar;
            }
            
            // Then check if there's a toolbar element in the DOM
            if (this.instance.wrapper) {
                const toolbarElem = this.instance.wrapper.querySelector('.st-toolbar');
                if (toolbarElem) {
                    console.log('ImportExportPlugin: Found toolbar in DOM');
                    return toolbarElem;
                }
            }
            
            return null;
        };
        
        // Try to find an existing toolbar
        const existingToolbar = findExistingToolbar();
        
        if (existingToolbar) {
            // We found the toolbar, set up the export UI
            console.log('ImportExportPlugin: Using existing toolbar');
            this.setupExportUI();
        } else {
            console.log('ImportExportPlugin: No toolbar found, waiting for one to be created...');
            
            // Set up a MutationObserver to watch for the toolbar
            const observer = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length) {
                        const toolbar = findExistingToolbar();
                        if (toolbar) {
                            console.log('ImportExportPlugin: Toolbar detected, setting up export UI');
                            this.setupExportUI();
                            observer.disconnect();
                            break;
                        }
                    }
                }
            });
            
            // Start observing the wrapper for changes
            if (this.instance.wrapper) {
                observer.observe(this.instance.wrapper, { childList: true, subtree: true });
                
                // Set a timeout as a fallback
                setTimeout(() => {
                    const toolbar = findExistingToolbar();
                    if (toolbar) {
                        console.log('ImportExportPlugin: Found toolbar after delay');
                        this.setupExportUI();
                    } else {
                        console.warn('ImportExportPlugin: Toolbar not found after waiting');
                    }
                    observer.disconnect();
                }, 1000);
            }
        }
    },

    createImportModal() {
        const modalId = 'importModal';
        
        // Check if modal already exists
        if (document.getElementById(modalId)) {
            return;
        }

        // Create modal structure
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = modalId;
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'importModalLabel');
        modal.setAttribute('aria-hidden', 'true');

        const modalDialog = document.createElement('div');
        modalDialog.className = 'modal-dialog modal-dialog-centered';

        const modalContent = document.createElement('div');
        modalContent.className = 'modal-content';

        // Modal header
        const modalHeader = document.createElement('div');
        modalHeader.className = 'modal-header';

        const modalTitle = document.createElement('h5');
        modalTitle.className = 'modal-title';
        modalTitle.id = 'importModalLabel';
        modalTitle.textContent = 'Import Data';

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn btn-system ms-auto';
        closeButton.setAttribute('data-bs-dismiss', 'modal');
        closeButton.setAttribute('aria-label', 'Close');
        closeButton.innerHTML = '<svg class="sa-icon sa-icon-2x"><use href="icons/sprite.svg#x"></use></svg>';

        modalHeader.appendChild(modalTitle);
        modalHeader.appendChild(closeButton);

        // Modal body
        const modalBody = document.createElement('div');
        modalBody.className = 'modal-body';

        // Status message
        const statusMessage = document.createElement('div');
        statusMessage.className = 'alert d-none mb-3';
        modalBody.appendChild(statusMessage);

        // Dropzone
        const dropZone = document.createElement('div');
        dropZone.className = 'st-dropzone p-5 text-center d-flex flex-column align-items-center border border-2 border-dashed rounded bg-faded';
        dropZone.innerHTML = '<svg class="sa-icon sa-icon-success sa-thin sa-icon-5x mb-2"><use href="icons/sprite.svg#upload-cloud"></use></svg><p>Drag and drop your CSV or JSON file here<br>or <b class="text-primary fw-500 cursor-pointer">click to browse</b></p>';

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.className = 'visually-hidden';
        fileInput.accept = '.csv,.json,.xlsx';

        dropZone.appendChild(fileInput);
        modalBody.appendChild(dropZone);

        // Modal footer
        const modalFooter = document.createElement('div');
        modalFooter.className = 'modal-footer';

        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-danger';
        cancelButton.setAttribute('data-bs-dismiss', 'modal');
        cancelButton.textContent = 'Cancel';

        const importButton = document.createElement('button');
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
        this.setupImportEventListeners(modal, dropZone, fileInput, importButton, statusMessage);
    },

    setupImportEventListeners(modal, dropZone, fileInput, importButton, statusMessage) {
        // Store reference to this for use in event handlers
        const self = this;

        dropZone.addEventListener('click', () => {
            fileInput.click();
        });

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('border-primary');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('border-primary');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-primary');

            if (e.dataTransfer.files.length) {
                this.handleFileSelection(e.dataTransfer.files[0], statusMessage, importButton);
            }
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                this.handleFileSelection(fileInput.files[0], statusMessage, importButton);
            }
        });

        // Fix for import button not working
        importButton.addEventListener('click', () => {
            console.log('Import button clicked, fileData:', this.fileData);
            if (this.fileData) {
                // Get a reference to the modal before importing data
                // as importing will potentially rebuild the DOM
                const modalInstance = bootstrap.Modal.getInstance(modal);
                
                try {
                    // Process the data first
                    this.importData(this.fileData);
                    
                    // Then hide the modal
                    if (modalInstance) {
                        console.log('Hiding modal after successful import');
                        modalInstance.hide();
                    } else {
                        console.warn('Could not find modal instance, trying to hide directly');
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        document.body.classList.remove('modal-open');
                        
                        // Remove the modal backdrop if it exists
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.remove();
                        }
                    }
                    
                    // Reset file data after successful import
                    this.fileData = null;
                    importButton.disabled = true;
                } catch (error) {
                    console.error('Error during import:', error);
                    statusMessage.className = 'alert alert-danger mb-3';
                    statusMessage.innerHTML = `<strong>Error:</strong> ${error.message}`;
                }
            } else {
                console.warn('Import button clicked but no fileData available');
                statusMessage.className = 'alert alert-warning mb-3';
                statusMessage.innerHTML = '<strong>Warning:</strong> No data to import. Please select a file first.';
            }
        });

        // Clear status message when modal is opened
        modal.addEventListener('show.bs.modal', () => {
            statusMessage.className = 'alert d-none mb-3';
            statusMessage.textContent = '';
            importButton.disabled = true;
            fileInput.value = '';
            this.fileData = null; // Reset the file data
        });
    },

    handleFileSelection(file, statusMessage, importButton) {
        console.log('Handling file selection:', file.name);
        statusMessage.className = 'alert alert-info mb-3';
        statusMessage.textContent = 'Reading file...';
        importButton.disabled = true;

        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const fileName = file.name.toLowerCase();
                const displayName = file.name.length > 30 ? 
                    file.name.substring(0, 27) + '...' : 
                    file.name;

                if (fileName.endsWith('.json')) {
                    this.fileData = JSON.parse(e.target.result);
                    statusMessage.className = 'alert alert-success mb-3';
                    statusMessage.innerHTML = `JSON file <b>${displayName}</b> loaded successfully. Ready to import.`;
                    importButton.disabled = false;
                    console.log('JSON file loaded successfully:', displayName);
                } else if (fileName.endsWith('.csv')) {
                    this.fileData = this.parseCSV(e.target.result);
                    statusMessage.className = 'alert alert-success mb-3';
                    statusMessage.innerHTML = `CSV file <b>${displayName}</b> loaded successfully. Ready to import.`;
                    importButton.disabled = false;
                    console.log('CSV file loaded successfully:', displayName);
                } else if (fileName.endsWith('.xlsx')) {
                    if (typeof XLSX === 'undefined') {
                        throw new Error('XLSX library is not loaded. Please include SheetJS in your project.');
                    }
                    const workbook = XLSX.read(e.target.result, { type: 'array' });
                    this.fileData = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
                    statusMessage.className = 'alert alert-success mb-3';
                    statusMessage.innerHTML = `Excel file <b>${displayName}</b> loaded successfully. Ready to import.`;
                    importButton.disabled = false;
                    console.log('Excel file loaded successfully:', displayName);
                } else {
                    statusMessage.className = 'alert alert-danger mb-3';
                    statusMessage.innerHTML = `Unsupported file format: <b>${displayName}</b>. Please use CSV, JSON, or Excel.`;
                    console.warn('Unsupported file format:', displayName);
                }
            } catch (error) {
                statusMessage.className = 'alert alert-danger mb-3';
                statusMessage.innerHTML = `Error processing file <b>${file.name}</b>: ${error.message}`;
                console.error('Error processing file:', error);
            }
        };

        if (file.name.endsWith('.xlsx')) {
            reader.readAsArrayBuffer(file);
        } else {
            reader.readAsText(file);
        }
    },

    parseCSV(csvText) {
        // Simple CSV parser - can be enhanced for more complex cases
        const lines = csvText.split('\n');
        const headers = lines[0].split(',').map(h => h.trim());
        const data = [];

        for (let i = 1; i < lines.length; i++) {
            if (!lines[i].trim()) continue;
            const values = lines[i].split(',').map(v => v.trim());
            const row = {};
            headers.forEach((header, index) => {
                row[header] = values[index];
            });
            data.push(row);
        }

        return data;
    },

    importData(data) {
        try {
            console.log('Importing data:', data);
            
            // Set a flag to indicate this is an import operation
            this.instance.isImportOperation = true;

            // Call beforeDataLoad hook
            this.instance.callHook('beforeDataLoad', data);

            // Show loading screen
            if (this.instance.options.loading.enabled) {
                this.instance.wrapper.classList.add('st-loading');

                // Add spinner if it doesn't exist
                if (!this.instance.wrapper.querySelector('.st-loading-spinner')) {
                    const spinner = document.createElement('div');
                    spinner.className = 'st-loading-spinner';
                    this.instance.wrapper.appendChild(spinner);
                }
            }

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
            
            console.log(`Processing ${data.length} rows of imported data`);

            // Auto-detect columns from the new data
            this.instance.options.data.columns = this.detectColumns(data);
            
            // We need to carefully update the table with the new data
            if (this.instance.isTableInitialized) {
                console.log('Table is initialized, will update existing table');
                // Reset the current data arrays
                this.instance.rows = [];
                this.instance.filteredRows = [];
                this.instance.originalRows = [];
                
                // Reset to the first page
                this.instance.currentPage = 1;
            
                // Process the imported data
                this.instance.options.data.serverSide = false; // Treat imported data as local data
                this.instance.processData(data);
            } else {
                console.log('Table is not initialized, will create new table');
                // Create a fresh table element if needed
                if (!this.instance.table) {
                    this.instance.table = document.createElement('table');
                    this.instance.table.className = this.instance.options.classes.table;
                    this.instance.wrapper.appendChild(this.instance.table);
                }
                
                // Process the imported data with a fresh table
                this.instance.options.data.serverSide = false; // Treat imported data as local data
                this.instance.processData(data);
            }
            
            // Ensure the loading spinner is hidden after a delay
            setTimeout(() => {
                if (this.instance.hideLoading) {
                    this.instance.hideLoading();
                } else {
                    this.instance.wrapper.classList.remove('st-loading');
                    const spinner = this.instance.wrapper.querySelector('.st-loading-spinner');
                    if (spinner) spinner.remove();
                }
                
                // Show a success notification
                if (this.instance.showNotification) {
                    this.instance.showNotification(`Successfully imported ${data.length} rows`, 'success');
                }
                
                console.log('Import completed successfully');
            }, 500);

            // Call onImport hook
            this.instance.callHook('onImport', data);
            
            // Return true to indicate success
            return true;
        } catch (error) {
            console.error('Import error:', error);

            // Hide loading screen if there's an error
            if (this.instance.hideLoading) {
                this.instance.hideLoading();
            } else {
                this.instance.wrapper.classList.remove('st-loading');
                const spinner = this.instance.wrapper.querySelector('.st-loading-spinner');
                if (spinner) spinner.remove();
            }

            // Show notification if the method exists
            if (this.instance.showNotification) {
                this.instance.showNotification('Import failed: ' + error.message, 'danger');
            }
            
            // Re-throw the error so the caller knows about it
            throw error;
        }
    },

    detectColumns(data) {
        if (!data || !data.length) {
            return [];
        }

        const firstRow = data[0];
        const columns = [];

        // Create columns from the keys in the first data row
        Object.keys(firstRow).forEach(key => {
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
    },

    exportData(format) {
        const data = this.getExportData();
        
        // Call onExport hook
        this.instance.callHook('onExport', format, data);
        
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
    },

    getExportData() {
        var headers = Array.from(this.instance.table.querySelectorAll('thead th')).map(function (th) {
            return th.textContent.trim();
        });

        var rows = this.instance.filteredRows.map(function (row) {
            return Array.from(row.element.cells).map(function (cell) {
                return cell.textContent.trim();
            });
        });

        return {
            headers: headers,
            rows: rows
        };
    },

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
        this.instance.showNotification('Successfully exported to ' + filename, 'success');
    },

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
        const filename = (this.instance.table.id || 'table-export') + '-' + new Date().toISOString().slice(0, 10) + '.csv';

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
                this.instance.showNotification('Successfully exported to ' + filename, 'success');
            }
        }
    },

    copyToClipboard(data) {
        let textContent = '';

        // Add headers
        textContent += data.headers.join('\t') + '\n';

        // Add rows
        data.rows.forEach(function (row) {
            textContent += row.join('\t') + '\n';
        });

        // Create temporary textarea
        const textarea = document.createElement('textarea');
        textarea.value = textContent;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);

        try {
            textarea.select();
            const success = document.execCommand('copy');
            if (success) {
                this.instance.showNotification('Copied to clipboard!', 'success');
            } else {
                this.instance.showNotification('Copy failed. Please try again.', 'error');
            }
        } catch (err) {
            this.instance.showNotification('Copy failed: ' + err, 'error');
        } finally {
            document.body.removeChild(textarea);
        }
    },

    exportToJSON(data) {
        // Convert the data to a JSON string
        const jsonData = JSON.stringify(data.rows, null, 2);
        
        // Create a Blob with the JSON data
        const blob = new Blob([jsonData], { type: 'application/json' });
        
        // Create a download link and trigger download
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'table-export.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        this.instance.showNotification('Data exported to JSON successfully', 'success');
    },
    
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
                this.instance.showNotification('Data exported to PDF successfully', 'success');
            } else {
                // If html2pdf isn't available, try jsPDF if available
                if (typeof jspdf !== 'undefined' && typeof jspdf.jsPDF === 'function') {
                    const doc = new jspdf.jsPDF();
                    
                    // Simple conversion with limited styling
                    doc.autoTable({ html: table });
                    doc.save('table-export.pdf');
                    
                    this.instance.showNotification('Data exported to PDF successfully', 'success');
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
                    
                    this.instance.showNotification('Please use print dialog to save as PDF', 'info');
                }
            }
            
            // Clean up
            document.body.removeChild(container);
        } catch (error) {
            console.error('Error exporting to PDF:', error);
            this.instance.showNotification('Failed to export to PDF: ' + error.message, 'error');
        }
    },

    exportToXML(data) {
        // Create XML structure
        let xmlContent = '<?xml version="1.0" encoding="UTF-8"?>\n<table>\n';
        
        // Add headers
        xmlContent += '  <headers>\n';
        data.headers.forEach(header => {
            xmlContent += `    <header>${this.escapeXML(header)}</header>\n`;
        });
        xmlContent += '  </headers>\n';
        
        // Add rows
        xmlContent += '  <rows>\n';
        data.rows.forEach(row => {
            xmlContent += '    <row>\n';
            row.forEach((cell, index) => {
                xmlContent += `      <${data.headers[index].toLowerCase().replace(/\s+/g, '_')}>${this.escapeXML(cell)}</${data.headers[index].toLowerCase().replace(/\s+/g, '_')}>\n`;
            });
            xmlContent += '    </row>\n';
        });
        xmlContent += '  </rows>\n</table>';
        
        // Create download link
        const blob = new Blob([xmlContent], { type: 'application/xml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'table-export.xml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        this.instance.showNotification('Data exported to XML successfully', 'success');
    },

    exportToHTML(data) {
        // Create styled HTML table
        let htmlContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Table Export</title>
                <style>
                    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    tr:nth-child(even) { background-color: #f9f9f9; }
                    tr:hover { background-color: #f5f5f5; }
                </style>
            </head>
            <body>
                <table>
                    <thead>
                        <tr>
                            ${data.headers.map(header => `<th>${this.escapeHTML(header)}</th>`).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${data.rows.map(row => `
                            <tr>
                                ${row.map(cell => `<td>${this.escapeHTML(cell)}</td>`).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </body>
            </html>
        `;
        
        // Create download link
        const blob = new Blob([htmlContent], { type: 'text/html' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'table-export.html';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        this.instance.showNotification('Data exported to HTML successfully', 'success');
    },

    // Helper methods
    escapeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    escapeXML(str) {
        return str.replace(/[<>&'"]/g, function (c) {
            switch (c) {
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '&': return '&amp;';
                case '\'': return '&apos;';
                case '"': return '&quot;';
            }
        });
    },

    destroy() {
        // Remove import modal if it exists
        const importModal = document.getElementById('importModal');
        if (importModal) {
            importModal.remove();
        }

        // Clean up any event listeners or DOM elements if needed
        delete this.instance.exportData;
        delete this.instance.getExportData;
        delete this.instance.exportToExcel;
        delete this.instance.exportToCSV;
        delete this.instance.copyToClipboard;
        delete this.instance.exportToJSON;
        delete this.instance.exportToPDF;
        delete this.instance.exportToXML;
        delete this.instance.exportToHTML;
        delete this.instance.importData;
    },

    setupExportUI() {
        console.log('ImportExportPlugin: Setting up export UI');
        
        // Make sure we're using the initialized toolbar
        if (!this.instance.toolbar || this.instance.toolbar.getAttribute('data-initialized') !== 'true') {
            console.warn('ImportExportPlugin: No initialized toolbar found.');
            return;
        }
        
        // Find the export column with more flexible selectors
        const exportCol = this.instance.toolbar.querySelector('.col-12.d-flex.justify-content-end') || 
                         this.instance.toolbar.querySelector('.col.d-flex.justify-content-end') ||
                         this.instance.toolbar.querySelector('.d-flex.justify-content-end') ||
                         this.instance.toolbar.querySelector('[class*="justify-content-end"]');
        
        if (!exportCol) {
            console.warn('ImportExportPlugin: Export column not found, cannot add export buttons');
            return;
        }
        
        console.log('ImportExportPlugin: Export column found, adding buttons');
        
        // More robust button detection to avoid duplicates
        const exportButtons = {
            print: exportCol.querySelector('button:has(.sa-printer), button:has(i[class*="printer"])'),
            import: exportCol.querySelector('button[data-bs-target="#importModal"]'),
            export: exportCol.querySelector('.dropdown-toggle, button:contains("Export")')
        };
        
        // Log button detection for debugging
        console.log('ImportExportPlugin: Detected print button:', exportButtons.print);
        console.log('ImportExportPlugin: Detected import button:', exportButtons.import);
        console.log('ImportExportPlugin: Detected export button:', exportButtons.export);
        
        // For extra safety, count all buttons that might be print buttons
        const allPrintButtons = exportCol.querySelectorAll('button:has(.sa-printer), button:has(i[class*="printer"]), button.btn-outline-secondary');
        if (allPrintButtons.length > 0) {
            console.log('ImportExportPlugin: Found', allPrintButtons.length, 'possible print buttons, skipping print button creation');
            exportButtons.print = allPrintButtons[0]; // Use the first one
        }
        
        // Add print button if enabled and not already present
        if (this.instance.options.print && !exportButtons.print) {
            const printBtn = document.createElement('button');
            printBtn.className = 'btn btn-sm btn-outline-secondary btn-icon h-100 order-1 px-3 fs-xl';
            printBtn.innerHTML = '<i class="sa sa-printer"></i>';
            printBtn.dataset.role = 'print-button';
            printBtn.addEventListener('click', () => {
                window.print();
            });
            exportCol.appendChild(printBtn);
            console.log('ImportExportPlugin: Added print button');
        }
        
        // Add import button if enabled and not already present
        if (this.instance.options.import && !exportButtons.import) {
            const importBtn = document.createElement('button');
            importBtn.className = 'btn btn-sm btn-outline-success d-flex align-items-center gap-1';
            importBtn.setAttribute('data-bs-toggle', 'modal');
            importBtn.setAttribute('data-bs-target', '#importModal');
            importBtn.dataset.role = 'import-button';
            importBtn.innerHTML = '<i class="sa sa-cloud-upload"></i> Import';
            exportCol.appendChild(importBtn);
            console.log('ImportExportPlugin: Added import button');
        }

        // Create the export dropdown if export is enabled and not already present
        if (this.instance.options.export && !exportButtons.export) {
            this.createExportDropdown(exportCol);
            console.log('ImportExportPlugin: Added export dropdown');
        }
    },

    createExportDropdown(container) {
        // Create the export dropdown
        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group';
    
        const exportBtn = document.createElement('button');
        exportBtn.className = 'btn btn-sm btn-outline-primary dropdown-toggle';
        exportBtn.setAttribute('data-bs-toggle', 'dropdown');
        exportBtn.textContent = 'Export';
    
        const dropdownMenu = document.createElement('ul');
        dropdownMenu.className = 'dropdown-menu';
    
        const exportOptions = [
            { format: 'excel', label: 'Excel' },
            { format: 'csv', label: 'CSV' },
            { format: 'copy', label: 'Copy' },
            { format: 'pdf', label: 'PDF' },
            { format: 'json', label: 'JSON' },
            { format: 'xml', label: 'XML' },
            { format: 'html', label: 'HTML' }
        ];
    
        const self = this;
        exportOptions.forEach(function (option) {
            const li = document.createElement('li');
            const a = document.createElement('a');
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
        container.appendChild(btnGroup);
    }
};

