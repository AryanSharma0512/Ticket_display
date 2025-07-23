// Fetch and display back-end customer statement
function updateBackendUnsettledValue() {
    const cb = document.getElementById('backendUnsettledOnly');
    const hidden = document.getElementById('backendUnsettledHidden');
    hidden.value = cb.checked ? '1' : '0';
}

function fetchBackendCustomerStatement() {
    const name = document.getElementById('backendAccountHolder').value.trim();
    const fromDate = document.getElementById('backendFromDate').value;
    const toDate = document.getElementById('backendToDate').value;
    const unsettled = document.getElementById('backendUnsettledHidden').value;
    if (!name) {
        alert('Please enter an Account Holder name.');
        return;
    }

    const outputContainer = document.getElementById('outputContainer');
    outputContainer.innerHTML = '<p>Loading statement...</p>';

    fetch('fetch_backend_customer_statement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `accountHolder=${encodeURIComponent(name)}&fromDate=${encodeURIComponent(fromDate)}&toDate=${encodeURIComponent(toDate)}&unsettledOnly=${unsettled}`
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
    const fromDate = document.getElementById('backendFromDate').value;
    const toDate = document.getElementById('backendToDate').value;
    const unsettled = document.getElementById('backendUnsettledHidden').value;
    if (!name) {
        alert('Please enter an Account Holder name before exporting.');
        return;
    }
    window.location.href = `export_backend_customer_statement.php?accountHolder=${encodeURIComponent(name)}&fromDate=${encodeURIComponent(fromDate)}&toDate=${encodeURIComponent(toDate)}&unsettledOnly=${unsettled}`;
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}-${month}-${year}`;
}

function generateBackendTable(data) {
    let running = parseFloat(data.opening_balance);
    let html = `<table class='transaction-table'>` +
               `<thead><tr>` +
               `<th>#</th><th>Entry Date</th><th>TXN ID</th><th>Category</th>` +
               `<th>Amount Used (INR)</th><th>Amount Paid (INR)</th>` +
               `<th>Balance (INR)</th>` +
               `</tr></thead><tbody>`;

    data.transactions.forEach((tx, idx) => {
        const used = parseFloat(tx.amount_used);
        const paid = parseFloat(tx.amount_paid);
        running += used - paid;
        html += `<tr>` +
                 `<td>${idx + 1}</td>` +
                 `<td>${formatDate(tx.entry_date)}</td>` +
                 `<td>${String(tx.transaction_id).slice(-6)}</td>` +
                 `<td>${tx.product_category}</td>` +
                 `<td>${used.toFixed(2)}</td>` +
                 `<td>${paid.toFixed(2)}</td>` +
                 `<td>${running.toFixed(2)}</td>` +
                 `</tr>`;
    });

    const net = running; // running already includes opening balance
    html += `</tbody><tfoot>` +
            `<tr class='total-due'>` +
            `<td colspan='4'>Totals</td>` +
            `<td>${parseFloat(data.total_used).toFixed(2)}</td>` +
            `<td>${parseFloat(data.total_paid).toFixed(2)}</td>` +
            `<td>${net.toFixed(2)}</td>` +
            `</tr></tfoot></table>`;
    return html;
}

window.fetchBackendCustomerStatement = fetchBackendCustomerStatement;
window.exportBackendCustomerStatement = exportBackendCustomerStatement;
window.updateBackendUnsettledValue = updateBackendUnsettledValue;
