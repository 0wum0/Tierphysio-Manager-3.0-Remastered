(function() {
    // Function to create the tree view
    function createTreeView(data, container, level) {
        var treeItem = document.createElement('div');
        treeItem.className = 'tree-item ' + (data.type === 'directory' ? 'directory-item' : 'file-item');
        
        // Auto-expand directories up to level 4
        var isExpanded = data.type === 'directory' && level <= 2;
        if (isExpanded) {
            treeItem.className += ' expanded';
        } else if (data.type === 'directory') {
            treeItem.className += ' collapsed';
        }

        var itemContent = document.createElement('div');
        itemContent.className = 'tree-item-content';

        var toggleIcon = document.createElement('span');
        toggleIcon.className = 'toggle-icon';
        itemContent.appendChild(toggleIcon);

        var icon = document.createElement('span');
        icon.className = 'tree-item-icon ' + (data.type === 'directory' ? 'directory-icon' : 'file-icon');
        icon.innerHTML = data.type === 'directory' ? '📁' : '📄';
        itemContent.appendChild(icon);

        var name = document.createElement('span');
        name.className = 'tree-item-name';
        name.textContent = data.name;
        itemContent.appendChild(name);

        if (data.path) {
            var path = document.createElement('span');
            path.className = 'path';
            path.textContent = data.path;
            itemContent.appendChild(path);
        }

        treeItem.appendChild(itemContent);

        if (data.type === 'directory' && data.children && data.children.length > 0) {
            var childrenContainer = document.createElement('div');
            childrenContainer.className = 'tree-item-children';
            
            // Sort children: directories first, then files, both alphabetically
            var sortedChildren = data.children.slice();
            sortedChildren.sort(function(a, b) {
                if (a.type === b.type) {
                    return a.name.localeCompare(b.name);
                }
                return a.type === 'directory' ? -1 : 1;
            });
            
            for (var i = 0; i < sortedChildren.length; i++) {
                createTreeView(sortedChildren[i], childrenContainer, level + 1);
            }
            
            treeItem.appendChild(childrenContainer);
            
            // Add click event to toggle directory
            itemContent.addEventListener('click', function(e) {
                e.stopPropagation();
                if (treeItem.classList.contains('expanded')) {
                    treeItem.classList.remove('expanded');
                    treeItem.classList.add('collapsed');
                } else {
                    treeItem.classList.remove('collapsed');
                    treeItem.classList.add('expanded');
                }
            });
        }

        container.appendChild(treeItem);
    } 

    // Function to load directory data using AJAX
    function loadDirectoryData() {
        var treeViewContainer = document.getElementById('tree-view');
        var xhr = new XMLHttpRequest();
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        var directoryData = JSON.parse(xhr.responseText);
                        treeViewContainer.innerHTML = '';
                        createTreeView(directoryData, treeViewContainer, 1);
                    } catch (e) {
                        treeViewContainer.innerHTML = '<div class="error">Error parsing JSON data: ' + e.message + '</div>';
                    }
                } else {
                    treeViewContainer.innerHTML = '<div class="error">Failed to load directory data. Status: ' + xhr.status + '</div>';
                }
            }
        };
        
        xhr.open('GET', 'json/directory-tree.json', true);
        xhr.send();
    }

    // Load the directory data when the page loads
    window.addEventListener('load', loadDirectoryData);
})();

