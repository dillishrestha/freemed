<?php
// $Id$
// desc: payment module 
// lic : GPL, v2

if (!defined("__PAYMENT_MODULE_PHP__")) {

    define (__PAYMENT_MODULE_PHP__, true);

    // class testModule extends freemedModule
    class PaymentModule extends freemedEMRModule {

        // override variables
        var $MODULE_NAME = "Payments";
        var $MODULE_VERSION = "0.1";

        var $item;
		var $view_query = "!='0'";  // by default see unpaid and overpaid
		var $view_closed = "='0'";  // see paid procedures when closed is selected
		var $view_unpaid = ">'0'";  // we use this when being called from the unpaid procs report
		var $table_name = "payrec";

		var $variables = array(
			"payrecdtadd",
			"payrecdtmod",
			"payrecpatient",
			"payrecdt",
			"payreccat",
			"payrecproc",
			"payrecsource",
			"payreclink",
			"payrectype",
			"payrecnum",
			"payrecamt",
			"payrecdescrip",
			"payreclock"
			);


        // contructor method
        function PaymentModule ($nullvar = "") {
            // call parent constructor
            $this->freemedEMRModule($nullvar);
        } // end function testModule

        function addform()
        {
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS)) global $$k;


			// special circumstances when being called from the unpaid
			// reports module
			if ($viewaction=="unpaidledger")  
			{
				$this->view_query = $this->view_updaid;
				$this->ledger();
				return;
			}

            if (!$been_here)
            {
                $this->item = 0;
                $this->view();
                return;
            }

            if ($viewaction=="ledgerall")
            {
                $this->ledger();
                return;
            }

            if ($viewaction=="ledger")
            {
                $this->ledger($item);
                return;
            }


            // everything else needs the proc id.

            $this->item = $item;
            if ($viewaction=="refresh" OR $viewaction=="closed" OR $item==0)
            {
				if ($viewaction=="closed")
					$this->view_query = $this->view_closed;
                $this->view();
                return;

            }

            if ($viewaction=="mistake")
            {
                $this->mistake($item);
            }

            //$this->view();  // always show the procs

            // from here on we should be processing the request type
            // start a wizard


            if ($viewaction=="rebill")
            {
                $this->transaction_wizard($item, REBILL);
            }

            if ($viewaction=="copay")
            {
                $this->transaction_wizard($item, COPAY);
            }

            if ($viewaction=="payment")
            {
                $this->transaction_wizard($item, PAYMENT);
            } 

            if ($viewaction=="transfer")
            {
                $this->transaction_wizard($item, TRANSFER);
            }

            if ($viewaction=="adjustment")
            {
                $this->transaction_wizard($item, ADJUSTMENT);
            }

            if ($viewaction=="deductable")
            {
                $this->transaction_wizard($item, DEDUCTABLE);
            }

            if ($viewaction=="withhold")
            {
                $this->transaction_wizard($item, WITHHOLD);
            }

            if ($viewaction=="denial")
            {
                $this->transaction_wizard($item, DENIAL);
            }

            if ($viewaction=="refund")
            {
                $this->transaction_wizard($item, REFUND);
            }

            if ($viewaction=="allowedamt")
            {
                $this->transaction_wizard($item, FEEADJUST);
            }



        } // end addform


        function transaction_wizard($procid, $paycat)
        {
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS)) global $$k;
            global $payrecproc, $payreccat;

            $payreccat = $paycat;
            $payrecproc = $procid;

            if ($patient>0) {
                $this_patient = new Patient($patient);
            }
            else
                DIE("No patient");

			$proc_rec = freemed_get_link_rec($procid, "procrec");
			if (!$proc_rec)
				DIE("Error getting procedure");
			$proccovmap = "0:".$proc_rec[proccov1].":".$proc_rec[proccov2].":".
							$proc_rec[proccov3].":".$proc_rec[proccov4];

            // **************** FORM THE WIZARD ***************
            $wizard = new wizard (array("item", "been_here", "module", "viewaction", "action", "patient", "_auth"));


            // ************** SECOND STEP PREP ****************
            // determine closest date if none is provided
            //if (empty($payrecdt) and empty($payrecdt_y))
            //	echo "date $payrecdt $payrecdt_y $cur_date";
            if (empty($payrecdt_y))
			{
				global $payrecdt;
                $payrecdt = $cur_date; // by default, the date is now...
			}
            // ************* ADD PAGE FOR STEP TWO *************

            switch ($payreccat)
            {
            case PAYMENT: // payment (0)
                    $wizard->add_page (
                        _("Describe the Payment"),
                        array_merge(array ("payrecsource", "payrectype", "payrecamt"), date_vars("payrecdt")),
                        html_form::form_table ( array (
                                                    _("Payment Source") =>
                                                                "<SELECT NAME=\"payrecsource\">"
                        											.$this->insuranceSelectionByType($proccovmap)."
                                                                 </SELECT>",

                                                    _("Payment Type") =>
                                                                    "<SELECT NAME=\"payrectype\">
                                                                    <OPTION VALUE=\"0\" ".
                                                                    ( ($payrectype==0) ? "SELECTED" : "" ).">cash
                                                                    <OPTION VALUE=\"1\" ".
                                                                    ( ($payrectype==1) ? "SELECTED" : "" ).">check
                                                                    <OPTION VALUE=\"2\" ".
                                                                    ( ($payrectype==2) ? "SELECTED" : "" ).">money order
                                                                    <OPTION VALUE=\"3\" ".
                                                                    ( ($payrectype==3) ? "SELECTED" : "" ).">credit card
                                                                    <OPTION VALUE=\"4\" ".
                                                                    ( ($payrectype==4) ? "SELECTED" : "" ).">traveller's check
                                                                    <OPTION VALUE=\"5\" ".
                                                                    ( ($payrectype==5) ? "SELECTED" : "" ).">EFT
                                                                    </SELECT>",

                                                    _("Date Received") =>
                                                                     fm_date_entry ("payrecdt"),

                                                    _("Payment Amount") =>
                                                                      "<INPUT TYPE=TEXT NAME=\"payrecamt\" SIZE=10 MAXLENGTH=15 ".
                                                                      "VALUE=\"".prepare($payrecamt)."\">\n"
                                                )
                                              )
                    );

                // second page of payments whois making the payment
                $second_page_array = "";

                switch ($payrecsource)
                {
                case "0":
                    if ($patient>0)
                    {
                        $second_page_array["Patient"] =
                            $this_patient->fullName()."
                            <INPUT TYPE=HIDDEN NAME=\"payreclink\" ".
                            "VALUE=\"".prepare($patient)."\">\n";
                    }
                    else
                    {
                        echo "
                        <TR><TD COLSPAN=2><CENTER>NOT IMPLEMENTED YET!</CENTER>
                        </TD></TR>
                        ";
                    }
                    break;
				default:
					$payreclink = $this->coverageIDFromType($proccovmap,$payrecsource);
					// we can make this hidden now also since we know the link amount
					// fixme when you get a chance.
                    $second_page_array[_("Insurance Company")] =
                        $this->insuranceName($payreclink)."
                        <INPUT TYPE=HIDDEN NAME=\"payreclink\" ".
                        "VALUE=\"".prepare($payreclink)."\">\n";
                    break;
                } // payment source switch end
                // how is the payment being made
                switch ($payrectype)
                {
                case "1": // check
                    $second_page_array[_("Check Number")] =
                        "<INPUT TYPE=TEXT NAME=\"payrecnum\" SIZE=20 ".
                        "VALUE=\"".prepare($payrecnum)."\">\n";
                    break;
                case "2": // money order
                    $second_page_array[] = "<B>NOT IMPLEMENTED YET!</B><BR>\n";
                    break;
                case "3": // credit card
                    $second_page_array[_("Credit Card Number")] =
                        "<INPUT TYPE=TEXT NAME=\"payrecnum_1\" SIZE=17 ".
                        "MAXLENGTH=16 VALUE=\"".prepare($payrecnum_1)."\">\n";

                    $second_page_array[_("Expiration Date")] =
                        number_select ("payrecnum_e1", 1, 12, 1, true).
                        "\n <B>/</B>&nbsp; \n".
                        number_select ("payrecnum_e2", (date("Y")-2), (date("Y")+10), 1);
                    break;
                case "4": // traveller's check
                    $second_page_array[_("Cheque Number")] =
                        "<INPUT TYPE=TEXT NAME=\"payrecnum\" SIZE=21 ".
                        "MAXLENGTH=20 VALUE=\"".prepare($payrecnum)."\">\n";
                    break;
                case "5": // EFT
                    $second_page_array[] = "<B>NOT IMPLEMENTED YET!</B><BR>\n";
                    break;
                case "0":
                default: // if nothing... (or cash)
                    break;
                } // end of type switch

                $second_page_array[_("Description")] =
                    "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ".
                    "VALUE=\"".prepare($payrecdescrip)."\">\n";

                $wizard->add_page(
                    _("Step Three").": "._("Specify the Payer"),
                    array ("payreclink", "payrecdescrip", "payrecnum",
                           "payrecnum_e1", "payrecnum_e2"),
                    html_form::form_table ( $second_page_array )
                );

                break; // end of payment

            case WITHHOLD: // adjustment (7)
            case DEDUCTABLE: // adjustment (8)
            case ADJUSTMENT: // adjustment (1)
				$amount_heading[WITHHOLD] = _("Withhold Amount");
				$amount_heading[ADJUSTMENT] = _("Adjustment Amount");
				$amount_heading[DEDUCTABLE] = _("Deductable Amount");
				$title[WITHHOLD] = _("Describe the Withholding");
				$title[ADJUSTMENT] = _("Describe the Adjustment");
				$title[DEDUCTABLE] = _("Describe the Deductable");
                $wizard->add_page (
                    _("Step Two").": ".$title[$payreccat],
                    array_merge(array ("payrecamt", "payrecdescrip"),date_vars("payrecdt")),
                    html_form::form_table ( array (
                                                _("Date Received") =>
                                                                 fm_date_entry ("payrecdt"),
                                                _("Description") =>
                                                                  "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ".
                                                                  "VALUE=\"".prepare($payrecdescrip)."\">\n",
                                                "$amount_heading[$payreccat]" =>
                                                                     "<INPUT TYPE=TEXT NAME=\"payrecamt\" SIZE=10 MAXLENGTH=9 ".
                                                                     "VALUE=\"".prepare($payrecamt)."\">\n"
                                            ) )
                );

                break; // end of adjustment

            case FEEADJUST: // adjustment (1)
                $wizard->add_page (
                    _("Step Two").": "._("Describe the Adjustment"),
                    array_merge(array ("payrecsource", "payrecamt", "payrecdescrip"),date_vars("payrecdt")),
                    html_form::form_table ( array (
                                                _("Insurance Company") =>
                        										"<SELECT NAME=\"payrecsource\">".
                        										$this->insuranceSelection($proccovmap).
                        										"</SELECT>\n",
                                                _("Date Received") =>
                                                                 fm_date_entry ("payrecdt"),
                                                _("Description") =>
                                                                  "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ".
                                                                  "VALUE=\"".prepare($payrecdescrip)."\">\n",
                                                "Allowed Amount" =>
                                                                     "<INPUT TYPE=TEXT NAME=\"payrecamt\" SIZE=10 MAXLENGTH=9 ".
                                                                     "VALUE=\"".prepare($payrecamt)."\">\n"
                                            ) )
                );

                break; // end of adjustment
            case REFUND: // refund (2)
                $wizard->add_page (
                    _("Step Two").": "._("Describe the Refund"),
                    array_merge(array ("payrecamt", "payrecdescrip", "payreclink"),date_vars("payrecdt")),
                    html_form::form_table ( array (
                                                _("Date of Refund") =>
                                                                  fm_date_entry ("payrecdt"),

                                                _("Destination") =>
                                                               "<SELECT NAME=\"payreclink\">
                                                               <OPTION VALUE=\"0\" ".
                                                               ( ($payreclink==0) ? "SELECTED" : "" ).">"._("Apply to Credit")."
                                                               <OPTION VALUE=\"1\" ".
                                                               ( ($payreclink==1) ? "SELECTED" : "" ).">"._("Refund to Patient")."
                                                               </SELECT>\n",

                                                _("Description") =>
                                                                  "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ".
                                                                  "VALUE=\"".prepare($payrecdescrip)."\">\n",
                                                _("Refund Amount") =>
                                                                 "<INPUT TYPE=TEXT NAME=\"payrecamt\" SIZE=10 ".
                                                                 "MAXLENGTH=9 VALUE=\"".prepare($payrecamt)."\">\n",

                                            ) )
                );

                break; // end of refund

            case COPAY: // copay (11)
                $wizard->add_page (
                    _("Step Two").": "._("Describe the Copayment"),
                    array_merge(array ("payrecamt", "payrecdescrip"),date_vars("payrecdt")),
                    html_form::form_table ( array (
                                                _("Date of Copay") =>
                                                                  fm_date_entry ("payrecdt"),

                                                _("Description") =>
                                                                  "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ".
                                                                  "VALUE=\"".prepare($payrecdescrip)."\">\n",
                                                _("Copay Amount") =>
                                                                 "<INPUT TYPE=TEXT NAME=\"payrecamt\" SIZE=10 ".
                                                                 "MAXLENGTH=9 VALUE=\"".prepare($payrecamt)."\">\n",

                                            ) )
                );

                break; // end of copay


            case DENIAL: // denial (3)
                $wizard->add_page (
                    _("Step Two").": "._("Describe the Denial"),
                    array_merge(array ("payreclink", "payrecdescrip"), date_vars("payrecdt")),
                    html_form::form_table ( array (
                                                _("Date of Denial") =>
                                                                  fm_date_entry ("payrecdt"),

                                                _("Description") =>
                                                                  "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ".
                                                                  "VALUE=\"".prepare($payrecdescrip)."\">\n",

                                                _("Adjust to Zero?") =>
                                                                   "<SELECT NAME=\"payreclink\">
                                                                   <OPTION VALUE=\"0\" ".
                                                                   ( ($payreclink==0) ? "SELECTED" : "" ).">"._("no")."
                                                                   <OPTION VALUE=\"1\" ".
                                                                   ( ($payreclink==1) ? "SELECTED" : "" ).">"._("yes")."
                                                                   </SELECT>\n"
                                            ) )
                );

                break; // end of denial

            case TRANSFER: // transfer (6)
                $wizard->add_page (
                    _("Step Two").": "._("Describe the Transfer"),
                    array_merge(array ("payrecsource", "payrecdescrip"), date_vars("payrecdt")),
                    html_form::form_table ( array (
                                                _("Date of Transfer") =>
                                                                  fm_date_entry ("payrecdt"),
                                                _("Description") =>
                                                                  "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ",
                                                _("Transfer to") =>
                                                                "<SELECT NAME=\"payrecsource\">"
                        											.$this->insuranceSelectionByType($proccovmap)."
                                                                 </SELECT>"
                                            ) )
                );

                break; // end of denial
            case REBILL: // rebill 4
                $wizard->add_page(
                    _("Step Two").": "._("Rebill Information"),
                    array_merge(array ("payrecdescrip"), date_vars("payrecdt")),
                    html_form::form_table ( array (
                                                _("Date of Rebill") =>
                                                                  fm_date_entry ("payrecdt"),
                                                _("Description") =>
                                                                  "<INPUT TYPE=TEXT NAME=\"payrecdescrip\" SIZE=30 ".
                                                                  "VALUE=\"".prepare($payrecdescrip)."\">\n"
                                            ) )
                );
                break; // end of rebills

            default: // we shouldn't be here
                // do nothing -- we haven't selected payments yet
                break;
            } // end switch payreccat

            if (!$wizard->is_done() and !$wizard->is_cancelled())
            {
                echo "<CENTER>".$wizard->display()."</CENTER>";
                return;
            }

            if ($wizard->is_cancelled())
            {
                // if the wizard was cancelled
                echo "<CENTER>CANCELLED<BR></CENTER><BR>\n";
            }

            if ($wizard->is_done())
            {
                //freemed_display_box_top (_("Adding")." "._($record_name));
                //if ($patient>0) echo freemed_patient_box ($this_patient);
                echo "<CENTER>\n";
                switch ($payreccat)
                { // begin category case (add)
                case PAYMENT: // payment category (add) 0
                    // first clean payrecnum vars
                    $payrecnum    = eregi_replace (":", "", $payrecnum   );
                    $payrecnum_1  = eregi_replace (":", "", $payrecnum_1 );
                    $payrecnum_e1 = eregi_replace (":", "", $payrecnum_e1);
                    $payrecnum_e2 = eregi_replace (":", "", $payrecnum_e2);

                    // then decide what to do with them
                    switch ($payrectype) {
                    case "0": // cash
                        break;
                    case "1": // check
                        $payrecnum = chop($payrecnum);
                        break;
                    case "2": // money order
                        echo "<B>NOT IMPLEMENTED YET!!!</B><BR>\n";
                        break;
                    case "3": // credit card
                        $payrecnum = chop($payrecnum_1). ":".
                                     chop($payrecnum_e1).":".
                                     chop($payrecnum_e2);
                        break;
                    case "4": // traveller's cheque
                        $payrecnum = chop($payrecnum);
                        break;
                    case "5": // EFT
                        break;
                    default: // if somebody messed up...
                        echo "$ERROR!!! payrectype not present<BR>\n";
                        $payrecnum = ""; // kill!!!
                        DIE("");
                        break;
                    } // end switch payrectype
                    break; // end payment category (add)

                case ADJUSTMENT: // adjustment category 1
                case DEDUCTABLE: // adjustment category 8
                case WITHHOLD: // adjustment category 7
					$payrecamt = abs($payrecamt);
                    break; // end adjustment category 

				case COPAY: // copay
					$payreclink = $patient;
					$payrecsource = 0; // patient is source
					break;
                case FEEADJUST: // adjustment category (add) 1
					// calc the payrecamt
					$payreclink = $this->coverageIDFromType($proccovmap,$payrecsource);
                    $proccharges = freemed_get_link_field ($payrecproc, "procrec",
                                                               "proccharges");
					$payrecamt = $proccharges - abs($payrecamt);
                    break; // end adjustment category (add)

                case REFUND: // refund category (add) 2
                    break; // end refund category (add)

                case TRANSFER: // refund category 6
						// show as transferring the balance
                        $payrecamt = freemed_get_link_field ($payrecproc, "procrec",
                                                               "procbalcurrent");
						if ($payrecsource == 0)
							$payreclink = 0;
						else
							$payreclink = $this->coverageIDFromType($proccovmap,$payrecsource);
					
                    break; // end refund category (add)
                case DENIAL: // denial category (add) 3
					$payrecamt = 0; // default
                    if ($payreclink==1) // adjust to zero
                    {
                        $payrecamt = freemed_get_link_field ($payrecproc, "procrec",
                                                               "procbalcurrent");
                        //$payrecamt   = -(abs($amount_left));
                    }
                    break; // end denial category (add)

                case REBILL: // rebill category (add) 4
						// save off the amount we are re billing
                        $payrecamt = freemed_get_link_field ($payrecproc, "procrec",
                                                               "procbalcurrent");
                    break; // end rebill category (add)
                } // end category switch (add)

                if (empty($payrecdt_y))
                {
                    global $payrecdt;
                    $payrecdt = $cur_date; // by default, the date is now...
                }
                else
                {
                    $payrecdt = fm_date_assemble("payrecdt");
                }
                echo "<$STDFONT_B>"._("Adding")." ... <$STDFONT_E>\n";
				$query = $sql->insert_query($this->table_name,
					array(
						"payrecdtadd" => $cur_date,
						"payrecdtmod" => $cur_date,
						"payrecpatient" => $patient,
						"payrecdt"      => fm_date_assemble("payrecdt"),
						"payreccat",
						"payrecproc",
						"payrecsource",
						"payreclink",
						"payrectype",
						"payrecnum",
						"payrecamt",
						"payrecdescrip",
						"payreclock" => "unlocked"
						));

                if ($debug) echo "<BR>(query = \"$query\")<BR>\n";
                $result = $sql->query($query);
            	if ($result) 
                { 
                    echo _("done")."."; 
                }
                else
                { 
                    echo _("ERROR");    
                }
                echo "  <BR><$STDFONT_B>"._("Modifying procedural charges")." ... <$STDFONT_E>\n";
				$procrec = freemed_get_link_rec($payrecproc,"procrec");	
				if (!$procrec)
					echo _("ERROR");

				$proccharges = $procrec[proccharges];
				$procamtpaid = $procrec[procamtpaid];
				$procbalorig = $procrec[procbalorig];

                switch ($payreccat)
                {

                case FEEADJUST: // adjustment category (add) 1
					// we had to blowout the payrecamt above so we calc the original
					// amt that was entered.
					
					$allowed = $proccharges - $payrecamt;
					$proccharges = $allowed;
					$procbalcurrent = $proccharges - $procamtpaid;
                    $query = "UPDATE procrec SET
                             procbalcurrent = '$procbalcurrent',
							 proccharges = '$proccharges',
							 procamtallowed = '$allowed'
                             WHERE id='".addslashes($payrecproc)."'";
                    break; // end fee adjustment 

                case REFUND: // refund category (add) 2
					$proccharges = $proccharges + $payrecamt;
					$procbalcurrent = $proccharges - $procamtpaid;
                    $query = "UPDATE procrec SET
                             proccharges    = '$proccharges',
							 procbalcurrent = '$procbalcurrent'
                             WHERE id='$payrecproc'";
                    break; // end refund category (add)

                case DENIAL: // denial category (add) 3
                    if ($payreclink==1)  // adjust to zero?
                    {
						// this should force it to 0 naturally
						$proccharges = $proccharges - $payrecamt;
						$procbalcurrent = $proccharges - $procamtpaid;
                        $query = "UPDATE procrec SET
                             proccharges    = '$proccharges',
							 procbalcurrent = '$procbalcurrent'
                             WHERE id='$payrecproc'";
                    }
                    else
                    { // if no adjust
                        $query = "";
                    } // end checking for adjust to zero
                    break; // end denial category (add)

                case REBILL: // rebill category (add) 4
                    $query = "UPDATE procrec SET procbilled='0' WHERE id='".addslashes($payrecproc)."'";
                    break; // end rebill category (add)

                case TRANSFER: // transfer (6)
					// here the link is an insurance type not an insco id.
                    $query = "UPDATE procrec SET proccurcovtp='".addslashes($payrecsource)."',
												 proccurcovid='".addslashes($payreclink)."'
							WHERE id='".addslashes($payrecproc)."'";
                    break; // end rebill category (add)

                case DEDUCTABLE: // adjustment category 8
                case WITHHOLD: // adjustment category 7
					$proccharges = $proccharges - $payrecamt;
					$procbalcurrent = $proccharges - $procamtpaid;
                    $query = "UPDATE procrec SET
                             procbalcurrent = '$procbalcurrent',
                             proccharges    = '$proccharges'
                             WHERE id='".addslashes($payrecproc)."'";
                    break;
                case ADJUSTMENT: // adjustment category (add) 1
					$procamtpaid = $procamtpaid - $payrecamt;
					$procbalcurrent = $proccharges - $procamtpaid;
                    $query = "UPDATE procrec SET
                             procbalcurrent = '$procbalcurrent',
                             procamtpaid    = '$procamtpaid'
                             WHERE id='".addslashes($payrecproc)."'";
					break;
                case PAYMENT: // payment category (add) 0
                case COPAY: // copay is a payment but we need to know the difference
                default:  // default is payment
					$procamtpaid = $procamtpaid + $payrecamt;
					$procbalcurrent = $proccharges - $procamtpaid;
                    $query = "UPDATE procrec SET
                             procbalcurrent = '$procbalcurrent',
                             procamtpaid    = '$procamtpaid'
                             WHERE id='".addslashes($payrecproc)."'";
                    break;
                } // end category switch (add)
                if ($debug) echo "<BR>(query = \"$query\")<BR>\n";
                if (!empty($query))
                {
                    $result = $sql->query($query);
                    if ($result) { echo _("done")."."; }
                    else        { echo _("ERROR");    }
                }
                else
                { // if there is no query, let the user know we did nothing
                    echo "unnecessary";
                } // end checking for null query
            }  // end processing wizard done


            // we send this if cancelled or done.
            echo "
            </CENTER>
            <P>
            <CENTER>
            <A HREF=\"$this->page_name?_auth=$_auth&been_here=1&viewaction=refresh".
            "&action=addform&item=$payrecproc&patient=$patient&module=$module\">
            <$STDFONT_B>"._("Back")."<$STDFONT_E></A>
            </CENTER>
            <P>
            ";

            return;


        } // wizard code

        function mod()
        {
            echo "Mod<BR>";
        }

        function add()
        {
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS))
            {
                global $$k;
                echo "$$k $v<BR>";
            }
            echo "Add<BR>";
        }

        function ledger($procid=0)
        {
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS))
            {
                global $$k;
                //echo "$$k $v<BR>";
            }

            if ($procid)
            {
                $pay_query  = "SELECT * FROM payrec
                              WHERE payrecpatient='$patient' AND payrecproc='$procid'
                              ORDER BY payrecdt,id";
            }
            else
            {
                $pay_query  = "SELECT * FROM payrec AS a, procrec AS b
                              WHERE b.procbalcurrent".$this->view_query." AND
                              b.id = a.payrecproc AND
                              a.payrecpatient='".addslashes($patient)."'
                              ORDER BY payrecproc,payrecdt,a.id";
            }
            $pay_result = $sql->query ($pay_query);

            if (!$sql->results($pay_result)) {
                echo "
                <CENTER>
                <P>
                <B><$STDFONT_B>
                "._("There are no records for this patient.")."
                </B><$STDFONT_E>
                <P>
                <A HREF=\"manage.php?$_auth&id=$patient\"
                ><$STDFONT_B>"._("Manage_Patient")."<$STDFONT_E></A>
                <P>
                </CENTER>
                ";
				freemed_display_box_bottom();
				freemed_display_html_bottom();
                DIE(""); // kill!!
            } // end/if there are no results

            // if there is something, show it...
            echo "
            <TABLE BORDER=0 CELLSPACING=0 CELLPADDING=3 WIDTH=100%>
            <TR>
            <TD><B>"._("Date")."</B></TD>
            <TD><B>"._("Type")."</B></TD>
            <TD><B>"._("Description")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Charges")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Payments")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Balance")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Action")."</B></TD>
            </TR>
            ";

            $total_payments = 0.00; // initially no payments
            $total_charges  = 0.00; // initially no charges

            while ($r = $sql->fetch_array ($chg_result)) {
                $procdate        = fm_date_print ($r["procdt"]);
                $procbalorig     = $r["procbalorig"];
                $id              = $r["id"];
                $total_charges  += $procbalorig;
                echo "
                <TR BGCOLOR=\"".($_alternate=freemed_bar_alternate_color($_alternate))."\">
                <TD>$procdate</TD>
                <TD><I>".( (!empty($r[proccomment])) ?
					prepare($r[proccomment]) : "&nbsp;" )."</I></TD>
                <TD>charge</TD>
                <TD ALIGN=RIGHT>
                <FONT COLOR=\"#ff0000\">
                <TT><B>".bcadd($procbalorig, 0, 2)."</B></TT>
                </FONT>
                </TD>
                <TD ALIGN=RIGHT>
                <FONT COLOR=\"$paycolor\">
                <TT>&nbsp;</TT>
                </FONT>
                </TD>
                <TD>
                ";
                echo "\n   &nbsp;</TD></TR>";
            } // wend?
            $prev_proc = "$";
            while ($r = $sql->fetch_array ($pay_result)) {
                $payrecdate      = fm_date_print ($r["payrecdt"]);
                $payrecdescrip   = prepare ($r["payrecdescrip"]);
                $payrecamt       = prepare ($r["payrecamt"]);
                $payrectype      = $r["payrectype"];

                // start control break processing
                // first time in
                if ($prev_proc=="$")
                {
                    $prev_proc = $r["payrecproc"];
                    $proc_charges = 0.00;
                    $proc_payments = 0.00;
                }
                if ($prev_proc != $r["payrecproc"])
                {  // control break
                    $proc_total = $proc_charges - $proc_payments;
                    $proc_total = bcadd ($proc_total, 0, 2);
                    if ($proc_total<0)
                    {
                        $prc_total = "<FONT COLOR=\"#000000\">".
                                     $proc_total."</FONT>";
                    }
                    else
                    {
                        $prc_total = "<FONT COLOR==\"#ff0000\">".
                                     $proc_total."</FONT>";
                    } // end of creating total string/color

                    // display the total payments
                    echo "
                    <TR BGCOLOR=\"".($_alternate=freemed_bar_alternate_color($_alternate))."\">
                    <TD><B><$STDFONT_B SIZE=-1>SUBTOT<$STDFONT_E></B></TD>
                    <TD>&nbsp;</TD>
                    <TD>&nbsp;</TD>
                    <TD ALIGN=RIGHT>
                    <FONT COLOR=\"#ff0000\"><TT>".bcadd($proc_charges,0,2)."</TT></FONT>
                    </TD>
                    <TD ALIGN=RIGHT>
                    <TT>".bcadd($proc_payments,0,2)."</TT>
                    </TD>
                    <TD ALIGN=RIGHT>
                    <B><TT>$prc_total</TT></B>
                    </TD>
                    <TD>&nbsp;</TD>
                    </TR>
                    <TR BGCOLOR=\"".
                    ($_alternate = freemed_bar_alternate_color ($_alternate))
					."\">
                    <TD COLSPAN=7>&nbsp;</TD>
                    </TR>
                    ";
                    $prev_proc = $r["payrecproc"];
                    $proc_charges = 0.00;
                    $proc_payments = 0.00;
                } // end control break

                // end control break processing
                switch ($r["payreccat"]) { // category switch
                case REFUND: // refunds 2
                case PROCEDURE: // charges 5
                    $pay_color       = "#000000";
                    $payment         = "&nbsp;";
                    $charge          = bcadd($payrecamt, 0, 2);
                    $total_charges  += $payrecamt;
                    $proc_charges   += $payrecamt;
                    break;
                case REBILL: // rebills 4
                    $payment         = "&nbsp;";
                    $charge          = "&nbsp;";
                    break;
                case DENIAL: // denials 3
                    $pay_color       = "#000000";
                    $charge          = bcadd(-$payrecamt, 0, 2);
                    $payment         = "&nbsp;";
                    $total_charges  += $charge;
                    $proc_charges   += $charge;
                    break;
                case TRANSFER: // transfer 6
                    $payment         = "&nbsp;";
                    $charge          = "&nbsp;";
                    break;
                case WITHHOLD: // withhold 7
                case DEDUCTABLE: // deductable 8
                    $pay_color       = "#000000";
                    $charge          = bcadd(-$payrecamt, 0, 2);
                    $payment         = "&nbsp;";
                    $total_charges  += $charge;
                    $proc_charges   += $charge;
                    break;
                case FEEADJUST: // feeadjust 9
                    $pay_color       = "#000000";
                    $charge          = bcadd(-$payrecamt, 0, 2);
                    $payment         = "&nbsp;";
                    $total_charges  += $charge;
                    $proc_charges   += $charge;
                    break;
                case BILLED: // billed on 10
                    $payment         = "&nbsp;";
                    $charge          = "&nbsp;";
                    break;
                case ADJUSTMENT: // adjustments 1
                    $pay_color       = "#ff0000";
                    $payment         = bcadd(-$payrecamt, 0, 2);
                    $charge          = "&nbsp;";
                    $total_payments += $payment;
                    $proc_payments += $payment;
                    break;
            	case PAYMENT: default: // default is payments 0
            	case COPAY: default: // default is payments 0
                    $pay_color       = "#ff0000";
                    $payment         = bcadd($payrecamt, 0, 2);
                    $charge          = "&nbsp;";
                    $total_payments += $payrecamt;
                    $proc_payments += $payrecamt;
                    break;
                } // end of category switch (for totals)
                switch ($r["payreccat"]) {
                case ADJUSTMENT: // adjustments 1
                    $this_type = _("Adjustment");
                    break;
                case REFUND: // refunds 2
                    $this_type = _("Refund");
                    break;
                case DENIAL: // denial 3
                    $this_type = _("Denial");
                    break;
                case REBILL: // rebill 4
                    $this_type = _("Rebill");
                    break;
                case PROCEDURE: // charge 5
                    $this_type = _("Charge");
                    break;
                case TRANSFER: // transfer 6
                    $this_type = _("Transfer to")." ".$PAYER_TYPES[$r["payrecsource"]];
                    break;
                case WITHHOLD: // withhold 7
                    $this_type = _("Withhold");
                    break;
                case DEDUCTABLE: // deductable 8
                    $this_type = _("Deductable");
                    break;
                case FEEADJUST: // feeadjust 9
                    $this_type = _("Fee Adjust");
                    break;
                case BILLED: // billed 10
                    $this_type = _("Billed")." ".$PAYER_TYPES[$r["payrecsource"]];
                    break;
                case COPAY: // COPAY 11
                    $this_type = _("Copay");
                    break;
                case PAYMENT: // payment 0
                default:  // default is payment
                    $this_type = _("Payment")." ".$PAYER_TYPES[$r["payrecsource"]];
                    break;
                } // end of categry switch (name)
                $id              = $r["id"];
                if (empty($payrecdescrip)) $payrecdescrip="NO DESCRIPTION";
                echo "
                <TR BGCOLOR=\"".
                ($_alternate = freemed_bar_alternate_color ($_alternate)).
                "\">
                <TD>$payrecdate</TD>
                <TD><B>$this_type</B></TD>
                <TD><I>$payrecdescrip</I></TD>
                <TD ALIGN=RIGHT>
                <FONT COLOR=\"#ff0000\">
                <TT><B>".$charge."</B></TT>
                </FONT>
                </TD>
                <TD ALIGN=RIGHT>
                <FONT COLOR=\"#000000\">
                <TT><B>".$payment."</B></TT>
                </FONT>
                </TD>
                <TD ALIGN=RIGHT>&nbsp;</TD>
                <TD ALIGN=RIGHT>
                ";

                //if (($this_user->getLevel() > $delete_level) and
                //	 ($r[payreclock] != "locked"))
                //  echo "
                //  <A HREF=\"$page_name?$_auth&id=$id&patient=$patient&action=del\"
                //  ><$STDFONT_B>"._("DEL")."<$STDFONT_E></A>
                //  ";

                echo "&nbsp;</TD></TR>";
            } // wend?

            // process last subtotal
            // calc last proc subtotal
            $proc_total = $proc_charges - $proc_payments;
            $proc_total = bcadd ($proc_total, 0, 2);
            if ($proc_total<0)
            {
                $prc_total = "<FONT COLOR=\"#000000\">".
                             $proc_total."</FONT>";
            }
            else
            {
                $prc_total = "<FONT COLOR=\"#ff0000\">".
                             $proc_total."</FONT>";
            } // end of creating total string/color

            // display the total payments
            echo "
            <TR BGCOLOR=\"".
            ($_alternate = freemed_bar_alternate_color ($_alternate))
			."\">
            <TD><B><$STDFONT_B SIZE=\"-1\">SUBTOT<$STDFONT_E></B></TD>
            <TD>&nbsp;</TD>
            <TD>&nbsp;</TD>
            <TD ALIGN=RIGHT>
            <FONT COLOR=\"#ff0000\"><TT>".bcadd($proc_charges,0,2)."</TT></FONT>
            </TD>
            <TD ALIGN=RIGHT>
            <TT>".bcadd($proc_payments,0,2)."</TT>
            </TD>
            <TD ALIGN=RIGHT>
            <B><TT>$prc_total</TT></B>
            </TD>
            <TD>&nbsp;</TD>
            </TR>
            <TR BGCOLOR=\"".
            ($_alternate = freemed_bar_alternate_color ($_alternate))
			."\">
            <TD COLSPAN=7>&nbsp;</TD>
            </TR>
            ";
            // end calc last proc subtotal

            // calculate patient ledger total
            $patient_total = $total_charges - $total_payments;
            $patient_total = bcadd ($patient_total, 0, 2);
            if ($patient_total<0) {
                $pat_total = "<FONT COLOR=\"#000000\">".
                             $patient_total."</FONT>";
            } else {
                $pat_total = "<FONT COLOR=\"#ff0000\">".
                             $patient_total."</FONT>";
            } // end of creating total string/color

            // display the total payments
            echo "
            <TR BGCOLOR=\"".
            ($_alternate = freemed_bar_alternate_color ($_alternate))
			."\">
            <TD><B><$STDFONT_B SIZE=-1>TOTAL<$STDFONT_E></B></TD>
            <TD>&nbsp;</TD>
            <TD>&nbsp;</TD>
            <TD ALIGN=RIGHT>
            <FONT COLOR=\"#ff0000\"><TT>".bcadd($total_charges,0,2)."</TT></FONT>
            </TD>
            <TD ALIGN=RIGHT>
            <TT>".bcadd($total_payments,0,2)."</TT>
            </TD>
            <TD ALIGN=RIGHT>
            <B><TT>$pat_total</TT></B>
            </TD>
            <TD>&nbsp;</TD>
            </TR>
            ";

        } // end ledger

        function mistake($procid)
        {
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS))
            {
                global $$k;
                //echo "$$k $v<BR>";
            }

            if (isset($delete))
            {
                $query = "DELETE FROM procrec WHERE id='".addslashes($procid)."'";
                $result = $sql->query($query);
                $query = "DELETE FROM payrec WHERE payrecproc='".addslashes($procid)."'";
                $result = $sql->query($query);
                echo "
                <P>
                <CENTER>
                <B>"._("All records for this procedure have been deleted.")."</B><BR><BR>
                <A HREF=\"$this->page_name?_auth=$_auth&been_here=1&viewaction=refresh".
                "&action=addform&item=$payrecproc&patient=$patient&module=$module\">
                <$STDFONT_B>"._("Back")."<$STDFONT_E></A>
                </CENTER>
                <P>
                ";
                return;

            } // end mistake

            echo "
            <P>
            <CENTER>
            "._("Confirm delete request or cancel?")."<P>
            <A HREF=\"$this->page_name?_auth=$_auth&been_here=1&viewaction=mistake".
            "&action=addform&delete=1&item=$procid&patient=$patient&module=$module\">
            <$STDFONT_B>"._("Confirm")."<$STDFONT_E></A>&nbsp;|&nbsp;
            <A HREF=\"$this->page_name?_auth=$_auth&been_here=1&viewaction=refresh".
            "&action=addform&item=$procid&patient=$patient&module=$module\">
            <$STDFONT_B>"._("Cancel")."<$STDFONT_E></A>
            </CENTER>
            <P>
            ";



        }
        function view()
        {
            global $sql,$patient,$module;

            echo "<FORM ACTION=\"$this->page_name\" METHOD=POST>
            <INPUT TYPE=HIDDEN NAME=\"_auth\"   VALUE=\"$_auth\"  >
            <INPUT TYPE=HIDDEN NAME=\"patient\" VALUE=\"$patient\">
            <INPUT TYPE=HIDDEN NAME=\"action\" VALUE=\"addform\">
            <INPUT TYPE=HIDDEN NAME=\"module\" VALUE=\"$module\">
            ";

            // initialize line item count
            $line_item_count = 0;

            $query = "SELECT * FROM procrec
                     WHERE ( (procpatient = '".addslashes($patient)."') AND
                     (procbalcurrent ".$this->view_query.") )
                     ORDER BY procdt,id";

            $result = $sql->query ($query);

            echo "
            <TABLE BORDER=0 CELLSPACING=0 CELLPADDING=3 WIDTH=100%>
            <TR BGCOLOR=\"#cccccc\">
            <TD>&nbsp;</TD>
            <TD ALIGN=LEFT><B>"._("Date")."</B></TD>
            <TD ALIGN=LEFT><B>"._("Proc Code")."</B></TD>
            <TD ALIGN=LEFT><B>"._("Provider")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Charged")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Allowed")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Charges")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Paid")."</B></TD>
            <TD ALIGN=RIGHT><B>"._("Balance")."</B></TD>
            <TD ALIGN=LEFT><B>"._("Billed")."</B></TD>
            <TD ALIGN=LEFT><B>"._("Date Billed")."</B></TD>
            <TD ALIGN=LEFT><B>"._("View")."</B></TD>
            </TR>
            ";

            // loop for all "line items"
            while ($r = $sql->fetch_array ($result))
            {
                $line_item_count++;
                $this_cpt = freemed_get_link_field ($r[proccpt], "cpt", "cptnameint");
                $this_cptcode = freemed_get_link_field ($r[proccpt], "cpt", "cptcode");
                $this_cptmod = freemed_get_link_field ($r[proccptmod],
                                                       "cptmod", "cptmod");
                $this_physician = new Physician ($r[procphysician]);
                echo "
                <TR BGCOLOR=".( ($this->item == $r[id]) ?  "#00ffff" :
                                ($_alternate = freemed_bar_alternate_color ($_alternate))).">
                <TD>
                <INPUT TYPE=RADIO NAME=\"item\" VALUE=\"".prepare($r[id])."\"
                ".( ($r[id] == $this->item) ?  "CHECKED": "" )."></TD>
                <TD ALIGN=LEFT>".fm_date_print ($r[procdt])."</TD>
                <TD ALIGN=LEFT>".prepare($this_cptcode." (".$this_cpt.")")."</TD>
                <TD ALIGN=LEFT>".prepare($this_physician->fullName())."&nbsp;</TD>
                <TD ALIGN=RIGHT>".bcadd ($r[procbalorig], 0, 2)."</TD>
                <TD ALIGN=RIGHT>".bcadd ($r[procamtallowed], 0, 2)."</TD>
                <TD ALIGN=RIGHT>".bcadd ($r[proccharges], 0, 2)."</TD>
                <TD ALIGN=RIGHT>".bcadd ($r[procamtpaid], 0, 2)."</TD>
                <TD ALIGN=RIGHT>".bcadd ($r[procbalcurrent], 0, 2)."</TD>
                <TD ALIGN=LEFT>".(($r[procbilled]) ? _("Yes") : _("No") )."</TD>
                <TD ALIGN=LEFT>".( !empty($r[procdtbilled]) ?
					prepare($r[procdtbilled]) : "&nbsp;" )."</TD>
                <TD ALIGN LEFT><A HREF=\"$this->page_name?_auth=$_auth&action=addform".
                "&module=$module&been_here=1&patient=$patient&viewaction=ledger&item=$r[id]\"
                >Ledger</A>
                </TR>
                ";
            } // end looping for results

            echo "
            </TABLE>
            <P>
            <CENTER>
            <SELECT NAME=\"viewaction\">
            <OPTION VALUE=\"refresh\"  >"._("Refresh")."
            <OPTION VALUE=\"rebill\"  >"._("Rebill")."
            <OPTION VALUE=\"payment\" >"._("Payment")."
            <OPTION VALUE=\"copay\" >"._("Copay")."
            <OPTION VALUE=\"adjustment\" >"._("Adjustment")."
            <OPTION VALUE=\"deductable\" >"._("Deductable")."
            <OPTION VALUE=\"withhold\" >"._("Withhold")."
            <OPTION VALUE=\"transfer\">"._("Transfer")."
            <OPTION VALUE=\"allowedamt\">"._("Allowed Amount")."
            <OPTION VALUE=\"denial\"  >"._("Denial")."
            <OPTION VALUE=\"refund\">"._("Refund")."
            <OPTION VALUE=\"mistake\" >"._("Mistake")."
            <OPTION VALUE=\"ledgerall\">"._("Ledger")."
            <OPTION VALUE=\"closed\">"._("Closed")."
            </SELECT>
            <INPUT TYPE=SUBMIT VALUE=\"  "._("Select Line Item")."  \">
            <INPUT TYPE=HIDDEN NAME=\"been_here\" VALUE=\"1\">
            </CENTER>
            </FORM>
            ";
        } // end view function
		
		function insuranceSelectionByType($proccovmap)
		{
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS))
			$returned_string = "";
			
			$cov_ids = explode(":",$proccovmap);

			$cnt = count($cov_ids);
			for ($i=0;$i<$cnt;$i++)
			{
				if ($i != 0)
				{
					if ($cov_ids[$i] != 0)
					{
						$insid = freemed_get_link_field($cov_ids[$i],"coverage","covinsco");
						$insname = freemed_get_link_field($insid,"insco","insconame");
						$returned_string .= "<OPTION VALUE=\"".$i."\">".$insname."\n";
					}
				}
				else
				{
					$returned_string .= "<OPTION VALUE=\"".$i."\">"._("Patient")."\n";
				}
			}
			return $returned_string;

		}

		function insuranceName($coverage)
		{
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS))
			$insid = freemed_get_link_field($coverage,"coverage","covinsco");
			$insname = freemed_get_link_field($insid,"insco","insconame");
			return $insname;

		}

		function coverageIDFromType($proccovmap, $type)
		{
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS))
			$cov_ids = explode(":",$proccovmap);
			return $cov_ids[$type];
			
		}

		function insuranceSelection($proccovmap)
		{
            reset ($GLOBALS);
            while (list($k,$v)=each($GLOBALS))
			$returned_string = "";
			
			$cov_ids = explode(":",$proccovmap);
			$cnt = count($cov_ids);
			for ($i=0;$i<$cnt;$i++)
			{
				if ($i != 0)
				{
					if ($cov_ids[$i] != 0)
					{
						$insid = freemed_get_link_field($cov_ids[$i],"coverage","covinsco");
						$insname = freemed_get_link_field($insid,"insco","insconame");
						$returned_string .= "<OPTION VALUE=\"".$cov_ids[$i]."\">".$insname."\n";
					}
				}
			}
			return $returned_string;
		}

    } // end class testModule


    register_module("PaymentModule");

} // end if not defined

?>
