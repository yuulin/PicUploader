<?php
/**
 * Created by PhpStorm.
 * User: bruce
 * Date: 2018-09-06
 * Time: 21:01
 */


namespace uploader;


use GuzzleHttp\Client;

class UploadImgur extends Upload{
    //Imgur的clientId，相当于在上面创建的一个应用的识别码
    public $clientId;
    //api url
    public $baseUrl;
    //代理url
    public $proxy;

    public static $config;
    //arguments from php client, the image absolute path
    public $argv;

    /**
     * Upload constructor.
     *
     * @param $config
     * @param $argv
     */
    public function __construct($config, $argv)
    {
	    $tmpArr = explode('\\',__CLASS__);
	    $className = array_pop($tmpArr);
	    $ServerConfig = $config['storageTypes'][strtolower(substr($className,6))];
	    
	    $this->clientId = $ServerConfig['clientId'];
	    $this->baseUrl = $ServerConfig['baseUrl'];
	    $this->proxy = $ServerConfig['proxy'] ?? '';

        $this->argv = $argv;
        static::$config = $config;
    }
	
	/**
	 * Upload images to Imgur
	 * @param $key
	 * @param $uploadFilePath
	 * @param $originFilename
	 *
	 * @return array
	 * @throws \GuzzleHttp\Exception\GuzzleException
	 */
	public function upload($key, $uploadFilePath, $originFilename){
		$fileSize = filesize($uploadFilePath);
		if($fileSize > 10000000){
			$imgWidth = isset(static::$config['imgWidth']) && static::$config['imgWidth'] ? static::$config['imgWidth'] : 0;
			if($imgWidth){
				$error = 'Due to https://sm.ms restriction, you can\'t upload photos lager than 5M, this photo is '.($fileSize/1000000).'M after compress.'."\n";
			}else{
				$error = "Due to https://sm.ms restriction, you can't upload photos lager than 5M, and you didn't set the compress option at the config file.\n";
			}
			
			$this->writeLog($error, 'error_log');
			return $error;
		}else{
			try {
				$GuzzleConfig = [
					'base_uri' => $this->baseUrl,
					'timeout'  => 10.0,
				];
				if($this->proxy){
					$GuzzleConfig['proxy'] = $this->proxy;
				}
				//实例化GuzzleHttp
				$client = new Client($GuzzleConfig);
				
				//上传
				$response = $client->request('POST', 'image', [
					'headers'=>[
						'Authorization' => 'Client-ID '.$this->clientId,
					],
					'multipart' => [
						[
							'name' => 'image',
							'contents' => fopen($uploadFilePath, 'r'),
						],
						[
							'name' => 'type',
							'contents' => 'file',
						],
						[
							'name' => 'name',
							'contents' => $key,
						],
						[
							'name' => 'title',
							'contents' => $originFilename,
						],
						[
							'name' => 'description',
							'contents' => $originFilename,
						],
					]
				]);
				
				$string = $response->getBody()->getContents();
				if($response->getReasonPhrase() != 'OK'){
					throw new \Exception($string);
				}else{
					$returnArr = json_decode($string, true);
					if($returnArr['success'] === true){
						$data = $returnArr['data'];
						$deleteLink = 'Delete Hash: '.$data['deletehash'];
						$link = [
							'link' => $data['link'],
							'delLink' => $deleteLink
						];
					}else{
						throw new \Exception(var_export($returnArr, true));
					}
				}
			} catch (\Exception $e) {
				//上传数错，记录错误日志(为了保证统一处理那里不出错，虽然报错，但这里还是返回对应格式)
				$link = [
					'link' => $e->getMessage()."\n",
					'delLink' => '',
				];
				$this->writeLog($link, 'error_log');
			}
			return $link;
		}
    }
}