import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';

// Wait for the DOM to load before initializing
document.addEventListener('DOMContentLoaded', () => {

    // Initialize SmartTables with the table ID and options
    const table = new SmartTables('myTable', {
        responsive: {
            enabled: true,
            breakpoint: 768,
            columnPrikorities: {
                0: 1,  // TradeID - highest priority (never hide)
                1: 2,  // Symbol - second highest priority
                2: 3,  // Qty - third priority
                3: 4,  // BuyPrice - fourth priority
                4: 5,  // SellPrice - fifth priority
                5: 6   // BuyDate - sixth priority
            }
        },
        debug: true,
        // Add hooks for customizing cell rendering
        hooks: {
            afterInit: function () {
                // Get all table cells after initialization
                const tbody = document.querySelector('#myTable tbody');
                if (!tbody) return;

                // Process each row
                Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                    // Apply formatting to specific columns
                    Array.from(row.cells).forEach((cell, index) => {
                        const text = cell.textContent.trim();

                        // Check for null values in any column
                        if (text === 'null') {
                            cell.textContent = 'null'; // Replace with em dash
                            cell.classList.add('text-muted', 'fst-italic');
                            return;
                        }

                        // Format Profit column (7)
                        if (index === 7) {
                            if (text.includes('-')) {
                                cell.classList.add('text-danger', 'fw-bold');
                            } else {
                                cell.classList.add('text-success', 'fw-bold');
                            }
                        }

                        // Format Net column (9)
                        if (index === 9) {
                            if (text.includes('-')) {
                                cell.classList.add('text-danger', 'fw-bold');
                            } else {
                                cell.classList.add('text-success', 'fw-bold');
                            }
                        }
                    });
                });
            }
        }
    });
});