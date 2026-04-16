/**
 * ApexCharts Wrapper for SmartAdmin
 * Prevents CSS injection by the library and ensures we only use our own CSS
 * Includes chart color handling for theme changes
 */

import OriginalApexCharts from './../../plugins/apexcharts/apexcharts.esm.js';


// Create a wrapper around ApexCharts
class SmartApexCharts extends OriginalApexCharts {
    // Icon mapping for toolbar
    static iconMap = {
        'apexcharts-zoomin-icon': 'plus-circle',
        'apexcharts-zoomout-icon': 'minus-circle',
        'apexcharts-zoom-icon': 'plus',
        'apexcharts-pan-icon': 'move',
        'apexcharts-reset-icon': 'refresh-ccw',
        'apexcharts-menu-icon': 'menu'
    };
    
    // Registry for chart instances and configurations
    static chartRegistry = {
        instances: [],
        configs: []
    };
    
    // Current and previous color states
    static currentColors = {};
    static previousColors = {};

    constructor(el, options) {
        // Ensure options object exists
        options = options || {};
        options.chart = options.chart || {};
        
        // Global styling defaults
        const defaults = {
            chart: {
                fontFamily: 'inherit',
                disableCssInjection: true
            },
            title: {
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            subtitle: {
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.5)
                }
            },
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.2)
            },
            xaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                title: {
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                title: {
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark'
            },
            legend: {
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            }
        };

        // Merge options with defaults
        options = SmartApexCharts.deepMerge(defaults, options);
        
        // Call parent constructor with merged options
        super(el, options);
        
        // Create a MutationObserver to remove any stray style tags and handle icons
        this._styleObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeName === 'STYLE' && 
                            node.id && 
                            node.id.startsWith('apexcharts')) {
                            node.remove();
                        }
                        // Handle toolbar icons
                        if (node.classList && node.classList.contains('apexcharts-toolbar')) {
                            this._replaceToolbarIcons(node);
                        }
                    });
                }
            });
        });
        
        // Start observing the document for added style nodes
        this._styleObserver.observe(document.head, {
            childList: true
        });

        // Also observe body for toolbar changes
        this._styleObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Replace toolbar icons with custom sprite system icons
    _replaceToolbarIcons(toolbar) {
        Object.entries(SmartApexCharts.iconMap).forEach(([className, iconName]) => {
            const iconContainer = toolbar.querySelector(`.${className}`);
            if (iconContainer) {
                // Add button classes
                iconContainer.classList.add('btn', 'btn-icon', 'btn-sm', 'btn-outline-default', 'p-0');
                // Clear existing icon
                iconContainer.innerHTML = '';
                // Create new icon using sprite system
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.classList.add('sa-icon', 'sa-thick', 'sa-nofill');
                const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
                use.setAttributeNS('http://www.w3.org/1999/xlink', 'href', `img/sprite.svg#${iconName}`);
                svg.appendChild(use);
                iconContainer.appendChild(svg);
            }
        });
    }
    
    // Clean up observer when chart is destroyed
    destroy() {
        if (this._styleObserver) {
            this._styleObserver.disconnect();
        }
        
        // Remove from registry if exists
        const index = SmartApexCharts.chartRegistry.instances.indexOf(this);
        if (index !== -1) {
            SmartApexCharts.chartRegistry.instances.splice(index, 1);
            SmartApexCharts.chartRegistry.configs.splice(index, 1);
        }
        
        return super.destroy();
    }
    
    // Override render method to register chart
    render() {
        const result = super.render();
        
        // Register the chart after rendering
        if (this && this.el && this.w && this.w.config) {
            SmartApexCharts.registerChart(this, this.el, this.w.config);
        }
        
        return result;
    }

    // Static deep merge helper
    static deepMerge(target, source) {
        const isObject = (obj) => obj && typeof obj === 'object' && !Array.isArray(obj);
        const output = Object.assign({}, target);
        
        if (isObject(target) && isObject(source)) {
            Object.keys(source).forEach(key => {
                if (isObject(source[key])) {
                    if (!(key in target)) {
                        Object.assign(output, { [key]: source[key] });
                    } else {
                        output[key] = SmartApexCharts.deepMerge(target[key], source[key]);
                    }
                } else {
                    Object.assign(output, { [key]: source[key] });
                }
            });
        }
        
        return output;
    }
    
    // Register a chart for later color updates
    static registerChart(chart, element, config) {
        if (chart && element && config) {
            SmartApexCharts.chartRegistry.instances.push(chart);
            SmartApexCharts.chartRegistry.configs.push({
                element: element,
                config: config
            });
            console.log(`SmartApexCharts: Registered chart #${SmartApexCharts.chartRegistry.instances.length}`);
        }
    }
    
    // Extract current theme colors
    static extractColors() {
        // Store previous colors
        SmartApexCharts.previousColors = {...SmartApexCharts.currentColors};
        
        // Extract new colors
        if (window.appDOM && typeof window.appDOM.extractColors === 'function') {
            window.appDOM.extractColors();
        }
        
        // Build a more comprehensive color map with all available shades
        SmartApexCharts.currentColors = {};
        
        if (window.colorMap) {
            // Define color categories to extract
            const colorCategories = ['primary', 'success', 'warning', 'danger', 'info'];
            
            // Get all available color shades for each category
            colorCategories.forEach(category => {
                if (window.colorMap[category]) {
                    // For each category, find all available shades
                    Object.keys(window.colorMap[category]).forEach(shade => {
                        if (window.colorMap[category][shade]?.hex) {
                            // Store each shade with a category-shade key
                            const colorKey = `${category}-${shade}`;
                            SmartApexCharts.currentColors[colorKey] = window.colorMap[category][shade].hex;
                        }
                    });
                }
            });
            
            // Also add bodyColor as it's commonly used
            if (window.colorMap.bootstrapVars?.bodyColor?.hex) {
                SmartApexCharts.currentColors['bodyColor'] = window.colorMap.bootstrapVars.bodyColor.hex;
                SmartApexCharts.currentColors['bodyColor-alpha'] = window.colorMap.bootstrapVars.bodyColor.rgba(0.07);
            }
        }
        
        return SmartApexCharts.currentColors;
    }
    
    // Helper to update a color value based on the before/after color mapping
    static updateColorValue(color) {
        if (!color || typeof color !== 'string') return color;
        
        // First, direct match from previous colors to current colors
        if (SmartApexCharts.previousColors) {
            for (const [key, oldColor] of Object.entries(SmartApexCharts.previousColors)) {
                if (color === oldColor) {
                    return SmartApexCharts.currentColors[key] || color;
                }
            }
        }
        
        // If no direct match, check if it's a color from the theme
        if (window.colorMap) {
            const colorCategories = ['primary', 'success', 'warning', 'danger', 'info'];
            
            // Try to match with any shade in each category
            for (const category of colorCategories) {
                if (window.colorMap[category]) {
                    for (const shade in window.colorMap[category]) {
                        if (window.colorMap[category][shade]?.hex === color) {
                            // We found a match - use the updated color for this category/shade
                            return window.colorMap[category][shade].hex;
                        }
                    }
                }
            }
        }
        
        // Handle rgba colors (especially for grid)
        if (color.includes('rgba') && window.colorMap?.bootstrapVars?.bodyColor) {
            const opacityMatch = color.match(/rgba\([^)]+,\s*([\d.]+)\)/);
            if (opacityMatch && opacityMatch[1]) {
                const opacity = parseFloat(opacityMatch[1]);
                
                // Common opacities to check against
                const standardOpacities = [0.07, 0.1, 0.2, 0.3, 0.5, 0.7, 0.8, 0.9];
                const closestOpacity = standardOpacities.reduce((prev, curr) => 
                    Math.abs(curr - opacity) < Math.abs(prev - opacity) ? curr : prev
                );
                
                // If this is a very close match to a standard opacity, use exact value
                if (Math.abs(closestOpacity - opacity) < 0.02) {
                    return window.colorMap.bootstrapVars.bodyColor.rgba(closestOpacity);
                }
                
                // Otherwise use the exact opacity provided
                return window.colorMap.bootstrapVars.bodyColor.rgba(opacity);
            }
        }
        
        return color;
    }
    
    // Helper to update an array of color values
    static updateColorValues(colors) {
        if (!colors || !Array.isArray(colors)) return colors;
        return colors.map(color => SmartApexCharts.updateColorValue(color));
    }
    
    // Update all registered charts with current theme colors
    static updateChartColors() {
        const instanceCount = SmartApexCharts.chartRegistry.instances.length;
        console.log(`SmartApexCharts: Updating colors for ${instanceCount} charts`);
        
        // Update color maps
        SmartApexCharts.extractColors();
        
        console.log('SmartApexCharts: Color update:', {
            before: SmartApexCharts.previousColors,
            after: SmartApexCharts.currentColors
        });
        
        if (instanceCount === 0) {
            console.warn('SmartApexCharts: No charts found to update');
            return;
        }
        
        // Update each chart with new colors
        SmartApexCharts.chartRegistry.instances.forEach((chart, index) => {
            try {
                if (!chart || typeof chart.updateOptions !== 'function') {
                    console.warn(`SmartApexCharts: Chart #${index + 1} cannot be updated`);
                    return;
                }
                
                // Get current options - Make a full deep copy to avoid reference issues
                const currentOptions = JSON.parse(JSON.stringify(chart.w.config));
                
                // Preserve non-serializable properties like formatters
                if (currentOptions.yaxis) {
                    if (Array.isArray(currentOptions.yaxis)) {
                        currentOptions.yaxis.forEach((axis, i) => {
                            if (axis.labels && chart.w.config.yaxis[i].labels.formatter) {
                                axis.labels.formatter = chart.w.config.yaxis[i].labels.formatter;
                            }
                        });
                    } else if (currentOptions.yaxis.labels && chart.w.config.yaxis.labels.formatter) {
                        currentOptions.yaxis.labels.formatter = chart.w.config.yaxis.labels.formatter;
                    }
                }
                
                if (currentOptions.xaxis && currentOptions.xaxis.labels && chart.w.config.xaxis.labels.formatter) {
                    currentOptions.xaxis.labels.formatter = chart.w.config.xaxis.labels.formatter;
                }
                
                // Start with minimal updates, focusing only on color changes
                const updatedOptions = {
                    colors: SmartApexCharts.updateColorValues(currentOptions.colors),
                    chart: {
                        background: currentOptions.chart?.background
                    }
                };
                
                // Update all stroke colors 
                if (currentOptions.stroke) {
                    updatedOptions.stroke = {...currentOptions.stroke};
                    
                    // Handle stroke colors array
                    if (currentOptions.stroke.colors) {
                        updatedOptions.stroke.colors = SmartApexCharts.updateColorValues(currentOptions.stroke.colors);
                    }
                    
                    // Handle single stroke color
                    if (currentOptions.stroke.color) {
                        updatedOptions.stroke.color = SmartApexCharts.updateColorValue(currentOptions.stroke.color);
                    }
                }
                
                // Update fill gradient if present
                if (currentOptions.fill && currentOptions.fill.gradient && 
                    currentOptions.fill.gradient.colorStops) {
                    
                    updatedOptions.fill = {
                        ...currentOptions.fill,
                        gradient: {
                            ...currentOptions.fill.gradient,
                            colorStops: currentOptions.fill.gradient.colorStops.map(stop => {
                                return {
                                    ...stop,
                                    color: SmartApexCharts.updateColorValue(stop.color)
                                };
                            })
                        }
                    };
                }
                
                // Update grid colors
                if (currentOptions.grid) {
                    updatedOptions.grid = {...currentOptions.grid};
                    
                    if (currentOptions.grid.borderColor) {
                        updatedOptions.grid.borderColor = SmartApexCharts.updateColorValue(currentOptions.grid.borderColor);
                    }
                }
                
                // Update marker colors
                if (currentOptions.markers) {
                    updatedOptions.markers = {...currentOptions.markers};
                    
                    if (currentOptions.markers.strokeColors) {
                        updatedOptions.markers.strokeColors = SmartApexCharts.updateColorValues(currentOptions.markers.strokeColors);
                    }
                    
                    if (currentOptions.markers.strokeColor) {
                        updatedOptions.markers.strokeColor = SmartApexCharts.updateColorValue(currentOptions.markers.strokeColor);
                    }
                }
                
                // Update zoom area colors if present
                if (currentOptions.chart && currentOptions.chart.zoom && currentOptions.chart.zoom.zoomedArea) {
                    if (!updatedOptions.chart.zoom) {
                        updatedOptions.chart.zoom = { 
                            ...currentOptions.chart.zoom,
                            zoomedArea: {...currentOptions.chart.zoom.zoomedArea} 
                        };
                    } else {
                        updatedOptions.chart.zoom.zoomedArea = {...currentOptions.chart.zoom.zoomedArea};
                    }
                    
                    if (currentOptions.chart.zoom.zoomedArea.fill && currentOptions.chart.zoom.zoomedArea.fill.color) {
                        updatedOptions.chart.zoom.zoomedArea.fill = {
                            ...currentOptions.chart.zoom.zoomedArea.fill,
                            color: SmartApexCharts.updateColorValue(currentOptions.chart.zoom.zoomedArea.fill.color)
                        };
                    }
                    
                    if (currentOptions.chart.zoom.zoomedArea.stroke && currentOptions.chart.zoom.zoomedArea.stroke.color) {
                        updatedOptions.chart.zoom.zoomedArea.stroke = {
                            ...currentOptions.chart.zoom.zoomedArea.stroke,
                            color: SmartApexCharts.updateColorValue(currentOptions.chart.zoom.zoomedArea.stroke.color)
                        };
                    }
                }
                
                // Update legend colors
                if (currentOptions.legend && currentOptions.legend.labels) {
                    if (!updatedOptions.legend) {
                        updatedOptions.legend = { ...currentOptions.legend, labels: {...currentOptions.legend.labels} };
                    } else if (!updatedOptions.legend.labels) {
                        updatedOptions.legend.labels = {...currentOptions.legend.labels};
                    }
                    
                    if (currentOptions.legend.labels.colors) {
                        updatedOptions.legend.labels.colors = SmartApexCharts.updateColorValue(currentOptions.legend.labels.colors);
                    }
                }
                
                // Update title and subtitle colors
                if (currentOptions.title && currentOptions.title.style) {
                    if (!updatedOptions.title) {
                        updatedOptions.title = { ...currentOptions.title, style: {...currentOptions.title.style} };
                    } else if (!updatedOptions.title.style) {
                        updatedOptions.title.style = {...currentOptions.title.style};
                    }
                    
                    if (currentOptions.title.style.color) {
                        updatedOptions.title.style.color = SmartApexCharts.updateColorValue(currentOptions.title.style.color);
                    }
                }
                
                if (currentOptions.subtitle && currentOptions.subtitle.style) {
                    if (!updatedOptions.subtitle) {
                        updatedOptions.subtitle = { ...currentOptions.subtitle, style: {...currentOptions.subtitle.style} };
                    } else if (!updatedOptions.subtitle.style) {
                        updatedOptions.subtitle.style = {...currentOptions.subtitle.style};
                    }
                    
                    if (currentOptions.subtitle.style.color) {
                        updatedOptions.subtitle.style.color = SmartApexCharts.updateColorValue(currentOptions.subtitle.style.color);
                    }
                }
                
                // Update annotation colors
                if (currentOptions.annotations) {
                    updatedOptions.annotations = {...currentOptions.annotations};
                    
                    // Handle points annotations
                    if (Array.isArray(currentOptions.annotations.points)) {
                        updatedOptions.annotations.points = currentOptions.annotations.points.map(point => {
                            const updated = {...point};
                            
                            if (point.strokeColor) {
                                updated.strokeColor = SmartApexCharts.updateColorValue(point.strokeColor);
                            }
                            
                            if (point.label && point.label.borderColor) {
                                updated.label = {...point.label, 
                                   borderColor: SmartApexCharts.updateColorValue(point.label.borderColor)
                                };
                            }
                            
                            return updated;
                        });
                    }
                    
                    // Handle xaxis annotations
                    if (Array.isArray(currentOptions.annotations.xaxis)) {
                        updatedOptions.annotations.xaxis = currentOptions.annotations.xaxis.map(xAnnotation => {
                            const updated = {...xAnnotation};
                            
                            if (xAnnotation.borderColor) {
                                updated.borderColor = SmartApexCharts.updateColorValue(xAnnotation.borderColor);
                            }
                            
                            if (xAnnotation.fillColor) {
                                updated.fillColor = SmartApexCharts.updateColorValue(xAnnotation.fillColor);
                            }
                            
                            if (xAnnotation.label) {
                                updated.label = {...xAnnotation.label};
                                
                                if (xAnnotation.label.borderColor) {
                                    updated.label.borderColor = SmartApexCharts.updateColorValue(xAnnotation.label.borderColor);
                                }
                                
                                if (xAnnotation.label.style) {
                                    updated.label.style = {...xAnnotation.label.style};
                                    
                                    if (xAnnotation.label.style.background) {
                                        updated.label.style.background = SmartApexCharts.updateColorValue(xAnnotation.label.style.background);
                                    }
                                    
                                    if (xAnnotation.label.style.color) {
                                        updated.label.style.color = SmartApexCharts.updateColorValue(xAnnotation.label.style.color);
                                    }
                                }
                            }
                            
                            return updated;
                        });
                    }
                    
                    // Handle yaxis annotations
                    if (Array.isArray(currentOptions.annotations.yaxis)) {
                        updatedOptions.annotations.yaxis = currentOptions.annotations.yaxis.map(yAnnotation => {
                            const updated = {...yAnnotation};
                            
                            if (yAnnotation.borderColor) {
                                updated.borderColor = SmartApexCharts.updateColorValue(yAnnotation.borderColor);
                            }
                            
                            if (yAnnotation.fillColor) {
                                updated.fillColor = SmartApexCharts.updateColorValue(yAnnotation.fillColor);
                            }
                            
                            if (yAnnotation.label) {
                                updated.label = {...yAnnotation.label};
                                
                                if (yAnnotation.label.borderColor) {
                                    updated.label.borderColor = SmartApexCharts.updateColorValue(yAnnotation.label.borderColor);
                                }
                                
                                if (yAnnotation.label.style) {
                                    updated.label.style = {...yAnnotation.label.style};
                                    
                                    if (yAnnotation.label.style.background) {
                                        updated.label.style.background = SmartApexCharts.updateColorValue(yAnnotation.label.style.background);
                                    }
                                    
                                    if (yAnnotation.label.style.color) {
                                        updated.label.style.color = SmartApexCharts.updateColorValue(yAnnotation.label.style.color);
                                    }
                                }
                            }
                            
                            return updated;
                        });
                    }
                }
                
                // Handle axis settings in a more direct way 
                // This ensures proper updating of axis colors including dual y-axes
                const bodyColor = window.colorMap?.bootstrapVars?.bodyColor?.hex || '#666';
                
                // X-Axis configuration
                if (currentOptions.xaxis) {
                    if (!updatedOptions.xaxis) {
                        updatedOptions.xaxis = {...currentOptions.xaxis};
                    }
                    
                    // Handle xaxis labels
                    if (currentOptions.xaxis.labels) {
                        if (!updatedOptions.xaxis.labels) {
                            updatedOptions.xaxis.labels = {...currentOptions.xaxis.labels};
                        }
                        
                        // Handle style object
                        if (currentOptions.xaxis.labels.style) {
                            updatedOptions.xaxis.labels.style = {...currentOptions.xaxis.labels.style};
                            
                            // Only update colors while preserving other style properties like fontSize
                            if (currentOptions.xaxis.labels.style.colors) {
                                if (Array.isArray(currentOptions.xaxis.labels.style.colors)) {
                                    updatedOptions.xaxis.labels.style.colors = 
                                        currentOptions.xaxis.labels.style.colors.map(color => 
                                            SmartApexCharts.updateColorValue(color));
                                } else {
                                    updatedOptions.xaxis.labels.style.colors = 
                                        SmartApexCharts.updateColorValue(currentOptions.xaxis.labels.style.colors);
                                }
                            } else {
                                // Don't overwrite other properties
                                updatedOptions.xaxis.labels.style.colors = bodyColor;
                            }
                        } else {
                            // Create style object with colors if it doesn't exist
                            updatedOptions.xaxis.labels.style = { colors: bodyColor };
                        }
                    }
                    
                    // Handle xaxis title
                    if (currentOptions.xaxis.title) {
                        if (!updatedOptions.xaxis.title) {
                            updatedOptions.xaxis.title = {...currentOptions.xaxis.title};
                        }
                        
                        // Handle style object
                        if (currentOptions.xaxis.title.style) {
                            updatedOptions.xaxis.title.style = {...currentOptions.xaxis.title.style};
                            
                            // Update color
                            if (currentOptions.xaxis.title.style.color) {
                                updatedOptions.xaxis.title.style.color = 
                                    SmartApexCharts.updateColorValue(currentOptions.xaxis.title.style.color);
                            } else {
                                updatedOptions.xaxis.title.style.color = bodyColor;
                            }
                        } else {
                            // Create style object with color if it doesn't exist
                            updatedOptions.xaxis.title.style = { color: bodyColor };
                        }
                    }
                }
                
                // Y-Axis configuration - handle both array and single object formats
                if (currentOptions.yaxis) {
                    // Handle array of y-axes (multi-axis charts)
                    if (Array.isArray(currentOptions.yaxis)) {
                        if (!updatedOptions.yaxis) {
                            updatedOptions.yaxis = [];
                        }
                        
                        // Process each y-axis
                        currentOptions.yaxis.forEach((axis, index) => {
                            if (!updatedOptions.yaxis[index]) {
                                updatedOptions.yaxis[index] = {...axis};
                            }
                            
                            // Handle labels
                            if (axis.labels) {
                                if (!updatedOptions.yaxis[index].labels) {
                                    updatedOptions.yaxis[index].labels = {...axis.labels};
                                }
                                
                                // Handle style object
                                if (axis.labels.style) {
                                    updatedOptions.yaxis[index].labels.style = {...axis.labels.style};
                                    
                                    // Only update colors while preserving other style properties
                                    // We want to keep fontSize, fontFamily, etc.
                                    updatedOptions.yaxis[index].labels.style.colors = bodyColor;
                                } else {
                                    // Create style object with colors if it doesn't exist
                                    updatedOptions.yaxis[index].labels.style = { colors: bodyColor };
                                }
                                
                                // Ensure formatter is preserved
                                if (axis.labels.formatter) {
                                    updatedOptions.yaxis[index].labels.formatter = axis.labels.formatter;
                                }
                            } else {
                                // Create labels with style if they don't exist
                                updatedOptions.yaxis[index].labels = { 
                                    style: { colors: bodyColor } 
                                };
                            }
                            
                            // Handle title
                            if (axis.title) {
                                if (!updatedOptions.yaxis[index].title) {
                                    updatedOptions.yaxis[index].title = {...axis.title};
                                }
                                
                                // Handle style object
                                if (axis.title.style) {
                                    updatedOptions.yaxis[index].title.style = {...axis.title.style};
                                    
                                    // Update color
                                    if (axis.title.style.color) {
                                        updatedOptions.yaxis[index].title.style.color = 
                                            SmartApexCharts.updateColorValue(axis.title.style.color);
                                    } else {
                                        updatedOptions.yaxis[index].title.style.color = bodyColor;
                                    }
                                } else {
                                    // Create style object with color if it doesn't exist
                                    updatedOptions.yaxis[index].title.style = { color: bodyColor };
                                }
                            }
                        });
                    } 
                    // Handle single y-axis object
                    else {
                        if (!updatedOptions.yaxis) {
                            updatedOptions.yaxis = {...currentOptions.yaxis};
                        }
                        
                        // Handle labels
                        if (currentOptions.yaxis.labels) {
                            if (!updatedOptions.yaxis.labels) {
                                updatedOptions.yaxis.labels = {...currentOptions.yaxis.labels};
                            }
                            
                            // Handle style object
                            if (currentOptions.yaxis.labels.style) {
                                updatedOptions.yaxis.labels.style = {...currentOptions.yaxis.labels.style};
                                
                                // Only update colors while preserving other style properties
                                updatedOptions.yaxis.labels.style.colors = bodyColor;
                            } else {
                                // Create style object with colors if it doesn't exist
                                updatedOptions.yaxis.labels.style = { colors: bodyColor };
                            }
                            
                            // Ensure formatter is preserved
                            if (currentOptions.yaxis.labels.formatter) {
                                updatedOptions.yaxis.labels.formatter = currentOptions.yaxis.labels.formatter;
                            }
                        } else {
                            // Create labels with style if they don't exist
                            updatedOptions.yaxis.labels = { 
                                style: { colors: bodyColor } 
                            };
                        }
                        
                        // Handle title
                        if (currentOptions.yaxis.title) {
                            if (!updatedOptions.yaxis.title) {
                                updatedOptions.yaxis.title = {...currentOptions.yaxis.title};
                            }
                            
                            // Handle style object
                            if (currentOptions.yaxis.title.style) {
                                updatedOptions.yaxis.title.style = {...currentOptions.yaxis.title.style};
                                
                                // Update color
                                if (currentOptions.yaxis.title.style.color) {
                                    updatedOptions.yaxis.title.style.color = 
                                        SmartApexCharts.updateColorValue(currentOptions.yaxis.title.style.color);
                                } else {
                                    updatedOptions.yaxis.title.style.color = bodyColor;
                                }
                            } else {
                                // Create style object with color if it doesn't exist
                                updatedOptions.yaxis.title.style = { color: bodyColor };
                            }
                        }
                    }
                }
                
                // Debug logging to verify font sizes are preserved
                console.log(`Chart #${index + 1} - Font sizes preserved:`, {
                    xaxis: currentOptions.xaxis?.labels?.style?.fontSize,
                    yaxis: Array.isArray(currentOptions.yaxis) 
                        ? currentOptions.yaxis.map(a => a.labels?.style?.fontSize)
                        : currentOptions.yaxis?.labels?.style?.fontSize,
                    updatedXAxis: updatedOptions.xaxis?.labels?.style?.fontSize,
                    updatedYAxis: Array.isArray(updatedOptions.yaxis) 
                        ? updatedOptions.yaxis.map(a => a.labels?.style?.fontSize)
                        : updatedOptions.yaxis?.labels?.style?.fontSize
                });
                
                // Update the chart with new options
                chart.updateOptions(updatedOptions, false, true);
                console.log(`SmartApexCharts: Updated colors for chart #${index + 1}`);
            } catch (e) {
                console.error(`SmartApexCharts: Error updating chart #${index + 1}:`, e);
            }
        });
        
        console.log('SmartApexCharts: Chart color update complete');
    }
}

// Override the prototype method that injects CSS
SmartApexCharts.prototype.injectCSS = function() {
    return;
};

// Initialize colors on load
SmartApexCharts.extractColors();

// Provide global updateChartColors function for backward compatibility
window.rebuildApexCharts = function() {
    SmartApexCharts.updateChartColors();
};

// Export wrapped version
export default SmartApexCharts; 
