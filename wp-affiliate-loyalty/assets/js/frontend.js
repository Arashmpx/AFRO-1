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

    const redeemBtn = document.getElementById('redeem-for-coupon-btn');
    const couponResultDiv = document.getElementById('coupon-result');

    if (redeemBtn && couponResultDiv && typeof affiliateDashboard !== 'undefined') {
        redeemBtn.addEventListener('click', function(e) {
            e.preventDefault();
            redeemBtn.disabled = true;
            couponResultDiv.innerHTML = 'Generating your coupon...';

            const formData = new URLSearchParams();
            formData.append('action', 'redeem_points_for_coupon');
            formData.append('nonce', affiliateDashboard.nonce);

            fetch(affiliateDashboard.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    couponResultDiv.innerHTML = `<p style="color: green;">${response.data.message}</p><p>Your coupon code is: <strong>${response.data.coupon_code}</strong></p>`;
                    redeemBtn.style.display = 'none'; // Hide button after successful redemption
                } else {
                    couponResultDiv.innerHTML = `<p style="color: red;">Error: ${response.data.message}</p>`;
                    redeemBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error redeeming coupon:', error);
                couponResultDiv.innerHTML = `<p style="color: red;">An unexpected error occurred.</p>`;
                redeemBtn.disabled = false;
            });
        });
    }
});
