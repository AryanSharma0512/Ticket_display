const express = require('express');
const fs = require('fs');
const bodyParser = require('body-parser');

const app = express();
const port = 3001;
const ipAddress = '192.168.1.142';

app.use(express.static(__dirname));
app.use(bodyParser.urlencoded({ extended: true }));

// Function to format date
function formatDate(dateStr) {
    const options = { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' };
    const date = new Date(dateStr + 'T00:00:00'); // Ensure the date is parsed correctly
    const formattedDate = date.toLocaleDateString('en-IN', options);
    return formattedDate;
  }

// Serve Options form.html when root path is requested
app.get('/', (req, res) => {
  res.sendFile(__dirname + '/Options form.html');
});



// Handle form submission to update flight options.html
app.post('/submit-options', (req, res) => {
  const {
    numOptions,
    dates,
    fromCities,
    toCities,
    flightStops,
    airlineNames,
    flightFares,
    departureTimes,
    flightTimes,
    arrivalTimes
  } = req.body;

  // Read base options.html as initial content
  let baseOptionsHtml = fs.readFileSync(__dirname + '/base options.html', 'utf8');


  // Generate HTML for flight options dynamically
  let optionsHtml = '';
  for (let i = 0; i < numOptions; i++) {

    const formattedDate = formatDate(dates[i]);

    optionsHtml += `
      <div class="option">
        <h2>Option ${i + 1}</h2>
        <p><strong>Date of Journey:</strong> ${formattedDate}</p>
        <div class="cities">
          <span class="from">From:</span><span class="city"> ${fromCities[i]}</span>
          <img src="elements/line.png" alt="line" class="line-icon"><img src="elements/plane.png" alt="Plane" class="plane-icon"><img src="elements/line.png" alt="line" class="line-icon">
          <span class="to">To: </span><span class="city">${toCities[i]}</span>
        </div>
        <p><span class="time">&emsp;&emsp;&ensp;${departureTimes[i]}</span>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&ensp;&emsp;&emsp;&emsp;<span class="ftime">${flightTimes[i]}</span>&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;<span class="time">${arrivalTimes[i]}</span></p>
        <br><br>
        <p><strong>Number Of Stops:</strong> ${flightStops[i]}</p>
        <p><strong>Airline Name:</strong> ${airlineNames[i]}</p>
        <p><strong>Flight Fare:</strong> ${flightFares[i]} <strong>INR</strong> (Per Passenger)</p>
      </div>
    `;
  }

  // Replace the placeholder in base options.html with actual flight options
  let data = baseOptionsHtml.replace('[Flight Options]', optionsHtml);

  // Write the updated HTML content to flight options.html
  fs.writeFile(__dirname + '/flight options.html', data, (err) => {
    if (err) {
      console.error('Error writing file:', err);
      return res.status(500).send('Error writing file');
    }
    // Redirect to the flight options.html page after successful update
    res.redirect('/flight options.html');
  });
});

// Handle GET request to revert options to base options
app.get('/revert-options', (req, res) => {
  // Read base options.html and write it to flight options.html
  let baseOptionsHtml = fs.readFileSync(__dirname + '/base options.html', 'utf8');
  fs.writeFile(__dirname + '/flight options.html', baseOptionsHtml, (err) => {
    if (err) {
      console.error('Error writing file:', err);
      return res.status(500).send('Error writing file');
    }
    res.send('Flight options reverted successfully!');
  });
});

// Start the server
app.listen(port, ipAddress, () => {
  console.log(`Server is running on http://${ipAddress}:${port}`);
});