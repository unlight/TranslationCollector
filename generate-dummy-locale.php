#!/usr/local/bin/php
<?php
/*
1. DESCRIPTION
==============
Script generates default locale files for all applications and plugins

2. USAGE
========
1. Delete locale_map.ini from cache directory
2. Run script: php -q generate-dummy-locale.php

*/

error_reporting(E_ALL);
ini_set('html_errors', 'Off');
ini_set('display_errors', 'On');
ini_set('track_errors', 'On');

if(!defined('DS')) define('DS', '/');
chdir(dirname(__FILE__).DS.'..'.DS.'..');
if(!defined('PATH_ROOT')) define('PATH_ROOT', realpath('.'));
if(!defined('APPLICATION')) define('APPLICATION', 'Garden');
if(!defined('APPLICATION_VERSION')) define('APPLICATION_VERSION', '1.0');

require_once PATH_ROOT.DS.'bootstrap.php';

$Applications = array('Dashboard', 'Vanilla', 'Conversations');
$Applications = array_combine($Applications, array_map('strtolower', $Applications));

$bSkipAlreadyTranslated = True;

$T = Gdn::FactoryOverwrite(TRUE);
$DefaultLocale = new Gdn_Locale('en-CA', $Applications, array());
Gdn::FactoryInstall(Gdn::AliasLocale, 'Gdn_Locale', PATH_LIBRARY_CORE.DS.'class.locale.php', Gdn::FactorySingleton, $DefaultLocale);
Gdn::FactoryOverwrite($T);


/*$TestFile = dirname(__FILE__).DS.'________class.invitationmodel.php';
GetTranslationFromFile($TestFile);die;*/

$Locale = array();
$Directory = new RecursiveDirectoryIterator(PATH_ROOT);
foreach(new RecursiveIteratorIterator($Directory) as $File){
	$RealPath = $File->GetRealPath();
	$Extension = strtolower(pathinfo($RealPath, 4));
	$FileName = strtolower(pathinfo($RealPath, 8));
	if ($Extension != 'php') continue;
	if (strpos($FileName, '__') !== False) continue;
	$Path = substr($RealPath, strlen(PATH_ROOT) + 1);
	$PathArray = explode('/', str_replace('\\', '/', $Path));
	$StoreDirectory = 'applications/dashboard';
	if (in_array($PathArray[0], array('applications', 'plugins'))) $StoreDirectory = $PathArray[0].'/'.$PathArray[1];
	if (in_array($PathArray[0], array('themes'))) continue;
	$StoreDirectory .= '/locale/zz-ZZ';
	$StoreDirectory = dirname(__FILE__).'/dummy-locale/' . $StoreDirectory;
	$DefinitionsFile = $StoreDirectory . '/definitions.php';
	$Codes = GetTranslationFromFile($RealPath);
	$LocalFileHash = md5($RealPath);
	if (!isset($Locale[$DefinitionsFile][$LocalFileHash])) $Locale[$DefinitionsFile][$LocalFileHash] = array();
	$R =& $Locale[$DefinitionsFile][$LocalFileHash];
	$R = array_merge($R, $Codes);
	
	//if (@$Count++ > 50) break;
}

if ($bSkipAlreadyTranslated) {
	$T = Gdn::FactoryOverwrite(TRUE);
	$DefaultLocale = new Gdn_Locale(C('Garden.Locale'), $Applications, array());
	Gdn::FactoryInstall(Gdn::AliasLocale, 'Gdn_Locale', PATH_LIBRARY_CORE.DS.'class.locale.php', Gdn::FactorySingleton, $DefaultLocale);
	Gdn::FactoryOverwrite($T);
}


// save it
$Date = date('r');
foreach ($Locale as $File => $LocalFiles) {
	
	$Directory = dirname($File);
	if (!is_dir($Directory)) mkdir($Directory, 0777, True);
	$FileContent = "<?php\n// Date: $Date \xEF\xBB\xBF"; // byte order mask
	
	foreach($LocalFiles as $Hash => $Codes) {
		if (count($Codes) == 0) continue;
		
		$FileContent .= "\n";
		
		foreach($Codes as $Code => $T){
			if ($bSkipAlreadyTranslated) if ($T != T($Code)) continue;
			$Code = var_export($Code, True);
			$T = var_export($T, True);
			$FileContent .= "\n\$Definition[{$Code}] = $T;";
		}
	}
	
	Gdn_FileSystem::SaveFile($File, $FileContent);
}

function GetTranslationFromFile($File){
	$Result = array();
	$Content = file_get_contents($File);
	if(!$Content) return $Result;
	$AllTokens = token_get_all($Content);
	foreach($AllTokens as $N => $TokenData){
		$TokenNum = ArrayValue(0, $TokenData);
		if($TokenNum != 307) continue;
		$FunctionName = strtolower($TokenData[1]);
		// Form
		$Functions = array('adderror', 't', 'translate', 'plural', 'button', 'close', 'label', 'addvalidationresult');
		if(!in_array($FunctionName, $Functions)) continue;
		// find strings between () [also default code]
		$NextTokens = array_slice($AllTokens, $N);
		$OffSet = False;
		$Length = False;
		foreach($NextTokens as $N => $Token){
			if($OffSet === False && is_string($Token) && $Token == '(') $OffSet = $N;
			elseif($Length === False && is_string($Token) && $Token == ')') $Length = $N;
			if($Length !== False && $OffSet !== False) break;
		}
		// find translation
		$Tokens = array_slice($NextTokens, $OffSet, $Length);
		$Strings = array();
		foreach($Tokens as $N => $TokenData){
			$TokenNum = ArrayValue(0, $TokenData);
			//if($TokenNum == 309) $VariableInTranslate = True;
			if($TokenNum != 315) continue;
			$String = trim($TokenData[1], '\'"');
			if(!is_numeric($String)) $Strings[] = stripslashes($String);
		}
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

		foreach($Strings as $N => $String){
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







