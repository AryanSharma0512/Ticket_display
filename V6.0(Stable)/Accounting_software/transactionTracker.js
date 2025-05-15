function trackTransaction() {
    const transactionID = document.getElementById('transactionID').value;
    const outputContainer = document.getElementById('outputContainer');

    outputContainer.innerHTML = "<p>Loading transaction details...</p>";

    fetch('track_transaction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `transactionID=${transactionID}`
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.error) {
            console.error('Error:', data.error);
            outputContainer.innerHTML = `<p>Error: ${data.error}</p>`;
        } else {
            console.log('Success:', data);

            let resultsHTML = "";

            // Function to generate table rows, excluding specified fields
            function generateTableRows(data, tableName, excludedFields, isAccNetwork) { // Added isAccNetwork parameter
                let tableHTML = "";
                if (data && data.length > 0) {
                    const containerClass = isAccNetwork ? "acc-network-table-container" : "transaction-table-container"; // Choose container class
                    const tableClass = isAccNetwork ? "acc-network-table" : "transaction-table"; // Choose table class

                    tableHTML += `<h3>${tableName} Results:</h3><div class="${containerClass}"><table border='1' class="${tableClass}">`; // Use chosen classes
                    tableHTML += "<tr>";

                    // Get the headers dynamically, excluding specified fields
                    const firstRow = data[0];
                    for (const key in firstRow) {
                        if (!excludedFields.includes(key)) {
                            tableHTML += `<th>${key}</th>`;
                        }
                    }
                    tableHTML += "</tr>";

                    data.forEach(row => {
                        tableHTML += "<tr>";
                        for (const key in row) {
                            if (!excludedFields.includes(key)) {
                                tableHTML += `<td>${row[key]}</td>`;
                            }
                        }
                        tableHTML += "</tr>";
                    });

                    tableHTML += "</table></div>"; // Close table and container
                } else {
                    tableHTML += `<p>No results found in ${tableName}.</p>`;
                }
                return tableHTML;
            }

            const excludedMainFields = [
                "net_total_profit_from_customer",
                "net_total_collectable",
                "net_amount_received",
                "total_collection_due",
                "net_total_profit",
                "net_margin",
                "net_total_collectable_from_customer",
                "net_amount_received_from_customer",
                "net_total_collectable_due_from_customer"
            ];

            const excludedAccNetworkFields = [
                "net_amount_used_from_customer",
                "net_amount_used",
                "net_amount_paid_to_customer",
                "net_amount_paid",
                "total_debt_due_to_customer",
                "total_debt_due",
            ];


            resultsHTML += generateTableRows(data.main_table, "Main Table", excludedMainFields, false); // false for isAccNetwork
            resultsHTML += generateTableRows(data.acc_network_main, "Accounts Network", excludedAccNetworkFields, true); // true for isAccNetwork

            outputContainer.innerHTML = resultsHTML;

        }
    })
    .catch(error => {
        console.error('Error tracking transaction:', error.message);
        outputContainer.innerHTML = `<p>Error tracking transaction: ${error.message}</p>`;
    });
}