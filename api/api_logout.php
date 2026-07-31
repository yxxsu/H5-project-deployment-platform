<?php
//api/api_logout.php
require __DIR__."/../config.php";
session_destroy();
echo json_encode(["code"=>1]);
exit;
?>

