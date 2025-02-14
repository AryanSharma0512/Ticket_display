function fetchBackendAmountDues() {
    const chartContainer = document.getElementById("chartContainer");
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
            document.getElementById("outputContainer").innerHTML = `<p>${data.error}</p>`;
            return;
        }

        const labels = data.map(row => row.account_holder);
        const dataset = data.map(row => parseFloat(row.amount_due));

        if (activeChart) {
            activeChart.destroy();
        }

        const ctx = document.getElementById("chartCanvas").getContext("2d");
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
        document.getElementById("outputContainer").innerHTML = `<p>Error fetching data: ${error.message}</p>`;
    });
}
