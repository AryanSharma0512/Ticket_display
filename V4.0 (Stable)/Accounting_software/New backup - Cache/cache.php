function trackTransaction() {
    const transactionID = document.getElementById('transactionID').value;
    fetch('your_php_script.php', {
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
        } else {
            console.log('Success:', data);
            // Process your data here...
        }
    })
    .catch(error => {
        console.error('Error tracking transaction:', error.message);
    });
}