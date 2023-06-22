<?php

namespace Bundles\Foundation\Ksb\Lib;

use Bundles\Foundation\Ksb\Exceptions\SoapFaultException;
use Inteleon\Soap\Client as SoapClient;
use RuntimeException, Path, File, DateTime;

class Slim
{

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     * @option string 'login'
     * @option string 'password'
     * @option string 'org'
     */
    protected $account = [];

    /**
     * @var string|null
     */
    protected $session = null;

    protected $clients = [];

    /**
     * Slim constructor.
     * @param array $options
     * @option string 'baseUrl'
     * @option array 'clientOptions'
     * @option array 'extClientOptions'
     * @option array 'callLogin'
     */
    public function __construct(array $options = [])
    {
        $this->options = [ // default options...
            'clientOptions' => config('ksb.soap_client_options'),
            'extClientOptions' => config('ksb.soap_client_extended_options'),
            'callLogin' => [
                'ConnectionID' => '',
                'UserAlias' => '',
                'Password' => '',
                'Language' => 'RU',
                'ProfileID' => '',
                'ContextXML' => '',
                'Timeout' => 900000 // ms
            ]
        ];
        if ($options) {
            $this->options = array_replace_recursive($this->options, $options);
        }
    }

    /**
     * @param array $account
     * @return $this
     */
    public function setAccount(array $account)
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @return mixed
     * @throws SoapFaultException
     */
    public function callLogin()
    {
        $params = array_merge($this->options['callLogin'], [
            'UserAlias' => $this->account['login'],
            'Password' => $this->account['password']
        ]);
        if (isset($this->account['org'])) {
            $params['ContextXML'] = sprintf(
                config('ksb.login_context_xml'),
                $this->account['org']
            );
        }
        $res = $this->LS('Login', $params);
        if ($res['return'] === 'lrSuccess') {
            return $this->session = $res['SessionID'];
        }
        throw new SoapFaultException($res['return']);
    }

    /**
     * @param string|null $sid
     * @return mixed
     */
    public function callLogout($sid = null)
    {
        $sid ?: $sid = $this->session;
        if (!$sid) {
            throw new RuntimeException("Missed 'SessionID' argument!");
        }
        return $this->LS('Logout', [$sid]);
    }

    /**
     * @return mixed|null|string
     */
    public function getSession()
    {
        if ($this->session) {
            return $this->session;
        }
        return $this->session = $this->callLogin();
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function LS($method, array $params = [])
    {
        return $this->callByServerKey('LS', $method, $params);
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function RS($method, array $params = [])
    {
        return $this->callByServerKey('RS', $method, $params);
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function WSS($method, array $params = [])
    {
        return $this->callByServerKey('WSS', $method, $params);
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function CRM($method, array $params = [])
    {
        return $this->callByServerKey('CRM', $method, $params);
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function EP($method, array $params = [])
    {
        return $this->callByServerKey('EP', $method, $params);
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     */
    public function EMS($method, array $params = [])
    {
        return $this->callByServerKey('EMS', $method, $params);
    }

    /**
     * @param string $key
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws SoapFaultException
     */
    protected function callByServerKey($key, $method, array $params = [])
    {
        $url = $this->options['baseUrl'] . '/' . config('ksb.' . $key);
        return $this->call($url, $method, $params);
    }

    /**
     * @param string $url
     * @return \SoapClient
     */
    protected function getClient($url)
    {
        if (!isset($this->clients[$url])) {
            $client = new SoapClient($url, $this->options['clientOptions']);
            if ($timeout = array_get($this->options, 'extClientOptions.timeout')) {
                $client->setTimeout($timeout);
            }
            if ($connect_timeout = array_get($this->options, 'extClientOptions.connect_timeout')) {
                $client->setConnectTimeout($connect_timeout);
            }
            $this->clients[$url] = $client;
        }
        return $this->clients[$url];
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws SoapFaultException
     */
    public function call($url, $method, array $params = [])
    {
        $client = $this->getClient($url);
        $call = $client->__soapCall($method, $params);
        if ($this->isInspect()) {
            $this->putInspect($method . '.req', (string)$client->__getLastRequest());
            $this->putInspect($method . '.res', (string)$client->__getLastResponse(), true);
        }
        if (is_soap_fault($call)) {
            throw new SoapFaultException($call->faultstring);
        }
        return $call;
    }

    /**
     * @return bool
     */
    protected function isInspect()
    {
        return env('KSB_INSPECT', false);
    }

    /**
     * @param string $name
     * @param string $soap
     * @param bool $unwrap
     */
    protected function putInspect(string $name, string $soap, bool $unwrap = false)
    {
        $date = date('Y-m-d');
        $time = (new DateTime())->format('H:i:s.v');
        $dir = Path::join(config('ksb.inspect_dir'), $date);
        File::exists($dir) ?: File::makeDirectory($dir);
        $file = $time . '_' . $name . '.xml';
        File::put(
            Path::join($dir, $file),
            $unwrap ? $this->unwrapSoapReturnXml($soap) : $soap
        );
    }

    /**
     * @param string $soap
     * @return string
     */
    protected function unwrapSoapReturnXml(string $soap)
    {
        $begin = substr($soap, 0, 50);
        $isEnvelop = stristr($begin, 'SOAP-ENV:Envelope');
        if ($isEnvelop) {
            $matches = [];
            preg_match('/<return[^>]*>([^<]*)<\/return>/', $soap, $matches);
            if (isset($matches[1])) {
                $xml = str_replace(['&lt;', '&gt;'], ['<', '>'], $matches[1]);
                return $xml;
            }
        }
        return $soap;
    }

}
