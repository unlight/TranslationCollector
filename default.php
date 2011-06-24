<?php if (!defined('APPLICATION')) exit();

$PluginInfo['TranslationCollector'] = array(
	'Name' => 'Translation collector',
	'Description' => 'Collects undefined translation codes and save it for translating.',
	'Version' => '1.6.9',
	'Date' => 'Summer 2011'
);



if (C('Plugins.TranslationCollector.CaptureDefinitions', True)) {
	
	class TranslationCollectorLocale extends Gdn_Locale {
		
		public function __construct($LocaleName = Null, $ApplicationWhiteList = Null, $PluginWhiteList = Null, $ForceRemapping = FALSE) {
			// If called from PluginManager::GetPluginInstance()
			// Do nothing.
			if ($LocaleName === Null) return;
			parent::__construct($LocaleName, $ApplicationWhiteList, $PluginWhiteList, $ForceRemapping);
		}
		
		public function Translate($Code, $Default = False) {
			$this->EventArguments['Code'] = $Code;
			$this->FireEvent('BeforeTranslate');
			$Result = parent::Translate($Code, $Default);
			return $Result;
		}
	}
	
	$TcLocale = Gdn::Locale();
	if (is_null($TcLocale)) {
		$CurrentLocale = Gdn::Config('Garden.Locale', 'en-CA');
		$TcLocale = new TranslationCollectorLocale($CurrentLocale, C('EnabledApplications'), C('EnabledPlugins'));
		$Overwrite = Gdn::FactoryOverwrite(True);
		Gdn::FactoryInstall(Gdn::AliasLocale, 'TranslationCollectorLocale', __FILE__, Gdn::FactorySingleton, $TcLocale);
		Gdn::FactoryOverwrite($Overwrite);
	}
	
}



class TranslationCollectorPlugin implements Gdn_IPlugin {
	
	private $_Definition = array();
	private $_EnabledApplication = 'Dashboard';
	private $SkipApplications = array();
	
	public function __construct() {
		$Locale = Gdn::Locale();
		unset($Locale->EventArguments['WildEventStack']);
		$Export = var_export($Locale, True);
		$CutPointA = strpos($Export, "'_Definition' =>") + 16;
		$CutPointB = strrpos($Export, "'_Locale' =>", $CutPointA);
		if ($CutPointA === False || $CutPointB === False) {
			trigger_error('Failed to detect cutpoints.', E_USER_ERROR);
		}
		$Match = substr($Export, $CutPointA, ($CutPointB - $CutPointA));
		$Match = trim(trim(trim($Match), ','));
		eval("\$this->_Definition = $Match;");
		
		$this->SkipApplications = C('Plugins.TranslationCollector.SkipApplications', array());
	}
	
	protected function Translate($Code) {
		return ArrayValue($Code, $this->_Definition);
	}
	
	public function TranslationCollectorLocale_BeforeTranslate_Handler(&$Sender) {
		
		$Application = $this->_EnabledApplication();
		if (in_array($Application, $this->SkipApplications)) return;
		
		$Code = GetValue('Code', $Sender->EventArguments, '');
		if (substr($Code, 0, 1) == '@') return;
		if (array_key_exists($Code, $this->_Definition)) return;
		
		$File = CombinePaths(array(dirname(__FILE__), 'undefined', $Application.'.php'));
		$HelpText = 'TRANSLATE, CUT AND PASTE THIS TO /locales/locale-name-folder/definitions.php';
		$HelpText .= "\xEF\xBB\xBF"; // utf-8 byte order mask
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