<?php

/**
 * Copyright 2019 Google LLC
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

namespace Google\Ads\GoogleAds\Examples\Extensions;

require __DIR__ . '/../../vendor/autoload.php';

use GetOpt\GetOpt;
use Google\Ads\GoogleAds\Examples\Utils\ArgumentNames;
use Google\Ads\GoogleAds\Examples\Utils\ArgumentParser;
use Google\Ads\GoogleAds\Examples\Utils\Helper;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClient;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Lib\V6\GoogleAdsException;
use Google\Ads\GoogleAds\V6\Common\MatchingFunction;
use Google\Ads\GoogleAds\V6\Common\Operand;
use Google\Ads\GoogleAds\V6\Common\Operand\ConstantOperand;
use Google\Ads\GoogleAds\V6\Enums\FeedOriginEnum\FeedOrigin;
use Google\Ads\GoogleAds\V6\Enums\MatchingFunctionOperatorEnum\MatchingFunctionOperator;
use Google\Ads\GoogleAds\V6\Enums\PlaceholderTypeEnum\PlaceholderType;
use Google\Ads\GoogleAds\V6\Errors\GoogleAdsError;
use Google\Ads\GoogleAds\V6\Resources\CustomerFeed;
use Google\Ads\GoogleAds\V6\Resources\Feed;
use Google\Ads\GoogleAds\V6\Resources\Feed\PlacesLocationFeedData;
use Google\Ads\GoogleAds\V6\Resources\Feed\PlacesLocationFeedData\OAuthInfo;
use Google\Ads\GoogleAds\V6\Services\CustomerFeedOperation;
use Google\Ads\GoogleAds\V6\Services\FeedOperation;
use Google\ApiCore\ApiException;

/**
 * This example adds a feed that syncs feed items from a Google My Business (GMB) account
 * and associates the feed with a customer.
 */
class AddGoogleMyBusinessLocationExtensions
{
    private const CUSTOMER_ID = 'INSERT_CUSTOMER_ID_HERE';
    private const GMB_EMAIL_ADDRESS = 'INSERT_GMB_EMAIL_ADDRESS_HERE';
    private const GMB_ACCESS_TOKEN = 'INSERT_GMB_ACCESS_TOKEN_HERE';
    private const BUSINESS_ACCOUNT_IDENTIFIER = 'INSERT_BUSINESS_ACCOUNT_IDENTIFIER_HERE';

    // The required scope for setting the OAuth info.
    private const GOOGLE_ADS_SCOPE = 'https://www.googleapis.com/auth/adwords';
    // The maximum number of customer feed ADD operation attempts to make before throwing an
    // exception.
    private const MAX_CUSTOMER_FEED_ADD_ATTEMPTS = 10;
    private const POLL_FREQUENCY_SECONDS = 5;

    public static function main()
    {
        // Either pass the required parameters for this example on the command line, or insert them
        // into the constants above.
        $options = (new ArgumentParser())->parseCommandArguments([
            ArgumentNames::CUSTOMER_ID => GetOpt::REQUIRED_ARGUMENT,
            ArgumentNames::GMB_EMAIL_ADDRESS => GetOpt::REQUIRED_ARGUMENT,
            ArgumentNames::GMB_ACCESS_TOKEN => GetOpt::REQUIRED_ARGUMENT,
            ArgumentNames::BUSINESS_ACCOUNT_IDENTIFIER => GetOpt::REQUIRED_ARGUMENT
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
                $options[ArgumentNames::GMB_EMAIL_ADDRESS] ?: self::GMB_EMAIL_ADDRESS,
                $options[ArgumentNames::GMB_ACCESS_TOKEN] ?: self::GMB_ACCESS_TOKEN,
                $options[ArgumentNames::BUSINESS_ACCOUNT_IDENTIFIER]
                    ?: self::BUSINESS_ACCOUNT_IDENTIFIER
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
     * @param int $customerId the client customer ID
     * @param string $gmbEmailAddress the email address associated with the GMB account
     * @param string $gmbAccessToken the access token created using the 'AdWords' scope and the
     *     client ID and client secret of with the Cloud project associated with the GMB account
     * @param string $businessAccountIdentifier the account number of the GMB account
     */
    public static function runExample(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $gmbEmailAddress,
        string $gmbAccessToken,
        string $businessAccountIdentifier
    ) {
        $gmbFeedResourceName = self::createFeed(
            $googleAdsClient,
            $customerId,
            $gmbEmailAddress,
            $gmbAccessToken,
            $businessAccountIdentifier
        );
        self::createCustomerFeed($googleAdsClient, $customerId, $gmbFeedResourceName);

        // OPTIONAL: Create a campaign feed to specify which feed items to use at the campaign
        // level.

        // OPTIONAL: Create an ad group feed for even more fine grained control over which feed
        // items are used at the ad group level.
    }

    /**
     * Creates a location feed that will sync to the Google My Business account specified by
     * `$gmbEmailAddress`. Do not add feed attributes to this object as Google Ads will add them
     * automatically because this will be a system generated feed.
     *
     * @param GoogleAdsClient $googleAdsClient the Google Ads API client
     * @param int $customerId the client customer ID
     * @param string $gmbEmailAddress the email address associated with the GMB account
     * @param string $gmbAccessToken the access token created using the 'AdWords' scope and the
     *     client ID and client secret of with the Cloud project associated with the GMB account
     * @param string $businessAccountIdentifier the account number of the GMB account
     * @return string the feed's resource name
     */
    // [START add_google_my_business_location_extensions]
    private static function createFeed(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $gmbEmailAddress,
        string $gmbAccessToken,
        string $businessAccountIdentifier
    ) {

        $gmbFeed = new Feed([
            'name' => 'Google My Business feed #' . Helper::getPrintableDatetime(),
            'origin' => FeedOrigin::GOOGLE,
            'places_location_feed_data' => new PlacesLocationFeedData([
                'email_address' => $gmbEmailAddress,
                'business_account_id' => $businessAccountIdentifier,
                // Used to filter Google My Business listings by labels. If entries exist in
                // label_filters, only listings that have at least one of the labels set are
                // candidates to be synchronized into FeedItems. If no entries exist in
                // label_filters, then all listings are candidates for syncing.
                'label_filters' => ['Stores in New York'],
                // Sets the authentication info to be able to connect Google Ads to the GMB
                // account.
                'oauth_info' => new OAuthInfo([
                    'http_method' => 'GET',
                    'http_request_url' => self::GOOGLE_ADS_SCOPE,
                    'http_authorization_header' => 'Bearer ' . $gmbAccessToken
                ])

            ])
        ]);
        // Creates a feed operation.
        $feedOperation = new FeedOperation();
        $feedOperation->setCreate($gmbFeed);

        // [START add_google_my_business_location_extensions_1]
        // Issues a mutate request to add the feed and print its information.
        // Since it is a system generated feed, Google Ads will automatically:
        // 1. Set up the feed attributes on the feed.
        // 2. Set up a feed mapping that associates the feed attributes of the feed with the
        //    placeholder fields of the LOCATION placeholder type.
        $feedServiceClient = $googleAdsClient->getFeedServiceClient();
        $response = $feedServiceClient->mutateFeeds(
            $customerId,
            [$feedOperation]
        );
        $gmbFeedResourceName = $response->getResults()[0]->getResourceName();
        printf("GMB feed created with resource name: '%s'.%s", $gmbFeedResourceName, PHP_EOL);

        return $gmbFeedResourceName;
        // [END add_google_my_business_location_extensions_1]
    }
    // [END add_google_my_business_location_extensions]

    /**
     * Creates a customer feed to attach the previously created GMB feed to the specified customer
     * ID.
     *
     * @param GoogleAdsClient $googleAdsClient the Google Ads API client
     * @param int $customerId the client customer ID
     * @param string $gmbFeedResourceName the feed's resource name to be used to create a customer
     *     feed
     */
    // [START add_google_my_business_location_extensions_2]
    private static function createCustomerFeed(
        GoogleAdsClient $googleAdsClient,
        int $customerId,
        string $gmbFeedResourceName
    ) {
        // Creates a customer feed that associates the feed with this customer for the LOCATION
        // placeholder type.
        $customerFeed = new CustomerFeed([
            'feed' => $gmbFeedResourceName,
            'placeholder_types' => [PlaceholderType::LOCATION],
            // Creates a matching function that will always evaluate to true.
            'matching_function' => new MatchingFunction([
                'left_operands' => [new Operand([
                    'constant_operand' => new ConstantOperand(['boolean_value' => true])
                ])],
                'function_string' => 'IDENTITY(true)',
                'operator' => MatchingFunctionOperator::IDENTITY
            ])
        ]);
        // Creates a customer feed operation.
        $customerFeedOperation = new CustomerFeedOperation();
        $customerFeedOperation->setCreate($customerFeed);

        // [START add_google_my_business_location_extensions_3]
        // After the completion of the feed ADD operation above the added feed will not be available
        // for usage in a customer feed until the sync between the Google Ads and GMB accounts
        // completes. The loop below will retry adding the customer feed up to ten times with an
        // exponential back-off policy.
        $numberOfAttempts = 0;
        $addedCustomerFeed = null;
        $customerFeedServiceClient = $googleAdsClient->getCustomerFeedServiceClient();
        do {
            $numberOfAttempts++;
            try {
                // Issues a mutate request to add a customer feed and print its information if the
                // request succeeded.
                $addedCustomerFeed = $customerFeedServiceClient->mutateCustomerFeeds(
                    $customerId,
                    [$customerFeedOperation]
                );
                printf(
                    "Customer feed created with resource name: '%s'.%s",
                    $addedCustomerFeed->getResults()[0]->getResourceName(),
                    PHP_EOL
                );
            } catch (GoogleAdsException $googleAdsException) {
                // Waits using exponential backoff policy.
                $sleepSeconds = self::POLL_FREQUENCY_SECONDS * pow(2, $numberOfAttempts);
                // Exits the loop early if $sleepSeconds grows too large in the event that
                // MAX_CUSTOMER_FEED_ADD_ATTEMPTS is set too high.
                if (
                    $sleepSeconds > self::POLL_FREQUENCY_SECONDS
                    * pow(2, self::MAX_CUSTOMER_FEED_ADD_ATTEMPTS)
                ) {
                    break;
                }
                printf(
                    "Attempt #%d to add the customer feed was not successful."
                    . " Waiting %d seconds before trying again.%s",
                    $numberOfAttempts,
                    $sleepSeconds,
                    PHP_EOL
                );
                sleep($sleepSeconds);
            }
        } while (
            $numberOfAttempts < self::MAX_CUSTOMER_FEED_ADD_ATTEMPTS
            && is_null($addedCustomerFeed)
        );
        // [END add_google_my_business_location_extensions_3]

        if (is_null($addedCustomerFeed)) {
            throw new \RuntimeException(
                'Could not create the customer feed after ' . self::MAX_CUSTOMER_FEED_ADD_ATTEMPTS
                . ' attempts. Please retry the customer feed ADD operation later.'
            );
        }
    }
    // [END add_google_my_business_location_extensions_2]
}

AddGoogleMyBusinessLocationExtensions::main();
