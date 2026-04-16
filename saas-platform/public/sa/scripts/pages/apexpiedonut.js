import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Simple Pie Chart
    if (document.getElementById('simple-pie-chart')) {
        const simplePieOptions = {
            series: [44, 55, 13, 43, 22],
            chart: {
                width: '100%',
                height: 380,
                type: 'pie',
            },
            title: {
                text: 'Simple Pie Chart',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            labels: ['Team A', 'Team B', 'Team C', 'Team D', 'Team E'],
            legend: {
                position: 'right',
                offsetY: 0,
                height: 230,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[600].hex],
        };

        const simplePieChart = new ApexCharts(
            document.getElementById('simple-pie-chart'),
            simplePieOptions
        );
        simplePieChart.render();
    }

    // Simple Donut Chart
    if (document.getElementById('simple-donut-chart')) {
        const simpleDonutOptions = {
            series: [44, 55, 41, 17, 15],
            chart: {
                width: '100%',
                height: 380,
                type: 'donut',
            },
            title: {
                text: 'Simple Donut Chart',
                align: 'left',
            },
            labels: ['Team A', 'Team B', 'Team C', 'Team D', 'Team E'],
            legend: {
                position: 'right',
                offsetY: 0,
                height: 230,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[600].hex],
            tooltip: {
                theme: 'dark'
            }
        };

        const simpleDonutChart = new ApexCharts(
            document.getElementById('simple-donut-chart'),
            simpleDonutOptions
        );
        simpleDonutChart.render();
    }

    // Donut Update Chart
    if (document.getElementById('donut-update-chart')) {
        const donutUpdateOptions = {
            series: [44, 55, 13, 33],
            chart: {
                width: '100%',
                height: 380,
                type: 'donut',
            },
            title: {
                text: 'Donut Update Chart (Click to Update)',
                align: 'left',
            },
            labels: ['Team A', 'Team B', 'Team C', 'Team D'],
            dataLabels: {
                enabled: true,
                formatter: function(val, opts) {
                    return opts.w.config.series[opts.seriesIndex] + '%';
                }
            },
            legend: {
                position: 'right',
                offsetY: 0,
                height: 230,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex],
        };

        const donutUpdateChart = new ApexCharts(
            document.getElementById('donut-update-chart'),
            donutUpdateOptions
        );
        donutUpdateChart.render();

        // Add click event for updating the chart
        document.getElementById('donut-update-chart').addEventListener('click', function() {
            // Generate random values for the update
            donutUpdateChart.updateSeries([
                Math.floor(Math.random() * 91) + 10,
                Math.floor(Math.random() * 91) + 10,
                Math.floor(Math.random() * 91) + 10,
                Math.floor(Math.random() * 91) + 10
            ]);
        });
    }

    // Monochrome Pie Chart - keep only the bottom example
    if (document.getElementById('monochrome-pie-chart')) {
        const monochromePieOptions = {
            series: [12.7, 7.6, 22.3, 27.9, 20.8, 8.5],
            chart: {
                width: '100%',
                height: 380,
                type: 'pie',
            },
            title: {
                text: 'Monochrome Pie Chart',
                align: 'left',
            },
            labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            theme: {
                monochrome: {
                    enabled: true,
                    color: window.colorMap.primary[300].hex,
                    shadeTo: 'dark',
                    shadeIntensity: 0.65
                }
            },
            legend: {
                position: 'right',
                offsetY: 0,
                height: 230,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            dataLabels: {
                formatter: function(val, opts) {
                    return val.toFixed(1) + '%';
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }]
        };

        const monochromePieChart = new ApexCharts(
            document.getElementById('monochrome-pie-chart'),
            monochromePieOptions
        );
        monochromePieChart.render();
    }

    // Gradient Donut Chart - make gradient much more visible
    if (document.getElementById('gradient-donut-chart')) {
        const gradientDonutOptions = {
            series: [25.5, 32, 21.8, 9.9, 8.7],
            chart: {
                width: '100%',
                height: 380,
                type: 'donut',
            },
            plotOptions: {
                pie: {
                    startAngle: 0,
                    endAngle: 360,
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                            },
                            value: {
                                show: true,
                                color: window.colorMap.bootstrapVars.bodyColor.hex,
                                formatter: function (val) {
                                    return val.toFixed(1) + '%';
                                }
                            },
                            total: {
                                show: true,
                                label: 'Total',
                                color: window.colorMap.bootstrapVars.bodyColor.hex,
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => {
                                        return a + b;
                                    }, 0).toFixed(1) + '%';
                                }
                            }
                            
                        }
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            fill: {
                type: 'gradient',
                gradient: {
                    gradientToColors: ['var(--primary-900)', 'var(--success-900)', 'var(--info-900)', 'var(--danger-900)', 'var(--warning-900)'],
                    shade: 'dark',
                    type: 'vertical',
                    inverseColors: true,
                    opacityFrom: 1,
                    opacityTo: 0.6,
                    stops: [0, 100]
                }
            },
            stroke: {
                width: 0
            },
            legend: {
                position: 'right',
                offsetY: 0,
                height: 230,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            title: {
                text: 'Gradient Donut Chart',
                align: 'left',
            },
            labels: ['Products', 'Services', 'Support', 'Marketing', 'R&D'],
            colors: [window.colorMap.primary[500].hex, window.colorMap.success[500].hex, window.colorMap.info[500].hex, window.colorMap.danger[500].hex, window.colorMap.warning[500].hex],
        };

        const gradientDonutChart = new ApexCharts(
            document.getElementById('gradient-donut-chart'),
            gradientDonutOptions
        );
        gradientDonutChart.render();
    }

    // Donut with Pattern chart
    if (document.getElementById('donut-with-pattern-chart')) {
        const patternDonutOptions = {
            series: [44, 55, 41, 17, 15],
            chart: {
                width: '100%',
                height: 380,
                type: 'donut',
                dropShadow: {
                    enabled: true,
                    color: '#111',
                    top: -1,
                    left: 3,
                    blur: 3,
                    opacity: 0.2
                }
            },
            stroke: {
                width: 0,
            },
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                color: window.colorMap.bootstrapVars.bodyColor.hex,
                                fontSize: '14px'
                            },
                            value: {
                                show: true,
                                fontSize: '16px',
                                formatter: function (val) {
                                    return val + '%';
                                },
                                color: window.colorMap.bootstrapVars.bodyColor.hex,
                                background: {
                                    enabled: true,
                                    foreColor: window.colorMap.bootstrapVars.bodyColor.hex
                                }
                            },
                            total: {
                                show: true,
                                showAlways: true,
                                label: 'Total',
                                fontSize: '16px',
                                fontWeight: 600,
                                color: window.colorMap.bootstrapVars.bodyColor.hex,
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => {
                                        return a + b;
                                    }, 0);
                                }
                            }
                        }
                    }
                }
            },
            labels: ['Comedy', 'Action', 'SciFi', 'Drama', 'Horror'],
            dataLabels: {
                enabled: true,
                dropShadow: {
                    blur: 3,
                    opacity: 0.8
                },
                background: {
                    enabled: true,
                    foreColor: '#000',
                    padding: 4,
                    borderRadius: 2,
                    borderWidth: 1,
                    opacity: 0.9
                },
                style: {
                    fontSize: '14px',
                    fontWeight: 'bold',
                    colors: ['#fff']
                }
            },
            fill: {
                type: 'pattern',
                opacity: 1,
                pattern: {
                    enabled: true,
                    style: ['verticalLines', 'squares', 'horizontalLines', 'circles', 'slantedLines'],
                }
            },
            states: {
                hover: {
                    filter: 'none'
                }
            },
            theme: {
                palette: 'palette2'
            },
            colors: [
                window.colorMap.primary[500].hex,
                window.colorMap.info[500].hex,
                window.colorMap.success[500].hex,
                window.colorMap.danger[500].hex,
                window.colorMap.warning[500].hex
            ],
            title: {
                text: 'Favorite Movie Genre',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            legend: {
                position: 'right',
                offsetY: 0,
                height: 230,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }]
        };

        const patternDonutChart = new ApexCharts(
            document.getElementById('donut-with-pattern-chart'),
            patternDonutOptions
        );
        patternDonutChart.render();
    }

    // Basic Pie Chart
    if (document.getElementById('basic-pie-chart')) {
        const basicPieOptions = {
            series: [44, 55, 13, 43, 22],
            chart: {
                width: 380,
                type: 'pie',
                toolbar: {
                    show: true
                }
            },
            labels: ['Team A', 'Team B', 'Team C', 'Team D', 'Team E'],
            title: {
                text: 'Basic Pie Chart',
                align: 'left'
            },
            tooltip: {
                theme: 'dark'
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[600].hex]
        };

        const basicPieChart = new ApexCharts(
            document.getElementById('basic-pie-chart'),
            basicPieOptions
        );
        basicPieChart.render();
    }

    // Pie Chart with Labels
    if (document.getElementById('pie-with-labels-chart')) {
        const pieWithLabelsOptions = {
            series: [44, 55, 13, 43, 22],
            chart: {
                width: 380,
                type: 'pie',
                toolbar: {
                    show: true
                }
            },
            labels: ['Team A', 'Team B', 'Team C', 'Team D', 'Team E'],
            title: {
                text: 'Pie Chart with Custom Labels',
                align: 'left'
            },
            tooltip: {
                theme: 'dark'
            },
            dataLabels: {
                enabled: true,
                formatter: function(val, opts) {
                    return opts.w.config.series[opts.seriesIndex] + ' (' + val.toFixed(1) + '%)';
                },
                style: {
                    fontSize: '12px',
                    fontWeight: 'normal'
                },
                dropShadow: {
                    enabled: false
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[600].hex]
        };

        const pieWithLabelsChart = new ApexCharts(
            document.getElementById('pie-with-labels-chart'),
            pieWithLabelsOptions
        );
        pieWithLabelsChart.render();
    }

    // Basic Donut Chart
    if (document.getElementById('basic-donut-chart')) {
        const basicDonutOptions = {
            series: [44, 55, 41, 17, 15],
            chart: {
                width: 380,
                type: 'donut',
                toolbar: {
                    show: true
                }
            },
            labels: ['Products', 'Services', 'Support', 'Marketing', 'R&D'],
            title: {
                text: 'Basic Donut Chart',
                align: 'left'
            },
            tooltip: {
                theme: 'dark'
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[600].hex]
        };

        const basicDonutChart = new ApexCharts(
            document.getElementById('basic-donut-chart'),
            basicDonutOptions
        );
        basicDonutChart.render();
    }

    // Donut Chart with Pattern
    if (document.getElementById('donut-pattern-chart')) {
        const donutPatternOptions = {
            series: [44, 55, 41, 17, 15],
            chart: {
                width: 380,
                type: 'donut',
                dropShadow: {
                    enabled: true,
                    color: '#111',
                    top: -1,
                    left: 3,
                    blur: 3,
                    opacity: 0.2
                },
                toolbar: {
                    show: true
                }
            },
            labels: ['Products', 'Services', 'Support', 'Marketing', 'R&D'],
            title: {
                text: 'Donut Chart with Pattern',
                align: 'left'
            },
            stroke: {
                width: 0,
            },
            tooltip: {
                theme: 'dark'
            },
            fill: {
                type: 'pattern',
                opacity: 1,
                pattern: {
                    enabled: true,
                    style: ['verticalLines', 'squares', 'horizontalLines', 'circles', 'slantedLines']
                }
            },
            states: {
                hover: {
                    filter: {
                        type: 'none'
                    }
                }
            },
            legend: {
                position: 'bottom'
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 300
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[600].hex]
        };

        const donutPatternChart = new ApexCharts(
            document.getElementById('donut-pattern-chart'),
            donutPatternOptions
        );
        donutPatternChart.render();
    }

    // Semi Donut Chart - fix spacing issue
    if (document.getElementById('semi-donut-chart')) {
        const semiDonutOptions = {
            series: [44, 55, 41, 17, 15],
            chart: {
                width: '100%',
                height: 300,
                type: 'donut',
            },
            title: {
                text: 'Semi Donut Chart',
                align: 'left',
            },
            plotOptions: {
                pie: {
                    startAngle: -90,
                    endAngle: 90,
                    offsetY: -20,
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                            },
                            value: {
                                show: true,
                                formatter: function (val) {
                                    return val + '%';
                                }
                            }
                        }
                    }
                }
            },
            grid: {
                padding: {
                    bottom: -100
                }
            },
            legend: {
                position: 'right',
                offsetY: 40,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            labels: ['Team A', 'Team B', 'Team C', 'Team D', 'Team E'],
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[600].hex],
            tooltip: {
                theme: 'dark'
            }
        };

        const semiDonutChart = new ApexCharts(
            document.getElementById('semi-donut-chart'),
            semiDonutOptions
        );
        semiDonutChart.render();
    }

    // Pie with Image Chart
    if (document.getElementById('pie-with-image-chart')) {
        const pieWithImageOptions = {
            series: [44, 33, 54, 45],
            chart: {
                width: '100%',
                height: 380,
                type: 'pie',
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex],
            fill: {
                type: 'image',
                opacity: 0.85,
                image: {
                    src: ['img/demo/gallery/15.jpg', 'img/demo/gallery/7.jpg', 'img/demo/gallery/10.jpg', 'img/demo/gallery/20.jpg']
                }
            },
            stroke: {
                width: 4
            },
            dataLabels: {
                enabled: true,
                style: {
                    colors: ['#111']
                },
                background: {
                    enabled: true,
                    foreColor: '#fff',
                    borderWidth: 0
                }
            },
            legend: {
                position: 'right',
                offsetY: 0,
                height: 230,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            title: {
                text: "Image Fill Pie Chart",
                align: "left",
            }
        };

        const pieWithImageChart = new ApexCharts(
            document.getElementById('pie-with-image-chart'),
            pieWithImageOptions
        );
        pieWithImageChart.render();
    }
}); 