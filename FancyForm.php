<?php

/**
 * Register credits
 */

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 *
 * TODO: Need to escape output... :-/
 */

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "FancyUpload is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Fancy Form',
        'author' => 'Edited by Juan Valencia',
        'description' => 'Easy creation of articles via forms. Original extension was [http://www.mediawiki.org/wiki/Extension:CreateTpl CreateTpl by RUNA]',
        'version' => '0.3'
);


// from Matt JC
// http://stackoverflow.com/questions/161738/what-is-the-best-regular-expression-to-check-if-a-string-is-a-valid-url
define('URL_FORMAT', 
'/^(https?):\/\/'.                                         // protocol 
'(([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+'.         // username 
'(:([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+)?'.      // password 
'@)?(?#'.                                                  // auth requires @ 
')((([a-z0-9][a-z0-9-]*[a-z0-9]\.)*'.                      // domain segments AND 
'[a-z][a-z0-9-]*[a-z0-9]'.                                 // top level domain  OR 
'|((\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])\.){3}'. 
'(\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])'.                 // IP address 
')(:\d+)?'.                                                // port 
')(((\/+([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)*'. // path 
'(\?([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)'.      // query string 
'?)?)?'.                                                   // path and query string optional 
'(#([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)?'.      // fragment 
'$/i'); 


// MediaWiki FancyForm Extension Ver 0.3
// set up MediaWiki to react to the "<FancyForm>" tag
$wgExtensionFunctions[] = "wfFancyForm";
function wfFancyForm() {
	global $wgParser;
        # When MediaWiki sees <FancyForm> or <paramtype> it runs these functions: 
        # RenderFancyForm($text, $params, $parser), and NoRender($text, $params, $parser) respectively
	$wgParser->setHook( "FancyForm", "RenderFancyForm" );
	$wgParser->setHook( "paramtype", "NoRender" );
	return true;
}
 

#Register an event called 'EditPage::showEditForm:initial'; so that it runs the function 'preloadCreateTemplate_initial'
#In particular, these events allow you to take the POST data from froms and create the Template wiki code in the edit window.
$wgHooks['EditPage::showEditForm:initial'][] = 'preloadCreateTemplate_initial';
$wgHooks['EditPage::showEditForm:fields'][] = 'preloadCreateTemplate';
 

# $input is the variable you change in order to display something different... don't change it for a hidden tag.
# $argv is relevant arguments
# $parser is used for contextual elements
function NoRender ( $input, $argv, $parser ) {
	#It's just blind to not display a block template <paramtype>
}


#########################
# RenderFancyForm: When MediaWiki sees a <FancyForm> tag, it replaces that tag with whatever this function returns.
#
# $input is the variable you change in order to display something different
# $argv is relevant arguments
# $parser is used for contextual elements

function RenderFancyForm( $input, $argv, $parser, $frame ) {

	global $wgScriptPath, $wgOut;
	// Load css
	$wgOut->addScript("<style type='text/css'>@import '$wgScriptPath/extensions/FancyForm/FancyForm.css';</style>");
	$wgOut->addScript(<<<EOJS
<script>
var indexName = Array();  //Change this to an array!! TODO TODO {formUploadDiv_??? => majorItem1, formUploadDiv_??? =>majorItem2}
var indexValue = Array();			// then you can remove all the right link from the right field.

function getUploadValue(passingValue) {
	try {
		var great = document.getElementById('form_FancyUploadPassAlong');
		
		if (great) {
			document.getElementById('form_' + passingValue).value = great.value;

			var link = document.createElement("a");
			link.innerHTML = great.value.replace('[[', '').replace(']]', '');
			link.href = wgScript + '/' + great.value.replace('[[', '').replace(']]', '');
	
			var linkArea = document.getElementById('formUploadDiv_' + passingValue);
			//alert(indexName.toString());
			//alert(indexValue.toString());
			if (indexName.length == 0) {
				linkArea.appendChild(link);
				indexName[0] = passingValue;
				indexValue[0] = link;				
			} else {
				var found = false;
				for (var i=0; i<indexName.length;i++) {
					if (indexName[i] == passingValue) {
						linkArea.removeChild(indexValue[i]);
						indexValue[i] = link;
						linkArea.appendChild(link);
						found = true;
					}
				}
				if (!found) {
					indexName[indexName.length] = passingValue;
					indexValue[indexValue.length] = link;
					linkArea.appendChild(link);
				}
			}


		} else {
			alert("Nothing has been uploaded");
		}
	} catch (err) {
		alert("Get Upload Value failure: " + err.description);
	}
	return false;
	
}
</script>
EOJS
	);

        #Set a flag noting that the output object is dynamic and should not be cached
	$parser->disableCache();
	global $wgScriptPath, $wgRequest, $wgHooks;

        #Within the FancyForm tag look for anything surrounded with double curly brackets that starts at the beginning of the line and is case insensitive
        #put the full text into $matches[0], put the string that was found in $matches[1].  This should be the name of a template.
	preg_match("/^{{(.*)}}/i", $input, $matches);
	$tpl_name = $matches[1];

        #This function will return $output which should be our form html.
	$output = "";

        #If this request was the result of a POST operation Do the following: 
	if ($wgRequest->wasPosted()
                # And it was POST-ed as both a create=FancyForm and with the tplname= the template name found in the {{*}} regular expression
		&& ($wgRequest->getVal("create") == "FancyForm") 
		&& ($wgRequest->getVal("tplname") == urlencode($tpl_name))) {
                # If there exists an articleTitle where the stringlength is greater than 0 
		if (strlen($wgRequest->getVal("articletitle"))) {

			#    TODO     TODO   ->  if article title is bad, complain and don't runt the following. 

			$mArticleTitle = ucfirst($wgRequest->getVal("articlenamespace")) . ":" .ucfirst($wgRequest->getVal("articletitle"));
                        # Since the article already exists, prompt the user, asking if they want to continue
			$output .= '
			<form id="'.urlencode($tpl_name).'" action="'.$wgScriptPath.'/index.php?title=' . $mArticleTitle.'&action=edit" method="POST">';
			foreach ($_POST as $key=>$value) {
                                #For each POST-ed argument, create the proper input field
				$output .= '<input type="hidden" name="'.$key.'" value="'.$value.'"/>';
			}
                        # Create a new MediaWiki Title object from the POST-ed "articletitle"
			$nt = Title::newFromText( $mArticleTitle );
                        # If the article title already exists, then output a warning. And give people the option of continuing or going back.
			if ($nt->exists()) {
				$mFullUrl = $nt->getFullURL();
				$mFormId = urlencode($tpl_name);
				$output .= <<<EOA

		<div id="FancyForm_$mFormId"/>
		<b>
			<font color='red'>Article </font>
			<a href=$mFullUrl>$mArticleTitle</a>
			<font color='red'> already exists.</font>
		</b>
		<p>Continuation will lead to the replacement text of the article.</p>
		<input type='button' value='Cancel' onclick='history.back()'/>
		<input type='submit' value='Continue'/>
</form>
EOA;

				$wgHooks['ParserAfterTidy'][] = array('fnFancyFormPositioning', urlencode($tpl_name));
				return $output;
			}
                        # If the article title does not exist, then continue saving.
			else {  #Do error checking on various fields TODO
				$output .= '</form>';
                                #$output .= $value; # debug
				$continue = true;
				foreach ($_POST as $key=>$value) {
					if (strpos($key, "type_")===0) {
						$param = urldecode(substr($key, 5));
						$mPostParam = $_POST["form_$param"];
						$mLongerThanNone = strlen($mPostParam);
						switch ($value) {
							case "number": #if its not a number, stop!
								if ($mLongerThanNone && (!is_numeric($mPostParam))) {
									$continue = false;
									$output .= "<b><font color='red'>Parameter </font>$param<font color='red'> must be a number.</font></b><BR/><BR/>";
								}
								break;
							case "temp": #if its not a number, stop!
								if ($mLongerThanNone && (!is_temp($mPostParam))) {
									$continue = false;
									$output .= "<b><font color='red'>Parameter </font>$param<font color='red'> must be a temperative of the form:</font>35K</b><BR/><BR/>";
								}
								break;
							case "color": #if its not a color, stop!
								if ($mLongerThanNone && (!is_color($mPostParam))) {
									$continue = false;
									$output .= "<b><font color='red'>Parameter </font>$param<font color='red'> must be a color of the form: </font>#AABBCC (rgb hex).</b><BR/><BR/>";
								}
								break;
							case "url": #if its not a color, stop!
								if ($mLongerThanNone && (!is_url($mPostParam))) {
									$continue = false;
									$output .= "<b><font color='red'>Parameter </font>$param<font color='red'> must be a valid web url:</font> http://some.regularurl.com</b><BR/><BR/>";
								}
								break;
							case "date": #if its not a date, stop!
								if ($mLongerThanNone && (!is_date($mPostParam))) {
									$continue = false;
									$output .= "<b><font color='red'>Parameter </font>$param<font color='red'> must be a date in format:</font> dd.mm.yyyy</b><BR/><BR/>";
								}
								break;
						}
					}
				}
				if ($continue) { #everythin is ok, finish submission using javascript
					$output .= '<script language="JavaScript">document.getElementById("'.urlencode($tpl_name).'").submit()</script>';
					return $output;
				}
				else {# error checking on fields was not ok, register the hook the displays the form, don't return.
					$wgHooks['ParserAfterTidy'][] = array('fnFancyFormPositioning', urlencode($tpl_name));
				}
			} #finish error checking
		} #finish if it was POST-ed
		else { # if no article title was POST-ed, then append this error: (and register the hook that displays the form)
			$output .= "<b><font color='red'>Specify the name of article!</font></b><BR/><BR/>";
			$wgHooks['ParserAfterTidy'][] = array('fnFancyFormPositioning', urlencode($tpl_name));
		}
	} 
 
        #If there was an error with the POST data, or if there was no POST, Then do the following to create a form:
        #Get the template object
	$dbr =& wfGetDB( DB_SLAVE );
	$res = $dbr->query( "
		select distinct t.`old_text` as page_text
		from `page` p
		inner join `revision` r on p.`page_latest`=r.`rev_id`
		inner join `text` t on r.`rev_text_id`=t.`old_id`
		where p.`page_title` = '".$tpl_name."'
		and p.`page_namespace` = 10
		limit 1
	" );
	$row = $dbr->fetchObject( $res );
	$tpl_text = $row->page_text;#Pull out the template text
	$dbr->freeResult( $res ); # release the DB info
 
	preg_match_all("/(?<=\{{3})[^\}]*[^\}]*/", $tpl_text, $matches); #Find all of the variables in the template
 
	$params_array = array();
	foreach ($matches[0] as $params) {#for each match,
		 $expl_params = explode("|", $params, 2);#split at "|" left should be var name, right should be default value
		 $params_array[] = $expl_params[0];#params gets all the variable names from the template
	}
	$params_array = array_unique($params_array); #get rid of all duplicate values in the array
 
	preg_match("/(?<=<paramtype>)[^<>]+(?=<\/paramtype>)/", $tpl_text, $matches);#find user defined types from the template
	#print_r($matches);
	$param_types_array = explode("|", $matches[0]); #changed this to pipe instead of newline/space to conform to wiki way
	#print_r($param_types_array); #-> Array([0]=>"var1:type1" [1]=>"var2:type2")
	$param_types = array();
	foreach ($param_types_array as $param_value) { #foreach key/value pair, get value
		if (strlen($param_value)) { #If it is actually there...
			$param_name_type = explode( ":", $param_value, 2 );#Split the string at Colon, then get the key, 0,1,2,3 etc
			$key = array_search(trim($param_name_type[0]), $params_array);#get the key of the template value that matches the <param_type> key
			if (($key === 0) || ($key > 0)) { #If it is a valid key
				$param_types[trim($param_name_type[0])] = trim($param_name_type[1]);#Add to the param_types array for parsing later.
			}
		}
	}
	#print_r($param_types); Array([var1]=>"type1" [var2]=>"type2")
	
        #make a form from all the parameters
	$form_inputs = "";
	$upload_form = "";
	if (count($params_array)) {
		$form_inputs .= "<hr />";
		foreach ($params_array as $param_name) {

			if (array_key_exists($param_name, $param_types)) {
				$type_text = "";
				$form_inputs .= "<label class='FancyInputLabel'><span class='FancyInputParam'>$param_name:</span><br />";
				$mPType = $param_types[$param_name];
				//If the form was previously submitted, you will want its value.
				$oldValue = $wgRequest->getVal("form_".urlencode($param_name));
				$mparam_name = urlencode($param_name);
				switch ($mPType) {
					case "number": #This is the paramtype						
						$form_inputs .= createTextInput("number", $mparam_name, $mPType, $oldValue);
						break;
					case "temp": #This is the paramtype						
						$form_inputs .= createTextInput("temperature in form 10K or 5C or 333F", $mparam_name, $mPType, $oldValue);
						break;
					case "color": 
						$form_inputs .= createTextInput("color in rgb hex form - #001122", $mparam_name, $mPType, $oldValue);
						break;
					case "url": 
						$form_inputs .= createTextInput("web url", $mparam_name, $mPType, $oldValue);
						break;
					case "date":
						$form_inputs .= createTextInput("date in format dd.mm.yyyy", $mparam_name, $mPType, $oldValue);
						break;
					case "textarea":
						$form_inputs .= createTextArea("unformatted text - edit wiki code after submission", $mparam_name, $mPType, $oldValue);
						break;
					case "category":#This is the paramtype
						$type_text = "categories";
						$form_inputs .= "<select class='FancyInputSelect' name='form_".urlencode($param_name)."' >";
						#If there is an error on POST, the form gets reloaded.  
						#This if tries to reinsert the posted data so that you don't have to fill it in again.
						if (strlen($wgRequest->getVal("form_".urlencode($param_name)))) {
							$cat_selected_Value .= $wgRequest->getVal("form_".urlencode($param_name)); #take care of this later
						}

						#modified from CategoryLink Extension... Thank You!
						global $wgDBuser,$wgDBpassword, $wgDBname,$wgDBserver,$IP, $wgDBprefix;
                				$link = mysql_connect($wgDBserver, $wgDBuser, $wgDBpassword)
				                	or die('Could not connect: ' . mysql_error());
				                mysql_select_db($wgDBname) or die('Could not select database');
				                $query = 'SELECT cl_to FROM '.$wgDBprefix.'categorylinks group by cl_to';
				                $result = mysql_query($query) or die('Query failed: ' . mysql_error());
				                $num_rows = mysql_num_rows($result);
				                while($num_row = mysql_fetch_array($result)) {
				                        $data[] = $num_row['cl_to'];
						}

						foreach ($data as $option) {
							if ($option != "MenuCategory") {
	                                                	$form_inputs .= "<option ";
								if ($cat_selected_Value == $option) {
									$form_inputs .= "selected='selected'"; #took care of it here
								}
 								$form_inputs .= ">$option</option>";
							}
						}
                                                $form_inputs .= "</select>";

                                                $form_inputs .= "<br /><span class='FancyInputSpan'>($type_text)<BR/></span>";
				                $form_inputs .= "<input type='hidden' name='type_".urlencode($param_name)."' value='".$param_types[$param_name]."'/>";
						break;
                                        case "namespace":#This is the paramtype
						$type_text = "namespaces";
						$form_inputs .= "<select class='FancyInputSelect' name='form_".urlencode($param_name)."' >";
						#If there is an error on POST, the form gets reloaded.  
						#This if tries to reinsert the posted data so that you don't have to fill it in again.
						if (strlen($wgRequest->getVal("form_".urlencode($param_name)))) {
							$ns_selected_Value .= $wgRequest->getVal("form_".urlencode($param_name)); #take care of this later
						}

						#TODO - add namespaces into option tags from js
						$namespaces = SearchEngine::searchableNamespaces();
						foreach ($namespaces as $option) {
							$form_inputs .= "<option ";
							if ($ns_selected_Value == $option) {
								$form_inputs .= "selected='selected'"; #took care of it here
							}
 							$form_inputs .= ">$option</option>";
						}
                                                $form_inputs .= "</select>";

                                                $form_inputs .= "<br /><span class='FancyInputSpan'>($type_text)<BR/></span>";
				                $form_inputs .= "<input type='hidden' name='type_".urlencode($param_name)."' value='".$param_types[$param_name]."'/>";
						break;
                                        case substr($param_types[$param_name], 0, 6) == "select":#This is the paramtype
						$type_text = "drop-down";
                                                $form_inputs .= "<select class='FancyInputSelect' name='form_".urlencode($param_name)."' >";
						#If there is an error on POST, the form gets reloaded.  
						#This if tries to reinsert the posted data so that you don't have to fill it in again.
						if (strlen($wgRequest->getVal("form_".urlencode($param_name)))) {
							$selected_Value .= $wgRequest->getVal("form_".urlencode($param_name)); #take care of this later
						}
						$select_options = array();
           					$select_options = explode("-", $param_types[$param_name]);
                    				$initial = true;
						foreach ($select_options as $option) {
							if (!$initial) {
                                                		$form_inputs .= "<option ";
								if ($selected_Value == $option) {
									$form_inputs .= "selected='selected'"; #took care of it here
								}
 								$form_inputs .= ">$option</option>";
							}
							$initial = false;
						}
                                                $form_inputs .= "</select>";

                                                $form_inputs .= "<br /><span class='FancyInputSpan'>($type_text)<BR/></span>";
				                $form_inputs .= "<input type='hidden' name='type_".urlencode($param_name)."' value='".$param_types[$param_name]."'/>";
                                                break;
					case "upload":
						$form_inputs .= createTextInput("update the value", $mparam_name, $mPType, $oldValue);
						$passingName = htmlentities($mparam_name);
						$form_inputs .= "<a class='FancyFormUploadButton' onClick='getUploadValue(\"$mparam_name\")'>Insert Uploaded</a>";
						$form_inputs .= "<div class='FormUploadDiv' id='formUploadDiv_" . $passingName . "'></div>";
						$form_inputs .= '<div class="FancyFormSpacer"></div>';
						break;
					default:
						$form_inputs .= createTextInput("default text box", $mparam_name, $mPType, $oldValue);
				} #end switch
				
			} else { 
				$form_inputs .= "<label class='FancyInputLabel'><span class='FancyInputParam'>$param_name:</span><br />";
				$oldValue = $wgRequest->getVal("form_".urlencode($param_name));
				$mparam_name = urlencode($param_name);
				$mPType = "text";
				$form_inputs .= createTextInput("text", $mparam_name, $mPType, $oldValue);
			}
			$form_inputs .= "</label>";
		}	
		$form_inputs .= "<br /><input class='FancyInputSubmit' type=submit value='Create' />
		";
	}
	$tt = Title::makeTitle( 10, $tpl_name );
        #When you finish the form, Put a couple of hidden fields, so that you can process what happened later, 
        #Label this POST as a FancyForm post and as a post of tplname templateName.
        #Stick the article Title input box at the beginning of the form....???Why not do it sooner?
        #TODO - pull out common namespace code from here and the switch statement above.
	$namespacesI = SearchEngine::searchableNamespaces();
        $optionStringI ='';
	foreach ($namespacesI as $optionI) {
		$optionStringI .= "<option ";
		if ($wgRequest->getVal("articlenamespace") == $optionI) {
			$optionStringI .= "selected='selected'"; #took care of it here
		}
 		$optionStringI .= ">$optionI</option>";
	}

	$output .= 'Create a page with a template : <b><a href="'.$tt->getFullURL().'">'.$tpl_name.'</a></b><br /><br />
<div class="FancyForm">' . $upload_form .
'<form action="" method="POST">
<input type="hidden" name="create" value="FancyForm"/>
<input type="hidden" name="tplname" value="'.urlencode($tpl_name).'"/>
<h3 class="FancyInputHeader">Form Fields:</h3>

<label class="FancyInputLabel"><span class="FancyInputParam">Article Title: </span><br /><input class="FancyInputText" type="text" name="articletitle" value="'.$wgRequest->getVal("articletitle").'" /><br /><span class="FancyInputSpan">(a great article title to avoid name collisions)<br /></span></label>

<label class="FancyInputLabel"><span class="FancyInputParam">Article Namespace: </span><br /><select class="FancyInputSelect" name="articlenamespace" value="'.$wgRequest->getVal("articlenamespace").'" />'.$optionStringI.'</select><br /><span class="FancyInputSpan">(Namespace for the article)<br /></span></label>
'.$form_inputs.'
<div class="FancyFormSpacer"></div>
</form></div>
';
	$tpl_name_enc = urlencode($tpl_name);
        #Make sure the formating is ok and stick the whole thing in a div.
	$output = "<div id='FancyForm_$tpl_name_enc' style='padding: 10px; boder: 1px solid black;'>".str_replace("\n", "", $output)."</div>";
	return $output; #finally done!
}


# Event EditPage::showEditForm:fields allows injection of form fields into edit form... the event calls this function
# In other words, the data that was posted via a form is then grabbed by this function, and used to populate the main edit box on the edit page.
function preloadCreateTemplate(&$editpage, &$output) {
	global $wgRequest; #a web request object useful for getting data passed via a POST-ed form
	if ( ($wgRequest->getVal("action") == "edit")
	&& ($wgRequest->getVal("create") == "FancyForm") ) {
        #The template name is decoded from the tplname that has been passed via POST
	$tplname = urldecode($wgRequest->getVal("tplname"));
        #get all the values from POST
	$post_array = $wgRequest->getValues();
        #Grabthe main editing textbox from the edit page and put the template code in there.
	$editpage->textbox1 = '{{'.$tplname;
	foreach($post_array as $key=>$value) {
		if ((strpos($key, "form_") === 0) && strlen(strval($value))) {
			$editpage->textbox1 .= '|'.urldecode(substr($key, 5)).'='.$value;
		}
	}
	$editpage->textbox1 .= '}}';
	}
	return true;
}


#Event EditPage::showEditForm:initial allows injection of html into edit form... the event calls this function
#It really does very little except let the editpage know that this is an "initial" event... you are creating a page, not editing a long standing one.
function preloadCreateTemplate_initial(&$editpage) {
        #Check to see if the user is both editing and FancyForm-ing a page.  If so change formtype to initial.
	global $wgRequest; #a web request object useful for getting data passed via a POST-ed form
        #Get particular values from POST, namely "action" and "create" if their values match, wala!
	if ( ($wgRequest->getVal("action") == "edit")
	&& ($wgRequest->getVal("create") == "FancyForm") ) {
                #??? Change the type so that editing instructions appear? or *so that MW knows this is an initial run* on an edit/create
		$editpage->formtype = "initial";
	}
	return true;
}


 #After it's all said and done, make some final edits to the html.  In particular add javascript to show the form.
function fnFancyFormPositioning ( $tpl_name_enc, &$parser, &$text) {
	$label = "FancyForm_$tpl_name_enc";
	$text .= "<script language='Javascript'>document.getElementById('$label').scrollIntoView(true);</script>";	
	return true;
}
 
 
function is_date($i_sDate)
{
  $blnValid = TRUE;
   // check the format first (may not be necessary as we use checkdate() below)
   if(!ereg ("^[0-9]{2}\.[0-9]{2}\.[0-9]{4}$", $i_sDate))
   {
    $blnValid = FALSE;
   }
   else //format is okay, check that days, months, years are okay
   {
      $arrDate = explode(".", $i_sDate); // break up date by slash
      $intDay = $arrDate[0];
      $intMonth = $arrDate[1];
      $intYear = $arrDate[2];
 
      $intIsDate = checkdate($intMonth, $intDay, $intYear);
 
     if(!$intIsDate)
     {
        $blnValid = FALSE;
     }
 
   }//end else
 
   return ($blnValid);
}

function is_color($mColor) {
	return preg_match("/^\#[0-9A-Fa-f]{6}$/", $mColor);
}

function is_temp($mTemp) {
	return preg_match("/^\-?[0-9]{1,5} ?(C|F|K)$/", $mTemp);
}

function is_url($mUrl) {
	return preg_match(URL_FORMAT, $mUrl);
}

function createTextInput($tooltip, $paramName, $paramType, $oldValue) {
	$mtooltip = htmlentities($tooltip);
	$mparamName = htmlentities($paramName);
	$mparamName = urlencode($mparamName);
	$mparamType = htmlentities($paramType);
	$moldValue = htmlentities($oldValue);

	$inputOld = "";
	if (strlen($moldValue)) { 
		$inputOld .= " value='".$moldValue."'";
	}

	$returnString = <<<EOS
		<input class='FancyInputText' type=text id='form_$mparamName' name='form_$mparamName' $inputOld /><br />
		<span class='FancyInputSpan'>($mtooltip)<br/></span>
        	<input type='hidden' name='type_$mparamName' value='$mparamType'/>
EOS;
	return $returnString;
}

function createTextArea($tooltip, $paramName, $paramType, $oldValue) {
	$mtooltip = htmlentities($tooltip);
	$mparamName = htmlentities($paramName);
	$mparamName = urlencode($mparamName);
	$mparamType = htmlentities($paramType);
	$moldValue = htmlentities($oldValue);

	$inputOld = "";
	if (strlen($moldValue)) { 
		$inputOld .= " value='".$moldValue."'";
	}

	$returnString = <<<EOS
		<input class='FancyInputTextArea' type=textarea name='form_$mparamName' $inputOld /><br />
		<span class='FancyInputSpan'>($mtooltip)<br/></span>
        	<input type='hidden' id='form_$mparamName' name='type_$mparamName' value='$mparamType'/>
EOS;
	return $returnString;
}

?>













