var NotesApp = {
    currentNoteId: null,
    autoSaveTimeout: null,
    activeFormatting: {
        bold: false,
        italic: false,
        underline: false
    },

    init: function() {
        this.renderNotesList();
        this.setupAutoSave();
        this.setupKeyboardShortcuts();
        this.setupEditorEvents();
    },

    setupAutoSave: function() {
        var self = this;
        document.getElementById('note-content').addEventListener('input', function() {
            self.scheduleAutoSave();
            self.updateNoteTitle();
        });
    },

    setupEditorEvents: function() {
        var self = this;
        var editor = document.getElementById('note-content');
        
        // Monitor formatting changes
        editor.addEventListener('keyup', function(e) {
            self.checkActiveFormatting();
            
            // When Enter is pressed, make sure new paragraph has normal styling
            if (e.key === 'Enter') {
                self.normalizeNewParagraph();
            }
        });
        
        editor.addEventListener('click', function() {
            self.checkActiveFormatting();
        });
        
        // Initial input handling
        editor.addEventListener('input', function(e) {
            // If this is a new note (empty), format the first line as it's typed
            if (editor.childNodes.length === 1 && editor.firstChild.nodeType === Node.TEXT_NODE) {
                // Wrap text in a paragraph with heading style
                const p = document.createElement('h3');
                p.style.fontWeight = 'bold';
                p.style.fontSize = '1.2em';
                p.style.marginBottom = '0.5em';
                p.appendChild(editor.firstChild);
                editor.appendChild(p);
                
                // Place cursor at the end
                const range = document.createRange();
                const sel = window.getSelection();
                range.setStart(p.firstChild, p.firstChild.textContent.length);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        });
    },
    
    checkActiveFormatting: function() {
        try {
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                const parent = range.commonAncestorContainer;
                
                // Check for formatting by analyzing the parent elements
                this.activeFormatting.bold = this.hasParentWithStyle(parent, 'fontWeight', 'bold') || 
                                              this.hasParentWithTag(parent, 'B') || 
                                              this.hasParentWithTag(parent, 'STRONG');
                                              
                this.activeFormatting.italic = this.hasParentWithStyle(parent, 'fontStyle', 'italic') || 
                                                this.hasParentWithTag(parent, 'I') || 
                                                this.hasParentWithTag(parent, 'EM');
                                                
                this.activeFormatting.underline = this.hasParentWithStyle(parent, 'textDecoration', 'underline') || 
                                                  this.hasParentWithTag(parent, 'U');
            }
        } catch (e) {
            // Use simpler DOM-based detection instead of queryCommandState
            console.warn("Selection-based formatting detection failed:", e);
            
            // Default to inactive formatting
            this.activeFormatting.bold = false;
            this.activeFormatting.italic = false;
            this.activeFormatting.underline = false;
            
            // Try to detect based on current cursor position
            try {
                const editor = document.getElementById('note-content');
                if (editor) {
                    // Check for formatting by looking at styles around cursor
                    const selectedNode = document.getSelection().focusNode || editor;
                    
                    this.activeFormatting.bold = this.isNodeFormatted(selectedNode, 'bold');
                    this.activeFormatting.italic = this.isNodeFormatted(selectedNode, 'italic');
                    this.activeFormatting.underline = this.isNodeFormatted(selectedNode, 'underline');
                }
            } catch (innerError) {
                console.error("Fallback formatting detection failed:", innerError);
            }
        }
        
        // Update UI to reflect active formatting
        this.updateFormattingButtons();
    },
    
    isNodeFormatted: function(node, format) {
        if (!node || node.nodeType !== Node.ELEMENT_NODE) {
            if (node && node.parentNode) {
                return this.isNodeFormatted(node.parentNode, format);
            }
            return false;
        }
        
        const element = node;
        
        switch(format) {
            case 'bold':
                return element.tagName === 'B' || 
                       element.tagName === 'STRONG' || 
                       window.getComputedStyle(element).fontWeight === 'bold' ||
                       parseInt(window.getComputedStyle(element).fontWeight) >= 700;
            case 'italic':
                return element.tagName === 'I' || 
                       element.tagName === 'EM' || 
                       window.getComputedStyle(element).fontStyle === 'italic';
            case 'underline':
                return element.tagName === 'U' || 
                       window.getComputedStyle(element).textDecoration.includes('underline');
            default:
                return false;
        }
    },
    
    hasParentWithStyle(node, style, value) {
        while (node && node.nodeType === 1) {
            const computedStyle = window.getComputedStyle(node);
            if (computedStyle[style] === value || 
                (style === 'textDecoration' && computedStyle[style].includes(value))) {
                return true;
            }
            node = node.parentNode;
        }
        return false;
    },
    
    hasParentWithTag(node, tagName) {
        while (node && node.nodeType === 1) {
            if (node.tagName === tagName) {
                return true;
            }
            node = node.parentNode;
        }
        return false;
    },

    updateFormattingButtons: function() {
        document.querySelectorAll('.formatting-btn').forEach(btn => {
            const format = btn.getAttribute('data-format');
            if (this.activeFormatting[format]) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    },

    setupKeyboardShortcuts: function() {
        document.addEventListener('keydown', function(e) {
            if (!NotesApp.currentNoteId) return;
            
            if (e.ctrlKey) {
                switch(e.key.toLowerCase()) {
                    case 'b':
                        e.preventDefault();
                        NotesApp.formatText('bold');
                        break;
                    case 'i':
                        e.preventDefault();
                        NotesApp.formatText('italic');
                        break;
                    case 'u':
                        e.preventDefault();
                        NotesApp.formatText('underline');
                        break;
                }
            }
        });
    },

    scheduleAutoSave: function() {
        var self = this;
        clearTimeout(this.autoSaveTimeout);
        this.autoSaveTimeout = setTimeout(function() {
            self.saveCurrentNote();
        }, 500);
    },

    getAllNotes: function() {
        try {
            var notes = localStorage.getItem('notes');
            return notes ? JSON.parse(notes) : {};
        } catch (error) {
            console.error('Error reading notes from localStorage:', error);
            return {};
        }
    },

    sanitizeHtml: function(html) {
        // Check if DOMPurify is available
        if (typeof DOMPurify !== 'undefined') {
            return DOMPurify.sanitize(html, {
                ALLOWED_TAGS: ['b', 'i', 'u', 'strong', 'em', 'h6', 'p', 'br', 'div', 'span'],
                ALLOWED_ATTR: ['style']
            });
        } else {
            // Fallback sanitization - very basic, not as secure
            console.warn('DOMPurify not loaded. Using basic sanitization fallback.');
            
            // Create a temporary div to handle the HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            
            // Remove potentially dangerous elements
            const scripts = tempDiv.querySelectorAll('script, iframe, object, embed, form, input, button, textarea, style, link, meta');
            scripts.forEach(element => element.remove());
            
            // Remove potentially dangerous attributes
            const allElements = tempDiv.querySelectorAll('*');
            for (let i = 0; i < allElements.length; i++) {
                const element = allElements[i];
                const attrs = element.attributes;
                
                for (let j = attrs.length - 1; j >= 0; j--) {
                    const attr = attrs[j];
                    const attrName = attr.name.toLowerCase();
                    
                    // Keep only style attribute
                    if (attrName !== 'style' && 
                        (attrName.startsWith('on') || 
                         attrName === 'href' || 
                         attrName === 'src' || 
                         attrName === 'srcset' || 
                         attrName === 'data')) {
                        element.removeAttribute(attr.name);
                    }
                }
            }
            
            return tempDiv.innerHTML;
        }
    },

    extractTitle: function(content) {
        if (!content) {
            return 'Untitled Note';
        }
        
        try {
            const div = document.createElement('div');
            div.innerHTML = content;
            
            // Look for the first heading or paragraph
            const firstElement = div.querySelector('h1, h2, h3, h4, h5, h6, p, div');
            
            if (firstElement) {
                const title = firstElement.textContent.trim();
                return title.substring(0, 50) + (title.length > 50 ? '...' : '');
            }
            
            // Fallback to first line if no elements found
            const firstLine = div.textContent.split('\n')[0] || 'Untitled Note';
            return firstLine.substring(0, 50) + (firstLine.length > 50 ? '...' : '');
        } catch (error) {
            console.error('Error extracting title:', error);
            return 'Untitled Note';
        }
    },

    updateNoteTitle: function() {
        if (!this.currentNoteId) return;
        
        const content = document.getElementById('note-content').innerHTML;
        const title = this.extractTitle(content);
        
        var notes = this.getAllNotes();
        if (notes[this.currentNoteId]) {
            notes[this.currentNoteId].title = title;
            localStorage.setItem('notes', JSON.stringify(notes));
            this.renderNotesList();
        }
    },

    saveNote: function(noteId, content) {
        if (!noteId) {
            console.error('Cannot save note: Missing note ID');
            return false;
        }
        
        // Don't save if content is empty or just the placeholder
        const cleanContent = this.cleanContent(content);
        if (!cleanContent) {
            return false;
        }
        
        try {
            var notes = this.getAllNotes();
            const sanitizedContent = this.sanitizeHtml(content);
            notes[noteId] = {
                id: noteId,
                title: this.extractTitle(sanitizedContent),
                content: sanitizedContent,
                lastModified: new Date().toISOString()
            };
            localStorage.setItem('notes', JSON.stringify(notes));
            return true;
        } catch (error) {
            console.error('Error saving note:', error);
            return false;
        }
    },
    
    cleanContent: function(content) {
        // Strip all HTML tags to check if there's actual content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = content;
        const textContent = tempDiv.textContent.trim();
        
        return textContent.length > 0 ? content : '';
    },

    deleteNote: function(noteId) {
        if (confirm('Are you sure you want to delete this note?')) {
            var notes = this.getAllNotes();
            delete notes[noteId];
            localStorage.setItem('notes', JSON.stringify(notes));
            this.renderNotesList();
        }
    },
    
    clearAllNotes: function() {
        if (confirm('Are you sure you want to delete ALL notes? This cannot be undone.')) {
            localStorage.removeItem('notes');
            this.renderNotesList();
        }
    },

    createNewNote: function() {
        var noteId = 'note_' + new Date().getTime();
        this.currentNoteId = noteId;
        
        // Create an empty note first
        var notes = this.getAllNotes();
        notes[noteId] = {
            id: noteId,
            title: 'Untitled Note',
            content: '',
            lastModified: new Date().toISOString()
        };
        localStorage.setItem('notes', JSON.stringify(notes));
        
        // Now show the editor
        this.showNoteEditor(noteId);
    },

    showNoteEditor: function(noteId) {
        var notes = this.getAllNotes();
        var note = notes[noteId];
        
        // Safety check to ensure the note exists
        if (!note) {
            console.error('Note not found:', noteId);
            return;
        }
        
        document.getElementById('note-content').innerHTML = note.content;
        
        document.getElementById('notes-list-view').style.display = 'none';
        document.getElementById('note-edit-view').style.display = 'block';
        
        this.currentNoteId = noteId;
        document.getElementById('note-content').focus();
        
        // Ensure the first line is properly formatted as a heading
        this.ensureFirstLineIsHeading();
        
        // Check formatting when opening a note
        this.checkActiveFormatting();
    },

    showNotesList: function() {
        document.getElementById('notes-list-view').style.display = 'block';
        document.getElementById('note-edit-view').style.display = 'none';
        this.currentNoteId = null;
        this.renderNotesList();
    },

    saveCurrentNote: function() {
        if (!this.currentNoteId) return;
        
        var content = document.getElementById('note-content').innerHTML;
        
        // Get notes and check if currentNoteId exists
        var notes = this.getAllNotes();
        if (!notes[this.currentNoteId]) {
            console.error('Cannot save note: Note ID not found in storage', this.currentNoteId);
            return;
        }
        
        // Only update the UI if the save was successful (not empty)
        if (this.saveNote(this.currentNoteId, content)) {
            this.renderNotesList();
        }
    },

    formatText: function(format) {
        try {
            const editor = document.getElementById('note-content');
            const selection = window.getSelection();
            
            if (selection.rangeCount === 0) {
                editor.focus();
                return;
            }
            
            const range = selection.getRangeAt(0);
            
            if (range.collapsed) {
                // If no text is selected, don't apply formatting
                editor.focus();
                return;
            }
            
            // Apply the formatting with appropriate HTML elements
            let wrapperTag;
            switch(format) {
                case 'bold':
                    wrapperTag = document.createElement('strong');
                    break;
                case 'italic':
                    wrapperTag = document.createElement('em');
                    break;
                case 'underline':
                    wrapperTag = document.createElement('u');
                    break;
                default:
                    // For unsupported formats, use alternative method
                    console.warn(`Format '${format}' is not directly supported`);
                    editor.focus();
                    this.scheduleAutoSave();
                    this.checkActiveFormatting();
                    return;
            }
            
            // Get existing formatting state
            const isFormatted = this.activeFormatting[format];
            
            if (isFormatted) {
                // If already formatted, remove the formatting by unwrapping content
                this.removeFormatting(selection, format);
            } else {
                // Apply new formatting - surround with appropriate tag
                const fragment = range.extractContents();
                wrapperTag.appendChild(fragment);
                range.insertNode(wrapperTag);
                
                // Preserve selection
                selection.removeAllRanges();
                const newRange = document.createRange();
                newRange.selectNodeContents(wrapperTag);
                selection.addRange(newRange);
            }
            
            editor.focus();
            this.scheduleAutoSave();
            this.checkActiveFormatting();
        } catch (e) {
            // Fallback to simplified approach if error occurs
            console.warn("Modern formatting failed:", e);
            
            // Create a temporary error notification
            const editor = document.getElementById('note-content');
            const notification = document.createElement('div');
            notification.textContent = "Formatting could not be applied at this time.";
            notification.style.cssText = "position:absolute; top:10px; right:10px; background:#fff3cd; color:#856404; padding:10px; border-radius:5px; z-index:1000;";
            document.body.appendChild(notification);
            
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 3000);
            
            editor.focus();
            this.scheduleAutoSave();
            this.checkActiveFormatting();
        }
    },

    removeFormatting: function(selection, format) {
        if (selection.rangeCount === 0) return;
        
        const range = selection.getRangeAt(0);
        let targetNodes = [];
        
        // Find all nodes with the specified format within the selection
        const findFormattedNodes = (node) => {
            if (!node) return;
            
            // Check if this node has the formatting we want to remove
            let isFormatted = false;
            
            switch(format) {
                case 'bold':
                    isFormatted = node.nodeName === 'STRONG' || node.nodeName === 'B';
                    break;
                case 'italic':
                    isFormatted = node.nodeName === 'EM' || node.nodeName === 'I';
                    break;
                case 'underline':
                    isFormatted = node.nodeName === 'U';
                    break;
            }
            
            if (isFormatted) {
                targetNodes.push(node);
                return;
            }
            
            // If not a match, check children
            if (node.hasChildNodes()) {
                Array.from(node.childNodes).forEach(child => findFormattedNodes(child));
            }
        };
        
        // Start from the common ancestor container
        findFormattedNodes(range.commonAncestorContainer);
        
        // If we didn't find any direct nodes, look at the selection
        if (targetNodes.length === 0) {
            const fragment = range.cloneContents();
            findFormattedNodes(fragment);
            
            // If we found formatting in the fragment, we need to unwrap in the actual DOM
            // We'll use the simpler approach of just replacing with text content
            if (targetNodes.length > 0) {
                const contentText = range.toString();
                range.deleteContents();
                range.insertNode(document.createTextNode(contentText));
                
                // Reset selection to the inserted text
                selection.removeAllRanges();
                selection.addRange(range);
                return;
            }
        }
        
        // Unwrap each formatted node (replace it with its contents)
        targetNodes.forEach(node => {
            if (node.parentNode) {
                while (node.firstChild) {
                    node.parentNode.insertBefore(node.firstChild, node);
                }
                node.parentNode.removeChild(node);
            }
        });
    },

    renderNotesList: function() {
        var notes = this.getAllNotes();
        var container = document.getElementById('notes-container');
        
        if (Object.keys(notes).length === 0) {
            container.innerHTML = '<p class="text-center text-muted mt-4">No notes yet. Create one by clicking the <u>New Note</u> button!</p>';
            return;
        }
        
        // Sort notes by date (newest first)
        var sortedNoteIds = Object.keys(notes).sort(function(a, b) {
            return new Date(notes[b].lastModified) - new Date(notes[a].lastModified);
        });
        
        // Group notes by date
        var groupedNotes = {};
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        var yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        sortedNoteIds.forEach(function(noteId) {
            var note = notes[noteId];
            var noteDate = new Date(note.lastModified);
            noteDate.setHours(0, 0, 0, 0);
            
            var dateKey;
            if (noteDate.getTime() === today.getTime()) {
                dateKey = 'Today';
            } else if (noteDate.getTime() === yesterday.getTime()) {
                dateKey = 'Yesterday';
            } else {
                dateKey = noteDate.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
            
            if (!groupedNotes[dateKey]) {
                groupedNotes[dateKey] = [];
            }
            
            groupedNotes[dateKey].push(note);
        });
        
        // Build HTML for grouped notes
        var notesHtml = '';
        
        Object.keys(groupedNotes).forEach(function(dateGroup) {
            notesHtml += `<h6 class="mt-2 mb-3">${dateGroup}</h6>`;
            
            groupedNotes[dateGroup].forEach(function(note) {
                // Create a temporary div to parse the content
                var contentDiv = document.createElement('div');
                contentDiv.innerHTML = note.content;
                
                var title = '';
                var contentText = '';
                
                // Extract title from the first heading or paragraph
                var firstElement = contentDiv.querySelector('h1, h2, h3, h4, h5, h6, p, div');
                
                if (firstElement) {
                    // Use the first element as the title
                    title = firstElement.textContent.trim();
                    
                    // Remove the first element from consideration for content
                    firstElement.remove();
                    
                    // Get content from the remaining elements
                    contentText = contentDiv.textContent.trim();
                } else {
                    // Fallback: use the note's title property
                    title = note.title || 'Untitled Note';
                }
                
                // Trim and limit the preview
                var preview = contentText.substring(0, 100) + (contentText.length > 100 ? '...' : '');
                
                // If there's no content after the title, show a placeholder preview
                if (!preview) {
                    preview = '<span class="text-muted fst-italic">No additional content</span>';
                }
                
                notesHtml += `
                    <div class="info-container p-3 mb-2 note-item position-relative rounded">
                        <div class="d-flex w-100" onclick="NotesApp.showNoteEditor('${note.id}')">
                            <div class="w-100">
                                <h6 class="mb-1 text-break">${title}</h6>
                                <p class="mb-0 text-muted preview-text fs-sm text-break">${preview}</p>
                            </div>
                        </div>
                        <button class="btn btn-outline-danger btn-xs position-absolute end-0 top-0 m-2 rounded-pill shadow-0" 
                            onclick="event.stopPropagation(); NotesApp.deleteNote('${note.id}')">
                            <i class="fal fa-trash"></i>
                        </button>
                    </div>`;
            });
        });
        
        container.innerHTML = notesHtml;
    },

    normalizeNewParagraph: function() {
        const editor = document.getElementById('note-content');
        const selection = window.getSelection();
        if (!selection.rangeCount) return;
        
        const range = selection.getRangeAt(0);
        const currentNode = range.startContainer;
        
        // Find the current paragraph
        let currentParagraph = currentNode;
        while (currentParagraph && currentParagraph.nodeType !== Node.ELEMENT_NODE) {
            currentParagraph = currentParagraph.parentNode;
        }
        
        // If this is not the first paragraph, ensure it has normal styling
        if (currentParagraph && currentParagraph.previousElementSibling) {
            // Remove heading styling if present
            currentParagraph.style.fontWeight = 'normal';
            currentParagraph.style.fontSize = '1em';
            
            // If it's an H tag, convert to a paragraph
            if (currentParagraph.tagName.match(/^H[1-6]$/)) {
                const newP = document.createElement('p');
                while (currentParagraph.firstChild) {
                    newP.appendChild(currentParagraph.firstChild);
                }
                currentParagraph.parentNode.replaceChild(newP, currentParagraph);
                
                // Reset selection to maintain cursor position
                const newRange = document.createRange();
                newRange.setStart(newP.firstChild || newP, 0);
                newRange.collapse(true);
                selection.removeAllRanges();
                selection.addRange(newRange);
            }
        }
    },

    ensureFirstLineIsHeading: function() {
        const editor = document.getElementById('note-content');
        if (!editor.innerHTML.trim()) {
            return;
        }
        
        // Look for the first element
        const firstElement = editor.querySelector('*');
        
        if (firstElement) {
            // If the first line is not already a heading, make it one
            if (!(['H1', 'H2', 'H3', 'H4', 'H5', 'H6'].includes(firstElement.tagName))) {
                // Convert to proper H3 element
                const h3 = document.createElement('h3');
                h3.style.fontWeight = 'bold';
                h3.style.fontSize = '1.2em';
                h3.style.marginBottom = '0.5em';
                
                // Move content to new heading
                while (firstElement.firstChild) {
                    h3.appendChild(firstElement.firstChild);
                }
                
                // Replace the element with our heading
                firstElement.parentNode.replaceChild(h3, firstElement);
            }
            
            // Make sure subsequent elements have normal styling
            let nextElement = firstElement.nextElementSibling;
            while (nextElement) {
                if (!(['H1', 'H2', 'H3', 'H4', 'H5', 'H6'].includes(nextElement.tagName))) {
                    nextElement.style.fontWeight = 'normal';
                    nextElement.style.fontSize = '1em';
                }
                nextElement = nextElement.nextElementSibling;
            }
        }
    }
};

// Global functions for HTML onclick handlers
function createNewNote() {
    NotesApp.createNewNote();
}

function showNotesList() {
    NotesApp.showNotesList();
}

function formatText(format) {
    NotesApp.formatText(format);
}

function clearAllNotes() {
    NotesApp.clearAllNotes();
}

// Initialize the app when the page loads
window.onload = function() {
    NotesApp.init();
};