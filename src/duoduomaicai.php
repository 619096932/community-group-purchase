<?php

namespace Avicii\CommunityGroupPurchase;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class duoduomaicai extends BaseApiAbstract
{

    public string    $HOST = 'https://mc.pinduoduo.com/';
    public CookieJar $cookieJar;
    public Client    $Client;
    public           $BasicInfo;

    public function __construct($cookie)
    {
        $this->cookieJar = cookieJar::fromArray($cookie, 'mc.pinduoduo.com');
        $this->Client = new  Client([
                                        'cookies' => $this->cookieJar
                                    ]);
    }

    public function apiurl($uri)
    {
        return sprintf('%s%s', $this->HOST, $uri);
    }

    protected function readTheRequestedContent(\Psr\Http\Message\ResponseInterface $Response)
    {
        $data = json_decode($Response->getBody()->getContents());
        if ($data->success) {
            printf('请求数据如下,%s%s%s%s%s', PHP_EOL, PHP_EOL, json_encode($data->result, 256), PHP_EOL, PHP_EOL);
            return $data->result;
        }
        throw  new  \RuntimeException($data->errorMsg ?? $data->error_msg);
    }

    /**
     * 获取基础信息
     */
    public function querySupplierBasicInfo()
    {
        if (!$this->BasicInfo) {
            $data = $this->Client->get($this->apiurl('syndra-mms/supplier/querySupplierBasicInfo'));
            $this->BasicInfo = $this->readTheRequestedContent($data);
        }
        return $this->BasicInfo;

    }


    /**
     * @return array
     */
    public function storeInformation()
    {

        return [
            'platform'    => '多多买菜',
            'mall_id'     => $this->querySupplierBasicInfo()->supplierCommonInfo->mallId,
            'mall_logo'   => $this->querySupplierBasicInfo()->supplierCommonInfo->logo,
            'mall_name'   => $this->querySupplierBasicInfo()->supplierCommonInfo->mallName,
            'mall_status' => $this->querySupplierBasicInfo()->supplierCommonInfo->isOpen ? 1 : 0
        ];
    }

    /**
     * @return array
     */
    public function productInformationInTheRegion()
    {
        if (!($this->areaId ?? false)) {
            throw  new  \RuntimeException('请设置 areaId 属性');
        }
        $params = [
            'areaId'      => $this->areaId,
            'extraParams' => [
                'goodsOffset'   => 0,
                'productOffset' => 0,
            ],
            'idList'      => [],
            'isOnSale'    => $this->isOnSale ?? true,
            'name'        => '',
            'pageNum'     => 1,
            'pageSize'    => 20
        ];
        $list = [];
        $api_url = $this->apiurl('fission/functions/mms-faas/goods-aggregation');
        while (true) {
            $data = $this->readTheRequestedContent($this->Client->post($api_url, ['json' => $params]));
            $params['extraParams']['goodsOffset'] = $params['pageNum'] * 20;
            $params['pageNum']++;
            foreach ($data->list as $goods) {
                $obj = new \stdClass();
                $obj->goodsId = $goods->goodsId;
                $obj->goodsName = $goods->goodsName;
                $obj->hdThumbUrl = $goods->hdThumbUrl;
                $obj->specName = $goods->specName;
                $obj->supplierId = $goods->supplierId;
                $code69 = [];
                if (isset($goods->shortCode) && $goods->shortCode) {
                    $code69[] = $goods->shortCode;
                }

                if (isset($goods->goodsProperties->code69List) && $goods->goodsProperties->code69List && is_array($goods->goodsProperties->code69List)) {
                    $code69 = array_merge($code69, $goods->goodsProperties->code69List);
                }
                $obj->code_69_list = $code69;
                $obj->cat = $goods->categoryLevelInfo->cat1;
                $list[] = $obj;
            }
            if (count($data->list) < 20) {
                break;
            }
        }

        return $list;
    }


    /**
     * @return array
     */
    public function salesStatistics(): array
    {
        if (!($this->areaId ?? false)) {
            throw  new  \RuntimeException('请设置 areaId 属性');
        }
        if (!($this->warehouseIds ?? false)) {
            throw  new  \RuntimeException('请设置 warehouseIds 属性');
        }

        if (!($this->endSessionTime ?? false)) {
            throw  new  \RuntimeException('请设置 endSessionTime 属性');
        }
        if (!($this->startSessionTime ?? false)) {
            throw  new  \RuntimeException('请设置 startSessionTime 属性');
        }

        $params = [
            'endSessionTime'   => $this->endSessionTime,
            'areaId'           => $this->areaId,
            'page'             => 1,
            'pageSize'         => 100,
            'startSessionTime' => $this->startSessionTime,
            'warehouseIds'     => $this->warehouseIds
        ];

        $apiurl = $this->apiurl('cartman-mms/orderManagement/pageQueryDetail');

        $list = [];
        while (true) {
            $data = $this->readTheRequestedContent($this->Client->post($apiurl, ['json' => $params]));

            $params['page']++;
            foreach ($data->resultList as $goods) {
                // 需要处理分仓数据
                $obj = new \stdClass();
                $obj->productId = $goods->productId;
                $obj->areaId = $goods->areaId;
                $obj->warehouseId = $goods->warehouseId;
                $obj->productName = $goods->productName;
                $obj->productThumbUrl = $goods->productThumbUrl;
                $obj->planSales = $goods->salesPlan->planSales ?? '-';
                $obj->warehouseName = $goods->warehouseName;
                $obj->inboundTotal = $goods->quantityManageInfo->quantityInfo->inboundTotal;
                $obj->quantity = $goods->quantityManageInfo->quantityInfo->quantity;
                $obj->sellUnitTotal = $goods->sellUnitTotal;
                $obj->sellUnitName = $goods->sellUnitName;
                $obj->quotationInformation = [];
                if ($goods->specQuantityDetails ?? false) {
                    foreach ($goods->specQuantityDetails[0]->priceDetail ?? [] as $price) {
                        $price_obj = new \stdClass();
                        $price_obj->price = $price->supplierPrice / 100;
                        $price_obj->sellUnitTotal = $price->sellUnitTotal;
                        $obj->quotationInformation[] = $price_obj;
                    }
                }
                try {
                    //共享仓出库量
                    $obj->centerAllotInboundQuantity = $goods->quantityManageInfo->inventoryDetail->centerInventoryList[0]->centerAllotInboundQuantity ?? 0;
                    // 共享仓可用库存
                    $obj->shareDistributableInventory = $goods->quantityManageInfo->inventoryDetail->shareDistributableInventory;
                } catch (\Throwable $th) {
                    $obj->centerAllotInboundQuantity = 0;
                }

                try {
                    // 预约在途量
                    $obj->appointmentTotal = $goods->quantityManageInfo->appointmentData->appointmentTotal;
                } catch (\Throwable $th) {
                    $obj->appointmentTotal = 0;
                }

                $list[] = $obj;
            }
            if (count($data->resultList) < $params['pageSize']) {
                break;
            }
        }
        return $list;
    }


    /**
     */
    public function warehouseData()
    {
        // TODO: Implement warehouseData() method.
        $warehouseGroupVOList = $this->querySupplierBasicInfo()->warehouseGroupVOList;
        $warehouse = [];
        foreach ($warehouseGroupVOList as $key => $value) {
            foreach ($value->warehouseList as $list) {
                $list->areaName = $value->areaName;
                $list->warehouseGroupId = $value->warehouseGroupId;
                $list->warehouseGroupName = $value->warehouseGroupName;
                $list->areaId = $value->areaId;
                $warehouse[] = $list;
            }
        }

        return $warehouse;
    }
}
