
import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Slope Chart
    if (document.getElementById('basic-slope-chart')) {
        const basicSlopeOptions = {
            series: [
                {
                    name: 'Sales',
                    data: [
                        {
                            x: '2019',
                            y: 40
                        },
                        {
                            x: '2023',
                            y: 80
                        }
                    ]
                },
                {
                    name: 'Revenue',
                    data: [
                        {
                            x: '2019',
                            y: 25
                        },
                        {
                            x: '2023',
                            y: 70
                        }
                    ]
                },
                {
                    name: 'Customers',
                    data: [
                        {
                            x: '2019',
                            y: 15
                        },
                        {
                            x: '2023',
                            y: 65
                        }
                    ]
                }
            ],
            chart: {
                type: 'line',
                height: 350,
                zoom: {
                    enabled: false
                },
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: true,
                offsetY: -5,
                style: {
                    fontSize: '12px',
                    fontWeight: 'normal',
                }
            },
            title: {
                text: 'Basic Slope Chart',
                align: 'left'
            },
            legend: {
                position: 'top'
            },
            colors: [window.colorMap.primary[100].hex, window.colorMap.primary[300].hex, window.colorMap.primary[600].hex],
            stroke: {
                width: 3
            },
            grid: {
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
            markers: {
                size: 5
            },
            xaxis: {
                type: 'category',
                tickAmount: 2,
                tickPlacement: 'on',
                axisTicks: {
                    show: false
                },
                axisBorder: {
                    show: false
                }
            },
            yaxis: {
                min: 0,
                max: 100,
                tickAmount: 5,
            }
        };

        const basicSlopeChart = new ApexCharts(
            document.getElementById('basic-slope-chart'),
            basicSlopeOptions
        );
        basicSlopeChart.render();
    }

    // Multi-group Slope Chart
    if (document.getElementById('multi-group-slope-chart')) {
        const multiGroupSlopeOptions = {
            series: [
                {
                    name: 'USA',
                    data: [
                        {
                            x: '2010',
                            y: 71
                        },
                        {
                            x: '2020',
                            y: 76
                        }
                    ]
                },
                {
                    name: 'UK',
                    data: [
                        {
                            x: '2010',
                            y: 55
                        },
                        {
                            x: '2020',
                            y: 81
                        }
                    ]
                },
                {
                    name: 'Germany',
                    data: [
                        {
                            x: '2010',
                            y: 50
                        },
                        {
                            x: '2020',
                            y: 65
                        }
                    ]
                },
                {
                    name: 'Japan',
                    data: [
                        {
                            x: '2010',
                            y: 40
                        },
                        {
                            x: '2020',
                            y: 85
                        }
                    ]
                },
                {
                    name: 'India',
                    data: [
                        {
                            x: '2010',
                            y: 45
                        },
                        {
                            x: '2020',
                            y: 70
                        }
                    ]
                }
            ],
            chart: {
                type: 'line',
                height: 350,
                zoom: {
                    enabled: false
                },
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: true,
                offsetX: 0,
                offsetY: 0,
                style: {
                    fontSize: '12px',
                    fontWeight: 'normal',
                }
            },
            title: {
                text: 'Multi-group Slope Chart',
                align: 'left'
            },
            tooltip: {
                theme: 'dark'
            },
            legend: {
                position: 'top'
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.info[500].hex, window.colorMap.success[500].hex, window.colorMap.warning[500].hex, window.colorMap.danger[500].hex],
            stroke: {
                width: 3
            },
            grid: {
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
            markers: {
                size: 6
            },
            xaxis: {
                type: 'category',
                tickAmount: 2,
                axisTicks: {
                    show: false
                },
                axisBorder: {
                    show: false
                }
            },
            yaxis: {
                min: 0,
                max: 100,
                tickAmount: 5,
            }
        };

        const multiGroupSlopeChart = new ApexCharts(
            document.getElementById('multi-group-slope-chart'),
            multiGroupSlopeOptions
        );
        multiGroupSlopeChart.render();
    }
}); 