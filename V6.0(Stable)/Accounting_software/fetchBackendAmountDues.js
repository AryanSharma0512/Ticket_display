function fetchBackendAmountDues() {
    const outputContainer = document.getElementById("outputContainer");
    const chartContainer = document.getElementById("chartContainer");
    const canvas = document.getElementById("chartCanvas");

    if (!outputContainer || !chartContainer || !canvas) {
        console.error("Output container, chart container, or canvas element not found.");
        return;
    }

    // Clear only any previous text output.
    outputContainer.innerHTML = '';
    // Simply ensure that the chart container is visible.
    chartContainer.style.display = "block";

    fetch('fetch_backend_amount_dues.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                outputContainer.innerHTML = `<p>${data.error}</p>`;
                return;
            }

            const labels = data.map(row => row.account_holder);
            const dataset = data.map(row => parseFloat(row.amount_due));

            // Destroy any existing chart instance (using global activeChart variable)
            if (activeChart) {
                activeChart.destroy();
            }

            const ctx = canvas.getContext("2d");
            activeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Amount Due (INR)',
                        data: dataset,
                        backgroundColor: "rgba(54, 162, 235, 0.2)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Back End Amount Dues'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₹' + new Intl.NumberFormat('en-IN').format(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Amount Due (INR)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Customer'
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            outputContainer.innerHTML = `<p>Error fetching data: ${error.message}</p>`;
        });
}
