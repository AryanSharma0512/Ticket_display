function searchEventID() {
    const eventID = document.getElementById('eventID').value;
    const outputContainer = document.getElementById('outputContainer');

    outputContainer.innerHTML = "<p>Searching for transactions related to Event ID...</p>";

    fetch('search_event.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `eventID=${eventID}`
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

            if (data.main_table.length > 0) {
                resultsHTML += "<div class='transaction-table-container'>";
                resultsHTML += "<h3>Main Table Results:</h3><table border='1' class='transaction-table'>";
                resultsHTML += "<tr><th>Transaction ID</th><th>Customer ID</th><th>Customer Name</th><th>Product</th><th>Price</th><th>Quantity</th><th>Bill Amount</th><th>Our Cost</th><th>Profit</th><th>Margin</th><th>Channel</th><th>Amount Received</th><th>Entry Date</th></tr>";

                data.main_table.forEach(row => {
                    resultsHTML += `<tr><td>${row.transaction_id}</td><td>${row.customer_id}</td><td>${row.customer_name}</td><td>${row.product}</td><td>${row.price}</td><td>${row.quantity}</td><td>${row.bill_amount}</td><td>${row.our_cost}</td><td>${row.profit}</td><td>${row.margin}</td><td>${row.channel}</td><td>${row.amount_received}</td><td>${row.entry_date}</td></tr>`;
                });

                resultsHTML += "</table>";
                resultsHTML += "</div>";
            } else {
                resultsHTML += "<p>No transactions found in Main Table.</p>";
            }

            if (data.acc_network_main.length > 0) {
                resultsHTML += "<div class='acc-network-table-container'>";
                resultsHTML += "<h3>Accounts Network Results:</h3><table border='1' class='acc-network-table'>";
                resultsHTML += "<tr><th>Transaction ID</th><th>Customer ID</th><th>Account Holder</th><th>Product Category</th><th>Amount Used</th><th>Amount Paid</th><th>Entry Date</th><th>Channel</th></tr>";

                data.acc_network_main.forEach(row => {
                    resultsHTML += `<tr><td>${row.transaction_id}</td><td>${row.customer_id}</td><td>${row.account_holder}</td><td>${row.product_category}</td><td>${row.amount_used}</td><td>${row.amount_paid}</td><td>${row.entry_date}</td><td>${row.channel}</td></tr>`;
                });

                resultsHTML += "</table>";
                resultsHTML += "</div>";
            } else {
                resultsHTML += "<p>No transactions found in Accounts Network Table.</p>";
            }

            outputContainer.innerHTML = resultsHTML;
        }
    })
    .catch(error => {
        console.error('Error searching event:', error.message);
        outputContainer.innerHTML = `<p>Error searching event: ${error.message}</p>`;
    });
}