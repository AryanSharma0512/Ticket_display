document.addEventListener("DOMContentLoaded", function() {
    function plotRevenueProfitTrends() {
        console.log("plotRevenueProfitTrends() invoked (Dual Axis with Indian formatting).");

        const outputContainer = document.getElementById("outputContainer");
        const chartContainer = document.getElementById("chartContainer");
        const canvas = document.getElementById("chartCanvas");

        if (!outputContainer || !chartContainer || !canvas) {
            console.error("Output container, chart container, or canvas element not found.");
            return;
        }

        // Clear only the text output area; do not remove or move the chart container.
        outputContainer.innerHTML = '';

        // Make sure the chart container is visible.
        chartContainer.style.display = "block";

        // Now proceed to fetch data and render the chart as before:
        fetch("fetch_monthly_revenue_profit.php")
            .then(response => {
                if (!response.ok) {
                    throw new Error("Network response was not ok.");
                }
                return response.json();
            })
            .then(data => {
                console.log("Revenue/Profit data fetched:", data);

                if (!Array.isArray(data) || data.length === 0) {
                    outputContainer.innerHTML = "<p>No revenue/profit data available.</p>";
                    return;
                }

                // Extract labels, revenue, and profit values
                const labels = data.map(item => item.month);
                const revenues = data.map(item => parseFloat(item.total_sales));
                const profits = data.map(item => parseFloat(item.total_profit));
                const margins = data.map((item, index) => {
                    const rev = revenues[index];
                    const prof = profits[index];
                    return rev > 0 ? ((prof / rev) * 100).toFixed(2) : "0.00";
                });

                // Destroy any existing chart instance if it exists
                if (window.activeRevenueProfitChart) {
                    window.activeRevenueProfitChart.destroy();
                }

                const ctx = canvas.getContext("2d");
                window.activeRevenueProfitChart = new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                type: "bar",
                                label: "Revenue (INR)",
                                data: revenues,
                                backgroundColor: "rgba(54, 162, 235, 0.5)",
                                borderColor: "rgba(54, 162, 235, 1)",
                                borderWidth: 1,
                                yAxisID: "y1"
                            },
                            {
                                type: "line",
                                label: "Profit Margin (%)",
                                data: margins,
                                borderColor: "rgba(255, 99, 132, 1)",
                                backgroundColor: "rgba(255, 99, 132, 0)",
                                borderWidth: 2,
                                pointRadius: 4,
                                yAxisID: "y2",
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: "Monthly Revenue & Profit Margin Trends (Dual Axis)"
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        if (context.dataset.label === "Profit Margin (%)") {
                                            const index = context.dataIndex;
                                            const margin = margins[index];
                                            const profit = profits[index];
                                            return "Profit Margin: " + margin + "% (Profit: INR " +
                                                new Intl.NumberFormat('en-IN').format(profit) + ")";
                                        } else {
                                            return context.dataset.label + ": INR " +
                                                new Intl.NumberFormat('en-IN').format(context.parsed.y);
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            y1: {
                                type: "linear",
                                position: "left",
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: "Revenue (INR)"
                                },
                                ticks: {
                                    callback: function (value) {
                                        return new Intl.NumberFormat('en-IN').format(value);
                                    }
                                }
                            },
                            y2: {
                                type: "linear",
                                position: "right",
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: "Profit Margin (%)"
                                },
                                ticks: {
                                    color: "red",
                                    font: { weight: "bold" },
                                    callback: function (value) {
                                        return new Intl.NumberFormat('en-IN').format(value);
                                    }
                                },
                                grid: { drawOnChartArea: false }
                            },
                            x: {
                                title: { display: true, text: "Month" }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error("Error fetching revenue/profit data:", error);
            });
    }

    // Expose the function globally so that it can be called from your handleAction switch-case
    window.plotRevenueProfitTrends = plotRevenueProfitTrends;
});
