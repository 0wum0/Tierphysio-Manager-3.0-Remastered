import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';

// Wait for the DOM to load before initializing
document.addEventListener('DOMContentLoaded', () => {
    // DOM elements
    const tableContainer = document.getElementById('tableContainer');
    const thresholdSlider = document.getElementById('threshold');
    const thresholdValue = document.getElementById('thresholdValue');
    const minMatchLengthSlider = document.getElementById('minMatchLength');
    const minMatchLengthValue = document.getElementById('minMatchLengthValue');
    const multiWordThresholdSlider = document.getElementById('multiWordThreshold');
    const multiWordThresholdValue = document.getElementById('multiWordThresholdValue');
    const maxDistanceSlider = document.getElementById('maxDistance');
    const maxDistanceValue = document.getElementById('maxDistanceValue');
    const descriptionEl = document.getElementById('description');
    const buildTableBtn = document.getElementById('buildTable');
    const destroyTableBtn = document.getElementById('destroyTable');
    const searchExample = document.getElementById('searchExample');
    const resetBtn = document.getElementById('resetSettings');

    // Table instance
    let table = null;
    
    // Initialize sliders with default values and ranges
    thresholdSlider.min = 0;
    thresholdSlider.max = 1;
    thresholdSlider.step = 0.1;
    thresholdSlider.value = 0;
    
    minMatchLengthSlider.min = 1;
    minMatchLengthSlider.max = 5;
    minMatchLengthSlider.step = 1;
    minMatchLengthSlider.value = 1;
    
    multiWordThresholdSlider.min = 0;
    multiWordThresholdSlider.max = 1;
    multiWordThresholdSlider.step = 0.1;
    multiWordThresholdSlider.value = 1;
    
    maxDistanceSlider.min = 0;
    maxDistanceSlider.max = 10;
    maxDistanceSlider.step = 1;
    maxDistanceSlider.value = 0;
    
    // Function to toggle controls state
    function toggleControlsState(disabled) {
        // Disable/enable all sliders
        thresholdSlider.disabled = disabled;
        minMatchLengthSlider.disabled = disabled;
        multiWordThresholdSlider.disabled = disabled;
        maxDistanceSlider.disabled = disabled;
        resetBtn.disabled = disabled;
    }
    
    // Update slider display values
    function updateSliderValues() {
        thresholdValue.textContent = thresholdSlider.value;
        minMatchLengthValue.textContent = minMatchLengthSlider.value;
        multiWordThresholdValue.textContent = multiWordThresholdSlider.value;
        maxDistanceValue.textContent = maxDistanceSlider.value;
        
        updateDescription();
    }
    
    // Provide an explanation based on current settings
    function updateDescription() {
        const threshold = parseFloat(thresholdSlider.value);
        const minMatchLength = parseInt(minMatchLengthSlider.value);
        const multiWordThreshold = parseFloat(multiWordThresholdSlider.value);
        const maxDistance = parseInt(maxDistanceSlider.value);
        
        let desc = "";
        
        // Threshold description
        if (threshold === 0) {
            desc += "• <strong>Exact matching only</strong> - No partial matches<br>";
        } else if (threshold < 0.3) {
            desc += "• <strong>Minor fuzzy matching</strong> - Requires high similarity<br>";
        } else if (threshold < 0.6) {
            desc += "• <strong>Moderate fuzzy matching</strong> - Allows reasonable variations<br>";
        } else if (threshold < 1) {
            desc += "• <strong>High fuzzy matching</strong> - Will find loosely related terms<br>";
        } else {
            desc += "• <strong>Maximum fuzzy matching</strong> - Will track down distant relatives like an overenthusiastic family reunion<br>";
        }
        
        // Min Match Length description
        if (minMatchLength === 1) {
            desc += "• Will match even with a <strong>single character</strong><br>";
        } else {
            desc += "• Requires at least <strong>" + minMatchLength + " characters</strong> to match<br>";
        }
        
        // Multi Word Threshold description
        if (multiWordThreshold === 1) {
            desc += "• <strong>All words</strong> in a multi-word search must match<br>";
        } else if (multiWordThreshold > 0.5) {
            desc += "• <strong>Most words</strong> in a multi-word search must match<br>";
        } else if (multiWordThreshold > 0) {
            desc += "• <strong>Some words</strong> in a multi-word search must match<br>";
        } else {
            desc += "• <strong>Any word</strong> in a multi-word search can match<br>";
        }
        
        // Max Distance description
        if (maxDistance === 0) {
            desc += "• <strong>No typo tolerance</strong> - Characters must match exactly<br>";
        } else if (maxDistance === 1) {
            desc += "• <strong>Single typo tolerance</strong> - Allows 1 character mismatch<br>";
        } else if (maxDistance < 5) {
            desc += "• <strong>Moderate typo tolerance</strong> - Allows " + maxDistance + " character mismatches<br>";
        } else {
            desc += "• <strong>High typo tolerance</strong> - Allows many character mismatches<br>";
        }
        
        // Example
        let example = "";
        if (threshold === 0 && maxDistance === 0) {
            example = "Try searching for exact terms like 'John' or 'Do'";
        } else if (threshold > 0 || maxDistance > 0) {
            if (maxDistance > 0) {
                example = "Try searches with typos like 'Jhon' instead of 'John' or 'Subrey' instead of 'Surgery'";
            } else {
                example = "Try partial searches like 'Jo' for 'John' or 'Surg' for 'Surgery'";
            }
        }
        searchExample.textContent = example;
        
        descriptionEl.innerHTML = desc;
    }
    
    // Add event listeners to sliders
    thresholdSlider.addEventListener('input', updateSliderValues);
    minMatchLengthSlider.addEventListener('input', updateSliderValues);
    multiWordThresholdSlider.addEventListener('input', updateSliderValues);
    maxDistanceSlider.addEventListener('input', updateSliderValues);
    
    // Reset settings to default values
    resetBtn.addEventListener('click', () => {
        thresholdSlider.value = 0.7;
        minMatchLengthSlider.value = 2;
        multiWordThresholdSlider.value = 0.5;
        maxDistanceSlider.value = 2;
        updateSliderValues();
    });
    
    // Build table with current settings
    buildTableBtn.addEventListener('click', () => {
        // If table already exists, destroy it first
        if (table) {
            table.destroy();
            table = null;
        }
        
        // Get current slider values
        const fuzzySettings = {
            threshold: parseFloat(thresholdSlider.value),
            minMatchLength: parseInt(minMatchLengthSlider.value),
            multiWordThreshold: parseFloat(multiWordThresholdSlider.value),
            maxDistance: parseInt(maxDistanceSlider.value)
        };
        
        // Log settings to confirm values
        console.log('Building table with fuzzy search settings:', fuzzySettings);
        
        // Show table container
        tableContainer.classList.remove('d-none');
        
        // Initialize SmartTables with the table ID and fuzzy settings
        table = new SmartTables('myTable', {
            data: {
                type: "json",
                source: "json/MOCK_DATA_HOSPITAL.json",
                columns: [
                    { data: "PatientID", title: "ID" },
                    { data: "PatientName", title: "First Name" },
                    { data: "Phone", title: "Phone" },
                    { data: "DOB", title: "DOB" },
                    { data: "Service", title: "Service" },
                    { data: "ServiceDate", title: "Service Date" },
                    { 
                        data: "Severity", 
                        title: "Severity",
                        render: function(value) {
                            let severityClass = {
                                "Mild": "badge bg-success",
                                "Moderate": "badge bg-warning text-dark",
                                "Severe": "badge bg-danger"
                            }[value] || "badge bg-dark";
                            return '<span class="' + severityClass + '">' + value + '</span>';
                        }
                    },
                    { data: "BillPaid", title: "Bill Paid" },
                    { data: "BillDue", title: "Bill Due" },
                    { data: "Department", title: "Department" },
                    { data: "Staff", title: "Doctor" },
                    { data: "Nurse", title: "Nurse" },
                ]
            },
            fuzzyMatch: fuzzySettings,
            perPage: 6,
            search: true,
            sort: true,
            pagination: true,
            debug: false,
            loading: {
                enabled: true,
                duration: 800
            },
            responsive: {
                enabled: true,
                breakpoint: 768,
                columnPriorities: {
                    0: 1,  // ID - highest priority (never hide)
                    1: 2,  // First Name - second highest priority
                    2: 3,  // Last Name - third priority
                    3: 4,  // Gender - fourth priority
                    4: 5,  // Phone - fifth priority
                    5: 6,  // DOB - sixth priority
                    6: 7,  // Service - seventh priority
                    7: 8,  // Service Date - eighth priority
                    8: 9,  // Severity - ninth priority
                    9: 10,  // Bill Paid - tenth priority
                    10: 11,  // Bill Due - eleventh priority
                    11: 12   // Department - twelfth priority
                }
            }
        });
        
        // Update button states
        buildTableBtn.disabled = true;
        destroyTableBtn.disabled = false;

        // Disable all fuzzy settings controls
        toggleControlsState(true);
        
        // Log the actual fuzzy settings used by the table
        console.log('Table created with actual fuzzy settings:', table.options.fuzzyMatch);
        
        // Add additional verification for search functionality
        // setTimeout(() => {
        //     console.log('Verifying table search functionality:');
        //     console.log('- Search method type:', typeof table.handleSearch);
        //     console.log('- Fuzzy match method type:', typeof table.fuzzyMatch);
        //     console.log('- Is server-side:', table.options.data.serverSide);
            
        //     // Log detailed fuzzy search settings
        //     console.log('DETAILED FUZZY SEARCH SETTINGS:');
        //     console.log('- Threshold:', table.options.fuzzyMatch.threshold, '(0-1, higher = more fuzzy)');
        //     console.log('- Min Match Length:', table.options.fuzzyMatch.minMatchLength, '(min characters to match)');
        //     console.log('- Multi-word Threshold:', table.options.fuzzyMatch.multiWordThreshold, '(0-1, lower = more lenient)');
        //     console.log('- Max Distance:', table.options.fuzzyMatch.maxDistance, '(max character mismatches allowed)');
            
        //     // Add a test search to demonstrate fuzzy matching
        //     const testSearchTerms = ["john", "jon", "medical", "surgery", "surgry"];
        //     console.log('FUZZY SEARCH TEST EXAMPLES:');
        //     testSearchTerms.forEach(term => {
        //         console.log(`Testing search term: "${term}"`);
        //         // Create a temp input to simulate search
        //         const tempInput = document.createElement('input');
        //         tempInput.value = term;
        //         // Call the search method directly
        //         const searchFunction = table.handleSearch.bind(table);
        //         searchFunction(term);
        //         console.log(`Results found: ${table.filteredRows.length} rows`);
        //     });
        // }, 1000);
    });
    
    // Destroy table
    destroyTableBtn.addEventListener('click', () => {
        if (table) {
            table.destroy();
            table = null;
        }
        tableContainer.classList.add('d-none');
        
        // Update button states
        buildTableBtn.disabled = false;
        destroyTableBtn.disabled = true;

        // Re-enable all fuzzy settings controls
        toggleControlsState(false);
    });
    
    // Initialize slider values and description
    updateSliderValues();
    
    // Disable destroy button initially since no table exists
    destroyTableBtn.disabled = true;
});