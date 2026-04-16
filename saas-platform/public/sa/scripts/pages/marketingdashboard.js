
// All imports for the Marketing Dashboard
import ApexCharts from '../thirdparty/apexchartsWrapper.js';
import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';
import { PeityAPI } from './../thirdparty/peity.es6.js';


document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /***************************************************************/
    /* Marketing Performance Chart                                 */
    /***************************************************************/

    const marketingPerformanceChart = new ApexCharts(document.querySelector('#marketing-profits-chart'), {
        chart: {
            height: 350,
            type: 'line',

            stacked: false,
            toolbar: {
                show: false
            },
            zoom: {
                enabled: false
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800
            },
            parentHeightOffset: 0,
            fontFamily: 'inherit'
        },
        colors: [
            window.colorMap.primary[300].hex,
            window.colorMap.warning[300].hex,
            window.colorMap.success[300].hex
        ],
        stroke: {
            width: [0, 3, 3],
            curve: 'smooth'
        },
        fill: {
            type: ['gradient', 'solid', 'solid'],
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.5,
                gradientToColors: [window.colorMap.primary[200].hex],
                inverseColors: false,
                opacityFrom: 1,
                opacityTo: 0.3
            }
        },
        grid: {
            show: true,
            borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
            strokeDashArray: 5,
            position: 'back',
            padding: {
                left: -5,
                right: 0,
                top: -20,
                bottom: -5
            },
            xaxis: {
                lines: {
                    show: false
                }
            },
            yaxis: {
                lines: {
                    show: true
                }
            }
        },
        plotOptions: {
            bar: {
                columnWidth: '50%',
                endingShape: 'rounded',
                borderRadius: 5,
            }
        },
        dataLabels: {
            enabled: false
        },
        markers: {
            size: 4,
            strokeWidth: 0,
            hover: {
                size: 6
            }
        },
        xaxis: {
            type: 'category',
            categories: ['Jan 2013', 'Apr 2013', 'Jul 2013', 'Oct 2013', 'Jan 2014', 'Apr 2014', 'Jul 2014', 'Oct 2014', 'Jan 2015', 'Apr 2015', 'Jul 2015', 'Oct 2015', 'Jan 2016', 'Apr 2016'],
            labels: {
                style: {
                    colors: window.colorMap.bootstrapVars.bodyColor.rgba(0.7),
                    fontSize: '10px'
                }
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            min: 0,
            max: 1200,
            tickAmount: 6,
            labels: {
                style: {
                    colors: window.colorMap.bootstrapVars.bodyColor.rgba(0.7),
                    fontSize: '10px'
                },
                formatter: function (val) {
                    return val.toFixed(0);
                }
            }
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: function (y) {
                    if (typeof y !== "undefined") {
                        return y.toFixed(0);
                    }
                    return y;
                }
            }
        },
        legend: {
            show: false
        },
        series: [
            {
                name: 'Target Profit',
                type: 'column',
                data: [150, 650, 200, 650, 800, 1050, 350, 750, 500, 250, 650, 250, 350, 350]
            },
            {
                name: 'Actual Profit',
                type: 'line',
                data: [50, 70, 90, 80, 300, 950, 800, 700, 650, 30, 100, 80, 30, 30]
            },
            {
                name: 'User Signups',
                type: 'line',
                data: [650, 430, 800, 350, 450, 450, 450, 470, 250, 830, 650, 250, 350, 350]
            }
        ]
    });

    marketingPerformanceChart.render();

    /***************************************************************/
    /* Returning Target Chart                                      */
    /***************************************************************/

    const returningTargetChart = new ApexCharts(document.querySelector('#returning-target-chart'), {
        chart: {
            height: 350,
            type: 'area',
            stacked: false,
            toolbar: {
                show: false
            },
            zoom: {
                enabled: false
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800
            },
            parentHeightOffset: 0,
            fontFamily: 'inherit'
        },
        colors: [
            window.colorMap.success[200].hex, // New Customer (darker blue)
            window.colorMap.primary[200].hex,    // Returning Customer (lighter teal)
        ],
        stroke: {
            width: 2,
            curve: 'straight'
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.4,
                opacityFrom: 0.8,
                opacityTo: 0.1,
                stops: [0, 90, 100]
            }
        },
        grid: {
            show: true,
            borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.07),
            strokeDashArray: 0,
            position: 'back',
            padding: {
                left: 5
            },
            xaxis: {
                lines: {
                    show: false
                }
            },
            yaxis: {
                lines: {
                    show: false
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        markers: {
            size: 4,
            strokeWidth: 0,
            hover: {
                size: 6
            }
        },
        xaxis: {
            type: 'numeric',
            categories: [0, 100, 200, 300, 400, 500, 600, 700],
            tickAmount: 8,
            labels: {
                style: {
                    colors: window.colorMap.bootstrapVars.bodyColor.rgba(0.7),
                    fontSize: '10px'
                }
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            show: false,
            max: 180,
            tickAmount: 10,
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: function (y) {
                    if (typeof y !== "undefined") {
                        return y.toFixed(0);
                    }
                    return y;
                }
            }
        },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            fontFamily: 'inherit',
            offsetY: 10,
            offsetX: -35,
            itemMargin: {
                horizontal: 10,
                vertical: 0
            },
            markers: {
                width: 8,
                height: 8,
                radius: 0,
            }
        },
        series: [
            {
                name: 'Returning Customer',
                data: [140, 120, 95, 80, 60, 95, 70, 50]
            },
            {
                name: 'New Customer',
                data: [110, 90, 70, 55, 50, 75, 50, 30]
            },
        ]
    });

    returningTargetChart.render();

    /***************************************************************/
    /* Efficiency Metrics Chart                                    */
    /***************************************************************/

    const efficiencyMetricsChart = new ApexCharts(document.querySelector('#efficiency-metrics-chart'), {
        chart: {
            height: 259,
            type: 'area',
            stacked: false,
            toolbar: {
                show: false
            },
            zoom: {
                enabled: false
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 800
            },
            parentHeightOffset: 0,
            fontFamily: 'inherit'
        },
        colors: [
            window.colorMap.primary[300].hex,  // Sessions (blue)
            window.colorMap.success[300].hex,  // New Sessions (teal)
            window.colorMap.warning[300].hex,  // Bounce Rate (yellow)
            window.colorMap.info[300].hex      // Clickthrough (cyan)
        ],
        stroke: {
            width: 2,
            curve: 'straight'
        },
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.4,
                opacityFrom: 0.8,
                opacityTo: 0.1,
                stops: [0, 90, 100]
            }
        },
        grid: {
            show: true,
            borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
            strokeDashArray: 5,
            position: 'back',
            padding: {
                top: -25,
            },
            xaxis: {
                lines: {
                    show: true
                }
            },
            yaxis: {
                lines: {
                    show: true
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        markers: {
            size: 3,
            strokeWidth: 0,
            hover: {
                size: 5
            }
        },
        xaxis: {
            type: 'category',
            categories: ['2am', '3am', '4am', '5am', '6am', '7am', '8am', '9am', '1pm', '2pm', '3pm', '4pm'],
            labels: {
                style: {
                    colors: window.colorMap.bootstrapVars.bodyColor.rgba(0.7),
                    fontSize: '10px'
                }
            },
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            }
        },
        yaxis: {
            min: 0,
            max: 300000,
            tickAmount: 3,
            labels: {
                style: {
                    colors: window.colorMap.bootstrapVars.bodyColor.rgba(0.7),
                    fontSize: '10px'
                },
                formatter: function (val) {
                    if (val >= 1000) {
                        return (val / 1000) + 'K';
                    }
                    return val;
                }
            }
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: function (y) {
                    if (typeof y !== "undefined") {
                        if (y >= 1000) {
                            return Math.round(y).toLocaleString();
                        }
                        return y.toFixed(0);
                    }
                    return y;
                }
            }
        },
        legend: {
            show: false
        },
        series: [
            {
                name: 'Sessions',
                data: [20000, 25000, 40000, 50000, 60000, 150000, 190000, 180000, 200000, 100000, 100000, 90000]
            },
            {
                name: 'New Sessions',
                data: [15000, 30000, 35000, 60000, 80000, 110000, 230000, 200000, 180000, 230000, 160000, 100000]
            },
            {
                name: 'Bounce Rate',
                data: [10000, 15000, 20000, 25000, 30000, 40000, 60000, 80000, 100000, 120000, 140000, 160000]
            },
            {
                name: 'Clickthrough',
                data: [5000, 10000, 15000, 20000, 25000, 30000, 40000, 50000, 60000, 70000, 80000, 90000]
            }
        ]
    });

    efficiencyMetricsChart.render();

    /***************************************************************/
    /* Peity Charts                                             */
    /***************************************************************/

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
            let options = { ...customOptions };
            const dataAttr = element.getAttribute('data-peity');

            if (dataAttr) {
                try {
                    options = { ...options, ...JSON.parse(dataAttr) };
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

            setInterval(function () {
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

    /***************************************************************/
    /* Sales Performance Table                                     */
    /***************************************************************/

    // Initialize SmartTables with the table ID and options
    const table = new SmartTables('sales-performance-table', {
        data: {
            type: "json",
            source: "json/MOCK_DATA_SALES_PERF.json",
            columns: [
                { data: "CustomerID", title: "ID" },
                {
                    data: "Name",
                    title: "Full Name",
                    render: function (data) {
                        // Assuming data is in format "LastName, FirstName"
                        if (!data) return '';

                        const nameParts = data.split(',');
                        if (nameParts.length === 2) {
                            const lastName = nameParts[0].trim();
                            const firstName = nameParts[1].trim();
                            return firstName + ' ' + lastName;
                        }
                        return data;
                    }
                },
                {
                    data: "PurchaseDate",
                    title: "Purchase Date",
                    render: function (data) {
                        if (!data) return '';

                        try {
                            const date = new Date(data);
                            if (isNaN(date.getTime())) return data;

                            const day = date.getDate().toString().padStart(2, '0');
                            const month = (date.getMonth() + 1).toString().padStart(2, '0');
                            const year = date.getFullYear();

                            return `${day}-${month}-${year}`;
                        } catch (e) {
                            return data;
                        }
                    }
                },
                {
                    data: "CustomerCVV",
                    title: "CVV",
                    render: function (data) {
                        if (!data) return '';

                        const cvvId = 'cvv-' + Math.random().toString(36).substring(2, 10);
                        return `
                            <div class="d-flex align-items-center">
                                <span id="${cvvId}-masked">***</span>
                                <span id="${cvvId}-actual" class="d-none">${data}</span>
                                <button class="btn btn-link btn-sm p-0 ms-2 cvv-toggle" data-cvv-id="${cvvId}">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                    </svg>
                                </button>
                            </div>
                        `;
                    }
                },
                {
                    data: "Country",
                    title: "Country",
                    render: function (data) {
                        if (!data) return '';

                        // Get country code (assuming data has the country name)
                        let countryCode = '';

                        // Simple mapping for common countries
                        const countryMap = {
                            'United States': 'us',
                            'USA': 'us',
                            'US': 'us',
                            'United Kingdom': 'gb',
                            'UK': 'gb',
                            'Canada': 'ca',
                            'Australia': 'au',
                            'Germany': 'de',
                            'France': 'fr',
                            'Italy': 'it',
                            'Spain': 'es',
                            'Japan': 'jp',
                            'China': 'cn',
                            'India': 'in',
                            'Brazil': 'br',
                            'Mexico': 'mx',
                            'Russia': 'ru'
                        };

                        // Try to get code from map
                        countryCode = countryMap[data] || data.toLowerCase().substring(0, 2);

                        // Using a div placeholder instead of img to completely prevent loading
                        return `
                            <div class="d-flex align-items-center">
                                <div class="flag-placeholder d-inline-block me-1 border-faded" 
                                     data-country="${countryCode}"
                                     style="width: 20px; height: 15px; background-color: #f5f5f5;">
                                </div>
                                <span>${data}</span>
                            </div>
                        `;
                    }
                },
                { data: "InvoiceAmount", title: "Invoice Amount" },
                { data: "CustomerEmail", title: "Email" }
            ]
        },
        export: true,
        print: true,
        responsive: true,
        hooks: {
            afterInit: function () {
                // Add event listener for CVV toggles
                document.addEventListener('click', function (e) {
                    if (e.target.closest('.cvv-toggle')) {
                        const button = e.target.closest('.cvv-toggle');
                        const cvvId = button.getAttribute('data-cvv-id');
                        const maskedElement = document.getElementById(cvvId + '-masked');
                        const actualElement = document.getElementById(cvvId + '-actual');

                        if (maskedElement && actualElement) {
                            // Toggle visibility
                            if (maskedElement.classList.contains('d-none')) {
                                maskedElement.classList.remove('d-none');
                                actualElement.classList.add('d-none');
                                // Change icon to eye
                                button.innerHTML = `
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                    </svg>
                                `;
                            } else {
                                maskedElement.classList.add('d-none');
                                actualElement.classList.remove('d-none');
                                // Change icon to eye-slash
                                button.innerHTML = `
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye-slash" viewBox="0 0 16 16">
                                        <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/>
                                        <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.299.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 0 2.829 2.829z"/>
                                        <path d="M3.35 5.47c-.18.16-.353.322-.518.487A13.134 13.134 0 0 0 1.172 8l.195.288c.335.48.83 1.12 1.465 1.755C4.121 11.332 5.881 12.5 8 12.5c.716 0 1.39-.133 2.02-.36l.77.772A7.029 7.029 0 0 1 8 13.5C3 13.5 0 8 0 8s.939-1.721 2.641-3.238l.708.709zm10.296 8.884-12-12 .708-.708 12 12-.708.708z"/>
                                    </svg>
                                `;
                            }
                        }
                    }
                });

                // Wait for the table to be fully rendered before processing flags
                // Use a longer timeout to ensure the table is completely initialized
                setTimeout(function () {
                    // Only load flags for the first page after complete initialization
                    loadFlagsForVisibleRows();
                }, 1000);

                // Set up event listeners for search input
                const searchInputs = document.querySelectorAll('.st-search');
                searchInputs.forEach(function (input) {
                    input.addEventListener('input', function () {
                        // Debounce the search - wait until typing stops
                        clearTimeout(input.searchTimer);
                        input.searchTimer = setTimeout(function () {
                            // After search completes, load flags
                            setTimeout(loadFlagsForVisibleRows, 100);
                        }, 300);
                    });
                });

                // Set up event listeners for sorting
                const tableHeaders = document.querySelectorAll('#sales-performance-table th');
                tableHeaders.forEach(function (th) {
                    th.addEventListener('click', function () {
                        // After sorting completes, load flags
                        setTimeout(loadFlagsForVisibleRows, 100);
                    });
                });
            },
            onPaginate: function () {
                // This is called when user navigates to a new page
                // Use a shorter timeout as pagination is usually faster
                setTimeout(function () {
                    loadFlagsForVisibleRows();
                }, 50);
            },
            onFilter: function () {
                // This is called when filtering is applied
                setTimeout(function () {
                    loadFlagsForVisibleRows();
                }, 100);
            },
            afterDraw: function () {
                // This catches any other table redraw events
                setTimeout(function () {
                    loadFlagsForVisibleRows();
                }, 100);
            }
        }
    });

    // Global flag to track if initial flags have been loaded
    // This prevents multiple loads during initialization
    let initialFlagsLoaded = false;

    // Track if we're currently loading flags to prevent multiple simultaneous loads
    let isLoadingFlags = false;

    // Function to load flag images only for visible rows
    function loadFlagsForVisibleRows() {
        // Prevent multiple simultaneous calls
        if (isLoadingFlags) return;
        isLoadingFlags = true;

        try {
            // Get all flag placeholders
            const flagPlaceholders = document.querySelectorAll('.flag-placeholder');
            let loadedCount = 0;

            // Process each placeholder
            flagPlaceholders.forEach(function (placeholder) {
                // Check if the placeholder is in a visible row
                const row = placeholder.closest('tr');
                if (row && row.offsetParent !== null) {
                    // Get country code
                    const countryCode = placeholder.getAttribute('data-country');
                    if (!countryCode) return;

                    // Create and set up the image element
                    const img = document.createElement('img');
                    img.src = `https://lipis.github.io/flag-icons/flags/4x3/${countryCode}.svg`;
                    img.alt = countryCode;
                    img.className = 'd-inline-block me-1 border-faded';
                    img.style.width = '20px';
                    img.style.height = 'auto';

                    // Replace the placeholder with the image
                    placeholder.parentNode.replaceChild(img, placeholder);
                    loadedCount++;
                }
            });

            if (loadedCount > 0) {
                console.log(`Loaded ${loadedCount} flags for current view`);
            }
            initialFlagsLoaded = true;
        } finally {
            // Reset loading flag
            isLoadingFlags = false;
        }
    }

    // Make function accessible globally for manual triggering if needed
    window.loadTableFlags = loadFlagsForVisibleRows;
});