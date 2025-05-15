// goalSeeker.js
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("goalSeekerForm");
    const outputContainer = document.getElementById("outputContainer");

    form.addEventListener("submit", function(e) {
        e.preventDefault();

        // Allow empty input; default to 0.
        const netProfit  = document.getElementById("netProfitInput").value || 0;
        const netRevenue = document.getElementById("netRevenueInput").value || 0;

        fetch("fetch_goal_seeker.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `net_profit=${encodeURIComponent(netProfit)}&net_revenue=${encodeURIComponent(netRevenue)}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                outputContainer.innerHTML = `<p style="color: red;">Error: ${data.error || "Unable to compute goals."}</p>`;
                return;
            }

            // Use the final months estimate returned by the PHP as 'monthsNeeded'
            const monthsNeeded = data.monthsNeeded;
            if (monthsNeeded >= 9999) {
                outputContainer.innerHTML = `
                    <h3>Goal Seeker Results</h3>
                    <p>This goal seems unreachable based on current trends.</p>
                `;
                return;
            }

            outputContainer.innerHTML = `
                <h3>Goal Seeker Results</h3>
                <p><strong>Target Date:</strong> ${data.target_date}</p>
                <p><strong>Extra Months Needed:</strong> ${monthsNeeded}</p>
                <p><strong>Current Total Revenue:</strong> INR ${parseFloat(data.current_revenue_total).toLocaleString("en-IN")}</p>
                <p><strong>Current Total Profit:</strong> INR ${parseFloat(data.current_profit_total).toLocaleString("en-IN")}</p>
            `;
        })
        .catch(err => {
            outputContainer.innerHTML = `<p style="color: red;">Error: ${err.message}</p>`;
        });
    });
});
