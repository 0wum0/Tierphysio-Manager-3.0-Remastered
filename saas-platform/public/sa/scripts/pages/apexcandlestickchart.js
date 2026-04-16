import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Sample data for candlestick charts
    const seriesData = [{
        x: new Date(2016, 1, 1),
        y: [51.98, 56.29, 51.59, 53.85]
    }, {
        x: new Date(2016, 2, 1),
        y: [53.66, 54.99, 51.35, 52.95]
    }, {
        x: new Date(2016, 3, 1),
        y: [52.96, 53.78, 51.54, 52.48]
    }, {
        x: new Date(2016, 4, 1),
        y: [52.54, 52.79, 47.88, 49.24]
    }, {
        x: new Date(2016, 5, 1),
        y: [49.10, 52.86, 47.70, 52.78]
    }, {
        x: new Date(2016, 6, 1),
        y: [52.83, 53.48, 50.32, 52.29]
    }, {
        x: new Date(2016, 7, 1),
        y: [52.20, 54.48, 51.64, 52.58]
    }, {
        x: new Date(2016, 8, 1),
        y: [52.76, 57.35, 52.15, 57.03]
    }, {
        x: new Date(2016, 9, 1),
        y: [57.04, 58.15, 48.88, 56.19]
    }, {
        x: new Date(2016, 10, 1),
        y: [56.09, 58.85, 55.48, 58.79]
    }, {
        x: new Date(2016, 11, 1),
        y: [58.78, 59.65, 58.23, 59.05]
    }, {
        x: new Date(2017, 0, 1),
        y: [59.37, 61.11, 59.35, 60.34]
    }, {
        x: new Date(2017, 1, 1),
        y: [60.40, 60.52, 56.71, 56.93]
    }, {
        x: new Date(2017, 2, 1),
        y: [57.02, 59.71, 56.04, 56.82]
    }, {
        x: new Date(2017, 3, 1),
        y: [56.97, 59.62, 54.77, 59.30]
    }, {
        x: new Date(2017, 4, 1),
        y: [59.11, 62.29, 59.10, 59.85]
    }, {
        x: new Date(2017, 5, 1),
        y: [59.97, 60.11, 55.66, 58.42]
    }, {
        x: new Date(2017, 6, 1),
        y: [58.34, 60.93, 56.75, 57.42]
    }, {
        x: new Date(2017, 7, 1),
        y: [57.76, 58.08, 51.18, 54.71]
    }, {
        x: new Date(2017, 8, 1),
        y: [54.80, 61.42, 53.18, 57.35]
    }, {
        x: new Date(2017, 9, 1),
        y: [57.56, 63.09, 57.00, 62.99]
    }, {
        x: new Date(2017, 10, 1),
        y: [62.89, 63.42, 59.72, 61.76]
    }, {
        x: new Date(2017, 11, 1),
        y: [61.71, 64.15, 61.29, 63.04]
    }];

    // Basic Candlestick Chart
    if (document.getElementById('basic-candlestick-chart')) {
        const basicCandlestickOptions = {
            series: [{
                data: seriesData
            }],
            chart: {
                type: 'candlestick',
                height: 350,
                toolbar: {
                    show: false,
                },
                zoom: {
                    enabled: false,
                }
            },
            // title: {
            //     text: 'Basic Candlestick Chart',
            //     align: 'left'
            // },
            tooltip: {
                theme: 'dark',
                y: {
                    formatter: function(y) {
                        if (typeof y !== "undefined") {
                            return "$" + y.toFixed(2);
                        }
                        return y;
                    }
                }
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    formatter: function(val) {
                        return new Date(val).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: {
                tooltip: {
                    enabled: true
                },
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
            plotOptions: {
                candlestick: {
                    colors: {
                        upward: window.colorMap.primary[500].hex,
                        downward: window.colorMap.danger[500].hex
                    }
                }
            }
        };

        const basicCandlestickChart = new ApexCharts(
            document.getElementById('basic-candlestick-chart'),
            basicCandlestickOptions
        );
        basicCandlestickChart.render();
    }

    // Combo Candlestick Chart
    if (document.getElementById('combo-candlestick-chart')) {
        // Generate volume data
        const volumeData = [];
        for (let i = 0; i < seriesData.length; i++) {
            volumeData.push({
                x: seriesData[i].x,
                y: Math.floor(Math.random() * 1000000) + 200000
            });
        }

        const comboCandlestickOptions = {
            series: [{
                name: 'candle',
                type: 'candlestick',
                data: seriesData
            }, {
                name: 'volume',
                type: 'bar',
                data: volumeData
            }],
            chart: {
                height: 350,
                type: 'line',
                stacked: false,
                toolbar: {
                    show: false,
                },
                zoom: {
                    enabled: false,
                }
            },
            stroke: {
                width: [1, 1]
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    formatter: function(val) {
                        return new Date(val).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    },
                }
            },
            yaxis: [{
                seriesName: 'candle',
                title: {
                    text: 'Price',
                    style: {
                        color: window.colorMap.primary[500].hex
                    }
                },
                labels: {
                    formatter: function(val) {
                        return '$' + val.toFixed(2);
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    },
                    tooltip: {
                        enabled: true
                    }
                }
            }, {
                seriesName: 'volume',
                opposite: true,
                title: {
                    text: 'Volume',
                    style: {
                        color: window.colorMap.success[500].hex
                    }
                },
                labels: {
                    formatter: function(val) {
                        return Intl.NumberFormat('en-US', { notation: 'compact', compactDisplay: 'short' }).format(val);
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            }],
            tooltip: {
                enabled: true,
                shared: true
            },
            plotOptions: {
                candlestick: {
                    colors: {
                        upward: window.colorMap.primary[500].hex,
                        downward: window.colorMap.danger[500].hex
                    }
                },
                bar: {
                    columnWidth: '80%',
                    colors: {
                        ranges: [{
                            from: 0,
                            to: 999999999,
                            color: window.colorMap.success[100].hex
                        }]
                    }
                }
            },
            legend: {
                position: 'top',
            },
            colors: [ window.colorMap.primary[500].hex, window.colorMap.success[100].hex],
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

        const comboCandlestickChart = new ApexCharts(
            document.getElementById('combo-candlestick-chart'),
            comboCandlestickOptions
        );
        comboCandlestickChart.render();
    }

    // Category X-axis Candlestick Chart
    if (document.getElementById('category-x-axis-candlestick-chart')) {
        // Data with categories
        const categorySeriesData = [
            {
                x: 'Jan',
                y: [1500, 1700, 1400, 1650]
            },
            {
                x: 'Feb',
                y: [1650, 1850, 1600, 1700]
            },
            {
                x: 'Mar',
                y: [1700, 2000, 1680, 1880]
            },
            {
                x: 'Apr',
                y: [1880, 2050, 1750, 1800]
            },
            {
                x: 'May',
                y: [1800, 1980, 1750, 1890]
            },
            {
                x: 'Jun',
                y: [1890, 2100, 1850, 2050]
            },
            {
                x: 'Jul',
                y: [2050, 2200, 1900, 2100]
            },
            {
                x: 'Aug',
                y: [2100, 2300, 2050, 2200]
            },
            {
                x: 'Sep',
                y: [2200, 2400, 2150, 2300]
            },
            {
                x: 'Oct',
                y: [2300, 2500, 2200, 2450]
            },
            {
                x: 'Nov',
                y: [2450, 2600, 2350, 2550]
            },
            {
                x: 'Dec',
                y: [2550, 2750, 2500, 2700]
            }
        ];

        const categoryXAxisOptions = {
            series: [{
                name: 'Product A',
                data: categorySeriesData
            }],
            chart: {
                type: 'candlestick',
                height: 350,
                toolbar: {
                    show: false,
                },
                zoom: {
                    enabled: false,
                }
            },
            title: {
                text: 'Monthly Performance Candlestick',
                align: 'left'
            },
            tooltip: {
                theme: 'dark',
                custom: function({ seriesIndex, dataPointIndex, w }) {
                    const data = w.globals.initialSeries[seriesIndex].data[dataPointIndex];
                    return '<div class="p-2">' +
                        '<span>Month: ' + data.x + '</span><br>' +
                        '<span>Open: $' + data.y[0] + '</span><br>' +
                        '<span>High: $' + data.y[1] + '</span><br>' +
                        '<span>Low: $' + data.y[2] + '</span><br>' +
                        '<span>Close: $' + data.y[3] + '</span>' +
                        '</div>';
                }
            },
            plotOptions: {
                candlestick: {
                    colors: {
                        upward: window.colorMap.primary[500].hex,
                        downward: window.colorMap.danger[500].hex
                    }
                }
            },
            xaxis: {
                type: 'category',
                labels: {
                    rotate: -45,
                    rotateAlways: false,
                }
            },
            yaxis: {
                tooltip: {
                    enabled: true
                },
                labels: {
                    formatter: function(val) {
                        return '$' + val;
                    },
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
            }
        };

        const categoryXAxisChart = new ApexCharts(
            document.getElementById('category-x-axis-candlestick-chart'),
            categoryXAxisOptions
        );
        categoryXAxisChart.render();
    }

    // Candlestick with Line Chart
    if (document.getElementById('candlestick-with-line-chart')) {
        // Generate line series data (moving average)
        const lineData = [];
        
        for (let i = 0; i < seriesData.length; i++) {
            if (i >= 4) { // 5-day moving average
                let sum = 0;
                for (let j = i; j > i - 5; j--) {
                    sum += seriesData[j].y[3]; // use the closing price
                }
                lineData.push({
                    x: seriesData[i].x,
                    y: +(sum / 5).toFixed(2)
                });
            } else {
                lineData.push({
                    x: seriesData[i].x,
                    y: seriesData[i].y[3]
                });
            }
        }

        const candlestickWithLineOptions = {
            series: [{
                name: 'Candle',
                type: 'candlestick',
                data: seriesData
            }, {
                name: '5-day MA',
                type: 'line',
                data: lineData
            }],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: false,
                },
                zoom: {
                    enabled: false,
                }
            },
            stroke: {
                width: [1, 3]
            },
            tooltip: {
                shared: true
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    formatter: function(val) {
                        return new Date(val).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            },
            yaxis: [{
                tooltip: {
                    enabled: true
                },
                labels: {
                    formatter: function(val) {
                        return '$' + val.toFixed(2);
                    },
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex
                    }
                }
            }],
            legend: {
                position: 'top',
            },
            plotOptions: {
                candlestick: {
                    colors: {
                        upward: window.colorMap.primary[500].hex,
                        downward: window.colorMap.danger[500].hex
                    }
                }
            },
            colors: [window.colorMap.primary[500].hex, window.colorMap.success[300].hex],
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

        const candlestickWithLineChart = new ApexCharts(
            document.getElementById('candlestick-with-line-chart'),
            candlestickWithLineOptions
        );
        candlestickWithLineChart.render();
    }
}); 