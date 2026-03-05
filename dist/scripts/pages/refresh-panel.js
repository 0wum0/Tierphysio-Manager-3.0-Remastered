// Your inline JavaScript code here
// Refresh panel content
function updatePanelContent(panel) {
    const panelContent = panel.querySelector('.panel-content > .js-refreshed-content');
    const timestamp = new Date().toLocaleString(); // Get current date and time
    panelContent.innerHTML = 'Refreshed content - ' + timestamp;
}
// generate random Graph
function getRandomGraphUrl() {
    var chartConfig = {
        type: 'bar',
        data:
        {
            labels: ['A', 'B', 'C', 'D', 'E', 'F'],
            datasets: [
                {
                    label: 'Random Data',
                    data: [
                        Math.floor(Math.random() * 100),
                        Math.floor(Math.random() * 100),
                        Math.floor(Math.random() * 100),
                        Math.floor(Math.random() * 100),
                        Math.floor(Math.random() * 100),
                        Math.floor(Math.random() * 100)
                    ],
                    backgroundColor: ['rgba(33, 150, 243, 0.5)', // #2196F3
                        'rgba(29, 201, 183, 0.5)', // #1dc9b7
                        'rgba(170, 134, 209, 0.5)', // #a86d1
                        'rgba(255, 194, 65, 0.5)', // #ffc241
                        'rgba(253, 57, 149, 0.5)', // #fd3995
                        'rgba(158, 158, 158, 0.5)' // grey for the 6th bar
                    ],
                    borderColor: ['rgba(33, 150, 243, 1)', // #2196F3
                        'rgba(29, 201, 183, 1)', // #1dc9b7
                        'rgba(170, 134, 209, 1)', // #a86d1
                        'rgba(255, 194, 65, 1)', // #ffc241
                        'rgba(253, 57, 149, 1)', // #fd3995
                        'rgba(158, 158, 158, 1)' // grey for the 6th bar
                    ],
                    borderWidth: 1
                }]
        },
        options:
        {
            responsive: true,
            maintainAspectRatio: false,
            scales:
            {
                y:
                {
                    beginAtZero: true
                }
            }
        }
    };
    var encodedConfig = encodeURIComponent(JSON.stringify(chartConfig));
    return 'https://quickchart.io/chart?c=' + encodedConfig;
}

function loadRandomGraph() {
    var imgElement = document.getElementById('randomGraph');
    imgElement.src = getRandomGraphUrl();
    // add timestamp to the panel
    const panelContent = document.querySelector('.js-last-refresh');
    const timestamp = new Date().toLocaleString(); // Get current date and time
    panelContent.innerHTML = 'Chart updated at <strong>' + timestamp + '</strong>';
}
loadRandomGraph();