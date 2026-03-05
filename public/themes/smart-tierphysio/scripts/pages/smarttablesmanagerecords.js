/**
 * Smart Tables - Manage Records Implementation
 * ---------------------------------------------
 * This script implements a complete data management UI for handling CRUD operations
 * on tabular data using the SmartTables library.
 * 
 * Key Features:
 * - Client-side data manipulation with full CRUD operations
 * - Row state tracking (editing, saved states with visual feedback)
 * - Custom responsive behavior
 * - Modal-based editing with form validation
 * - Pagination, sorting, and searching
 * - Bootstrap integration for modern UI components
 * 
 * Data Flow:
 * 1. Initial data is loaded from the sampleData array
 * 2. CRUD operations update both the UI and the underlying data source
 * 3. Hooks provide extension points for customizing behavior
 * 4. Event listeners manage user interactions
 * 
 * Dependencies:
 * - SmartTables library (../smartTables.bundle.js)
 * - Bootstrap 5.x (for modal dialogs and UI components)
 * 
 * @author Sunnyat A. (SmartAdmin Template Team)
 * @license WrapBootstrap
 */

import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';

/**
 * Sample Data
 * -----------
 * This data represents a collection of employee records that will be used to populate the table.
 * In a production environment, this would typically come from an API or database.
 * 
 * Data Structure:
 * - id: Unique identifier for each record
 * - name: Employee full name
 * - email: Employee email address
 * - phone: Contact phone number with formatting
 * - salary: Numeric value representing annual salary
 * - department: Department name (used for filtering/grouping)
 * - position: Job title
 * - joinDate: ISO date string representing hire date
 * - active: Boolean indicating current employment status
 * - status: Employment type (Full-time, Part-time, Contract, Intern)
 * 
 * Note: IDs are converted to strings during initialization to ensure consistent comparison
 * with string values that might come from form inputs or URL parameters.
 */
const sampleData = [
    { 
        id: 146134, 
        name: 'John Doe', 
        email: 'john@example.com', 
        phone: '(555) 123-4567',
        salary: 75000,
        department: 'Engineering',
        position: 'Senior Developer',
        joinDate: '2020-03-15',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146137, 
        name: 'Jane Smith', 
        email: 'jane@example.com', 
        phone: '(555) 987-6543',
        salary: 82000,
        department: 'Marketing',
        position: 'Marketing Manager',
        joinDate: '2019-07-22',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146142, 
        name: 'Bob Johnson', 
        email: 'bob@example.com', 
        phone: '(555) 234-5678',
        salary: 65000,
        department: 'Customer Support',
        position: 'Support Specialist',
        joinDate: '2021-01-10',
        active: false,
        status: 'Part-time'
    },
    { 
        id: 146145, 
        name: 'Sarah Williams', 
        email: 'sarah@example.com', 
        phone: '(555) 345-6789',
        salary: 90000,
        department: 'Human Resources',
        position: 'HR Director',
        joinDate: '2018-11-05',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146147, 
        name: 'Mike Davis', 
        email: 'mike@example.com', 
        phone: '(555) 456-7890',
        salary: 67500,
        department: 'Engineering',
        position: 'Junior Developer',
        joinDate: '2022-02-28',
        active: true,
        status: 'Contract'
    },
    { 
        id: 146152, 
        name: 'Emily Wilson', 
        email: 'emily@example.com', 
        phone: '(555) 567-8901',
        salary: 72000,
        department: 'Finance',
        position: 'Financial Analyst',
        joinDate: '2020-09-14',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146156, 
        name: 'David Brown', 
        email: 'david@example.com', 
        phone: '(555) 678-9012',
        salary: 85000,
        department: 'Engineering',
        position: 'DevOps Engineer',
        joinDate: '2019-05-18',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146161, 
        name: 'Lisa Taylor', 
        email: 'lisa@example.com', 
        phone: '(555) 789-0123',
        salary: 58000,
        department: 'Sales',
        position: 'Sales Representative',
        joinDate: '2021-11-30',
        active: false,
        status: 'Part-time'
    },
    { 
        id: 146165, 
        name: 'James Miller', 
        email: 'james@example.com', 
        phone: '(555) 890-1234',
        salary: 95000,
        department: 'Executive',
        position: 'CTO',
        joinDate: '2017-08-12',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146169, 
        name: 'Amanda Clark', 
        email: 'amanda@example.com', 
        phone: '(555) 901-2345',
        salary: 78000,
        department: 'Marketing',
        position: 'Content Strategist',
        joinDate: '2020-06-25',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146172, 
        name: 'Robert White', 
        email: 'robert@example.com', 
        phone: '(555) 012-3456',
        salary: 62000,
        department: 'Customer Support',
        position: 'Technical Support',
        joinDate: '2022-04-05',
        active: true,
        status: 'Contract'
    },
    { 
        id: 146175, 
        name: 'Jennifer Lee', 
        email: 'jennifer@example.com', 
        phone: '(555) 123-7890',
        salary: 88000,
        department: 'Product',
        position: 'Product Manager',
        joinDate: '2019-02-17',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146178, 
        name: 'Thomas Harris', 
        email: 'thomas@example.com', 
        phone: '(555) 234-8901',
        salary: 71000,
        department: 'Engineering',
        position: 'QA Engineer',
        joinDate: '2021-07-08',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146181, 
        name: 'Patricia Martin', 
        email: 'patricia@example.com', 
        phone: '(555) 345-9012',
        salary: 83000,
        department: 'Human Resources',
        position: 'Recruitment Manager',
        joinDate: '2018-04-22',
        active: false,
        status: 'Contract'
    },
    { 
        id: 146184, 
        name: 'Daniel Anderson', 
        email: 'daniel@example.com', 
        phone: '(555) 456-0123',
        salary: 69000,
        department: 'Finance',
        position: 'Accountant',
        joinDate: '2020-12-03',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146187, 
        name: 'Michelle Walker', 
        email: 'michelle@example.com', 
        phone: '(555) 567-1234',
        salary: 77000,
        department: 'Sales',
        position: 'Sales Manager',
        joinDate: '2019-10-19',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146190, 
        name: 'Christopher Young', 
        email: 'chris@example.com', 
        phone: '(555) 678-2345',
        salary: 92000,
        department: 'Engineering',
        position: 'Data Scientist',
        joinDate: '2018-07-11',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146193, 
        name: 'Nancy Hall', 
        email: 'nancy@example.com', 
        phone: '(555) 789-3456',
        salary: 64000,
        department: 'Marketing',
        position: 'Social Media Specialist',
        joinDate: '2021-05-27',
        active: true,
        status: 'Part-time'
    },
    { 
        id: 146196, 
        name: 'Kevin Allen', 
        email: 'kevin@example.com', 
        phone: '(555) 890-4567',
        salary: 87000,
        department: 'Product',
        position: 'UX Designer',
        joinDate: '2020-01-14',
        active: true,
        status: 'Full-time'
    },
    { 
        id: 146199, 
        name: 'Laura King', 
        email: 'laura@example.com', 
        phone: '(555) 901-5678',
        salary: 73000,
        department: 'Customer Support',
        position: 'Customer Success Manager',
        joinDate: '2019-09-08',
        active: false,
        status: 'Contract'
    }
];

// Convert IDs to strings during initialization
sampleData.forEach(item => {
    item.id = String(item.id);
});

/**
 * Table Initialization
 * --------------------
 * This section initializes the SmartTables instance when the DOM is fully loaded.
 * It configures all the columns, data types, and event handling for the table.
 */
document.addEventListener('DOMContentLoaded', () => {
    /**
     * Row State Management
     * -------------------
     * This object tracks the state of rows across various operations.
     * 
     * Properties:
     * - editing: Stores the ID of the row currently being edited (null when no editing in progress)
     * - saved: A Set containing IDs of rows that were recently saved (for visual feedback)
     * - adding: Boolean flag indicating if a new record is being added
     * - deleting: Stores the ID of the row currently being deleted (null when no deletion in progress)
     * 
     * This state management allows for visual indicators and proper cleanup across operations.
     */
    const rowStates = {
        editing: null, // ID of the row currently being edited
        saved: new Set(), // Set of row IDs that should have the 'saved' class
        adding: false, // Flag to track when a new record is being added
        deleting: null // ID of the row currently being deleted
    };

    /**
     * SmartTables Instance
     * -------------------
     * This is the main table instance that powers the data management interface.
     * It's configured with columns, data types, and hooks for various operations.
     * 
     * @type {SmartTables}
     */
    const clientTable = new SmartTables('clientTable', {
        /**
         * Data Configuration
         * -----------------
         * Defines the data source and column structure for the table.
         */
        data: {
            type: 'json',        // Data format is JSON
            source: sampleData,  // Use the sample data array as the data source
            idField: 'id',       // Field that uniquely identifies each record
            columns: [
                {   
                    data: 'id',         // Field name in the data object
                    title: 'ID',        // Column header text
                    editable: false     // ID cannot be edited
                },
                { 
                    data: 'name', 
                    title: 'Name',
                    required: true      // Field is required in the edit form
                },
                { 
                    data: 'email', 
                    title: 'Email', 
                    type: 'email',      // Email input type for validation
                    required: true
                },
                { 
                    data: 'phone', 
                    title: 'Phone', 
                    type: 'tel',        // Telephone input type
                    format: 'phone',    // Custom formatting for display
                    placeholder: '(555) 555-5555',
                    required: true
                },
                { 
                    data: 'salary', 
                    title: 'Salary', 
                    type: 'number',     // Numeric input type
                    min: 0,             // Minimum value
                    step: 500,          // Increment by 500
                    render: data => '$' + data.toLocaleString() // Format with $ and commas
                },
                { 
                    data: 'department', 
                    title: 'Department', 
                    type: 'select',     // Dropdown select input
                    options: [          // Available options
                        'Engineering',
                        'Sales',
                        'Executive',
                        'Marketing',
                        'Human Resources',
                        'Customer Support',
                        'Finance',
                        'Operations',
                        'Product',
                        'Research',
                        'Legal',
                        'IT'
                    ]
                },
                { 
                    data: 'joinDate', 
                    title: 'Join Date',  
                    type: 'date',       // Date input type
                    render: data => new Date(data).toLocaleDateString() // Format as local date
                },
                { 
                    data: 'active', 
                    title: 'Active', 
                    type: 'boolean',    // Boolean/checkbox input type
                    render: data => data === true 
                        ? '<span class="badge bg-success">Yes</span>' // Visual indicator for active
                        : '<span class="badge bg-danger">No</span>'   // Visual indicator for inactive
                },
                { 
                    data: 'status', 
                    title: 'Status', 
                    type: 'select',
                    options: [          // Options with separate value and label
                        { value: 'Full-time', label: 'Full-time' },
                        { value: 'Part-time', label: 'Part-time' },
                        { value: 'Contract', label: 'Contract' },
                        { value: 'Intern', label: 'Intern' }
                    ]
                },
                {
                    data: 'actions',    // Special column for action buttons
                    title: 'Actions',
                    sortable: false,    // Don't allow sorting by actions
                    editable: false,    // Not included in edit form
                    render: (data, row) => `
                        <button type="button" class="btn btn-primary btn-xs edit-btn" data-id="${row.id}">Edit</button>
                        <button type="button" class="btn btn-danger btn-xs delete-btn" data-id="${row.id}">Del</button>
                    `
                }
            ]
        },
        debug: true,       // Enable debug logging
        responsive: true,  // Enable responsive behavior
        addRecord: true,   // Enable the built-in Add Record button
        /**
         * Hooks Configuration
         * ------------------
         * Hooks provide extension points for customizing behavior at different stages 
         * of the table's lifecycle and operations.
         */
        hooks: {
            /**
             * Before Edit Hook
             * ---------------
             * Called before a row enters edit mode.
             * Sets up visual feedback by adding the 'editing' class.
             * 
             * @param {string} rowId - ID of the row being edited
             * @returns {boolean} - Whether to proceed with the edit operation
             */
            beforeEdit(rowId) {
                // Set the editing state
                rowStates.editing = rowId;
                // Apply the editing class
                const rowElement = this.table.querySelector(`tbody tr[data-id="${rowId}"]`);
                if (rowElement) {
                    rowElement.classList.add('editing');
                }
                return true; // Allow the edit to proceed
            },
            
            /**
             * After Edit Hook
             * --------------
             * Called after an edit operation completes (success or failure).
             * Handles visual feedback and table redrawing.
             * 
             * @param {Object|string} rowId - Row ID or object containing edit result
             * @param {Object} [rowData] - Data of the edited row
             * @param {boolean} [success] - Whether the edit was successful
             */
            afterEdit(...args) {
                // Handle different possible argument signatures
                let rowId, rowData, success;
                if (args.length === 1 && typeof args[0] === 'object') {
                    ({ rowId, rowData, success } = args[0]);
                } else {
                    [rowId, rowData, success] = args;
                }

                // Clear editing state
                rowStates.editing = null;

                // If the edit was successful, mark the row as saved
                if (success === true) {
                    rowStates.saved.add(rowId);
                    setTimeout(() => {
                        rowStates.saved.delete(rowId);
                        // Reapply classes after timeout
                        const rowElement = this.table.querySelector(`tbody tr[data-id="${rowId}"]`);
                        if (rowElement) {
                            rowElement.classList.remove('saved');
                        }
                    }, 3000);
                }

                // Redraw the table to reflect updated data
                this.draw();
                
                // Reapply any active search
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    this.handleSearch(this.searchQuery);
                }

                // Reapply any active column sorting
                if (this.currentSortColumn !== undefined && this.currentSortDirection) {
                    this.sortBy(this.currentSortColumn, this.currentSortDirection);
                }

                // Reapply classes after redraw
                if (rowStates.editing) {
                    const editingRow = this.table.querySelector(`tbody tr[data-id="${rowStates.editing}"]`);
                    if (editingRow) {
                        editingRow.classList.add('editing');
                    }
                }
                rowStates.saved.forEach(savedRowId => {
                    const savedRow = this.table.querySelector(`tbody tr[data-id="${savedRowId}"]`);
                    if (savedRow) {
                        savedRow.classList.add('saved');
                    }
                });
            },
            
            /**
             * Edit Modal Created Hook
             * ----------------------
             * Called when the edit modal HTML is created.
             * Allows customization of the modal HTML before it's added to the DOM.
             * 
             * @param {string} modalHTML - Generated HTML for the modal
             * @param {string} rowId - ID of the row being edited
             * @param {Object} rowData - Data of the row being edited
             * @returns {string} - Original or modified HTML for the modal
             */
            onEditModalCreated(modalHTML, rowId, rowData) {
                // You could customize the modal HTML here
                // For example, add custom fields, change styling, etc.
                return modalHTML; // Return original or modified HTML
            },
            
            /**
             * Edit Modal Before Show Hook
             * --------------------------
             * Called just before the edit modal is shown.
             * Allows adding event listeners, initializing third-party components, etc.
             * 
             * @param {HTMLElement} modalElement - The modal DOM element
             * @param {string} rowId - ID of the row being edited
             * @param {Object} rowData - Data of the row being edited
             */
            onEditModalBeforeShow(modalElement, rowId, rowData) {
                // Add custom event listeners, initialize third-party components
                console.log('Edit modal is about to be shown for row:', rowId);
                
                // Example: Add custom class to modal
                modalElement.classList.add('custom-edit-modal');
                
                // Example: Add custom event listener
                const form = modalElement.querySelector('form');
                if (form) {
                    form.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault(); // Prevent default form submission on Enter
                        }
                    });
                }
            },
            
            /**
             * Edit Data Collected Hook
             * -----------------------
             * Called after form data is collected but before it's applied to the table.
             * Allows transformation and validation of the data.
             * 
             * @param {Object} updatedData - Data collected from the edit form
             * @param {string} rowId - ID of the row being edited
             * @param {Object} originalData - Original data of the row before editing
             * @returns {Object} - Processed data to be applied to the table
             */
            onEditDataCollected(updatedData, rowId, originalData) {
                // Process data before it's applied to the table
                console.log('Data collected from edit form:', updatedData);
                
                const processedData = { ...updatedData };
                
                // Map column data fields to their types for easy lookup
                const columnTypes = {};
                this.options.data.columns.forEach(column => {
                    if (column.data && column.type) {
                        columnTypes[column.data] = column.type;
                    }
                });

                // Process each field in updatedData based on its column type
                for (const field in columnTypes) {
                    const type = columnTypes[field];
                    const value = processedData[field];

                    switch (type) {
                        case 'number':
                            // Convert to number, default to 0 if invalid
                            processedData[field] = value !== undefined && value !== '' 
                                ? parseFloat(value) 
                                : 0;
                            break;
                        case 'boolean':
                            // Convert to boolean: true if field is present and "on", false if not present
                            processedData[field] = field in processedData && value === 'on';
                            break;
                        case 'date':
                            // Ensure the value is a valid date string (or convert to Date object if needed)
                            processedData[field] = value ? new Date(value).toISOString().split('T')[0] : '';
                            break;
                        case 'text':
                        case 'select':
                            // Keep as string, no conversion needed
                            break;
                        default:
                            // No type specified, leave as-is
                            break;
                    }
                }
                
                // Example: Add timestamp for when the record was modified
                processedData.lastModified = new Date().toISOString();
                
                // Update sampleData directly using the hook
                const recordIndex = sampleData.findIndex(item => item.id === rowId);
                if (recordIndex !== -1) {
                    sampleData[recordIndex] = { ...sampleData[recordIndex], ...processedData };
                }
                
                return processedData;
            },
            
            /**
             * Edit Success Hook
             * ---------------
             * Called when a record is successfully updated.
             * Handles success notifications and UI updates.
             * 
             * @param {string} rowId - ID of the edited row
             * @param {Object} updatedRecord - The updated record data
             * @param {Object} submittedData - The data that was submitted
             */
            onEditSuccess(rowId, updatedRecord, submittedData) {
                // Handle successful edit
                console.log('Record updated successfully:', rowId);
                
                // Example: Show custom notification
                const notification = document.createElement('div');
                notification.className = 'floating-notification success';
                notification.textContent = `Record ${rowId} updated successfully`;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.classList.add('show');
                    setTimeout(() => {
                        notification.classList.remove('show');
                        setTimeout(() => notification.remove(), 300);
                    }, 2000);
                }, 10);
            },
            
            /**
             * Edit Error Hook
             * -------------
             * Called when an error occurs during record update.
             * Handles error notifications and recovery.
             * 
             * @param {string} rowId - ID of the row that failed to update
             * @param {Error} error - The error that occurred
             * @param {Object} attemptedData - The data that was attempted to be submitted
             */
            onEditError(rowId, error, attemptedData) {
                // Handle edit error
                console.error('Error updating record:', rowId, error);
            },
            
            /**
             * Before Delete Hook
             * ----------------
             * Called before a row is deleted.
             * Sets up visual feedback and can abort the deletion.
             * 
             * @param {string} rowId - ID of the row to be deleted
             * @returns {boolean} - Whether to proceed with the deletion
             */
            beforeDelete(rowId) {
                // Set the deleting state and apply visual feedback
                rowStates.deleting = rowId;
                const rowElement = this.table.querySelector(`tbody tr[data-id="${rowId}"]`);
                if (rowElement) {
                    rowElement.classList.add('deleting');
                }
                return true; // Allow the deletion to proceed
            },
            
            /**
             * After Delete Hook
             * ---------------
             * Called after a deletion attempt completes (success or failure).
             * Handles cleanup and visual feedback.
             * 
             * @param {string} rowId - ID of the deleted (or attempted) row
             * @param {Object} data - Response data, if any
             * @param {boolean} success - Whether the deletion was successful
             */
            afterDelete(rowId, data, success) {
                // Clear the deleting state
                rowStates.deleting = null;
                
                // Handle deletion result
                if (success) {
                    console.log('Record deleted successfully:', rowId);
 
                    //draw the table to fix pagination
                    this.draw();

                    // Reapply any active column sorting
                    if (this.currentSortColumn !== undefined && this.currentSortDirection) {
                        this.sortBy(this.currentSortColumn, this.currentSortDirection);
                    }
 
                } else {
                    // Clear deleting state on failure
                    const rowElement = this.table.querySelector(`tbody tr[data-id="${rowId}"]`);
                    if (rowElement) {
                        rowElement.classList.remove('deleting');
                    }
                }
            },
            onDeleteModalCreated(modalHtml, rowId) {
                // Customize delete confirmation modal
                return modalHtml; // Return original or modified HTML
            },
            
            /**
             * Delete Success Hook
             * -----------------
             * Called when a record is successfully deleted.
             * Handles data updates and notifications.
             * 
             * @param {string} rowId - ID of the deleted row
             * @param {Object} deletedRecord - The data of the deleted record
             */
            onDeleteSuccess(rowId, deletedRecord) {
                // Handle successful deletion
                console.log('Record deleted:', rowId);
                // Note: Data updates are now handled in the click handler
            },

            /**
             * Before Add Record Hook
             * ---------------------
             * Called before a new record form is displayed.
             * Sets up visual feedback and initializes default values.
             * 
             * @param {Object} initialData - Initial data for the new record
             * @param {Object} options - Additional options for the add operation
             * @returns {Object|boolean} - Modified initial data or false to abort
             */
            beforeAddRecord(initialData, options) {
                // Set the adding state
                rowStates.adding = true;
                
                // Log the operation
                console.log('Adding new record with initial data:', initialData);
                
                // Return the initial data (potentially modified)
                return initialData;
            },

            /**
             * After Add Record Hook
             * --------------------
             * Called after a record add operation completes (success or failure).
             * Handles cleanup and visual feedback.
             * 
             * @param {Object} newRecordData - The data of the new record
             * @param {boolean} success - Whether the operation was successful
             */
            afterAddRecord(newRecordData, success) {
                // Reset the adding state
                rowStates.adding = false;
                
                if (success) {
                    console.log('Successfully added new record:', newRecordData);
                    
                    // Add the new record to our local data source (if not already done by the library)
                    // This ensures our local data stays in sync with the table
                    const recordExists = sampleData.some(item => item.id === newRecordData.id);
                    if (!recordExists) {
                        sampleData.push(newRecordData);
                    }
                } else {
                    console.warn('Failed to add new record');
                }
            },

            /**
             * Add Modal Created Hook
             * ---------------------
             * Called when the add record modal is created.
             * Allows customization of the modal HTML.
             * 
             * @param {string} modalHTML - The default modal HTML
             * @param {Object} initialData - Initial data for the new record
             * @param {Object} options - Additional options for the add operation
             * @returns {string} - Customized modal HTML
             */
            onAddModalCreated(modalHTML, initialData, options) {
                // Parse the modal HTML to a DOM element for easier manipulation
                const parser = new DOMParser();
                const modalDoc = parser.parseFromString(modalHTML, 'text/html');
                const modalElement = modalDoc.body.firstChild;
                
                // Add custom classes
                modalElement.classList.add('smarttables-add-modal');
                
                // Customize the modal header with additional styling
                // const modalHeader = modalElement.querySelector('.modal-header');
                // if (modalHeader) {
                //     modalHeader.classList.add('bg-primary', 'text-white');
                // }
                
                // Customize the submit button
                const submitButton = modalElement.querySelector('[id$="-submit"]');
                if (submitButton) {
                    submitButton.classList.add('btn-success');
                    // Add an icon to the button
                    submitButton.innerHTML = `<i class="fas fa-plus-circle me-1"></i> ${submitButton.textContent}`;
                }
                
                // Convert the modified modal back to HTML string
                return modalElement.outerHTML;
            },

            /**
             * Add Modal Before Show Hook
             * -------------------------
             * Called right before the add modal is shown.
             * Use for last-minute DOM manipulations.
             * 
             * @param {HTMLElement} modalElement - The modal DOM element
             * @param {Object} initialData - Initial data for the new record
             * @param {Object} options - Additional options
             */
            onAddModalBeforeShow(modalElement, initialData, options) {
                // Add animation class
                modalElement.classList.add('fade');
                
                // Focus the first input when the modal is shown
                modalElement.addEventListener('shown.bs.modal', () => {
                    const firstInput = modalElement.querySelector('form input:not([type="hidden"]):not([readonly]):not([disabled])');
                    if (firstInput) {
                        firstInput.focus();
                    }
                });
            },

            /**
             * Add Data Collected Hook
             * ----------------------
             * Called after the form data is collected but before submission.
             * Allows for data transformation and validation.
             * 
             * @param {Object} newRecordData - The collected form data
             * @param {Object} options - Additional options
             * @returns {Object} - Transformed data to be submitted
             */
            onAddDataCollected(newRecordData, options) {
                // Transform any data if needed before submission
                
                // For example, ensure salary is a number
                if (newRecordData.salary) {
                    newRecordData.salary = Number(newRecordData.salary);
                }
                
                // Set boolean values correctly
                if (newRecordData.active === 'on' || newRecordData.active === 'true') {
                    newRecordData.active = true;
                } else if (newRecordData.active === 'off' || newRecordData.active === 'false' || newRecordData.active === undefined) {
                    newRecordData.active = false;
                }
                
                // Generate a unique ID if not provided
                if (!newRecordData.id) {
                    // Get highest ID and increment
                    const highestId = Math.max(...sampleData.map(item => parseInt(item.id)));
                    newRecordData.id = String(highestId + 1);
                }
                
                return newRecordData;
            },

            /**
             * Add Record Success Hook
             * ----------------------
             * Called when a record is successfully added.
             * Handles UI updates and additional processing.
             * 
             * @param {Object} newRecord - The newly added record
             */
            onAddRecordSuccess(newRecord) {
                console.log('Record added successfully:', newRecord);
                
                // Draw the table to fix pagination
                this.draw();
                
                // Reapply any active search query
                if (this.searchQuery && this.searchQuery.trim() !== '') {
                    setTimeout(() => this.handleSearch(this.searchQuery), 50);
                }
                
                // Reapply any active column sorting
                if (this.currentSortColumn !== undefined && this.currentSortDirection) {
                    setTimeout(() => this.sortBy(this.currentSortColumn, this.currentSortDirection), 100);
                }

                // Add visual feedback for the new row
                setTimeout(() => {
                    const newRow = clientTable.table.querySelector(`tbody tr[data-id="${newRecord.id}"]`);
                    if (newRow) {
                        newRow.classList.add('new-record');
                        // Remove class after animation completes
                        setTimeout(() => {
                            newRow.classList.remove('new-record');
                        }, 3000);
                    }
                }, 200);
            },

            /**
             * Add Record Error Hook
             * -------------------
             * Called when an error occurs during record addition.
             * Handles error reporting and recovery.
             * 
             * @param {Error} error - The error that occurred
             * @param {Object} attemptedData - The data that failed to be added
             */
            onAddRecordError(error, attemptedData) {
                console.error('Error adding record:', error);
                console.error('Attempted data:', attemptedData);
                
                // Display error in UI if needed
                // This supplements the notification already shown by the library
            },

            /**
             * Add Cancelled Hook
             * -----------------
             * Called when the add operation is cancelled.
             * 
             * @param {Object} options - Options that were passed to addRecord
             */
            onAddCancelled(options) {
                console.log('Add operation cancelled');
                rowStates.adding = false;
            },

            /**
             * After Init Hook
             * --------------
             * Called after the table is fully initialized.
             * Use to add custom UI elements or attach event handlers.
             */
            afterInit() {
                // Add a button to the toolbar to trigger adding a new record
                const toolbar = this.toolbarElement;
                if (toolbar) {
                    // Create the Add Record button
                    const addButton = document.createElement('button');
                    addButton.className = 'btn btn-success add-record-btn ms-2';
                    addButton.innerHTML = '<i class="fas fa-plus-circle me-1"></i> Add Record';
                    addButton.setAttribute('type', 'button');
                    
                    // Find the toolbar's button group or create one
                    let buttonGroup = toolbar.querySelector('.btn-group');
                    if (!buttonGroup) {
                        buttonGroup = document.createElement('div');
                        buttonGroup.className = 'btn-group ms-2';
                        toolbar.appendChild(buttonGroup);
                    } else {
                        // If button group exists, add margin
                        buttonGroup.classList.add('ms-2');
                    }
                    
                    // Add button to toolbar
                    toolbar.insertBefore(addButton, buttonGroup);
                    
                    // Attach event listener
                    addButton.addEventListener('click', this.handleAddRecord.bind(this));
                }
            },
        }
    });

    /**
     * Add Record Handler
     * ----------------
     * Method to handle adding a new record.
     * Attached to the Add Record button.
     */
    SmartTables.prototype.handleAddRecord = function() {
        if (rowStates.adding || rowStates.editing) {
            // Prevent multiple add/edit operations simultaneously
            console.warn('Cannot add record while another operation is in progress');
            this.showNotification('Please complete the current operation first', 'warning');
            return;
        }
        
        // Call the addRecord method with default empty data
        this.addRecord({}, {
            showNotifications: true,
            title: 'Add New Employee',
            submitButtonText: 'Create Employee',
            cancelButtonText: 'Cancel'
        }).catch(error => {
            console.error('Error during add operation:', error);
            rowStates.adding = false;
        });
    };

    /**
     * Event Delegation
     * ---------------
     * Uses event delegation to handle all button clicks within the table.
     * This is more efficient than attaching individual handlers to each button.
     */
    clientTable.table.addEventListener('click', e => {
        if (e.target.classList.contains('edit-btn')) {
            const rowId = e.target.getAttribute('data-id');
            clientTable.edit(rowId).catch(error => {
                console.error('Error during edit operation:', error);
                rowStates.editing = null;
            });
        } else if (e.target.classList.contains('delete-btn')) {
            const rowId = e.target.getAttribute('data-id');
            clientTable.delete(rowId)
                .then((result) => {
                    // Check if the delete operation was successful or cancelled
                    if (result === true) {
                        // After successful delete, force redraw with updated data
                        // This ensures pagination is correctly recalculated
                        const recordIndex = sampleData.findIndex(item => item.id === rowId);
                        if (recordIndex !== -1) {
                            sampleData.splice(recordIndex, 1);
                            clientTable.options.data.source = sampleData;
                            clientTable.updateTableData(sampleData);
                            
                            // Reapply any active column sorting
                            if (clientTable.currentSortColumn !== undefined && clientTable.currentSortDirection) {
                                clientTable.sortBy(clientTable.currentSortColumn, clientTable.currentSortDirection);
                            }
                            
                            // Note: handleSearch is already called in updateTableData if there's an active search
                        }
                    } else if (result === false) {
                        // Delete operation was cancelled by user
                        // Clean up the 'deleting' class that was added in beforeDelete
                        const rowElement = clientTable.table.querySelector(`tbody tr[data-id="${rowId}"]`);
                        if (rowElement) {
                            rowElement.classList.remove('deleting');
                        }
                        console.log('Delete operation cancelled for row:', rowId);
                    }
                })
                .catch(error => {
                    console.error('Error during delete operation:', error);
                    // Also clean up the 'deleting' class on error
                    const rowElement = clientTable.table.querySelector(`tbody tr[data-id="${rowId}"]`);
                    if (rowElement) {
                        rowElement.classList.remove('deleting');
                    }
                });
        }
    });

    // Add an event listener for the custom add button
    document.addEventListener('click', e => {
        if (e.target.closest('.add-record-btn')) {
            // Call the handler method on the clientTable instance
            clientTable.handleAddRecord();
        }
    });

    /**
     * Modal Close Event Handler
     * ------------------------
     * Event listener for Bootstrap modal hidden event.
     * Cleans up row states and classes when modals are closed.
     * 
     * This ensures proper visual state is maintained even if the user
     * cancels an edit operation by closing the modal.
     */
    document.addEventListener('hidden.bs.modal', (event) => {
        // Check if this is a delete modal by looking at the modal ID
        const modal = event.target;
        const isDeleteModal = modal && modal.id && modal.id.startsWith('st-delete-modal-');
        
        if (isDeleteModal && rowStates.deleting) {
            // Extract row ID from modal ID (format: st-delete-modal-{rowId})
            const modalRowId = modal.id.replace('st-delete-modal-', '');
            if (modalRowId === rowStates.deleting) {
                // Clean up the deleting class since the modal was closed without confirmation
                const rowElement = clientTable.table.querySelector(`tbody tr[data-id="${rowStates.deleting}"]`);
                if (rowElement) {
                    rowElement.classList.remove('deleting');
                }
                rowStates.deleting = null;
                console.log('Delete modal closed without confirmation for row:', modalRowId);
            }
        }
        
        if (rowStates.editing) {
            // Remove the editing class from the row
            const editingRow = clientTable.table.querySelector(`tbody tr[data-id="${rowStates.editing}"]`);
            if (editingRow) {
                editingRow.classList.remove('editing');
            }
            rowStates.editing = null;
        }
        // Reapply saved classes if any
        rowStates.saved.forEach(savedRowId => {
            const savedRow = clientTable.table.querySelector(`tbody tr[data-id="${savedRowId}"]`);
            if (savedRow) {
                savedRow.classList.add('saved');
            }
        });
        
        // Also handle the adding state
        if (rowStates.adding) {
            rowStates.adding = false;
        }
    });
});