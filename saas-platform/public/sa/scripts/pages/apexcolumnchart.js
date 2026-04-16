
import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Column Chart
    if (document.getElementById('basic-column-chart')) {
        const basicColumnOptions = {
            series: [{
                name: 'Net Profit',
                data: [44, 55, 57, 56, 61, 58, 63, 60, 66]
            }, {
                name: 'Revenue',
                data: [76, 85, 101, 98, 87, 105, 91, 114, 94]
            }, {
                name: 'Free Cash Flow',
                data: [35, 41, 36, 26, 45, 48, 52, 53, 41]
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: {
                    show: true
                },
                zoom: {
                    enabled: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded'
                },
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                axisBorder: {
                    show: true,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.1)
                },
                axisTicks: {
                    show: true,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.1)
                }
            },
            yaxis: {
                title: {
                    text: '$ (thousands)',
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
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                },
                row: {
                    colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05), 'transparent'],
                    opacity: 0.5
                }
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return "$ " + val + " thousands"
                    }
                },
                theme: 'dark'
            },
            legend: {
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex]
        };

        const basicColumnChart = new ApexCharts(
            document.getElementById('basic-column-chart'),
            basicColumnOptions
        );
        basicColumnChart.render();
    }

    // Column with Data Labels
    if (document.getElementById('column-with-data-labels-chart')) {
        const columnWithDataLabelsOptions = {
            series: [{
                name: 'Inflation',
                data: [2.3, 3.1, 4.0, 10.1, 4.0, 3.6, 3.2, 2.3, 1.4, 0.8, 0.5, 0.2]
            }],
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: true
                },
                zoom: {
                    enabled: false
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded'
                },
            },
            dataLabels: {
                enabled: true,
                offsetY: -20,
                style: {
                    fontSize: '12px',
                    colors: ['#fff']
                }
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                axisBorder: {
                    show: true,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.1)
                },
                axisTicks: {
                    show: true,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.1)
                }
            },
            yaxis: {
                title: {
                    text: 'Servings',
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
            grid: {
                show: true,
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.08),
                strokeDashArray: 5,
                position: 'back',
                xaxis: {
                    lines: {
                        show: false
                    }
                },
                row: {
                    colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05), 'transparent'],
                    opacity: 0.5
                }
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + " Servings"
                    }
                },
                theme: 'dark'
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex]
        };

        const columnWithDataLabelsChart = new ApexCharts(
            document.getElementById('column-with-data-labels-chart'),
            columnWithDataLabelsOptions
        );
        columnWithDataLabelsChart.render();
    }

    // Stacked Column Chart
    if (document.getElementById('stacked-column-chart')) {
        const stackedColumnOptions = {
            series: [{
                name: 'PRODUCT A',
                data: [44, 55, 41, 67, 22, 43]
            }, {
                name: 'PRODUCT B',
                data: [13, 23, 20, 8, 13, 27]
            }, {
                name: 'PRODUCT C',
                data: [11, 17, 15, 15, 21, 14]
            }, {
                name: 'PRODUCT D',
                data: [21, 7, 25, 13, 22, 8]
            }],
            chart: {
                height: 350,
                type: 'bar',
                stacked: true,
                toolbar: {
                    show: true
                },
                zoom: {
                    enabled: true
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    legend: {
                        position: 'bottom',
                        offsetX: -10,
                        offsetY: 0
                    }
                }
            }],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    endingShape: 'rounded'
                },
            },
            dataLabels: {
                enabled: true,
                style: {
                    fontSize: '12px',
                    colors: ["#304758"]
                }
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                type: 'category',
                categories: ['Q1 2020', 'Q2 2020', 'Q3 2020', 'Q4 2020', 'Q1 2021', 'Q2 2021'],
                labels: {
                    style: {
                        colors: Array(6).fill(window.colorMap.bootstrapVars.bodyColor.hex)
                    }
                },
                axisBorder: {
                    show: true,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.1)
                },
                axisTicks: {
                    show: true,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.1)
                }
            },
            yaxis: {
                title: {
                    text: 'Sales',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                labels: {
                    style: {
                        colors: [window.colorMap.bootstrapVars.bodyColor.hex]
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
                },
                row: {
                    colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05), 'transparent'],
                    opacity: 0.5
                }
            },
            legend: {
                position: 'right',
                offsetY: 40,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function (val) {
                        return val + " units"
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[100].hex]
        };

        const stackedColumnChart = new ApexCharts(
            document.getElementById('stacked-column-chart'),
            stackedColumnOptions
        );
        stackedColumnChart.render();
    }

    // Stacked 100 Column Chart
    if (document.getElementById('stacked-100-column-chart')) {
        const stacked100ColumnOptions = {
            series: [{
                name: 'PRODUCT A',
                data: [44, 55, 41, 67, 22, 43, 21, 49]
            }, {
                name: 'PRODUCT B',
                data: [13, 23, 20, 8, 13, 27, 33, 12]
            }, {
                name: 'PRODUCT C',
                data: [11, 17, 15, 15, 21, 14, 15, 13]
            }, {
                name: 'PRODUCT D',
                data: [21, 7, 25, 13, 22, 8, 10, 7]
            }],
            chart: {
                type: 'bar',
                height: 350,
                stacked: true,
                stackType: '100%',
                toolbar: {
                    show: true
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    legend: {
                        position: 'bottom',
                        offsetX: -10,
                        offsetY: 0
                    }
                }
            }],
            xaxis: {
                categories: ['2011 Q1', '2011 Q2', '2011 Q3', '2011 Q4', '2012 Q1', '2012 Q2',
                    '2012 Q3', '2012 Q4'
                ],
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
            fill: {
                opacity: 1
            },
            legend: {
                position: 'right',
                offsetX: 0,
                offsetY: 50,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
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
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[100].hex]
        };

        const stacked100ColumnChart = new ApexCharts(
            document.getElementById('stacked-100-column-chart'),
            stacked100ColumnOptions
        );
        stacked100ColumnChart.render();
    }

    // Grouped Stacked Column Chart
    if (document.getElementById('grouped-stacked-column-chart')) {
        const groupedStackedColumnOptions = {
            series: [{
                name: 'Actual Budget',
                group: 'Budget',
                data: [44000, 55000, 41000, 67000, 22000, 43000]
            }, {
                name: 'Expected Budget',
                group: 'Budget',
                data: [13000, 36000, 20000, 8000, 13000, 27000]
            }, {
                name: 'Actual Spending',
                group: 'Spending',
                data: [48000, 17000, 15000, 29000, 21000, 14000]
            }, {
                name: 'Expected Spending',
                group: 'Spending',
                data: [17000, 28000, 13000, 23000, 15000, 13000]
            }],
            chart: {
                type: 'bar',
                height: 350,
                stacked: true,
                toolbar: {
                    show: true
                }
            },
            stroke: {
                width: 1,
                colors: ['#fff']
            },
            plotOptions: {
                bar: {
                    horizontal: false
                }
            },
            xaxis: {
                categories: [
                    'Marketing',
                    'Research',
                    'Operations',
                    'Sales',
                    'HR',
                    'IT'
                ],
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
            fill: {
                opacity: 1
            },
            legend: {
                position: 'top',
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
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
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.danger[500].hex, window.colorMap.danger[300].hex]
        };

        const groupedStackedColumnChart = new ApexCharts(
            document.getElementById('grouped-stacked-column-chart'),
            groupedStackedColumnOptions
        );
        groupedStackedColumnChart.render();
    }

    // Column Range (Dumbbell Chart)
    if (document.getElementById('column-range-chart')) {
        const columnRangeOptions = {
            series: [{
                data: [{
                    x: '2015',
                    y: [5000, 8000]
                }, {
                    x: '2016',
                    y: [4000, 9500]
                }, {
                    x: '2017',
                    y: [5000, 7500]
                }, {
                    x: '2018',
                    y: [4500, 8500]
                }, {
                    x: '2019',
                    y: [6000, 9000]
                }, {
                    x: '2020',
                    y: [5000, 8700]
                }, {
                    x: '2021',
                    y: [6500, 8800]
                }]
            }],
            xaxis: {
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
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '30%',
                    borderRadius: 5
                }
            },
            dataLabels: {
                enabled: false
            },
            title: {
                text: 'Column Range Chart (Dumbbell)',
                align: 'left'
            },
            colors: [window.colorMap.primary[500].hex],
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

        const columnRangeChart = new ApexCharts(
            document.getElementById('column-range-chart'),
            columnRangeOptions
        );
        columnRangeChart.render();
    }

    // Distributed Column Chart
    if (document.getElementById('distributed-column-chart')) {
        const distributedColumnOptions = {
            series: [{
                data: [21, 22, 10, 28, 16, 21, 13, 30, 18, 20]
            }],
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: true
                },
                events: {
                    click: function(chart, w, e) {
                        // Handle click events if needed
                    }
                }
            },
            colors: [
                window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex, window.colorMap.primary[100].hex, window.colorMap.danger[500].hex, 
                window.colorMap.danger[50].hex, window.colorMap.info[200].hex, window.colorMap.info[600].hex, window.colorMap.success[300].hex, window.colorMap.warning[300].hex
            ],
            plotOptions: {
                bar: {
                    columnWidth: '45%',
                    distributed: true,
                    borderRadius: 4,
                    dataLabels: {
                        position: 'top'
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
                enabled: true,
                formatter: function(val) {
                    return val;
                },
                offsetY: -20,
                style: {
                    fontSize: '12px',
                    colors: [window.colorMap.bootstrapVars.bodyColor.hex]
                }
            },
            legend: {
                show: false
            },
            xaxis: {
                categories: [
                    'Team A', 'Team B', 'Team C', 'Team D', 'Team E',
                    'Team F', 'Team G', 'Team H', 'Team I', 'Team J'
                ],
                labels: {
                    style: {
                        fontSize: '12px',
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            title: {
                text: 'Distributed Column Chart',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            subtitle: {
                text: 'Each column has a different color',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.5)
                }
            }
        };

        const distributedColumnChart = new ApexCharts(
            document.getElementById('distributed-column-chart'),
            distributedColumnOptions
        );
        distributedColumnChart.render();
    }

    // Column with Negative Values
    if (document.getElementById('column-with-negative-values-chart')) {
        const columnWithNegativeValuesOptions = {
            series: [{
                name: 'Cash Flow',
                data: [1.45, 5.42, 5.9, -0.42, -12.6, -18.1, -18.2, -14.16, -11.1, -6.09, 0.34, 3.88, 13.07,
                    5.8, 2, 7.37, 8.1, 13.57, 15.75, 17.1, 19.8, -27.03, -54.4, -47.2, -43.3, -18.6,
                    -48.6, -41.1, -39.6, -37.6, -29.4, -21.4, -2.4
                ]
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    colors: {
                        ranges: [{
                            from: -100,
                            to: -46,
                            color: window.colorMap.danger[500].hex
                        }, {
                            from: -45,
                            to: 0,
                            color: window.colorMap.warning[500].hex
                        }]
                    },
                    columnWidth: '80%'
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                type: 'datetime',
                categories: [
                    '2011-01-01', '2011-02-01', '2011-03-01', '2011-04-01', '2011-05-01', '2011-06-01',
                    '2011-07-01', '2011-08-01', '2011-09-01', '2011-10-01', '2011-11-01', '2011-12-01',
                    '2012-01-01', '2012-02-01', '2012-03-01', '2012-04-01', '2012-05-01', '2012-06-01',
                    '2012-07-01', '2012-08-01', '2012-09-01', '2012-10-01', '2012-11-01', '2012-12-01',
                    '2013-01-01', '2013-02-01', '2013-03-01', '2013-04-01', '2013-05-01', '2013-06-01',
                    '2013-07-01', '2013-08-01', '2013-09-01'
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
                    text: 'Growth',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                labels: {
                    formatter: function(val) {
                        return val.toFixed(0) + "%";
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return val.toFixed(2) + "%";
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex],
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

        const columnWithNegativeValuesChart = new ApexCharts(
            document.getElementById('column-with-negative-values-chart'),
            columnWithNegativeValuesOptions
        );
        columnWithNegativeValuesChart.render();
    }

    // Column with Markers
    if (document.getElementById('column-with-markers-chart')) {
        const columnWithMarkersOptions = {
            series: [{
                name: 'Actual',
                data: [
                    {
                        x: '2011',
                        y: 1292,
                        goals: [
                            {
                                name: 'Expected',
                                value: 1400,
                                strokeHeight: 5,
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    },
                    {
                        x: '2012',
                        y: 4432,
                        goals: [
                            {
                                name: 'Expected',
                                value: 5400,
                                strokeHeight: 5,
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    },
                    {
                        x: '2013',
                        y: 5423,
                        goals: [
                            {
                                name: 'Expected',
                                value: 5200,
                                strokeHeight: 5,
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    },
                    {
                        x: '2014',
                        y: 6653,
                        goals: [
                            {
                                name: 'Expected',
                                value: 6500,
                                strokeHeight: 5,
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    },
                    {
                        x: '2015',
                        y: 8133,
                        goals: [
                            {
                                name: 'Expected',
                                value: 6600,
                                strokeHeight: 13,
                                strokeWidth: 0,
                                strokeLineCap: 'round',
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    },
                    {
                        x: '2016',
                        y: 7132,
                        goals: [
                            {
                                name: 'Expected',
                                value: 7500,
                                strokeHeight: 5,
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    },
                    {
                        x: '2017',
                        y: 7332,
                        goals: [
                            {
                                name: 'Expected',
                                value: 8700,
                                strokeHeight: 5,
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    },
                    {
                        x: '2018',
                        y: 6553,
                        goals: [
                            {
                                name: 'Expected',
                                value: 7300,
                                strokeHeight: 2,
                                strokeDashArray: 2,
                                strokeColor: window.colorMap.primary[300].hex
                            }
                        ]
                    }
                ]
            }],
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    columnWidth: '60%',
                    borderRadius: 3
                }
            },
            colors: [window.colorMap.warning[500].hex],
            dataLabels: {
                enabled: false
            },
            legend: {
                show: true,
                showForSingleSeries: true,
                customLegendItems: ['Actual', 'Expected'],
                markers: {
                    fillColors: [window.colorMap.warning[500].hex, window.colorMap.primary[300].hex]
                },
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            stroke: {
                width: 1
            },
            title: {
                text: 'Actual vs Expected Values',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            xaxis: {
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
                shared: true,
                intersect: false
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

        const columnWithMarkersChart = new ApexCharts(
            document.getElementById('column-with-markers-chart'),
            columnWithMarkersOptions
        );
        columnWithMarkersChart.render();
    }

    // Column with Group Labels
    if (document.getElementById('column-with-group-label-chart')) {
        const columnWithGroupLabelOptions = {
            series: [{
                name: 'Actual',
                data: [150000, 205000, 178000, 235000, 210000, 280000, 310000, 300000, 340000, 365000, 340000, 400000]
            }, {
                name: 'Target',
                data: [190000, 230000, 200000, 255000, 235000, 300000, 330000, 320000, 360000, 385000, 360000, 420000]
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '60%',
                    borderRadius: 3,
                    rangeBarOverlap: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 0,
                colors: ['transparent']
            },
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
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
                title: {
                    text: 'Sales (in dollars)',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                labels: {
                    formatter: function(val) {
                        return '$' + val.toLocaleString(undefined, {maximumFractionDigits: 0});
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                min: 0,
                max: 450000,
                tickAmount: 5
            },
            fill: {
                opacity: 1
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex],
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
                y: {
                    formatter: function(val) {
                        return '$' + val.toLocaleString(undefined, {maximumFractionDigits: 0});
                    }
                }
            }
        };

        const columnWithGroupLabelChart = new ApexCharts(
            document.getElementById('column-with-group-label-chart'),
            columnWithGroupLabelOptions
        );
        columnWithGroupLabelChart.render();
    }

    // Column with Rotated Labels
    if (document.getElementById('column-with-rotated-labels-chart')) {
        const columnWithRotatedLabelsOptions = {
            series: [{
                name: 'Population',
                data: [2.8, 3.9, 5.2, 6.1, 8.5, 11.7, 14.3, 16.5, 19.8, 24.1, 31.0]
            }],
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '70%',
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                width: 2
            },
            xaxis: {
                categories: [
                    'South Korea', 'Canada', 'United Kingdom', 'France', 'Germany', 
                    'Japan', 'Russia', 'Brazil', 'United States', 'Indonesia', 'India'
                ],
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    style: {
                        fontSize: '12px',
                        colors: Array(11).fill(window.colorMap.bootstrapVars.bodyColor.hex)
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Population (in millions)',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                labels: {
                    formatter: function(val) {
                        return val.toFixed(1) + 'M';
                    },
                    style: {
                        colors: Array(6).fill(window.colorMap.bootstrapVars.bodyColor.hex)
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.4,
                    gradientToColors: ['#ffffff'],
                    inverseColors: false,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 100]
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return val.toFixed(1) + ' million';
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex],
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

        const columnWithRotatedLabelsChart = new ApexCharts(
            document.getElementById('column-with-rotated-labels-chart'),
            columnWithRotatedLabelsOptions
        );
        columnWithRotatedLabelsChart.render();
    }

    // Dynamic Loaded Column Chart
    if (document.getElementById('dynamic-loaded-column-chart')) {
        const dynamicLoadedColumnOptions = {
            series: [{
                name: "Load",
                data: []
            }],
            chart: {
                height: 350,
                type: 'bar',
                toolbar: {
                    show: true
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                    animateGradually: {
                        enabled: true,
                        delay: 150
                    },
                    dynamicAnimation: {
                        enabled: true,
                        speed: 350
                    }
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '45%',
                    distributed: false
                }
            },
            dataLabels: {
                enabled: false
            },
            legend: {
                show: false
            },
            stroke: {
                show: true,
                width: 2
            },
            xaxis: {
                categories: [],
                labels: {
                    rotate: 0,
                    style: {
                        colors: Array(10).fill(window.colorMap.bootstrapVars.bodyColor.hex)
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: Array(6).fill(window.colorMap.bootstrapVars.bodyColor.hex)
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(val) {
                        return val.toFixed(0);
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex],
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

        const dynamicLoadedColumnChart = new ApexCharts(
            document.getElementById('dynamic-loaded-column-chart'),
            dynamicLoadedColumnOptions
        );
        dynamicLoadedColumnChart.render();

        // Function to simulate loading data
        const updateDynamicChart = function() {
            const newData = [];
            const newCategories = [];
            
            // Generate random data
            for (let i = 1; i <= 10; i++) {
                newData.push(Math.floor(Math.random() * 90) + 10);
                newCategories.push('Item ' + i);
            }
            
            // Update chart with new data
            dynamicLoadedColumnChart.updateOptions({
                xaxis: {
                    categories: newCategories
                }
            });
            
            dynamicLoadedColumnChart.updateSeries([{
                name: 'Load',
                data: newData
            }]);
        };

        // Initial load
        setTimeout(updateDynamicChart, 1000);
        
        // Set up reload button if it exists
        const reloadButton = document.getElementById('reload-dynamic-chart');
        if (reloadButton) {
            reloadButton.addEventListener('click', updateDynamicChart);
        } else {
            // If no button exists, reload every 5 seconds
            setInterval(updateDynamicChart, 5000);
        }
    }

    // Dumbbell Column Chart 
    if (document.getElementById('dumbbell-column-chart')) {
        const dumbbellColumnOptions = {
            series: [{
                data: [{
                    x: '2015',
                    y: [5000, 8000]
                }, {
                    x: '2016',
                    y: [4000, 9500]
                }, {
                    x: '2017',
                    y: [5000, 7500]
                }, {
                    x: '2018',
                    y: [4500, 8500]
                }, {
                    x: '2019',
                    y: [6000, 9000]
                }, {
                    x: '2020',
                    y: [5000, 8700]
                }, {
                    x: '2021',
                    y: [6500, 8800]
                }, {
                    x: '2022',
                    y: [7200, 9400]
                }, {
                    x: '2023',
                    y: [7800, 10200]
                }]
            }],
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '30%',
                    borderRadius: 5,
                    isDumbbell: true,
                    dumbbellColors: [[window.colorMap.primary[500].hex, window.colorMap.danger[500].hex]]
                }
            },
            title: {
                text: 'Year-wise Revenue Range (Min-Max)',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            subtitle: {
                text: 'Range between minimum and maximum revenue',
                align: 'left',
                style: {
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.5)
                }
            },
            legend: {
                show: false
            },
            xaxis: {
                type: 'category',
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    style: {
                        colors: Array(9).fill(window.colorMap.bootstrapVars.bodyColor.hex)
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Revenue ($ thousands)',
                    style: {
                        color: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                },
                labels: {
                    formatter: function(val) {
                        return '$' + val.toFixed(0) + 'k';
                    },
                    style: {
                        colors: Array(20).fill(window.colorMap.bootstrapVars.bodyColor.hex)
                    }
                }
            },
            tooltip: {
                theme: 'dark',
                custom: function({series, seriesIndex, dataPointIndex, w}) {
                    const data = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
                    const min = data.y[0];
                    const max = data.y[1];
                    const range = max - min;
                    
                    return (
                        '<div class="p-2">' +
                        '<div class="mb-1"><b>' + data.x + ' Revenue Range</b></div>' +
                        '<div>Min: $' + min.toLocaleString() + '</div>' +
                        '<div>Max: $' + max.toLocaleString() + '</div>' +
                        '<div>Range: $' + range.toLocaleString() + '</div>' +
                        '</div>'
                    );
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
            }
        };

        const dumbbellColumnChart = new ApexCharts(
            document.getElementById('dumbbell-column-chart'),
            dumbbellColumnOptions
        );
        dumbbellColumnChart.render();
    }
}); 