/*
 You can use this wrapper to initialize peity charts globally. It will automatically detect the chart 
 type based on the content of the element. 

 - You can also use the data-peity attribute to pass in options for the chart.
 - The data-peity attribute can be a JSON string or a string that can be parsed into a JSON object.
 - The wrapper will also handle the:
    -- updating of the chart with new data.
    -- negative values for the bar charts.
    -- color transitions for the bar charts.
    -- updating of the chart with new data.
*/

import { PeityAPI } from './../thirdparty/peity.es6.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Global default settings for charts
    const defaults = {
        // Default background color for donut and pie charts (replaces the gray)
        donutBackground: 'var(--bs-border-color)', // Light purple background instead of gray
        pieBackground: 'var(--bs-border-color)'    // Same for pie charts
    };
    
    // Helper to create peity charts (similar to jQuery plugin style)
    function createPeity(selector, type, customOptions = {}) {
        document.querySelectorAll(selector).forEach(element => {
            // Parse data-peity attribute for options
            let options = {...customOptions};
            const dataAttr = element.getAttribute('data-peity');
            
            if (dataAttr) {
                try {
                    options = {...options, ...JSON.parse(dataAttr)};
                } catch (e) {
                    console.warn('Invalid data-peity format for', selector);
                }
            }
            
            // Apply defaults for donut and pie charts background colors
            if (type === 'donut' || type === 'pie') {
                // If fill is not defined or is an empty array, set default colors
                if (!options.fill || options.fill.length === 0) {
                    options.fill = type === 'donut' ? 
                        ['var(--primary-500)', defaults.donutBackground] : 
                        ['var(--primary-500)', defaults.pieBackground];
                } 
                // If fill is defined but doesn't include a background color (for fractions like "1/4")
                else if (Array.isArray(options.fill) && options.fill.length === 1) {
                    options.fill.push(type === 'donut' ? defaults.donutBackground : defaults.pieBackground);
                }
                // If data contains multiple values (e.g., "10,4,4,6"), don't add background
            }
            
            PeityAPI.create(element, type, options);
        });
    }
    
    try {
        // Simple chart initializations with default settings
        createPeity('.peity-pie', 'pie');
        createPeity('.peity-donut', 'donut');
        createPeity('.peity-line', 'line');
        createPeity('.peity-bar', 'bar');
        
        // Updating chart with animation
        const updatingChart = document.querySelector('.updating-chart');
        if (updatingChart) {
            const values = updatingChart.textContent.split(',').map(Number);
            const chart = PeityAPI.create(updatingChart, 'line', {
                width: 200, 
                height: 40,
                stroke: 'var(--info-500)',
                fill: 'var(--info-200)',
                min: 0,
                max: 10
            });
            
            setInterval(function() {
                values.shift();
                values.push(Math.floor(Math.random() * 10));
                updatingChart.textContent = values.join(',');
                chart.draw();
            }, 500);
        }
        
        // Bar charts with negative values (red/green coloring)
        document.querySelectorAll('.bar-negative').forEach(element => {
            const values = element.textContent.split(',').map(Number);
            PeityAPI.create(element, 'bar', {
                height: 40,
                width: 110,
                fill: values.map(value => value > 0 ? 'var(--success-500)' : 'var(--danger-500)')
            });
        });
        
        // Bar charts with color transitions
        document.querySelectorAll('.bar-transition').forEach(element => {
            const values = element.textContent.split(',').map(Number);
            PeityAPI.create(element, 'bar', {
                height: 40,
                width: 110,
                fill: values.map((_, i, all) => {
                    const g = parseInt((i / all.length) * 255);
                    return `rgb(255, ${g}, ${g})`;
                })
            });
        });

        // Process all remaining elements with data-peity attribute
        document.querySelectorAll('[data-peity]').forEach(element => {
            // Skip elements already handled by specific selectors
            if (element.classList.contains('peity-pie') || 
                element.classList.contains('peity-donut') || 
                element.classList.contains('peity-line') || 
                element.classList.contains('peity-bar') ||
                element.classList.contains('updating-chart') ||
                element.classList.contains('bar-negative') || 
                element.classList.contains('bar-transition')) {
                return;
            }
            
            // Auto-detect chart type based on content
            const content = element.textContent.trim();
            let type;
            
            if (content.includes('/')) {
                type = 'donut'; // Fraction data is best for donut
            } else if (content.includes(',')) {
                // If it has commas, it's likely a series
                const hasNegative = content.split(',').some(val => parseFloat(val) < 0);
                type = hasNegative ? 'bar' : 'line'; // Bars handle negative values better visually
            } else {
                type = 'pie'; // Default fallback
            }
            
            // Get options from data attribute
            let options = {};
            const dataAttr = element.getAttribute('data-peity');
            if (dataAttr) {
                try {
                    options = JSON.parse(dataAttr);
                } catch (e) {
                    console.warn('Invalid data-peity format for element', element);
                }
            }
            
            // Apply global background color for donut/pie charts
            if ((type === 'donut' || type === 'pie') && content.includes('/')) {
                // For fraction notation (e.g. "1/4"), make sure we have a background color
                if (!options.fill || !Array.isArray(options.fill) || options.fill.length < 2) {
                    options.fill = options.fill || [];
                    // If we have one color already, keep it and add the background
                    if (options.fill.length === 1) {
                        options.fill.push(type === 'donut' ? defaults.donutBackground : defaults.pieBackground);
                    } else {
                        // No colors defined yet, set defaults
                        options.fill = [
                            type === 'donut' ? 'var(--primary-500)' : 'var(--success-500)', 
                            type === 'donut' ? defaults.donutBackground : defaults.pieBackground
                        ];
                    }
                }
            }
            
            // Create chart
            PeityAPI.create(element, type, options);
        });
        
    } catch (error) {
        console.error('Error initializing Peity charts:', error);
    }
});