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
        $country        = pluginApp(CountryService::class);
        $webstoreConfig = pluginApp(WebstoreConfigurationService::class);
        $checkout       = pluginApp(CheckoutService::class);

        $lang = $sessionStorage->getLang();
        if(is_null($lang) || !strlen($lang))
        {
            $lang = 'de';
        }

        $currentShippingCountryId = $checkout->getShippingCountryId();
        if($currentShippingCountryId <= 0)
        {
            $currentShippingCountryId = $webstoreConfig->getDefaultShippingCountryId();
        }

        return [
            'activeShippingCountries'  => $country->getActiveCountriesList($lang),
            'activeShopLanguageList'   => $webstoreConfig->getActiveLanguageList(),
            'currentShippingCountryId' => $currentShippingCountryId,
            'shopLanguage'             => $lang
        ];
    }

    public function setLanguage($newLanguage, $fireEvent = true)
    {
        $localeService = pluginApp(LocaleService::class);
        $localeService->setLanguage($newLanguage, $fireEvent);
    }

    public function getTranslations( string $plugin, string $group, $lang = null )
    {
        if ( $lang === null )
        {
            $lang = pluginApp(SessionStorageService::class)->getLang();
        }

        /** @var Resources $resource */
        $resource = pluginApp( Resources::class );
        $res = $resource->load( "$plugin::lang/$lang/$group" )->getData();
        $conf = $this->configRepository->get('IO.template');
        $providerPlugin = $conf['template_provider_plugin_name'];
        $disabled = $conf['disable_language_merge'];
        if($disabled != 'true' && $providerPlugin && $plugin === 'Ceres' && $group === 'Template'){
            return array_merge($res, $resource->load("$providerPlugin::lang/$lang/Template")->getData());
        }

        return $res;
    }
}