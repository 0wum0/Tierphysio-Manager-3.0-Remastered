import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    function generateData(count, yrange) {
        var i = 0;
        var series = [];
        while (i < count) {
            var x = 'w' + (i + 1).toString();
            var y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;

            series.push({
                x: x,
                y: y
            });
            i++;
        }
        return series;
    }

    // Basic Heatmap Chart
    if (document.getElementById('basic-heatmap-chart')) {
        const basicHeatmapOptions = {
            series: [{
                name: 'Metric 1',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 2',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 3',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 4',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 5',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 6',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 7',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 8',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 9',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            }
            ],
            chart: {
                height: 350,
                type: 'heatmap',
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: false
            },
            colors: [window.colorMap.primary[500].hex],
            tooltip: {
                theme: 'dark'
            },
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.2)
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
            legend: {
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            }
        };

        const basicHeatmapChart = new ApexCharts(
            document.getElementById('basic-heatmap-chart'),
            basicHeatmapOptions
        );
        basicHeatmapChart.render();
    }

    // Heatmap Chart with Multiple Series
    if (document.getElementById('multiple-series-heatmap-chart')) {
        const multipleSeriesHeatmapOptions = {
            series: [{
                name: 'Jan',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Feb',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Mar',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Apr',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'May',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Jun',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Jul',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Aug',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Sep',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            }
            ],
            chart: {
                height: 350,
                type: 'heatmap',
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: false
            },
            colors: [window.colorMap.primary[500].hex],
            xaxis: {
                type: 'category',
                categories: ['10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '01:00', '01:30', '02:00', '02:30', '03:00', '03:30', '04:00', '04:30', '05:00', '05:30', '06:00', '06:30', '07:00', '07:30'],
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
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.2)
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

        const multipleSeriesHeatmapChart = new ApexCharts(
            document.getElementById('multiple-series-heatmap-chart'),
            multipleSeriesHeatmapOptions
        );
        multipleSeriesHeatmapChart.render();
    }

    // Heatmap Chart with Color Range
    if (document.getElementById('color-range-heatmap-chart')) {
        const colorRangeHeatmapOptions = {
            series: [{
                name: 'Jan',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Feb',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Mar',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Apr',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'May',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Jun',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Jul',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Aug',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            },
            {
                name: 'Sep',
                data: generateData(20, {
                    min: -30,
                    max: 55
                })
            }
            ],
            chart: {
                height: 350,
                type: 'heatmap',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                heatmap: {
                    shadeIntensity: 0.5,
                    radius: 0,
                    useFillColorAsStroke: true,
                    colorScale: {
                        ranges: [{
                            from: -30,
                            to: 5,
                            name: 'Low',
                            color: window.colorMap.success[100].hex
                        },
                        {
                            from: 6,
                            to: 20,
                            name: 'Medium',
                            color: window.colorMap.primary[300].hex
                        },
                        {
                            from: 21,
                            to: 45,
                            name: 'High',
                            color: window.colorMap.primary[500].hex
                        },
                        {
                            from: 46,
                            to: 55,
                            name: 'Extreme',
                            color: window.colorMap.danger[500].hex
                        }
                        ]
                    }
                }
            },
            dataLabels: {
                enabled: false
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
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.2)
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

        const colorRangeHeatmapChart = new ApexCharts(
            document.getElementById('color-range-heatmap-chart'),
            colorRangeHeatmapOptions
        );
        colorRangeHeatmapChart.render();
    }

    // Heatmap Chart with Rounded Corners
    if (document.getElementById('rounded-heatmap-chart')) {
        const roundedHeatmapOptions = {
            series: [{
                name: 'Metric 1',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 2',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 3',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 4',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 5',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 6',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 7',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 8',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            },
            {
                name: 'Metric 9',
                data: generateData(18, {
                    min: 0,
                    max: 90
                })
            }
            ],
            chart: {
                height: 350,
                type: 'heatmap',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                heatmap: {
                    radius: 15,
                    enableShades: false,
                    colorScale: {
                        ranges: [{
                            from: 0,
                            to: 30,
                            color: window.colorMap.primary[300].hex
                        },
                        {
                            from: 31,
                            to: 60,
                            color: window.colorMap.primary[500].hex
                        },
                        {
                            from: 61,
                            to: 90,
                            color: window.colorMap.primary[700].hex
                        }
                        ],
                    }
                }
            },
            dataLabels: {
                enabled: false
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
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.2)
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

        const roundedHeatmapChart = new ApexCharts(
            document.getElementById('rounded-heatmap-chart'),
            roundedHeatmapOptions
        );
        roundedHeatmapChart.render();
    }
}); 