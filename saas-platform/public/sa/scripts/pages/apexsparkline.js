
import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Basic Sparkline Chart
    if (document.getElementById('basic-sparkline-chart')) {
        // Generate random data for sparklines
        function generateSparklineData(count, min, max) {
            const data = [];
            for (let i = 0; i < count; i++) {
                data.push(Math.floor(Math.random() * (max - min + 1)) + min);
            }
            return data;
        }

        // Create sparkline layout
        const sparklineContainer = document.getElementById('basic-sparkline-chart');
        sparklineContainer.innerHTML = `
            <div class="row m-0">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-0">Sales</h5>
                                    <span class="text-muted">Monthly</span>
                                </div>
                                <h3 class="text-success mb-0">+24%</h3>
                            </div>
                            <div id="spark1"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-0">Revenue</h5>
                                    <span class="text-muted">Quarterly</span>
                                </div>
                                <h3 class="text-success mb-0">+17%</h3>
                            </div>
                            <div id="spark2"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-0">Customers</h5>
                                    <span class="text-muted">Daily</span>
                                </div>
                                <h3 class="text-danger mb-0">-3%</h3>
                            </div>
                            <div id="spark3"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row m-0">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-0">Orders</h5>
                                    <span class="text-muted">Weekly</span>
                                </div>
                                <h3 class="text-success mb-0">+12%</h3>
                            </div>
                            <div id="spark4"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-0">Engagement</h5>
                                    <span class="text-muted">Monthly</span>
                                </div>
                                <h3 class="text-success mb-0">+8%</h3>
                            </div>
                            <div id="spark5"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-0">Conversion</h5>
                                    <span class="text-muted">Monthly</span>
                                </div>
                                <h3 class="text-success mb-0">+5%</h3>
                            </div>
                            <div id="spark6"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Sparkline 1 - Line
        const spark1Options = {
            series: [{
                data: generateSparklineData(30, 30, 90)
            }],
            chart: {
                type: 'line',
                height: 80,
                sparkline: {
                    enabled: true
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            stroke: {
                width: 2,
                curve: 'smooth'
            },
            colors: [window.colorMap.primary[500].hex],
            tooltip: {
                fixed: {
                    enabled: false
                },
                x: {
                    show: false
                },
                y: {
                    title: {
                        formatter: function() {
                            return 'Sales:';
                        }
                    }
                },
                marker: {
                    show: false
                }
            }
        };

        const spark1Chart = new ApexCharts(document.querySelector("#spark1"), spark1Options);
        spark1Chart.render();

        // Sparkline 2 - Column
        const spark2Options = {
            series: [{
                data: generateSparklineData(20, 10, 60)
            }],
            chart: {
                type: 'bar',
                height: 80,
                sparkline: {
                    enabled: true
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            colors: [window.colorMap.primary[400].hex],
            plotOptions: {
                bar: {
                    columnWidth: '60%'
                }
            },
            tooltip: {
                fixed: {
                    enabled: false
                },
                x: {
                    show: false
                },
                y: {
                    title: {
                        formatter: function() {
                            return 'Revenue:';
                        }
                    }
                },
                marker: {
                    show: false
                }
            }
        };

        const spark2Chart = new ApexCharts(document.querySelector("#spark2"), spark2Options);
        spark2Chart.render();

        // Sparkline 3 - Area
        const spark3Options = {
            series: [{
                data: generateSparklineData(30, 20, 50)
            }],
            chart: {
                type: 'area',
                height: 80,
                sparkline: {
                    enabled: true
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            stroke: {
                curve: 'straight',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.4,
                    gradientToColors: [window.colorMap.bootstrapVars.bodyBg.hex],
                    inverseColors: false,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 100]
                }
            },
            colors: [window.colorMap.danger[500].hex],
            tooltip: {
                fixed: {
                    enabled: false
                },
                x: {
                    show: false
                },
                y: {
                    title: {
                        formatter: function() {
                            return 'Customers:';
                        }
                    }
                },
                marker: {
                    show: false
                }
            }
        };

        const spark3Chart = new ApexCharts(document.querySelector("#spark3"), spark3Options);
        spark3Chart.render();

        // Sparkline 4 - Line with markers
        const spark4Options = {
            series: [{
                data: generateSparklineData(15, 40, 100)
            }],
            chart: {
                type: 'line',
                height: 80,
                sparkline: {
                    enabled: true
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            stroke: {
                width: 2,
                curve: 'straight'
            },
            colors: [window.colorMap.success[500].hex],
            markers: {
                size: 4,
                colors: [window.colorMap.success[500].hex],
                strokeColors: '#ffffff',
                strokeWidth: 2,
                hover: {
                    size: 6
                }
            },
            tooltip: {
                fixed: {
                    enabled: false
                },
                x: {
                    show: false
                },
                y: {
                    title: {
                        formatter: function() {
                            return 'Orders:';
                        }
                    }
                }
            }
        };

        const spark4Chart = new ApexCharts(document.querySelector("#spark4"), spark4Options);
        spark4Chart.render();

        // Sparkline 5 - Area with negative values
        const spark5Data = generateSparklineData(15, -10, 40).map(val => val - 15);
        const spark5Options = {
            series: [{
                data: spark5Data
            }],
            chart: {
                type: 'area',
                height: 80,
                sparkline: {
                    enabled: true
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: 'vertical',
                    shadeIntensity: 0.4,
                    gradientToColors: [window.colorMap.bootstrapVars.bodyBg.hex],
                    inverseColors: false,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 100]
                }
            },
            colors: [window.colorMap.primary[500].hex],
            tooltip: {
                fixed: {
                    enabled: false
                },
                x: {
                    show: false
                },
                y: {
                    title: {
                        formatter: function() {
                            return 'Engagement:';
                        }
                    }
                },
                marker: {
                    show: false
                }
            }
        };

        const spark5Chart = new ApexCharts(document.querySelector("#spark5"), spark5Options);
        spark5Chart.render();

        // Sparkline 6 - Bar with colors
        const spark6Data = generateSparklineData(24, 10, 50);
        const spark6Colors = spark6Data.map(value => {
            if (value >= 40) return window.colorMap.primary[500].hex;
            if (value >= 30) return window.colorMap.primary[100].hex;
            if (value >= 20) return window.colorMap.danger[500].hex;
            return window.colorMap.warning[500].hex;
        });

        const spark6Options = {
            series: [{
                data: spark6Data
            }],
            chart: {
                type: 'bar',
                height: 80,
                sparkline: {
                    enabled: true
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 800,
                }
            },
            colors: spark6Colors,
            plotOptions: {
                bar: {
                    columnWidth: '80%',
                    distributed: true
                }
            },
            tooltip: {
                fixed: {
                    enabled: false
                },
                x: {
                    show: false
                },
                y: {
                    title: {
                        formatter: function() {
                            return 'Conversion:';
                        }
                    }
                },
                marker: {
                    show: false
                }
            }
        };

        const spark6Chart = new ApexCharts(document.querySelector("#spark6"), spark6Options);
        spark6Chart.render();
    }
}); 