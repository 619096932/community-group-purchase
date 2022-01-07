<?php

namespace Avicii\CommunityGroupPurchase;
abstract class  BaseApiAbstract
{
    /**
     * 获取店铺信息
     * @return array
     */
    abstract public function storeInformation();

    /**
     * 区域内商品信息
     * @return array
     */
    abstract public function productInformationInTheRegion();


//    /**
//     * 商品每日供货价信息
//     * @return array
//     */
//    abstract public function dailySupplyPriceOfGoods();

    /**
     * 每日销售数据
     * @return array
     */
    abstract public function salesStatistics();




    /**
     * 仓库数据(中心仓)
     * @return array
     */
    abstract public function warehouseData();

}