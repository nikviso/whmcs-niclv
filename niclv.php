<?php
/**
 * WHMCS SDK Sample Registrar Module
 *
 * Registrar Modules allow you to create modules that allow for domain
 * registration, management, transfers, and other functionality within
 * WHMCS.
 *
 * This sample file demonstrates how a registrar module for WHMCS should
 * be structured and exercises supported functionality.
 *
 * Registrar Modules are stored in a unique directory within the
 * modules/registrars/ directory that matches the module's unique name.
 * This name should be all lowercase, containing only letters and numbers,
 * and always start with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For
 * example this file, the filename is "registrarmodule.php" and therefore all
 * function begin "registrarmodule_".
 *
 * If your module or third party API does not support a given function, you
 * should not define the function within your module. WHMCS recommends that
 * all registrar modules implement Register, Transfer, Renew, GetNameservers,
 * SaveNameservers, GetContactDetails & SaveContactDetails.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/domain-registrars/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license https://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require('lib/ApiClient.php');

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Database\Capsule;
//use WHMCS\Module\Registrar\Niclv\ApiClient;


// Require any libraries needed for the module to function.
// require_once __DIR__ . '/path/to/library/loader.php';
//
// Also, perform any initialization required by the service's library.

/**
 * Define module related metadata
 *
 * Provide some module information including the display name and API Version to
 * determine the method of decoding the input values.
 *
 * @return array
 */
function niclv_MetaData()
{
    return array(
        'DisplayName' => 'NIC LV',
        'APIVersion' => '1.1',
    );
}

/**
 * Define registrar configuration options.
 *
 * The values you return here define what configuration options
 * we store for the module. These values are made available to
 * each module function.
 *
 * You can store an unlimited number of configuration settings.
 * The following field types are supported:
 *  * Text
 *  * Password
 *  * Yes/No Checkboxes
 *  * Dropdown Menus
 *  * Radio Buttons
 *  * Text Areas
 *
 * @return array
 */
function niclv_getConfigArray()
{
    return [
        // Friendly display name for the module
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'NIC LV',
        ],
        // a text field type allows for single line text input
/*
        'RegistrarURL' => [
            'FriendlyName' => 'Registrar URL',
            'Type' => 'text',
            'Size' => '128',
            'Default' => 'tls://epp-sandbox.nic.lv',
            'Description' => 'Enter Registrar URL',
        ],
        'RegistrarTCPPort' => [
            'FriendlyName' => 'Registrar TCP Port',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '700',
            'Description' => 'Enter Registrar TCP Port',
        ],
        
        'Username' => [
            'FriendlyName' => 'Username',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter Username',
        ],
*/
        'APIUsername' => [
            'FriendlyName' => 'API Username',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter API Username',
        ],
        // a password field type allows for masked text input
        'APIKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter API Key',
        ],
        // the yesno field type displays a single checkbox option
        'TestMode' => [
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable',
        ],
/*
        // the dropdown field type renders a select menu of options
        'AccountMode' => [
            'FriendlyName' => 'Account Mode',
            'Type' => 'dropdown',
            'Options' => [
                'option1' => 'Display Value 1',
                'option2' => 'Second Option',
                'option3' => 'Another Option',
            ],
            'Description' => 'Choose one',
        ],
        // the radio field type displays a series of radio button options
        'EmailPreference' => [
            'FriendlyName' => 'Email Preference',
            'Type' => 'radio',
            'Options' => 'First Option,Second Option,Third Option',
            'Description' => 'Choose your preference',
        ],
        // the textarea field type allows for multi-line text input
        'Email' => [
            'FriendlyName' => 'Email',
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '60',
            'Description' => 'Freeform multi-line text input field',
        ],
*/        
    ];
}

/**
 * Register a domain.
 *
 * Attempt to register a domain with the domain registrar.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain registration order
 * * When a pending domain registration order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_RegisterDomain($params)
{

    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    /**
     * Nameservers.
     *
     * If purchased with web hosting, values will be taken from the
     * assigned web hosting server. Otherwise uses the values specified
     * during the order process.
     */
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];

    // registrant information
    $firstName = $params["firstname"];
    $lastName = $params["lastname"];
    $fullName = $params["fullname"]; // First name and last name combined
    $companyName = $params["companyname"];
    $email = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"]; // eg. TX
    $stateFullName = $params["fullstate"]; // eg. Texas
    $postcode = $params["postcode"]; // Postcode/Zip code
    $countryCode = $params["countrycode"]; // eg. GB
    $countryName = $params["countryname"]; // eg. United Kingdom
    $phoneNumber = $params["phonenumber"]; // Phone number as the user provided it
    $phoneCountryCode = $params["phonecc"]; // Country code determined based on country
    $phoneNumberFormatted = $params["fullphonenumber"]; // Format: +CC.xxxxxxxxxxxx
    $regNumber = $params[customfields1]; // Registration number/Personal code

    /**
     * Admin contact information.
     *
     * Defaults to the same as the client information. Can be configured
     * to use the web hosts details if the `Use Clients Details` option
     * is disabled in Setup > General Settings > Domains.
     */
    $adminFirstName = $params["adminfirstname"];
    $adminLastName = $params["adminlastname"];
    $adminFullName = $params["adminfullname"];    
    $adminCompanyName = $params["admincompanyname"];
    $adminEmail = $params["adminemail"];
    $adminAddress1 = $params["adminaddress1"];
    $adminAddress2 = $params["adminaddress2"];
    $adminCity = $params["admincity"];
    $adminState = $params["adminstate"]; // eg. TX
    $adminStateFull = $params["adminfullstate"]; // eg. Texas
    $adminPostcode = $params["adminpostcode"]; // Postcode/Zip code
    $adminCountry = $params["admincountry"]; // eg. GB
    $adminPhoneNumber = $params["adminphonenumber"]; // Phone number as the user provided it
    $adminPhoneNumberFormatted = $params["adminfullphonenumber"]; // Format: +CC.xxxxxxxxxxxx

    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];

    /**
     * Premium domain parameters.
     *
     * Premium domains enabled informs you if the admin user has enabled
     * the selling of premium domain names. If this domain is a premium name,
     * `premiumCost` will contain the cost price retrieved at the time of
     * the order being placed. The premium order should only be processed
     * if the cost price now matches the previously fetched amount.
     */
    $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'years' => $registrationPeriod,
        'nameservers' => array(
            'ns1' => $nameserver1,
            'ns2' => $nameserver2,
            'ns3' => $nameserver3,
            'ns4' => $nameserver4,
            'ns5' => $nameserver5,
        ),
        'contacts' => array(
            'registrant' => array(
                'firstname' => $firstName,
                'lastname' => $lastName,
                'fullname' => $fullName,
                'companyname' => $companyName,
                'email' => $email,
                'address1' => $address1,
                'address2' => $address2,
                'city' => $city,
                'state' => $state,
                'zipcode' => $postcode,
                'country' => $countryCode,
                'phonenumber' => $phoneNumberFormatted,
                'regnumber' => $regNumber,
            ),
            'admin' => array(
                'firstname' => $adminFirstName,
                'lastname' => $adminLastName,
                'fullname' => $adminFirstName.' '.$adminLastName, 
                'companyname' => $adminCompanyName,
                'email' => $adminEmail,
                'address1' => $adminAddress1,
                'address2' => $adminAddress2,
                'city' => $adminCity,
                'state' => $adminState,
                'zipcode' => $adminPostcode,
                'country' => $adminCountry,
                'phonenumber' => $adminPhoneNumberFormatted,
            ),
        ),
        'dnsmanagement' => $enableDnsManagement,
        'emailforwarding' => $enableEmailForwarding,
        'idprotection' => $enableIdProtection,
    );

    if ($premiumDomainsEnabled && $premiumDomainsCost) {
        $postfields['accepted_premium_cost'] = $premiumDomainsCost;
    }

    try {
        $api = new ApiClient();
        $response = $api->call('Register', $postfields);


        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Initiate domain transfer.
 *
 * Attempt to create a domain transfer request for a given domain.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain transfer order
 * * When a pending domain transfer order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_TransferDomain($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];
    $eppCode = $params['eppcode'];
    $request = 'OPERATION_REQUEST';

    /**
     * Nameservers.
     *
     * If purchased with web hosting, values will be taken from the
     * assigned web hosting server. Otherwise uses the values specified
     * during the order process.
     */
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];

    // registrant information
    $firstName = $params["firstname"];
    $lastName = $params["lastname"];
    $fullName = $params["fullname"]; // First name and last name combined
    $companyName = $params["companyname"];
    $email = $params["email"];
    $address1 = $params["address1"];
    $address2 = $params["address2"];
    $city = $params["city"];
    $state = $params["state"]; // eg. TX
    $stateFullName = $params["fullstate"]; // eg. Texas
    $postcode = $params["postcode"]; // Postcode/Zip code
    $countryCode = $params["countrycode"]; // eg. GB
    $countryName = $params["countryname"]; // eg. United Kingdom
    $phoneNumber = $params["phonenumber"]; // Phone number as the user provided it
    $phoneCountryCode = $params["phonecc"]; // Country code determined based on country
    $phoneNumberFormatted = $params["fullphonenumber"]; // Format: +CC.xxxxxxxxxxxx
    $regNumber = $params[customfields1]; // Registration number/Personal code
    
    /**
     * Admin contact information.
     *
     * Defaults to the same as the client information. Can be configured
     * to use the web hosts details if the `Use Clients Details` option
     * is disabled in Setup > General Settings > Domains.
     */
    $adminFirstName = $params["adminfirstname"];
    $adminLastName = $params["adminlastname"];
    $adminCompanyName = $params["admincompanyname"];
    $adminEmail = $params["adminemail"];
    $adminAddress1 = $params["adminaddress1"];
    $adminAddress2 = $params["adminaddress2"];
    $adminCity = $params["admincity"];
    $adminState = $params["adminstate"]; // eg. TX
    $adminStateFull = $params["adminfullstate"]; // eg. Texas
    $adminPostcode = $params["adminpostcode"]; // Postcode/Zip code
    $adminCountry = $params["admincountry"]; // eg. GB
    $adminPhoneNumber = $params["adminphonenumber"]; // Phone number as the user provided it
    $adminPhoneNumberFormatted = $params["adminfullphonenumber"]; // Format: +CC.xxxxxxxxxxxx

    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];

    /**
     * Premium domain parameters.
     *
     * Premium domains enabled informs you if the admin user has enabled
     * the selling of premium domain names. If this domain is a premium name,
     * `premiumCost` will contain the cost price retrieved at the time of
     * the order being placed. The premium order should only be processed
     * if the cost price now matches that previously fetched amount.
     */
    $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'eppcode' => $eppCode,
        'request' => $request,
        'nameservers' => array(
            'ns1' => $nameserver1,
            'ns2' => $nameserver2,
            'ns3' => $nameserver3,
            'ns4' => $nameserver4,
            'ns5' => $nameserver5,
        ),
        'years' => $registrationPeriod,
        'contacts' => array(
            'registrant' => array(
                'firstname' => $firstName,
                'lastname' => $lastName,
                'fullname' => $fullName,
                'companyname' => $companyName,
                'email' => $email,
                'address1' => $address1,
                'address2' => $address2,
                'city' => $city,
                'state' => $state,
                'zipcode' => $postcode,
                'country' => $countryCode,
                'phonenumber' => $phoneNumberFormatted,
            ),
            'tech' => array(
                'firstname' => $adminFirstName,
                'lastname' => $adminLastName,
                'companyname' => $adminCompanyName,
                'email' => $adminEmail,
                'address1' => $adminAddress1,
                'address2' => $adminAddress2,
                'city' => $adminCity,
                'state' => $adminState,
                'zipcode' => $adminPostcode,
                'country' => $adminCountry,
                'phonenumber' => $adminPhoneNumberFormatted,
            ),
        ),
        'dnsmanagement' => $enableDnsManagement,
        'emailforwarding' => $enableEmailForwarding,
        'idprotection' => $enableIdProtection,
    );

    try {
        $api = new ApiClient();
        $response = $api->call('Transfer', $postfields);
        
        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Renew a domain.
 *
 * Attempt to renew/extend a domain for a given number of years.
 *
 * This is triggered when the following events occur:
 * * Payment received for a domain renewal order
 * * When a pending domain renewal order is accepted
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_RenewDomain($params)
{
     
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    // domain addon purchase status
    $enableDnsManagement = (bool) $params['dnsmanagement'];
    $enableEmailForwarding = (bool) $params['emailforwarding'];
    $enableIdProtection = (bool) $params['idprotection'];

    /**
     * Premium domain parameters.
     *
     * Premium domains enabled informs you if the admin user has enabled
     * the selling of premium domain names. If this domain is a premium name,
     * `premiumCost` will contain the cost price retrieved at the time of
     * the order being placed. A premium renewal should only be processed
     * if the cost price now matches that previously fetched amount.
     */
    $premiumDomainsEnabled = (bool) $params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];

    // Build post data.
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'years' => $registrationPeriod,
        'dnsmanagement' => $enableDnsManagement,
        'emailforwarding' => $enableEmailForwarding,
        'idprotection' => $enableIdProtection,
    );

    try {
        $api = new ApiClient();
        $api->call('Renew', $postfields);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Fetch current nameservers.
 *
 * This function should return an array of nameservers for a given domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_GetNameservers($params)
{
    $results = array();
    
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();
        $results = $api->call('GetNameservers', $postfields);

        return array(
            'ns1' => $api->getFromResponse($results,'nameserver1'),
            'ns2' => $api->getFromResponse($results,'nameserver2'),
            'ns3' => $api->getFromResponse($results,'nameserver3'),
            'ns4' => $api->getFromResponse($results,'nameserver4'),
            'ns5' => $api->getFromResponse($results,'nameserver5'),
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Save nameserver changes.
 *
 * This function should submit a change of nameservers request to the
 * domain registrar.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_SaveNameservers($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // submitted nameserver values
    $nameserver1 = $params['ns1'];
    $nameserver2 = $params['ns2'];
    $nameserver3 = $params['ns3'];
    $nameserver4 = $params['ns4'];
    $nameserver5 = $params['ns5'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameservers' => array(
            'nameserver1' => $nameserver1,
            'nameserver2' => $nameserver2,
            'nameserver3' => $nameserver3,
            'nameserver4' => $nameserver4,
            'nameserver5' => $nameserver5,
        ),
    );

    try {
        $api = new ApiClient();
        $api->call('SetNameservers', $postfields);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Get the current WHOIS Contact Information.
 *
 * Should return a multi-level array of the contacts and name/address
 * fields that be modified.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_GetContactDetails($params)
{
    $results = array();
    $out = array();
/*
    $customFieldID = 3; //Identifier of the "Registration number/Personal code" field in the "tblcustomfieldsvalues" table of the database
    $regnumber = Capsule::table('tblcustomfieldsvalues')
                                    ->where('relid',$params[userid])
                                    ->where('fieldid',$customFieldID)
                                    ->value('value');
*/
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();
        $results = $api->call('GetWhoisInformation', $postfields);
        
        $out = [
            'Registrant' => array(
                'Registrant LV ID' => $api->getFromResponse($results,'registrant.id'),
                'Full Name' => $api->getFromResponse($results,'registrant.fullname'),
                'Company Name' => str_replace(array('\'', '"'), '',html_entity_decode($api->getFromResponse($results,'registrant.company'),ENT_QUOTES)),
                'Email Address' => $api->getFromResponse($results,'registrant.email'),
                'Address' => $api->getFromResponse($results,'registrant.address'),
                'City' => $api->getFromResponse($results,'registrant.city'),
                'Postcode' => $api->getFromResponse($results,'registrant.postcode'),
                'Country' => $api->getFromResponse($results,'registrant.country'),
                'Phone Number' => $api->getFromResponse($results,'registrant.phone'),
//                'VAT' => $api->getFromResponse($results,'registrant.vat'),
            ),
            'Admin' => array(
                'Admin LV ID' => $api->getFromResponse($results,'admin.id'),
                'Full Name' => $api->getFromResponse($results,'admin.fullname'),
                'Email Address' => $api->getFromResponse($results,'admin.email'),
                'Address' => $api->getFromResponse($results,'admin.address'),
                'City' => $api->getFromResponse($results,'admin.city'),
                'Postcode' => $api->getFromResponse($results,'admin.postcode'),
                'Country' => $api->getFromResponse($results,'admin.country'),
                'Phone Number' => $api->getFromResponse($results,'admin.phone'),
            ),
        ];
        
        if($out['Registrant']['Company Name']){
            $out['Registrant']['Registration number'] = $api->getFromResponse($results,'registrant.regnr');
        } else {
            $out['Registrant']['Personal code'] = $api->getFromResponse($results,'registrant.regnr');
        }    
        
        return $out;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Update the WHOIS Contact Information for a given domain.
 *
 * Called when a change of WHOIS Information is requested within WHMCS.
 * Receives an array matching the format provided via the `GetContactDetails`
 * method with the values from the users input.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */

function niclv_SaveContactDetails($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // whois information
    $contactDetails = $params['contactdetails'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,

        'contacts' => array(
            'registrant' => array(
                'id' => $contactDetails['Registrant']['Registrant LV ID'],
                'firstname' => $contactDetails['Registrant']['First Name'],
                'lastname' => $contactDetails['Registrant']['Last Name'],
                'fullname' => $contactDetails['Registrant']['Full Name'],
                'company' => str_replace(array('\'', '"'), '',html_entity_decode($contactDetails['Registrant']['Company Name'],ENT_QUOTES)),
                'email' => $contactDetails['Registrant']['Email Address'],
                'address' => $contactDetails['Registrant']['Address'],
                'city' => $contactDetails['Registrant']['City'],
                'postcode' => $contactDetails['Registrant']['Postcode'],
                'country' => $contactDetails['Registrant']['Country'],
                'phone' => $contactDetails['Registrant']['Phone Number'],
//                'vat' => $contactDetails['Registrant']['VAT'],                
            ),
            'admin' => array(
                'id' => $contactDetails['Admin']['Admin LV ID'],
                'firstname' => $contactDetails['Admin']['First Name'],
                'lastname' => $contactDetails['Admin']['Last Name'],
                'fullname' => $contactDetails['Admin']['Full Name'],
                'email' => $contactDetails['Admin']['Email Address'],
                'address' => $contactDetails['Admin']['Address'],
                'city' => $contactDetails['Admin']['City'],
                'postcode' => $contactDetails['Admin']['Postcode'],
                'country' => $contactDetails['Admin']['Country'],
                'phone' => $contactDetails['Admin']['Phone Number'],
            ),
        ), 
    );
    
    if(array_key_exists('Registration number',$contactDetails['Registrant'])){
        $postfields['contacts']['registrant']['regnr'] = $contactDetails['Registrant']['Registration number'];
    } else {
        $postfields['contacts']['registrant']['regnr'] = $contactDetails['Registrant']['Personal code'];
    }    
    
    try {
        $api = new ApiClient();
        $api->call('UpdateWhoisInformation', $postfields);

        return array(
            'success' => true,
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Check Domain Availability.
 *
 * Determine if a domain or group of domains are available for
 * registration or transfer.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain availability check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function niclv_CheckAvailability($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // availability check parameters
    $searchTerm = $params['searchTerm'];
    $punyCodeSearchTerm = $params['punyCodeSearchTerm'];
    $tldsToInclude = $params['tldsToInclude'];
    $isIdnDomain = (bool) $params['isIdnDomain'];
    $premiumEnabled = (bool) $params['premiumEnabled'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'searchTerm' => $searchTerm,
        'tldsToSearch' => $tldsToInclude,
        'includePremiumDomains' => $premiumEnabled,
    );

    try {
        $api = new ApiClient();
        $api->call('CheckAvailability', $postfields);

        $results = new ResultsList();
        foreach ($api->getFromResponse('domains') as $domain) {

            // Instantiate a new domain search result object
            $searchResult = new SearchResult($domain['sld'], $domain['tld']);

            // Determine the appropriate status to return
            if ($domain['status'] == 'available') {
                $status = SearchResult::STATUS_NOT_REGISTERED;
            } elseif ($domain['status'] == 'registered') {
                $status = SearchResult::STATUS_REGISTERED;
            } elseif ($domain['status'] == 'reserved') {
                $status = SearchResult::STATUS_RESERVED;
            } else {
                $status = SearchResult::STATUS_TLD_NOT_SUPPORTED;
            }
            $searchResult->setStatus($status);

            // Return premium information if applicable
            if ($domain['isPremiumName']) {
                $searchResult->setPremiumDomain(true);
                $searchResult->setPremiumCostPricing(
                    array(
                        'register' => $domain['premiumRegistrationPrice'],
                        'renew' => $domain['premiumRenewPrice'],
                        'CurrencyCode' => 'USD',
                    )
                );
            }

            // Append to the search results list
            $results->append($searchResult);
        }

        return $results;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Domain Suggestion Settings.
 *
 * Defines the settings relating to domain suggestions (optional).
 * It follows the same convention as `getConfigArray`.
 *
 * @see https://developers.whmcs.com/domain-registrars/check-availability/
 *
 * @return array of Configuration Options
 */
function niclv_DomainSuggestionOptions() {
    return array(
        'includeCCTlds' => array(
            'FriendlyName' => 'Include Country Level TLDs',
            'Type' => 'yesno',
            'Description' => 'Tick to enable',
        ),
    );
}

/**
 * Get Domain Suggestions.
 *
 * Provide domain suggestions based on the domain lookup term provided.
 *
 * @param array $params common module parameters
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @see \WHMCS\Domains\DomainLookup\SearchResult
 * @see \WHMCS\Domains\DomainLookup\ResultsList
 *
 * @throws Exception Upon domain suggestions check failure.
 *
 * @return \WHMCS\Domains\DomainLookup\ResultsList An ArrayObject based collection of \WHMCS\Domains\DomainLookup\SearchResult results
 */
function niclv_GetDomainSuggestions($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // availability check parameters
    $searchTerm = $params['searchTerm'];
    $punyCodeSearchTerm = $params['punyCodeSearchTerm'];
    $tldsToInclude = $params['tldsToInclude'];
    $isIdnDomain = (bool) $params['isIdnDomain'];
    $premiumEnabled = (bool) $params['premiumEnabled'];
    $suggestionSettings = $params['suggestionSettings'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'searchTerm' => $searchTerm,
        'tldsToSearch' => $tldsToInclude,
        'includePremiumDomains' => $premiumEnabled,
        'includeCCTlds' => $suggestionSettings['includeCCTlds'],
    );

    try {
        $api = new ApiClient();
        $api->call('GetSuggestions', $postfields);

        $results = new ResultsList();
        foreach ($api->getFromResponse('domains') as $domain) {

            // Instantiate a new domain search result object
            $searchResult = new SearchResult($domain['sld'], $domain['tld']);

            // All domain suggestions should be available to register
            $searchResult->setStatus(SearchResult::STATUS_NOT_REGISTERED);

            // Used to weight results by relevance
            $searchResult->setScore($domain['score']);

            // Return premium information if applicable
            if ($domain['isPremiumName']) {
                $searchResult->setPremiumDomain(true);
                $searchResult->setPremiumCostPricing(
                    array(
                        'register' => $domain['premiumRegistrationPrice'],
                        'renew' => $domain['premiumRenewPrice'],
                        'CurrencyCode' => 'USD',
                    )
                );
            }

            // Append to the search results list
            $results->append($searchResult);
        }

        return $results;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Get registrar lock status.
 *
 * Also known as Domain Lock or Transfer Lock status.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return string|array Lock status or error message
 */
function niclv_GetRegistrarLock($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];


    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();
        $api->call('GetLockStatus', $postfields);

        if ($api->getFromResponse('lockstatus') == 'locked') {
            return 'locked';
        } else {
            return 'unlocked';
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Set registrar lock status.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_SaveRegistrarLock($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // lock status
    $lockStatus = $params['lockenabled'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'registrarlock' => ($lockStatus == 'locked') ? 1 : 0,
    );

    try {
        $api = new ApiClient();
        $api->call('SetLockStatus', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Get DNS Records for DNS Host Record Management.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array DNS Host Records
 */
function niclv_GetDNS($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();
        $api->call('GetDNSHostRecords', $postfields);

        $hostRecords = array();
        foreach ($api->getFromResponse('records') as $record) {
            $hostRecords[] = array(
                "hostname" => $record['name'], // eg. www
                "type" => $record['type'], // eg. A
                "address" => $record['address'], // eg. 10.0.0.1
                "priority" => $record['mxpref'], // eg. 10 (N/A for non-MX records)
            );
        }
        return $hostRecords;

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Update DNS Host Records.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_SaveDNS($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // dns record parameters
    $dnsrecords = $params['dnsrecords'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'records' => $dnsrecords,
    );

    try {
        $api = new ApiClient();
        $api->call('SaveDNSHostRecords', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Enable/Disable ID Protection.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
/* 
function niclv_IDProtectToggle($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // id protection parameter
    $protectEnable = (bool) $params['protectenable'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();

        if ($protectEnable) {
            $api->call('EnableIDProtection', $postfields);
        } else {
            $api->call('DisableIDProtection', $postfields);
        }

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}
*/
/**
 * Request EEP Code.
 *
 * Supports both displaying the EPP Code directly to a user or indicating
 * that the EPP Code will be emailed to the registrant.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 *
 */
function niclv_GetEPPCode($params)
{
    $results = array();
    
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();
        $results = $api->call('RequestEPPCode', $postfields);

        $eppcode = $api->getFromResponse($results, 'authorisationcode');

        if ($eppcode) {
            // If EPP Code is returned, return it for display to the end user
            return array(
                'eppcode' => $eppcode,
            );
        } else {
            // If EPP Code is not returned, it was sent by email, return success
            return array(
                'success' => 'success',
            );          
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Release a Domain.
 *
 * Used to initiate a transfer out such as an IPSTAG change for .UK
 * domain names.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
/* 
function niclv_ReleaseDomain($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // transfer tag
    $transferTag = $params['transfertag'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'newtag' => $transferTag,
    );

    try {
        $api = new ApiClient();
        $api->call('ReleaseDomain', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}
*/
/**
 * Delete Domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_RequestDelete($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();
        $api->call('DeleteDomain', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Register a Nameserver.
 *
 * Adds a child nameserver for the given domain name.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_RegisterNameserver($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // nameserver parameters
    $nameserver = $params['nameserver'];
    $ipAddress = $params['ipaddress'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameserver' => $nameserver,
        'ip' => $ipAddress,
    );

    try {
        $api = new ApiClient();
        $api->call('RegisterNameserver', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Modify a Nameserver.
 *
 * Modifies the IP of a child nameserver.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_ModifyNameserver($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // nameserver parameters
    $nameserver = $params['nameserver'];
    $currentIpAddress = $params['currentipaddress'];
    $newIpAddress = $params['newipaddress'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameserver' => $nameserver,
        'currentip' => $currentIpAddress,
        'newip' => $newIpAddress,
    );

    try {
        $api = new ApiClient();
        $api->call('ModifyNameserver', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Delete a Nameserver.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_DeleteNameserver($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // nameserver parameters
    $nameserver = $params['nameserver'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'nameserver' => $nameserver,
    );

    try {
        $api = new ApiClient();
        $api->call('DeleteNameserver', $postfields);

        return array(
            'success' => 'success',
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Sync Domain Status & Expiration Date.
 *
 * Domain syncing is intended to ensure domain status and expiry date
 * changes made directly at the domain registrar are synced to WHMCS.
 * It is called periodically for a domain.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_Sync($params)
{
    $results = array();
    
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];
    $request = 'OPERATION_QUERY';
    
    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'request' => $request,
    );

    try {
        $api = new ApiClient();
        $results = $api->call('GetDomainInfo', $postfields);

        if ($api->getFromResponse($results, 'domainstatus') == 'auto-renewal') {
            $active = true;
        } else {
            $active = false;        
        }        
        
        return array(
            'expirydate' => date("Y-m-d", strtotime($api->getFromResponse($results, 'expirationdate'))), // Format: YYYY-MM-DD
            'active' => (bool) $active, // Return true if the domain is active
            'expired' => (bool) $api->getFromResponse($results, 'expired'), // Return true if the domain has expired
            'transferredAway' => (bool) $api->getFromResponse($results, 'transferredaway'), // Return true if the domain is transferred out
        );

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Incoming Domain Transfer Sync.
 *
 * Check status of incoming domain transfers and notify end-user upon
 * completion. This function is called daily for incoming domains.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_TransferSync($params)
{
    $results = array();

    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];
    $request = 'OPERATION_QUERY';

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'request' => $request,
    );

    try {
        $api = new ApiClient();
//        $api->call('CheckDomainTransfer', $postfields);
        $response = $api->call('Transfer', $postfields);
        
        if ($api->getFromResponse($response, 'transfercomplete')) {
            return array(
                'completed' => true,
                'expirydate' => $api->getFromResponse($response, 'expirydate'), // Format: YYYY-MM-DD
            );
        } elseif ($api->getFromResponse($response, 'transferfailed')) {
            return array(
                'failed' => true,
                'reason' => $api->getFromResponse($response, 'failurereason'), // Reason for the transfer failure if available
            );
        } else {
            // No status change, return empty array
            return array();
        }

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
}

/**
 * Admin Area Custom Button Array.
 *
 * Allows you to define additional actions your module supports.
 * In this example, we register a Push Domain action which triggers
 * the `registrarmodule_push` function when invoked.
 *
 * @return array
 */
function niclv_AdminCustomButtonArray()
{

    return array(
//        'Transfer INFO' => 'TransferQuery',
        'Transfer APPROVE' => 'TransferAPPROVE',
        'Transfer REJECT' => 'TransferREJECT',
        'Transfer CANCEL' => 'TransferCANCEL',
//        'Sync Domain Status' => 'Sync',        
    );
}

/**
 * Client Area Custom Button Array.
 *
 * Allows you to define additional actions your module supports.
 * In this example, we register a Push Domain action which triggers
 * the `registrarmodule_push` function when invoked.
 *
 * @return array
 */
function niclv_ClientAreaCustomButtonArray()
{
/*    
    return array(
//        'Transfer INFO' => 'TransferQuery',
        'Transfer APPROVE' => 'TransferAPPROVE',
        'Transfer REJECT' => 'TransferREJECT',
        'Transfer CANCEL' => 'TransferCANCEL',
    );
    
    
    return array(
        'Push Domain' => 'push',
    );
*/
}

/**
 * Client Area Allowed Functions.
 *
 * Only the functions defined within this function or the Client Area
 * Custom Button Array can be invoked by client level users.
 *
 * @return array
 */
function niclv_ClientAreaAllowedFunctions()
{
/*    
    return array(
//        'Transfer INFO' => 'TransferQuery',
        'Transfer APPROVE' => 'TransferAPPROVE',
        'Transfer REJECT' => 'TransferREJECT',
        'Transfer CANCEL' => 'TransferCANCEL',
    );
    
    return array(
        'Push Domain' => 'push',
    );
*/
}


function niclv_TransferAPPROVE($params)
{
    $results = array();
    
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];
    $eppCode = $params['eppcode'];
    $request = 'OPERATION_APPROVE';

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'eppcode' => $eppCode,
        'request' => $request,
    );
    
    try {
        $api = new ApiClient();
        $response = $api->call('Transfer', $postfields);
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
        
    return array($response);
}

function niclv_TransferREJECT($params)
{
    $results = array();
    
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];
    $eppCode = $params['eppcode'];
    $request = 'OPERATION_REJECT';

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'eppcode' => $eppCode,
        'request' => $request,
    );
    
    try {
        $api = new ApiClient();
        $response = $api->call('Transfer', $postfields);
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
        
    return array($response);
}

function niclv_TransferCANCEL($params)
{
    $results = array();
    
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];
    $eppCode = $params['eppcode'];
    $request = 'OPERATION_CANCEL';

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'eppcode' => $eppCode,
        'request' => $request,
    );
    
    try {
        $api = new ApiClient();
        $response = $api->call('Transfer', $postfields);
    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }
        
    return array($response);
}

function niclv_TransferQuery($params)
{
    $results = array();
    
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // registration parameters
    $sld = $params['sld'];
    $tld = $params['tld'];
    $registrationPeriod = $params['regperiod'];
    $eppCode = $params['eppcode'];
    $request = 'OPERATION_QUERY';

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
        'eppcode' => $eppCode,
        'request' => $request,
    );
    
    try {
        $api = new ApiClient();
        $response = $api->call('Transfer', $postfields);

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }

    return array($response);

}


/**
 * Example Custom Module Function: Push
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return array
 */
function niclv_push($params)
{
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Perform custom action here...

    return 'Not implemented';
}

/**
 * Client Area Output.
 *
 * This function renders output to the domain details interface within
 * the client area. The return should be the HTML to be output.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/domain-registrars/module-parameters/
 *
 * @return string HTML Output
 */
function niclv_ClientArea($params)
{
    $results = array();
    
    // user defined configuration values
    $userIdentifier = $params['APIUsername'];
    $apiKey = $params['APIKey'];
    $testMode = $params['TestMode'];
    $accountMode = $params['AccountMode'];
    $emailPreference = $params['EmailPreference'];

    // domain parameters
    $sld = $params['sld'];
    $tld = $params['tld'];

    // Build post data
    $postfields = array(
        'username' => $userIdentifier,
        'password' => $apiKey,
        'testmode' => $testMode,
        'domain' => $sld . '.' . $tld,
    );

    try {
        $api = new ApiClient();
        $results = $api->call('InfoDomain', $postfields);

    } catch (\Exception $e) {
        return array(
            'error' => $e->getMessage(),
        );
    }    

    $addoutput = '';

    for ($i=1; $i<6; $i++) {
      $ipaddress = $api->getFromResponse($results, 'ipaddress'.(string)$i);
      if ($ipaddress) {
          $addoutput = $addoutput . '<span>'.Lang::trans('domainnameserver'.(string)$i).': '. 
                     $api->getFromResponse($results, 'nameserver'.(string)$i).' ('.$ipaddress.')</span><br>';
      }
    };
    if(!$addoutput) $addoutput = '<span>'.Lang::trans('notfound').'</span><br>';  
    
    $domainnameserver = 'domainnameserver'.(string)1;
    $nameserver = 'nameserver'.(string)1;
    $ipaddress = 'ipaddress'.(string)1;
    $output = '<div class="row">
                <div class="col-sm-offset-1 col-sm-5">
                <h4>
                <strong>'.Lang::trans('domainprivatenameservers').':</strong>
                </h4> 
                '.$addoutput.'    
                </div>
    </div>';

    return $output;

}
