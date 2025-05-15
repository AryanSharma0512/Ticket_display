<?php
// db_config_pdetails.php
// Configuration for Passenger_details database
// Defines separate variables so generate_invoice.php can connect via $pd_conn

$pd_servername = "localhost";       // your MySQL server
$pd_username   = "root";            // your MySQL user
$pd_password   = "";                // your MySQL password
$pd_dbname     = "Passenger_details";  // the database name

// Note: do NOT create $conn here – 
// generate_invoice.php will use these vars to instantiate $pd_conn itself.
