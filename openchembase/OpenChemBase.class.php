<?php
	class OpenChemBase {        
		private $chemicals_per_page = 30;
		private $addslashes = false;  //set false if $_GET and $_POST were backslashed already elsewhere
		private $msds_url = "http://hazard.com/msds/gn.cgi?query=";  //enter some public search service for MSDS. Example: http://hazard.com/msds/gn.cgi?query=
		public $mysql;

		function __construct() {
            require './config.inc.php';
			$this->mysql = @mysql_connect($cfg['db_server'], $cfg['db_user'], $cfg['db_password']);
            if (!$this->mysql) {
                die(mysql_error());
            }
			mysql_select_db($cfg['db_database']) OR die(mysql_error());
			if ($this->addslashes) {
                $this->security();
            }
		}

		function __destruct() {
			if(is_resource($this->mysql)) mysql_close($this->mysql);
		}

		// Public methods

		public function display() {
			$view_num = $this->chemicals_per_page; // numbers of chemicals per page
			$page = (isset($_GET['page']) && preg_match("/^[0-9]*$/", $page)) ? (int) $_GET['page'] : 0;
			$order_by = (isset($_GET['orderby']) && preg_match("/^[0-9_a-z]+$/i", $_GET['orderby'])) ? $_GET['orderby'] : "name";
			$direction = (isset($_GET['direction']) && $_GET['direction'] == "desc") ? "DESC" : "ASC";

			//generate the link panel
			$output = $this->_generate_navigation();

			//$output .= $this->generate_search_form();

			//generate the count panel
			$result = $this->sql("SELECT count(id) FROM `och_chemicals`");
			$count = mysql_fetch_row($result);
			$output .= '<p id="count-panel">We have '.$count[0].' chemicals in the database.</p>';

			// generate the previous / next links
			$previous_next_links = $this->_generate_prev_next_links($page, $count[0]);
			$output .= $previous_next_links;

			//generate the listing
			$result = $this->sql("SELECT ch.id, ch.name, ch.formula, ch.size, ch.amount, ch.location, l.name AS location_name FROM `och_chemicals` AS ch LEFT JOIN och_locations AS l ON ch.location=l.id ORDER BY ".$order_by." ".$direction." LIMIT ".($page * $view_num).", ".$view_num);

			$table_rows = "";
			$i = 1;
			while($item = mysql_fetch_assoc($result)) {
				$table_rows .= "<tr";
				if(($i++ % 2) == 1) $table_rows .= " class='alternate'";
				$table_rows .= ">
				<td><a href='view_chem.php?id=".$item['id']."' title='Click to view details.'>".htmlentities($item['name'])."</a></td>
				<td>".htmlentities($item['formula'])."</td>
				<td><a href='view_loc.php?id=".$item['location']."' title='Click to view details.'>".htmlentities($item['location_name'])."</a></td>
				<td>".htmlentities($item['size'])."</td>
				<td>".htmlentities($item['amount'])."</td>
				<td><a href='edit_chem.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_edit.png' alt='Edit' title='Edit' /></a></td>
				<td><a href='delete_chem.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_drop.png' alt='Delete' title='Delete' /></a></td>
</tr>";
			}

			//reverse order direction for 1 item
			$direction_name = "asc";
			$direction_formula = "asc";
			$direction_location = "asc";
			$direction_size = "asc";
			$direction_amount = "asc";
			switch($order_by) {
				case "name":
					$direction_name = ($direction == "ASC") ? "desc" : "asc";
					break;
				case "formula":
					$direction_formula = ($direction == "ASC") ? "desc" : "asc";
					break;
				case "location":
					$direction_location = ($direction == "ASC") ? "desc" : "asc";
					break;
				case "size":
					$direction_size = ($direction == "ASC") ? "desc" : "asc";
					break;
				case "amount":
					$direction_amount = ($direction == "ASC") ? "desc" : "asc";
					break;
			}

			$output .= '<table id="chemicals">
<tr>
	<th><a href="'.$_SERVER['PHP_SELF'].'?orderby=name&direction='.$direction_name.'">Name</a></th>
	<th><a href="'.$_SERVER['PHP_SELF'].'?orderby=formula&direction='.$direction_formula.'">Formula</a></th>
	<th><a href="'.$_SERVER['PHP_SELF'].'?orderby=location&direction='.$direction_location.'">Location</a></th>
	<th><a href="'.$_SERVER['PHP_SELF'].'?orderby=size&direction='.$direction_size.'">Size</a></th>
	<th><a href="'.$_SERVER['PHP_SELF'].'?orderby=amount&direction='.$direction_amount.'">Amount</a></th>
	<th colspan="2">Action</th>
</tr>
'.$table_rows.'
</table>';


			$output .= $previous_next_links;

			echo $output;
		}

		public function add_chemical() {
			$name = (isset($_POST['name'])) ? $_POST['name'] : "";
			$formula = (isset($_POST['formula'])) ? $_POST['formula'] : "";
			$cas = (isset($_POST['cas'])) ? $_POST['cas'] : "";
			$location = (isset($_POST['location'])) ? $_POST['location'] : "";
			$size = (isset($_POST['size'])) ? $_POST['size'] : "";
			$amount = (isset($_POST['amount'])) ? $_POST['amount'] : "";
			$remarks = (isset($_POST['remarks'])) ? $_POST['remarks'] : "";

			echo $this->_generate_navigation();

			if(isset($_POST['confirmation']) && $_POST['confirmation'] == "Add" ) {
				//check format and for missing values
				$msg_err = "";
				$msg_ok = "";
				if($name == "" ||
					$formula == "" ||
					$location == "" ||
					$size == "" ||
					$amount == ""
					) {
					$msg_err = "You did not enter some required information.";
				} else {
					if(!preg_match("/^[-0-9]+$/i", $location)) $msg_err .= "Invalid location!";
					if(!preg_match("/^[0-9]+$/i", $amount)) $msg_err .= "Amount must be an integer.";
					if(strlen($name) > 256) {
						$msg_err .= "Name is too long (max. 256 characters).";
					}
					if(strlen($formula) > 256) {
						$msg_err .= "Formula is too long (max. 256 characters).";
					}
					if(strlen($cas) > 50) {
						$msg_err .= "CAS is too long (max. 50 characters).";
					}
					if(strlen($size) > 256) {
						$msg_err .= "Size is too long (max. 30 characters).";
					}
					if(strlen($remarks) > 256) {
						$msg_err .= "Remarks is too long (max. 256 characters).";
					}

					if($msg_err == "") {
						$this->sql("INSERT INTO `och_chemicals` (`name`, `formula`, `cas`, `location`, `size`, `amount`, `remarks`, `time`) VALUES ('".$name."', '".$formula."', '".$cas."', '".$location."', '".$size."', '".$amount."', '".$remarks."', UNIX_TIMESTAMP())") OR die(mysql_error());
						$msg_ok = "The chemical database was updated.";
						$name = "";
						$formula = "";
						$cas = "";
						$location = "";
						$size = "";
						$amount = "1";
						$remarks = "";
					}

				}

				if($msg_err != "") echo "<fieldset class='msgError'><legend>Error</legend>".$msg_err."</fieldset>";
				if($msg_ok != "") echo "<fieldset class='msgSuccess'><legend>Success</legend>".$msg_ok."</fieldset>";


			} else {
				if($amount == "") $amount = "1";  // default value
			}

			$form = '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" id="insert_chem" >
	<fieldset>
		<legend>Add new chemical</legend>
		<label for="name">Name</label>
		<input type="text" name="name" maxlength="256" value="'.htmlentities($name).'" />
		<label for="formula">Formula</label>
		<input type="text" name="formula" maxlength="256" value="'.htmlentities($formula).'" />
		<label for="cas">CAS</label>
		<input type="text" name="cas" maxlength="50" value="'.htmlentities($cas).'" />
		<label for="location">Location</label>
		<select name="location">';

		$result = $this->sql("SELECT id, name FROM och_locations WHERE 1");
		if(mysql_num_rows($result) > 0) {
			while($location = mysql_fetch_assoc($result)) {

				$form .= '<option value="'.$location['id'].'">'.substr($location['name'], 0, 25).'</option>';
			}
		} else {
			echo '<option value="-1">No locations exist in the database!</options>';
		}
		$form .= '</select>
		<label for="size">Container size</label>
		<input type="text" name="size" maxlength="30" value="'.htmlentities($size).'"/>
		<label for="amount">Amount</label>
		<input type="text" name="amount" maxlength="6" value="'.htmlentities($amount).'" />
		<label for="remarks">Remarks</label>
		<textarea name="remarks" rows="5" cols="27">'.htmlentities($remarks).'</textarea>
	</fieldset>
	<fieldset id="form_buttons">
		<input type="submit" name="confirmation" value="Add" />
		<input type="reset" name="reset" value="Reset" />
		<a href="'.$_SERVER['HTTP_REFERER'].'"> Go back</a>
	</fieldset>
</form>';

		echo $form;
		}

		public function delete_chemical() {
			$id = (isset($_GET['id'])) ? (int)$_GET['id'] : false;
			if($id === false) die("No id was specified.");
			if((string)$id != $_GET['id']) die("Invalid id.");

			//Delete now if confirmed
			if(isset($_GET['confirmation']) && $_GET['confirmation'] == 'Yes') {
				$result = $this->sql("DELETE FROM `och_chemicals` WHERE id='".$id."' LIMIT 1");
				header("Location: index.php");
				die("Chemical #$id deleted.");
			}

			if(isset($_GET['confirmation']) && $_GET['confirmation'] != 'Yes') {
				header("Location: index.php");
				die("Chemical #$id was not deleted.");
			}

			$print = $this->_generate_navigation();

			$chemical = $this->_fetch_chemical($id);

			$print .= '<p>Do you really want to delete this chemical from the database? This action cannot be undone.</p>
			<p class="emBlock">
			<strong>Name:</strong> '.htmlentities($chemical['name']).'<br/>
			<strong>Formula:</strong> '.htmlentities($chemical['formula']).'<br/>
			<strong>CAS:</strong> '.htmlentities($chemical['cas']).'<br/>
			<strong>Location:</strong> '.htmlentities($chemical['location']).'<br/>
			<strong>Size:</strong> '.htmlentities($chemical['size']).'<br/>
			<strong>Amount:</strong> '.htmlentities($chemical['amount']).'<br/>
			<strong>Remarks:</strong> '.htmlentities($chemical['remarks']).'
			</p>
			<form action="'.$_SERVER['PHP_SELF'].'" method="GET" id="form_confirmation">
			<input type="hidden" name="id" value="'.$id.'" />
			<input type="submit" name="confirmation" value="Yes" />
			<input type="submit" name="confirmation" value="No" />
			</form>';
			echo $print;
		}

		public function edit_chemical() {

			echo $this->_generate_navigation();

			if(isset($_POST['confirmation']) && $_POST['confirmation'] == 'Save') {
				$id = (isset($_POST['id'])) ? $_POST['id'] : $id;
				$id = ($id == (int)$id) ? (int)$id : false;
				if($id === false) {
					die("No/invalid ID was specified.");
				}
				$name = (isset($_POST['name'])) ? $_POST['name'] : "";
				$formula = (isset($_POST['formula'])) ? $_POST['formula'] : "";
				$cas = (isset($_POST['cas'])) ? $_POST['cas'] : "";
				$location = (isset($_POST['location'])) ? $_POST['location'] : "";
				$size = (isset($_POST['size'])) ? $_POST['size'] : "";
				$amount = (isset($_POST['amount'])) ? $_POST['amount'] : "";
				$remarks = (isset($_POST['remarks'])) ? $_POST['remarks'] : "";

				//check format and for missing values
				$msg_err = "";
				$msg_ok = "";
				if($name == "" ||
					$formula == "" ||
					$location == "" ||
					$size == "" ||
					$amount == ""
					) {
					$msg_err = "You did not enter some required information.";
				} else {
					if(!preg_match("/^[-0-9]+$/i", $location)) $msg_err .= "Invalid location!";
					if(!preg_match("/^[0-9]+$/i", $amount)) $msg_err .= "Amount must be an integer.";
					if(strlen($name) > 256) {
						$msg_err .= "Name is too long (max. 256 characters).";
					}
					if(strlen($formula) > 256) {
						$msg_err .= "Formula is too long (max. 256 characters).";
					}
					if(strlen($cas) > 50) {
						$msg_err .= "CAS is too long (max. 50 characters).";
					}
					if(strlen($size) > 256) {
						$msg_err .= "Size is too long (max. 30 characters).";
					}
					if(strlen($remarks) > 256) {
						$msg_err .= "Remarks is too long (max. 256 characters).";
					}

					if($msg_err == "") {
						$this->sql("UPDATE `och_chemicals` SET `name`='".$name."', `formula`='".$formula."', `cas`='".$cas."', `location`='".$location."', `size`='".$size."', `amount`='".$amount."', `remarks`='".$remarks."', `time`=UNIX_TIMESTAMP() WHERE id='".$id."' LIMIT 1") OR die(mysql_error());
						$msg_ok = "The chemical database was updated.";
					}

				}

				if($msg_err != "") echo "<fieldset class='msgError'><legend>Error</legend>".$msg_err."</fieldset>";
				if($msg_ok != "") echo "<fieldset class='msgSuccess'><legend>Success</legend>".$msg_ok."</fieldset>";

			} else {
				$id = (isset($_GET['id'])) ? $_GET['id'] : false;
				$id = ($id == (int)$id) ? (int)$id : false;
				if($id === false) {
					die("No/invalid ID was specified.");
				}
				$chemical = $this->_fetch_chemical($id);
				$name = $chemical['name'];
				$formula = $chemical['formula'];
				$cas = $chemical['cas'];
				$location = $chemical['location'];
				$size = $chemical['size'];
				$amount = $chemical['amount'];
				$remarks = $chemical['remarks'];
			}


			$print = '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" id="edit_chem" >
	<fieldset>
		<input type="hidden" name="id" value="'.$id.'" />
		<legend>Edit chemical</legend>
		<label for="name">Name</label>
		<input type="text" name="name" maxlength="256" value="'.htmlentities($name).'" />
		<label for="formula">Formula</label>
		<input type="text" name="formula" maxlength="256" value="'.htmlentities($formula).'" />
		<label for="cas">CAS</label>
		<input type="text" name="cas" maxlength="50" value="'.htmlentities($cas).'" />
		<label for="location">Location</label>
		<select name="location">';

			$result = $this->sql("SELECT id, name FROM och_locations WHERE 1");
			if(mysql_num_rows($result) > 0) {
				while($some_location = mysql_fetch_assoc($result)) {

					$print .= '<option value="'.$some_location['id'].'" ';
					$print .= ($location == $some_location['id']) ? 'selected="selected"' : '';
					$print .= '>'.substr($some_location['name'], 0, 25).'</option>';
				}
			} else {
				echo '<option value="-1">No locations exist in the database!</options>';
			}
			$print .= '</select>
			<label for="size">Container size</label>
			<input type="text" name="size" maxlength="30" value="'.htmlentities($size).'"/>
			<label for="amount">Amount</label>
			<input type="text" name="amount" maxlength="6" value="'.htmlentities($amount).'" />
			<label for="remarks">Remarks</label>
			<textarea name="remarks" rows="5" cols="27">'.htmlentities($remarks).'</textarea>
		</fieldset>
		<fieldset id="form_buttons">
			<input type="submit" name="confirmation" value="Save" />
			<input type="reset" name="reset" value="Reset" />
			<a href="'.$_SERVER['HTTP_REFERER'].'"> Go back</a>
		</fieldset>
</form>';
			echo $print;
		}

		public function view_chemical() {
			$print = $this->_generate_navigation();
			$id = (isset($_GET['id'])) ? (int)$_GET['id'] : false;
			if($id === false) die("No id was specified.");
			if((string)$id != $_GET['id']) die("Invalid id.");

			$chemical = $this->_fetch_chemical($id);

			$print .= '
			<p class="emBlock">
			<strong>Name:</strong> '.htmlentities($chemical['name']).'<br/>
			<strong>Formula:</strong> '.htmlentities($chemical['formula']).'<br/>
			<strong>CAS:</strong> '.htmlentities($chemical['cas']).'<br/>
			<strong>Location:</strong> '.htmlentities($chemical['location']).'<br/>
			<strong>Size:</strong> '.htmlentities($chemical['size']).'<br/>
			<strong>Amount:</strong> '.htmlentities($chemical['amount']).'<br/>
			<strong>Remarks:</strong> '.htmlentities($chemical['remarks']).'
			</p>
			<p><a href="'.$_SERVER['HTTP_REFERER'].'"> Go back</a></p>
			</form>';
			echo $print;
		}

		public function add_location() {
			$name = (isset($_POST['name'])) ? $_POST['name'] : "";
			$description = (isset($_POST['description'])) ? $_POST['description'] : "";

			echo $this->_generate_navigation();

			if(isset($_POST['confirmation']) && $_POST['confirmation'] == "Add" ) {
				//check format and for missing values
				$msg_err = "";
				$msg_ok = "";
				if($name == "" ) {
					$msg_err = "You did not enter any name.";
				} else {
					if(strlen($name) > 30) {
						$msg_err .= "Name is too long (max. 30 characters).";
					}
					if(strlen($description) > 256) {
						$msg_err .= "Description is too long (max. 256 characters).";
					}

					if($msg_err == "") {
						$this->sql("INSERT INTO `och_locations` (`name`, `description`) VALUES ('".$name."', '".$description."')") OR die(mysql_error());
						$msg_ok = "The chemical database was updated.";
						$name = "";
						$description = "";
					}

				}

				if($msg_err != "") echo "<fieldset class='msgError'><legend>Error</legend>".$msg_err."</fieldset>";
				if($msg_ok != "") echo "<fieldset class='msgSuccess'><legend>Success</legend>".$msg_ok."</fieldset>";


			}

			$form = '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" id="insert_loc" >
	<fieldset>
		<legend>Add new location</legend>
		<label for="name">Name</label>
		<input type="text" name="name" maxlength="256" value="'.htmlentities($name).'" />
		<label for="description">Description</label>
		<textarea name="description" rows="5" cols="27">'.htmlentities($description).'</textarea>
	</fieldset>
	<fieldset id="form_buttons">
		<input type="submit" name="confirmation" value="Add" />
		<input type="reset" name="reset" value="Reset" />
		<a href="'.$_SERVER['HTTP_REFERER'].'"> Go back</a>
	</fieldset>
</form>';

		echo $form;

		}

		public function delete_location() {
			$id = (isset($_GET['id'])) ? $_GET['id'] : false;
			$id = ($id == (int)$id) ? (int)$id : false;
			if($id === false) die("No id was specified.");

			//Delete now if confirmed
			if(isset($_GET['confirmation']) && $_GET['confirmation'] == 'Yes') {
				$this->sql("DELETE FROM `och_locations` WHERE id='".$id."' LIMIT 1");
				header("Location: manage_loc.php");
				die("Location #$id deleted.");
			}

			if(isset($_GET['confirmation']) && $_GET['confirmation'] != 'Yes') {
				header("Location: manage_loc.php");
				die("Location #$id was not deleted.");
			}

			$print = $this->_generate_navigation();

			$location = $this->_fetch_location($id);

			$print .= '<p>Do you really want to delete this location from the database? This action cannot be undone.</p>
			<p class="emBlock">
			<strong>Name:</strong> '.htmlentities($location['name']).'<br/>
			<strong>Description:</strong> '.htmlentities($location['description']).'<br/>
			</p>
			<form action="'.$_SERVER['PHP_SELF'].'" method="GET" id="form_confirmation">
			<input type="hidden" name="id" value="'.$id.'" />
			<input type="submit" name="confirmation" value="Yes" />
			<input type="submit" name="confirmation" value="No" />
			</form>';
			echo $print;
		}

		public function edit_location() {
			echo $this->_generate_navigation();

			if(isset($_POST['confirmation']) && $_POST['confirmation'] == 'Save') {
				$id = (isset($_POST['id'])) ? $_POST['id'] : $id;
				$id = ($id == (int)$id) ? (int)$id : false;
				if($id === false) {
					die("No/invalid ID was specified.");
				}
				$name = (isset($_POST['name'])) ? $_POST['name'] : "";
				$description = (isset($_POST['description'])) ? $_POST['description'] : "";

				//check format and for missing values
				$msg_err = "";
				$msg_ok = "";
				if($name == "") {
					$msg_err = "Name is required.";
				} else {
					if(strlen($name) > 30) {
						$msg_err .= "Name is too long (max. 30 characters).";
					}
					if(strlen($description) > 256) {
						$msg_err .= "Description is too long (max. 256 characters).";
					}

					if($msg_err == "") {
						$this->sql("UPDATE `och_locations` SET `name`='".$name."', `description`='".$description."' WHERE id='".$id."' LIMIT 1") OR die(mysql_error());
						$msg_ok = "The chemical database was updated.";
					}

				}

				if($msg_err != "") echo "<fieldset class='msgError'><legend>Error</legend>".$msg_err."</fieldset>";
				if($msg_ok != "") echo "<fieldset class='msgSuccess'><legend>Success</legend>".$msg_ok."</fieldset>";

			} else {
				$id = (isset($_GET['id'])) ? $_GET['id'] : false;
				$id = ($id == (int)$id) ? (int)$id : false;
				if($id === false) {
					die("No/invalid ID was specified.");
				}
				$location = $this->_fetch_location($id);
				$name = $location['name'];
				$description = $location['description'];
			}


			$print = '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" id="edit_loc" >
	<fieldset>
		<input type="hidden" name="id" value="'.$id.'" />
		<legend>Edit location</legend>
		<label for="name">Name</label>
		<input type="text" name="name" maxlength="256" value="'.htmlentities($name).'" />
		<label for="description">Description</label>
		<textarea name="description" rows="5" cols="27">'.htmlentities($description).'</textarea>
		</fieldset>
		<fieldset id="form_buttons">
			<input type="submit" name="confirmation" value="Save" />
			<input type="reset" name="reset" value="Reset" />
			<a href="'.$_SERVER['HTTP_REFERER'].'"> Go back</a>
		</fieldset>
</form>';
			echo $print;
}

		public function manage_locations() {
			$view_num = 10; // numbers of items per page
			$page = (isset($_GET['page']) && preg_match("/^[0-9]*$/", $page)) ? (int) $_GET['page'] : 0;
			$direction = (isset($_GET['direction']) && $_GET['direction'] == "desc") ? "DESC" : "ASC";

			//generate the link panel
			$print = $this->_generate_navigation();

			//generate the count panel
			$result = $this->sql("SELECT count(id) FROM `och_locations`") OR die(mysql_error());
			$count = mysql_fetch_row($result);
			$print .= '<p id="count-panel">We have '.$count[0].' storage locations.</p>';

			// generate the previous / next links
			$previous_next_links = $this->_generate_prev_next_links($page, $count[0]);
			$print .= $previous_next_links;

			//generate the listing
			$result = $this->sql("SELECT * FROM `och_locations` ORDER BY `name` ".$direction." LIMIT ".($page * $view_num).", ".$view_num) OR die(mysql_error());

			$table_rows = "";
			$i = 1;
			while($item = mysql_fetch_assoc($result)) {
				$table_rows .= "<tr";
				if(($i++ % 2) == 1) $table_rows .= " class='alternate'";
				$table_rows .= ">
				<td><a href='view_loc.php?id=".$item['id']."' title='Click to view details.'>".htmlentities($item['name'])."</a></td>
				<td>".htmlentities($item['description'])."</td>
				<td><a href='edit_loc.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_edit.png' alt='Edit' title='Edit' /></a></td>
				<td><a href='delete_loc.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_drop.png' alt='Delete' title='Delete' /></a></td>
</tr>";
			}

			$print .= '<table id="locations">
<tr>
	<th>Name</th>
	<th>Description</th>
	<th colspan="2">Action</th>
</tr>
'.$table_rows.'
</table>';
			$print .= $previous_next_links;
			echo $print;

		}

		public function view_location() {
			$id = (isset($_GET['id'])) ? (int)$_GET['id'] : false;
			if($id === false) die("No id was specified.");
			if((string)$id != $_GET['id']) die("Invalid id.");

			$view_num = $this->chemicals_per_page; // numbers of chemicals per page
			$page = (isset($_GET['page']) && preg_match("/^[0-9]*$/", $page)) ? (int) $_GET['page'] : 0;
			$order_by = (isset($_GET['orderby']) && preg_match("/^[0-9_a-z]+$/i", $_GET['orderby'])) ? $_GET['orderby'] : "name";
			$direction = (isset($_GET['direction']) && $_GET['direction'] == "desc") ? "DESC" : "ASC";


			echo $this->_generate_navigation();


			$location = $this->_fetch_location($id);

			$print = '
			<div class="emBlock">
			<h3>'.htmlentities($location['name']).'</h3><br/>
			<p>'.htmlentities($location['description']).'</p>
			</div>
			<p><a href="'.$_SERVER['HTTP_REFERER'].'"> Go back</a></p>
			';

			// list all chemicals in this location
			$chemicals = $this->sql("SELECT id, name, formula, size, amount FROM och_chemicals WHERE location = '".$id."' ORDER BY ".$order_by." ".$direction." LIMIT ".($page * $view_num).", ".$view_num);

			if(mysql_num_rows($chemicals) > 0) {
				// generate the previous / next links
				$count = $this->sql("SELECT count(id) FROM `och_chemicals` WHERE location = '".$id."'");
				$count = mysql_fetch_row($count);
				$print .= $this->_generate_prev_next_links($page, $count[0]);

				$table_rows = "";
				$i = 1;
				while($item = mysql_fetch_assoc($chemicals)) {
					$table_rows .= "<tr";
					if(($i++ % 2) == 1) $table_rows .= " class='alternate'";
					$table_rows .= ">
					<td><a href='view_chem.php?id=".$item['id']."' title='Click to view details.'>".htmlentities($item['name'])."</a></td>
					<td>".htmlentities($item['formula'])."</td>
					<td>".htmlentities($item['size'])."</td>
					<td>".htmlentities($item['amount'])."</td>
					<td><a href='edit_chem.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_edit.png' alt='Edit' title='Edit' /></a></td>
					<td><a href='delete_chem.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_drop.png' alt='Delete' title='Delete' /></a></td>
</tr>";
				}
			}

			//reverse order direction for 1 item
			$direction_name = "asc";
			$direction_formula = "asc";
			$direction_location = "asc";
			$direction_size = "asc";
			$direction_amount = "asc";
			switch($order_by) {
				case "name":
					$direction_name = ($direction == "ASC") ? "desc" : "asc";
					break;
				case "formula":
					$direction_formula = ($direction == "ASC") ? "desc" : "asc";
					break;
				case "size":
					$direction_size = ($direction == "ASC") ? "desc" : "asc";
					break;
				case "amount":
					$direction_amount = ($direction == "ASC") ? "desc" : "asc";
					break;
			}

			$print .= '<p style="font-weight:bold">'.$count[0].' chemicals are stored in this place.</p><table id="chemicals">
<tr>
	<th><a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&orderby=name&direction='.$direction_name.'">Name</a></th>
	<th><a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&orderby=formula&direction='.$direction_formula.'">Formula</a></th>
	<th><a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&orderby=size&direction='.$direction_size.'">Size</a></th>
	<th><a href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&orderby=amount&direction='.$direction_amount.'">Amount</a></th>
	<th colspan="2">Action</th>
</tr>
'.$table_rows.'
</table>';

			echo $print;

		}

		public function print_chemicals() {

			$chemicals = $this->sql("SELECT ch.id, ch.name, ch.formula, ch.cas, ch.location, l.name AS location_name, ch.size, ch.amount, ch.remarks FROM `och_chemicals` AS ch LEFT JOIN `och_locations` AS l ON ch.location=l.id WHERE 1 ORDER BY location_name ASC, ch.name ASC");
			if(mysql_num_rows($chemicals) < 1) {
				$print = '<p>No chemicals were found in the database.</p>';
			} else {
				$print = '<p class="date-panel">'.date("F d, Y G:i:s").'</p>';
				$print .= '<table class="chemicals-for-print">';
				$print .= '<tr>
				<th>Name</th>
				<th>Formula</th>
				<th>CAS</th>
				<th>Size</th>
				<th>Amount</th>
</tr>';
				$last_location = -1;
				while($item = mysql_fetch_assoc($chemicals)) {
						if($last_location != $item['location']) { //insert a separator line
							$print .= '<tr><th colspan="6">'.$item['location_name'].'</th></tr>';
							$last_location = $item['location'];
						}
						$print .= '<tr>';
						$print .= '<td>'.htmlentities($item['name']).'</td>
						<td>'.htmlentities($item['formula']).'</td>
						<td>'.htmlentities($item['cas']).'</td>
						<td>'.htmlentities($item['size']).'</td>
						<td>'.htmlentities($item['amount']).'</td>
</tr>';
				}
				$print .= '</table>';
			}
			echo $print;
		}

		public function print_locations() {
		}

		public function search_chemical() {
			$view_num = $this->chemicals_per_page; // numbers of chemicals per page
			$page = (isset($_GET['page']) && preg_match("/^[0-9]*$/", $page)) ? (int) $_GET['page'] : 0;
			$keywords = (isset($_GET['search']) && !empty($_GET['search'])) ? stripslashes($_GET['search']) : "";

			//generate navigation
			$output = $this->_generate_navigation();

			//display form
			$output .= $this->generate_search_form($keywords);

			//get keywords
			if($keywords === "") { //no keywords, only display form

			} else {
				$keywords = $this->_process_keywords($keywords);

				$sql= "SELECT ch.id, ch.name, ch.formula, ch.location, l.name AS location_name, ch.size, amount, MATCH(ch.name, ch.formula) AGAINST ('".$keywords."') AS score FROM och_chemicals AS ch LEFT JOIN och_locations AS l ON (ch.location = l.id) WHERE MATCH(ch.name, ch.formula) AGAINST ('".$keywords."' IN BOOLEAN MODE) ORDER BY score DESC, ch.name ASC, ch.formula ASC LIMIT ".($page * $view_num).", ".$view_num;
				$result = $this->sql($sql);
				if(mysql_num_rows($result) < 1) {  //no matches
					$output .= '<p id="count-panel">Sorry, no matches were found.</p>';
				} else {
					$output .= '<p id="count-panel">'.mysql_num_rows($result).' matches found.</p>';

					// generate the previous / next links
					$previous_next_links = $this->_generate_prev_next_links($page, $count[0]);
					$output .= $previous_next_links;

					$table_rows = "";
					$i = 1;
					while($item = mysql_fetch_assoc($result)) {
						$table_rows .= "<tr";
						if(($i++ % 2) == 1) $table_rows .= " class='alternate'";
						$table_rows .= ">
						<td><a href='view_chem.php?id=".$item['id']."' title='Click to view details.'>".htmlentities($item['name'])."</a></td>
						<td>".htmlentities($item['formula'])."</td>
						<td><a href='view_loc.php?id=".$item['location']."' title='Click to view details.'>".htmlentities($item['location_name'])."</a></td>
						<td>".htmlentities($item['size'])."</td>
						<td>".htmlentities($item['amount'])."</td>
						<td><a href='edit_chem.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_edit.png' alt='Edit' title='Edit' /></a></td>
						<td><a href='delete_chem.php?id=".$item['id']."' ><img src='openchembase/theme/img/b_drop.png' alt='Delete' title='Delete' /></a></td>
</tr>";

					}

					$output .= '<table id="chemicals">
<tr>
		<th>Name</a></th>
		<th>Formula</a></th>
		<th>Location</a></th>
		<th>Size</a></th>
		<th>Amount</a></th>
		<th colspan="2">Action</th>
</tr>
'.$table_rows.'
</table>';

					$output .= $previous_next_links;
				}
			}

			echo $output;

		}

		public function generate_search_form($keywords = "") {
			$keywords = str_replace("'", "&#039;", $keywords);
			$keywords = str_replace("\"", '&quot;', $keywords);
			$form = '<form action="search.php" metod="GET" id="search-form" >
			<input name="search" type="text" value="'.$keywords.'" />
			<input type="submit" name="confirmation" value="Search" />
			</form>';
			return $form;
		}


		// Private methods

		private function _process_keywords($keywords) {
			$keywords_arr = explode(" ", $keywords);
			$keywords = "";
			foreach($keywords_arr as $item) {
				$keywords .= "+".$item." ";
			}
			$keywords = addslashes(rtrim($keywords));
			return $keywords;
		}

		private function _generate_navigation() {
			$navigation = '<div id="link-panel"><a href="index.php"><img src="openchembase/theme/img/table.png" alt="" /> Show</a>&nbsp;&nbsp;
			<a href="insert_chem.php"><img src="openchembase/theme/img/add.png" alt="" /> Add chemical</a>&nbsp;&nbsp;
			<a href="insert_loc.php"><img src="openchembase/theme/img/folder_add.png" alt="" /> Add location</a>&nbsp;&nbsp;
<a href="manage_loc.php"><img src="openchembase/theme/img/folder_edit.png" alt="" /> Locations</a>
<a href="search.php"><img src="openchembase/theme/img/magnifier.png" alt="" /> Search</a>&nbsp;&nbsp;
<a href="print_chem.php"><img src="openchembase/theme/img/printer.png" alt="" /> Print</a>
</div>';
			return $navigation;
		}

		private function _generate_prev_next_links($page, $total_pages) {
					$previous_page = $page - 1;
					$next_page = $page + 1;
					$previous_next_links = '';
					$query_string = (isset($_SERVER['QUERY_STRING'])) ? $_SERVER['QUERY_STRING'] : '';
					$query_string = trim(preg_replace("/page=[0-9]*/i", "", $query_string), "&");

					$previous_next_links .= '<div class="navigation-panel">';
					if($previous_page > -1) $previous_next_links .= '<a href="'.$_SERVER['PHP_SELF'].'?page='.$previous_page.'&'.$query_string.'" class="previous">&laquo; previous</a>';
					if($next_page < ceil($total_pages/$this->chemicals_per_page)) $previous_next_links .= '<a href="'.$_SERVER['PHP_SELF'].'?page='.$next_page.'&'.$query_string.'" class="next"> next &raquo;</a>';
					$previous_next_links .= '</div>';
					return $previous_next_links;
		}


		private function sql($query) {
			$result = mysql_query($query, $this->mysql) OR die(mysql_error());
			return $result;
		}

		private function security() {
			// Force magic_quotes
			if (!get_magic_quotes_gpc()) {
			  	foreach($_POST as $key => $value) {
				  	$_POST[$key] = addslashes($value);
			  	}
				foreach($_GET  as $key => $value) {
					$_GET[$key]  = addslashes($value);
				}
			}
		}

		private function _fetch_chemical($id) {
			$result = $this->sql("SELECT * FROM `och_chemicals` WHERE `id`='".$id."'");
			if(mysql_num_rows($result) <> 1) {
				die("The chemical was not found in the database.");
			} else {
				$chemical = mysql_fetch_assoc($result);
				return $chemical;
			}
		}

		private function _fetch_location($id) {
			$result = $this->sql("SELECT * FROM `och_locations` WHERE `id`='".$id."'");
			if(mysql_num_rows($result) <> 1) {
				die("The location was not found in the database.");
			} else {
				$location = mysql_fetch_assoc($result);
				return $location;
			}
		}

	}

	$openchembase = new OpenChemBase;
	//echo "<link href='openchembase/theme/style.css' rel='stylesheet' type='text/css' />"; //*/
?>
