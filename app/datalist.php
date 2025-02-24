<?php

define('datalist_filters_count', 20);
define('datalist_image_uploads_exist', true);
define('datalist_max_records_multi_selection', 1000);
define('datalist_max_page_lump', 50);
define('datalist_max_records_dv_print', 100);
define('datalist_auto_complete_size', 1000);
define('datalist_date_separator', '/');
define('datalist_date_format', 'mdY');
define('datalist_max_records_per_page', 2000);

$curr_dir = dirname(__FILE__);
require_once($curr_dir . '/combo.class.php');
require_once($curr_dir . '/data_combo.class.php');
require_once($curr_dir . '/date_combo.class.php');

class DataList{
	// this class generates the data table ...

	var $QueryFieldsTV,
		$QueryFieldsCSV,
		$QueryFieldsFilters,
		$QueryFieldsQS,
		$QueryFrom,
		$QueryWhere,
		$QueryOrder,
		$filterers,

		$ColWidth, // array of field widths
		$TableName,

		$AllowSelection,
		$AllowDelete,
		$AllowMassDelete,
		$AllowDeleteOfParents,
		$AllowInsert,
		$AllowUpdate,
		$SeparateDV,
		$Permissions,
		$AllowFilters,
		$AllowSavingFilters,
		$AllowSorting,
		$AllowNavigation,
		$AllowPrinting,
		$AllowPrintingDV,
		$HideTableView,
		$AllowCSV,
		$CSVSeparator,

		$QuickSearch, // 0 to 3

		$RecordsPerPage,
		$ScriptFileName,
		$RedirectAfterInsert,
		$TableTitle,
		$PrimaryKey,
		$DefaultSortField,
		$DefaultSortDirection,

		// Templates variables
		$Template,
		$SelectedTemplate,
		$TemplateDV,
		$TemplateDVP,
		$ShowTableHeader, // 1 = show standard table headers
		$TVClasses,
		$DVClasses,
		// End of templates variables

		$AllowDVNavigation, // true = show next/prev record buttons in DV
		$AllowConsoleLog, // true = show logs in client-side console ... use only for debugging, not suitable for production

		$ContentType,    // set by DataList to 'tableview', 'detailview', 'tableview+detailview', 'print-tableview', 'print-detailview' or 'filters'
		$HasCalculatedFields,
		$HTML;           // generated html after calling Render()

	function __construct() {  // PHP 7 compatibility
		$this->DataList();
	}

	function DataList() {     // Constructor function
		global $Translation;
		$this->translation = $Translation;

		$this->AllowSelection = 1;
		$this->AllowDelete = 1;
		$this->AllowInsert = 1;
		$this->AllowUpdate = 1;
		$this->AllowFilters = 1;
		$this->AllowNavigation = 1;
		$this->AllowPrinting = 1;
		$this->AllowPrintingDV = 1;
		$this->HideTableView = 0;
		$this->QuickSearch = 0;
		$this->AllowCSV = 0;
		$this->CSVSeparator = ',';

		$this->AllowDVNavigation = true;
		$this->AllowConsoleLog = false;

		$this->RecordsPerPage = 10;
		$this->Template = '';
		$this->HTML = '';
		$this->filterers = [];
	}

	function showTV() {
		if($this->SeparateDV) {
			$this->HideTableView = ($this->Permissions['view'] == 0 ? 1 : 0);
		}
	}

	function hideTV() {
		if($this->SeparateDV) {
			$this->HideTableView = 1;
		}
	}

	function Render() {
		$adminConfig = config('adminConfig');

		$FiltersPerGroup = 4;

		$current_view = ''; /* TV, DV, TVDV, TVP, DVP, Filters */

		$Embedded = intval($_REQUEST['Embedded']);
		$AutoClose = intval($_REQUEST['AutoClose']);

		$SortField = $_REQUEST['SortField'];
		$SortDirection = $_REQUEST['SortDirection'];
		$FirstRecord = intval($_REQUEST['FirstRecord']);
		$Previous_x = $_REQUEST['Previous_x'];
		$Next_x = $_REQUEST['Next_x'];
		$Filter_x = $_REQUEST['Filter_x'];
		$SaveFilter_x = $_REQUEST['SaveFilter_x'];
		$NoFilter_x = $_REQUEST['NoFilter_x'];
		$CancelFilter = $_REQUEST['CancelFilter'];
		$ApplyFilter = $_REQUEST['ApplyFilter'];
		$Search_x = $_REQUEST['Search_x'];
		$SearchString = $_REQUEST['SearchString'];
		$CSV_x = $_REQUEST['CSV_x'];
		$Print_x = $_REQUEST['Print_x'];
		$PrintTV = $_REQUEST['PrintTV'];
		$PrintDV = $_REQUEST['PrintDV'];
		$SelectedID = $_REQUEST['SelectedID'];
		$insert_x = $_REQUEST['insert_x'];
		$update_x = $_REQUEST['update_x'];
		$delete_x = $_REQUEST['delete_x'];
		$SkipChecks = $_REQUEST['confirmed'];
		$deselect_x = $_REQUEST['deselect_x'];
		$addNew_x = $_REQUEST['addNew_x'];
		$dvprint_x = $_REQUEST['dvprint_x'];
		$DisplayRecords = (in_array($_REQUEST['DisplayRecords'], ['user', 'group']) ? $_REQUEST['DisplayRecords'] : 'all');
		list($FilterAnd, $FilterField, $FilterOperator, $FilterValue) = $this->validate_filters($_REQUEST, $FiltersPerGroup);
		$record_selector = [];
		if(isset($_REQUEST['record_selector']) && is_array($_REQUEST['record_selector']))
			$record_selector = $_REQUEST['record_selector'];

		if($SelectedID && !$Embedded && $this->AllowDVNavigation) {
			$setSelectedIDPreviousPage = !empty($_REQUEST['setSelectedIDPreviousPage']);
			$setSelectedIDNextPage = !empty($_REQUEST['setSelectedIDNextPage']) && !$setSelectedIDPreviousPage;
			$previousRecordDV = !empty($_REQUEST['previousRecordDV']) && !$setSelectedIDPreviousPage && !$setSelectedIDNextPage;
			$nextRecordDV = !empty($_REQUEST['nextRecordDV']) && !$previousRecordDV && !$setSelectedIDPreviousPage && !$setSelectedIDNextPage;
		}

		$mi = getMemberInfo();

		// validate user inputs
		if(!preg_match('/^\s*[1-9][0-9]*\s*(asc|desc)?(\s*,\s*[1-9][0-9]*\s*(asc|desc)?)*$/i', $SortField)) {
			$SortField = '';
		}
		if(!preg_match('/^(asc|desc)$/i', $SortDirection)) {
			$SortDirection = '';
		}

		if(!$this->AllowDelete) $delete_x = '';
		if(!$this->AllowDeleteOfParents) $SkipChecks = '';
		if(!$this->AllowInsert) $insert_x = $addNew_x = '';
		if(!$this->AllowUpdate) $update_x = '';
		if(!$this->AllowFilters) $Filter_x = '';
		if(!$this->AllowPrinting) $Print_x = $PrintTV = '';
		if(!$this->AllowPrintingDV) $PrintDV = '';
		if(!$this->QuickSearch) $SearchString = '';
		if(!$this->AllowCSV) $CSV_x = '';

		// enforce record selection if user has edit/delete permissions on the current table
		$this->Permissions = getTablePermissions($this->TableName);
		if($this->Permissions['edit'] || $this->Permissions['delete']) {
			$this->AllowSelection = 1;
		} elseif(!$this->AllowSelection) {
			$SelectedID = '';
			$PrintDV = '';
		}

		if(!$this->AllowSelection || !$SelectedID) { $dvprint_x = ''; }

		$this->QueryFieldsIndexed = reIndex($this->QueryFieldsFilters);

		// determine type of current view: TV, DV, TVDV, TVP, DVP or Filters?
		if($this->SeparateDV) {
			$current_view = 'TV';
			if($Print_x != '' || $PrintTV != '') $current_view = 'TVP';
			elseif($dvprint_x != '' || $PrintDV != '') $current_view = 'DVP';
			elseif($Filter_x != '') $current_view = 'Filters';
			elseif(($SelectedID && !$deselect_x && !$delete_x) || $addNew_x != '') $current_view = 'DV';
		} else {
			$current_view = 'TVDV';
			if($Print_x != '' || $PrintTV != '') $current_view = 'TVP';
			elseif($dvprint_x != '' || $PrintDV != '') $current_view = 'DVP';
			elseif($Filter_x != '') $current_view = 'Filters';
		}

		$this->HTML .= '<div class="row' . ($this->HasCalculatedFields ? ' has-calculated-fields' : '') . '"><div class="col-xs-12">';
		$this->HTML .= '<form ' . (datalist_image_uploads_exist ? 'enctype="multipart/form-data" ' : '') . 'method="post" name="myform" action="' . $this->ScriptFileName . '">';
		if($Embedded) $this->HTML .= '<input name="Embedded" value="1" type="hidden">';
		if($AutoClose) $this->HTML .= '<input name="AutoClose" value="1" type="hidden">';
		$this->HTML .= csrf_token();
		$this->HTML .= '<script>';
		$this->HTML .= 'function enterAction() {';
		$this->HTML .= '   if($j("input[name=SearchString]:focus").length) { $j("#Search").click(); }';
		$this->HTML .= '   return false;';
		$this->HTML .= '}';
		$this->HTML .= '</script>';
		$this->HTML .= '<input id="EnterAction" type="submit" style="position: absolute; left: 0px; top: -250px;" onclick="return enterAction();">';

		$this->ContentType = 'tableview'; // default content type

		if($PrintTV != '') {
			$Print_x = 1;
			$_REQUEST['Print_x'] = 1;
		}

		// handle user commands ...
		if($deselect_x != '') {
			$SelectedID = '';
			$this->showTV();
		}

		elseif($insert_x != '') {
			$error_message = '';
			$SelectedID = call_user_func_array(
				$this->TableName . '_insert', 
				[&$error_message]
			);

			// redirect to a safe url to avoid refreshing and thus
			// insertion of duplicate records.
			$url = $this->RedirectAfterInsert;
			$insert_status = 'record-added-ok=' . rand();
			if(!$SelectedID)
				$insert_status = 'error_message=' . urlencode($error_message) .
								 '&record-added-error=' . rand();

			// compose filters and sorting
			foreach($this->filterers as $filterer => $caption) {
				if($_REQUEST['filterer_' . $filterer] != '') $filtersGET .= '&filterer_' . $filterer . '=' . urlencode($_REQUEST['filterer_' . $filterer]);
			}
			for($i = 1; $i <= (datalist_filters_count * $FiltersPerGroup); $i++) { // Number of filters allowed
				if($FilterField[$i] != '' && $FilterOperator[$i] != '' && ($FilterValue[$i] != '' || strpos($FilterOperator[$i], 'empty'))) {
					$filtersGET .= "&FilterAnd[{$i}]={$FilterAnd[$i]}&FilterField[{$i}]={$FilterField[$i]}&FilterOperator[{$i}]={$FilterOperator[$i]}&FilterValue[{$i}]=" . urlencode($FilterValue[$i]);
				}
			}
			if($Embedded) $filtersGET .= '&Embedded=1&SelectedID=' . urlencode($SelectedID);
			if($AutoClose) $filtersGET .= '&AutoClose=1';
			$filtersGET .= "&SortField={$SortField}&SortDirection={$SortDirection}&FirstRecord={$FirstRecord}";
			$filtersGET .= "&DisplayRecords={$DisplayRecords}";
			$filtersGET .= '&SearchString=' . urlencode($SearchString);
			$filtersGET = substr($filtersGET, 1); // remove initial &

			if($url) {
				/* if designer specified a redirect-after-insert url */
				$url .= (strpos($url, '?') !== false ? '&' : '?') . $insert_status;
				$url .= (strpos($url, $this->ScriptFileName) !== false ? "&{$filtersGET}" : '');
				$url = str_replace("#ID#", urlencode($SelectedID), $url);
			} else {
				/* if no redirect-after-insert url, use default */
				$url = "{$this->ScriptFileName}?{$insert_status}&{$filtersGET}";

				/* if DV and TV in same page, select new record */
				if(!$this->SeparateDV) $url .= '&SelectedID=' . urlencode($SelectedID);
			}

			@header('Location: ' . $url);
			$this->HTML .= "<META HTTP-EQUIV=\"Refresh\" CONTENT=\"0;url=" . $url ."\">";

			return;
		}

		elseif($delete_x != '') {
			$delete_res = call_user_func_array($this->TableName.'_delete', array($SelectedID, $this->AllowDeleteOfParents, $SkipChecks));
			// handle ajax delete requests
			if(is_ajax()) {
				die($delete_res ? $delete_res : 'OK');
			}

			if($delete_res) {
				//$_REQUEST['record-deleted-error'] = 1;
				$this->HTML .= showNotifications($delete_res, 'alert alert-danger', false);
				$this->hideTV();
				$current_view = ($this->SeparateDV ? 'DV' : 'TVDV');
			} else {
				$_REQUEST['record-deleted-ok'] = 1;
				$SelectedID = '';
				$this->showTV();

				/* close window if embedded */
				if($Embedded) {
					$this->HTML .= '<script>$j(function() { setTimeout(function() { AppGini.closeParentModal(); }, 2000); })</script>';
				}
			}
		}

		elseif($update_x != '') {
			$error_message = '';
			$updated = call_user_func_array(
				$this->TableName . '_update', 
				[&$SelectedID, &$error_message]
			);

			$update_status = 'record-updated-ok=' . rand();
			if($updated === false)
				$update_status = 'error_message=' . urlencode($error_message) . 
								 '&record-updated-error=' . rand();

			// compose filters and sorting
			foreach($this->filterers as $filterer => $caption) {
				if($_REQUEST['filterer_' . $filterer] != '') $filtersGET .= '&filterer_' . $filterer . '=' . urlencode($_REQUEST['filterer_' . $filterer]);
			}
			for($i = 1; $i <= (datalist_filters_count * $FiltersPerGroup); $i++) { // Number of filters allowed
				if($FilterField[$i] != '' && $FilterOperator[$i] != '' && ($FilterValue[$i] != '' || strpos($FilterOperator[$i], 'empty'))) {
					$filtersGET .= "&FilterAnd[{$i}]={$FilterAnd[$i]}&FilterField[{$i}]={$FilterField[$i]}&FilterOperator[{$i}]={$FilterOperator[$i]}&FilterValue[{$i}]=" . urlencode($FilterValue[$i]);
				}
			}
			$filtersGET .= "&SortField={$SortField}&SortDirection={$SortDirection}&FirstRecord={$FirstRecord}&Embedded={$Embedded}";
			if($AutoClose) $filtersGET .= '&AutoClose=1';
			$filtersGET .= "&DisplayRecords={$DisplayRecords}";
			$filtersGET .= '&SearchString=' . urlencode($SearchString);
			$filtersGET = substr($filtersGET, 1); // remove initial &

			$redirectUrl = $this->ScriptFileName . '?SelectedID=' . urlencode($SelectedID) . '&' . $filtersGET . '&' . $update_status;
			@header("Location: $redirectUrl");
			$this->HTML .= '<META HTTP-EQUIV="Refresh" CONTENT="0;url='.$redirectUrl.'">';
			return;
		}

		elseif($addNew_x != '') {
			$SelectedID = '';
			$this->hideTV();
		}

		elseif($Print_x != '') {
			// print code here ....
			$this->AllowNavigation = 0;
			$this->AllowSelection = 0;
		}

		elseif($SaveFilter_x != '' && $this->AllowSavingFilters) {
			$filter_link = $_SERVER['HTTP_REFERER'] . '?SortField=' . urlencode($SortField) . '&SortDirection=' . $SortDirection . '&';
			for($i = 1; $i <= (datalist_filters_count * $FiltersPerGroup); $i++) { // Number of filters allowed
				if(($FilterField[$i] != '' || $i == 1) && $FilterOperator[$i] != '' && ($FilterValue[$i] != '' || strpos($FilterOperator[$i], 'empty'))) {
					$filter_link .= urlencode("FilterAnd[$i]") . '=' . urlencode($FilterAnd[$i]) . '&';
					$filter_link .= urlencode("FilterField[$i]") . '=' . urlencode($FilterField[$i]) . '&';
					$filter_link .= urlencode("FilterOperator[$i]") . '=' . urlencode($FilterOperator[$i]) . '&';
					$filter_link .= urlencode("FilterValue[$i]") . '=' . urlencode($FilterValue[$i]) . '&';
				} elseif(($i % $FiltersPerGroup == 1) && in_array($FilterAnd[$i], array('and', 'or'))) {
					/* always include the and/or at the beginning of each group */
					$filter_link .= urlencode("FilterAnd[$i]") . '=' . urlencode($FilterAnd[$i]) . '&';
				}
			}
			$filter_link = substr($filter_link, 0, -1); /* trim last '&' */

			$this->HTML .= '<div id="saved_filter_source_code" class="row"><div class="col-md-6 col-md-offset-3">';
				$this->HTML .= '<div class="panel panel-info">';
					$this->HTML .= '<div class="panel-heading"><h3 class="panel-title">' . $this->translation["saved filters title"] . "</h3></div>";
					$this->HTML .= '<div class="panel-body">';
						$this->HTML .= $this->translation["saved filters instructions"];
						$this->HTML .= '<textarea rows="4" class="form-control vspacer-lg" style="width: 100%;" onfocus="$j(this).select();">' . "&lt;a href=\"{$filter_link}\"&gt;{$this->translation['saved filter link']}&lt;/a&gt;" . '</textarea>';
						$this->HTML .= "<div><a href=\"{$filter_link}\" title=\"" . html_attr($filter_link) . "\">{$this->translation['permalink']}</a></div>";
						$this->HTML .= '<button type="button" class="btn btn-default btn-block vspacer-lg" onclick="$j(\'#saved_filter_source_code\').remove();"><i class="glyphicon glyphicon-remove"></i> ' . $this->translation['hide code'] . '</button>';
					$this->HTML .= '</div>';
				$this->HTML .= '</div>';
			$this->HTML .= '</div></div>';
		}

		elseif($Filter_x != '') {
			$orderBy = [];
			if($SortField) {
				$sortFields = explode(',', $SortField);
				$i = 0;
				foreach($sortFields as $sf) {
					$tob = preg_split('/\s+/', $sf, 2);
					$orderBy[] = [trim($tob[0]) => (strtolower(trim($tob[1])) == 'desc' ? 'desc' : 'asc')];
					$i++;
				}
				$orderBy[$i - 1][$tob[0]] = (strtolower(trim($SortDirection)) == 'desc' ? 'desc' : 'asc');
			}

			// check if magic filter files exist
			$hooksDir = dirname(__FILE__) . '/hooks';
			$uff = "{$hooksDir}/{$this->TableName}.filters.{$mi['username']}.php"; // user-specific filter file
			$gff = "{$hooksDir}/{$this->TableName}.filters.{$mi['group']}.php"; // group-specific filter file
			$tff = "{$hooksDir}/{$this->TableName}.filters.php"; // table-specific filter file

			/*
				Look for filter files in the in this order:
					1. $this->FilterPage
					2. hooks/tablename.filters.username.php ($uff)
					3. hooks/tablename.filters.groupname.php ($gff)
					4. hooks/tablename.filters.php ($tff)
					5. defaultFilters.php
			*/
			if(!is_file($this->FilterPage)) {
				$this->FilterPage = 'defaultFilters.php';
				if(is_file($uff)) {
					$this->FilterPage = $uff;
				} elseif(is_file($gff)) {
					$this->FilterPage = $gff;
				} elseif(is_file($tff)) {
					$this->FilterPage = $tff;
				}
			}

			if($this->FilterPage != '') {
				$Translation = &$this->translation;
				ob_start();
				@include($this->FilterPage);
				$this->HTML .= ob_get_clean();
			}

			// hidden variables ....
			$this->HTML .= '<input name="SortField" value="' . html_attr($SortField) . '" type="hidden">';
			$this->HTML .= '<input name="SortDirection" type="hidden" value="' . html_attr($SortDirection) . '" >';
			$this->HTML .= '<input name="FirstRecord" type="hidden" value="1" >';
			$this->HTML .= '</form></div></div>';

			$this->ContentType = 'filters';
			return;
		}

		elseif($NoFilter_x != '') {
			// clear all filters ...
			for($i = 1; $i <= (datalist_filters_count * $FiltersPerGroup); $i++) { // Number of filters allowed
				$FilterField[$i] = '';
				$FilterOperator[$i] = '';
				$FilterValue[$i] = '';
			}
			$DisplayRecords = 'all';
			$SearchString = '';
			$FirstRecord = 1;

			// clear filterers
			foreach($this->filterers as $filterer => $caption) {
				$_REQUEST['filterer_' . $filterer] = '';
			}
		}

		elseif($SelectedID) {
			$this->hideTV();
		}

		// TV code, only if user has view permission
		if($this->Permissions['view']) {
			$QueryHasCustomWhere = (strlen($this->QueryWhere) > 0);

			// apply lookup filterers to the query
			foreach($this->filterers as $filterer => $caption) {
				if($_REQUEST['filterer_' . $filterer] != '') {
					if($this->QueryWhere == '')
						$this->QueryWhere = "where ";
					else
						$this->QueryWhere .= " and ";
					$this->QueryWhere .= "`{$this->TableName}`.`$filterer`='" . makeSafe($_REQUEST['filterer_' . $filterer]) . "' ";
					break; // currently, only one filterer can be applied at a time
				}
			}

			// apply quick search to the query
			if($SearchString != '') {
				if($Search_x != '') { $FirstRecord = 1; }

				if($this->QueryWhere == '')
					$this->QueryWhere = "where ";
				else
					$this->QueryWhere .= " and ";

				foreach($this->QueryFieldsQS as $fName => $fCaption)
					if(strpos($fName, '<img') === false)
						$this->QuerySearchableFields[$fName] = $fCaption;

				$this->QueryWhere .= '(' . implode(" LIKE '%" . makeSafe($SearchString) . "%' or ", array_keys($this->QuerySearchableFields)) . " LIKE '%" . makeSafe($SearchString) . "%')";
			}


			// set query filters
			// $this->QueryWhere might be empty or might contain a clause (starting with WHERE) to retrieve only user/group data
			$QueryHasWhere = preg_match('/^WHERE\s+/i', $this->QueryWhere);

			$WhereNeedsClosing = 0;
			for($i = 1; $i <= (datalist_filters_count * $FiltersPerGroup); $i += $FiltersPerGroup) { // Number of filters allowed
				// test current filter group
				$GroupHasFilters = 0;
				for($j = 0; $j < $FiltersPerGroup; $j++) {
					$ij = $i + $j;
					if(
						$FilterField[$ij] != '' && 
						$this->QueryFieldsIndexed[($FilterField[$ij])] != '' && 
						$FilterOperator[$ij] != '' && (
							$FilterValue[$ij] != '' || 
							strpos($FilterOperator[$ij], 'empty') !== false
						)
					) {
						$GroupHasFilters = 1;
						break;
					}
				}

				if($GroupHasFilters) {
					if(stripos($this->QueryWhere, 'where ') === false)
						$this->QueryWhere = 'WHERE (';
					elseif($QueryHasWhere) {
						$this->QueryWhere .= ' and (';
						$QueryHasWhere = 0;
					}

					$this->QueryWhere .= " <FilterGroup> " . $FilterAnd[$i] . " (";

					for($j = 0; $j < $FiltersPerGroup; $j++) {
						$ij = $i + $j;
						if($FilterField[$ij] != '' && $this->QueryFieldsIndexed[($FilterField[$ij])] != '' && $FilterOperator[$ij] != '' && ($FilterValue[$ij] != '' || strpos($FilterOperator[$ij], 'empty'))) {
							if($FilterAnd[$ij] == '') {
								$FilterAnd[$ij] = 'and';
							}
							// test for date/time fields
							$tries = 0; $isDateTime = $isDate = false;
							$fieldName = str_replace('`', '', $this->QueryFieldsIndexed[($FilterField[$ij])]);
							list($tn, $fn) = explode('.', $fieldName);
							while(!($res = sql("SHOW COLUMNS FROM `{$tn}` LIKE '{$fn}'", $eo)) && $tries < 2) {
								$tn = substr($tn, 0, -1);
								$tries++;
							}
							if($res !== false && $row = @db_fetch_array($res)) {
								$isDateTime = in_array($row['Type'], array('date', 'time', 'datetime'));
								$isDate = in_array($row['Type'], ['date', 'datetime']);
							}
							// end of test
							if($FilterOperator[$ij] == 'is-empty' && !$isDateTime) {
								$this->QueryWhere .= ' <FilterItem> ' . $FilterAnd[$ij] . ' (' . $this->QueryFieldsIndexed[($FilterField[$ij])] . "='' or " . $this->QueryFieldsIndexed[($FilterField[$ij])] . ' is NULL) </FilterItem>';
							} elseif($FilterOperator[$ij] == 'is-not-empty' && !$isDateTime) {
								$this->QueryWhere .= ' <FilterItem> ' . $FilterAnd[$ij] . " " . $this->QueryFieldsIndexed[($FilterField[$ij])] . "!='' </FilterItem>";
							} elseif($FilterOperator[$ij] == 'is-empty' && $isDateTime) {
								$this->QueryWhere .= " <FilterItem> " . $FilterAnd[$ij] . " (" . $this->QueryFieldsIndexed[($FilterField[$ij])] . "=0 or " . $this->QueryFieldsIndexed[($FilterField[$ij])] . " is NULL) </FilterItem>";
							} elseif($FilterOperator[$ij] == 'is-not-empty' && $isDateTime) {
								$this->QueryWhere .= " <FilterItem> " . $FilterAnd[$ij] . " " . $this->QueryFieldsIndexed[($FilterField[$ij])] . "!=0 </FilterItem>";
							} elseif($FilterOperator[$ij] == 'like' && !strstr($FilterValue[$ij], "%") && !strstr($FilterValue[$ij], "_")) {
								$this->QueryWhere .= " <FilterItem> " . $FilterAnd[$ij] . " " . $this->QueryFieldsIndexed[($FilterField[$ij])] . " like '%" . makeSafe($FilterValue[$ij]) . "%' </FilterItem>";
							} elseif($FilterOperator[$ij] == 'not-like' && !strstr($FilterValue[$ij], "%") && !strstr($FilterValue[$ij], "_")) {
								$this->QueryWhere .= " <FilterItem> " . $FilterAnd[$ij] . " " . $this->QueryFieldsIndexed[($FilterField[$ij])] . " not like '%" . makeSafe($FilterValue[$ij]) . "%' </FilterItem>";
							} elseif($isDate) {
								$dateValue = mysql_datetime($FilterValue[$ij]);
								$this->QueryWhere .= " <FilterItem> " . $FilterAnd[$ij] . " " . $this->QueryFieldsIndexed[($FilterField[$ij])] . " " . $GLOBALS['filter_operators'][$FilterOperator[$ij]] . " '$dateValue' </FilterItem>";
							} else {
								$this->QueryWhere .= " <FilterItem> " . $FilterAnd[$ij] . " " . $this->QueryFieldsIndexed[($FilterField[$ij])] . " " . $GLOBALS['filter_operators'][$FilterOperator[$ij]] . " '" . makeSafe($FilterValue[$ij]) . "' </FilterItem>";
							}
						}
					}

					$this->QueryWhere .= ") </FilterGroup>";
					$WhereNeedsClosing = 1;
				}
			}

			if($WhereNeedsClosing && !$QueryHasCustomWhere)
				$this->QueryWhere .= ")";

			// set query sort
			if(!stristr($this->QueryOrder, "order by ") && $SortField != '' && $this->AllowSorting) {
				$actualSortField = $SortField;
				foreach($this->SortFields as $fieldNum => $fieldSort) {
					$actualSortField = str_replace(" $fieldNum ", " $fieldSort ", " $actualSortField ");
					$actualSortField = str_replace(",$fieldNum ", ",$fieldSort ", " $actualSortField ");
				}
				$this->QueryOrder = "order by $actualSortField $SortDirection";
			}

			// clean up query
			$this->QueryWhere = str_replace('( <FilterGroup> and ', '( ', $this->QueryWhere);
			$this->QueryWhere = str_replace('( <FilterGroup> or ', '( ', $this->QueryWhere);
			$this->QueryWhere = str_replace('( <FilterItem> and ', '( ', $this->QueryWhere);
			$this->QueryWhere = str_replace('( <FilterItem> or ', '( ', $this->QueryWhere);
			$this->QueryWhere = str_replace('<FilterGroup>', '', $this->QueryWhere);
			$this->QueryWhere = str_replace('</FilterGroup>', '', $this->QueryWhere);
			$this->QueryWhere = str_replace('<FilterItem>', '', $this->QueryWhere);
			$this->QueryWhere = str_replace('</FilterItem>', '', $this->QueryWhere);

			// if no 'order by' clause found, apply default sorting if specified
			if($this->DefaultSortField != '' && $this->QueryOrder == '') {
				$this->QueryOrder = "order by {$this->DefaultSortField} {$this->DefaultSortDirection}";
			}

			// Output CSV on request
			if($CSV_x != '') {
				$this->outputCSV();
				exit;
			}

			// get count of matching records ...
			$RecordCount = sqlValue($this->buildQuery());
			if(!$RecordCount) $FirstRecord = 1;

			// table view navigation code ...
			if($RecordCount && $this->AllowNavigation && $RecordCount > $this->RecordsPerPage) {
				while($FirstRecord > $RecordCount)
					$FirstRecord -= $this->RecordsPerPage;

				if($FirstRecord == '' || $FirstRecord < 1) $FirstRecord = 1;

				if($Previous_x != '') {
					$FirstRecord -= $this->RecordsPerPage;
					if($FirstRecord <= 0)
						$FirstRecord = 1;
				} elseif($Next_x != '') {
					$FirstRecord += $this->RecordsPerPage;
					if($FirstRecord > $RecordCount)
						$FirstRecord = $RecordCount - ($RecordCount % $this->RecordsPerPage) + 1;
					if($FirstRecord > $RecordCount)
						$FirstRecord = $RecordCount - $this->RecordsPerPage + 1;
					if($FirstRecord <= 0)
						$FirstRecord = 1;
				}

			} elseif($RecordCount) {
				$FirstRecord = 1;
				$this->RecordsPerPage = datalist_max_records_per_page; // a limit on max records in print preview to avoid performance drops
			}
			// end of table view navigation code

			if($SelectedID && $RecordCount && !$Embedded && $this->AllowDVNavigation) {
				// in DV, if user is navigating to prev record, 
				// and current one is first record in page,
				// navigate to previous page (unless this is the first page)
				if($setSelectedIDPreviousPage) {
					if($FirstRecord > $this->RecordsPerPage) $FirstRecord -= $this->RecordsPerPage;

				// in DV, if user is navigating to next record, 
				// and current one is last record in page,
				// navigate to next page (unless this is the last page)
				} elseif($setSelectedIDNextPage) {
					if($RecordCount >= ($FirstRecord + $this->RecordsPerPage))
						$FirstRecord += $this->RecordsPerPage;
				}
			}

			$tvRecords = $this->getTVRevords($FirstRecord);
			$fieldCountTV = count($this->QueryFieldsTV);

			if($SelectedID && count($tvRecords) && !$Embedded) {
				if($setSelectedIDPreviousPage)
					// set SelectedID to pk of last item in $tvRecords
					$indexOfSelectedID = count($tvRecords) - 1;

				elseif($setSelectedIDNextPage)
					// set SelectedID to pk of first item in $tvRecords
					$indexOfSelectedID = 0;

				elseif($previousRecordDV || $nextRecordDV) {
					// get index of SelectedID record in $tvRecords
					for($i = 0; $i < count($tvRecords); $i++) {
						$rec = $tvRecords[$i];
						if($SelectedID != $rec[$fieldCountTV]) continue;

						$indexOfSelectedID = $i;

						if($previousRecordDV && $indexOfSelectedID > 0)
							$indexOfSelectedID--;

						elseif($nextRecordDV)
							$indexOfSelectedID++;

						break;
					}
				}

				$newID = $tvRecords[$indexOfSelectedID][$fieldCountTV];
				if($newID || $newID === 0) $SelectedID = $newID;
			}

			$t = time(); // just a random number for any purpose ...

			// should SelectedID be reset on clicking TV buttons?
			$resetSelection = $this->resetSelection();

			if($current_view == 'DV' && !$Embedded) {
				$this->HTML .= '<div class="page-header">';
					$this->HTML .= '<h1>';
						$this->HTML .= '<a style="text-decoration: none; color: inherit;" href="' . $this->TableName . '_view.php"><img src="' . $this->TableIcon . '"> ' . $this->TableTitle . '</a>';
						/* show add new button if user can insert and there is a selected record */
						if($SelectedID && $this->Permissions['insert'] && $this->SeparateDV && $this->AllowInsert) {
							$this->HTML .= ' <button type="submit" id="addNew" name="addNew_x" value="1" class="btn btn-success"><i class="glyphicon glyphicon-plus-sign"></i> ' . $this->translation['Add New'] . '</button>';
						}
					$this->HTML .= '</h1>';
				$this->HTML .= '</div>';
			}

			// quick search and TV action buttons
			$tvRowNeedsClosing = false;
			if(!$this->HideTableView && !($dvprint_x && $this->AllowSelection && $SelectedID) && !$PrintDV) {
				/* if user can print DV, add action to 'More' menu */
				$selected_records_more = [];

				if($this->AllowPrintingDV) {
					$selected_records_more[] = array(
						'function' => ($this->SeparateDV ? 'print_multiple_dv_sdv' : 'print_multiple_dv_tvdv'),
						'title' => $this->translation['Print Preview Detail View'],
						'icon' => 'print'
					);
				}

				/* if user can mass-delete selected records, add action to 'More' menu */
				if($this->AllowMassDelete && $this->AllowDelete) {
					$selected_records_more[] = array(
						'function' => 'mass_delete',
						'title' => $this->translation['Delete'],
						'icon' => 'trash',
						'class' => 'text-danger'
					);
				}

				/* if user is admin, add 'Change owner' action to 'More' menu */
				/* also, add help link for adding more actions */
				if(getLoggedAdmin() !== false) {
					$selected_records_more[] = array(
						'function' => 'mass_change_owner',
						'title' => $this->translation['Change owner'],
						'icon' => 'user'
					);
					$selected_records_more[] = array(
						'function' => 'add_more_actions_link',
						'title' => $this->translation['Add more actions'],
						'icon' => 'question-sign',
						'class' => 'text-info'
					);
				}

				/* user-defined actions ... should be set in the {tablename}_batch_actions() function in hooks/{tablename}.php */
				$user_actions = [];
				if(function_exists($this->TableName.'_batch_actions')) {
					$args = [];
					$user_actions = call_user_func_array($this->TableName . '_batch_actions', array(&$args));
					if(is_array($user_actions) && count($user_actions)) {
						$selected_records_more = array_merge($selected_records_more, $user_actions);
					}
				}

				$actual_more_count = 0;
				$more_menu = $more_menu_js = '';
				if(count($selected_records_more)) {
					$more_menu .= '<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" id="selected_records_more"><i class="glyphicon glyphicon-check"></i> ' . $this->translation['More'] . ' <span class="caret"></span></button>';
					$more_menu .= '<ul class="dropdown-menu" role="menu">';
					foreach($selected_records_more as $action) {
						if(!$action['function'] || !$action['title']) continue;
						$action['class'] = (!isset($action['class']) ? '' : $action['class']);
						$action['icon'] = (!isset($action['icon']) ? '' : $action['icon']);
						$actual_more_count++;
						$more_menu .= '<li>' .
								'<a href="#" id="selected_records_' . $action['function'] . '">' .
									'<span class="' . $action['class'] . '">' .
										($action['icon'] ? '<i class="glyphicon glyphicon-' . $action['icon'] . '"></i> ' : '') .
										$action['title'] .
									'</span>' .
								'</a>' .
							'</li>';

						// on clicking an action, call its js handler function, passing the current table name and an array of selected IDs to it
						$more_menu_js .= "jQuery('[id=selected_records_{$action['function']}]').click(function() { {$action['function']}('{$this->TableName}', get_selected_records_ids()); return false; });";
					}
					$more_menu .= '</ul>';
				}

				if($Embedded) {
					$this->HTML .= '<script>$j(function() { $j(\'[id^=notification-]\').parent().css({\'margin-top\': \'15px\', \'margin-bottom\': \'0\'}); })</script>';
				} else {
					$this->HTML .= '<div class="page-header">';
						$this->HTML .= '<h1>';
							$this->HTML .= '<div class="row">';
								$this->HTML .= '<div class="col-sm-8">';
									$this->HTML .= '<a style="text-decoration: none; color: inherit;" href="' . $this->TableName . '_view.php"><img src="' . $this->TableIcon . '"> ' . $this->TableTitle . '</a>';
									/* show add new button if user can insert and there is a selected record */
									if($SelectedID && $this->Permissions['insert'] && !$this->SeparateDV && $this->AllowInsert) {
										$this->HTML .= ' <button type="submit" id="addNew" name="addNew_x" value="1" class="btn btn-success"><i class="glyphicon glyphicon-plus-sign"></i> ' . $this->translation['Add New'] . '</button>';
									}
								$this->HTML .= '</div>';
								if($this->QuickSearch && $Print_x == '') {
									$this->HTML .= '<div class="col-sm-4">';
										$this->HTML .= quick_search_html($SearchString, $this->QuickSearchText, $this->SeparateDV);
									$this->HTML .= '</div>';
								}
							$this->HTML .= '</div>';
						$this->HTML .= '</h1>';
					$this->HTML .= '</div>';

					$this->HTML .= '<div id="top_buttons" class="hidden-print">';
						/* .all_records: container for buttons that don't need a selection */
						/* .selected_records: container for buttons that need a selection */
						$this->HTML .= '<div class="btn-group btn-group-lg visible-md visible-lg all_records pull-left">' . $this->getTVButtons($Print_x) . '</div>';
						$this->HTML .= '<div class="btn-group btn-group-lg visible-md visible-lg selected_records hidden pull-left hspacer-lg">' . ($actual_more_count ? $more_menu : '') . '</div>';
						$this->HTML .= '<div class="btn-group-vertical btn-group-lg visible-xs visible-sm all_records">' . $this->getTVButtons($Print_x) . '</div>';
						$this->HTML .= '<div class="btn-group-vertical btn-group-lg visible-xs visible-sm selected_records hidden vspacer-lg">' . ($actual_more_count ? $more_menu : '') . '</div>';
						$this->HTML .= $this->tv_tools();
						$this->HTML .= '<p></p>';
					$this->HTML .= '</div>';

					$this->HTML .= '<div class="row"><div data-table="' . $this->TableName . '" class="table-' . $this->TableName . ' table_view col-xs-12 ' . $this->TVClasses . '">';
					$tvRowNeedsClosing = true;
				}

				if($Print_x != '') {
					/* fix top margin for print-preview */
					$this->HTML .= '<style>body{ padding-top: 0 !important; }</style>';

					/* disable links inside table body to prevent printing their href */
					$this->HTML .= '<script>jQuery(function() { jQuery("tbody a").removeAttr("href").removeAttr("rel"); });</script>';
				}

				// script for focusing into the search box on loading the page
				// and for declaring record action handlers
				$this->HTML .= '<script>jQuery(function() { ' . (!isset($_REQUEST['noQuickSearchFocus']) ? 'jQuery("input[name=SearchString]").focus();' : '') . '' . $more_menu_js . ' });</script>';

			}

			// begin table and display table title
			if(!$this->HideTableView && !($dvprint_x && $this->AllowSelection && $SelectedID) && !$PrintDV && !$Embedded) {
				$this->HTML .= '<div class="table-responsive"><table data-tablename="' . $this->TableName . '" class="table table-striped table-bordered table-hover">';

				$this->HTML .= '<thead><tr>';
				if(!$Print_x) $this->HTML .= '<th style="width: 18px;" class="text-center"><input class="hidden-print" type="checkbox" title="' . html_attr($this->translation['Select all records']) . '" id="select_all_records"></th>';

				// Templates
				$rowTemplate = $selrowTemplate = '';
				if($this->Template) {
					$rowTemplate = @file_get_contents('./' . $this->Template);
					if($rowTemplate && $this->SelectedTemplate) {
						$selrowTemplate = @file_get_contents('./' . $this->SelectedTemplate);
					}
				}

				// process translations
				$rowTemplate = parseTemplate($rowTemplate);
				$selrowTemplate = parseTemplate($selrowTemplate);
				// End of templates

				// $this->ccffv: map $FilterField values to field captions as stored in ColCaption
				$this->ccffv = [];
				foreach($this->ColCaption as $captionIndex => $caption) {
					$ffv = 1;
					foreach($this->QueryFieldsFilters as $filterCaption) {
						if($caption == $filterCaption) {
							$this->ccffv[$captionIndex] = $ffv;
						}
						$ffv++;
					}
				}

				// display table headers
				$forceHeaderWidth = false;
				if($rowTemplate == '' || $this->ShowTableHeader) {
					for($i = 0; $i < count($this->ColCaption); $i++) {
						/* Sorting icon and link */
						$sort1 = $sort2 = $filterHint = '';
						if($this->AllowSorting == 1) {
							if($current_view != 'TVP') {
								$sort1 = "<a href=\"{$this->ScriptFileName}?SortDirection=asc&SortField=".($this->ColNumber[$i])."\" onClick=\"$resetSelection document.myform.NoDV.value=1; document.myform.SortDirection.value='asc'; document.myform.SortField.value = '".($this->ColNumber[$i])."'; document.myform.submit(); return false;\" class=\"TableHeader\">";
								$sort2 = "</a>";
							}
							if($this->ColNumber[$i] == $SortField) {
								$SortDirection = ($SortDirection == 'asc' ? 'desc' : 'asc');
								if($current_view != 'TVP')
									$sort1 = "<a href=\"{$this->ScriptFileName}?SortDirection=$SortDirection&SortField=".($this->ColNumber[$i])."\" onClick=\"$resetSelection document.myform.NoDV.value=1; document.myform.SortDirection.value='$SortDirection'; document.myform.SortField.value = ".($this->ColNumber[$i])."; document.myform.submit(); return false;\" class=\"TableHeader\">";
								$sort2 = ' <i class="text-warning glyphicon glyphicon-sort-by-attributes' . ($SortDirection == 'desc' ? '' : '-alt') . "\"></i>{$sort2}";
								$SortDirection = ($SortDirection == 'asc' ? 'desc' : 'asc');
							}
						}

						/* Filtering icon and hint */
						if($this->AllowFilters && is_array($FilterField)) {
							// check to see if there is any filter applied on the current field
							if(isset($this->ccffv[$i]) && in_array($this->ccffv[$i], $FilterField)) {
								// render filter icon
								$filterHint = '&nbsp;<button type="submit" class="btn btn-default btn-xs' . ($current_view == 'TVP' ? ' disabled' : '') . '" name="Filter_x" value="1" title="'.html_attr($this->translation['filtered field']).'"><i class="glyphicon glyphicon-filter"></i></button>';
							}
						}

						$this->HTML .= "\t<th class=\"{$this->TableName}-{$this->ColFieldName[$i]}\" " . ($forceHeaderWidth ? ' style="width: ' . ($this->ColWidth[$i] ? $this->ColWidth[$i] : 100) . 'px;"' : '') . ">{$sort1}{$this->ColCaption[$i]}{$sort2}{$filterHint}</th>\n";
					}
				} elseif($current_view != 'TVP') {
					// Display a Sort by drop down
					$this->HTML .= "\t<th class=\"hidden-print\" colspan=\"" . (count($this->ColCaption)) . "\">";
					$this->HTML .= "\t<div class=\"pull-right\" id=\"order-by-selector\">";

					if($this->AllowSorting == 1) {
						$sortCombo = new Combo;
						for($i = 0; $i < count($this->ColCaption); $i++) {
							$sortCombo->ListItem[] = $this->ColCaption[$i];
							$sortCombo->ListData[] = $this->ColNumber[$i];
						}
						$sortCombo->SelectName = "FieldsList";
						$sortCombo->SelectedData = $SortField;
						$sortCombo->Class = '';
						$sortCombo->SelectedClass = '';
						$sortCombo->Render();
						$sortby_dropdown = $sortCombo->HTML;
						$sortby_dropdown = str_replace('<select ', "<select onChange=\"document.myform.SortDirection.value='$SortDirection'; document.myform.SortField.value=document.myform.FieldsList.value; document.myform.NoDV.value=1; document.myform.submit();\" ", $sortby_dropdown);
						if($SortField) {
							$SortDirection = ($SortDirection == 'desc' ? 'asc' : 'desc');
							$sort_class = ($SortDirection == 'asc' ? 'sort-by-attributes-alt' : 'sort-by-attributes');
							$sort = "<a href=\"javascript: document.myform.NoDV.value = 1; document.myform.SortDirection.value = '{$SortDirection}'; document.myform.SortField.value = '{$SortField}'; document.myform.submit();\" class=TableHeader><i class=\"text-warning glyphicon glyphicon-{$sort_class}\"></i></a>";
							$SortDirection = ($SortDirection == 'desc' ? 'asc' : 'desc');
						} else {
							$sort = '';
						}

						$sortby_sep = '<span class="hspacer-md"></span>';

						$this->HTML .= "{$this->translation['order by']}{$sortby_sep}{$sortby_dropdown}{$sortby_sep}{$sort}{$sortby_sep}";
					}
					$this->HTML .= "</div><style>#s2id_FieldsList{ min-width: 12em; width: unset !important; }</style></th>\n";
				}

				$this->HTML .= "\n\t</tr>\n\n</thead>\n\n<tbody><!-- tv data below -->\n";

				$i = 0;
				if($RecordCount) {
					$i = $FirstRecord;

					// change below to walking through array $tvRecords ... 
					reset($tvRecords);
					while(($row = current($tvRecords)) && ($i < ($FirstRecord + $this->RecordsPerPage))) {
						next($tvRecords); // advance tvRecords pointer to the next element
						$currentID = $row[$fieldCountTV];

						/* skip displaying the current record if we're in TVP or multiple DVP and the record is not checked */
						if(($PrintTV || $Print_x) && count($record_selector) && !in_array($currentID, $record_selector)) continue;

						$attr_id = html_attr($currentID); /* pk value suitable for inserting into html tag attributes */
						$js_id = addslashes($currentID); /* pk value suitable for inserting into js strings */

						/* show record selector except in TVP */
						if($Print_x != '') {
							$this->HTML .= "<tr data-id=\"{$attr_id}\">";
						} else {
							$this->HTML .= ($SelectedID == $currentID ? "<tr data-id=\"{$attr_id}\" class=\"active\">" : "<tr data-id=\"{$attr_id}\">");
							$checked = (is_array($record_selector) && in_array($currentID, $record_selector) ? ' checked' : '');
							$this->HTML .= "<td class=\"text-center\"><input class=\"hidden-print record_selector\" type=\"checkbox\" id=\"record_selector_{$attr_id}\" name=\"record_selector[]\" value=\"{$attr_id}\"{$checked}></td>";
						}

						/* apply record templates */
						if($rowTemplate != '') {
							$rowTemp = $rowTemplate;
							if($this->AllowSelection == 1 && $SelectedID == $currentID && $selrowTemplate != '') {
								$rowTemp = $selrowTemplate;
							}

							if($this->AllowSelection == 1 && $SelectedID != $currentID) {
								$rowTemp = str_replace('<%%SELECT%%>',"<a onclick=\"document.myform.SelectedField.value = this.parentNode.cellIndex; document.myform.SelectedID.value = '" . addslashes($currentID) . "'; document.myform.submit(); return false;\" href=\"{$this->ScriptFileName}?SelectedID=" . html_attr($currentID) . "\" style=\"display: block; padding:0px;\">",$rowTemp);
								$rowTemp = str_replace('<%%ENDSELECT%%>','</a>',$rowTemp);
							} else {
								$rowTemp = str_replace('<%%SELECT%%>', '', $rowTemp);
								$rowTemp = str_replace('<%%ENDSELECT%%>', '', $rowTemp);
							}

							for($j = 0; $j < $fieldCountTV; $j++) {
								$fieldTVCaption = current(array_slice($this->QueryFieldsTV, $j, 1));

								$fd = safe_html($row[$j]);

								/*
									the TV template could contain field placeholders in the format 
									<%%FIELD_n%%> or <%%VALUE(Field caption)%%> or <%%HTML_ATTR(field caption)%%>
								*/
								$rowTemp = str_replace("<%%FIELD_$j%%>", thisOr($fd, ''), $rowTemp);
								$rowTemp = str_replace("<%%VALUE($fieldTVCaption)%%>", thisOr($fd, ''), $rowTemp);
								$rowTemp = str_replace("<%%HTML_ATTR($fieldTVCaption)%%>", html_attr($fd), $rowTemp);

								if(strpos($rowTemp, "<%%YOUTUBETHUMB($fieldTVCaption)%%>") !== false) $rowTemp = str_replace("<%%YOUTUBETHUMB($fieldTVCaption)%%>", thisOr(get_embed('youtube', $fd, '', '', 'thumbnail_url'), 'blank.gif'), $rowTemp);
								if(strpos($rowTemp, "<%%GOOGLEMAPTHUMB($fieldTVCaption)%%>") !== false) $rowTemp = str_replace("<%%GOOGLEMAPTHUMB($fieldTVCaption)%%>", thisOr(get_embed('googlemap', $fd, '', '', 'thumbnail_url'), 'blank.gif'), $rowTemp);
								if(thisOr($fd) == '&nbsp;' && preg_match('/<a href=".*?&nbsp;.*?<\/a>/i', $rowTemp, $m)) {
									$rowTemp = str_replace($m[0], '', $rowTemp);
								}
							}

							$this->HTML .= $rowTemp;
							$rowTemp = '';

						} else {
							// default view if no template
							for($j = 0; $j < $fieldCountTV; $j++) {
								if($this->AllowSelection == 1) {
									$sel1 = "<a href=\"{$this->ScriptFileName}?SelectedID=" . html_attr($currentID) . "\" onclick=\"document.myform.SelectedID.value = '" . addslashes($currentID) . "'; document.myform.submit(); return false;\" style=\"padding:0px;\">";
									$sel2 = "</a>";
								} else {
									$sel1 = '';
									$sel2 = '';
								}

								$this->HTML .= "<td valign=\"top\"><div>&nbsp;{$sel1}{$row[$j]}{$sel2}&nbsp;</div></td>";
							}
						}
						$this->HTML .= "</tr>\n";
						$i++;
					}
					$i--;
				}

				$this->HTML = preg_replace("/<a href=\"(mailto:)?&nbsp;[^\n]*title=\"&nbsp;\"><\/a>/", '&nbsp;', $this->HTML);
				$this->HTML = preg_replace("/<a [^>]*>(&nbsp;)*<\/a>/", '&nbsp;', $this->HTML);
				$this->HTML = preg_replace("/<%%.*%%>/U", '&nbsp;', $this->HTML);
				// end of data
				$this->HTML .= '<!-- tv data above -->';
				$this->HTML .= "\n</tbody>";

				if($Print_x == '') { // TV
					$pagesMenu = '';
					if($RecordCount > $this->RecordsPerPage) {
						$pagesMenuId = "{$this->TableName}_pagesMenu";
						$pagesMenu = $this->translation['go to page'] . ' <select style="width: 90%; max-width: 8em;" class="input-sm ltr form-control" id="' . $pagesMenuId . '" onChange="document.myform.writeAttribute(\'novalidate\', \'novalidate\'); document.myform.NoDV.value = 1; document.myform.FirstRecord.value = (this.value * ' . $this->RecordsPerPage . '+1); document.myform.submit();">';
						$pagesMenu .= '</select>';

						$pagesMenu .= '<script>';
						$pagesMenu .= 'var lastPage = ' . (ceil($RecordCount / $this->RecordsPerPage) - 1) . ';';
						$pagesMenu .= 'var currentPage = ' . (($FirstRecord - 1) / $this->RecordsPerPage) . ';';
						$pagesMenu .= 'var pagesMenu = document.getElementById("' . $pagesMenuId . '");';
						$pagesMenu .= 'var lump = ' . datalist_max_page_lump . ';';

						$pagesMenu .= 'if(lastPage <= lump * 3) {';
						$pagesMenu .= '  addPageNumbers(0, lastPage);';
						$pagesMenu .= '} else {';
						$pagesMenu .= '  addPageNumbers(0, lump - 1);';
						$pagesMenu .= '  if(currentPage < lump) addPageNumbers(lump, currentPage + lump / 2);';
						$pagesMenu .= '  if(currentPage >= lump && currentPage < (lastPage - lump)) {';
						$pagesMenu .= '    addPageNumbers(';
						$pagesMenu .= '      Math.max(currentPage - lump / 2, lump),';
						$pagesMenu .= '      Math.min(currentPage + lump / 2, lastPage - lump - 1)';
						$pagesMenu .= '    );';
						$pagesMenu .= '  }';
						$pagesMenu .= '  if(currentPage >= (lastPage - lump)) addPageNumbers(currentPage - lump / 2, lastPage - lump - 1);';
						$pagesMenu .= '  addPageNumbers(lastPage - lump, lastPage);';
						$pagesMenu .= '}';

						$pagesMenu .= 'function addPageNumbers(fromPage, toPage) {';
						$pagesMenu .= '  var ellipsesIndex = 0;';
						$pagesMenu .= '  if(fromPage > toPage) return;';
						$pagesMenu .= '  if(fromPage > 0) {';
						$pagesMenu .= '    if(pagesMenu.options[pagesMenu.options.length - 1].text != fromPage) {';
						$pagesMenu .= '      ellipsesIndex = pagesMenu.options.length;';
						$pagesMenu .= '      fromPage--;';
						$pagesMenu .= '    }';
						$pagesMenu .= '  }';
						$pagesMenu .= '  for(i = fromPage; i <= toPage; i++) {';
						$pagesMenu .= '    var option = document.createElement("option");';
						$pagesMenu .= '    option.text = (i + 1);';
						$pagesMenu .= '    option.value = i;';
						$pagesMenu .= '    if(i == currentPage) { option.selected = "selected"; }';
						$pagesMenu .= '    try{';
						$pagesMenu .= '      /* for IE earlier than version 8 */';
						$pagesMenu .= '      pagesMenu.add(option, pagesMenu.options[null]);';
						$pagesMenu .= '    }catch(e) {';
						$pagesMenu .= '      pagesMenu.add(option, null);';
						$pagesMenu .= '    }';
						$pagesMenu .= '  }';
						$pagesMenu .= '  if(ellipsesIndex > 0) {';
						$pagesMenu .= '    pagesMenu.options[ellipsesIndex].text = " ... ";';
						$pagesMenu .= '  }';
						$pagesMenu .= '}';
						$pagesMenu .= '</script>';
					}

					$this->HTML .= "\n\t";

					if($i) { // 1 or more records found
						$this->HTML .= "<tfoot><tr><td colspan=".(count($this->ColCaption)+1).'>';
							$this->HTML .= $this->translation['records x to y of z'];
						$this->HTML .= '</td></tr></tfoot>';
					}

					if(!$i) { // no records found
						$this->HTML .= "<tfoot><tr><td colspan=".(count($this->ColCaption)+1).'>';
							$this->HTML .= '<div class="alert alert-warning">';
								$this->HTML .= '<i class="glyphicon glyphicon-warning-sign"></i> ';
								$this->HTML .= $this->translation['No matches found!'];
							$this->HTML .= '</div>';
						$this->HTML .= '</td></tr></tfoot>';
					}

				} else { // TVP
					if($i)  $this->HTML .= "\n\t<tfoot><tr><td colspan=".(count($this->ColCaption) + 1). '>' . $this->translation['records x to y of z'] . '</td></tr></tfoot>';
					if(!$i) $this->HTML .= "\n\t<tfoot><tr><td colspan=".(count($this->ColCaption) + 1). '>' . $this->translation['No matches found!'] . '</td></tr></tfoot>';
				}

				$this->HTML = str_replace("<FirstRecord>", number_format($FirstRecord), $this->HTML);
				$this->HTML = str_replace("<LastRecord>", number_format($i), $this->HTML);
				$this->HTML = str_replace("<RecordCount>", number_format($RecordCount), $this->HTML);
				$tvShown = true;

				$this->HTML .= "</table></div>\n";

				/* highlight quick search matches */
				if($SearchString != '' && $RecordCount) $this->HTML .= '<script>$j(function() { $j(".table-responsive td:not([colspan])").mark("' . html_attr($SearchString) . '", { className: "text-bold bg-warning", diacritics: false }); })</script>';

				if($Print_x == '' && $i) { // TV
					$this->HTML .= '<div class="row pagination-section">';
						$this->HTML .= '<div class="col-xs-4 col-md-3 col-lg-2 vspacer-lg">';
							if($FirstRecord > 1) $this->HTML .= '<button onClick="' . $resetSelection . ' document.myform.NoDV.value = 1; return true;" type="submit" name="Previous_x" id="Previous" value="1" class="btn btn-default btn-block"><i class="glyphicon glyphicon-chevron-left"></i> <span class="hidden-xs">' . $this->translation['Previous'] . '</span></button>';
						$this->HTML .= '</div>';

						$this->HTML .= '<div class="col-xs-4 col-md-4 col-lg-2 col-md-offset-1 col-lg-offset-3 text-center vspacer-lg form-inline">';
							$this->HTML .= $pagesMenu;
						$this->HTML .= '</div>';

						$this->HTML .= '<div class="col-xs-4 col-md-3 col-lg-2 col-md-offset-1 col-lg-offset-3 text-right vspacer-lg">';
							if($i < $RecordCount) $this->HTML .= '<button onClick="'.$resetSelection.' document.myform.NoDV.value = 1; return true;" type="submit" name="Next_x" id="Next" value="1" class="btn btn-default btn-block"><span class="hidden-xs">' . $this->translation['Next'] . '</span> <i class="glyphicon glyphicon-chevron-right"></i></button>';
						$this->HTML .= '</div>';
					$this->HTML .= '</div>';
				}

				$this->HTML .= '</div>'; // end of div.table_view
			}
			/* that marks the end of the TV table */
		}

		// hidden variables ....
		foreach($this->filterers as $filterer => $caption) {
			if($_REQUEST['filterer_' . $filterer] != '') {
				$this->HTML .= "<input name=\"filterer_{$filterer}\" value=\"" . html_attr($_REQUEST['filterer_' . $filterer]) . "\" type=\"hidden\">";
				break; // currently, only one filterer can be applied at a time
			}
		}

		$this->HTML .= '<!-- possible values for current_view: TV, TVP, DV, DVP, Filters, TVDV -->';
		$this->HTML .= '<input name="current_view" id="current_view" value="' . $current_view . '" type="hidden">';
		$this->HTML .= '<input name="SortField" value="' . $SortField . '" type="hidden">';
		$this->HTML .= '<input name="SelectedID" value="' . html_attr($SelectedID) . '" type="hidden">';
		$this->HTML .= '<input name="SelectedField" value="" type="hidden">';
		$this->HTML .= '<input name="SortDirection" type="hidden" value="' . $SortDirection . '">';
		$this->HTML .= '<input name="FirstRecord" type="hidden" value="' . $FirstRecord . '">';
		$this->HTML .= '<input name="NoDV" type="hidden" value="">';
		$this->HTML .= '<input name="PrintDV" type="hidden" value="">';
		if($this->QuickSearch && !strpos($this->HTML, 'SearchString')) $this->HTML .= '<input name="SearchString" type="hidden" value="' . html_attr($SearchString) . '">';
		// hidden variables: filters ...
		$FiltersCode = '';
		for($i = 1; $i <= (datalist_filters_count * $FiltersPerGroup); $i++) { // Number of filters allowed
			if($i%$FiltersPerGroup == 1 && $i != 1 && $FilterAnd[$i] != '') {
				$FiltersCode .= "<input name=\"FilterAnd[$i]\" value=\"$FilterAnd[$i]\" type=\"hidden\">\n";
			}
			if($FilterField[$i] != '' && $FilterOperator[$i] != '' && ($FilterValue[$i] != '' || strpos($FilterOperator[$i], 'empty'))) {
				if(!strstr($FiltersCode, "<input name=\"FilterAnd[{$i}]\" value="))
					$FiltersCode .= "<input name=\"FilterAnd[{$i}]\" value=\"{$FilterAnd[$i]}\" type=\"hidden\">\n";
				$FiltersCode .= "<input name=\"FilterField[{$i}]\" value=\"{$FilterField[$i]}\" type=\"hidden\">\n";
				$FiltersCode .= "<input name=\"FilterOperator[{$i}]\" value=\"{$FilterOperator[$i]}\" type=\"hidden\">\n";
				$FiltersCode .= "<input name=\"FilterValue[{$i}]\" value=\"" . html_attr($FilterValue[$i]) . "\" type=\"hidden\">\n";
			}
		}
		$FiltersCode .= "<input name=\"DisplayRecords\" value=\"$DisplayRecords\" type=\"hidden\">";
		$this->HTML .= $FiltersCode;

		// display details form ...
		if(($this->AllowSelection || $this->AllowInsert || $this->AllowUpdate || $this->AllowDelete) && $Print_x == '' && !$PrintDV) {
			if(($this->SeparateDV && $this->HideTableView) || !$this->SeparateDV) {
				$dvCode = call_user_func_array($this->TableName . '_form', [
					$SelectedID,
					$this->AllowUpdate,
					$this->HideTableView && $SelectedID ? 0 : $this->AllowInsert,
					$this->AllowDelete,
					$this->SeparateDV,
					$this->TemplateDV,
					$this->TemplateDVP
				]);

				if($this->Permissions['view'] && $this->AllowDVNavigation && !$Embedded) $this->addDVNavigation(
					$dvCode,
					$SelectedID,
					// array of PKs of TV records
					array_map(function($rec) use($fieldCountTV) {
						return $rec[$fieldCountTV];
					}, $tvRecords),
					$FirstRecord == 1, // first page?
					$FirstRecord + $this->RecordsPerPage > $RecordCount // last page?
				);

				if($dvCode) {
					$this->HTML .= "\n\t<div data-table=\"{$this->TableName}\" class=\"col-xs-12 table-{$this->TableName} detail_view {$this->DVClasses}\">{$tv_dv_separator}<div class=\"panel panel-default\">{$dvCode}</div></div>";
					$this->ContentType = 'detailview';
					$dvShown = true;
				}

				$this->HTML .= ($this->SeparateDV ? '<input name="SearchString" value="' . html_attr($SearchString) . '" type="hidden">' : '');

				// if we're in embedded mode and a new record has just been inserted,
				// save its ID to localStorage in order to be used in child DV to
				// auto-select the new parent
				if(isset($_REQUEST['record-added-ok']) && $Embedded && $SelectedID) {
					ob_start();
					?><script>
						localStorage.setItem(
							'<?php echo $this->TableName; ?>_last_added_id', 
							<?php echo json_encode($SelectedID); ?>
						);
					</script><?php
					$this->HTML .= ob_get_clean();
				}

				// handle the case were user has no view access and has just inserted a record
				// by redirecting to tablename_view.php (which should redirect them to insert form)
				if(!$this->Permissions['view'] && !$dvCode && $SelectedID && isset($_REQUEST['record-added-ok'])) {
					ob_start();
					?><script>
						setTimeout(function() {
							window.location = '<?php echo $this->TableName; ?>_view.php';
						}, 4000);
					</script><?php
					$this->HTML .= ob_get_clean();
				}
			}
		}

		// display multiple printable detail views
		if($PrintDV) {
			$dvCode = '';
			$_REQUEST['dvprint_x'] = 1;

			// hidden vars
			foreach($this->filterers as $filterer => $caption) {
				if($_REQUEST['filterer_' . $filterer] != '') {
					$this->HTML .= "<input name=\"filterer_{$filterer}\" value=\"" . html_attr($_REQUEST['filterer_' . $filterer]) . "\" type=\"hidden\">";
					break; // currently, only one filterer can be applied at a time
				}
			}

			// count selected records
			$selectedRecords = 0;
			if(is_array($record_selector)) foreach($record_selector as $id) {
				$selectedRecords++;
				$this->HTML .= '<input type="hidden" name="record_selector[]" value="' . html_attr($id) . '">'."\n";
			}

			if($selectedRecords && $selectedRecords <= datalist_max_records_dv_print) { // if records selected > {datalist_max_records_dv_print} don't show DV preview to avoid db performance issues.
				foreach($record_selector as $id) {
					$dvCode .= call_user_func_array($this->TableName . '_form', array($id, 0, 0, 0, 1, $this->TemplateDV, $this->TemplateDVP));
				}

				if($dvCode != '') {
					$dvCode = preg_replace('/<input .*?type="?image"?.*?>/', '', $dvCode);
					$this->HTML .= $dvCode;
				}
			} else {
				$this->HTML .= error_message($this->translation['Maximum records allowed to enable this feature is'] . ' ' . datalist_max_records_dv_print);
				$this->HTML .= '<input type="submit" class="print-button" value="'.$this->translation['Print Preview Table View'].'">';
			}
		}

		if($tvRowNeedsClosing) $this->HTML .= "</div>";
		$this->HTML .= "</form>";
		$this->HTML .= '</div><div class="col-xs-1 md-hidden lg-hidden"></div></div>';

		// $this->HTML .= '<font face="garamond">'.html_attr($tvQuery).'</font>';  // uncomment this line for debugging the table view query

		if($dvShown && $tvShown) $this->ContentType = 'tableview+detailview';
		if($dvprint_x != '') $this->ContentType = 'print-detailview';
		if($Print_x != '') $this->ContentType = 'print-tableview';
		if($PrintDV != '') $this->ContentType = 'print-detailview';

		// call detail view javascript hook file if found
		$dvJSHooksFile = dirname(__FILE__) . '/hooks/' . $this->TableName . '-dv.js';
		if(is_file($dvJSHooksFile) && ($this->ContentType == 'detailview' || $this->ContentType == 'tableview+detailview')) {
			$this->HTML .= "\n<script src=\"hooks/{$this->TableName}-dv.js\"></script>\n";
		}

		return;
	}

	function validate_filters($req, $FiltersPerGroup = 4, $is_gpc = true) {
		$fand = (isset($req['FilterAnd']) && is_array($req['FilterAnd']) ? $req['FilterAnd'] : []);
		$ffield = (isset($req['FilterField']) && is_array($req['FilterField']) ? $req['FilterField'] : []);
		$fop = (isset($req['FilterOperator']) && is_array($req['FilterOperator']) ? $req['FilterOperator'] : []);
		$fvalue = (isset($req['FilterValue']) && is_array($req['FilterValue']) ? $req['FilterValue'] : []);

		/* make sure FilterAnd is either 'and' or 'or' */
		foreach($fand as $i => $f) {
			if($f && !preg_match('/^(and|or)$/i', trim($f))) $fand[$i] = 'and';
		}

		/* FilterField must be a positive integer */
		foreach($ffield as $ffi => $ffn) {
			$ffield[$ffi] = max(0, intval($ffn));
		}

		/* validate FilterOperator */
		foreach($fop as $i => $f) {
			$fop[$i] = trim($f);
			if($f && !in_array(trim($f), array_keys($GLOBALS['filter_operators']))) {
				$fop[$i] = '';
			}
		}

		/* clear fand, ffield and fop for filters having no value or no field */
		/* assume equal-to op and 'and' if missing */
		for($i = 1; $i <= datalist_filters_count * $FiltersPerGroup; $i++) {
			if(!isset($fand[$i]) && !isset($ffield[$i]) && !isset($fop[$i]) && !isset($fvalue[$i])) continue;

			if(($fvalue[$i] == '' && !in_array($fop[$i], array('is-empty', 'is-not-empty'))) || !$ffield[$i]) {
				unset($ffield[$i], $fop[$i], $fvalue[$i]);
				if($i % $FiltersPerGroup != 1) unset($fand[$i]);
			} else {
				if(!$fand[$i]) $fand[$i] = 'and';
				if(!$fop[$i]) $fop[$i] = 'equal-to';
			}
		}

		/* empty FilterAnd for empty groups or set to 'and' if empty while group not empty */
		for($i = 1; $i <= datalist_filters_count * $FiltersPerGroup; $i += $FiltersPerGroup) {
			$empty_group = true;

			for($j = $i; $j < ($i + $FiltersPerGroup); $j++) {
				if($ffield[$j]) $empty_group = false;
			}

			if($empty_group) {
				$fand[$i] = '';
				continue;
			}

			if(!$fand[$i]) $fand[$i] = 'and';
		}

		return array($fand, $ffield, $fop, $fvalue);
	}

	/**
	 *  @brief Returns HTML/JS code for displaying TV table options (hide/show columns)
	 */
	function tv_tools() {
		ob_start();
		?>

		<?php if($this->ShowTableHeader) { ?>
			<div class="pull-right flip btn-group vspacer-md tv-tools">
				<button title="<?php echo html_attr($this->translation['hide/show columns']); ?>" type="button" class="btn btn-default tv-toggle" data-toggle="collapse" data-target="#toggle-columns-container"><i class="glyphicon glyphicon-align-justify rotate90"></i></button>
			</div>
		<?php } ?>

		<div class="pull-right flip btn-group vspacer-md hspacer-md tv-tools">
			<button title="<?php echo html_attr($this->translation['previous column']); ?>" type="button" class="btn btn-default tv-scroll" onclick="AppGini.TVScroll().less()"><i class="glyphicon glyphicon-step-backward"></i></button>
			<button title="<?php echo html_attr($this->translation['next column']); ?>" type="button" class="btn btn-default tv-scroll" onclick="AppGini.TVScroll().more()"><i class="glyphicon glyphicon-step-forward"></i></button>
		</div>
		<div class="clearfix"></div>

		<?php if($this->ShowTableHeader) { ?>
			<div class="collapse" id="toggle-columns-container">
				<div class="well pull-right flip" style="width: 100%; max-width: 600px;">
					<div class="row" id="toggle-columns">
						<div class="col-md-12">
							<div class="btn-group" style="width: 100%;">
								<button type="button" class="btn btn-default" id="show-all-columns" style="width: 33.3%;"><i class="glyphicon glyphicon-check"></i> <?php echo $this->translation['Reset Filters']; ?></button>
								<button type="button" class="btn btn-default" id="hide-all-columns" style="width: 33.3%;"><i class="glyphicon glyphicon-unchecked"></i> <?php echo $this->translation['hide all']; ?></button>
								<button type="button" class="btn btn-default" id="toggle-columns-checks" style="width: 33.4%;"><i class="glyphicon glyphicon-random"></i> <?php echo $this->translation['toggle']; ?></button>
							</div>
						</div>
						<div class="col-md-12"><button type="button" class="btn btn-default btn-block" id="toggle-columns-collapser" data-toggle="collapse" data-target="#toggle-columns-container"><i class="glyphicon glyphicon-ok"></i> <?php echo $this->translation['ok']; ?></button></div>
					</div>
				</div>
				<div class="clearfix"></div>
			</div>
		<?php } ?>

		<script>
			$j(function() {
				/**
				 *  @brief Saves/retrieves value of column toggle status
				 *  
				 *  @param [in] col_class class of column concerned
				 *  @param [in] val boolean, optional value to save.
				 *  @return column toggle status if no value is passed
				 */
				var col_cookie = function(col_class, val) {
					if(col_class === undefined) return true;
					if(val !== undefined && val !== true && val !== false) val = true;

					var cn = 'columns-' + location.pathname.split(/\//).pop().split(/\./).shift(); // cookie name
					var c = JSON.parse(localStorage.getItem(cn)) || {};

					/* if no cookie, create it and set it to val (or true if no val) */
					if(c[col_class] === undefined) {
						if(val === undefined) val = true;

						c[col_class] = val;
						localStorage.setItem(cn, JSON.stringify(c));
						return val;
					}

					/* if cookie found and val provided, set cookie to new val */
					if(val !== undefined) {
						c[col_class] = val;
						localStorage.setItem(cn, JSON.stringify(c));
						return val;
					}

					/* if cookie found and no val, return cookie val */
					return c[col_class];
				}

				/**
				 *  @brief shows/hides column given its class, and saves this into localStorage
				 *  
				 *  @param [in] col_class class of column to show/hide
				 *  @param [in] show boolean, optional. Set to false to hide. Default is true (to show).
				 */
				var show_column = function(col_class, show) {
					if(col_class == undefined) return;
					if(show == undefined) show = true;

					if(show === false) $j('.' + col_class).hide();
					else $j('.' + col_class).show();

					AppGini.TVScroll().reset();

					col_cookie(col_class, show);
				}

				/* initiate TVScroll */
				AppGini.TVScroll().less();

			<?php if($this->ShowTableHeader) { ?>
				/* handle toggling columns' checkboxes */
				$j('#toggle-columns-container').on('click', 'input[type=checkbox]', function() {
					show_column($j(this).data('col'), $j(this).prop('checked'));
				});

				/* get TV columns and populate the #toggle-columns section */
				$j('.table_view th').each(function() {
					var th = $j(this);

					/* ignore the record selector column */
					if(th.find('#select_all_records').length > 0) return;

					var col_class = th.attr('class');
					var label = $j.trim(th.text());

					/* Add a toggler for the column in the #toggle-columns section */
					$j(
						'<div class="col-md-6"><div class="checkbox"><label>' +
							'<input type="checkbox" data-col="' + col_class + '" checked> ' + label +
						'</label></div></div>'
					).insertBefore('#toggle-columns-collapser');

					/* load saved column status */
					var col_status = col_cookie(col_class);
					if(col_status === false) $j('#toggle-columns input[type=checkbox]:last').trigger('click');
				});

				/* handle clicking 'show all [columns]' */
				$j('#show-all-columns').click(function() {
					$j('#toggle-columns input[type=checkbox]:not(:checked)').trigger('click');
				});

				/* handle clicking 'hide all [columns]' */
				$j('#hide-all-columns').click(function() {
					$j('#toggle-columns input[type=checkbox]:checked').trigger('click');
				});

				/* handle clicking 'toggle [columns]' */
				$j('#toggle-columns-checks').click(function() {
					$j('#toggle-columns input[type=checkbox]').trigger('click');
				});
			<?php } ?>
			})
		</script>
		<?php
		return ob_get_clean();
	}

	function resetSelection() {
		if($this->SeparateDV)
			return "document.myform.SelectedID.value = '';";

		return "document.myform.writeAttribute('novalidate', 'novalidate');";
	}

	function getTVButtons($print = false) {
		$buttons = '';

		if($print) return parseTemplate(
			'<button class="btn btn-primary" type="button" id="sendToPrinter" onClick="window.print();"><i class="glyphicon glyphicon-print"></i> <%%TRANSLATION(Print)%%></button>' .
			'<button class="btn btn-default cancel-print" type="submit"><i class="glyphicon glyphicon-remove-circle"></i> <%%TRANSLATION(Cancel Printing)%%></button>'
		);

		// display 'Add New' icon
		if($this->Permissions['insert'] && $this->SeparateDV && $this->AllowInsert)
			$buttons .= '<button type="submit" id="addNew" name="addNew_x" value="1" class="btn btn-success"><i class="glyphicon glyphicon-plus-sign"></i> <%%TRANSLATION(Add New)%%></button>';

		// display Print icon
		if($this->AllowPrinting)
			$buttons .= '<button onClick="document.myform.NoDV.value = 1; <%%RESET_SELECTION%%> return true;" type="submit" name="Print_x" id="Print" value="1" class="btn btn-default"><i class="glyphicon glyphicon-print"></i> <%%TRANSLATION(Print Preview)%%></button>';

		// display CSV icon
		if($this->AllowCSV)
			$buttons .= '<button onClick="document.myform.NoDV.value = 1; <%%RESET_SELECTION%%> return true;" type="submit" name="CSV_x" id="CSV" value="1" class="btn btn-default"><i class="glyphicon glyphicon-download-alt"></i> <%%TRANSLATION(CSV)%%></button>';

		// display Filter icon
		if($this->AllowFilters)
			$buttons .= '<button onClick="document.myform.NoDV.value = 1; <%%RESET_SELECTION%%> return true;" type="submit" name="Filter_x" id="Filter" value="1" class="btn btn-default"><i class="glyphicon glyphicon-filter"></i> <%%TRANSLATION(filter)%%></button>';

		// display Show All icon
		if(($this->AllowFilters))
			$buttons .= '<button onClick="document.myform.NoDV.value = 1; <%%RESET_SELECTION%%> return true;" type="submit" name="NoFilter_x" id="NoFilter" value="1" class="btn btn-default"><i class="glyphicon glyphicon-remove-circle"></i> <%%TRANSLATION(Reset Filters)%%></button>';

		return str_replace(
			'<%%RESET_SELECTION%%>', 
			$this->resetSelection(), 
			parseTemplate($buttons)
		);
	}

	function buildQuery($fieldsArray = false, $start = false, $length = false) {
		$fieldList = ['COUNT(1)'];

		if(is_array($fieldsArray)) {
			$fieldList = [];
			foreach($fieldsArray as $fn => $fc) {
				$sfc = makeSafe($fc);
				$fieldList[] = "{$fn} AS '{$sfc}'";
			}
		}

		$limit = '';
		if($length !== false) {
			$limit = "LIMIT {$length}";
			if($start !== false)
				$limit = "LIMIT {$start}, {$length}";
		}

		return implode(' ', [
			'SELECT',
			implode(', ', $fieldList),
			'FROM',
			$this->QueryFrom,
			$this->QueryWhere,
			is_array($fieldsArray) ? $this->QueryOrder : '',
			$limit
		]);
	}

	function getTVRevords($first) {
		// TV/TVP query
		$tvFields = $this->QueryFieldsTV;

		// Append pk as last field -- using COALESCE is merely a trick to avoid overwriting any existing array key!
		if($this->PrimaryKey)
			$tvFields["COALESCE($this->PrimaryKey)"] = str_replace('`', '', $this->PrimaryKey);

		$tvQuery = $this->buildQuery($tvFields, $first - 1, $this->RecordsPerPage);
		//$eo = ['silentErrors' => true];
		$result = sql($tvQuery, $eo);
		$tvRecords = [];
		if(!$result) return $tvRecords;
		while($row = db_fetch_array($result)) $tvRecords[] = $row;

		return $tvRecords;
	}

	function outputCSV() {
		$csvData = [];
		$BOM = (datalist_db_encoding == 'UTF-8' ? "\xEF\xBB\xBF" : ''); // BOM characters for UTF-8 output

		// execute query for CSV output
		$csvQuery = $this->buildQuery($this->QueryFieldsCSV);

		// hook: table_csv
		if(function_exists($this->TableName.'_csv')) {
			$args = [];
			$mq = call_user_func_array($this->TableName . '_csv', array($csvQuery, $mi, &$args));
			$csvQuery = ($mq ? $mq : $csvQuery);
		}

		$eo = ['silentErrors' => true];
		$result = sql($csvQuery, $eo);
		$FieldCountCSV = db_num_fields($result);

		// output CSV field names
		$csvHead = [];
		for($i = 0; $i < $FieldCountCSV; $i++)
			$csvHead[] = db_field_name($result, $i);

		$csvData[] = '"' . implode("\"{$this->CSVSeparator}\"", $csvHead) . '"';

		// output CSV data
		while($row = db_fetch_row($result)) {
			$prep = array_map(function($field) {
				return strip_tags(
					preg_replace(
						['/<br\s*\/?>/i', '/^([=+\-@]+)/', '/[\r\n]+/', '/"/'], 
						[' ',             '\'$1',          ' ',         '""'],
						trim($field)
					)
				);
			}, $row);

			$csvData[] = '"' . implode("\"{$this->CSVSeparator}\"", $prep) . '"';
		}

		// clean any output buffers
		while(@ob_end_clean());

		$csvDataStr = $BOM . implode("\n", $csvData);

		// output CSV HTTP headers ...
		$csv_filename = $this->TableName . date('-Ymd-His') . '.csv';
		header('HTTP/1.1 200 OK');
		header('Date: ' . @date("D M j G:i:s T Y"));
		header('Last-Modified: ' . @date("D M j G:i:s T Y"));
		header("Content-Type: application/force-download");
		header("Content-Length: " . (string)(strlen($csvDataStr)));
		header("Content-Transfer-Encoding: Binary");
		header("Content-Disposition: attachment; filename={$csv_filename}");

		// send output and quit script
		echo $csvDataStr;
		exit;
	}

	private function console(&$html, $arr, $ret) {
		if(!$this->AllowConsoleLog) return $ret;

		ob_start(); ?>
		<script>
			console.log(JSON.stringify(<?php echo json_encode($arr); ?>, true, 2));
		</script>
		<?php
		$html .= ob_get_clean();

		return $ret;
	}


	/**
	 * Adds next/prev record navigation links to DV.
	 *
	 * @param      string  $dvCode     The html code of the DV, passed by reference
	 * @param      string  $id         The PK of the currently selected record
	 * @param      array   $pks        The PKs of the records of the current TV page, in their same order
	 * @param      bool    $firstPage  set to true if this is first page in TV
	 * @param      bool    $lastPage   set to true if this is last page in TV
	 */
	function addDVNavigation(&$dvCode, $id, $pks, $firstPage = false, $lastPage = false) {
		$lastIndex = count($pks) - 1;
		if($lastIndex < 0)
			return $this->console($dvCode, compact('id', 'pks', 'firstPage', 'lastPage', 'index', 'lastIndex', 'btnPrev', 'btnNext', 'actionPrev', 'actionNext'), true);

		// get index of current record in $records
		$index = array_search($id, $pks);
		if($index === false)
			return $this->console($dvCode, compact('id', 'pks', 'firstPage', 'lastPage', 'index', 'lastIndex', 'btnPrev', 'btnNext', 'actionPrev', 'actionNext'), true);

		$btnPrev = $btnNext = true;

		// no prev btn for first record on first page
		if($index == 0 && $firstPage) $btnPrev = false;

		// no next btn for last record on last page
		if($index == $lastIndex && $lastPage) $btnNext = false;

		if(!$btnPrev && !$btnNext)
			return $this->console($dvCode, compact('id', 'pks', 'firstPage', 'lastPage', 'index', 'lastIndex', 'btnPrev', 'btnNext', 'actionPrev', 'actionNext'), true);

		$actionPrev = ($index == 0 ? 'setSelectedIDPreviousPage' : 'previousRecordDV');
		$actionNext = ($index == $lastIndex ? 'setSelectedIDNextPage' : 'nextRecordDV');

		ob_start(); ?>
		<div class="hidden">
			<div
				class="btn-group-vertical btn-group-lg" id="dv-navigation-buttons" 
				style="width: 100%; margin: 0; margin-top: 10px;">
				<?php if($btnPrev) { ?>
					<button
						class="btn btn-default previous-record"
						type="submit" style="width: 100%;"
						name="<?php echo $actionPrev; ?>" value="1"
						><i class="glyphicon glyphicon-step-backward"></i>
							<?php echo $this->translation['Previous']; ?>
					</button>
				<?php } ?>
				<?php if($btnNext) { ?>
					<button
						class="btn btn-default next-record"
						type="submit" style="width: 100%;"
						name="<?php echo $actionNext; ?>" value="1"
						><?php echo $this->translation['Next']; ?>
							<i class="glyphicon glyphicon-step-forward"></i>
					</button>
				<?php } ?>
			</div>
		</div>
		<script>
			$j(function() {
				var navBtns = $j('#dv-navigation-buttons');

				// switch to horizontal btn-group if buttons too wide
				$j(window).resize(function() {
					if(navBtns.width() > 300) {
						navBtns
							.removeClass('btn-group-vertical')
							.addClass('btn-group')
							.find('.btn')
							.css({ width: (100 / navBtns.find('.btn').length) + '%' });
						return;
					}

					navBtns
						.removeClass('btn-group')
						.addClass('btn-group-vertical')
						.find('.btn').css({ width: '100%' });
				}).trigger('resize');

				navBtns.on('click', '.btn', function(e) {
					// detect changes and confirm they'd be lost
					if($j('#deselect').hasClass('btn-warning'))
						if(!confirm(
							<?php echo json_encode(to_utf8($this->translation['discard changes confirm'])); ?>
						)) {
							e.preventDefault();
							return false;
						}

					// reset form and prevent validation
					var form = $j(this).parents('form');
					form.prop('novalidate', true).get(0).reset();
					return true;
				})
				.appendTo('#<?php echo $this->TableName; ?>_dv_action_buttons > .btn-toolbar:first-child');
			})
		</script>
		<?php

		$dvCode .= ob_get_clean();

		return $this->console($dvCode, compact('id', 'pks', 'firstPage', 'lastPage', 'index', 'lastIndex', 'btnPrev', 'btnNext', 'actionPrev', 'actionNext'), true);
	}

}

