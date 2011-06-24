<?php
/**
* Script generates default locale files for all applications and plugins.
* Delete locale_map.ini from cache directory.
* 
* Usage:
* php -q generate-dummy-locale.php [Options]
* 
* Options:
* 
* -type value
* How to store files.
* value may be:
* 	pack translation file collecting to one folder (like in locales).
* 	near: translation file collecting to locale folder for application/plugin.
* Default: pack
* 
* -locale
* Locale name.
* Default: zz-ZZ
* 
* -skiptranslated
* If present, not saves already translated definitions.
* Default: false
* 
* Example: php -q generate-dummy-locale.php -type pack -locale en-US -skiptranslated
*/

require_once dirname(__FILE__).'/../../plugins/UsefulFunctions/bootstrap.console.php';

$DigDirectory = PATH_ROOT;

$MaxScanFiles = Console::Argument('m');
if (!is_numeric($MaxScanFiles) || $MaxScanFiles <= 0) $MaxScanFiles = 0;
$SkipTranslated = Console::Argument('skiptranslated') !== False;
$LocaleName = Console::Argument('locale');
if (!$LocaleName) $LocaleName = 'zz-ZZ';
$Type = Console::Argument('type');
switch ($Type) {
	case 'near': break;
	case 'pack': 
	default: $Type = 'pack';
}

$TestFile = Console::Argument('test');
if (file_exists($TestFile)) {
	if (is_dir($TestFile)) $DigDirectory = $TestFile;
	else {
		// TODO: cleanup here
		$Codes = GetTranslationFromFile($TestFile);
		d('TODO: cleanup here', $Codes);
	}
} else $TestFile = False;



$Applications = array('Dashboard', 'Vanilla', 'Conversations');
$Applications = array_combine($Applications, array_map('strtolower', $Applications));



$T = Gdn::FactoryOverwrite(True);
$DefaultLocale = new Gdn_Locale('en-CA', $Applications, array());
Gdn::FactoryInstall(Gdn::AliasLocale, 'Gdn_Locale', PATH_LIBRARY_CORE.'/class.locale.php', Gdn::FactorySingleton, $DefaultLocale);
Gdn::FactoryOverwrite($T);

$Count = 0;
$UndefinedTranslation = array();
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($DigDirectory)) as $File) {
	
	$Pathname = str_replace('\\', '/', $File->GetPathname());
	if (strpos($Pathname, '__') !== False) continue;
	if (pathinfo($Pathname, PATHINFO_EXTENSION) != 'php') continue;
	
	$Path = substr($Pathname, strlen(PATH_ROOT) + 1);
	$PathArray = explode('/', $Path);
	if (in_array($PathArray[0], array('themes', '_notes'))) continue;
	$Codes = GetTranslationFromFile($Pathname);
	if (count($Codes) == 0) continue;
	
	Console::Message('^3%s ^1(%s)', $Path, count($Codes));
	
	$StoreDirectory = 'applications/dashboard';
	if (in_array($PathArray[0], array('applications', 'plugins'))) $StoreDirectory = $PathArray[0].'/'.$PathArray[1];
	
	if ($Type == 'near') {

		$DefinitionsFile = dirname(__FILE__) . '/dummy-locale/' . $StoreDirectory . "/locale/{$LocaleName}.php";
	} elseif ($Type == 'pack') {
		$PackFile = ArrayValue(1, explode('/', $StoreDirectory));
		$DefinitionsFile = dirname(__FILE__)."/dummy-locale/$LocaleName/$PackFile.php";
	}
	
	$FileHash = md5_file($File->GetRealPath());
	
	$FileUndefinedTranslation =& $UndefinedTranslation[$DefinitionsFile];
	if (!is_array($FileUndefinedTranslation)) $FileUndefinedTranslation = array();
	
	$Index = count($FileUndefinedTranslation);

	$FileUndefinedTranslation[$Index]['Path'] = $Path;
	$FileUndefinedTranslation[$Index]['Codes'] = $Codes;

	++$Count;
	if ($MaxScanFiles > 0 && $Count >= $MaxScanFiles) break;
}

if ($SkipTranslated) {
	$T = Gdn::FactoryOverwrite(True);
	$DefaultLocale = new Gdn_Locale(C('Garden.Locale'), $Applications, array());
	Gdn::FactoryInstall(Gdn::AliasLocale, 'Gdn_Locale', PATH_LIBRARY_CORE.'/class.locale.php', Gdn::FactorySingleton, $DefaultLocale);
	Gdn::FactoryOverwrite($T);
}


// save it
foreach ($UndefinedTranslation as $File => $FileUndefinedTranslation) {
	
	$Directory = dirname($File);
	if (!is_dir($Directory)) mkdir($Directory, 0777, True);
	$FileContent = '';
		
	foreach ($FileUndefinedTranslation as $Index => $InfoArray) {
		$Codes = $InfoArray['Codes'];
		if (count($Codes) == 0) continue;
		$RelativePath = $InfoArray['Path'];
		$FileContent .= "\n\n// $RelativePath";
		
		foreach ($Codes as $Code => $T) {
			if ($SkipTranslated) if ($T != T($Code)) continue;
			$Code = var_export($Code, True);
			$T = var_export($T, True);
			$FileContent .= "\n\$Definition[{$Code}] = $T;";
		}
	}
	
	if ($FileContent == '') continue;
	
	$FileContent = "<?php\n// Date: " . date('r') . " \xEF\xBB\xBF " . $FileContent;
	$File = $Directory . '/' . strtolower(pathinfo($File, PATHINFO_BASENAME));
	
	Gdn_FileSystem::SaveFile($File, $FileContent);
}

/**
* Undocumented 
* 
* @param string $File, path to file.
* @return array $Result.
*/
function GetTranslationFromFile($File) {
	$Result = array();
	$Content = file_get_contents($File);
	if (!$Content) return $Result;
	$AllTokens = token_get_all($Content);
	foreach ($AllTokens as $N => $TokenData) {
		$TokenNum = ArrayValue(0, $TokenData);
		if ($TokenNum != 307) continue;
		$FunctionName = strtolower($TokenData[1]);
		// Form
		$Functions = array('adderror', 't', 'translate', 'plural', 'button', 'close', 'label', 'addvalidationresult');
		if (!in_array($FunctionName, $Functions)) continue;
		
		// find strings between () [also default code]
		$NextTokens = array_slice($AllTokens, $N);
		
		$OffSet = False;
		$Length = False;
		foreach ($NextTokens as $N => $Token) {
			if ($OffSet === False && is_string($Token) && $Token == '(') $OffSet = $N;
			elseif ($Length === False && is_string($Token) && $Token == ')') $Length = $N;
			if ($Length !== False && $OffSet !== False) break;
		}
		// find translation
		$Tokens = array_slice($NextTokens, $OffSet, $Length);
		$Strings = array();
		foreach ($Tokens as $N => $TokenData) {
			$TokenNum = ArrayValue(0, $TokenData);
			//if($TokenNum == 309) $VariableInTranslate = True;
			if ($TokenNum != 315) continue;
			$String = trim($TokenData[1], '\'"');
			if (!is_numeric($String)) $Strings[] = stripslashes($String);
		}
		if (count($Strings) == 0) continue;
		$TokenValues = array_values(array_filter($Tokens, 'is_string'));
		$RightBracketKey = array_search(')', $TokenValues);
		$TokenValues = array_slice($TokenValues, 1, $RightBracketKey - 1);
		$CountTokenValues = array_count_values($TokenValues);
		$bTernary = (in_array('?', $TokenValues) && in_array(':', $TokenValues));
		$bPlural = ($FunctionName == 'plural');
		$bPluralForms = ($bPlural && array_key_exists(',', $CountTokenValues) && $CountTokenValues[','] == 2);
		$bForm = in_array($FunctionName, array('button', 'close', 'label'));
		$bDefaultCode = (!$bForm && array_key_exists(',', $CountTokenValues) && $CountTokenValues[','] == 1 && count($Strings) == 2);
		$bConcatenated = in_array('.', $TokenValues);
		
		// save to array and return
		
		foreach ($Strings as $N => $String) {
			if ($FunctionName == 'addvalidationresult') {
				$bDefaultCode = False;
				if ($N == 1) {
					$Result[$String] = 'vr. %s: ' . $String;
					continue;
				}
			}
			if ($bDefaultCode) {
				$Result[$String] = $Strings[1];
				break;
			}
			$T = $String;
			if ($bPluralForms) $T = 'pl. '.$String;
			$Result[$String] = T($T);
			if ($bForm) break;
		}
	}

	return $Result;
}