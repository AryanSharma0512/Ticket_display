function trackThreadedTransaction() {
    const transactionID = document.getElementById('threadedTransactionID').value;
    const outputContainer = document.getElementById('outputContainer');

    outputContainer.innerHTML = "<p>Loading related transactions...</p>";

    fetch('fetch_threaded_transactions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `transactionID=${encodeURIComponent(transactionID)}`
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(err => {throw new Error(`HTTP error! status: ${response.status}, ${err}`)});
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

            let headers = Object.keys(data[0]); // Get table headers dynamically

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

        resultsHTML += generateStyledTable(data.main_table, "Main Table Transactions", "transaction-table");
        resultsHTML += generateStyledTable(data.acc_network_main, "Account Network Transactions", "acc-network-table");

        outputContainer.innerHTML = resultsHTML;
    })
    .catch(error => {
        outputContainer.innerHTML = `<p>Error tracking transactions: ${error.message}</p>`;
        console.error("Fetch error:", error);
    });
}
