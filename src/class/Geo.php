<?php
namespace Leo;

/**
 * 地理位置相关类
 * User: leo
 * Date: 2016/7/2
 * Time: 14:52
 */
class Geo
{
    // 高德的类型对应关系
    private $_gaode_catelog_mapping = [
        10102 => '加油站',
        50000 => '蛋糕店',
        60200 => '便利店',
        50111 => '北京菜',
        50100 => '中餐',
        60404 => '综合超市',
        70000 => '生活服务',
        71300 => '快照冲印',
        80000 => '休闲娱乐',
        90000 => '医疗',
        90100 => '一甲医院',
        90102 => '社区医院',
        90300 => '诊所',
        90400 => '急救中心',
        190301 => '道路',
        991400 => '门',
        991401 => '门/南',
        100100 => '商务酒店',
        100103 => '四星级酒店',
        100200 => '旅馆招待所',
        110101 => '公园',
        120000 => '住宅区',
        120201 => '写字楼',
        120203 => '住宅区',
        120300 => '住宅区/1',
        120302 => '住宅区/2',
        120303 => '宿舍',
        141201 => '高校',
        141201 => '中学',
        141202 => '中学',
        141203 => '小学',
        141206 => '职业学校',
        150700 => '公交站',
        150904 => '路边停车场',
        150905 => '地下停车场',
        150907 => '停车场入口',
        150500 => '地铁站',
        190306 => '桥',
        190403 => '门牌地址',
        190700 => '商圈',

    ];

    private $_city_mapping = [
        '北京市' => [
            'gaode_id' => 110000,
            'baidu_id' => 131,
        ],
        '广州市' => [
            'gaode_id' => 440100,
            'baidu_id' => 257,
        ]
    ];

    private $_gaode_arr_borough = ['120000', '120201', '120203',
                                   '120300', '120302', '120303', '190403'];

    private function _get_gaode_city_id($city_name)
    {
        try {
            if (empty($city_name)) {
                throw new \Exception('城市参数为空');
            }
            $city = $this->_city_mapping[$city_name];
            if (empty($city)) {
                throw new \Exception("【{$city_name}】该城市尚未开通");
            }

            return $city['gaode_id'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 获取百度的城市id
    private function _get_baidu_city_id($city_name)
    {
        try {
            if (empty($city_name)) {
                throw new \Exception('城市参数为空');
            }
            $city = $this->_city_mapping[$city_name];
            if (empty($city)) {
                throw new \Exception("【{$city_name}】该城市尚未开通");
            }

            return $city['baidu_id'];
        } catch (\Exception $e) {
            throw $e;
        }
    }


    public function getGeo($word, $city_name, $categorys = [])
    {
        try {
            try {
                $list = $this->_getGaodeList($word);
                $geo = $this->_getGaodeMatchGeo($list, $this->_gaode_arr_borough);
                $geo = $this->_convertCoord($geo);
            } catch (\Exception $e) {
                $geo = $this->getBaiduGeo($word, '北京');
            }
            return $geo;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 根据坐标获取地址
    public function getAddrByGeo($geo)
    {
        try {
            if (!isset($geo['lng'], $geo['lat'])) {
                throw new \Exception('参数值为空');
            }

            $mercator = $this->lngLatToMercator($geo);
            $url = "http://api.map.baidu.com/?qt=rgc&x={$mercator['lng']}&y={$mercator['lat']}";

            $str_res = curl_get($url);
            $arr_res = json_decode($str_res, true);

            $addr = $arr_res['content']['address'];

            return $addr;
        } catch (\Exception $e) {
        }
    }

    // 获取地址
    public function getAddr($word, $city_name, $categorys = [])
    {
        try {

            $list = $this->_getGaodeList($word, $city_name);
            $address = $this->_getGaodeMatchAddr($list, $categorys);

            if (empty($address)) {
                $address = $this->getBaiduAddr($word, $city_name);
            }

            return $address;
        } catch (\Exception $e) {
            $address = $this->getBaiduAddr($word, $city_name);
            return $address;
            // throw $e;
        }
    }

    public function getBaiduAddr($word, $city_name, $categorys = [25])
    {
        try {
            $url_word = urlencode($word);
            $city_id = $this->_get_baidu_city_id($city_name);
            $url = "http://map.baidu.com/?qt=s&wd={$url_word}&c={$city_id}";
            $addr = '';

            $str_res = curl_get($url);
            $arr_res = json_decode($str_res, true);

            $list = $arr_res['content'];

            if (empty($list)) {
                $addrs = $arr_res['addrs'];
                if(isset($addrs[0]['addr'])) {
                    $addr = $addrs[0]['addr'];
                }
            }

            if (empty($addr)) {
                if ($word == $list[0]['name']) {
                    $addr = $list[0]['addr'];
                }
            }

            if (empty($addr)) {
                foreach ($list as $v) {
                    if (in_array($v['catalogID'],$categorys)) {
                        $addr = $v['addr'];
                        if (empty($addr)) {
                            break;
                        }
                    }
                }
            }

            if (empty($addr)) {
                foreach ($list as $v) {
                    $cla =  array_column($v['cla'], 0);
                    if (!empty(array_intersect($cla, $categorys))) {
                        $addr = $v['addr'];
                        if (empty($addr)) {
                            break;
                        }
                    }
                }
            }

            if (empty($addr)) {
                foreach ($list as $v) {
                    $cla =  array_column($v['cla'], 0);
                    if (!empty(array_intersect($cla, $categorys))) {
                        $addr = $v['addr'];
                        if (empty($addr)) {
                            break;
                        }
                    }
                }
            }

            if (empty($addr)) {
                foreach ($list as $v) {
                    $addr = $v['addr'];
                    if (empty($addr)) {
                        break;
                    }
                }
            }

            return $addr;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 获取高德匹配类型的坐标值
    private function _getGaodeMatchAddr($list, $categorys = [])
    {
        try {
            foreach ($list as $v) {
                if(empty($categorys) || in_array($v['category'],$categorys)) {
                    return $v['address'];
                }
            }
            throw new \Exception('搜索结果中无匹配类型的数据');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 获取高德匹配类型的坐标值
    private function _getGaodeMatchGeo($list, $categorys = [])
    {
        try {
            foreach ($list as $v) {
                if(empty($categorys) || in_array($v['category'],$categorys)) {
                    return [
                        'lng' => $v['x'],
                        'lat' => $v['y']
                    ];
                }
            }
            throw new \Exception('搜索结果中无匹配类型的数据');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 获取高德的搜索列表
    private function _getGaodeList($word, $city_name)
    {
        try {
            $city_id = $this->_get_gaode_city_id($city_name);
            $word = urlencode($word);
            $url = "http://ditu.amap.com/service/poiTipslite?&city={$city_id}&words={$word}";
            $str_res = getHtml($url);
            $return = [];

            if (empty($str_res)) {
                throw new \Exception("url请求错误！【{$url}}】");
            }

            $arr_res = json_decode($str_res, true);

            if (1 != $arr_res['status']) {
                return new \Exception($arr_res['data'], $arr_res['status']);
            }

            if (1 != $arr_res['data']['code'] || 0 == $arr_res['data']['total']) {
                return new \Exception('查询失败');
            }

            $return  = array_column($arr_res['data']['tip_list'], 'tip');

            if (empty($return)) {
                return new \Exception('数据为空');
            }
            return $return;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 截取百度的一个api
    public function getBaiduGeo2($word, $city, $categorys = [25])
    {
        try {
            $city_id = $this->_get_baidu_city_id($city);
            // 检测并获取城市id
            $url_word = urlencode($word);

            // 请求接口内容
            $url = "http://map.baidu.com/?qt=s&wd={$url_word}&c={$city_id}";

            $arr_res = json_decode(curl_get($url), true);

            $list = $arr_res['content'];

            if (empty($mercator)) {
                if ($word == $list[0]['name']) {
                    $mercator = [
                        'lng' => $list[0]['x'] / 100,
                        'lat' => $list[0]['y'] / 100
                    ];
                }
            }

            if (empty($mercator)) {
                foreach ($list as $v) {
                    if (in_array($v['catalogID'],$categorys)) {
                        $mercator = [
                            'lng' => $v['x'] / 100,
                            'lat' => $v['y'] / 100
                        ];
                        break;
                    }
                }
            }

            if (empty($mercator)) {
                foreach ($list as $v) {
                    $cla =  array_column($v['cla'], 0);
                    if (!empty(array_intersect($cla, $categorys))) {
                        $mercator = [
                            'lng' => $v['x'] / 100,
                            'lat' => $v['y'] / 100
                        ];
                        break;
                    }
                }
            }

            if (empty($mercator)) {
                foreach ($list as $v) {
                    $mercator = [
                        'lng' => $v['x'] / 100,
                        'lat' => $v['y'] / 100
                    ];
                    break;
                }
            }

            $geo = $this->mercatorToLngLat($mercator);
            return $geo;
        } catch (\Exception $e) {
            return null;
        }

    }


    // 获取百度的经纬度
    public function getBaiduGeo($word, $city)
    {
        try {
            // 检测并获取城市id
            $word = urlencode($word);

            // 请求接口内容
            $url = "http://api.map.baidu.com/geocoder/v2/?ak=aqLgbABLabxT9csGOEhrjDFM&output=json&address={$word}&city={$city}";

            $res = json_decode(curl_get($url), true);

            if (0 != $res['status']) {
                throw new \Exception('查询百度经纬度接口失败');
            }

            $geo = $res['result']['location'];

            return $geo;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 百度坐标转换成经纬度
    private function _convertCoord($geo, $from = 3, $to = 5)
    {
        try {
            // 判空处理
            if (!isset($geo['lng'], $geo['lat'])) {
                throw new \Exception('参数为空');
            }

            $url = "http://api.map.baidu.com/geoconv/v1/?coords={$geo['lng']},{$geo['lat']}&from={$from}&to={$to}&ak=EF06cfb26173665ad80b8edf6a328192";
            $res = json_decode(file_get_contents($url), true);

            if (0 != $res['status']) {
                throw new \Exception("百度坐标转换接口请求失败，失败信息（{$res['message']}}）");
            }

            $geo['lng'] = $res['result'][0]['x'];
            $geo['lat'] = $res['result'][0]['y'];

            return $geo;
        } catch (\Exception $e) {
            throw $e;
        }
    }


    // 获取小区相关的分类id
    private function _getCatelogId()
    {
        try {
            return array_keys($this->_catelog_mapping);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 检查city参数是否合法
    private function _checkCityParam($city)
    {
        try {
            if (empty($this->_city_mapping[$city])) {
                throw new \Exception('city参数不合法');
            } else {
                return $this->_city_mapping[$city];
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }



    private function pixelTopoint($point, $zoom, $center, $bounds) {
        // 像素到坐标
        if (!$point) {
            return;
        }
        $zoomUnits = $this->getzoomUnits($zoom);
        $mercatorlng = $center['lng'] + $zoomUnits * ($point['x'] - $bounds['width'] / 2);
        $mercatorLat = $center['lat'] - $zoomUnits * ($point['y'] - $bounds['height'] / 2);
        $mercatorlngLat = ['lng' =>  $mercatorlng, 'lat' =>  $mercatorLat];
        return $this->mercatorToLngLat($mercatorlngLat);
    }

    private function pointToPixel ($point, $zoom, $mcenter, $bounds) {
        // 坐标到像素
        if (!$point) {
            return;
        }
        $point = $this->lngLatToMercator($point);
        $units = $this->getzoomUnits($zoom);
        $x = round(($point['lng'] - $mcenter['lng']) / $units + $bounds['width'] / 2);
        $y = round(($mcenter['lat'] - $point['lat']) / $units + $bounds['height'] / 2);
        return [
            'x' => $x,
            'y' => $y
        ];
    }
 
    private function getzoomUnits($zoom) {
        return pow(2, (18 - $zoom));
    }
 
    private function  mercatorToLngLat ($mLngLat) {
        $absLngLat;
        $mc;
        $absLngLat = [
            'lng' => abs($mLngLat['lng']),
            'lat' => abs($mLngLat['lat'])
        ];
        for ($i = 0; $i < count($this->MCBAND); $i++) {
            if ($absLngLat['lat'] >= $this->MCBAND[i]) {
                $mc = $this->MC2LL[$i];
                break;
            }
        }
        $lngLat = $this->convertor($mLngLat, $mc);
        $lngLat = [
            'lng' =>  round($lngLat['lng'], 6),
            'lat' =>  round($lngLat['lat'], 6)
        ];
        return $lngLat;
    }
 
    public function  lngLatToMercator ($point) {
        $point['lng'] = $this->getLoop($point['lng'], -180, 180);
        $point['lat'] = $this->getRange($point['lat'], -74, 74);
        $lng_lat = [
            'lng' => $point['lng'],
            'lat' => $point['lat']
        ];
        for ($i = 0; $i < count($this->LLBAND); $i++) {
            if ($lng_lat['lat'] >= $this->LLBAND[$i]) {
                $mc = $this->LL2MC[$i];
                break;
            }
        }
        if (!$mc) {
            for ($i = count($this->LLBAND) - 1; $i >= 0; $i--) {
                if ($lng_lat['lat'] <= -$this->LLBAND[$i]) {
                    $mc = $this->LL2MC[$i];
                    break;
                }
            }
        }
        $cE = $this->convertor($point, $mc);
        $lng_lat = [
            'lng' => round($cE['lng'], 2),
            'lat' => round($cE['lat'], 2)
        ];
        return $lng_lat;
    }
 
    private function  getLoop ($lng, $a, $b) {
        while ($lng >$b) {
            $lng -= $b - $a;
        }
        while ($lng <$a) {
            $lng +=$b -$a;
        }
        return $lng;
    }
 
    function getRange ($lat,$a,$b) {
        if ($a != null) {
            $lat = max($lat,$a);
                 }
        if ($b != null) {
            $lat = min($lat,$b);
                 }
        return $lat;
    }
 
    function  convertor ($point, $mc) {
        if (!$point || !$mc) {
            return;
        }
        $lng =$mc[0] +$mc[1] * abs($point['lng']);
        $c = abs($point['lat']) /$mc[9];
        $lat =$mc[2] +$mc[3] *$c +$mc[4] *$c *$c +$mc[5] *$c *$c *$c +$mc[6] *$c *$c *$c *$c +$mc[7] *$c *$c *$c *$c *$c +$mc[8] *$c *$c *$c *$c *$c *$c;
        $lng *= ($point['lng'] < 0 ? -1 : 1);
        $lat *= ($point['lat'] < 0 ? -1 : 1);
        return ['lng'=>$lng,'lat' =>$lat];
    }
 
    private $MCBAND = [12890594.86, 8362377.87, 5591021, 3481989.83, 1678043.12, 0];
    private $LLBAND = [75, 60, 45, 30, 15, 0];
    private $MC2LL = [
        [1.410526172116255e-8, 0.00000898305509648872, -1.9939833816331, 200.9824383106796, -187.2403703815547, 91.6087516669843, -23.38765649603339, 2.57121317296198, -0.03801003308653, 17337981.2],
        [ - 7.435856389565537e-9, 0.000008983055097726239, -0.78625201886289, 96.32687599759846, -1.85204757529826, -59.36935905485877, 47.40033549296737, -16.50741931063887, 2.28786674699375, 10260144.86], 
        [ - 3.030883460898826e-8, 0.00000898305509983578, 0.30071316287616, 59.74293618442277, 7.357984074871, -25.38371002664745, 13.45380521110908, -3.29883767235584, 0.32710905363475, 6856817.37],
        [ - 1.981981304930552e-8, 0.000008983055099779535, 0.03278182852591, 40.31678527705744, 0.65659298677277, -4.44255534477492, 0.85341911805263, 0.12923347998204, -0.04625736007561, 4482777.06],
        [3.09191371068437e-9, 0.000008983055096812155, 0.00006995724062, 23.10934304144901, -0.00023663490511, -0.6321817810242, -0.00663494467273, 0.03430082397953, -0.00466043876332, 2555164.4],
        [2.890871144776878e-9, 0.000008983055095805407, -3.068298e-8, 7.47137025468032, -0.00000353937994, -0.02145144861037, -0.00001234426596, 0.00010322952773, -0.00000323890364, 826088.5]
    ];
    private $LL2MC = [
        [ - 0.0015702102444, 111320.7020616939, 1704480524535203, -10338987376042340, 26112667856603880, -35149669176653700, 26595700718403920, -10725012454188240, 1800819912950474, 82.5],
        [0.0008277824516172526, 111320.7020463578, 647795574.6671607, -4082003173.641316, 10774905663.51142, -15171875531.51559, 12053065338.62167, -5124939663.577472, 913311935.9512032, 67.5],
        [0.00337398766765, 111320.7020202162, 4481351.045890365, -23393751.19931662, 79682215.47186455, -115964993.2797253, 97236711.15602145, -43661946.33752821, 8477230.501135234, 52.5],
        [0.00220636496208, 111320.7020209128, 51751.86112841131, 3796837.749470245, 992013.7397791013, -1221952.21711287, 1340652.697009075, -620943.6990984312, 144416.9293806241, 37.5],
        [ - 0.0003441963504368392, 111320.7020576856, 278.2353980772752, 2485758.690035394, 6070.750963243378, 54821.18345352118, 9540.606633304236, -2710.55326746645, 1405.483844121726, 22.5],
        [ - 0.0003218135878613132, 111320.7020701615, 0.00369383431289, 823725.6402795718, 0.46104986909093, 2351.343141331292, 1.58060784298199, 8.77738589078284, 0.37238884252424, 7.45]
    ];

}