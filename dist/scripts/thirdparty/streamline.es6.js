/**
 * Streamline
 * A lightweight, dependency-free JavaScript library for generating micro carts
 */

const utils = {
    debounce: (fn, delay) => {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    },
    parseValues: (values) => {
        if (typeof values === 'string') {
            return values.split(',').map(val => {
                const parsed = parseFloat(val.trim());
                return isNaN(parsed) ? null : parsed;
            }).filter(val => val !== null);
        }
        return Array.isArray(values) ? values.filter(val => !isNaN(val) && val !== null) : [0];
    },
    parseDimension: (value, fallback) => {
        if (value === 'auto') return fallback;
        if (typeof value === 'number') return Math.round(value);
        if (typeof value === 'string') {
            const parsed = parseFloat(value);
            return isNaN(parsed) ? fallback : Math.round(parsed);
        }
        return fallback;
    },
    clamp: (value, min, max) => Math.min(Math.max(value, min), max),
    isValidColor: (color) => {
        if (!color || typeof color !== 'string') return false;
        const s = new Option().style;
        s.color = color;
        return s.color !== '';
    }
};

const defaultOptions = {
    type: 'line',
    width: 'auto',
    height: 'auto',
    lineColor: 'var(--primary-500)',
    fillColor: 'var(--primary-100)',
    lineWidth: 2,
    barColor: 'var(--primary-500)',
    negBarColor: 'var(--danger-500)',
    zeroBarColor: 'var(--bs-secondary)',
    barWidth: 4,
    barSpacing: 1,
    posBarColor: 'var(--success-500)',
    thresholdColor: 'var(--bs-secondary)',
    thresholdValue: 0,
    sliceColors: ['var(--primary-500)', 'var(--danger-500)', 'var(--bs-success)', 'var(--info-500)', 'var(--bs-warning)'],
    disableTooltips: false,
    tooltipPrefix: '',
    tooltipSuffix: '',
    tooltipOffsetX: 10,
    tooltipOffsetY: -20,
    responsive: true
};

function streamline(element, values, options = {}) {
    try {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (!element instanceof Element) {
            throw new Error('Invalid element provided');
        }
        if (!Array.isArray(values) && typeof values === 'object') {
            options = values;
            values = undefined;
        }
        values = values || utils.parseValues(element.getAttribute('data-values') || element.textContent);
        options = { ...defaultOptions, ...options };
        if (element.chartData) {
            cleanupChart(element.chartData);
        }
        const { svg, chartGroup } = initializeSVG(element, options);
        const chartData = {
            element,
            svg,
            values,
            options,
            pointCoordinates: []
        };
        element.chartData = chartData;
        drawChart(svg, values, options);
        setupAccessibility(element, options, values);
        if (!options.disableTooltips) {
            addInteraction(chartData);
        }
        if (options.responsive) {
            setupResponsive(chartData);
        }
        return element;
    } catch (error) {
        console.error('Streamline initialization failed:', error);
        return element;
    }
}

function cleanupChart(chartData) {
    const { element, svg } = chartData;
    if (chartData.resizeHandler) {
        window.removeEventListener('resize', chartData.resizeHandler);
    }
    if (chartData.zoomHandler) {
        window.removeEventListener('wheel', chartData.zoomHandler);
    }
    if (chartData.tooltip) {
        chartData.tooltip.remove();
        svg.removeEventListener('mousemove', chartData.mouseMoveHandler);
        svg.removeEventListener('mouseout', chartData.mouseOutHandler);
        svg.removeEventListener('mouseleave', chartData.mouseLeaveHandler);
    }
    element.chartData = null;
}

function initializeSVG(element, options) {
    element.innerHTML = '';
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const parentStyle = window.getComputedStyle(element);
    const parentWidth = element.offsetWidth || parseFloat(parentStyle.width) || 100;
    const parentHeight = element.offsetHeight || parseFloat(parentStyle.height) || 30;
    const width = utils.parseDimension(options.width, parentWidth);
    const height = utils.parseDimension(options.height, parentHeight);

    // Set up crisp rendering with proper scaling
    const scale = window.devicePixelRatio || 1;
    const scaledWidth = width * scale;
    const scaledHeight = height * scale;

    svg.setAttribute('width', scaledWidth);
    svg.setAttribute('height', scaledHeight);
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    svg.style.display = 'block';
    svg.style.overflow = 'visible';

    // Add a group for all chart elements with proper scaling
    const chartGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    chartGroup.setAttribute('shape-rendering', 'crispEdges');
    chartGroup.setAttribute('stroke-width', (1 / scale).toString());
    chartGroup.setAttribute('transform', `scale(${scale})`);
    svg.appendChild(chartGroup);

    element.appendChild(svg);

    // Store scale factor for later use
    svg.scaleFactor = scale;
    return { svg, chartGroup };
}

function setupAccessibility(element, options, values) {
    element.setAttribute('role', 'img');
    const description = generateChartDescription(options, values);
    element.setAttribute('aria-label', description);
    const descId = `streamline-desc-${Math.random().toString(36).substr(2, 9)}`;
    const descElement = document.createElement('span');
    descElement.id = descId;
    Object.assign(descElement.style, {
        position: 'absolute',
        width: '1px',
        height: '1px',
        padding: '0',
        margin: '-1px',
        overflow: 'hidden',
        clip: 'rect(0, 0, 0, 0)',
        border: '0'
    });
    descElement.textContent = description;
    document.body.appendChild(descElement);
    element.setAttribute('aria-describedby', descId);
}

function generateChartDescription(options, values) {
    const type = options.type.charAt(0).toUpperCase() + options.type.slice(1);
    const valueCount = values.filter(v => v !== null).length;
    const min = Math.min(...values.filter(v => v !== null));
    const max = Math.max(...values.filter(v => v !== null));
    return `${type} chart with ${valueCount} data points, ranging from ${min.toFixed(2)} to ${max.toFixed(2)}`;
}

function setupResponsive(chartData) {
    const { element, svg, values, options } = chartData;
    const resizeHandler = utils.debounce(() => {
        const parentStyle = window.getComputedStyle(element);
        const parentWidth = element.offsetWidth || parseFloat(parentStyle.width) || 100;
        const parentHeight = element.offsetHeight || parseFloat(parentStyle.height) || 30;
        const width = utils.parseDimension(options.width, parentWidth);
        const height = utils.parseDimension(options.height, parentHeight);
        svg.setAttribute('width', width);
        svg.setAttribute('height', height);
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }
        drawChart(svg, values, options);
        if (element.chartData) {
            element.chartData.pointCoordinates = [];
            if (element.chartData.recachePoints) {
                element.chartData.recachePoints();
            }
        }
    }, 100);
    chartData.resizeHandler = resizeHandler;
    window.addEventListener('resize', resizeHandler);
}

function drawChart(svg, values, options) {
    const chartGroup = svg.querySelector('g') || svg;
    while (chartGroup.firstChild) {
        chartGroup.removeChild(chartGroup.firstChild);
    }
    const chartTypes = {
        line: drawLineChart,
        bar: drawBarChart,
        tristate: drawTristateChart,
        discrete: drawDiscreteChart,
        pie: drawPieChart
    };
    const drawFn = chartTypes[options.type] || drawLineChart;
    drawFn(svg, values, options);
}

function drawLineChart(svg, values, options) {
    const width = parseInt(svg.getAttribute('width')) / (svg.scaleFactor || 1);
    const height = parseInt(svg.getAttribute('height')) / (svg.scaleFactor || 1);
    const padding = 4;
    const cleanValues = values.filter(v => v !== null && !isNaN(v));
    if (!cleanValues.length) return;
    const minValue = Math.min(...cleanValues);
    const maxValue = Math.max(...cleanValues);
    const valueRange = maxValue === minValue ? maxValue || 1 : maxValue - minValue;
    const xScale = (width - padding * 2) / Math.max(1, values.length - 1);
    const yScale = (height - padding * 2) / valueRange;

    // Clear existing content
    while (svg.firstChild) {
        if (svg.firstChild.tagName !== 'defs') {
            svg.removeChild(svg.firstChild);
        }
    }

    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    svg.appendChild(group);

    const points = [];
    const areaPoints = [];
    let pointCoordinates = [];

    areaPoints.push(`${padding},${height - padding}`);

    values.forEach((val, i) => {
        if (val !== null && !isNaN(val)) {
            const x = padding + i * xScale;
            const y = height - padding - (val - minValue) * yScale;
            points.push(`${x},${y}`);
            areaPoints.push(`${x},${y}`);
            pointCoordinates.push({ x, y, value: val });
        } else {
            pointCoordinates.push(null);
        }
    });

    areaPoints.push(`${width - padding},${height - padding}`);

    if (options.fillColor) {
        const area = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        area.setAttribute('points', areaPoints.join(' '));
        area.setAttribute('fill', options.fillColor);
        area.setAttribute('stroke', 'none');
        group.appendChild(area);
    }

    const line = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
    line.setAttribute('points', points.join(' '));
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', options.lineColor);
    line.setAttribute('stroke-width', options.lineWidth.toString());
    line.setAttribute('stroke-linecap', 'round');
    line.setAttribute('stroke-linejoin', 'round');
    line.setAttribute('vector-effect', 'non-scaling-stroke');
    group.appendChild(line);

    // Store point coordinates for tooltips
    svg.pointCoordinates = pointCoordinates;
}

function drawBarChart(svg, values, options) {
    const scale = svg.scaleFactor || 1;
    const width = parseInt(svg.getAttribute('width')) / scale;
    const height = parseInt(svg.getAttribute('height')) / scale;
    const padding = 2;
    const cleanValues = values.filter(v => v !== null && !isNaN(v));
    if (!cleanValues.length) return;

    // Clear existing content
    while (svg.firstChild) {
        if (svg.firstChild.tagName !== 'defs') {
            svg.removeChild(svg.firstChild);
        }
    }

    const minValue = Math.min(0, ...cleanValues);
    const maxValue = Math.max(...cleanValues);
    const valueRange = maxValue === minValue ? maxValue || 1 : maxValue - minValue;
    const barCount = cleanValues.length;
    const barWidth = Math.max(1, Math.min(options.barWidth, (width - padding * 2) / barCount - options.barSpacing));
    const yScale = (height - padding * 2) / valueRange;

    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    group.setAttribute('vector-effect', 'non-scaling-stroke');
    svg.appendChild(group);

    const zeroY = height - padding - (0 - minValue) * yScale;
    let barIndex = 0;
    let pointCoordinates = [];

    values.forEach((val, i) => {
        if (val !== null && !isNaN(val)) {
            const x = Math.max(0, padding + barIndex * (barWidth + options.barSpacing));
            const barHeight = Math.max(1, Math.abs(val * yScale));
            const y = val >= 0 ? Math.min(height - barHeight, zeroY - barHeight) : Math.min(height - barHeight, zeroY);

            const color = val < 0 ? options.negBarColor :
                val === 0 ? options.zeroBarColor :
                    options.barColor;

            const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            rect.setAttribute('x', x.toString());
            rect.setAttribute('y', y.toString());
            rect.setAttribute('width', barWidth.toString());
            rect.setAttribute('height', barHeight.toString());
            rect.setAttribute('fill', color);
            rect.setAttribute('shape-rendering', 'crispEdges');
            group.appendChild(rect);

            pointCoordinates.push({
                x: x + barWidth / 2,
                y: y + barHeight / 2,
                value: val,
                barLeft: x,
                barTop: y,
                barWidth,
                barHeight
            });
            barIndex++;
        } else {
            pointCoordinates.push(null);
        }
    });

    // Store point coordinates for tooltips
    svg.pointCoordinates = pointCoordinates;
}

function drawTristateChart(svg, values, options) {
    const width = parseInt(svg.getAttribute('width')) || parseInt(svg.closest('svg').getAttribute('width'));
    const height = parseInt(svg.getAttribute('height')) || parseInt(svg.closest('svg').getAttribute('height'));
    const padding = 2;
    const validValues = values.filter(v => v !== null && !isNaN(v));
    if (!validValues.length) return;
    const barCount = validValues.length;
    const barWidth = Math.max(1, Math.min(options.barWidth, (width - padding * 2) / barCount - options.barSpacing));
    const barHeight = Math.max(1, (height - padding * 2) / 3);
    const zeroY = padding + (height - padding * 2) / 2;
    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    svg.appendChild(group);
    let barIndex = 0;
    let pointCoordinates = [];
    values.forEach((val, i) => {
        if (val !== null) {
            const x = Math.max(0, padding + barIndex * (barWidth + options.barSpacing));
            let y, color;
            if (val === -1) {
                y = zeroY;
                color = options.negBarColor;
            } else if (val === 0) {
                y = zeroY - barHeight / 2;
                color = options.zeroBarColor;
            } else {
                y = zeroY - barHeight;
                color = options.posBarColor;
            }
            const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            rect.setAttribute('x', x.toString());
            rect.setAttribute('y', y.toString());
            rect.setAttribute('width', barWidth.toString());
            rect.setAttribute('height', barHeight.toString());
            rect.setAttribute('fill', color);
            group.appendChild(rect);
            pointCoordinates.push({
                x: x + barWidth / 2,
                y: y + barHeight / 2,
                value: val,
                barLeft: x,
                barTop: y,
                barWidth,
                barHeight
            });
            barIndex++;
        } else {
            pointCoordinates.push(null);
        }
    });
    svg.pointCoordinates = pointCoordinates;
}

function drawDiscreteChart(svg, values, options) {
    const width = parseInt(svg.getAttribute('width'));
    const height = parseInt(svg.getAttribute('height'));
    const padding = 2;
    const cleanValues = values.filter(v => v !== null && !isNaN(v));
    if (!cleanValues.length) return;
    const minValue = Math.min(...cleanValues);
    const maxValue = Math.max(...cleanValues);
    const valueRange = maxValue === minValue ? maxValue || 1 : maxValue - minValue;
    const xScale = (width - padding * 2) / Math.max(1, cleanValues.length - 1);
    const yScale = (height - padding * 2) / valueRange;
    const lineHeight = Math.round(height * 0.3);
    const pHeight = height - lineHeight;
    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    svg.appendChild(group);
    if (options.thresholdValue !== undefined && options.thresholdColor) {
        const thresholdY = height - padding - (options.thresholdValue - minValue) * yScale;
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', padding);
        line.setAttribute('y1', thresholdY);
        line.setAttribute('x2', width - padding);
        line.setAttribute('y2', thresholdY);
        line.setAttribute('stroke', options.thresholdColor);
        line.setAttribute('stroke-width', 1);
        group.appendChild(line);
    }
    let validIndex = 0;
    let pointCoordinates = [];
    values.forEach((val, i) => {
        if (val !== null) {
            const x = padding + validIndex * xScale;
            const ytop = Math.round(pHeight - pHeight * ((val - minValue) / valueRange));
            const color = options.thresholdValue !== undefined && val < options.thresholdValue ?
                options.thresholdColor : options.lineColor;
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', x);
            line.setAttribute('y1', ytop);
            line.setAttribute('x2', x);
            line.setAttribute('y2', ytop + lineHeight);
            line.setAttribute('stroke', color);
            line.setAttribute('stroke-width', options.lineWidth);
            group.appendChild(line);
            pointCoordinates.push({
                x,
                y: ytop + lineHeight / 2,
                value: val,
                lineTop: ytop,
                lineHeight: lineHeight
            });
            validIndex++;
        } else {
            pointCoordinates.push(null);
        }
    });
    svg.pointCoordinates = pointCoordinates;
}

function drawPieChart(svg, values, options) {
    const width = parseInt(svg.getAttribute('width'));
    const height = parseInt(svg.getAttribute('height'));
    const cleanValues = values.filter(v => v !== null && v > 0);
    if (!cleanValues.length) return;
    const total = cleanValues.reduce((sum, val) => sum + val, 0);
    const radius = Math.min(width, height) / 2 - 2;
    const centerX = width / 2;
    const centerY = height / 2;
    const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    svg.appendChild(group);
    let startAngle = 0;
    const sliceInfo = [];
    cleanValues.forEach((val, i) => {
        const sliceAngle = (val / total) * Math.PI * 2;
        const endAngle = startAngle + sliceAngle;
        const color = options.sliceColors[i % options.sliceColors.length];
        const x1 = centerX + radius * Math.cos(startAngle);
        const y1 = centerY + radius * Math.sin(startAngle);
        const x2 = centerX + radius * Math.cos(endAngle);
        const y2 = centerY + radius * Math.sin(endAngle);
        const largeArcFlag = sliceAngle > Math.PI ? 1 : 0;
        const pathData = [
            `M ${centerX} ${centerY}`,
            `L ${x1} ${y1}`,
            `A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2}`,
            'Z'
        ].join(' ');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', pathData);
        path.setAttribute('fill', color);
        path.setAttribute('stroke', 'none');
        group.appendChild(path);
        sliceInfo.push({
            startAngle,
            endAngle,
            value: val,
            color
        });
        startAngle = endAngle;
    });
    svg.sliceInfo = sliceInfo;
    svg.pointCoordinates = [{
        centerX,
        centerY,
        radius,
        sliceInfo: sliceInfo.map((slice, index) => ({
            ...slice,
            index
        }))
    }];
}

function addInteraction(chartData) {
    const { element, svg, values, options } = chartData;
    let tooltip = null;
    let activePointIndex = -1;
    let activeElement = null;
    let isTooltipVisible = false;
    let positionUpdateTimeout = null;
    let currentIndex = -1;
    let lastAngle = null;
    let cachedTipWidth = 100;
    let cachedTipHeight = 30;
    const pointCoordinates = svg.pointCoordinates || [];
    const createTooltip = () => {
        if (!tooltip) {
            console.log('Creating tooltip');
            tooltip = document.createElement('div');
            Object.assign(tooltip.style, {
                position: 'fixed',
                padding: '6px 10px',
                backgroundColor: 'rgba(0, 0, 0, 0.9)',
                color: '#fff',
                borderRadius: '4px',
                fontSize: '13px',
                fontFamily: 'Arial, sans-serif',
                pointerEvents: 'none',
                zIndex: '10000',
                opacity: '0',
                boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
                transition: 'opacity 0.1s ease, left 0.1s ease, top 0.1s ease'
            });
            document.body.appendChild(tooltip);
            chartData.tooltip = tooltip;
        }
        return tooltip;
    };
    const getPointColor = (index, value) => {
        if (options.type === 'pie' && pointCoordinates[0]?.sliceInfo[index]) {
            return pointCoordinates[0].sliceInfo[index].color;
        } else if (options.type === 'bar') {
            if (options.barColors) {
                return value < 0 ? options.barColors.negative :
                    value === 0 ? options.barColors.zero :
                        options.barColors.positive;
            }
            return value < 0 ? options.negBarColor :
                value === 0 ? options.zeroBarColor :
                    options.barColor;
        } else if (options.type === 'tristate') {
            return value === -1 ? options.negBarColor :
                value === 0 ? options.zeroBarColor :
                    options.posBarColor;
        } else if (options.type === 'discrete' && options.thresholdValue !== undefined &&
            value < options.thresholdValue) {
            return options.thresholdColor;
        }
        return options.lineColor;
    };
    const findNearestPoint = (x, y) => {
        if (!pointCoordinates || pointCoordinates.length === 0) {
            console.log('No point coordinates available');
            return -1;
        }
        if (options.type === 'pie') {
            if (!pointCoordinates[0]?.sliceInfo) {
                console.log('No slice info for pie chart');
                return -1;
            }
            const { centerX, centerY, radius, sliceInfo } = pointCoordinates[0];
            const dx = x - centerX;
            const dy = y - centerY;
            const distance = Math.sqrt(dx * dx + dy * dy);
            if (distance > radius) return -1;
            let angle = Math.atan2(dy, dx);
            if (angle < 0) angle += Math.PI * 2;
            const angleThreshold = Math.PI / 180;
            if (lastAngle !== null && Math.abs(angle - lastAngle) < angleThreshold) {
                return currentIndex;
            }
            lastAngle = angle;
            for (let i = 0; i < sliceInfo.length; i++) {
                const { startAngle, endAngle } = sliceInfo[i];
                if (angle >= startAngle && angle <= endAngle) {
                    console.log(`Pie slice ${i} detected`);
                    return i;
                }
            }
            return -1;
        } else if (options.type === 'bar' || options.type === 'tristate') {
            const hitAreaExpansion = 5;
            for (let i = 0; i < pointCoordinates.length; i++) {
                const point = pointCoordinates[i];
                if (!point) continue;
                const { barLeft, barTop, barWidth, barHeight } = point;
                const barRight = barLeft + barWidth;
                const barBottom = barTop + barHeight;
                if (x >= barLeft - hitAreaExpansion &&
                    x <= barRight + hitAreaExpansion &&
                    y >= barTop - hitAreaExpansion &&
                    y <= barBottom + hitAreaExpansion) {
                    console.log(`Bar/tristate point ${i} detected`);
                    return i;
                }
            }
            return -1;
        } else if (options.type === 'discrete') {
            for (let i = 0; i < pointCoordinates.length; i++) {
                const point = pointCoordinates[i];
                if (!point) continue;
                const hitAreaWidth = Math.max(15, options.lineWidth * 4);
                const hitAreaHeight = point.lineHeight;
                if (Math.abs(x - point.x) <= hitAreaWidth / 2 &&
                    y >= point.lineTop && y <= point.lineTop + hitAreaHeight) {
                    console.log(`Discrete point ${i} detected`);
                    return i;
                }
            }
            return -1;
        }
        let minDistance = Infinity;
        let nearestIndex = -1;
        const hitRadius = 20;
        pointCoordinates.forEach((point, i) => {
            if (point && point.x !== undefined && point.y !== undefined) {
                const dx = x - point.x;
                const dy = y - point.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                if (distance < minDistance && distance <= hitRadius) {
                    minDistance = distance;
                    nearestIndex = i;
                }
            }
        });
        if (nearestIndex >= 0) console.log(`Line point ${nearestIndex} detected`);
        return nearestIndex;
    };
    const highlightHoveredElement = (index) => {
        if (activeElement) {
            activeElement.remove();
            activeElement = null;
        }
        if (index < 0) return;
        if (options.type === 'pie') {
            const { centerX, centerY, radius, sliceInfo } = pointCoordinates[0];
            if (!sliceInfo[index]) return;
            const { startAngle, endAngle, color } = sliceInfo[index];
            const highlightRadius = radius * 1.03;
            const x1 = centerX + highlightRadius * Math.cos(startAngle);
            const y1 = centerY + highlightRadius * Math.sin(startAngle);
            const x2 = centerX + highlightRadius * Math.cos(endAngle);
            const y2 = centerY + highlightRadius * Math.sin(endAngle);
            const sliceAngle = endAngle - startAngle;
            const largeArcFlag = sliceAngle > Math.PI ? 1 : 0;
            const pathData = [
                `M ${centerX} ${centerY}`,
                `L ${x1} ${y1}`,
                `A ${highlightRadius} ${highlightRadius} 0 ${largeArcFlag} 1 ${x2} ${y2}`,
                'Z'
            ].join(' ');
            const highlightPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            highlightPath.setAttribute('d', pathData);
            highlightPath.setAttribute('fill', color);
            highlightPath.setAttribute('opacity', '0.7');
            svg.appendChild(highlightPath);
            activeElement = highlightPath;
        } else if (options.type === 'bar') {
            const point = pointCoordinates[index];
            if (!point) return;
            const { barLeft, barTop, barWidth, barHeight } = point;
            const val = values[index];
            const color = getPointColor(index, val);
            const highlightRect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            highlightRect.setAttribute('x', barLeft.toString());
            highlightRect.setAttribute('y', barTop.toString());
            highlightRect.setAttribute('width', barWidth.toString());
            highlightRect.setAttribute('height', barHeight.toString());
            highlightRect.setAttribute('fill', color);
            highlightRect.setAttribute('opacity', '0.7');
            svg.appendChild(highlightRect);
            activeElement = highlightRect;
        } else if (options.type === 'tristate') {
            const point = pointCoordinates[index];
            if (!point) return;
            const { barLeft, barTop, barWidth, barHeight } = point;
            const val = values[index];
            const color = getPointColor(index, val);
            const highlightRect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            highlightRect.setAttribute('x', barLeft.toString());
            highlightRect.setAttribute('y', barTop.toString());
            highlightRect.setAttribute('width', barWidth.toString());
            highlightRect.setAttribute('height', barHeight.toString());
            highlightRect.setAttribute('fill', color);
            highlightRect.setAttribute('opacity', '0.7');
            svg.appendChild(highlightRect);
            activeElement = highlightRect;
        } else if (options.type === 'discrete') {
            const point = pointCoordinates[index];
            if (!point) return;
            const { x, lineTop, lineHeight } = point;
            const val = values[index];
            const color = getPointColor(index, val);
            const highlightLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            highlightLine.setAttribute('x1', x.toString());
            highlightLine.setAttribute('y1', lineTop.toString());
            highlightLine.setAttribute('x2', x.toString());
            highlightLine.setAttribute('y2', (lineTop + lineHeight).toString());
            highlightLine.setAttribute('stroke', color);
            highlightLine.setAttribute('stroke-width', (options.lineWidth + 1).toString());
            svg.appendChild(highlightLine);
            activeElement = highlightLine;
        } else if (options.type === 'line') {
            const point = pointCoordinates[index];
            if (!point) return;
            const { x, y } = point;
            const highlightCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            highlightCircle.setAttribute('cx', x.toString());
            highlightCircle.setAttribute('cy', y.toString());
            highlightCircle.setAttribute('r', '5');
            highlightCircle.setAttribute('fill', options.lineColor);
            highlightCircle.setAttribute('fill-opacity', '0.3');
            highlightCircle.setAttribute('stroke', options.lineColor);
            highlightCircle.setAttribute('stroke-width', '2');
            svg.appendChild(highlightCircle);
            activeElement = highlightCircle;
        }
    };
    const updateTooltipContent = (index) => {
        const tip = createTooltip();
        tip.innerHTML = '';
        if (options.type === 'pie') {
            if (index < 0 || !pointCoordinates[0]?.sliceInfo[index]) {
                return false;
            }
            const { value, color } = pointCoordinates[0].sliceInfo[index];
            const total = values.reduce((sum, val) => sum + (val || 0), 0);
            const percentage = ((value / total) * 100).toFixed(1);
            const colorDot = document.createElement('span');
            Object.assign(colorDot.style, {
                display: 'inline-block',
                width: '10px',
                height: '10px',
                borderRadius: '50%',
                backgroundColor: color
            });
            const textSpan = document.createElement('span');
            textSpan.innerHTML = `${options.tooltipPrefix}${value.toFixed(2)}${options.tooltipSuffix} <small>(${percentage}%)</small>`;
            tip.appendChild(colorDot);
            tip.appendChild(textSpan);
            console.log(`Pie tooltip updated: ${value}`);
            return true;
        } else {
            if (index < 0 || !pointCoordinates[index]) {
                return false;
            }
            const { value } = pointCoordinates[index];
            const color = getPointColor(index, value);
            const colorDot = document.createElement('span');
            Object.assign(colorDot.style, {
                display: 'inline-block',
                width: '10px',
                height: '10px',
                borderRadius: '50%',
                backgroundColor: color
            });
            const textSpan = document.createElement('span');
            if (options.type === 'tristate') {
                let stateText = value === 1 ? "Positive" : (value === -1 ? "Negative" : "Neutral");
                textSpan.textContent = `${stateText}: ${options.tooltipPrefix}${value}${options.tooltipSuffix}`;
            } else {
                textSpan.textContent = `${options.tooltipPrefix}${typeof value === 'number' ? value.toFixed(2) : value}${options.tooltipSuffix}`;
            }
            tip.appendChild(colorDot);
            tip.appendChild(textSpan);
            console.log(`Tooltip updated: ${value}`);
            return true;
        }
    };
    const updateTooltipPosition = (mouseX, mouseY) => {
        if (positionUpdateTimeout) return;
        positionUpdateTimeout = setTimeout(() => {
            positionUpdateTimeout = null;
            const tip = createTooltip();
            if (!tip || !isTooltipVisible) return;
            let left = mouseX + options.tooltipOffsetX;
            let top = mouseY + options.tooltipOffsetY;
            if (left + cachedTipWidth > window.innerWidth) {
                left = mouseX - cachedTipWidth - 5;
            }
            if (top + cachedTipHeight > window.innerHeight) {
                top = mouseY - cachedTipHeight - 5;
            }
            if (top < 0) top = mouseY + 15;
            tip.style.left = `${left}px`;
            tip.style.top = `${top}px`;
            cachedTipWidth = tip.offsetWidth || cachedTipWidth;
            cachedTipHeight = tip.offsetHeight || cachedTipHeight;
            console.log(`Tooltip positioned at (${left}, ${top})`);
        }, 10);
    };
    const showTooltip = (index, mouseX, mouseY) => {
        if (index < 0 || (options.type !== 'pie' && !pointCoordinates[index]) || (options.type === 'pie' && !pointCoordinates[0]?.sliceInfo[index])) {
            hideTooltip();
            currentIndex = -1;
            return;
        }

        if (index !== currentIndex || !isTooltipVisible) {
            currentIndex = index;
            if (updateTooltipContent(index)) {
                const tip = createTooltip();
                tip.style.opacity = '1';
                isTooltipVisible = true;
                activePointIndex = index;
                highlightHoveredElement(index);
            }
        }
        updateTooltipPosition(mouseX, mouseY);
    };
    const hideTooltip = () => {
        clearTimeout(positionUpdateTimeout);
        positionUpdateTimeout = null;
        const tip = createTooltip();
        tip.style.opacity = '0';
        highlightHoveredElement(-1);
        activePointIndex = -1;
        isTooltipVisible = false;
        console.log('Tooltip hidden');
    };
    const handleMouseMove = (e) => {
        const rect = svg.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        let scaleX = 1, scaleY = 1;
        const viewBoxAttr = svg.getAttribute('viewBox');
        if (viewBoxAttr) {
            const viewBox = viewBoxAttr.split(' ').map(parseFloat);
            if (viewBox.length === 4) {
                scaleX = viewBox[2] / rect.width;
                scaleY = viewBox[3] / rect.height;
            }
        } else {
            const svgWidth = parseInt(svg.getAttribute('width'));
            const svgHeight = parseInt(svg.getAttribute('height'));
            if (svgWidth && svgHeight) {
                scaleX = svgWidth / rect.width;
                scaleY = svgHeight / rect.height;
            }
        }
        const scaledX = x * scaleX;
        const scaledY = y * scaleY;
        const nearestIndex = findNearestPoint(scaledX, scaledY);
        if (nearestIndex >= 0) {
            showTooltip(nearestIndex, e.clientX, e.clientY);
        } else {
            hideTooltip();
            currentIndex = -1;
        }
    };
    const handleMouseOut = (e) => {
        const rect = svg.getBoundingClientRect();
        if (e.clientX < rect.left || e.clientX > rect.right ||
            e.clientY < rect.top || e.clientY > rect.bottom) {
            hideTooltip();
            currentIndex = -1;
        }
    };
    const handleMouseLeave = () => {
        hideTooltip();
        currentIndex = -1;
    };
    chartData.recachePoints = () => {
        drawChart(svg, values, options);
        hideTooltip();
        currentIndex = -1;
    };
    chartData.mouseMoveHandler = handleMouseMove;
    chartData.mouseOutHandler = handleMouseOut;
    chartData.mouseLeaveHandler = handleMouseLeave;
    svg.addEventListener('mousemove', handleMouseMove);
    svg.addEventListener('mouseout', handleMouseOut);
    svg.addEventListener('mouseleave', handleMouseLeave);
    console.log('Interaction handlers added to SVG');
}

function initStreamlines() {
    if (typeof document === 'undefined') return;
    const initialize = () => {
        document.querySelectorAll('.streamline').forEach(element => {
            if (element.querySelector('svg')) return;
            const type = element.dataset.type || 'line';
            const options = { type };
            const computedStyle = window.getComputedStyle(element);
            for (const [key, value] of Object.entries(element.dataset)) {
                const optKey = key.replace(/^data-/, '').replace(/-./g, c => c[1].toUpperCase());
                if (value && typeof value === 'string' && value.includes('var(')) {
                    const temp = document.createElement('div');
                    temp.style.color = value;
                    document.body.appendChild(temp);
                    const resolvedColor = window.getComputedStyle(temp).color;
                    document.body.removeChild(temp);
                    options[optKey] = resolvedColor;
                } else {
                    try {
                        options[optKey] = JSON.parse(value);
                    } catch {
                        options[optKey] = value;
                    }
                }
            }
            streamline(element, options);
        });
    };
    document.readyState === 'loading' ?
        document.addEventListener('DOMContentLoaded', initialize) :
        initialize();
}

export { streamline, initStreamlines };