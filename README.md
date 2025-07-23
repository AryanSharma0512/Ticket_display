# Ticket Display & Accounting Suite

This repository contains the internal tools for **Shree Dhanlaxmi Travels**. It
provides two main applications:

1. **Tickets Frontend** – Node.js services for generating dynamic flight and
   hotel tickets as well as option pages.
2. **Accounting Software** – A PHP and JavaScript based admin panel with various
   utilities for managing transactions and visualising business performance.

## Key Features

- Flight ticket generation with barcode uploads (see `server.js`)
- Hotel reservation ticket creation and option pages
- Admin dashboard offering income statements, outstanding payments and more
- CSV and PDF export utilities
- Goal seeking and projection of future revenue or profit
- Synchronisation scripts to merge table data

## Technologies Used

- Node.js with Express
- PHP 7
- MySQL
- JavaScript (client side)
- Chart.js for data visualisations
- Python for data processing scripts
- HTML & CSS for the UI

## Folder Structure

```
V6.0(Stable)/
├── Tickets frontend/      # Node.js server and ticket templates
└── Accounting_software/   # PHP based admin panel and utilities
```

`Tickets frontend` contains HTML forms, option pages and server scripts such as
`server.js` and `Options server.js`. `Accounting_software` contains the admin
panel (`Admin panel.html`), styling, PHP endpoints and helper scripts.

## Setup & Local Deployment

### Tickets Frontend
1. Navigate to the folder:
   ```bash
   cd "V6.0(Stable)/Tickets frontend"
   ```
2. Install dependencies:
   ```bash
   npm install
   ```
3. Update database credentials in the server files if required.
4. Start the services:
   ```bash
   node server.js          # main ticket server
   node "Options server.js"  # flight options
   node "Hotel opt server.js" # hotel options
   node hotel_server.js      # hotel ticket generator
   ```

### Accounting Software
1. Serve the `V6.0(Stable)/Accounting_software` folder with a PHP capable
   webserver (Apache, nginx + php-fpm or `php -S`).
2. Adjust MySQL credentials in `db_config.php` and related config files.
3. Import the required SQL tables (e.g. `create_b2b_table.sql`).

## Using the Applications

- **Tickets Frontend** – Access `http://<ip>:3000/` to fill out ticket forms.
  Submissions update `ticket.html` with passenger details and fares.
- **Options Servers** – Use ports 3001 and 3002 for flight and hotel option forms.
- **Admin Panel** – Open `Admin panel.html` from your PHP server to access
  actions like revenue trends, customer statements and invoice generation.
  Buttons allow PDF/CSV export of results.

## Deployment Notes

- Default ports are `3000`–`3002` (see server scripts). IP addresses are set in
  the files but can be modified for your environment.
- PHP scripts expect local MySQL databases; check `db_config.php` for details.
- For production deployments, secure credentials and consider HTTPS.

## Known Issues & Planned Improvements

- Credentials are committed in plain text. Environment variables should be used.
- No automated tests or continuous integration.
- Manual restarts are required when code changes.
- Input validation and security hardening are incomplete.

## Credits & License

Developed for **Shree Dhanlaxmi Travels** by Aryan Sharma and contributors.
All rights reserved.
