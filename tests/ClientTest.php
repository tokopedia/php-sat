<?php declare(strict_types=1);
/**
 * ClientTest class for testing Client class
 *
 * PHP version 8.2
 *
 * @category Test
 * @package  Sat\ClientSdk
 * @author   egon12 <egon.firman@bytedance.com>
 * @license  MIT https://opensource.org/license/MIT
 * @link     https://github.com/tokopedia/php-sat
 */

use PHPUnit\Framework\TestCase;
use Sat\ClientSdk\Client;
use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;

/**
 * ClientTest class for testing Client class
 *
 * @category Test
 * @package  Sat\ClientSdk
 * @author   egon12 <egon.firman@bytedance.com>
 * @license  MIT https://opensource.org/license/MIT
 * @link     https://github.com/tokopedia/php-sat
 */
class ClientTest extends TestCase
{
    protected static MockWebServer $server;

    /**
     * Set up before class
     *
     * @return void
     */
    public static function setUpBeforeClass() : void
    {
        self::$server = new MockWebServer;
        self::$server->start();
    }

    /**
     * Tear down after class
     *
     * @return void
     */
    public static function tearDownAfterClass() : void
    {
        self::$server->stop();
    }

    /**
     * Testing new Client
     *
     * @return void
     */
    public function testNewClient(): void
    {
        $c = new Client(
            [
            'client_id' => 'foo',
            'client_secret' => 'bar',
            ]
        );
        $this->assertInstanceOf(Client::class, $c);
    }


    /**
     * Testing new Client but without client_id and client_secret
     *
     * @return void
     */
    public function testNewClientFailed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $c = new Client();
    }



    /**
     * Test ProductList
     *
     * @return void
     */
    public function testProductList(): void 
    {
        $authRes = '{"access_token":"c:a2fCGFN3S-eSsxX5eZchZQ"}';
        self::$server->setResponseOfPath('/token', new Response($authRes));

        $rawRes = '{"data":[{"type":"product","id":"25k-xl","attributes"'.
        ':{"is_inquiry":false,"price":25100,"product_name":"XL 25,000"'.
        ',"status":1}},{"type":"product","id":"15k-xl","attributes":'.
        '{"is_inquiry":false,"price":15600,"product_name":"XL 15,000",'.
        '"status":1}}]}';
        self::$server->setResponseOfPath('/v2/product-list', new Response($rawRes));


        $c = new Client(
            [
            'client_id' => 'foo',
            'client_secret' => 'bar',
            'base_url' => self::$server->getServerRoot(),
            'token_url' => self::$server->getServerRoot() . '/token',
            ]
        );

        $products = $c->productList()["data"];

        $product0 = [
        "type"=>"product",
        "id"=>"25k-xl",
        "attributes"=>[
        "is_inquiry"=>false,
        "price"=>25100,
        "product_name"=>"XL 25,000" ,
        "status"=>1
        ]
        ];
    
        $this->assertEquals($product0, $products[0]);
    }


    /**
     * Testing inquiry
     *
     * @return void
     */
    public function testInquiry(): void
    {
        $authRes = '{"access_token":"c:a2fCGFN3S-eSsxX5eZchZQ"}';
        self::$server->setResponseOfPath('/token', new Response($authRes));


        $rawRes = '{"data":{"type":"inquiry","id":"0811111111",'.
        '"attributes":{"admin_fee":2500,"base_price":'.
        '49250,"client_name":"BUD* SANTO**","client_number"'.
        ':"0811111111","fields":null,"inquiry_result":'.
        '[{"name":"Nama PDAM","value":"Kota Cirebon"},'.
        '{"name":"No. Pelanggan","value":"0811111111"},'.
        '{"name":"Nama","value":"BUD* SANTO**"},{"name"'.
        ':"Total Tagihan","value":"Rp51.750"},{"name":'.
        '"Periode","value":"DEC21"},{"name":"Pemakaian"'.
        ',"value":"10 M3"},{"name":"Jumlah Tagihan","value"'.
        ':"Rp49.250"},{"name":"Biaya Admin","value":'.
        '"Rp2.500"}],"meter_id":"","product_code":'.
        '"pdam-jabar-kota-cirebon","sales_price":51750}}}';

        self::$server->setResponseOfPath('/v2/inquiry', new Response($rawRes));

        $c = new Client(
            [
            'base_url' => self::$server->getServerRoot(),
            'token_url' => self::$server->getServerRoot() . '/token',
            'client_id' => 'foo',
            'client_secret' => 'bar',
            ]
        );
        $res = $c->inquiry('pdam-jabar-kota-cirebon', '0811111111');

        $this->assertEquals(
            [
            'data' => [
            'id' => '0811111111',
            'type' => 'inquiry',
            'attributes' => [
            'product_code' => 'pdam-jabar-kota-cirebon',
            'client_number' => '0811111111',
            'fields' => null,
            'admin_fee' => 2500,
            'base_price' => 49250,
            'client_name' => 'BUD* SANTO**',
            'inquiry_result' => [
            ['name' => 'Nama PDAM'      ,'value' => 'Kota Cirebon' ],
            ['name' => 'No. Pelanggan'  ,'value' => '0811111111'   ],
            ['name' => 'Nama'           ,'value' => 'BUD* SANTO**' ],
            ['name' => 'Total Tagihan'  ,'value' => 'Rp51.750'     ],
            ['name' => 'Periode'        ,'value' => 'DEC21'        ],
            ['name' => 'Pemakaian'      ,'value' => '10 M3'        ],
            ['name' => 'Jumlah Tagihan' ,'value' => 'Rp49.250'     ],
            ['name' => 'Biaya Admin'    ,'value' => 'Rp2.500'      ],
            ],
            'meter_id' => '',
            'sales_price' => 51750,
            ]],
            ], $res
        );

        $req = self::$server->getLastRequest();
        $this->assertEquals(
            '{"data":{"id":"0811111111","type":"inquiry",'. 
            '"attributes":{"product_code":"pdam-jabar-kota-cirebon",'.
            '"client_number":"0811111111","fields":[]}}}', 
            $req->getInput(),
        );
    }

 
    /**
     * Testing Checkout
     *
     * @return void
     */
    public function testCheckout(): void
    {

        $authRes = '{"access_token":"c:a2fCGFN3S-eSsxX5eZchZQ"}';
        self::$server->setResponseOfPath('/token', new Response($authRes));

$rawRes = '{"data":{"id":"order123111","type":"order","attributes'.
'":{"product_code":"pdam-jabar-kota-cirebon","client_nu'.
'mber":"0811111111","fields":null,"error_code":"","erro'.
'r_detail":"","fulfilled_at":null,"partner_fee":100,"sa'.
'les_price":51750,"serial_number":"","status":"Pending"}}}';
        self::$server->setResponseOfPath('/v2/order', new Response($rawRes));

        $client = new Client(
            [
            'client_id' => 'foo',
            'client_secret' => 'foo',
            'base_url' => self::$server->getServerRoot(),
            'token_url' => self::$server->getServerRoot() . '/token',
            'private_key' => '-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAmY/kTrmX7uZCTY/qXmyVwLpB554iIkibeUhUzOOTLKwMAgrQ
z+2LuNhkSd5UrXf4nhW3uNBqcWnAZCMLaQbVUYhzvmjM5EjpK6L5Lix5Wwo7I4on
JFgdvPGiMYMh0IJKfW6N6vgtczBuUVPj44LGcpq6g1PKcN2c4vJ9kFTpKRO1Kacz
IO5BNNJvvnkY0z9pDUcAtAMzdvTnf9LViGJ3hPHITMVaN9hQNELYHpPy9GrG7ykL
txk3/ncDfwvzhlyK74k1MwRq02q9EU+z0m3q+3NHSvqMFthTaYfBm8jfAE3UaHoI
FfSD966NEXfO/r3/b0QsQrJOiV55QjFHcWrFhwIDAQABAoIBAEiguYZSWP1wgeNV
Ma+/A4THkuwM6m/0jzPpddIBwFXceUFuBByTaQXfsm8NbFcu6rM56k7Ko81ICupR
eNAPn0yUkMl5n45IvZ8Z0Wh5OFxKlnXUnXafBlGapu9r6c5IERsJ8q0y+6wDN+nX
F20/KMSDUbtTIegHqG/d6G0e+7elEuV2gSE4zXmjdtFgzbIn5NKMlwuOZGj0B8Cf
LpP2NQpk1RAImIluf5fHLf5DF5jy34zYAwie3D/wxpNx/l5Z/phffBj/0GUXSThJ
5MUlA9CvBZkd99jjPu9TBhRkFNbSZnxoWBlZWIwVSaFpEhxF596Yg/lqthlQQI9R
K4L0WOECgYEAy/6gDRguHPnAH7EtWQjv1Q34cB6UQNeVuq9rET3tStU5JWLQLlgA
FXAwJ8I59wGNfRceCahXou9m99CuSbtubpO/cE9EbNxV5xFuL8vAT8ZQ9JU/epBp
65Ok9jT6HbTvlgacq4lRFCcRH5XiTanrgex0TVkx6eq+g9napuJc0e0CgYEAwLXe
bRkWcZUFsGElTWS13hxu9d9bdNugcwtZ7lp5VDFwr/THrOX24QUZWQASUAa7Y4x1
hM+blURXlPqkeoO7wVs+DcOjopyY1K58ezaZcjH44F6NCmYQHu+KA4kU6hqDbpeo
ZljkLDXbUu6gvdfYTbP3l6te5eQuKlXsOenOlsMCgYBV5A3rtYSk+ptkPKuFU3f3
0vwJ6TYu3xbSFc4U4mgpHAIFtcdF3BOc8zGza9oQIH08cCFbm5/aoMZQDXN05BAp
SthOJ3H+C/+3XOVyBm4gqLWpZbXmmyud3vqUF9Y/79D48CvDJfwXaiORkwBIBwV8
HN0TPD0B6q7wwSeJIMJIOQKBgQCGoUgwHbvBRCQCUgv2Yqpv7ptSaGWDYUBZvw9n
5osm15drRe4Ni2cLUz2fIN6qS9m0NVeQnl2KTYGGUgiAkvGjprPWd9wk6ZQX2YKb
rcxLrD+7uDJ+lkki46QezjDvT/CMXaVHQ0i83i9IY++mUVoLBvStYArfPqdF6lsr
Jn2ucwKBgQCoDl3HnDcKrOxecqRIDPo8zo/aAyvYPTkR0UPEm6ExlZa1IWgWNCSy
SV8DpCl8ISqoDLIPy1HB8NxkyRrWKO2ClewP/+e+0dEw/clZ8n8aWtWD/eFDjqmv
0jUwnrzrFaabRzKIJmL5aDvlFpy2wfOpfAoO3e69XfmuRHbccaCR1g==
-----END RSA PRIVATE KEY-----',
            //'base_url' => 'http://localhost:8000',
            ]
        );

        $res = $client->checkout(
            'order123111',
            'pdam-jabar-kota-cirebon',
            '0811111111',
            51750,
            [],
        );

        $this->assertEquals(
            [
            'data' => [
            'id' => 'order123111',
            'type' => 'order',
            'attributes' => [
            'product_code' => 'pdam-jabar-kota-cirebon',
            'client_number' => '0811111111',
            'fields' => null,
            'error_code' => '',
            'error_detail' => '',
            'fulfilled_at' => null,
            'partner_fee' => 100,
            'sales_price' => 51750,
            'serial_number' => '',
            'status' => 'Pending',
            ]]], $res
        );

        $req = self::$server->getLastRequest();
        $this->assertEquals(
            '{"data":{"type":"order","id":"order123111","attributes":'.
            '{"product_code":"pdam-jabar-kota-cirebon","client_number"'.
            ':"0811111111","amount":51750,"fields":[]}}}',
            $req->getInput()
        );

	$this->assertNotEmpty($req->getHeaders()['Signature']);
    }


    /**
     * Testing Checkout
     *
     * @return void
     */
    public function testCheckStatus(): void
    {
        $authRes = '{"access_token":"c:a2fCGFN3S-eSsxX5eZchZQ"}';
        self::$server->setResponseOfPath('/token', new Response($authRes));

        $rawRes = '{"data":{"id":"order12311","type":"order","attributes"'.
        ':{"product_code":"pdam-jabar-kota-cirebon","client_num'.
        'ber":"0811111111","fields":null,"error_code":"","error'.
        '_detail":"","fulfilled_at":"2024-06-24T03:09:27Z","ful'.
        'fillment_result":[{"name":"Wilayah","value":"Kota Cire'.
        'bon"},{"name":"Nomor Pelanggan","value":"0811111111"},'.
        '{"name":"Nama","value":"BUDI SANTOSO"},{"name":"Alamat'.
        '","value":"HRPN\/BAKTI VI\/59\/89"},{"name":"Total Tag'.
        'ihan","value":"Rp49.250"},{"name":"No.Reff","value":"1'.
        '23412341234"},{"name":"Biaya Admin","value":"Rp2.500"}'.
        ',{"name":"Total Bayar","value":"Rp51.750"},{"name":"Pe'.
        'riode","value":"DEC21"},{"name":"Tagihan","value":"Rp4'.
        '9.250"}],"partner_fee":100,"sales_price":51750,"serial'.
        '_number":"123412341234","status":"Success","admin_fee"'.
        ':2500,"client_name":"BUDI SANTOSO","voucher_code":""}}}';
        self::$server->setResponseOfPath('/v2/order/order12311', new Response($rawRes));

        $client = new Client(
            [
            'client_id' => 'foo',
            'client_secret' => 'foo',
            'base_url' => self::$server->getServerRoot(),
            'token_url' => self::$server->getServerRoot() . '/token',
            ]
        );
        $res = $client->checkStatus('order12311');


        $this->assertEquals(
            [
            'data' => [
            'id' => 'order12311',
            'type' => 'order',
            'attributes' => [
            'product_code' => 'pdam-jabar-kota-cirebon',
            'client_number' => '0811111111',
            'fields' => null,
            'error_code' => '',
            'error_detail' => '',
            'fulfilled_at' => null,
            'fulfilled_at' => '2024-06-24T03:09:27Z',
            'fulfillment_result' => [
            ['name' => 'Wilayah'         ,'value' => 'Kota Cirebon'           ],
            ['name' => 'Nomor Pelanggan' ,'value' => '0811111111'             ],
            ['name' => 'Nama'            ,'value' => 'BUDI SANTOSO'           ],
            ['name' => 'Alamat'          ,'value' => 'HRPN/BAKTI VI/59/89'    ],
            ['name' => 'Total Tagihan'   ,'value' => 'Rp49.250'               ],
            ['name' => 'No.Reff'         ,'value' => '123412341234'           ],
            ['name' => 'Biaya Admin'     ,'value' => 'Rp2.500'                ],
            ['name' => 'Total Bayar'     ,'value' => 'Rp51.750'               ],
            ['name' => 'Periode'         ,'value' => 'DEC21'                  ],
            ['name' => 'Tagihan'         ,'value' => 'Rp49.250'               ],
            ],
            'partner_fee' => 100,
            'sales_price' => 51750,
            'serial_number' => '123412341234',
            'status' => 'Success' ,
            'admin_fee' => 2500  ,
            'client_name' => 'BUDI SANTOSO'  ,
            'voucher_code' => '' ,
            ]]], $res
        );
    }
}
