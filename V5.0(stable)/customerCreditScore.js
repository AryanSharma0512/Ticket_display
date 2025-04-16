function fetchCustomerCreditScore() {
    const customerID = document.getElementById('creditCustomerID').value.trim();
    const outputContainer = document.getElementById('outputContainer'); // Ensure correct reference

    if (!customerID) {
        outputContainer.innerHTML = "<p style='color: red;'>Please enter a Customer ID.</p>";
        return;
    }

    outputContainer.innerHTML = "<p>Loading Credit Score...</p>";

    fetch('fetch_credit_score.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `customerID=${encodeURIComponent(customerID)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            outputContainer.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
            return;
        }

        // Color coding for Credit Score
        let scoreColor;
        if (data.credit_score < 400) scoreColor = "red";
        else if (data.credit_score < 700) scoreColor = "orange";
        else scoreColor = "green";

        // Generate the result HTML
        let resultsHTML = `
            <div class="credit-score-container">
                <h2 style="text-align:center;">Credit Score: 
                    <span style="color: ${scoreColor}; font-size: 2rem;">${data.credit_score}/900</span>
                </h2>
                <table class="credit-score-table">
                    <tr><th>Customer Since</th><td>${data.customer_since} years</td></tr>
                    <tr><th>Total Transactions</th><td>${data.total_transactions.toLocaleString()}</td></tr>
                    <tr><th>Total Business</th><td>₹${data.total_business.toLocaleString()}</td></tr>
                    <tr><th>Highest Credit Used</th><td>₹${data.max_credit_used.toLocaleString()}</td></tr>
                    <tr><th>Repayment Ratio</th><td>${data.repayment_ratio.toFixed(2)}%</td></tr>
                    <tr><th>Profitability</th><td>₹${data.total_profit.toLocaleString()}</td></tr>
                    <tr><th>Average Margin</th><td>${data.average_margin.toFixed(2)}%</td></tr>
                </table>
            </div>
        `;

        outputContainer.innerHTML = resultsHTML;
    })
    .catch(error => {
        outputContainer.innerHTML = `<p style="color: red;">Error fetching credit score: ${error.message}</p>`;
    });
}

// ✅ Apply Styling (Ensures a professional and modern look)
document.addEventListener("DOMContentLoaded", () => {
    const style = document.createElement('style');
    style.innerHTML = `
        .credit-score-container {
            text-align: center;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 60%;
            margin: auto;
        }

        .credit-score-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 1rem;
            text-align: left;
        }

        .credit-score-table th, .credit-score-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            transition: background-color 0.3s;
        }

        .credit-score-table th {
            background-color: #f4f4f4;
            font-weight: bold;
        }

        .credit-score-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .credit-score-table tr:hover {
            background-color: #e0f7fa;
        }

        .credit-score-container h2 span {
            font-weight: bold;
        }
    `;
    document.head.appendChild(style);
});
