<?php declare(strict_types=1);
/**
 * Client class for handling API request
 *
 * PHP version 8.2
 *
 * @category Library
 * @package  Sat\ClientSdk
 * @author   egon12 <egon.firman@bytedance.com>
 * @license  MIT https://opensource.org/license/MIT
 * @link     https://github.com/tokopedia/sat-client-sdk
 */
namespace Sat\ClientSdk;

use GuzzleHttp\Client as HttpClient;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use phpseclib3\Crypt\RSA;

/**
 * Client class for handling API request
 *
 * Sample code
 *
 * ```
 * $client = new Client(
 * [
 *     'client_id' => 'your_client_id',
 *     'client_secret' => 'your_client_secret',
 * ],
 *
 * $res = $client->inquiry(
 *     'client_number' => '123', 
 *     'product_code' => 'token-pln-prepaid-10k'
 * );
 *
 * print_r($res);
 * ```
 *
 * @category Library
 * @package  Sat\ClientSdk
 * @author   egon12 <egon.firman@bytedance.com>
 * @license  MIT https://opensource.org/license/MIT
 * @link     https://github.com/tokopedia/php-sat
 */
class Client
{

    protected HttpClient $client;

    protected GenericProvider $provider;

    protected $privKey;

    /**
     * Client constructor.
     *
     * @param array $config configuration
     */
    public function __construct(public array $config = [])
    {
        if (!isset($config['client_id']) || !isset($config['client_secret'])) {
            throw new \InvalidArgumentException(
                "client_id and client_secret must be provided"
            );
        }

        if (isset($config['private_key'])) {
            $this->privKey = RSA::loadPrivateKey($config['private_key']);
        }

        $tokenUrl = $config['token_url'] ?? 'https://accounts.tokopedia.com/token';
        $baseUrl = $config['base_url'] ?? 'https://b2b-playground.tokopedia.com/api/';
        $timeout = $config['timeout'] ?? 10;

        $this->client = new HttpClient(
            [
            'base_uri'        => $baseUrl,
            'timeout'         => $timeout,
            'headers'         => [ 
            'X-Sat-Sdk-Version' => 'php-sat@1.0',
            'Accept' => 'application/json' ],
            ]
        );

        $this->provider = new GenericProvider(
            [
            'clientId'                => $config['client_id'],
            'clientSecret'            => $config['client_secret'],
            'urlAccessToken'          => $tokenUrl,

            // not used but needed by
            'urlAuthorize'            => $tokenUrl . '/authorize',
            'urlResourceOwnerDetails' => $tokenUrl . '/resource',
            ], [

            // advance options that replace client and option provider
            'httpClient'              => $this->client,
            'optionProvider'          => new HttpBasicAuthOptionProvider(),
            ]
        );
    } 

    /**
     * Get request
     *
     * @return array
     */
    public function productList(): array
    {
        return $this->_get('v2/product-list');
    }

    /**
     * Inquiry
     *
     * @param string $product_code  for product code
     * @param string $client_number for client number could be their phone number, 
     *                              billing no or anything that identify the client
     * @param array  $fields        for additional fields that needed for the product
     *
     * @return array
     */
    public function inquiry(
        string $product_code, 
        string $client_number, 
        array $fields = [],
    ): array {
        $body = [
        'data' => [
        'id' => $client_number,
        'type' => 'inquiry',
        'attributes' => [
        'product_code' => $product_code,
        'client_number' => $client_number,
        'fields' => $fields,
        ]
        ]
        ];


        return $this->_post('v2/inquiry', $body);
    }


    /**
     * Checkout
     *
     * @param string $id            for order id that you create
     * @param string $product_code  for product code
     * @param string $client_number for client number could be their phone number, 
     *                              billing no or anything that identify the client
     * @param int    $amount        amount that will be pay
     * @param array  $fields        for additional fields that needed for the product
     *
     * @return array
     */
    public function checkout(
        string $id, 
        string $product_code, 
        string $client_number, 
        int $amount = 0, 
        array $fields = [],
    ): array {
        $body = [
        'data' => [
        'type' => 'order',
        'id' => $id,
        'attributes' => [
        'product_code' => $product_code,
        'client_number' => $client_number,
        'amount' => $amount,
        'fields' => $fields,
        ]
        ]
        ];

        return $this->_postWithSignature('v2/order', $body);
    }

    /**
     * Check Status
     *
     * @param string $id for order id that you create
     *
     * @return array
     */
    public function checkStatus(
        string $id, 
    ): array {
        return $this->_get('v2/order/'. $id);
    }


    /**
     * Get request
     *
     * @param string $path path url or full url.
     *
     * @return mixed
     */
    private function _get(string $path): mixed
    {
        $token = $this->provider->getAccessToken('client_credentials');
        $response = $this->client->get(
            $path, [
            'headers' => [
            'Authorization' => 'Bearer ' . $token,
            ]]
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Post request
     *
     * @param string $path path url or full url.
     * @param array  $data data that will be store in the body
     *
     * @return mixed
     */
    private function _post(string $path, array $data): mixed
    {
        $token = $this->provider->getAccessToken('client_credentials');
        $response = $this->client->post(
            $path, [
            'json' => $data,
            'headers' => [
            'Authorization' => 'Bearer ' . $token,
            ]]
        );
        return json_decode($response->getBody()->getContents(), true);
    }


    /**
     * Post request
     *
     * @param string $path path url or full url.
     * @param array  $data data that will be store in the body
     *
     * @return mixed
     */
    private function _postWithSignature(string $path, array $data): mixed
    {
        $token = $this->provider->getAccessToken('client_credentials');
        $signature = $this->_sign($data);
        $response = $this->client->post(
            $path, [
            'json' => $data,
            'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Signature' => $signature,
            ]]
        );
        return json_decode($response->getBody()->getContents(), true);
    }
    
    /**
     * _sign
     *
     * @param array $data data that will be store in the body
     *
     * @return string
     */
    private function _sign($data): string
    {
        // $priv = $priv->withPadding(RSA::SIGNATURE_PKCS1); // Add this code to change the padding to PKCS1, By default, the padding is RSA::SIGNATURE_PSS

        $plaintext = json_encode($data);

        $keySize = $this->privKey->getLength() / 8; // key length in byte
        $hashed = hash('sha256', $plaintext, true); // hash with sha256 
        $hashLength = strlen($hashed); // length of hash 
        $saltLength = $keySize - 2 - $hashLength; // length of salt
        $priv = $this->privKey->withSaltLength($saltLength);

        $signature = $priv->sign($plaintext);
        return base64_encode($signature);
    }
}

