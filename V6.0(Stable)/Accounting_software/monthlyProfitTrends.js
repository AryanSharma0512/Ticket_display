document.addEventListener("DOMContentLoaded", function() {
    function plotMonthlyProfitTrends() {
        console.log("plotMonthlyProfitTrends() invoked.");

        const outputContainer = document.getElementById("outputContainer");
        const chartContainer = document.getElementById("chartContainer");
        const canvas = document.getElementById("chartCanvas");

        if (!outputContainer || !chartContainer || !canvas) {
            console.error("Output container, chart container, or canvas element not found.");
            return;
        }

        // Clear only text output.
        outputContainer.innerHTML = '';

        // Make sure the chart container is visible.
        chartContainer.style.display = "block";

        fetch("fetch_monthly_profit.php")
            .then(response => {
                if (!response.ok) {
                    throw new Error("Network response was not ok.");
                }
                return response.json();
            })
            .then(data => {
                console.log("Monthly profit data fetched:", data);

                if (!Array.isArray(data) || data.length === 0) {
                    outputContainer.innerHTML = "<p>No profit data available.</p>";
                    return;
                }

                const labels = data.map(item => item.month);
                const profits = data.map(item => parseFloat(item.total_profit));

                if (window.activeProfitChart) {
                    window.activeProfitChart.destroy();
                }

                const ctx = canvas.getContext("2d");
                window.activeProfitChart = new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: labels,
                        datasets: [{
                            label: "Profit (INR)",
                            data: profits,
                            borderColor: "rgba(75, 192, 192, 1)",
                            borderWidth: 2,
                            fill: false,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: 0.2
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: "Monthly Profit Trends"
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: "Profit (INR)" }
                            },
                            x: {
                                title: { display: true, text: "Month" }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error("Error fetching monthly profit data:", error);
            });
    }

    window.plotMonthlyProfitTrends = plotMonthlyProfitTrends;
});
