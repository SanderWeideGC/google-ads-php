<?php

/**
 * Copyright 2018 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Ads\GoogleAds\Examples\BasicOperations;

require __DIR__ . '/../../vendor/autoload.php';

use GetOpt\GetOpt;
use Google\Ads\GoogleAds\Examples\Utils\ArgumentNames;
use Google\Ads\GoogleAds\Examples\Utils\ArgumentParser;
use Google\Ads\GoogleAds\Examples\Utils\Helper;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsException;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Util\V6\ResourceNames;
use Google\Ads\GoogleAds\V6\Common\ExpandedTextAdInfo;
use Google\Ads\GoogleAds\V6\Enums\AdGroupAdStatusEnum\AdGroupAdStatus;
use Google\Ads\GoogleAds\V6\Errors\GoogleAdsError;
use Google\Ads\GoogleAds\V6\Resources\Ad;
use Google\Ads\GoogleAds\V6\Resources\AdGroupAd;
use Google\Ads\GoogleAds\V6\Services\AdGroupAdOperation;
use Google\ApiCore\ApiException;

/** This example demonstrates how to add expanded text ads to a given ad group. */
class AddExpandedTextAds
{
    private const CUSTOMER_ID = 'INSERT_CUSTOMER_ID_HERE';
    private const AD_GROUP_ID = 'INSERT_AD_GROUP_ID_HERE';
    // Specify the number of ads to be added in this example.
    private const NUMBER_OF_ADS_TO_ADD = 2;

    public static function main()
    {
        // Either pass the required parameters for this example on the command line, or insert them
        // into the constants above.
        $options = (new ArgumentParser())->parseCommandArguments([
            ArgumentNames::CUSTOMER_ID => GetOpt::REQUIRED_ARGUMENT,
            ArgumentNames::AD_GROUP_ID => GetOpt::REQUIRED_ARGUMENT
        ]);

        // Generate a refreshable OAuth2 credential for authentication.
        $oAuth2Credential = (new OAuth2TokenBuilder())->fromFile()->build();

        // Construct a Google Ads client configured from a properties file and the
        // OAuth2 credentials above.
        $googleAdsClient = (new GoogleAdsClientBuilder())->fromFile()
            ->withOAuth2Credential($oAuth2Credential)
            ->build();

        try {
            self::runExample(
                $googleAdsClient,
                $options[ArgumentNames::CUSTOMER_ID] ?: self::CUSTOMER_ID,
                $options[ArgumentNames::AD_GROUP_ID] ?: self::AD_GROUP_ID
            );
        } catch (GoogleAdsException $googleAdsException) {
            printf(
                "Request with ID '%s' has failed.%sGoogle Ads failure details:%s",
                $googleAdsException->getRequestId(),
                PHP_EOL,
                PHP_EOL
            );
            foreach ($googleAdsException->getGoogleAdsFailure()->getErrors() as $error) {
                /** @var GoogleAdsError $error */
                printf(
                    "\t%s: %s%s",
                    $error->getErrorCode()->getErrorCode(),
                    $error->getMessage(),
                    PHP_EOL
                );
            }
            exit(1);
        } catch (ApiException $apiException) {
            printf(
                "ApiException was thrown with message '%s'.%s",
                $apiException->getMessage(),
                PHP_EOL
            );
            exit(1);
        }
    }

    /**
     * Runs the example.
     *
     * @param GoogleAdsClient $googleAdsClient the Google Ads API client
     * @param int $customerId the customer ID
     * @param int $adGroupId the ad group ID to add expanded text ads to
     */
    // [START add_expanded_text_ads]
    public static function runExample(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        int $adGroupId
    ) {
        $operations = [];
        for ($i = 0; $i < self::NUMBER_OF_ADS_TO_ADD; $i++) {
            // Creates the expanded text ad info.
            $expandedTextAdInfo = new ExpandedTextAdInfo([
                'headline_part1' => 'Cruise to Mars #' . Helper::getShortPrintableDatetime(),
                'headline_part2' => 'Best Space Cruise Line',
                'description' => 'Buy your tickets now!'
            ]);

            // Sets the expanded text ad info on an Ad.
            $ad = new Ad([
                'expanded_text_ad' => $expandedTextAdInfo,
                'final_urls' => ['http://www.example.com']
            ]);

            // Creates an ad group ad to hold the above ad.
            $adGroupAd = new AdGroupAd([
                'ad_group' => ResourceNames::forAdGroup($customerId, $adGroupId),
                'status' => AdGroupAdStatus::PAUSED,
                'ad' => $ad
            ]);

            // Creates an ad group ad operation and add it to the operations array.
            $adGroupAdOperation = new AdGroupAdOperation();
            $adGroupAdOperation->setCreate($adGroupAd);
            $operations[] = $adGroupAdOperation;
        }

        // Issues a mutate request to add the ad group ads.
        $adGroupAdServiceClient = $googleAdsClient->getAdGroupAdServiceClient();
        $response = $adGroupAdServiceClient->mutateAdGroupAds($customerId, $operations);

        foreach ($response->getResults() as $addedAdGroupAd) {
            /** @var AdGroupAd $addedAdGroupAd */
            printf(
                "Expanded text ad was created with resource name: '%s'%s",
                $addedAdGroupAd->getResourceName(),
                PHP_EOL
            );
        }
    }
    // [END add_expanded_text_ads]
}

AddExpandedTextAds::main();
