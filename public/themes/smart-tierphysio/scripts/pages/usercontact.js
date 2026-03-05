document.addEventListener('DOMContentLoaded', function() {
    // Use direct filtering implementation instead of SmartFilter
    implementDirectFiltering();
    
    // Function to remove classes with a specific prefix
    function removeClassPrefix(elements, prefix) {
        elements.forEach(element => {
            const classes = Array.from(element.classList);
            classes.forEach(cls => {
                if (cls.startsWith(prefix)) {
                    element.classList.remove(cls);
                }
            });
        });
    }

    // Select all radio inputs with name="contactview"
    const radioButtons = document.querySelectorAll('input[type="radio"][name="contactview"]');
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            const jsContacts = document.querySelector('#js-contacts');
            const cards = jsContacts.querySelectorAll('.card');
            const colXlElements = jsContacts.querySelectorAll('[class*="col-xl-"]');
            const expandButtons = jsContacts.querySelectorAll('.js-expand-btn');
            const doubleCardBodies = jsContacts.querySelectorAll('.card-body + .card-body');

            if (this.value === 'grid') {
                // Handle cards
                removeClassPrefix(cards, 'mb-');
                cards.forEach(card => card.classList.add('mb-g'));

                // Handle col-xl classes
                removeClassPrefix(colXlElements, 'col-xl-');
                colXlElements.forEach(el => el.classList.add('col-xl-4'));

                // Handle expand buttons
                expandButtons.forEach(btn => btn.classList.add('d-none'));

                // Handle double card bodies
                doubleCardBodies.forEach(body => body.classList.add('show'));
            } 
            else if (this.value === 'table') {
                // Handle cards
                removeClassPrefix(cards, 'mb-');
                cards.forEach(card => card.classList.add('mb-1'));

                // Handle col-xl classes
                removeClassPrefix(colXlElements, 'col-xl-');
                colXlElements.forEach(el => el.classList.add('col-xl-12'));

                // Handle expand buttons
                expandButtons.forEach(btn => btn.classList.remove('d-none'));

                // Handle double card bodies
                doubleCardBodies.forEach(body => body.classList.remove('show'));
            }
        });
    });

    // Direct filtering implementation that doesn't rely on SmartFilter
    function implementDirectFiltering() {
        const filterInput = document.getElementById('js-filter-contacts');
        const clearBtn = document.getElementById('js-clear-filter');
        const counterEl = document.getElementById('filter-result-counter');
        
        // Input filtering
        filterInput.addEventListener('input', function() {
            const filterValue = this.value.toLowerCase();
            const cards = document.querySelectorAll('#js-contacts .card');
            const columns = document.querySelectorAll('#js-contacts [class*="col-xl-"]');
            let visibleCount = 0;
            
            // First, hide all columns
            columns.forEach(col => {
                col.style.display = 'none';
            });
            
            // Filter cards based on their data-filter-tags attribute
            cards.forEach(card => {
                const filterTags = card.getAttribute('data-filter-tags') || '';
                const parentColumn = card.closest('[class*="col-xl-"]');
                
                if (filterValue === '' || filterTags.toLowerCase().includes(filterValue)) {
                    if (parentColumn) {
                        parentColumn.style.display = ''; // Show the column
                    }
                    visibleCount++;
                }
            });
            
            // Update UI
            if (filterValue) {
                counterEl.textContent = `Showing ${visibleCount} of ${cards.length} contacts`;
                filterInput.classList.add('border-primary');
                clearBtn.classList.remove('d-none');
            } else {
                counterEl.textContent = '';
                filterInput.classList.remove('border-primary');
                clearBtn.classList.add('d-none');
            }
        });
        
        // Clear button functionality
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            filterInput.value = '';
            
            // Show all columns
            const columns = document.querySelectorAll('#js-contacts [class*="col-xl-"]');
            columns.forEach(col => {
                col.style.display = '';
            });
            
            // Reset UI
            counterEl.textContent = '';
            filterInput.classList.remove('border-primary');
            clearBtn.classList.add('d-none');
        });
        
        // Set up keyboard events for convenience
        filterInput.addEventListener('keydown', function(e) {
            // Clear on Escape key
            if (e.key === 'Escape') {
                e.preventDefault();
                this.value = '';
                clearBtn.click();
            }
        });
    }
});