import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';

// Wait for the DOM to load before initializing
document.addEventListener('DOMContentLoaded', () => {
    // Initialize SmartTables with the table ID and options
    const table = new SmartTables('myTable',
        {});
});