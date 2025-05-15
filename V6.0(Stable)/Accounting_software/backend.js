// Function to fetch customer names and populate dropdowns
function populateCustomers() {
  var accountDetailsSelect = document.getElementById("account_details");

  // Use AJAX to fetch customer names from a PHP script
  var xhr = new XMLHttpRequest();
  xhr.open("GET", "get_customers.php", true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      var customers = JSON.parse(xhr.responseText);
      console.log(customers); // Check if customers array is correctly populated

      // Clear existing options
      accountDetailsSelect.innerHTML = '';

      // Add default option
      var defaultOption = document.createElement("option");
      defaultOption.text = "Select a customer";
      accountDetailsSelect.appendChild(defaultOption);

      // Add options for each customer
      customers.forEach(function(customer) {
        var option = document.createElement("option");
        option.value = customer;
        option.text = customer;
        accountDetailsSelect.appendChild(option);
      });
    } else {
      console.error("Error fetching customers:", xhr.statusText);
    }
  };
  xhr.send();
}

// Call populateCustomers function on page load
populateCustomers();

// Function to show account details
function showAccountDetails() {
  var customerName = document.getElementById("customer_name").value.trim();
  var detailsContainer = document.getElementById("account_details_container");

  if (customerName === "") {
      detailsContainer.innerHTML = "Please enter a customer name.";
      return;
  }

  // Use AJAX to fetch and display account details
  var xhr = new XMLHttpRequest();
  xhr.open("GET", "get_account_details.php?customer=" + encodeURIComponent(customerName), true);
  xhr.onload = function() {
      if (xhr.status === 200) {
          detailsContainer.innerHTML = xhr.responseText;
      } else {
          detailsContainer.innerHTML = "Error fetching account details: " + xhr.statusText;
      }
  };
  xhr.send();

}