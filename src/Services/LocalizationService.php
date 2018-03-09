<?php //strict

namespace IO\Services;

use IO\Services\SessionStorageService;
use IO\Services\CountryService;
use IO\Services\WebstoreConfigurationService;
use IO\Services\CheckoutService;
use Plenty\Modules\Frontend\Services\LocaleService;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Data\Contracts\Resources;

class LocalizationService
{

    private $configRepository;

    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    public function getLocalizationData()
    {
        $sessionStorage = pluginApp(SessionStorageService::class);
        $country = pluginApp(CountryService::class);
        $webstoreConfig = pluginApp(WebstoreConfigurationService::class);
        $checkout = pluginApp(CheckoutService::class);

        $lang = $sessionStorage->getLang();
        if (is_null($lang) || !strlen($lang)) {
            $lang = 'de';
        }

        $currentShippingCountryId = $checkout->getShippingCountryId();
        if ($currentShippingCountryId <= 0) {
            $currentShippingCountryId = $webstoreConfig->getDefaultShippingCountryId();
        }

        return [
            'activeShippingCountries' => $country->getActiveCountriesList($lang),
            'activeShopLanguageList' => $webstoreConfig->getActiveLanguageList(),
            'currentShippingCountryId' => $currentShippingCountryId,
            'shopLanguage' => $lang
        ];
    }

    public function setLanguage($newLanguage, $fireEvent = true)
    {
        $localeService = pluginApp(LocaleService::class);
        $localeService->setLanguage($newLanguage, $fireEvent);
    }

    /**
     * @param string $plugin
     * @param string $group
     * @param null|string $lang
     * @return array
     */
    public function getTranslations(string $plugin, string $group, $lang = null)
    {
        // TODO: get fallback language from webstore configuration
        $fallbackLanguageCode = 'en';
        if ($lang === null) {
            /** @var SessionStorageService $sessionStorage */
            $sessionStorage = pluginApp(SessionStorageService::class);
            if ($sessionStorage) {
                $lang = $sessionStorage->getLang();
            } else {
                $lang = $fallbackLanguageCode;
            }
        }
        /** @var Resources $resource */
        $resource = pluginApp(Resources::class);

        // fallback language
        $fallbackData = [];
        if ($fallbackLanguageCode !== $lang) {
            try {
                $fallbackData = $resource->load("$plugin::lang/$fallbackLanguageCode/$group")->getData();
            } catch (\Exception $e) {
                $fallbackData = [];
            }
        }
        // current language
        try {
            $defaultData = $resource->load("$plugin::lang/$lang/$group")->getData();
        } catch (\Exception $e) {
            $defaultData = $fallbackData;
        }

        $defaultData = array_merge($defaultData, $fallbackData);

        // load conf
        $conf = $this->configRepository->get('IO.template');

        /**
         * {
         * "tab"       : "Template",
         * "key"       : "template.template_provider_plugin_name",
         * "label"     : "Namespace of the used provider template plugin",
         * "type"      : "text",
         * "default"   : ""
         * },
         */
        $providerPlugin = $conf['template_provider_plugin_name'];

        /**
         * {
         * "tab": "Template",
         * "key": "template.disable_language_merge",
         * "label": "Disable merging of language file",
         * "type": "checkbox",
         * "default": false
         * },
         */
        $disabled = $conf['disable_language_merge'];


        if ($disabled !== 'true' && $providerPlugin && $plugin === 'Ceres' && $group === 'Template') {
            $overwriteFallbackData = [];
            if ($fallbackLanguageCode !== $lang) {
                try {
                    $overwriteFallbackData = $resource->load("$providerPlugin::lang/$fallbackLanguageCode/Template")->getData();
                } catch (\Exception $e) {
                    $overwriteFallbackData = [];
                }
            }
            try {
                $overwriteData = $resource->load("$providerPlugin::lang/$lang/Template")->getData();
            } catch (\Exception $e) {
                $overwriteData = $overwriteFallbackData;
            }
            return array_merge($defaultData, array_merge($overwriteFallbackData, $overwriteData));
        }
        return $defaultData;
    }
}