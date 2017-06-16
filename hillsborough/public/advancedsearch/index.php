<?php 
require_once("database.php");

$ilSite = 2;

if ($ilSite==2)
	$db = new Database( "localhost", "hillsborough", "root", "" );
else
	$db = new Database( "localhost", "il0", "root", "" );

$suppressnav = GetApplicationIni();

function GetRedacted()
{
	global $db; 
	$sql = "select redacted_group_name, solr_query from redactedgroups where solr_query!='' ORDER BY group_order";
	$data = $db->dbFetch($sql);
	
	$html = "<option value=\"all\">All material (non-redacted and redacted)</option>";
	$html .= "<option value=\"unredactedonly\">Non-redacted material only</option>";
	$html .= "<option value=\"redactedonly\">Redacted material only</option>";
	foreach($data as $datarow)
		$html .= "<option value=\"".converttosolr($datarow['solr_query'])."\">".$datarow['redacted_group_name']."</option>";
	return $html;
}

function converttosolr($oldstr)
{
	return urlencode($oldstr);
}
	
function GetDayOptions()
{
	$html = "<option value=\"01\">1</option>\r\n".
		"<option value=\"02\">2</option>\r\n".
		"<option value=\"03\">3</option>\r\n".
		"<option value=\"04\">4</option>\r\n".
		"<option value=\"05\">5</option>\r\n".
		"<option value=\"06\">6</option>\r\n".
		"<option value=\"07\">7</option>\r\n".
		"<option value=\"08\">8</option>\r\n".
		"<option value=\"09\">9</option>\r\n".
		"<option value=\"10\">10</option>\r\n".
		"<option value=\"11\">11</option>\r\n".
		"<option value=\"12\">12</option>\r\n".
		"<option value=\"13\">13</option>\r\n".
		"<option value=\"14\">14</option>\r\n".
		"<option value=\"15\">15</option>\r\n".
		"<option value=\"16\">16</option>\r\n".
		"<option value=\"17\">17</option>\r\n".
		"<option value=\"18\">18</option>\r\n".
		"<option value=\"19\">19</option>\r\n".
		"<option value=\"20\">20</option>\r\n".
		"<option value=\"21\">21</option>\r\n".
		"<option value=\"22\">22</option>\r\n".
		"<option value=\"23\">23</option>\r\n".
		"<option value=\"24\">24</option>\r\n".
		"<option value=\"25\">25</option>\r\n".
		"<option value=\"26\">26</option>\r\n".
		"<option value=\"27\">27</option>\r\n".
		"<option value=\"28\">28</option>\r\n".
		"<option value=\"29\">29</option>\r\n".
		"<option value=\"30\">30</option>\r\n".
		"<option value=\"31\">31</option>\r\n";
	return $html;
}

function GetMonthOptions()
{
	$html = "<option value=\"01\">January</option>\r\n" . 	
		"<option value=\"02\">February</option>\r\n" .
		"<option value=\"03\">March</option>\r\n" .
		"<option value=\"04\">April</option>\r\n" .
		"<option value=\"05\">May</option>\r\n" .
		"<option value=\"06\">June</option>\r\n" .
		"<option value=\"07\">July</option>\r\n" .
		"<option value=\"08\">August</option>\r\n" .
		"<option value=\"09\">September</option>\r\n" .	
		"<option value=\"10\">October</option>\r\n" .
		"<option value=\"11\">November</option>\r\n" .
		"<option value=\"12\">December</option>\r\n";
	return $html;
}
		
function GetYearOptions()
{
	global $ilSite;
	
	$startDate = 1890;
	$endDate = 1930;

	if ($ilSite==2)
	{
		$startDate = 1980;
		$endDate = 2001;
	}
	
	$html = "";
	for($i=$startDate; $i<=$endDate; $i++)
		$html .= "<option value=\"".$i."\">".$i."</option>\r\n";
	return $html;
}

function GetOrgs()
{
	global $db, $suppressnav; 
	if ($suppressnav)
	{
		$sql = "SELECT distinct o.owning_organisation as owning_organisation, o.description, o.dir_name, o.non_contributing " . 
			"FROM organisations o " . 
			"inner join disclosed_material d on o.owning_organisation = d.owning_organisation and d.out_of_scope_reason = '' " . 
			"where o.non_contributing = 0 " . 
			"order by owning_organisation asc";
	}
	else
	{
		$sql = 
			"SELECT distinct owning_organisation, dir_name " .
			"FROM organisations " .
			"where non_contributing = 0 " .
			"order by owning_organisation";
	}
	
	$data = $db->dbFetch($sql);	
	
	$html = "";
	foreach($data as $datarow)
	{
		
		$html .= "<option value=\"".converttosolr($datarow['owning_organisation'])."\">".$datarow['owning_organisation']."</option>\r\n";
	}
	return $html;
}

function GetLookupData($id)
{
	global $db; 
	$sql = "SELECT id, presentation_format, url_name FROM autopopulatelookup where type = " . $id . " ORDER BY presentation_format";
	$data = $db->dbFetch($sql);
	
	$html = "";
	foreach($data as $datarow)
		$html .= "<option value=\"".converttosolr($datarow['id'])."\">".$datarow['presentation_format']."</option>\r\n";
	return $html;
}

function GetApplicationIni()
{
	$iniArray = parse_ini_file("../../application/configs/application.ini");
	if (isset($iniArray["hillsborough.suppress_navigation"]))
	{
		if ($iniArray["hillsborough.suppress_navigation"] == 1)
		{
			return TRUE;
		}
	}
	return FALSE;
}

?>


<!DOCTYPE html>

<!--[if IEMobile 7]><html class="no-js iem7"><![endif]-->
<!--[if lt IE 7]><html class="no-js ie6" lang="en"><![endif]-->
<!--[if (IE 7)&!(IEMobile)]><html class="no-js ie7" lang="en"><![endif]-->
<!--[if IE 8]><html class="no-js ie8" lang="en"><![endif]-->
<!--[if (gte IE 9)|(gt IEMobile 7)|!(IEMobile)|!(IE)]><!--><html class="no-js" lang="en"><!--<![endif]-->

<head>
<META http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Advanced search of disclosed material | Hillsborough Independent Panel</title>
<!-- HIP generated <?php echo date("d-m-Y:Gi", time()) ?> -->

<!-- http://t.co/dKP3o1e -->
<meta name="HandheldFriendly" content="True">
<meta name="MobileOptimized" content="320">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0;">  

<!-- For all browsers -->
<link rel="stylesheet" media="screen" href="/css/style.css">
<link rel="stylesheet" media="print" href="/css/print.css">
<!-- For progressively larger displays -->
<link rel="stylesheet" media="only screen and (min-width: 480px)" href="/css/480.css">
<link rel="stylesheet" media="only screen and (min-width: 768px)" href="/css/768.css">
<link rel="stylesheet" media="only screen and (min-width: 992px)" href="/css/992.css">
<link rel="stylesheet" media="only screen and (min-width: 1382px)" href="/css/1382.css">
<!-- For Retina displays -->
<link rel="stylesheet" media="only screen and (-webkit-min-device-pixel-ratio: 2), only screen and (min-device-pixel-ratio: 2)" href="/css/2x.css">

<!--[if (lt IE 9) & (!IEMobile)]>
<link rel="stylesheet" media="screen" href="/css/480.css">
<link rel="stylesheet" media="screen" href="/css/768.css">
<link rel="stylesheet" media="screen" href="/css/992.css">
<![endif]-->

<!-- Scripts -->
<script src="/js/search.js"></script>
<script src="/js/libs/jquery-1.5.1.min.js"></script>
<script src="/js/libs/modernizr-custom.js"></script>
<script src="/js/utils.js"></script>

<!--[if (lt IE 9) & (!IEMobile)]>
<script src="/js/libs/jquery-extended-selectors.js"></script>
<script src="/js/libs/selectivizr-min.js"></script>
<script src="/js/libs/imgsizer.js"></script>
<![endif]-->

<!-- For iPhone 4 -->
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="/img/h/apple-touch-icon.png">
<!-- For iPad 1-->
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="/img/m/apple-touch-icon.png">
<!-- For iPhone 3G, iPod Touch and Android -->
<link rel="apple-touch-icon-precomposed" href="/img/l/apple-touch-icon-precomposed.png">
<!-- For Nokia -->
<link rel="shortcut icon" href="/img/l/apple-touch-icon.png">
<!-- For everything else -->
<link rel="shortcut icon" href="/favicon.ico">

<!--iOS. Delete if not required -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-startup-image" href="/img/splash.png">

<!--Microsoft. Delete if not required -->
<meta http-equiv="cleartype" content="on">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

<!-- http://t.co/y1jPVnT -->
<link rel="canonical" href="/">

<!-- Google analytics tracking code -->

<script type="text/javascript">

var ga_tc = 'UA-31561646-1';
var ga_domain = 'google-analytics.com';

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', ga_tc]);
  _gaq.push(['_trackPageview']);
  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.' + ga_domain + '/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();
</script>


<script type="text/javascript">
$(function(){
var p = getURLVar("fq=hip_redacted:");
if (p) 
{ 
	var parsed = stripAsterisk(unescape(p));
	if (parsed=="[\"\" TO *]") 
		document.getElementById("redtype").value = "redactedonly";
	else
		document.getElementById("redtype").value = parsed;
}
var p = getURLVar("fq=-hip_redacted:");
if (p) 
{ 
//	alert(stripAsterisk(unescape(p)));
	document.getElementById("redtype").value = "unredactedonly"; 
}

var p = getURLVar("fq=hip_chapter:");
if (p) { document.getElementById("onlyreport").checked = true; }
var p = getURLVar("q=");
if ((p)&&(p!="*:*")) { document.getElementById("keywords").value = unescape(p); }
var p = getURLVar("fq=hip_uid:");
if (p) { document.getElementById("uid").value = stripAsterisk(unescape(p)); }
var p = getURLVar("fq=hip_archive_ref:");
if (p) { document.getElementById("orgref").value = stripAsterisk(unescape(p)); }
var p = getURLVar("fq=hip_contrib_org:");
if (p) { //alert(stripQuotes(unescape(p)));
 document.getElementById("organisation").value = escape(stripQuotes(unescape(p))); }
var p = getURLVar("fq=hip_victim:");
if (p) { document.getElementById("victim").value = stripQuotes(unescape(p)); }
var p = getURLVar("fq=hip_corporate:");
if (p) { document.getElementById("corpbody").value = stripQuotes(unescape(p)); }
var p = getURLVar("fq=hip_person:");
if (p) { document.getElementById("person").value = stripQuotes(unescape(p)); }
var p = getURLVar("fq=hip_search_end:");
if (p) {
p = unescape(p);
var q = p.slice(9,11);
if (!q) q = "-";
document.getElementById("startdateday").value = q;
q = p.slice(6,8);
if (!q) q = "-";
document.getElementById("startdatemonth").value = q;
q = p.slice(1,5);
if (!q) q = "-";
document.getElementById("startdateyear").value = q;
q = p.slice(113,115);
if (!q) q = "-";
document.getElementById("enddateday").value = q;
q = p.slice(110,112);
if (!q) q = "-";
document.getElementById("enddatemonth").value = q;
q = p.slice(105,109);
if (!q) q = "-";
document.getElementById("enddateyear").value = q;
}
});
</script>


</head>
<body class="clearfix">

<header role="banner" class="clearfix">
<a href="/">
	<hgroup class="clearfix">
		<h1>Hillsborough Independent Panel</h1>
		<h2>Disclosed Material and Report</h2>
	</hgroup>
</a>
<nav class="clearfix">
<ul>
<li><a href="/">Home</a></li>
<?php if (!$suppressnav)
		{ ?>
<li><a href="/contact-us/">Contact</a></li>
<li><a href="/glossary/">Glossary</a></li>
<li><a href="/site-map/">Site map</a></li>
<li><a href="/help/">Help</a></li>
<?php	} ?>
</ul>
<form method="get" action="/search/select" role="search">
<p>
<input name="rows" type="hidden" value="10" />
<input name="fq" type="hidden" value="-hip_outofscope_reason:['' TO *]" />
<label for="q" style="display: none">Search everything</label>
<input name="q" type="text" placeholder="Search everything" autocomplete="off" list="search-list" />
<button>Search</button>
<datalist id="search-list">
<option value="Disaster">
<option value="Football">
<option value="Hillsborough">
<option value="Liverpool">
<option value="Sheffield">
</datalist></p>
<p class="search-option">
<a href="/advancedsearch/">Advanced search</a></p>
</form>
</nav>
</header>
 	
<div class="clearfix">

<nav role="navigation">
<ul class="menu">
<?php if (!$suppressnav)
		{ ?>
<li><a href="/report/">The Report</a></li>
<?php	} ?>
<li><a href="/browse/">Browse the disclosed material</a></li>
<li><a href="/catalogue/index/organisation/all/outofscope/all/perpage/20/page/1">Catalogue of all material considered for disclosure</a></li>
<?php if (!$suppressnav)
		{ ?>
<li><a href="/disclosure-process/">The disclosure process</a></li>
<li><a href="/the-independent-panel/">The Independent Panel</a></li>
<?php	} ?>
</ul>
</nav>

<div class="content clearfix">
<h1>Advanced search of disclosed material</h1>
<div role="main">
<div id="validationmessage"></div>

<p>Refine your search of the website and disclosed material by selecting one or more of the options below.</p>

<p>Advanced search looks at disclosed material only. To see a list of all material considered by the Panel (including material not disclosed on this website) <a href="/catalogue/index/organisation/all/outofscope/all/perpage/20/page/1">go to the Catalogue</a>.</p>

<p>If you just want to search the Report, go to the Report section of the website and use the &quot;search everything&quot; bar, with the &quot;Search report only&quot; box ticked.</p>

<p>If you require assistance using advanced search go to <a href="/help/">Help</a></p>

<form method="get" action="javascript:searchsubmit();" role="search">

<h3>What disclosed material to include in your search</h3>
<fieldset>
<p>
<input id="incdisclosed" type="radio" value="radio" name="include disclosed" checked />
<label for="incdisclosed">Search all disclosed material</label><br/>
<input id="onlyreport" type="radio" value="radio" name="include disclosed" />
<label for="onlyreport">Search only disclosed material referenced in the Panel's Report</label></p>
</fieldset>

<fieldset>
<p>
<label for="redtype">Redacted material</label>
<select name="redtype" id="redtype">
	<?php echo GetRedacted(); ?>
</select>
</p>
</fieldset>


<h3>What to search for</h3>

<p class="box">To narrow down your search select some or all of the categories below.</p>

<br/>

<fieldset>
<p><label for="keywords">Keywords</label><br/>You can add words or phrases related to your search<input id="keywords" name="keywords" type="text" placeholder="keywords" autocomplete="off"></p>
</fieldset>

<fieldset>
<p><label for="uid">Unique ID</label><br/>Number given to the document by the Panel<input id="uid" name="uid" type="text" placeholder="unique ID" autocomplete="off"></p>
</fieldset>

<fieldset><p>
<label for="organisation">Contributor</label><br/>The organisation or individual who supplied the material
<select id="organisation" name="organisation">
	<option value="-">choose</option>
	<?php echo GetOrgs(); ?>
</select>
</p>
</fieldset>

<fieldset>
<p><label for="orgref">Contributor reference</label><br/>Number given to the document by the contributor
<input id="orgref" name="orgref" type="text" placeholder="contributor reference" autocomplete="off"></p>

</fieldset>
<fieldset>
<p><label for="victim">Name of those who died</label><br/>
<select id="victim" name="victim">
	<option value="-">choose</option>
	<?php echo GetLookupData(1); ?>
</select></p>
<p><label for="corpbody">Organisation involved</label><br/>Organisations mentioned frequently in the Panel's Report<br/>
<select id="corpbody" name="corpbody">
<?php if (!$suppressnav)
		{ ?>
	<option value="-">choose</option>
	<?php echo GetLookupData(2); ?>
<?php	} else { ?>
	<option value="-"> - to be confirmed - </option>
<?php	} ?>
</select></p>
<p><label for="person">Person involved</label><br/>Individuals mentioned frequently in the Panel's Report. Does not include those who died.<br/>
<select id="person" name="person">
<?php if (!$suppressnav)
		{ ?>
	<option value="-">choose</option>
	<?php echo GetLookupData(3); ?>
<?php	} else { ?>
	<option value="-"> - to be confirmed - </option>
<?php	} ?>
</select></p>
</fieldset>

<fieldset>
<p class="dtstart">
<label for="startdateday">Date (or start date)</label><br/>If you know when the material was created or are interested in a particular date you can add it here.
<br/>
<select id="startdateday" name="startdateday" class="selectshort">
	<option value="-" selected="true">day</option>
	<?php echo GetDayOptions(); ?>
</select>
	<label for="startdatemonth" style="display: none">Month (or start month)</label>
	<select id="startdatemonth" name="startdatemonth" class="selectlong">
		<option value="-" selected="true">month</option>
		<?php echo GetMonthOptions(); ?>
	</select>
	<label for="startdateyear" style="display: none">Year (or start year)</label>
	<select id="startdateyear" name="startdateyear" class="selectshort">
	<option value="-" selected="true">year</option>
<?php echo GetYearOptions(); ?>
		
</select>
</p>

<p class="dtend">
<label for="enddateday">End date</label>
<br/>
If you want to search for a range of dates include an end date.
<br/>
<select id="enddateday" name="enddateday" class="selectshort">
	<option value="-" selected="true">day</option>
<?php echo GetDayoptions(); ?>

</select>
	<label for="enddatemonth" style="display: none">End month</label>
	<select id="enddatemonth" name="enddatemonth" class="selectlong">
		<option value="-" selected="true">month</option>
		<?php echo GetMonthOptions(); ?>
	</select>
	<label for="enddateyear" style="display: none">End year</label>
	<select id="enddateyear" name="enddateyear" class="selectshort">
	<option value="-" selected="true">year</option>
	<?php echo GetYearOptions(); ?>
	</select>
</p>
</fieldset>


 
<button>Search</button>
</form>
</div>


<div role="complementary">
	<div class="box">
		<h3>Advice</h3>
		<p>To help find documents, we are using Optical Character Recognition (OCR) software to 'read' words on the scanned images of pages.  Documents may not appear if the OCR software was unable to recognise the relevant word eg where they are hand written, or the paper original was in a poor condition.</p>
		<h3>Redaction</h3>
		<p>This means removal of words or phrases from document text. Only minimal redaction has been allowed eg for legal reasons or reasons of public decency. This is explained fully in the <a href="/disclosure-process/redaction-framework/">Panel's redaction framework</a>.</p>
		<h3>Unique ID and contributor reference</h3>
		<p>If you are looking for a specific document and know its reference number type it into <strong>Unique ID</strong>. Alternatively, If you know the contributor reference number type it in the <strong>Contributor reference</strong> box.</p>
		<h3>Using dates to filter your search</h3>
		<p>Dates may be entered as year only; month and year; or day, month and year.</p>
		<p>You do not need to enter an end date.</p><p>You will receive an error message if you enter an end date which is before the start date or an end date without a start date.</p>
		<p>If an 'invalid' date is entered, the site will return documents with dates in the month being searched and the following month, eg if 31st February is searched it will include February and March.</p>
		<p>Go to <a href="/help/">Help</a> for more tips on searching.</p>
	</div>
</div>



</div>

</div>

<footer role="contentinfo" class="clearfix">

<?php if (!$suppressnav)
		{ ?>
<nav>
	<ul>
		<li><a href="/website-accessibility/">Accessibility</a></li>
		<li><a href="/terms-conditions/">Terms and conditions</a></li>
		<li><a href="/cookies/">Cookies</a></li>		
		<li><a href="/open-data/">Open Data</a></li>
	</ul>
</nav>
<?php	} ?>

<small>&#169; Crown Copyright 2012</small>

</footer>

</body>
</html>