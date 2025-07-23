// Fetch and display back-end customer statement
function fetchBackendCustomerStatement() {
    const name = document.getElementById('backendAccountHolder').value.trim();
    if (!name) {
        alert('Please enter an Account Holder name.');
        return;
    }

    const outputContainer = document.getElementById('outputContainer');
    outputContainer.innerHTML = '<p>Loading statement...</p>';

    fetch('fetch_backend_customer_statement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `accountHolder=${encodeURIComponent(name)}`
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.error) {
            outputContainer.innerHTML = `<p>Error: ${data.error}</p>`;
            return;
        }
        outputContainer.innerHTML = generateBackendTable(data);
        document.getElementById('exportPdfBackendCustomer').style.display = 'block';
    })
    .catch(err => {
        outputContainer.innerHTML = `<p>Error fetching statement: ${err.message}</p>`;
    });
}

function exportBackendCustomerStatement() {
    const name = document.getElementById('backendAccountHolder').value.trim();
    if (!name) {
        alert('Please enter an Account Holder name before exporting.');
        return;
    }
    window.location.href = `export_backend_customer_statement.php?accountHolder=${encodeURIComponent(name)}`;
}

function generateBackendTable(data) {
    let running = 0;
    let html = `<table class='transaction-table'>` +
               `<thead><tr>` +
               `<th>#</th><th>Transaction ID</th><th>Category</th>` +
               `<th>Amount Used (INR)</th><th>Amount Paid (INR)</th>` +
               `<th>Entry Date</th><th>Balance (INR)</th>` +
               `</tr></thead><tbody>`;

    data.transactions.forEach((tx, idx) => {
        const used = parseFloat(tx.amount_used);
        const paid = parseFloat(tx.amount_paid);
        running += used - paid;
        html += `<tr>` +
                 `<td>${idx + 1}</td>` +
                 `<td>${tx.transaction_id}</td>` +
                 `<td>${tx.product_category}</td>` +
                 `<td>${used.toFixed(2)}</td>` +
                 `<td>${paid.toFixed(2)}</td>` +
                 `<td>${tx.entry_date}</td>` +
                 `<td>${running.toFixed(2)}</td>` +
                 `</tr>`;
    });

    html += `</tbody><tfoot>` +
            `<tr class='total-due'>` +
            `<td colspan='3'>Totals</td>` +
            `<td>${parseFloat(data.total_used).toFixed(2)}</td>` +
            `<td>${parseFloat(data.total_paid).toFixed(2)}</td>` +
            `<td></td>` +
            `<td>${(parseFloat(data.total_used) - parseFloat(data.total_paid)).toFixed(2)}</td>` +
            `</tr></tfoot></table>`;
    return html;
}

window.fetchBackendCustomerStatement = fetchBackendCustomerStatement;
window.exportBackendCustomerStatement = exportBackendCustomerStatement;
