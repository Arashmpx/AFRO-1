document.addEventListener('DOMContentLoaded', function() {
    const commissionChartCanvas = document.getElementById('commission-chart');

    if (commissionChartCanvas && typeof affiliateDashboard !== 'undefined') {
        const formData = new URLSearchParams();
        formData.append('action', 'get_affiliate_chart_data');
        formData.append('nonce', affiliateDashboard.nonce);

        fetch(affiliateDashboard.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                const chartData = {
                    labels: response.data.labels,
                    datasets: [{
                        label: 'Paid Commission Earnings',
                        data: response.data.data,
                        fill: false,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                };

                new Chart(commissionChartCanvas, {
                    type: 'line',
                    data: chartData,
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            } else {
                console.error('Failed to load chart data:', response.data);
            }
        })
        .catch(error => {
            console.error('Error fetching chart data:', error);
        });
    }
});
