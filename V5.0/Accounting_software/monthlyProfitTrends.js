// monthlyProfitTrends.js

document.addEventListener("DOMContentLoaded", function() {
    function plotMonthlyProfitTrends() {
        console.log("plotMonthlyProfitTrends() invoked.");
        
        const chartContainer = document.getElementById("chartContainer");
        const canvas = document.getElementById("chartCanvas");
        
        if (!chartContainer || !canvas) {
            console.error("Chart container or canvas element not found.");
            return;
        }
        
        // Ensure the chart container is visible
        chartContainer.style.display = "block";
        
        // Fetch monthly profit data from the PHP endpoint
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
                    chartContainer.innerHTML = "<p>No profit data available.</p>";
                    return;
                }
                
                // Process data to extract labels and profit values
                const labels = data.map(item => item.month);
                const profits = data.map(item => parseFloat(item.total_profit));
                
                // Destroy any existing chart instance (if using Chart.js v3+)
                if (window.activeProfitChart) {
                    window.activeProfitChart.destroy();
                }
                
                // Create a new Chart.js instance
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
                            fill: false,           // No shading under the line
                            pointRadius: 4,        // Show markers
                            pointHoverRadius: 6,   // Larger markers on hover
                            tension: 0.2           // Slight curvature
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
                                title: {
                                    display: true,
                                    text: "Profit (INR)"
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: "Month"
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error("Error fetching monthly profit data:", error);
            });
    }
    
    // Expose the function globally so that it can be called from your handleAction switch-case
    window.plotMonthlyProfitTrends = plotMonthlyProfitTrends;
});
