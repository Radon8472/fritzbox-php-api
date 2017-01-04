<?php

class Fritzbox
{
    private $host;
    private $user;
    private $pass;

    protected $cacheDir = '';

    public function __construct($host = NULL, $user = NULL, $pass = NULL, $options = array())
    {
        // authentication data
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;

        // overwrite options
        foreach ($options AS $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // test connection
        $this->getSid();
    }

    /**
     * Checks, if router is connected to the internet
     * @return bool
     */
    public function isConnected()
    {
        $response = $this->connect(
            'igdupnp/control/WANIPConn1',
            'WANIPConnection:1',
            'GetStatusInfo'
        );

        return $response['NewConnectionStatus'] == 'Connected';
    }

    /**
     * Reconnects internet connection
     * @return bool
     */
    public function reconnect()
    {
        $this->connect(
            'igdupnp/control/WANIPConn1',
            'WANIPConnection:1',
            'ForceTermination'
        );

        return true;
    }

    /**
     * Downloads a file from router
     * @param null $path
     * @return mixed
     */
    public function downloadPath($path = NULL)
    {
        $sid = $this->getSid();
        $link = 'http://' . $this->host . ':49000/download.lua?path=' . $path . '&' . $sid;

        $curl = curl_init($link);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        return curl_exec($curl);
    }

    /**
     * Read public ip address
     * @return string
     */
    public function getIpAddress()
    {
        $response = $this->connect(
            'igdupnp/control/WANIPConn1',
            'WANIPConnection:1',
            'GetExternalIPAddress'
        );

        return $response;
    }

    /**
     * Reads the calling history
     * @return array
     */
    public function getCallersList()
    {
        $response = $this->connect(
            'upnp/control/x_contact',
            'urn:dslforum-org:service:X_AVM-DE_OnTel:1',
            'GetCallList'
        );

        $xml = @simplexml_load_file($response);

        return $this->xml2Array($xml);
    }

    /**
     * Reads the answering machine history
     * @return array
     */
    public function getAnsweringmachineList()
    {
        $response = $this->connect(
            'upnp/control/x_tam',
            'urn:dslforum-org:service:X_AVM-DE_TAM:1',
            'GetMessageList',
            new SoapParam(0, 'NewIndex')
        );

        $xml = @simplexml_load_file($response);

        return $this->xml2Array($xml);
    }


    /**
     * Reads the current streaming rates
     * @return array
     */
    public function getStreamRates()
    {
        $response = $this->connect(
            'igdupnp/control/WANCommonIFC1',
            'WANCommonInterfaceConfig:1',
            'GetCommonLinkProperties'
        );

        return array(
            'down' => round($response['NewLayer1DownstreamMaxBitRate'] / 1000000, 1),
            'up' => round($response['NewLayer1UpstreamMaxBitRate'] / 1000000, 1)
        );
    }

    /**
     * Soap Client
     * @param $location
     * @param $uri
     * @param $function
     * @param null $param
     * @return mixed
     */
    protected function connect($location, $uri, $function, $param = NULL)
    {
        $client = new SoapClient(
            null,
            array(
                'location' => 'http://' . $this->host . ':49000/' . $location,
                'uri' => strstr($uri, 'urn:') ? $uri : 'urn:schemas-upnp-org:service:' . $uri,
                'noroot' => true,
                'login' => $this->user,
                'password' => $this->pass
            )
        );

        $response = $client->$function($param);

        return $response;
    }

    /**
     * Gets a valid sid for authentication
     * @return string
     */
    private function getSid()
    {
        $client = new SoapClient(
            null,
            array(
                'location' => 'http://' . $this->host . ':49000/upnp/control/deviceconfig',
                'uri' => "urn:dslforum-org:service:DeviceConfig:1",
                'noroot' => true,
                'login' => $this->user,
                'password' => $this->pass
            )
        );

        return $client->{"X_AVM-DE_CreateUrlSID"}();
    }

    /**
     * Converts an xml file object to array
     * @param null $xml
     * @return array
     */
    private function xml2Array($xml = NULL)
    {
        return json_decode(json_encode((array)$xml), true);
    }
}