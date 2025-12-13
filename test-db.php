<?php
require 'db.php';
$db = (new Database())->getConnection();
echo "Database connected successfully";
