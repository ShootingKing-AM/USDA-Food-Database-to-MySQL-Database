# USDASR28 ASCII to MySQL PHP Script
A PHP script that automatically downloads and parses the USDA SR28 ASCII data files into a MySQL database.

The script runs as of now on PHP 7.1.0.

## Configurations

The following configs are available at the top of the script:

```php
const DOWNLOAD_SR28_PATH = "Path/To/Download/";
const SR28_DOWNLOAD_LINK = "https://www.ars.usda.gov/ARSUserFiles/80400525/Data/SR/SR28/dnload/sr28asc.zip";
const DB_HOST = "127.0.0.1";
const DB_USER = "root";
const DB_PASS = "";
const DATABASE_NAME = "fooddata";
const TABLE_PREFIX = "";
const TABLE_SUFIX = "";
//The array is formatted as follows {USDA SR28 filename, Name of the table, Number of records in the file}
const TABLE_NAME_SIZE = array(
	"SRC_CD.txt" , "SourceCode", 10,
  ...
);
```

The database will be created if it does not exist. 
Prefixes and sufixes can be added to the table names and it can be changed in the second column of the TABLE_NAME_SIZE array.

## Images

### Start screen
![alt text](https://imgur.com/a/uLpkp "Start screen")

### Fail screen
![alt text](https://imgur.com/PrUTp4Z "Fail screen")

### Success screen
![alt text](https://imgur.com/Mzce8fs "Success screen")

Any improvments or sugestions are welcome.
