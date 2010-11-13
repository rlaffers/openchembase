<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Chemical inventory</title>
	<link href='openchembase/theme/style.css' rel='stylesheet' type='text/css' />
</head>
<body>
<h1>Chemical database</h1>
<p>Search:</p>

<?php  
require("openchembase/OpenChemBase.class.php");
$openchembase->search_chemical();
?>

</body>
</html>