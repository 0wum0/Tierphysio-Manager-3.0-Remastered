import { SmartTables } from './../core/smartTables/smartTables.bundle.js';

// Make SmartTables available globally for console debugging
window.SmartTables = SmartTables;

document.addEventListener('DOMContentLoaded', () => {
    // Define some sample data for testing
    const sampleData = [
        {
            PositionData: { PositionID: "16", Position: "LM" },
            PlayerImage: "<img src='img/default-player.png' style='width: 40px; height: 40px; border-radius: 50%;'>",
            PlayerData: {
                Player: "Fousseni Diabaté",
                PlayerRender: "Fousseni Diabaté | 28 Jahre",
                flag: "img/demo/flags/ml.png"
            },
            TeamImage: "<img src='img/default-team.png' style='width: 30px; height: 30px;'>",
            TeamData: { TeamID: "28", TeamRender: "Hamburger SV" },
            OverallData: { OverallNew: "69", OverallOld: "68" },
            PotentialData: { PotentialNew: "69", PotentialOld: "69" }
        },
        {
            PositionData: { PositionID: "3", Position: "ST" },
            PlayerImage: "<img src='img/default-player.png' style='width: 40px; height: 40px; border-radius: 50%;'>",
            PlayerData: {
                Player: "Roberto Piccoli",
                PlayerRender: "Roberto Piccoli | 23 Jahre",
                flag: "img/demo/flags/it.png"
            },
            TeamImage: "<img src='img/default-team.png' style='width: 30px; height: 30px;'>",
            TeamData: { TeamID: "45", TeamRender: "FC Ingolstadt 04" },
            OverallData: { OverallNew: "75", OverallOld: "74" },
            PotentialData: { PotentialNew: "80", PotentialOld: "79" }
        },
        {
            PositionData: { PositionID: "18", Position: "IV" },
            PlayerImage: "<img src='img/default-player.png' style='width: 40px; height: 40px; border-radius: 50%;'>",
            PlayerData: {
                Player: "Clément Akpa",
                PlayerRender: "Clément Akpa | 22 Jahre",
                flag: "img/demo/flags/ci.png"
            },
            TeamImage: "<img src='img/default-team.png' style='width: 30px; height: 30px;'>",
            TeamData: { TeamID: "10", TeamRender: "FC Schalke 04" },
            OverallData: { OverallNew: "73", OverallOld: "75" },
            PotentialData: { PotentialNew: "79", PotentialOld: "81" }
        }
    ];

    // First table - Edited Players
    const editedPlayersTable = new SmartTables('demo-table', {
        data: {
            type: "json",
            source: sampleData,
            columns: [
                {
                    data: "PositionData",
                    title: "Pos.",
                    width: "5%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<div style="background-color: #333; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block; text-align: center; font-weight: bold;">-</div>`;
                        }
                        const positionId = val.PositionID || 0;
                        const position = val.Position || '-';
                        return `<div class="pos-${positionId}" style="background-color: #333; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block; text-align: center; font-weight: bold;">${position}</div>`;
                    }
                },
                {
                    data: "PlayerImage",
                    title: " ",
                    width: "5%",
                    alignment: "center",
                    sortable: false,
                    hideTitleInResponsive: true,
                    render: (val) => val || `<img src="img/default-player.png" style="width: 40px; height: 40px; border-radius: 50%;">`
                },
                {
                    data: "PlayerData",
                    title: "Spieler",
                    width: "32.5%",
                    alignment: "left",
                    render: (val, row) => {
                        if (!val || typeof val !== 'object') {
                            return `<div><div style="font-weight: bold;">-</div></div>`;
                        }
                        const flagImg = val.flag ? `<img src="${val.flag}" style="width: 18px; height: 12px; margin-right: 5px;">` : '';
                        const playerName = val.Player || '-';
                        const playerRender = val.PlayerRender || '';
                        const age = playerRender.includes('|') ? playerRender.split('|')[1] : '';
                        return `<div>
                            <div style="font-weight: bold;">${playerName}</div>
                            <div style="font-size: 0.85em; color: #777;">${flagImg} ${age}</div>
                        </div>`;
                    }
                },
                {
                    data: "TeamImage",
                    title: " ",
                    width: "5%",
                    alignment: "center",
                    sortable: false,
                    hideTitleInResponsive: true,
                    render: (val) => val || `<img src="img/default-team.png" style="width: 30px; height: 30px;">`
                },
                {
                    data: "TeamData",
                    title: "Team",
                    width: "32.5%",
                    alignment: "left",
                    render: (val, row) => {
                        if (!val || typeof val !== 'object') {
                            return `<div style="font-weight: bold;">-</div>`;
                        }
                        const teamRender = val.TeamRender || '-';
                        return `<div style="font-weight: bold;">${teamRender}</div>`;
                    }
                },
                {
                    data: "OverallData",
                    title: "Stärke",
                    width: "10%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<span class="bg-secondary" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">-</span>`;
                        }
                        const overallNew = val.OverallNew || 0;
                        const overallOld = val.OverallOld !== undefined && val.OverallOld !== "" ? val.OverallOld : overallNew;

                        const bgColorClass = getBgColorClass(overallNew);
                        let html = `<span class="${bgColorClass}" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">${overallNew}</span>`;

                        if (overallOld !== overallNew) {
                            const diff = overallNew - overallOld;
                            const color = diff > 0 ? 'green' : 'red';
                            const sign = diff > 0 ? '+' : '';
                            html += `<span style="margin-left: 5px; color: ${color}; font-weight: bold;">${sign}${diff}</span>`;
                        }

                        return html;
                    }
                },
                {
                    data: "PotentialData",
                    title: "Potenzial",
                    width: "10%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<span class="bg-secondary" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">-</span>`;
                        }
                        const potentialNew = val.PotentialNew || 0;
                        const potentialOld = val.PotentialOld !== undefined && val.PotentialOld !== "" ? val.PotentialOld : potentialNew;

                        const bgColorClass = getBgColorClass(potentialNew);
                        let html = `<span class="${bgColorClass}" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">${potentialNew}</span>`;

                        if (potentialOld !== potentialNew) {
                            const diff = potentialNew - potentialOld;
                            const color = diff > 0 ? 'green' : 'red';
                            const sign = diff > 0 ? '+' : '';
                            html += `<span style="margin-left: 5px; color: ${color}; font-weight: bold;">${sign}${diff}</span>`;
                        }

                        return html;
                    }
                }
            ]
        },
        numericColumns: [0, 5, 6],
        responsive: true
    });

    // Add console logging to debug data
    console.log('Sample data:', sampleData);

    // Second table - Deleted Players
    const deletedPlayersTable = new SmartTables('demo-table-2', {
        data: {
            type: "json",
            source: sampleData,
            columns: [
                {
                    data: "PositionData",
                    title: "Pos.",
                    width: "5%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<div style="background-color: #333; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block; text-align: center; font-weight: bold;">-</div>`;
                        }
                        const positionId = val.PositionID || 0;
                        const position = val.Position || '-';
                        return `<div class="pos-${positionId}" style="background-color: #333; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block; text-align: center; font-weight: bold;">${position}</div>`;
                    }
                },
                {
                    data: "PlayerImage",
                    title: " ",
                    width: "5%",
                    alignment: "center",
                    sortable: false,
                    hideTitleInResponsive: true,
                    render: (val) => val || `<img src="img/default-player.png" style="width: 40px; height: 40px; border-radius: 50%;">`
                },
                {
                    data: "PlayerData",
                    title: "Spieler",
                    width: "32.5%",
                    alignment: "left",
                    render: (val, row) => {
                        if (!val || typeof val !== 'object') {
                            return `<div><div style="font-weight: bold;">-</div></div>`;
                        }
                        const flagImg = val.flag ? `<img src="${val.flag}" style="width: 18px; height: 12px; margin-right: 5px;">` : '';
                        const playerName = val.Player || '-';
                        const playerRender = val.PlayerRender || '';
                        const age = playerRender.includes('|') ? playerRender.split('|')[1] : '';
                        return `<div>
                            <div style="font-weight: bold;">${playerName}</div>
                            <div style="font-size: 0.85em; color: #777;">${flagImg} ${age}</div>
                        </div>`;
                    }
                },
                {
                    data: "TeamImage",
                    title: " ",
                    width: "5%",
                    alignment: "center",
                    sortable: false,
                    hideTitleInResponsive: true,
                    render: (val) => val || `<img src="img/default-team.png" style="width: 30px; height: 30px;">`
                },
                {
                    data: "TeamData",
                    title: "Team",
                    width: "32.5%",
                    alignment: "left",
                    render: (val, row) => {
                        if (!val || typeof val !== 'object') {
                            return `<div style="font-weight: bold;">-</div>`;
                        }
                        const teamRender = val.TeamRender || '-';
                        return `<div style="font-weight: bold;">${teamRender}</div>`;
                    }
                },
                {
                    data: "OverallData",
                    title: "Stärke",
                    width: "10%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<span class="bg-secondary" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">-</span>`;
                        }
                        const overallNew = val.OverallNew || 0;
                        const overallOld = val.OverallOld !== undefined && val.OverallOld !== "" ? val.OverallOld : overallNew;

                        const bgColorClass = getBgColorClass(overallNew);
                        let html = `<span class="${bgColorClass}" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">${overallNew}</span>`;

                        if (overallOld !== overallNew) {
                            const diff = overallNew - overallOld;
                            const color = diff > 0 ? 'green' : 'red';
                            const sign = diff > 0 ? '+' : '';
                            html += `<span style="margin-left: 5px; color: ${color}; font-weight: bold;">${sign}${diff}</span>`;
                        }

                        return html;
                    }
                },
                {
                    data: "PotentialData",
                    title: "Potenzial",
                    width: "10%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<span class="bg-secondary" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">-</span>`;
                        }
                        const potentialNew = val.PotentialNew || 0;
                        const potentialOld = val.PotentialOld !== undefined && val.PotentialOld !== "" ? val.PotentialOld : potentialNew;

                        const bgColorClass = getBgColorClass(potentialNew);
                        let html = `<span class="${bgColorClass}" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">${potentialNew}</span>`;

                        if (potentialOld !== potentialNew) {
                            const diff = potentialNew - potentialOld;
                            const color = diff > 0 ? 'green' : 'red';
                            const sign = diff > 0 ? '+' : '';
                            html += `<span style="margin-left: 5px; color: ${color}; font-weight: bold;">${sign}${diff}</span>`;
                        }

                        return html;
                    }
                }
            ],
        },
        numericColumns: [0, 5, 6],
        responsive: true
    });

    // Third table - New Players
    const newPlayersTable = new SmartTables('demo-table-3', {
        data: {
            type: "json",
            source: sampleData,
            columns: [
                {
                    data: "PositionData",
                    title: "Pos.",
                    width: "5%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<div style="background-color: #333; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block; text-align: center; font-weight: bold;">-</div>`;
                        }
                        const positionId = val.PositionID || 0;
                        const position = val.Position || '-';
                        return `<div class="pos-${positionId}" style="background-color: #333; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block; text-align: center; font-weight: bold;">${position}</div>`;
                    }
                },
                {
                    data: "PlayerImage",
                    title: " ",
                    width: "5%",
                    alignment: "center",
                    sortable: false,
                    hideTitleInResponsive: true,
                    render: (val) => val || `<img src="img/default-player.png" style="width: 40px; height: 40px; border-radius: 50%;">`
                },
                {
                    data: "PlayerData",
                    title: "Spieler",
                    width: "32.5%",
                    alignment: "left",
                    render: (val, row) => {
                        if (!val || typeof val !== 'object') {
                            return `<div><div style="font-weight: bold;">-</div></div>`;
                        }
                        const flagImg = val.flag ? `<img src="${val.flag}" style="width: 18px; height: 12px; margin-right: 5px;">` : '';
                        const playerName = val.Player || '-';
                        const playerRender = val.PlayerRender || '';
                        const age = playerRender.includes('|') ? playerRender.split('|')[1] : '';
                        return `<div>
                            <div style="font-weight: bold;">${playerName}</div>
                            <div style="font-size: 0.85em; color: #777;">${flagImg} ${age}</div>
                        </div>`;
                    }
                },
                {
                    data: "TeamImage",
                    title: " ",
                    width: "5%",
                    alignment: "center",
                    sortable: false,
                    hideTitleInResponsive: true,
                    render: (val) => val || `<img src="img/default-team.png" style="width: 30px; height: 30px;">`
                },
                {
                    data: "TeamData",
                    title: "Team",
                    width: "32.5%",
                    alignment: "left",
                    render: (val, row) => {
                        if (!val || typeof val !== 'object') {
                            return `<div style="font-weight: bold;">-</div>`;
                        }
                        const teamRender = val.TeamRender || '-';
                        return `<div style="font-weight: bold;">${teamRender}</div>`;
                    }
                },
                {
                    data: "OverallData",
                    title: "Stärke",
                    width: "10%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<span class="bg-secondary" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">-</span>`;
                        }
                        const overallNew = val.OverallNew || 0;
                        const overallOld = val.OverallOld !== undefined && val.OverallOld !== "" ? val.OverallOld : overallNew;

                        const bgColorClass = getBgColorClass(overallNew);
                        let html = `<span class="${bgColorClass}" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">${overallNew}</span>`;

                        if (overallOld !== overallNew) {
                            const diff = overallNew - overallOld;
                            const color = diff > 0 ? 'green' : 'red';
                            const sign = diff > 0 ? '+' : '';
                            html += `<span style="margin-left: 5px; color: ${color}; font-weight: bold;">${sign}${diff}</span>`;
                        }

                        return html;
                    }
                },
                {
                    data: "PotentialData",
                    title: "Potenzial",
                    width: "10%",
                    alignment: "center",
                    render: (val) => {
                        if (!val || typeof val !== 'object') {
                            return `<span class="bg-secondary" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">-</span>`;
                        }
                        const potentialNew = val.PotentialNew || 0;
                        const potentialOld = val.PotentialOld !== undefined && val.PotentialOld !== "" ? val.PotentialOld : potentialNew;

                        const bgColorClass = getBgColorClass(potentialNew);
                        let html = `<span class="${bgColorClass}" style="display: inline-block; width: 30px; height: 25px; line-height: 25px; text-align: center; color: white; border-radius: 4px; font-weight: bold;">${potentialNew}</span>`;

                        if (potentialOld !== potentialNew) {
                            const diff = potentialNew - potentialOld;
                            const color = diff > 0 ? 'green' : 'red';
                            const sign = diff > 0 ? '+' : '';
                            html += `<span style="margin-left: 5px; color: ${color}; font-weight: bold;">${sign}${diff}</span>`;
                        }

                        return html;
                    }
                }
            ]
        },
        numericColumns: [0, 3, 4],
        responsive: true
    });

    // Helper function to determine background color class based on rating
    function getBgColorClass(rating) {
        rating = parseInt(rating, 10);
        if (isNaN(rating)) return 'bg-secondary';
        if (rating >= 80) return 'bg-success';
        if (rating >= 75) return 'bg-success-700';
        if (rating >= 70) return 'bg-success-500';
        if (rating >= 65) return 'bg-warning';
        if (rating >= 60) return 'bg-warning-700';
        return 'bg-danger';
    }

    // For debugging
    console.log('Tables initialized');

    // Check browser console for any errors
    window.addEventListener('error', function (e) {
        console.log('JavaScript error:', e.message);
    });

    document.addEventListener('shown.bs.tab', function (e) {
        console.log('Tab changed to:', e.target);
        SmartTables.recalculateResponsive();
    });
});