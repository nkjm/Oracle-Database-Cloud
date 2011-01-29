<?php
define("DB_USER", "");
define("DB_PASSWORD", "");
define("DB_SERVICE", "");
define("ASM_USER", "");
define("ASM_PASSWORD", "");
define("ASM_SERVICE", "+ASM");
define("DEFAULT_DG", "DATA");

define("CUSTOMER_SERVICE", DB_SERVICE);
define("CUSTOMER_PREFIX", "CLOUD_");

// Roll
define("CLOUD_USER", "CLOUD_USER");

// Consumer Group 
define("DEFAULT_CONSUMER_GROUP", "MID");
$array_consumer_group = array("HIGH", "MID", "LOW");
$array_cpu_utilization_limit = array("HIGH" => "100", "MID" => "50", "LOW" => "10");

// Resource Plan
define("RESOURCE_PLAN", "CLOUD");

// Drive 
define("DEFAULT_DISK_TYPE", "HDD");
$array_disk_type = array("SSD" => "/dev/sd%", "HDD" => "/dev/xvd%");

// Error
$err_msg = ""
?>
