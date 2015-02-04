<?php
	require_once( 'db.inc.php' );
	require_once( 'facilities.inc.php' );

	$subheader=__("Data Center PDU Detail");

	$pdu=new PowerDistribution();
	$cab=new Cabinet();
	$powerConn=new PowerConnection();
	$connDev=new Device();
	$template=new CDUTemplate();
	$templateList=$template->GetTemplateList();
	$manufacturer=new Manufacturer();
	$upTime='';

	// Ajax actions
	// List of devices in the rack
	if(isset($_POST['c'])){
		$connDev->Cabinet=$_POST['c'];
		header('Content-Type: application/json');
		$devices=$connDev->ViewDevicesByCabinet();
		// filter for rights
		foreach($devices as $i => $dev){
			if($dev->Rights!="Write"){unset($devices[$i]);}
		}
		echo json_encode($devices);
		exit;
	}
	if(isset($_POST['pdu'])){
		$powerConn->PDUID=$_POST['pdu'];
		$powerConn->PDUPosition=$_POST['output'];
		$powerConn->GetPDUConnectionByPosition();
		if(isset($_POST['deviceid']) && isset($_POST['devinput'])){
			$powerConn->DeviceID=$_POST['deviceid'];
			$powerConn->DeviceConnNumber=$_POST['devinput'];
			if($powerConn->DeviceID=="" || $powerConn->DeviceConnNumber==""){
				$powerConn->RemoveConnection();
				echo 1;
			}else{
				echo $powerConn->CreateConnection();
			}
			exit;
		}
		if(isset($_POST['output'])){
			header('Content-Type: application/json');
			$connDev->DeviceID=$powerConn->DeviceID;
			$connDev->GetDevice();
			$powerConn->DeviceLabel=$connDev->Label;
			echo json_encode($powerConn);
			exit;
		}
		exit;
	}

	if(isset($_REQUEST['pid']) && isset($_POST['confirmdelete'])){
		$pdu->PDUID=$_POST['pid'];
		if($pdu->DeletePDU()){
			echo 'ok';
		}else{
			echo 'no';
		}
		exit;
	}

	if(isset($_POST['test'])){
		$pdu->PDUID=$_POST["test"];
		$pdu->GetPDU();
		
		$template=new CDUTemplate();
		$template->TemplateID=$pdu->TemplateID;
		$template->GetTemplate();
		
		printf( "<p>%s %s.<br>\n", __("Testing SNMP communication to CDU"), $pdu->Label );
		printf( "%s %s.<br>\n", __("Connecting to IP address"), $pdu->IPAddress );
		if ( $pdu->SNMPCommunity != "" ) {
			$Community = $pdu->SNMPCommunity;
			printf( "%s %s.</p>\n", __("Using SNMP Community string"), $Community );
		} else {
			$Community = $config->ParameterArray["SNMPCommunity"];
			printf( "%s %s.</p>\n", __("Using default SNMP Community string"), $Community );
		}
		
		print "<div id=\"infopanel\"><fieldset><legend>".__("Results")."</legend>\n";

		if ( $template->ATS ) {
			$ATSStatus = $pdu->getATSStatus();
			printf( "ATS Status returned = %s.  Desired status = %s.\n", $ATSStatus, $template->ATSDesiredResult );
		}
		
		$upTime=$pdu->GetSmartCDUUptime();
		if($upTime!=""){
			printf("<p>%s: %s</p>\n", __("SNMP Uptime"),$upTime);
		}else{
			print "<p>".__("SNMP Uptime did not return a valid value.")."</p>\n";
		}
		
		$cduVersion = $pdu->GetSmartCDUVersion();
		if($cduVersion != ""){
			printf( "<p>%s %s.  %s</p>\n", __("VersionOID returned a value of"), $cduVersion, __("Please check to see if it makes sense.") );
		}else{
			print "<p>".__("The OID for Firmware Version did not return a value.  Please check your MIB table.")."</p>\n";
		}
		
                // Check if ports are to be queried for power by their own
                if ($template->DiscretePowerForPorts == 1) {
                    $OID1template = $template->OID1;
                    if ($OID1template != '') {
                        $index = strpos($OID1template, "?");
                        $foreOID1 = substr($OID1template, 0, $index);
                        $backOID1 = "";
                        if ($index != strlen($OID1template - 1)) {
                            $backOID1 = substr($OID1template, ($index + 1));
                        }
                    }
                    $OID2template = $template->OID2;
                    if ($OID2template != '') {
                        $index = strpos($OID2template, "?");
                        $foreOID2 = substr($OID2template, 0, $index);
                        $backOID2 = "";
                        if ($index != strlen($OID2template - 1)) {
                            $backOID2 = substr($OID2template, ($index + 1));
                        }
                    }
                    $OID3template = $template->OID3;
                    if ($OID3template != '') {
                        $index = strpos($OID3template, "?");
                        $foreOID3 = substr($OID3template, 0, $index);
                        $backOID3 = "";
                        if ($index != strlen($OID3template - 1)) {
                            $backOID3 = substr($OID3template, ($index + 1));
                        }
                    }
                    $TotalPowerUsage=0;
                    for ($i=1;$i<=$template->NumOutlets;$i++) {
                        $data = array(
                            "OID1" => "",
                            "OID2" => "",
                            "OID3" => ""
                        );
                        // query OID1
                        if ($OID1template != '') {
                            // build OID1
                            $OID1 = sprintf("%s%s%s", $foreOID1, $i, $backOID1);
                            // query OID1
                            $ret = PowerDistribution::GetSNMPObject($pdu->IPAddress, $Community, $template->SNMPVersion, $OID1);
                            if ($ret === FALSE) {
                                printf("<b>Error while processing OID1 for port %s on CDU with IP %s!</b>\n", $i, $pdu->IPAddress);
                                break;
                            }
                            $data["OID1"] = $ret;
                        }
                        // query OID2
                        if ($OID2template != '') {
                            // build OID2
                            $OID2 = sprintf("%s%s%s", $foreOID2, $i, $backOID2);
                            $ret = PowerDistribution::GetSNMPObject($pdu->IPAddress, $Community, $template->SNMPVersion, $OID2);
                            if ($ret === FALSE) {
                                printf("<b>Error while processing OID2 for port %s on CDU with IP %s!</b>\n", $i, $pdu->IPAddress);
                                break;
                            }
                            $data["OID2"] = $ret;
                        }
                        // query OID3
                        if ($OID3template != '') {
                            // build OID3
                            $OID3 = sprintf("%s%s%s", $foreOID3, $index, $backOID3);
                            $ret = PowerDistribution::GetSNMPObject($pdu->IPAddress, $Community, $template->SNMPVersion, $OID3);
                            if ($ret === FALSE) {
                                printf("<b>Error while processing OID3 for port %s on CDU with IP %s!</b>\n", $i, $pdu->IPAddress);
                                break;
                            }
                            $data["OID3"] = $ret;
                        }
                        $ret = PowerDistribution::HandleProcessingProfiles($template->ProcessingProfile, $template->Multiplier,$template->Voltage, $data["OID1"], $data["OID2"], $data["OID3"]);
                        if ($ret === FALSE) {
                            printf("Something went wrong during processing of the power values.\n");
                            break;
                        }
                        printf("Power usage of port <b>%s</b>: %s<br>",$i, $ret);
                        $TotalPowerUsage+=$ret;
                    }
                    printf("<p>%s %.2f kW</p>", __("Resulting kW from this test is"),$TotalPowerUsage/1000);
                } else {
                    if($template->OID1) {
                        $ret = PowerDistribution::GetSNMPObject($pdu->IPAddress, $Community, $template->SNMPVersion, $OID1);
                        if ($ret === FALSE) {
                            printf("<b>Error while processing OID1 for port %s on CDU with IP %s!</b>\n", $i, $pdu->IPAddress);
                        }
                    }
                    if($template->OID2) {
                        $ret = PowerDistribution::GetSNMPObject($pdu->IPAddress, $Community, $template->SNMPVersion, $OID2);
                        if ($ret === FALSE) {
                            printf("<b>Error while processing OID2 for port %s on CDU with IP %s!</b>\n", $i, $pdu->IPAddress);
                        }
                        
                    }
                    if($template->OID3) {
                        $ret = PowerDistribution::GetSNMPObject($pdu->IPAddress, $Community, $template->SNMPVersion, $OID3);
                        if ($ret === FALSE) {
                            printf("<b>Error while processing OID3 for port %s on CDU with IP %s!</b>\n", $i, $pdu->IPAddress);
                        }
                    }
                    $watts = PowerDistribution::HandleProcessingProfiles($template->ProcessingProfile, $template->Multiplier,$template->Voltage, $data["OID1"], $data["OID2"], $data["OID3"]);
                    printf("<p>%s %.2f kW</p>", __("Resulting kW from this test is"),$watts/1000);
                    
                }
		echo '	</fieldset></div>';
		exit;
	}


	if(isset($_POST["currwatts"]) && isset($_POST['pduid']) && $_POST['pduid'] >0){
		$pdu->PDUID=$_POST['pduid'];
		$wattage->Wattage='Err';
		$wattage->LastRead='Err';
		if($pdu->GetPDU()){
			$cab->CabinetID=$pdu->CabinetID;
			$cab->GetCabinet();
			if($user->canWrite($cab->AssignedTo)){
				$wattage=$pdu->LogManualWattage($_POST["currwatts"]);
				$wattage->LastRead=strftime("%c",strtotime($wattage->LastRead));
			}
		}
		header('Content-Type: application/json');
		echo json_encode($wattage);
		exit;
	}

	// END - Ajax


	if(isset($_REQUEST['pduid'])){
		$pdu->PDUID=(isset($_REQUEST['pduid'])?(isset($_POST['pduid']))?$_POST['pduid']:$_GET['pduid']:$_GET['pduid']);
	}else{
		echo 'Do not call this file directly';
		exit;
	}

	$pdu->GetPDU();
	$cab->CabinetID=(isset($_REQUEST['cabinetid']))?$_REQUEST['cabinetid']:$pdu->CabinetID;
	$cab->GetCabinet();

	if(isset($_REQUEST['action']) && (($_REQUEST['action']=='Create') || ($_REQUEST['action']=='Update')) && $user->canWrite($cab->AssignedTo)) {
		$pdu->Label=$_REQUEST['label'];
		$pdu->CabinetID=$_REQUEST['cabinetid'];
		$pdu->TemplateID=$_REQUEST['templateid'];
		$pdu->IPAddress=$_REQUEST['ipaddress'];
		$pdu->SNMPCommunity=$_REQUEST['snmpcommunity'];
		$pdu->PanelID=$_REQUEST['panelid'];
		$pdu->BreakerSize=$_REQUEST['breakersize'];
		$pdu->PanelPole=$_REQUEST['panelpole'];
		$pdu->InputAmperage=$_REQUEST['inputamperage'];
                $pdu->Notes=$_REQUEST['notes'];
                $pdu->Fuse=$_REQUEST['fuse'];
		// If failsafe is unset clear auto transfer switch panel information
		if(isset($_REQUEST['failsafe'])){
			$pdu->FailSafe=1;
			$pdu->PanelID2=$_REQUEST['panelid2'];
			$pdu->PanelPole2=$_REQUEST['panelpole2'];
		}else{
			$pdu->FailSafe=0;
			$pdu->PanelID2="";
			$pdu->PanelPole2="";
		}

		if($_REQUEST['action']=='Create'){
			$ret=$pdu->CreatePDU();
		}else{
			$pdu->PDUID = $_REQUEST['pduid'];
			$pdu->UpdatePDU();
		}
	}

	if($pdu->PDUID >0){
		$upTime=$pdu->GetSmartCDUUptime();
		
		$template->TemplateID=$pdu->TemplateID;
		$template->GetTemplate();
	} else {
		$pdu->CabinetID=$_REQUEST['cabinetid'];
	}

	$lastreading=$pdu->GetLastReading();
	$LastWattage=($lastreading)?$lastreading->Wattage:0;
	$LastRead=($lastreading)?strftime("%c",strtotime($lastreading->LastRead)):"Never";	

	$cab->CabinetID=$pdu->CabinetID;
	$cab->GetCabinet();

	$write=$user->canWrite($cab->AssignedTo);
	
	$Panel=new PowerPanel();
	$PanelList=$Panel->GetPanelList();
	/* For strict panel selection, comment out the line above and uncomment the following line */
	// $PanelList = $Panel->GetPanelsByDataCenter( $cab->DataCenterID,  );

	$powerConn->PDUID=$pdu->PDUID;
	$connList=$powerConn->GetConnectionsByPDU();

	$title=($pdu->Label!='')?"$pdu->Label :: $pdu->PDUID":'Data Center PDU Detail';

?>
<!doctype html>
<html>
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  
  <title><?php echo $title; ?></title>
  <link rel="stylesheet" href="css/inventory.php" type="text/css">
  <link rel="stylesheet" href="css/jquery-ui.css" type="text/css">
  <link rel="stylesheet" href="css/print.css" type="text/css" media="print">
  <!--[if lt IE 9]>
  <link rel="stylesheet"  href="css/ie.css" type="text/css">
  <![endif]-->

  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/jquery-ui.min.js"></script>
  <script type="text/javascript">
	$(document).ready(function() {
		$('#panelid').change( function(){
			$.get('scripts/ajax_panel.php?q='+$(this).val(), function(data) {
				$('#voltage').html(data['PanelVoltage'] +'/'+ Math.floor(data['PanelVoltage']/1.73));
			});
		});
		$('#pdutestlink').click(function(e){
			e.preventDefault();
			$.post('power_pdu.php', {test: $('#pduid').val()}, function(data){
				$('#pdutest').html(data);
			});
			$('#pdutest').dialog({minWidth: 450, maxWidth: 450, closeOnEscape: true });
		});
		$('.pdu #btn_override').on('click',function(e){
			var btn=$(e.currentTarget);
			var target=$(e.currentTarget.previousSibling);
			if(btn.val()=='edit'){
				btn.val('submit').text(btn.data('submit')).css('height','2em');
				target.replaceWith($('<input>').attr('size',5).val(target.text()));
			}else{
				btn.val('edit').text(btn.data('edit')).css('height','');
				$.post('',{currwatts: target.val(), pduid:$('#pduid').val()}).done(function(data){
					target.replaceWith($('<span>').text(data.Wattage));
					$('#lastread').text(data.LastRead);
				});
			}
		});


		$('.center > div + div > .table > div:first-child ~ div').each(function(){
			var row=$(this);
			var output=row.find('div:first-child');
			if(portrights[output.text()]){
				output.click(function(){
					if(!row.data('edit')){
						function update(){
							if(input.val()=='' && select.val()==''){
								save();
							}else if(input.val()!='' && select.val()!=''){
								save();
							}
						}
						var pduid=$('#pduid');
						var cabid=$('#cabinetid').val();
						var option=$('<option>');
						var select=$('<select>').append(option).on('focusout',update).css('background-color','transparent');
						var input=$('<input>').on('focusout',update).css('background-color','transparent');
						var btn_delete=$('<button>').text('Delete');
						var btn_cancel=$('<button>').text('Cancel');
						var controls=$('<div>').css('padding','0px').append(btn_delete).append(btn_cancel);
						var device=output.next();
						var devinput=device.next();
						var width=devinput.width();
						btn_delete.click(function(){
							select.val('');
							input.val('');
							select.focus();
							input.focus();
						});
						btn_cancel.click(function(){
							redraw();
						});
						function save(){
							$.post('',{pdu: $('#pduid').val(), output: output.text(), deviceid: select.val(), devinput: input.val()}).done(function(data){
								if(data.trim()=='1'){
									//success
									row.effect('highlight', {color: 'lightgreen'}, 1500);
									redraw();
								}else{
									//fail
									row.effect('highlight', {color: 'salmon'}, 1500);
								}
							});
						}
						function redraw(){
							$.post('',{pdu: $('#pduid').val(), output: output.text()}).done(function(data){
								var link=$('<a>').text(data.DeviceLabel).prop('href','devices.php?deviceid='+data.DeviceID);
								data.DeviceConnNumber=(data.DeviceID==0)?'':data.DeviceConnNumber;
								device.data('device',data.DeviceID).html(link).prop('style','');
								devinput.data('input',data.DeviceConnNumber).text(data.DeviceConnNumber).prop('style','');
								controls.remove();
								row.data('edit',false);
							});
						}
						row.data('edit', true);
						$.post('',{c: cabid}).done(function(data){
							$.each(data, function(i,dev){
								select.append(option.clone().val(dev.DeviceID).text(dev.Label));
							});
							device.html(select.val(device.data('device'))).css('padding','0px');
							devinput.html(input.val(devinput.data('input')).width(width)).css('padding','0px');
							row.append(controls);
						});
					}
					resize();
				}).css({'cursor': 'pointer', 'text-decoration': 'underline'});
			}
		});
		$('.main button[value=Delete]').click(function(){
			var defaultbutton={
				"<?php echo __("Yes"); ?>": function(){
					$.post('', {pid: $('#pduid').val(),confirmdelete: ''}, function(data){
						if(data.trim()=='ok'){
							self.location=$('.main > a').last().attr('href');
							$(this).dialog("destroy");
						}else{
							alert('Nope');
						}
					});
				}
			}
			var cancelbutton={
				"<?php echo __("No"); ?>": function(){
					$(this).dialog("destroy");
				}
			}
<?php echo "			var modal=$('<div />', {id: 'modal', title: '".__("PDU Deletion Confirmation")."'}).html('<div id=\"modaltext\">".__("Are you sure that you want to delete this PDU and all the power connections on it?")."</div>').dialog({"; ?>
				dialogClass: 'no-close',
				appendTo: 'body',
				modal: true,
				buttons: $.extend({}, defaultbutton, cancelbutton)
			});
		});
	});
  </script>


</head>
<body>
<?php include( 'header.inc.php' ); ?>
<div class="page pdu">
<?php
	include( 'sidebar.inc.php' );

echo '<div class="main">
<div class="center"><div class="left">
<form name="pduform" id="pduform" action="',$_SERVER["PHP_SELF"],'" method="POST">
<div class="table">
<div>
   <div><label for="pduid">',__("PDU ID"),'</label></div>
   <div><input type="text" name="pduid" id="pduid" value="',$pdu->PDUID,'" size="6" readonly></div>
</div>
<div>
   <div><label for="label">',__("Label"),'</label></div>
   <div><input type="text" name="label" id="label" size="50" value="',$pdu->Label,'"></div>
</div>
<div>
   <div><label for="cabinetid">',__("Cabinet"),'</label></div>
   <div>',$cab->GetCabinetSelectList(),'</div>
</div>
<div>
   <div><label for="panelid">',__("Source Panel"),'</label></div>
   <div><select name="panelid" id="panelid" ><option value=0>',__("Select Panel"),'</option>';

foreach($PanelList as $key=>$value){
	if($value->PanelID == $pdu->PanelID){$selected=' selected';}else{$selected="";}
	print "<option value=\"$value->PanelID\"$selected>$value->PanelLabel</option>\n"; 
}

echo '   </select></div>
</div>
<div>
	<div><label for="voltage">',__("Voltages:"),'</label></div>
	<div id="voltage">';

	if($pdu->PanelID >0){
		$pnl=new PowerPanel();
		$pnl->PanelID=$pdu->PanelID;
		$pnl->GetPanel();
	
		print $pnl->PanelVoltage." / ".intval($pnl->PanelVoltage/1.73);
	}

echo '	</div>
</div>
<div>
  <div><label for="breakersize">',__("Breaker Size (# of Poles)"),'</label></div>
  <div>
	<select name="breakersize">';

	for($i=1;$i<4;$i++){
		if($i==$pdu->BreakerSize){$selected=" selected";}else{$selected="";}
		print "<option value=\"$i\"$selected>$i</option>";
	}

echo '	</select>
  </div>
</div>
<div>
  <div><label for="panelpole">',__("Panel Pole Number"),'</label></div>
  <div><input type="text" name="panelpole" id="panelpole" size=5 value="',$pdu->PanelPole,'"></div>
</div>';

if($pdu->BreakerSize>1) {
	echo '
	<div>
  	  <div><label for="allbreakerpoles">',__("All Breaker Poles"),'</label></div>
  	  <div>',$pdu->GetAllBreakerPoles(),'</div>
	</div>';
}
echo '
<div>
   <div><label for="inputamperage">',__("Input Amperage"),'</label></div>
   <div><input type="text" name="inputamperage" id="inputamperage" size=5 value="',$pdu->InputAmperage,'"></div>
</div>
<div>
	<div><label for="templateid">',__("CDU Template"),'</label></div>
	<div><select name="templateid" id="templateid">';

	foreach($templateList as $templateRow){
		$manufacturer->ManufacturerID=$templateRow->ManufacturerID;
		$manufacturer->GetManufacturerByID();
		
		$selected=($pdu->TemplateID==$templateRow->TemplateID)?" selected":"";		
		print "		<option value=$templateRow->TemplateID$selected>[$manufacturer->Name] $templateRow->Model</option>\n";
	}
	
echo '   </select></div>
</div>
<div>
   <div><label for="ipaddress">',__("IP Address / Host Name"),'</label></div>
   <div><input type="text" name="ipaddress" id="ipaddress" size=15 value="',$pdu->IPAddress,'">',((strlen($pdu->IPAddress)>0)?"<a href=\"http://$pdu->IPAddress\" target=\"new\">http://$pdu->IPAddress</a>":""),'</div>
</div>
<div>
   <div><label for="snmpcommunity">',__("SNMP Community"),'</label></div>
   <div><input type="text" name="snmpcommunity" id="snmpcommunity" size=15 value="',$pdu->SNMPCommunity,'"><a id="pdutestlink" href="#">', __("Test Communications"), '</a></div>
</div>';

// Only show the version, etc if we aren't creating a CDU
if($pdu->PDUID>0){
echo '
<div>
    <div>',__("Uptime"),'</div>
    <div>',$upTime,'</div>
</div>
<div>
    <div>',__("Firmware Version"),'</div>
    <div>',$pdu->FirmwareVersion,'</div>
</div>
<div>
	<div><label for="currwatts">',__("Wattage"),'</label></div>
	<div><span>',$LastWattage,'</span><button type="button" id="btn_override" value="edit" data-edit="',__("Manual Entry"),'" data-submit="',__("Submit"),'">',__("Manual Entry"),'</button></div>
</div>
<div>
	<div>',__("Last Update"),':</div>
	<div id="lastread">',$LastRead,'</div>
</div>';         
}

// Notice field
echo '
<div>
    <div>',__("Notes"),':</div>
    <div><textarea name="notes" id="notes" cols="40" rows="8">',$pdu->Notes,'</textarea></div>
</div>';
// Fuse number
echo '
<div>
    <div>',__("Fuse"),': </div>
    <div><input type="text" name="fuse" id="fuse" size=5 value="',$pdu->Fuse,'"></div>
</div>';

echo '
<div class="caption">
<h3>',__("Automatic Transfer Switch"),'</h3>
<fieldset id="powerinfo">
<div class="table centermargin border">
<div>
  <div><label for="failsafe">',__("Fail Safe Switch?"),'</label></div>
  <div><input type="checkbox" name="failsafe" id="failsafe"',(($pdu->FailSafe)?" checked":""),'></div>
</div>
<div>
   <div><label for="panelid2">',__("Source Panel (Secondary Source)"),'</label></div>
   <div><select name="panelid2" id="panelid2"><option value=0>',__("Select Panel"),'</option>';

	foreach($PanelList as $key=>$value){
		if($value->PanelID==$pdu->PanelID2){$selected=" selected";}else{$selected="";}
		print "		<option value=$value->PanelID$selected>$value->PanelLabel</option>\n";
	}

echo '   </select></div>
</div>
<div>
  <div><label for="panelpole2">',__("Panel Pole Number (Secondary Source)"),'</label></div>
  <div><input type="text" name="panelpole2" id="panelpole2" size=4 value="',$pdu->PanelPole2,'"></div>
</div>
<div class="caption">';

	if($pdu->PDUID >0){
		if($write || $user->SiteAdmin){
			echo '   <button type="submit" name="action" value="Update">',__("Update"),'</button>';
			if($user->SiteAdmin){
				echo '   <button type="button" name="action" value="Delete">',__("Delete"),'</button>';
			}
		}
	}else{
		if($write || $user->SiteAdmin){
			echo '   <button type="submit" name="action" value="Create">',__("Create"),'</button>';
		}
	}

echo '</div>
</div> <!-- END div.table -->
</div>
</div> <!-- END div.table -->
</form>

</div><div class="right">

<div class="table border">
	<div>
		<div>',__("Output No."),'</div>
		<div>',__("Device Name"),'</div>
		<div>',__("Dev Input No"),'</div>
                <div>',__("Power Usage"),'</div>
	</div>';
        // Get port usage 
        $sql="SELECT * FROM fac_PowerPorts WHERE PDUID=$pdu->PDUID;";
        global $dbh;
        $result = $dbh->query($sql);
	$portrights=array();
	for($connNumber=1; $connNumber<$template->NumOutlets+1; $connNumber++){
            $row=$result->fetch();
		if(isset($connList[$connNumber])){
			$connDev->DeviceID=$connList[$connNumber]->DeviceID;
			$connDev->GetDevice();
			$portrights[$connNumber]=($connDev->Rights=="Write")?true:$write;
                        print "	<div>\n		<div>$connNumber</div>\n		<div alt=\"{$connList[$connNumber]->DeviceID}\" data-device=\"{$connList[$connNumber]->DeviceID}\"><a href=\"devices.php?deviceid={$connList[$connNumber]->DeviceID}\">$connDev->Label</a></div>\n		<div data-input=\"{$connList[$connNumber]->DeviceConnNumber}\">{$connList[$connNumber]->DeviceConnNumber}</div>\n                <div>{$row["Wattage"]}</div>\n </div>\n";
		}else{
			$portrights[$connNumber]=$write;
			print "	<div>\n		<div>$connNumber</div>\n		<div alt=\"\"></div>\n		<div></div>\n                <div>{$row["Wattage"]}</div>\n	</div>\n";
		}
	}
	
	// If there are any connections > NumOutlets, print them as ghosts
	foreach ( $connList as $ghostConnection ) {
		if ( $ghostConnection->PDUPosition > $template->NumOutlets ) {
			$connDev->DeviceID=$ghostConnection->DeviceID;
			$connDev->GetDevice();
			$portrights[$connNumber]=($connDev->Rights=="Write")?true:$write;
			print "	<div>\n		<div>$ghostConnection->PDUPosition</div>\n		<div alt=\"{$ghostConnection->DeviceID}\" data-device=\"{$ghostConnection->DeviceID}\"><a href=\"devices.php?deviceid={$ghostConnection->DeviceID}\">$connDev->Label</a></div>\n		<div data-input=\"{$ghostConnection->DeviceConnNumber}\">{$ghostConnection->DeviceConnNumber}</div>\n	</div>\n";
		}
	}
?>  
    </div> <!-- END div.table -->
</div></div>

<div id="pdutest" title="Testing SNMP Communications"></div>

<?php echo '<a href="cabnavigator.php?cabinetid=',$cab->CabinetID,'">[ ',__("Return to Navigator"),' ]</a>'; ?>
</div><!-- END div.main -->
</div><!-- END div.page -->
<script type="text/javascript">
	var portrights=$.parseJSON('<?php echo json_encode($portrights); ?>');

<?php
	if($pdu->PDUID >0 && !$write){
		print "$('.main select, .main input').prop('disabled', true);";
	}
?>
</script>
</body>
</html>
