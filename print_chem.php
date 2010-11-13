<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Chemical inventory</title>
	<link href='openchembase/theme/style.css' rel='stylesheet' type='text/css' />
	<script type="text/javascript">
		window.onload = function() {
			window.print();
		}
	</script>
</head>
<body>
<h1>Chemical Inventory</h1>
<p class="lab-info">Location: Building ABCD, lab #666
Lab manager:                                    Contact:                              
Lab telephone: 123-456-7890</p>
<p style="font-style:italic;">The most recent version of this document is available at <strong>http://your-url/</strong></p>

<?php  
require("openchembase/OpenChemBase.class.php");
$openchembase->print_chemicals();
?>

</body>
</html>