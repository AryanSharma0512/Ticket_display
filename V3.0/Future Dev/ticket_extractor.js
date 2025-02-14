const fs = require('fs');

// Read the content of the parsed text file
fs.readFile('parsed_text.txt', 'utf8', (err, data) => {
    if (err) {
        console.error('Error reading parsed text file:', err);
        return;
    }

    // Define the pattern to match
    const patternToMatch = /Trip\s+ID\s+:\s+(\d+)\s+[\w\s]+?(\w+)\s+(\w+)\s+(\w+)\s+((?:-)?\d+),\s+((?:-)?\d+),\s+((?:-)?\d+),\s+((?:-)?\d+),\s+((?:-)?\d+)\s+([\w\s]+)\s+to\s+([\w\s]+)\s+([\w\s]+),\s+(\d+)\s+([\w\s]+)\s+[\w\s]+\s+:\s+(\d+:\d+)(\d+:\d+)\s+([\w\s]+)\s+[\w\s]+\s+:\s+(\d+:\d+)(\d+:\d+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)\s+([\w\s]+)/;

    // Execute the pattern matching on the data
    const matches = data.match(patternToMatch);

    if (!matches) {
        console.error('No matches found in the parsed text');
        return;
    }

    // Extract matched groups
    const [
        _,
        tripId,
        passenger1Title,
        passenger1FirstName,
        passenger1LastName,
        checkInBaggageAdult,
        cabinBaggageAdult,
        checkInBaggageChild,
        cabinBaggageChild,
        checkInBaggageInfant,
        cabinBaggageInfant,
        originCity,
        destinationCity,
        departureDate,
        airlineName,
        flightNumber,
        departureTime,
        layoverDuration,
        arrivalTime,
        airlineName2,
        flightNumber2,
        departureTime2,
        layoverDuration2,
        arrivalTime2,
        originCity2,
        destinationCity2,
        departureDate2,
        cabinClass,
        fareType,
        baseFare,
        discountsCashbacks,
        taxesFees,
        gstAirline,
        totalFare,
        traveler1Title,
        traveler1AirlinePnr,
        traveler1TicketNumber,
    ] = matches;

    // Output the extracted data
    console.log('Trip ID:', tripId);
    console.log('Passenger 1:', `${passenger1Title} ${passenger1FirstName} ${passenger1LastName}`);
    console.log('Check-in Baggage (Adult):', checkInBaggageAdult);
    console.log('Cabin Baggage (Adult):', cabinBaggageAdult);
    console.log('Check-in Baggage (Child):', checkInBaggageChild);
    console.log('Cabin Baggage (Child):', cabinBaggageChild);
    console.log('Check-in Baggage (Infant):', checkInBaggageInfant);
    console.log('Cabin Baggage (Infant):', cabinBaggageInfant);
    console.log('Origin City:', originCity);
    console.log('Destination City:', destinationCity);
    console.log('Departure Date:', departureDate);
    console.log('Airline Name:', airlineName);
    console.log('Flight Number:', flightNumber);
    console.log('Departure Time:', departureTime);
    console.log('Layover Duration:', layoverDuration);
    console.log('Arrival Time:', arrivalTime);
    console.log('Airline Name (Layover):', airlineName2);
    console.log('Flight Number (Layover):', flightNumber2);
    console.log('Departure Time (Layover):', departureTime2);
    console.log('Layover Duration (Layover):', layoverDuration2);
    console.log('Arrival Time (Layover):', arrivalTime2);
    console.log('Origin City (Layover):', originCity2);
    console.log('Destination City (Layover):', destinationCity2);
    console.log('Departure Date (Layover):', departureDate2);
    console.log('Cabin Class:', cabinClass);
    console.log('Fare Type:', fareType);
    console.log('Base Fare:', baseFare);
    console.log('Discounts and Cashbacks:', discountsCashbacks);
    console.log('Taxes and Fees:', taxesFees);
    console.log('GST (Airline):', gstAirline);
    console.log('Total Fare:', totalFare);
    console.log('Traveler 1:', `${traveler1Title} (${traveler1AirlinePnr}), Ticket Number: ${traveler1TicketNumber}`);
});
