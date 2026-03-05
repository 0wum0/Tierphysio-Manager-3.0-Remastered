// SmartTables Row Highlight Plugin
// Copyright SmartAdmin WebApp Copyright 2025-2026.

/**
 * Row Highlight Plugin for SmartTables
 * Allows highlighting rows based on conditions and adds hover effects
 */
export const RowHighlightPlugin = {
    name: 'rowHighlight',
    defaults: {
        hoverEffect: true,
        conditions: [],
        highlightClass: 'st-row-highlight',
        hoverClass: 'st-row-hover'
    },
    
    init() {
        this.options = {
            ...this.defaults,
            ...(this.instance.options?.rowHighlight || {})
        };
        
        this.options.conditions = this.options.conditions || [];
        
        console.log('RowHighlight Plugin initialized with options:', this.options);
        
        this.setupStyles();
        this.bindEvents();
    },
    
    setupStyles() {
        // Add CSS for highlighting
        const style = document.createElement('style');
        style.textContent = `
            .${this.options.highlightClass} {
                background-color: rgba(255, 255, 0, 0.2) !important;
            }
            .${this.options.hoverClass} {
                background-color: rgba(0, 123, 255, 0.1) !important;
                transition: background-color 0.2s ease;
            }
        `;
        document.head.appendChild(style);
        this.styleElement = style;
    },
    
    bindEvents() {
        if (this.options.hoverEffect) {
            this.instance.table.addEventListener('mouseover', this.handleMouseOver.bind(this));
            this.instance.table.addEventListener('mouseout', this.handleMouseOut.bind(this));
        }
    },
    
    handleMouseOver(e) {
        const row = e.target.closest('tr');
        if (row && !row.classList.contains('st-child-row')) {
            row.classList.add(this.options.hoverClass);
        }
    },
    
    handleMouseOut(e) {
        const row = e.target.closest('tr');
        if (row) {
            row.classList.remove(this.options.hoverClass);
        }
    },
    
    afterDraw() {
        this.applyHighlights();
    },
    
    applyHighlights() {
        const conditions = this.options?.conditions || [];
        if (!conditions.length) return;
        
        const tbody = this.instance.table.querySelector('tbody');
        if (!tbody) return;
        
        Array.from(tbody.rows).forEach(row => {
            if (row.classList.contains('st-child-row')) return;
            
            const shouldHighlight = conditions.some(condition => {
                if (!condition || typeof condition.column === 'undefined') return false;
                
                const cell = row.cells[condition.column];
                if (!cell) return false;
                
                const cellValue = cell.textContent.trim();
                
                switch (condition.operator) {
                    case 'equals':
                        return cellValue === condition.value;
                    case 'contains':
                        return cellValue.includes(condition.value);
                    case 'greater':
                        return parseFloat(cellValue) > parseFloat(condition.value);
                    case 'less':
                        return parseFloat(cellValue) < parseFloat(condition.value);
                    case 'between':
                        const num = parseFloat(cellValue);
                        return Array.isArray(condition.value) &&
                               num >= parseFloat(condition.value[0]) && 
                               num <= parseFloat(condition.value[1]);
                    case 'custom':
                        return typeof condition.callback === 'function' &&
                               condition.callback(cellValue, row);
                    default:
                        return false;
                }
            });
            
            row.classList.toggle(this.options.highlightClass, shouldHighlight);
        });
    },
    
    destroy() {
        if (this.styleElement) {
            this.styleElement.remove();
        }
        
        if (this.options.hoverEffect) {
            this.instance.table.removeEventListener('mouseover', this.handleMouseOver);
            this.instance.table.removeEventListener('mouseout', this.handleMouseOut);
        }
        
        // Remove highlights
        const tbody = this.instance.table.querySelector('tbody');
        if (tbody) {
            Array.from(tbody.rows).forEach(row => {
                row.classList.remove(this.options.highlightClass, this.options.hoverClass);
            });
        }
    }
}; 