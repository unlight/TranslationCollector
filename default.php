<?php if (!defined('APPLICATION')) exit();

/*
1. DESCRIPTION
==============
1. Plugin catch undefined translate codes and save it 
(Make sure for exists event BeforeTranslate ~ line 200 in /library/core/class.locale.php)
2. Generates dummy locale for all applications and plugins (see generate-dummy-locale.php)

2. INSTALL
==========
Make sure for exists event BeforeTranslate ~ line 220 in /library/core/class.locale.php
If it is not, add lines:
$this->EventArguments['Code'] = $Code;
$this->FireEvent('BeforeTranslate');
Before this line:
if (array_key_exists($Code, $this->_Definition)) {

3. USAGE
========
Enable plugin in dashboard, and see plugins directory /plugins/TranslationCollector/undefined
Translation codes will be saved to file [ApplicationName].php
[!] Already translated codes (definitions which are exists in locale files) will NOT be saved.

4. KNOWN ISSUES
===============
If you get error: syntax error, unexpected T_STRING, expecting ']' in
/some/path/to/plugins/TranslationCollector/[ApplicationName].php 
If you get this error remove line or delete this file.
This is issue fixed in version 1.1

5. CHANGELOG
============
2 Sep 2010 / 1.4
[unknown] something changed
28 Aug 2010 / 1.3
[fix] http://github.com/vanillaforums/Garden/issues/issue/497
[alt] non *handler methods made protected [no need check it by PluginManager::RegisterPlugins()]
[add] default translate for english codes eg. EmailWelcome, PasswordRequest, etc.
[add] collect codes from form methods (Label, Button, etc.)
01 Aug 2010 / 1.2
[new] dummy locale generator
[alt] undefined definitions moved to directory "undefined"
[add] byte order mask for saved files
19 Jun 2010 / 1.1
[fix] fixed issue (syntax error, unexpected T_STRING, expecting ']')
[alt] default application Vanilla to Dashboard
17 Jun 2010 / 1.0
[new] first release

6. TODO
=======
option skip already translated strings
fix warning: Unterminated comment starting
Better detection default code // $Definition['Theme_$Key'] = '';
console param to collect codes from desired application/plugin
how we can catch this array_map('T', array())?
remove garbage: if (!$this->t->isDone()) minify plugin

7. CONFIG
$Configuration['Plugins']['TranslationCollector']['SkipApplications'] = array();
*/


$PluginInfo['TranslationCollector'] = array(
	'Name' => 'Translation collector',
	'Description' => 'Collects undefined translation codes and save it for translating.',
	'Version' => '1.4.7',
	'Date' => '4 Jan 2011'
);

class TranslationCollectorPlugin implements Gdn_IPlugin {
	
	private $_Definition = array();
	private $_EnabledApplication = 'Dashboard';
	
	public function __construct() {
		$Locale = Gdn::Locale();
		unset($Locale->EventArguments['WildEventStack']);
		$Export = var_export($Locale, True);
		$RegExp = "/\s+'_Definition' => (\s+ array \(.+?,\s+\)),/s";
		preg_match($RegExp, $Export, $Match);
		eval("\$this->_Definition = $Match[1];");
	}
	
	protected function Translate($Code) {
		return ArrayValue($Code, $this->_Definition);
	}
	
	public function Gdn_Locale_BeforeTranslate_Handler(&$Sender) {
		$Application = $this->_EnabledApplication();
		$SkipApplications = C('Plugins.TranslationCollector.SkipApplications', array());
		if (in_array($Application, $SkipApplications)) return;
		
		$Code = GetValue('Code', $Sender->EventArguments, '');
		if (array_key_exists($Code, $this->_Definition)) return;
		
		$File = CombinePaths(array(dirname(__FILE__), 'undefined', $Application.'.php'));
		$HelpText = 'TRANSLATE, CUT AND PASTE THIS TO /applications/application-folder/locale/locale-name-folder/definitions.php';
		$HelpText .= '\xEF\xBB\xBF'; // UTF-8 byte order mask
		if (!file_exists($File)) Gdn_FileSystem::SaveFile($File, "<?php // $HelpText\n");
		$Definition = array();
		include $File;
		if (!array_key_exists($Code, $Definition)) {
			$FileContent = file_get_contents($File);
			$Code = var_export($Code, True); // should be escaped
			$FileContent .= "\n\$Definition[".$Code."] = $Code;";
			Gdn_FileSystem::SaveFile($File, $FileContent);
		}
	}
		
	public function Gdn_Dispatcher_AfterEnabledApplication_Handler(&$Sender) {
		$this->_EnabledApplication = ArrayValue('EnabledApplication', $Sender->EventArguments, 'Dashboard');
	}
	
	private function _EnabledApplication() {
		return $this->_EnabledApplication;
	}
	
	public function Setup() {
	}
	
	
	
}