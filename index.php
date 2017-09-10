<?php
/**
 * sr28tomysql.php
 *
 * USDA SR28 ASCII file to MySQL database
 *
 * @author     CSMC Projects
 * @copyright  2017 CSMC Projects
 * @license    https://opensource.org/licenses/MIT  MIT
 * @version    1.0.0
 * @link       
 */

declare(strict_types=1);

/**************************** CONFIGURATION BY THE USER ********************************/

const DOWNLOAD_SR28_PATH = "D:/Git/USDASR28/DB/";
const SR28_DOWNLOAD_LINK = "https://www.ars.usda.gov/ARSUserFiles/80400525/Data/SR/SR28/dnload/sr28asc.zip";
const DB_HOST = "127.0.0.1";
const DB_USER = "root";
const DB_PASS = "1234";
const DATABASE_NAME = "fooddata";
const TABLE_NAME_PREFIX = "";
const TABLE_NAME_SUFIX = "";
//The array is formatted as follows {USDA SR28 filename, Name of the table, Number of records in the file}
const TABLE_NAME_SIZE = array(
	"SRC_CD.txt" , "SourceCode", 10,
	"DERIV_CD.txt" , "DataDerivationCodeDescription", 55,
	"DATA_SRC.txt" , "SourcesOfData", 682,
	"FOOTNOTE.txt" , "Footnote", 552,
	"LANGDESC.txt" , "LanguaLFactorsDescription", 774,
	"NUTR_DEF.txt" , "NutrientDefinition", 150,
	"FD_GROUP.txt" , "FoodGroupDescription", 25,
	"FOOD_DES.txt" , "FoodDescription", 8789,
	"NUT_DATA1.txt" , "NutrientData", 200000,
	"NUT_DATA2.txt" , "NutrientData", 200000,
	"NUT_DATA3.txt" , "NutrientData", 200000,
	"NUT_DATA4.txt" , "NutrientData", 79046,
	"WEIGHT.txt" , "Weight", 15438,
	"LANGUAL.txt" , "LanguaLFactor", 38301,
	"DATSRCLN.txt", "SourcesOfDataLink", 244496
);

/**********************************************************************/
session_start();
ini_set('max_execution_time', '3000');
ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']);
ini_set('display_errors',"0");
error_reporting(0);

//Enum for log levels
abstract class LogLevel
{
    const Success = 0;
    const Error = 1;
	const Normal = 2;
}

//A simple class for logging progress
class Logger{
	//Add a new message to the logger
	public static function add(string $message, int $level){
		if(!isset($_SESSION["fooddata_log"])){$_SESSION["fooddata_log"] = array();}
		else{ $_SESSION["fooddata_log"][] = self::color($level, self::format($level, $message));}
	}
	//Format the message accordingly to the log level
	private static function format(int $level, string $log): string{
        //Format as you wish
        return  "{". (count($_SESSION["fooddata_log"])+ 1) ."} (".date("Y-m-d H:i:s").")\t".$log;
    }
	//Format the message to a certain color
	private static function color(int $level, string $formated_log): string {
        if($level == LogLevel::Success){
			return '<p style="color:white">'.$formated_log.'</p>';
		} else if($level == LogLevel::Error){
			return '<p style="color:black">'.$formated_log.'</p>';
		} else if($level == LogLevel::Normal){
			return '<p style="color:black">'.$formated_log.'</p>';
		}
	}
	//Clear the log
	public static function clear(){
		$_SESSION["fooddata_log"] = array();
	}
	//Show the log
	public static function show(){
		$logString = "<h1 style=\"font-family: monospace; margin: 5px 5px 0px 5px; padding: 5px; background: gold;\">
		Log</h1><div style=\"font-size: 10px; background-color: #E91E63; margin: 0px 5px 5px 5px; padding: 5px;\"><pre>";
		if(!isset($_SESSION["fooddata_log"])){ $_SESSION["fooddata_log"] = array();}
			if(count($_SESSION["fooddata_log"]) != 0){
				for($i = 0; $i < count($_SESSION["fooddata_log"]); $i++){
					$logString .= $_SESSION["fooddata_log"][$i]."";
				}
			} else {
				$logString .= self::format(LogLevel::Success, "Nothing to show.");
			}

			$logString .= "</pre></div>";
			echo $logString;
	}
}

//A simple database handler for performing requests
class omysqli{
	public $omysqli;
	public function __destruct(){
        //Only closes the omysqli connection has been open
           $this->omysqli->close();
    }
	public function sanitize($dirtyObj){
        $cleanObj = htmlentities(strip_tags($this->omysqli->real_escape_string($dirtyObj)));
        return $cleanObj;
    }
	public function  __construct(){
		$this->omysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, "fooddata");
		if($this->omysqli->connect_errno){
			$this->omysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, "");
            if(!self::boolExecute("CREATE DATABASE ".DATABASE_NAME)){
                Logger::add('Failed to create database. Make sure the user has permissions to create one.', LogLevel::Error);
            }
		}
	}
	public function countPExecute($query){
        //Use omysqli->prepare to set the query
		$stmt = $this->omysqli->prepare($query);
        if($stmt){
            if($stmt->execute()){
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();
                return $count;
            } else {
               $stmt->close();
               return false;
            }
        } else {
            return false;
        }
    }
	public function dataPExecute(string $query, string $rows){
        //Makes the omysqli query object
        $stmt = $this->omysqli->query($query);
        //If the query is executed successfully
        if($stmt){
            //Array that will return the results
            $return = array();
            //The name of the array keys, that are the names of the rows
            $arrayKeys = explode(',', $rows);
            $i = 0;
            //IMPORTANT: The array must be accessed at index 1
			$ii = 0;
            //For each row[i] => (colum[i])
			//Iterates over each row
            while($rows_f = $stmt->fetch_array(MYSQLI_ASSOC)){
				//Iterates over each column
                while($i < count($arrayKeys)){
                    $return[$ii][$arrayKeys[$i]] = $rows_f[$arrayKeys[$i]];
                    $i++;
                }
                $ii++;
                $i = 0;
            }
            //Frees the buffer from the data
            $stmt->free();
            //Returns the multidimensional array of the type array[i][colum_name]
            return $return;
        } else {
            return false;
        }
    }

	public function boolPExecute(string $query): bool {
		//Use omysqli->prepare to set the query
		$stmt = $this->omysqli->prepare($query);
        if($stmt){
            if($stmt->execute()){
                $stmt->close();
                return true;
            } else {
                $stmt->close();
				if($this->omysqli->errno){
					Logger::add('Mysql error: '.$this->omysqli->error, LogLevel::Error);
				}
                return false;
            }
        } else {
			if($this->omysqli->errno){
				Logger::add('Mysql error: '.$this->omysqli->error, LogLevel::Error);
			}
            return false;
        }
	}

	public function boolExecute(string $query): bool {
		if ($this->omysqli->query($query) == true) {
			return true;
		} else {
			if($this->omysqli->errno){
				Logger::add('Mysql error: '.$this->omysqli->error, LogLevel::Error);
			}
			return false;
		};
	}
}

abstract class Page
{
    const Fail = "/?Failed";
    const Done = "/?Done";
    const Self = "/";
}

//A class for redirecting pages
class Redirects{
	public static function to($page){
		echo '<script>window.onload = function () { setTimeout(function(){window.location.href="'.$page.'"},500); }</script>';
	}
}

//Class that handles download, processing, database creation and data insertion
class FoodDatabase{
	public static function NumberOfTables(): int{
		return count(TABLE_NAME_SIZE) / 3;
	}

	public static function CreateTables(): void
	{
		$omysqli = new omysqli();

		//Flag for the success of the table creation
		$fdt = false;

		//Create the Source Code table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'SourceCode'.TABLE_NAME_SUFIX.' (
			`Src_Cd` CHAR(2) PRIMARY KEY,
			`SrcCd_Desc` CHAR(60) NOT NULL
			)');
		self::TableSuccess('Source Code', $fdt);

		//Create the Data Derivation Code Description table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'DataDerivationCodeDescription'.TABLE_NAME_SUFIX.' (
			`Deriv_Cd` CHAR(4) PRIMARY KEY,
			`Deriv_Desc` CHAR(120) NOT NULL
			)');
		self::TableSuccess('Data Derivation Code Description', $fdt);

		//Create Sources of Data Link table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'SourcesOfData'.TABLE_NAME_SUFIX.' (
			`DataSrc_ID` CHAR(6) PRIMARY KEY,
			`Authors` CHAR(255),
			`Title` CHAR(255) NOT NULL,
			`Year` CHAR(4),
			`Journal` CHAR(135),
			`Vol_City` CHAR(16),
			`Issue_State` CHAR(5),
			`Start_Page` CHAR(5),
			`End_Page` CHAR(5)
				)');
		self::TableSuccess('Sources Of Data', $fdt);

		//Create the Footnote table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'Footnote'.TABLE_NAME_SUFIX.' (
			`NDB_No` CHAR(5) NOT NULL,
			`Footnt_No` CHAR(4) NOT NULL,
			`Footnt_Typ` CHAR(1) NOT NULL,
			`Nutr_No` CHAR(5),
			`Footnt_Txt` CHAR(200) NOT NULL
				)');
		self::TableSuccess('Footnote', $fdt);

		//Create the LanguaL Factor Description Format table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'LanguaLFactorsDescription'.TABLE_NAME_SUFIX.' (
			`Factor_Code` CHAR(5) PRIMARY KEY,
			`Description` CHAR(140) NOT NULL
			)');
		self::TableSuccess('LanguaL Factor Description', $fdt);

		//Create the Nutrient Definition table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'NutrientDefinition'.TABLE_NAME_SUFIX.' (
			`Nutr_No` CHAR(3) PRIMARY KEY,
			`Units` CHAR(7) NOT NULL,
			`Tagname` CHAR(20),
			`NutrDesc` CHAR(60) NOT NULL,
			`Num_Desc` CHAR(1) NOT NULL,
			`SR_Order` INT(6) UNSIGNED NOT NULL
				)');
		self::TableSuccess('Nutrient Definition', $fdt);

		//Create the food group description	table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'FoodGroupDescription'.TABLE_NAME_SUFIX.' (
								`FdGrp_Cd` CHAR(4) PRIMARY KEY,
								`FdGrp_Desc` CHAR(60) NOT NULL)
								');
		self::TableSuccess('Food Group Description', $fdt);

		//Create the food description table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'FoodDescription'.TABLE_NAME_SUFIX.' (
								`NDB_No` CHAR(5) PRIMARY KEY,
								`FdGrp_Cd` CHAR(4) NOT NULL,
								FOREIGN KEY (`FdGrp_Cd`) REFERENCES FoodGroupDescription(`FdGrp_Cd`),
								`Long_Desc` CHAR(200) NOT NULL,
								`Shrt_Desc` CHAR(60) NOT NULL,
								`ComName` CHAR(100),
								`ManufacName` CHAR(65),
								`Survey` CHAR(1),
								`Ref_desc` CHAR(135),
								`Refuse` DECIMAL(2),
								`SciName` CHAR(65),
								`N_Factor` DECIMAL(4,2),
								`Pro_Factor` DECIMAL(4,2),
								`Fat_Factor` DECIMAL(4,2),
								`CHO_Factor` DECIMAL(4,2),
								CONSTRAINT FOOD_DES_UK UNIQUE (NDB_No, FdGrp_Cd)
								)');
		self::TableSuccess('Food Description', $fdt);

		//Create the Nutrient Data table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'NutrientData'.TABLE_NAME_SUFIX.' (
								`NDB_No` CHAR(5) NOT NULL,
								`Nutr_No` CHAR(3) NOT NULL,
								`Nutr_Val` DECIMAL(10,3) NOT NULL,
								`Num_Data_Pts` DECIMAL(5,0) NOT NULL,
								`Std_Error` DECIMAL(8,3),
								`Src_Cd` CHAR(2) NOT NULL,
								`Deriv_Cd` CHAR(4),
								`Ref_NDB_No` CHAR(5),
								`Add_Nutr_Mark` CHAR(1),
								`Num_Studies` INT(2) UNSIGNED,
								`Min` DECIMAL(10,3),
								`Max` DECIMAL(10,3),
								`DF` INT(4) UNSIGNED,
								`Low_EB` DECIMAL(10,3),
								`Up_EB` DECIMAL(10,3),
								`Stat_cmt` CHAR(10),
								`AddMod_Date` CHAR(10),
								`CC` CHAR(1),
								CONSTRAINT NUT_DATA_PK PRIMARY KEY(`NDB_No`, `Nutr_No`),
								FOREIGN KEY (`NDB_No`) REFERENCES `FoodDescription` (`NDB_No`),
								FOREIGN KEY (`Nutr_No`) REFERENCES `NutrientDefinition`(`Nutr_No`)
								)');
		self::TableSuccess('Nutrient Data', $fdt);

		//Create the Weight table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'Weight'.TABLE_NAME_SUFIX.' (
								`NDB_No` CHAR(5) NOT NULL,
								`Seq` CHAR(2) NOT NULL,
								`Amount` DECIMAL(5,3) NOT NULL,
								`Msre_Desc` CHAR(84) NOT NULL,
								`Gm_Wgt` DECIMAL(7,1) NOT NULL,
								`Num_Data_Pts` INT(3) UNSIGNED,
								`Std_Dev` DECIMAL(7,3),
								CONSTRAINT WEIGHT_PK PRIMARY KEY(NDB_No,Seq),
								FOREIGN KEY (`NDB_No`) REFERENCES `FoodDescription` (`NDB_No`)
									)');
		self::TableSuccess('Weight', $fdt);

		//Create the LanguaL Factor table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'LanguaLFactor'.TABLE_NAME_SUFIX.' (
			`NDB_No` CHAR(5) NOT NULL,
			`Factor_Code` CHAR(5) NOT NULL,
			CONSTRAINT LANGUAL_PK PRIMARY KEY(NDB_No, Factor_Code),
			FOREIGN KEY (`NDB_No`) REFERENCES FoodDescription(`NDB_No`),
			FOREIGN KEY (`Factor_Code`) REFERENCES LanguaLFactorsDescription(`Factor_Code`)
			)');
		self::TableSuccess('LanguaL Factor', $fdt);

		//Create Sources of Data Link table
		$fdt = $omysqli->boolPExecute('CREATE TABLE IF NOT EXISTS '.TABLE_NAME_PREFIX.'SourcesOfDataLink'.TABLE_NAME_SUFIX.' (
								`NDB_No` CHAR(5) NOT NULL,
								`Nutr_No` CHAR(3) NOT NULL,
								`DataSrc_ID` CHAR(6) NOT NULL,
								CONSTRAINT DATSRCLN_PK PRIMARY KEY(NDB_No,Nutr_No,DataSrc_ID),
								FOREIGN KEY (`NDB_No`) REFERENCES `FoodDescription` (`NDB_No`),
								FOREIGN KEY (`Nutr_No`) REFERENCES `NutrientDefinition` (`Nutr_No`),
								FOREIGN KEY (`DataSrc_ID`) REFERENCES `SourcesOfData` (`DataSrc_ID`)
									)');
		self::TableSuccess('Sources Of Data Link', $fdt);
	}

	public static function PopulateDatabase(int $table): void
	{
		$omysqli = new omysqli();

		if($omysqli->boolExecute("LOAD DATA LOCAL INFILE '".DOWNLOAD_SR28_PATH.TABLE_NAME_SIZE[3*$table]."'
								INTO TABLE ".TABLE_NAME_SIZE[3*$table+1]."
								FIELDS TERMINATED BY '^'
								OPTIONALLY ENCLOSED BY '~'
								LINES TERMINATED BY '\\r\\n'") == true)
		{
			//Check if there are the correct number of records
			$record_num = $omysqli->countPExecute("SELECT COUNT(*) FROM ".TABLE_NAME_SIZE[3*$table+1]."");
			//Success message
			Logger::add('The file '.TABLE_NAME_SIZE[3*$table].' was successfully parsed with '.(int)$record_num.' out of '.TABLE_NAME_SIZE[3*$table+2].' records added to the '.TABLE_NAME_SIZE[3*$table+1].' table.', LogLevel::Success);
            Redirects::to("/?Process=ParseTable".($table+1));
        } else {
			Logger::add('Failed to parse the '.TABLE_NAME_SIZE[3*$table].' file with 0 out of '.TABLE_NAME_SIZE[3*$table+2].' records added to the '.TABLE_NAME_SIZE[3*$table+1].' table.', LogLevel::Error);
			Redirects::to("/?Process=Failed");
		}
	}

	public static function TableSuccess($table_name, &$fdt): void
	{
		if ($fdt){
			Logger::add('The '.$table_name.' was created successfully.', LogLevel::Success);
			$fdt = false;
		} else {
			Logger::add('Failed to create the '.$table_name.' table.', LogLevel::Error);
			Redirects::to("/?Process=Failed");
		}
	}

    public static function Download(): void{
        //Download the file
        $zipFile = DOWNLOAD_SR28_PATH."sr28data.zip";

        $down_time = microtime(true);

        $ch = curl_init (SR28_DOWNLOAD_LINK);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
        curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $rawdata=curl_exec($ch);
        curl_close ($ch);

        if(empty($rawdata) === true){
            Logger::add("Failed to download the USDA SR28 database.", LogLevel::Error);
            Redirects::to(Page::Fail);
            exit();
        } else {
            Logger::add("Download of USDA SR28 zip file completed in "
                .(microtime(true)-$down_time)." seconds.", LogLevel::Success);
        }

        //Save the file
        $myfile = fopen($zipFile, "wb");
        if($myfile !== false){
            fwrite($myfile, $rawdata);
            fclose($myfile);
        } else {
            Logger::add("Failed to save the downloaded file.", LogLevel::Error);
            Redirects::to(Page::Fail);
            exit();
        }

        //Open the zip file
        $zip = new ZipArchive;
        if($zip->open($zipFile) != true){
            Logger::add("Unable to unzip the USDA SR28 file.", LogLevel::Error);
            Redirects::to(Page::Fail);
            exit();
        }

        //Extract the zip File
        $zip->extractTo(DOWNLOAD_SR28_PATH);
        $zip->close();

        //Remove the zip file
        @unlink($zipFile);

        //Divide NUT_DATA into smaller file size and remove the original
        self::split_file(DOWNLOAD_SR28_PATH."NUT_DATA.txt", DOWNLOAD_SR28_PATH, 200000);
        @unlink(DOWNLOAD_SR28_PATH."NUT_DATA.txt");
    }

    public static function split_file($source, $targetpath, $lines=10){
        $i=0;
        $j=1;
        $buffer='';

        $handle = @fopen ($source, "r");
        if($handle != true){
            Logger::add("Failed to split the NUT_DATA.txt file.", LogLevel::Error);
            Redirects::to("/?Process=Failed");
            return;
        }
        while (!feof ($handle)) {
            $buffer .= @fgets($handle, 4096);
            $i++;
            if ($i >= $lines) {
                $fname = $targetpath."NUT_DATA".$j.".txt";
                self::saveToFile($buffer, $fname);
                $j++;
                $i=0;
            }
        }
        $fname = $targetpath."NUT_DATA".$j.".txt";
        self::saveToFile($buffer, $fname);
        fclose ($handle);
    }

    public static function saveToFile(&$buffer, $fname)
    {
        if (!$fhandle = @fopen($fname, 'w')) {
            Logger::add("Failed to open file ($fname).", LogLevel::Error);
            Redirects::to(Page::Fail);
            exit();
        }
        if (!@fwrite($fhandle, $buffer)) {
            Logger::add("Failed to write to ($fname).", LogLevel::Error);
            Redirects::to(Page::Fail);
            exit();
        }
        fclose($fhandle);
        $buffer = '';
    }

    public static function CreateDatabase(){
        //The constructor of this class creates the database so we can connect to it
        $omysqli = new omysqli();

		if($omysqli != NULL){
			Logger::add("Database created with name ".DATABASE_NAME.".", LogLevel::Success);
        } else {
			Logger::add('Failed to create the database '.DATABASE_NAME.'.
            Please make sure the user has the necessary credentials and the database does not exist already.', LogLevel::Error);
			Redirects::to(Page::Fail);
            exit();
		}
    }
}

//A class that tracks the current stage of the script processing
class Stage{
    public function __construct(){
        $_SESSION['STAGE'] = 0;
    }

    public static function SetNext(){
        $_SESSION['STAGE'] += 1;
    }

    public static function Get(): int{
        if(!isset($_SESSION['STAGE'])) $_SESSION['STAGE'] = 0;
        return $_SESSION['STAGE'];
    }

    public static function Reset(): void{
         $_SESSION['STAGE'] = 0;
         $_SESSION['LAST_PARSED'] = -1;
    }

    public static function GetParsing(): int{
        if(!isset($_SESSION['LAST_PARSED'])){
            $_SESSION['LAST_PARSED'] = -1;
        }
        return $_SESSION['LAST_PARSED'] += 1;
    }
}
?>


<!doctype html>
<html>
	<head>
		<title>USDA SR28 to MYSQL PHP Script</title>
	</head>
    <body style="font-family:monospace">
        <h1 style="font-family: monospace; margin: 5px 5px 0px 5px; padding: 5px; background: gold;">
            USDA SR28 to MYSQL PHP Script
        </h1>
    <?php
			echo '<div style="font-size: 14px; background-color: #E91E63; margin: 0px 5px 5px 5px; padding: 5px;">';
           if(isset($_GET['Done'])){
				echo '<p>Success all the tables were created and populated with the USDA SR28 data!</p>';
				echo '<p>Execution time: '.(microtime(true) - $_SESSION["EXECUTION_TIME"]).' seconds.</p>';
			} else if(isset($_GET['Failed'])){
				echo '<p>Failed to execute some task, check the log for more information.</p>';
				echo '<p>Execution time: '.(microtime(true) - $_SESSION["EXECUTION_TIME"]).' seconds.</p>';
                Stage::Reset();
			} else if (isset($_GET['Reset'])){
                Stage::Reset();
                Redirects::to(Page::Self);
            } else if(Stage::Get() == 0){
				Logger::clear();
                Stage::SetNext();
                echo '<p>This script will download and unzip the USDA SR28 database automatically and then create a database and the necessary tables.</p>';
                echo '<p>Make sure to open the file and configure the database information, download folder and others.</p>';
				echo '<p>Some datasets are quite large so be patient as they can take a while to process.</p>';
				echo '<a href="/index.php">
					<button style="cursor:auto;border:none; padding: 5px 15px 5px 15px; font-family:Fantasy;
					background-color:mediumturquoise;">Start</button></a>';
			} else if(Stage::Get() == 1){
                echo '<p>Downloading USDA SR28 ASCII data...</p>';
                Stage::SetNext();
                Redirects::to(Page::Self);
            } else if(Stage::Get() == 2){
                $_SESSION["EXECUTION_TIME"] = microtime(true);
                //FoodDatabase::Download();
                Stage::SetNext();
                Redirects::to(Page::Self);
            } else if(Stage::Get() == 3){
                echo '<p>Creating database...</p>';
                Stage::SetNext();
                Redirects::to(Page::Self);
            } else if(Stage::Get() == 4){
                FoodDatabase::CreateDatabase();
                Stage::SetNext();
                Redirects::to(Page::Self);
           } else if(Stage::Get() == 5){
               echo '<p>Creating tables...</p>';
               Stage::SetNext();
               Redirects::to(Page::Self);
           } else if (Stage::Get() == 6){
               FoodDatabase::CreateTables();
               Stage::SetNext();
               Redirects::to(Page::Self);
           } else if(Stage::Get() == 7){
                echo '<p>Parsing data into the MySQL database... This may take a while, but not much.</p>';
                Stage::SetNext();
                Redirects::to(Page::Self);
            } else if(Stage::Get() == 8) {
				$parsing_stage = Stage::GetParsing();
                if($parsing_stage >= 0 && $parsing_stage < FoodDatabase::NumberOfTables()){
                    FoodDatabase::PopulateDatabase((int)$parsing_stage);
                    Redirects::to(Page::Self);
                } else if($parsing_stage >= FoodDatabase::NumberOfTables()){
                    Redirects::to(Page::Done);
                }
			}
			echo '</div>';
			//Display log
			Logger::show();
    ?>
</body>
</html>
