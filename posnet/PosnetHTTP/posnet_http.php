<?php
/*
 * posnet_http.php
 *
 */

if (!defined('POSNET_MODULES_DIR')) {
    define('POSNET_MODULES_DIR', dirname(__FILE__).'/..');
}

// Include the http library
require_once POSNET_MODULES_DIR.'/HTTP/http.php';

use Illuminate\Support\Facades\Log;

class PosnetHTTPConection
{
    /**
     * Error message for http connection.
     */
    public $error;
    /**
     * Used for debugging.
     */
    public $debug = 1;
    /**
     * Used for forcing OpenSSL.
     */
    public $useOpenSSL = false;
    /**
     * Used for indicating debug level
     * 0->No debug (default) <br>
     * 1->Posnet debug <br>
     * 2->Posnet & HTTP debug <br>.
     */
    public $debuglevel = 0;
    /**
     * HTTP method
     *  'POST' (default) <br>
     *  'GET'.
     */
    public $request_method = 'POST';

    public $url = '';

    /**
     * Constructor.
     */
    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * This function is used to connect to POSNET system via HTTP/HTTPS protocol.
     * $http and $arguments references will be return after a success call.
     *
     * @param string $url
     * @param array  $postValues
     * @param HTTP   &$http
     * @param array  &$arguments
     *
     * @return string
     */
    public function ConnectToPosnetSystem($url, $postValues, &$http, &$arguments)
    {
        /* Connection timeout */
        $http->timeout = 30;

        /* Data transfer timeout */
        $http->data_timeout = 60;

        if ($this->debuglevel > 1) {
            /* Output debugging information about the progress of the connection */
            $http->debug = 1;
            /* Format dubug output to display with HTML pages */
            $http->html_debug = 1;
        }

        /*
         *  Need to emulate a certain browser user agent?
         *  Set the user agent this way:
         */
        $http->user_agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)';

        $error = $http->GetRequestArguments($url, $arguments);
        if ($error != '') {
            return $error;
        }

        //$arguments["ProxyHostName"]="127.0.0.1";
        //$arguments["ProxyHostPort"]=6060;

        /* Set additional request headers */
        $arguments['Headers']['Pragma'] = 'nocache';

        if ($this->request_method == 'POST') {
            $arguments['RequestMethod'] = $this->request_method;
            $arguments['PostValues'] = $postValues;
        }

        if ($this->debug) {
            Log::info("Posnet - Opening connection to: " . htmlentities($arguments['HostName']));
        }

        return $http->Open($arguments);
    }

    /**
     * This function is used to send and receive data via GET/POST methods.
     *
     * @param string $strRequestData
     *
     * @return string
     */
    public function SendDataAndGetResponse($strRequestData)
    {
        $http = new HTTP();

        if ($this->useOpenSSL) {
            $http->use_openssl = 1;
        }

        $strResponseData = '';
        $strTempURL = $this->url;

        //POST Method
        if ($this->request_method == 'POST') {
            $postValues = array(
                'xmldata' => $strRequestData,
            );
        } //GET Method
        else {
            $strTempURL .= ('?xmldata='.urlencode($strRequestData));
        }

        //Connect
        $error = $this->ConnectToPosnetSystem($strTempURL,
            $postValues,
            $http,
            $arguments);

        if ($error == '') {
            if ($this->debug) {
                Log::info("Posnet - Sending request for page: " . htmlentities($arguments['RequestURI']));
            }

            //Send
            $error = $http->SendRequest($arguments);
            if ($error == '') {
                if ($this->debug) {
                    Log::info("Posnet - Request: " . htmlentities($http->request));
                }

                $headers = array();
                //Read Response Headers
                $error = $http->ReadReplyHeaders($headers);
                if ($error == '') {
                    if ($this->debug) {
                        Log::info("Posnet - Response status code: " . $http->response_status);
                    }
                    switch ($http->response_status) {
                        case '301':
                        case '302':
                        case '303':
                        case '307':
                            Log::info("Posnet -  (redirect to  " .$headers['location'].") \nSet the follow_redirect variable to handle redirect responses automatically.");
                            break;
                    }

                    //Read Response Body
                    for (; ;) {
                        $error = $http->ReadReplyBody($body, 2000);
                        if (strlen($body) == 0) {
                            break;
                        }
                        $strResponseData .= $body;
                    }
                }
            }
            $http->Close();
        }
        if (strlen($error)) {
            $this->error = $error;
            if ($this->debug) {
                Log::info("Posnet -  Error: $error");
            }

            return '';
        }

        return $strResponseData;
    }

    /* Public methods */

    /**
     * This function is used to set remote URL of POSNET system.
     *
     * @param string $url
     */
    public function SetURL($url)
    {
        $this->url = $url;
    }

    /**
     * It is used for forcing to use OpenSSL Extension for secure connection.
     */
    public function UseOpenssl()
    {
        $this->useOpenSSL = true;
    }

    /**
     * This function is used to set errors like communication errors.
     *
     * @param string $error
     */
    public function SetError($error)
    {
        $this->error = $error;
    }

    /**
     * This function is used to set debug level.
     *
     * @param string $debuglevel
     */
    public function SetDebugLevel($debuglevel)
    {
        $this->debuglevel = $debuglevel;
        if ($this->debuglevel > 0) {
            $this->debug = 1;
        }
    }
}
