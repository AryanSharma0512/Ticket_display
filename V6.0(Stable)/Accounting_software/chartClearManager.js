// chartClearManager.js
// This file provides a centralized function to clear any rendered chart and reset the canvas element.

(function () {
    /**
     * Clears any existing Chart.js instance from the canvas within the chart container,
     * and creates a new, fresh canvas element for subsequent chart rendering.
     */
    function clearChart() {
        // Get the chart container element.
        const chartContainer = document.getElementById("chartContainer");
        if (!chartContainer) {
            console.error("Chart container not found.");
            return;
        }
        // Look for an existing canvas element inside the container.
        let canvas = chartContainer.querySelector("#chartCanvas");
        if (canvas) {
            // Destroy the Chart.js instance if one exists.
            const existingChart = Chart.getChart(canvas);
            if (existingChart) {
                existingChart.destroy();
            }
            // Remove only the canvas element.
            chartContainer.removeChild(canvas);
        }
        // Create a new canvas element.
        canvas = document.createElement("canvas");
        canvas.id = "chartCanvas";
        canvas.style.maxWidth = "100%"; // optional styling
        // Append the new canvas into the chart container.
        chartContainer.appendChild(canvas);
        
        // Clear any global chart tracking if used.
        window.activeRevenueProfitChart = null;
    }

    // Expose the clearChart function globally.
    window.clearChart = clearChart;
})();
