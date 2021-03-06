<?php

//
// This script searches the specified file or directory (and subdirectories)
// for images wrapped in  <figure> <a class="gallery" tags
// adding the required markup, JavaScript and link to CSS library.
//
// Note: The original files are renamed with .bak extensions
// Note2: Has to be run after the CMS has been 'post processed' to remove some of the Morello garbage
//

// Before:
//	<figure>
//		<a class="gallery" href="#">
//			<img class="gallery" alt="Figure 1: Description, frequently too lengthy for us to use" src="http://drugs.homeoffice.gov.uk/images/fig-1">
//		</a>
//		<figcaption></figcaption>
//	</figure>


define("WEB_ROOT", "/");

function processFile($file)
{
	$images = array(
		WEB_ROOT."images/test-image.jpg" => WEB_ROOT."images/test-image_large.jpg",
		WEB_ROOT."images/test-latch-image.jpg" => WEB_ROOT."images/test-latch-image_large.jpg"
	);

	$contents = file_get_contents($file);
	
	// Check to see whether the file has already been processed
	if (strpos($contents, "shadowbox.js") !== false)
	{
		echo "Warning: Skipping '$file' as it has already been processed\r\n";
		return;
	}

	$imageCount = 0;
	// Look for references to images found in the 'images' array
	$figLoc = stripos($contents, "<figure>");
	while (($figLoc !== false) && ($figLoc < strlen($contents)))
	{
		$endFigLoc = stripos($contents, "</figure>", $figLoc);
			
		if ($endFigLoc === false)
		{
			echo "Error: No closing </figure> tag in $file\r\n";
			break;
		}
			
		$aLoc = stripos($contents, "<a class=\"gallery\"", $figLoc);
		
		if (($aLoc === false) || ($aLoc >= $endFigLoc))
		{
			echo "Warning: <figure> without <a class=\"gallery\"... in $file\r\n";
		}
		else
		{
			// We've got a live one
			if (processImg($contents, $figLoc, $aLoc, $endFigLoc, $file))
			{
				$imageCount++;
			}
		}
		$figLoc = stripos($contents, "<figure>", $figLoc + 1);
	}
	
	if ($imageCount > 0)
	{
		// Add lightbox CSS reference
		if (strpos($contents, "shadowbox.css") === false)
		{
			$insertAfter = "css/print.css\">";
			$insertPoint = strpos($contents, $insertAfter);
			if ($insertPoint === false)
			{
				die ("Error: Unable to find insert point (i.e. after css/print.css) in '$file'\r\n");
			}
			$insertPoint += strlen($insertAfter);
			$contents = substr($contents, 0, $insertPoint) . "\r\n<link rel=\"stylesheet\" href=\"" . WEB_ROOT . "css/shadowbox.css\" type=\"text/css\">\r\n" . substr($contents, $insertPoint);
		}

		// Add lightbox JS reference
		if (strpos($contents, "shadowbox.js") === false)
		{
			$insertAfter = "js/plugins.js\"></script>";
			$insertPoint = strpos($contents, $insertAfter);
			if ($insertPoint === false)
			{
				die ("Error: Unable to find insert point (i.e. after js/plugins.js) in '$file'\r\n");
			}
			$insertPoint += strlen($insertAfter);
			$contents = substr($contents, 0, $insertPoint) . "\r\n<script type=\"text/javascript\" src=\"" . WEB_ROOT . "js/shadowbox.js\"></script>\r\n" . substr($contents, $insertPoint);
		}

		// Add JavaScript function call
		if (strpos($contents, "Shadowbox.init") !== false)
		{
			die ("Error: Existing 'Shadowbox.init' call in '$file'\r\n");
		}
		else
		{
			$insertPoint = strpos($contents, "</head>");
			if ($insertPoint === false)
			{
				die ("Error: Unable to find insert point (i.e. </head>) in '$file'\r\n");
			}
			$contents = substr($contents, 0, $insertPoint) . "<script>\r\nShadowbox.init({overlayOpacity: 0.7});\r\n</script>\r\n" . substr($contents, $insertPoint);
		}
			
		// Rename original file
		$backup = $file.".bak";
		$i = 1;
		while ((file_exists($backup)) && ($i < 10))
		{
			$backup = $file.".bak".$i;
			$i++;
		}
		if ($i > 9)
		{
			die ("Error: Unable to backup file '$file'. Too many existing backups.\r\n");
		}
		rename($file, $backup);
		
		// Write new version
		if (file_put_contents($file, $contents) !== false)
		{
			unlink ($backup);
		}
		else
		{
			echo "Error! Failed to write $file. Please restore the backup.\r\n";
		}
		
		echo "Updated file: $file\r\n";
	}
}

function processImg(&$contents, $figLoc, $aLoc, $endFigLoc, $file)
{
	$aLocEnd = strpos($contents, ">", $aLoc);

	if (($aLocEnd === false) || ($aLocEnd > $endFigLoc))
	{
		echo "Error: Malformed <a> element in $file\r\n";
		return false;
	}
	
	$endALoc = stripos($contents, "</a>", $aLocEnd);
	if (($endALoc === false) || ($endALoc > $endFigLoc))
	{
		echo "Error: Missing </a> element in $file\r\n";
		return false;
	}

	// Find the src for the image and set it as the target for the <a>
	if (preg_match("/src=\"([^\"]*)\"/i", substr($contents, $aLocEnd, ($endALoc - $aLocEnd)), $matches) > 0)
	{
		$url = substr($matches[0], 4);
        
        // Manual override; remove figure & anchor tags from the Bishop's signature
        if (strpos($url, "/images/bishops-signature") !== false)
        {
            $contents = substr($contents, 0, $figLoc) . "<img class=\"gallery\" alt=\"Bishop James Jones&#039; signature\" src=" . $url . ">" . substr($contents, $endFigLoc + 9);
            
            return true;
        }
        
		// Find the <figcaption> text to use as a caption
		$caption = "";
		if (preg_match("/<figcaption>.*<\/figcaption>/is", substr($contents, $aLocEnd, ($endFigLoc - $aLocEnd)), $matches) > 0)
		{
			$caption = trim(str_replace(array("\r", "\r\n", "\n"), '', substr($matches[0], 12, -13)));
            
            // Manual overrides for overly long figure captions
            // Chapter 5, Page 8, Figure 5
            $shortText = "Figure 5: Route and time of entry of those who died";
            if (strpos($caption, $shortText) !== false)
                $caption = $shortText;
            // Chapter 5, Page 8, Figure 6
            $shortText = "Figure 6: Route and time of entry of those who died";
            if (strpos($caption, $shortText) !== false)
                $caption = $shortText;
            // Chapter 6, Page 2, Figure 9
            $shortText = "Figure 9: Prime Minister Margaret Thatcher with Press Secretary Bernard Ingham";
            if (strpos($caption, $shortText) !== false)
                $caption = $shortText;
 		}

		// Replace <a ... with an appropriately formatted one
		$contents = substr($contents, 0, $aLoc) . "<a class=\"gallery\" href=" . $url . " rel=\"shadowbox\" title=\"" . $caption . "\"" . substr($contents, $aLocEnd);	
	}
	else
	{
		echo "Error: Missing <img src=\"...\"> in $file\r\n";
		return false;
	}
	
	return true;
}

function processDir($dir)
{
	$dirContents = scandir($dir);
	// Loop through the directory's contents looking for files or sub-directories
	foreach($dirContents as $entry)
	{
		if (($entry != ".") && ($entry != ".."))
		{
			$entry = $dir."\\".$entry;
			if (is_file($entry))
			{
				processFile($entry);
			}
			elseif (is_dir($entry))
			{
				processDir($entry);
			}
		}
	}
}

function _main($argv, $argc)
{
	if ($argc < 2)
	{
		die ("Usage: php addLightboxes.php filename|directory [filename|directory ...]\r\n");
	}

	// Process each file or directory passed as a parameter
	for($i = 1; $i < $argc; $i++)
	{
		$arg = $argv[$i];
		if (is_file($arg))
		{
			processFile($arg);
		}
		else if (is_dir($arg))
		{
			processDir($arg);
		}
		else
		{
			echo "Error: '$arg' is not a recognised file or directory\r\n";
		}
	}
}

// ********************
//  SCRIPT ENTRY POINT
// ********************
_main($argv, $argc);


?>
