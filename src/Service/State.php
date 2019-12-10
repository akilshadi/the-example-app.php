<?php

/**
 * This file is part of the contentful/the-example-app package.
 *
 * @copyright 2015-2019 Contentful GmbH
 * @license   MIT
 */

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * State.
 *
 * This class is used to store information about the current state of the app.
 * Once it is initialized, it can be available everywhere through the DI container,
 * and in templates through the "state" variable.
 */
class State
{
    /**
     * @var string
     */
    public const SESSION_SETTINGS_NAME = 'settings';

    /**
     * @var string
     */
    private $spaceId;

    /**
     * @var string
     */
    private $deliveryToken;

    /**
     * @var string
     */
    private $previewToken;

    /**
     * @var bool
     */
    private $editorialFeatures;

    /**
     * @var string
     */
    private $api;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var string
     */
    private $queryString;

    /**
     * @var bool
     */
    private $cookieCredentials;

    /**
     * @param string[] $credentials
     */
    public function __construct(?Request $request, array $credentials, string $locale)
    {
        $settings = [
            'spaceId' => $credentials['space_id'],
            'deliveryToken' => $credentials['delivery_token'],
            'previewToken' => $credentials['preview_token'],
            'locale' => $locale,
            'editorialFeatures' => false,
            'api' => Contentful::API_DELIVERY,
            'queryString' => '',
            'cookieCredentials' => false,
        ];

        // Request can be null when running the CLI.
        if ($request) {
            $settings = \array_merge($settings, $this->extractValues($request));
        }

        foreach ($settings as $setting => $value) {
            $this->$setting = $value;
        }
    }

    private function extractValues(Request $request): array
    {
        $settings = $this->extractCookieSettings($request);

        if ($request->query->has('api')) {
            $settings['api'] = Contentful::API_PREVIEW === $request->query->get('api')
                ? Contentful::API_PREVIEW
                : Contentful::API_DELIVERY;
        }
        $settings['locale'] = $request->query->get('locale');
        $settings['queryString'] = $this->extractQueryString($settings['api'] ?? null, $settings['locale']);

        return \array_filter($settings);
    }

    private function extractCookieSettings(Request $request): array
    {
        $cookieSettings = (array) \json_decode(
            \stripslashes($request->cookies->get(Contentful::COOKIE_SETTINGS_NAME, '')),
            true
        );

        $settings = [];

        if ($cookieSettings) {
            $settings['cookieCredentials'] = true;
            $settings['spaceId'] = $cookieSettings['spaceId'];
            $settings['deliveryToken'] = $cookieSettings['deliveryToken'];
            $settings['previewToken'] = $cookieSettings['previewToken'];
            $settings['editorialFeatures'] = $cookieSettings['editorialFeatures'];
        }

        return $settings;
    }

    private function extractQueryString(?string $api, ?string $locale): string
    {
        // http_build_query will automatically skip null values.
        $queryString = \http_build_query([
            'api' => $api,
            'locale' => $locale,
        ]);

        return $queryString ? '?'.$queryString : '';
    }

    /**
     * Returns a representation of the current settings structure.
     *
     * @return string[]
     */
    public function getSettings(): array
    {
        return [
            'spaceId' => $this->spaceId,
            'deliveryToken' => $this->deliveryToken,
            'previewToken' => $this->previewToken,
            'editorialFeatures' => $this->editorialFeatures,
        ];
    }

    public function getSpaceId(): string
    {
        return $this->spaceId;
    }

    public function getDeliveryToken(): string
    {
        return $this->deliveryToken;
    }

    public function getPreviewToken(): string
    {
        return $this->previewToken;
    }

    public function usesCookieCredentials(): bool
    {
        return $this->cookieCredentials;
    }

    public function getApi(): string
    {
        return $this->api;
    }

    public function getApiLabel(): string
    {
        return $this->isDeliveryApi()
            ? 'Content Delivery API'
            : 'Content Preview API';
    }

    public function isDeliveryApi(): bool
    {
        return Contentful::API_DELIVERY === $this->api;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function hasEditorialFeaturesEnabled(): bool
    {
        return $this->editorialFeatures;
    }

    public function getQueryString(): string
    {
        return $this->queryString;
    }

    public function hasEditorialFeaturesLink(): bool
    {
        return $this->editorialFeatures && Contentful::API_PREVIEW === $this->api;
    }

    public function getShareableLinkQuery(): string
    {
        return '?'.\http_build_query([
            'space_id' => $this->spaceId,
            'delivery_token' => $this->deliveryToken,
            'preview_token' => $this->previewToken,
            'editorial_features' => $this->editorialFeatures ? 'enabled' : 'disabled',
            'api' => $this->api,
            'locale' => $this->locale,
        ]);
    }
}
