document.addEventListener('DOMContentLoaded', function() {
    const treeContainer = document.getElementById('genealogy-tree-container');

    if (treeContainer && typeof d3 !== 'undefined' && typeof affiliateDashboard !== 'undefined') {
        const width = treeContainer.clientWidth;
        const height = treeContainer.clientHeight;

        const svg = d3.select(treeContainer).append("svg")
            .attr("width", width)
            .attr("height", height)
            .call(d3.zoom().on("zoom", function (event) {
                g.attr("transform", event.transform);
            }))
            .append("g");

        const g = svg.append("g");

        const formData = new URLSearchParams();
        formData.append('action', 'get_genealogy_data');
        formData.append('nonce', affiliateDashboard.nonce);

        fetch(affiliateDashboard.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(response => {
            if (response.success) {
                renderTree(response.data);
            } else {
                console.error('Failed to load genealogy data:', response.data);
                g.append("text").attr("x", 10).attr("y", 25).text("Error loading data.");
            }
        })
        .catch(error => {
            console.error('Error fetching genealogy data:', error);
            g.append("text").attr("x", 10).attr("y", 25).text("Error fetching data.");
        });

        function renderTree(treeData) {
            const root = d3.hierarchy(treeData);
            const treeLayout = d3.tree().size([height, width - 200]);
            treeLayout(root);

            // Links
            g.selectAll('line')
                .data(root.links())
                .enter()
                .append('line')
                .attr('x1', d => d.source.y + 50)
                .attr('y1', d => d.source.x)
                .attr('x2', d => d.target.y + 50)
                .attr('y2', d => d.target.x)
                .style('stroke', '#ccc');

            // Nodes
            const node = g.selectAll('g.node')
                .data(root.descendants())
                .enter()
                .append('g')
                .attr('class', 'node')
                .attr('transform', d => `translate(${d.y + 50},${d.x})`);

            node.append('circle')
                .attr('r', 7)
                .style('fill', '#fff')
                .style('stroke', 'steelblue')
                .style('stroke-width', '3px');

            node.append('text')
                .attr('dy', '.35em')
                .attr('x', d => d.children ? -13 : 13)
                .style('text-anchor', d => d.children ? 'end' : 'start')
                .text(d => d.data.name);
        }
    }
});
