<?php if (!defined('APPLICATION')) exit();

$PluginInfo['TranslationCollector'] = array(
	'Name' => 'Translation Collector',
	'Description' => 'Collects undefined translation codes and save it for translating.',
	'Version' => '1.6.10',
	'Date' => 'Summer 2011'
);



if (C('Plugins.TranslationCollector.CaptureDefinitions', True)) {
	
	class TranslationCollectorLocale extends Gdn_Locale {
		
		public function __construct($LocaleName = Null, $ApplicationWhiteList = Null, $PluginWhiteList = Null, $ForceRemapping = False) {
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
		$CurrentLocale = C('Garden.Locale', 'en-CA');
		$TcLocale = new TranslationCollectorLocale($CurrentLocale, C('EnabledApplications'), C('EnabledPlugins'));
		$Overwrite = Gdn::FactoryOverwrite(True);
		Gdn::FactoryInstall(Gdn::AliasLocale, 'TranslationCollectorLocale', Null, Gdn::FactorySingleton, $TcLocale);
		Gdn::FactoryOverwrite($Overwrite);
	}
	
}



class TranslationCollectorPlugin implements Gdn_IPlugin {
	
	private $_Definition = array();
	private $_EnabledApplication = 'Dashboard';
	private $_SkipApplications = array();
	
	public function __construct() {
		$this->_Definition = self::GetLocaleDefinitions();
		$this->_SkipApplications = C('Plugins.TranslationCollector.SkipApplications', array());
	}
	
	public static function GetLocaleDefinitions() {
		$Locale = Gdn::Locale();
		unset($Locale->EventArguments['WildEventStack']);
		$Variable = var_export($Locale, True);
		$CutPoint1 = strpos($Variable, "'_Definition' =>") + 16;
		$CutPoint2 = strrpos($Variable, "'_Locale' =>", $CutPoint1);
		if ($CutPoint1 === False || $CutPoint2 === False) throw new Exception('Failed to detect cutpoints.');
		
		$Match = substr($Variable, $CutPoint1, ($CutPoint2 - $CutPoint1));
		$Match = trim(trim(trim($Match), ','));
		// Kids, never use eval.
		eval("\$Result = $Match;");
		return $Result;
	}
	
	public function TranslationCollectorLocale_BeforeTranslate_Handler($Sender) {
		
		$Application = $this->_EnabledApplication();
		if (in_array($Application, $this->_SkipApplications)) return;
		
		$Code = GetValue('Code', $Sender->EventArguments, '');
		if (substr($Code, 0, 1) == '@') return;
		if (array_key_exists($Code, $this->_Definition)) return;
		
		$File = dirname(__FILE__) . '/undefined/' . $Application . '.php';
		$HelpText = "Translate, cut and paste this to /locales/your-locale/$Application.php";
		if (!file_exists($File)) file_put_contents($File, "<?php // \xEF\xBB\xBF $HelpText\n");
		$Definition = array();
		include $File;
		if (!array_key_exists($Code, $Definition)) {
			// Should be escaped.
			$Code = var_export($Code, True);
			$PhpArrayCode .= "\n\$Definition[".$Code."] = $Code;";
			file_put_contents($File, $PhpArrayCode, FILE_APPEND | LOCK_EX);
		}
	}
		
	public function Gdn_Dispatcher_AfterEnabledApplication_Handler($Sender) {
		$this->_EnabledApplication = ArrayValue('EnabledApplication', $Sender->EventArguments, 'Dashboard');
	}
	
	public function Setup() {
		return True;
	}
	
	private function _EnabledApplication() {
		return $this->_EnabledApplication;
	}
	
}