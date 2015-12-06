<?php
/**
 * Seafile PHP API Client Example test file
 *
 * PHP version 5
 *
 * @author    Christoph Haas <christoph.h@sprinternet.at>
 * @copyright 2015 Christoph Haas <christoph.h@sprinternet.at>
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/h44z/Seafile-PHP-Client
 *
 * To run this example, at least 1 library with one subfolder containing a file has to exist!
 *
 * Required structure:
 *
 * /
 * |_ Library
 *     |_Folder
 *        |_Examplefile
 */

date_default_timezone_set("Europe/Vienna");

require_once __DIR__ . '/../class.seafileapiclient.php';

use Seafile\SeafileAPIClient;

$client = new SeafileAPIClient("https://seacloud.cc");
$client->getAPIToken("test@test.com", "test12345");
$client->initClient();


echo "<br><br>Starting LS<br>";
$lsdata = $client->ls("/");
foreach($lsdata as $path => $details) {
	printf("%s \t-- Name: %s, ID: %s, is encrypted: %s<br>\n", $path, $details["displayname"], $details["id"], $details["encrypted"] ? 'YES' : 'NO');
}

echo "<br><br>LS in first SUBLIB<br>";
reset($lsdata);
$lsdata2 = $client->ls(key($lsdata));
foreach($lsdata2 as $path => $details) {
	printf("%s \t-- Name: %s, ID: %s, is encrypted: %s<br>\n", $path, $details["displayname"], $details["id"], $details["encrypted"] ? 'YES' : 'NO');
}

echo "<br><br>LS in first SUBDIR<br>";
reset($lsdata2);
$lsdata3 = $client->ls(key($lsdata2));
foreach($lsdata3 as $path => $details) {
	printf("%s \t-- Name: %s, ID: %s, is encrypted: %s<br>\n", $path, $details["displayname"], $details["id"], $details["encrypted"] ? 'YES' : 'NO');
}

echo "<br><br>MKDIR in first SUBDIR<br>";
reset($lsdata2);
$path = key($lsdata2);
$result = $client->mkdir($path, "sampledir" . time());
echo "Parent: " . $path . ", Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>RM in SUBDIR<br>";
$lstmp = $client->ls($path);
$result = $client->rm(key($lstmp));
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>RENDIR in SUBDIR<br>";
next($lstmp);
$result = $client->rendir(key($lstmp), "renamed" . time());
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>MKDIR in ROOT<br>";
$result = $client->mkdir("/", "newlib" . time());
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>RM in ROOT<br>";
$lstmp = $client->ls("/");
$result = $client->rm(key($lstmp));
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>UPLOADING FILE<br>";
$lstmp = $client->ls("/");
$path = key($lstmp);
$newFilename = tempnam('.', 'UL_');
rename($newFilename, $newFilename . '.txt');
$newFilename .= '.txt';
file_put_contents($newFilename, 'Hello World: ' . date('Y-m-d H:i:s'));
$result = $client->upload($path,$newFilename);
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>DOWNLOADING FILE<br>";
$dlpath = ($path . "/" . basename($newFilename));
$result = $client->download($dlpath, dirname($newFilename) . DIRECTORY_SEPARATOR . "DL_". time(). ".txt");
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>COPYING FILE<br>";
$lstmp = $client->ls($path);
$result = $client->copy($dlpath, key($lstmp));
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>RENAMING FILE<br>";
$renName = ("RENFILE" . time() . ".txt");
$result = $client->renfile($dlpath, $renName);
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>MOVING FILE<br>";
$result = $client->move($path . "/" . $renName, key($lstmp));
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";


echo "<br><br>UPLOADING FILE with custom name<br>";
$custName = ("CustomFile" . time() . ".txt");
$result = $client->upload($path, $newFilename, $custName);
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

echo "<br><br>DOWNLOADING FILE with custom name<br>";
$dlpath = ($path . "/" . $custName);
echo "Dl: $dlpath<br>";
$result = $client->download($dlpath, dirname($newFilename) . DIRECTORY_SEPARATOR . "DLC_". time(). ".txt");
echo "Result" . ($result ? "OK" : "FAIL") . "<br>";

