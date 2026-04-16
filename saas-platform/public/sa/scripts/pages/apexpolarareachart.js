
import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Basic Polar Area Chart
    if (document.getElementById('basic-polar-area-chart')) {
        const basicPolarAreaOptions = {
            series: [14, 23, 21, 17, 15, 10, 12, 17, 21],
            chart: {
                height: 350,
                type: 'polarArea',
                toolbar: {
                    show: true
                }
            },
            stroke: {
                colors: [window.colorMap.bootstrapVars.bodyBg.hex]
            },
            fill: {
                opacity: 0.8
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 200
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            colors: window.colorMap.bootstrapVars.bodyColor.hex
                        }
                    }
                }
            }],
            labels: ['Category A', 'Category B', 'Category C', 'Category D', 'Category E', 'Category F', 'Category G', 'Category H', 'Category I'],
            colors: [
                window.colorMap.primary[50].hex,
                window.colorMap.primary[100].hex,
                window.colorMap.primary[200].hex,
                window.colorMap.primary[300].hex,
                window.colorMap.primary[400].hex,
                window.colorMap.primary[500].hex,
                window.colorMap.primary[600].hex,
                window.colorMap.info[50].hex,
                window.colorMap.info[100].hex
            ],
        };

        const basicPolarAreaChart = new ApexCharts(
            document.getElementById('basic-polar-area-chart'),
            basicPolarAreaOptions
        );
        basicPolarAreaChart.render();
    }

    // Polar Area with Monochrome Theme
    if (document.getElementById('monochrome-polar-area-chart')) {
        const monochromePolarAreaOptions = {
            series: [42, 47, 52, 58, 65],
            chart: {
                height: 350,
                type: 'polarArea',
                toolbar: {
                    show: true
                }
            },
            labels: ['Market Research', 'Direct Sales', 'Email Marketing', 'Social Media', 'Referrals'],
            fill: {
                opacity: 1
            },
            stroke: {
                width: 1,
                colors: undefined
            },
            yaxis: {
                show: false
            },
            legend: {
                position: 'bottom',
            },
            plotOptions: {
                polarArea: {
                    rings: {
                        strokeWidth: 0
                    },
                    spokes: {
                        strokeWidth: 0
                    },
                }
            },
            theme: {
                monochrome: {
                    enabled: true,
                    shadeTo: 'light',
                    shadeIntensity: 0.6,
                    color: window.colorMap.primary[500].hex
                }
            }
        };

        const monochromePolarAreaChart = new ApexCharts(
            document.getElementById('monochrome-polar-area-chart'),
            monochromePolarAreaOptions
        );
        monochromePolarAreaChart.render();
    }

    // Polar Area with Gradient
    if (document.getElementById('gradient-polar-area-chart')) {
        const gradientPolarAreaOptions = {
            series: [30, 40, 35, 50, 49, 60, 70, 91, 125],
            chart: {
                height: 350,
                type: 'polarArea',
                toolbar: {
                    show: true
                }
            },
            labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September'],
            fill: {
                opacity: 1,
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.5,
                    gradientToColors: undefined,
                    inverseColors: false,
                    opacityFrom: 0.8,
                    opacityTo: 0.5,
                    stops: [0, 100]
                }
            },
            stroke: {
                width: 1,
                colors: [window.colorMap.bootstrapVars.bodyBg.hex]
            },
            yaxis: {
                show: false
            },
            legend: {
                position: 'bottom',
            },
            plotOptions: {
                polarArea: {
                    rings: {
                        strokeWidth: 1,
                        strokeColor: window.colorMap.bootstrapVars.bodyBg.hex
                    },
                    spokes: {
                        strokeWidth: 1,
                        connectorColors: window.colorMap.bootstrapVars.bodyBg.hex
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return "$" + val + "K";
                    }
                }
            },
            colors: [
                window.colorMap.primary[50].hex,
                window.colorMap.primary[100].hex,
                window.colorMap.primary[200].hex,
                window.colorMap.primary[300].hex,
                window.colorMap.primary[400].hex,
                window.colorMap.primary[500].hex,
                window.colorMap.primary[600].hex,
                window.colorMap.primary[700].hex,
                window.colorMap.primary[800].hex
            ]
        };

        const gradientPolarAreaChart = new ApexCharts(
            document.getElementById('gradient-polar-area-chart'),
            gradientPolarAreaOptions
        );
        gradientPolarAreaChart.render();
    }
}); 