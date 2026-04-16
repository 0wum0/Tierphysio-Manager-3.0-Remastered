import { SmartTables } from '../optional/smartTables/smartTables.bundle.js';

document.addEventListener('DOMContentLoaded', () => {
    // Define column definitions
    let columnDefs = [
        { data: "id", title: "ID" },
        { data: "name", title: "Name" },
        { data: "age", title: "Age" },
        {
            data: "salary",
            title: "Salary",
            render: function(value) {
                let salary = parseFloat(value);
                if (isNaN(salary)) return "$0.00";
                return "$" + salary.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        },
        {
            data: "bonus",
            title: "Bonus",
            render: function(value) {
                let bonus = parseFloat(value) || 0;
                
                let formattedBonus = "$" + bonus.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                // Check if bonus is less than $3,000
                let shouldBeRed = bonus < 3000;
                
                return shouldBeRed ? 
                    `<span class="text-danger fw-bold">${formattedBonus}</span>` : 
                    formattedBonus;
            }
        },
        { 
            data: "performance", 
            title: "Performance",
            render: function(value) {
                let performance = Math.min(Math.max(parseInt(value), 1), 100);
                let progressClass;
                if (performance < 30) {
                    progressClass = "bg-danger";
                } else if (performance > 70) {
                    progressClass = "bg-success-700";
                } else if (performance >= 50 && performance <= 70) {
                    progressClass = "bg-success-500";
                } else {
                    progressClass = "bg-warning text-dark";
                }
                return '<div class="progress" style="height: 20px;">' +
                       '<div class="progress-bar ' + progressClass + '" ' +
                       'role="progressbar" ' +
                       'data-width="' + performance + '" ' +
                       'aria-valuenow="' + performance + '" ' +
                       'aria-valuemin="0" ' +
                       'aria-valuemax="100" ' +
                       'style="width: ' + performance + '%;">' +
                       performance + '%' +
                       '</div>' +
                       '</div>';
            }
        },
        { data: "department", title: "Department" },
        { data: "hire_date", title: "Hired" },
        {
            data: "overtime_hours",
            title: "Overtime Hrs",
            render: function(value) {
                let hours = parseFloat(value) || 0;
                const maxHours = 50;
                let widthPercent = Math.min((hours / maxHours) * 100, 100);
                let barColor = hours > 40 ? "bg-danger" : 
                             hours > 20 ? "bg-warning text-dark" : 
                             "bg-success";
                return `
                    <div class="progress" style="height: 20px; width: 100%;">
                        <div class="progress-bar ${barColor}" 
                             role="progressbar" 
                             style="width: ${widthPercent}%;" 
                             aria-valuenow="${hours}" 
                             aria-valuemin="0" 
                             aria-valuemax="${maxHours}">
                            ${hours} hrs
                        </div>
                    </div>
                `;
            }
        },
        { data: "projects_completed", title: "Completed" },
        {
            data: "satisfaction_score",
            title: "Score",
            render: function(value) {
                let score = parseFloat(value) || 0;
                score = Math.min(Math.max(score, 0), 10);
                
                let badgeClass, label;
                if (score <= 2) {
                    badgeClass = "bg-danger-300";
                    label = "Poor";
                } else if (score <= 4) {
                    badgeClass = "bg-warning-300";
                    label = "Fair";
                } else if (score <= 6) {
                    badgeClass = "bg-success-400";
                    label = "Average";
                } else if (score <= 8) {
                    badgeClass = "bg-success-500";
                    label = "Good";
                } else {
                    badgeClass = "bg-success-700";
                    label = "Excellent";
                }
                
                return `<span class="badge ${badgeClass}">${label} (${score})</span>`;
            }
        },
        { data: "remote_work_days", title: "Remote Work Days" },
        { data: "training_hours", title: "Training Hrs" },
        { data: "email", title: "Email" },
    ];
    // Initilize tables
    const smartTable = new SmartTables('demo-table', {
        data: {
            type: 'ajax',
            source: 'https://getwebora.com/smartadmin/json//mock-server.php?scenario=success',
            serverSide: true,
            method: 'GET',
            columns: columnDefs
        },
        perPage: 10,
        debug: true,
        responsive: {
            enabled: true,
            breakpoint: 768,
        },
    });

});