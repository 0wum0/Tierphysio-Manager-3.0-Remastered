


import ApexCharts from '../thirdparty/apexchartsWrapper.js';
import { PeityAPI } from './../thirdparty/peity.es6.js';


// Control Center Dashboard
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /***************************************************************/
    /* Script for the area chart with live updates but no animation*/
    /***************************************************************/

    // Generate initial random data with natural patterns
    const generateData = (count) => {
        const data = [];
        let value = 50; // Start from middle
        for (let i = 0; i < count; i++) {
            // Create more natural movements with smaller changes
            const change = (Math.random() - 0.5) * 8; // Smaller random changes
            value = Math.min(Math.max(value + change, 10), 90); // Keep within bounds
            data.push(Math.round(value));
        }
        return data;
    };

    // Area Chart
    const areaChartOptions = {
        series: [{
            name: 'Activity',
            data: generateData(180)
        }],
        chart: {
            type: 'area',
            height: 300,
            toolbar: {
                show: false
            },
            zoom: {
                enabled: false
            },
            animations: {
                enabled: false
            },
            parentHeightOffset: 0,
            sparkline: {
                enabled: false
            }
        },
        colors: [window.colorMap.primary[500].hex],
        dataLabels: {
            enabled: false
        },
        tooltip: {
            enabled: true,
            followCursor: true
        },
        stroke: {
            curve: 'straight',
            width: 1.5,
            colors: [window.colorMap.primary[500].hex]
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [0, 95, 100],
                colorStops: [
                    {
                        offset: 0,
                        color: window.colorMap.primary[500].hex,
                        opacity: 0.4
                    },
                    {
                        offset: 100,
                        color: window.colorMap.primary[500].hex,
                        opacity: 0.1
                    }
                ]
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
        xaxis: {
            type: 'numeric',
            tickAmount: 10,
            labels: {
                style: {
                    colors: window.colorMap.bootstrapVars.bodyColor.rgba(0.5),
                    fontSize: '10px',
                },
                show: true,
                offsetX: 10
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
            max: 100,
            tickAmount: 5,
            show: true,
            labels: {
                style: {
                    colors: window.colorMap.bootstrapVars.bodyColor.rgba(0.5),
                    fontSize: '10px',
                },
                formatter: function (value) {
                    return value;
                },
                show: true,
                offsetX: -15,
                align: 'left'
            }
        },
        legend: {
            show: false
        }
    };

    // Initialize the chart
    const areaChart = new ApexCharts(
        document.querySelector('#apex-chart-1'),
        areaChartOptions
    );
    areaChart.render();

    // Create and add the live update checkbox
    const controlDiv = document.createElement('div');
    controlDiv.className = 'mb-0 position-relative';
    controlDiv.innerHTML = `
        <div class="form-check form-switch position-absolute z-1" style="top: 1.5rem; left: 2.5rem;">
            <input class="form-check-input" type="checkbox" id="liveUpdateCheck">
            <label class="form-check-label" for="liveUpdateCheck">
                Live Update
            </label>
        </div>
    `;
    document.querySelector('#apex-chart-1').parentNode.insertBefore(controlDiv, document.querySelector('#apex-chart-1'));

    // Live update functionality with smoother transitions
    let updateInterval;
    const checkbox = document.querySelector('#liveUpdateCheck');

    checkbox.addEventListener('change', function () {
        if (this.checked) {
            // Start live updates
            updateInterval = setInterval(() => {
                const currentData = areaChart.w.config.series[0].data;
                const lastValue = currentData[currentData.length - 1];
                const change = (Math.random() - 0.5) * 8;
                const newValue = Math.round(Math.min(Math.max(lastValue + change, 10), 90));

                const newData = [...currentData.slice(1), newValue];
                areaChart.updateSeries([{
                    data: newData
                }], true);
            }, 1000);
        } else {
            // Stop live updates
            clearInterval(updateInterval);
        }
    });

    /***************************************************************/
    /* Script that generates random images for sound clips in chat */
    /***************************************************************/
    // Create a hidden canvas for rendering
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    // Waveform parameters
    const height = 35; // Fixed height like WhatsApp
    const barWidth = 2; // Width of each bar
    const barGap = 1; // Gap between bars
    const barColor = '#aaaaaa'; // Gray like WhatsApp
    const maxBarHeight = height * 0.4; // Max height (40% of canvas height)
    const minWidth = 100; // Minimum canvas width
    const maxWidth = 300; // Maximum canvas width

    // Function to generate random waveform
    function generateRandomWaveform(samples) {
        const frequency = 0.05 + Math.random() * 0.1; // Random frequency for wave variation
        const amplitude = maxBarHeight * 0.7; // Base amplitude
        const noiseLevel = 0.3 + Math.random() * 0.4; // Random noise level

        const waveform = [];
        for (let i = 0; i < samples; i++) {
            // Combine sine wave with noise for audio-like effect
            const sineValue = Math.sin(i * frequency) * amplitude +
                Math.sin(i * frequency * 2) * (amplitude / 3);
            const noise = (Math.random() - 0.5) * amplitude * noiseLevel;
            const value = Math.abs(sineValue + noise); // Absolute value for bar height
            waveform.push(Math.min(value, maxBarHeight)); // Cap at max height
        }
        return waveform;
    }

    // Function to draw WhatsApp-style waveform
    function drawSoundwave(waveform, width) {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = barColor;

        // Draw vertical bars
        for (let i = 0; i < waveform.length; i++) {
            const x = i * (barWidth + barGap);
            const barHeight = waveform[i];
            const y = height / 2 - barHeight / 2; // Center the bar vertically

            // Draw bar (rectangle)
            ctx.fillRect(x, y, barWidth, barHeight);
        }
    }

    // Function to render soundwave for a div
    function renderSoundwave(div) {
        // Generate random canvas width
        const width = Math.floor(minWidth + Math.random() * (maxWidth - minWidth + 1));
        canvas.width = width; // Set canvas width
        canvas.height = height;

        // Calculate number of bars based on width
        const samples = Math.floor(width / (barWidth + barGap));

        // Generate random waveform
        const waveform = generateRandomWaveform(samples);

        // Draw on canvas
        drawSoundwave(waveform, width);

        // Create image element
        const img = document.createElement('img');
        img.src = canvas.toDataURL('image/png');
        img.alt = 'WhatsApp-Style Soundwave';

        // Clear div and append image
        div.innerHTML = '';
        div.appendChild(img);
    }

    // Find all sound-image divs and render unique waveforms
    document.querySelectorAll('.sound-image').forEach(div => {
        renderSoundwave(div);
    });

    /***************************************************************/
    /* Script for the calendar                                     */
    /***************************************************************/

    const eventData = [
        { id: '1', title: 'Team Meeting', start: new Date(new Date().setHours(10, 0)), end: new Date(new Date().setHours(11, 30)), backgroundColor: 'var(--primary-500)', borderColor: 'var(--primary-600)', description: 'Weekly team status meeting', location: 'Conference Room A' },
        { id: '2', title: 'Client Call', start: new Date(new Date().setDate(new Date().getDate() + 1)), allDay: true, backgroundColor: 'var(--success-500)', borderColor: 'var(--success-600)', description: 'Quarterly review with major client', location: 'Zoom Meeting' },
        { id: '3', title: 'Product Launch', start: new Date(new Date().setDate(new Date().getDate() + 3)), end: new Date(new Date().setDate(new Date().getDate() + 3)).setHours(15, 0), backgroundColor: 'var(--danger-500)', borderColor: 'var(--danger-600)', description: 'New product launch event', location: 'Main Auditorium' },
        { id: '4', title: 'Deadline: Q3 Report', start: new Date(new Date().setDate(new Date().getDate() + 5)), allDay: true, backgroundColor: 'var(--warning-500)', borderColor: 'var(--warning-600)', description: 'Submit quarterly financial reports', location: 'Finance Department' },
        { id: '5', title: 'Training Session', start: new Date(new Date().setDate(new Date().getDate() - 2)), end: new Date(new Date().setDate(new Date().getDate() - 2)).setHours(16, 0), backgroundColor: 'var(--info-500)', borderColor: 'var(--info-600)', description: 'New software training for all employees', location: 'Training Room B' },
        { id: '6', title: 'Board Meeting', start: new Date(new Date().setDate(new Date().getDate() + 7)), allDay: true, backgroundColor: 'var(--danger-500)', borderColor: 'var(--danger-700)', description: 'Annual board meeting with stakeholders', location: 'Executive Boardroom' },
        { id: '7', title: 'Website Maintenance', start: new Date(new Date().setDate(new Date().getDate() - 1)).setHours(23, 0), end: new Date(new Date().setDate(new Date().getDate())).setHours(5, 0), backgroundColor: 'var(--info-500)', borderColor: 'var(--info-600)', description: 'Scheduled website maintenance window', location: 'IT Department' },
        { id: '8', title: 'Team Building', start: new Date(new Date().setDate(new Date().getDate() + 12)), end: new Date(new Date().setDate(new Date().getDate() + 12)).setHours(16, 0), backgroundColor: 'var(--primary-300)', borderColor: 'var(--primary-400)', description: 'Annual team building activities', location: 'City Park' },
        { id: '9', title: 'Client Dinner', start: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(19, 0), end: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(21, 0), backgroundColor: 'var(--success-300)', borderColor: 'var(--success-400)', description: 'Dinner with potential investors', location: 'Downtown Restaurant' },
        { id: '10', title: 'Vacation', start: new Date(new Date().setDate(new Date().getDate() + 14)), end: new Date(new Date().setDate(new Date().getDate() + 21)), backgroundColor: 'var(--bs-teal)', borderColor: 'var(--bs-teal)', description: 'Annual vacation time', location: 'Beach Resort' },
        { id: '11', title: 'Marketing Campaign', start: new Date(new Date().setDate(new Date().getDate() + 2)), end: new Date(new Date().setDate(new Date().getDate() + 2)).setHours(17, 0), backgroundColor: 'var(--bs-purple)', borderColor: 'var(--bs-purple)', description: 'Launch new product marketing campaign', location: 'Marketing Department', category: 'marketing' },
        { id: '12', title: 'Sales Meeting', start: new Date(new Date().setDate(new Date().getDate() + 3)).setHours(9, 0), end: new Date(new Date().setDate(new Date().getDate() + 3)).setHours(10, 30), backgroundColor: 'var(--success-600)', borderColor: 'var(--success-700)', description: 'Monthly sales team catchup', location: 'Conference Room B', category: 'sales' },
        { id: '13', title: 'Development Sprint Review', start: new Date(new Date().setDate(new Date().getDate() + 6)).setHours(14, 0), end: new Date(new Date().setDate(new Date().getDate() + 6)).setHours(16, 0), backgroundColor: 'var(--bs-indigo)', borderColor: 'var(--bs-indigo)', description: 'End of sprint review with stakeholders', location: 'Dev Team Area', category: 'development' },
        { id: '14', title: 'Tech Conference', start: new Date(new Date().setDate(new Date().getDate() + 8)), end: new Date(new Date().setDate(new Date().getDate() + 10)), backgroundColor: 'var(--bs-indigo)', borderColor: 'var(--bs-indigo)', description: 'Annual tech conference for industry professionals', location: 'Convention Center', category: 'conference' },
        { id: '15', title: 'Dentist Appointment', start: new Date(new Date().setDate(new Date().getDate() + 2)).setHours(14, 0), end: new Date(new Date().setDate(new Date().getDate() + 2)).setHours(15, 0), backgroundColor: 'var(--warning-500)', borderColor: 'var(--warning-600)', description: 'Routine dental checkup', location: 'Downtown Clinic', category: 'personal' },
        { id: '16', title: 'Project Milestone: Beta Release', start: new Date(new Date().setDate(new Date().getDate() + 15)), allDay: true, backgroundColor: 'var(--success-500)', borderColor: 'var(--success-600)', description: 'Beta release of the new app', location: 'Development Team', category: 'development' },
        { id: '17', title: 'Company Announcement', start: new Date(new Date().setDate(new Date().getDate() + 1)).setHours(11, 0), end: new Date(new Date().setDate(new Date().getDate() + 1)).setHours(11, 30), backgroundColor: 'var(--primary-500)', borderColor: 'var(--primary-600)', description: 'Announcement of new company policies', location: 'Main Hall', category: 'announcement' },
        { id: '18', title: 'Weekly Team Sync', start: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(9, 0), end: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(9, 30), backgroundColor: 'var(--primary-400)', borderColor: 'var(--primary-500)', description: 'Recurring weekly sync for project updates', location: 'Meeting Room C', category: 'team', extendedProps: { recurrence: 'weekly' } }
    ];

    // Initialize the calendar
    const calendarEl = document.getElementById('myCalendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        themeSystem: 'bootstrap',
        headerToolbar: {
            right: 'today prev,next',
            left: 'title'
        },
       
        footerToolbar: {
            left: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            day: 'Day',
            list: 'List'
        },
        dayMaxEvents: 2, // Show only 2 events per day, then show "more" link
        height: '760px', // Set minimum height for desktop
        eventSources: [
            { events: eventData }
        ]
    });
    calendar.render();

    /***************************************************************/
    /* Script to display current day, month, year                  */
    /***************************************************************/

    const currentDate = new Date();
    const day = currentDate.getDate();
    const month = currentDate.getMonth() + 1; // Months are 0-based
    const year = currentDate.getFullYear();

    //document.querySelector('.currentDate').innerHTML = `${day} ${month} ${year}`;

    /***************************************************************/
    /* Subscription Chart                                          */
    /***************************************************************/

    // Generate hourly data for subscriptions
    const generateHourlyData = () => {
        const today = [
            { x: '3am', y: 0 },
            { x: '4am', y: 0 },
            { x: '5am', y: 5000 },
            { x: '6am', y: 7500 },
            { x: '7am', y: 12000 },
            { x: '8am', y: 22500 },
            { x: '9am', y: 27000 },
            { x: '10am', y: 37500 },
            { x: '11am', y: 35000 },
            { x: '12m', y: 37500 },
            { x: '1pm', y: 9500 },
            { x: '2pm', y: 0 },
            { x: '3pm', y: 0 },
            { x: '4pm', y: 0 },
            { x: '5pm', y: 0 }
        ];

        const yesterday = [
            { x: '3am', y: 0 },
            { x: '4am', y: 0 },
            { x: '5am', y: 0 },
            { x: '6am', y: 5500 },
            { x: '7am', y: 15000 },
            { x: '8am', y: 20000 },
            { x: '9am', y: 35000 },
            { x: '10am', y: 30500 },
            { x: '11am', y: 35000 },
            { x: '12m', y: 27500 },
            { x: '1pm', y: 42500 },
            { x: '2pm', y: 32500 },
            { x: '3pm', y: 37500 },
            { x: '4pm', y: 27500 },
            { x: '5pm', y: 17500 }
        ];

        return {
            today: today,
            yesterday: yesterday
        };
    };

    const hourlyData = generateHourlyData();

    const subscriptionChartOptions = {
        series: [{
            name: 'Today',
            data: hourlyData.today
        }, {
            name: 'Yesterday',
            data: hourlyData.yesterday
        }],
        chart: {
            height: '100%',
            maxHeight: 275,
            width: '100%',
            type: 'bar',
            toolbar: {
                show: false
            },
            sparkline: {
                enabled: false
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '70%',
                endingShape: 'rounded',
                borderRadius: 0,
            }
        },
        colors: [window.colorMap.success[500].hex, window.colorMap.bootstrapVars.bodyColor.rgba(0.7)],
        dataLabels: {
            enabled: false
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
                    show: true
                }
            },
            yaxis: {
                lines: {
                    show: false
                }
            }
        },
        xaxis: {
            type: 'category',
            labels: {
                style: {
                    colors: '#8e8da4',
                    fontSize: '10px'
                },
                show: true
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
            max: 50000,
            tickAmount: 4,
            labels: {
                style: {
                    colors: '#8e8da4',
                    fontSize: '10px'
                },
                formatter: function (value) {
                    return '$' + Math.floor(value / 1000) + 'k';
                },
                show: true,
                offsetX: -15,
                align: 'left'
            }
        },
        tooltip: {
            enabled: true,
            followCursor: true,
            theme: 'dark',
            y: {
                formatter: function (value) {
                    return '$' + value.toLocaleString();
                }
            }
        },
        legend: {
            show: false
        }
    };

    // Initialize the subscriptions chart
    const subscriptionsChart = new ApexCharts(
        document.querySelector('#subscriptionsChart'),
        subscriptionChartOptions
    );
    subscriptionsChart.render();

    /***************************************************************/
    /* Secession Chart                                             */
    /***************************************************************/

    // Calculate total for percentages
    const populationData = [
        { label: "Current Usage", value: 4119630000, color: window.colorMap.primary[500].hex },
        { label: "Net Usage", value: 590950000, color: window.colorMap.info[500].hex },
        { label: "Users Blocked", value: 1012960000, color: window.colorMap.warning[500].hex },
        { label: "Custom Cases", value: 95100000, color: window.colorMap.danger[500].hex },
        { label: "Test Logs", value: 727080000, color: window.colorMap.success[500].hex },
        { label: "Uptime Records", value: 344120000, color: window.colorMap.bootstrapVars.bodyColor.rgba(0.7) }
    ];

    const total = populationData.reduce((sum, item) => sum + item.value, 0);

    const secessionChartOptions = {
        series: populationData.map(item => item.value),
        chart: {
            type: 'donut',
            height: '100%',
            maxHeight: 275,
            width: '100%'
        },
        colors: populationData.map(item => item.color),
        plotOptions: {
            pie: {
                donut: {
                    size: '50%'
                }
            }
        },
        labels: populationData.map(item => item.label),
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        tooltip: {
            enabled: false,
            y: {
                formatter: function (value) {
                    // Calculate percentage
                    const percentage = ((value / total) * 100).toFixed(1);
                    // Format the population number with commas
                    const population = value.toLocaleString();
                    return `${percentage}% (${population})`;
                }
            }
        },
        stroke: {
            width: 0
        }
    };

    // Initialize the secession chart
    const secessionChart = new ApexCharts(
        document.querySelector('#secessionChart'),
        secessionChartOptions
    );
    secessionChart.render();

    /***************************************************************/
    /* Global View                                                 */
    /***************************************************************/

    // GDP data per country (raw data with lowercase keys)
    const rawGdpData = {
        "af": 16.63, "al": 0, "dz": 158.97, "ao": 85.81, "ag": 1.1, "ar": 351.02, "am": 8.83, "au": 1219.72, "at": 366.26, "az": 52.17,
        "bs": 7.54, "bh": 21.73, "bd": 105.4, "bb": 3.96, "by": 52.89, "be": 461.33, "bz": 1.43, "bj": 6.49, "bt": 1.4, "bo": 19.18,
        "ba": 16.2, "bw": 12.5, "br": 2023.53, "bn": 11.96, "bg": 44.84, "bf": 8.67, "bi": 1.47, "kh": 11.36, "cm": 21.88, "ca": 1563.66,
        "cv": 1.57, "cf": 2.11, "td": 7.59, "cl": 199.18, "cn": 5745.13, "co": 283.11, "km": 0.56, "cd": 12.6, "cg": 11.88, "cr": 35.02,
        "ci": 22.38, "hr": 59.92, "cy": 22.75, "cz": 195.23, "dk": 304.56, "dj": 1.14, "dm": 0.38, "do": 50.87, "ec": 61.49, "eg": 216.83,
        "sv": 21.8, "gq": 14.55, "er": 2.25, "ee": 19.22, "et": 30.94, "fj": 3.15, "fi": 231.98, "fr": 2555.44, "ga": 12.56, "gm": 1.04,
        "ge": 11.23, "de": 3305.9, "gh": 18.06, "gr": 305.01, "gd": 0.65, "gt": 40.77, "gn": 4.34, "gw": 0.83, "gy": 2.2, "ht": 6.5,
        "hn": 15.34, "hk": 226.49, "hu": 132.28, "is": 0, "in": 1430.02, "id": 695.06, "ir": 337.9, "iq": 84.14, "ie": 204.14, "il": 201.25,
        "it": 2036.69, "jm": 13.74, "jp": 5390.9, "jo": 27.13, "kz": 129.76, "ke": 32.42, "ki": 0.15, "kw": 117.32, "kg": 4.44, "la": 6.34,
        "lv": 23.39, "lb": 39.15, "ls": 1.8, "lr": 0.98, "lt": 35.73, "lu": 52.43, "mk": 9.58, "mg": 8.33, "mw": 5.04, "my": 218.95,
        "mv": 1.43, "ml": 9.08, "mt": 7.8, "mr": 3.49, "mu": 9.43, "mx": 1004.04, "md": 5.36, "rw": 5.69, "ws": 0.55, "st": 0.19,
        "sa": 434.44, "sn": 12.66, "rs": 38.92, "sc": 0.92, "sl": 1.9, "sg": 217.38, "sk": 86.26, "si": 46.44, "sb": 0.67, "za": 354.41,
        "es": 1374.78, "lk": 48.24, "kn": 0.56, "lc": 1, "vc": 0.58, "sd": 65.93, "sr": 3.3, "sz": 3.17, "se": 444.59, "ch": 522.44,
        "sy": 59.63, "tw": 426.98, "tj": 5.58, "tz": 22.43, "th": 312.61, "tl": 0.62, "tg": 3.07, "to": 0.3, "tt": 21.2, "tn": 43.86,
        "tr": 729.05, "tm": 0, "ug": 17.12, "ua": 136.56, "ae": 239.65, "gb": 2258.57, "us": 4624.18, "uy": 40.71, "uz": 37.72, "vu": 0.72,
        "ve": 285.21, "vn": 101.99, "ye": 30.02, "zm": 15.69, "zw": 0
    };

    // Convert to uppercase keys for jsVectorMap
    const gdpData = {};
    for (const countryCode in rawGdpData) {
        gdpData[countryCode.toUpperCase()] = rawGdpData[countryCode];
    }

    console.log("US GDP value:", gdpData["US"]); // Debug log

    // Initialize the map
    const map = new jsVectorMap({
        map: "world_merc",
        selector: "#globalView",
        zoomButtons: true,
        zoomOnScroll: true,
        regionsSelectable: true,
        regionsSelectableOne: true, // Allow only one region to be selected at a time
        markersSelectable: true,
        backgroundColor: "transparent",
        responsive: true, // Enable responsive behavior
        regionStyle: {
            initial: {
                fill: '#9c99a130', // Default color
                stroke: window.colorMap.bootstrapVars.bodyColor.rgba(0.7), // Border color
                strokeWidth: 0.5, // Border width
                strokeOpacity: 0.8 // Border opacity
            },
            hover: {
                fill: window.colorMap.success[400].hex,
                stroke: window.colorMap.bootstrapVars.bodyColor.rgba(0.8), // Darker border on hover
                strokeWidth: 1, // Slightly thicker border on hover
                cursor: 'pointer'
            },
            selected: {
                fill: window.colorMap.success[300].hex,
                stroke: window.colorMap.bootstrapVars.bodyColor.hex, // Darkest border when selected
                strokeWidth: 1.5 // Thickest border when selected
            }
        },
        visualizeData: {
            scale: [window.colorMap.primary[100].hex, window.colorMap.primary[700].hex], // Light blue to dark blue scale
            values: gdpData
        },
        onRegionTooltipShow: function (event, tooltip, code) {
            const value = gdpData[code] || 0;
            let countryName = code;
            if (window.jsVectorMap && window.jsVectorMap.maps && window.jsVectorMap.maps.world_merc && window.jsVectorMap.maps.world_merc.regions && window.jsVectorMap.maps.world_merc.regions[code]) {
                countryName = window.jsVectorMap.maps.world_merc.regions[code].name;
            }

            tooltip.text(
                `${countryName} - $${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
            );
        },
        onRegionClick: function (event, code) {
            const value = gdpData[code] || 0;
            updateCountryInfoPanel(code, value);
        }
    });

    // Store map in window for global access
    window.map = map;

    // Helper to update info panel
    function updateCountryInfoPanel(code, value) {
        // Get country name from map data if available
        let countryName = code;
        if (window.jsVectorMap && window.jsVectorMap.maps && window.jsVectorMap.maps.world_merc && window.jsVectorMap.maps.world_merc.regions && window.jsVectorMap.maps.world_merc.regions[code]) {
            countryName = window.jsVectorMap.maps.world_merc.regions[code].name;
        }

        // Update flag
        const flagImg = document.querySelector('.js-jqvmap-flag');
        if (flagImg) {
            flagImg.src = `https://lipis.github.io/flag-icons/flags/4x3/${code.toLowerCase()}.svg`;
        }

        // Update country info text
        const infoText = document.querySelector('.js-jqvmap-country');
        if (infoText) {
            infoText.textContent = `${countryName} - $${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
    }

    // Set initial info panel to US with the correct value
    const usCode = "US";
    updateCountryInfoPanel(usCode, gdpData[usCode]);

    // Force map update to apply visualization and handle initial sizing
    setTimeout(() => {
        map.updateSize();
    }, 100);

    // Add window resize handler for responsiveness
    window.addEventListener('resize', function () {
        map.updateSize();
    });

    //update map colors
    const updateMapColors = function () {
        console.log('Rebuilding map with new theme colors');

        // Try to find the map instance
        const originalMap = window.map;

        // Get map element
        const mapElement = document.querySelector('#globalView');
        if (!mapElement) {
            console.warn('Map element #globalView not found');
            return;
        }

        try {
            // Save important state before rebuilding
            let currentSelectedRegion = null;
            let currentZoom = 1;
            let currentOffset = { x: 0, y: 0 };

            // Try to extract current state from original map
            if (originalMap) {
                // Save selected region
                if (originalMap.regions) {
                    Object.entries(originalMap.regions).forEach(([code, region]) => {
                        if (region && region.element && region.element.isSelected) {
                            currentSelectedRegion = code;
                        }
                    });
                }

                // Save zoom level and position if available
                if (originalMap.baseScale) {
                    currentZoom = originalMap.scale / originalMap.baseScale;
                }

                if (originalMap.transX !== undefined && originalMap.transY !== undefined) {
                    currentOffset.x = originalMap.transX;
                    currentOffset.y = originalMap.transY;
                }

                // Destroy original map
                // First clear all event listeners to prevent memory leaks
                mapElement.innerHTML = '';
            }

            // Create fresh map with new colors
            window.map = new jsVectorMap({
                map: "world_merc",
                selector: "#globalView",
                zoomButtons: true,
                zoomOnScroll: true,
                regionsSelectable: true,
                regionsSelectableOne: true,
                markersSelectable: true,
                backgroundColor: "transparent",
                responsive: true,
                regionStyle: {
                    initial: {
                        fill: '#9c99a130', // Default color
                        stroke: window.colorMap.bootstrapVars.bodyColor.rgba(0.7), // Border color
                        strokeWidth: 0.5,
                        strokeOpacity: 0.8
                    },
                    hover: {
                        fill: window.colorMap.success[400].hex, // Updated from current theme
                        stroke: window.colorMap.bootstrapVars.bodyColor.rgba(0.8),
                        strokeWidth: 1,
                        cursor: 'pointer'
                    },
                    selected: {
                        fill: window.colorMap.success[300].hex, // Updated from current theme
                        stroke: window.colorMap.bootstrapVars.bodyColor.hex,
                        strokeWidth: 1.5
                    }
                },
                visualizeData: {
                    scale: [window.colorMap.primary[100].hex, window.colorMap.primary[700].hex], // Updated scale colors
                    values: gdpData
                },
                onRegionTooltipShow: function (event, tooltip, code) {
                    const value = gdpData[code] || 0;
                    let countryName = code;
                    if (window.jsVectorMap?.maps?.world_merc?.regions?.[code]) {
                        countryName = window.jsVectorMap.maps.world_merc.regions[code].name;
                    }

                    tooltip.text(
                        `${countryName} - $${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
                    );
                },
                onRegionClick: function (event, code) {
                    const value = gdpData[code] || 0;
                    updateCountryInfoPanel(code, value);
                }
            });

            // Restore previous state if available
            setTimeout(() => {
                const newMap = window.map;

                // Restore selected region
                if (currentSelectedRegion && newMap.setSelected) {
                    newMap.setSelected('regions', currentSelectedRegion);
                }

                // Restore zoom and position if possible
                if (currentZoom !== 1 && newMap.setScale) {
                    newMap.setScale(newMap.baseScale * currentZoom, currentOffset.x, currentOffset.y);
                }

                console.log('Map rebuilt successfully with new theme colors');
            }, 100);

        } catch (e) {
            console.error('Error rebuilding map:', e);
        }
    };

    // Add map color updates
    window.updateMiscPluginColors = function () {
        updateMapColors();
    };


    /***************************************************************/
    /* Script for PeityCharts                                      */
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
    /* Script for EasyPieCharts                                    */
    /***************************************************************/
    // Create and initialize easy pie charts
    // function initEasyPieCharts() {
    //     document.querySelectorAll('.js-easy-pie-chart').forEach(function(element) {
    //         // Skip if chart is already initialized
    //         if (element._easyPieChart) return;

    //         // Get element properties using vanilla JS
    //         const computedStyle = window.getComputedStyle(element);
    //         const barcolor = element.dataset.barcolor || computedStyle.color || 'var(--primary-700)';

    //         // Check if window.colorMap exists, if not use a fallback
    //         let trackcolor;
    //         try {
    //             if (window.colorMap && window.colorMap.bootstrapVars && window.colorMap.bootstrapVars.bodyColorRgb) {
    //                 trackcolor = window.colorMap.bootstrapVars.bodyColorRgb.rgba(0.07);
    //             } else {
    //                 trackcolor = 'rgba(0,0,0,0.04)';
    //             }
    //         } catch (e) {
    //             trackcolor = 'rgba(0,0,0,0.04)';
    //         }

    //         // Read dataset attributes with fallbacks
    //         const size = parseInt(element.dataset.piesize) || 50;
    //         const scalecolor = element.dataset.scalecolor || computedStyle.color || 'var(--primary-700)';
    //         const scalelength = parseInt(element.dataset.scalelength) || 0;
    //         const linewidth = parseInt(element.dataset.linewidth) || parseInt(size / 8.5);
    //         const linecap = element.dataset.linecap || 'butt'; // butt, round and square.

    //         // Create EasyPieChart instance
    //         const chart = new EasyPieChart(element, {
    //             size: size,
    //             barColor: barcolor,
    //             trackColor: trackcolor,
    //             scaleColor: scalecolor,
    //             scaleLength: scalelength,
    //             lineCap: linecap,
    //             lineWidth: linewidth,
    //             animate: {
    //                 duration: 1500,
    //                 enabled: true
    //             },
    //             easing: 'easeOutQuad', // Use our built-in easing function
    //             onStep: function(from, to, percent) {
    //                 // Find the percentage element and update its text
    //                 const percentElement = element.querySelector('.js-percent');
    //                 if (percentElement) {
    //                     percentElement.textContent = Math.round(percent);
    //                 }
    //             }
    //         });

    //         // Store the original percent value for rebuilds
    //         if (element.dataset.percent) {
    //             element._originalPercent = parseFloat(element.dataset.percent);
    //         } else if (element.getAttribute('data-percent')) {
    //             element._originalPercent = parseFloat(element.getAttribute('data-percent'));
    //         }
    //     });
    // }

    // // Destroy all existing charts
    // function destroyEasyPieCharts() {
    //     document.querySelectorAll('.js-easy-pie-chart').forEach(function(element) {
    //         if (element._easyPieChart) {
    //             element._easyPieChart.destroy();
    //         }
    //     });
    // }

    // // Rebuild all charts with current theme colors
    // window.rebuildEasyPieCharts = function() {
    //     destroyEasyPieCharts();
    //     initEasyPieCharts();
    // };

    // // Initialize easy pie charts
    // initEasyPieCharts();
});