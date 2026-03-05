
import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Radar Chart
    if (document.getElementById('basic-radar-chart')) {
        const basicRadarOptions = {
            series: [{
                name: 'Basic Radar',
                data: [80, 50, 30, 40, 100, 20],
            }],
            chart: {
                height: 350,
                type: 'radar',
                toolbar: {
                    show: true
                }
            },
            xaxis: {
                categories: ['January', 'February', 'March', 'April', 'May', 'June'],
            },
            colors: [window.colorMap.primary[500].hex],
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val;
                    }
                }
            },
            markers: {
                size: 4,
                colors: [window.colorMap.primary[500].hex],
                strokeColors: '#fff',
                strokeWidth: 2
            },
        };

        const basicRadarChart = new ApexCharts(
            document.getElementById('basic-radar-chart'),
            basicRadarOptions
        );
        basicRadarChart.render();
    }

    // Multi-series Radar Chart
    if (document.getElementById('multi-series-radar-chart')) {
        const multiSeriesRadarOptions = {
            series: [{
                name: 'Series 1',
                data: [80, 50, 30, 40, 100, 20],
            }, {
                name: 'Series 2',
                data: [20, 30, 40, 80, 20, 80],
            }, {
                name: 'Series 3',
                data: [44, 76, 78, 13, 43, 10],
            }],
            chart: {
                height: 350,
                type: 'radar',
                dropShadow: {
                    enabled: true,
                    blur: 1,
                    left: 1,
                    top: 1
                },
                toolbar: {
                    show: true
                }
            },
            stroke: {
                width: 2
            },
            fill: {
                opacity: 0.1
            },
            markers: {
                size: 0
            },
            xaxis: {
                categories: ['January', 'February', 'March', 'April', 'May', 'June'],
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex],
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val;
                    }
                }
            },
            legend: {
                position: 'top',
            }
        };

        const multiSeriesRadarChart = new ApexCharts(
            document.getElementById('multi-series-radar-chart'),
            multiSeriesRadarOptions
        );
        multiSeriesRadarChart.render();
    }

    // Polygon Fill Radar Chart
    if (document.getElementById('polygon-fill-radar-chart')) {
        const polygonFillRadarOptions = {
            series: [{
                name: 'Series 1',
                data: [20, 100, 40, 30, 50, 80, 33],
            }],
            chart: {
                height: 350,
                type: 'radar',
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: true
            },
            plotOptions: {
                radar: {
                    size: 140,
                    polygons: {
                        strokeColors: window.colorMap.bootstrapVars.bodyColor.rgba(0.2),
                        fill: {
                            colors: [window.colorMap.bootstrapVars.bodyBg.rgba(0.1), '#fff']
                        }
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex],
            markers: {
                size: 4,
                colors: [window.colorMap.primary[500].hex],
                strokeColors: '#fff',
                strokeWidth: 2
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val;
                    }
                }
            },
            xaxis: {
                categories: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            },
            yaxis: {
                tickAmount: 7,
            }
        };

        const polygonFillRadarChart = new ApexCharts(
            document.getElementById('polygon-fill-radar-chart'),
            polygonFillRadarOptions
        );
        polygonFillRadarChart.render();
    }
}); 