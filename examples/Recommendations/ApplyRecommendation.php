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

namespace Google\Ads\GoogleAds\Examples\Recommendations;

require __DIR__ . '/../../vendor/autoload.php';

use GetOpt\GetOpt;
use Google\Ads\GoogleAds\Examples\Utils\ArgumentNames;
use Google\Ads\GoogleAds\Examples\Utils\ArgumentParser;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsException;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Util\V6\ResourceNames;
use Google\Ads\GoogleAds\V6\Errors\GoogleAdsError;
use Google\Ads\GoogleAds\V6\Resources\Recommendation;
use Google\Ads\GoogleAds\V6\Services\ApplyRecommendationOperation;
use Google\ApiCore\ApiException;

/**
 * This example applies a given recommendation. To retrieve recommendations for text ads,
 * run GetTextAdRecommendations.php.
 */
class ApplyRecommendation
{
    private const CUSTOMER_ID = 'INSERT_CUSTOMER_ID_HERE';
    // Recommendation ID is the last alphanumeric portion of the value from
    // ResourceNames::forRecommendation(), which has the format of
    // `customers/<customer_id>/recommendations/<recommendation_id>`.
    // Its example can be retrieved from GetTextAdRecommendations.php
    private const RECOMMENDATION_ID = 'INSERT_RECOMMENDATION_ID_HERE';

    public static function main()
    {
        // Either pass the required parameters for this example on the command line, or insert them
        // into the constants above.
        $options = (new ArgumentParser())->parseCommandArguments([
            ArgumentNames::CUSTOMER_ID => GetOpt::REQUIRED_ARGUMENT,
            ArgumentNames::RECOMMENDATION_ID => GetOpt::REQUIRED_ARGUMENT
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
                $options[ArgumentNames::RECOMMENDATION_ID] ?: self::RECOMMENDATION_ID
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
     * @param string $recommendationId the recommendation ID to apply
     */
    // [START apply_recommendation]
    public static function runExample(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $recommendationId
    ) {
        $recommendationResourceName =
            ResourceNames::forRecommendation($customerId, $recommendationId);

        $applyRecommendationOperation = new ApplyRecommendationOperation();
        $applyRecommendationOperation->setResourceName($recommendationResourceName);

        // Each recommendation type has optional parameters to override the recommended values.
        // This is an example to override a recommended ad when a TextAdRecommendation is applied.
        // For details, please read
        // https://developers.google.com/google-ads/api/reference/rpc/latest/ApplyRecommendationOperation.
        /*
        $overridingAd = new Ad([
            'id' => 'INSERT_AD_ID_AS_INTEGER_HERE'
        ]);
        $applyRecommendationOperation->setTextAd(new TextAdParameters(['ad' => $overridingAd]));
        */
        // Issues a mutate request to apply the recommendation.
        $recommendationServiceClient = $googleAdsClient->getRecommendationServiceClient();
        $response = $recommendationServiceClient->applyRecommendation(
            $customerId,
            [$applyRecommendationOperation]
        );
        /** @var Recommendation $appliedRecommendation */
        $appliedRecommendation = $response->getResults()[0];

        printf(
            "Applied recommendation with resource name: '%s'.%s",
            $appliedRecommendation->getResourceName(),
            PHP_EOL
        );
    }
    // [END apply_recommendation]
}

ApplyRecommendation::main();
