// All imports for the Project Management Dashboard

import ApexCharts from '../thirdparty/apexchartsWrapper.js';

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    /***************************************************************/
    /* Development Phases Chart #development-phases-chart          */
    /***************************************************************/

    if (document.getElementById('development-phases-chart')) {
        // Generate complex development timeline data
        const generateComplexTimelineData = () => {
            // Project teams - reduced number of teams to make it less busy
            const teams = [
                { 
                    name: 'Frontend Team', 
                    color: window.colorMap.warning[400].hex,
                    members: [
                        { id: 1, name: 'Alex Johnson', img: './img/demo/avatars/avatar-a.png' },
                        { id: 2, name: 'Emma Wilson', img: './img/demo/avatars/avatar-b.png' },
                        { id: 3, name: 'Michael Chen', img: './img/demo/avatars/avatar-c.png' }
                    ] 
                },
                { 
                    name: 'Backend Team', 
                    color: window.colorMap.success[400].hex,
                    members: [
                        { id: 4, name: 'Sarah Williams', img: './img/demo/avatars/avatar-d.png' },
                        { id: 5, name: 'James Lee', img: './img/demo/avatars/avatar-e.png' }
                    ] 
                },
                { 
                    name: 'Database Team', 
                    color: window.colorMap.info[400].hex,
                    members: [
                        { id: 6, name: 'Olivia Smith', img: './img/demo/avatars/avatar-f.png' },
                        { id: 7, name: 'David Rodriguez', img: './img/demo/avatars/avatar-g.png' }
                    ] 
                },
                { 
                    name: 'QA Team', 
                    color: window.colorMap.danger[100].hex,
                    members: [
                        { id: 8, name: 'Sophia Martinez', img: './img/demo/avatars/avatar-h.png' },
                        { id: 9, name: 'Oliver Wilson', img: './img/demo/avatars/avatar-i.png' }
                    ] 
                },
                { 
                    name: 'DevOps Team', 
                    color: window.colorMap.primary[200].hex,
                    members: [
                        { id: 10, name: 'Jessica Brown', img: './img/demo/avatars/avatar-j.png' },
                        { id: 11, name: 'Robert Taylor', img: './img/demo/avatars/avatar-k.png' }
                    ] 
                }
            ];

            // Project Phases (Broader categories) - simplified with fewer activities and expanded over 6 months
            const phases = [
                { name: 'Research & Planning', startDate: '2025-01-15', activities: [
                    { name: 'Market Research', duration: [10, 15] },
                    { name: 'Requirement Analysis', duration: [12, 20] },
                    { name: 'Feasibility Study', duration: [8, 14] }
                ]},
                { name: 'Design', startDate: '2025-02-10', activities: [
                    { name: 'Information Architecture', duration: [15, 20] },
                    { name: 'Wireframing', duration: [14, 21] },
                    { name: 'UI Design', duration: [18, 30] }
                ]},
                { name: 'Development', startDate: '2025-03-05', activities: [
                    { name: 'Core Architecture', duration: [20, 30] },
                    { name: 'Frontend Framework', duration: [25, 40] },
                    { name: 'Backend API Development', duration: [30, 45] },
                    { name: 'Database Schema', duration: [15, 25] }
                ]},
                { name: 'Testing', startDate: '2025-04-20', activities: [
                    { name: 'Unit Testing', duration: [20, 30] },
                    { name: 'Integration Testing', duration: [15, 25] },
                    { name: 'User Acceptance Testing', duration: [15, 25] }
                ]},
                { name: 'Deployment', startDate: '2025-05-25', activities: [
                    { name: 'Staging Environment Setup', duration: [10, 15] },
                    { name: 'Production Environment Prep', duration: [12, 18] },
                    { name: 'Data Migration', duration: [10, 20] }
                ]},
                { name: 'Post-Launch', startDate: '2025-06-20', activities: [
                    { name: 'Monitoring', duration: [14, 21] },
                    { name: 'Bug Fixes', duration: [10, 30] },
                    { name: 'Performance Optimization', duration: [15, 25] }
                ]}
            ];

            // Complex data generation - ensure most tasks have at least 3 team members
            const complexData = [];
            let taskId = 1;
            
            phases.forEach(phase => {
                const phaseStartDate = new Date(phase.startDate);
                let currentStartDate = new Date(phaseStartDate);
                
                phase.activities.forEach(activity => {
                    // Assign different teams to different activities with some randomness
                    teams.forEach(team => {
                        // Increased probability to ~50% to reduce number of entries
                        if (Math.random() > 0.5) {
                            // Calculate a random start date within a reasonable range from the current date
                            const randomStartOffset = Math.floor(Math.random() * 5); // 0-4 days offset (slightly increased for more spread)
                            const startDate = new Date(currentStartDate);
                            startDate.setDate(startDate.getDate() + randomStartOffset);
                            
                            // Calculate duration (days) within the specified range
                            const minDuration = activity.duration[0];
                            const maxDuration = activity.duration[1];
                            const actualDuration = Math.floor(Math.random() * (maxDuration - minDuration + 1)) + minDuration;
                            
                            // Calculate end date
                            const endDate = new Date(startDate);
                            endDate.setDate(startDate.getDate() + actualDuration);
                            
                            // Assign team members to the task - ensure at least 3 members when possible
                            const assignedMembers = [];
                            // Determine number of members to assign (3-5 for most tasks)
                            const memberCount = Math.min(team.members.length, Math.floor(Math.random() * 3) + 3); // 3-5 members
                            const availableMembers = [...team.members];
                            
                            // If team has fewer than 3 members, assign all of them
                            if (availableMembers.length <= 3) {
                                assignedMembers.push(...availableMembers);
                            } else {
                                // Otherwise, randomly select memberCount members
                                for (let i = 0; i < memberCount; i++) {
                                    const randomIndex = Math.floor(Math.random() * availableMembers.length);
                                    assignedMembers.push(availableMembers.splice(randomIndex, 1)[0]);
                                }
                            }
                            
                            // Create the task
                            complexData.push({
                                id: taskId++,
                                name: team.name,
                                data: [{
                                    x: `${phase.name}: ${activity.name}`,
                                    y: [startDate.getTime(), endDate.getTime()],
                                    fillColor: team.color,
                                    team: team.name,
                                    phase: phase.name,
                                    activity: activity.name,
                                    duration: actualDuration,
                                    assignedMembers: assignedMembers
                                }]
                            });
                        }
                    });
                    
                    // Move the current start date forward
                    currentStartDate.setDate(currentStartDate.getDate() + Math.floor(Math.random() * 7) + 3); // 3-9 days gap (increased for more spread)
                });
            });
            
            return { complexData, phases, teams };
        };

        const { complexData, phases, teams } = generateComplexTimelineData();
        
        // No longer need these functions for adding images to chart bars
        const dataLabelsFormatter = function(val, opts) {
            // Return empty string - we're not showing images in bars anymore
            return '';
        };
        
        const developmentPhasesOptions = {
            series: complexData,
            chart: {
                height: 600, // Increased height for better visualization
                type: 'rangeBar',
                fontFamily: 'inherit',
                parentHeightOffset: 0,
                animations: {
                    enabled: false // Disable animations for better performance with large dataset
                },
                zoom: {
                    enabled: true,
                    type: 'x',
                    autoScaleYaxis: false,
                    allowMouseWheelZoom: false,
                    zoomedArea: {
                        fill: {
                            color: '#90CAF9',
                            opacity: 0.4
                        },
                        stroke: {
                            color: '#0D47A1',
                            opacity: 0.4,
                            width: 1
                        }
                    }
                },
                toolbar: {
                    show: true,
                    tools: {
                        download: false,
                        selection: false,
                        zoom: false,
                        zoomin: true,
                        zoomout: true,
                        pan: true,
                        reset: true
                    },
                    autoSelected: 'pan'
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    distributed: false,
                    dataLabels: {
                        hideOverflowingLabels: true
                    },
                    rangeBarGroupRows: true
                }
            },
            dataLabels: {
                enabled: false,
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '11px'
                    },
                    datetimeUTC: false,
                    format: 'MMM dd'
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '11px'
                    },
                    maxWidth: 300
                }
            },
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.1),
                strokeDashArray: 3,
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
            tooltip: {
                custom: function(opts) {
                    const data = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
                    const from = new Date(opts.y1).toLocaleDateString();
                    const to = new Date(opts.y2).toLocaleDateString();
                    const duration = Math.floor((opts.y2 - opts.y1) / (1000 * 60 * 60 * 24));
                    
                    // Create team member images HTML for tooltip
                    let assignedMembersHtml = '';
                    if (data.assignedMembers && data.assignedMembers.length > 0) {
                        // Get the first 4 members to display
                        const displayMembers = data.assignedMembers.slice(0, 4);
                        // Calculate remaining members count for +X label
                        const remainingCount = Math.max(0, data.assignedMembers.length - 4);
                        
                        assignedMembersHtml = `
                            <div class="mt-2 fs-xs mb-1 fw-500">Assigned Members:</div>
                            <div class="fs-sm d-flex align-items-center mt-2">
                                ${displayMembers.map(member => `
                                    <span class="profile-image-md ms-1 rounded-circle d-inline-block" 
                                          style="background-image:url('${member.img}'); background-size: cover;"></span>
                                `).join('')}
                                ${remainingCount > 0 ? `
                                    <div data-hasmore="+${remainingCount}" class="rounded-circle profile-image-md ms-1">
                                        <span class="profile-image-md ms-1 rounded-circle d-inline-block" 
                                              style="background-image:url('${data.assignedMembers[4].img}'); background-size: cover;"></span>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }
                    
                    return (
                        '<div class="arrow_box p-2">' +
                        '<div class="fw-bold mb-1">' + data.team + '</div>' +
                        '<div>' + data.activity + '</div>' +
                        '<div><small class="text-danger">Phase: ' + data.phase + '</small></div>' +
                        '<div class="mt-2"><small>Start: ' + from + '</small></div>' +
                        '<div><small>End: ' + to + '</small></div>' +
                        '<div><small>Duration: <span class="fw-bold">' + duration + ' days</span></small></div>' +
                        assignedMembersHtml +
                        '</div>'
                    );
                }
            },
            legend: {
                show: false,
            },
            // Advanced features
            annotations: {
                xaxis: [
                    {
                        x: new Date('2025-02-15').getTime(),
                        borderColor: window.colorMap.primary[500].hex,
                        strokeDashArray: 0,
                        label: {
                            borderColor: window.colorMap.primary[500].hex,
                            style: {
                                color: '#fff',
                                background: window.colorMap.primary[500].hex
                            },
                            text: 'Design Review'
                        }
                    },
                    {
                        x: new Date('2025-04-15').getTime(),
                        borderColor: window.colorMap.danger[500].hex,
                        strokeDashArray: 0,
                        label: {
                            borderColor: window.colorMap.danger[500].hex,
                            style: {
                                color: '#fff',
                                background: window.colorMap.danger[500].hex
                            },
                            text: 'Critical Milestone'
                        }
                    },
                    {
                        x: new Date('2025-06-15').getTime(),
                        borderColor: window.colorMap.success[500].hex,
                        strokeDashArray: 0,
                        label: {
                            borderColor: window.colorMap.success[500].hex,
                            style: {
                                color: '#fff',
                                background: window.colorMap.success[500].hex
                            },
                            text: 'Release Candidate'
                        }
                    }
                ]
            }
        };

        const developmentPhasesChart = new ApexCharts(
            document.getElementById('development-phases-chart'),
            developmentPhasesOptions
        );

        developmentPhasesChart.render();
        
        // Handler for team filter buttons
        document.querySelectorAll('.development-phases-filters [data-filter]').forEach(button => {
            button.addEventListener('click', function() {
                // Update button states
                document.querySelectorAll('.development-phases-filters [data-filter]').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                let filteredData;
                
                if (filter === 'all') {
                    filteredData = complexData;
                } else {
                    filteredData = complexData.filter(series => series.name === filter);
                }
                
                // Update the chart
                developmentPhasesChart.updateSeries(filteredData);
            });
        });
    }

    /***************************************************************/
    /* Project Progress Chart #project-progress-chart              */
    /***************************************************************/

    if (document.getElementById('project-progress-chart')) {
        const categories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'];
        const plannedData = [42, 53, 66, 78, 86, 95, 100];
        const actualData = [38, 45, 55, 72, 80, 84, 92];

        const projectProgressChartOptions = {
            series: [
                {
                    name: 'Planned Progress',
                    type: 'line',
                    data: plannedData
                },
                {
                    name: 'Actual Progress',
                    type: 'area',
                    data: actualData
                }
            ],
            chart: {
                height: 350,
                type: 'line',
                toolbar: {
                    show: false
                },
                fontFamily: 'inherit',
                parentHeightOffset: 0
            },
            colors: [
                window.colorMap.primary[500].hex, // Planned (blue line)
                window.colorMap.success[400].hex  // Actual (green area)
            ],
            stroke: {
                width: [3, 2],
                curve: 'smooth',
                dashArray: [0, 0]
            },
            fill: {
                type: ['solid', 'gradient'],
                opacity: [1, 0.8],
                gradient: {
                    shade: 'dark',
                    type: 'vertical',
                    shadeIntensity: 0.5,
                    gradientToColors: [window.colorMap.success[400].hex], // End color (top)
                    opacityFrom: 0.8, // Higher opacity at bottom
                    opacityTo: 0.2,   // Lower opacity at top
                    stops: [0, 90, 100],
                    colorStops: [
                        {
                            offset: 0,
                            color: window.colorMap.success[100].hex || '#333333', // Dark color at bottom
                            opacity: 0.8
                        },
                        {
                            offset: 90,
                            color: window.colorMap.success[400].hex,  // Line color in middle
                            opacity: 0.2
                        }
                    ]
                }
            },
            markers: {
                size: [4, 4],
                colors: [window.colorMap.primary[500].hex, window.colorMap.success[400].hex],
                strokeColors: ['#fff', '#fff'],
                strokeWidth: 2,
                hover: {
                    size: 7
                }
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
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
                min: 0,
                max: 100,
                title: {
                    text: 'Completion %',
                    style: {
                        fontWeight: 400
                    }
                },
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
                    },
                    formatter: function (val) {
                        return val + '%';
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '14px',
                markers: {
                    width: 10,
                    height: 10,
                    radius: 0
                },
                itemMargin: {
                    horizontal: 15,
                    vertical: 5
                }
            },
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.1),
                strokeDashArray: 3
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val) {
                        return val + '%';
                    }
                }
            }
        };

        const projectProgressChart = new ApexCharts(
            document.getElementById('project-progress-chart'),
            projectProgressChartOptions
        );
        projectProgressChart.render();
    }

    /***************************************************************/
    /* Task Status Chart #task-status-chart                        */
    /***************************************************************/

    if (document.getElementById('task-status-chart')) {
        const taskStatusChartOptions = {
            series: [35, 20, 15, 30],
            chart: {
                type: 'donut',
                height: 350,
                fontFamily: 'inherit',
                parentHeightOffset: 0
            },
            colors: [
                window.colorMap.success[400].hex,  // Completed
                window.colorMap.warning[400].hex,  // In Progress
                window.colorMap.danger[400].hex,   // Overdue
                window.colorMap.primary[400].hex   // Not Started
            ],
            labels: ['Completed', 'In Progress', 'Overdue', 'Not Started'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '14px',
                                fontWeight: 600,
                                offsetY: -10
                            },
                            value: {
                                show: true,
                                fontSize: '24px',
                                fontWeight: 400,
                                formatter: function (val) {
                                    return val + '%';
                                }
                            },
                            total: {
                                show: true,
                                label: 'Total Tasks',
                                fontSize: '16px',
                                fontWeight: 600,
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0) + ' Tasks';
                                }
                            }
                        }
                    }
                }
            },
            legend: {
                position: 'bottom',
                fontSize: '14px',
                markers: {
                    width: 12,
                    height: 12,
                    radius: 0
                },
                itemMargin: {
                    horizontal: 10,
                    vertical: 5
                }
            },
            tooltip: {
                enabled: true,
                y: {
                    formatter: function (val) {
                        return val + '%';
                    }
                }
            }
        };

        const taskStatusChart = new ApexCharts(
            document.getElementById('task-status-chart'),
            taskStatusChartOptions
        );
        taskStatusChart.render();
    }

    /***************************************************************/
    /* Team Performance Chart #team-performance-chart              */
    /***************************************************************/

    if (document.getElementById('team-performance-chart')) {
        const categories = ['Work Quality', 'Productivity', 'Communication', 'Problem Solving', 'Task Completion', 'Innovation', 'Collaboration'];
        
        const teamPerformanceChartOptions = {
            series: [
                {
                    name: 'Team Performance',
                    data: [85, 75, 90, 70, 80, 65, 95]
                }
            ],
            chart: {
                height: 350,
                type: 'radar',
                toolbar: {
                    show: false
                },
                fontFamily: 'inherit',
                parentHeightOffset: 0
            },
            colors: [window.colorMap.primary[400].hex],
            fill: {
                opacity: 0.4
            },
            markers: {
                size: 5,
                colors: [window.colorMap.primary[400].hex],
                strokeColors: "#fff",
                strokeWidth: 2
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                max: 100,
                min: 0,
                tickAmount: 5,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '10px'
                    }
                }
            },
            plotOptions: {
                radar: {
                    polygons: {
                        strokeColors: window.colorMap.bootstrapVars.bodyColor.rgba(0.1),
                        connectorColors: window.colorMap.bootstrapVars.bodyColor.rgba(0.1)
                    }
                }
            }
        };

        const teamPerformanceChart = new ApexCharts(
            document.getElementById('team-performance-chart'),
            teamPerformanceChartOptions
        );
        teamPerformanceChart.render();
    }

    /***************************************************************/
    /* Weekly Activity Chart #weekly-activity-chart                */
    /***************************************************************/

    if (document.getElementById('weekly-activity-chart')) {
        const categories = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        const completedData = [18, 25, 20, 30, 22, 5, 2];
        const newData = [12, 15, 30, 20, 25, 8, 0];
        
        const weeklyActivityChartOptions = {
            series: [
                {
                    name: 'Completed Tasks',
                    data: completedData
                },
                {
                    name: 'New Tasks',
                    data: newData
                }
            ],
            chart: {
                type: 'bar',
                height: 350,
                stacked: false,
                toolbar: {
                    show: false
                },
                fontFamily: 'inherit',
                parentHeightOffset: 0
            },
            colors: [
                window.colorMap.success[400].hex,  // Completed
                window.colorMap.primary[400].hex   // New
            ],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 3,
                    dataLabels: {
                        total: {
                            enabled: false
                        }
                    }
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                width: 0,
                colors: ['transparent']
            },
            xaxis: {
                categories: categories,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
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
                title: {
                    text: 'Number of Tasks',
                    style: {
                        fontWeight: 400
                    }
                },
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '14px',
                markers: {
                    width: 12,
                    height: 12,
                    radius: 2
                },
                itemMargin: {
                    horizontal: 10,
                    vertical: 5
                }
            },
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.1),
                strokeDashArray: 3
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function (val) {
                        return val + ' Tasks';
                    }
                }
            }
        };

        const weeklyActivityChart = new ApexCharts(
            document.getElementById('weekly-activity-chart'),
            weeklyActivityChartOptions
        );
        weeklyActivityChart.render();
    }

    /***************************************************************/
    /* Team Workload Heatmap #team-workload-heatmap                */
    /***************************************************************/

    if (document.getElementById('team-workload-heatmap')) {
        // Team members
        const teamMembers = [
            'Alex Johnson',
            'Sarah Williams',
            'Michael Chen',
            'Olivia Smith',
            'David Rodriguez',
            'Emma Wilson',
            'James Lee',
            'Sophia Martinez'
        ];

        // Days of the week
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        // Generate workload data (hours spent on tasks per day)
        // Each cell represents hours spent on tasks by a team member on a specific day
        function generateData(count, min, max) {
            const data = [];
            for (let i = 0; i < count; i++) {
                const teamMemberData = [];
                for (let j = 0; j < days.length; j++) {
                    const value = Math.floor(Math.random() * (max - min + 1)) + min;
                    teamMemberData.push({
                        x: days[j],
                        y: value
                    });
                }
                data.push({
                    name: teamMembers[i],
                    data: teamMemberData
                });
            }
            return data;
        }

        const workloadData = generateData(teamMembers.length, 0, 10);

        const teamWorkloadHeatmapOptions = {
            series: workloadData,
            chart: {
                height: 370,
                type: 'heatmap',
                toolbar: {
                    show: false
                },
                fontFamily: 'inherit',
                parentHeightOffset: 0
            },
            dataLabels: {
                enabled: false
            },
            colors: [window.colorMap.primary[500].hex],
            tooltip: {
                enabled: true,
                y: {
                    formatter: function(val) {
                        return val + " hours";
                    }
                }
            },
            plotOptions: {
                heatmap: {
                    shadeIntensity: 0.5,
                    radius: 0,
                    useFillColorAsStroke: false,
                    colorScale: {
                        ranges: [
                            {
                                from: 0,
                                to: 2,
                                name: 'Low',
                                color: window.colorMap.success[300].hex
                            },
                            {
                                from: 3,
                                to: 5,
                                name: 'Medium',
                                color: window.colorMap.primary[300].hex
                            },
                            {
                                from: 6,
                                to: 8,
                                name: 'High',
                                color: window.colorMap.warning[400].hex
                            },
                            {
                                from: 9,
                                to: 10,
                                name: 'Critical',
                                color: window.colorMap.danger[400].hex
                            }
                        ]
                    }
                }
            },
            xaxis: {
                categories: days,
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'center',
                fontSize: '14px',
                markers: {
                    radius: 0
                },
                itemMargin: {
                    horizontal: 15,
                    vertical: 5
                }
            },
            stroke: {
                width: 1,
                colors: [window.colorMap.bootstrapVars.bodyColor.rgba(0.05)]
            }
        };

        const teamWorkloadHeatmap = new ApexCharts(
            document.getElementById('team-workload-heatmap'),
            teamWorkloadHeatmapOptions
        );
        teamWorkloadHeatmap.render();

        // Event listeners for the filter buttons
        document.querySelectorAll('.team-workload-filters [data-filter]').forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');
                
                // Remove active class from all buttons
                document.querySelectorAll('.team-workload-filters [data-filter]').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Generate new data based on filter
                let newData;
                if (filter === 'week') {
                    newData = generateData(teamMembers.length, 0, 10);
                } else if (filter === 'month') {
                    newData = generateData(teamMembers.length, 10, 40); // Higher values for monthly view
                }
                
                // Update chart
                teamWorkloadHeatmap.updateSeries(newData);
            });
        });
    }

    /***************************************************************/
    /* Project Timeline Chart #project-timeline-chart              */
    /***************************************************************/

    if (document.getElementById('project-timeline-chart')) {
        const projectTimelineOptions = {
            series: [
                {
                    name: 'Frontend',
                    data: [
                        {
                            x: 'Design',
                            y: [
                                new Date('2023-03-05').getTime(),
                                new Date('2023-03-15').getTime()
                            ]
                        },
                        {
                            x: 'Testing',
                            y: [
                                new Date('2023-03-25').getTime(),
                                new Date('2023-04-05').getTime()
                            ]
                        },
                        {
                            x: 'Integration Testing',
                            y: [
                                new Date('2023-04-05').getTime(),
                                new Date('2023-04-20').getTime()
                            ]
                        }
                    ]
                },
                {
                    name: 'Backend',
                    data: [
                        {
                            x: 'Development',
                            y: [
                                new Date('2023-03-10').getTime(),
                                new Date('2023-03-30').getTime()
                            ]
                        },
                        {
                            x: 'API Design',
                            y: [
                                new Date('2023-03-08').getTime(),
                                new Date('2023-03-20').getTime()
                            ]
                        },
                        {
                            x: 'Implementation',
                            y: [
                                new Date('2023-03-20').getTime(),
                                new Date('2023-04-05').getTime()
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
                                new Date('2023-03-10').getTime(),
                                new Date('2023-03-25').getTime()
                            ]
                        },
                        {
                            x: 'Data Migration',
                            y: [
                                new Date('2023-03-25').getTime(),
                                new Date('2023-04-10').getTime()
                            ]
                        },
                        {
                            x: 'Performance Testing',
                            y: [
                                new Date('2023-04-10').getTime(),
                                new Date('2023-04-25').getTime()
                            ]
                        }
                    ]
                }
            ],
            chart: {
                height: 370,
                type: 'rangeBar',
                fontFamily: 'inherit',
                parentHeightOffset: 0,
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false,
                }
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '50%',
                    rangeBarGroupRows: true
                }
            },
            colors: [
                window.colorMap.primary[100].hex,  // Frontend
                window.colorMap.primary[300].hex,  // Backend
                window.colorMap.primary[600].hex   // Database
            ],
            fill: {
                type: 'solid'
            },
            xaxis: {
                type: 'datetime',
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: window.colorMap.bootstrapVars.bodyColor.hex,
                        fontSize: '12px'
                    }
                }
            },
            grid: {
                borderColor: window.colorMap.bootstrapVars.bodyColor.rgba(0.1),
                strokeDashArray: 3,
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
            tooltip: {
                custom: function(opts) {
                    const from = new Date(opts.y1).toLocaleDateString();
                    const to = new Date(opts.y2).toLocaleDateString();
                    const duration = Math.floor((opts.y2 - opts.y1) / (1000 * 60 * 60 * 24));
                    const seriesName = opts.w.globals.seriesNames[opts.seriesIndex];
                    const taskName = opts.w.globals.labels[opts.dataPointIndex];

                    return (
                        '<div class="arrow_box p-2">' +
                        '<div class="font-weight-bold mb-1">' + seriesName + ': ' + taskName + '</div>' +
                        '<div>Start: ' + from + '</div>' +
                        '<div>End: ' + to + '</div>' +
                        '<div>Duration: ' + duration + ' days</div>' +
                        '</div>'
                    );
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '14px',
                markers: {
                    width: 12,
                    height: 12,
                    radius: 0
                },
                itemMargin: {
                    horizontal: 10,
                    vertical: 5
                }
            }
        };

        const projectTimelineChart = new ApexCharts(
            document.getElementById('project-timeline-chart'),
            projectTimelineOptions
        );
        projectTimelineChart.render();

        // Handle view toggle buttons
        const timelineViewButtons = document.querySelectorAll('.project-timeline-filters [data-timeline-view]');
        timelineViewButtons.forEach(button => {
            button.addEventListener('click', function() {
                timelineViewButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                const view = this.getAttribute('data-timeline-view');
                if (view === 'phases') {
                    // Show simplified view with only project phases
                    projectTimelineChart.updateSeries([
                        {
                            name: 'Project Phases',
                            data: [
                                {
                                    x: 'Research',
                                    y: [
                                        new Date('2023-03-01').getTime(),
                                        new Date('2023-03-05').getTime()
                                    ],
                                    fillColor: '#4FC3F7'
                                },
                                {
                                    x: 'Design',
                                    y: [
                                        new Date('2023-03-06').getTime(),
                                        new Date('2023-03-10').getTime()
                                    ],
                                    fillColor: '#4DB6AC'
                                },
                                {
                                    x: 'Development',
                                    y: [
                                        new Date('2023-03-11').getTime(),
                                        new Date('2023-03-25').getTime()
                                    ],
                                    fillColor: '#FFB74D'
                                },
                                {
                                    x: 'Testing',
                                    y: [
                                        new Date('2023-03-26').getTime(),
                                        new Date('2023-04-10').getTime()
                                    ],
                                    fillColor: '#F06292'
                                },
                                {
                                    x: 'Deployment',
                                    y: [
                                        new Date('2023-04-11').getTime(),
                                        new Date('2023-04-25').getTime()
                                    ],
                                    fillColor: '#9575CD'
                                }
                            ]
                        }
                    ]);
                } else {
                    // Restore detailed view
                    projectTimelineChart.updateSeries(projectTimelineOptions.series);
                }
            });
        });
    }

});
