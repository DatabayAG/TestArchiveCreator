<?php


use ILIAS\FileDelivery\Delivery;
use ILIAS\HTTP\Services;

/**
 * Class ilTestArchiveCreatorAssetsDelivery
 * @see ilWebAccessCheckerDelivery
 */
class ilTestArchiveCreatorAssetsDelivery
{
    protected Services $http;

    /**
     * Constructor.
     */
    public function __construct(Services $httpState)
    {
        $this->http = $httpState;
    }

    /**
     * Handle the request for an asset
     */
    public function handleRequest(): void
    {
        // Set error reporting
        ilInitialisation::handleErrorReporting();

        // Find and deliver asset
        try {
            $ini = new ilIniFile("../ilias.ini.php");
            $ini->read();
            $data_dir = $ini->readVariable("clients", "datadir");
            $client_id = $ini->readVariable("clients", "default");

            // url is .../assets.php/obj_id/temp_id/name
            $parts = explode('/', $this->http->request()->getUri()->getPath());
            $count = count($parts);
            $obj_id = (int) ($parts[$count - 3] ?? '');
            $temp_id = (string) ($parts[$count - 2] ?? '');
            $name = basename($parts[$count - 1] ?? '');

            // check if file exists in asset directory and deliver it
            $asset_dir = $data_dir . '/' . $client_id . '/temp/tarc_ui/' . $obj_id . '/' . $temp_id . '/tst_' . $obj_id . '/assets';
            if (is_dir($asset_dir)) {
                $assets = array_diff(scandir($asset_dir), ['.', '..']);
                if (in_array($name, $assets)) {
                    $this->deliver($asset_dir . '/' . $name);
                }
            }


        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }


    /**
     * Send an error response
     */
    protected function error(string $message): void
    {
        $response = $this->http->response()->withStatus(500);

        /** @var \Psr\Http\Message\StreamInterface $stream */
        $stream = $response->getBody();
        $stream->write($message);

        $this->http->saveResponse($response);
        $this->http->sendResponse();
    }


    /**
     * Deliver a file
     */
    protected function deliver(string $path, string $disposition = 'inline'): void
    {
        $delivery = new Delivery($path, $this->http);
        $delivery->setCache(true);
        $delivery->setDisposition($disposition);
        $delivery->deliver();
    }
}
