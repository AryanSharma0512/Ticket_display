const express = require('express');
const fs = require('fs');
const bodyParser = require('body-parser');

const app = express();
const port = 3002;
const ipAddress = '100.69.173.223';

app.use(express.static(__dirname));
app.use(bodyParser.urlencoded({ extended: true }));

// Function to format date
function formatDate(dateStr) {
    const options = { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' };
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', options);
}

// Function to format date1
function formatDate1(dateStr) {
    const options = { year:"numeric", month: 'numeric', day: 'numeric' };
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', options);
}

// Function to calculate number of nights between two dates
function calculateNights(checkinDate, checkoutDate) {
    const checkin = new Date(checkinDate);
    const checkout = new Date(checkoutDate);
    const timeDiff = checkout - checkin;
    const nights = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
    return nights;
}

// Serve Options form.html when root path is requested
app.get('/', (req, res) => {
    res.sendFile(__dirname + '/Hotel_opt_form.html');
});

// Handle form submission to update hotel options.html
app.post('/submit-options', (req, res) => {
    const {
        numOptions,
        Hname,
        checkinDates,
        checkoutDates,
        roomno,
        flightStops,
        airlineNames,
        flightFares
    } = req.body;

    // Read baseHoptions.html as initial content
    let baseHOptionsHtml = fs.readFileSync(__dirname + '/baseHoptions.html', 'utf8');

    // Generate HTML for Hotel options dynamically
    let optionsHtml = '';
    for (let i = 0; i < numOptions; i++) {
        const formattedCheckinDate = formatDate(checkinDates[i]);
        const formattedCheckinnDate = formatDate1(checkinDates[i]);
        const formattedCheckouttDate = formatDate1(checkoutDates[i]);
        const nights = calculateNights(checkinDates[i], checkoutDates[i]);

        optionsHtml += `
            <div class="option">
                <h2>Option ${i + 1} : ${Hname[i]}</h2>
                <p><strong>Date of Checkin:</strong> ${formattedCheckinDate}</p>
                <div class="cities">
                    <span class="from">From:</span><span class="city"> ${formattedCheckinnDate}</span>
                    <img src="elements/line.png" alt="line" class="line-icon"><img src="elements/line.png" alt="line" class="line-icon">
                    <span class="to">To: </span><span class="city">${formattedCheckouttDate}</span>
                </div>
                <br>
                <p><strong>Number Of Adults:</strong> ${flightStops[i]}</p>
                <p><strong>Hotel Fare:</strong> ${flightFares[i]} <strong>INR</strong> (For ${roomno[i]} Room(s), ${nights} Night(s) - Inclusive of all taxes and charges.)</p>
                <p><strong>Services Included:</strong> ${airlineNames[i]}</p>
            </div>
        `;
    }

    // Replace the placeholder in baseHoptions.html with actual Hotel options
    let data = baseHOptionsHtml.replace('[Flight Options]', optionsHtml);

    // Write the updated HTML content to Hotel options.html
    fs.writeFile(__dirname + '/Hotel options.html', data, (err) => {
        if (err) {
            console.error('Error writing file:', err);
            return res.status(500).send('Error writing file');
        }
        // Redirect to the Hotel options.html page after successful update
        res.redirect('/Hotel options.html');
    });
});

// Handle GET request to revert options to base options
app.get('/revert-options', (req, res) => {
  // Read baseHoptions.html and write it to Hotel options.html
  let baseHOptionsHtml = fs.readFileSync(__dirname + '/baseHoptions.html', 'utf8');
  fs.writeFile(__dirname + '/Hotel options.html', baseHOptionsHtml, (err) => {
    if (err) {
      console.error('Error writing file:', err);
      return res.status(500).send('Error writing file');
    }
    res.send('Hotel options reverted successfully!');
  });
});

// Start the server
app.listen(port, ipAddress, () => {
  console.log(`Server is running on http://${ipAddress}:${port}`);
});
