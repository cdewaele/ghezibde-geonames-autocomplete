<?php

/**
 * custom geonames module for https://www.ghezibde.net/genealogie
 */

declare(strict_types=1);

namespace GhezibdeGeonames;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMapAutocompleteInterface;
use Fisharebest\Webtrees\Module\ModuleMapAutocompleteTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Site;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\Validator;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\View;

class GhezibdeGeonamesAutocomplete extends AbstractModule implements ModuleConfigInterface, ModuleCustomInterface, ModuleMapAutocompleteInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;
    use ModuleMapAutocompleteTrait;

    public function __construct() {}

    public function boot(): void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    /**
     * Where does this module store its resources
     * 
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        // I18N: https://www.geonames.org
        return I18N::translate('Ghezibde GeoNames');
    }

    public function description(): string
    {
        return I18N::translate('Ghezibde autocomplete for place names using %s in local language.', '<a href="https://geonames.org">geonames.org</a>');
    }

    public function customModuleAuthorName(): string
    {
        return 'Ghezibde';
    }

    public function customModuleVersion(): string
    {
        return '2.0.0';
    }

    public function customModuleLatestVersionUrl(): string
    {
        return 'https://github.com/cdewaele/ghezibde-geonames-autocomplete/raw/main/latest-version.txt';
    }


    /**
     * @return ResponseInterface
     */
    public function getAdminAction(): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        // This was a global setting before it became a module setting...
        $default  = Site::getPreference('GHEZIBDE_GEONAMES');
        $username = $this->getPreference('username', $default);

        return $this->viewResponse($this->name() . '::ghezibde-config', [
            'username' => $username,
            'title'    => $this->title(),
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $username = Validator::parsedBody($request)->string('username');

        $this->setPreference('username', $username);

        FlashMessages::addMessage(I18N::translate('The preferences for the module “%s” have been updated.', $this->title()), 'success');

        return redirect($this->getConfigLink());
    }

    /**
     * @param string $place
     *
     * @return RequestInterface
     */
    protected function createPlaceNameSearchRequest(string $place): RequestInterface
    {
        // This was a global setting before it became a module setting...
        $default  = Site::getPreference('GHEZIBDE_GEONAMES');
        $username = $this->getPreference('username', $default);

        $uri = Html::url('https://secure.geonames.org/searchJSON', [
            'name_startsWith' => $place,
            'featureClass'    => 'P',
            // 'lang'            => I18N::languageTag(),
            'lang'            => 'local',  // ghezibde uses 'local' to get the local language instead of the user language
            'style'           => 'FULL',
            'username'        => $username,
        ]);

        return new Request('GET', $uri);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array<string>
     */
    protected function parsePlaceNameSearchResponse(ResponseInterface $response): array
    {
        $body    = $response->getBody()->getContents();
        $places  = [];
        $results = json_decode($body, false, 512, JSON_THROW_ON_ERROR);

        foreach ($results->geonames as $result) {
            // ghezibde uses the country name united Kingdom 
            // if (($result->countryName ?? null) === 'United Kingdom') {
            //     // adminName1 will be England, Scotland, etc.
            //     $result->countryName = null;
            // }

            $parts = [
                $result->name,
                $result->adminName2 ?? null,
                $result->adminName1 ?? null,
                $result->countryName ?? null,
            ];

            $places[] = implode(Gedcom::PLACE_SEPARATOR, array_filter($parts));
        }

        usort($places, I18N::comparator());

        return $places;
    }
}
