<?php

namespace Incapption\PdfService;

use RuntimeException;
use GuzzleHttp\Client;
use Incapption\Helper\Crypto;
use GuzzleHttp\Exception\GuzzleException;

class PdfService
{
    /**
     * @var string
     */
    private $hmacSecret;

    private const SERVICE_URL = 'https://pdf.incapption.io/api/v1/print/';

    public function __construct(string $hmacSecret)
    {
        $this->hmacSecret = $hmacSecret;
    }

    /**
     * @param array  $liquidData Data for the liquid template
     * @param string $liquidTemplate The full liquid template as string
     * @param string $saveFilePath The full path where the file should be saved, including file name and extension
     *
     * @return string The path to the html file
     * @throws GuzzleException
     */
    public function generateHtml(array $liquidData, string $liquidTemplate, string $saveFilePath) : string
    {
        if (file_exists($saveFilePath))
        {
            throw new RuntimeException('The file already exists: '.$saveFilePath);
        }

        $client = $this->configureClient();

        $data = [
            'filename' => basename($saveFilePath),
            'data' => $liquidData,
            'template' => $liquidTemplate
        ];

        $response = $client->request('POST', 'html', [
            'headers' => [
                'X-Hmac-Sha256' => Crypto::hmacFromArray($data, $this->hmacSecret)
            ],
            'json' => $data
        ]);

        $contents = $response->getBody()->getContents();
		$results = json_decode($contents, true);

		file_put_contents($saveFilePath, $results['html']);

        return $saveFilePath;
    }

    /**
     * @param array  $liquidData Data for the liquid template
     * @param string $liquidTemplate The full liquid template as string
     * @param string $saveFilePath The full path where the file should be saved, including file name and extension
     *
     * @return string The path to the pdf file
     * @throws GuzzleException
     */
    public function generatePdf(array $liquidData, string $liquidTemplate, string $saveFilePath) : string
    {
        if (file_exists($saveFilePath))
        {
            throw new RuntimeException('The file already exists: '.$saveFilePath);
        }

        $client = $this->configureClient();

        $data = [
            'filename' => basename($saveFilePath),
            'data' => $liquidData,
            'template' => $liquidTemplate
        ];

        try
		{
			$client->request('POST', 'pdf', [
				'headers' => [
                    'X-Hmac-Sha256' => Crypto::hmacFromArray($data, $this->hmacSecret)
                ],
				'json' => $data,
                'sink' => $saveFilePath
			]);

			return $saveFilePath;
		}
		catch (GuzzleException $e)
		{
		    if (file_exists($saveFilePath))
            {
                unlink($saveFilePath);
            }

			throw $e;
		}
    }

    /**
     * Serves a file to the client using standard file transfer. Exits PHP when transfer is completed.
     *
     * @param string $filePath The local file path
     * @param bool $unlink Delete the local file after serving it
     */
    public function serveFile(string $filePath, bool $unlink = false)
    {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($filePath).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        flush();

        readfile($filePath);

        if ($unlink)
        {
            unlink($filePath);
        }

        exit;
    }

    private function configureClient() : Client
    {
        return new Client([
			'base_uri' => self::SERVICE_URL,
			'timeout'  => 5.0,
		]);
    }
}