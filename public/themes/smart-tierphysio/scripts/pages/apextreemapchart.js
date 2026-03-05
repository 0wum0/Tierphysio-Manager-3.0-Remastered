

import ApexCharts from '../thirdparty/apexchartsWrapper.js';


document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Treemap Chart
    if (document.getElementById('basic-treemap-chart')) {
        const basicTreemapOptions = {
            series: [{
                data: [
                    {
                        x: 'New Delhi',
                        y: 218
                    }, {
                        x: 'Kolkata',
                        y: 149
                    }, {
                        x: 'Mumbai',
                        y: 184
                    }, {
                        x: 'Ahmedabad',
                        y: 55
                    }, {
                        x: 'Bangalore',
                        y: 84
                    }, {
                        x: 'Pune',
                        y: 31
                    }, {
                        x: 'Chennai',
                        y: 70
                    }, {
                        x: 'Jaipur',
                        y: 30
                    }, {
                        x: 'Surat',
                        y: 44
                    }, {
                        x: 'Hyderabad',
                        y: 68
                    }, {
                        x: 'Lucknow',
                        y: 28
                    }, {
                        x: 'Indore',
                        y: 19
                    }, {
                        x: 'Kanpur',
                        y: 29
                    }
                ]
            }],
            chart: {
                height: 350,
                type: 'treemap',
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false,
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[100].hex],
            tooltip: {
                theme: 'dark'
            }
        };

        const basicTreemapChart = new ApexCharts(
            document.getElementById('basic-treemap-chart'),
            basicTreemapOptions
        );
        basicTreemapChart.render();
    }

    // Multiple Series Treemap Chart
    if (document.getElementById('multiple-series-treemap-chart')) {
        const multiSeriesTreemapOptions = {
            series: [{
                name: 'Desktops',
                data: [
                    {
                        x: 'ABC',
                        y: 10
                    }, {
                        x: 'DEF',
                        y: 60
                    }, {
                        x: 'XYZ',
                        y: 41
                    }
                ]
            }, {
                name: 'Mobile',
                data: [
                    {
                        x: 'ABCD',
                        y: 10
                    }, {
                        x: 'DEFG',
                        y: 20
                    }, {
                        x: 'WXYZ',
                        y: 51
                    }, {
                        x: 'PQR',
                        y: 30
                    }, {
                        x: 'MNO',
                        y: 20
                    }, {
                        x: 'CDE',
                        y: 30
                    }
                ]
            }],
            chart: {
                height: 350,
                type: 'treemap',
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false,
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[100].hex],
            plotOptions: {
                treemap: {
                    distributed: false,
                    enableShades: false
                }
            }
        };

        const multiSeriesTreemapChart = new ApexCharts(
            document.getElementById('multiple-series-treemap-chart'),
            multiSeriesTreemapOptions
        );
        multiSeriesTreemapChart.render();
    }

    // Color Range Treemap Chart
    if (document.getElementById('color-range-treemap-chart')) {
        const colorRangeOptions = {
            series: [{
                data: [
                    {
                        x: 'INTC',
                        y: 1.2
                    }, {
                        x: 'GS',
                        y: 0.4
                    }, {
                        x: 'CVX',
                        y: -1.4
                    }, {
                        x: 'GE',
                        y: 2.7
                    }, {
                        x: 'CAT',
                        y: -0.3
                    }, {
                        x: 'RTX',
                        y: 5.1
                    }, {
                        x: 'CSCO',
                        y: -2.3
                    }, {
                        x: 'JNJ',
                        y: 2.1
                    }, {
                        x: 'PG',
                        y: 0.3
                    }, {
                        x: 'TRV',
                        y: 0.12
                    }, {
                        x: 'MMM',
                        y: -2.31
                    }, {
                        x: 'NKE',
                        y: 3.98
                    }, {
                        x: 'IYT',
                        y: 1.67
                    }
                ]
            }],
            chart: {
                height: 350,
                type: 'treemap',
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false,
                }
            },
            tooltip: {
                theme: 'dark'
            },
            plotOptions: {
                treemap: {
                    distributed: true,
                    enableShades: false,
                    colorScale: {
                        ranges: [{
                            from: -6,
                            to: 0,
                            color: window.colorMap.danger[500].hex
                        }, {
                            from: 0.001,
                            to: 6,
                            color: window.colorMap.primary[500].hex
                        }]
                    }
                }
            }
        };

        const colorRangeTreemapChart = new ApexCharts(
            document.getElementById('color-range-treemap-chart'),
            colorRangeOptions
        );
        colorRangeTreemapChart.render();
    }

    // Distributed Treemap Chart
    if (document.getElementById('distributed-treemap-chart')) {
        const distributedTreemapOptions = {
            series: [{
                data: [
                    {
                        x: 'New Delhi',
                        y: 218
                    }, {
                        x: 'Kolkata',
                        y: 149
                    }, {
                        x: 'Mumbai',
                        y: 184
                    }, {
                        x: 'Ahmedabad',
                        y: 55
                    }, {
                        x: 'Bangalore',
                        y: 84
                    }, {
                        x: 'Pune',
                        y: 31
                    }, {
                        x: 'Chennai',
                        y: 70
                    }, {
                        x: 'Jaipur',
                        y: 30
                    }, {
                        x: 'Surat',
                        y: 44
                    }, {
                        x: 'Hyderabad',
                        y: 68
                    }, {
                        x: 'Lucknow',
                        y: 28
                    }, {
                        x: 'Indore',
                        y: 19
                    }, {
                        x: 'Kanpur',
                        y: 29
                    }
                ]
            }],
            chart: {
                height: 350,
                type: 'treemap',
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false,
                }
            },
            colors: [
                window.colorMap.primary[800].hex, window.colorMap.primary[500].hex, window.colorMap.success[500].hex, window.colorMap.success[700].hex, 
                window.colorMap.danger[500].hex, window.colorMap.warning[500].hex, window.colorMap.info[500].hex, window.colorMap.primary[100].hex
            ],
            tooltip: {
                theme: 'dark'
            },
            plotOptions: {
                treemap: {
                    distributed: true,
                    enableShades: false
                }
            }
        };

        const distributedTreemapChart = new ApexCharts(
            document.getElementById('distributed-treemap-chart'),
            distributedTreemapOptions
        );
        distributedTreemapChart.render();
    }
}); 