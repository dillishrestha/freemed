<?php
 // $Id$
 // desc: module prototype
 // lic : GPL, v2

if (!defined("__MODULE_EMR_PHP__")) {

define ('__MODULE_EMR_PHP__', true);

// class freemedEMRModule
class freemedEMRModule extends freemedModule {

	// override variables
	var $CATEGORY_NAME = "Electronic Medical Record";
	var $CATEGORY_VERSION = "0.1";

	// vars to be passed from child modules
	var $order_field;
	var $form_vars;
	var $table_name;

	// contructor method
	function freemedEMRModule () {
		// call parent constructor
		$this->freemedModule();
	} // end function freemedEMRModule

	// override check_vars method
	function check_vars ($nullvar = "") {
		global $module, $patient, $LoginCookie;
		if (!isset($module)) return false;
		if ($patient < 1) return false;
		// check access to patient
		if (!freemed_check_access_for_patient($LoginCookie, $patient)) return false;
		return true;
	} // end function check_vars

	// function main
	// - generic main function
	function main ($nullvar = "") {
		global $action, $patient, $LoginCookie;

		if (!isset($this_patient))
			$this->this_patient = new Patient ($patient);
		if (!isset($this_user))
			$this->this_user    = new User ($LoginCookie);

		// display universal patient box
		echo freemed_patient_box($this->this_patient)."<P>\n";

		switch ($action) {
			case "add":
				$this->add();
				break;

			case "addform":
				$this->addform();
				break;

			case "del":
			case "delete":
				$this->del();
				break;

			case "mod":
			case "modify":
				$this->mod();
				break;

			case "modform":
				$this->modform();
				break;

			case "display";
				$this->display();
				break;

			case "view":
			default:
				$this->view();
				break;
		} // end switch action
	} // end function main

	// ********************** MODULE SPECIFIC ACTIONS *********************

	// function add
	// - addition routine
	function add () { $this->_add(); }
	function _add () {
		foreach ($GLOBALS as $k => $v) global $$k;
	
		echo "
			<P><CENTER>
			<$STDFONT_B>"._("Adding")." ...
		";

		$result = $sql->query (
			$sql->insert_query (
				$this->table_name,
				$this->variables
			)
		);

		if ($result) { echo "<B>"._("done").".</B>\n"; }
		 else		 { echo "<B>"._("ERROR")."</B>\n"; }

		echo "
			<$STDFONT_E></CENTER>
			<P>
			<CENTER>
				<A HREF=\"$this->page_name?$_auth&module=$module&patient=$patient\"
				><$STDFONT_B>"._("back")."<$STDFONT_E></A>
			</CENTER>
		";

	} // end function _add

	// function del
	// - delete function
	function del () { $this->_del(); }
	function _del () {
		global $STDFONT_B, $STDFONT_E, $id, $sql;
		echo "<P ALIGN=CENTER>".
			"<$STDFONT_B>"._("Deleting")." . . . \n";
		$query = "DELETE FROM $this->table_name ".
			"WHERE id = '".prepare($id)."'";
		$result = $sql->query ($query);
		if ($result) { echo _("done"); }
		 else		 { echo "<FONT COLOR=\"#ff0000\">"._("ERROR")."</FONT>"; }
		echo "<$STDFONT_E></P>\n";
	} // end function _del

	// function mod
	// - modification function
	function mod () { $this->_mod(); }
	function _mod () {
		foreach ($GLOBALS as $k => $v) global $$k;
	
		echo "
			<P><CENTER>
			<$STDFONT_B>"._("Modifying")." ...
		";

		$result = $sql->query (
			$sql->update_query (
				$this->table_name,
				$this->variables,
				array (
					"id"	=>		$id
				)
			)
		);

		if ($result) { echo "<B>"._("done").".</B>\n"; }
		 else		 { echo "<B>"._("ERROR")."</B>\n"; }

		echo "
			<$STDFONT_E></CENTER>
			<P>
			<CENTER>
				<A HREF=\"$this->page_name?$_auth&module=$module&patient=$patient\"
				><$STDFONT_B>"._("back")."<$STDFONT_E></A>
			</CENTER>
		";

	} // end function _mod

	// function add/modform
	// - wrappers for form
	function addform () { $this->form(); }
	function modform () { $this->form(); }

	// function display
	// by default, a wrapper for view
	function display () { $this->view(); }

	// function form
	// - add/mod form stub
	function form () {
		global $action, $id, $sql;

		if (is_array($this->form_vars)) {
			reset ($this->form_vars);
			while (list ($k, $v) = each ($this->form_vars)) global $$v;
		} // end if is array

		switch ($action) {
			case "addform":
				break;

			case "modform":
				$result = $sql->query ("SELECT * FROM ".$this->table_name.
					" WHERE ( id = '".prepare($id)."' )");
				$r = $sql->fetch_array ($result);
				extract ($r);
				break;
		} // end of switch action
		
	} // end function form

	// function view
	// - view stub
	function view () {
		global $sql;
		$result = $sql->query ("SELECT ".$this->order_fields." FROM ".
			$this->table_name." ORDER BY ".$this->order_fields);
		echo freemed_display_itemlist (
			$result,
			"module_loader.php",
			$this->form_vars,
			array ("", _("NO DESCRIPTION")),
			"",
			"t_page"
		);
	} // end function view

} // end class freemedEMRModule

} // end if not defined

?>
