// ✅ Function to update the hidden field when checkbox is toggled
function updateUnsettledValue() {
    const checkbox = document.getElementById("unsettledOnly");
    const hiddenField = document.getElementById("unsettledOnlyHidden");

    hiddenField.value = checkbox.checked ? "1" : "0"; // ✅ Ensure correct value
}

// ✅ Function to fetch customer statement correctly
function fetchCustomerStatement() {
    const customerID = document.getElementById('customerID').value;
    const unsettledOnly = document.getElementById('unsettledOnlyHidden').value; // ✅ Correct value

    if (!customerID) {
        alert("❌ Please enter a Customer ID before generating the statement.");
        return;
    }

    console.log(`✅ Fetching with: customerID=${customerID}, unsettledOnly=${unsettledOnly}`);

    fetch('fetch_customer_statement.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `customerID=${encodeURIComponent(customerID)}&unsettledOnly=${unsettledOnly}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            document.getElementById('outputContainer').innerHTML = `<p>Error: ${data.error}</p>`;
            return;
        }

        // ✅ Generate the table with transaction data
        let resultsHTML = `<div class='transaction-table-container'>` + 
            generateTable(data, "transaction-table", "Customer Statement") + `</div>`;
        document.getElementById('outputContainer').innerHTML = resultsHTML;

        document.getElementById('exportPdfCustomer').style.display = "block";
    })
    .catch(error => {
        document.getElementById('outputContainer').innerHTML = `<p>Error fetching customer statement: ${error.message}</p>`;
    });
}

// ✅ Function to export customer statement as PDF
function exportCustomerStatement() {
    const customerID = document.getElementById('customerID').value;
    const unsettledOnly = document.getElementById('unsettledOnlyHidden').value; // ✅ Use hidden field

    if (!customerID) {
        alert("❌ Please enter a Customer ID before exporting.");
        return;
    }

    console.log(`📄 Exporting PDF with: customerID=${customerID}, unsettledOnly=${unsettledOnly}`);

    window.location.href = `export_customer_statement.php?customerID=${encodeURIComponent(customerID)}&unsettledOnly=${unsettledOnly}`;
}

// ✅ Generate the transactions table with proper formatting
function generateTable(data, tableClass, title) {
    let tableHTML = `<h2>${title}</h2>
        <table class='${tableClass}'>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Transaction ID</th>
                    <th>Product</th>
                    <th>Price (INR)</th>
                    <th>Qty</th>
                    <th>Bill Amount (INR)</th>
                    <th>Amount Received (INR)</th>
                    <th>Entry Date</th>
                </tr>
            </thead>
            <tbody>`;

    // Loop through transactions and create table rows
    data.transactions.forEach((transaction, index) => {
        tableHTML += `<tr>
            <td>${index + 1}</td>
            <td>${transaction.transaction_id}</td>
            <td>${transaction.product}</td>
            <td>${parseFloat(transaction.price).toFixed(2)}</td>
            <td>${transaction.quantity}</td>
            <td>${parseFloat(transaction.bill_amount).toFixed(2)}</td>
            <td>${parseFloat(transaction.amount_received).toFixed(2)}</td>
            <td>${transaction.entry_date}</td>
        </tr>`;
    });

    // Add the total due amount row
    tableHTML += `</tbody>
        <tfoot>
            <tr class='total-due'>
                <td colspan='7'>Due from Customer:</td>
                <td><strong>${parseFloat(data.due_from_customer || 0).toFixed(2)} INR</strong></td>
            </tr>
        </tfoot>
    </table>`;

    return tableHTML;
}

// ✅ Apply styling for table aesthetics
document.addEventListener("DOMContentLoaded", () => {
    const style = document.createElement('style');
    style.innerHTML = `
        .transaction-table-container {
            margin-top: 20px;
            text-align: center;
        }
        .transaction-table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
        }
        .transaction-table th, .transaction-table td {
            border: 2px solid #000;
            padding: 8px;
            text-align: center;
        }
        .transaction-table th {
            background-color: #004080;
            color: #fff;
        }
        .total-due {
            background-color: #FFD700;
            font-weight: bold;
        }
    `;
    document.head.appendChild(style);
});

// ✅ Ensure functions are available globally
window.updateUnsettledValue = updateUnsettledValue;
window.fetchCustomerStatement = fetchCustomerStatement;
window.exportCustomerStatement = exportCustomerStatement;