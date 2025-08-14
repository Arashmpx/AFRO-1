jQuery(document).ready(function($) {
    'use strict';

    // Payout Request Form Submission
    $('#wal-request-payout-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var amount = form.find('#payout_amount').val();
        var method = form.find('#payout_method').val();
        var messageDiv = $('#wal-payout-message');

        messageDiv.text('Processing...').show();

        $.ajax({
            type: 'POST',
            url: wal_public_ajax.ajax_url,
            data: {
                action: 'wal_request_payout',
                nonce: wal_public_ajax.nonce,
                amount: amount,
                method: method
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.text(response.data.message).css('color', 'green');
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    messageDiv.text('Error: ' + response.data.message).css('color', 'red');
                }
            },
            error: function() {
                messageDiv.text('An unknown error occurred.').css('color', 'red');
            }
        });
    });

    // Earnings Chart
    var earningsChartCanvas = document.getElementById('walEarningsChart');
    if (earningsChartCanvas) {
        $.ajax({
            type: 'GET',
            url: wal_public_ajax.ajax_url,
            data: {
                action: 'wal_get_earnings_chart_data',
                nonce: wal_public_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    new Chart(earningsChartCanvas, {
                        type: 'line',
                        data: {
                            labels: response.data.labels,
                            datasets: [{
                                label: 'Earnings',
                                data: response.data.data,
                                fill: false,
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            }
        });
    }

    // Genealogy Tree
    var genealogyTreeContainer = document.getElementById('wal-genealogy-tree');
    if (genealogyTreeContainer) {
        $.ajax({
            type: 'GET',
            url: wal_public_ajax.ajax_url,
            data: {
                action: 'wal_get_genealogy_data',
                nonce: wal_public_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const width = genealogyTreeContainer.clientWidth;
                    const height = 500;

                    const simulation = d3.forceSimulation(response.data.nodes)
                        .force("link", d3.forceLink(response.data.links).id(d => d.id).distance(100))
                        .force("charge", d3.forceManyBody().strength(-150))
                        .force("center", d3.forceCenter(width / 2, height / 2));

                    const svg = d3.select(genealogyTreeContainer).append("svg")
                        .attr("width", width)
                        .attr("height", height)
                        .attr("viewBox", [0, 0, width, height])
                        .attr("style", "max-width: 100%; height: auto;");

                    const link = svg.append("g")
                        .attr("stroke", "#999")
                        .attr("stroke-opacity", 0.6)
                        .selectAll("line")
                        .data(response.data.links)
                        .join("line")
                        .attr("stroke-width", d => Math.sqrt(d.value || 1));

                    const node = svg.append("g")
                        .attr("stroke", "#fff")
                        .attr("stroke-width", 1.5)
                        .selectAll("circle")
                        .data(response.data.nodes)
                        .join("circle")
                        .attr("r", 10)
                        .attr("fill", "#0073aa")
                        .call(drag(simulation));

                    node.append("title")
                        .text(d => `${d.name}\n${d.email}`);

                    simulation.on("tick", () => {
                        link
                            .attr("x1", d => d.source.x)
                            .attr("y1", d => d.source.y)
                            .attr("x2", d => d.target.x)
                            .attr("y2", d => d.target.y);

                        node
                            .attr("cx", d => d.x)
                            .attr("cy", d => d.y);
                    });

                    function drag(simulation) {
                        function dragstarted(event) {
                            if (!event.active) simulation.alphaTarget(0.3).restart();
                            event.subject.fx = event.subject.x;
                            event.subject.fy = event.subject.y;
                        }
                        function dragged(event) {
                            event.subject.fx = event.x;
                            event.subject.fy = event.y;
                        }
                        function dragended(event) {
                            if (!event.active) simulation.alphaTarget(0);
                            event.subject.fx = null;
                            event.subject.fy = null;
                        }
                        return d3.drag()
                            .on("start", dragstarted)
                            .on("drag", dragged)
                            .on("end", dragended);
                    }
                }
            }
        });
    }
});
