import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Funnel Chart
    if (document.getElementById('funnel-chart')) {
        const funnelOptions = {
            series: [
                {
                    name: "Funnel",
                    data: [1380, 1100, 690, 580, 380, 170]
                }
            ],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 0,
                    horizontal: true,
                    distributed: true,
                    barHeight: '80%',
                    isFunnel: true,
                }
            },
            states: {
                hover: {
                    filter: {
                        type: 'darken',
                        value: 0.85
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val, opt) {
                    return opt.w.globals.labels[opt.dataPointIndex] + ': ' + val
                },
                style: {
                    fontSize: '12px',
                    fontWeight: 600,
                    colors: ['#fff']
                },
                dropShadow: {
                    enabled: false
                }
            },
            title: {
                text: 'Website Conversion Funnel',
                align: 'left',
                style: {
                    fontSize: '16px',
                    fontWeight: 600,
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            subtitle: {
                text: 'Sales Conversion Path Analysis',
                align: 'left',
                style: {
                    fontSize: '12px',
                    fontWeight: 400,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.6)
                }
            },
            xaxis: {
                categories: [
                    'Visits', 
                    'Unique Visitors', 
                    'Signup', 
                    'Demo Requests', 
                    'Trials', 
                    'Purchases'
                ],
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                labels: {
                    show: false
                }
            },
            legend: {
                show: false,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(value) {
                        return value.toLocaleString();
                    }
                }
            },
            colors: [
                window.colorMap.primary[500].hex, 
                window.colorMap.primary[400].hex, 
                window.colorMap.primary[300].hex, 
                window.colorMap.primary[200].hex, 
                window.colorMap.primary[100].hex,
                window.colorMap.danger[500].hex
            ],
            grid: {
                padding: {
                    left: 20,
                    right: 20
                },
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.2)
            }
        };

        const funnelChart = new ApexCharts(
            document.getElementById('funnel-chart'),
            funnelOptions
        );
        funnelChart.render();
    }

    // Pyramid Chart
    if (document.getElementById('pyramid-chart')) {
        const pyramidOptions = {
            series: [
                {
                    name: "Pyramid",
                    data: [120, 220, 350, 480, 580, 690, 1100]
                }
            ],
            chart: {
                type: 'bar',
                height: 380,
                toolbar: {
                    show: true
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    distributed: true,
                    barHeight: '85%',
                    isFunnel: true,
                    flipFunnel: true
                }
            },
            states: {
                hover: {
                    filter: {
                        type: 'darken',
                        value: 0.85
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val, opt) {
                    return opt.w.globals.labels[opt.dataPointIndex] + ': ' + val
                },
                style: {
                    fontSize: '12px',
                    fontWeight: 600,
                    colors: ['#fff']
                },
                offsetX: 15,
                dropShadow: {
                    enabled: false
                }
            },
            title: {
                text: 'Organization Structure',
                align: 'left',
                style: {
                    fontSize: '16px',
                    fontWeight: 600,
                    color: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            subtitle: {
                text: 'Employee Distribution by Level',
                align: 'left',
                style: {
                    fontSize: '12px',
                    fontWeight: 400,
                    color: window.colorMap.bootstrapVars.bodyColor.rgba(0.6)
                }
            },
            legend: {
                show: false,
                labels: {
                    colors: window.colorMap.bootstrapVars.bodyColor.hex
                }
            },
            grid: {
                show: false,
                padding: {
                    left: 20,
                    right: 20
                },
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.2)
            },
            xaxis: {
                categories: [
                    'CEO', 
                    'Directors', 
                    'Managers', 
                    'Team Leads', 
                    'Senior Staff', 
                    'Junior Staff', 
                    'Interns'
                ],
                labels: {
                    show: false,
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
                labels: {
                    show: false
                }
            },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(value) {
                        return value + ' employees';
                    }
                }
            },
            colors: [
                window.colorMap.danger[500].hex,
                window.colorMap.primary[100].hex,
                window.colorMap.primary[200].hex, 
                window.colorMap.primary[300].hex,
                window.colorMap.primary[400].hex,
                window.colorMap.primary[500].hex,
                window.colorMap.primary[600].hex
            ]
        };

        const pyramidChart = new ApexCharts(
            document.getElementById('pyramid-chart'),
            pyramidOptions
        );
        pyramidChart.render();
    }
}); 