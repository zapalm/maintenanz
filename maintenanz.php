<?php
/**
 * Tool for maintenance & debug: module for PrestaShop.
 *
 * @author    Maksim T. <zapalm@yandex.com>
 * @copyright 2014 Maksim T.
 * @link      https://prestashop.modulez.ru/en/administrative-features/24-tool-for-maintenance-debug.html
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (false === defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'maintenanz/autoload.inc.php';

/**
 * Module Maintenanz.
 *
 * @author Maksim T. <zapalm@yandex.com>
 */
class Maintenanz extends Module
{
    /** The product ID of the module on its homepage. */
    const HOMEPAGE_PRODUCT_ID = 24;

    /** @var array Default settings. */
    private $confDefault = [
        'MAINTENANZ_MSG'   => ['value' => '',  'lang' => true],
        'MAINTENANZ_CONT'  => ['value' => '',  'lang' => true],
        'MAINTENANZ_SHOP'  => ['value' => '0', 'lang' => false],
        'MAINTENANZ_DEBUG' => ['value' => '',  'lang' => false],
        'MAINTENANZ_MODE'  => ['value' => '1', 'lang' => false],
    ];

    /**
     * @inheritdoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function __construct()
    {
        $this->name          = 'maintenanz';
        $this->tab           = 'administration';
        $this->version       = '1.4.0';
        $this->author        = 'zapalm';
        $this->need_instance = false;
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = $this->l('Maintenance tool');
        $this->description = $this->l('Allows to add maintenance messages, manage the debug mode, diagnose a software, check for vulnerabilities and other.');
    }

    /**
     * @inheritdoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function install()
    {
        $result = parent::install();

        if ($result) {
            foreach ($this->confDefault as $confName => $definition) {
                if (false === $definition['lang']) {
                    Configuration::updateValue($confName, $definition['value']);
                }
            }

            $result = $this->registerHook('top')
                && $this->registerHook('header')
                && $this->registerHook('displayMaintenance')
            ;
        }

        (new \zapalm\prestashopHelpers\components\qualityService\QualityServiceClient(self::HOMEPAGE_PRODUCT_ID))
            ->installModule($this)
        ;

        return $result;
    }

    /**
     * @inheritdoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function uninstall()
    {
        $result = (bool)parent::uninstall();

        if ($result) {
            foreach (array_keys($this->confDefault) as $confName) {
                Configuration::deleteByName($confName);
            }
        }

        (new \zapalm\prestashopHelpers\components\qualityService\QualityServiceClient(self::HOMEPAGE_PRODUCT_ID))
            ->uninstallModule($this)
        ;

        return $result;
    }

    /**
     * Assign common variables.
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    private function assignCommonVariables()
    {
        $languageId = (int)$this->context->language->id;

        $variables                    = Configuration::getMultiple(['MAINTENANZ_MSG', 'MAINTENANZ_CONT'], $languageId);
        $variables['MAINTENANZ_SHOP'] = (Configuration::get('MAINTENANZ_SHOP') ? $this->context->shop->name : '');
        $variables['MAINTENANZ_MODE'] = (bool)Configuration::get('MAINTENANZ_MODE');
        $variables['IS_SHOP_ENABLED'] = (bool)Configuration::get('PS_SHOP_ENABLE');
        $variables['img_uri']         = $this->_path . 'views/img/';
        $variables['link']            = $this->context->link;

        $this->context->smarty->assign($variables);
    }

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function hookHeader($params)
    {
        $this->context->controller->addCSS($this->_path . 'views/css/main.css');
    }

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function hookTop($params)
    {
        $this->assignCommonVariables();

        return $this->display(__FILE__, 'top.tpl');
    }

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function hookDisplayMaintenance($params)
    {
        $this->assignCommonVariables();

        return $this->display(__FILE__, 'maintenance.tpl');
    }

    /**
     * @inheritDoc
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function getContent()
    {
        $output         = '';
        $errors         = [];
        $submitConfig   = (bool)Tools::getValue('submitConfig');
        $submitDebug    = (bool)Tools::getValue('submitDebug');
        $result         = 1;
        $languagesIds   = \zapalm\prestashopHelpers\helpers\ArrayHelper::getColumn($this->context->controller->getLanguages(), 'id_lang');

        // Change the mode to debug or to normal
        if ($submitDebug && (bool)Tools::getValue('MAINTENANZ_DEBUG') !== (bool)_PS_MODE_DEV_) {
            $newMode         = (Tools::getValue('MAINTENANZ_DEBUG') ? 'true' : 'false');
            $configFilePath  = dirname(__FILE__) . '/../../config/defines.inc.php';
            $currentSettings = file_get_contents($configFilePath);

            $newSettings  = preg_replace(
                '/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui',
                'define(\'_PS_MODE_DEV_\', ' . $newMode . ');',
                $currentSettings,
                -1,
                $replacementsCount
            );

            if (null !== $newSettings && 1 === $replacementsCount && false !== file_put_contents($configFilePath, $newSettings)) {
                Configuration::updateValue('MAINTENANZ_DEBUG', ('true' === $newMode ? 1 : 0));

                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($configFilePath);
                }

                $result &= 1;
            } else {
                $errors[] = $this->l('The "defines.inc.php" file cannot be overwritten.');
                $result &= 0;
            }
        } else {
            Configuration::updateValue('MAINTENANZ_DEBUG', (int)_PS_MODE_DEV_);
        }

        // Change other settings
        if ($submitConfig) {
            foreach ($this->confDefault as $confName => $definition) {
                if ($definition['lang']) {
                    foreach ($languagesIds as $languageId) {
                        $value   = (string)Tools::getValue($confName . '_' . $languageId);
                        $result &= Configuration::updateValue($confName, [$languageId => $value]);
                    }
                } else {
                    $result &= Configuration::updateValue($confName, (string)Tools::getValue($confName));
                }
            }
        }

        $diagnosisReport = Configuration::get('MAINTENANZ_DIAGNOSIS_REPORT');
        if (false === $diagnosisReport || Tools::isSubmit('submitRefreshDiagnosisReport')) {
            $psDiag = new \zapalm\PsDiag();
            $report = $psDiag->diagnose();

            ob_start();
            $psDiag->printReport($report);
            $diagnosisReport = \zapalm\prestashopHelpers\helpers\FormHelper::encode(strip_tags(ob_get_clean()));
            Configuration::updateValue('MAINTENANZ_DIAGNOSIS_REPORT', $diagnosisReport);
        }

        $securityReport = Configuration::get('MAINTENANZ_SECURITY_REPORT');
        if (false === $securityReport || Tools::isSubmit('submitRefreshSecurityReport')) {
            $securityChecker = new \zapalm\PrestaShopSecurityVulnerabilityChecker();
            $report = $securityChecker->check();

            ob_start();
            $securityChecker->printReport($report);
            $securityReport = \zapalm\prestashopHelpers\helpers\FormHelper::encode(strip_tags(ob_get_clean()));
            Configuration::updateValue('MAINTENANZ_SECURITY_REPORT', $securityReport);
        }

        $result = (bool)$result;
        if ($submitConfig || $submitDebug) {
            if (false === $result) {
                $errors[] = $this->l('Some setting not updated');
            }

            $output .= ([] !== $errors
                ? $this->displayError(implode('<br/>', $errors))
                : $this->displayConfirmation($this->l('Settings updated'))
            );
        }

        return $output . $this->displayForm();
    }

    /**
     * Renders the settings form.
     *
     * @return string
     *
     * @author Maksim T. <zapalm@yandex.com>
     */
    protected function displayForm()
    {
        $token          = Tools::getAdminTokenLite('AdminModules');
        $currentIndex   = AdminController::$currentIndex;
        $url            = $currentIndex . '&configure=' . $this->name;
        $languages      = $this->context->controller->getLanguages(); // The getter with properties initialization for a form

        $form = new HelperForm();
        $form->identifier               = $this->identifier;
        $form->module                   = $this;
        $form->name_controller          = $this->name;
        $form->token                    = $token;
        $form->currentIndex             = $url;
        $form->title                    = $this->displayName;
        $form->show_toolbar             = false;
        $form->submit_action            = 'submit_' . $this->name;
        $form->languages                = $languages;
        $form->default_form_language    = $this->context->controller->default_form_language;
        $form->allow_employee_form_lang = $this->context->controller->allow_employee_form_lang;

        $formFields = [];

        $formFields[]['form'] = [
            'legend' => [
                'title' => $this->l('Notifying settings'),
                'icon'  => 'icon-cogs',
            ],
            'input'  => [
                [
                    'type'  => 'text',
                    'label' => $this->l('Displaying message'),
                    'desc'  => implode(' ', [
                        $this->l('For example: '),
                        '<u>',
                        $this->l('The site is under maintenance.'),
                        $this->l('Do not forget to enable the site for all users!'),
                        '</u>',
                    ]),
                    'name'  => 'MAINTENANZ_MSG',
                    'size'  => 135,
                    'lang'  => true,
                ],
                [
                    'type'  => 'text',
                    'label' => $this->l('Contact message'),
                    'desc'  => implode(' ', [
                        $this->l('For example: '),
                        '<u>',
                        $this->l('Contact us if you have an urgent question.'),
                        '</u>',
                    ]),
                    'name'  => 'MAINTENANZ_CONT',
                    'size'  => 135,
                    'lang'  => true,
                ],
                array_merge(
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Display messages only when the maintenance mode is active'),
                        'desc'   => $this->l('Choose NO to display these messages regardless of whether the maintenance mode is enabled or not.'),
                        'name'   => 'MAINTENANZ_MODE',
                        'values' => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    (version_compare(_PS_VERSION_, '1.6', '>=') ? [] : [
                        'type'    => 'radio',
                        'is_bool' => true,
                        'class'   => 't',
                    ])
                ),
                array_merge(
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Display shop name'),
                        'name'   => 'MAINTENANZ_SHOP',
                        'values' => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    (version_compare(_PS_VERSION_, '1.6', '>=') ? [] : [
                        'type'    => 'radio',
                        'is_bool' => true,
                        'class'   => 't',
                    ])
                ),
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'button btn btn-default',
                'name'  => 'submitConfig',
            ],
        ];

        $formFields[]['form'] = [
            'legend' => [
                'title' => $this->l('Debug settings'),
                'icon'  => 'icon-cogs',
            ],
            'input'  => [
                array_merge(
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Enable the debug mode of PrestaShop'),
                        'name'   => 'MAINTENANZ_DEBUG',
                        'values' => [
                            [
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    (version_compare(_PS_VERSION_, '1.6', '>=') ? [] : [
                        'type'    => 'radio',
                        'is_bool' => true,
                        'class'   => 't',
                    ])
                ),
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'button btn btn-default',
                'name'  => 'submitDebug',
            ]
        ];

        $formFields[]['form'] = [
            'legend' => [
                'title' => $this->l('Software diagnosis'),
                'icon'  => 'icon-info',
            ],
            'description' => '<pre>' . \zapalm\prestashopHelpers\helpers\FormHelper::decode(Configuration::get('MAINTENANZ_DIAGNOSIS_REPORT')) . '</pre>',
            'submit' => [
                'title' => $this->l('Refresh'),
                'class' => 'button btn btn-default',
                'icon'  => 'process-icon-refresh',
                'name'  => 'submitRefreshDiagnosisReport',
            ]
        ];

        $formFields[]['form'] = [
            'legend' => [
                'title' => $this->l('Security check'),
                'icon'  => 'icon-info',
            ],
            'description' => '<pre>' . \zapalm\prestashopHelpers\helpers\FormHelper::decode(Configuration::get('MAINTENANZ_SECURITY_REPORT')) . '</pre>',
            'submit' => [
                'title' => $this->l('Refresh'),
                'class' => 'button btn btn-default',
                'icon'  => 'process-icon-refresh',
                'name'  => 'submitRefreshSecurityReport',
            ]
        ];

        $languagesIds = \zapalm\prestashopHelpers\helpers\ArrayHelper::getColumn($languages, 'id_lang');
        foreach ($this->confDefault as $confName => $definition) {
            if ($definition['lang']) {
                foreach ($languagesIds as $languageId) {
                    $form->fields_value[$confName][$languageId] = (string)Configuration::get($confName, $languageId);
                }
            } else {
                $form->fields_value[$confName] = (string)Configuration::get($confName);
            }
        }

        $output = $form->generateForm($formFields);

        $output .= (new \zapalm\prestashopHelpers\widgets\AboutModuleWidget($this))
            ->setProductId(self::HOMEPAGE_PRODUCT_ID)
            ->setLicenseTitle(\zapalm\prestashopHelpers\widgets\AboutModuleWidget::LICENSE_AFL30)
        ;

        return $output;
    }
}