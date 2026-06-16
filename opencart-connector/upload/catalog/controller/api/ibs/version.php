<?php



/**

 * IBS Sync Connector — lightweight version endpoint.

 * Route: index.php?route=api/ibs/version&api_token=...

 */

class ControllerApiIbsVersion extends Controller

{

    public function index()

    {

        require_once DIR_SYSTEM . 'library/ibs/bootstrap.php';

        list($apiAuth, $apiResponse) = ibs_sync_api_services($this->registry);



        $this->load->model('api/ibs/connector');



        $authError = $apiAuth->authenticate();

        if ($authError !== null) {

            $apiResponse->error($authError, 401);



            return;

        }



        $apiResponse->send([

            'success' => true,

            'read_only' => true,

            'connector_version' => IBS_SYNC_CONNECTOR_VERSION,

            'version' => IBS_SYNC_CONNECTOR_VERSION,

            'opencart_version' => defined('VERSION') ? (string) VERSION : '',

            'compatibility' => $this->model_api_ibs_connector->getCompatibilityReport(),

        ]);

    }

}

