<?php
/**
 * SteamMarket v0.1b
 * Class can fetch popular items from the Steam Market pages
 */

include(__DIR__.'/config.php');

class SteamMarketer
{
    private $strLang;
    private $pageElement = [];

    private $arrItem;
    private $pages;
    private $pdo;

    /**
     * SteamMarket constructor.
     * Initialize the class and start fetching content from Steam Market page
     * Since we can fetch multiple page per call it's possible to specify the number of pages to retrieve
     * @param int $intPage
     */
    public function __construct($intPage = 1)
    {
        $this->strLang = 'english';
        if(is_null($intPage) || $intPage==1) {
            $this->pages = 1;
        } else {
            $this->pages = intval(preg_replace("/[^0-9]/",'',$intPage));
        }
        $this->loadContent();
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
     */
    private function loadContent() {
        for ($i=0;$i<$this->pages;$i++) {
            $elem = $i*10;
            $fileName = 'https://steamcommunity.com/market/search/?query=&start='.$elem.'&count=10&search_descriptions=0'.
                        '&sort_column=popular&sort_dir=desc&l=' . $this->strLang;
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
     * @param string $strSize
     * @return mixed
     */
    public function getItems($strSize = 'small') {
        $this->fetchPopularItems($strSize);
        return $this->arrItem;
    }

    /**
     * List available Steam portal languages
     * @return array
     */
    public function getLanguages()
    {
        return array('danish', 'dutch', 'english', 'finnish', 'french', 'german', 'greek', 'hungarian', 'italian', 'japanese', 'koreana', 'norwegian', 'polish', 'portuguese',
            'brazilian', 'romanian', 'russian', 'schinese', 'spanish', 'swedish', 'tchinese', 'thai', 'turkish', 'ukrainian', 'czech');
    }

    /**
     * Set page results language
     * @param $strLang
     */
    public function setLang($strLang)
    {
        $this->strLang = $strLang;
        $this->loadContent();
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