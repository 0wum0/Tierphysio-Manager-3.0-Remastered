import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';

// Wait for the DOM to load before initializing
document.addEventListener('DOMContentLoaded', () => {
    console.log('Initializing SmartTables with JSON data source');

    function getEmailProviderIcon(email) {
        const domain = email.toLowerCase().split('@')[1]?.split('.')[0]; // Extract domain (e.g., "hotmail" from "user@hotmail.com")
        switch (domain) {
            case 'hotmail':
            case 'outlook':
                return 'fab fa-microsoft'; // Microsoft icon for Hotmail/Outlook
            case 'gmail':
                return 'fab fa-google'; // Google icon for Gmail
            case 'yahoo':
                return 'fab fa-yahoo'; // Yahoo icon for Yahoo
            default:
                return 'fas fa-envelope'; // Default email icon for other providers
        }
    }

    // Initialize SmartTables with the table ID and options
    const table = new SmartTables('myTable', {
        data: {
            type: "json",
            source: "json/smarttable-data.json",
            columns: [
                { data: "ID", title: "ID" },
                { data: "FirstName", title: "First Name" },
                { data: "LastName", title: "Last Name" },
                { data: "Gender", title: "Gender" },
                { data: "Phone", title: "Phone" },
                { 
                    data: "Status", 
                    title: "Payment",
                    render: function(value) {
                        let statusClass = {
                            "Success": "badge bg-success",
                            "Pending": "badge bg-warning text-dark",
                            "Rejected": "badge bg-danger",
                            "Cash": "badge bg-secondary"
                        }[value] || "badge bg-dark";
                        return '<span class="' + statusClass + '">' + value + '</span>';
                    }
                },
                { 
                    data: "Due", 
                    title: "Sales",
                    render: function(value) {
                        return '$' + parseFloat(value).toFixed(2);
                    }
                },
                { 
                    data: "Product", 
                    title: "Product",
                    render: function(value, row) {
                        if (!value) return ''; // Handle null/undefined values
                        // Check if the row has a ProductImage
                        if (row.ProductImage) {
                            return `<a href="#" class="product-name text-decoration-underline" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true" data-bs-content="<img src='${row.ProductImage}' alt='${value}' style='max-width: 200px; max-height: 200px;' />">${value}</a>`;
                        }
                        return value; // Return the product name as plain text if no image
                    }
                },
                { data: "Department", title: "POS" },
                {
                    data: "Email", 
                    title: "Email",
                    render: function (value) {
                        if (!value) return ''; // Handle null/undefined values
                        const lowerCaseEmail = value.toLowerCase(); // Convert email to lowercase
                        const iconClass = getEmailProviderIcon(lowerCaseEmail); // Get the appropriate icon
                        return `<span class="bg-secondary bg-opacity-10 border border-success border-opacity-50 py-1 px-2 fs-sm rounded"><i class="${iconClass} me-2 text-success"></i>${lowerCaseEmail}</span>`;
                    }
                },
                { data: "City", title: "Area" },
                { data: "Address", title: "Address" },
                { data: "Company", title: "Company" },
                { data: "CreatedDate", title: "Created Date" }
            ]
        },
        perPage: 15,
        search: true,
        sort: true,
        pagination: true,
        export: true,
        print: true,
        import: true,
        debug: false,
        responsive: {
            enabled: true,
            breakpoint: 768,
            columnPriorities: {
                0: 1,  // ID - highest priority (never hide)
                1: 2,  // First Name - second highest priority
                2: 3,  // Last Name - third priority
                3: 4,  // Gender - fourth priority
                4: 5,  // Phone - fifth priority
                5: 6   // Status - lowest priority (hide first)
            }
        },
        hooks: {
            beforeInit: function() {
                console.log('SmartTables: Before initialization');
            },
            afterInit: function() {
                console.log('SmartTables: After initialization complete');
            },
            afterDraw: function() {
                console.log('SmartTables: Table drawn');
                
                // Initialize popovers for products with images
                const popoverElements = document.querySelectorAll('.product-name');
                popoverElements.forEach(element => {
                    // Destroy any existing popover to avoid duplicates
                    if (element._popover) {
                        bootstrap.Popover.getInstance(element).dispose();
                    }
            
                    // Initialize new popover with explicit top placement and high z-index
                    new bootstrap.Popover(element, {
                        placement: 'top',  // Forces the popover to appear on top
                        trigger: 'hover',  // Ensures popover appears on hover
                        customClass: 'popover-body-p-0', // Custom class to control styling
                    });
                });
            }
        }
    });
});