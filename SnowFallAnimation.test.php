<?php namespace ProcessWire;

/**
 * WireTests for the SnowFallAnimation module (live environment)
 *
 * These tests run inside a real ProcessWire installation with the real database, hooks and templates.
 * They complement the unit tests in the "tests" folder (which run without ProcessWire).
 *
 * Run from the ProcessWire root directory:
 *
 *   php index.php test SnowFallAnimation
 *
 * Requirements: ProcessWire with the WireTests module installed (core module since 3.0.267).
 *
 * The test changes the module configuration temporarily and restores the original configuration
 * in finish() - also if a test fails.
 */
class WireTest_SnowFallAnimation extends WireTest {

	/** @var SnowFallAnimation */
	protected $module;

	/** original config data as stored in the database (null = not yet backed up) */
	protected $originalConfig = null;

	/** original in-memory values of the module instance */
	protected $originalData = [];
	protected $originalDates = [];

	/** original value of the error flag in the session */
	protected $originalSessionFlag = null;

	/** original $input->get->name value */
	protected $originalInputName = null;

	/** language changed by the test (to be reset in finish()) */
	protected $languageChanged = false;

	/** notices that existed before the test (to remove the ones we create) */
	protected $noticeCount = 0;

	protected $today;

	public function allow() {
		return $this->wire()->modules->isInstalled('SnowFallAnimation');
	}

	public function init() {
		$modules = $this->wire()->modules;
		$this->module = $modules->get('SnowFallAnimation');
		$this->today = new \DateTime('today');

		// back up everything the test changes
		$this->originalConfig = $modules->getConfig('SnowFallAnimation');
		foreach(SnowFallAnimation::getDefaultConfig() as $key => $value) {
			$this->originalData[$key] = $this->module->get($key);
		}
		foreach(['dateStart', 'dateEnd', 'today'] as $property) {
			$value = $this->getProperty($property);
			$this->originalDates[$property] = $value instanceof \DateTime ? clone $value : $value;
		}
		$this->originalSessionFlag = $this->wire()->session->get('snowfalldateserror');
		$this->originalInputName = $this->wire()->input->get('name');
		$this->noticeCount = count($this->wire()->notices);
	}

	public function execute() {
		$this->testInstallation();
		$this->testHooks();
		$this->testSaveConfigValidation();
		$this->testSaveConfigLimits();
		$this->testYearlyRecurrence();
		$this->testFrontendOutput();
		$this->testFrontendOutputSkipped();
		$this->testConfigForm();
		$this->testStatusAlert();
		$this->testOpenFieldset();
		$this->testTranslations();
		$this->testUpgradeFrom100();
		$this->testHttpAccess();
	}

	public function finish() {
		if($this->originalConfig === null || !$this->module) return;
		$modules = $this->wire()->modules;

		// restore the original config WITHOUT the validation hook of the module
		// (the original dates may be in the past, which the validation would reject)
		$configs = $modules->configs;
		if($configs && method_exists($configs, 'saveConfig')) {
			$configs->saveConfig('SnowFallAnimation', $this->originalConfig);
		} else {
			$modules->saveConfig('SnowFallAnimation', $this->originalConfig);
		}
		$this->check('Original config restored', $this->originalConfig, $modules->getConfig('SnowFallAnimation'));

		// restore the in-memory values of the module
		foreach($this->originalData as $key => $value) {
			$this->module->set($key, $value);
		}
		foreach($this->originalDates as $property => $value) {
			$this->setProperty($property, $value);
		}

		// restore session and input
		$session = $this->wire()->session;
		if($this->originalSessionFlag === null) {
			$session->remove('snowfalldateserror');
		} else {
			$session->set('snowfalldateserror', $this->originalSessionFlag);
		}
		$this->wire()->input->get->set('name', $this->originalInputName);
		if($this->languageChanged && $this->wire()->languages) {
			$this->wire()->languages->unsetLanguage();
			$this->languageChanged = false;
		}

		// remove the error notices created by the validation tests
		$notices = $this->wire()->notices;
		$remove = [];
		foreach($notices as $index => $notice) {
			if($index >= $this->noticeCount) $remove[] = $notice;
		}
		foreach($remove as $notice) $notices->remove($notice);
	}

	// ---------------------------------------------------------------------------------------------
	// Tests
	// ---------------------------------------------------------------------------------------------

	/**
	 * Module is installed correctly and all required files exist
	 */
	protected function testInstallation() {
		$modules = $this->wire()->modules;
		$config = $this->wire()->config;

		$this->check('Module instance', true, $this->module instanceof SnowFallAnimation);
		$this->check('Module is autoload', true, (bool) $modules->isAutoload($this->module));
		$this->check('Module is singular', true, (bool) $modules->isSingular($this->module));
		$this->check('Required module LazyCron is installed', true, $modules->isInstalled('LazyCron'));

		$fileVersion = $modules->formatVersion(SnowFallAnimation::getModuleInfo()['version']);
		$installedVersion = $modules->formatVersion($modules->getModuleInfoProperty('SnowFallAnimation', 'version'));
		$this->check('Installed version matches file version (no pending module refresh)', $fileVersion, $installedVersion);

		$path = $config->paths('SnowFallAnimation');
		foreach(['snow.min.js', 'snow.js', 'snowfall.min.css'] as $file) {
			$this->check("File exists: $file", true, is_file($path . $file));
		}
	}

	/**
	 * All four hooks are attached to the module methods
	 */
	protected function testHooks() {
		$modules = $this->wire()->modules;
		$hooks = [
			'Page::render' => [$this->wire()->pages->get('/'), 'render', 'addScript'],
			'Modules::saveConfig' => [$modules, 'saveConfig', 'validateDates'],
			'Inputfield::render' => [$modules->get('InputfieldText'), 'render', 'openFieldset'],
			'LazyCron::everyDay' => [$modules->get('LazyCron'), 'everyDay', 'generateDates'],
		];
		foreach($hooks as $label => list($object, $method, $toMethod)) {
			$found = false;
			foreach($this->wire()->hooks->getHooks($object, $method) as $hook) {
				if($hook['toObject'] instanceof SnowFallAnimation && $hook['toMethod'] === $toMethod) $found = true;
			}
			$this->check("Hook $label -> $toMethod() is attached", true, $found);
		}
	}

	/**
	 * Modules::saveConfig: invalid dates are rejected and the previous dates are kept
	 */
	protected function testSaveConfigValidation() {
		$modules = $this->wire()->modules;
		$session = $this->wire()->session;

		// valid dates are saved
		$start = $this->ts('+10 days');
		$end = $this->ts('+40 days');
		$modules->saveConfig('SnowFallAnimation', $this->config(['input_start' => $start, 'input_end' => $end, 'input_recurrence' => 1]));
		$saved = $modules->getConfig('SnowFallAnimation');
		$this->check('Valid dates are saved (start)', $start, (int) $saved['input_start'], '==');
		$this->check('Valid dates are saved (end)', $end, (int) $saved['input_end'], '==');
		$this->check('Recurrence is saved', 1, (int) $saved['input_recurrence'], '==');

		// start date after end date -> rejected, previous dates are kept
		$session->remove('snowfalldateserror');
		$notices = count($this->wire()->notices);
		$modules->saveConfig('SnowFallAnimation', $this->config(['input_start' => $this->ts('+50 days'), 'input_end' => $this->ts('+20 days'), 'input_count' => 77]));
		$saved = $modules->getConfig('SnowFallAnimation');
		$this->check('Start after end: start date is kept', $start, (int) $saved['input_start'], '==');
		$this->check('Start after end: end date is kept', $end, (int) $saved['input_end'], '==');
		$this->check('Start after end: other values are still saved', 77, (int) $saved['input_count'], '==');
		$this->check('Start after end: error notice is shown', $notices, count($this->wire()->notices), '<');
		$this->check('Start after end: error message', $this->module->_('The end date must be after the start date.'), $this->lastNoticeText(), '*=');
		$this->check('Start after end: session flag for the fieldset is set', 1, (int) $session->get('snowfalldateserror'), '==');

		// end date in the past -> rejected
		$modules->saveConfig('SnowFallAnimation', $this->config(['input_start' => $this->ts('-20 days'), 'input_end' => $this->ts('-1 day')]));
		$saved = $modules->getConfig('SnowFallAnimation');
		$this->check('End date in the past: end date is kept', $end, (int) $saved['input_end'], '==');
		$this->check('End date in the past: error message', $this->module->_('The end date must be in the future not in the past.'), $this->lastNoticeText(), '*=');

		// more than 1 year with recurrence -> rejected
		$modules->saveConfig('SnowFallAnimation', $this->config(['input_start' => $this->ts('+1 day'), 'input_end' => $this->ts('+400 days'), 'input_recurrence' => 1]));
		$saved = $modules->getConfig('SnowFallAnimation');
		$this->check('Range > 1 year with recurrence: end date is kept', $end, (int) $saved['input_end'], '==');

		// recurrence without both dates -> recurrence is removed
		$modules->saveConfig('SnowFallAnimation', $this->config(['input_start' => $this->ts('+1 day'), 'input_end' => '', 'input_recurrence' => 1]));
		$saved = $modules->getConfig('SnowFallAnimation');
		$this->check('Recurrence without end date is removed', 0, (int) $saved['input_recurrence'], '==');
	}

	/**
	 * Modules::saveConfig: limits are applied and min/max are swapped
	 */
	protected function testSaveConfigLimits() {
		$modules = $this->wire()->modules;

		$modules->saveConfig('SnowFallAnimation', $this->config([
			'input_count' => 999999,
			'input_minRadius' => 50,
			'input_maxRadius' => 0.01,
			'input_minSpeed' => 500,
			'input_maxSpeed' => 0,
		]));
		$saved = $modules->getConfig('SnowFallAnimation');
		$this->check('Density is limited', SnowFallAnimation::MAX_DENSITY, (int) $saved['input_count'], '==');
		$this->check('Size min/max swapped and limited (min)', SnowFallAnimation::MIN_SIZE, (float) $saved['input_minRadius'], '==');
		$this->check('Size min/max swapped and limited (max)', SnowFallAnimation::MAX_SIZE, (float) $saved['input_maxRadius'], '==');
		$this->check('Duration min/max swapped and limited (min)', SnowFallAnimation::MIN_DURATION, (int) $saved['input_minSpeed'], '==');
		$this->check('Duration min/max swapped and limited (max)', SnowFallAnimation::MAX_DURATION, (int) $saved['input_maxSpeed'], '==');

		// the values are also stored like this in the database
		$data = $this->configFromDatabase();
		$this->check('Limited density is stored in the database', SnowFallAnimation::MAX_DENSITY, (int) ($data['input_count'] ?? 0), '==');
	}

	/**
	 * LazyCron::everyDay: dates are moved to the next year and stored in the database
	 */
	protected function testYearlyRecurrence() {
		$modules = $this->wire()->modules;

		// end date yesterday -> dates must be moved by one year
		$start = new \DateTime('today -30 days');
		$end = new \DateTime('today -1 day');
		$modules->saveConfig('SnowFallAnimation', $this->config(['input_recurrence' => 1]));
		$this->module->set('input_recurrence', 1);
		$this->setProperty('dateStart', clone $start);
		$this->setProperty('dateEnd', clone $end);
		$this->setProperty('today', new \DateTime('today'));

		$this->callMethod('generateDates', $this->wire(new HookEvent()));

		$data = $this->configFromDatabase();
		$expectStart = (clone $start)->modify('+1 year')->format('Y-m-d');
		$expectEnd = (clone $end)->modify('+1 year')->format('Y-m-d');
		$this->check('Recurrence: new start date stored in the database', $expectStart, date('Y-m-d', (int) ($data['input_start'] ?? 0)));
		$this->check('Recurrence: new end date stored in the database', $expectEnd, date('Y-m-d', (int) ($data['input_end'] ?? 0)));
		$this->check('Recurrence: module uses the new end date', $expectEnd, $this->getProperty('dateEnd')->format('Y-m-d'));

		// end date 3 years ago (e.g. site was offline) -> dates must be moved by 3 years
		$start = new \DateTime('today -3 years -30 days');
		$end = new \DateTime('today -3 years +5 days');
		$this->setProperty('dateStart', clone $start);
		$this->setProperty('dateEnd', clone $end);
		$this->callMethod('generateDates', $this->wire(new HookEvent()));
		$data = $this->configFromDatabase();
		$this->check('Recurrence after 3 years: start date moved by 3 years', (clone $start)->modify('+3 years')->format('Y-m-d'), date('Y-m-d', (int) ($data['input_start'] ?? 0)));
		$this->check('Recurrence after 3 years: end date moved by 3 years', (clone $end)->modify('+3 years')->format('Y-m-d'), date('Y-m-d', (int) ($data['input_end'] ?? 0)));

		// end date in the future -> nothing happens
		$before = $this->configFromDatabase();
		$this->callMethod('generateDates', $this->wire(new HookEvent()));
		$this->check('Recurrence: nothing changes while the end date is in the future', $before, $this->configFromDatabase());
	}

	/**
	 * Page::render: script and stylesheet are added to a real page
	 */
	protected function testFrontendOutput() {
		$home = $this->wire()->pages->get('/');
		$this->setVisibility('1');

		try {
			$html = $home->render();
		} catch(\Throwable $e) {
			$this->li('Frontend: home page could not be rendered in CLI (' . $e->getMessage() . ') - using test markup');
			$html = $this->renderWithHook('<html><head></head><body><p>Test</p></body></html>', $home);
		}

		if(stripos($html, '</body>') === false) {
			$this->li('Frontend: home page has no </body> tag - using test markup');
			$html = $this->renderWithHook('<html><head></head><body><p>Test</p></body></html>', $home);
		}

		$url = $this->wire()->config->urls('SnowFallAnimation');
		$this->check('Frontend: script is added', $url . 'snow.min.js?v=', $html, '*=');
		$this->check('Frontend: stylesheet is added', $url . 'snowfall.min.css?v=', $html, '*=');
		// the script must be inside the body and before the closing body tag
		// (other modules may add their own markup between the script and </body>)
		$scriptPos = strpos($html, 'snow.min.js');
		$bodyOpenPos = stripos($html, '<body');
		$bodyClosePos = strripos($html, '</body>');
		$this->check('Frontend: script is inside <body>', true, $scriptPos !== false && $bodyOpenPos !== false && $scriptPos > $bodyOpenPos);
		$this->check('Frontend: script is placed before </body>', true, $scriptPos !== false && $bodyClosePos !== false && $scriptPos < $bodyClosePos);
		if($scriptPos !== false && $bodyClosePos !== false && $scriptPos < $bodyClosePos) {
			$scriptEnd = strpos($html, '</script>', $scriptPos);
			$between = $scriptEnd === false ? '' : trim(substr($html, $scriptEnd + 9, $bodyClosePos - $scriptEnd - 9));
			if($between !== '') {
				$this->li('Frontend: other markup between the snowfall script and </body> (added by other modules): '
					. mb_substr(preg_replace('/\s+/', ' ', $between), 0, 200));
			}
		}
		$this->check('Frontend: exactly one snowfall script', 1, substr_count($html, 'snow.min.js'));
		$this->check('Frontend: no inline config script (CSP)', false, strpos($html, 'window.SnowThemeConfig') !== false);

		// data-config contains the sanitized config
		$config = null;
		if(preg_match('#snow\.min\.js[^"]*" data-config="([^"]*)"#', $html, $match)) {
			$config = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);
		}
		$this->check('Frontend: data-config is valid JSON', true, is_array($config));
		$this->check('Frontend: data-config matches the module config', $this->callMethod('getJsConfig'), $config);

		// the URLs point to existing files
		preg_match_all('#(?:src|href)="(' . preg_quote($url, '#') . '[^"?]+)#', $html, $matches);
		$root = rtrim($this->wire()->config->paths->root, '/');
		$rootUrl = rtrim($this->wire()->config->urls->root, '/');
		foreach(array_unique($matches[1]) as $assetUrl) {
			$file = $root . substr($assetUrl, strlen($rootUrl));
			$this->check("Frontend: asset exists for $assetUrl", true, is_file($file));
		}
	}

	/**
	 * Page::render: nothing is added when the snowfall is off, on admin pages or outside the date range
	 */
	protected function testFrontendOutputSkipped() {
		$pages = $this->wire()->pages;
		$html = '<html><head></head><body><p>Test</p></body></html>';

		$this->setVisibility('0');
		$this->check('Snowfall off: nothing is added', $html, $this->renderWithHook($html, $pages->get('/')));

		$this->setVisibility('1');
		$admin = $pages->get($this->wire()->config->adminRootPageID);
		$this->check('Admin page: nothing is added', $html, $this->renderWithHook($html, $admin));

		$this->setVisibility('2', '+10 days', '+20 days');
		$this->check('Before the start date: nothing is added', $html, $this->renderWithHook($html, $pages->get('/')));

		$this->setVisibility('2', '-10 days', '+20 days');
		$this->check('Inside the date range: script is added', 'snow.min.js', $this->renderWithHook($html, $pages->get('/')), '*=');
	}

	/**
	 * Module config form in the real admin environment
	 */
	protected function testConfigForm() {
		$form = $this->wire()->modules->getModuleConfigInputfields('SnowFallAnimation');
		$this->check('Config form is available', true, $form instanceof InputfieldWrapper);
		if(!$form instanceof InputfieldWrapper) return;

		$names = ['input_visibility', 'input_start', 'input_end', 'input_recurrence', 'input_count', 'input_minRadius',
			'input_maxRadius', 'input_minSpeed', 'input_maxSpeed', 'input_text', 'input_color', 'input_zIndex'];
		foreach($names as $name) {
			$this->check("Config form: field $name exists", true, $form->getChildByName($name) instanceof Inputfield);
		}
		$this->check('Config form: fieldset1 exists', true, $form->getChildByName('fieldset1') instanceof InputfieldFieldset);
		$this->check('Config form: density has max attribute', (string) SnowFallAnimation::MAX_DENSITY, (string) $form->getChildByName('input_count')->attr('max'));

		// the form can be rendered without errors
		$markup = $form->render();
		$this->check('Config form renders', 'input_zIndex', $markup, '*=');
	}

	/**
	 * Status message as UIkit alert box in the three states
	 */
	protected function testStatusAlert() {
		$states = [
			'uk-alert-success' => ['1'],
			'uk-alert-warning' => ['2', '+10 days', '+20 days'],
			'uk-alert-danger' => ['0'],
		];
		foreach($states as $class => $args) {
			$this->setVisibility(...$args);
			$wrapper = $this->wire(new InputfieldWrapper());
			$this->module->getModuleConfigInputfields($wrapper);
			$markup = $wrapper->children()->first();
			$html = $markup ? $markup->render() : '';
			$this->check("Status alert: $class", $class, $html, '*=');
		}
	}

	/**
	 * Inputfield::render hook opens the fieldset after an error
	 */
	protected function testOpenFieldset() {
		$session = $this->wire()->session;
		$this->wire()->input->get->set('name', 'SnowFallAnimation');

		$fieldset = $this->wire()->modules->get('InputfieldFieldset');
		$fieldset->attr('name', 'fieldset1');
		$fieldset->collapsed = Inputfield::collapsedYes;

		$session->set('snowfalldateserror', 1);
		$fieldset->render();
		$this->check('Fieldset is opened after an error', Inputfield::collapsedNo, (int) $fieldset->collapsed, '==');
		$this->check('Error flag is removed after opening', null, $session->get('snowfalldateserror'));

		$other = $this->wire()->modules->get('InputfieldFieldset');
		$other->attr('name', 'fieldset2');
		$other->collapsed = Inputfield::collapsedYes;
		$session->set('snowfalldateserror', 1);
		$other->render();
		$this->check('Other fieldsets stay closed', Inputfield::collapsedYes, (int) $other->collapsed, '==');
	}

	/**
	 * Translations: every text of the module is translated, and the status message and the date format
	 * are shown in the language of the user (only for languages with a translation file for this module)
	 */
	protected function testTranslations() {
		$modules = $this->wire()->modules;
		$languages = $this->wire()->languages;
		if(!$modules->isInstalled('LanguageSupport') || !$languages) {
			$this->li('Translations: skipped - LanguageSupport is not installed');
			return;
		}

		$file = $this->wire()->config->paths('SnowFallAnimation') . 'SnowFallAnimation.module';
		$texts = $this->getTranslatableTexts($file);
		$this->check('Translations: translatable texts found in the module', 0, count($texts), '<');

		$tested = 0;
		foreach($languages as $language) {
			if($language->isDefault()) continue;
			$name = $language->name;
			$translator = $language->translator();
			$textdomain = $translator->filenameToTextdomain($file);
			if(!$translator->textdomainFileExists($textdomain)) {
				$this->li("Translations: language '$name' has no translation for this module - skipped");
				continue;
			}
			$tested++;

			// every text has a translation
			$missing = [];
			foreach($texts as $text) {
				$translation = $translator->getTranslationOrFalse($textdomain, $text);
				if($translation === false || $translation === '') $missing[] = $text;
			}
			$this->check("Translations ($name): all " . count($texts) . ' texts are translated', [], $missing);

			// no outdated translations (texts that do not exist in the module anymore)
			$hashes = [];
			// same hash as LanguageTranslator::getTextHash() (protected)
			foreach($texts as $text) $hashes[md5(str_replace('\\n', "\n", $text))] = true;
			$outdated = array_values(array_diff_key($translator->getTranslations($textdomain), $hashes));
			$this->check("Translations ($name): no outdated translations", 0, count($outdated));

			// status message and date format in the language of the user
			$languages->setLanguage($language);
			$this->languageChanged = true;
			try {
				$format = $translator->getTranslation($textdomain, 'Y-m-d');
				$this->check("Translations ($name): date format", $format, $this->module->_('Y-m-d'));

				$this->setVisibility('2', '-5 days', '+10 days');
				$end = new \DateTime('today +10 days');
				$expected = sprintf($translator->getTranslation($textdomain, 'At the moment the snowfall is enabled. It will be disabled on %s.'), $end->format($format));
				$this->check("Translations ($name): status message with translated text and date format", htmlspecialchars($expected, ENT_QUOTES, 'UTF-8'), $this->renderStatus(), '*=');

				$form = $this->wire()->modules->getModuleConfigInputfields('SnowFallAnimation');
				$field = $form ? $form->getChildByName('input_end') : null;
				$this->check("Translations ($name): date picker uses the translated date format", $format, $field ? (string) $field->dateInputFormat : '');
			} finally {
				$languages->unsetLanguage();
				$this->languageChanged = false;
			}
		}
		if(!$tested) $this->li('Translations: no language with a translation file for this module');
	}

	/**
	 * Upgrade from version 1.0.0: the config saved by 1.0.0 (old defaults, no z-index, dates of the last season)
	 * must work without errors, and the new limits and the recurrence must be applied
	 */
	protected function testUpgradeFrom100() {
		$modules = $this->wire()->modules;
		$start = new \DateTime('today -300 days');
		$end = new \DateTime('today -270 days');

		// config as stored by version 1.0.0 (the z-index field was missing in the form, so there is no input_zIndex)
		$old = [
			'input_count' => 500,
			'input_minRadius' => 0.8,
			'input_maxRadius' => 1.5,
			'input_minSpeed' => 1,
			'input_maxSpeed' => 3,
			'input_text' => '❄',
			'input_color' => '#99ccff',
			'input_visibility' => '2',
			'input_start' => $start->getTimestamp(),
			'input_end' => $end->getTimestamp(),
			'input_recurrence' => 1,
		];
		$configs = $modules->configs;
		if($configs && method_exists($configs, 'saveConfig')) {
			$configs->saveConfig('SnowFallAnimation', $old); // as it is in the database (without validation)
		} else {
			$modules->saveConfig('SnowFallAnimation', $old);
		}

		// load the old config into the module like ProcessWire does (missing keys keep their defaults)
		foreach(SnowFallAnimation::getDefaultConfig() as $key => $value) {
			$this->module->set($key, array_key_exists($key, $old) ? $old[$key] : $value);
		}
		$this->setProperty('today', new \DateTime('today'));
		$this->setProperty('dateStart', $this->callMethod('toDate', $old['input_start']));
		$this->setProperty('dateEnd', $this->callMethod('toDate', $old['input_end']));

		// the JavaScript config works with the old values
		$js = $this->callMethod('getJsConfig');
		$this->check('Upgrade 1.0.0: density 500 is kept (below the limit)', 500, $js['density']);
		$this->check('Upgrade 1.0.0: old fall duration 1-3 s is kept', [1, 3], [$js['minDuration'], $js['maxDuration']]);
		$this->check('Upgrade 1.0.0: missing z-index uses the default', 1000, $js['zIndex']);

		// the season is over -> no snowfall
		$html = '<html><head></head><body><p>Test</p></body></html>';
		$this->check('Upgrade 1.0.0: no snowfall after the end date of last season', $html, $this->renderWithHook($html, $this->wire()->pages->get('/')));

		// config form works with the old config
		$form = $modules->getModuleConfigInputfields('SnowFallAnimation');
		$zIndex = $form ? $form->getChildByName('input_zIndex') : null;
		$this->check('Upgrade 1.0.0: config form shows the default z-index', '1000', $zIndex ? (string) $zIndex->attr('value') : '');

		// LazyCron moves the dates of last season to this season, the other old values are kept
		$this->callMethod('generateDates', $this->wire(new HookEvent()));
		$data = $this->configFromDatabase();
		$this->check('Upgrade 1.0.0: start date moved to this season', (clone $start)->modify('+1 year')->format('Y-m-d'), date('Y-m-d', (int) ($data['input_start'] ?? 0)));
		$this->check('Upgrade 1.0.0: end date moved to this season', (clone $end)->modify('+1 year')->format('Y-m-d'), date('Y-m-d', (int) ($data['input_end'] ?? 0)));
		$this->check('Upgrade 1.0.0: other old values are kept', 500, (int) ($data['input_count'] ?? 0), '==');

		// saving the form once (as the admin does after the upgrade) works and stores the z-index
		$modules->saveConfig('SnowFallAnimation', array_merge($data, ['input_zIndex' => '1000']));
		$saved = $modules->getConfig('SnowFallAnimation');
		$this->check('Upgrade 1.0.0: first save after the upgrade stores the z-index', '1000', (string) ($saved['input_zIndex'] ?? ''));
		$this->check('Upgrade 1.0.0: first save keeps the moved dates', (int) $data['input_end'], (int) ($saved['input_end'] ?? 0), '==');

		// values stored as strings (e.g. by older ProcessWire versions) are converted
		$this->module->set('input_count', '500');
		$this->module->set('input_minRadius', '0.8');
		$js = $this->callMethod('getJsConfig');
		$this->check('Upgrade 1.0.0: numeric strings are converted', [500, 0.8], [$js['density'], $js['minSize']]);
	}

	/**
	 * Web server access rules (only if the site is reachable via HTTP from here)
	 */
	protected function testHttpAccess() {
		$config = $this->wire()->config;
		$host = $config->httpHost ?: (is_array($config->httpHosts) ? (string) reset($config->httpHosts) : '');
		if(!$host) {
			$this->li('HTTP checks skipped: no httpHost configured');
			return;
		}

		$http = $this->wire(new WireHttp());
		$http->setTimeout(5);
		$base = ($config->https ? 'https://' : 'http://') . $host . $config->urls('SnowFallAnimation');

		$status = (int) $http->status($base . 'snow.min.js');
		if(!$status) {
			$this->li("HTTP checks skipped: $base is not reachable from here");
			return;
		}
		$this->check('HTTP: snow.min.js is accessible', 200, $status);

		$blocked = [
			'SnowFallAnimation.module',
			'SnowFallAnimation.test.php',
			'tests/composer.json',
			'tests/bootstrap.php',
		];
		foreach($blocked as $file) {
			$code = (int) $http->status($base . $file);
			if($code === 404) {
				$this->li("HTTP: $file does not exist on this server (404)");
				continue;
			}
			$this->check("HTTP: $file is not accessible", 403, $code);
		}
	}

	// ---------------------------------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------------------------------

	/**
	 * Complete config array based on the defaults with some overrides
	 */
	protected function config(array $overrides = []) {
		return array_merge(SnowFallAnimation::getDefaultConfig(), $overrides);
	}

	/**
	 * Read the config directly from the modules table (not from the in-memory cache)
	 */
	protected function configFromDatabase() {
		$database = $this->wire()->database;
		$query = $database->prepare('SELECT data FROM modules WHERE class=:class');
		$query->bindValue(':class', 'SnowFallAnimation');
		$query->execute();
		$json = (string) $query->fetchColumn();
		$query->closeCursor();
		$data = $json ? json_decode($json, true) : [];
		return is_array($data) ? $data : [];
	}

	/**
	 * Set visibility (and optionally the date range) on the module instance
	 */
	protected function setVisibility($visibility, $start = '', $end = '') {
		$this->module->set('input_visibility', $visibility);
		$this->setProperty('today', new \DateTime('today'));
		$this->setProperty('dateStart', $start ? new \DateTime("today $start") : null);
		$this->setProperty('dateEnd', $end ? new \DateTime("today $end") : null);
	}

	/**
	 * Run the Page::render hook method of the module with the given markup
	 */
	protected function renderWithHook($html, Page $page) {
		$event = $this->wire(new HookEvent(['object' => $page, 'method' => 'render', 'return' => $html]));
		$this->callMethod('addScript', $event);
		return $event->return;
	}

	/**
	 * Get all texts of the module that are translatable with $this->_('...')
	 */
	protected function getTranslatableTexts($file) {
		$texts = [];
		if(preg_match_all('/\$this->_\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\)/', (string) file_get_contents($file), $matches)) {
			foreach($matches[1] as $text) $texts[] = stripcslashes($text);
		}
		return array_values(array_unique($texts));
	}

	/**
	 * Render the status message of the config form
	 */
	protected function renderStatus() {
		$wrapper = $this->wire(new InputfieldWrapper());
		$this->module->getModuleConfigInputfields($wrapper);
		$markup = $wrapper->children()->first();
		return $markup ? $markup->render() : '';
	}

	protected function lastNoticeText() {
		$text = '';
		foreach($this->wire()->notices as $notice) $text = (string) $notice->text;
		return $text;
	}

	protected function ts($modify) {
		return (new \DateTime("today $modify"))->getTimestamp();
	}

	protected function getProperty($name) {
		$property = new \ReflectionProperty($this->module, $name);
		if(PHP_VERSION_ID < 80100) $property->setAccessible(true);
		return $property->getValue($this->module);
	}

	protected function setProperty($name, $value) {
		$property = new \ReflectionProperty($this->module, $name);
		if(PHP_VERSION_ID < 80100) $property->setAccessible(true);
		$property->setValue($this->module, $value);
	}

	protected function callMethod($name, ...$args) {
		$method = new \ReflectionMethod($this->module, $name);
		if(PHP_VERSION_ID < 80100) $method->setAccessible(true);
		return $method->invoke($this->module, ...$args);
	}
}
