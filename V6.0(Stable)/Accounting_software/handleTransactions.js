// Function to handle displaying last 10 transactions
function fetchLastTenTransactions() {
    const outputContainer = document.getElementById("outputContainer");
    outputContainer.innerHTML = '<p>Loading last 10 transactions...</p>';

    fetch("fetch_last_ten_transactions.php")
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                outputContainer.innerHTML = `<p>Error: ${data.error}</p>`;
                return;
            }
            if (data.transactions.length === 0) {
                outputContainer.innerHTML = '<p>No transactions found.</p>';
                return;
            }

            // Display transactions
            let html = `<h2>Last 10 Transactions</h2>
                        <div style="display: flex; justify-content: space-between;">
                          <table border="1">
                            <caption>Main Table Transactions</caption>
                            <tr>
                              <th>Transaction ID</th>
                              <th>Customer ID</th>
                              <th>Customer Name</th>
                              <th>Amount</th>
                              <th>Date</th>
                            </tr>`;
            data.transactions.mainTable.forEach(tx => {
                html += `<tr>
                            <td>${tx.transaction_id}</td>
                            <td>${tx.customer_id}</td>
                            <td>${tx.customer_name}</td>
                            <td>${tx.bill_amount}</td>
                            <td>${tx.entry_date}</td>
                          </tr>`;
            });
            html += `</table><table border="1">
                      <caption>Account Network Main Transactions</caption>
                      <tr>
                        <th>Transaction ID</th>
                        <th>Account Holder</th>
                        <th>Amount</th>
                        <th>Date</th>
                      </tr>`;
            data.transactions.accNetworkMain.forEach(tx => {
                html += `<tr>
                            <td>${tx.transaction_id}</td>
                            <td>${tx.account_holder}</td>
                            <td>${tx.amount_used}</td>
                            <td>${tx.entry_date}</td>
                          </tr>`;
            });
            html += `</table></div>`;

            outputContainer.innerHTML = html;
        })
        .catch(error => {
            outputContainer.innerHTML = `<p>Error loading transactions: ${error.message}</p>`;
        });
}
