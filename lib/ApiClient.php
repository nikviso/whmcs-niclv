<?php

//namespace WHMCS\Module\Registrar\Niclv;

require('autoloader.php');

use Metaregistrar\EPP\eppConnection;
use Metaregistrar\EPP\eppException;
use Metaregistrar\EPP\eppDomain;
use Metaregistrar\EPP\eppInfoDomainRequest;
use Metaregistrar\EPP\eppContactHandle;
use Metaregistrar\EPP\eppHost;
use Metaregistrar\EPP\eppCheckDomainRequest;
use Metaregistrar\EPP\eppCheckDomainResponse;
use Metaregistrar\EPP\eppCheckRequest;
use Metaregistrar\EPP\eppContactPostalInfo;
use Metaregistrar\EPP\eppCreateHostRequest;
use Metaregistrar\EPP\eppInfoContactRequest;
use Metaregistrar\EPP\eppDeleteDomainRequest;
use Metaregistrar\EPP\eppPollRequest;
use Metaregistrar\EPP\eppResponse;
use Metaregistrar\EPP\eppCreateDomainRequest;
use Metaregistrar\EPP\lvEppContact;
use Metaregistrar\EPP\lvEppCreateContactRequest;
use Metaregistrar\EPP\lvEppUpdateContactRequest;
use Metaregistrar\EPP\eppDeleteContactRequest;
use Metaregistrar\EPP\eppUpdateDomainRequest;
use Metaregistrar\EPP\eppUpdateDomainResponse;
use Metaregistrar\EPP\lvEppUpdateDomainRenewStatusRequest;
use Metaregistrar\EPP\eppTransferRequest;
use Metaregistrar\EPP\lvEppInfoContactResponse;


/**
 * Sample Registrar Module Simple API Client.
 *
 * A simple API Client for communicating with an external API endpoint.
 */
class ApiClient
{
//    protected $results = array();
    
    /**
     * Make external API call to registrar API.
     *
     * @param string $action
     * @param array $postfields
     *
     * @throws \Exception Connection error
     * @throws \Exception Bad API response
     *
     * @return array
     */
    public function call($action, $postfields)
    {

        $results = array();
  
        try {

            $conn = new Metaregistrar\EPP\lvEppConnection;

            if(! $postfields[testmode]){
                $conn->setHostname('https://www.example.com/api/1.0/');
            } else {
                $conn->setHostname('tls://epp-sandbox.nic.lv');
            }
            $conn->setPort(700);
            $conn->setUsername($postfields[username]);
            $conn->setPassword($postfields[password]);    
            
            if (! $conn) {
                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,    
                    $postfields,
                    "Couldn't establish EPP connection",
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                ); 
                return "error: couldn't establish EPP connection";
            }
            
            //Connect and login to the EPP server
            if(! $conn->login()) {
                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,    
                    $postfields,
                    "Couldn't login with this credentials",
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                ); 
                throw new \Exception ("Couldn't login with this credentials");                
            }
            
            //Polls message
            $messageid = $this->poll($conn);
            if ($messageid) {
                $this->pollack($conn, $messageid);
            }
           
            if ($action == 'Register') {

                $domain = $postfields[domain];
                $period = $postfields[years];
                $registrantOrganization = $postfields[contacts][registrant][companyname];
                $regnumber = $postfields[contacts][registrant][regnumber];
                if ($registrantOrganization){            
                    $registrantEmail = $postfields[contacts][registrant][email];
                    $registrantTelephone = $postfields[contacts][registrant][phonenumber];
                    $registrantName = $postfields[contacts][registrant][fullname];
                    $registrantAddress = $postfields[contacts][registrant][address1];
                    $registrantPostcode = $postfields[contacts][registrant][zipcode];
                    $registrantCity = $postfields[contacts][registrant][city];
                    $registrantCountry = $postfields[contacts][registrant][country];
                }              

                $admineMail = $postfields[contacts][admin][email];
                $adminTelephone = $postfields[contacts][admin][phonenumber];
                $adminName = $postfields[contacts][admin][fullname];
                $adminOrganization = $postfields[contacts][admin][companyname];
                $adminAddress = $postfields[contacts][admin][address1];
                $adminPostcode = $postfields[contacts][admin][zipcode];
                $adminCity = $postfields[contacts][admin][city];
                $adminCountry = $postfields[contacts][admin][country];
  
               
                //Check domain names for availability
                if (!$this->checkDomains($conn, $domain)) {
                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        'Domain name is in use',
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );             
                    throw new \Exception('Domain name is in use');
                }         
                                           
                if ($registrantOrganization){  
                    $registrantcontactid = $this->createContact($conn, $registrantEmail, $registrantTelephone, $registrantName, 
                                                    $registrantOrganization, $registrantAddress, $registrantPostcode, 
                                                    $registrantCity, $registrantCountry, $regnumber);
                    $admincontactid = $this->createContact($conn, $admineMail, $adminTelephone, $adminName, 
                                               NULL, $adminAddress, $adminPostcode, 
                                               $adminCity, $adminCountry);                                                    
                } else {
                    $admincontactid = $this->createContact($conn, $admineMail, $adminTelephone, $adminName, 
                                               NULL, $adminAddress, $adminPostcode, 
                                               $adminCity, $adminCountry, $regnumber);                                                    
                    $registrantcontactid = $admincontactid;
                }       

                if ($registrantcontactid && $admincontactid) {
                    // Creates domain name
                    $results = $this->createDomain($conn, $domain, $registrantcontactid, $admincontactid, $postfields['nameservers'], $period);

                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        $results,
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );
                    
                } else {
                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        'error registrantcontactid or admincontactid',
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );             
                    throw new \Exception('Bad response received from API');
                }
            } else if ($action == 'Transfer') {
                /**
                 *Possible options:
                 *  OPERATION_QUERY   - Gets info about transfer status
                 *  OPERATION_REQUEST - Requests transfer, requires transfer code
                 *  OPERATION_APPROVE - Approves transfer request
                 *  OPERATION_REJECT  - Rejects transfer request
                 *  OPERATION_CANCEL  - Cancels transfer request
                 */
                $results = array();
                $request = $postfields['request'];
                
                if ($request == 'OPERATION_REQUEST') {
                    $request = eppTransferRequest::OPERATION_REQUEST;
                } else if ($request == 'OPERATION_APPROVE') {
                    $request = eppTransferRequest::OPERATION_APPROVE;
                } else if ($request == 'OPERATION_REJECT') {
                    $request = eppTransferRequest::OPERATION_REJECT;
                } else if ($request == 'OPERATION_CANCEL') {
                    $request = eppTransferRequest::OPERATION_CANCEL;
                } else  if ($request == 'OPERATION_QUERY') {
                    $request = eppTransferRequest::OPERATION_QUERY;
                } else {
                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        'Not set request type',
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );
                    throw new \Exception('Not set request type');
                } 

//                $postfields['domain'] = 'transfer-reject-clouds365-3.lv';
                $results = $this->transferDomain($conn, $postfields['domain'], $postfields['eppcode'], $request);

                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );    

            } else if ($action == 'GetNameservers') {
                
                $results = array();
                $results = $this->infoDomain($conn, $postfields['domain']);
                
            } else if ($action == 'SetNameservers') {

                $results = $this->modifyDomain($conn, $postfields['domain'], null, null, $postfields['nameservers']);                

                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );
                
            } else if ($action == 'RegisterNameserver') {
                
                $results = array();
                $flag = false;
                $info = $this->infoDomain($conn, $postfields['domain']);
                
                for ($i=1; $i<6; $i++) {
                    $hostname = $info['nameserver'.$i];
                    $ipaddress = $info['ipaddress'.$i];
                    if (!$hostname and !$ipaddress and !$flag) {
                        $hostnames['nameserver'.$i] = $postfields['nameserver'];
                        $ipaddresses['nameserver'.$i] = $postfields['ip'];
                        $flag = true;
                    } else {
                        $hostnames['nameserver'.$i] = $info['nameserver'.$i];
                        $ipaddresses['nameserver'.$i] = $info['ipaddress'.$i];
                    }
                }    
                
                $results = $this->modifyDomain($conn, $postfields['domain'], null, null, $hostnames, $ipaddresses);  

                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );                 

            } else if ($action == 'ModifyNameserver') {

                $results = array();
                $info = $this->infoDomain($conn, $postfields['domain']);
                $flag = false;
                
                for ($i=1; $i<6; $i++) {
                    $hostname = $info['nameserver'.$i];
                    $ipaddress = $info['ipaddress'.$i];
                    if ($hostname == $postfields['nameserver'] and $ipaddress == $postfields['currentip']) {
                        $hostnames['nameserver'.$i] = $postfields['nameserver'];
                        $ipaddresses['nameserver'.$i] = $postfields['newip'];
                        $flag = true;
                    } else {
                        $hostnames['nameserver'.$i] = $info['nameserver'.$i];
                        $ipaddresses['nameserver'.$i] = $info['ipaddress'.$i];
                    }
                }    
                
                if (!$flag) { 
                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        'Not found DNS server',
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );
                    throw new \Exception('Not found DNS server');
                }
                
                $results = $this->modifyDomain($conn, $postfields['domain'], null, null, $hostnames, $ipaddresses);  
                
                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );
                
            } else if ($action == 'DeleteNameserver') {
                
                $results = array();
                $info = $this->infoDomain($conn, $postfields['domain']);
                $flag = false;
                
                for ($i=1; $i<6; $i++) {
                    $hostname = $info['nameserver'.$i];
                    $ipaddress = $info['ipaddress'.$i];
                    if ($hostname == $postfields['nameserver'] and $ipaddress) {
                        $flag = true;
                    } else {
                        $hostnames['nameserver'.$i] = $info['nameserver'.$i];
                        $ipaddresses['nameserver'.$i] = $info['ipaddress'.$i];
                    }
                }
                
                if (!$flag) { 
                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        'Not found DNS server',
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );
                    throw new \Exception('Not found DNS server');
                }
                
                $results = $this->modifyDomain($conn, $postfields['domain'], null, null, $hostnames, $ipaddresses);                  
                
                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );

            } else if ($action == 'GetWhoisInformation') {
               $resultscontact = array();
                $results = $this->infoDomain($conn, $postfields['domain']);
                $registrantid = $results['registrant'];
                $adminid = $results['admin'];
               
                $resultscontact = $this->infoContact($conn, $registrantid);
                $results['registrant.id'] = $registrantid;
                $results['registrant.fullname'] = $resultscontact['name'];
                $results['registrant.company'] = str_replace(array('\'', '"'), '',html_entity_decode($resultscontact['org'],ENT_QUOTES));
                $results['registrant.email'] = $resultscontact['email'];
                $results['registrant.address'] = $resultscontact['street'];
                $results['registrant.city'] = $resultscontact['city'];
                $results['registrant.postcode'] = $resultscontact['pc'];
                $results['registrant.country'] = $resultscontact['cc'];
                $results['registrant.phone'] = $resultscontact['voice'];
                $results['registrant.regnr'] = $resultscontact['regnr'];                
//                $results['registrant.vat'] = $resultscontact['vat'];
//                $results['registrant.fax'] = $resultscontact['fax'];
                
                $resultscontact = $this->infoContact($conn, $adminid);
                $results['admin.id'] = $adminid;
                $results['admin.fullname'] = $resultscontact['name'];
                $results['admin.email'] = $resultscontact['email'];
                $results['admin.address'] = $resultscontact['street'];
                $results['admin.city'] = $resultscontact['city'];
                $results['admin.postcode'] = $resultscontact['pc'];
                $results['admin.country'] = $resultscontact['cc'];
                $results['admin.phone'] = $resultscontact['voice'];
//                $results['admin.fax'] = $resultscontact['fax'];                 

            } else if ($action == 'UpdateWhoisInformation') {
                $results = array();
                $regnumberlen = 11;
                $currentinfodomain = array();
                $registrant = $postfields['contacts']['registrant'];
                $admin = $postfields['contacts']['admin'];

                $currentinfodomain = $this->infoDomain($conn, $postfields['domain']);
                $currentregistrantid = $currentinfodomain['registrant'];
                $currentadminid = $currentinfodomain['admin'];
                
                if($registrant['fullname']){
                    $registrantName = $registrant['fullname'];
                } else {
                    $registrantName = $registrant['firstname'].' '.$registrant['lastname'];
                } 
                $registrantOrganization = $registrant['company'];
                $registrantEmail = $registrant['email'];
                $registrantAddress = $registrant['address'];
                $registrantCity = $registrant['city'];
                $registrantPostcode = $registrant['postcode'];
                $registrantCountry = $registrant['country'];
                $registrantTelephone = $registrant['phone'];
                $regnumber = $registrant['regnr'];                
//                $vatnr = $registrant['vat'];

                $checkregnumber = false;
                if($registrantOrganization) {
                    //Checking the correctness of entering the registration number of the organization
                    //$regnumber = str_replace('-','',$regnumber);
                    if(strlen($regnumber) == $regnumberlen){
                        if(is_numeric($regnumber)) $checkregnumber = true;
                    }            
                } else {
                    //checking the correctness of entering the personal code
                    $regnumber = str_replace('-','',$regnumber);
                    if(strlen($regnumber) == $regnumberlen){
                        if(is_numeric($regnumber)) $checkregnumber = true;
                        $regnumber = substr($regnumber, 0, 6)."-".substr($regnumber, 6, 5);
                    }
                }  

                if (!$checkregnumber) {
                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        'Not correct Registration number or Personal code',
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );                         
                    throw new \Exception('Not correct Registration number or Personal code');
                }  

                $registrantcontactid = $this->createContact($conn, $registrantEmail, $registrantTelephone, $registrantName, 
                                                $registrantOrganization, $registrantAddress, $registrantPostcode, 
                                                $registrantCity, 'LV', $regnumber);

                if($registrantOrganization) {
                    if($admin['fullname']){
                        $adminName = $admin['fullname'];
                    } else {
                        $adminName = $admin['firstname'].' '.$admin['lastname'];
                    }
                    $admineMail = $admin['email'];
                    $adminAddress = $admin['address'];
                    $adminCity = $admin['city'];
                    $adminPostcode = $admin['postcode'];
                    $adminCountry = $admin['country'];
                    $adminTelephone = $admin['phone'];
                    
                    $admincontactid = $this->createContact($conn, $admineMail, $adminTelephone, $adminName, 
                                               NULL, $adminAddress, $adminPostcode, 
                                               $adminCity, 'LV');                     
                } else {
                    $admincontactid = $registrantcontactid;
                }

                $results = $this->modifyDomain($conn, $postfields[domain], $registrantcontactid, $admincontactid, null);
                $this->deleteContact($conn, $currentregistrantid);
                if($currentregistrantid <> $currentadminid) $this->deleteContact($conn, $currentadminid);
/*                
                $results = $this->updateContact($conn, $contactid, $email, $telephone, $name, $organization, $address, $postcode, $city, $country, $regnr, $vatnr);
                $results = $this->updateContact($conn,  $registrant, $email, $telephone, $name, null, $address, $postcode, $city, 'LV', null, null);
                if(array_key_exists('error',$results)) throw new \Exception('ID:'.$registrant.', '.$results['error']);
*/              
            } else if ($action == 'DeleteDomain') {

                $results = array();
                $results = $this->deleteDomain($conn, $postfields['domain']);
                if($results['code'] <> "1000" and $results['code'] <> "1001"){
                    logModuleCall(
                        'niclv',
                        __METHOD__ . '->' . $action,
                        $postfields,
                        'Error['.$results['code'].'],'.$results['msg'],
                        '',
                        array(
                            $postfields['username'], // Mask username & password in request/response data
                            $postfields['password'],
                        )                      
                    );             
                    throw new \Exception('Error['.$results['code'].'],'.$results['msg']);                
                }
                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );      
            } else if ($action == 'RequestEPPCode') { 
                $results = array();
                $results = $this->infoDomain($conn, $postfields[domain]);

                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );            
            } else if ($action == 'InfoDomain') { 
                $results = array();
                $results = $this->infoDomain($conn, $postfields['domain']);

                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );            
            } else if ($action == 'Renew') {
                $results = array();
                //Allows auto renewal
                $results = $this->renewDomain($conn, $postfields[domain], true, "Client wants to keep domain");

                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    $results,
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );  

                //Set to false, if domain shouldnt be scheduled for auto-renewal and supply reason like so:
//                $this->renewDomain($conn, $postfields[domain], false, "Client didnt want to keep domain");                

            } else if ($action == 'GetLockStatus') {
            } else if ($action == 'GetDNSHostRecords') {
            } else {
                logModuleCall(
                    'niclv',
                    __METHOD__ . '->' . $action,
                    $postfields,
                    'NOT ELSE',
                    '',
                    array(
                        $postfields['username'], // Mask username & password in request/response data
                        $postfields['password'],
                    )                      
                );             
            }

            $conn->logout();
        
        } catch (eppException $e) {
            logModuleCall(
                'niclv',
                __METHOD__ . '->' . $action,
                $postfields,
                $e->getMessage(),
                '',
                array(
                    $postfields['username'], // Mask username & password in request/response data
                    $postfields['password'],
                )                      
            );             
            throw new \Exception('Bad response received from API');
        }


        /*
        error_log ("results:....1" . var_dump($this->results) . PHP_EOL);
        error_log ("results-->".$key.': '.$nameserver. PHP_EOL);            
        */


        if (empty($results)) {
            logModuleCall(
                'niclv',
                __METHOD__ . '->' . $action,
                $postfields,
                $results,
                '',
                array(
                    $postfields['username'], // Mask username & password in request/response data
                    $postfields['password'],
                )                      
            );            
            throw new \Exception('Bad response received from API');
        }
      
        return $results;

    }

    /**
     * Process API response.
     *
     * @param string $response
     *
     * @return array
     */
/*
    public function processResponse($response)
    {
        return json_decode($response, true);
    }
*/

    /**
     * Get from response results.
     *
     * @param string $key
     *
     * @return string
     */
    public function getFromResponse(array $results,$key)
    {
        return isset($results[$key]) ? $results[$key] : '';
    }

    function checkDomains($conn, $domain) {
        // Create request to be sent to EPP service
        $domains[0] = $domain;
        $check = new eppCheckDomainRequest($domains);
        // Write request to EPP service, read and check the results
        $response = $conn->request($check);
        if ($response) {
            /* @var $response eppCheckDomainResponse */
            // Walk through the results
            $checks = $response->getCheckedDomains();
            if (!$checks[0]['available']) {
                return false;
            } else {
                return true;
            }            
        }
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $domainname
     */
    public function infoDomain($conn, $domainname) {
        $results = array();
        $info = new eppInfoDomainRequest(new eppDomain($domainname));
        if ($response = $conn->request($info)) {
            /* @var $response Metaregistrar\EPP\eppInfoDomainResponse */
            $d = $response->getDomain();            

            $results['domainname'] = $d->getDomainname();
            $results['domaincreatedate'] = $response->getDomainCreateDate();
            $results['domainupdatedate'] = $response->getDomainUpdateDate();
            $results['expirationdate'] = $response->getDomainExpirationDate();                 
            $results['registrant'] = $d->getRegistrant();
            
            if( !empty($response->getLvDomainStatus()) ){
                $results['domainstatus'] = $response->getLvDomainStatus();
            } else {
                $results['domainstatus'] = 'auto-renewal';
            }

            foreach ($d->getContacts() as $contact) {
                /* @var $contact eppContactHandle */
                $results[$contact->getContactType()] = $contact->getContactHandle();
            }
            
            $i = 1;
            foreach ($d->getHosts() as $nameserver) {
                /* @var $nameserver eppHost */
               $results['nameserver'.$i] = $nameserver->getHostname();
               $ipaddress = $nameserver->getIpAddresses();
               if($ipaddress) $results['ipaddress'.$i] = key($ipaddress);               
               $i++;
            }
            if($d->getAuthorisationCode() != null){
                $results['authorisationcode'] = $d->getAuthorisationCode();
            }
        } else {
            logModuleCall(
                'niclv',
                __METHOD__ ,
                '',
                'ERROR infoDomain',
            );    
        }
        return $results;
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $domainname
     * @param boolean $renew
     * @param string $reason
     */
    public function renewDomain($conn, $domainname, $renew, $reason = null) {
        try {
            $results = array();
            $domain = new eppDomain($domainname);
            $update = new lvEppUpdateDomainRenewStatusRequest($domain, $renew, $reason);

            if ($response = $conn->request($update)) {
                /* @var eppUpdateDomainResponse $response */
                $results['response'] = $response->getResultMessage();
            }
        } catch (eppException $e) {
            logModuleCall(
                'niclv',
                __METHOD__,
                '',
                $e->getMessage(),
            );              
            return array(
                'error' => $e->getMessage(),
            );            
        }
        return $results;
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $domainname
     * @param string $registrant
     * @param string $admincontact
     * @param string $techcontact
     * @param string $nameservers
     */
    public function createDomain($conn, $domainname, $registrant, $admincontact, $nameservers, $period, $addresses = null) {
        /* @var $conn Metaregistrar\EPP\eppConnection */
        $results = array();
        
        try {
            $domain = new eppDomain($domainname, $registrant);
            $domain->setPeriod($period);
            $domain->setRegistrant(new eppContactHandle($registrant));
            $domain->addContact(new eppContactHandle($admincontact, eppContactHandle::CONTACT_TYPE_ADMIN));
            $results['authorisationcode'] = $domain->getAuthorisationCode();
/*
            if (is_array($nameservers)) {
                foreach ($nameservers as $nameserver) {
                    if ($nameserver) $domain->addHost(new eppHost($nameserver));
                }
            }
 */           
            if (is_array($nameservers)) {
                foreach($nameservers as $key => $nameserver) {
                    if($addresses and array_key_exists($key, $addresses)){
                        $domain->addHost(new eppHost($nameservers[$key],$addresses[$key]));
                    } else {
                        if ($nameserver) $domain->addHost(new eppHost($nameserver));
                    }    
                } 
            } 
            
            // true is required, because .lv registry uses domain:hostAttr instead of domain:hostObj
            $create = new eppCreateDomainRequest($domain, true);
            if ($response = $conn->request($create)) {
                /* @var $response Metaregistrar\EPP\eppCreateDomainResponse */
     
                $results['registrantid'] = $registrant;
                $results['admincontactid'] = $admincontact;
                $results['domainname'] = $response->getDomainName();
                $results['createdate'] = $response->getDomainCreateDate();
                $results['expirationdate'] = $response->getDomainExpirationDate();              
               
                return $results;

            }
        } catch (eppException $e) {
            logModuleCall(
                'niclv',
                __METHOD__ ,
                '',
                $e->getMessage(),
            );            
        }
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $domainname
     * @param string $registrant
     * @param string $admincontact
     * @param string $techcontact
     * @param string $nameservers
     */
    public function modifyDomain($conn, $domainname, $registrant = null, $admincontact = null, $nameservers = null, $addresses = null) {
        $response = null;
        try {
            // First, retrieve the current domain info. Nameservers can be unset and then set again.
            $del = null;
            $domain = new eppDomain($domainname);
            $info = new eppInfoDomainRequest($domain);
            if ($response = $conn->request($info)) {
                // If new nameservers are given, get the old ones to remove them
                if (is_array($nameservers)) {
                    /* @var Metaregistrar\EPP\eppInfoDomainResponse $response */
                    $oldns = $response->getDomainNameservers();
                    if (is_array($oldns)) {
                        if (!$del) {
                            $del = new eppDomain($domainname);
                        }
                        foreach ($oldns as $ns) {
                            $del->addHost($ns);
                        }
                    }
                }
                if ($admincontact) {
                    $oldadmin = $response->getDomainContact(eppContactHandle::CONTACT_TYPE_ADMIN);
                    if ($oldadmin == $admincontact) {
                        $admincontact = null;
                    } else {
                        if (!$del) {
                            $del = new eppDomain($domainname);
                        }
                        $del->addContact(new eppContactHandle($oldadmin, eppContactHandle::CONTACT_TYPE_ADMIN));
                    }
                }
            }
            // In the UpdateDomain command you can set or add parameters
            // - Registrant is always set (you can only have one registrant)
            // - Admin, Tech, Billing contacts are Added (you can have multiple contacts, don't forget to remove the old ones)
            // - Nameservers are Added (you can have multiple nameservers, don't forget to remove the old ones
            $mod = null;
            if ($registrant) {
                $mod = new eppDomain($domainname);
                $mod->setRegistrant(new eppContactHandle($registrant));
            }
            $add = null;
            if ($admincontact) {
                if (!$add) {
                    $add = new eppDomain($domainname);
                }
                $add->addContact(new eppContactHandle($admincontact, eppContactHandle::CONTACT_TYPE_ADMIN));
            }
/*            
            if (is_array($nameservers)) {
                if (!$add) {
                    $add = new eppDomain($domainname);
                }
                foreach ($nameservers as $nameserver) {
                    if ($nameserver) $add->addHost(new eppHost($nameserver));
                }
            }
*/
            if (is_array($nameservers)) {
                if (!$add) {
                    $add = new eppDomain($domainname);
                }
                foreach($nameservers as $key => $nameserver) {
                    if($addresses and array_key_exists($key, $addresses)){
                        if ($nameserver) $add->addHost(new eppHost($nameserver,$addresses[$key]));
                    } else {
                        if ($nameserver) $add->addHost(new eppHost($nameserver));
                    }    
                } 
            } 
            
            $update = new eppUpdateDomainRequest($domain, $add, $del, $mod, true);
            //echo $update->saveXML();
            if ($response = $conn->request($update)) {
                /* @var eppUpdateDomainResponse $response */
                return $response->getResultMessage();
            }
        } catch (eppException $e) {
            $error = $e->getMessage();
            if ($response instanceof eppUpdateDomainResponse) {
                $error = $error.': '.$response->textContent;
            }
            logModuleCall(
                'niclv',
                __METHOD__,
                $nameservers,
                $error,
            );
        }
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $domainname
     * @return null
     */
    public function deleteDomain($conn, $domainname) {
        
        $delete = new eppDeleteDomainRequest(new eppDomain($domainname));
        if ($response = $conn->request($delete)) {
            /* @var $response \Metaregistrar\EPP\eppDeleteResponse */
            return array(
                'msg' => $response->getResultMessage(),
                'code' => $response->getResultCode(),
            );
        } else {
            logModuleCall(
                'niclv',
                __METHOD__ ,
                '',
                'Domain delete failed',
            );
        }
        return null;
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $email
     * @param string $telephone
     * @param string $name
     * @param string $organization
     * @param string $address
     * @param string $postcode
     * @param string $city
     * @param string $country
     * @param string $regNr
     * @param string $vatNr
     */
    public function createContact($conn, $email, $telephone, $name, $organization, $address, $postcode, $city, $country, $regNr = null, $vatNr = null) {

        $postalinfo = new eppContactPostalInfo($name, $city, $country, $organization, $address, null, $postcode, lvEppContact::TYPE_LOC);
        $contactinfo = new lvEppContact($postalinfo, $email, $telephone);
        $contactinfo->setContactExtReg($regNr);
        $contactinfo->setContactExtVat($vatNr);
        //$contactinfo->setPassword('12345');

        //$contactinfo->setContactExtReg($regNr);
        $contact = new lvEppCreateContactRequest($contactinfo);
        if ($response = $conn->request($contact)) {
            /* @var $response Metaregistrar\EPP\eppCreateContactResponse */
            return $response->getContactId();
        } else {
            logModuleCall(
                'niclv',
                __METHOD__ ,
                '',
                'Create contact failed',
            );              
        }
        return null;
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $contactid
     * @param string $email
     * @param string $telephone
     * @param string $name
     * @param string $organization
     * @param string $address
     * @param string $postcode
     * @param string $city
     * @param string $country
     * @param string $regNr
     * @param string $vatNr
    */
    public function updateContact($conn, $contactid, $email, $telephone, $name, $organization, $address, $postcode, $city, $country, $regNr = null, $vatNr = null) {
        try {
            $postalinfo = new eppContactPostalInfo($name, $city, $country, $organization, $address, null, $postcode, lvEppContact::TYPE_LOC);
            $contact = new eppContactHandle($contactid);
            $update = new lvEppContact($postalinfo, $email, $telephone);
            if(!empty($regNr)){
                $update->setContactExtReg($regNr);
            }
            if(!empty($vatNr)){
                $update->setContactExtVat($vatNr);
            }
            $up = new lvEppUpdateContactRequest($contact, null, null, $update);
            if ($response = $conn->request($up)) {
                /* @var $response Metaregistrar\EPP\eppCreateResponse */
                return array(
                    'success' => true,
                );
            }
        } catch (Metaregistrar\EPP\eppException $e) {
            logModuleCall(
                'niclv',
                __METHOD__,
                '',
                $e->getMessage(),
            );              
            return array(
                'error' => $e->getMessage(),
            );
        }
    }

    public function deleteContact($conn, $contactid) {
        try {
            $contact = new eppContactHandle($contactid);
            $del = new eppDeleteContactRequest($contact);
            if ($response = $conn->request($del)) {
                /* @var $response Metaregistrar\EPP\eppCreateResponse */
                logModuleCall(
                    'niclv',
                    __METHOD__,
                    '',
                    'Contact '.$contactid.' deleted.',
                );                 
                return array(
                    'success' => true,
                    'delitedid' => $contactid,
                );                    
            }
        } catch (eppException $e) {
            logModuleCall(
                'niclv',
                __METHOD__,
                '',
                $e->getMessage(),
            );              
            return array(
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * @param \Metaregistrar\EPP\eppConnection $conn
     * @param string $contactid
     */
    public function infoContact($conn, $contactid) {
        $results = array();
        
        try {
            $contact = new eppContactHandle($contactid);
            $info = new eppInfoContactRequest($contact);
            if ((($response = $conn->writeandread($info)) instanceof lvEppInfoContactResponse) && ($response->Success())) {
                /* @var $response Metaregistrar\EPP\lvEppInfoContactResponse */
                $results['id'] = $response->getContactId();
                $results['roid'] = $response->getContactRoid();
                $results['clid'] = $response->getContactClientId();
                $results['crid'] = $response->getContactCreateClientId();
                $results['update'] = $response->getContactUpdateDate();
                $results['crdate'] = $response->getContactCreateDate();
                $results['status'] = $response->getContactStatusCSV();
                $results['voice'] = $response->getContactVoice();
                $results['fax'] = $response->getContactFax();
                $results['email'] = $response->getContactEmail();
                $results['name'] = $response->getContactName();
                $results['street'] = $response->getContactStreet();
                $results['city'] = $response->getContactCity();
                $results['pc'] = $response->getContactZipcode();
                $results['cc'] = $response->getContactCountrycode();
                $results['org'] = $response->getContactCompanyname();
                $results['upid'] = $response->getContactUpdateClientId();
                $results['pw'] = $response->getContactAuthInfo();
                $results['vat'] = $response->getVatNr();
                $results['regnr'] = $response->getRegNr();
                //return $response->saveXML();
            }
        } catch (eppException $e) {
            logModuleCall(
                'niclv',
                __METHOD__ ,
                '',
                $e->getMessage(),
            ); 
        }

        return $results;
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $domainname
     * @param string $authcode
     * @param Metaregistrar\EPP\eppTransferRequest $request
     */
    /**
     *Possible options:
     *  OPERATION_QUERY   - Gets info about transfer status
     *  OPERATION_REQUEST - Requests transfer, requires transfer code
     *  OPERATION_APPROVE - Approves transfer request
     *  OPERATION_REJECT  - Rejects transfer request
     *  OPERATION_CANCEL  - Cancels transfer request
     */
    public function transferDomain($conn, $domainname, $authcode, $request) {
        try {

            $results = array();
            $domain = new eppDomain($domainname);
            if($request === eppTransferRequest::OPERATION_REQUEST){
                $domain->setAuthorisationCode($authcode);
            }
            $transfer = new eppTransferRequest($request, $domain);
            $response = $conn->request($transfer);
            
            if ($response instanceof Metaregistrar\EPP\eppTransferResponse) {

                if ($request === eppTransferRequest::OPERATION_QUERY){
                    $results['tname'] = $response->getDomainName();
                    $results['ttrStatus'] = $response->getTransferStatus();
                    $results['treID'] = $response->getTransferRequestClientId();
                    $results['treDate'] = $response->getTransferRequestDate();
                    $results['texDate'] = $response->getTransferExpirationDate();
                    $results['tacID'] = $response->getTransferActionClientId();
                    $results['tacDate'] = $response->getTransferActionDate();
                } else {
                    $results['msg'] = $response->getResultMessage();
                }

            } else {
                $results['xml'] = $response->saveXML();
            }

        } catch (eppException $e) {
            logModuleCall(
                'niclv',
                __METHOD__ ,
                '',
                $e->getMessage(),
            ); 
        }

        return $results;        
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @return null|string
     */
    public function poll($conn) {
        try {
            $poll = new eppPollRequest(eppPollRequest::POLL_REQ, 0);
            if ($response = $conn->request($poll)) {
                /* @var $response Metaregistrar\EPP\eppPollResponse */
                if ($response->getResultCode() == eppResponse::RESULT_MESSAGE_ACK) {
                    $conn->setUsername('*******');
                    $conn->setPassword('*******'); 
                    logModuleCall(
                        'niclv',
                        __METHOD__,
                        $conn,
                        $response->getMessageCount() . " messages waiting in the queue\n".
                        "Picked up message " . $response->getMessageId() . ': ' . $response->getMessage(),
                    ); 
                
                    return $response->getMessageId();
                } else {
                    $conn->setUsername('*******');
                    $conn->setPassword('*******'); 
                    logModuleCall(
                        'niclv',
                        __METHOD__,
                        $conn,
                        $response->getResultMessage(),
                    ); 
                }
            }
        } catch (eppException $e) {
            $conn->setUsername('*******');
            $conn->setPassword('*******'); 
            logModuleCall(
                'niclv',
                __METHOD__,
                $conn,
                $e->getMessage(),
            );             
        }
        return null;
    }

    /**
     * @param Metaregistrar\EPP\eppConnection $conn
     * @param string $messageid
     */
    public function pollack($conn, $messageid) {
        try {
            $poll = new eppPollRequest(eppPollRequest::POLL_ACK, $messageid);
            if ($response = $conn->request($poll)) {
                /* @var $response Metaregistrar\EPP\eppPollResponse */
            $conn->setUsername('*******');
            $conn->setPassword('*******'); 
            logModuleCall(
                'niclv',
                __METHOD__,
                $conn,
                "Message $messageid is acknowledged",
            );                 
            }
        } catch (eppException $e) {
//            echo $e->getMessage() . "\n";
            $conn->setUsername('*******');
            $conn->setPassword('*******'); 
            logModuleCall(
                'niclv',
                __METHOD__,
                $conn,
                $e->getMessage(),
            );            
        }
    }
}
