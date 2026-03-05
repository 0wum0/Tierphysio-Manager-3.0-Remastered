import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Function to generate bubble chart data
    function generateBubbleData(baseval, count, yrange) {
        let i = 0;
        const series = [];
        while (i < count) {
            const x = Math.floor(Math.random() * (45 - 20 + 1)) + 20;
            const y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
            const z = Math.floor(Math.random() * (75 - 15 + 1)) + 15;

            series.push([x, y, z]);
            baseval += 86400000;
            i++;
        }
        return series;
    }

    // Simple Bubble Chart
    if (document.getElementById('simple-bubble-chart')) {
        const simpleBubbleOptions = {
            series: [{
                name: 'Bubble 1',
                data: generateBubbleData(new Date('11 Feb 2022 GMT').getTime(), 20, {
                    min: 10,
                    max: 60
                })
            }, {
                name: 'Bubble 2',
                data: generateBubbleData(new Date('11 Feb 2022 GMT').getTime(), 20, {
                    min: 10,
                    max: 60
                })
            }, {
                name: 'Bubble 3',
                data: generateBubbleData(new Date('11 Feb 2022 GMT').getTime(), 20, {
                    min: 10,
                    max: 60
                })
            }],
            chart: {
                height: 350,
                type: 'bubble',
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: false
            },
            fill: {
                opacity: 0.8
            },
            xaxis: {
                tickAmount: 12,
                type: 'category',
            },
            yaxis: {
                max: 70,
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex],
            legend: {
                position: 'bottom',
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

        const simpleBubbleChart = new ApexCharts(
            document.getElementById('simple-bubble-chart'),
            simpleBubbleOptions
        );
        simpleBubbleChart.render();
    }

    // 3D Bubble Chart
    if (document.getElementById('3d-bubble-chart')) {
        // Generate data for 3D bubble chart
        function generateData(count, yrange) {
            let i = 0;
            const series = [];
            while (i < count) {
                const x = (Math.floor(Math.random() * 100) + 1).toString();
                const y = Math.floor(Math.random() * (yrange.max - yrange.min + 1)) + yrange.min;
                const z = Math.floor(Math.random() * 20) + 10;
                
                series.push({
                    x: x,
                    y: y,
                    z: z
                });
                i++;
            }
            return series;
        }

        const bubbleChart3dOptions = {
            series: [{
                name: 'Product 1',
                data: generateData(30, {
                    min: 10,
                    max: 80
                })
            }, {
                name: 'Product 2',
                data: generateData(30, {
                    min: 10,
                    max: 80
                })
            }, {
                name: 'Product 3',
                data: generateData(30, {
                    min: 10,
                    max: 80
                })
            }],
            chart: {
                height: 350,
                type: 'bubble',
                toolbar: {
                    show: true
                }
            },
            dataLabels: {
                enabled: false
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    gradientToColors: [window.colorMap.bootstrapVars.bodyBg.hex],
                    opacityFrom: 0.8,
                    opacityTo: 0.5
                }
            },
            xaxis: {
                type: 'category',
                tickAmount: 10,
            },
            yaxis: {
                max: 90,
                tickAmount: 7,
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.primary[300].hex, window.colorMap.primary[200].hex],
            legend: {
                position: 'bottom',
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

        const bubbleChart3d = new ApexCharts(
            document.getElementById('3d-bubble-chart'),
            bubbleChart3dOptions
        );
        bubbleChart3d.render();
    }
}); 