# SteamMarketer v0.3
A PHP class to fetch asynchronously items from the Steam Market

## Requirements

- PHP 5.6 or greater
- cURL (php-curl) library (optional)
- MySql database ( optional )

## Installation

 1. Copy or clone the reporitory
 2. Place the SteamMarketer.php and config.php files in the same folder you like
 3. Include the SteamMarketer.php inside a dedicated page or in any place of your code
 4. Change the setting into the config.php to match your database installation
 
## Data and information

All data coming from this class it is publicly accessible. We do not :
- use any illegal action to fetch them
- interfere with service or security service of the website
- steal this data from any protected section of the website 

## Run the tool

 1. Create a new instance of the SteamMarketer : 
     ```
     $sm = new SteamMarketer();
        OR
     $sm = new SteamMarketer(pageNum); # When not specified pageNum it's set to 1st page
     ```
 2. Fetch content live or save it into your database
 
    ```
        $sm->getItems(searchString, imgSize);       # Live fetching
        $sm->storeIntoDb(searchString, start, count); # Store results into DB
    ```
 
 3. Fetch content live or save it into your database using cURL:
    ```
        $sm->getItems(searchString, imgSize)            # Live fetching via curl
        $sm->storeIntoDbCURL(searchString, start, count); # Store results into DB        
    ```
    
    Start it's the first element to be fetched, count it's the number of element after the starting point 

 4. Image size accept 3 type : small, medium, big as option
   
## Live fetching

When using live fetching, tool reads the Steam market page based on the number of pages you specify in the creation call.

Resulting bjects will be an array containing a set of items defined as follows:
```
    $sm->name     : Item name
    $sm->game     : Game that owns the item
    $sm->url      : Item market page url
    $sm->quantity : Items available on the market at that moment
    $sm->price    : Item starting price
```

## Persistence on DB

 Is it possible to store fetched items for <pageNum> pages, based on the number of pages you specify in the creation call,  into a predefined table. To store them execute:
```
$sm->storeIntoDb(searchString, start, end);
    OR
$sm->storeIntoDbCURL(searchString, start, end);
```
The function takes care of all the necessary steps to be able to store all information. Prior this, you have to define in the config.php file, your setting to be able to communicate with the database: 
 1. Choose your database deamon driver : default value mysql ( leave this if you don't know what to do )
 2. Set your host address : default value => "localhost" ( leave this if you don't know what to do )
 3. Set your database user
 4. Set your user password
 5. Set your database name
 6. Choose the tables prefix : default value => "steamMarket_" ( leave this if you don't know what to do )

A single table it's created following this structure :

```
  CREATE TABLE IF NOT EXISTS `steamMarket_items` (
      `id` int(11) AUTO_INCREMENT NOT NULL,
      `name` varchar(255) CHARACTER SET utf8 NOT NULL,
      `game` varchar(255) CHARACTER SET utf8 NULL,
      `url` longtext CHARACTER SET utf8 NULL,
      `img` longtext CHARACTER SET utf8 NULL,
      `quantity` int(11) NULL,
      `price` double NULL,
      PRIMARY KEY (`name`),
      KEY `id` (`id`)
  );             
```

## Limitations

Since Steam website can limitate your call/access to their market page a waiting time it's set ( 1 second ) between each call. You can easily adjust it changing this value by editing its value on SteamMarketer.php@row:78. We suggest to keep as it is if you want to fetch several page ( 5-10 at once ). If you want to fetch just 1 page you can lower it. Be aware that when Steam limits it, script won't be able to fetch data from the website and you will have to wait before being able to read them

# DISCLAIMER

 All contents fetched,loaded,read from Steam are protected by copyright and trademarks by Steam, the software owner and/or third party license . Please check [Legal](http://store.steampowered.com/legal/), [Privacy Policy](http://store.steampowered.com/privacy_agreement/), [User Agreement](http://store.steampowered.com/subscriber_agreement/) for further information
 
 I'm not responsible for any issue nor any kind of legal affair that this component could or would cause at your person/business.