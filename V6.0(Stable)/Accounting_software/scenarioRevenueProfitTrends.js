document.addEventListener("DOMContentLoaded", function() {
    function plotRevenueProfitTrendsProjection() {
        console.log("plotRevenueProfitTrendsProjection() invoked.");

        // Get output container (it should always exist)
        const outputContainer = document.getElementById("outputContainer");
        if (!outputContainer) {
            console.error("Output container not found.");
            return;
        }

        // Get the fixed chart container and canvas from the DOM.
        const chartContainer = document.getElementById("chartContainer");
        const canvas = document.getElementById("chartCanvas");

        if (!chartContainer || !canvas) {
            console.error("Chart container or canvas element not found.");
            return;
        }

        // Clear previous output (text only)
        outputContainer.innerHTML = '';
        // Ensure chart container is visible (it stays fixed in the DOM)
        chartContainer.style.display = "block";

        // Clear the canvas.
        const ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Destroy any active Chart.js instance.
        if (window.activeRevenueProfitChart) {
            window.activeRevenueProfitChart.destroy();
            window.activeRevenueProfitChart = null;
        }

        // Remove hardcoded dimensions so that CSS can govern responsiveness.
        // If you wish, you can calculate the canvas size dynamically based on the container, for example:
        // const containerWidth = chartContainer.offsetWidth;
        // canvas.width = containerWidth;
        // canvas.height = containerWidth * 0.5; // e.g., a 2:1 aspect ratio

        // Create or get the custom tooltip element.
        let capTooltip = document.getElementById("capTooltip");
        if (!capTooltip) {
            capTooltip = document.createElement("div");
            capTooltip.id = "capTooltip";
            document.body.appendChild(capTooltip);
        }

        // Fetch scenario data.
        fetch("fetch_scenario_analysis.php")
            .then(response => {
                if (!response.ok) {
                    throw new Error("Network response was not ok.");
                }
                return response.json();
            })
            .then(data => {
                console.log("Scenario data fetched:", data);
                if (!Array.isArray(data) || data.length === 0) {
                    outputContainer.innerHTML = "<p>No scenario data available.</p>";
                    return;
                }

                // Extract data arrays.
                const labels = data.map(item => item.month);
                const revenues = data.map(item => parseFloat(item.actual_revenue));
                const profits = data.map(item => parseFloat(item.actual_profit));
                const margins = profits.map((p, i) => (revenues[i] > 0 ? (p / revenues[i]) * 100 : 0));

                // Ensure the current month exists even if no transactions yet
                const now = new Date();
                const offset = now.getTimezoneOffset() * 60000;
                const currentMonthLabel = new Date(now - offset).toISOString().slice(0, 7);
                if (!labels.includes(currentMonthLabel)) {
                    labels.push(currentMonthLabel);
                    revenues.push(0);
                    profits.push(0);
                    margins.push(0);
                }

                // Identify current month index.
                const currentIndex = labels.indexOf(currentMonthLabel);
                const currentData = data.find(d => d.month === currentMonthLabel) || {actual_revenue: 0, actual_profit: 0};
                const actualRevenueCurrent = parseFloat(currentData.actual_revenue);
                const actualProfitCurrent = parseFloat(currentData.actual_profit);
                const actualMarginCurrent = (actualRevenueCurrent > 0)
                    ? (actualProfitCurrent / actualRevenueCurrent) * 100
                    : 0;

                // Projection calculation.
                const today = new Date();
                const totalDays = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
                const elapsedDays = today.getDate();
                const paceProjection = (actualRevenueCurrent / elapsedDays) * totalDays;

                let historicalAvg = paceProjection;
                let historicalMarginAvg = actualMarginCurrent;
                if (labels.length >= 4) {
                    const histData = data.slice(-4, -1);
                    const histRevs = histData.map(d => parseFloat(d.actual_revenue));
                    const histProfs = histData.map(d => parseFloat(d.actual_profit));
                    historicalAvg = histRevs.reduce((a, b) => a + b, 0) / histRevs.length;
                    historicalMarginAvg = histRevs.reduce((acc, rev, i) => {
                        return acc + (rev > 0 ? (histProfs[i] / rev) * 100 : 0);
                    }, 0) / histRevs.length;
                }
                const alpha = Math.max(0.2, Math.min(0.8, elapsedDays / totalDays));
                const projectedRevenue = Math.round((alpha * paceProjection + (1 - alpha) * historicalAvg) / 1000) * 1000;
                const blendedMargin = alpha * actualMarginCurrent + (1 - alpha) * historicalMarginAvg;
                const projectedProfit = projectedRevenue * (blendedMargin / 100);

                // Set margin range to ±3%.
                const marginRange = 3;
                const bestMargin = blendedMargin + marginRange;
                const worstMargin = blendedMargin - marginRange;
                const bestCaseProfit = projectedRevenue * (bestMargin / 100);
                const worstCaseProfit = projectedRevenue * (worstMargin / 100);

                // Build bar datasets.
                const actualRevenueDataset = {
                    type: "bar",
                    label: "Actual Revenue",
                    data: revenues,
                    backgroundColor: "rgba(112,128,144,0.9)",
                    borderColor: "rgba(54, 162, 235, 1)",
                    borderWidth: 1,
                    yAxisID: "y1",
                    stack: "stack",
                    order: 3
                };

                const diffArray = labels.map((_, i) =>
                    i === currentIndex ? (projectedRevenue - actualRevenueCurrent) : 0
                );

                function createProjectionPattern(ctx) {
                    const patternCanvas = document.createElement("canvas");
                    patternCanvas.width = 10;
                    patternCanvas.height = 10;
                    const pctx = patternCanvas.getContext("2d");
                    pctx.fillStyle = "rgba(255, 255, 0, 0.3)";
                    pctx.fillRect(0, 0, 10, 10);
                    pctx.fillStyle = "rgba(128,128,128,0.6)";
                    pctx.beginPath();
                    pctx.arc(3, 3, 1.5, 0, 2 * Math.PI);
                    pctx.fill();
                    return ctx.createPattern(patternCanvas, "repeat");
                }
                const ctx2 = canvas.getContext("2d");
                const projectionDiffDataset = {
                    type: "bar",
                    label: "Projection",
                    data: diffArray,
                    backgroundColor: createProjectionPattern(ctx2),
                    borderColor: "rgba(128,128,128,0.8)",
                    borderDash: [5, 5],
                    borderWidth: 2,
                    yAxisID: "y1",
                    stack: "stack",
                    order: 4
                };

                const profitLineDataset = {
                    type: "line",
                    label: "Profit (INR)",
                    data: labels.map((label, i) => ({ x: label, y: profits[i] })),
                    borderColor: "rgba(255,87,34,1)",
                    backgroundColor: "rgba(75, 192, 192, 0.2)",
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: "rgba(75, 192, 192, 1)",
                    fill: false,
                    yAxisID: "y1",
                    order: 1,
                    tension: 0.3
                };
                const marginLineDataset = {
                    type: "line",
                    label: "Margin (%)",
                    data: labels.map((label, i) => ({ x: label, y: margins[i] })),
                    borderColor: "rgba(63,81,181,1)",
                    backgroundColor: "rgba(255,99,132,0)",
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: "rgba(255,99,132,1)",
                    fill: false,
                    yAxisID: "y2",
                    order: 2,
                    tension: 0.3
                };

                // Draw dotted line from last month's margin to current month's projection.
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : 0;
                const marginConnectionLine = {
                    type: "line",
                    label: "Margin Projection",
                    data: [
                        { x: labels[prevIndex], y: margins[prevIndex] },
                        { x: labels[currentIndex], y: blendedMargin }
                    ],
                    borderColor: "rgba(0,0,0,0.7)",
                    borderDash: [5, 5], // Adjust these values to get a dotted effect.
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                    yAxisID: "y2",
                    order: 5
                };

                const projectedMarginScatter = {
                    type: "scatter",
                    label: "Projected Margin",
                    data: [{
                        x: labels[currentIndex],
                        y: blendedMargin,
                        bestCase: bestMargin,
                        worstCase: worstMargin,
                        projectedProfit: projectedProfit,
                        bestCaseProfit: bestCaseProfit,
                        worstCaseProfit: worstCaseProfit
                    }],
                    pointStyle: "circle",
                    pointRadius: 8,
                    borderColor: "rgba(0,0,0,1)",
                    borderWidth: 2,
                    backgroundColor: "rgba(255,99,132,0.8)",
                    yAxisID: "y2",
                    order: 6
                };

                // Custom plugins for error bars…
                // (Plugin code remains unchanged.)

                const projectedMarginErrorBarPlugin = {
                    id: "projectedMarginErrorBarPlugin",
                    afterDatasetsDraw(chart, args, options) {
                        const ctx = chart.ctx;
                        const xScale = chart.scales.x;
                        const yScale = chart.scales.y2;
                        const currentLabel = labels[currentIndex];
                        const xPos = xScale.getPixelForValue(currentLabel);

                        const pmDataset = chart.data.datasets.find(ds => ds.label === "Projected Margin");
                        if (!pmDataset || !pmDataset.data[0]) return;
                        const pmData = pmDataset.data[0];

                        const yBest = yScale.getPixelForValue(pmData.bestCase);
                        const yWorst = yScale.getPixelForValue(pmData.worstCase);
                        const capWidth = 15;

                        if (!chart.customCapPositions) chart.customCapPositions = {};
                        chart.customCapPositions.margin = {
                            best: { 
                                x: xPos, 
                                y: yBest, 
                                width: capWidth, 
                                data: {
                                    margin: pmData.bestCase,
                                    profit: pmData.bestCaseProfit
                                }
                            },
                            worst: { 
                                x: xPos, 
                                y: yWorst, 
                                width: capWidth, 
                                data: {
                                    margin: pmData.worstCase,
                                    profit: pmData.worstCaseProfit
                                }
                            }
                        };

                        let lineWidth = 2;
                        let strokeStyle = "rgba(0,0,0,1)";
                        const activeElements = chart.tooltip._active;
                        if (activeElements && activeElements.length > 0) {
                            activeElements.forEach(element => {
                                const dsLabel = chart.data.datasets[element.datasetIndex].label;
                                if (dsLabel === "Projected Margin") {
                                    lineWidth = 4;
                                    strokeStyle = "rgba(0,0,0,1)";
                                }
                            });
                        }
                        
                        ctx.save();
                        ctx.beginPath();
                        ctx.strokeStyle = strokeStyle;
                        ctx.lineWidth = lineWidth;
                        ctx.setLineDash([5, 5]);
                        ctx.moveTo(xPos, yBest);
                        ctx.lineTo(xPos, yWorst);
                        ctx.stroke();
                        
                        ctx.beginPath();
                        ctx.moveTo(xPos - capWidth/2, yBest);
                        ctx.lineTo(xPos + capWidth/2, yBest);
                        ctx.strokeStyle = "rgba(0,128,0,0.8)";
                        ctx.stroke();
                        
                        ctx.beginPath();
                        ctx.moveTo(xPos - capWidth/2, yWorst);
                        ctx.lineTo(xPos + capWidth/2, yWorst);
                        ctx.strokeStyle = "rgba(255,0,0,0.8)";
                        ctx.stroke();
                        ctx.restore();
                    }
                };

                const projectedRevenueErrorBarPlugin = {
                    id: "projectedRevenueErrorBarPlugin",
                    afterDatasetsDraw(chart, args, options) {
                        const projIndex = chart.data.datasets.findIndex(ds => ds.label === "Projection");
                        if (projIndex === -1) return;
                        const meta = chart.getDatasetMeta(projIndex);
                        const barElement = meta.data[currentIndex];
                        if (!barElement) return;
                        const xCenter = barElement.x;
                        const bestProjRevenue = projectedRevenue * 1.15;
                        const worstProjRevenue = projectedRevenue * 0.85;
                        const y1 = chart.scales.y1;
                        const yBest = y1.getPixelForValue(bestProjRevenue);
                        const yWorst = y1.getPixelForValue(worstProjRevenue);
                        const capWidth = 15;
                        const ctx = chart.ctx;

                        if (!chart.customCapPositions) chart.customCapPositions = {};
                        chart.customCapPositions.revenue = {
                            best: {
                                x: xCenter,
                                y: yBest,
                                width: capWidth,
                                data: { revenue: bestProjRevenue }
                            },
                            worst: {
                                x: xCenter,
                                y: yWorst,
                                width: capWidth,
                                data: { revenue: worstProjRevenue }
                            }
                        };

                        ctx.save();
                        ctx.beginPath();
                        ctx.setLineDash([5, 5]);
                        ctx.strokeStyle = "rgba(0,0,0,1)";
                        ctx.lineWidth = 2;
                        ctx.moveTo(xCenter, yBest);
                        ctx.lineTo(xCenter, yWorst);
                        ctx.stroke();

                        ctx.beginPath();
                        ctx.moveTo(xCenter - capWidth/2, yBest);
                        ctx.lineTo(xCenter + capWidth/2, yBest);
                        ctx.strokeStyle = "rgba(0,128,0,0.8)";
                        ctx.stroke();

                        ctx.beginPath();
                        ctx.moveTo(xCenter - capWidth/2, yWorst);
                        ctx.lineTo(xCenter + capWidth/2, yWorst);
                        ctx.strokeStyle = "rgba(255,0,0,0.8)";
                        ctx.stroke();
                        ctx.restore();
                    }
                };

                // Destroy any previous Chart.js instance.
                if (window.activeRevenueProfitChart) {
                    window.activeRevenueProfitChart.destroy();
                }
                const ctxFinal = canvas.getContext("2d");

                window.activeRevenueProfitChart = new Chart(ctxFinal, {
                    type: "bar",
                    data: {
                        labels: labels,
                        datasets: [
                            profitLineDataset,
                            marginLineDataset,
                            actualRevenueDataset,
                            projectionDiffDataset,
                            marginConnectionLine,
                            projectedMarginScatter
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: "Monthly Revenue & Profit Trends with Projection",
                                font: { size: 18, weight: "bold" }
                            },
                            legend: {
                                position: "top",
                                labels: {
                                    font: { size: 12 },
                                    filter: function(legendItem) {
                                        const hiddenNames = ["Margin Projection", "Projected Margin"];
                                        return !hiddenNames.includes(legendItem.text);
                                    },
                                    sort: (a, b) => {
                                        const order = {
                                            "Profit (INR)": 1,
                                            "Margin (%)": 2,
                                            "Actual Revenue": 3,
                                            "Projection": 4,
                                            "Projected Margin": 5
                                        };
                                        return order[a.text] - order[b.text];
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: "rgba(0,0,0,0.8)",
                                titleFont: { size: 14, weight: "bold" },
                                bodyFont: { size: 12 },
                                callbacks: {
                                    label: function(context) {
                                        const dsLabel = context.dataset.label || "";
                                        const val = context.parsed.y;
                                        function formatINR(num) {
                                            return new Intl.NumberFormat("en-IN", {
                                                style: "currency",
                                                currency: "INR",
                                                maximumFractionDigits: 0
                                            }).format(num).replace("₹", "INR ");
                                        }
                                        if (dsLabel === "Projection") {
                                            const diffVal = projectedRevenue - actualRevenueCurrent;
                                            if (diffVal > 0) {
                                                return [
                                                    `Projected Revenue: ${formatINR(projectedRevenue)}`,
                                                    `Shortfall from actual: ${formatINR(diffVal)}`
                                                ];
                                            } else {
                                                return [
                                                    `Projected Revenue: ${formatINR(projectedRevenue)}`,
                                                    `Actual exceeds projection by: ${formatINR(Math.abs(diffVal))}`
                                                ];
                                            }
                                        }
                                        if (dsLabel === "Margin (%)") {
                                            const i = labels.indexOf(context.label);
                                            const actualProfit = profits[i] || 0;
                                            return [
                                                `Margin: ${val.toFixed(2)}%`,
                                                `Profit: ${formatINR(actualProfit)}`
                                            ];
                                        }
                                        if (dsLabel === "Profit (INR)") {
                                            return `Profit: ${formatINR(val)}`;
                                        }
                                        if (dsLabel === "Projected Margin") {
                                            const pointData = context.dataset.data[context.dataIndex];
                                            return [
                                                `Projected Margin: ${val.toFixed(2)}%`,
                                                `Best Case (+3%): ${pointData.bestCase.toFixed(2)}% | Profit: ${formatINR(pointData.bestCaseProfit)}`,
                                                `Worst Case (-3%): ${pointData.worstCase.toFixed(2)}% | Profit: ${formatINR(pointData.worstCaseProfit)}`,
                                                `Projected Revenue: ${formatINR(projectedRevenue)}`
                                            ];
                                        }
                                        return `${dsLabel}: ${formatINR(Math.round(val))}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: "category",
                                title: { 
                                    display: true, 
                                    text: "Month",
                                    font: { weight: "bold", size: 12 }
                                },
                                stacked: true,
                                offset: true,
                                grid: { display: false },
                                ticks: { autoSkip: false, font: { size: 11 } }
                            },
                            y1: {
                                type: "linear",
                                position: "left",
                                stacked: true,
                                beginAtZero: true,
                                title: { 
                                    display: true, 
                                    text: "Revenue & Profit (INR)",
                                    font: { weight: "bold", size: 12 }
                                },
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat("en-IN", {
                                            style: "currency",
                                            currency: "INR",
                                            maximumFractionDigits: 0
                                        }).format(value).replace("₹", "");
                                    },
                                    font: { size: 11 }
                                },
                                grid: { color: "rgba(0,0,0,0.1)" }
                            },
                            y2: {
                                type: "linear",
                                position: "right",
                                beginAtZero: true,
                                title: { 
                                    display: true, 
                                    text: "Profit Margin (%)",
                                    font: { weight: "bold", size: 12 }
                                },
                                ticks: {
                                    color: "red",
                                    font: { weight: "bold", size: 11 },
                                    callback: function(value) { return value + "%"; }
                                },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    },
                    plugins: [projectedMarginErrorBarPlugin, projectedRevenueErrorBarPlugin]
                });

                canvas.addEventListener("mousemove", function(event) {
                    if (!window.activeRevenueProfitChart || !window.activeRevenueProfitChart.customCapPositions) {
                        capTooltip.style.opacity = 0;
                        return;
                    }

                    const rect = canvas.getBoundingClientRect();
                    const mouseX = event.clientX - rect.left;
                    const mouseY = event.clientY - rect.top;
                    const proximity = 8;
                    let tooltipContent = "";
                    let showTooltip = false;

                    function formatINR(num) {
                        return new Intl.NumberFormat("en-IN", { maximumFractionDigits: 0 }).format(num);
                    }

                    if (window.activeRevenueProfitChart.customCapPositions.margin) {
                        const { best, worst } = window.activeRevenueProfitChart.customCapPositions.margin;
                        if (
                            Math.abs(mouseX - best.x) <= best.width &&
                            Math.abs(mouseY - best.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Best Margin:</strong> ${best.data.margin.toFixed(2)}%<br>Profit: INR ${formatINR(best.data.profit)}`;
                            showTooltip = true;
                        }
                        if (
                            Math.abs(mouseX - worst.x) <= worst.width &&
                            Math.abs(mouseY - worst.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Worst Margin:</strong> ${worst.data.margin.toFixed(2)}%<br>Profit: INR ${formatINR(worst.data.profit)}`;
                            showTooltip = true;
                        }
                    }

                    if (window.activeRevenueProfitChart.customCapPositions.revenue) {
                        const { best, worst } = window.activeRevenueProfitChart.customCapPositions.revenue;
                        if (
                            Math.abs(mouseX - best.x) <= best.width &&
                            Math.abs(mouseY - best.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Best Projected Revenue:</strong> INR ${formatINR(best.data.revenue)}<br><em>Error Extent:</em> +10% (from INR ${formatINR(projectedRevenue)})`;
                            showTooltip = true;
                        }
                        if (
                            Math.abs(mouseX - worst.x) <= worst.width &&
                            Math.abs(mouseY - worst.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Worst Projected Revenue:</strong> INR ${formatINR(worst.data.revenue)}<br><em>Error Extent:</em> -10% (from INR ${formatINR(projectedRevenue)})`;
                            showTooltip = true;
                        }
                    }

                    if (showTooltip) {
                        capTooltip.innerHTML = tooltipContent;
                        capTooltip.style.opacity = 1;
                        capTooltip.style.left = event.pageX + 10 + "px";
                        capTooltip.style.top = event.pageY + 10 + "px";
                    } else {
                        capTooltip.style.opacity = 0;
                    }
                });

                canvas.addEventListener("mouseout", function() {
                    capTooltip.style.opacity = 0;
                });
            })
            .catch(error => {
                console.error("Error fetching scenario data:", error);
            });
    }

    window.plotRevenueProfitTrendsProjection = plotRevenueProfitTrendsProjection;
    
    const tooltipStyles = document.createElement("style");
    tooltipStyles.innerHTML = `
        #capTooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.85);
            color: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            transition: opacity 0.2s ease;
            opacity: 0;
            z-index: 1000;
        }
    `;
    document.head.appendChild(tooltipStyles);
});
