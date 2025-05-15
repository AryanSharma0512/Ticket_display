document.addEventListener("DOMContentLoaded", function() {
    function plotCustomerContributionPieChart() {
        console.log("plotCustomerContributionPieChart invoked.");

        // Get required DOM elements.
        const outputContainer = document.getElementById("outputContainer");
        const chartContainer = document.getElementById("chartContainer");
        const canvas = document.getElementById("chartCanvas");

        if (!outputContainer || !chartContainer || !canvas) {
            console.error("Required elements not found.");
            return;
        }

        // Clear text output and ensure the chart container is visible.
        outputContainer.innerHTML = '';
        chartContainer.style.display = "block";

        // Destroy any existing pie chart instance.
        if (window.activeCustomerContributionChart) {
            window.activeCustomerContributionChart.destroy();
        }

        // Fetch customer revenue data.
        fetch("fetch_customer_revenue.php")
            .then(response => {
                if (!response.ok) {
                    throw new Error("Network response was not ok.");
                }
                return response.json();
            })
            .then(data => {
                console.log("Data received for pie chart:", data);
                if (data.error) {
                    outputContainer.innerHTML = `<p>${data.error}</p>`;
                    return;
                }
                if (!Array.isArray(data) || data.length === 0) {
                    outputContainer.innerHTML = "<p>No customer revenue data available.</p>";
                    return;
                }

                // Build the labels and revenues arrays.
                const labels = data.map(item => item.customer_name);
                const revenues = data.map(item => parseFloat(item.total_sales));
                console.log("Labels:", labels);
                console.log("Pie Chart Revenues:", revenues);

                // Create the pie chart.
                const ctx = canvas.getContext("2d");
                window.activeCustomerContributionChart = new Chart(ctx, {
                    type: "pie",
                    data: {
                        labels: labels,
                        datasets: [{
                            data: revenues,
                            backgroundColor: generateProfessionalColors(revenues.length),
                            borderWidth: 0,
                            hoverOffset: 20,
                            hoverBorderColor: "#808080",
                            hoverBorderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: "Customer Contribution to Revenue",
                                font: {
                                    family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                                    size: 18,
                                    weight: "600"
                                },
                                padding: { top: 10, bottom: 10 }
                            },
                            legend: {
                                position: "top",
                                labels: {
                                    font: {
                                        family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
                                        size: 12
                                    },
                                    boxWidth: 15
                                }
                            },
                            tooltip: {
                                backgroundColor: "rgba(0,0,0,0.85)",
                                bodyFont: { family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif", size: 14, weight: "bold" },
                                titleFont: { family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif", size: 16, weight: "600" },
                                callbacks: {
                                    label: function(context) {
                                        const chart = context.chart;
                                        const label = context.label || "";
                                        const value = context.parsed;
                                        const formattedValue = new Intl.NumberFormat("en-IN", { maximumFractionDigits: 0 }).format(value);

                                        // Original total from all data points.
                                        const originalData = chart.data.datasets[0].data;
                                        const originalTotal = originalData.reduce((a, b) => a + b, 0);

                                        // Sum up only those slices that are currently visible.
                                        let visibleTotal = 0;
                                        const meta = chart.getDatasetMeta(0);
                                        meta.data.forEach((arc, index) => {
                                            if (chart.getDataVisibility(index)) {
                                                visibleTotal += originalData[index];
                                            }
                                        });

                                        // Compute percentages.
                                        const originalPercentage = ((value / originalTotal) * 100).toFixed(2);

                                        // If one or more segments are hidden, calculate the updated percentage.
                                        if (visibleTotal !== originalTotal) {
                                            const updatedPercentage = ((value / visibleTotal) * 100).toFixed(2);
                                            return `${label}: INR ${formattedValue} (${updatedPercentage}% of the selected options) / (${originalPercentage}% of the actual revenue)`;
                                        } else {
                                            return `${label}: INR ${formattedValue} (${originalPercentage}%)`;
                                        }
                                    }
                                }
                            }
                        },
                        animation: {
                            animateRotate: true,
                            animateScale: true
                        }
                    },
                    plugins: [outerBorderPlugin]
                });

                // Add click event listener on canvas.
                canvas.onclick = function(evt) {
                    // Obtain the active slice at the click event.
                    const activePoints = window.activeCustomerContributionChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, false);
                    if (activePoints.length) {
                        const index = activePoints[0].index;
                        // Retrieve the clicked customer name.
                        const customerName = labels[index];
                        console.log("Clicked customer name:", customerName);
                        
                        // Automatically populate the credit score textbox.
                        const creditTextbox = document.getElementById("creditCustomerID");
                        if (creditTextbox) {
                            creditTextbox.value = customerName;
                        }
                        
                        // Send the payload (customer name) to the PHP by calling fetchCustomerDetails.
                        fetchCustomerDetails(customerName);
                    }
                };

            })
            .catch(error => {
                console.error("Error fetching pie chart data:", error);
                outputContainer.innerHTML = `<p>Error fetching pie chart data: ${error.message}</p>`;
            });
    }

    // Updated fetchCustomerDetails function using external loading CSS
    function fetchCustomerDetails(customerName) {
        const outputContainer = document.getElementById("outputContainer");
        // Use the spinner HTML (the CSS for this is in your external file, e.g., loading.css)
        const loadingHtml = `
            <div class="loadingio-spinner-bean-eater-2by998twmg8">
              <div class="ldio-yzaezf3dcmj">
                <div><div></div><div></div><div></div></div>
                <div><div></div><div></div><div></div></div>
              </div>
            </div>`;
        outputContainer.innerHTML = loadingHtml;
      
        // Create a Promise that resolves after 1.5 seconds
        const delayPromise = new Promise(resolve => setTimeout(resolve, 1500));
      
        // Fetch customer details from the backend
        const fetchPromise = fetch("fetch_customer_details.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "customerName=" + encodeURIComponent(customerName)
        })
        .then(response => {
          if (!response.ok) {
            throw new Error("Network response was not ok.");
          }
          return response.json();
        });
      
        // Wait for both the delay and the fetch promise to complete
        Promise.all([fetchPromise, delayPromise])
          .then(([details]) => {
            if (details.error) {
              outputContainer.innerHTML = `<p>${details.error}</p>`;
              return;
            }
            // Format numbers in Indian style
            const formattedTotalSales = new Intl.NumberFormat("en-IN", { maximumFractionDigits: 0 }).format(details.total_sales);
            const formattedTotalTransactions = new Intl.NumberFormat("en-IN", { maximumFractionDigits: 0 }).format(details.total_transactions);
            const formattedTotalProfit = new Intl.NumberFormat("en-IN", { maximumFractionDigits: 0 }).format(details.total_profit);
      
            outputContainer.innerHTML = `
              <h3>Customer Details</h3>
              <p><strong>Name:</strong> ${details.name}</p>
              <p><strong>Total Sales:</strong> INR ${formattedTotalSales}</p>
              <p><strong>Customer Since:</strong> ${details.customer_since} years</p>
              <p><strong>Total Transactions:</strong> ${formattedTotalTransactions}</p>
              <p><strong>Total Profit:</strong> INR ${formattedTotalProfit}</p>
              <p><strong>Average Margin:</strong> ${details.average_margin}%</p>
            `;
          })
          .catch(error => {
            console.error("Error fetching customer details:", error);
            outputContainer.innerHTML = `<p>Error fetching customer details: ${error.message}</p>`;
          });
    }

    // Generates a limited professional color palette.
    function generateProfessionalColors(num) {
        const palette = [
            "#4e73df", // Professional blue.
            "#1cc88a", // Muted green.
            "#36b9cc", // Teal.
            "#f6c23e", // Soft gold.
            "#e74a3b", // Professional red.
            "#858796"  // Gray.
        ];
        const colors = [];
        for (let i = 0; i < num; i++) {
            colors.push(palette[i % palette.length]);
        }
        return colors;
    }

    // Plugin to draw a border (circumference) around the entire pie chart.
    const outerBorderPlugin = {
        id: 'outerBorderPlugin',
        afterDraw(chart) {
            const ctx = chart.ctx;
            const meta = chart.getDatasetMeta(0);
            if (!meta.data || meta.data.length === 0) return;
            // Use the first arc element to get center and outerRadius.
            const firstArc = meta.data[0];
            const centerX = firstArc.x;
            const centerY = firstArc.y;
            const radius = firstArc.outerRadius;
            ctx.save();
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.lineWidth = 2;
            ctx.strokeStyle = "#000000"; // Black circumference border.
            ctx.stroke();
            ctx.restore();
        }
    };

    // Expose the function globally.
    window.plotCustomerContributionPieChart = plotCustomerContributionPieChart;
});
