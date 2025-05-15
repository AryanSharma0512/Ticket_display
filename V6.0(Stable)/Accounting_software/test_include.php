<?php
require 'db_config.php';  // No path needed, same directory
require 'db_config_acc.php'; // No path needed, same directory


// Check if the variables are defined
if (isset($conn)) {
    echo "db_config.php included successfully<br>";
    var_dump($conn); // Check connection details
} else {
    echo "ERROR: db_config.php NOT included<br>";
}

if (isset($connAcc)) {
    echo "db_config_acc.php included successfully<br>";
    var_dump($connAcc);  // Check connection details
} else {
    echo "ERROR: db_config_acc.php NOT included<br>";
}

?>