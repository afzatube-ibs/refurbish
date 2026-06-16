<?php



/**

 * IBS Sync Connector — warehouse products (from_warehouse = 1) + variants/POIP images.

 * Route: index.php?route=api/ibs/products&api_token=...&page=1&limit=20

 */

class ControllerApiIbsProducts extends Controller

{

    public function index()

    {

        require_once DIR_SYSTEM . 'library/ibs/bootstrap.php';

        list($apiAuth, $apiResponse) = ibs_sync_api_services($this->registry);



        $this->load->model('api/ibs/product');



        $authError = $apiAuth->authenticate();

        if ($authError !== null) {

            $apiResponse->error($authError, 401);



            return;

        }



        $bridgeTable = $apiAuth->bridgeTable();

        $bridgeAvailable = $this->model_api_ibs_product->bridgeAvailable($bridgeTable);

        if (!$bridgeAvailable) {

            $apiResponse->send([

                'success' => false,

                'read_only' => true,

                'connector_version' => IBS_SYNC_CONNECTOR_VERSION,

                'bridge_available' => false,

                'error' => 'Dispatch Location bridge table not found. Product sync cannot safely identify supplier products.',

                'page' => $apiAuth->page(),

                'limit' => $apiAuth->limit(),

                'has_previous' => false,

                'has_next' => false,

                'products' => [],

            ], 503);



            return;

        }



        $page = $apiAuth->page();

        $limit = $apiAuth->limit();

        $result = $this->model_api_ibs_product->getPagedProducts($bridgeTable, $page, $limit);

        $total = (int) ($result['total'] ?? 0);

        $offset = ($page - 1) * $limit;



        $apiResponse->send([

            'success' => true,

            'read_only' => true,

            'connector_version' => IBS_SYNC_CONNECTOR_VERSION,

            'bridge_available' => true,

            'page' => $page,

            'limit' => $limit,

            'has_previous' => $page > 1,

            'has_next' => ($offset + $limit) < $total,

            'products' => $result['products'] ?? [],

        ]);

    }

}

