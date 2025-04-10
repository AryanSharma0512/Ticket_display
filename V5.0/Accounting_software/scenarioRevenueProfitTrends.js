document.addEventListener("DOMContentLoaded", function() {
    function plotRevenueProfitTrendsProjection() {
        console.log("plotRevenueProfitTrendsProjection() invoked.");

        const chartContainer = document.getElementById("chartContainer");
        const canvas = document.getElementById("chartCanvas");
        if (!chartContainer || !canvas) {
            console.error("Chart container or canvas element not found.");
            return;
        }

        // Set canvas dimensions and center it.
        canvas.width = 1200;
        canvas.height = 600;
        canvas.style.display = "block";
        canvas.style.margin = "0 auto";
        chartContainer.style.display = "block";

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
                    chartContainer.innerHTML = "<p>No scenario data available.</p>";
                    return;
                }

                // Extract data arrays.
                const labels = data.map(item => item.month);
                const revenues = data.map(item => parseFloat(item.actual_revenue));
                const profits = data.map(item => parseFloat(item.actual_profit));
                const margins = profits.map((p, i) => (revenues[i] > 0 ? (p / revenues[i]) * 100 : 0));

                // Identify current month index.
                const currentIndex = labels.length - 1;
                const currentData = data[currentIndex];
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

                // Build bar datasets (Actual Revenue, Projection).
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

                // The bar that shows how much we add (or difference) for the projection in the current month.
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

                // REMOVE T-MARKERS:
                // The code that generated "Best Revenue T" and "Worst Revenue T" is removed entirely.
                // This ensures no "T" markers appear in your chart.

                // Profit line and margin line datasets.
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

                // Thin dashed line connecting last known margin to the projected margin.
                const marginConnectionLine = {
                    type: "line",
                    label: "Margin Projection",
                    data: [
                        { x: labels[labels.length - 2], y: margins[labels.length - 2] },
                        { x: labels[currentIndex], y: blendedMargin }
                    ],
                    borderColor: "rgba(0,0,0,0.7)",
                    borderDash: [5, 3],
                    borderWidth: 2,
                    pointRadius: 0,
                    fill: false,
                    yAxisID: "y2",
                    order: 5
                };

                // Projected Margin scatter point (with ±3% error range).
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

                // 1) Custom plugin to draw vertical error bars for MARGIN (±3%).
                const projectedMarginErrorBarPlugin = {
                    id: "projectedMarginErrorBarPlugin",
                    afterDatasetsDraw(chart, args, options) {
                        const ctx = chart.ctx;
                        const xScale = chart.scales.x;
                        const yScale = chart.scales.y2;
                        const currentLabel = labels[currentIndex];
                        const xPos = xScale.getPixelForValue(currentLabel);

                        // Retrieve projected margin data.
                        const pmDataset = chart.data.datasets.find(ds => ds.label === "Projected Margin");
                        if (!pmDataset || !pmDataset.data[0]) return;
                        const pmData = pmDataset.data[0];

                        const yBest = yScale.getPixelForValue(pmData.bestCase);
                        const yWorst = yScale.getPixelForValue(pmData.worstCase);
                        
                        // Larger cap width for visibility
                        const capWidth = 15;

                        // Store margin cap positions for interactivity.
                        // We'll put them under chart.customCapPositions.margin
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

                        // Determine line styling based on tooltip active state.
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
                        
                        // Draw horizontal cap for best-case (green).
                        ctx.beginPath();
                        ctx.moveTo(xPos - capWidth/2, yBest);
                        ctx.lineTo(xPos + capWidth/2, yBest);
                        ctx.strokeStyle = "rgba(0,128,0,0.8)";
                        ctx.stroke();
                        
                        // Draw horizontal cap for worst-case (red).
                        ctx.beginPath();
                        ctx.moveTo(xPos - capWidth/2, yWorst);
                        ctx.lineTo(xPos + capWidth/2, yWorst);
                        ctx.strokeStyle = "rgba(255,0,0,0.8)";
                        ctx.stroke();
                        ctx.restore();
                    }
                };

                // 2) New plugin to draw vertical error bars for REVENUE (±3%) on the "Projection" bar.
                const projectedRevenueErrorBarPlugin = {
                    id: "projectedRevenueErrorBarPlugin",
                    afterDatasetsDraw(chart, args, options) {
                        // Find the "Projection" dataset index.
                        const projIndex = chart.data.datasets.findIndex(ds => ds.label === "Projection");
                        if (projIndex === -1) return;
                        const meta = chart.getDatasetMeta(projIndex);
                        const barElement = meta.data[currentIndex];
                        if (!barElement) return;

                        // The x position is typically barElement.x for category scale.
                        // For stacked bar, barElement.y is the top pixel, barElement.base is the bottom pixel, etc.
                        const xCenter = barElement.x;

                        // We'll define ±3% of projectedRevenue for best/worst.
                        const bestProjRevenue = projectedRevenue * 1.15;
                        const worstProjRevenue = projectedRevenue * 0.85;

                        // Use y1 scale for revenue & profit.
                        const y1 = chart.scales.y1;
                        const yBest = y1.getPixelForValue(bestProjRevenue);
                        const yWorst = y1.getPixelForValue(worstProjRevenue);

                        // We'll center these error bars on the same x as the "Projection" bar.
                        const capWidth = 15;
                        const ctx = chart.ctx;

                        // Store these cap positions for interactivity under chart.customCapPositions.revenue
                        if (!chart.customCapPositions) chart.customCapPositions = {};
                        chart.customCapPositions.revenue = {
                            best: {
                                x: xCenter,
                                y: yBest,
                                width: capWidth,
                                data: {
                                    revenue: bestProjRevenue
                                }
                            },
                            worst: {
                                x: xCenter,
                                y: yWorst,
                                width: capWidth,
                                data: {
                                    revenue: worstProjRevenue
                                }
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

                        // Draw horizontal cap for best-case revenue (green).
                        ctx.beginPath();
                        ctx.moveTo(xCenter - capWidth/2, yBest);
                        ctx.lineTo(xCenter + capWidth/2, yBest);
                        ctx.strokeStyle = "rgba(0,128,0,0.8)";
                        ctx.stroke();

                        // Draw horizontal cap for worst-case revenue (red).
                        ctx.beginPath();
                        ctx.moveTo(xCenter - capWidth/2, yWorst);
                        ctx.lineTo(xCenter + capWidth/2, yWorst);
                        ctx.strokeStyle = "rgba(255,0,0,0.8)";
                        ctx.stroke();
                        ctx.restore();
                    }
                };

                // Destroy any previous chart instance.
                if (window.activeRevenueProfitChart) {
                    window.activeRevenueProfitChart.destroy();
                }
                const ctxFinal = canvas.getContext("2d");

                // Build the chart with all datasets EXCEPT the T markers.
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
                        responsive: false,
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
                                    // Hide "Margin Projection" and "Projected Margin" from the legend.
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
                                        // Default label for other bars/lines.
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
                    // Include both plugins: margin error bars & revenue error bars.
                    plugins: [projectedMarginErrorBarPlugin, projectedRevenueErrorBarPlugin]
                });

                // Mousemove event to handle hovering over any custom cap areas (margin or revenue).
                canvas.addEventListener("mousemove", function(event) {
                    if (!window.activeRevenueProfitChart || !window.activeRevenueProfitChart.customCapPositions) {
                        capTooltip.style.opacity = 0;
                        return;
                    }

                    const rect = canvas.getBoundingClientRect();
                    const mouseX = event.clientX - rect.left;
                    const mouseY = event.clientY - rect.top;
                    const proximity = 8; // pixel tolerance
                    let tooltipContent = "";
                    let showTooltip = false;

                    // Helper to format a number as INR (no ₹ symbol).
                    function formatINR(num) {
                        return new Intl.NumberFormat("en-IN", {
                            maximumFractionDigits: 0
                        }).format(num);
                    }

                    // Check Margin Caps (if any).
                    if (window.activeRevenueProfitChart.customCapPositions.margin) {
                        const { best, worst } = window.activeRevenueProfitChart.customCapPositions.margin;
                        // best cap:
                        if (
                            Math.abs(mouseX - best.x) <= best.width &&
                            Math.abs(mouseY - best.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Best Margin:</strong> ${best.data.margin.toFixed(2)}%<br>
                                              Profit: INR ${formatINR(best.data.profit)}`;
                            showTooltip = true;
                        }
                        // worst cap:
                        if (
                            Math.abs(mouseX - worst.x) <= worst.width &&
                            Math.abs(mouseY - worst.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Worst Margin:</strong> ${worst.data.margin.toFixed(2)}%<br>
                                              Profit: INR ${formatINR(worst.data.profit)}`;
                            showTooltip = true;
                        }
                    }

                    // Check Revenue Caps (if any).
                    if (window.activeRevenueProfitChart.customCapPositions.revenue) {
                        const { best, worst } = window.activeRevenueProfitChart.customCapPositions.revenue;
                        // best revenue cap:
                        if (
                            Math.abs(mouseX - best.x) <= best.width &&
                            Math.abs(mouseY - best.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Best Projected Revenue:</strong> INR ${formatINR(best.data.revenue)}<br>
                                            <em>Error Extent:</em> +10% (from INR ${formatINR(projectedRevenue)})`;
                            showTooltip = true;
                        }
                        // worst revenue cap:
                        if (
                            Math.abs(mouseX - worst.x) <= worst.width &&
                            Math.abs(mouseY - worst.y) <= proximity
                        ) {
                            tooltipContent = `<strong>Worst Projected Revenue:</strong> INR ${formatINR(worst.data.revenue)}<br>
                                            <em>Error Extent:</em> -10% (from INR ${formatINR(projectedRevenue)})`;
                            showTooltip = true;
                        }
                    }

                    // Show or hide the tooltip accordingly.
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

    // Expose the plotting function.
    window.plotRevenueProfitTrendsProjection = plotRevenueProfitTrendsProjection;
    
    // Append additional CSS for the custom tooltip (if not already).
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
