<?php

namespace Lichen\CommunityGroupPurchase;

class init
{
    const DDMC    = '多多买菜';
    const MEITUAN = '美团优选';

    /** @var  BaseApiAbstract */
    public BaseApiAbstract $API;

    public function __construct(string $cookie, string $platform)
    {
        $api = [
            self::DDMC    => duoduomaicai::class,
//            self::MEITUAN => ''
        ];
        if (isset($api[$platform]) === false) {
            throw  new \RuntimeException('不支持的平台', 99876);
        }
        $this->API = new $api[$platform](self::CookieStrToArray($cookie));
    }

    protected static function CookieStrToArray($cookieStr): array
    {
        $cookie = [];
        foreach (explode('; ', $cookieStr) as $key => $value) {
            $a = explode('=', $value);
            $cookie[$a[0]] = $a[1];
        }
        return $cookie;

    }

}