<?php
/**
 * Seafile PHP API Client
 *
 * PHP version 5
 *
 * @category  API
 * @package   Seafile
 * @author    Christoph Haas <christoph.h@sprinternet.at>
 * @copyright 2015 Christoph Haas <christoph.h@sprinternet.at>
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/h44z/Seafile-PHP-Client
 */

namespace Seafile;

require_once __DIR__ . "/vendor/autoload.php";

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Monolog\Logger;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Seafile\Http\Client;
use Seafile\Resource\Directory;
use Seafile\Resource\File;
use Seafile\Resource\Library;
use Seafile\Resource\Multi;

class SeafileAPIClient {
    /**
     * @var Client Seafile API Client
     */
	private $seafcl = null;

    /**
     * @var array Holding references to Library/Directory/Files/Multi resource
     */
	private $res = [];

    /**
     * @var string Authentication token
     */
	private $token = "";

    /**
     * @var string Server base URL
     */
	private $server = "";

    /**
     * @var HandlerStack Stack for Guzzle
     */
	private $stack;

    /**
     * @var Logger Logger for Guzzle
     */
	private $logger;

    /**
     * @var array Library cache
     */
	private $libraries = [];

    /**
     * SeafileAPIClient constructor.
     *
     * @param string $server Server base URL
     */
	public function __construct($server) {
		$this->server = $server;
	}

    /**
     * Initialise the client.
     *
     * @param array $config Config for the client
     * @return bool|Client
     */
	public function initClient($config = null) {
		$this->logger = new Logger('Logger');

		$this->stack = HandlerStack::create();
		$this->stack->push(
			Middleware::log(
				$this->logger,
				new MessageFormatter("{hostname} {req_header_Authorization} - {req_header_User-Agent} - [{date_common_log}] \"{method} {host}{target} HTTP/{version}\" {code} {res_header_Content-Length} req_body: {req_body} response_body: {res_body}")
			)
		);

		if(!$this->token) {
			return false;
		}

		if(!$config) {
			$config = [
				'base_uri' => "https://seacloud.cc",
				'debug' => false,
				'handler' => $this->stack,
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Token ' . $this->token
				]
			];
		}

		// always add the auth token
		if(is_array($config) && isset($config["headers"])) {
			$config["headers"]["Authorization"] = 'Token ' . $this->token;
		}

		// always add the stack handler
		if(is_array($config) && !isset($config["handler"])) {
			$config["handler"] = $this->stack;
		}

		$this->seafcl = new Client($config);

		// create resources
		$this->res["lib"] = new Library($this->seafcl);
		$this->res["dir"] = new Directory($this->seafcl);
		$this->res["file"] = new File($this->seafcl);
        $this->res["multi"] = new Multi($this->seafcl);

		return $this->seafcl;
	}

    /**
     * Get the API token from the server.
     *
     * @param string $user     Username/Email
     * @param string $password Password
     * @return bool|string
     */
	public function getAPIToken($user, $password) {
		$logger = new Logger('Logger');

		$stack = HandlerStack::create();
		$stack->push(
			Middleware::log(
				$logger,
				new MessageFormatter("{hostname} {req_header_Authorization} - {req_header_User-Agent} - [{date_common_log}] \"{method} {host}{target} HTTP/{version}\" {code} {res_header_Content-Length} req_body: {req_body} response_body: {res_body}")
			)
		);

		$client = new \GuzzleHttp\Client(['debug' => false, 'handler' => $stack]);

		$tokenaddress = rtrim($this->server, "/") . "/api2/auth-token/";
		$authstring = "username=" . urlencode($user) . "&password=" . urlencode($password);

		error_log("Sending auth request: " . $tokenaddress);

		$response = false;
		try {
			$response = $client->request('POST', $tokenaddress, array(
					'body' => $authstring,
					'headers' => array(
						'Content-Type' => 'application/x-www-form-urlencoded'
					)
				)
			);
		} catch (ClientException $e) {
			$response =$e->getResponse();
			error_log(print_r($response, true));
			error_log(print_r($response->getBody(), true));
			error_log("C E: " . self::parseErrorCode($response->getStatusCode()));
		} catch (ServerException $e) {
			$response =$e->getResponse();
			error_log(print_r($response, true));
			error_log(print_r($response->getBody(), true));
			error_log("S E: " . self::parseErrorCode($response->getStatusCode()));
		} catch (BadResponseException $e) {
			$response =$e->getResponse();
			error_log(print_r($response, true));
			error_log(print_r($response->getBody(), true));
			error_log("BR E: " . self::parseErrorCode($response->getStatusCode()));
		} catch (\Exception $e) {
			error_log($e->getMessage());
		}

		if(!$response || $response->getStatusCode() != 200) {
			return false;
		}

		$token = json_decode($response->getBody());
		$this->token = (string) $token->token;

		return $this->token;
	}

    /**
     * Refresh the library cache.
     */
	public function refreshLibraries() {
		$libs = $this->res["lib"]->getAll();

		foreach ($libs as $lib) {
			$this->libraries[$lib->id] = $lib;
		}
	}

    /**
     * List contents of a diretory.
     *
     * Returned fields:
     * - displayname (string)
     * - id (string)
     * - resourcetype (string, "collection" | "file")
     * - contentlength (int)
     * - lastmodified (string)
     * - encrypted (bool)
     *
     * @param string $path Directory path
     * @return array Key of the array is the path of the file/folder
     */
	public function ls($path) {
		if ($path !== '/') {
			$path = rtrim($path, '/');
		}

		if ($path == "/") { // we are listing all libraries
			$libs = array();
			try {
				$libs = $this->res["lib"]->getAll();
			} catch (\Exception $e) {
			}

			$data = array();
			foreach ($libs as $lib) {
				$this->libraries[$lib->id] = $lib; // refresh library cache

				$data["/" . $lib->id] = Array(
					'displayname' => $lib->name,
					'id' => $lib->id,
					'resourcetype' => 'collection',
					'contentlength' => $lib->size,
					'lastmodified' => $lib->mtime,
					'encrypted' => $lib->encrypted
				);
			}

			return $data;
		} else { // we arte listing some folders
			$libId = $this->getLibraryIDfromPath($path);
            $dir = $this->stripLibraryFromPath($path);

			$items = $this->res["dir"]->getAll($this->libraries[$libId], $dir);

			$data = array();
			$pathprefix = "/" . $libId . ($dir != "/" ? rtrim($dir, "/") : "") . "/";
			foreach ($items as $item) {
				$data[$pathprefix . $item->name] = Array(
					'displayname' => $item->name,
					'id' => $item->id,
					'resourcetype' => $item->type == "dir" ? "collection" : "file",
					'contentlength' => $item->size,
					'lastmodified' => $item->mtime,
					'encrypted' => false
				);
			}

			return $data;
		}
	}

    /**
     * Create a new directory.
     *
     * @param string $path    Destination folder
     * @param string $dirname Directory name
     * @return mixed
     */
	public function mkdir($path, $dirname) {
		if($path === "/") {
			return $this->res["lib"]->create($dirname);
		} else {
			return $this->res["dir"]->create($this->getLibraryFromPath($path), $dirname, $this->stripLibraryFromPath($path), false);
		}
	}

    /**
     * Rename a directory.
     *
     * @param string $path       Directory path
     * @param string $newDirname New directory name
     * @return bool
     */
    public function rendir($path, $newDirname) {
        if($path === "/") {
            return false;
        } else {
            return $this->res["dir"]->rename($this->getLibraryFromPath($path), $this->stripLibraryFromPath($path), $newDirname);
        }
    }

    /**
     * Rename a file.
     *
     * @param string $path        File path
     * @param string $newFilename New file name
     * @return bool
     */
    public function renfile($path, $newFilename) {
        if($path === "/") {
            return false;
        } else {
            return $this->res["file"]->rename($this->getLibraryFromPath($path), $this->stripLibraryFromPath($path), $newFilename);
        }
    }

    /**
     * Remove a file or directory.
     *
     * @param string $path Path of file/directory
     * @return bool
     */
	public function rm($path) {
		if($path === "/") {
			return false;
		} else if($this->stripLibraryFromPath($path) === "/") { // we only have a library
			return $this->res["lib"]->remove($this->getLibraryIDfromPath($path));
		} else { // delete a file or directory
			return $this->res["multi"]->delete($this->getLibraryFromPath($path), [$this->stripLibraryFromPath($path)]);
		}
	}

    /**
     * Move a file or directory.
     *
     * @param string|array $srcPaths Path of files/directories
     * @param string       $dstPath  Destination path
     * @return mixed
     */
    public function move($srcPaths, $dstPath) {
        if(!is_array($srcPaths)) {
            $srcPaths = [$srcPaths];
        }

        $sourceLib = $this->getLibraryFromPath($srcPaths[0]);

        // remove library id's from paths
        for($i = 0; $i < count($srcPaths); $i++) {
            $srcPaths[$i] = $this->stripLibraryFromPath($srcPaths[$i]);
        }

        return $this->res["multi"]->move($sourceLib, $srcPaths, $this->getLibraryFromPath($dstPath), $this->stripLibraryFromPath($dstPath));
    }

    /**
     * Copy a file or directory.
     *
     * @param string|array $srcPaths Path of files/directories
     * @param string       $dstPath  Destination path
     * @return mixed
     */
    public function copy($srcPaths, $dstPath) {
        if(!is_array($srcPaths)) {
            $srcPaths = [$srcPaths];
        }

        $sourceLib = $this->getLibraryFromPath($srcPaths[0]);

        // remove library id's from paths
        for($i = 0; $i < count($srcPaths); $i++) {
            $srcPaths[$i] = $this->stripLibraryFromPath($srcPaths[$i]);
        }

        return $this->res["multi"]->copy($sourceLib, $srcPaths, $this->getLibraryFromPath($dstPath), $this->stripLibraryFromPath($dstPath));

    }

    /**
     * Upload a file.
     *
     * @param string      $path          Destination directory path
     * @param string      $localFilePath Local file path
     * @param bool|string $dstFilename   Destination file name, or false to use the filename of $localFilePath
     * @return mixed
     */
	public function upload($path, $localFilePath, $dstFilename = false) {
        return $this->res["file"]->upload($this->getLibraryFromPath($path), $localFilePath, $this->stripLibraryFromPath($path), $dstFilename);
	}

    /**
     * Download a file.
     *
     * @param string $path          Remote file path
     * @param string $localFilePath Local file path, the file will be stored here
     * @return mixed
     */
	public function download($path, $localFilePath) {
        return $this->res["file"]->download($this->getLibraryFromPath($path), $this->stripLibraryFromPath($path), $localFilePath);
	}

    /**
     * Parse error code to a meaningful message.
     *
     * @param int $code
     * @return string
     */
	private function parseErrorCode($code) {
		$msg = $code;
		switch($code) {
			case 405: $msg = "Method not allowed. Are you using HTTPS?"; break;
			case 403: $msg = "Forbidden."; break;
			case 429: $msg = "Too many requests."; break;
			case 500: $msg = "Internal Server error."; break;
			case 400: $msg = "Bad reqest."; break;
		}

		return $msg;
	}

    /**
     * Get the library id for the given $path.
     *
     * @param string $path File or folder path
     * @return bool|string
     */
	private function getLibraryIDfromPath($path) {
		if(!$path || $path === "/") {
			return false;
		}

		$path = trim($path, "/");
        if(strpos($path, "/") === false) {
            $libID = $path; // only the library in path
        } else {
            $libID = substr($path, 0, strpos($path, "/")); // library with subfolder(s)
        }

		return $libID;
	}

    /**
     * Get the library object for the given $path.
     *
     * @param string $path File or folder path
     * @return bool|Library
     */
	private function getLibraryFromPath($path) {
		$id = $this->getLibraryIDfromPath($path);

		if(!$id) {
			return false;
		}

		return $this->libraries[$id];
	}

    /**
     * Get only the file/folder path without library id.
     *
     * @param string $path File or folder path
     * @return bool|string
     */
	private function stripLibraryFromPath($path) {
		if(!$path || $path === "/") {
			return false;
		}

		$path = ltrim($path, "/");
		if(strpos($path, "/") !== false) {
			return substr($path, strpos($path, "/"));
		}

		return "/";
	}
}
