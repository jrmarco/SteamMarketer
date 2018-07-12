<?php
/**
 * SteamMarket v0.3
 *
 * rev 0.3 - Add cURL operations support
 * rev 0.2 - Add search parameter to functions, removed language support
 * rev 0.1 - Release
 *
 * Class can fetch popular items from the Steam Market pages
 */

include(__DIR__.'/config.php');

class SteamMarketer
{
    private $pageElement = [];

    private $arrItem;
    private $pages;
    private $pdo;
    private $total_count;

    /**
     * SteamMarket constructor.
     * Initialize the class and start fetching content from Steam Market page
     * Since we can fetch multiple page per call it's possible to specify the number of pages to retrieve
     * @param int $intPage
     */
    public function __construct($intPage = 1)
    {
        if(is_null($intPage) || $intPage==1) {
            $this->pages = 1;
        } else {
            $this->pages = intval(preg_replace("/[^0-9]/",'',$intPage));
        }
    }

    /**
     * Store the resulting items after page fetching
     * @param string $strSize
     */
    public function storeIntoDb($strSize = 'small') {
        $this->initDB();
        $this->initTable();

        $this->fetchPopularItems($strSize);

        foreach ($this->arrItem as $item) {
            global $db;

            $sql = "INSERT INTO `".$db['prefix']."items` (name,game,url,img,quantity,price) ".
                "values (:name,:game,:url,:img,:quantity,:price) ".
                "ON DUPLICATE KEY UPDATE ".
                "game=:game,url=:url,img=:img,quantity=:quantity,price=:price;";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':name',utf8_encode($item->name),PDO::PARAM_STR);
            $stmt->bindValue(':game',utf8_encode($item->game),PDO::PARAM_STR);
            $stmt->bindValue(':url',$item->url,PDO::PARAM_STR);
            $stmt->bindValue(':img',$item->img,PDO::PARAM_STR);
            $stmt->bindValue(':quantity',$item->quantity,PDO::PARAM_INT);
            $stmt->bindValue(':price',$item->price,PDO::PARAM_STR);
            try {
                $stmt->execute();
            } catch(Exception $e) {
                print_r($e->getError());
                die();
            }
        }
    }

    /**
     * Load content from Steam Market pages. There's a waiting time of 1 second between one page and the other to avoid block
     * caused by too many connections at the same time
     * @param $strSearch Search string
     */
    private function loadContent($strSearch) {
        for ($i=0;$i<$this->pages;$i++) {
            $elem = $i*10;
            $fileName = 'https://steamcommunity.com/market/search/?query=&start='.$elem.'&count=10&search_descriptions=0'.
                '&sort_column=popular&sort_dir=desc';
            if(!is_null($strSearch)) {
                $fileName = 'https://steamcommunity.com/market/search?q='.$strSearch.'&start='.$elem.'&count=10&search_descriptions=0'.
                    '&sort_column=popular&sort_dir=desc';
            }
            $this->pageElement[$i] = file($fileName);
            sleep(1);
        }
    }

    /**
     * Fetching method to parse and compose Items data from the Steam Market pages
     * @param $strSize
     */
    private function fetchPopularItems($strSize) {
        for($i=0;$i<$this->pages;$i++) {
            foreach ($this->pageElement[$i] as $key => $string) {

                if (preg_match("/market_listing_row_link/", $string)) {

                    $item = new stdClass();
                    $item->name = ''; $item->game = 'Steam event'; $item->url = '';
                    $item->quantity = ''; $item->price = '';

                    $strDiv = $this->pageElement[$i][$key];
                    $strImg = $this->pageElement[$i][$key+2];

                    if(strpos("/data-hash-name/",$strDiv)==-1) {
                        continue;
                    }

                    $rawLink = explode("href=\"",$strDiv);
                    $rawLink = explode("\"",$rawLink[1]);
                    $item->url = trim($rawLink[0]);

                    $rawName = str_replace('https://steamcommunity.com/market/listings/','',$rawLink[0]);
                    $rawName = explode("/",$rawName);
                    $item->name = urldecode($rawName[1]);

                    $rawImg = explode("src=\"",$strImg);
                    $rawImg = explode("\"",$rawImg[1]);
                    $rawImg = substr($rawImg[0],0,count($rawImg[0])-8);
                    $size = null;
                    switch ($strSize) {
                        case 'small' : $size = '64fx64f'; break;
                        case 'medium' : $size = '128fx128f'; break;
                        case 'big' : $size = '256fx256f'; break;
                    }
                    $item->img = $rawImg.$size;

                    $item->quantity = intval(preg_replace("/[^0-9]/",'',trim(strip_tags($this->pageElement[$i][$key+6]))));
                    $rawPrice = preg_replace("/[^0-9.]/",'',trim(strip_tags($this->pageElement[$i][$key+12])));
                    $item->price = doubleval($rawPrice);

                    if (!empty($item)) {
                        $this->arrItem[$item->name] = $item;
                    }
                }
            }
        }

        for($i=0;$i<$this->pages;$i++) {
            foreach ($this->pageElement[$i] as $key => $string) {
                if (preg_match("/market_listing_item_name_block/", $string)) {
                    $itemName = trim(strip_tags($this->pageElement[$i][$key + 1]));

                    if(isset($this->arrItem[$itemName])) {
                        $this->arrItem[$itemName]->game = trim(strip_tags($this->pageElement[$i][$key + 3]));
                    }
                }
            }
        }
    }

    /**
     * Retrieve composed items
     * @param string $strSearch Search string
     * @param string $strSize Image size
     * @return mixed
     */
    public function getItems($strSearch = '',$strSize = 'small') {
        $this->loadContent($strSearch);
        $this->fetchPopularItems($strSize);
        return $this->arrItem;
    }

    /**
     * Fetching solution using cUrl library
     * @param string Item name
     * @param int Number of items to skip
     * @param int Number of items to parse
     * @return mixed
     */
    public function getItemsCURL($strSearch = '',$start = 0, $end = 10) {
        $url     = $this->itemsList($strSearch, $start, $end);
        $result  = $this->curlCall($url);
        if(empty($result) || is_null($result)) {
            return [];
        } else {
            $jsonObj   = json_decode($result);
            $formatted = [];

            foreach ($jsonObj->results as $obj) {
                $item = new stdClass();

                $item->name     = $obj->name;
                $item->game     = $obj->app_name;
                $item->url      = "https://steamcommunity.com/market/listings/".$obj->asset_description->appid."/".str_replace("+",'%20',urlencode($obj->hash_name));
                $item->quantity = $obj->sell_listings;
                $item->price    = preg_replace("/[^0-9.]/",'',$obj->sell_price_text);
                $item->img      = "https://steamcommunity-a.akamaihd.net/economy/image/".$obj->asset_description->icon_url;

                $formatted[] = $item;
            }

            if(empty($jsonObj) || is_null($jsonObj)) {
                $this->total_count = -1;
            } else {
                $this->total_count = intval($jsonObj->total_count);
            }

            return $formatted;
        }
    }

    /**
     * Return items count for the given search
     * @param string $strSearch
     * @return int
     */
    public function countTotalItems() {
        return $this->total_count;
    }

    /**
     * Generate cUrl call URL based on provided parameters
     * @param string Item name
     * @param int Number of items to skip
     * @param int Number of items to parse
     * @return string
     */
    private function itemsList($strSearch, $start, $end) {
        return 'https://steamcommunity.com/market/search/render/?query='.$strSearch
            .'&start='.$start.'&count='.$end.'&search_descriptions=0&sort_column=popular&sort_dir=desc&norender=1';
    }

    /**
     * Generate random token for cUrl call
     * @return bool|string
     */
    private function generateToken() {
        $token = '';
        for($i=0;$i<5;$i++) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            for ($i = 0; $i < 6; $i++) {
                $token .= $characters[rand(0, $charactersLength - 1)];
            }
            $token .= '-';
        }
        $token = substr($token,0,-1);

        return $token;
    }

    /**
     * Perform cUrl call
     * @param URL generated string
     * @return mixed
     */
    private function curlCall($strUrl) {

        $curl = curl_init();

        $token = $this->generateToken();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $strUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "Token-call: ".$token
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            print_r($err);
            return $err;
        } else {
            return $response;
        }
    }

    /**
     * Store fetched data via cURL into db
     * @param string Item name
     * @param int Number of items to skip
     * @param int Number of items to parse
     */
    public function storeIntoDbCURL($strSearch = '', $start = 0, $end = 10) {
        global $db;

        $this->initDB();
        $this->initTable();

        $arrItems = $this->getItemsCURL($strSearch, $start, $end);

        if(empty($arrItems) || is_null($arrItems->results)) {
            die('403 Too many requests');
        }

        foreach ($arrItems->results as $item) {
            $sql = "INSERT INTO `".$db['prefix']."items` (name,game,url,img,quantity,price) ".
                "values (:name,:game,:url,:img,:quantity,:price) ".
                "ON DUPLICATE KEY UPDATE ".
                "game=:game,url=:url,img=:img,quantity=:quantity,price=:price;";

            $price = preg_replace("/[^0-9.]/",'',$item->sell_price_text);

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':name',$item->name,PDO::PARAM_STR);
            $stmt->bindValue(':game',$item->app_name,PDO::PARAM_STR);
            $stmt->bindValue(':url',"https://steamcommunity.com/market/listings/".$item->asset_description->appid."/".str_replace("+",'%20',urlencode($item->hash_name)),PDO::PARAM_STR);
            $stmt->bindValue(':img',"https://steamcommunity-a.akamaihd.net/economy/image/".$item->asset_description->icon_url,PDO::PARAM_STR);
            $stmt->bindValue(':quantity',$item->sell_listings,PDO::PARAM_INT);
            $stmt->bindValue(':price',$price,PDO::PARAM_STR);
            try {
                $stmt->execute();
            } catch(Exception $e) {
                print_r($e->getError());
                die();
            }
        }
    }

    /**
     * Initialize the DB connection
     * Database parameters must be inserted into config.php file to be able to connect and use it
     */
    private function initDB()
    {
        global $db;
        try {
            $this->pdo = new PDO ($db['daemon'] . ':host=' . $db['host'] . ';dbname=' . $db['dbname'], $db['user'], $db['psw']);
        } catch (PDOException $e) {
            print_r($e->getMessage());
            die('PDO Init error');
        }
    }

    /**
     * Create necessary table to store data into a DB
     */
    private function initTable() {
        global $db;

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$db['prefix'].'items` (
              `id` int(11) AUTO_INCREMENT NOT NULL,
              `name` varchar(255) CHARACTER SET utf8 NOT NULL,
              `game` varchar(255) CHARACTER SET utf8 NULL,
              `url` longtext CHARACTER SET utf8 NULL,
              `img` longtext CHARACTER SET utf8 NULL,
              `quantity` int(11) NULL,
              `price` double NULL,
              PRIMARY KEY (`name`),
              KEY `id` (`id`)
            );';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stmt = null;
        } catch(Exception $e) {
            print_r($e->getMessage());
            die('Cannot create table');
        }
    }
}

?>