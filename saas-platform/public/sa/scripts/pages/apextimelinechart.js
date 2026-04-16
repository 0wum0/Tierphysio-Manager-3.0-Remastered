
import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Timeline Chart
    if (document.getElementById('basic-timeline-chart')) {
        const basicTimelineOptions = {
            series: [
                {
                    data: [
                        {
                            x: 'Code',
                            y: [
                                new Date('2019-03-02').getTime(),
                                new Date('2019-03-04').getTime()
                            ]
                        },
                        {
                            x: 'Test',
                            y: [
                                new Date('2019-03-04').getTime(),
                                new Date('2019-03-08').getTime()
                            ]
                        },
                        {
                            x: 'Validation',
                            y: [
                                new Date('2019-03-08').getTime(),
                                new Date('2019-03-12').getTime()
                            ]
                        },
                        {
                            x: 'Deployment',
                            y: [
                                new Date('2019-03-12').getTime(),
                                new Date('2019-03-18').getTime()
                            ]
                        }
                    ]
                }
            ],
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true
                }
            },
            xaxis: {
                type: 'datetime'
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
            },
        };

        const basicTimelineChart = new ApexCharts(
            document.getElementById('basic-timeline-chart'),
            basicTimelineOptions
        );
        basicTimelineChart.render();
    }

    // Custom Colors Timeline Chart
    if (document.getElementById('custom-colors-timeline-chart')) {
        const customColorsTimelineOptions = {
            series: [
                {
                    data: [
                        {
                            x: 'Research',
                            y: [
                                new Date('2019-03-01').getTime(),
                                new Date('2019-03-05').getTime()
                            ],
                            fillColor: window.colorMap.primary[500].hex
                        },
                        {
                            x: 'Design',
                            y: [
                                new Date('2019-03-05').getTime(),
                                new Date('2019-03-09').getTime()
                            ],
                            fillColor: window.colorMap.success[500].hex
                        },
                        {
                            x: 'Development',
                            y: [
                                new Date('2019-03-09').getTime(),
                                new Date('2019-03-15').getTime()
                            ],
                            fillColor: window.colorMap.warning[600].hex
                        },
                        {
                            x: 'Testing',
                            y: [
                                new Date('2019-03-15').getTime(),
                                new Date('2019-03-20').getTime()
                            ],
                            fillColor: window.colorMap.danger[600].hex
                        },
                        {
                            x: 'Deployment',
                            y: [
                                new Date('2019-03-20').getTime(),
                                new Date('2019-03-25').getTime()
                            ],
                            fillColor: window.colorMap.info[500].hex
                        }
                    ]
                }
            ],
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    distributed: true,
                    dataLabels: {
                        hideOverflowingLabels: false
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val, opts) {
                    const label = opts.w.globals.labels[opts.dataPointIndex];
                    const a = new Date(val[0]);
                    const b = new Date(val[1]);
                    const diff = Math.floor((b - a) / (1000 * 60 * 60 * 24));
                    return label + ': ' + diff + (diff > 1 ? ' days' : ' day');
                },
                style: {
                    colors: ['#f3f4f5', '#fff']
                }
            },
            xaxis: {
                type: 'datetime'
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
        };

        const customColorsTimelineChart = new ApexCharts(
            document.getElementById('custom-colors-timeline-chart'),
            customColorsTimelineOptions
        );
        customColorsTimelineChart.render();
    }

    // Multi-series Timeline Chart
    if (document.getElementById('multi-series-timeline-chart')) {
        const multiSeriesTimelineOptions = {
            series: [
                {
                    name: 'Development Team',
                    data: [
                        {
                            x: 'Design',
                            y: [
                                new Date('2019-03-01').getTime(),
                                new Date('2019-03-05').getTime()
                            ]
                        },
                        {
                            x: 'Code',
                            y: [
                                new Date('2019-03-06').getTime(),
                                new Date('2019-03-15').getTime()
                            ]
                        },
                        {
                            x: 'Test',
                            y: [
                                new Date('2019-03-16').getTime(),
                                new Date('2019-03-22').getTime()
                            ]
                        }
                    ]
                },
                {
                    name: 'Testing Team',
                    data: [
                        {
                            x: 'Review',
                            y: [
                                new Date('2019-03-05').getTime(),
                                new Date('2019-03-08').getTime()
                            ]
                        },
                        {
                            x: 'QA Testing',
                            y: [
                                new Date('2019-03-14').getTime(),
                                new Date('2019-03-25').getTime()
                            ]
                        },
                        {
                            x: 'User Testing',
                            y: [
                                new Date('2019-03-20').getTime(),
                                new Date('2019-03-28').getTime()
                            ]
                        }
                    ]
                }
            ],
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true
                }
            },
            dataLabels: {
                enabled: true
            },
            legend: {
                position: 'top'
            },
            xaxis: {
                type: 'datetime'
            },
            tooltip: {
                theme: 'dark'
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex],
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
        };

        const multiSeriesTimelineChart = new ApexCharts(
            document.getElementById('multi-series-timeline-chart'),
            multiSeriesTimelineOptions
        );
        multiSeriesTimelineChart.render();
    }

    // Advanced Timeline Chart
    if (document.getElementById('advanced-timeline-chart')) {
        const advancedTimelineOptions = {
            series: [
                {
                    name: 'Bob',
                    data: [
                        {
                            x: 'Design',
                            y: [
                                new Date('2019-03-05').getTime(),
                                new Date('2019-03-08').getTime()
                            ]
                        },
                        {
                            x: 'Code',
                            y: [
                                new Date('2019-03-08').getTime(),
                                new Date('2019-03-11').getTime()
                            ]
                        },
                        {
                            x: 'Test',
                            y: [
                                new Date('2019-03-11').getTime(),
                                new Date('2019-03-16').getTime()
                            ]
                        }
                    ]
                },
                {
                    name: 'Alice',
                    data: [
                        {
                            x: 'Design',
                            y: [
                                new Date('2019-03-02').getTime(),
                                new Date('2019-03-05').getTime()
                            ]
                        },
                        {
                            x: 'Code',
                            y: [
                                new Date('2019-03-06').getTime(),
                                new Date('2019-03-09').getTime()
                            ]
                        },
                        {
                            x: 'Test',
                            y: [
                                new Date('2019-03-10').getTime(),
                                new Date('2019-03-19').getTime()
                            ]
                        }
                    ]
                }
            ],
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true
                }
            },
            xaxis: {
                type: 'datetime'
            },
            legend: {
                position: 'top'
            },
            tooltip: {
                theme: 'dark'
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.danger[500].hex],
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
        };

        const advancedTimelineChart = new ApexCharts(
            document.getElementById('advanced-timeline-chart'),
            advancedTimelineOptions
        );
        advancedTimelineChart.render();
    }

    // Multiple Series - Group Rows Timeline Chart
    if (document.getElementById('multiple-series-group-timeline-chart')) {
        const multipleSeriesGroupTimelineOptions = {
            series: [
                {
                    name: 'Frontend',
                    data: [
                        {
                            x: 'Design',
                            y: [
                                new Date('2019-03-02').getTime(),
                                new Date('2019-03-08').getTime()
                            ]
                        },
                        {
                            x: 'Development',
                            y: [
                                new Date('2019-03-08').getTime(),
                                new Date('2019-03-20').getTime()
                            ]
                        },
                        {
                            x: 'Testing',
                            y: [
                                new Date('2019-03-20').getTime(),
                                new Date('2019-03-28').getTime()
                            ]
                        }
                    ]
                },
                {
                    name: 'Backend',
                    data: [
                        {
                            x: 'API Design',
                            y: [
                                new Date('2019-03-01').getTime(),
                                new Date('2019-03-10').getTime()
                            ]
                        },
                        {
                            x: 'Implementation',
                            y: [
                                new Date('2019-03-10').getTime(),
                                new Date('2019-03-22').getTime()
                            ]
                        },
                        {
                            x: 'Integration Testing',
                            y: [
                                new Date('2019-03-22').getTime(),
                                new Date('2019-04-05').getTime()
                            ]
                        }
                    ]
                },
                {
                    name: 'Database',
                    data: [
                        {
                            x: 'Schema Design',
                            y: [
                                new Date('2019-03-05').getTime(),
                                new Date('2019-03-12').getTime()
                            ]
                        },
                        {
                            x: 'Data Migration',
                            y: [
                                new Date('2019-03-15').getTime(),
                                new Date('2019-03-25').getTime()
                            ]
                        },
                        {
                            x: 'Performance Testing',
                            y: [
                                new Date('2019-03-25').getTime(),
                                new Date('2019-04-10').getTime()
                            ]
                        }
                    ]
                }
            ],
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '70%',
                    rangeBarGroupRows: true
                }
            },
            xaxis: {
                type: 'datetime'
            },
            legend: {
                position: 'top'
            },
            tooltip: {
                theme: 'dark',
                custom: function({series, seriesIndex, dataPointIndex, w}) {
                    const task = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
                    const from = new Date(task.y[0]);
                    const to = new Date(task.y[1]);
                    
                    const formatDate = (date) => {
                        return date.getDate() + ' ' + date.toLocaleString('default', { month: 'short' }) + ' ' + date.getFullYear();
                    };
                    
                    return (
                        '<div class="p-2">' +
                        '<div><b>' + w.globals.initialSeries[seriesIndex].name + ': ' + task.x + '</b></div>' +
                        '<div>Start: ' + formatDate(from) + '</div>' +
                        '<div>End: ' + formatDate(to) + '</div>' +
                        '</div>'
                    );
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[400].hex, window.colorMap.primary[100].hex],
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
        };

        const multipleSeriesGroupTimelineChart = new ApexCharts(
            document.getElementById('multiple-series-group-timeline-chart'),
            multipleSeriesGroupTimelineOptions
        );
        multipleSeriesGroupTimelineChart.render();
    }

    // Dumbbell Chart (Horizontal)
    if (document.getElementById('dumbbell-timeline-chart')) {
        const dumbbellTimelineOptions = {
            series: [
                {
                    data: [
                        {
                            x: 'Project A',
                            y: [
                                new Date('2019-01-01').getTime(),
                                new Date('2019-03-15').getTime()
                            ]
                        },
                        {
                            x: 'Project B',
                            y: [
                                new Date('2019-02-15').getTime(),
                                new Date('2019-06-01').getTime()
                            ]
                        },
                        {
                            x: 'Project C',
                            y: [
                                new Date('2019-04-01').getTime(),
                                new Date('2019-08-15').getTime()
                            ]
                        },
                        {
                            x: 'Project D',
                            y: [
                                new Date('2019-05-15').getTime(),
                                new Date('2019-09-01').getTime()
                            ]
                        },
                        {
                            x: 'Project E',
                            y: [
                                new Date('2019-07-01').getTime(),
                                new Date('2019-12-31').getTime()
                            ]
                        }
                    ]
                }
            ],
            chart: {
                height: 350,
                type: 'rangeBar',
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    isDumbbell: true,
                    dumbbellColors: [[window.colorMap.primary[500].hex, window.colorMap.danger[500].hex]]
                }
            },
            xaxis: {
                type: 'datetime'
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        return val
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
                custom: function({series, seriesIndex, dataPointIndex, w}) {
                    const data = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
                    const startDate = new Date(data.y[0]);
                    const endDate = new Date(data.y[1]);
                    
                    const formatDate = (date) => {
                        return date.toLocaleDateString('en-US', { 
                            day: 'numeric', 
                            month: 'short', 
                            year: 'numeric' 
                        });
                    };
                    
                    return `
                    <div class="p-2">
                        <div><b>${data.x}</b></div>
                        <div>Start: ${formatDate(startDate)}</div>
                        <div>End: ${formatDate(endDate)}</div>
                        <div>Duration: ${Math.floor((endDate - startDate) / (1000 * 60 * 60 * 24))} days</div>
                    </div>
                    `;
                }
            }
        };

        const dumbbellTimelineChart = new ApexCharts(
            document.getElementById('dumbbell-timeline-chart'),
            dumbbellTimelineOptions
        );
        dumbbellTimelineChart.render();
    }
}); 
