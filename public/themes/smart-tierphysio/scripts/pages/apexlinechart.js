import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Line Chart
    if (document.getElementById('basic-line-chart')) {
        const basicLineOptions = {
            series: [{
                name: 'Sales',
                data: [30, 40, 35, 50, 49, 60, 70, 91, 125]
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: true
                },
                zoom: {
                    enabled: true
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'straight',
                width: 3
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                row: {
                    colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05), 'transparent'],
                    opacity: 0.5
                },
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark'
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const basicLineChart = new ApexCharts(
            document.getElementById('basic-line-chart'),
            basicLineOptions
        );
        basicLineChart.render();
    }

    // Line Chart with Data Labels
    if (document.getElementById('line-with-data-labels-chart')) {
        const lineWithDataLabelsOptions = {
            series: [{
                name: 'Website Traffic',
                data: [45, 52, 38, 24, 33, 56, 85, 112, 97, 84, 72, 89]
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: true
                },
                dropShadow: {
                    enabled: true,
                    color: window.colorMap.primary[500].hex,
                    top: 18,
                    left: 7,
                    blur: 10,
                    opacity: 0.2
                }
            },
            dataLabels: {
                enabled: true
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                row: {
                    colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05), 'transparent'],
                    opacity: 0.5
                },
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            markers: {
                size: 6
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                title: {
                    text: 'Month',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Visitors (thousands)',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return val + "k visitors";
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                floating: true,
                offsetY: -25,
                offsetX: -5
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const lineWithDataLabelsChart = new ApexCharts(
            document.getElementById('line-with-data-labels-chart'),
            lineWithDataLabelsOptions
        );
        lineWithDataLabelsChart.render();
    }

    // Multi-line Chart
    if (document.getElementById('multi-line-chart')) {
        const multiLineOptions = {
            series: [{
                name: 'Desktop',
                data: [45, 52, 38, 24, 33, 26, 21, 20, 6, 8, 15, 10]
            }, {
                name: 'Mobile',
                data: [35, 41, 62, 42, 13, 18, 29, 37, 36, 51, 32, 35]
            }, {
                name: 'Tablet',
                data: [87, 57, 74, 99, 75, 38, 62, 47, 82, 56, 45, 47]
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: true
                },
                zoom: {
                    enabled: true
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                width: [3, 3, 3],
                curve: 'straight',
                dashArray: [0, 0, 0]
            },
            title: {
                text: 'Device Traffic Comparison',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            legend: {
                tooltipHoverFormatter: function(val, opts) {
                    return val + ' - ' + opts.w.globals.series[opts.seriesIndex][opts.dataPointIndex] + ' users';
                },
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                position: 'top'
            },
            markers: {
                size: 0,
                hover: {
                    sizeOffset: 6
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return val + "k users";
                    }
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.danger[500].hex]
        };

        const multiLineChart = new ApexCharts(
            document.getElementById('multi-line-chart'),
            multiLineOptions
        );
        multiLineChart.render();
    }

    // Steeped Line Chart
    if (document.getElementById('stepped-line-chart')) {
        const steppedLineOptions = {
            series: [{
                name: 'Orders',
                data: [34, 44, 54, 21, 12, 43, 33, 23, 66, 66, 58, 79]
            }],
            chart: {
                type: 'line',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            stroke: {
                curve: 'stepline',
                width: 3
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'Daily Order Count',
                align: 'left'
            },
            markers: {
                hover: {
                    sizeOffset: 4
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            },
            tooltip: {
                theme: 'dark'
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const steppedLineChart = new ApexCharts(
            document.getElementById('stepped-line-chart'),
            steppedLineOptions
        );
        steppedLineChart.render();
    }

    // Gradient Line Chart
    if (document.getElementById('gradient-line-chart')) {
        const gradientLineOptions = {
            series: [{
                name: 'Revenue',
                data: [31, 40, 28, 51, 42, 109, 100, 120, 80, 95, 110, 140]
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: true
                }
            },
            stroke: {
                width: 5,
                curve: 'smooth'
            },
            title: {
                text: 'Monthly Revenue',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    gradientToColors: [window.colorMap.danger[500].hex],
                    shadeIntensity: 1,
                    type: 'horizontal',
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 100]
                },
            },
            markers: {
                size: 4,
                colors: [window.colorMap.success[500].hex],
                strokeColors: window.colorMap.bootstrapVars.bodyBg.hex,
                strokeWidth: 2,
                hover: {
                    size: 7,
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Revenue in $1000',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                min: 0,
                max: 150,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return "$" + val + "k";
                    }
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            colors: [window.colorMap.success[500].hex]
        };

        const gradientLineChart = new ApexCharts(
            document.getElementById('gradient-line-chart'),
            gradientLineOptions
        );
        gradientLineChart.render();
    }

    // Line Chart with Annotations
    if (document.getElementById('line-with-annotations-chart')) {
        const lineWithAnnotationsOptions = {
            series: [{
                name: 'Stock Price',
                data: [8107, 8128, 8122, 8165, 8340, 8423, 8423, 8514, 8481, 8487, 8506, 8626, 8668, 8602, 8607, 8512, 8496, 8600, 8881, 9340]
            }],
            chart: {
                height: 350,
                type: 'line',
                id: 'stock-prices',
                toolbar: {
                    show: true
                }
            },
            annotations: {
                yaxis: [{
                    y: 8600,
                    borderColor: window.colorMap.primary[500].hex,
                    label: {
                        borderColor: window.colorMap.primary[500].hex,
                        style: {
                            color: window.colorMap.bootstrapVars.bodyBg.hex,
                            background: window.colorMap.primary[500].hex
                        },
                        text: 'Support Level',
                    }
                }],
                xaxis: [{
                    x: new Date('15 Nov 2021').getTime(),
                    borderColor: window.colorMap.danger[500].hex,
                    yAxisIndex: 0,
                    label: {
                        borderColor: window.colorMap.danger[500].hex,
                        style: {
                            color: window.colorMap.bootstrapVars.bodyBg.hex,
                            background: window.colorMap.danger[500].hex,
                        },
                        text: 'Market Dip',
                    },
                }],
                points: [{
                    x: new Date('01 Dec 2021').getTime(),
                    y: 9340,
                    marker: {
                        size: 8,
                        fillColor: window.colorMap.primary[500].hex,
                        strokeColor: window.colorMap.bootstrapVars.bodyBg.hex,
                        radius: 2
                    },
                    label: {
                        borderColor: window.colorMap.primary[500].hex,
                        offsetY: 0,
                        style: {
                            color: window.colorMap.bootstrapVars.bodyBg.hex,
                            background: window.colorMap.primary[500].hex,
                        },
                        text: 'All-time High',
                    }
                }]
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'straight',
                width: 3
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                padding: {
                    right: 30,
                    left: 20
                },
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            title: {
                text: 'Stock Market Analysis',
                align: 'left'
            },
            xaxis: {
                type: 'datetime',
                categories: [
                    '01 Nov 2021', '02 Nov 2021', '03 Nov 2021', '04 Nov 2021', '05 Nov 2021', 
                    '08 Nov 2021', '09 Nov 2021', '10 Nov 2021', '11 Nov 2021', '12 Nov 2021', 
                    '15 Nov 2021', '16 Nov 2021', '17 Nov 2021', '18 Nov 2021', '19 Nov 2021', 
                    '22 Nov 2021', '23 Nov 2021', '24 Nov 2021', '29 Nov 2021', '01 Dec 2021'
                ],
            },
            tooltip: {
                theme: 'dark',
                shared: false,
                x: {
                    format: 'dd MMM yyyy'
                },
                y: {
                    formatter: function(val) {
                        return "$" + val;
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const lineWithAnnotationsChart = new ApexCharts(
            document.getElementById('line-with-annotations-chart'),
            lineWithAnnotationsOptions
        );
        lineWithAnnotationsChart.render();
    }

    // Dashed Line Chart
    if (document.getElementById('dashed-line-chart')) {
        const dashedLineOptions = {
            series: [{
                name: "Session Duration",
                data: [45, 52, 38, 24, 33, 26, 21, 20, 6, 8, 15, 10]
            }, {
                name: "Page Views",
                data: [35, 41, 62, 42, 13, 18, 29, 37, 36, 51, 32, 35]
            }, {
                name: 'Total Visits',
                data: [87, 57, 74, 99, 75, 38, 62, 47, 82, 56, 45, 47]
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                width: [3, 3, 3],
                curve: 'straight',
                dashArray: [0, 8, 5]
            },
            title: {
                text: 'Page Statistics',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            legend: {
                tooltipHoverFormatter: function(val, opts) {
                    return val + ' - ' + opts.w.globals.series[opts.seriesIndex][opts.dataPointIndex] + '';
                },
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            markers: {
                size: 0,
                hover: {
                    sizeOffset: 6
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                y: [{
                    title: {
                        formatter: function (val) {
                            return val + " (mins)";
                        }
                    }
                }, {
                    title: {
                        formatter: function (val) {
                            return val;
                        }
                    }
                }, {
                    title: {
                        formatter: function (val) {
                            return val;
                        }
                    }
                }]
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.danger[500].hex]
        };

        const dashedLineChart = new ApexCharts(
            document.getElementById('dashed-line-chart'),
            dashedLineOptions
        );
        dashedLineChart.render();
    }

    // Zoomable Timeseries Chart
    if (document.getElementById('zoomable-timeseries-chart')) {
        const generateDayWiseTimeSeries = function(baseval, count, yrange) {
            let i = 0;
            let series = [];
            while (i < count) {
                const y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
                series.push({
                    x: baseval,
                    y: y
                });
                baseval += 86400000;
                i++;
            }
            return series;
        };

        const zoomableTimeseriesOptions = {
            series: [{
                name: 'XYZ MOTORS',
                data: generateDayWiseTimeSeries(new Date('11 Feb 2020 GMT').getTime(), 200, {
                    min: 30,
                    max: 90
                })
            }],
            chart: {
                type: 'area',
                stacked: false,
                height: 350,
                zoom: {
                    type: 'x',
                    enabled: true,
                    autoScaleYaxis: true
                },
                toolbar: {
                    autoSelected: 'zoom',
                    show: true
                }
            },
            stroke: {
                //curve: 'straight',
                width: 2
            },
            dataLabels: {
                enabled: false
            },
            markers: {
                size: 0,
            },
            title: {
                text: 'Stock Price Movement',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    inverseColors: false,
                    opacityFrom: 0.5,
                    opacityTo: 0,
                    stops: [0, 90, 100]
                },
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return (val).toFixed(2);
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                title: {
                    text: 'Price',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                shared: false,
                y: {
                    formatter: function (val) {
                        return "$" + (val).toFixed(2);
                    }
                },
                x: {
                    format: 'dd MMM yyyy'
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const zoomableTimeseriesChart = new ApexCharts(
            document.getElementById('zoomable-timeseries-chart'),
            zoomableTimeseriesOptions
        );
        zoomableTimeseriesChart.render();
    }

    // Annotations Line Chart (replacing the duplicate)
    if (document.getElementById('annotations-line-chart')) {
        const annotationsLineOptions = {
            series: [{
                name: 'Website Traffic',
                data: [31, 40, 28, 51, 42, 109, 100, 120, 95, 98, 86, 75]
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: true
                }
            },
            annotations: {
                yaxis: [{
                    y: 100,
                    borderColor: window.colorMap.success[500].hex,
                    label: {
                        borderColor: window.colorMap.success[500].hex,
                        style: {
                            color: '#fff',
                            background: window.colorMap.success[500].hex,
                        },
                        text: 'Traffic Goal',
                    }
                }],
                xaxis: [{
                    x: new Date('07 Jul 2023').getTime(),
                    borderColor: window.colorMap.danger[500].hex,
                    yAxisIndex: 0,
                    label: {
                        style: {
                            color: '#fff',
                            background: window.colorMap.danger[500].hex,
                        },
                        text: 'Summer Event',
                    },
                }]
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                padding: {
                    right: 30,
                    left: 20
                },
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            title: {
                text: 'Monthly Website Traffic',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            xaxis: {
                type: 'category',
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark'
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const annotationsLineChart = new ApexCharts(
            document.getElementById('annotations-line-chart'),
            annotationsLineOptions
        );
        annotationsLineChart.render();
    }

    // Synchronized Charts
    if (document.getElementById('synchronized-line-chart')) {
        function generateSyncData(count, min, max) {
            let i = 0;
            const series = [];
            const baseDate = new Date('31 Jan 2023').getTime();
            
            while (i < count) {
                const x = baseDate + i * (24 * 60 * 60 * 1000);
                const y = Math.floor(Math.random() * (max - min + 1)) + min;
                series.push({ x, y });
                i++;
            }
            return series;
        }

        // Generate data once and reuse
        const syncData = generateSyncData(30, 50, 80);

        const syncChartOptions = {
            series: [{
                data: syncData  // Use the same data
            }],
            chart: {
                id: 'sync-chart',
                type: 'line',
                height: 250,
                toolbar: {
                    autoSelected: 'pan',
                    show: true
                },
                animations: {
                    enabled: true,
                    easing: 'linear',
                    dynamicAnimation: {
                        speed: 1000
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'straight',
                width: 3
            },
            title: {
                text: 'Synchronized Chart (use Pan & Zoom)',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                row: {
                    colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05), 'transparent'],
                    opacity: 0.5
                },
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                min: 45,  // Set minimum value with padding
                max: 85,  // Set maximum value with padding
                tickAmount: 5,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark'
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const syncChartSmallOptions = {
            series: [{
                data: syncData  // Use the same data
            }],
            chart: {
                id: 'sync-chart-small',
                height: 100,
                type: 'area',
                brush: {
                    target: 'sync-chart',
                    enabled: true
                },
                selection: {
                    enabled: true,
                    xaxis: {
                        min: new Date('31 Jan 2023').getTime(),
                        max: new Date('15 Feb 2023').getTime() // Show 15 days initially
                    },
                    fill: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex,
                        opacity: 0.1
                    },
                    stroke: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex,
                        width: 2,
                    }
                },
                toolbar: {
                    show: false
                }
            },
            stroke: {
                curve: 'straight',
                width: 1
            },
            colors: [window.colorMap.primary[300].hex],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.4,
                    gradientToColors: [window.colorMap.bootstrapVars.bodyBg.hex, window.colorMap.bootstrapVars.bodyBg.hex],
                    inverseColors: false,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 100]
                }
            },
            xaxis: {
                type: 'datetime',
                tooltip: {
                    enabled: false
                },
                labels: {
                    show: false,
                }
            },
            yaxis: {
                show: false,
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            dataLabels: {
                enabled: false
            }
        };

        const syncChart = new ApexCharts(
            document.getElementById('synchronized-line-chart'),
            syncChartOptions
        );
        syncChart.render();

        const syncChartSmall = new ApexCharts(
            document.getElementById('synchronized-line-chart-small'),
            syncChartSmallOptions
        );
        syncChartSmall.render();
    }

    // Brush Chart
    if (document.getElementById('brush-line-chart')) {
        const brushChartOptions = {
            series: [{
                name: "series1",
                data: [45, 52, 38, 45, 19, 23, 2, 48, 38, 41, 45, 54, 28, 35, 36, 39, 46, 28, 38, 54, 36, 42, 45, 54, 29, 32, 25]
            }],
            chart: {
                id: 'chart2',
                type: 'line',
                height: 230,
                toolbar: {
                    autoSelected: 'pan',
                    show: true
                }
            },
            colors: [window.colorMap.primary[500].hex],
            stroke: {
                width: 3,
                curve: 'smooth'
            },
            dataLabels: {
                enabled: false
            },
            fill: {
                opacity: 1,
            },
            markers: {
                size: 0
            },
            xaxis: {
                type: 'datetime',
                categories: generateDateCategories(27),
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark'
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            }
        };

        const brushChartSmallOptions = {
            series: [{
                name: "series1",
                data: [45, 52, 38, 45, 19, 23, 2, 48, 38, 41, 45, 54, 28, 35, 36, 39, 46, 28, 38, 54, 36, 42, 45, 54, 29, 32, 25]
            }],
            
            chart: {
                id: 'chart1',
                height: 130,
                type: 'area',
                brush: {
                    target: 'chart2',
                    enabled: true
                },
                selection: {
                    enabled: true,
                    fill: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex,
                        opacity: 0.1
                    },
                    stroke: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex,
                        width: 2,
                    }
                }
            },
            colors: [window.colorMap.primary[300].hex],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.4,
                    gradientToColors: [window.colorMap.bootstrapVars.bodyBg.hex, window.colorMap.bootstrapVars.bodyBg.hex],
                    inverseColors: false,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 100]
                }
            },
            xaxis: {
                type: 'datetime',
                categories: generateDateCategories(27),
                tooltip: {
                    enabled: false
                },
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                tickAmount: 2,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            dataLabels: {
                enabled: false
            }
        };



        const brushChart = new ApexCharts(
            document.getElementById('brush-line-chart'),
            brushChartOptions
        );
        brushChart.render();

        const brushChartSmall = new ApexCharts(
            document.getElementById('brush-line-chart-small'),
            brushChartSmallOptions
        );
        brushChartSmall.render();

        function generateDateCategories(count) {
            const categories = [];
            let startDate = new Date('01 Jan 2023');
            
            for (let i = 0; i < count; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                categories.push(date.toISOString());
            }
            
            return categories;
        }
    }

    // Negative Values Line Chart
    if (document.getElementById('negative-values-line-chart')) {
        const negativeValuesOptions = {
            series: [{
                name: 'Cash Flow',
                data: [1.45, 5.42, 5.9, -0.42, -12.6, -18.1, -18.2, -14.16, -11.1, -6.09, 0.34, 3.88, 13.07,
                    5.8, 2, 7.37, 8.1, 13.57, 15.75, 17.1, 19.8, -27.03, -54.4, -47.2, -43.3, -18.6, -
                    48.6, -41.1, -39.6, -37.6, -29.4, -21.4, -2.4
                ]
            }],
            chart: {
                type: 'line',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'Monthly Cash Flow',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            markers: {
                size: 0,
                hover: {
                    sizeOffset: 6
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
                    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
                    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'
                ],
                labels: {
                    rotate: -90,
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Cash Flow (thousands)',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                min: -60,
                max: 30,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                shared: false,
                y: {
                    formatter: function (val) {
                        return "$" + val + "k";
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex],
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    gradientToColors: [window.colorMap.danger[500].hex],
                    shadeIntensity: 1,
                    type: 'horizontal',
                    opacityFrom: 1,
                    opacityTo: 1,
                    colorStops: [
                        {
                            offset: 0,
                            color: window.colorMap.primary[500].hex,
                            opacity: 1
                        },
                        {
                            offset: 50,
                            color: window.colorMap.primary[500].hex,
                            opacity: 1
                        },
                        {
                            offset: 50,
                            color: window.colorMap.danger[500].hex,
                            opacity: 1
                        },
                        {
                            offset: 100,
                            color: window.colorMap.danger[500].hex,
                            opacity: 1
                        }
                    ]
                }
            }
        };

        const negativeValuesChart = new ApexCharts(
            document.getElementById('negative-values-line-chart'),
            negativeValuesOptions
        );
        negativeValuesChart.render();
    }

    // Missing/Null Values Chart
    if (document.getElementById('null-values-line-chart')) {
        const nullValuesOptions = {
            series: [{
                name: 'Network Usage',
                data: [31, 40, 28, 51, null, 42, 109, null, null, 70, 25, 35, 58, 62, 85, 95, 112, 118, 95, 97, 92, null, 90, 88, 82, 75, 68, 65]
            }],
            chart: {
                height: 350,
                type: 'line',
                animations: {
                    enabled: true,
                    easing: 'linear',
                    dynamicAnimation: {
                        speed: 1000
                    }
                },
                dropShadow: {
                    enabled: true,
                    top: 3,
                    left: 3,
                    blur: 1,
                    opacity: 0.2
                },
                toolbar: {
                    show: true
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'Network Usage with Missing Data',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                row: {
                    colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05), 'transparent'],
                    opacity: 0.5
                },
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            xaxis: {
                categories: ["Jan 01", "Jan 02", "Jan 03", "Jan 04", "Jan 05", "Jan 06", "Jan 07", "Jan 08", "Jan 09", "Jan 10", "Jan 11", "Jan 12", "Jan 13", "Jan 14", "Jan 15", "Jan 16", "Jan 17", "Jan 18", "Jan 19", "Jan 20", "Jan 21", "Jan 22", "Jan 23", "Jan 24", "Jan 25", "Jan 26", "Jan 27", "Jan 28"],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                x: {
                    format: 'dd/MM/yy'
                }
            },
            markers: {
                size: 5,
                hover: {
                    size: 9
                }
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const nullValuesChart = new ApexCharts(
            document.getElementById('null-values-line-chart'),
            nullValuesOptions
        );
        nullValuesChart.render();
    }

    // Realtime Line Chart
    if (document.getElementById('realtime-line-chart')) {
        const realtimeOptions = {
            series: [{
                name: 'Data',
                data: generateRealtimeData(100)
            }],
            chart: {
                id: 'realtime',
                height: 350,
                type: 'line',
                animations: {
                    enabled: false,
                    easing: 'linear',
                    dynamicAnimation: {
                        speed: 500
                    }
                },
                toolbar: {
                    show: true
                },
                zoom: {
                    enabled: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            title: {
                text: 'Dynamic Updating Chart',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            markers: {
                size: 0
            },
            xaxis: {
                type: 'datetime',
                tickAmount: 10,
                labels: {
                    datetimeUTC: false,
                    format: 'HH:mm:ss',
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                min: 20,
                max: 100,
                labels: {
                    formatter: function(val) {
                        return val.toFixed(0);
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            legend: {
                show: true,
                legend: {
                    labels: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
            },
            tooltip: {
                theme: 'dark',
                x: {
                    format: 'HH:mm:ss'
                }
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const realtimeChart = new ApexCharts(
            document.getElementById('realtime-line-chart'),
            realtimeOptions
        );
        realtimeChart.render();

        // Create initial data that's evenly distributed across the chart
        function generateRealtimeData(count) {
            const data = [];
            const now = new Date();
            const yrange = { min: 30, max: 90 };
            
            // Distribute points evenly from left to right
            for (let i = 0; i < count; i++) {
                // Calculate timestamp to spread points evenly across the chart
                // This creates a series of points going from left (past) to right (now)
                const timeOffset = (count - i) * 1000; // 1 second intervals
                const x = now.getTime() - timeOffset;
                
                // Create some natural-looking data
                const y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
                data.push({ x, y });
            }
            return data;
        }

        // Update data every 1000ms
        let lastDate = new Date();
        let data = realtimeChart.w.config.series[0].data;
        let TICKINTERVAL = 1000;

        const updateRealtimeChart = function() {
            let newDate = new Date(lastDate.getTime() + TICKINTERVAL);
            lastDate = newDate;
            
            // Shift all data points left by one position
            for (let i = 0; i < data.length - 1; i++) {
                data[i].x = data[i + 1].x;
                data[i].y = data[i + 1].y;
            }
            
            // Add new point at the right edge
            data[data.length - 1].x = newDate.getTime();
            data[data.length - 1].y = Math.floor(Math.random() * (100 - 30 + 1)) + 30;
            
            // Update the chart
            realtimeChart.updateSeries([{
                name: 'Data',
                data: data
            }]);
        };

        // Start update interval
        const realtimeInterval = setInterval(updateRealtimeChart, TICKINTERVAL);

        // Clean up interval when navigating away
        window.addEventListener('beforeunload', function() {
            clearInterval(realtimeInterval);
        });
    }

    // Stepped Line Chart (fix the ID to match the HTML)
    if (document.getElementById('stepline-chart')) {
        const steplineOptions = {
            series: [{
                name: 'Orders',
                data: [34, 44, 54, 21, 12, 43, 33, 23, 66, 66, 58, 79]
            }],
            chart: {
                type: 'line',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            stroke: {
                curve: 'stepline',
                width: 3
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'Daily Order Count',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            markers: {
                hover: {
                    sizeOffset: 4
                }
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                }
            },
            tooltip: {
                theme: 'dark'
            },
            colors: [window.colorMap.primary[500].hex]
        };

        const steplineChart = new ApexCharts(
            document.getElementById('stepline-chart'),
            steplineOptions
        );
        steplineChart.render();
    }
}); 