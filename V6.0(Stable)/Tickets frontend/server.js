/*************************************************************
 * server.js
 *************************************************************/
const express    = require('express');
const fs         = require('fs');
const bodyParser = require('body-parser');
const multer     = require('multer');
const path       = require('path');
const mysql      = require('mysql2');  // Make sure you installed mysql2

const app        = express();
const port       = 3000;
const ipAddress  = '192.168.1.117';

// --------------------- 1) DATABASE CONNECTION ---------------------
// Primary Flights DB (holds flight_info)
const db = mysql.createConnection({
  host:     'localhost',
  user:     'root',       // Default MySQL user
  password: '',           // Default MySQL password (often empty)
  database: 'Flights'     // Adjust if your DB name differs
});
db.connect((err) => {
  if (err) {
    console.error('Database connection error:', err);
  } else {
    console.log('Connected to the Flights database.');
  }
});

// Additional Passenger_details DB
const pdDb = mysql.createConnection({
  host:     'localhost',
  user:     'root',
  password: '',
  database: 'Passenger_details'
});
pdDb.connect((err) => {
  if (err) {
    console.error('Passenger_details DB connection error:', err);
  } else {
    console.log('Connected to the Passenger_details database.');
  }
});

// --------------------- 2) MIDDLEWARE SETUP ------------------------
app.use(express.static(__dirname));
app.use(bodyParser.urlencoded({ extended: true }));

// For handling barcode file uploads
const upload = multer({ dest: 'uploads/' });
app.use(express.static('public'));

// Read base ticket HTML (for revert)
const baseTicketHTML = fs.readFileSync(path.join(__dirname, 'Base ticket.html'), 'utf8');

// --------------------- 3) HELPER FUNCTIONS ------------------------

// Parse a date string for display in ticket.html
function parseDepartureDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString + 'T00:00:00'); // Append time to avoid timezone issues
  const options = { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}

// Format a time string to HH:MM (ignoring seconds)
function formatTimeToHHMM(timeString) {
  if (!timeString) return '';
  const [hh, mm] = timeString.split(':');
  const hours   = hh || '00';
  const minutes = mm || '00';
  return `${hours}:${minutes}`;
}

// Normalize dash-like characters to the standard ASCII hyphen
function unifyDashes(str) {
  if (!str) return str;
  // Replace common Unicode dash variants with '-'
  return str.replace(/[\u2010\u2011\u2012\u2013\u2014\u2015]/g, '-');
}

// --------------------- 4) HOME PAGE (FORM) ------------------------
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'form.html'));
});

// --------------------- 5) UPDATE TICKET INFO ----------------------
app.post('/update-ticket-info', upload.array('barcodes'), (req, res) => {
  // Extract form fields
  const {
    // For placeholders only (NOT stored in DB):
    tripId,
    departureDate,
    airlinePNR,
    separatePnrs,
    baseFare,
    discountsCashbacks,
    taxesFees,
    gstAirline,
    totalFare,

    // For both placeholders AND DB:
    flightNumber,
    airlineName,
    originCity,
    destinationCity,
    departureAirportCode,
    flightDuration,
    arrivalAirportCode,
    baggageCheckinWeight,
    baggageCheckinCount,
    baggageCabinWeight,
    isDomestic,

    // Times to be stored in DB with no seconds:
    departureTime,
    arrivalTime,

    // Passengers
    numPassengers,
    passengerNames,
    ticketNumbers,
    // New: Date Change
    dateChange,
    dateChangeNumber,
    // New field:
    transactionId
  } = req.body;

  // Convert checkbox "on" to TINYINT(1)
  const isDomesticVal = isDomestic === 'on' ? 1 : 0;
  // Determine date_changed_by: if checkbox is not checked assign 0, else use input (or 0 if empty)
  const dateChangedBy = dateChange === 'on' ? (parseInt(dateChangeNumber, 10) || 0) : 0;

  const pnrInput = airlinePNR;
  const pnrArray = Array.isArray(pnrInput) ? pnrInput : null;

  // Format times so MySQL stores only HH:MM
  const finalDepartureTime = formatTimeToHHMM(departureTime);
  const finalArrivalTime   = formatTimeToHHMM(arrivalTime);

  // Gather barcodes
  const barcodes = req.files.map(file => file.path);

  // ------------------ 5a) Update ticket.html placeholders ------------------
  fs.readFile(path.join(__dirname, 'ticket.html'), 'utf8', (err, data) => {
    if (err) {
      console.error('Error reading ticket.html:', err);
      return res.status(500).send('Error reading ticket.html');
    }

    // Replace placeholders in ticket.html
    let updatedData = data
      .replace(/\[Trip ID\]/g, tripId || '')
      .replace(/\[Origin City\]/g, originCity || '')
      .replace(/\[Destination City\]/g, destinationCity || '')
      .replace(/\[Day, Departure Date\]/g, parseDepartureDate(departureDate))
      .replace(/\[Airline Name\]/g, airlineName || '')
      .replace(/\[Flight Number\]/g, flightNumber || '')
      .replace(/\[Departure Airport Code\]/g, departureAirportCode || '')
      .replace(/\[Departure Time\]/g, finalDepartureTime || '')
      .replace(/\[Flight Duration\]/g, flightDuration || '')
      .replace(/\[Arrival Airport Code\]/g, arrivalAirportCode || '')
      .replace(/\[Arrival Time\]/g, finalArrivalTime || '')
      // Baggage placeholders
      .replace(/\[Baggage Checkin Weight\]/g, baggageCheckinWeight || '0')
      .replace(/\[Baggage Checkin Count\]/g, baggageCheckinCount || '0')
      .replace(/\[Baggage Cabin Weight\]/g, baggageCabinWeight || '0')
      // Fare placeholders (not in DB)
      .replace(/\[Base Fare\]/g, baseFare || '0')
      .replace(/\[Discounts And Cashbacks\]/g, discountsCashbacks || '0')
      .replace(/\[Taxes and Fees\]/g, taxesFees || '0')
      .replace(/\[GST\(Airline\)\]/g, gstAirline || '0')
      .replace(/\[Total Fare\]/g, totalFare || '0');

    // --- NEW: Inject date-change placeholder using regex ---
    // Use his approach to inject the "+x" if dateChangedBy is greater than 0
    const dateChangeSpanRegex = /<span[^>]*id="date-change-placeholder"[^>]*>[\s\S]*?<\/span>/;
    const replacementSpan = dateChangedBy > 0
     ? `<span id="date-change-placeholder">+${dateChangedBy} Day</span>`
     : `<span id="date-change-placeholder"></span>`;
    updatedData = updatedData.replace(dateChangeSpanRegex, replacementSpan);

    // Generate passenger details rows
    let passengerDetailsHtml = '';
    for (let i = 0; i < numPassengers; i++) {
      const pnrVal = pnrArray ? (pnrArray[i] || '') : (pnrInput || '');
      passengerDetailsHtml += `
        <tr>
          <td>${passengerNames[i]}</td>
          <td><img src="${barcodes[i] || ''}" alt="Barcode for passenger ${i + 1}" style="width: 100px;"></td>
          <td>${pnrVal}</td>
          <td>${ticketNumbers[i]}</td>
        </tr>
      `;
    }

    // Find and replace the passenger details table content
    const tableStartIndex = updatedData.indexOf('<tbody id="passenger-details-placeholder">');
    const tableEndIndex = updatedData.indexOf('</tbody>', tableStartIndex);
    if (tableStartIndex === -1 || tableEndIndex === -1) {
      console.error('Error: Passenger details placeholder not found in ticket.html');
      return res.status(500).send('Passenger details placeholder not found.');
    }
    updatedData =
      updatedData.slice(0, tableStartIndex) +
      passengerDetailsHtml +
      updatedData.slice(tableEndIndex + '</tbody>'.length);

    // Write the updated HTML to ticket.html
    fs.writeFile(path.join(__dirname, 'ticket.html'), updatedData, (err) => {
      if (err) {
        console.error('Error writing updated ticket.html:', err);
        return res.status(500).send('Error writing updated ticket.html');
      }

      // ------------------ 5b) Insert/Update in flight_info DB (Primary) ------------------
      const flightSql = `
        INSERT INTO \`flight_info\` (
          flight_number,
          airline_name,
          origin_city,
          destination_city,
          departure_airport_code,
          departure_time,
          flight_duration,
          arrival_airport_code,
          arrival_time,
          baggage_checkin_weight,
          baggage_checkin_count,
          baggage_cabin_weight,
          is_domestic,
          date_changed_by
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          airline_name = VALUES(airline_name),
          origin_city = VALUES(origin_city),
          destination_city = VALUES(destination_city),
          departure_airport_code = VALUES(departure_airport_code),
          departure_time = VALUES(departure_time),
          flight_duration = VALUES(flight_duration),
          arrival_airport_code = VALUES(arrival_airport_code),
          arrival_time = VALUES(arrival_time),
          baggage_checkin_weight = VALUES(baggage_checkin_weight),
          baggage_checkin_count = VALUES(baggage_checkin_count),
          baggage_cabin_weight = VALUES(baggage_cabin_weight),
          is_domestic = VALUES(is_domestic),
          date_changed_by = VALUES(date_changed_by)
      `;
      const flightVals = [
        flightNumber || '',
        airlineName || '',
        originCity || '',
        destinationCity || '',
        departureAirportCode || '',
        finalDepartureTime || '00:00',
        flightDuration || '',
        arrivalAirportCode || '',
        finalArrivalTime || '00:00',
        parseInt(baggageCheckinWeight, 10) || 0,
        parseInt(baggageCheckinCount, 10) || 0,
        parseInt(baggageCabinWeight, 10) || 0,
        isDomesticVal,
        dateChangedBy
      ];

      db.query(flightSql, flightVals, (flightErr) => {
        if (flightErr) {
          console.error('Database error while saving flight info:', flightErr);
        }
        
        // ------------------ 5c) Insert/Update in passenger_details DB ------------------
        // Convert arrays to JSON strings
        const namesJson = JSON.stringify(passengerNames);
        const etixJson = JSON.stringify(ticketNumbers);
        const pdSql = `
          INSERT INTO \`passenger_details\` (
            Flight_number,
            Passenger_names,
            Eticket_numbers,
            Trip_id,
            Departure_date,
            PNR,
            Transaction_id
          ) VALUES (?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            Passenger_names = VALUES(Passenger_names),
            Eticket_numbers = VALUES(Eticket_numbers),
            Departure_date = VALUES(Departure_date),
            PNR = VALUES(PNR),
            Transaction_id = VALUES(Transaction_id)
        `;
        const pnrDbVal = pnrArray ? JSON.stringify(pnrArray) : (pnrInput || '');
        const pdVals = [
          flightNumber || '',
          namesJson,
          etixJson,
          tripId || '',
          departureDate || '',
          pnrDbVal,
          transactionId || ''
        ];

        pdDb.query(pdSql, pdVals, (pdErr) => {
          if (pdErr) {
            console.error('Database error while saving passenger details:', pdErr);
          }
          res.redirect('/ticket.html');
        });
      });
    });
  });
});

// --------------------- 6) REVERT TICKET INFO -----------------------
app.get('/revert-ticket-info', (req, res) => {
  fs.readFile(path.join(__dirname, 'Base ticket.html'), 'utf8', (err, data) => {
    if (err) {
      console.error('Error reading Base ticket.html:', err);
      return res.status(500).send('Error reading Base ticket.html');
    }
    fs.writeFile(path.join(__dirname, 'ticket.html'), data, (err2) => {
      if (err2) {
        console.error('Error writing ticket.html:', err2);
        return res.status(500).send('Error writing ticket.html');
      }
      res.send('Ticket information reverted successfully!');
    });
  });
});

// --------------------- 7) GET FLIGHT INFO (AJAX) -------------------
app.get('/get-flight-info', (req, res) => {
  const flightNum = req.query.flightNumber;
  if (!flightNum) {
    return res.status(400).json({ found: false, message: 'No flight number provided.' });
  }
  const query = `
    SELECT
      flight_number,
      airline_name,
      origin_city,
      destination_city,
      departure_airport_code,
      departure_time,
      flight_duration,
      arrival_airport_code,
      arrival_time,
      baggage_checkin_weight,
      baggage_checkin_count,
      baggage_cabin_weight,
      is_domestic
    FROM \`flight_info\`
    WHERE flight_number = ?
  `;
  db.query(query, [flightNum], (err, results) => {
    if (err) {
      console.error('Database error in /get-flight-info:', err);
      return res.status(500).json({ found: false, message: 'Database error.' });
    }
    if (results.length > 0) {
      const row = results[0];
      row.departure_time = row.departure_time ? row.departure_time.slice(0, 5) : '';
      row.arrival_time = row.arrival_time ? row.arrival_time.slice(0, 5) : '';
      return res.json({ found: true, ...row });
    } else {
      return res.json({ found: false });
    }
  });
});

// --------------------- 8) SEARCH FLIGHT INFO (AJAX) -------------------
app.get('/search-flight-info', (req, res) => {
  let queryParam = req.query.query;
  if (!queryParam) {
    return res.status(400).json([]);
  }
  
  // Normalize user input dashes
  queryParam = unifyDashes(queryParam);
  
  // Build the LIKE query parameter
  const likeQuery = `%${queryParam}%`;

  // Updated SQL query with an extra clause to also search the flight number with hyphens removed.
  const sql = `
    SELECT 
      flight_number, 
      origin_city, 
      departure_time, 
      destination_city, 
      arrival_time
    FROM \`flight_info\`
    WHERE LOWER(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(flight_number, '‐','-'),
              '‑','-'),
            '–','-'),
          '—','-'),
        '―','-')
    ) LIKE LOWER(?)
       OR LOWER(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(origin_city, '‐','-'),
              '‑','-'),
            '–','-'),
          '—','-'),
        '―','-')
    ) LIKE LOWER(?)
       OR LOWER(
      REPLACE(
        REPLACE(
          REPLACE(
            REPLACE(
              REPLACE(destination_city, '‐','-'),
              '‑','-'),
            '–','-'),
          '—','-'),
        '―','-')
    ) LIKE LOWER(?)
       OR LOWER(SUBSTRING_INDEX(flight_number, '-', -1)) LIKE LOWER(?)
       OR LOWER(REPLACE(flight_number, '-', '')) LIKE LOWER(?)
    LIMIT 10
  `;

  db.query(sql, [likeQuery, likeQuery, likeQuery, likeQuery, likeQuery], (err, results) => {
    if (err) {
      console.error('Error searching flight info:', err);
      return res.status(500).json([]);
    }
    // Strip seconds from time fields
    results.forEach(row => {
      if (row.departure_time) row.departure_time = row.departure_time.slice(0, 5);
      if (row.arrival_time) row.arrival_time = row.arrival_time.slice(0, 5);
    });
    res.json(results);
  });
});

// --------------------- 9) START SERVER -----------------------------
app.listen(port, ipAddress, () => {
  console.log(`Server is running on http://${ipAddress}:${port}`);
});
