
import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic RadialBar Chart
    if (document.getElementById('basic-radialbar-chart')) {
        const basicRadialbarOptions = {
            series: [70],
            chart: {
                height: 350,
                type: 'radialBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                radialBar: {
                    hollow: {
                        size: '70%',
                    }
                },
            },
            labels: ['Progress'],
            colors: [window.colorMap.success[500].hex]
        };

        const basicRadialbarChart = new ApexCharts(
            document.getElementById('basic-radialbar-chart'),
            basicRadialbarOptions
        );
        basicRadialbarChart.render();
    }

    // Multiple RadialBar Chart
    if (document.getElementById('multiple-radialbar-chart')) {
        const multipleRadialbarOptions = {
            series: [44, 55, 67, 83],
            chart: {
                height: 350,
                type: 'radialBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                radialBar: {
                    dataLabels: {
                        name: {
                            fontSize: '22px',
                        },
                        value: {
                            fontSize: '16px',
                        },
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function(w) {
                                return Math.round(w.globals.seriesTotals.reduce((a, b) => a + b, 0) / w.globals.series.length) + '%';
                            }
                        }
                    }
                }
            },
            labels: ['Apples', 'Oranges', 'Bananas', 'Berries'],
            colors: [window.colorMap.primary[500].hex, window.colorMap.info[500].hex, window.colorMap.success[500].hex, window.colorMap.warning[500].hex]
        };

        const multipleRadialbarChart = new ApexCharts(
            document.getElementById('multiple-radialbar-chart'),
            multipleRadialbarOptions
        );
        multipleRadialbarChart.render();
    }

    // Custom Angle Circle Chart
    if (document.getElementById('custom-angle-circle-chart')) {
        const customAngleOptions = {
            series: [76, 67, 61, 90],
            chart: {
                height: 350,
                type: 'radialBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                radialBar: {
                    offsetY: 0,
                    startAngle: 0,
                    endAngle: 270,
                    hollow: {
                        margin: 5,
                        size: '30%',
                        background: 'transparent',
                        image: undefined,
                    },
                    dataLabels: {
                        name: {
                            show: false,
                        },
                        value: {
                            show: false,
                        }
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.info[500].hex, window.colorMap.success[500].hex, window.colorMap.warning[500].hex],
            labels: ['Vimeo', 'Messenger', 'Facebook', 'LinkedIn'],
            legend: {
                show: true,
                floating: true,
                fontSize: '16px',
                position: 'left',
                offsetX: 160,
                offsetY: 15,
                labels: {
                    useSeriesColors: true,
                },
                markers: {
                    size: 0
                },
                formatter: function(seriesName, opts) {
                    return seriesName + ":  " + opts.w.globals.series[opts.seriesIndex] + "%";
                },
                itemMargin: {
                    vertical: 3
                }
            },
            responsive: [{
                breakpoint: 480,
                options: {
                    legend: {
                        show: false
                    }
                }
            }]
        };

        const customAngleChart = new ApexCharts(
            document.getElementById('custom-angle-circle-chart'),
            customAngleOptions
        );
        customAngleChart.render();
    }

    // Gradient RadialBar Chart
    if (document.getElementById('gradient-radialbar-chart')) {
        const gradientRadialbarOptions = {
            series: [75],
            chart: {
                height: 350,
                type: 'radialBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                radialBar: {
                    startAngle: -135,
                    endAngle: 225,
                    hollow: {
                        margin: 0,
                        size: '70%',
                        background: '#fff',
                        image: undefined,
                        imageOffsetX: 0,
                        imageOffsetY: 0,
                        position: 'front',
                        dropShadow: {
                            enabled: true,
                            top: 3,
                            left: 0,
                            blur: 4,
                            opacity: 0.24
                        }
                    },
                    track: {
                        background: '#fff',
                        strokeWidth: '67%',
                        margin: 0,
                        dropShadow: {
                            enabled: true,
                            top: -3,
                            left: 0,
                            blur: 4,
                            opacity: 0.35
                        }
                    },
                    dataLabels: {
                        show: true,
                        name: {
                            offsetY: -10,
                            show: true,
                            color: '#888',
                            fontSize: '17px'
                        },
                        value: {
                            formatter: function(val) {
                                return parseInt(val) + '%';
                            },
                            color: '#111',
                            fontSize: '36px',
                            show: true,
                        }
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    type: 'horizontal',
                    shadeIntensity: 0.5,
                    gradientToColors: [window.colorMap.primary[500].hex],
                    inverseColors: true,
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 100]
                }
            },
            stroke: {
                lineCap: 'round'
            },
            labels: ['Percent'],
        };

        const gradientRadialbarChart = new ApexCharts(
            document.getElementById('gradient-radialbar-chart'),
            gradientRadialbarOptions
        );
        gradientRadialbarChart.render();
    }

    // RadialBar with Image
    if (document.getElementById('radialbar-with-image-chart')) {
        const radialbarWithImageOptions = {
            series: [67],
            chart: {
                height: 350,
                type: 'radialBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                radialBar: {
                    startAngle: -135,
                    endAngle: 225,
                    hollow: {
                        margin: 0,
                        size: '70%',
                        background: '#fff',
                        image: 'img/logo.png',
                        imageWidth: 150,
                        imageHeight: 150,
                        imageClipped: false,
                        position: 'front',
                        dropShadow: {
                            enabled: true,
                            top: 3,
                            left: 0,
                            blur: 4,
                            opacity: 0.24
                        }
                    },
                    track: {
                        background: '#fff',
                        strokeWidth: '67%',
                        margin: 0,
                        dropShadow: {
                            enabled: true,
                            top: -3,
                            left: 0,
                            blur: 4,
                            opacity: 0.35
                        }
                    },
                    dataLabels: {
                        show: true,
                        name: {
                            offsetY: -10,
                            show: true,
                            color: '#888',
                            fontSize: '17px'
                        },
                        value: {
                            formatter: function(val) {
                                return parseInt(val) + '%';
                            },
                            color: '#111',
                            fontSize: '36px',
                            show: true,
                        }
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    type: 'horizontal',
                    shadeIntensity: 0.5,
                    gradientToColors: [window.colorMap.primary[600].hex],
                    inverseColors: true,
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 100]
                }
            },
            stroke: {
                lineCap: 'round'
            },
            labels: ['Performance'],
        };

        const radialbarWithImageChart = new ApexCharts(
            document.getElementById('radialbar-with-image-chart'),
            radialbarWithImageOptions
        );
        radialbarWithImageChart.render();
    }

    // Stroked Gauge Chart
    if (document.getElementById('stroked-gauge-chart')) {
        const strokedGaugeOptions = {
            series: [67],
            chart: {
                height: 350,
                type: 'radialBar',
                offsetY: -10,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                radialBar: {
                    startAngle: -135,
                    endAngle: 135,
                    dataLabels: {
                        name: {
                            fontSize: '16px',
                            color: undefined,
                            offsetY: 120
                        },
                        value: {
                            offsetY: 76,
                            fontSize: '22px',
                            color: undefined,
                            formatter: function(val) {
                                return val + "%";
                            }
                        }
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'dark',
                    shadeIntensity: 0.15,
                    inverseColors: false,
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 50, 65, 91]
                },
            },
            stroke: {
                dashArray: 4
            },
            colors: [window.colorMap.primary[500].hex],
            labels: ['Performance'],
        };

        const strokedGaugeChart = new ApexCharts(
            document.getElementById('stroked-gauge-chart'),
            strokedGaugeOptions
        );
        strokedGaugeChart.render();
    }

    // Semi Circle Gauge Chart
    if (document.getElementById('semi-circle-gauge-chart')) {
        const semiCircleGaugeOptions = {
            series: [76],
            chart: {
                type: 'radialBar',
                height: 350,
                offsetY: -20,
                sparkline: {
                    enabled: true
                },
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                radialBar: {
                    startAngle: -90,
                    endAngle: 90,
                    track: {
                        background: "#e7e7e7",
                        strokeWidth: '97%',
                        margin: 5,
                        dropShadow: {
                            enabled: true,
                            top: 2,
                            left: 0,
                            color: '#999',
                            opacity: 1,
                            blur: 2
                        }
                    },
                    dataLabels: {
                        name: {
                            show: false
                        },
                        value: {
                            offsetY: -2,
                            fontSize: '22px'
                        }
                    }
                }
            },
            grid: {
                padding: {
                    top: -10
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    shadeIntensity: 0.4,
                    inverseColors: false,
                    opacityFrom: 1,
                    opacityTo: 1,
                    stops: [0, 50, 53, 91]
                },
            },
            colors: [window.colorMap.primary[500].hex],
            labels: ['Average Results'],
        };

        const semiCircleGaugeChart = new ApexCharts(
            document.getElementById('semi-circle-gauge-chart'),
            semiCircleGaugeOptions
        );
        semiCircleGaugeChart.render();
    }
}); 