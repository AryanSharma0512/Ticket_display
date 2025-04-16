// ✅ Function to fetch and display last 10 transactions from both tables
function fetchLastTenTransactions() {
    const outputContainer = document.getElementById('outputContainer');
    outputContainer.innerHTML = "<p>Loading last 10 transactions...</p>";

    fetch('fetch_last_ten_transactions.php') // ✅ Use correct PHP file
        .then(response => {
            if (!response.ok) {
                return response.text().then(err => { throw new Error(`HTTP error! status: ${response.status}, ${err}`) });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                outputContainer.innerHTML = `<p>Error: ${data.error}</p>`;
                return;
            }

            let resultsHTML = "";

            function generateStyledTable(data, title, tableClass) {
                if (!data || !Array.isArray(data) || data.length === 0) {
                    return `<p>No results found in ${title}.</p>`;
                }

                let headers = Object.keys(data[0]); // ✅ Get table headers dynamically

                let tableHTML = `<h3>${title}</h3>
                    <div class='${tableClass}-container'>
                        <table class='${tableClass}'>
                            <thead>
                                <tr>`;

                headers.forEach(header => {
                    tableHTML += `<th>${header}</th>`;
                });

                tableHTML += `</tr>
                            </thead>
                            <tbody>`;

                data.forEach(row => {
                    tableHTML += "<tr>";
                    headers.forEach(header => {
                        tableHTML += `<td>${row[header] || ""}</td>`;
                    });
                    tableHTML += "</tr>";
                });

                tableHTML += `</tbody>
                        </table>
                    </div>`;
                return tableHTML;
            }

            resultsHTML += generateStyledTable(data.transactions.mainTable, "Last 10 Transactions (Main Table)", "transaction-table");
            resultsHTML += generateStyledTable(data.transactions.accNetworkMain, "Last 10 Transactions (Account Network)", "acc-network-table");

            outputContainer.innerHTML = resultsHTML;
        })
        .catch(error => {
            outputContainer.innerHTML = `<p>Error fetching transactions: ${error.message}</p>`;
            console.error("Fetch error:", error);
        });
}