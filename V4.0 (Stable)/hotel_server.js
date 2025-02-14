const express = require('express');
const fs = require('fs');
const bodyParser = require('body-parser');
const multer = require('multer');
const pdf = require('html-pdf');
const path = require('path');

const app = express();
const port = 3000;
const ipAddress = '192.168.29.153';

app.use(express.static(__dirname));
app.use(bodyParser.urlencoded({ extended: true }));


const upload = multer({ dest: 'uploads/' });

app.use(express.static('public'));
const baseHotelHTML = fs.readFileSync(__dirname + '/Base hotel.html', 'utf8');

app.get('/', (req, res) => {
  res.sendFile(__dirname + '/hotel_form.html');
});



function parseDepartureDate(dateString) {
  const date = new Date(dateString);
  const options = { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}

app.post('/update-ticket-info', upload.array('barcodes'), (req, res) => { 
  const {
    tripId,
    numPassengers,
    passengerNames,
    ticketNumbers,
    originCity,
    destinationCity,
    noofrooms,
    departureDate,
    airlineName,
    flightNumber,
    departureAirportCode,
    departureTime,
    arrivalAirportCode,
    arrivalTime,
    airlinePNR,
    baseFare,
    discountsCashbacks,
    taxesFees,
    gstAirline,
    totalFare
  } = req.body;

  const barcodes = req.files.map(file => file.path);

  fs.readFile(__dirname + '/hotel.html', 'utf8', (err, data) => {
    if (err) {
      console.error('Error reading file:', err);
      return res.status(500).send('Error reading file');
    }

    // Replace placeholders with actual data
    data = data.replace(/\[Trip ID\]/g, tripId)
               .replace(/\[Origin City\]/g, originCity)
               .replace(/\[Destination City\]/g, destinationCity)
               .replace(/\[No of Rooms\]/g, noofrooms)
               .replace(/\[Day, Departure Date\]/g, parseDepartureDate(departureDate))
               .replace(/\[Airline Name\]/g, airlineName)
               .replace(/\[Flight Number\]/g, flightNumber)
               .replace(/\[Departure Airport Code\]/g, departureAirportCode)
               .replace(/\[Departure Time\]/g, departureTime)
               .replace(/\[Arrival Airport Code\]/g, arrivalAirportCode)
               .replace(/\[Arrival Time\]/g, arrivalTime)
               .replace(/\[Base Fare\]/g, baseFare)
               .replace(/\[Discounts And Cashbacks\]/g, discountsCashbacks)
               .replace(/\[Taxes and Fees\]/g, taxesFees)
               .replace(/\[GST\(Airline\)\]/g, gstAirline)
               .replace(/\[Total Fare\]/g, totalFare);

    // Generate HTML for passenger details table
    let passengerDetailsHtml = '';
     for (let i = 0; i < numPassengers; i++) {
       passengerDetailsHtml += `
         <tr>
           <td>${passengerNames[i]}</td>
           <td><img src="${barcodes[i]}" alt="Barcode for passenger ${i + 1}" style="width: 100px;"></td> <!-- Corrected line -->
           <td>${airlinePNR}</td>
           <td>${ticketNumbers[i]}</td>
         </tr>
       `;
      }

    // Find and replace the passenger details table content
    const tableStartIndex = data.indexOf('<tbody id="passenger-details-placeholder">');
    const tableEndIndex = data.indexOf('</tbody>', tableStartIndex);
    if (tableStartIndex !== -1 && tableEndIndex !== -1) {
      const updatedHtml = data.slice(0, tableStartIndex) +
                          passengerDetailsHtml +
                          data.slice(tableEndIndex + '</tbody>'.length);

      // Write the updated HTML content to the hotel.html file
      fs.writeFile(__dirname + '/hotel.html', updatedHtml, (err) => {
        if (err) {
          console.error('Error writing file:', err);
          return res.status(500).send('Error writing file');
        }
        // Redirect to the hotel.html page after successful update
        res.redirect('/hotel.html');
      });
    } else {
      console.error('Error: Passenger details table not found in HTML');
      return res.status(500).send('Error: Passenger details table not found in HTML');
    }
  });
}); 


// Route to revert ticket information to default
app.get('/revert-ticket-info', (req, res) => {
  // Read the content of Base hotel.html and write it to hotel.html
  fs.readFile(__dirname + '/Base hotel.html', 'utf8', (err, data) => {
    if (err) {
      console.error('Error reading file:', err);
      return res.status(500).send('Error reading file');
    }

    // Write the content to hotel.html
    fs.writeFile(__dirname + '/hotel.html', data, (err) => {
      if (err) {
        console.error('Error writing file:', err);
        return res.status(500).send('Error writing file');
      }
      res.send('Reservation information reverted successfully!');
    });
  });
});

app.listen(port, ipAddress, () => {
  console.log(`Server is running on http://${ipAddress}:${port}`);
});