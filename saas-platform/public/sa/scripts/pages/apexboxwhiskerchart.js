import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Box & Whisker Chart
    if (document.getElementById('basic-box-whisker-chart')) {
        const basicBoxOptions = {
            series: [{
                type: 'boxPlot',
                data: [
                    {
                        x: 'Jan 2015',
                        y: [54, 66, 69, 75, 88]
                    },
                    {
                        x: 'Jan 2016',
                        y: [43, 65, 69, 76, 81]
                    },
                    {
                        x: 'Jan 2017',
                        y: [31, 39, 45, 51, 59]
                    },
                    {
                        x: 'Jan 2018',
                        y: [39, 46, 55, 65, 71]
                    },
                    {
                        x: 'Jan 2019',
                        y: [29, 31, 35, 39, 44]
                    },
                    {
                        x: 'Jan 2020',
                        y: [41, 49, 58, 61, 67]
                    },
                    {
                        x: 'Jan 2021',
                        y: [54, 59, 66, 71, 88]
                    }
                ]
            }],
            chart: {
                type: 'boxPlot',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                boxPlot: {
                    colors: {
                        upper: window.colorMap.primary[500].hex,
                        lower: window.colorMap.primary[300].hex
                    }
                }
            },
            stroke: {
                colors: [window.colorMap.bootstrapVars.bodyColor.hex]
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
                }
            },
            tooltip: {
                theme: 'dark'
            }
        };

        const basicBoxChart = new ApexCharts(
            document.getElementById('basic-box-whisker-chart'),
            basicBoxOptions
        );
        basicBoxChart.render();
    }

    // Horizontal Boxplot Chart
    if (document.getElementById('horizontal-boxplot-chart')) {
        const horizontalBoxplotOptions = {
            series: [{
                data: [
                    {
                        x: 'Category A',
                        y: [54, 66, 69, 75, 88]
                    },
                    {
                        x: 'Category B',
                        y: [43, 65, 69, 76, 81]
                    },
                    {
                        x: 'Category C',
                        y: [31, 39, 45, 51, 59]
                    },
                    {
                        x: 'Category D',
                        y: [39, 46, 55, 65, 71]
                    },
                    {
                        x: 'Category E',
                        y: [29, 31, 35, 39, 44]
                    },
                    {
                        x: 'Category F',
                        y: [41, 49, 58, 61, 67]
                    },
                    {
                        x: 'Category G',
                        y: [54, 59, 66, 71, 88]
                    }
                ]
            }],
            chart: {
                type: 'boxPlot',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '50%'
                },
                boxPlot: {
                    colors: {
                        upper: window.colorMap.primary[500].hex,
                        lower: window.colorMap.primary[300].hex
                    },
                    horizontal: true
                }
            },
            stroke: {
                colors: [window.colorMap.bootstrapVars.bodyColor.hex]
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
            }
        };

        const horizontalBoxplotChart = new ApexCharts(
            document.getElementById('horizontal-boxplot-chart'),
            horizontalBoxplotOptions
        );
        horizontalBoxplotChart.render();
    }
}); 