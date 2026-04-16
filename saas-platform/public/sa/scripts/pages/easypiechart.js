/**
 * Easy Pie Chart Implementation
 * Vanilla JavaScript implementation for easy pie charts
 */


// Global registry to keep track of all chart instances
window.easyPieChartRegistry = {
	instances: [],
	elements: []
};

// Global color state
window.easyPieChartColors = {
	currentColors: {},
	previousColors: {}
};

// Extract colors from the theme
function extractEasyPieChartColors() {
	// Store previous colors
	window.easyPieChartColors.previousColors = {...window.easyPieChartColors.currentColors};
	
	// Extract new colors
	window.easyPieChartColors.currentColors = {};
	
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
						window.easyPieChartColors.currentColors[colorKey] = window.colorMap[category][shade].hex;
					}
				});
			}
		});
		
		// Also add bodyColor as it's commonly used
		if (window.colorMap.bootstrapVars?.bodyColor?.hex) {
			window.easyPieChartColors.currentColors['bodyColor'] = window.colorMap.bootstrapVars.bodyColor.hex;
			window.easyPieChartColors.currentColors['bodyColor-alpha'] = window.colorMap.bootstrapVars.bodyColor.rgba(0.07);
		}
	}
	
	return window.easyPieChartColors.currentColors;
}

// Helper to update a color value based on the before/after color mapping
function updateEasyPieChartColorValue(color) {
	if (!color || typeof color !== 'string') return color;
	
	// First, direct match from previous colors to current colors
	if (window.easyPieChartColors.previousColors) {
		for (const [key, oldColor] of Object.entries(window.easyPieChartColors.previousColors)) {
			if (color === oldColor) {
				return window.easyPieChartColors.currentColors[key] || color;
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
	
	// Handle rgba colors
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
	
	// Handle CSS variables
	if (color.startsWith('var(')) {
		// Try to extract the variable value
		const varName = color.replace('var(', '').replace(')', '').trim();
		const computedStyle = getComputedStyle(document.documentElement);
		const varValue = computedStyle.getPropertyValue(varName);
		if (varValue) {
			return varValue.trim();
		}
	}
	
	return color;
}

// Global function to update all Easy Pie Chart colors
window.updateEasyPieCharts = function() {
	console.log(`EasyPieChart: Updating colors for ${window.easyPieChartRegistry.instances.length} charts`);
	
	// Update color maps
	extractEasyPieChartColors();
	
	if (window.easyPieChartRegistry.instances.length === 0) {
		console.warn('EasyPieChart: No charts found to update');
		return;
	}
	
	// Update each chart with new colors
	window.easyPieChartRegistry.instances.forEach((chart, index) => {
		try {
			if (!chart) {
				console.warn(`EasyPieChart: Chart #${index + 1} cannot be updated`);
				return;
			}
			
			const element = window.easyPieChartRegistry.elements[index];
			if (!element) return;
			
			// Get computed style
			const computedStyle = window.getComputedStyle(element);
			
			// Update barColor
			const currentBarColor = chart.options.barColor;
			const newBarColor = computedStyle.color || currentBarColor;
			const updatedBarColor = updateEasyPieChartColorValue(newBarColor);
			
			// Update trackColor
			let updatedTrackColor;
			try {
				if (window.colorMap && window.colorMap.bootstrapVars && window.colorMap.bootstrapVars.bodyColorRgb) {
					updatedTrackColor = window.colorMap.bootstrapVars.bodyColorRgb.rgba(0.07);
				} else {
					updatedTrackColor = 'rgba(0,0,0,0.04)';
				}
			} catch (e) {
				updatedTrackColor = 'rgba(0,0,0,0.04)';
			}
			
			// Update scaleColor
			const currentScaleColor = chart.options.scaleColor;
			const newScaleColor = element.dataset.scalecolor || computedStyle.color || currentScaleColor;
			const updatedScaleColor = updateEasyPieChartColorValue(newScaleColor);
			
			// Apply the new colors using Canvas API's getImageData for efficiency
			const canvas = chart.renderer.getCanvas();
			const ctx = chart.renderer.getCtx();
			
			// If canvas context is available, use pixel manipulation
			if (ctx && ctx.getImageData && ctx.putImageData) {
				// Store current canvas state
				const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
				const data = imageData.data;
				
				// Parse colors to RGB
				const barRGB = parseColorToRGB(updatedBarColor);
				const oldBarRGB = parseColorToRGB(currentBarColor);
				const trackRGB = parseColorToRGB(updatedTrackColor);
				
				// Update pixels - only change bar color (non-transparent, non-track pixels)
				for (let i = 0; i < data.length; i += 4) {
					// Only process non-transparent pixels
					if (data[i + 3] > 0) {
						// Check if pixel is close to the old bar color and not track color
						const isBarPixel = 
							(Math.abs(data[i] - oldBarRGB.r) < 15 && 
							 Math.abs(data[i + 1] - oldBarRGB.g) < 15 && 
							 Math.abs(data[i + 2] - oldBarRGB.b) < 15) ||
							(Math.abs(data[i] - trackRGB.r) > 10 || 
							 Math.abs(data[i + 1] - trackRGB.g) > 10 || 
							 Math.abs(data[i + 2] - trackRGB.b) > 10);
						
						if (isBarPixel) {
							// Update to new bar color
							data[i] = barRGB.r;
							data[i + 1] = barRGB.g;
							data[i + 2] = barRGB.b;
							// Keep original alpha
						}
					}
				}
				
				// Put the modified image data back
				ctx.putImageData(imageData, 0, 0);
				
				// Update the chart's options
				chart.options.barColor = updatedBarColor;
				chart.options.trackColor = updatedTrackColor;
				chart.options.scaleColor = updatedScaleColor;
				
				console.log(`EasyPieChart: Updated colors for chart #${index + 1} using pixel manipulation`);
			} else {
				// Fallback to standard update method
				chart.options.barColor = updatedBarColor;
				chart.options.trackColor = updatedTrackColor;
				chart.options.scaleColor = updatedScaleColor;
				
				// Redraw the chart with new colors
				chart.update(chart.currentValue);
				
				console.log(`EasyPieChart: Updated colors for chart #${index + 1} using redraw`);
			}
		} catch (e) {
			console.error(`EasyPieChart: Error updating chart #${index + 1}:`, e);
		}
	});
	
	console.log('EasyPieChart: Chart color update complete');
};

// Helper function to parse color string to RGB
function parseColorToRGB(color) {
	// For hex colors
	if (color.startsWith('#')) {
		const hex = color.substring(1);
		const bigint = parseInt(hex, 16);
		return {
			r: (bigint >> 16) & 255,
			g: (bigint >> 8) & 255,
			b: bigint & 255
		};
	}
	// For rgb/rgba colors
	else if (color.startsWith('rgb')) {
		const matches = color.match(/(\d+),\s*(\d+),\s*(\d+)/);
		if (matches) {
			return {
				r: parseInt(matches[1]),
				g: parseInt(matches[2]),
				b: parseInt(matches[3])
			};
		}
	}
	
	// Default fallback
	return { r: 0, g: 0, b: 0 };
}

// Initialize colors on load
extractEasyPieChartColors();

document.addEventListener('DOMContentLoaded', function () {

	/*
	Browsers optimize canvas rendering for drawing, not for reading pixel data. 
	When you use getImageData() repeatedly (as some chart libraries do for animations or gradients),
	it can cause readback performance hits.
	
	https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API/Tutorial/Optimizing_canvas
	*/
	(function patchEasyPieCanvasContext() {
		const originalGetContext = HTMLCanvasElement.prototype.getContext;
		HTMLCanvasElement.prototype.getContext = function(type, options) {
			if (type === '2d') {
				options = { ...options, willReadFrequently: true };
			}
			return originalGetContext.call(this, type, options);
		};
	})();

	/* 	Easy pie chart initialization
		Pure JavaScript implementation with no jQuery dependencies
	 */
	document.querySelectorAll('.js-easy-pie-chart').forEach(function(element) {
		// Get element properties using vanilla JS
		const computedStyle = window.getComputedStyle(element);
		const barcolor = computedStyle.color || 'var(--primary-700)';
		
		// Check if window.colorMap exists, if not use a fallback
		let trackcolor;
		try {
			if (window.colorMap && window.colorMap.bootstrapVars && window.colorMap.bootstrapVars.bodyColorRgb) {
				trackcolor = window.colorMap.bootstrapVars.bodyColorRgb.rgba(0.07);
			} else {
				trackcolor = 'rgba(0,0,0,0.04)';
			}
		} catch (e) {
			trackcolor = 'rgba(0,0,0,0.04)';
		}
		
		// Read dataset attributes with fallbacks
		const size = parseInt(element.dataset.piesize) || 50;
		const scalecolor = element.dataset.scalecolor || computedStyle.color;
		const scalelength = parseInt(element.dataset.scalelength) || 0;
		const linewidth = parseInt(element.dataset.linewidth) || parseInt(size / 8.5);
		const linecap = element.dataset.linecap || 'butt'; // butt, round and square.
		
		// Create EasyPieChart instance
		const chart = new EasyPieChart(element, {
			size: size,
			barColor: barcolor,
			trackColor: trackcolor,
			scaleColor: scalecolor,
			scaleLength: scalelength,
			lineCap: linecap,
			lineWidth: linewidth,
			animate: {
				duration: 1500,
				enabled: true
			},
			easing: 'easeOutQuad', // Use our built-in easing function
			onStep: function(from, to, percent) {
				// Find the percentage element and update its text
				const percentElement = element.querySelector('.js-percent');
				if (percentElement) {
					percentElement.textContent = Math.round(percent);
				}
			}
		});
		
		// Register the chart instance
		window.easyPieChartRegistry.instances.push(chart);
		window.easyPieChartRegistry.elements.push(element);
	});
});