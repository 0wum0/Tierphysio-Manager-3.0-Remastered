document.addEventListener('DOMContentLoaded', function () {
    // Add styles for suggestions
    const style = document.createElement('style');
    style.textContent = ['.suggestions-container {', '    margin: -0.5rem 0 1rem 0;', '    font-size: 0.9rem;', '    color: #666;', '    padding: 0', '}', '.did-you-mean {', '    padding: 0.25rem 0;', '    font-weight: 500;', '    color: var(--danger-500);', '}', '.did-you-mean a {', '    color: #2196F3;', '    text-decoration: none;', '    font-weight: 500;', '}', '.did-you-mean a:hover {', '    text-decoration: underline;', '}'].join('\n');
    document.head.appendChild(style);
    
    // Helper function to safely initialize ListFilter
    function initListFilter(listId, inputId, options) {
        const list = document.querySelector(listId);
        const input = document.querySelector(inputId);
        if (!list || !input) {
            console.warn('ListFilter initialization failed: ' + (!list ? 'List element' : 'Input element') + ' not found');
            return null;
        }
        try {
            // Add suggestions container if it doesn't exist
            const suggestionsContainer = document.createElement('div');
            suggestionsContainer.id = inputId + 'Suggestions';
            suggestionsContainer.className = 'suggestions-container';
            // Find the input-group div and insert the suggestions container after it
            const inputGroup = input.closest('.input-group');
            if (inputGroup && inputGroup.parentNode) {
                inputGroup.parentNode.insertBefore(suggestionsContainer, inputGroup.nextSibling);
            }
            // Add Levenshtein distance function
            function levenshteinDistance(a, b) {
                if (a.length === 0) return b.length;
                if (b.length === 0) return a.length;
                const matrix = Array(b.length + 1).fill(null).map(() => Array(a.length + 1).fill(null));
                for (let i = 0; i <= b.length; i++) matrix[i][0] = i;
                for (let j = 0; j <= a.length; j++) matrix[0][j] = j;
                for (let i = 1; i <= b.length; i++) {
                    for (let j = 1; j <= a.length; j++) {
                        matrix[i][j] = b.charAt(i - 1) === a.charAt(j - 1) ? matrix[i - 1][j - 1] : Math.min(matrix[i - 1][j - 1] + 1, matrix[i][j - 1] + 1, matrix[i - 1][j] + 1);
                    }
                }
                return matrix[b.length][a.length];
            }
            // Add find suggestions function
            function findSuggestions(searchTerm, items) {
                if (!searchTerm || searchTerm.length < 2) return [];
                searchTerm = searchTerm.toLowerCase();
                return items.map(item => {
                    const text = item.querySelector('.nav-link-text')?.textContent.toLowerCase() || '';
                    return {
                        text: text,
                        distance: levenshteinDistance(searchTerm, text)
                    };
                }).filter(item => {
                    const maxDistance = Math.min(Math.floor(searchTerm.length * 0.4), 3);
                    return item.distance > 0 && item.distance <= maxDistance;
                }).sort((a, b) => a.distance - b.distance).slice(0, 3).map(item => item.text);
            }
            // Modify the onFilter callback to include suggestions
            const originalOnFilter = options.onFilter;
            options.onFilter = function (filter) {
                if (originalOnFilter) originalOnFilter(filter);
                const suggestionsContainer = document.getElementById(inputId + 'Suggestions');
                if (!suggestionsContainer) {
                    console.error('Suggestions container not found');
                    return;
                }
                const visibleItems = list.querySelectorAll('li:not(.js-filter-hide)');
                const suggestions = findSuggestions(filter, Array.from(list.querySelectorAll('li')));
                if (filter.length >= 2 && visibleItems.length < 10 && suggestions.length > 0) {
                    // Get the first suggestion and capitalize it
                    const suggestion = suggestions[0];
                    const capitalizedSuggestion = suggestion.charAt(0).toUpperCase() + suggestion.slice(1);
                    suggestionsContainer.innerHTML = ['<div class="did-you-mean">', '    Did you mean: <a href="#" class="suggestion-link">' + capitalizedSuggestion + '</a>', '</div>'].join('\n');
                    // Add click event listener to the suggestion link
                    const suggestionLink = suggestionsContainer.querySelector('.suggestion-link');
                    if (suggestionLink) {
                        suggestionLink.addEventListener('click', function (e) {
                            e.preventDefault();
                            input.value = suggestion;
                            input.dispatchEvent(new Event('input',
                                {
                                    bubbles: true
                                }));
                            suggestionsContainer.innerHTML = '';
                        });
                    }
                }
                else {
                    suggestionsContainer.innerHTML = '';
                }
            };
            return new ListFilter(listId, inputId, options);
        }
        catch (error) {
            console.error('ListFilter initialization error:', error);
            return null;
        }
    }
    // File Explorer Example with Nested Structure
    initListFilter('#fileExplorer', '#fileFilterInput',
        {
            messageSelector: '#fileFilterMessage',
            debounceWait: 150,
            minLength: 1,
            onFilter: function (filter) {
                console.log('File filter:', filter);
            }
        });
    // Did you mean suggestions example
    initListFilter('#suggestionsNav', '#suggestionsFilterInput',
        {
            messageSelector: '#suggestionsFilterMessage',
            debounceWait: 200,
            minLength: 1,
            onFilter: function (filter) {
                console.log('Suggestions filter:', filter);
            }
        });
});